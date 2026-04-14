# Plan: Phase 4 — Orders CRUD + Status Machine

> Source: `docs/plans/2026-04-13-05-plan-pivot-inventory-warehouse.md` (Phase 4)
> PRD: `docs/plans/2026-04-13-04-prd-pivot-inventory-warehouse.md`
> User stories: 6, 7, 8, 12

## Context

Phases 1–3 of the inventory/warehouse pivot are merged: roles are renamed, event-rental code is gone, and admin-only catalog CRUD (Fornecedores, Categorias, Unidades, Produtos, Armazéns, Equipes) is live. Phase 4 introduces **Pedidos** — a purchase order placed with a supplier for a single product at a given quantity — plus the `OrderStatus` state machine that every subsequent movement phase (Recebimento, Transferência, Devolução) will plug into.

This is an admin-only CRUD slice plus the first enum-backed state machine since the retired `ItemCondition`. No stock is affected in this phase: orders only declare intent. Receipts will decrement `saldo` in Phase 5, so this phase also provisions an empty `receipts` table so the "quantity-edit-blocked-when-receipts-exist" validation can be wired up once and not revisited. Operador gets a read-only list so they can see what's incoming.

## Architectural decisions (Phase 4 scope)

- **Routes**: `/pedidos` via `Route::resource('pedidos', OrderController::class)->parameters(['pedidos' => 'order'])->names('orders')`, nested inside the existing `role:administrador` group in `routes/web.php`. Operador read access (`index`, `show`) is carved out separately — see Authorization below.
- **Schema — `orders`**: `id`, `supplier_id` (FK → `suppliers`, restrict on delete), `product_id` (FK → `products`, restrict on delete), `ordered_quantity` (unsigned int, DB check `> 0`), `status` (string, default `'open'`), `notes` (text, nullable), `created_by_user_id` (FK → `users`, nullable on user delete), timestamps. One product per order — no line items table.
- **Schema — `receipts` (empty stub)**: `id`, `order_id` (FK → `orders`, cascade), `quantity` (unsigned int), timestamps. No controller, no writes in this phase. Existence alone enables the FK-count validation on order update.
- **Enum — `OrderStatus`**: mirrors the retired `ItemCondition` shape (`cases` + `label()` + `nextAllowed(): array` + `canTransitionTo(self $next): bool`). Cases: `Open`, `PartiallyReceived`, `FullyReceived`, `Cancelled`, `ClosedShort` (string values: `open`, `partially_received`, `fully_received`, `cancelled`, `closed_short` — lowercase snake_case to match existing pt-BR enum convention).
- **Transitions**:
  - `Open → PartiallyReceived` and `Open → FullyReceived` (receipts will drive these in Phase 5)
  - `PartiallyReceived → FullyReceived`
  - `PartiallyReceived → ClosedShort`
  - `{Open, PartiallyReceived} → Cancelled` (blocked in Phase 4 via guard: if `receipts()->exists()`, 422 with `"Não é possível cancelar: existem recebimentos registrados. Estorne-os ou encerre como saldo curto."`)
  - Terminal: `FullyReceived`, `Cancelled`, `ClosedShort`
- **Authorization**: write endpoints (`create`, `store`, `edit`, `update`, `destroy`, `cancel`, `close-short`) require `administrador` via `SaveOrderRequest extends AdministradorRequest`. Read endpoints (`index`, `show`) require any authenticated user — carve out with a second `Route::middleware(['auth', 'verified'])` block that registers only `index` and `show`, above the admin group, so operador sees a read-only list.
- **Pagination**: `Order::query()->with(['supplier:id,name', 'product:id,name'])->orderByDesc('created_at')->paginate(50)->withQueryString()`. Filters (status, supplier_id) applied before pagination via `when()`.
- **Quantity-edit lock**: enforced in `SaveOrderRequest::rules()` — on update, if the order has any `receipts`, strip `ordered_quantity` from input or reject with a `Rule::prohibitedIf`. Mirror with a DB-level CHECK via a trigger-free approach: keep the check in the FormRequest for Phase 4; Phase 5 will add the transactional lock when receipts land.
- **State transitions as explicit actions**: `Cancelar` and `Encerrar como saldo curto` are dedicated POST routes (`POST /pedidos/{order}/cancelar`, `POST /pedidos/{order}/encerrar-saldo-curto`), not a generic status field on the edit form — protects the state machine from accidental form tampering.

## Commit sequence

The phase ships as 6 focused commits on a feature branch `feat/phase-4-orders-crud`. Each commit keeps `php artisan test --compact` and `npm run build` green.

### Commit 1 — `feat(orders): add OrderStatus enum with state machine`

- Create `app/Enums/OrderStatus.php` mirroring the `ItemCondition` shape (recovered from git `5c14e27`).
- Labels in pt-BR: `Open → "Aberto"`, `PartiallyReceived → "Recebido parcialmente"`, `FullyReceived → "Recebido integralmente"`, `Cancelled → "Cancelado"`, `ClosedShort → "Encerrado com saldo curto"`.
- `nextAllowed()` returns the transition list above.
- Add `tests/Feature/Orders/OrderStatusTest.php` (Pest) covering: every valid transition returns `true`, every invalid transition returns `false`, terminal states have empty `nextAllowed()`, `label()` returns the expected pt-BR string.

### Commit 2 — `feat(orders): add orders and receipts migrations + models`

- `php artisan make:migration create_orders_table` and `create_receipts_table` with the schema above.
- `php artisan make:model Order` and `Receipt` (no `-mfsc`; seeder/factory come in commit 3).
- `Order` model: `$casts = ['status' => OrderStatus::class]`, `$fillable = ['supplier_id', 'product_id', 'ordered_quantity', 'status', 'notes', 'created_by_user_id']`. Relations: `supplier()`, `product()`, `createdBy()`, `receipts()`.
- `Receipt` model: minimal — just `order()` relation and `$fillable = ['order_id', 'quantity']`. No routes, no controller, no requests in this phase.
- Add a boot hook on `Order`: guard `deleting` to block hard delete when receipts exist (redundant with FK restrict but produces a friendly 422 instead of a FK violation — mirrors the unit/category pattern from Phase 3).

### Commit 3 — `feat(orders): add OrderFactory and update DatabaseSeeder`

- `OrderFactory` states: `open()` (default), `partiallyReceived()`, `fullyReceived()`, `cancelled()`, `closedShort()`. Each produces a realistic `ordered_quantity` (5–100) and picks a random existing supplier + product via `Supplier::inRandomOrder()->first()` fallback.
- `ReceiptFactory` minimal — used only by tests for the "has receipts" path.
- Do **not** seed orders in `DatabaseSeeder` (PRD says demo seed ships no orders — they grow per phase via factories).

### Commit 4 — `feat(orders): add OrderController and SaveOrderRequest with route wiring`

- `php artisan make:controller Orders/OrderController --resource --model=Order --requests`. Wayfinder will regenerate on `npm run build`.
- `app/Http/Requests/Orders/SaveOrderRequest.php` extends `AdministradorRequest`. Rules:
  - `supplier_id`: required, exists
  - `product_id`: required, exists
  - `ordered_quantity`: required, integer, min:1 (**on update**: `Rule::prohibitedIf($this->route('order')->receipts()->exists())` with a custom message naming the existing receipts)
  - `notes`: nullable, string, max:2000
- Controller methods:
  - `index(Request $request)` — apply `status` and `supplier_id` filters via `when()`, eager-load, paginate 50, render `Orders/Index` with `filters` prop reflecting current query state.
  - `create()` — render `Orders/Create` with `suppliers` and `products` lists (id + name only).
  - `store(SaveOrderRequest $request)` — create with `status = Open` and `created_by_user_id = auth()->id()`, redirect to `orders.index`.
  - `show(Order $order)` — eager-load supplier, product, receipts, render `Orders/Show`.
  - `edit(Order $order)` — same lists as `create`; pass a `canEditQuantity` boolean (`! $order->receipts()->exists()`) so the Vue form can disable the field.
  - `update(SaveOrderRequest $request, Order $order)` — update, redirect to `orders.index`.
  - `destroy(Order $order)` — block if `$order->status !== OrderStatus::Open` OR if receipts exist; otherwise delete. 422 with a clear message on block.
- Two extra invokable controllers in `app/Http/Controllers/Orders/`:
  - `CancelOrderController` (`POST /pedidos/{order}/cancelar`): validates `$order->status->canTransitionTo(OrderStatus::Cancelled)` AND `! $order->receipts()->exists()`; updates status; redirects back with flash.
  - `CloseShortOrderController` (`POST /pedidos/{order}/encerrar-saldo-curto`): validates `$order->status->canTransitionTo(OrderStatus::ClosedShort)`; updates status; redirects back with flash.
  Both extend `AdministradorRequest`-style authorization inline (or via a shared `AdministradorRequest` FormRequest with empty rules).
- `routes/web.php` additions:
  - Read-only (any authenticated + verified + active user): `Route::resource('pedidos', OrderController::class)->only(['index', 'show'])->parameters(['pedidos' => 'order'])->names('orders');`
  - Admin writes inside the existing `role:administrador` group: `Route::resource('pedidos', OrderController::class)->except(['index', 'show'])->parameters(['pedidos' => 'order'])->names('orders');` plus the two named transition POSTs.
- Run `npm run build` so `@/actions/App/Http/Controllers/Orders/OrderController` and friends are generated and committed.

### Commit 5 — `feat(orders): add Inertia Vue pages for orders CRUD`

Files under `resources/js/pages/Orders/`:
- `OrderForm.vue` — shared component, props `{ initial?: Order, suppliers: Option[], products: Option[], canEditQuantity: boolean, submitUrl: string, method: 'post' | 'put', submitLabel: string }`. Use `useForm` from `@inertiajs/vue3`. Quantity input disabled + helper text when `canEditQuantity === false`.
- `Index.vue` — paginated table with columns: Fornecedor, Produto, Quantidade, Status (badge colored by enum), Criado em, Ações. Status filter (select of all cases) + supplier filter (select). Action column: "Ver" (always), "Editar" / "Excluir" (admin only — gate off `page.props.auth.user.role`). Row click navigates to show.
- `Create.vue`, `Edit.vue` — thin wrappers over `OrderForm.vue`. Breadcrumbs via Wayfinder `OrderController.index.url()` etc.
- `Show.vue` — order detail with status badge, supplier + product names, ordered_quantity, saldo placeholder (computed as `ordered_quantity - receipts.sum(quantity)`, will be 0 here), notes, "Cancelar" and "Encerrar como saldo curto" action buttons gated by `canTransitionTo` server-sent booleans (`canCancel`, `canCloseShort`) to keep transition rules server-authoritative. Each button POSTs via `router.post(CancelOrderController.store.url({ order: id }))` with a `confirm()`.
- `AppSidebar.vue`: add `{ title: 'Pedidos', href: pedidosIndex().url, icon: ClipboardList }` — unconditional (operador also sees the read-only list). Import from the Wayfinder-generated `@/routes/pedidos/index.ts`.

### Commit 6 — `test(orders): full feature-test coverage for orders phase`

Create under `tests/Feature/Orders/`:
- `OrderCrudTest.php` — admin create (happy), admin update (happy), admin delete of Open order with no receipts (happy), operador forbidden on `store`/`update`/`destroy`, operador CAN hit `index` and `show`, guest redirected to login.
- `OrderValidationTest.php` — missing supplier, missing product, non-existent supplier/product, `ordered_quantity` = 0 / negative / missing, notes over 2000 chars.
- `OrderQuantityEditLockTest.php` — given an order with a receipt, PUT to update with a new `ordered_quantity` is rejected with 422; PUT with only notes/supplier/product succeeds.
- `OrderStateMachineTest.php` — cancel on Open succeeds; cancel on FullyReceived/Cancelled/ClosedShort returns 422; cancel on PartiallyReceived with no receipts succeeds; cancel on PartiallyReceived **with** a receipt returns 422 with the expected message; close-short on PartiallyReceived succeeds; close-short on Open returns 422.
- `OrderListFilterTest.php` — filter by status returns only that status; filter by supplier returns only that supplier; combined filters AND together; `withQueryString` preserves filters in pagination links.

All tests use `RefreshDatabase`, seed `RoleSeeder` in `beforeEach`, and create users via the existing `User::factory()->withTwoFactor()->create()->assignRole(...)` pattern documented in `tests/Feature/Suppliers/*`.

## Critical files to modify / create

- `app/Enums/OrderStatus.php` (new)
- `app/Models/Order.php`, `app/Models/Receipt.php` (new)
- `database/migrations/*_create_orders_table.php`, `*_create_receipts_table.php` (new)
- `database/factories/OrderFactory.php`, `database/factories/ReceiptFactory.php` (new)
- `app/Http/Controllers/Orders/OrderController.php` (new)
- `app/Http/Controllers/Orders/CancelOrderController.php` (new, invokable)
- `app/Http/Controllers/Orders/CloseShortOrderController.php` (new, invokable)
- `app/Http/Requests/Orders/SaveOrderRequest.php` (new, extends `AdministradorRequest`)
- `routes/web.php` (modify: add read-only `Route::resource` outside admin group + admin writes inside + two transition POSTs)
- `resources/js/pages/Orders/{Index,Create,Edit,Show,OrderForm}.vue` (new)
- `resources/js/components/AppSidebar.vue` (modify: add Pedidos link for all authenticated users)
- `tests/Feature/Orders/{OrderStatusTest,OrderCrudTest,OrderValidationTest,OrderQuantityEditLockTest,OrderStateMachineTest,OrderListFilterTest}.php` (new)

## Patterns to reuse (verbatim)

- `AdministradorRequest` (`app/Http/Requests/AdministradorRequest.php`) — extend for `SaveOrderRequest`; no override of `authorize()` needed.
- Suppliers CRUD shape (`app/Http/Controllers/Suppliers/SupplierController.php` + `resources/js/pages/Suppliers/*` + `SupplierForm.vue`) — the closest mirror; treat it as the template.
- Pagination idiom: `Model::query()->…->paginate(50)->withQueryString()` with `Paginated<T>` type on the Vue side (`@/types/pagination`).
- Delete-on-Edit pattern with `router.delete(...)` + `confirm()` (from `resources/js/pages/Suppliers/Edit.vue`).
- Sidebar gating idiom (`resources/js/components/AppSidebar.vue` — the existing `role === 'administrador'` block).
- Wayfinder imports: `import * as OrderController from '@/actions/App/Http/Controllers/Orders/OrderController'` in pages; `pedidosIndex` from `@/routes/pedidos/index.ts` in the sidebar.

## Verification

Per-commit and end-of-phase:

- `php artisan test --compact` — green (expect ~20–25 new tests across the 6 new test files).
- `vendor/bin/pint --dirty --format agent` — no diffs.
- `npm run build` — succeeds; Wayfinder-generated files under `resources/js/actions/App/Http/Controllers/Orders/` and `resources/js/routes/pedidos/` are committed.
- Manual smoke via `php artisan serve`:
  1. Log in as admin → sidebar shows "Pedidos" → open `/pedidos` → list empty → "Novo Pedido" → fill supplier + product + quantity + notes → save → redirected to list showing status "Aberto".
  2. Edit the order → change quantity → save → list reflects change.
  3. Click "Cancelar" on the order → confirm dialog → order status flips to "Cancelado"; buttons disappear.
  4. Create a second order, use tinker to insert a `Receipt` row against it, then try to edit quantity → form field is disabled; POSTing raw data to the update endpoint returns 422.
  5. Try to cancel the order-with-receipt → 422 with the saldo-curto hint message.
  6. Log in as operador → sidebar still shows "Pedidos" → `/pedidos` loads read-only → "Editar" / "Excluir" / "Cancelar" / "Encerrar" controls are absent → direct POST to `/pedidos` returns 403.
  7. Log out → `/pedidos` redirects to `/login`.
- State machine spot-check via tinker: `OrderStatus::Open->canTransitionTo(OrderStatus::ClosedShort)` → `false`; `OrderStatus::PartiallyReceived->canTransitionTo(OrderStatus::ClosedShort)` → `true`.

## Out of scope (deferred to Phase 5+)

- Any actual receipt writes, `stock_lots`, `stock_movements`, photos, or idempotency keys.
- Auto-transitioning order status from receipts (Phase 5 wires this to `OrderStatus::canTransitionTo()`).
- Reversal UI (`reverses_movement_id`) — Phase 5.
- Order-level authorization beyond role (no team scoping yet — PRD defers team scoping indefinitely).
