# TaisarTownHub — Billion-Dollar Real Estate ERP & Public Portal

**Taiser Town Scheme 45, Karachi** — Enterprise Real Estate Platform featuring a luxury public showcase, 12 verified partner agencies, interactive video walkthroughs, and an advanced Real Estate ERP dashboard.

---

## Architecture Overview

The project is structured into two dedicated applications:

```text
TaisarTownHub/
├── frontend/             # React 19 + TypeScript + Vite + Tailwind CSS
│   ├── src/
│   │   ├── components/   # Luxury Public Portal & ERP Dashboard
│   │   ├── context/      # AppContext (Dual API + LocalStorage state)
│   │   ├── services/     # Typed Laravel REST API client (api.ts)
│   │   └── domain/       # RBAC, Ledger, Audit, Validation logic
│   └── package.json
│
├── backend/              # Laravel 12 + MySQL REST API
│   ├── app/Models/       # Dealer, Property, AgencyVideo, Inquiry, etc.
│   ├── app/Http/         # API Controllers with JSON responses & CORS
│   ├── routes/api.php    # REST endpoints (/api/dealers, /api/videos, etc.)
│   └── database/         # Migrations and Seeders for 12 agencies & plots
│
└── package.json          # Root workspace scripts to run both apps seamlessly
```

---

## Quick Start Guide

### 1. Database (MySQL via XAMPP)
Ensure MySQL is running on port `3306`:
- Database Name: `taisartownhub`
- User: `root` (no password by default)

### 2. Run Backend (Laravel API on port 8080)
```bash
# From repository root:
npm run dev:backend

# Or directly in backend folder:
cd backend
php artisan serve --port=8080
```
Backend API will be live at `http://127.0.0.1:8080/api/health`.

### 3. Run Frontend (React Vite on port 3000)
```bash
# From repository root:
npm run dev

# Or directly in frontend folder:
cd frontend
npm run dev
```
Open `http://localhost:3000` in your browser.

---

## Testing & Verification

All 59 unit tests and strict TypeScript type-checking pass with zero errors:

```bash
# Run strict TypeScript linting:
npm run lint

# Run all 59 unit tests:
npm run test

# Production build:
npm run build
```

---

## Key Features
- **12 Verified Scheme 45 Agencies**: Al-Ghafoor, Falaknaz, Saima, Paragon, Rufi, Country Builders, etc.
- **Agency Video Tours**: Public video showcase with real-time Likes, Comments, and Views connected directly to MySQL.
- **Scheme 45 Phase Filter**: Phase 1, Phase 2, and Phase 3 filters for residential, commercial, and industrial plots.
- **Real Estate ERP Dashboard**: Transaction ledger, seller/buyer records, file verification, dealer commission calculator, and audit trails.
- **Dual Offline/Online Mode**: Seamlessly queries MySQL via Laravel API when online, with instant offline fallback.
