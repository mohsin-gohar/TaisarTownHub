# PRODUCTION_READINESS.md — TaisarTownHub

**Detailed checklist:** [`PRODUCTION_READINESS_CHECKLIST.md`](PRODUCTION_READINESS_CHECKLIST.md).
**Preflight:** `php artisan app:production-check --strict` — the single command that must pass
before a release.

---

## 1. Current status

| Area | State | Evidence |
|---|---|---|
| Multi-tenancy | ✅ implemented | `agency_members` is the tenancy source of truth; cross-tenant reads/writes return 404 (`AgencyContextTest`, `CrmLeadsTest`, `PropertyLifecycleTest`, `SupportTicketsTest`, `DocumentSharesTest`) |
| RBAC | ✅ enforced server-side | 62 permissions / 13 roles / 362 grants — see `RBAC_MATRIX.md` |
| Sensitive data | ✅ protected | CNIC encrypted at rest + masked in responses (`EncryptionAtRestTest`); MFA secrets encrypted; vault on a non-servable disk (`StorageSecurityTest`) |
| Financial integrity | ✅ transaction-safe | DB transactions + `lockForUpdate` on booking/payment/void (`BookingConcurrencyTest`, `FinanceLifecycleTest`); bigint money; audited voids |
| Authentication | ✅ complete | Sign-in, logout (current/all), reset, verification, **TOTP MFA with single-use recovery codes**, per-account lockout, login throttle, login history + suspicious-login alerting (`MfaTest`, `LoginHistoryTest`, `AuthFlowsTest`) |
| API design | ✅ versioned + documented | `/api` and `/api/v1` share one route body (`ApiVersioningTest`); `docs/openapi.yaml` (OpenAPI 3.1) |
| Queues | ✅ real | `ShouldQueue` notifications (verify/reset/password-change/suspicious-login); database driver; worker documented in `DEPLOYMENT_GUIDE.md` |
| Frontend quality | ✅ | 126 unit tests, 5 Playwright specs, 10/10 MFA browser journey, 240-button interaction audit, 0 dead clicks |
| PWA | ✅ | manifest + versioned service worker that caches **only** content-hashed assets |
| Deployment | ✅ | Dockerfiles + compose + GitHub Actions CI (3 gates) + nginx reference + strict preflight |
| Headers | ✅ | nosniff, DENY framing, referrer, permissions, COOP, scoped CORP and a strict CSP (API **and** frontend nginx) |
| Backups | ⚠️ operational | Procedures + verification steps written (`BACKUP_GUIDE.md`); the actual schedule/rotation is an environment task |
| Mail delivery | ⚠️ operational | SMTP credentials are environment-specific — the code path is real and queued, delivery needs a provider |
| SMS / WhatsApp | ⛔ intentionally absent | No provider credentials and no supported gateway in this workspace; **no simulated integration was added** |

---

## 2. Acceptance criteria — status

| Criterion (master prompt) | Status | Proof |
|---|---|---|
| All critical security issues resolved | ✅ | `SECURITY_AUDIT.md` (this session: CSP, MFA, deterministic ordering, 404, clipboard fallback) |
| Backend RBAC enforced | ✅ | `AuthorizesApi` + 362 grants + cross-tenant tests |
| Cross-tenant access blocked and tested | ✅ | 404 on foreign ids across properties/leads/finance/documents/tickets |
| Sensitive information protected | ✅ | encrypted CNIC + MFA material, masked responses, private vault, no bundle leaks (`check:bundle`) |
| Financial operations transaction-safe | ✅ | `lockForUpdate` + DB transactions + audits |
| Authentication flows work | ✅ | reset / verification / MFA / lockout / revoke — all with tests |
| API versioning implemented | ✅ | `/api/v1` parity |
| Critical automated tests pass | ✅ | backend **176 / 1613**, frontend **126**, Playwright **5**, MFA browser journey **10/10** |
| Production build succeeds | ✅ | `npm run verify` green (build + prerender + bundle guard) |
| Deployment and backup procedures verified | ✅ procedures / ⚠️ environment | `DEPLOYMENT_GUIDE.md`, `BACKUP_GUIDE.md`, `app:production-check --strict` |
| Known remaining limitations documented | ✅ | this file + `FINAL_ENTERPRISE_TRANSFORMATION_REPORT.md` + `SECURITY_AUDIT.md` |

---

## 3. Release blockers (must be handled by the deployment, not the code)

1. **Secrets** — `APP_KEY` must be generated per environment; the same key is required to read the
   encrypted CNIC/MFA columns. Rotating it without a re-encryption run makes those columns
   unreadable.
2. **TLS** — terminate HTTPS at the edge, keep `SESSION_SECURE_COOKIE=true`, confirm
   `Strict-Transport-Security` is emitted.
3. **CORS** — set `CORS_ALLOWED_ORIGINS` to the exact frontend origin(s).
4. **Mail** — configure SMTP so verification/reset/security notifications actually leave the box.
5. **Queue + scheduler** — run the worker and the cron entry (see `DEPLOYMENT_GUIDE.md` §3.2/3.3).
6. **Backups** — schedule and *drill* the DB + vault backup.
7. **Ownership data** — the legacy `business_records` store still holds part of the ERP data
   (96 rows in dev). It is agency-stamped and read-only hardened, but a gradual migration to the
   typed tables is recommended before multi-tenant production load.

---

## 4. Explicitly not implemented (and why)

| Item | Reason |
|---|---|
| SMS / WhatsApp notification drivers | No provider credentials or supported gateway in this workspace. Adding a fake driver would violate the "no dummy integrations" rule. The notification layer is provider-agnostic and ready for a driver. |
| Malware scanning of uploads | Requires an external scanner (ClamAV) or a SaaS sandbox; the upload path already enforces MIME/size/type validation and stores files outside the web root. |
| Redis queue/cache | The infrastructure is a single host; the database driver is real and documented. Redis is a configuration change (`QUEUE_CONNECTION=redis`), not a code change. |
| MFA enforcement by policy (e.g. "MFA required for platform admins") | The capability is per-account. A global policy switch can be added once the business decides the rule; no silent default was imposed. |
| `agency_branches`, `project_plots`, `project_blocks` tables | Not required by any implemented workflow: branches are modelled as agency memberships with roles, sectors/blocks already cover the location taxonomy, and plots are listings. Adding unused tables would duplicate data models. |
