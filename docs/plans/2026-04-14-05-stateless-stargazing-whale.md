# Configurable Upload Disk via Env Var

## Context

Attachment uploads currently hardcode the target filesystem disk at `app/Services/AttachmentUploader.php:11` (`public const DISK = 's3'`). This prevents switching between the two S3-compatible disks already defined in `config/filesystems.php` (`s3` and `spaces`) without a code change.

Compounding this: tests uniformly `Storage::fake('spaces')` while the uploader targets `'s3'` — a latent mismatch that would surface the moment someone swaps the active disk.

**Goal:** make the upload disk configurable via a single env var (`ATTACHMENT_DISK`). No UI, no DB-backed setting, no per-attachment tracking, no connectivity validation. Admin changes `.env` and redeploys.

**Accepted trade-off:** changing the disk after attachments exist may break URLs to existing files. The user has accepted this risk (no per-row `disk` column).

## Approach

Replace the `AttachmentUploader::DISK` class constant with a config value backed by an env var. All existing consumers (`ProcessAttachmentJob`, `AttachmentViewController`) already read via `AttachmentUploader::DISK` — so they continue to work after we convert the constant to a static accessor (or inline `config()` calls).

Chosen shape: introduce a dedicated config file `config/attachments.php` exposing `disk` (reads `ATTACHMENT_DISK` env var, defaults to `'spaces'` — matching what tests already assume and what the rustfs infra actually provides). Replace `self::DISK` / `AttachmentUploader::DISK` references with `config('attachments.disk')`.

Defaulting to `'spaces'` (not `'s3'`) fixes the latent mismatch: production and tests converge on the same disk.

## Changes

### 1. New config file — `config/attachments.php`

```php
<?php

return [
    'disk' => env('ATTACHMENT_DISK', 'spaces'),
];
```

### 2. `.env.example` — add the new variable

Add near the `FILESYSTEM_DISK` line:

```
ATTACHMENT_DISK=spaces
```

### 3. `app/Services/AttachmentUploader.php`

- Remove `public const DISK = 's3';` (line 11).
- Replace `Storage::disk(self::DISK)` (line 31) with `Storage::disk(config('attachments.disk'))`.
- Keep `RAW_DIRECTORY` constant as-is.

### 4. `app/Http/Controllers/Attachments/AttachmentViewController.php:32`

Replace `Storage::disk(AttachmentUploader::DISK)` with `Storage::disk(config('attachments.disk'))`. Drop the now-unused `use App\Services\AttachmentUploader;` import if that was its only reason for being there (verify during edit).

### 5. `app/Jobs/ProcessAttachmentJob.php:33`

Same replacement as above.

### 6. Tests — make disk name dynamic

In each of the following files, replace the hardcoded `'spaces'` in `Storage::fake(...)` and `Storage::disk(...)->...` with `config('attachments.disk')` so tests follow the config:

- `tests/Feature/Attachments/AttachmentUploaderTest.php:8,19`
- `tests/Feature/Attachments/ProcessAttachmentJobTest.php:13,14`
- `tests/Feature/Attachments/AttachmentViewControllerTest.php:13,23,60`
- `tests/Feature/Receipts/CreateReceiptTest.php:23`
- `tests/Feature/Receipts/CorrectReceiptTest.php:20`

Tests continue to default to `'spaces'` via `config/attachments.php`.

### 7. New unit test — `tests/Unit/Config/AttachmentDiskConfigTest.php`

A tiny regression test:

- Asserts `config('attachments.disk')` defaults to `'spaces'` when `ATTACHMENT_DISK` is unset.
- Asserts it picks up the env var when set (via `config(['attachments.disk' => 's3'])` override or `putenv` + config reload).

## Critical Files to Modify

| File | Change |
|------|--------|
| `config/attachments.php` | **new** |
| `.env.example` | add `ATTACHMENT_DISK=spaces` |
| `app/Services/AttachmentUploader.php` | remove `DISK` const, use `config()` |
| `app/Http/Controllers/Attachments/AttachmentViewController.php` | use `config()` |
| `app/Jobs/ProcessAttachmentJob.php` | use `config()` |
| 5 test files (listed above) | use `config()` instead of hardcoded `'spaces'` |
| `tests/Unit/Config/AttachmentDiskConfigTest.php` | **new** |

## Verification

1. `php artisan config:show attachments.disk` — prints `spaces` with no env override; prints `s3` after `ATTACHMENT_DISK=s3` in `.env`.
2. `php artisan test --compact` — all existing tests pass without modification to their intent (they still fake and assert against whatever disk is configured).
3. Manual smoke test: set `ATTACHMENT_DISK=s3` in `.env`, upload a receipt photo via the UI, verify the object lands in the AWS S3 bucket (not rustfs). Set back to `spaces` for local dev.
4. `vendor/bin/pint --dirty --format agent` — formatting clean.

## Out of Scope

- Settings UI (explicitly dropped).
- Per-attachment disk tracking (explicitly dropped — changing the env var may break existing file URLs).
- Connectivity validation / test-write on save.
- Migrating existing attachments between disks.
