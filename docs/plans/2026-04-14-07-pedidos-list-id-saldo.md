# Pedidos List — Add ID and Saldo Columns (Implementation Plan)

## Context

The Orders (Pedidos) index shows 4 columns today: Fornecedor, Produto, Quantidade, Status. Users can't see the internal order ID or the remaining-to-receive balance (**Saldo**) without opening each order. Saldo is already computed on the Show page (`OrderController@show:69`) but not exposed in the list.

This plan adds an **ID** column (first position) and a **Saldo** column (between Quantidade and Status), computed via a single SQL aggregate to avoid N+1.

Spec: `docs/specs/2026-04-14-pedidos-list-id-saldo-design.md`.

## Changes

### 1. `app/Models/Order.php` — add `saldo` accessor

Add `use Illuminate\Database\Eloquent\Casts\Attribute;` and:

```php
protected function saldo(): Attribute
{
    return Attribute::make(
        get: fn () => $this->ordered_quantity - (int) ($this->received_quantity ?? $this->receipts()->sum('quantity')),
    );
}
```

- Uses pre-loaded `received_quantity` (from `withSum`) when present — zero queries.
- Falls back to a relation sum for lazy callers (tests, show page).

Also append `'saldo'` to a new `$appends` array so it serializes into the Inertia payload by default on any loaded Order.

### 2. `app/Http/Controllers/Orders/OrderController.php`

**`index` (lines 18–43):** add `->withSum('receipts as received_quantity', 'quantity')` to the query chain (after `with(...)`). No other changes — the `saldo` accessor in `$appends` handles serialization.

**`show` (line 69):** replace `$saldo = $order->ordered_quantity - $order->receipts->sum('quantity');` with `$saldo = $order->saldo;`. The `receipts` relation is already loaded, so the accessor falls back cleanly.

### 3. `resources/js/pages/Orders/Index.vue`

- Extend `OrderRow` interface: add `saldo: number` and `received_quantity: number | null`.
- Reorder `<thead>` to: **ID · Fornecedor · Produto · Quantidade · Saldo · Status · (actions)**.
- Add corresponding `<td>` cells in the same order:
  - **ID**: `#{{ order.id }}`, `text-muted-foreground tabular-nums`.
  - **Saldo**: `{{ order.saldo }}`, right-aligned `tabular-nums`; when `order.saldo === 0` apply `text-muted-foreground`.
- Bump the empty-state `colspan` from `5` to `7`.

### 4. `tests/Feature/Orders/OrderIndexTest.php` — extend

Add one test after the existing ones:

```php
test('index exposes saldo computed from received receipts', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $order = Order::factory()->create(['ordered_quantity' => 10]);
    // create two receipts summing to 7 via factory (receipts(3) + receipts(4))
    // leaving saldo = 3

    $this->actingAs($admin)->get('/pedidos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.id', $order->id)
            ->where('orders.data.0.saldo', 3)
            ->where('orders.data.0.received_quantity', 7)
        );
});
```

Check `Receipt::factory()` signature before writing — it may require `warehouse_id`, `user_id`, `idempotency_key`. Build two receipts belonging to `$order` with quantities `3` and `4`.

## Critical Files

| File | Change |
|------|--------|
| `app/Models/Order.php` | `saldo` accessor + `$appends` |
| `app/Http/Controllers/Orders/OrderController.php` | `withSum` on index; `$order->saldo` on show |
| `resources/js/pages/Orders/Index.vue` | ID + Saldo columns, reorder, colspan |
| `tests/Feature/Orders/OrderIndexTest.php` | new saldo test |

## Verification

1. **Test suite:** `docker compose exec -T laravel.test php artisan test --compact --filter=Order` — all pass (existing 4 + new 1).
2. **Full suite:** `docker compose exec -T laravel.test php artisan test --compact` — 218 tests pass.
3. **Formatting:** `vendor/bin/pint --dirty --format agent`.
4. **Manual smoke:** load `/pedidos` in the browser. Confirm 7 columns in the order above. Confirm a partially-received order shows the correct Saldo; a fully-received order shows `0` in muted text.
5. **N+1 check:** with 3+ orders and receipts, verify only one SQL aggregate runs (Debugbar or `DB::listen` log).

## Out of Scope

- Sorting/filtering by saldo.
- Human-readable order code — user picked raw `orders.id`.
- Frontend unit tests (none in project).
