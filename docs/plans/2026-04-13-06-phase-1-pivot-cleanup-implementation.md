# Phase 1 Implementation Plan: Pivot Cleanup + Roles

> Parent plan: `docs/plans/2026-04-13-05-plan-pivot-inventory-warehouse.md` (Phase 1 section).
> Source PRD: `docs/plans/2026-04-13-04-prd-pivot-inventory-warehouse.md` (to be promoted to `docs/PRD.md` as step 1).

## Context

The project is pivoting from an event-rental system to an inventory/warehouse management system. Phase 1 is the **subtractive foundation slice**: it deletes every event-rental artifact, renames roles to the new two-role model (`administrador` / `operador`), drops the WhatsApp column that powered the retired notifications feature, and adds `is_active` for soft-disable + mandatory-TOTP middleware for administrators. Nothing new is built yet. Goal: end with a green `php artisan test`, green `npm run build`, and a boot-able app whose sidebar shows only Dashboard + Fornecedores + Categorias — the minimal surface that Phases 2–8 additively build upon.

Pre-production: no deployed DB exists, so migration files are deleted outright and `php artisan migrate:fresh --seed` is the reset. This is the cheapest, cleanest pivot posture.

## Commit sequence

Each commit leaves `php artisan test` green. Run `/tdw:preflight-review` before each commit.

### Commit 1 — Promote the PRD

- Move `docs/plans/2026-04-13-04-prd-pivot-inventory-warehouse.md` → `docs/PRD.md`, replacing the existing event-rental PRD.
- Reference the parent phased plan (`docs/plans/2026-04-13-05-plan-pivot-inventory-warehouse.md`) from `docs/PRD.md`.

### Commit 2 — Delete event-rental code

Delete in this order to keep intermediate states compilable:

- Vue pages: `resources/js/pages/Events/`, `resources/js/pages/EventItems/`.
- Sidebar: remove Events/EventItems entries in `resources/js/components/AppSidebar.vue`.
- Wayfinder generated actions/routes referencing `EventController` or `EventItemController` (regenerate via `php artisan wayfinder:generate` after backend deletion in commit 3).
- Tests: `tests/Feature/Events/`, `tests/Feature/EventItems/`, `tests/Feature/UserWhatsappNumberTest.php`.
- Requests: `app/Http/Requests/SaveEventRequest.php`, `app/Http/Requests/EventItems/`.
- Controllers: `app/Http/Controllers/EventController.php`, `app/Http/Controllers/EventItems/`.
- Models: `app/Models/Event.php`, `app/Models/EventItem.php`.
- Enums: `app/Enums/EventStatus.php`, `app/Enums/ItemCondition.php` (only consumer was `EventItem`; the state-machine pattern is documented for reuse in the future `OrderStatus`).
- Routes: strip event/event_item routes from `routes/web.php`.
- Factories/seeders: delete any `EventFactory`, `EventItemFactory`, event-seeded rows in `DatabaseSeeder`.
- Migrations: delete `database/migrations/2026_04_13_181848_create_events_table.php` and `database/migrations/2026_04_13_190448_create_event_items_table.php`.

Intermediate check: `php artisan migrate:fresh --seed && php artisan test --compact` — expect suppliers/categories/auth tests to still pass; no stray event references.

### Commit 3 — Drop `whatsapp_number`

- Delete migration `database/migrations/2026_04_13_174607_add_whatsapp_number_to_users_table.php`.
- Remove `whatsapp_number` from `$fillable` / `$casts` in `app/Models/User.php`.
- Scrub `whatsapp_number` references from `database/seeders/DemoUserSeeder.php` and `UserFactory`.
- Delete `tests/Feature/UserWhatsappNumberTest.php` if not already in commit 2.
- `php artisan migrate:fresh --seed` then `php artisan test --compact`.

### Commit 4 — Role rename to Administrador / Operador

Reuse existing `Role` enum pattern in `app/Enums/Role.php`.

- `app/Enums/Role.php`: change cases to `Administrador = 'administrador'`, `Operador = 'operador'`; drop `Client`. Update `label()` accordingly.
- `database/seeders/RoleSeeder.php`: seed the two Spatie roles with permissions; drop `Client` seed.
- `database/seeders/DemoUserSeeder.php`: replace coordinator/staff/client demo users with one `administrador` + one `operador`, passwords sourced from `env('DEMO_ADMIN_PASSWORD')` / `env('DEMO_OPERADOR_PASSWORD')` with dev defaults; gate on `APP_ENV !== 'production'`.
- Rename `app/Http/Requests/CoordinatorRequest.php` → `app/Http/Requests/AdministradorRequest.php`. Update class name and the role check (`Role::Administrador->value`).
- Update every `CoordinatorRequest` reference: `app/Http/Requests/Suppliers/*`, `app/Http/Requests/ItemCategories/*`, any other consumers. Grep `CoordinatorRequest` to catch all.
- Tests: update `tests/Feature/RoleSeederTest.php`, `tests/Feature/UserRoleAssignmentTest.php`, `tests/Feature/DemoUserSeederTest.php`, and every Suppliers/ItemCategories test that references `Coordinator`/`Staff`/`Client` role strings.
- `php artisan migrate:fresh --seed && php artisan test --compact`.

### Commit 5 — Add `is_active` + soft-disable login middleware

- New migration: `add_is_active_to_users_table` — `boolean is_active default true`.
- `app/Models/User.php`: add `is_active` to `$fillable` and `$casts`.
- Middleware: new `app/Http/Middleware/EnsureUserActive.php` — aborts 403 with a pt-BR message when `auth()->user()?->is_active === false`. Register in `bootstrap/app.php` web middleware group after auth.
- Admin user CRUD reflecting disable/enable is **out of scope for Phase 1** (part of Phase 2/later admin settings); Phase 1 just adds the plumbing.
- Feature test: `tests/Feature/InactiveUserCannotAccessAppTest.php` — seed an inactive user, assert 403 on dashboard.
- `php artisan test --compact`.

### Commit 6 — Mandatory 2FA for Administrador

- Middleware: new `app/Http/Middleware/EnsureAdminHasTwoFactor.php` — when `auth()->user()?->hasRole(Role::Administrador) && auth()->user()->two_factor_secret === null`, redirect to the Fortify 2FA enrollment route. Register in the web middleware group.
- Operador remains opt-in — existing Fortify profile page is sufficient.
- Feature test: `tests/Feature/AdminMust EnrollTwoFactorTest.php` — admin without 2FA is redirected; admin with 2FA is not; operador is never redirected.
- `php artisan test --compact`.

### Commit 7 — Sidebar scaffolding + empty dashboard cleanup

- `resources/js/components/AppSidebar.vue`: reduce to Dashboard, Fornecedores, Categorias. Everything else is added per future phase.
- `resources/js/pages/Dashboard.vue`: trim to a minimal pt-BR empty state ("Bem-vindo ao Santostok"). No widgets (Phase 8 adds the real hub).
- Regenerate Wayfinder: `php artisan wayfinder:generate`; commit the generated `@/actions` and `@/routes` diffs.
- Pest browser smoke test (Pest 4): `tests/Browser/DashboardLoadsTest.php` — login as `administrador`, assert "Bem-vindo" text visible and sidebar shows exactly three items.
- `vendor/bin/pint --dirty --format agent` to normalize style.
- `npm run build` must succeed.

## Critical files touched

### Created

- `docs/PRD.md` (moved from `docs/plans/2026-04-13-04-...`).
- `database/migrations/YYYY_MM_DD_add_is_active_to_users_table.php`.
- `app/Http/Requests/AdministradorRequest.php` (renamed from `CoordinatorRequest.php`).
- `app/Http/Middleware/EnsureUserActive.php`.
- `app/Http/Middleware/EnsureAdminHasTwoFactor.php`.
- `tests/Feature/InactiveUserCannotAccessAppTest.php`.
- `tests/Feature/AdminMustEnrollTwoFactorTest.php`.
- `tests/Browser/DashboardLoadsTest.php`.

### Modified

- `app/Enums/Role.php` — new two-role shape.
- `app/Models/User.php` — drop `whatsapp_number`; add `is_active`.
- `database/seeders/{RoleSeeder,DemoUserSeeder}.php` — new roles + env-gated passwords.
- All `app/Http/Requests/Suppliers/*`, `app/Http/Requests/ItemCategories/*` referencing `CoordinatorRequest`.
- `resources/js/components/AppSidebar.vue` — three items only.
- `resources/js/pages/Dashboard.vue` — minimal empty state.
- `routes/web.php` — remove event/event_item routes.
- `bootstrap/app.php` — register new middleware.
- Existing Suppliers / ItemCategories tests for new role strings.

### Deleted

- `app/Models/{Event,EventItem}.php`
- `app/Enums/{EventStatus,ItemCondition}.php`
- `app/Http/Controllers/EventController.php`, `app/Http/Controllers/EventItems/`
- `app/Http/Requests/{SaveEventRequest.php,EventItems/}`
- `app/Http/Requests/CoordinatorRequest.php` (renamed in commit 4)
- `database/migrations/2026_04_13_181848_create_events_table.php`
- `database/migrations/2026_04_13_190448_create_event_items_table.php`
- `database/migrations/2026_04_13_174607_add_whatsapp_number_to_users_table.php`
- `tests/Feature/{Events,EventItems}/`
- `tests/Feature/UserWhatsappNumberTest.php`
- `resources/js/pages/{Events,EventItems}/`

## Patterns to reuse

- **Role enum shape**: current `app/Enums/Role.php` is already the template — `cases` + `label()`. Just swap the cases.
- **FormRequest authorization**: current `CoordinatorRequest::authorize()` is the template for `AdministradorRequest`; same pattern, new role enum reference.
- **Supplier / ItemCategory CRUD**: `app/Http/Controllers/Suppliers/SupplierController.php` is the reference controller style (paginate(50), named routes, Wayfinder consumption in Vue). Preserved untouched except for the role-string updates in request classes.
- **RefreshDatabase + RoleSeeder in tests**: existing `tests/Feature/Suppliers/*` tests are the template for future movement tests.

## Verification

### Automated

- `php artisan test --compact` — all tests pass. Includes:
  - Existing Suppliers, ItemCategories, Dashboard, RoleSeeder, UserRoleAssignment, DemoUserSeeder tests updated for new roles.
  - New: `InactiveUserCannotAccessAppTest`, `AdminMustEnrollTwoFactorTest`.
  - New Pest 4 browser smoke: `DashboardLoadsTest`.
- `vendor/bin/pint --dirty --format agent` — no style diffs.
- `npm run build` — clean Vite build.
- `grep -r "Coordinator\|Staff\|Client\|whatsapp\|event_item\|Event::" --include="*.php" --include="*.vue" app/ resources/ tests/ database/` returns zero hits.

### Manual

1. `php artisan migrate:fresh --seed` — DB has exactly: users, cache/jobs, permission tables, 2FA columns, suppliers, item_categories, is_active column. No events, event_items, whatsapp_number.
2. `php artisan serve` + browser.
3. Log in as the seeded administrador: after password, 2FA enrollment page appears (empty `two_factor_secret`). Enroll. Land on dashboard. Sidebar shows Dashboard, Fornecedores, Categorias.
4. Log out, log in as the seeded operador: dashboard loads immediately (no 2FA forced). Sidebar shows Dashboard, Fornecedores, Categorias.
5. Admin panel or `php artisan tinker`: set `operador.is_active = false`. Reload any route → 403 with the pt-BR inactive message.
6. Restore `is_active = true`; normal access resumes.

### Concurrency / idempotency

Phase 1 introduces no stock writes, so no concurrency spot-check is needed yet. (Reappears starting Phase 5.)

## Out of scope for Phase 1

- Teams (Phase 2).
- Units, Products, Warehouses (Phase 3).
- Orders / receipts / transfers / returns / stock (Phases 4–7).
- Admin user-CRUD UI for toggling `is_active` and triggering LGPD scrub (future admin-settings phase — tracked separately).
- Attachments, DO Spaces, Intervention Image pipeline (Phase 5).
- Role-aware dashboard widgets (Phase 8).
