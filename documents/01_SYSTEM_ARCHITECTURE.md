# TaisarTownHub — System Architecture & Technical Specifications

**Version:** 2.0.0 · **Last updated:** 2026-11-08

> **Historical architecture snapshot:** this describes an earlier frontend/state architecture. Current runtime behavior is defined by the Laravel source, current migrations, `../docs/API.md` and `../docs/STATUS.md`.

**Owner:** Gohar AI / TaisarTownHub Real Estate ERP SaaS

---

## 1. Module Breakdown

TaisarTownHub is a multi-tenant, role-aware real-estate ERP SaaS for **MDA Taiser Town Scheme 45, Karachi**. It ships as a split-stack application: a Laravel 12 REST API backend + a React 19 SPA frontend, served separately and connected over HTTP/JSON.

### Backend (Laravel 12, PHP 8.2+, MySQL 8.4)

| Module | Location | Responsibility |
|---|---|---|
| `Auth` | `app/Http/Controllers/Api/AuthController.php` | Sanctum login/register/logout/me, presenter PIN gate |
| `RBAC` | `app/Support/RolePermissions.php`, `AuthorizesApi` trait | Role → permission matrix, `requireAny()` gate, audit writer |
| `Workspace` | `WorkspaceController` | Multi-collection sync (properties, projects, dealers, …), CMS payload, soft-delete, audit of media sales |
| `Properties` | `PropertyController` | CRUD + soft delete + restore, validation, scoping |
| `Dealers` | `DealerController` | Dealer CRUD, commission type/value, scoping |
| `DealerFiles` | `DealerFilesController` | File upload, sale attribution (buyer name/phone/CNIC), profit/commission, reports |
| `Projects` | `ProjectController` | Project CRUD |
| `Transactions` | `TransactionController` | Deal ledger, payments, commissions, status transitions |
| `Inquiries` | `InquiryController` | Public submit, admin CRM, status updates |
| `AgencyVideos` | `AgencyVideoController` | Video tours, likes (append-only), comments, views |
| `Audit` | `AuditLog` model + `WorkspaceController` | Append-only audit trail, recycle bin restore |
| `Catalog` | `WorkspaceController::catalog()` | Public, unauthenticated read of published collections |

### Frontend (React 19, TypeScript, Vite 6, Tailwind)

| Area | Location | Responsibility |
|---|---|---|
| App shell | `src/App.tsx` | View routing (home/projects/properties/…/admin), SEO per view, error boundary |
| State | `src/context/AppContext.tsx` | Single source of truth: collections, auth, CRUD actions, workspace sync, toasts |
| Domain | `src/domain/permissions.ts` | Canonical role ids + permission matrix (P0-4) |
| Services | `src/services/api.ts` | Typed fetch wrapper, token storage (sessionStorage), health check |
| Public views | `src/components/public/*` | Hero slideshow, listings, agencies, services, media gallery, contact |
| Admin views | `src/components/admin/*` | ERP dashboard modules (lazy-loaded, code-split) |
| Common | `src/components/common/*` | VideoModal, StatusBadges, Toasts, ConfirmDialog, StatsCounter |

---

## 2. Request Lifecycle

```
Browser ──HTTP──► Laravel router (routes/api.php)
   │
   ├── Public: /api/catalog, /api/properties, /api/videos, /api/inquiries (POST), /api/subscribers
   │     └─► Controller::index/show/store ──► Eloquent/DB ──► JSON
   │
   └── Authenticated: auth:sanctum middleware
         ├── token from Authorization: Bearer <token> (sessionStorage on FE)
         ├── WorkspaceController::show/save  (collection sync)
         ├── Property/Dealer/Transaction/... CRUD
         └── AuditLog::create on sensitive actions

Frontend flow:
  AppProvider mounts → getAuthToken()? → if present, fetch /api/workspace + /api/catalog
  → hydrate state → render view
  Mutations → optimistic local update → api.*() → on success persist, on failure revert + toast error
```

### Workspace sync contract
- `GET /api/workspace` returns all collections (auth required).
- `PUT /api/workspace` accepts `{ collections: { [name]: rows[] } }`, validates `collections required array`, syncs each list (soft-deletes removed rows), audits media sales.
- `GET /api/catalog` returns public-safe subset (no CNICs, no purchase prices, no commissions) for unauthenticated visitors.

---

## 3. Data Flow

1. **Seed:** `backend/database/seed-data/workspace.json` → `BusinessRecord` rows (collections: properties, projects, dealers, sellers, buyers, transactions, media, videos, inquiries, subscribers, testimonials, plots, cms).
2. **Runtime:** Admin edits → `saveWorkspace()` → `PUT /api/workspace` → `syncList()` upserts `BusinessRecord` rows + soft-deletes.
3. **Public:** `/api/catalog` reads `BusinessRecord` rows where `deleted_at IS NULL` and maps to public-safe payloads (PII stripped).
4. **Media sales:** `auditNewMediaSales()` runs before sync on `media` collection, writing append-only `audit_logs` rows with seller/buyer/profit attribution.

---

## 4. Directory Structure

```
TaisarTownHub/
├── backend/
│   ├── app/
│   │   ├── Http/Controllers/Api/   # REST controllers + AuthorizesApi trait
│   │   ├── Models/                 # Eloquent models (soft deletes, casts)
│   │   ├── Support/RolePermissions.php
│   │   └── Providers/
│   ├── database/
│   │   ├── migrations/             # Schema (users, properties, dealers, …, business_records, audit_logs)
│   │   └── seed-data/workspace.json
│   ├── routes/api.php
│   └── tests/                      # PHPUnit (5 tests)
├── frontend/
│   ├── src/
│   │   ├── App.tsx                 # Router + SEO + error boundary
│   │   ├── components/{public,admin,common}/
│   │   ├── context/AppContext.tsx   # Global state + CRUD actions
│   │   ├── domain/permissions.ts   # Canonical RBAC matrix
│   │   ├── services/api.ts         # HTTP client
│   │   ├── types/index.ts          # Shared TS types
│   │   └── utils/                  # Pure helpers + unit tests
│   └── public/                     # Static assets (image.png hero slide 1, logo)
├── docs/                           # ← this folder (architecture, PRD, …)
└── *.md                            # Audit/bug/deployment reports
```

---

## 5. Key Design Decisions

- **Single workspace table (`business_records`)** for multi-collection flexibility + soft deletes + audit, alongside dedicated typed tables (`properties`, `dealers`, `transactions`, …) for relational integrity and indexing.
- **Permission union:** explicit `permissions` array on `AuthUser` is unioned with the role matrix; `'all'` grants everything (super_admin).
- **Honest UX:** mutating actions return `{ success, error }`; no fake success toasts; toasts only fire after persistence resolves.
- **Bundle isolation:** public chunks must not contain ERP data or credentials — enforced by `scripts/check-bundle.mjs` in `npm run verify`.
- **Slideshow:** module-level `HERO_SLIDES` + `HERO_SLIDE_INTERVAL_MS = 4500`, opacity cross-fade, `reduceMotion` disables autoplay; first slide = user's uploaded image (`/image.png`).
- **Reload protection:** >5 reloads/60s shows a friendly error screen (client-side guard).

---

## 6. Non-Functional Requirements

- **Performance:** lazy-loaded admin chunks, code-split public sub-pages, Vite build with gzip.
- **Security:** Sanctum tokens in sessionStorage (not localStorage), CSRF for cookie auth, rate limiting on auth/video/inquiry endpoints, permission gates on every mutating action, soft deletes (no hard deletes of audit-relevant rows).
- **Accessibility:** skip-to-content link, keyboard navigation, ARIA labels, reduced-motion support.
- **Observability:** structured `AUDIT` log lines + `audit_logs` table; Laravel `Log::info/warning`.
- **Testing:** 101 frontend unit tests (`tsx --test`), 5 backend PHPUnit tests; `npm run verify` is the release gate.
- **Deployment:** cPanel/server deployment documented in `DEPLOYMENT.md`; health endpoint `/api/health`.
