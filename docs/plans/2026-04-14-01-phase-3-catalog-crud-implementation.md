# Phase 3 Implementation Plan: Units + Warehouses + Products CRUD

> Parent plan: `docs/plans/2026-04-13-05-plan-pivot-inventory-warehouse.md` (Phase 3 section).
> PRD: `docs/PRD.md` (user stories 3, 4, 5).
> Depends on: Phase 2 (merged) — `AdministradorRequest`, `role:administrador` middleware group, Suppliers/Teams reference pattern.

## Context

Phase 3 introduces the product catalog. Three resources, each with admin CRUD:

- **Unidades** (`units`): `name`, `abbreviation`. Admin-only end-to-end.
- **Armazéns** (`warehouses`): `name`. Admin CRUD; operador gets a read-only list view (needed by Phase 5 movement forms).
- **Produtos** (`products`): `name`, `item_category_id`, `unit_id`. Admin CRUD; operador gets a read-only list view.

Design decisions:

- **FK-restrict on delete.** Hard delete with foreign-key restriction (no soft-delete). When a referenced row is deleted, the DB raises a `QueryException` that controllers must convert to a 422 with a pt-BR message. Implemented as a small shared trait `HandlesRestrictedDelete` in `app/Http/Controllers/Concerns/` — introduced in the first commit and reused by all three controllers.
- **Split authorization.** For Produtos/Armazéns, only write endpoints go inside `role:administrador`; read endpoints (`index`, `show`) sit in the outer `auth + verified` group so operador can access them. Unidades and Categorias (existing) remain fully inside the admin group. Writes stay gated because `SaveXRequest extends AdministradorRequest::authorize()` returns 403 for non-admins even if a route slipped through.
- **Wayfinder route names**: URL prefixes are Portuguese (`/unidades`, `/armazens`, `/produtos`); resource names stay English (`units`, `warehouses`, `products`) so existing naming conventions and test-helper patterns keep working.
- **Sidebar updates** happen incrementally in each resource's Vue commit (one nav item per resource, admin-gated).

Reuse targets (do not reinvent):

- `app/Http/Controllers/Suppliers/SupplierController.php` and `app/Http/Controllers/Teams/TeamController.php` for shape, `paginate(50)->withQueryString()`, named routes, Inertia props.
- `app/Http/Requests/Suppliers/SaveSupplierRequest.php` for FormRequest shape; `SaveTeamRequest` for `Rule::unique(...)->ignore(...)` on update.
- `resources/js/pages/Suppliers/*.vue` and `resources/js/pages/Teams/*.vue` for the Create/Edit/Show/Index + shared form component pattern.
- `tests/Feature/Suppliers/*.php` and `tests/Feature/Teams/*.php` for Pest test shape (RefreshDatabase + RoleSeeder in beforeEach, `withTwoFactor()->assignRole(Role::Administrador->value)`).

## Commit sequence

Each commit leaves `./vendor/bin/sail test --compact` green. Lint + format per commit; full preflight at the end before PR.

### Commit 1 — `HandlesRestrictedDelete` trait + Unidades backend

- Create `app/Http/Controllers/Concerns/HandlesRestrictedDelete.php` — single method `restrictedDelete(Model $model, string $redirectRoute, string $errorMessage): RedirectResponse` that wraps `$model->delete()` in try/catch for `QueryException` with SQLSTATE `23503` (foreign_key_violation), returns `back()->withErrors(['delete' => $errorMessage])` on conflict.
- `php artisan make:model Unit -mf`:
  - Migration: `id`, `string name unique`, `string abbreviation` (short, e.g., "un", "kg", "m", "par"), timestamps.
  - Model: `$fillable = ['name', 'abbreviation']`.
  - Factory: plausible pt-BR values (`'Unidade'`/`'un'`, `'Quilograma'`/`'kg'`, etc.).
- `app/Http/Requests/Units/SaveUnitRequest.php` extending `AdministradorRequest`:
  - `name` required|string|max:255|unique (ignore-on-update).
  - `abbreviation` required|string|max:20.
- `app/Http/Controllers/Units/UnitController.php` mirroring SupplierController; `destroy()` uses `restrictedDelete()` with message "Esta unidade não pode ser excluída porque está em uso."
- Route inside `role:administrador`: `Route::resource('unidades', UnitController::class)->parameters(['unidades' => 'unit'])->names('units');`
- `tests/Feature/Units/UnitCrudTest.php`: happy path CRUD + unique name + required abbreviation.
- `tests/Feature/Units/UnitAuthorizationTest.php`: operador 403 on all endpoints; guest redirect to login.
- Run sail tests. Commit: `feat(units): add units CRUD with FK-restricted delete`

### Commit 2 — Unidades Vue pages + sidebar entry

- `resources/js/pages/Units/{Index,Create,Edit,Show,UnitForm}.vue` following Teams pattern.
- `AppSidebar.vue`: add Unidades item inside the administrador block (after Categorias).
- `npm run build` green.
- Commit: `feat(units): add Inertia Vue pages and sidebar entry`

### Commit 3 — Armazéns backend

- `php artisan make:model Warehouse -mf`:
  - Migration: `id`, `string name unique`, timestamps.
  - Factory: pt-BR names (`'Armazém Central'`, `'Depósito Sul'`, etc.).
- `SaveWarehouseRequest` — `name` required|unique (ignore-on-update).
- `WarehouseController` — `index()` and `show()` available to any `auth+verified` user; `store/update/destroy/create/edit` only to admin. `destroy()` uses `restrictedDelete()` with message "Este armazém não pode ser excluído porque possui estoque." (Phase 3 has no stock yet, but the FK will exist from Phase 5; for now any future FK will be honored. The try/catch is harmless pre-stock.)
- Routes — split: reads in outer `auth+verified` group; writes (`store`, `update`, `destroy`, `create`, `edit`) inside `role:administrador`:
  ```php
  Route::middleware(['auth', 'verified'])->group(function () {
      Route::get('armazens', [WarehouseController::class, 'index'])->name('warehouses.index');
      Route::get('armazens/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');

      Route::middleware('role:administrador')->group(function () {
          Route::resource('armazens', WarehouseController::class)
              ->parameters(['armazens' => 'warehouse'])
              ->names('warehouses')
              ->except(['index', 'show']);
      });
  });
  ```
- `tests/Feature/Warehouses/WarehouseCrudTest.php`: happy paths.
- `tests/Feature/Warehouses/WarehouseAuthorizationTest.php`: operador can `index`/`show`; operador forbidden on all writes; guest redirect.
- Commit: `feat(warehouses): add warehouses CRUD with operador read access`

### Commit 4 — Armazéns Vue pages + sidebar entry

- `resources/js/pages/Warehouses/{Index,Create,Edit,Show,WarehouseForm}.vue`.
- Sidebar — Armazéns item visible to **both** administrador and operador (new gating case). Admin-only pages (Create, Edit) protected by route-level middleware; Index and Show are accessible.
- Commit: `feat(warehouses): add Inertia Vue pages and sidebar entry`

### Commit 5 — Produtos backend

- `php artisan make:model Product -mf`:
  - Migration: `id`, `string name`, `foreignId item_category_id ->constrained()->restrictOnDelete()`, `foreignId unit_id ->constrained()->restrictOnDelete()`, timestamps. Unique index on `(name, item_category_id)` so the same category can't hold duplicate product names (reasonable default; revisit if it annoys users).
  - Factory: uses existing `ItemCategoryFactory` and `UnitFactory`.
- `SaveProductRequest` — `name` required|string|max:255; `item_category_id` required|exists; `unit_id` required|exists. Unique `(name, item_category_id)` via `Rule::unique('products','name')->where('item_category_id', $this->input('item_category_id'))->ignore($this->route('product'))`.
- `ProductController` — `index()` eager-loads `itemCategory:id,name` and `unit:id,name,abbreviation`. `create/edit` pass `itemCategories` and `units` select lists. Read routes open to auth+verified; writes admin-only (same split as Warehouses). `destroy()` uses `restrictedDelete()` with "Este produto não pode ser excluído porque está em uso."
- Tests: `ProductCrudTest`, `ProductAuthorizationTest`, `ProductValidationTest` (required category + unit, FK existence, duplicate name within same category).
- Commit: `feat(products): add products CRUD with category and unit foreign keys`

### Commit 6 — Produtos Vue pages + sidebar entry

- `resources/js/pages/Products/{Index,Create,Edit,Show,ProductForm}.vue`.
- `ProductForm` has two `<select>` inputs (category, unit) populated from controller props.
- `Index.vue` table shows name, category, unit (via eager-loaded relations).
- Sidebar — Produtos item visible to both roles.
- Commit: `feat(products): add Inertia Vue pages and sidebar entry`

## Critical files touched

### Created

- `app/Http/Controllers/Concerns/HandlesRestrictedDelete.php`
- `app/Models/{Unit,Warehouse,Product}.php`
- `database/factories/{UnitFactory,WarehouseFactory,ProductFactory}.php`
- Three migrations under `database/migrations/`.
- `app/Http/Requests/{Units,Warehouses,Products}/Save*Request.php`
- `app/Http/Controllers/{Units,Warehouses,Products}/*Controller.php`
- `resources/js/pages/{Units,Warehouses,Products}/{Index,Create,Edit,Show,*Form}.vue` (20 files)
- `tests/Feature/{Units,Warehouses,Products}/*Test.php` (~7 files)

### Modified

- `routes/web.php` — new route groups for reads vs writes on Warehouses/Products; admin-only resource for Units.
- `resources/js/components/AppSidebar.vue` — three new nav entries.

### Deleted

*(none)*

## Patterns to reuse

- **Controller shape:** `SupplierController` (paginate, `withQueryString`, Inertia render, named routes).
- **FormRequest:** extend `AdministradorRequest`.
- **Delete conflict handling:** `HandlesRestrictedDelete` trait introduced in commit 1.
- **Test setup:** `beforeEach(fn () => $this->seed(RoleSeeder::class))` + `withTwoFactor()->assignRole(Role::Administrador->value)` for admin factory.
- **Wayfinder consumption:** `import * as UnitController from '@/actions/App/Http/Controllers/Units/UnitController'`.
- **Sidebar gating:** reuse existing `role === 'administrador'` computed; for Warehouses/Products items, render unconditionally for any authenticated user.

## Verification

### Automated

- `./vendor/bin/sail test --compact` — all green. Counts: ~25 new tests across Units/Warehouses/Products (CRUD, auth, validation, FK-restrict).
- `vendor/bin/pint --dirty --format agent` — clean.
- `./vendor/bin/sail npm run build` — clean.
- `grep -r "/unidades\|/armazens\|/produtos" --include="*.php" --include="*.vue"` returns only expected files.

### Manual

1. `./vendor/bin/sail artisan migrate:fresh --seed`.
2. Login as administrador → sidebar shows Dashboard, Fornecedores, Equipes, Categorias, Unidades, Armazéns, Produtos.
3. Create a unit ("Quilograma" / "kg"), a warehouse ("Depósito Central"), a category, then a product tying category + unit → appears in Produtos index with relations rendered.
4. Try to delete the unit → 422 with "está em uso"; delete the product first, then the unit succeeds.
5. Logout, login as operador → sidebar shows Dashboard, Armazéns, Produtos only. Try `/produtos/create` → 403. Visit `/produtos` → 200 read-only.

### Concurrency / idempotency

No stock writes in Phase 3. FK-restrict is enforced at the DB layer, which is naturally safe under concurrency.

## Out of scope for Phase 3

- Orders and status machine (Phase 4).
- Stock tables, receipts, movements, attachments (Phase 5).
- Bulk import for products / units (CSV).
- Archival / soft-delete.
- Per-warehouse permissions or team scoping.
- Barcode/SKU fields on products (not in PRD; can be added as an additive migration later).
