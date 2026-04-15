# Plan: Phase 5 — Receipts + Stock Lots + Attachments

> Source: `docs/plans/2026-04-13-05-plan-pivot-inventory-warehouse.md` (Phase 5)
> PRD: `docs/plans/2026-04-13-04-prd-pivot-inventory-warehouse.md`
> User stories: 15, 16, 22 (partial)
>
> **Revision — 2026-04-14:** this plan was rewritten after a `/grill-me` pass resolved 23 decision branches. The original reversal-via-`reverses_movement_id` design, the polymorphic attachments design, the per-mount synchronous uploader, and the role-name-based authorization were all replaced. See the "Decisions made during grilling" section at the bottom for the full list.

## Context

Phase 4 delivered Pedidos and the `OrderStatus` state machine but no stock moves. Phase 5 is the first movement slice: a user picks an order, records a receipt against it (quantity + ≥1 photo), and the system creates a `stock_lot` at the target warehouse, writes an immutable signed-quantity `stock_movement` entry, stores the attachments asynchronously, and auto-transitions the order status. Admins can file a **correction** against a receipt; corrections create a new negative `receipts` row (symmetric with the ledger) and rewind order status via a dedicated state-machine helper.

This phase also establishes long-lived infrastructure that Phases 6 (transfers) and 7 (returns) will reuse without revisiting: the attachments upload pipeline with async processing, the `stock_lots` / `stock_movements` tables, the idempotency contract, a permission-based authorization system (replacing the demo role-name check), and HEIC support for iPhone camera uploads.

## Architectural decisions

### Authorization: permissions, not role names

**Demo role names (`Administrador`, `Operador`) are user-configurable labels.** Users can rename them or create new groups through a future admin UI, so every authorization check must gate on a **permission**, never a role name.

Permissions introduced in Phase 5:

- `orders.view`
- `receipts.create`
- `receipts.view`
- `receipts.correct`
- `attachments.view`
- `attachments.manage` (admin override of the 15-minute owner window on attachment edits)

A new `PermissionSeeder` populates the `permissions` table. `RoleSeeder` is extended to attach permission sets to the demo roles:

- `Administrador` → all permissions.
- `Operador` → `orders.view`, `receipts.create`, `receipts.view`, `attachments.view`.

Authorization happens through policies (`ReceiptPolicy`, `AttachmentPolicy`) using `$user->can('permission.name')`. The two existing role-name checkpoints from Phase 4 (`AdministradorRequest` + one `role:administrador` middleware group in `routes/web.php`) are refactored to permission-based checks in Commit 1; Phase 4 tests updated accordingly.

**Abstract `OperadorOrAdministradorRequest` is NOT introduced.** It would hardcode role names, exactly what we are avoiding.

### Routes

- `GET /pedidos/{order}/recebimentos/create` → `ReceiptController@create`.
- `POST /pedidos/{order}/recebimentos` → `ReceiptController@store` — accepts quantity + warehouse_id + photos + idempotency_key.
- `POST /recebimentos/{receipt}/corrigir` → `CorrectReceiptController` (invokable). UI label stays "Estornar"; route path uses `/corrigir` to reflect the forward-only correction semantics.
- `DELETE /recebimentos/{receipt}/attachments/{attachment}` → `AttachmentController@destroy` — soft-deletes an attachment; gated by the 15-minute owner window or `attachments.manage`.
- `GET /attachments/{attachment}/thumbnail` and `GET /attachments/{attachment}/original` → `AttachmentViewController` — authorizes the viewer, then 302-redirects to a freshly-signed 10-minute URL.

All routes sit inside `Route::middleware(['auth', 'verified'])`. Authorization is handled by policies inside each FormRequest / controller — no role-based middleware groups.

### Schema

- **`attachments`** (non-polymorphic):
  - `id`, `receipt_id` (FK restrict), `path`, `thumbnail_path`, `original_filename`, `mime`, `size` (unsigned int, bytes), `sha256` (char 64), `creator_id` (FK users nullable, nullOnDelete), `deleted_at` (nullable timestamp, for soft-delete), timestamps.
  - Indexes: `receipt_id`, `creator_id`, `(receipt_id, deleted_at)`.
  - **Not polymorphic.** Phase 6/7 will add their own attachment tables, or refactor to polymorphic once the second owner's rules are understood — an informed decision, not a forward guess.
- **`stock_lots`**:
  - `id`, `product_id` (FK restrict), `warehouse_id` (FK restrict), `receipt_id` (FK restrict), timestamps.
  - Indexes: `(product_id, warehouse_id)`, `receipt_id`.
  - **No `parent_lot_id`** — deferred to Phase 6 when transfers actually need it.
  - **No `current_balance`** — balance is computed on demand via `SUM(stock_movements.quantity)` grouped by `stock_lot_id`. Single source of truth.
- **`stock_movements`** (append-only ledger, forward-only, signed quantity):
  - `id`, `stock_lot_id` (FK restrict), `warehouse_id` (FK restrict — denormalized copy of the lot's warehouse for query ergonomics), `user_id` (FK nullable, nullOnDelete), `type` (string; Phase 5 values: `receipt`, `receipt_correction`), `quantity` (**signed** integer — no DB `> 0` check), `idempotency_key` (string), `corrects_movement_id` (FK self-ref nullable, nullOnDelete — audit link, NOT a unique index), timestamps.
  - Unique index: `(user_id, idempotency_key)`.
  - Plain indexes: `(stock_lot_id, created_at)`, `type`, `warehouse_id`, `corrects_movement_id`.
  - **No `reverses_movement_id`**, no unique index on it — the forward-only model needs neither.
- **`orders`** augment (new migration, not an edit):
  - Add `warehouse_id` (FK warehouses nullable, nullOnDelete). Set by `CreateReceiptAction` on the first receipt; null until then. Enforces warehouse-lock across the order's receipts.
- **`receipts`** augment (new migration):
  - Add `warehouse_id` (FK restrict), `user_id` (FK nullable, nullOnDelete), `idempotency_key` (string), `reason` (text nullable — required at FormRequest level on corrections only), `corrects_receipt_id` (FK self-ref nullable, nullOnDelete).
  - Keep existing `order_id` + `quantity`.
  - **Drop** any positive-quantity DB constraint — corrections are encoded as negative `receipts.quantity` rows.
  - Unique index on `(user_id, idempotency_key)` mirrors the movements index.

### Attachments pipeline — async

- **Driver**: S3-compatible via `league/flysystem-aws-s3-v3`. Dev uses the existing RustFS container (`http://rustfs:7081`, path-style). Prod target is DO Spaces.
- **Disk**: `spaces` (new named disk in `config/filesystems.php`). `FILESYSTEM_DISK=local` stays.
- **HEIC support**: `libheif1` + `libheif-dev` added to the Sail Dockerfile. Intervention Image v3 reads HEIC natively once the extension is present. iPhone operators upload directly; no client-side conversion needed.
- **Flow (upload-first, transact-second, process-async):**
  1. `CreateReceiptAction` receives the request. **Before opening any DB transaction**, each `UploadedFile` is streamed to `attachments/raw/{uuid}.bin` on the `spaces` disk. File SHA-256 of the raw bytes is captured.
  2. A DB transaction opens; it contains only DB writes (`orders` lock, `receipt` row, `stock_lot` row, `stock_movement` row, stub `attachments` rows with `path = raw/{uuid}.bin`, `thumbnail_path = null`). Lock hold time is milliseconds.
  3. Transaction commits. The action dispatches one `ProcessAttachmentJob` per stub attachment row.
  4. Each job, in a queue worker: decodes the raw blob (including HEIC), strips EXIF, re-encodes to JPEG at 85% quality, generates a 400px (longest-edge, aspect-preserved) thumbnail, computes SHA-256 of the re-encoded original bytes, writes both as `attachments/{uuid}-original.jpg` and `attachments/{uuid}-thumb.jpg`, updates the `attachments` row with the final paths + sha256, deletes the raw blob.
- **Failure handling**: a failed `ProcessAttachmentJob` leaves the attachment row with `path = raw/...` — visible in admin audit as "falhou". Standard Laravel `failed_jobs` retry flow. The receipt itself is unaffected. Orphaned raw blobs on transaction failure (upload succeeded, tx rejected) are an accepted MVP trade-off; a janitor job is deferred.
- **Delivery**: attachment metadata is returned in Inertia responses with attachment IDs only — **no eager signed URLs**. The frontend resolves thumb/original URLs through Wayfinder-typed routes (`AttachmentViewController@thumbnail`, `@original`) that 302-redirect to a freshly-signed 10-min URL after authorization. Avoids computing hundreds of signed URLs per list-view render.
- **Limits**: 1..10 files per receipt; ≥1 required on receipts. Enforced in `SaveReceiptRequest`.

### Attachment lifecycle

- **15-minute owner window**: the creator may soft-delete their own attachment up to 15 minutes after upload, and may also upload a replacement (new row). After 15 minutes, or once any correction exists on the receipt, the window closes.
- **Admin override**: any user with `attachments.manage` (Administrador by default) may soft-delete at any time.
- **Soft-delete**: `attachments.deleted_at` nullable timestamp; the default model scope filters deleted rows; admin audit views show all.
- **S3-object lifecycle**: soft-deleted rows' blobs remain in object storage. A janitor job for orphan/tombstoned blobs is deferred.

### Receipt creation semantics

`app/Actions/Receipts/CreateReceiptAction::execute(array $input, User $user)`:

1. **Outside any transaction**: stream each uploaded file to `attachments/raw/{uuid}.bin` on the `spaces` disk. Capture raw SHA-256s. If any upload fails, abort and return 500 — no DB writes yet, no orphans worth chasing.
2. Open `DB::transaction()`.
3. Look up `$order = Order::where('id', $input['order_id'])->lockForUpdate()->firstOrFail()`.
4. **Warehouse lock**: if `$order->warehouse_id` is not null, reject 422 if `$input['warehouse_id'] !== $order->warehouse_id`. If null, stamp it with the input's `warehouse_id`.
5. **Idempotency**: `StockMovement::where('user_id', $user->id)->where('idempotency_key', $input['idempotency_key'])->first()`. If present → return the associated `Receipt`, no writes. Prevents double-click duplicates.
6. **Saldo check**: `saldo = $order->ordered_quantity - $order->receipts()->sum('quantity')`. (Sum includes correction rows, which are negative — `sum` still yields net received.) Reject 422 if `input.quantity > saldo`.
7. Create `Receipt` (`order_id`, `warehouse_id`, `quantity` (positive), `user_id`, `idempotency_key`, `reason = null`, `corrects_receipt_id = null`).
8. Create `StockLot` with `product_id = order.product_id`, `warehouse_id = input.warehouse_id`, `receipt_id = receipt.id`.
9. Create `StockMovement`: `type=receipt`, `stock_lot_id`, `warehouse_id`, `user_id`, `quantity = +receipt.quantity`, `idempotency_key`.
10. For each pre-uploaded raw blob, create a stub `Attachment` row: `receipt_id`, `path = raw/{uuid}.bin`, `thumbnail_path = null`, `original_filename`, `mime`, `size`, `sha256` (of the raw bytes — will be overwritten by the job with the re-encoded sha256), `creator_id = user.id`.
11. **Auto-transition**: recompute `saldo` after this insert. If `saldo == 0` → `Open|PartiallyReceived → FullyReceived` (skipping `PartiallyReceived` when a single receipt fully satisfies the order is allowed by the existing enum). Else if previous status was `Open` → `Open → PartiallyReceived`. Use `OrderStatus::canTransitionTo()` to guard; a rejection here is a bug, surface a 500.
12. Commit.
13. **After commit**: dispatch one `ProcessAttachmentJob` per stub attachment row.
14. Return `Receipt`.

### Correction semantics (forward-only, replaces "reversal")

`app/Actions/Receipts/CorrectReceiptAction::execute(Receipt $receipt, int $deltaQuantity, string $reason, User $admin)`:

The admin's UI sends a *delta* (typically the full negative of the original quantity, but partial corrections are also valid — e.g., operator recorded 50, actual was 45 → delta = -5).

1. Admin-only: policy checks `receipts.correct`.
2. `DB::transaction()` + `lockForUpdate` on the parent order.
3. **Terminal status guard**: if order is `Cancelled` → 422 with a clear message (reopen flow is out of scope for Phase 5). If `ClosedShort`, proceed — `canRewindTo()` allows it.
4. **Double-correction guard**: if any `receipts.corrects_receipt_id = $receipt->id` exists and the net on this receipt is already zero, reject 422. (Multiple partial corrections are allowed as long as they don't over-correct.)
5. **Over-correction guard**: the net quantity on `$receipt` after applying `$delta` must be ≥ 0. Reject 422 otherwise.
6. Create a new `Receipt` row: `order_id = $receipt->order_id`, `warehouse_id = $receipt->warehouse_id` (same warehouse), `quantity = $delta` (negative), `user_id = $admin->id`, `idempotency_key = uuid()` (server-generated), `reason` (required, 1–500 chars), `corrects_receipt_id = $receipt->id`.
7. Create a new `StockMovement`: `type=receipt_correction`, `stock_lot_id = $receipt->lot->id`, `warehouse_id`, `user_id = $admin->id`, `quantity = $delta` (signed), fresh `idempotency_key`, `corrects_movement_id = original movement id`.
8. Recompute order saldo. Use `OrderStatus::canRewindTo()` to advance to the correct rewound state:
   - `FullyReceived → PartiallyReceived` when saldo becomes > 0.
   - `FullyReceived → Open` when saldo becomes equal to `ordered_quantity`.
   - `PartiallyReceived → Open` when saldo becomes equal to `ordered_quantity`.
   - `ClosedShort → PartiallyReceived` when saldo becomes > 0 and < ordered.
   - `ClosedShort → Open` when saldo becomes equal to `ordered_quantity`.
   - A rejection from `canRewindTo()` is a bug — surface a 500.
9. Commit.
10. No new attachments required on corrections; optional attachments allowed (reuse the `CreateReceiptAction` upload flow).

### `OrderStatus::canRewindTo()` addition

New method on the enum, companion to `canTransitionTo()`. Separate allow-list to keep forward business transitions distinct from correction-driven rewinds:

| From | Allowed rewind destinations |
|---|---|
| `FullyReceived` | `PartiallyReceived`, `Open` |
| `PartiallyReceived` | `Open` |
| `ClosedShort` | `PartiallyReceived`, `Open` |
| `Open`, `Cancelled` | (none) |

### Idempotency

- Client generates a UUID v4 per form mount; sent as `idempotency_key`.
- Server stores it on the `StockMovement` row, unique per user.
- Repeat submission with the same key returns the existing `Receipt`, no DB writes.
- Mirror on `receipts.idempotency_key` ensures a user can't sneak a duplicate via two different movements pointing at different receipts.
- **Cross-mount idempotency (reload / crash recovery)** is out of scope — users recover via admin correction.

### Pest browser test infrastructure — deferred

No browser tests in Phase 5. Feature tests use `UploadedFile::fake()->image(...)` to cover upload wiring end-to-end on the Laravel side. UI wiring is verified manually per the verification section. Browser infra lands in a dedicated later phase and gets reused by Phases 6/7.

## Commit sequence

7 commits on branch `feat/phase-5-receipts`. Each keeps `php artisan test --compact` and `npm run build` green.

### Commit 1 — `chore(phase-5): infra — spaces disk, libheif, permissions seeder, queue worker docs`

- `composer require intervention/image league/flysystem-aws-s3-v3`.
- Sail Dockerfile: add `libheif-dev` and `libheif1` (and any GD / Imagick bindings required). Rebuild image.
- `config/filesystems.php`: add `spaces` disk with `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT`, bucket + key/secret envs. Not the default disk.
- `.env` / `.env.example`: RustFS-ready defaults (`AWS_ENDPOINT=http://rustfs:7081`, `AWS_USE_PATH_STYLE_ENDPOINT=true`).
- `santostok-attachments` bucket created in RustFS via artisan command or documented README step.
- `PermissionSeeder` (new): 6 permissions — `orders.view`, `receipts.create`, `receipts.view`, `receipts.correct`, `attachments.view`, `attachments.manage`.
- `RoleSeeder` (extended): attaches permission sets to demo roles as specified.
- Refactor `AdministradorRequest` to authorize via `$user->can('…')` (the appropriate permission for its callsites) — or delete it entirely if it becomes redundant with policies.
- Refactor the `Route::middleware('role:administrador')` group in `routes/web.php` to `auth`-only middleware; authorization moves into FormRequest `authorize()` / policies on each controller action.
- Update Phase 4 tests that relied on role assignment to instead grant explicit permissions via `givePermissionTo()` — with a sibling `RoleSeederTest` pinning the role↔permission contract.
- README: document the `queue:work` requirement; add to `composer run dev` (or a sibling `composer run queue` target).
- Tests: `tests/Feature/Auth/RoleSeederTest.php` (asserts each demo role has the expected permission set).

### Commit 2 — `feat(receipts): migrations + models for stock_lots, stock_movements, attachments + receipts/orders augment`

- Migrations:
  - `create_stock_lots_table`
  - `create_stock_movements_table`
  - `create_attachments_table` (non-polymorphic; `receipt_id` FK)
  - `add_warehouse_id_to_orders_table`
  - `augment_receipts_table` (`warehouse_id`, `user_id`, `idempotency_key`, `reason`, `corrects_receipt_id`; any positive-quantity constraint dropped)
- Models: `StockLot`, `StockMovement`, `Attachment` with the relations listed above. Augment `Receipt` (fillable, `warehouse()`, `user()`, `attachments()` hasMany, `movement()`, `corrects()` / `correctedBy()`).
- Factories for all three new models + updated `ReceiptFactory` with a `correction()` state that produces a negative-quantity row with `corrects_receipt_id` set.
- Test: `tests/Feature/Stock/SchemaShapeTest.php` asserting FKs, indexes, signed-quantity column (no DB-level `> 0` check), `orders.warehouse_id`.

### Commit 3 — `feat(receipts): AttachmentUploader + ProcessAttachmentJob + AttachmentViewController + uploader tests`

- `app/Services/AttachmentUploader.php` — persists raw bytes to `attachments/raw/{uuid}.bin` on `spaces`, returns `{uuid, original_filename, mime, size, sha256}` tuples. No DB writes — caller persists the stub row inside its transaction.
- `app/Jobs/ProcessAttachmentJob.php` — reads the raw blob, decodes (including HEIC via libheif + Intervention v3), strips EXIF, re-encodes JPEG 85%, generates 400px thumb, writes final paths, updates the `attachments` row, deletes the raw blob. Standard Laravel retry / `failed_jobs` semantics.
- `app/Http/Controllers/Attachments/AttachmentViewController.php` — invokable methods `thumbnail` and `original`. Each authorizes via `AttachmentPolicy` (see next bullet), then `return redirect()->to($this->disk->temporaryUrl($path, now()->addMinutes(10)))`.
- `app/Policies/AttachmentPolicy.php`:
  - `view(User $u, Attachment $a)` → `$u->can('attachments.view')` AND `$a->deleted_at === null` (except for `attachments.manage` holders, who can see soft-deleted).
  - `delete(User $u, Attachment $a)` → (`$u->id === $a->creator_id` AND `$a->created_at->diffInMinutes(now()) < 15` AND `!receiptHasCorrections($a->receipt)`) OR `$u->can('attachments.manage')`.
- Wire `Attachment` to a default scope filtering `deleted_at IS NULL`; add a `withTrashed` scope or a dedicated admin query path for audit views.
- Tests (`tests/Feature/Attachments/`):
  - `AttachmentUploaderTest.php` — happy path writes raw blob, returns tuple; non-image rejection, oversize rejection (at FormRequest level, not service — document in test comments).
  - `ProcessAttachmentJobTest.php` — happy path (JPEG in → JPEG out + thumb + sha256 + raw deleted), HEIC input (uses a 1×1 `.heic` fixture at `tests/fixtures/photo.heic`), retry on transient failure, `failed_jobs` on permanent failure.
  - `AttachmentViewControllerTest.php` — authorized viewer gets 302 with signed URL, unauthorized user gets 403, soft-deleted blocked for non-admin.
- Mock `Queue::fake()` and `Storage::fake('spaces')` where appropriate; one integration test uses real Intervention against the fixture.

### Commit 4 — `feat(receipts): CreateReceiptAction + ReceiptController + auto-transition`

- `app/Actions/Receipts/CreateReceiptAction.php` implementing the 14 steps above.
- `app/Http/Controllers/Receipts/ReceiptController.php` with `create` + `store`; `store` dispatches to the action.
- `app/Http/Requests/Receipts/SaveReceiptRequest.php`:
  - `authorize()` → `$this->user()->can('receipts.create')`.
  - Rules: `warehouse_id` exists; `quantity` int ≥ 1 ≤ saldo; `photos` array 1..10 required; each photo `image|mimes:jpeg,png,webp,heic,heif|max:10240` (10 MB); `idempotency_key` required uuid.
- `app/Policies/ReceiptPolicy.php` (new, minimal — just `create` for now; `correct` lands in Commit 5).
- Routes in `routes/web.php` inside `Route::middleware(['auth', 'verified'])`. No role-based middleware.
- Tests (`tests/Feature/Receipts/`):
  - `CreateReceiptTest.php` — with-permission happy path (admin + operador both work via `receipts.create`); without-permission forbidden; unauthenticated guest redirected to login; Inertia response shape.
  - `ReceiptValidationTest.php` — missing/invalid warehouse, quantity over saldo, zero or 11 files, non-image bytes.
  - `ReceiptIdempotencyTest.php` — same key twice → one row + same response.
  - `ReceiptAutoTransitionTest.php` — partial receipt flips `Open→PartiallyReceived`; final partial flips to `FullyReceived`; **single-shot full receipt flips `Open→FullyReceived` directly**; saldo computed correctly including corrections.
  - `ReceiptWarehouseLockTest.php` — first receipt stamps `orders.warehouse_id`; second receipt with different warehouse → 422.
  - `ReceiptConcurrencyTest.php` — two parallel `CreateReceiptAction` calls with different keys and quantities that together exceed saldo → one succeeds, one 422s. Implementation either via `pcntl_fork` or documented TODO if Sail's runner doesn't support it.
  - `AttachmentAsyncTest.php` — after `store`, assert a `ProcessAttachmentJob` was dispatched per photo (via `Queue::fake`); run one inline against a real fixture to assert final paths land.

### Commit 5 — `feat(receipts): CorrectReceiptAction + correction endpoint + canRewindTo`

- `app/Enums/OrderStatus.php` — add `canRewindTo(self $next): bool` with the matrix above. `nextAllowed()` stays untouched.
- `app/Actions/Receipts/CorrectReceiptAction.php` implementing the correction semantics.
- `app/Http/Controllers/Receipts/CorrectReceiptController.php` (invokable).
- `app/Http/Requests/Receipts/CorrectReceiptRequest.php`:
  - `authorize()` → `$this->user()->can('receipts.correct')`.
  - Rules: `delta_quantity` integer < 0; `reason` required string 1..500.
- `ReceiptPolicy::correct(User, Receipt)` → permission + terminal-status guard.
- Route: `POST /recebimentos/{receipt}/corrigir` under the same auth middleware group.
- Tests (`tests/Feature/Receipts/CorrectReceiptTest.php`):
  - Admin corrects `FullyReceived` receipt → status rewinds to `PartiallyReceived`; a new negative `receipts` row with `corrects_receipt_id` exists; a new `stock_movements` row with `type=receipt_correction`, signed quantity, and `corrects_movement_id` exists.
  - Admin corrects the only receipt of an `Open→FullyReceived` order → status reverts to `Open`.
  - Admin corrects a receipt on a `ClosedShort` order → status rewinds to `PartiallyReceived` (or `Open` depending on magnitude).
  - Correction on a `Cancelled` order → 422 (reopen flow deferred).
  - Over-correction (delta would make receipt net negative) → 422.
  - Missing `reason` → 422.
  - Operador without `receipts.correct` permission → 403.
  - Two sequential partial corrections → both accepted; net on receipt tracked correctly.
  - Ledger integrity: `SUM(stock_movements.quantity) WHERE stock_lot_id = ?` equals the live sum; `receipts` table sum equals the derived saldo offset.

### Commit 6 — `feat(receipts): attachment owner window + soft delete + admin override`

- `app/Http/Controllers/Attachments/AttachmentController.php@destroy` (or extend the existing view controller).
- Policy check: `AttachmentPolicy::delete`.
- Soft-delete sets `attachments.deleted_at`.
- No new route is added for re-upload — the creator uses the existing receipt-update path (or, simplest: the existing Phase 5 UI only supports "delete + open a new correction"). Concrete UX: within 15 min the creator can remove a photo; to replace, they add a new photo via the correction flow or (if receipt is still editable) a dedicated endpoint to be scoped in Commit 7 UI.
- Tests (`tests/Feature/Attachments/AttachmentDeleteTest.php`):
  - Creator deletes within 15 min → soft-delete applied.
  - Creator deletes after 16 min → 403.
  - Creator deletes after any correction exists on the receipt → 403.
  - Admin with `attachments.manage` deletes at any time → success.
  - Non-creator operador without `attachments.manage` → 403.
  - Default index filters deleted; admin audit query returns deleted rows.

### Commit 7 — `feat(receipts): Vue pages`

- `resources/js/pages/Orders/Show.vue` gains a "Registrar recebimento" button (visible when `canCreateReceipt` is true, server-sent) opening the new `Receipts/Create.vue`.
- `resources/js/pages/Receipts/Create.vue`:
  - Warehouse select — pre-filled with the order's `warehouse_id` and disabled if set; otherwise a free select defaulting to the most recent warehouse this user received to.
  - Quantity input with `max = saldo`.
  - File input (multiple, `image/*,.heic,.heif`).
  - Client-generated `idempotency_key` via `crypto.randomUUID()`.
  - Submit via Inertia `useForm`.
- `Orders/Show.vue` receipts section:
  - List receipts with thumbnail strip. Thumbnails source through Wayfinder-typed URLs for `AttachmentViewController@thumbnail`. Thumbs missing a final path show a "Processando…" placeholder; Inertia polling (3s interval, auto-stops when all are ready) refreshes until final paths appear.
  - "Estornar" button on each receipt row, visible when `canCorrectReceipt` (server-sent). Opens a modal with delta-quantity input + required `reason` textarea.
  - "Remover foto" affordance next to each thumbnail, visible when `canDeleteAttachment` (server-sent, computed from the policy).
  - Correction rows (negative `receipts.quantity`) rendered distinctly under an "Ajustes" subsection, showing the reason.
- Wayfinder regenerates via `npm run build`.
- **No browser tests** (deferred).

## Critical files

### New
- `app/Actions/Receipts/CreateReceiptAction.php`
- `app/Actions/Receipts/CorrectReceiptAction.php`
- `app/Services/AttachmentUploader.php`
- `app/Jobs/ProcessAttachmentJob.php`
- `app/Http/Controllers/Receipts/ReceiptController.php`
- `app/Http/Controllers/Receipts/CorrectReceiptController.php`
- `app/Http/Controllers/Attachments/AttachmentController.php`
- `app/Http/Controllers/Attachments/AttachmentViewController.php`
- `app/Http/Requests/Receipts/SaveReceiptRequest.php`
- `app/Http/Requests/Receipts/CorrectReceiptRequest.php`
- `app/Policies/ReceiptPolicy.php`
- `app/Policies/AttachmentPolicy.php`
- `app/Models/{StockLot,StockMovement,Attachment}.php`
- `database/seeders/PermissionSeeder.php`
- `database/migrations/*_create_stock_lots_table.php`
- `database/migrations/*_create_stock_movements_table.php`
- `database/migrations/*_create_attachments_table.php`
- `database/migrations/*_add_warehouse_id_to_orders_table.php`
- `database/migrations/*_augment_receipts_table.php`
- `database/factories/{StockLotFactory,StockMovementFactory,AttachmentFactory}.php`
- `resources/js/pages/Receipts/Create.vue`
- `tests/Feature/Receipts/*.php`, `tests/Feature/Attachments/*.php`, `tests/Feature/Stock/SchemaShapeTest.php`, `tests/Feature/Auth/RoleSeederTest.php`
- `tests/fixtures/photo.heic`, `tests/fixtures/photo.jpg`

### Modify
- `app/Models/Receipt.php` (relations, `correction()` factory state, fillable)
- `app/Enums/OrderStatus.php` (`canRewindTo()`)
- `app/Http/Requests/AdministradorRequest.php` (refactor to permission-based, or delete if unused)
- `database/seeders/RoleSeeder.php` (attach permission sets)
- `config/filesystems.php` (`spaces` disk)
- `.env`, `.env.example` (AWS envs)
- `routes/web.php` (receipts + attachment routes; drop role middleware group)
- `resources/js/pages/Orders/Show.vue` (buttons, receipts list, thumbs, polling)
- Sail `Dockerfile` (`libheif`)
- `composer.json`, `package.json` (new deps)

## Patterns to reuse

- Phase 4 controller shape (`app/Http/Controllers/Orders/OrderController.php`) — pagination, Inertia render, eager-loading style.
- Phase 4 test patterns (`tests/Feature/Orders/`) — `beforeEach` seeding, user factory, 2FA helpers.
- Wayfinder-typed URLs — `import * as ReceiptController from '@/actions/…/ReceiptController'` in Vue; zero hardcoded paths.
- `to_route('orders.show', $order)` redirect pattern from Phase 4.
- `OrderStatus::canTransitionTo()` — for forward auto-transition on receipt creation.

## Verification

Per-commit and end-of-phase:

- `./vendor/bin/sail test --compact` — green. Phase adds ~40–50 new tests.
- `./vendor/bin/sail exec -T laravel.test vendor/bin/pint --dirty --format agent` — no diffs.
- `./vendor/bin/sail npm run build` — succeeds.
- Composer/npm audit — no new CRITICAL/HIGH.

**Manual smoke (12 steps):**

1. Admin logs in → opens an `Open` order → clicks "Registrar recebimento" → warehouse select is editable → uploads 1 JPEG, qty = half of ordered → submits → order show page lists the receipt with "Processando…" placeholder → thumbnail replaces within ~5s; status = "Recebido parcialmente".
2. Admin opens the same order → "Registrar recebimento" again → warehouse select is pre-filled **and disabled** → qty = remaining → submits → status = "Recebido integralmente".
3. Admin clicks "Estornar" on the last receipt → fills `reason` textarea (required) + delta = -remaining → confirms → negative `receipts` row appears under "Ajustes"; new `stock_movements` row with `type=receipt_correction`; status rewinds to "Recebido parcialmente".
4. Admin clicks "Estornar" again on the same (already net-zero) receipt → 422.
5. Operador logs in → can create a receipt (has `receipts.create`) but "Estornar" button is absent (no `receipts.correct`); direct POST to `/recebimentos/{id}/corrigir` returns 403.
6. Submit the create form with the same `idempotency_key` twice (devtools network replay) → only one receipt created.
7. Thumbnail click hits the Wayfinder proxy route → 302 to a fresh signed URL → original opens in new tab; each render produces a new signed URL.
8. Upload 3 photos → receipt row appears immediately with "Processando…" placeholders → Inertia polling replaces each within seconds as `ProcessAttachmentJob` completes.
9. Within 15 min of uploading, creator clicks "Remover foto" → photo disappears from the public view → admin audit view shows the soft-deleted row with `deleted_at`. After 15 min, "Remover foto" is hidden/disabled.
10. iPhone operator uploads a HEIC photo → `ProcessAttachmentJob` converts to JPEG → final thumb renders correctly.
11. First receipt on a new order stamps `orders.warehouse_id` → a follow-up receipt with a different warehouse_id returns 422 with a clear message.
12. Admin closes an 80/100 order short → corrects a receipt with delta = -5 → status rewinds to "Recebido parcialmente", saldo = 25.

**Ledger integrity check:**

```sql
-- Per lot, the ledger sum equals the derived balance.
SELECT stock_lot_id, SUM(quantity) AS ledger_balance
FROM stock_movements
GROUP BY stock_lot_id;

-- Per order, saldo matches the derived value.
SELECT o.id, o.ordered_quantity, COALESCE(SUM(r.quantity), 0) AS net_received
FROM orders o
LEFT JOIN receipts r ON r.order_id = o.id
GROUP BY o.id, o.ordered_quantity;
```

No cached `current_balance` to compare against — the sum *is* the balance.

## Out of scope (deferred)

- Transfers and transfer movements (Phase 6).
- Returns (Phase 7).
- Stock browsing page `/estoque`, movement history list (Phase 8).
- **Cross-mount idempotency** (reload / crash recovery via server-issued or Redis-TTL keys) — users recover via admin correction.
- **Browser test infrastructure** — Pest browser plugin + Playwright; deferred to a dedicated infra phase and reused by Phases 6/7.
- **`Cancelled` requires zero receipts** state-machine guard — enforced at Phase 4 cancellation callsite in a later hardening pass.
- **Reopen-order flow** (required before corrections on `Cancelled` orders) — out of scope indefinitely until business demand.
- **Polymorphic attachments** — Phase 6 decides whether to polymorph or add per-owner tables.
- **`parent_lot_id` on stock_lots** — added in Phase 6 when transfers actually split lots.
- **Janitor jobs** for soft-deleted attachment blobs and for orphans from failed transactions.
- **Privacy-scrub / GDPR tombstoning** for attachments — its own flow, not silent deletion.
- **HEIC client-side conversion** — unnecessary; libheif in Sail handles it server-side.
- **Cross-region Spaces replication / object versioning** — infrastructure ticket.
- **Team scoping of warehouses** — PRD defers indefinitely.

## Decisions made during grilling

| # | Topic | Decision |
|---|---|---|
| 1 | Upload ordering | Upload first, transact second; DB transaction contains only DB writes (milliseconds). |
| 2 | Idempotency key scope | Per-mount UUID. Cross-mount reload/crash recovery deferred; admin correction is the escape hatch. |
| 3 | Ledger reversal model | Forward-only. `type='receipt_correction'` + signed quantity + `corrects_movement_id`. No `reverses_movement_id`. |
| 4 | `stock_lot.current_balance` | Dropped. Balance computed via `SUM(stock_movements.quantity)`. Single source of truth. |
| 5 | Attachment path structure | Flat: `attachments/{uuid}-original.jpg`, `{uuid}-thumb.jpg`, `raw/{uuid}.bin`. |
| 6 | Browser test infra | Deferred to a dedicated later phase. Phase 5 ships with feature tests + manual smoke only. |
| 7 | Image processing | Re-encode to JPEG 85% + strip EXIF + thumb — server-side always. |
| 8 | Re-encode timing | Full async via `ProcessAttachmentJob`. Receipt persisted synchronously; photos process in the queue. |
| 9 | Correction and `receipts` | Correction creates a new negative `receipts` row with `corrects_receipt_id`. Drop positive-quantity constraint. |
| 10 | Correction metadata | Required `reason` text (1..500). Attachments optional. |
| 11 | Warehouse on receipt | Operator picks at first receipt; stamped on `orders.warehouse_id`; subsequent receipts inherit + form disables the select. |
| 12 | State machine rewind | New `canRewindTo()` companion method; forward transitions unchanged. |
| 13 | Terminal status + correction | `ClosedShort` rewindable; `Cancelled` blocked 422; reopen-flow deferred. |
| 14 | Attachment edits | 15-min owner window; soft-delete via `deleted_at`; admin override via `attachments.manage`. |
| 15 | Signed URL delivery | On-demand via authorized proxy route; 10-min TTL at edge. No eager signing on list views. |
| 16 | Attachment view auth | Permission-based (`attachments.view`); role-agnostic. |
| 17 | Test strategy | Build users by permission (`givePermissionTo`); per-action with/without pair. `RoleSeederTest` pins seed contract. |
| 18 | `parent_lot_id` | Deferred to Phase 6. |
| 19 | `stock_movements.warehouse_id` | Kept as denormalization for query ergonomics. |
| 20 | Morph map | Void. Polymorphism dropped; `attachments.receipt_id` is a concrete FK. |
| 21 | Commit sequence | 7 commits as listed. |
| 22 | Deferral list | Updated; this "Decisions" section added. |
| 23 | Verification | 12-step smoke list + new ledger integrity formula. |
| 24 | Scope | Keep all 7 commits; no trimming. |
