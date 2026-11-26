# Feature Coverage Matrix

## Backend controllers (audited)

| Controller | Status | Notes |
|---|---|---|
| AuthController | Reviewed | Sanctum, registration, password reset, Google OAuth, MFA |
| AgencyController / AgencyStaffController | Reviewed | Registration, onboarding, staff invites |
| PropertyController | Reviewed | Workflow fields, approval, media |
| TransactionController / FinanceController | Reviewed + hardened | Money precision, tenant scoping, overpayment guards |
| DocumentController | Reviewed | Private vault, scan lifecycle, quarantine, share/download |
| MediaController | Reviewed | Upload, sale attribution audit |
| WorkspaceController | Hardened | Tenant isolation, replace guards, catalog allowlists |
| SubscriptionController | Reviewed | Agency-scoped plans/invoices |
| Admin*Controller | Reviewed | Oversight, users, agencies, featured, system |
| Inquiry/Lead/Support/Notification/Map/Dealer/Project | Reviewed | CRUD, tenancy, permissions |

## Frontend

- Routes/pages/components enumerated (253 non-node_modules files). Lint/typecheck/build pass.
- E2E specs present (13 files) but not re-run locally (no seeded demo DB).

## Roles

Guest, customer, agency owner/manager/staff, dealer/agent, finance user, platform staff, super admin. Role catalogue discovered in `App\Support\Permissions`.

## CRUD coverage

All major modules (properties, deals, payments, commissions, schedules, documents, media, inquiries, dealers, sellers, buyers, files, plots, projects, testimonials, subscribers, CMS) support create/read/update/delete-or-archive with permission gates and tenant scoping.
