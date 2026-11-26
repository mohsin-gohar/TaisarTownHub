# Production Readiness

**Release branch:** `qa/cross-browser-multireplica`  
**Verified code commit:** `b7920297d5d0f0b97ed5e1a030fd04b24aa83e93`  
**Release PR:** #5 targets `main`; main has not been modified by this release work.

## Verified engineering gates

CI run: https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45877162123

- Backend PHP 8.3 + MySQL 8: **547 passed, 0 failed, 4,563 assertions**.
- Frontend: **176 passed, 0 failed**; TypeScript, production build and bundle guard passed.
- SEO prerender: **29 pages**, sitemap **9 URLs**.
- Public bundle guard found no private ERP data or demo credentials.
- Chromium: **46 passed, 2 skipped, 0 failed**.
- Firefox + WebKit: **14 passed, 0 failed**.
- OpenAPI parse passed.

## Docker / production-architecture verification

Docker CI run: https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45877162113

- Backend and frontend production images built successfully.
- CI and production Nginx configurations validated.
- MySQL backup and isolated restore drill passed.
- Full stack reached readiness; two backend replicas operated through the gateway.
- Shared private vault/quarantine storage verified across replicas and worker.
- Real ClamAV clean-file and threat-signature verdicts passed.
- API smoke and 13 API-backed role tests passed.
- Public interaction audit: 10 links, 0 broken; 234 buttons, 0 no-effect/click/error findings; 11 forms/flows, 0 failed.
- Admin interaction audit across Super Admin, Agency Owner, Manager, Dealer and Operator: 51 role-tab visits; 0 no-effect, click-failure or error findings.
- Ten-worker / ten-second load: 42,280 requests, 4,228 req/s; p95 4 ms, p99 44 ms; 40,772 rate-limited 429 responses, 114 other 4xx, **0 5xx and 0 network failures**.
- Eight concurrent transaction writes created eight unique deal numbers; test transactions were voided in cleanup.

## Coverage limitations

- Repository inspection was risk-ranked and targeted; this is not a claim that every tracked file was read line by line.
- The separate superseded PR #9 workflow probe reported 15/20 due test-fixture gaps (captcha, trial staff quota, dealer agency context and malware scan clearance). It is not accepted as a passing release gate.
- Lighthouse scoring, systematic N+1/query measurement, deep structured-data/Open Graph validation and independent penetration testing remain outstanding.

## Production launch requirements

Repository CI cannot provision or verify the live owner's DNS/TLS, SMTP, Google OAuth, Stripe/Flutterwave credentials, monitoring accounts, production ClamAV daemon, off-site backup target, production data migration, staging deployment or rollback rehearsal. Owner-approved privacy/retention and incident-response policies are also required.

The Vercel preview deployment was blocked by the account's daily deployment quota. No production deployment, live database mutation or production credential change was performed.
