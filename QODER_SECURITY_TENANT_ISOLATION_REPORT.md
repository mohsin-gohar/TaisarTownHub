# QODER Security and Tenant Isolation Report

| # | Severity | File | Lines | Vulnerability | Confidence | Result |
|---|----------|------|-------|---------------|------------|--------|
| 1 | 🟠 HIGH | docker-compose.yml | 25-30, 50-51 | Host-published MySQL/Redis and default passwords | 9/10 | Fixed: ports removed and required secrets added |
| 2 | 🟠 HIGH | backend/app/Http/Controllers/Api/WorkspaceController.php | 113, 583-599 | Public catalog legacy media/internal payload exposure | 9/10 | Fixed: public catalog media allowlist and public visibility filter |
| 3 | 🟡 MEDIUM | backend/app/Http/Controllers/Api/PropertyController.php | 152, 155, 165-171 | Private property detail lacks property-view permission gate | 9/10 | Fixed: owner agency detail requires `properties.view` |
| 4 | 🟡 MEDIUM | backend/app/Http/Controllers/Api/DocumentController.php | 393-403, 628-640 | Sensitive document category downgrade | 9/10 | Fixed: downgrade requires `documents.view_sensitive` |
| 5 | 🟡 MEDIUM | backend/app/Http/Controllers/Api/PropertyController.php; backend/app/Models/Property.php | 171; 32, 36 | Public property detail may expose buyer/seller/internal fields | 8/10 | Fixed: public allowlist |

## Additional fixes
- Sale-only public queries and legacy catalog rent filtering.
- Public Projects removed from catalog.
- Live `/api/properties?purpose=rent` returned only sale listings.

## Verification
- Catalog regression tests passed.
- Document downgrade regression passed.
- Property privacy/rent lifecycle tests passed.
- Full backend suite passed.
