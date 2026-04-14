# Phase 2 Implementation Plan: Teams CRUD

> Parent plan: `docs/plans/2026-04-13-05-plan-pivot-inventory-warehouse.md` (Phase 2 section).
> PRD: `docs/PRD.md` (user story 11; Teams module).
> Depends on: Phase 1 (merged via PR #15) — roles renamed to `administrador` / `operador`, `AdministradorRequest` base in place, sidebar reduced to Dashboard + Fornecedores + Categorias.

## Context

Phase 2 is a pure **additive slice**: introduces `teams` and `team_user` many-to-many to capture organizational grouping. No data scoping yet — teams are labels only. The migration shape deliberately leaves room for a later additive `team_id` column on warehouses/orders without reshaping the pivot.

Admin-only end to end: only `administrador` can create/edit/delete teams and attach/detach users. Operador sees no `Equipes` sidebar item and gets 403 on every team endpoint. User attach/detach happens inside the team edit view via a `user_ids[]` multi-select — one atomic `sync()` per save.

Reuse targets (do not reinvent):

- `app/Http/Controllers/Suppliers/SupplierController.php` — controller shape (paginate(50)->withQueryString(), named resource routes, Wayfinder-consumable).
- `app/Http/Requests/Suppliers/SaveSupplierRequest.php` — FormRequest shape extending `AdministradorRequest`.
- `resources/js/pages/Suppliers/*.vue` — Index/Create/Edit/Show page scaffolding and Wayfinder action imports.
- `tests/Feature/Suppliers/*` — Pest feature-test structure with `RefreshDatabase` + `RoleSeeder` in `beforeEach`.

## Commit sequence

Each commit leaves `php artisan test --compact` green. Run `/tdw:preflight-review` before each commit.

### Commit 1 — Migration + Model + Factory

- `php artisan make:model Team -mf` then edit:
  - Migration `create_teams_table`: `id`, `string name` (unique), `string description nullable`, timestamps.
  - Migration `create_team_user_table` (new): composite PK (`team_id`, `user_id`), both `foreignId().cascadeOnDelete()`, `timestamps()`.
  - `app/Models/Team.php`: `$fillable = ['name', 'description']`; `users(): BelongsToMany` against `User` through `team_user`.
  - `app/Models/User.php`: add `teams(): BelongsToMany` inverse.
  - `database/factories/TeamFactory.php`: `name` via `fake()->unique()->company()`, `description` via `fake()->optional()->sentence()`.
- `php artisan migrate:fresh --seed` — confirm new tables land without breaking existing seeds.
- No test yet; structural commit.

### Commit 2 — Request + Controller + Routes (RED → GREEN via TDD)

Per project convention, write the feature test first, then the implementation.

- `tests/Feature/Teams/TeamCrudTest.php` (Pest, `RefreshDatabase`, `RoleSeeder` in `beforeEach`):
  - Admin creates a team (POST `/equipes`) → 302 + row exists.
  - Admin edits name + description (PUT `/equipes/{team}`) → row updated.
  - Admin deletes a team (DELETE `/equipes/{team}`) → row gone, pivot rows gone (cascade).
  - Admin lists teams (GET `/equipes`) → 200, paginated Inertia response shape.
  - Admin shows a team (GET `/equipes/{team}`) → 200, includes attached users.
  - Name is required and unique (validation errors).
- Create `app/Http/Requests/Teams/SaveTeamRequest.php` extending `AdministradorRequest`:
  - Rules: `name` required|string|max:255|unique:teams,name,{team?->id}; `description` nullable|string|max:1000; `user_ids` array|nullable; `user_ids.*` exists:users,id.
- Create `app/Http/Controllers/Teams/TeamController.php` mirroring `SupplierController`:
  - `index()` → `Team::withCount('users')->orderBy('name')->paginate(50)->withQueryString()` → Inertia render `Teams/Index`.
  - `create()` → `Inertia::render('Teams/Create', ['users' => User::orderBy('name')->get(['id','name'])])`.
  - `store(SaveTeamRequest)` → create team, `$team->users()->sync($request->input('user_ids', []))`, redirect to index with flash.
  - `show(Team $team)` → load `users:id,name`, Inertia render `Teams/Show`.
  - `edit(Team $team)` → render with `users` list and `attachedUserIds`.
  - `update(SaveTeamRequest, Team $team)` → update + `sync()`; redirect.
  - `destroy(Team $team)` → delete; redirect index.
- `routes/web.php`: `Route::resource('equipes', TeamController::class)->middleware(['auth','verified'])->names('teams')->parameters(['equipes' => 'team']);`
- `php artisan wayfinder:generate` — regenerate typed actions.
- Make the tests green.

### Commit 3 — Authorization tests (operador 403)

- `tests/Feature/Teams/TeamAuthorizationTest.php`:
  - Operador: GET index/show/create/edit → 403; POST/PUT/DELETE → 403.
  - Unauthenticated: every endpoint → redirect to login.
- Should already pass because `AdministradorRequest` guards writes; but index/show/create/edit need a guard too. Add a policy or a shared middleware on the controller constructor: `$this->middleware(fn(...) => Gate::allows('administrador') ...)` — simplest path: register a `RequireAdministrador` middleware in `bootstrap/app.php` and apply on the `equipes` resource alongside `auth`. (Suppliers already have this pattern; reuse the same middleware alias if it exists, otherwise add `RequireAdministrador` in this commit.)
- `php artisan test --compact`.

### Commit 4 — Pivot behavior tests

- `tests/Feature/Teams/TeamUserPivotTest.php`:
  - Creating a team with `user_ids = [u1, u2]` attaches exactly those two.
  - Updating with `user_ids = [u2, u3]` syncs (u1 removed, u3 added).
  - Updating with `user_ids = []` detaches all.
  - Deleting a user removes them from `team_user` (FK cascade).
  - Deleting a team removes all pivot rows for that team.
- No implementation changes expected — this test commit proves the `sync()` semantics and FK cascade behavior lock.

### Commit 5 — Vue pages (Index / Create / Edit / Show)

Model after `resources/js/pages/Suppliers/*.vue`.

- `resources/js/pages/Teams/Index.vue`: paginated table (Nome, Descrição, Nº de usuários, Ações: ver/editar/excluir). Uses `useForm` + Wayfinder `TeamController.destroy` for delete. Empty-state card with "Nenhuma equipe cadastrada" + link to create.
- `resources/js/pages/Teams/Create.vue`: form for name, description, `user_ids` multi-select (reuse the existing multi-select component if present; otherwise `<select multiple>` styled with Tailwind). Submit via Wayfinder `TeamController.store`.
- `resources/js/pages/Teams/Edit.vue`: same form, pre-populated. `user_ids` defaults to `attachedUserIds` prop.
- `resources/js/pages/Teams/Show.vue`: read-only — name, description, bulleted list of attached users with link back to index. Action buttons: Editar, Excluir.
- Breadcrumbs on every page using Wayfinder route helpers (no hardcoded URLs — matches the pattern established in commit `1325` per memory).
- `npm run build` must succeed.

### Commit 6 — Sidebar entry + Wayfinder regenerate

- `resources/js/components/AppSidebar.vue`: add `Equipes` item between `Fornecedores` and `Categorias`, gated on `$page.props.auth.user.role === 'administrador'` (match the existing admin-gating pattern — inspect current sidebar to confirm the exact prop shape).
- `php artisan wayfinder:generate` — regenerate if any new named route appeared.
- Browser smoke (Pest 4): `tests/Browser/TeamsSidebarTest.php` — login as administrador, assert Equipes visible; login as operador, assert absent.
- `vendor/bin/pint --dirty --format agent`.

## Critical files touched

### Created

- `database/migrations/YYYY_MM_DD_create_teams_table.php`
- `database/migrations/YYYY_MM_DD_create_team_user_table.php`
- `app/Models/Team.php`
- `database/factories/TeamFactory.php`
- `app/Http/Requests/Teams/SaveTeamRequest.php`
- `app/Http/Controllers/Teams/TeamController.php`
- `app/Http/Middleware/RequireAdministrador.php` *(only if not already introduced by Phase 1 Suppliers work — verify first)*
- `resources/js/pages/Teams/{Index,Create,Edit,Show}.vue`
- `tests/Feature/Teams/{TeamCrudTest,TeamAuthorizationTest,TeamUserPivotTest}.php`
- `tests/Browser/TeamsSidebarTest.php`

### Modified

- `app/Models/User.php` — add `teams()` relation.
- `routes/web.php` — `Route::resource('equipes', ...)`.
- `bootstrap/app.php` — register `RequireAdministrador` middleware alias if new.
- `resources/js/components/AppSidebar.vue` — add Equipes (admin-gated).
- Generated Wayfinder files under `resources/js/actions/` and `resources/js/routes/` (commit the diff).

### Deleted

*(none — Phase 2 is purely additive)*

## Patterns to reuse

- **Controller shape**: `SupplierController` — `paginate(50)->withQueryString()`, Inertia render with pt-BR flash messages, `Route::resource` with a Portuguese URL prefix mapped to an English controller binding via `->parameters([...])`.
- **FormRequest authorization**: extend `AdministradorRequest`; declare rules only.
- **Test setup**: `uses(RefreshDatabase::class)` + `beforeEach(fn () => $this->seed(RoleSeeder::class))` — the Suppliers tests are the closest template.
- **Sidebar admin-gating**: the existing Fornecedores/Categorias items' gating expression (inspect `AppSidebar.vue` before writing).
- **Wayfinder consumption**: `import { store, update, destroy } from '@/actions/App/Http/Controllers/Teams/TeamController'` — never hardcode URLs.

## Verification

### Automated

- `php artisan test --compact` — all tests pass. Includes new:
  - `Teams/TeamCrudTest` (happy path + validation)
  - `Teams/TeamAuthorizationTest` (operador 403, guest redirect)
  - `Teams/TeamUserPivotTest` (sync + cascade)
  - `Browser/TeamsSidebarTest` (visibility by role)
- `vendor/bin/pint --dirty --format agent` — no style diffs.
- `npm run build` — clean Vite build.
- `grep -r "/equipes\|'teams'" --include="*.php" --include="*.vue" app/ resources/ routes/ tests/` — references only in expected files (controller, routes, sidebar, tests).

### Manual

1. `php artisan migrate:fresh --seed` then `php artisan serve`.
2. Login as administrador → sidebar shows `Equipes` between Fornecedores and Categorias.
3. `/equipes` → empty-state. Click *Nova equipe* → create "Depósito Central" with description + attach both seeded users → redirect to index; row shows user count = 2.
4. Edit the team → change description, remove one user, save → show page reflects the change.
5. Delete the team → row gone from index; verify in `php artisan tinker`: `DB::table('team_user')->count() === 0`.
6. Logout; login as operador → no `Equipes` sidebar item. Visit `/equipes` directly → 403 (pt-BR message).
7. Logout; visit `/equipes` → redirected to login.

### Concurrency / idempotency

Teams introduce no stock writes. The only write that matters is `sync()`, which is inherently idempotent for a given `user_ids` array. No additional concurrency spot-check needed. (Reappears starting Phase 5.)

## Out of scope for Phase 2

- Data scoping (warehouses/orders per team) — explicitly deferred; will be additive when introduced.
- Team roles or per-team permissions — flat membership only.
- Bulk user import / CSV upload — out of MVP.
- Audit log of membership changes — out of MVP.
- Showing a user's teams on the user profile page — defer to the future admin user-CRUD phase.
