# Proxy Attachment Downloads Through the Controller

## Context

`AttachmentViewController` currently redirects (`302`) to a Flysystem `temporaryUrl()` pointing at the configured disk endpoint. In the Sail dev setup, `SPACES_ENDPOINT=http://rustfs:7081` is a docker-internal hostname: reachable from the Laravel container, unreachable from the user's browser. The redirect lands nowhere; thumbnails and originals silently fail to load.

The previous commit already hardened `AttachmentUploader` to throw on failed writes; this change closes the matching gap on the read path and removes the browser's dependency on a publicly reachable object-storage URL.

**Goal:** make the controller stream bytes from the configured disk back to the browser. The browser only ever talks to Laravel; Laravel handles the internal-network read.

**Accepted trade-off:** Laravel is now in the hot path for every attachment view (bandwidth + a PHP-FPM worker per request). For a low-traffic receipts app with small JPEG thumbnails this is acceptable and gains real per-request authorization (vs. a signed URL valid for its full TTL).

## Approach

Replace `redirectToSigned()` in `app/Http/Controllers/Attachments/AttachmentViewController.php` with a streamed response built from `Storage::disk(...)->readStream($path)`. Keep `Gate::authorize('view', $attachment)` at the entry of both endpoints. Drop `SIGNED_URL_TTL_MINUTES` (no longer used).

Use Laravel's `response()->stream()` with:
- `Content-Type` from the attachment's stored `mime` (fall back to `application/octet-stream`).
- `Content-Length` from the disk's `size()` when available.
- `Content-Disposition: inline` — the file displays in the browser (photo preview). We're not forcing a download.
- Surface a `404` when `readStream()` returns `false` (object missing) instead of a 500.

## Changes

### 1. `app/Http/Controllers/Attachments/AttachmentViewController.php`

- Remove the `SIGNED_URL_TTL_MINUTES` const and the `redirectToSigned()` helper.
- Both `thumbnail()` and `original()` now return a `StreamedResponse` via a new private `streamFromDisk(Attachment $attachment, string $path): StreamedResponse` helper.
- Helper responsibilities:
  - Resolve disk from `config('santostok.attachments.disk')`.
  - `abort(404)` if the file is absent on the disk.
  - Stream via `readStream` inside a `response()->stream(...)` closure that `fpassthru()`s and closes the handle.
  - Set `Content-Type`, `Content-Length` (when known), and `Content-Disposition: inline; filename="..."` using `$attachment->original_filename`.

### 2. `tests/Feature/Attachments/AttachmentViewControllerTest.php`

Update assertions — the endpoints no longer redirect:

- `assertRedirect()` → `assertOk()` and `expect($response->getContent())->toBe('fake-jpeg-bytes')`.
- Drop the `Location`-header expectation; replace with a `Content-Type` check (e.g., `assertHeader('Content-Type', ...)`) or a body-bytes check.
- Add a new test: **returns 404 when the file is missing on disk** (create `Attachment` row but don't `put` the file; expect `assertNotFound()`).
- The existing soft-delete and permission tests keep their authorization assertions unchanged; only swap `assertRedirect()` for `assertOk()` / `assertForbidden()` is already correct.

No changes to the upload path, the job, or any routes.

## Critical Files to Modify

| File | Change |
|------|--------|
| `app/Http/Controllers/Attachments/AttachmentViewController.php` | swap redirect → streamed response; drop TTL const |
| `tests/Feature/Attachments/AttachmentViewControllerTest.php` | swap redirect assertions for body/status; add 404-on-missing test |

## Reuse

- `config('santostok.attachments.disk')` — established in the previous change; use here, not a new constant.
- `Gate::authorize('view', $attachment)` — already in place, unchanged.
- `Illuminate\Support\Facades\Storage` `readStream()`/`size()`/`exists()` — standard Flysystem; no new wrapper.

## Verification

1. **Tests:** `docker compose exec -T laravel.test php artisan test --compact --filter=AttachmentView` — all pass including the new 404 test. Then full suite: `docker compose exec -T laravel.test php artisan test --compact`.
2. **Formatting:** `vendor/bin/pint --dirty --format agent`.
3. **Manual smoke (Sail + rustfs):** with `ATTACHMENT_DISK=spaces`, create a receipt with a photo through the UI, wait for `ProcessAttachmentJob` to complete, then load the order show page. The thumbnail must render (previously 302'd to an unreachable `http://rustfs:7081/...`).
4. **Negative check:** manually delete the object from the rustfs bucket via its admin UI (`http://localhost:7082`) and re-request the thumbnail URL — expect a `404`, not a 500.

## Out of Scope

- Caching headers (`Cache-Control`, `ETag`) — can be added later if browsers thrash.
- Range requests (partial content) — not needed for small JPEGs.
- Switching back to signed URLs in production — the proxy works for both environments; a future optimization can conditionally redirect when `config('santostok.attachments.disk')` points to a public-reachable bucket.
