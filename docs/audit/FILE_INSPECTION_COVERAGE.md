# File Inspection Coverage

Branch: `fix/workspace-transaction-lifecycle-20261126`
Date: 2026-11-26

## Method and coverage
Counts below describe the previous risk-ranked inspection pass. They do not mean every file was read line by line. Generated/vendor/build output was excluded.

| Area | Files | Inspection status |
|---|---:|---|
| Backend app PHP | 118 | 118 targeted deep reads |
| Backend migrations | 54 | 54 reviewed for schema, indexes and constraints |
| Backend routes | 4 | 4 reviewed |
| Backend config | 15 | 15 reviewed |
| Backend tests | 68 | 68 executed, not individually line-read |
| Frontend TS/TSX source | 130 | Targeted inspection; not exhaustive line-by-line |
| Frontend E2E | 15 | Browser suites run; see TESTING_REPORT |
| Docker/Compose | 4 + compose | Configuration reviewed; branch Docker runtime not verified |
| Nginx | 1 | Configuration reviewed |
| CI workflows | 2 | Reviewed |
| Documentation | 8 + audit reports | Reviewed selectively |

## Deep inspection focus
- Dealer, finance, workspace, document and agency-staff authorization paths.
- Tenant isolation, transaction lifecycle, payment/commission ledgers and replacement safety.
- Regression tests for finance, workspace tenant matrix, dealer authorization, vault security and concurrency.
- Frontend transaction domain and admin transaction UI.
- Docker/Compose, Nginx and CI workflow definitions.

## Exclusions
`backend/vendor/`, `frontend/node_modules/`, `frontend/dist/`, generated bundles, binaries and lockfile internals were not line-read as application source.

## Latest verification reference
GitHub Actions run #641 on `d4ba0e4`: backend 540 passed; frontend 173 passed; Chromium 46 passed; Firefox/WebKit 14 combined passed. A subsequent dealer commission validation fix and regression test were committed after that run and remain pending CI verification.
