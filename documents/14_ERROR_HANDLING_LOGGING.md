# TaisarTownHub — Error Handling & Structured Logging (Pillar 14)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. Global Exception Rendering

Laravel 11 exception rendering is configured in `backend/bootstrap/app.php` via `withExceptions()` (there is no legacy `app/Exceptions/Handler.php` in this application).

API responses preserve expected status codes:
- `401` → generic authentication-required JSON.
- `403` → `Permission denied.`.
- `404` → `Resource not found.`, including Eloquent model-not-found exceptions.
- `422` → `Validation failed.` plus field errors.
- `429` → `Too many requests.`.
- Other 4xx → generic request-failed message.
- `5xx` → `Server error.`; internal exception details are not returned to clients.

Uncaught exceptions are reported through Laravel's exception reporting pipeline. Operational failures caught and converted into API responses should call `report($e)` where a stack trace is required.

## 2. Frontend Error Boundary

`ErrorBoundary` wraps `<AppProvider><MainAppContent /></AppProvider>`:
- Catches render errors.
- Shows fallback UI with retry action.
- Logs to console in dev.

## 3. Safe User-Facing Fallbacks

- Missing/invalid token → redirect to login (no crash).
- Network failure → toast error + revert optimistic update; modal stays open.
- Empty data → honest empty state (no fake content).
- 404 route → redirect to home or show 404 page.

## 4. Structured Logging

- Laravel `Log::info('AUDIT', $row)` for audit events (actor, role, action, target, detail, ip, timestamp); each request log carries the validated `X-Request-Id` context.
- `Log::warning('Audit table write failed', …)` on audit DB failure (best-effort).
- Production uses `LOG_LEVEL=warning` and daily log rotation (14 days) by default; configure writable log storage and central retention on the host.
- Never log passwords, bearer/reset/verification tokens, raw uploaded filenames, file contents or raw provider exception messages.
- `SENTRY_DSN` is only a configuration placeholder at present; no Laravel Sentry SDK integration is installed. Do not treat external error tracking as active until the SDK and capture tests are added.
- Frontend ErrorBoundary logs details to the console only in development and shows a generic error message in production. No frontend error-tracking SDK is currently wired.

## 5. Reload Protection

Client-side guard: counts reloads in `sessionStorage` within 60s window; >5 reloads shows friendly error screen instead of crashing. Prevents infinite reload loops.

## 6. Audit Trail

- `audit_logs` table: append-only, never soft-deleted.
- Columns: actor, role, action, target, detail, ip, created_at.
- Indexed on created_at, action.
- Super admins review via Audit & Recycle Bin module.

## 7. Recycle Bin

- Soft-deleted rows (`deleted_at IS NOT NULL`) listed per collection.
- Restore reverses deletion (`restore()`).
- Hard purge requires explicit super_admin action + audit entry.

## 8. Debugging Aids

- `AUDIT_REPORT.md`, `BUG_FIX_REPORT.md`, `TEST_REPORT.md` in repo root.
- `php artisan tinker` for ad-hoc inspection.
- Browser console + network tab for frontend.
- `npm run verify` as release gate.
