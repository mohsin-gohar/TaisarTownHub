# Critical Repair Tenant Isolation

Branch: `fix/critical-security-integrity-repairs` @ `2fb1996`

## Fix
InquiryController::findScopedForAgent() and index() now deny access when a non-platform user has no valid agency context. Previously, unscoped queries returned all inquiries across tenants.

## Tests
- Inquiry tenant isolation verified (Agency A cannot read/update/delete Agency B inquiries)