# Phase 1 Route Inventory

Branch: `fix/symfony-security-advisories-20261127` @ `72c501d`

## Scope
Dual registration: routes/api_definitions.php registered at both `/api` (legacy) and `/api/v1` (versioned). v1 route names prefixed `v1.`.

## Route count
- ~203+ unversioned API route declarations (verified, not assumed).
- Legacy + v1 = ~406 total registrations (shared controllers).

## Key route groups
- Auth: login, register, logout, password reset, MFA, Google OAuth
- Agency: CRUD, staff, onboarding, subscription
- Property: CRUD, images, videos, workflow (submit/approve/reject)
- Dealer: CRUD, files, sell
- Finance: transactions, payments, commissions, schedules, void, refund
- Workspace: sync (merge/replace), scoped collections
- Documents: vault, folders, upload, download, quarantine
- Admin: agencies, users, dashboard, featured, system
- Public: properties, agencies, maps, contact

## Verification
- `php artisan route:list` runs clean.
- All routes have auth/permission middleware where required.
- Tenant scoping enforced via AgencyContext middleware.