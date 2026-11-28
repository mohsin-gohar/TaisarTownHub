# Phase 6 SMTP Verification

Branch: `release/phase-6-production-deployment` @ `f0209e8`

## Status: NOT VERIFIED (external)

## Required (owner action)
- SMTP credentials (host, port, username, password)
- MAIL_FROM_ADDRESS, MAIL_FROM_NAME
- Test registration verification email
- Test password reset email

## Configuration
- MAIL_MAILER=smtp (default in .env.example)
- MAIL_SCHEME=tls, MAIL_PORT=587

## Blocked
- No SMTP credentials available
- No email delivery tested