# Bugs Fixed and Remaining — TaisarTownHub

**HEAD:** `28c32edb7bd9f6267e5a58bb97239b371c4b1084`

## Fixed in this pass

### Google OAuth / auth
- OAuth login CSRF: server now requires a per-tab nonce, hashes it into one-time state, echoes the hash back, and the SPA rejects callbacks whose nonce does not match this tab.
- OAuth account eligibility: suspended/inactive/rejected users cannot link or obtain a session; password and Google rules are shared via `User::authenticationBlockedReason()`.
- Email normalisation: registration, agency registration, forgot/reset use lower-case normalized emails; public agency profiles now report sale-only listing stats.
- Stale comments: storage “quota” claims and featured “paid/complimentary” UI were removed or updated.

### Storage
- Removed storage quota enforcement from vault, version, media, and legacy dealer-file upload paths.
- Removed quota/limit/over-quota/quota-history UI and API responses.
- Upload safety preserved: per-file size, extension allowlist, MIME/magic validation, malware scan, encrypted-at-rest storage, tenant scoping, private delivery.

### Featured Agencies
- Made Featured Agencies free by contract: service forces price 0 and `payment_status=waived`; API/UI no longer exposes payment/revenue controls; public badge remains truthful (`Featured`).
- Public agency profiles are now strictly sale-only.

## Remaining known gaps

| # | Gap | Status / blocker |
|---|---|---|
| 1 | Live Google OAuth E2E | Blocked: no real `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` configured. Unit/integration/OAuth behavior tests pass. |
| 2 | Live payment sandbox transactions | Blocked: no Stripe/FlutterWave sandbox credentials; not required by current free-launch scope. |
| 3 | Docker/MySQL/Redis/queue/ClamAV runtime | Blocked locally: Docker absent; MySQL server binaries present but service not reachable on 3306. Config/scripts exist and production check passes. |
| 4 | MySQL backup/restore drill | Not executed: SQLite only locally; MySQL unreachable. Existing SQLite backups recorded in storage logs. |
| 5 | Load/stress testing | Not executed. |
| 6 | Role/admin browser E2E | Not verified: Playwright API logins fail with 422/500 in this environment and the dev server is intermittently unavailable. Super-admin/agency-admin portions passed in `admin-pages`. |
| 7 | Commit/push/deploy | Blocked/not performed: requires explicit user permission. |

## No code bug found in ordinary audit paths
- No storage quota enforcement paths remain reachable.
- Public agency profile now reports only sale-only stats.
- Public properties remain sale-only.
- Feature/security/responsive tests pass.
- An earlier same-day backend run failed after concurrent DB/browser activity; SQLite `malformed` and one local-storage-state issue were environmental. The authoritative clean isolated run is **320 passed / 2587 assertions**.
- Remaining role/admin browser failures are environment/credential/service-availability issues, not observed code-logic regressions.
