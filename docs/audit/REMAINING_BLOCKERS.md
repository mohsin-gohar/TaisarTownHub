# Remaining Blockers

The integrated QA code has passed CI and disposable Docker integration gates. These are the remaining launch gates; repository tests must not be represented as completing them.

1. **Live infrastructure:** provision and verify production DNS, TLS certificates, CDN/load-balancer trusted proxies and the real hosting environment.
2. **Credentials:** configure and test SMTP, Google OAuth, Stripe/Flutterwave webhooks, Sentry/LogRocket and analytics keys using owner-controlled secrets.
3. **Production malware scanning:** verify the production ClamAV daemon/socket and fail-closed behavior on the actual host. CI verified a disposable ClamAV container only.
4. **Backups:** connect the real off-site backup target, protect APP_KEY separately, and rehearse restore against a production-like copy with documented RPO/RTO. CI verified a disposable MySQL backup/restore drill.
5. **Staging and rollback:** deploy the release to staging, run smoke tests with owner-approved accounts, rehearse rollback and verify migrations/data compatibility.
6. **Production data audit:** map legacy unowned records, verify agency ownership and perform a protected migration review before enabling tenants.
7. **Performance and SEO:** run Lighthouse/Core Web Vitals, systematic N+1/query profiling, deep structured-data/Open Graph validation and independent penetration testing.
8. **Owner policies:** approve privacy/retention, incident response, MFA enforcement and financial reversal procedures.
9. **Deployment provider quota:** the Vercel preview deployment hit the account's daily deployment limit; wait for quota reset or have the owner adjust the Vercel plan/limits before retrying.

## Supplemental workflow probe

The superseded PR #9 workflow probe reported 15/20. Its test setup did not solve the inquiry captcha, exceeded the seeded trial staff limit, omitted dealer agency context and attempted document sharing before malware scan clearance. It is not a passing release gate; the corresponding integrated backend feature/security tests and the current full-stack browser gates are green.

## Integration status

PR #5 is the integrated release PR to `main`. CI and Docker CI passed on verified code commit `b7920297d5d0f0b97ed5e1a030fd04b24aa83e93`. Production has not been deployed or modified.
