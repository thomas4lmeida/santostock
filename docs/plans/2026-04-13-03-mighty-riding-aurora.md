# Plan: Phase 1 — Auth & Roles Foundation (TDD)

> Source: `docs/plans/2026-04-13-02-santostok-mvp.md` (Phase 1) · GitHub issue #2 · Parent PRD #1

## Context

The Laravel 13 app is already scaffolded (Vue/Inertia starter kit, Fortify
auth, Pest, Pestgres, 2FA, Spatie Permission v7 in composer). What the PRD
adds on top is:

1. A roles model so coordinators, staff, and clients see different things.
2. A `whatsapp_number` field on users (used later by Phase 10).
3. Role-gated routing so a staff member never reaches a coordinator-only page.

You will boot/validate the scaffold (`composer install`, `php artisan migrate`,
`npm install && npm run dev`); I will then drive the rest TDD-style, vertical
slices, one Pest test → one minimal implementation at a time.

## Decisions (locked)

- **Role storage**: **Spatie Permission v7** (already in `composer.json`).
  Roles seeded: `coordinator`, `staff`, `client`. Use `HasRoles` trait on
  `User`; protect routes with Spatie's `role:` middleware alias.
- **Post-login redirect**: single `/dashboard` for all roles. The dashboard
  Vue page receives the user's primary role via Inertia props and renders
  role-specific sections.
- **Auth flow**: keep Fortify as-is — no changes to login/registration
  routes.
- **Tests**: Pest feature tests; one behavior per test; no horizontal slicing
  (no batch-writing tests up front).

## Critical files

To be created/modified:

- `database/migrations/xxxx_add_whatsapp_number_to_users_table.php` — new
- `database/migrations/xxxx_xx_xx_create_permission_tables.php` — published
  from Spatie via `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
- `config/permission.php` — published from Spatie
- `app/Models/User.php` — add `HasRoles` trait, add `whatsapp_number` to
  `$fillable`
- `database/seeders/RoleSeeder.php` — new (seeds the 3 Spatie roles)
- `database/seeders/DemoUserSeeder.php` — new (one user per role for dev)
- `database/seeders/DatabaseSeeder.php` — call the two seeders
- `bootstrap/app.php` — register Spatie middleware aliases if not auto-registered
  in Laravel 13 (Spatie publishes them automatically; verify)
- `routes/web.php` — add a `role:coordinator`-gated sample route used by
  middleware tests (could be a placeholder `/events` index returning 200)
- `resources/js/pages/Dashboard.vue` — render role-specific section based on
  Inertia prop `auth.user.role`
- `app/Http/Middleware/HandleInertiaRequests.php` — extend `share()` to
  expose `auth.user.role` (primary role name)
- `tests/Feature/Auth/RoleAccessTest.php` — new
- `tests/Feature/Auth/DashboardRoleContentTest.php` — new
- `tests/Feature/UserWhatsappNumberTest.php` — new

Reused from existing scaffold:

- `tests/Feature/Auth/AuthenticationTest.php` — pattern for login feature tests
- `app/Models/User.php` — already has `HasFactory`, `Notifiable`,
  `TwoFactorAuthenticatable`
- `database/factories/UserFactory.php` — extend with `withRole($name)` state

## Build sequence (vertical slices, TDD)

Each step is one RED → GREEN cycle. Refactor only after GREEN.

### Step 0 — You: validate scaffold

```
composer install
cp .env.example .env   # if not done
php artisan key:generate
php artisan migrate
npm install
npm run dev            # in another shell
php artisan test       # baseline: existing tests should pass
```

Stop and report if any of the above fails before I take over.

### Step 1 — Publish Spatie + run migration (no test, infra)

```
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

Verify Spatie's `roles`, `permissions`, `model_has_roles`, etc. tables exist.

### Step 2 — RED/GREEN: User has `whatsapp_number`

- **Test** (`tests/Feature/UserWhatsappNumberTest.php`): create a user with
  `whatsapp_number = '+5511999999999'`, fetch from DB, assert the value
  round-trips.
- **Impl**: new migration adding nullable `whatsapp_number` string column;
  add to `$fillable` on `User`; run migrate.

### Step 3 — RED/GREEN: RoleSeeder creates 3 roles

- **Test**: run `RoleSeeder`, assert `Role::pluck('name')` contains exactly
  `['coordinator','staff','client']`.
- **Impl**: create `RoleSeeder` using `Role::firstOrCreate(['name' => …,
  'guard_name' => 'web'])`. Idempotent.

### Step 4 — RED/GREEN: User can be assigned a role

- **Test**: seed roles; create a user; call `$user->assignRole('coordinator')`;
  assert `$user->hasRole('coordinator')` is true.
- **Impl**: add `use HasRoles;` to `User` model.

### Step 5 — RED/GREEN: DemoUserSeeder seeds one user per role

- **Test**: run `DemoUserSeeder`; assert exactly one user exists per role
  with predictable emails (`coordinator@santostok.test`, etc.).
- **Impl**: `DemoUserSeeder` creates 3 users and assigns roles; wire into
  `DatabaseSeeder`.

### Step 6 — RED/GREEN: Role middleware blocks wrong role

- **Test**: register a temp route `/events` with `middleware(['auth',
  'role:coordinator'])`; staff user → 403; coordinator user → 200.
- **Impl**: confirm Spatie's middleware alias `role` is auto-registered in
  Laravel 13 (it is, via Spatie's service provider). If not, register in
  `bootstrap/app.php` `withMiddleware()->alias([...])`. Add the `/events`
  placeholder route (real impl lands in Phase 2).

### Step 7 — RED/GREEN: Dashboard exposes user role to Inertia

- **Test**: log in as each role, GET `/dashboard`, assert the Inertia
  response shares `auth.user.role` matching the user's primary role.
- **Impl**: extend `HandleInertiaRequests::share()` to include
  `'auth' => ['user' => fn() => $request->user()?->only(['id','name','email'])
  + ['role' => $request->user()?->roles->pluck('name')->first()]]`.

### Step 8 — Frontend: Dashboard.vue renders per-role section (manual)

Not TDD-tested at the JS level for MVP. Render three small sections in
`Dashboard.vue` keyed off `page.props.auth.user.role`. Manual smoke check
in browser as each seeded user.

### Step 9 — Refactor pass

- Extract a `Role` PHP enum (`App\Enums\Role`) with cases `Coordinator`,
  `Staff`, `Client` for type-safe comparisons in code (Spatie still owns DB).
- DRY any duplication in the new tests using `beforeEach` for role seeding.
- Run `php artisan pint` and `php artisan test`.

## Verification

End-to-end before closing issue #2:

1. `php artisan migrate:fresh --seed`
2. `php artisan test` — all green, including new tests
3. `npm run dev` and log in as `coordinator@santostok.test`,
   `staff@santostok.test`, `client@santostok.test` (password: `password`).
   Each lands on `/dashboard` and sees a role-specific section.
4. As staff: visit `/events` → 403.
5. `php artisan pint --test` — no formatting violations.

Acceptance criteria from issue #2 mapped:

- [x] `composer install && php artisan migrate --seed` produces a runnable app
      → Steps 0 + 5
- [x] A user can log in and is redirected to a role-appropriate landing page
      → Step 7 + 8
- [x] Hitting a route gated by a different role returns 403
      → Step 6
- [x] Feature test asserts each role's middleware behavior
      → Step 6
