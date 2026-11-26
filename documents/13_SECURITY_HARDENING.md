# TaisarTownHub — Security Hardening & Rate Limiting (Pillar 13)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

> **Historical security design:** verify every claim against current middleware, policies, migrations and tests. The current security baseline is `../docs/SECURITY.md`.


## 1. CSRF Protection

- Sanctum cookie-based auth not used (token-based), so CSRF not required for API.
- If cookie auth enabled later: `VerifyCsrfToken` middleware + `X-XSRF-TOKEN` header.

## 2. SQL Injection Defenses

- All DB access via Eloquent / parameterized queries.
- No raw string interpolation in queries.
- `whereNotIn`, `whereIn` use bound arrays.
- Input validated before query construction.

## 3. XSS Sanitization

- React escapes all interpolated values by default.
- No `dangerouslySetInnerHTML` for user content.
- Backend strips tags on free-text fields where stored.
- CSP headers recommended in production (via web server config).

## 4. Rate Limiting

| Endpoint | Limit | Key |
|---|---|---|
| POST /auth/login | 5/min | IP |
| POST /auth/register | 3/min | IP |
| POST /videos | 10/min | user+IP |
| POST /videos/{id}/like | 30/min | IP |
| POST /videos/{id}/comments | 10/min | IP |
| POST /inquiries | 10/min | IP |
| POST /subscribers | 10/min | IP |

Implemented via `throttle:X,Y` middleware. Returns 429 with `Retry-After`.

## 5. Brute-Force Protection

- Login throttle 5/min per IP.
- Presenter PIN: 3 failures → 30s lock (client + server-side if exposed).
- No account enumeration: identical error for unknown email vs wrong password.

## 6. Environment Secret Guards

- `.env` (gitignored) holds `APP_KEY`, `DB_PASSWORD`, `SANCTUM_SECRET`.
- `.env.example` documents required keys without values.
- `config/` reads via `env()` with defaults.
- Secrets never logged; `Log::info` excludes sensitive fields.
- Production: `APP_DEBUG=false`, `APP_ENV=production`.

## 7. Data Protection

- CNICs masked in public payloads; unmasked only with `view_sensitive_cnic`.
- Private purchase prices & margins never sent to `/api/catalog`.
- Tokens in sessionStorage (cleared on tab close).
- Soft deletes preserve audit trail (no silent hard deletes).

## 8. File Upload Security

- MIME whitelist: pdf, jpg, jpeg, png, webp.
- Size cap: 5MB (server-enforced).
- Data URLs stored, not executed; no path traversal (no filesystem paths from client).
- Virus scanning recommended in production (ClamAV hook).

## 9. Security Audit

See `SECURITY_AUDIT.md` for the full audit (P0/P1 findings + remediation). All P0/P1 items resolved.
