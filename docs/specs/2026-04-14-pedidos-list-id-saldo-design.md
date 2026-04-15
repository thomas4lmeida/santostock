# Pedidos List — Add ID and Saldo Columns

## Context

The Orders (Pedidos) index table currently shows four columns: **Fornecedor, Produto, Quantidade, Status**. Users have no way to see an order's internal ID from the list, and — more importantly — no way to see each order's remaining-to-receive balance (**Saldo**) without opening the Show page one-by-one.

Saldo is already computed on the Show page (`OrderController@show:69`) as `ordered_quantity - sum(receipts.quantity)`. Surfacing it on the index lets operators scan the list and immediately spot partially-fulfilled orders.

## Goals

1. Add an **ID** column (the internal numeric `orders.id`) as the **first** column of the list.
2. Add a **Saldo** column between **Quantidade** and **Status**, showing `ordered_quantity - sum(receipts.quantity)`.
3. No N+1: compute saldo with a single aggregate join on the paginated query.
4. Reuse the new accessor on the Show page to remove the inline inline computation.

## Design

### Data layer

**`app/Models/Order.php`** — add a `saldo` accessor:

```php
protected function saldo(): Attribute
{
    return Attribute::make(
        get: fn () => $this->ordered_quantity - (int) ($this->received_quantity ?? $this->receipts()->sum('quantity')),
    );
}
```

- When the controller eager-sums via `withSum`, the accessor uses the pre-loaded `received_quantity` attribute (zero queries).
- When called on a plain model instance (e.g., unit tests), it falls back to the relation sum. Safe, but slower — intentional.

Cast `received_quantity` → `int` on access so JSON serialization is numeric, not string.

### Controller (index)

**`app/Http/Controllers/Orders/OrderController.php:18-43`** — extend the index query:

```php
$orders = Order::query()
    ->with(['supplier:id,name', 'product:id,name'])
    ->withSum('receipts as received_quantity', 'quantity')
    ->orderByDesc('id')
    ->paginate(50)
    ->withQueryString();
```

Append `saldo` alongside `status_label` in the existing `through()` map so Inertia ships it as a plain prop.

### Controller (show) — small cleanup

**`OrderController@show:69`** — replace `$saldo = $order->ordered_quantity - $order->receipts->sum('quantity');` with `$saldo = $order->saldo;`. Removes the duplicate computation now that the accessor exists.

### Frontend

**`resources/js/pages/Orders/Index.vue:97-101`** — reorder and extend the `<thead>` / `<tbody>`:

Final columns: **ID · Fornecedor · Produto · Quantidade · Saldo · Status · [ações]**

- **ID**: small, monospace or `tabular-nums`, muted (`text-gray-500`). Renders `order.id`.
- **Saldo**: right-aligned, `tabular-nums`. When `order.saldo === 0`, render in `text-gray-400` to visually de-emphasize fully-received orders. Otherwise inherit default text.

No empty-state changes; the "no orders" row keeps the same copy, only the `colspan` grows from 5 to 7.

## Tests

**`tests/Feature/Orders/OrderIndexTest.php`** (extend or add if missing):

1. Existing happy-path test — extend to assert each paginated row has `id`, `received_quantity`, and `saldo` keys.
2. **New:** factory-create an order with `ordered_quantity = 10`, add two receipts of 3 and 4 → request `/orders` → assert `saldo === 3` on that row.
3. **New:** order with zero receipts → `saldo === ordered_quantity`.

**`tests/Feature/Orders/OrderShowTest.php`** — no assertion changes; the page still exposes `saldo`. If an existing test mocks the inline computation, update to match the accessor path.

No Vue component tests in this project; UI changes are covered by the existing smoke test if any, otherwise manually verified.

## Verification

1. `docker compose exec -T laravel.test php artisan test --compact --filter=Order` — all pass.
2. `vendor/bin/pint --dirty --format agent`.
3. Load `/pedidos` (or `/orders`) in Sail; confirm seven columns render in the order above. Create a partial receipt on an order, refresh, verify Saldo drops. Receive fully, verify Saldo renders as `0` in muted text.
4. Open network tab — page load should remain a single SQL aggregate (`withSum`), not N+1. Use `php artisan pail` or Debugbar if installed to confirm.

## Critical Files

| File | Change |
|------|--------|
| `app/Models/Order.php` | add `saldo` accessor |
| `app/Http/Controllers/Orders/OrderController.php` | `withSum` on index; expose `saldo`; use accessor on show |
| `resources/js/pages/Orders/Index.vue` | add ID + Saldo columns |
| `tests/Feature/Orders/OrderIndexTest.php` | new saldo assertions |

## Out of Scope

- Sorting/filtering by saldo.
- A human-readable order code (e.g., `PED-00042`) — user picked raw numeric `orders.id`.
- Caching saldo — `withSum` is cheap enough at 50 rows/page.
