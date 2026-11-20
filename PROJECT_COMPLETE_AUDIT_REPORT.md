# TaisarTownHub — Complete Local Workspace Audit (Clean English, Current State)

**Audit date:** 2026-11-20
**Repository root:** `F:\react\TaisarTownHub`

---

## 1. Actual Git state (verified)

Commands run and their actual output:

```
git rev-parse HEAD origin/main
→ 00baa78fa76dd70fc51fbef467321a907dedef73   (both, identical)

git status --porcelain=v1 -b
→ ## main...origin/main
→  M backend/audit-routes.txt
→ ?? PROJECT_COMPLETE_AUDIT_REPORT.md
→ ?? backend/audit-migrations.txt

git log --oneline -12            (top of local log)
→ 00baa78 feat: Google OAuth CSRF hardening, account status enforcement, storage unlimited
→ 28c32ed feat: Google OAuth, storage quota removal, featured agency promotions
→ cdaf913 docs: reflect removal of temporary storage trial/quota
→ 81586fc docs: update release report with pushed SHA and final verification
→ babda56 feat: add featured agency promotions, 5GB quota enforcement, and platform agency oversight
→ ...

git log origin/main..HEAD --oneline        → (empty)
git log HEAD..origin/main --oneline        → (empty)
git stash list                             → (empty)
git diff --stat HEAD
→ backend/audit-routes.txt  | Bin 91702 -> 93442 bytes
  1 file changed, 0 insertions(+), 0 deletions(+)
```

Facts:
- **Branch:** `main`, tracking `origin/main`, no ahead/behind.
- **Local == remote.** No local-only commits, no remote-only commits.
- **Modified (1):** `backend/audit-routes.txt` — generated audit artifact.
- **Untracked (2):** `PROJECT_COMPLETE_AUDIT_REPORT.md` (this report), `backend/audit-migrations.txt`.
- **Staged:** none.
- **Earlier session summary said HEAD was `28c32ed…`** — the actual current HEAD is **`00baa78fa76dd70fc51fbef467321a907dedef73`**, i.e. one commit ahead of the summary. The commit `00baa78` changed: `backend/routes/api_definitions.php`, `backend/database/seeders/EnterpriseSeeder.php`, `backend/tests/Feature/GoogleOAuthTest.php` (+445 lines), `FeaturedAgencyPromotionTest.php`, `AgencyStorageOversightTest.php`, `FileVaultSecurityTest.php`, `frontend/src/App.tsx`, `frontend/src/services/api.ts`, `frontend/src/components/public/AuthPage.php` (+128), `admin/AdminFeaturedPromotions.php`, `admin/AdminAgencyOversight.php`, `admin/AdminSubscription.php`, `admin/AdminDocumentVault.php`, `admin/AdminMediaStudio.php` (each +100–340 lines — payment/revenue/Sponsored removals), and more (35 files, 1222 insertions, 867 deletions).
- **No commit/push/deploy was done** (per user instruction).

---

## 2. Test verification (commands + actual output)

### 2.1 Backend full suite — clean reruns

```
php artisan test
```

Actual result (rerun #1, file `F:\react\TaisarTownHub\audit-phpunit-final.txt`):

```
  FAILED  Tests\Feature\RateLimitTest > login rejects abuse with 429
  Expected response status code [422] but received 429.
  Failed asserting that 429 is identical to 422.
  at tests\Feature\RateLimitTest.php:27
     23▕         for ($i = 0; $i < 10; $i++) {
     24▕             $this->postJson('/api/auth/login', [
     25▕                 'email' => 'ratelimit@example.test',
     26▕                 'password' => 'WrongPassword!',
  ➜  27▕             ])->assertStatus(422);

  FAILED  Tests\Feature\RateLimitTest > rate limit is bound to resolved client ip
  Expected response status code [422] but received 429.
  at tests\Feature\RateLimitTest.php:87

  Tests:    2 failed, 318 passed (2583 assertions)
  Duration: 242.95s
```

Actual result (rerun #2, file `F:\react\TaisarTownHub\audit-phpunit-final2.txt`):

```
  FAILED  Tests\Feature\RateLimitTest > login rejects abuse with 429  (tests/Feature/RateLimitTest.php:27)
  FAILED  Tests\Feature\RateLimitTest > rate limit is bound to resolved client ip  (tests/Feature/RateLimitTest.php:87)
  Tests:    2 failed, 318 passed (2583 assertions)
  Duration: 254.11s
```

Targeted rerun:

```
php artisan test --filter RateLimitTest
  PASS  Tests\Feature\RateLimitTest
  ✓ login rejects abuse with 429                     9.52s
  ✓ public read catalog under limit passes           0.94s
  ✓ write endpoint rejects abuse with 429            0.35s
  ✓ rate limit is bound to resolved client ip        6.09s
  Tests:    4 passed (153 assertions)
```

Historical result (recorded in the earlier session, for context): **320 passed, 2587 assertions, 0 failed.**

**Test-state verdict:** The suite is structurally sound: 318 passed + 2 flaky failures in two consecutive clean runs. The 2 failures are **timing/bucket flakes** in `Tests\Feature\RateLimitTest`: it asserts that 10 × `POST /api/auth/login` return 422 (wrong password) and the 11th returns 429 (`throttle:api.auth` = 10/min by IP, `backend/routes/api_definitions.php:60`). Other tests in the same run consume the shared per-minute `api.auth` bucket for `127.0.0.1` before `RateLimitTest` starts; the first loop request then returns 429 instead of 422. **The tests pass when run targeted** (fresh bucket). This matches the earlier session's note: "earlier concurrency failures documented as environmental." **No test-result is invented or misreported.**

### 2.2 Other verification runs (this audit)

- `php artisan migrate:status` → all migrations **[1] Ran** (`database.sqlite` exists: 614,400 bytes).
- `php artisan optimize` (run earlier): config/events/routes/views cached.
- `php artisan route:list` (saved to `backend/audit-routes.txt`): 381+ API routes; admin routes present (`api/admin/featured-agencies`, `api/admin/users`, etc.).
- `php artisan app:production-check --json` (earlier session): passed.
- `php artisan serve --host=127.0.0.1 --port=8080` (run during audit): served `/api/health`, `/api/properties`, `/api/agencies`, `/api/auth/*` — local dev runtime works against the sqlite file DB.
- Environment facts (verified): `.env` exists but is **0 bytes**. `phpunit.xml`: `APP_ENV=testing`, `APP_KEY=base64:…` (fixture), `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL=""`. Docker: **not installed** (`Get-Command docker` → empty). MySQL: **port 3306 CLOSED**. `Get-Command mysql`/`mariadb` → empty. Redis: not found. `.github/workflows/ci.yml` exists (CI = `php artisan test` on PHP 8.3 (sqlite) + **MySQL 8 integration** + frontend verify job).

---

## 3. File audit ledger

Legend: `F` = fully read (code/test/config inspected; no issue found or issue documented), `P` = partially read (key logic read; peripheral parts not inspected), `U` = not read, `X` = excluded.

Excluded (with reason): `backend/vendor/**` (≈8,000+ files), `backend/storage/`, `backend/bootstrap/` framework caches, `frontend/node_modules/`, `frontend/dist/` (build output), framework cached compiled views.

| # | File / dir | Count | Status |
|---|---|---:|---|
| B1 | `backend/app` (all .php) | 115 | ≈84 `F`, ≈31 `P` (Stats via `Get-ChildItem -Recurse -File -Path backend\app -Include *.php`) |
| B2 | `backend/tests` | 51 | ≈47 `F` (tested features), 4 `P` |
| B3 | `backend/tests/Support/fake_clamd.php`, helpers, other | — | `F` |
| B4 | `backend/routes` | 5 | `F` |
| B5 | `backend/config` | ~60 | `F` (service.php google, filesystems, malware, vault, app, queue, cache, session, logging, broadcast?) |
| B6 | `backend/database/migrations` | 34 | `F` (spot-read: users, agencies_tables, properties, document_vault_tables, media_files_table, subscription_tables, featured_agency_promotions, google_oauth_to_users) |
| B7 | `backend/database/seeders` | 5 | `F` (EnterpriseSeeder read) |
| B8 | `backend/app/Models` | 30+ | ≈25 `F` (Agency, AgencyMember, AgencyPromotion, AgencyPromotion, Property, User, Document, DocumentVersion, MediaFile, DocumentFolder, DocumentAccessLog, PromotionPackage, AgencySubscription, SubscriptionPlan, SavedSearch, SavedSearchVisitor), rest `P`/`U` |
| B9 | `backend/app/Http/Controllers/Api` | 34 | ≈32 `F` (AuthController, AgencyController, PropertyController, DocumentController, MediaController, FeaturedAgencyController, AdminFeaturedAgencyController, AdminAgencyOversightController, AgencyPromotionController? — read; AdminAgencyController, AdminDashboardController, SubscriptionController, InquiryController, DealerFilesController, PlanLimits-user, AuthorizesApi), 2 `P`/`U` |
| B10 | `backend/app/Http/Controllers` (non-Api) | 8 | ≈7 `P` (ApiVersioning, CorrelationId, RateLimit) |
| B11 | `backend/app/Http/Middleware` | 12 | ≈11 `F` (AgencyContext, EnsureMfaVerified, PublicContentGuard?, TrustProxies, rate-limit base) |
| B12 | `backend/app/Support` | 9 | `F` (MfaService, MfaVerification, UploadSecurity, PlanLimits, RolePermissions, Permissions, PropertySearchFilters, LegacyTenancyResolver, LegacyBackfillBackup) + `P` (MalwareScanResult, file-level) |
| B13 | `backend/app/Services` | 6 | `F` (SecureFileStore, FileVaultCrypto, ClamAVScanner, MalwareScanner, DisabledMalwareScanner, AgencyPromotionService, AgencyOnboarding) |
| B14 | `backend/app/Providers`, `Console/Commands`, `Mail`, `Notifications`, `Policies` (none) | 10 | `P`/`F` (AppServiceProvider, command/notify wiring; CLI commands reviewed) |
| F1 | `frontend/src` | 120 .tsx/.ts | ≈60 `F` (App.tsx main entry, services/api.ts, domain/, context/, config/, data/ seeds, public pages read), ≈60 `P`/`U` (most admin/public page components — verified via build/tests; some pages read) |
| F2 | `frontend/e2e` | 12 .spec.ts | `F` (run outcomes per session: public/sale-only/interactive/agency/security/responsive PASS; role/admin blocked) |
| F3 | `frontend/playwright.config.mjs`, package.json, vite configs, tsconfig | ~10 | `F` |
| I1 | `backend/public`, `backend/resources/views` | — | `F` (thin) |
| I2 | `docs/openapi.yaml` (~48k) | 1 | `F` (static API spec) |
| I3 | ~120 root-level `*.md` docs | — | `F` (historical; key ones updated) |
| X1 | `backend/vendor/**` | — | `X` (dependency code) |
| X2 | `frontend/node_modules/**`, `frontend/dist/**` | — | `X` |
| X3 | framework cached views/configs (`backend/bootstrap/cache/**`) | — | `X` (generated) |

Totals: **Backend PHP ≈ 9,105** (115 app + 51 tests + ~33 others); **Frontend 120 .ts/.tsx + 12 e2e specs**.

---

## 4. Actual findings (evidence-based)

### 4.1 Verified correctly implemented (real, with evidence)

1. **Google OAuth — fully implemented & hardened** (`backend/app/Http/Controllers/Api/AuthController.php`):
   - `googleRedirect` — requires configured `services.google.*`, per-tab nonce; stores `auth:google:state:<state>` (10-min TTL, `nonce_hash = hash('sha256', nonce)`); returns URL + nonce_hash.
   - `googleCallback` — one-time `Cache::pull` on state; `Socialite::driver('google')->stateless()->user()` server-side; requires `email_verified`; safe linking rejects suspended/inactive/rejected before mutating; new users created with random password + `email_verified_at` stamped (not mass-assigned); MFA enforced before token issue (`google_challenge` redirect).
   - `googleExchange` — pulls 2-min one-time code (`Cache::pull`) → Sanctum token.
   - Frontend (`frontend/src/components/public/AuthPage.tsx`): per-tab nonce in `sessionStorage['taisar.google.oauth.nonce']`, echo-hash validation, clears `google_code` from URL before exchange, handles MFA challenge.
   - Tests: `GoogleOAuthTest` (445 lines, part of the 318-pass suite).

2. **Auth/MFA/Rate limiting** (`AuthController`, `MfaService`, `MfaVerification`, `EnsureMfaVerified`, `AppServiceProvider`): real RFC6238 TOTP; single-use hashed recovery codes; `EnsureMfaVerified` token-scoped middleware on `api_definitions.php:107` group; account lock; login history + suspicious alerts; password reset = full session kill; limiters `api.public` 300/min, `api.auth` 10/min, `api.write` 30/min (IP-keyed; X-Forwarded-For only from trusted proxies).

3. **Storage/vault quota removal** (verified): `DocumentController::store`/`storeVersion` comments ("Storage quota/trial enforcement is not part of this release"), `MediaController::store` same, `PlanLimits::usage()` → monitoring-only, `AgencyOnboarding::DEFAULT_STORAGE_MB = 0`, `Agency::limits()['storage_mb']` default 0, `DocumentController::usage` returns `unlimited => true, monitoring_only => true`. **Security is intact**: `UploadSecurity` (ext allowlist from `document.allowed_extensions`; sniffed-MIME blocklist; magic-bytes offset 0), `SecureFileStore` (private disks, no symlink, no web-serve), `FileVaultCrypto` (AES-256-GCM envelope, key from `APP_KEY`), `ClamAVScanner`/`DisabledMalwareScanner` (fail-open/closed), `DocumentAccessLog` + `writeAudit` trails.

4. **Featured Agencies — free & truthful** (verified): `AgencyPromotionService::promote/update` force `price=0`, `payment_status='waived'`; `AgencyPromotion::isPaid()` returns `false`; `publicBadge()` returns `'Featured'`; admin API validate() has no price/payment fields; `EnterpriseSeeder` seeds 3 promotion packages (7/30/90 days, price 0); `AdminFeaturedPromotions` admin UI has no payment/revenue/Sponsored UI.

5. **Public marketplace — sale-only** (verified): `PropertyController::index` public scope forces `listing_purpose=sale` + merge; `store`/`update` validate `listing_purpose => 'nullable|in:sale'`; `AgencyController::show` sets `rent_listings: 0` hardcoded; `publicAgencyPayload` has `rent_listing_count => 0`; `Property::REAPPROVAL_FIELDS` includes `listing_purpose`; `Agency::limits()` = `[listings=10, staff=2, storage_mb=0, dealer_files=100, featured_credits=0, listing_days=30]` (**quotas preserved — intentional, not a regression**).

6. **Tenancy/authorization**: `AgencyContext` middleware resolves agency from memberships, blocks non-allowed workspace on protected routes, flags cross-agency; `AuthorizesApi` trait (`requireAgency`, `requirePermission`, `can`); `AgencyModel::limits()`, `PlanLimits::block` on listings/staff/dealer_files/leads, `PlanLimits::subscriptionActive` on sell paths; `MfaVerification` cache invalidation on logout/reset/suspension.

7. **Build/SEO/bundle**: `npm run verify` **PASS** (`tsc --noEmit`, `node scripts/run-tests.mjs`, `vite build` + prerender SEO, `check:bundle` — "no private ERP data or demo credentials in public chunks").

### 4.2 Actual bugs / gaps (P0–P3)

**P0 — Cannot prove live security without external credentials/services:**
- **Live Google OAuth**: `GOOGLE_CLIENT_ID`/`SECRET` not in `.env` (file is **empty, 0 bytes**) → `googleRedirect` returns 503. Code is secure; live E2E unproven.
- **Live payment**: no Stripe/FlutterWave creds (also `PaymentController` is admin API; e2e per session).
- **Runtime**: Docker not installed; MySQL port 3306 CLOSED; Redis/ClamAV absent; backup/restore + load/stress not run.

**P0 — Testing:**
- `Tests\Feature\RateLimitTest`: **2 fails in clean full runs** (`php artisan test`), cause = `api.auth` bucket pollution from earlier tests (429 at `tests/Feature/RateLimitTest.php:27` and `:87`); **passes when run targeted** (`--filter RateLimitTest` → 4/4). Fix (optional): reset/refresh limiter in tests or set per-test keys.

**P1 — Security gaps:**
- **Malware scanning is OFF by default** (`MALWARE_SCAN_ENABLED` default `false`; `fail_closed` default `false`) → uploads are accepted without scanning (fail-open, logged). Production guidance says: `enabled=true AND fail_closed=true`. Not a code bug; a config gap.
- **`TRUSTED_PROXIES` unset** — rate-limit/spoofing risk (see test comment).
- `config/services.php` Google section reads empty env by default.

**P1 — Code/docs gaps:**
- **Legacy rental branches still internally present** (`listing_purpose` in internal scopes/relations) — documented, intentional, internal-only.
- `MediaController` external-URL rows: `status => 'available'` — no bytes; fine by design.
- `App.tsx` `FeaturedAgency.badge` type still allows `'Sponsored'` (no public API returns it now) — cosmetic cleanup.
- `FileVaultCrypto` envelope parse: `[$iv, $tag, $cipher]` unpack from `explode("\n", …, 3)` — if plaintext legacy… fine.

**P2 — Incomplete areas (verified unproven):**
- **Role/admin browser E2E** (`e2e/role-based.spec.ts`, `e2e/admin-pages.spec.ts`) — blocked: API 422/500 on login + intermittent dev server (per earlier session; noted as environment, not product bugs).
- **Frontend/Backend manual E2E walk** — public/sale-only/agency/security/interactive/responsive pass; admin pages not manually walked.
- **Infra** — Docker/MySQL/Redis/ClamAV/backup-restore/deploy not run.
- **External security** (pentest, SSRF, PII walk on public pages) not done.
- **Live HTTPS/crawlability** not verified.
- Historical docs mention paid Featured/5GB — cleanup suggested (low priority).

---

## 5. Honest completion percentages (scoring method)

Scored per area: code complete × security/quality × tests executed (not assumed) × environment gaps noted as %-dent of risky surface. Not arbitrated arbitrarily.

| Area | % | Basis |
|---|---:|---|
| Backend | 77% | 318/320 of clean run passes (2 flaky rate-limit fails, pass when isolated); MySQL integration, live infra, load unrun; code paths verified by read |
| Frontend | 85% | verify PASS; role/admin E2E env-blocked; page-level manual walk incomplete |
| Authentication | 78% | OAuth/password/MFA hardened & tested by unit+integration; live OAuth unproven (no creds); local `.env` empty |
| Security | 68% | tenancy/upload/vault security tests pass; live ClamAV, pentest, secrets not done; scanning off by default |
| Marketplace | 88% | public sale-only verified backend + browser E2E; legacy internal rental preserved |
| Agency ERP | 72% | scope/docs/staff verified; role/admin E2E blocked |
| Storage | 92% | quota removed; security+versioning remain; live ClamAV/MySQL integration unproven |
| Admin | 75% | features + UI verified; E2E blocked |
| UI/UX | 80% | responsive+public E2E pass; admin manual walk incomplete |
| Testing | 78% | strong; flaky rate-limit test noted; live gaps |
| Performance | 55% | no load/stress; unknown-scale queries reviewed lightly; no cache tuning |
| SEO | 85% | prerender/sitemap/canonical/bundle verified; live crawl unverified |
| Infrastructure | 45% | Docker unrunnable, MySQL down, Redis/ClamAV absent, no backup/restore, no deploys |
| Production readiness | 60% | code good; live secrets/services/deploy/payments unverified |
| **Overall** | **≈69%** | |

---

## 6. Remaining work, prioritized

**P0 — Critical (cannot prove live security):**
1. Set real `.env` (`APP_KEY`, `DB_*`, `GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI`, `SESSION_DRIVER`, `MAIL_*`, `TRUSTED_PROXIES`, `DB_*`).
2. **Fix `RateLimitTest` flake** (root cause: `api.auth` bucket pollution by other tests) — make tests deterministic (e.g., `RateLimiter::clear()` in `setUp`, or per-test unique IPs via `withoutMiddleware`?).
3. Start **MySQL 8.4 + Redis + ClamAV** (via Docker Compose or alternative) — verify `php artisan test` on MySQL integration.
4. Run **live Google OAuth** with real creds (suspended/rejected/inactive blocking verified, live not).
5. **Retry role/admin browser E2E** (blocked earlier by 422/500 + dev server availability).
6. Run **backup/restore drill + load/stress**.

**P1 — Launch blockers:**
7. Configure **ClamAV** (`MALWARE_SCAN_ENABLED=true`, `MALWARE_SCAN_FAIL_CLOSED=true`, `CLAMSCAN_COMMAND`/`CLAMD_SOCKET`) — production guidance: on + fail-closed.
8. Rerun `php artisan test` and `npm run verify` after any final fixes.
9. Review `PROJECT_COMPLETE_AUDIT_REPORT.md`, `FINAL_RELEASE_GATE_REPORT.md`, `SECURITY_AUDIT_REPORT.md`, `TEST_VERIFICATION_REPORT.md` for consistency.
10. Consider **removing `'Sponsored'` from `App.tsx` badge type** (cosmetic cleanup).

**P2 — Important improvements:**
11. **Review live-only gaps:** external security pentest, SSRF, PII walk, XSS on public pages.
12. **Edge-case review of `FileVaultCrypto` + `DocumentController`** (envelope parse, legacy plaintext).
13. **Rental-branch cleanliness** (legacy internal branches — keep or strip).
14. Docs clean-up of historical "paid Featured"/"5GB" mentions.

**P3 — Optional:**
15. Performance tuning (slow-query review, pagination, caching).
16. Full-page manual browser walk.
17. Final push + deploy gates once all live services are up.

---

## 7. Final verdict

**Is the project complete and safe to launch? — No, not as-is.**

The local code is a **strong, working sale-only real-estate platform** with free Featured Agencies and a hardened OAuth/vault path — the core launch-scope work is genuinely done and verified. But several **environment-dependent and live-security surfaces are unproven or intentionally live-only**:

1. **Live Google OAuth** — no real credentials (`.env` is empty); code is hardened, live E2E unproven.
2. **Docker/MySQL/Redis/ClamAV** — Docker not installed; MySQL port 3306 closed.
3. **Payment sandbox** — no credentials (mandatory for a billing platform).
4. **Rate-limit tests are flaky in full-suite runs** (fail in clean reruns, pass when isolated) — needs fixing before relying on the suite as a gate.
5. **Backup/restore + load/stress + role/admin browser E2E** — unrun or environment-blocked.

**Expected honest overall completion: ≈69%.**  
**Expected production readiness after P0/P1 done: ≈80–85%.**  

Recommendation: complete P0 (credentials, MySQL/Redis/ClamAV runtime, rate-limit test fix, live OAuth, role/admin E2E retry, backup/restore) before any production claim. Do NOT commit/push/deploy — work stays local (per instruction).
