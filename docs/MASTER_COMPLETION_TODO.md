# TaisarTownHub — Master Completion Plan

**Tracking baseline:** `main` at `4baf396fb3074bac134dac9c8bdc40ba2da67bf0` (2026-11-27)  
**Working rule:** execute phases in order; keep every change on an isolated branch and open a PR. Never force-push, rewrite main, alter production data/secrets/DNS, or enable paid services without explicit approval.

## Status protocol
- [ ] **PENDING** — not started.
- [~] **IN PROGRESS** — work started; not yet independently verified.
- [x] **VERIFIED** — evidence recorded (commit, CI run, test output, or reproducible observation).
- [!] **BLOCKED** — requires an external owner, credential, domain, provider, or approval.
- **N/A** — explicitly not applicable, with reason.

A task is VERIFIED only when the evidence is linked or recorded. A passing unit test does not prove a production workflow. A deployed frontend does not prove the API works.

## Phase 1 — Repository and delivery baseline
- [x] Confirm repository identity, default branch, access permissions, and exact main SHA.
- [x] Inventory the complete recursive Git tree; record blob count, directories, and file types.
- [x] Inspect current status/readiness documents and identify contradictory or stale counts.
- [x] Inspect active pull requests, compare each head with main, and record mergeability/CI state.
- [x] Inspect authoritative CI workflows and latest available CI evidence.
- [x] Separate main-merged evidence from unmerged PR evidence and production observations.
- [x] Record production launch blockers without changing production.
- [x] Create a reconciled baseline report and this master checklist.
- [ ] Reconcile every historical file-ledger entry to the current 566-blob tree; classify added, removed, renamed, generated, binary, and historical files.
- [ ] Complete the actual line-by-line file review; inventory alone is not a file audit.
- [ ] Review all branches/PRs and resolve stale/diverged work only after code-level review and explicit merge authorization.
**Exit gate:** repository baseline is reproducible; all open PRs and branches are dispositioned; file ledger reconciles exactly; review coverage is evidence-backed.

## Phase 2 — Backend architecture and code audit
- [ ] Map all API routes to controllers, middleware, policies, validators, services, jobs, events, notifications, and tests.
- [ ] Review controllers and request validation for authorization, mass assignment, error handling, transactions, and consistent API responses.
- [ ] Review models, casts, relations, scopes, observers, factories, and serialization.
- [ ] Review service boundaries, duplicate logic, race conditions, idempotency, retries, and failure handling.
- [ ] Review queue jobs, scheduler commands, events/listeners, notifications, mail, and logging/PII.
- [ ] Review caching, query counts, pagination, N+1 risks, timeouts, and resource limits.
- [ ] Fix verified defects with focused regression tests; no speculative rewrites.
**Exit gate:** route-to-code matrix complete; every P0/P1 finding fixed or explicitly blocked; backend suite green.

## Phase 3 — Database, tenancy, and data integrity
- [ ] Inventory migrations, schema, indexes, foreign keys, constraints, seeders, factories, and production-safe commands.
- [ ] Verify tenant ownership on every agency-owned read/write/export/download and background job.
- [ ] Verify cross-tenant isolation, legacy unowned records, invitation ownership, and agency membership lifecycle.
- [ ] Verify transaction boundaries, concurrency/unique IDs, financial ledger invariants, soft deletes, and audit trails.
- [ ] Verify migration forward/backward safety and compatibility with existing data.
- [ ] Run MySQL 8 integration tests and disposable backup/restore drill.
- [ ] Produce a read-only legacy-data/ownership report; do not mutate production records.
**Exit gate:** schema and tenant matrix verified; MySQL tests and restore drill pass; unresolved legacy ownership is quantified.

## Phase 4 — Authentication and security
- [ ] Verify registration, login, logout, session/token lifecycle, verification, password reset, OAuth, and MFA.
- [ ] Verify RBAC and object-level permissions for visitor, agency owner, agency member, staff, and super-admin.
- [ ] Test IDOR/BOLA, privilege escalation, mass assignment, CSRF, XSS, SQL injection, SSRF, rate limits, and session fixation.
- [ ] Verify upload MIME/signature/size/quota checks, malware scanning fail-closed behavior, quarantine, private storage, and download authorization.
- [ ] Verify secrets handling, CORS/Sanctum, trusted proxies, TLS assumptions, security headers, logs, PII, and dependency advisories.
- [ ] Perform independent security review/penetration test when an authorized environment is available.
**Exit gate:** security regression suite green; no unresolved critical/high finding; external pen-test limitations documented.

## Phase 5 — Frontend and design-system audit
- [ ] Inventory all routes/pages, layouts, shared components, navigation, forms, dialogs, and action controls.
- [ ] Verify loading, empty, success, error, offline, and permission-denied states.
- [ ] Verify responsive behavior at mobile, tablet, laptop, and desktop breakpoints.
- [ ] Verify keyboard navigation, focus management, labels, contrast, reduced motion, and screen-reader semantics.
- [ ] Verify API error handling, stale state, optimistic updates, validation, and destructive-action confirmations.
- [ ] Check image/media fallbacks, maps, console errors, broken links, and bundle budgets.
**Exit gate:** route/control inventory complete; no dead controls or critical accessibility/responsive regressions.

## Phase 6 — Public marketplace
- [ ] Verify homepage, sale-only listings, search/filter/sort/pagination, property details, galleries, maps, agencies, and agency profiles.
- [ ] Verify inquiry/contact flows, favorites, video/gallery pages, featured content, and empty/error states.
- [ ] Verify public serialization never leaks private fields, owner data, internal notes, or moderation state.
- [ ] Verify SEO metadata, canonical URLs, sitemap, robots, structured data, social previews, and redirects.
**Exit gate:** visitor journeys pass in browser against a working API/staging dataset.

## Phase 7 — Agency ERP and staff workflows
- [ ] Verify agency onboarding, profile, verification, subscriptions, plan limits, and team invitations.
- [ ] Verify property create/edit/submit/moderate/archive and media lifecycle.
- [ ] Verify CRM leads, assignment, activities, inquiries, and exports with tenant scoping.
- [ ] Verify private document vault, quarantine, preview/download, retention, and audit history.
- [ ] Verify finance, transactions, expenses, commissions, reports, and permission-scoped financial data.
- [ ] Verify saved searches, alerts, notifications, and agency workspace settings.
**Exit gate:** owner/member/staff workflows pass end-to-end with cross-tenant negative tests.

## Phase 8 — Super-admin operations
- [ ] Verify dashboard, user/agency management, approvals, moderation, featured agencies, and support.
- [ ] Verify CMS/system settings, maps/location data, plans/subscriptions, payment reconciliation, and audit logs.
- [ ] Verify role boundaries, privileged actions, confirmations, exports, and immutable audit evidence.
**Exit gate:** super-admin workflows pass; lower-privilege users cannot perform platform-only actions.

## Phase 9 — Integrations and background processing
- [ ] Verify SMTP delivery and queue retry/failure behavior.
- [ ] Verify Redis, workers, scheduler, ClamAV health, private vault persistence, and multi-replica behavior.
- [ ] Verify Google OAuth and payment gateways/webhooks only when credentials and approved accounts exist.
- [ ] Verify monitoring, error reporting, health/readiness endpoints, alerts, and backup target.
- [ ] Document disabled integrations explicitly; no fake-success fallbacks.
**Exit gate:** all enabled integrations have staging evidence; unavailable external services remain clearly BLOCKED.

## Phase 10 — Comprehensive QA and performance
- [ ] Run backend MySQL suite, frontend tests/typecheck/lint/build/prerender/bundle checks, and OpenAPI validation.
- [ ] Run Playwright Chromium, Firefox, and WebKit across public and authenticated personas.
- [ ] Exercise every page, link, button, form, modal, upload, export, and critical workflow.
- [ ] Test tenant boundaries and negative permission cases through the real API.
- [ ] Run Docker build, nginx config, full-stack integration, backup/restore, multi-replica, and ClamAV checks.
- [ ] Run performance/load, Lighthouse/Core Web Vitals, SEO, and accessibility audits on staging.
- [ ] Re-run all relevant suites after fixes; attach logs/artifacts to PRs.
**Exit gate:** reproducible green checks; no open release-blocking bug; performance/security exceptions approved and documented.

## Phase 11 — Production infrastructure and deployment
- [!] Register/verify the intended custom domain and configure registrar DNS for apex and www.
- [!] Select and provision a supported PHP/container backend host; shared hosting that cannot run the required services is not acceptable.
- [!] Provision production MySQL, Redis, persistent private storage, ClamAV, queue worker, and scheduler.
- [!] Configure production APP_KEY, database credentials, CORS/Sanctum origins, trusted proxies, SMTP, OAuth/payment secrets (or disable unused integrations), and monitoring.
- [!] Deploy Laravel and frontend through a documented release pipeline; configure API DNS and TLS.
- [ ] Run migrations with a reviewed backup and rollback plan.
- [ ] Verify health/readiness, auth, marketplace, agency workflows, private files, mail, maps, and enabled payment flows on production.
- [ ] Configure off-site backups, retention, restore drills, alerts, incident response, and rollback.
**Exit gate:** all owner/provider gates cleared; production acceptance signed off. Never weaken malware scanning or tenant security to fit unsupported hosting.

## Phase 12 — Independent acceptance and handover
- [ ] Reconcile all audit ledgers and report exact file/page/route/control/role coverage.
- [ ] Have an independent reviewer re-run the release-critical checks.
- [ ] Confirm all release blockers resolved or formally accepted with owner and risk.
- [ ] Publish final architecture, operations, deployment, backup/restore, incident, and support runbooks.
- [ ] Publish a final evidence-based report separating VERIFIED, BLOCKED, and NOT TESTED items.
**Exit gate:** independent sign-off and owner acceptance. Do not report 100% unless every required acceptance criterion has evidence.

## Known baseline blockers (2026-11-27)
- Main SHA: `4baf396fb3074bac134dac9c8bdc40ba2da67bf0`; recursive tree: 642 entries, 566 tracked blobs; tree response not truncated.
- `docs/STATUS.md` and the prior independent review used 565; the historical ledger uses 564 in its warning and 706 as a stale historical inventory. These counts require reconciliation; current Git tree count is 566.
- PR #31 (`fix/persist-quarantine-vault-and-deploy-runbook`) is open, 21 commits ahead of main, 6 changed files. CI and Docker CI passed at head `f54afa5`; Vercel check failed due to deployment rate limiting. It is not merged.
- PR #5 (`qa/cross-browser-multireplica`) is open but diverged: 649 commits ahead, 8 behind main, 164 changed files, and GitHub reports it not mergeable. Its description contains extensive older QA evidence; none of that means the PR is integrated into main.
- Main's current combined status reports Vercel success only. Prior backend/test results are documented in STATUS.md but must not be presented as live-production verification.
- Production API host/DNS are unavailable; custom-domain DNS and `api.taisartownhub.com` were reported NXDOMAIN; Vercel API proxy returned 502. Verify again before deployment work.
