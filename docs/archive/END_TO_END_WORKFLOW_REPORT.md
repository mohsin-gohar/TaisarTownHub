# END_TO_END_WORKFLOW_REPORT.md — TaisarTownHub

**Date:** 2026-11-14 · Complete business workflows, driven end-to-end by
`frontend/scripts/workflows-e2e.mts` against the real API + MySQL. Every step persists real
records and asserts persisted state transitions. Evidence: `frontend/audit/workflows.json`
(**28/28 PASS**). All fixtures disposable (`@audit.test`, `WORKFLOW-*`); the owner's real
account was never touched. MFA and mail workflows were verified by their dedicated suites
(`mfa-e2e.mts` 10/10, `mail-flow-e2e.mts` 6/6 — code unchanged since).

Only workflows **supported by the existing application** were tested; missing workflows are
listed in `ENTERPRISE_FEATURE_GAP_ANALYSIS.md`.

## W2 — Seller/Owner: property creation → approval → public listing

| Step | Role | Expected | Actual | Verdict |
|---|---|---|---|---|
| Create listing (scoped owner) | agency_owner | 201 persisted | 201 `prop_6b4b41c5e232a7d5` | PASS |
| Submit for review | agency_owner | status → submitted | 200 | PASS |
| Approve | super_admin | moderation gate | 200 | PASS |
| Public visibility of approved listing | guest (no auth) | 200 detail | 200 | PASS |
| Agency manager tries to reject/moderate | agency_manager | 403 | **403** | PASS |

**Persistence:** rows verified in `properties` (agency-scoped, approval_status transitions);
tenant id = `agency-demo` throughout.

## W1 — Buyer: register → gate → favorites → inquiry → agency CRM

| Step | Role | Expected | Actual | Verdict |
|---|---|---|---|---|
| Register via public form | guest | 201 + agency | 201 + `agy_a0a7acb319909a20` | PASS |
| Favorites **before** agency verification | registered buyer | 403 gate | **403** "pending platform-owner approval" | PASS |
| Platform admin verifies agency | super_admin | 200 | 200 (also activates agency) | PASS |
| Sign-in | buyer | token | 200 | PASS |
| Favorites empty → toggle on → listed → removed | buyer | each 200 | all 200 (`favorited=true`, contains, gone) | PASS |
| Public inquiry on the W2 listing | buyer | 201 | 201 (lead auto-attached to owning agency) | PASS |
| Lead visible in owning agency CRM | agency_owner | lead present | `GET /agency/leads` contains buyer email | PASS |

**Notification:** registration queued the verify-email notification (queue worker running);
email channel verified separately by the mail-flow suite.

## W3 — Deal & commission: accountant → dealer file → sell → payment

| Step | Role | Expected | Actual | Verdict |
|---|---|---|---|---|
| Accountant fixture via real invitation | agency_owner → invitee | account via accept | 200 | PASS |
| Dealer creates file | agent | 201 | 201 id=4 | PASS |
| Sell file once | agent | ledger txn, single sell | 200 | PASS |
| `dealsClosed` counter moves | guest GET `/dealers/{id}` | +1 | 15 → 16 | PASS |
| Sales report contains the deal | agent | buyer present | 200 + name | PASS |
| **Accountant records payment** (`amount/type/method/received_at/reference/receipt_no`) | accountant | 201 | **201** | PASS |

**Financial integrity:** payment insert + derived deal status update run inside one
`DB::transaction` with `lockForUpdate` — concurrency cannot overshoot the agreement amount
(regression-tested in the backend suite, `ConcurrencyBookingTest`).

## W4 — Document vault: upload → share → revoke → dead token

| Step | Role | Expected | Actual | Verdict |
|---|---|---|---|---|
| Owner uploads private document (multipart) | agency_owner | 201 | 201 `doc_6418522cab501467` (vault disk, validation) | PASS |
| Create share link (`expires_in_days`, `max_downloads`, note) | agency_owner | 201 + token once | 201 | PASS |
| Anonymous download while valid | guest | 200 | 200 (token-only, no auth) | PASS |
| Revoke share | agency_owner | 200/204 | 200 | PASS |
| Revoked token download | guest | dead | **410** (immediate kill) | PASS |
| Access trail | agency_owner | logs present | 200 with entries | PASS |

**Security:** tokens are sha256-hashed at rest; only the creation response ever shows the
plaintext; revocation kills existing links instantly; downloads are audit-logged.

## Cross-cutting verifications

| Check | Result |
|---|---|
| Tenant id on every created record | correct (`agency-demo` / buyer agency) |
| Ownership restrictions | agent writes only own dealer files; staff write only agency rows |
| Status transitions | draft → submitted → approved → public; token/advance/installment ledger; share active → revoked |
| Financial calculations | `dealsClosed`, `totalEarnedPKR`, `pendingCommissionPKR` derived from ledger entries (P1-3 single source of truth) |
| Audit logs | invitations, moderation, share create/revoke all write audit rows (`writeAudit`) |
| API responses | consistent success/error envelopes; legacy bare-array contracts documented (N-04) |
| Persistence after logout/login | token-based state re-hydrates from MySQL (`/auth/me` → workspace reload) |
| Duplicate submissions | sell-once enforced (sell endpoint locks + sold-file check); payment double-entry guarded by locked ledger |

## Findings during workflow testing

| ID | Severity | Finding | Disposition |
|---|---|---|---|
| N-01 | High | Catalog degraded properties list to a JSON object when legacy rows were filtered → zero listings sitewide | **Fixed** (`WorkspaceController::catalog()` reindex) + `CatalogShapeTest.php` (2 tests) |
| N-04 | Low | QA script misread the legacy bare-array `GET /agency/transactions` contract | Script fixed; accountant payment step then passed 201 |

## Verdict

**28/28 steps PASS across 4 complete workflows.** Buyer onboarding is deny-by-default,
moderation is platform-only, money paths are transactional and locked, document sharing is
hash-tokened and revocable, and every step leaves correct tenant-scoped, audit-logged,
persisted state.
