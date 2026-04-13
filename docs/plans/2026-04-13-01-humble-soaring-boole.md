# PRD: Santostok — Event Rental Stock Manager

## Context

Event production companies rent equipment ("alugados") from suppliers for each event. Currently there is no system to track which items are expected, who is responsible for receiving them, when they arrive, and when they are returned. This PRD defines the MVP for Santostok, a web application that manages rented stock across the lifecycle of an event.

---

## Problem Statement

Event coordinators have no centralized way to manage rented equipment for events. Tasks like "receive the sound box from supplier X at 20:00" are communicated informally (chat, phone), leading to missed deliveries, unreturned items, and no visibility for clients. There is no stock history, no accountability per staff member, and no automated follow-up for returns.

---

## Solution

Santostok is a web application that allows event coordinators to register events with their required rented items (grouped by category), assign tasks to staff for receiving and returning equipment, track each item's status through its lifecycle, and automatically trigger return tasks when an event ends. Staff receive multi-channel notifications (in-app, email, WhatsApp/SMS) and can self-assign tasks from an open pool.

---

## User Stories

### Event Coordinator

1. As a coordinator, I want to create an event with a name, description, start/end datetime, and venue, so that I have a central record for each production.
2. As a coordinator, I want to add rented items to an event grouped by category (audio, lighting, furniture, etc.), so that the stock list is organized and easy to scan.
3. As a coordinator, I want to register suppliers with contact information, so that staff know who to coordinate with for delivery and pickup.
4. As a coordinator, I want to associate a rented item with a specific supplier, quantity, rental cost, and current condition, so that I have full visibility into what is coming from where.
5. As a coordinator, I want to assign a task directly to a specific staff member, so that accountability is clear.
6. As a coordinator, I want to post a task to an open pool so that any available staff member can claim it.
7. As a coordinator, I want to see the status of all tasks for an event in a single dashboard, so that I can monitor progress in real time.
8. As a coordinator, I want the system to automatically create return tasks for all items when an event ends, so that nothing gets forgotten.
9. As a coordinator, I want to view the full history of an item (which events it was used in, condition changes, who handled it), so that I can track wear and accountability.
10. As a coordinator, I want to filter and search events by date, status, and client, so that I can quickly find what I need.

### Staff / Volunteer

11. As a staff member, I want to see all tasks assigned to me, so that I know what I am responsible for.
12. As a staff member, I want to accept a task assigned to me, so that the coordinator knows I acknowledged it.
13. As a staff member, I want to claim an unassigned task from the open pool, so that I can take ownership voluntarily.
14. As a staff member, I want to mark a task as "delivered" when I have received the item from the supplier, so that the stock is updated.
15. As a staff member, I want to mark a task as "returned" when I have sent the item back to the supplier, so that the lifecycle is closed.
16. As a staff member, I want to receive an in-app notification when a task is assigned to me, so that I am aware immediately.
17. As a staff member, I want to receive an email notification when a task is assigned to me, so that I have a written record.
18. As a staff member, I want to receive a WhatsApp or SMS notification when a task is assigned to me, so that I am alerted even if I am not in the app.
19. As a staff member, I want to see the supplier's contact details on my task, so that I can coordinate delivery directly.
20. As a staff member, I want to see the event venue on my task, so that I know where to bring the item.

### Event Client / Owner

21. As a client, I want to log in and see the events associated with my account, so that I have visibility without needing to call the coordinator.
22. As a client, I want to see the status of each rented item for my event (expected, delivered, returned), so that I know the production is on track.
23. As a client, I want to see which items are still pending delivery, so that I can flag concerns to the coordinator early.

---

## Implementation Decisions

### Modules

- **Auth & Roles**: Three roles — `coordinator`, `staff`, `client`. Role-based access control gates what each actor can see and do.
- **Event Module**: CRUD for events. An event has a name, description, start datetime, end datetime, and venue. When `end datetime` passes, the system triggers automatic return task creation for all unresolved items.
- **Item & Category Module**: Items belong to categories. Each item record on an event includes: name, category, quantity, supplier, rental cost, and condition/status enum (`available`, `in_use`, `returned`).
- **Supplier Module**: Supplier entity with name, phone, email, and associated items. Coordinators manage suppliers independently of events.
- **Task Module**: Tasks link a user, an event, and a rented item. Status machine: `pending → accepted → delivered → returned`. Tasks can be directly assigned or posted to an open pool.
- **Notification Module**: Sends notifications via three channels (in-app, email, WhatsApp/SMS) on task assignment and status changes. In-app uses a bell/badge system. Email via SMTP. WhatsApp/SMS via a third-party API (e.g. Twilio or Z-API).
- **Dashboard**: Coordinator view showing event task board (all items, statuses, assignees). Staff view showing personal task list. Client view showing item delivery status for their events.

### Architecture

- **Backend**: Laravel (PHP) with PostgreSQL database. RESTful API with Blade views or a decoupled JSON API.
- **Database key relationships**:
  - `events` hasMany `event_items` (pivot with category, quantity, cost, condition)
  - `event_items` belongsTo `suppliers`
  - `tasks` belongsTo `event_items`, `users`, `events`
  - `notifications` polymorphic to `tasks`
- **Return task auto-creation**: A scheduled job (Laravel scheduler) checks for events whose `end_at` has passed and creates `pending` return tasks for any item that has a `delivered` (but not yet `returned`) task.
- **Open task pool**: Tasks with `assigned_user_id = null` and status `pending` are visible to all staff as claimable.

### Schema (key tables)

- `users`: id, name, email, phone, role, whatsapp_number
- `events`: id, name, description, venue, starts_at, ends_at, client_user_id
- `suppliers`: id, name, email, phone
- `item_categories`: id, name
- `event_items`: id, event_id, supplier_id, item_category_id, name, quantity, rental_cost, condition
- `tasks`: id, event_item_id, event_id, assigned_user_id (nullable), status (enum), type (receive|return), notes
- `notifications`: id, user_id, task_id, channel, read_at, sent_at

---

## Testing Decisions

### What makes a good test

Tests should verify external behavior — what the system does, not how it does it internally. Test state transitions (task goes from `pending` to `accepted` when claimed), not method calls. Do not test private methods or mock internal services unless at a system boundary.

### Modules to test

- **Task status machine**: Unit tests for all valid and invalid transitions (e.g. cannot go from `pending` to `returned` directly).
- **Return task auto-creation**: Feature test that simulates an event ending and asserts that `return` tasks are created for all `delivered` items.
- **Role-based access**: Feature tests asserting that `client` cannot create tasks, `staff` cannot create events, `coordinator` can do everything.
- **Task pool claiming**: Feature test asserting that a staff member can claim an unassigned task and it becomes assigned to them.
- **Notification dispatch**: Feature test that asserts notifications are queued/sent on task assignment (mock external channels, assert dispatch).

---

## Out of Scope

- Payment processing or invoicing for rentals
- Item ownership (buying vs. renting distinction)
- Multi-tenancy / multiple companies sharing the platform
- Mobile native app (web-only MVP)
- Real-time push updates (WebSockets) — polling or page refresh is acceptable for MVP
- Supplier portal (suppliers do not log in)
- Item photos or attachments

---

## Further Notes

- The name "Santostok" suggests the product may be positioned for a specific company ("Santos") or the Santos/SP event market in Brazil.
- WhatsApp notification integration should use a Brazilian-friendly provider (Z-API, Evolution API, or Twilio with WhatsApp Business API).
- The auto-return task scheduler should be idempotent — running it twice should not create duplicate return tasks.
- "Condition" tracking on items is important for accountability: a coordinator should be able to note if an item came back damaged.
