# TaisarTownHub — Error Handling & Structured Logging (Pillar 14)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

> **Historical error-handling design:** current logging, API error rendering and correlation behavior are defined by live Laravel middleware/configuration and tests.


## 1. Global Exception Handler

Laravel's `Handler` (in `app/Exceptions/`) renders:
- `404` → JSON `{ message: "Not found." }` for API; custom Blade/React fallback for web.
- `403` → `{ message: "Permission denied." }`.
- `422` → `{ message, errors }`.
- `500` → `{ message: "Server error." }` (no stack trace in production; `APP_DEBUG=false`).
- `429` → `{ message: "Too many requests." }` + `Retry-After`.

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

- Laravel `Log::info('AUDIT', $row)` for audit events (actor, role, action, target, detail, ip, timestamp).
- `Log::warning('Audit table write failed', …)` on audit DB failure (best-effort).
- `Log::error` for unhandled exceptions (with stack trace in dev).
- Frontend: `console.error` in dev only; production builds strip debug.

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
