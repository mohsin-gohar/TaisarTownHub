# REMAINING_BLOCKERS.md — TaisarTownHub

**Date:** 2026-11-14 · **Verdict: the code is deployment-ready; 4 items need a decision and 5 need
infrastructure/credentials. None of them is a rewrite.**

Status legend: 🔴 needs a decision before go-live · 🟠 needs infrastructure/credentials ·
🟡 operational hygiene · 🟢 resolved

---

## B-01 🔴 Legacy `business_records` are not tenant-scoped (data-ownership decision)

**What the audit found (measured, not assumed):**

- The legacy store holds **103 rows** across 16 collections (`buyers, cms, commissions, dealers,
  files, inquiries, media, payments, plots, projects, properties, sellers, subscribers,
  testimonials, transactions, videos`).
- **Only 8 rows carry an `agency_id`**; of those, 2 are valid and **6 are bogus** — the `videos`
  rows store a *deal id* (`deal-1`, `deal-2`, …) in the `agencyId` field.
- **95 rows have no owner at all** (pre-tenancy seed data).
- A two-tenant probe (`frontend/scripts/legacy-visibility-probe.mts`) proves the impact:
  **every one of those records is returned to every tenant** — 16 properties, 13 dealers,
  13 inquiries, 3 transactions, 3 buyers, 3 sellers, 8 plots, 7 media, 6 videos, 5 files,
  4 payments, 3 commissions, 4 projects, 2 testimonials, 9 subscribers — identical for tenant A
  and tenant B.
- The **typed** tables are already isolated (tenant B gets 404 for tenant A's listing and cannot
  download A's document) — so this is strictly a legacy-store problem.

**Why it matters:** sellers, buyers, transactions, payments and commissions are customer/financial
data. Before real agencies load their own data, the legacy rows must be either assigned to an
agency or explicitly marked as platform-owned and hidden from agency workspaces.

**Ready-to-use tooling (delivered, nothing destructive executed):**

| Command | Purpose |
|---|---|
| `php artisan platform:legacy-audit` | per-collection tenancy picture, validity, derivable owners, migration candidates |
| `php artisan platform:legacy-audit --json` | the same as machine-readable JSON for a reconciliation report |
| `php artisan platform:legacy-backfill` | **dry run by default** — prints exactly which rows it *would* stamp |
| `php artisan platform:legacy-backfill --apply --backup=<verified dump> [--mapping=<json>]` | writes the stamps; refuses to run without a backup; only *adds* an `agency_id` key; verifies the row count afterwards |

**Mapping evidence already derived (dry run):**

| Collection | Rows | Owner derivable | Owner source |
|---|---:|---:|---|
| commissions | 3 | 3 | dealer → agency |
| properties | 19 | 7 | typed property / dealer → agency (`agency-demo`) |
| transactions | 3 | 3 | dealer → agency |
| videos | 6 | 1 | (6 rows carry a bogus `deal-N` agency id — needs manual mapping) |
| buyers, sellers, files, plots, media, cms, subscribers, testimonials, inquiries, dealers, payments, projects | 69 | 0 | **no derivable owner — business decision required** |

**Options (pick one):**

| Option | Effect | Risk |
|---|---|---|
| **A. Assign to a founding agency** (map every legacy row to one agency) | Simple, no code change; the rows stop being shared | That agency effectively owns historical records |
| **B. Mark legacy rows as platform-owned** and hide them from agency workspaces (workspace filter change) | Legacy data stays with the platform; no cross-tenant exposure | One code change in `WorkspaceController` + a re-run of the visibility probe |
| **C. Per-row mapping file** (`{ record_id: agency_id }` JSON) for the 11 derivable rows, leave the other 81 as platform-owned | Most precise | Needs the business to confirm the mapping |

**Recommendation:** **B + C** — stamp the 11 derivable rows to their real owner, mark the rest as
platform-owned, and hide platform-owned rows from agency workspaces. The change is small, the
tooling exists, and the verification probe can prove the result in one run.

**Not done here on purpose:** this is a data-ownership decision, so no rows were written. The
backfill tool refuses to write without an explicit `--apply` **and** a verified backup.

---

## B-02 🔴 API rate-limit profile is too tight for production

**Measured:** in a 15 s / 10-user run, **301 of 428 requests were rejected with 429**; the API
limiter is `throttle:api` = **60 requests/minute per IP**. A single office IP therefore
sustains ~1 req/s.

**What is needed:** a production profile, e.g. public reads 300/min, authenticated reads
120/min, writes 30/min, with per-route overrides (login 5/min, register 3/min, reset 10/min,
share 30/min, subscribers 10/min — all already in place). This is a **configuration change** in
`bootstrap/app.php` + `.env`, not a rewrite. It must be agreed before load testing a real box.

---

## B-03 🟡 Queue workers do not reload `.env`

**Found live during the SMTP test:** `artisan serve` restarts itself when `.env` changes, but a
running `queue:work` process keeps the old configuration — the mail kept going to the log
driver after `MAIL_MAILER=smtp` was written. **Action:** always restart the worker after an
environment change (runbook: `DEPLOYMENT_GUIDE.md` §3.2). Optionally add a supervisor
`autorestart` on config change.

---

## B-04 🔴 Global "MFA required" policy for platform administrators

The capability is real and per-account (enrolment, removal, recovery codes, enforcement — all
tested). What is **missing** is a *global rule* such as "every `super_admin`/`admin` must have a
second factor". Implementing it silently would lock operators out of their own system, so it is
**not enforced**. Decision needed:

- Which roles are in scope (recommendation: `super_admin` + `admin`)?
- What happens to accounts without an enrolled factor — a grace period, or a forced enrolment at
  the next sign-in?
- What about the break-glass path (seeded superadmin on the server)?

Once decided, the implementation is a `system_settings` key read by the auth flow — roughly one
middleware check plus a frontend prompt.

---

## B-05 🟠 Malware scanning of uploads — options, requirements, no fake scanner

Nothing was faked. Current state: uploads are validated by **MIME type, extension whitelist and
size limit**, stored outside the web root on a non-servable disk, and served only through
authenticated, audit-logged endpoints. That is a real control, but it is not malware scanning.

| Option | Deployment requirement | Effort |
|---|---|---|
| **ClamAV sidecar** (recommended) | `clamav/clamav` container on the same network; the API posts the file to `clamd` (`INSTREAM`) and rejects on a positive match | docker-compose service + a `Scanner` interface + upload hook |
| Host ClamAV | `apt install clamav-daemon`, keep signatures updated, mount the socket | as above, no container |
| SaaS scanner (VirusTotal, Cloudmersive) | outbound HTTPS + provider key; **data leaves the tenant** — review with legal | same interface, provider adapter |
| Object-storage scan pipeline | upload to a quarantine bucket, scan asynchronously, promote on a clean verdict | largest change; best for large files |

Minimum viable rollout: a `Scanner` interface with a **no-op local implementation** that logs
"not scanned" (honest, no fake verdict) plus a ClamAV adapter enabled by env var, and a
`documents.scan_status` column so the UI can show *unknown / clean / infected* truthfully.

---

## B-06 🟠 SMS / WhatsApp readiness (no fake integration added)

No driver was added because no provider credentials or supported gateway exists in this
environment — a simulated sender would be worse than none. Readiness:

| Channel | Provider contract | What is needed |
|---|---|---|
| **WhatsApp Business Cloud API** | `POST https://graph.facebook.com/v18.0/{PHONE_NUMBER_ID}/messages` with a bearer token, template name + language for anything outside the 24 h customer-service window | `META_WABA_TOKEN`, `META_PHONE_NUMBER_ID`, `META_TEMPLATE_*`, verified business account, a webhook endpoint for delivery receipts |
| **Twilio SMS** | `POST https://api.twilio.com/2010-04-01/Accounts/{SID}/Messages.json` (form-encoded, Basic auth) | `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM` |
| **Generic webhook** (any ESP/SMSP) | `POST {SMS_WEBHOOK_URL}` with a JSON body and an HMAC signature header | URL + secret |

The Laravel notification layer is already provider-agnostic (`Notification::route(...)` +
`via()` channels), so each of the above is a ~40-line channel class plus a config block —
no core change. Delivery receipts can be persisted in the existing `notifications` table
(`data.delivery_status`).

---

## B-07 🟠 Production environment items (credentials/infrastructure)

| Item | Needed for | Status |
|---|---|---|
| `APP_KEY` per environment | decrypting CNIC + MFA columns | ⛔ not available here |
| TLS certificates + DNS | HTTPS, HSTS, secure cookies | ⛔ |
| Real SMTP account | verification / reset / security mail | ⛔ (pipeline proven over SMTP with a local sink) |
| MariaDB instance + credentials | production database (dev runs on SQLite) | ⛔ |
| Off-site encrypted backup target | backup copies outside the host | ⛔ |
| Docker registry / host | `docker compose up` for api/queue/web/mysql | ⛔ |
| CI secrets (`CI` env) | GitHub Actions runs | ⛔ |

Everything else — nginx configs, Dockerfile/compose, CI workflow, supervisor/cron units, backup
scripts, restore drill, preflight — is in the repository and verified.

---

## B-08 🟡 Gradual migration of the legacy JSON store

The ERP keeps 14 collections in `business_records` (JSON) next to the typed tables. The typed
tables are isolated and indexed; the JSON store is not. Plan: migrate collection by collection
(sellers → buyers → payments → commissions → files → plots → media), keeping the JSON row as a
read-only fallback until the typed table is verified, then removing it from the workspace
payload. Each step is a separate, reversible migration with a reconciliation report
(`platform:legacy-audit --json` produces the "before" side).

---

## B-09 🟡 Redis vs the database driver

The database driver for queue/cache/session is deliberate and documented — it is production-safe
for a single host. Redis is an **infrastructure** upgrade (docker-compose service + three env
values), not a code change. It becomes worthwhile when queue depth or cache hit-rate demands it.

---

## Summary

| ID | Item | Severity | Status |
|---|---|---|---|
| B-01 | Legacy records shared across tenants | 🔴 Critical (data ownership) | **Decision needed** — tooling delivered, nothing written |
| B-02 | Rate-limit profile too tight | 🔴 High | **Approval needed** (config change) |
| B-03 | Workers don't reload `.env` | 🟡 Low | Runbook action |
| B-04 | Global MFA policy | 🔴 Medium | **Decision needed** — capability exists, not enforced |
| B-05 | Malware scanning | 🟠 Medium | Options documented; ClamAV adapter recommended |
| B-06 | SMS / WhatsApp | 🟠 Medium | Provider contracts documented; no fake driver |
| B-07 | Production credentials & infra | 🟠 High | Environment work |
| B-08 | Legacy store gradual migration | 🟡 Medium | Plan documented |
| B-09 | Redis | 🟡 Low | Configuration decision |

**Release recommendation:** go live on the **typed** tables only (do not load legacy customer
data into a multi-tenant production environment), resolve B-01 by marking the legacy rows
platform-owned, agree B-02, and keep the current single-host stack until the traffic demands
more.
