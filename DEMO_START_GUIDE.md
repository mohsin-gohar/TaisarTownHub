# TaisarTownHub — Demo Start Guide

## Purpose
Quick-start instructions for local demonstration of the agency-first marketplace. Uses SQLite backend; no XAMPP/MySQL required beyond what is already running.

## Prerequisites
- Node.js v20+ (v18 lts also works)
- PHP 8.2+ with extensions: php-mbstring, php-xml, php-curl, php-zip
- Composer (for backend dependencies)
- Git (for version control)

## Backend Setup

### 1. Directory
```
F:\react\TaisarTownHub\backend\
```

### 2. Environment
```bash
cp .env.example .env
# The default .env uses SQLite (no database server required)
# Verify: DATABASE_URL=sqlite
# If MySQL was configured, ensure only the disposable 3310 instance is used
# XAMPP PIDs 1012/14360 on 3306/3307 must NOT be touched
```

### 3. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 4. Generate Key
```bash
php artisan key:generate
```

### 5. Migrate & Seed
```bash
php artisan migrate:fresh  # creates all tables, runs default seed
# Or step-by-step:
php artisan migrate:fresh --seed  # migrates + runs AgencySeed (verified agencies + properties)
# Verified seed data:
# - agency-demo: "TaisarTownHub Premier Advisory" (owner: admin@taisartownhub.com)
# - agy_7e27e6c466591c4b: "Rise Ground Properties"
# - 10 properties total, all Taisar Town sale listings
# - approval_status: approved, marketplace_visibility: public
```

### 6. Verify Backend Running
```bash
php artisan serve --port=8000
# or
php artisan serve  # defaults to port 8000
```

Visit: http://127.0.0.1:8000/api/agencies (should return JSON agencies)

## Frontend Setup

### 1. Directory
```
F:\react\TaisarTownHub\frontend\
```

### 2. Install Dependencies
```bash
npm install
```

### 2. Run Dev Server
```bash
npm run dev
# Vite dev server starts on http://127.0.0.1:3000
```

### 3. Verify Application
Visit: http://127.0.0.1:3000

Expected pages:
- **Homepage**: Premium 12-section layout with navy/gold identity
- **Agencies**: `/agencies` - directory of verified agencies
- **Agency Profile**: `/agencies/{slug}` - e.g., `/agencies/agency-demo`
- **Properties**: `/properties?purpose=sale` - sale listings only
- **Property Detail**: Click any property card to view details modal

## Full Demo Workflow

### Visitor Flow
1. Open http://127.0.0.1:3000 (homepage)
   - Verify navy/gold logo (TT monogram)
   - Verify 12-section structure
   - No Rent or Projects navigation
   - Hero section with "Discover Premium Properties in Taisar Town"
   - Featured Verified Agencies section
   - Browse All Agencies CTA works → /agencies

2. Navigate to http://127.0.0.1:3000/agencies
   - Agency directory with search and filters
   - Agency cards with logos, names, verified status, listing counts
   - "View All Agencies" CTA

3. Click on an agency card → http://127.0.0.1:3000/agencies/{slug}
   - Agency profile with cover image, logo, name
   - Verification badge
   - Description and contact information
   - Approved public sale listings (property cards)
   - Contact agency form
   - WhatsApp CTA if configured

4. Click a property card → details modal
   - Main property image
   - Sale price in PKR
   - Property type, phase, sector, block
   - Location, bedrooms & bathrooms
   - Short description
   - Agency name
   - No owner CNIC or private documents exposed

5. Contact the agency via form
   - Form submits to actual backend
   - Success/failure feedback

### Agency User Flow
1. Log in at http://127.0.0.1:3000/login
   - Use: admin@taisartownhub.com / password (see seed data)

2. Open agency dashboard
   - Navigate to Secure File Vault
   - Upload owner documents (CNIC, ownership agreements, etc.)
   - Manage property visibility toggle
   - Publish or hide eligible approved listings

2. Manage property visibility
   - In dashboard: "AdminProperties" component
   - Toggle visibility per listing with confirmation dialog
   - Published listings appear on public agency profile
   - Hidden listings disappear immediately from all public API results
   - Unhide eligible approved listings anytime

3. Secure file upload
   - In dashboard: "AdminDocumentVault" module
   - Upload: CNIC documents, ownership agreements, payment proofs, photographs, videos, floor plans, legal documents
   - Files validated, scanned for viruses, encrypted at rest
   - stored in `storage/app/private/agencies/{agency_id}/`
   - Download via signed URLs (15-minute validity)

## Performance & Build Commands

### Development
```bash
# Frontend dev server
cd frontend && npm run dev

# Backend API
cd backend && php artisan serve

# Full stack (concurrently recommended)
# Two terminal windows: one for frontend, one for backend
```

### Production Build
```bash
# Frontend
cd frontend
npm run build  # vite build → dist/

# Prerender (generate sitemap + robots + per-route HTML)
cd frontend
npx tsx scripts/prerender.mts

# Verify output
ls dist/*.html  # 27 pages
cat dist/sitemap.xml  # 8 URLs
cat dist/robots.txt  # 6 disallow rules
```

### Type Check
```bash
cd frontend
npx tsc --noEmit  # clean, no errors
```

### Test Suite
```bash
# Backend (SQLite, verified)
cd backend
php artisan test  # 266 tests, 2129 assertions OK

# Frontend type check
cd frontend
npx tsc --noEmit  # clean

# Prerender
cd frontend
npx tsx scripts/prerender.mts  # generates sitemap/robots
```

## Troubleshooting

### Common Issues
1. **`ENOENT: sqlite database file not found`**
   - Run `php artisan migrate:fresh --seed` to create SQLite database
   - Verify `.env` has `DB_DATABASE=database.sqlite`

2. **`ECONNREFUSED connect 127.0.0.1:3000`**
   - Ensure `npm run dev` is running in frontend directory
   - Vite may take ~2s first startup to compile

3. **`Authentication failed` on login**
   - Seed data creates: `admin@taisartownhub.com` with password `password123`
   - Use Laravel's default or reset via `php artisan db:seed --class=UserSeed`

4. **Properties not appearing on agency profile**
   - Verify property has `approval_status = 'approved'`
   - Verify `marketplace_visibility = 'public'`
   - Check agency owns the property (tenant isolation)

5. **Prerender fails: `dist/index.html not found`**
   - Run `vite build` first, then `npx tsx scripts/prerender.mts`

### Environment Notes
- Local demo uses SQLite — no MySQL configuration needed
- XAMPP processes (PIDs 1012/14360 on 3306/3307) must not be touched
- Only disposable 3310 instance was stopped (already handled)
- All CI-verified vault state remains on main branch (green runs)