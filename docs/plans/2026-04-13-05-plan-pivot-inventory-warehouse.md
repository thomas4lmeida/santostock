# Plan: Santostok — Inventory & Warehouse Management (Pivot)

> Source PRD: `docs/plans/2026-04-13-04-prd-pivot-inventory-warehouse.md` (to be promoted to `docs/PRD.md` at Phase 1 start).

## Context

The project is pivoting away from the event-rental domain (events, event_items, tasks, clients, notifications, WhatsApp) to an inventory/warehouse management system: Pedidos → Recebimento → Transferência → Devolução, with photo-evidenced movements tracked per (product × warehouse × lot). This plan decomposes the new PRD into eight vertical tracer-bullet slices, each demoable on its own. Phase 1 is subtractive (removes the old scope and renames roles) so later additive phases build on a minimal, CI-green surface.

## Architectural decisions

Durable across all phases:

- **Language**: pt-BR for UI/labels/enum values; English for code identifiers.
- **Roles**: two roles only — `administrador`, `operador` (lowercase Portuguese enum values). `Client` removed.
- **Routes**: resource routes per module under `/` — `/fornecedores`, `/categorias`, `/unidades`, `/produtos`, `/armazens`, `/equipes`, `/pedidos`, `/recebimentos`, `/transferencias`, `/devolucoes`, `/estoque`. Named routes consumed from the frontend via Wayfinder (`@/actions/*`, `@/routes/*`) — no hardcoded URLs.
- **Schema shape**:
  - `users`, `teams`, `team_user` (pivot).
  - `suppliers`, `item_categories`, `units`, `products(item_category_id, unit_id)`, `warehouses(name)`.
  - `orders(supplier_id, product_id, ordered_quantity, status)`.
  - `receipts(order_id, warehouse_id, quantity, received_at, user_id)`.
  - `stock_lots(product_id, warehouse_id, receipt_id)`, `stock_movements(stock_lot_id, warehouse_id, user_id, type, quantity)`.
  - `transfers(source_warehouse_id, destination_warehouse_id, product_id, lot_id?, quantity, user_id)`.
  - `returns(supplier_id, warehouse_id, product_id, lot_id?, quantity, user_id)`.
  - Polymorphic `attachments(attachable_type, attachable_id, path, ...)`.
  - All quantities are positive integers; no money columns.
- **Order status machine**: `Open → PartiallyReceived → FullyReceived`; `{Open, PartiallyReceived} → Cancelled`; `PartiallyReceived → ClosedShort`. Implemented as an enum with `label()` + `nextAllowed()` + `canTransitionTo()` (same pattern as existing `ItemCondition`).
- **Authorization**: FormRequest-based. Abstract `AdministradorRequest` (replaces `CoordinatorRequest`) for admin-only writes. Operador may create receipts/transfers/returns and browse.
- **Transactions**: every stock-affecting write (receipt, transfer, return) wrapped in `DB::transaction`.
- **Attachments**: public disk, image validation, min-one enforced on receipt/transfer/return.
- **Testing**: Pest feature tests with `RefreshDatabase`. Reuse the structure of `EventItemCrudTest.php` (beforeEach + `RoleSeeder` + explicit 403 checks + happy path + edge cases) as a template — then delete that file in Phase 1.

## Critical files to touch / reference

- `app/Enums/ItemCondition.php` — reference pattern for state-machine enums.
- `app/Http/Requests/CoordinatorRequest.php` — renamed to `AdministradorRequest` in Phase 1.
- `database/seeders/RoleSeeder.php`, `app/Models/User.php` — role rename + `Client` removal in Phase 1.
- `app/Http/Controllers/EventController.php`, `app/Models/Event*.php`, `database/migrations/*event*`, `resources/js/pages/Events/*`, `tests/Feature/Event*` — deleted in Phase 1.
- `routes/web.php`, `resources/js/components/AppSidebar.vue` — rewritten incrementally per phase.
- `vite.config.ts` + Wayfinder plugin — already wired; regenerate `@/actions` after each backend change.

---

## Phase 1: Pivot cleanup + roles

**User stories**: 10 (partial — admin manages users), 23, 24.

### What to build

A subtractive slice. Promote the new PRD to `docs/PRD.md`, delete all event-rental code (events, event_items, tasks, clients, related Vue pages, routes, tests, migrations), rename roles (`Coordinator → Administrador`, `Staff → Operador`, drop `Client`) in the `RoleSeeder`, Spatie seed, FormRequest base class, and any references. Keep auth (Fortify + 2FA), `users`, `suppliers`, `item_categories`. End state: app boots, user logs in, sees an empty dashboard + sidebar with Fornecedores and Categorias; `php artisan test` is green.

### Acceptance criteria

- [ ] `docs/PRD.md` contains the inventory/warehouse scope.
- [ ] No event/event_item/task/client code, migrations, routes, Vue pages, or tests remain.
- [ ] `AdministradorRequest` replaces `CoordinatorRequest`; only two roles exist in seeds and enum.
- [ ] Suppliers and ItemCategories CRUD still pass their tests under the new role names.
- [ ] `php artisan test --compact` is fully green; `npm run build` succeeds.

---

## Phase 2: Teams CRUD

**User stories**: 11.

### What to build

Teams as organizational-only grouping. Migration for `teams` (name, description?) and `team_user` pivot. Policy: admin-only. CRUD pages under `/equipes`, attach/detach users from a team's edit view. No data scoping — teams are informational in MVP but the migration shape allows later additive `team_id` scoping on warehouses/orders.

### Acceptance criteria

- [ ] Administrador can create/edit/delete teams and attach/detach users.
- [ ] Operador receives 403 on all team write endpoints.
- [ ] `users ↔ teams` many-to-many works end-to-end via the edit UI.
- [ ] Feature tests cover happy path + forbidden + validation.

---

## Phase 3: Units + Products + Warehouses CRUD

**User stories**: 3, 4, 5.

### What to build

Three admin-only resources. `units` (lookup: name, abbreviation), `products` (name, item_category_id, unit_id), `warehouses` (name). Hard delete with FK-restrict when referenced. Wayfinder-generated TS actions; pt-BR labels inline. No stock/order logic yet — pure catalog.

### Acceptance criteria

- [ ] Admin CRUD works for each resource; operador is 403.
- [ ] Products require an existing category and unit (validation + FK).
- [ ] Deleting a unit/category/warehouse that is referenced returns a friendly error, not a 500.
- [ ] Feature tests cover CRUD + authorization + FK-restrict behavior.

---

## Phase 4: Orders CRUD + status machine

**User stories**: 6, 7, 8, 12.

### What to build

`orders` table and admin-only CRUD under `/pedidos`. `OrderStatus` enum with `nextAllowed()` + `canTransitionTo()` mirroring `ItemCondition`. Creation sets `Open`. Edit of `ordered_quantity` allowed only when no receipts exist (enforced in FormRequest + DB check). Explicit actions: *cancel* (from Open/PartiallyReceived) and *close short* (from PartiallyReceived) — the latter is a no-op for stock but finalizes the order. List view filters by status + supplier. Saldo is derived (not stored): `ordered_quantity - sum(receipts.quantity)`; in Phase 4 it equals ordered_quantity because receipts don't exist yet.

### Acceptance criteria

- [ ] Admin creates an order with supplier+product+quantity; it starts `Open`.
- [ ] Quantity edit is blocked once receipts exist (covered in Phase 5's tests too).
- [ ] Cancel and ClosedShort transitions obey the state machine; invalid transitions 422.
- [ ] List filters by status enum and supplier work; operador can only browse.
- [ ] Feature tests cover the state machine, the quantity-edit lock, and authorization.

---

## Phase 5: Receipts + stock lots + attachments

**User stories**: 15, 16, 22 (partial).

### What to build

The foundational movement slice. Introduces the polymorphic `attachments` table with at-least-one-photo enforcement, and the `stock_lots` + `stock_movements` tables. Receiving a quantity against an order (a) creates one `stock_lot` keyed to the receipt, (b) writes a `stock_movement` of type `receipt`, (c) attaches ≥1 photo, (d) validates `quantity ≤ saldo`, (e) auto-transitions the order to `PartiallyReceived` or `FullyReceived`. All inside `DB::transaction`. Operador can create receipts; browsing movements by receipt is available.

### Acceptance criteria

- [ ] Receipt creation is atomic: lot + movement + attachments or nothing.
- [ ] Over-receipt (quantity > saldo) is rejected with 422.
- [ ] At least one photo is required; non-image files rejected.
- [ ] Partial receipt flips order to `PartiallyReceived`; final receipt flips to `FullyReceived`.
- [ ] Operador can create receipts; admin retains full access.

---

## Phase 6: Transfers

**User stories**: 17, 18.

### What to build

Instant, atomic warehouse-to-warehouse movement. `transfers` table + create endpoint. One DB transaction writes two `stock_movement` rows (`transfer_out` at source, `transfer_in` at destination). Source ≠ destination enforced. Validates `quantity ≤ lot/warehouse balance`. ≥1 photo required. Reuses the attachment polymorph from Phase 5.

### Acceptance criteria

- [ ] Source = destination returns 422.
- [ ] Insufficient balance returns 422; stock never goes negative.
- [ ] Two movement rows written atomically; rollback on photo failure.
- [ ] Feature tests cover balance math, source/destination rule, photos, auth.

---

## Phase 7: Returns

**User stories**: 19, 20.

### What to build

Free-form devolução (no link to a specific receipt). `returns` table + create endpoint. Decrements stock via a `return` movement row. Validates `quantity ≤ available lot/warehouse balance`. ≥1 photo required. Operador can create; admin too.

### Acceptance criteria

- [ ] Return decrements the correct lot/warehouse balance.
- [ ] Over-return is rejected; stock never goes negative.
- [ ] ≥1 photo required.
- [ ] Feature tests cover happy path + edge cases + authorization.

---

## Phase 8: Stock browsing + movement history

**User stories**: 21, 22.

### What to build

Read-only views. `/estoque` lets any authenticated user browse current stock grouped by product × warehouse (derived from `stock_movements` aggregate or a simple SQL sum query). A movement-history view per product or per warehouse shows all movements with their photos and timestamps. No new writes; purely reporting on data seeded by phases 5–7.

### Acceptance criteria

- [ ] Stock-by-warehouse view matches the sum of `stock_movements` for each (product, warehouse).
- [ ] Movement history lists receipts/transfers/returns chronologically with thumbnails.
- [ ] Both admin and operador can access browsing views.
- [ ] Feature tests verify aggregate math against seeded factories.

---

## Verification (overall)

After all phases are executed, for each phase independently:

- `php artisan test --compact` is green.
- `vendor/bin/pint --dirty --format agent` reports no diffs after run.
- `npm run build` succeeds; Wayfinder-generated actions are committed.
- Manual smoke (`php artisan serve` + browser): admin can walk the full flow — create supplier/category/unit/product/warehouse, place an order, receive with photos (partial then final), transfer a lot, return some quantity, and observe the resulting stock-by-warehouse and movement history.
- Role check: log in as operador and confirm write endpoints for catalog/orders/teams return 403 while receipts/transfers/returns/browsing succeed.
