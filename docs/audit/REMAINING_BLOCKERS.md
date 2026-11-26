# Remaining Blockers

The integrated QA code has green CI and Docker CI. These are the remaining launch gates; they must not be represented as completed by repository tests alone.

1. **Live infrastructure:** provision and verify production DNS, TLS certificates, CDN/load-balancer trusted proxies and the real hosting environment.
2. **Credentials:** configure and test SMTP, Google OAuth, Stripe/Flutterwave webhooks, Sentry/LogRocket and any analytics keys using owner-controlled secrets.
3. **Production malware scanning:** verify the production ClamAV daemon/socket and fail-closed behavior against the actual host; CI's disposable ClamAV container is not a production deployment.
4. **Backups:** connect the real off-site backup target, protect APP_KEY separately, and rehearse restore against a production-like copy with documented RPO/RTO.
5. **Staging and rollback:** deploy the integrated release to staging, run smoke tests with owner-approved accounts, rehearse rollback and verify migrations/data compatibility.
6. **Production data audit:** map legacy unowned records, verify agency ownership and perform a protected migration review before enabling tenants.
7. **Performance and SEO:** run Lighthouse/Core Web Vitals, systematic N+1/query profiling, deep structured-data/Open Graph validation and independent penetration testing.
8. **Owner policies:** approve privacy/retention, incident response, MFA enforcement and financial reversal procedures.

## Supplemental workflow probe

A separate PR #9 workflow probe reported 15/20. Its test setup did not solve the required inquiry captcha, attempted to exceed the seeded trial staff limit, omitted dealer agency context and attempted document sharing before malware scan clearance. It is not a passing release gate and was not merged into the integrated release workflow. The corresponding backend feature/security suite is green.

## Integration status

PR #11 has been merged into `qa/cross-browser-multireplica`. PR #5 is the release PR to `main`; do not merge it unless its latest CI and Docker CI checks remain green. Production has not been deployed or modified.