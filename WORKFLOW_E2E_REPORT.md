# WORKFLOW_E2E_REPORT.md — TaisarTownHub

**Date:** 2026-11-14 · **Method:** `frontend/scripts/workflows-e2e.mts` runs four complete
business workflows against the real API + MySQL — every step persists real records and the
assertions check persisted state transitions, not just HTTP codes.
Evidence: `frontend/audit/workflows.json` (**28/28 PASS**). All fixtures are disposable
(`@audit.test`, `WORKFLOW-*`, `PROBE-`), the owner's real account is untouched.

## W2 — Property lifecycle (runs first: its listing is W1's subject)

| Step | Result | Evidence |
|---|---|---|
| Owner (agency scope) creates a listing | 201, `prop_6b4b41c5e232a7d5` | id persisted |
| Submit for platform review | 200 | approval_status → submitted |
| Platform admin approves | 200 | moderation gate works |
| Approved listing publicly visible | 200 | `GET /properties/{id}` unauthenticated |
| Agency manager cannot moderate | **403** | `properties.approve/reject` is platform-only |

## W1 — Buyer journey

| Step | Result | Evidence |
|---|---|---|
| Register through the public form (`/auth/register`) | 201 + agency created | CNIC not retained (replaced at creation) |
| **Workspace gate: favorites denied before approval** | **403** "Your agency is pending platform-owner approval." | deny-by-default onboarding |
| Platform admin verifies the buyer agency (real admin endpoint) | 200 | approval also activates the agency |
| Sign-in issues token | 200 | |
| Favorites list starts empty | 200 | |
| Favorite the W2 listing (toggle on) | 200, `favorited=true` | |
| Listing appears in favorites | contains=true | |
| Favorite removed | delete=200, gone=true | |
| Public inquiry on the listing | 201 | lead auto-attached to the owning agency |
| **Lead reached the owning agency's CRM** (`GET /agency/leads` as owner) | found=true | inquiry → CRM pipeline loop closed |

## W3 — Deal & commission

| Step | Result | Evidence |
|---|---|---|
| Accountant fixture created via the **real invitation flow** | 200 | no hand-granted privileges |
| Dealer creates a file (`POST /dealer-files`) | 201, id=4 | |
| Sell the file once (`POST /dealer-files/{id}/sell`) | 200 | ledger txn inside `DB::transaction` |
| Dealer's closed-deal counter incremented | 15 → 16 | `dealsClosed` derived from ledger |
| Sales report contains the new deal | 200 | buyer name present |
| **Accountant records a payment on the deal** (`POST /agency/transactions/{id}/payments`) | **201** | `amount/type/method=bank_transfer/received_at/reference/receipt_no`; insert + derived deal status are one locked transaction |

## W4 — Document share lifecycle

| Step | Result | Evidence |
|---|---|---|
| Owner uploads a private document (multipart) | 201, `doc_6418522cab501467` | vault disk, MIME/extension/size validated |
| Owner creates a share link (`expires_in_days`, `max_downloads`) | 201 + plaintext token returned once | token hashed (`sha256`) at rest |
| Anonymous holder downloads while valid | 200 | no auth required, token-only |
| Owner revokes the share | 200 | idempotent |
| Revoked token no longer downloads | **410** | immediate kill, not 404-hide |
| Access trail recorded (`GET …/access-logs`) | 200 | audit of downloads present |

## Findings

| ID | Severity | Finding | Disposition |
|---|---|---|---|
| N-01 | High | The catalog bug discovered *through* this phase (properties array degraded to an object when draft/deleted legacy rows exist → zero listings sitewide) | **Fixed** in `WorkspaceController::catalog()`; regression tests in `CatalogShapeTest.php`; see `BUG_REGISTER_AND_FIXES.md` |
| N-04 | Low | QA script misread the legacy bare-array contract of `GET /agency/transactions` (accountant payment step was skipped) | Script fixed; payment now proven 201 |
| — | Info | Agent sign-in may list own-agency deals only when scoped (`X-Agency-Id`); unscoped platform users correctly see nothing | by design |

## Verdict

All four complete business workflows pass end-to-end (28/28): buyer onboarding → approval
gate → favorites → inquiry → CRM; listing creation → moderation → public visibility;
deal → sell → commission counters → sales report → payment ledger; upload → share →
revoke → dead token → access log. Money paths use locked transactions; share tokens are
hashed and revocable; the onboarding gate is deny-by-default.
