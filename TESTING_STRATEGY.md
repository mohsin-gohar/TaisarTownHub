# Testing Strategy

## Test pyramid (as actually implemented)

| Layer | Runner | Where | Current count |
|---|---|---|---|
| Backend feature suite | `php artisan test` (PHPUnit 11, sqlite `:memory:`, APP_KEY pinned in `backend/phpunit.xml`) | `backend/tests/Feature/*` | **154 tests / 1506 assertions** |
| Frontend unit + domain | `tsx --test` | `frontend/src/**/*.test.ts` | **117 tests** |
| Frontend gates chain | `npm run verify` = lint → tests → `vite build` + SEO prerender → bundle privacy guard | `frontend/package.json` | all green |
| E2E (G-13) | Playwright | `frontend/e2e/*` | smoke spec — see "attempt" note below |

## Backend conventions (hard-won, documented for reviewers)

- `EnterpriseTestCase` provides `agencyUser()` → `[$user, $agency, $role]`,
  `makeAgency/makeRole/makeMember`, `makePublicProperty`,
  `seedPlatformRole(key)`, and `tokenFor()` which **forgets the sanctum
  guard cache** — every user switch must inline a fresh
  `$this->tokenFor($user)` per request (batched tokens cause stale-user
  cross-tenant failures).
- Feature tests run against sqlite `:memory:`; date-cast columns are stored
  as `'Y-m-d H:i:s'` strings — date windows must use `whereDate`, never
  string `whereBetween` (this exact bug was caught by `FinanceReportsTest`).
- Streamed downloads are asserted via **headers** (content-disposition,
  nosniff), not bodies — mirrors `StorageSecurityTest`'s convention.
- Test users must not invent FK ids: `agency_id=2` style mistakes create
  violations that surface as SQL integrity errors, not logic failures.
- Registration-dependent tests require the `role_agency_owner` Role row;
  seeded from factories/helpers, not assumed.

## What the backend suite proves (by suite)

| Suite | Proves |
|---|---|
| `ApiVersioningTest` (5) | legacy ↔ v1 route-for-route parity, health probe |
| `BookingConcurrencyTest` (5) | `lockForUpdate` sell/void/approve/payment races |
| `EncryptionAtRestTest` (3) | CNIC encrypted at rest, masked output, migration idempotence |
| `AuthFlowsTest` (8) | reset password, email verification tokens, queued mail contract |
| `DocumentSharesTest` (6) | hash-only tokens, one-time visibility, caps under lock, tenant scoping |
| `SupportTicketsTest` (5) | self-service tickets, server-side tenant link, staff actions |
| `ExpensesTest` (3) | permission gates, validation, soft-delete retention, totals |
| `SubscriptionInvoicesTest` (4) | raise/verify-once, unique INV numbers, agency-only read |
| `PlanLimitEnforcementTest` (5) | listings/staff/storage/leads quotas, 0=unlimited, visitor-safe 402 |
| `FinanceReportsTest` (3) | exact date-window math, voided split, tenant scoping |
| `CorrelationIdTest` (4) | X-Request-Id generation, echo, sanitisation |
| …plus the pre-existing suites | permissions matrix, workspace approval, vault storage security, CRUD lifecycles, migration smoke, production readiness |

## Frontend gates

- `npm run lint` — ESLint over `src/`.
- `npm run test` — 117 unit tests (domain state machines, permission
  contract, app-mode safety, auth flows).
- `npm run build` — `vite build` + `prerender:seo`: emits one HTML file per
  public route with baked meta/JSON-LD and regenerates `sitemap.xml` from
  `src/config/seo.ts` (noindex utility pages excluded).
- `npm run check:bundle` — fails the build if private field names (`cnic`,
  `cnicMasked`) leak into static public chunks.

## E2E (G-13) — honest status

Playwright is **installed and green at smoke level**: the runner plus
`e2e/smoke.spec.ts` covers 5 checks (homepage shell, auth portal,
`/reset-password?token=…&email=…` cold-loading outside the SPA shell,
`/verify-email` bad-token UX, and the public share-link endpoint returning
410 for unknown well-formed tokens). Running the full **18-journey** E2E
matrix remains **open work** — it needs a stable CI browser environment and
seed data scripts; it is tracked in `CODEBASE_AUDIT.md` (G-13: partial).

## CI

`.github/workflows/ci.yml` runs backend suite, frontend verify chain and an
OpenAPI YAML parse check on every push/PR.

## Rules (from the master prompt, enforced in review)

1. Never delete or weaken tests to go green — fix the code.
2. No fake data or hardcoded statistics anywhere in tests or app code.
3. Never make authorization frontend-only.
4. Every new backend module lands with its feature suite in the same batch.
