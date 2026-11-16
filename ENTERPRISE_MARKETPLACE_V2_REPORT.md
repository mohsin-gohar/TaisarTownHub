# Enterprise Marketplace v2 — Saved Searches, Comparison & Support Center

**Project:** TaisarTownHub — multi-agency real estate SaaS (Taiser Town / MDA Scheme 45, Karachi)
**Date:** 2026-11-16
**Branch:** `feature/enterprise-marketplace-v2` (pushed to origin)
**Commits:** `24db8a6` (backend) · `c067a7d` (frontend) · this report
**Base:** `e488193` (`main`)

---

## 1. Verdict (truthful, no marketing)

This increment adds three genuinely working marketplace capabilities on top of
the existing Laravel + React architecture:

- **Saved searches with email alerts** — persisted per account, ownership-scoped,
  filter-normalised, quota-limited, delivered by an hourly scheduled sweep.
- **Property comparison / shortlist** — a four-listing tray, a 13-attribute
  side-by-side table, lowest-price marking, shareable `?compare=` URLs and a
  clipboard-blocked fallback that shows the link for manual copying.
- **Support center** — ticket queue, threaded replies, status transitions,
  staff assignment and permission gating, wired into the existing admin shell.

The code is tested and was exercised in a real browser against the local
Laravel API. It is **not** claimed to be production-ready: Docker, real
ClamAV, a live Vercel Laravel API, real SMTP and the full browser security
matrix remain unverified (§5).

## 2. Verified gates (re-run after the final edit)

| Gate | Result |
|---|---|
| Backend feature suite (`php artisan test`) | **216 passed / 1,866 assertions** |
| Frontend unit tests (`npm test`) | **133 passed / 9 suites, 0 failed** |
| TypeScript (`npm run lint` → `tsc --noEmit`) | clean |
| Production build (`npm run build`) | green; 29 prerendered HTML files; sitemap 9 URLs |
| Bundle guard (`npm run check:bundle`) | OK — 10 public chunks, no private ERP data or demo credentials |

## 3. What was built

### 3.1 Backend — saved searches and alerts (`24db8a6`)

| Item | Detail |
|---|---|
| Schema | `saved_searches` (`id`, `user_id`, name, JSON filters, frequency, alerts_enabled, `last_run_at`, `last_notified_at`) |
| CRUD | `GET/POST /api/saved-searches`, `PUT/PATCH/DELETE /api/saved-searches/{id}`, all `auth:sanctum` |
| Ownership | Queries always filter by the authenticated user; another account's id returns 404 |
| Limits | 20 searches per account; write routes throttled 30/min |
| Filter contract | `PropertySearchFilters` allow-lists 12 keys, escapes LIKE wildcards, maps legacy `"1"`/`"Phase 1"` phase spellings, rejects min > max |
| Alert sweep | `saved-searches:send-alerts` runs hourly (`withoutOverlapping`), per-search instant/daily/weekly window |
| Email | HTML + text Blade templates; links back to the public listing |
| Visibility | Alerts are built only on `Property::publiclyVisible()` — drafts/private/expired rows cannot leak |
| Support endpoint | `GET /api/admin/support-tickets/assignable-staff` returns only id/name/email/role for active staff, gated by `admin.support_tickets` |
| Tests | `SavedSearchAlertsTest` — CRUD, isolation, validation, quota, alert delivery, staff list |

### 3.2 Frontend — saved searches and comparison (`c067a7d`)

- `PropertiesListingPage` integrates save/apply/pause/resume/delete with an
  accessible confirmation dialog and empty state.
- `CompareTray` + `CompareModal` with keyboard-operable buttons, focus-safe
  overlay (`role="dialog"`, labelled title, body scroll lock), dedupe, 4-item cap,
  localStorage persistence and URL round-trip.
- Share link uses `?compare=id,id`; when Clipboard API is denied, the modal
  shows a read-only labelled input containing the exact URL instead of failing
  silently. This fallback was verified by overriding `navigator.clipboard`.
- Pure contracts (`propertyCompare.ts`, `savedSearchFilters.ts`) are covered by
  `marketplaceFeatures.test.ts`.

### 3.3 Frontend — support center (`c067a7d`)

- `AdminSupportDesk`: stats, status/category filters, search, "all tickets" vs
  "my agency" lanes, create-ticket modal, threaded replies with STAFF badge,
  status transitions and staff assignment.
- Permission-gated: only users with `admin.support_tickets` see staff lanes,
  the assignable-staff request or assignment controls. Agency members without
  the permission see only their own agency's tickets.
- Wired as an `AdminLayout` tab without touching the owner-protected
  `AppContext.tsx`.

## 4. Browser and runtime evidence

Browser verification was performed against the local API with disposable QA
accounts. Because the Windows PHP dev server dropped pooled keep-alive
connections, a temporary loopback Node proxy forwarded `127.0.0.1:8080` to a
second Laravel instance on `8082` for the verification run only. Both the proxy
and the duplicate instance were stopped afterwards; a **single `php artisan
serve` now runs on `127.0.0.1:8080`** as the project expects.

| Scenario | Evidence |
|---|---|
| Listing filters | `/properties` showed 22 public listings; `Residential Plot` + `Sector 63` → 2 matches |
| Saved search lifecycle | Saved "QA Sector 63 residential plots"; badge `ALERTS ON`; `Pause` → `PAUSED`; `Resume` → `ALERTS ON`; `Reset Filters` + `Apply filters` restored both selects to `Residential Plot` / `Sector 63` |
| Delete confirmation | Delete button opened `role="alertdialog"` ("Delete saved search… Email alerts for this search will stop."); confirming produced the empty state |
| Comparison | Added `prop-r66` + `prop-15`; tray `2/4`; modal rendered 13 attribute rows, `LOWEST` price badge, body scroll locked |
| Share link | `/properties?compare=prop-r66,prop-15` restored the tray, stored ids and `aria-pressed` states |
| Clipboard fallback | Forced `navigator.clipboard.writeText` rejection; modal rendered `role="status"` banner and read-only input with `http://127.0.0.1:3000/properties?compare=prop-r66%2Cprop-15` |
| Support ticket | Created document/high ticket `TKT_F7F380CB272C9637`; thread loaded; reply showed `STAFF`; assigned to Saleem Siddiqui (id 2); `Mark resolved` persisted; UI stat moved to 1 |
| Permission gating | Agency member `qa.agent@audit.test`: no `ALL TICKETS`, no `assignable-staff` request, no assign control, operator ticket not visible |
| Alert pipeline | Probe search + property; dry-run reported `Would send 1`; real run `Sent 1`; `laravel.log` contains subject `New matches for "QA alert pipeline check"` and `QA-ALERT-MATCH Sector 63 test plot` |

**Alert delivery caveat:** the local `.env` uses `MAIL_MAILER=log`. The pipeline
is proven end-to-end through the log transport, not through a real SMTP
mailbox.

**Cleanup:** the probe property, saved search, support ticket/messages and both
QA users were deleted after verification. Local database is back to
9 properties, 0 saved searches, 0 support tickets, 27 users.

## 5. Remaining blockers (unchanged; not claimed as done)

1. **Docker** — CLI unavailable, so `docker compose build/up` and container
   health checks were not executed.
2. **ClamAV** — no real daemon; only fake-daemon upload tests exist.
3. **Live deployment** — Vercel serves the SPA only: `/api` returns 404 and
   `https://api.taisartownhub.com/api/health` is unreachable. Auth, admin and
   write workflows are not usable there.
4. **Custom domain** — `https://taisartownhub.com` returns a transport error;
   only `taisartownhub.vercel.app` is reachable.
5. **Real email** — SMTP delivery unverified (`MAIL_MAILER=log`).
6. **Browser security matrix** — public PII exposure, dealer file-sales, plan
   limits, staff escalation and MFA role-switch checks are not fully executed.
7. **Legacy ownership** — 110 unowned records remain (26 confirmed, 0 ambiguous,
   84 unresolved). No auto-assignment without business-approved mapping and a
   verified backup; unresolved rows stay hidden.
8. **Payments / SMS / WhatsApp** — no gateway or provider credentials.
9. **Performance / accessibility / cross-browser** — no measured audit; browser
   screenshots are unavailable because the tab is not visible, and some pages
   return an empty accessibility `main`.
10. **Gallery media** — still the shared placeholder `/sample-video.mp4`, not
    genuine Scheme 45 footage.

## 6. Bottom line

The saved-search, comparison and support-center increments are implemented,
tested, browser-verified and pushed on `feature/enterprise-marketplace-v2`
(`24db8a6`, `c067a7d`). The repository remains a **conditional / not
production-ready** delivery until the infrastructure and owner-credential
blockers in §5 are closed. No test was weakened or removed, no owner-protected
file was edited, and no probe data remains in the local database.
