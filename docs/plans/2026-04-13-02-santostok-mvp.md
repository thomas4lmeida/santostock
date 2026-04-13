# Plan: Santostok MVP — Event Rental Stock Manager

> Source PRD: `docs/PRD.md` (also published as GitHub issue #1).

## Context

Santostok is a greenfield Laravel application that gives event production
companies a single source of truth for rented equipment across an event's
lifecycle. Today, coordinators rely on chat and phone calls to track what is
expected, who receives it, and when it is returned — leading to missed
deliveries and unreturned items. This plan breaks the MVP into thin vertical
slices so each phase delivers a demoable end-to-end behavior, lets risky
integrations (auto-return scheduler, WhatsApp provider) fail in isolation, and
keeps progress continuously verifiable.

## Architectural decisions

Durable choices that apply across all phases:

- **Stack**: Laravel (PHP) + PostgreSQL. Server-rendered Blade views for MVP
  (no SPA); HTMX/Livewire optional for partial updates. Queue driver: database
  (upgrade to Redis later).
- **Auth**: Laravel built-in auth (session-based). Single `users.role` enum
  column: `coordinator | staff | client`. Route-level middleware enforces
  role gates.
- **Routes** (top-level groups):
  - `/events`, `/events/{event}`, `/events/{event}/items` — coordinator
  - `/suppliers`, `/categories` — coordinator
  - `/tasks`, `/tasks/pool`, `/tasks/{task}` — staff + coordinator
  - `/notifications` — all authenticated users
  - `/client/events`, `/client/events/{event}` — client portal
- **Schema** (per PRD §Schema): `users`, `events`, `suppliers`,
  `item_categories`, `event_items`, `tasks`, `notifications`. Migrations
  added incrementally per phase, never retro-edited.
- **Task state machine**: `pending → accepted → delivered → returned`.
  Encapsulated in a single `Task::transitionTo()` method that throws on
  invalid transitions; all controllers/jobs go through it.
- **Open task pool**: `tasks.assigned_user_id IS NULL AND status = 'pending'`.
  Claim is an atomic `UPDATE … WHERE assigned_user_id IS NULL` to prevent
  double-claim races.
- **Notifications**: Laravel's notification system with one Notification class
  per event type (e.g. `TaskAssigned`), fanning out to channels enabled per
  phase. WhatsApp/SMS is a custom channel wrapping a Brazilian-friendly
  provider (Z-API or Evolution API).
- **Auto-return job**: idempotent scheduled command
  (`php artisan tasks:create-returns`) running every 5 min; guards against
  duplicates via `WHERE NOT EXISTS` against existing `return` tasks for the
  same `event_item_id`.
- **Testing**: Pest (or PHPUnit) feature tests for end-to-end behavior; unit
  tests for the task state machine. External channels are mocked at the
  Laravel `Notification::fake()` boundary.

---

## Phase 1: Auth & roles foundation

**User stories**: enables 1, 11, 21 (foundational for all roles).

### What to build

Scaffold the Laravel app, add the `users` table with the `role` enum and
`whatsapp_number`, ship login/logout, and add role middleware
(`role:coordinator`, `role:staff`, `role:client`). Seed one user per role for
local dev.

### Acceptance criteria

- [ ] `composer install && php artisan migrate --seed` produces a runnable app
- [ ] A user can log in and is redirected to a role-appropriate landing page
- [ ] Hitting a route gated by a different role returns 403
- [ ] Feature test asserts each role's middleware behavior

---

## Phase 2: Events CRUD

**User stories**: 1, 10 (partial — date/status filters; client filter lands
with phase 11).

### What to build

Coordinator can create, edit, list, and view events with name, description,
venue, `starts_at`, `ends_at`. Index page supports filtering by date range
and event status (upcoming / ongoing / past, derived from datetimes).

### Acceptance criteria

- [ ] Coordinator creates an event via form; it appears in the index
- [ ] Validation rejects `ends_at < starts_at`
- [ ] Index filters work and are reflected in the URL query string
- [ ] Staff and client roles cannot reach the create/edit routes

---

## Phase 3: Suppliers, categories & event items

**User stories**: 2, 3, 4.

### What to build

CRUD for `suppliers` and `item_categories`. On the event detail page, the
coordinator adds `event_items` (name, category, supplier, quantity, rental
cost, condition enum: `available | in_use | returned`). Items are grouped
by category in the UI.

### Acceptance criteria

- [ ] Supplier and category CRUD pages work end-to-end
- [ ] Coordinator adds an item to an event; it shows under its category group
- [ ] Item edit preserves history (no destructive overwrites of condition)
- [ ] Feature test covers the full add-item-to-event flow

---

## Phase 4: Direct task assignment + staff receive flow

**User stories**: 5, 11, 12, 14, 19, 20.

### What to build

Coordinator assigns a `receive` task on a specific `event_item` to a specific
staff member. Staff sees their personal task list, can `accept` (pending →
accepted) and `mark delivered` (accepted → delivered). Task detail shows
supplier contact and event venue. Implements `Task::transitionTo()` and
guards invalid transitions.

### Acceptance criteria

- [ ] Coordinator assigns a task; staff sees it in `/tasks`
- [ ] Staff transitions the task through accept → delivered
- [ ] Invalid transitions (e.g. pending → returned) return a 422 / show error
- [ ] Unit tests cover all valid and invalid transitions
- [ ] Feature test covers the assign → accept → deliver flow

---

## Phase 5: Open task pool

**User stories**: 6, 13.

### What to build

Coordinator can post a task without an assignee. `/tasks/pool` lists all
unassigned pending tasks. Any staff member can claim one; claim uses an
atomic `UPDATE` so concurrent claims never both succeed.

### Acceptance criteria

- [ ] Coordinator posts an unassigned task; it appears in the pool
- [ ] Staff claims a task; it moves out of the pool into their personal list
- [ ] Concurrent-claim feature test (two simultaneous claims) — exactly one wins
- [ ] Claimed task follows the same state machine as directly assigned tasks

---

## Phase 6: Coordinator task board & event filters

**User stories**: 7, 10 (completes filter coverage from phase 2).

### What to build

Per-event task board showing every item, its current task status, and
assignee, grouped by status column or category. Adds remaining event-index
filters (by client once phase 11 lands; placeholder field is fine now).

### Acceptance criteria

- [ ] Event detail page renders a board with all tasks and statuses at a glance
- [ ] Status counts (pending / accepted / delivered / returned) are visible
- [ ] Page loads with reasonable performance for an event with 50+ items

---

## Phase 7: Auto return-task creation + return flow

**User stories**: 8, 15.

### What to build

`php artisan tasks:create-returns` scheduled command runs every 5 minutes,
finds events whose `ends_at` has passed, and creates `pending` tasks of type
`return` for every `event_item` that has a `delivered` task without a
matching `return` task. Staff can mark `delivered → returned` on these tasks.
Idempotent: a second run creates no duplicates.

### Acceptance criteria

- [ ] Feature test: simulating an event ending creates exactly one return task
      per delivered item
- [ ] Running the command twice does not create duplicate return tasks
- [ ] Staff transitions a return task from delivered → returned (the item's
      `condition` may be updated in the same form)
- [ ] Scheduler is registered in `app/Console/Kernel.php`

---

## Phase 8: In-app notifications

**User stories**: 16.

### What to build

`TaskAssigned` and `TaskStatusChanged` notifications dispatched via Laravel's
notification system on the `database` channel. Bell/badge in the top nav
shows unread count; `/notifications` lists them and marks read on view.

### Acceptance criteria

- [ ] Assigning a task creates a `notifications` row for the target user
- [ ] Bell badge updates on next page load (polling/refresh acceptable)
- [ ] Marking a notification read clears it from the unread badge
- [ ] Feature test asserts notification creation on assign and status change

---

## Phase 9: Email notifications

**User stories**: 17.

### What to build

Add the `mail` channel to the same notification classes from phase 8. SMTP
config via `.env`. Mailable templates for assignment and key status changes.

### Acceptance criteria

- [ ] Assigning a task sends an email (asserted via `Mail::fake()`)
- [ ] Email contains task summary, event venue, and supplier contact
- [ ] User-level preference scaffolded (even if default-on for MVP)

---

## Phase 10: WhatsApp / SMS notifications

**User stories**: 18.

### What to build

Custom Laravel notification channel wrapping a Brazilian-friendly provider
(Z-API or Evolution API; Twilio as fallback). Adds the channel to the same
notification classes. Uses `users.whatsapp_number`. Provider credentials in
`.env`; outbound calls are queued.

### Acceptance criteria

- [ ] Assigning a task enqueues a WhatsApp/SMS job (asserted via fake)
- [ ] Failed sends are retried via the queue with backoff
- [ ] Provider is swappable behind a single channel class
- [ ] Manual smoke test sends a real message to a test number

---

## Phase 11: Client portal

**User stories**: 21, 22, 23.

### What to build

`events.client_user_id` links an event to a client. Client login lands on
`/client/events` (only their events). Per-event view shows each item's
delivery status (expected / delivered / returned) and a "pending delivery"
section flagging items still in `pending`/`accepted`. Read-only.

### Acceptance criteria

- [ ] Client sees only events where they are `client_user_id`
- [ ] Per-event page shows item statuses without exposing internal task
      assignees or costs
- [ ] Client cannot reach any coordinator or staff routes (403)
- [ ] Feature test asserts cross-tenant isolation between two client users

---

## Phase 12: Item history timeline

**User stories**: 9.

### What to build

Per-`event_item` (or across an item-name aggregation, TBD during phase) view
showing every event it appeared in, condition changes, and which staff
handled each task. Source data is the existing tasks + condition columns —
no new schema unless aggregation requires it.

### Acceptance criteria

- [ ] Coordinator opens an item history page and sees a chronological
      timeline of events, statuses, and handlers
- [ ] Condition changes are visible with timestamps
- [ ] Page loads in reasonable time for items with long histories

---

## Verification (whole MVP)

End-to-end smoke after all phases land:

1. `php artisan migrate:fresh --seed`
2. `php artisan serve` and `php artisan schedule:work` in parallel
3. As coordinator: create supplier, category, event, add 3 items, assign 1
   task directly, post 1 to the pool, leave 1 unassigned
4. As staff A: claim the pooled task, accept the direct task, deliver both
5. Wait/advance time past `ends_at`; confirm return tasks auto-create
6. As staff A: mark both returned, noting condition on one
7. As client: log in, see the event, see all items as `returned`
8. Confirm in-app, email, and WhatsApp notifications fired at each
   assignment (check `notifications` table, mail log, provider dashboard)
9. Run the full test suite: `php artisan test`
