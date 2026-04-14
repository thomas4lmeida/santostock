# Plan: Santostok — Inventory & Warehouse Management (Pivot)

> Source PRD: `docs/plans/2026-04-13-04-prd-pivot-inventory-warehouse.md` (promoted to `docs/PRD.md` at Phase 1 start).
> Architectural decisions below supersede the PRD where they conflict — the PRD was refined through a grill-me interview session.

## Context

The project is pivoting from the event-rental domain (events, event_items, tasks, clients, notifications, WhatsApp) to an inventory/warehouse management system: Pedidos → Recebimento → Transferência → Devolução, with photo-evidenced movements tracked per (product × warehouse × lot). This plan decomposes the new PRD into eight vertical tracer-bullet slices, each demoable on its own. Phase 1 is subtractive (removes the old scope and renames roles) so later additive phases build on a minimal, CI-green surface. Pre-production: no deployed DB — `migrate:fresh --seed` is the reset command; old migration files are deleted outright rather than unwound via down-migrations.

## Architectural decisions

### Language, roles, routes

- **Language**: pt-BR for UI/labels/enum values; English for code identifiers.
- **Roles**: exactly two — `administrador`, `operador` (lowercase Portuguese enum values). `Client` removed.
- **2FA**: mandatory for `administrador` (enforced via post-login middleware); optional opt-in for `operador`.
- **Routes**: resource routes per module under `/` — `/fornecedores`, `/categorias`, `/unidades`, `/produtos`, `/armazens`, `/equipes`, `/pedidos`, `/recebimentos`, `/transferencias`, `/devolucoes`, `/estoque`. Named routes consumed from the frontend via Wayfinder (`@/actions/*`, `@/routes/*`) — no hardcoded URLs.
- **Pagination**: `paginate(50)->withQueryString()` everywhere (matches existing `SupplierController` / `ItemCategoryController` convention).

### Stock data model

- **Schema shape**:
  - `users`, `teams`, `team_user` (pivot).
  - `suppliers`, `item_categories`, `units`, `products(item_category_id, unit_id)`, `warehouses(name)`.
  - `orders(supplier_id, product_id, ordered_quantity, status)` — **one product per order**.
  - `receipts(order_id, warehouse_id, quantity, user_id, idempotency_key)` — **one warehouse per receipt**.
  - `stock_lots(product_id, warehouse_id, receipt_id nullable, parent_lot_id nullable, current_balance)` — **append-only**. A transfer creates a new lot at the destination with `parent_lot_id` pointing at its source lot; supplier provenance survives across transfers via the chain.
  - `stock_movements(stock_lot_id, warehouse_id, user_id, type, quantity, idempotency_key, reverses_movement_id nullable)` — **immutable ledger**. `type ∈ {receipt, transfer_in, transfer_out, return}`. `quantity` unsigned with DB check `quantity > 0`. Unique indexes on `idempotency_key` (per user) and on `reverses_movement_id` (at most one reversal per original).
  - `transfers(source_warehouse_id, destination_warehouse_id, product_id, quantity, user_id, idempotency_key)` — no `lot_id` on the form; FIFO-drains source lots.
  - `returns(supplier_id, warehouse_id, product_id, quantity, user_id, idempotency_key)` — no `lot_id`; FIFO-drains supplier-matched lots.
  - Polymorphic `attachments(attachable_type, attachable_id, path, thumbnail_path, sha256, mime, size)`.
  - Quantities are positive integers. No money columns.

### Movement semantics (receipt / transfer / return / reversal)

- **Transactional writes**: every stock-affecting write wrapped in `DB::transaction`.
- **Concurrency**: `lockForUpdate` on the target `order` (receipts) or source `stock_lot` (transfers/returns) inside the transaction. Revalidate balance/saldo after the lock before inserting.
- **Idempotency**: every movement-creating POST carries a client-generated UUID `idempotency_key`. Stored on the movement row with a unique index per user. Repeat submissions return the original response (no duplicate insert).
- **Timing**: single `created_at` per movement — no separate `occurred_at`, no backdating.
- **Receipts**:
  - Atomically create one `stock_lot` (1:1 with the receipt), one `stock_movement` of type `receipt`, and ≥1 `attachments`.
  - Validate `quantity ≤ order.saldo` where `saldo = ordered_quantity - sum(receipts.quantity)`.
  - Auto-transition order state via the `OrderStatus` state machine (→ `PartiallyReceived` when saldo > 0, → `FullyReceived` when saldo = 0).
  - Update `stock_lot.current_balance = quantity`.
- **Transfers**:
  - Source ≠ destination (422 otherwise).
  - FIFO across all product lots in source warehouse. Each contributing source lot produces a matching destination `stock_lot` (new row, `parent_lot_id` → source lot) so supplier provenance is preserved downstream.
  - For each source lot touched: write one `transfer_out` movement (decrements source lot balance) and one `transfer_in` movement (sets new lot balance).
  - ≥1 photo attached to the `transfer` row (not to each movement).
- **Returns**:
  - Form = supplier + source warehouse + product + quantity + photos. No lot picker.
  - FIFO across lots in the source warehouse where `lot.receipt.order.supplier_id == return.supplier_id`. Validation: total supplier-matched balance ≥ requested quantity, else 422 with a helpful message (`"Fornecedor X tem apenas N disponíveis neste armazém"`).
  - Each contributing lot gets one `return` movement decrementing its balance. No new lot is created.
- **Reversals (corrections)**:
  - Admin-only. Operadores escalate.
  - Stored as ordinary movements with `reverses_movement_id` FK (unique — one reversal per original). Quantity and target lot are pre-filled and locked from the original; photo still required.
  - Receipt reversal rewinds the parent order via the same state-machine helper (`FullyReceived` → `PartiallyReceived`, or `PartiallyReceived` → `Open`). `stock_lot.current_balance` adjusted within the same transaction.
  - A reversal of a multi-lot transfer/return produces linked reversal movements — one per source lot originally touched — in one transaction.
- **Cancel vs ClosedShort**: `Cancelled` is blocked when receipts exist. Admin must either `ClosedShort` (keep stock, close saldo) or explicitly reverse receipts first. UI shows this as a 422 with actionable copy.

### Stock balance projection

- `stock_lots.current_balance` is maintained per transaction alongside each movement insert (one `UPDATE` inside the already-held lock, no new race).
- `stock_movements` remains the immutable source of truth; balances are reconstructible via `php artisan stock:rebuild-balances`.
- Warehouse-level "how much of product P is in warehouse W?" = `SUM(current_balance) GROUP BY product_id, warehouse_id` over `stock_lots` — cheap indefinitely.

### Attachments (production-grade from day one)

- **Storage**: DigitalOcean Spaces via Laravel's `s3` driver (endpoint override). Signed URLs for delivery; bundled DO CDN for speed.
- **Upload pipeline** (Intervention Image): re-encode to jpg/webp → strip EXIF → generate 400px thumbnail → write both originals and thumbs to Spaces. HEIC input accepted and converted. Non-image bytes fail the re-encode and are rejected (implicit malformed-file defense; no ClamAV in MVP).
- **Limits**: images only, 10 MB per file, 1..10 per movement, ≥1 required on receipts / transfers / returns.
- **Durability**: Spaces object versioning enabled; lifecycle rule expires non-current versions after 90 days. Cross-region sync deferred.
- **No content-addressable dedup** (each attachment is its own Spaces object; simpler lifecycle, negligible duplication in practice).

### Order status machine

- States: `Open`, `PartiallyReceived`, `FullyReceived`, `Cancelled`, `ClosedShort`.
- Transitions: `Open → PartiallyReceived → FullyReceived`; `{Open, PartiallyReceived} → Cancelled` (blocked if any receipt exists — see Cancel rule above); `PartiallyReceived → ClosedShort`. Terminal states: `FullyReceived`, `Cancelled`, `ClosedShort`.
- Enum shape mirrors existing `ItemCondition`: `cases` + `label()` + `nextAllowed()` + `canTransitionTo()`. Reversal reuses `canTransitionTo()` for the rewind direction.
- `ordered_quantity` is editable only while no receipts exist against the order (FormRequest + DB check).

### Authorization

- FormRequest-based. Abstract `AdministradorRequest` (replaces `CoordinatorRequest`) for admin-only writes; `SaveXRequest` per resource.
- **Admin**: everything — user management (disable/scrub), teams, catalog CRUD, orders CRUD + cancel + close-short, any movement (create + reverse), all browsing.
- **Operador**: create receipts/transfers/returns; read-only browse of Pedidos, Fornecedores, Produtos, Armazéns, Estoque, and all movements. No access to Usuários, Equipes, Categorias, Unidades.
- **Movement history visibility**: any authenticated user sees all movements (full-transparency audit trail).

### User lifecycle

- Users are soft-disabled via `is_active` (no hard delete). Disabled users can't log in; existing FK references to their actions are preserved.
- LGPD personal-data scrub is a separate explicit admin action that replaces name/email with a tombstone (`"Usuário removido #ID"`) while keeping the row and FKs.

### Dashboard

- Phase 1 ships an empty dashboard.
- Phase 8 replaces it with a role-aware hub: admin sees order counts + recent movements + user/team counts; operador sees large action shortcuts (Registrar Recebimento / Transferir / Devolver) + their last 10 movements.

### Testing posture

- Pest feature tests with `RefreshDatabase` for every module. Reuse the structure of `EventItemCrudTest.php` as the template before deleting it in Phase 1.
- One Pest 4 browser smoke test per movement creation flow (receipt, transfer, return) added alongside its phase — catches Wayfinder/Inertia/multipart regressions that HTTP-level tests miss.
- No unit tests for private methods. Factories supply test data; no mocked Eloquent.

### Seed data

- `php artisan migrate:fresh --seed` ships: 2 roles, 1 admin + 1 operador with known env-gated passwords, ~3 suppliers, ~5 categories, ~5 units, ~10 products, 2 warehouses. No orders/stock — those grow per phase via their own factories.
- Env guard: demo users only seeded when `APP_ENV !== 'production'`.

---

## Critical files to touch / reference

### Phase 1 — delete

- Models: `app/Models/Event.php`, `app/Models/EventItem.php`
- Enums: `app/Enums/EventStatus.php`, `app/Enums/ItemCondition.php` (only consumer was `EventItem`; the state-machine pattern survives in the new `OrderStatus`)
- Controllers: `app/Http/Controllers/EventController.php`, `app/Http/Controllers/EventItems/*`
- Requests: `app/Http/Requests/SaveEventRequest.php`, `app/Http/Requests/EventItems/*`
- Migrations: `2026_04_13_181848_create_events_table.php`, `2026_04_13_190448_create_event_items_table.php`, `2026_04_13_174607_add_whatsapp_number_to_users_table.php`
- Tests: `tests/Feature/Events/*`, `tests/Feature/EventItems/*`, `tests/Feature/UserWhatsappNumberTest.php`
- Vue: `resources/js/pages/Events/*`, `resources/js/pages/EventItems/*`
- Routes & sidebar entries for events/event_items in `routes/web.php` and `resources/js/components/AppSidebar.vue`

### Phase 1 — rename / modify

- `app/Http/Requests/CoordinatorRequest.php` → `AdministradorRequest.php` (class + references)
- `database/seeders/RoleSeeder.php` — `Coordinator` → `Administrador`, `Staff` → `Operador`, remove `Client`
- `app/Models/User.php` — drop `whatsapp_number` from fillable
- `tests/Feature/RoleSeederTest.php`, `tests/Feature/UserRoleAssignmentTest.php` — update to the two-role model
- `resources/js/components/AppSidebar.vue` — new sidebar scaffolding (Fornecedores + Categorias links retained)

### Phase 1 — preserve

- Auth (Fortify + 2FA migrations + permission_tables)
- `app/Models/{User,Supplier,ItemCategory}.php`
- `app/Http/Controllers/Suppliers/*`, `app/Http/Controllers/ItemCategories/*`
- `tests/Feature/{DashboardTest,RoleSeederTest,UserRoleAssignmentTest,DemoUserSeederTest}.php`
- `resources/js/pages/{auth,settings,Suppliers,ItemCategories,Dashboard.vue}`

---

## Phase 1: Pivot cleanup + roles

**User stories**: 10 (partial), 23, 24.

### What to build

A subtractive slice. Promote the new PRD to `docs/PRD.md`, delete all event-rental code (models, controllers, requests, migrations, tests, Vue pages, routes), drop the `whatsapp_number` column, rename roles (`Coordinator → Administrador`, `Staff → Operador`, remove `Client`) in the RoleSeeder, Spatie seed, and FormRequest base class. Add `is_active` to users for soft-disable. Keep auth (Fortify + 2FA), Suppliers, ItemCategories, user/team plumbing (teams itself lands in Phase 2). End state: user logs in, sees an empty dashboard + sidebar with Fornecedores and Categorias; `php artisan test` green; `npm run build` green.

### Acceptance criteria

- [ ] `docs/PRD.md` contains the inventory/warehouse scope.
- [ ] No event/event_item/whatsapp code, migrations, routes, Vue pages, or tests remain.
- [ ] `AdministradorRequest` replaces `CoordinatorRequest`; only `administrador` and `operador` exist in seeds and enums.
- [ ] `users.is_active` column exists; login middleware rejects inactive users.
- [ ] 2FA middleware enforces mandatory TOTP for administrador; operador is opt-in.
- [ ] Suppliers and ItemCategories CRUD pass their tests under the new role names.
- [ ] `php artisan test --compact` is fully green; `npm run build` succeeds.

---

## Phase 2: Teams CRUD

**User stories**: 11.

### What to build

Teams as organizational-only grouping. Migration for `teams (name, description nullable)` and `team_user` pivot. Admin-only CRUD pages under `/equipes` with attach/detach users from the team edit view. No data scoping in MVP — the migration shape leaves room for later additive `team_id` scoping on warehouses/orders.

### Acceptance criteria

- [ ] Administrador can create/edit/delete teams and attach/detach users.
- [ ] Operador receives 403 on all team endpoints and no `Equipes` item in sidebar.
- [ ] `users ↔ teams` many-to-many works end-to-end via the edit UI.
- [ ] Feature tests cover happy path + forbidden + validation + pivot attach/detach.

---

## Phase 3: Units + Products + Warehouses CRUD

**User stories**: 3, 4, 5.

### What to build

Three admin-only catalog resources. `units` (name, abbreviation), `products` (name, item_category_id, unit_id), `warehouses` (name). Hard delete with FK-restrict when referenced — a friendly 422 when the FK blocks, not a 500. Operador gets read-only list views for Produtos and Armazéns (needed for movement forms later). Categorias and Unidades remain admin-only top to bottom.

### Acceptance criteria

- [ ] Admin CRUD works for each resource; operador list-only for Produtos/Armazéns, 403 for Categorias/Unidades.
- [ ] Products require existing category and unit (validation + FK).
- [ ] Deleting a referenced unit/category/warehouse returns 422 with a readable message.
- [ ] Feature tests cover CRUD + authorization + FK-restrict behavior.
- [ ] Wayfinder actions generated and consumed from Vue pages (no hardcoded URLs).

---

## Phase 4: Orders CRUD + status machine

**User stories**: 6, 7, 8, 12.

### What to build

`orders` table and admin-only write CRUD under `/pedidos`. `OrderStatus` enum with `label()` + `nextAllowed()` + `canTransitionTo()` mirroring the retired `ItemCondition`. Creation sets `Open`. `ordered_quantity` edit blocked once receipts exist (enforced in FormRequest + DB check — `receipts` table exists as an empty table in this phase so the check works). Explicit admin actions: *Cancelar* (blocked if any receipt exists) and *Encerrar como saldo curto* (allowed from `PartiallyReceived` only). List filters: status enum, supplier. Operador gets a read-only list.

### Acceptance criteria

- [ ] Admin creates an order with supplier + product + quantity; status starts `Open`.
- [ ] Quantity edit is blocked once receipts exist (also re-verified in Phase 5).
- [ ] Cancel and ClosedShort transitions obey the state machine; invalid transitions 422 with clear messages.
- [ ] List filters work; operador sees read-only list.
- [ ] Feature tests cover state machine, quantity-edit lock, cancel-blocked-with-receipts, and authorization.

---

## Phase 5: Receipts + stock lots + attachments

**User stories**: 15, 16, 22 (partial).

### What to build

The foundational movement slice. Introduces:

- Polymorphic `attachments` table + Intervention Image upload pipeline (re-encode, thumbnail, EXIF strip, HEIC, 10 MB cap, max 10 per movement, ≥1 required) + DO Spaces S3 driver + signed-URL delivery + versioning+lifecycle setup.
- `stock_lots` (with `current_balance`) and `stock_movements` tables (immutable ledger with `idempotency_key` unique per user).
- Receipt creation: `lockForUpdate` on parent order → revalidate saldo → insert `stock_lot` (1:1 with receipt) → insert one `receipt` movement → attach photos → auto-transition order status. All inside `DB::transaction`.
- Admin-only reversal UI: "Estornar recebimento" pre-fills an inverse movement linked via `reverses_movement_id`, rewinds order status.
- Pest 4 browser smoke test: login → open order → post receipt with photo → assert movement appears.

### Acceptance criteria

- [ ] Receipt creation is atomic: lot + movement + attachments or nothing (verified via forced-failure test).
- [ ] Over-receipt (quantity > saldo) rejected with 422.
- [ ] ≥1 photo required; non-image/HEIC/malformed files handled as specified.
- [ ] Idempotency key dedupes repeated submissions (feature test with repeated POST).
- [ ] Partial receipt flips order to `PartiallyReceived`; final to `FullyReceived`.
- [ ] Admin reversal rewinds `stock_lot.current_balance` and order status.
- [ ] Operador can create receipts; admin retains full access.
- [ ] Browser smoke test passes.

---

## Phase 6: Transfers

**User stories**: 17, 18.

### What to build

Instant warehouse-to-warehouse movement with supplier provenance preserved. Form: source warehouse, destination warehouse, product, quantity, photos. No lot picker. Inside `DB::transaction`: `lockForUpdate` on each candidate source lot in FIFO order, accumulate draws until quantity is satisfied, fail with 422 if insufficient balance. For each source lot drawn: create a new destination `stock_lot` with `parent_lot_id` → source, write `transfer_out` + `transfer_in` movements, update `current_balance` on both. ≥1 photo attached to the `transfer` row. Idempotency key enforced.

### Acceptance criteria

- [ ] Source = destination returns 422.
- [ ] Insufficient balance returns 422; stock never negative.
- [ ] FIFO draws across multiple source lots produce matching destination lots preserving supplier chain (tested with a 2-lot split).
- [ ] Two movement rows per source lot written atomically; full rollback on photo failure or lock failure.
- [ ] Idempotency key dedupes repeated submissions.
- [ ] Admin reversal produces linked reversal movements across all source lots originally touched.
- [ ] Browser smoke test passes.

---

## Phase 7: Returns

**User stories**: 19, 20.

### What to build

Free-form devolução with supplier matching. Form: supplier, source warehouse, product, quantity, photos. FIFO across lots in the source warehouse where `lot.receipt.order.supplier_id == return.supplier_id`. Validation: aggregate supplier-matched balance ≥ requested quantity, else 422 with message naming the supplier. One `return` movement per contributing lot, decrementing `current_balance`. No new lot created (goods leave the system). Idempotency key enforced. ≥1 photo on the `return` row.

### Acceptance criteria

- [ ] Return decrements the correct supplier-matched lots in FIFO order; other suppliers' lots untouched.
- [ ] Over-return rejected (422); stock never negative.
- [ ] Mismatched supplier lots are skipped in the FIFO scan (tested with multi-supplier warehouse setup).
- [ ] ≥1 photo required; idempotency key dedupes.
- [ ] Admin reversal restores each source lot it drained.
- [ ] Browser smoke test passes.

---

## Phase 8: Stock browsing + movement history + dashboard

**User stories**: 21, 22.

### What to build

Read-only projection views for everyone + the role-aware dashboard replacing the Phase 1 empty page.

- `/estoque`: current stock grouped by product × warehouse, derived from `SUM(stock_lots.current_balance) GROUP BY product, warehouse`. Drill-down to per-lot detail.
- Movement history: filterable reverse-chronological list of all movements (by product, warehouse, user, date range, type) with photo thumbnails and signed-URL originals. Available to everyone.
- Dashboard: admin hub (open-order count, recent movements feed, user/team counts) and operador hub (large "Registrar Recebimento / Transferir / Devolver" action cards + last 10 own movements).
- `php artisan stock:rebuild-balances` command: rebuild `stock_lots.current_balance` from `stock_movements` (safety net; CI test asserts idempotent rebuild on seeded data).

### Acceptance criteria

- [ ] Stock-by-warehouse matches `SUM(quantity WHERE type IN receipt/transfer_in) - SUM(quantity WHERE type IN transfer_out/return)` computed independently in the test.
- [ ] Movement history lists all types chronologically with thumbnails and signed-URL originals.
- [ ] Operador and admin dashboards render the right widgets for each role.
- [ ] `stock:rebuild-balances` recomputes identical balances to the maintained projection.
- [ ] Everyone (admin + operador) can access browsing views.

---

## Verification (overall)

For each phase independently:

- `php artisan test --compact` is green (including phase-specific browser smoke tests in 5/6/7).
- `vendor/bin/pint --dirty --format agent` reports no diffs after run.
- `npm run build` succeeds; Wayfinder-generated actions are committed.
- Manual smoke (`php artisan serve` + browser): admin walks the full flow — create supplier/category/unit/product/warehouse, place an order, receive with photos (partial then final), transfer a lot, return some quantity, observe resulting stock-by-warehouse and movement history, reverse a movement and see balances/state unwind.
- Role check: log in as operador, confirm write endpoints for catalog/orders/teams return 403 while receipts/transfers/returns succeed and browsing surfaces the permitted views.
- 2FA check: administrador login flow forces TOTP enrollment on first login after Phase 1.
- Concurrency spot-check: two parallel curl submissions with different idempotency keys against the same order — one succeeds, the other 422s on saldo. Same key twice — second returns the first's response, no duplicate row.
