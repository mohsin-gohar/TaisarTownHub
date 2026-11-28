# Phase 5 Hostinger Release Runbook

Branch: `audit/phase-5-final-stabilization` @ `fc5ad34`

## Target
- Frontend: https://taisartownhub.com (Vercel)
- Backend: https://taisartownhubbackend.infinityfree.io (InfinityFree)

## Prerequisites
- PHP 8.2+ selected in hosting panel
- MySQL database created
- .env configured with hosting credentials
- APP_KEY generated locally

## Steps
1. Upload backend/ contents to htdocs/
2. Move public/index.php to htdocs/index.php (adjust paths)
3. Upload .env with production values
4. Import database via phpMyAdmin
5. Set storage permissions 755/775
6. Configure CORS for Vercel origin
7. Update Vercel VITE_API_URL
8. Test /up, /api/public/properties, login

## Blocked
- Not deployed (no hosting access in this session)
- See docs/INFINITYFREE_DEPLOYMENT.md for full guide