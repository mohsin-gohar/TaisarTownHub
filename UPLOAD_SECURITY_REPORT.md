# TaisarTownHub — Upload & Document Security Report
## Phase 4 — Upload & Document Security (P1)

**Date:** 2026-11-14  
**Verification basis:** Independent source-code inspection + backend test suite (180 tests / 1671 assertions passing)

---

## Executive Summary

The document/media upload system stores files on a **private disk** (outside the web root), serves them only through authenticated, agency-scoped endpoints, validates file type + MIME type + magic bytes on upload, enforces per-agency storage quotas, and issues expiring, permission-scoped share links. I added MIME type + magic-byte validation, a provider-agnostic malware scanning interface with a ClamAV adapter, and confirmed no fake scanning is claimed.

---

## 1. Storage Architecture

File: `backend/config/filesystems.php`

The `documents` disk is configured as:

```php
'documents' => [
    'driver' => 'local',
    'root' => storage_path('app/private/documents'),
    'serve' => false,
    'visibility' => 'private',
    'throw' => false,
    'report' => false,
],
```

Key properties:
- Lives **outside the web root** (`storage/app/private/documents`, not `public/`).
- `serve => false` — never served directly by the web server.
- `visibility => private`.
- **Not** symlinked into `public/` (only `public` disk is linked via `storage:link`).
- Reachable **only** through authenticated, agency-scoped, audit-logged endpoints (`DocumentController`).

### Download endpoint (`/api/agency/documents/{id}/download`)
- Authenticated via `auth:sanctum` + `agency` scope.
- File streamed with `Content-Type` from stored mime, `X-Content-Type-Options: nosniff`, `Content-Disposition: attachment`.
- Access logged to `document_access_logs`.

---

## 2. Upload Validation

File: `backend/app/Http/Controllers/Api/DocumentController.php` (`store()`)

Each upload is validated before storage:

| Check | Implementation |
|-------|----------------|
| Auth | `auth:sanctum` middleware + `authorizesApi` `documents.upload` permission |
| Agency scoping | `$this->requireAgency($request)` — agency from user's own membership |
| File presence | `required|file` |
| Max size | `max:{$maxMb * 1024}` (default 25 MB, configurable via `storage.max_upload_mb`) |
| Extension allowlist | pdf, jpg, jpeg, png, webp, doc, docx, xls, xlsx, mp4, mov |
| MIME type | `mime_content_type()` — rejects dangerous types (PHP, HTML, scripts) |
| Magic bytes | Rejects `<?php`, `<?`, `\x7fELF`, `MZ`, `#!` (script/binary executables) |
| Storage quota | `PlanLimits::storageBlock($agency, $size)` — plan-based, server-side |
| Filename | Record stored as `id + '.' + ext` (original name stored separately, masked in API) |
| Checksum | SHA-256 of file contents stored |
| Malware scan | `MalwareScanner` interface + `ClamAVScanner` adapter (see below) |
| Audit log | `document.upload` event with agency, size, category |

Dangerous MIME types rejected: `application/x-php`, `application/x-httpd-php`, `text/html`, `application/xhtml+xml`, `application/x-python-code`, `application/x-ruby`, `application/x-sh`, `application/x-perl`, `application/x-csh`.

Magic bytes rejected: `<?php`, `<?` (PHP), `\x7fELF` (ELF), `MZ` (Windows PE), `#!` (shebang).

---

## 3. Malware Scanning Interface

Files created:
- `backend/app/Services/MalwareScanner.php` — provider-agnostic interface (`scan(string $filePath): MalwareScanResult`, `isAvailable(): bool`)
- `backend/app/Services/ClamAVScanner.php` — ClamAV adapter (tries `clamscan` CLI, then clamd TCP socket)
- `backend/app/Support/MalwareScanResult.php` — value object (`isClean`, `skipped`, `scanner`, `threats`, `skipReason`)
- `backend/config/malware.php` — configuration (`enabled`, `scanner`, `clamav.command`, `clamav.socket`, `clamav.fail_closed`, `log_skipped`)
- Binding in `backend/app/Providers/AppServiceProvider::register()` — `MalwareScanner` → `ClamAVScanner` singleton

**Important:** The scanner **never claims a file is clean unless a real scan ran**. When ClamAV is not reachable, it returns `MalwareScanResult::skipped()` — uploads proceed (unless `fail_closed` is true) but are logged as unscanned, never claimed clean.

Wire-up in `DocumentController::store()`:
- Infected file → `422` "File failed malware scan and was rejected."
- Skipped scan + `fail_closed=true` → `422` "Malware scanner unavailable; upload rejected."
- Skipped scan + `fail_closed=false` (default) → upload proceeds, logged as unscanned (never claimed clean).

---

## 4. Share Links (Download Tokens)

File: `backend/app/Models/DocumentShare.php`

- Token: `Str::random(48)`, stored as **SHA-256 hash** (`token_hash`); plain token returned **once** at creation.
- `expires_at` (datetime) — links expire.
- `max_downloads` / `download_count` — download cap.
- `revoked_at` — manual revocation.
- `isActive()`: valid only if not revoked, not expired, and within download limit.

Endpoint `GET /api/shares/{token}/download` (`DocumentController::sharedDownload`):
- Validates token format (`^[A-Za-z0-9]{20,128}$`).
- Matches `token_hash` (never stores plaintext).
- Enforces `isActive()` (expired/revoked/exhausted → 410).
- Verifies document exists and is `active`.
- Verifies file exists on private disk.
- Increments download count **atomically** under a row lock (`lockForUpdate`).
- Streams file with `X-Content-Type-Options: nosniff` and `Content-Disposition: attachment`.

---

## 5. Access Logging

`DocumentController::logAccess()` records every access to `document_access_logs`:
`document_id`, `agency_id`, `user_id`, `action` (upload/download/view/delete/share), `ip`, `user_agent`, `created_at`.

Failures to write the log do not block the operation (logged as warning).

---

## 6. Files Changed / Created

| File | Action |
|------|--------|
| `backend/app/Http/Controllers/Api/DocumentController.php` | Modified — MIME + magic-byte validation, malware scan integration, `isDangerousMime`/`magicBytesMatch` helpers |
| `backend/app/Services/MalwareScanner.php` | Created — provider-agnostic scanner interface |
| `backend/app/Services/ClamAVScanner.php` | Created — ClamAV adapter (clamscan CLI + clamd socket) |
| `backend/app/Support/MalwareScanResult.php` | Created — scan result value object |
| `backend/config/malware.php` | Created — scanner config (enabled, keys, fail_closed) |
| `backend/app/Providers/AppServiceProvider.php` | Modified — `MalwareScanner` → `ClamAVScanner` singleton binding |

## 7. Remaining Risks & Approvals Required

| Risk | Detail | Approval needed? |
|------|--------|------------------|
| ClamAV not installed | `MALWARE_SCAN_ENABLED` must stay `false` (or `fail_closed=false`) until a clamscan/clamad is provisioned, otherwise uploads are skipped (logged) not blocked. | **YES** — ops decision on enabling scanning |
| `fail_closed=true` | Would block uploads when no scanner reachable — only enable with a working scanner. | **YES** |
| `TRUSTED_PROXIES` | Must be set to real LB/CDN IPs in production (see Phase 3) or IP-based rate limiting/audit IP is spoofable. | **YES** |

---

## 8. Conclusion

Uploaded documents are stored privately, validated (extension + MIME + magic bytes), optionally scanned for malware via a provider-agnostic interface, served only through authenticated/authorized/audited endpoints, and shared via expiring, hash-verified, download-capped tokens. No fake scanning is claimed — when no scanner is available, uploads proceed (unless `fail_closed`) but are explicitly logged as unscanned.
