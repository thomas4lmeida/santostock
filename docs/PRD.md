# PRD: Santostok — Inventory & Warehouse Management (Pivot)

> **Context for this plan:** This file is the draft of the **revised Santostok PRD** produced under plan mode. After approval it will be moved to `docs/PRD.md` (replacing the original event-rental PRD) and submitted as a GitHub issue. The current `docs/PRD.md` (event-rental scope) is superseded by this document.

---

## Problem Statement

Small-to-medium operations that hold rented or purchased stock from suppliers have no centralized, auditable way to manage what is on order, what has been received, where it is physically stored, and what has been returned. Spreadsheets and chat messages lose information, make partial deliveries hard to track, and offer no audit trail when goods arrive damaged or never arrive at all.

Operators need to know, at any moment: what was ordered from which supplier, what is still outstanding (saldo a receber), where each lot is physically located, and what evidence (photos) supports every movement in and out.

## Solution

Santostok is a web application for tracking purchased/rented stock across four operations:

1. **Pedidos (Orders)** — an administrator registers expected deliveries: supplier + product + quantity. The order carries an open balance (*saldo a receber*).
2. **Recebimento (Receiving)** — an operator registers what actually arrived. Stock is credited to a specific warehouse as a new lot. The order's saldo decreases. Partial receipts are allowed; over-receipt is blocked.
3. **Transferência (Transfer)** — an operator moves lots between warehouses instantly (debit source / credit destination in one transaction).
4. **Devolução (Return)** — an operator returns stock to a supplier from a specific warehouse. Free-form: no link required to a specific receipt or order.

Every movement (receipt, transfer, return) requires photographic evidence. Stock is tracked per **(product × warehouse × lot)**. Orders track no money — quantities only. Users belong to one or more teams (organizational grouping only; no data scoping in MVP).

The application is in Brazilian Portuguese. Code identifiers remain in English where it matches existing conventions, with Portuguese values for role enums and UI labels.

## User Stories

### Administrador

1. As an administrador, I want to create, edit, and delete suppliers, so that I can maintain the list of vendors the business works with.
2. As an administrador, I want to create, edit, and delete item categories, so that I can group products meaningfully.
3. As an administrador, I want to create, edit, and delete units of measurement (un, m, kg, par, etc.), so that each product can express quantity appropriately.
4. As an administrador, I want to create, edit, and delete products (name, category, unit), so that I have a global catalog independent of any supplier.
5. As an administrador, I want to create, edit, and delete warehouses (armazéns), so that I can represent the physical locations where stock is kept.
6. As an administrador, I want to create orders specifying supplier, product, and ordered quantity, so that expected deliveries are tracked.
7. As an administrador, I want to edit an order's quantity only while no receipts exist against it, so that received history cannot be rewritten.
8. As an administrador, I want to cancel an order at any time before it is fully received, so that I can stop expecting a delivery that will not come.
9. As an administrador, I want to close an order as *short* after receiving partial quantity, so that the remaining saldo is voided without treating the order as cancelled.
10. As an administrador, I want to create, edit, and delete users, and assign them roles, so that I control who can use the system.
11. As an administrador, I want to create, edit, and delete teams, and attach or detach users from teams, so that the organizational structure is captured.
12. As an administrador, I want to see a list of orders filterable by status (Open, PartiallyReceived, FullyReceived, Cancelled, ClosedShort) and supplier, so that I can monitor outstanding balances.
13. As an administrador, I want to do everything an operador can do, so that I can step in when needed.

### Operador

14. As an operador, I want to see a list of open and partially received orders, so that I know what deliveries to expect.
15. As an operador, I want to register a receipt against an open order, specifying warehouse, quantity received, and photos, so that stock is credited and saldo decreases.
16. As an operador, I want to be blocked from receiving more than the order's remaining saldo, so that data integrity is preserved.
17. As an operador, I want to transfer a lot (or part of it) from one warehouse to another with photos, so that stock location reflects reality.
18. As an operador, I want to be blocked from transferring when source and destination warehouses are the same, so that I cannot create meaningless movements.
19. As an operador, I want to return stock to a supplier from a specific warehouse with photos, so that returned items are subtracted from stock.
20. As an operador, I want to be blocked from returning more than available in the chosen warehouse/lot, so that stock never goes negative.
21. As an operador, I want to browse current stock by product and warehouse, so that I can answer "how much of X do we have in armazém Y?".
22. As an operador, I want to see the history of movements for a product or warehouse, including photos, so that I can audit what happened.

### All authenticated users

23. As an authenticated user, I want to log in using email and password with optional 2FA, so that the system is secure.
24. As an authenticated user, I want the entire UI in Portuguese (pt-BR), so that the team can use it naturally.

## Implementation Decisions

### Scope disposition

- The previous event-rental scope (events, event_items, tasks, open pool, clients, notifications, WhatsApp, scheduler) is **removed**. Corresponding code, migrations, and tests are deleted on a clean pivot branch.
- **Kept** from phases 1–3: authentication (Laravel Fortify + Spatie Permissions), `users`, `suppliers`, `item_categories`, pt-BR inline localization, existing code patterns (FormRequest-based authorization, state-machine enums, Wayfinder-generated TypeScript actions, Pest feature tests, migration FK/index conventions).

### Modules

- **Auth & Roles** — Fortify (login, password, 2FA) + Spatie. Two roles: `Administrador` and `Operador`. The `Client` role is removed. Role enum values stored as lowercase Portuguese (`administrador`, `operador`).
- **Teams** — `teams` table (name, description?), `team_user` pivot (many-to-many). CRUD for teams + attach/detach users. **No data scoping yet** (MVP); teams are organizational only. Design migrations so adding `team_id` scoping later is additive.
- **Suppliers (Fornecedores)** — CRUD (kept from phase 3).
- **Item Categories (Categorias)** — CRUD (kept from phase 3).
- **Units (Unidades)** — new lookup table with CRUD. Products reference `unit_id`.
- **Products (Produtos)** — new global catalog (name, item_category_id, unit_id). No supplier binding on the product itself.
- **Warehouses (Armazéns)** — new. Minimal schema: `name` only. FK-restrict delete when stock exists.
- **Orders (Pedidos)** — new. Fields: `supplier_id`, `product_id`, `ordered_quantity`, `status` (enum), timestamps. Derived `saldo = ordered_quantity - sum(receipts.quantity)`. Quantity is editable only while no receipts exist. **Order status machine**: `Open → PartiallyReceived → FullyReceived`, and `{Open, PartiallyReceived} → Cancelled`, and `PartiallyReceived → ClosedShort`. Terminal states: `FullyReceived`, `Cancelled`, `ClosedShort`. Implemented with the same `nextAllowed()` + `canTransitionTo()` pattern already in `ItemCondition`.
- **Receipts (Recebimentos)** — new. Fields: `order_id`, `warehouse_id`, `quantity`, `received_at`, user. Creating a receipt (a) inserts a `stock_lot` row, (b) inserts `stock_movement` rows, (c) requires at least one photo attachment, (d) validates `quantity ≤ order.saldo`, (e) auto-transitions the parent order to `PartiallyReceived` or `FullyReceived`.
- **Transfers (Transferências)** — new. Fields: `source_warehouse_id`, `destination_warehouse_id`, `product_id`, `lot_id` (optional, if transferring from a specific lot), `quantity`, user, timestamps. Instant: one atomic DB transaction writes two `stock_movement` rows (out of source, into destination). Source ≠ destination enforced. Requires photo evidence.
- **Returns (Devoluções)** — new. Free-form: `supplier_id`, `warehouse_id`, `product_id`, `lot_id` (optional), `quantity`, user, timestamps. No FK to a specific receipt. Decrements stock; requires photo evidence. Validates `quantity ≤ available lot/warehouse balance`.
- **Stock** — tracked as `stock_lots` (one per receipt, per (product, warehouse, lot)) and derived aggregates (query or a materialized view). Every change is logged in `stock_movements`.
- **Attachments (Evidências)** — polymorphic `attachments` table storing files uploaded to `storage/app/public`. Belongs-to polymorphic `attachable` (receipts, transfers, returns). Unlimited photos per movement. At least one required on receipt, transfer, and return.

### Key relationships

- `suppliers` hasMany `orders`, `returns`
- `products` belongsTo `item_category`, `unit`; hasMany `orders`, `stock_lots`
- `warehouses` hasMany `stock_lots`, `stock_movements`
- `orders` belongsTo `supplier`, `product`; hasMany `receipts`
- `receipts` belongsTo `order`, `warehouse`; hasOne `stock_lot`; morphMany `attachments`
- `stock_lots` belongsTo `product`, `warehouse`, `receipt`; hasMany `stock_movements`
- `stock_movements` belongsTo `stock_lot`, `warehouse`, `user`; (type: receipt | transfer_in | transfer_out | return)
- `transfers` belongsTo source/destination `warehouse`, `product`, `lot?`; morphMany `attachments`
- `returns` belongsTo `supplier`, `warehouse`, `product`, `lot?`; morphMany `attachments`
- `users` belongsToMany `teams` via `team_user`

### Patterns (follow existing codebase conventions)

- All write endpoints use FormRequests for authorization and validation. Introduce `AdministradorRequest` (replaces `CoordinatorRequest`) as abstract base for admin-only actions. `SaveXRequest` per resource.
- State-machine enums use `cases` + `label()` + `nextAllowed()` + `canTransitionTo()`.
- Wayfinder auto-generates typed TS actions; Vue pages consume `@/actions/...` instead of hardcoded URLs.
- pt-BR labels inline in enums (`label()`) and Vue templates; no `lang/` files unless a need emerges.
- Quantities are positive integers. No cost/money fields anywhere.

### Out-of-box concerns

- **File uploads:** Use Laravel's default public disk. Photos validated as image/*, reasonable size cap (e.g., 10 MB each).
- **Transactions:** All stock-affecting writes (receipt, transfer, return) wrapped in `DB::transaction` to keep aggregates consistent.
- **Authorization:** Administrador does everything. Operador can create receipts/transfers/returns and browse, but cannot manage users, teams, suppliers, categories, units, products, warehouses, or orders.

## Testing Decisions

### What makes a good test

Tests verify external behavior: the HTTP request produces the expected DB state, status transition, or forbidden/validation error. Do not test private methods. Do not mock Eloquent models. Factories supply test data.

### Modules to test (Pest feature tests, `RefreshDatabase`)

- **Role-based access** — administrador can do everything; operador can do operational things only; unauthenticated requests are redirected.
- **Order status machine** — valid transitions (Open → PartiallyReceived on partial receipt; → FullyReceived when saldo = 0; → Cancelled from Open or PartiallyReceived; → ClosedShort from PartiallyReceived). Invalid transitions are rejected.
- **Receipt constraints** — cannot receive more than saldo; auto-transitions parent order; at least one photo required; creates stock_lot + stock_movement.
- **Transfer constraints** — source ≠ destination; photos required; atomic movement rows; cannot transfer more than lot balance.
- **Return constraints** — cannot return more than warehouse/lot balance; photos required; stock decreases.
- **Teams CRUD** — admin can create/edit/delete teams and manage pivot; operador is forbidden.
- **Quantity edit lock** — order quantity editable while no receipts; locked after first receipt.

Use phase 3's `EventItemCrudTest.php` as a reference for structure (beforeEach factory setup + RoleSeeder, explicit role checks for 403, happy-path + edge cases).

### Prior art

- State-machine tests: `ItemConditionStateMachineTest` / transition assertions in `EventItemCrudTest`.
- FormRequest authorization: `CoordinatorRequest::authorize()`.
- Wayfinder integration: `EventController` routes consumed by `resources/js/pages/Events/*.vue`.

## Out of Scope

- Cost / money tracking (unit price, totals, currency, invoicing).
- Serial-number tracking (lot-level is the finest granularity).
- Two-step (em trânsito) transfers.
- Data scoping per team (warehouses/orders/stock are global in MVP).
- Notifications (in-app, email, WhatsApp, SMS).
- Client portal or any third user type beyond Administrador/Operador.
- Multi-company / multi-tenant isolation.
- Mobile native app (web-only).
- Barcode/QR scanning.
- Reports beyond simple list views and stock-by-warehouse browsing.
- Soft delete / archival of warehouses, products, suppliers (hard delete with FK-restrict is the MVP).

## Further Notes

- Clean-slate branch strategy: the pivot happens on a dedicated branch. Phase 3 artifacts (events, event_items, tasks-related seeds, related tests and Vue pages) are removed in the first commit so CI reflects the new surface.
- Role rename (`Coordinator` → `Administrador`, `Staff` → `Operador`, drop `Client`) is a DB migration plus Spatie role seed change plus FormRequest base-class rename.
- Photo evidence may dominate disk usage; plan for storage growth but do not build CDN/S3 integration in MVP.
- When stock queries become a bottleneck (unlikely at MVP scale), an aggregate `stock_balances` materialized view or an event-sourced projection can be added without changing the API.
- This PRD decomposes naturally into phases for future issues: (1) pivot cleanup + Administrador/Operador roles, (2) Teams CRUD, (3) Units + Products + Warehouses CRUD, (4) Orders CRUD + status machine, (5) Receipts + stock_lots + attachments, (6) Transfers, (7) Returns, (8) Stock browsing + movement history. Each phase gets its own implementation plan.

---

## Verification

After the new PRD is approved and moved to `docs/PRD.md`:

- `docs/PRD.md` contains the inventory/warehouse scope (this document), not the event-rental scope.
- A GitHub issue is opened with this PRD body.
- The original event-rental PRD is preserved in git history (prior commits) for reference.
- No code changes are made by this plan alone; implementation is scheduled as subsequent phased plans.
