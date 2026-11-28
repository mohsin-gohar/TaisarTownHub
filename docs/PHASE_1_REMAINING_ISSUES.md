# Phase 1 Remaining Issues

Branch: `fix/symfony-security-advisories-20261127` @ `72c501d`

## Blocked (external/unavailable)
1. Docker daemon not installed — image builds, compose smoke, multi-replica unverified
2. ClamAV not available locally — real upload scanning unverified (fake-clamd tests pass)
3. TLS certificates — owner-provisioned
4. SMTP credentials — owner-provisioned
5. DNS configuration — owner-controlled
6. Third-party keys (Stripe, Flutterwave, Sentry, GA) — owner-provisioned
7. Lighthouse — run once (Performance 44, needs optimization)
8. N+1 query systematic audit — not measured
9. Deeper SEO validation (structured data, OG tags) — partially inspected

## Not blocked but not done
- Mobile/tablet viewport matrix — NOW DONE (42 tests passed across 6 viewports)
- Admin role-journey browser tests — NOW DONE (10 passed)
- Agency marketplace smoke — NOW DONE (5 passed)

## Phase 1 status
PHASE 1: INCOMPLETE — blocked items require external resources/tools. All safely completable tasks are done and verified.