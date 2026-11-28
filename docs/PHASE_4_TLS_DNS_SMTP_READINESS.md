# Phase 4 TLS, DNS & SMTP Readiness

Branch: `audit/phase-4-production-readiness` @ `3b800ee`

## Domain
- Production: `taisartownhub.com` (frontend on Vercel)
- Backend: `api.taisartownhub.com` or `taisartownhubbackend.infinityfree.io`

## TLS
- nginx terminates TLS 1.2/1.3, HSTS, CSP configured in nginx.conf
- Certificates mounted at runtime (not committed) — `nginx/ssl/` gitignored
- Certificates must be provisioned by operator (Let's Encrypt recommended)

## DNS
- A/CNAME records for frontend (Vercel) and backend (hosting provider)
- Must be configured by domain owner

## SMTP
- MAIL_MAILER=smtp, MAIL_HOST/PORT/USERNAME/PASSWORD from .env
- Password reset and email verification depend on SMTP
- Not tested (no credentials in this environment)

## Status: BLOCKED (external — owner must provision)