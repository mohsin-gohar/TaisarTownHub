# TaisarTownHub — Toast Feedback & Notification Engine (Pillar 12)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. Toast System

- **Component:** `ToastContainer` (rendered in App.tsx, both public and admin).
- **API:** `showToast(type, message)` from `useApp()`.
- **Types:** `success`, `error`, `info`, `warning`.
- **Behavior:** auto-dismiss (≈4s), stackable, animated enter/exit, accessible (aria-live).

## 2. Toast Usage Rules (Honesty)

- Success toast fires **only after** persistence resolves.
- Error toast fires on failure, network error, or permission denial.
- Info toast for neutral actions (un-like, link copied).
- Warning toast for recoverable issues.
- Never show success before the API call resolves.

## 3. Notification Channels

| Channel | Trigger | Implementation |
|---|---|---|
| In-app toast | Every mutation result | `showToast()` |
| Email | Inquiry submission, deal booking | (planned: SMTP via Laravel Mail) |
| WhatsApp | Site visit booking, inquiry | `generateWhatsAppLink()` → wa.me URL |
| SMS | OTP/verification | (planned: webhook) |
| Audit log | Sensitive actions | `AuditLog::create()` + `Log::info('AUDIT', …)` |

## 4. Email Triggers (planned v2.1)

- New inquiry → notify assigned agent.
- Deal status change → notify buyer/seller.
- Media sold → notify dealer + admin.
- Welcome email on registration.

## 5. WhatsApp Integration

- `generateWhatsAppLink(phone, message)` in `src/utils/` builds encoded `wa.me` URL.
- Normalizes PK formats (+92, 03XX, 92XX).
- Used in Contact section and property detail pages.

## 6. Audit as Notification

Every sensitive action writes to `audit_logs` (append-only) and `Log::info('AUDIT', $row)`. Super admins review via Audit & Recycle Bin module. This doubles as a notification trail for compliance.

## 7. Real-time (future)

- v2.1: Laravel Echo + Pusher/Reverb for live inquiry alerts in admin dashboard.
- Currently: polling on mount + manual refresh.
