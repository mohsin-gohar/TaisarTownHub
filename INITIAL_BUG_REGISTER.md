# INITIAL_BUG_REGISTER.md — TaisarTownHub

**Date:** 2026-11-14 · Registered **before** this browser pass, from the code/state inspection.
Statuses are tracked in `BUG_REGISTER_AND_FIXES.md` (pre-existing K-items + new N-## findings
with root cause → fix → regression proof).

| ID | Sev | Area | Finding | Status |
|---|---|---|---|---|
| K-01 | 🔴 High (decision) | Data | Legacy `business_records` store is not tenant-scoped — 95/103 rows visible to every tenant (proven by `legacy-visibility-probe.mts`) | OPEN — awaiting owner decision (B-01); tooling delivered (`platform:legacy-audit`, `platform:legacy-backfill`, dry-run default) |
| K-02 | 🔴 High | Config | Rate limit 60 req/min per IP rejects 70 % of requests under 10-user load — unusable for a real office | OPEN — needs approved production profile (B-02) |
| K-03 | 🟠 Medium | Ops | Queue worker keeps stale `.env` config after changes (observed live during SMTP test) | OPEN — runbook action; document in DEPLOYMENT_GUIDE (B-03) |
| K-04 | 🟠 Medium | Data | Legacy `videos` rows store a deal id (`deal-1`) in `agencyId` — 6 bogus agency references | OPEN — part of B-01 mapping |
| K-05 | 🟠 Medium | Security | No malware scanning of uploads (MIME/extension/size only) | OPEN — options documented (B-05); no fake scanner added |
| K-06 | 🟡 Low | Feature gap | No SMS/WhatsApp channels (no provider) | OPEN — provider contracts documented (B-06) |
| K-07 | 🟡 Low | Feature gap | Global "MFA required for platform admins" not enforced | OPEN — decision required (B-04) |
| K-08 | 🟡 Low | Dev data | 9 probe users/agencies + 2 probe properties + probe files remain in the dev DB (marked, suspended where agency) | OPEN — cleanup decision (kept for traceability) |
| K-09 | 🟠 Medium | Coverage | Manager/dealer admin-interaction run was never observed completing in a prior session — must be re-verified this pass | OPEN — scheduled (Phase 4) |

Previously fixed in code and re-verified this session (regression-checked, not re-registered):
soft-404 handling, deterministic list ordering, sidebar overlap, `window.confirm` → accessible
dialogs, clipboard fallback, 429 friendly message, strict CSP, stale `FRONTEND_URL` port.
