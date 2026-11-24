# QODER Page-by-Page Browser Audit

## Live application
- Frontend: `http://127.0.0.1:3002`
- Backend: `http://127.0.0.1:8080`

## Route results
| Route | Result |
|---|---|
| `/` | Passed |
| `/properties` | Passed, sale listings |
| `/agencies` | Passed |
| `/guide` | Passed |
| `/gallery` | Passed |
| `/services` | Passed |
| `/about` | Passed |
| `/contact` | Passed |
| `/login` | Passed |
| `/register` | Passed |
| `/404` | Passed |
| `/projects` | Redirected to sale properties |
| `/project-detail` | Redirected to sale properties |

The public subset produced 17 passes in parallel; the sole navbar timeout passed alone. Screenshot/evidence folders from prior sessions remain available in `frontend/.screenshots/audit`.
