# Critical Repair Security Audit

Branch: `fix/critical-security-integrity-repairs` @ `2fb1996`

## Fixes
1. Agency staff permission escalation — restricted delegation to requester's permission set (with legacy alias expansion)
2. Inquiry tenant isolation — denied unscoped queries for non-platform users
3. SoftDeletes on Role/Sector/Block — proper soft delete behavior

## Verified
- Tenant isolation tests pass
- RBAC tests pass
- Auth tests pass