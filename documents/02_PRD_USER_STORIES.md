# TaisarTownHub — Product Requirements Document (PRD) & User Stories

**Version:** 2.0.0 · **Last updated:** 2026-11-08

> **Historical PRD snapshot (2026-11-08):** reconcile these original user stories with the current sale-only marketplace scope and the live implementation. Current acceptance evidence and remaining decisions are in `../docs/STATUS.md`.

**Product:** TaisarTownHub Real Estate ERP SaaS — MDA Taiser Town Scheme 45, Karachi

---

## 1. Product Vision

A single, honest, role-aware real-estate ERP that lets **agencies, dealers, and investors** manage verified plots, projects, deals, media assets, and client inquiries for MDA Taiser Town Scheme 45 — with biometric file verification, on-ground demarcation tracking, and full audit trails — while presenting a premium public marketing site to overseas and local investors.

---

## 2. Personas

| Persona | Role id | Capabilities | Pain points addressed |
|---|---|---|---|
| **Super Admin** | `super_admin` | Full control: unmasked CNICs, private pricing, staff management, DB migration, audit & recycle bin | Needs oversight + fraud detection |
| **Agency Admin** | `admin` | Property/Project CRUD, sellers/buyers, staff registration, payouts, CMS builder | Runs the back office |
| **Branch Manager** | `manager` | Projects, media, videos, view reports | Manages a branch |
| **Property Dealer** | `dealer` | Own deals & commissions, upload documents, view own scope | Commission transparency |
| **Data Entry Operator** | `operator` | Upload documents, never verifies | Data entry only |
| **Public Investor** | (anonymous) | Browse listings, search, inquire, watch video tours, subscribe | Trust & verification |

---

## 3. Core User Flows & Acceptance Criteria

### F1 — Public property search & discovery
- **As a** public investor, **I want to** search/filter properties by type, phase, sector, budget, size, **so that** I find verified plots quickly.
- AC: Hero search applies filters and navigates to `/properties`; results reflect active (non-deleted) inventory; empty state shown honestly (no fake counts).

### F2 — Property detail & inquiry
- **As an** investor, **I want to** view plot details (sector, size, price, file status, map) and submit an inquiry.
- AC: Inquiry form validates required fields (name, phone); submission returns honest success/error; admin sees it in Inquiries CRM with Pending status.

### F3 — Project browsing
- **As an** investor, **I want to** browse housing/commercial projects with progress, location, and gallery.
- AC: `/projects` lists active projects; detail page shows gallery + specs; no demo/placeholder data in production.

### F4 — Media & video tours
- **As an** investor, **I want to** watch on-ground video tours, like and comment.
- AC: Videos load from `/api/videos`; like increments only once per visitor (localStorage + backend); comment requires name + text; VideoModal closes on Escape.
- **As an** agency, **I want to** publish a video tour.
- AC: Requires `manage_media`/`upload_media` permission; persists via `POST /api/videos`; success toast only after persistence; modal stays open on failure.

### F5 — Admin property CRUD
- **As an** admin, **I want to** add/edit/soft-delete/restore listings.
- AC: Form validates required fields; soft delete moves to Recycle Bin; restore reverses; audit log entry written; permission-gated.

### F6 — Deal & transaction ledger
- **As an** admin/dealer, **I want to** book a deal (seller/buyer/plot/amount/advance/commission) and track payments/status.
- AC: Seller ≠ buyer; advance ≤ amount; commission computed from dealer's type/value; payments append to ledger; status transitions auditable.

### F7 — Dealer file sales & attribution (anti-fraud)
- **As an** admin, **I want to** upload PDFs/images per dealer, mark sold with buyer name/phone/CNIC, track profit & commission.
- AC: Upload validates type (PDF/JPG/PNG/WebP) and size (≤5MB); sale records buyer attribution + profit; report aggregates per seller; soft delete + restore.

### F8 — CMS & homepage builder
- **As an** admin, **I want to** edit hero headline, subtitle, contact info, social links, stats, notice banner.
- AC: Save & Apply persists to `cms` collection; public site reflects changes; no fake success.

### F9 — Security & roles
- **As a** super admin, **I want to** switch roles (presenter PIN) and review the permission matrix.
- AC: PIN gate locks after 3 failures for 30s; role switch changes visible modules; CNICs/prices masked by role.

### F10 — Audit & recycle bin
- **As a** super admin, **I want to** see append-only audit log and restore soft-deleted records.
- AC: Audit log shows actor/role/action/target/detail/time; recycle bin lists soft-deleted rows per collection; restore reverses deletion.

### F11 — Reload protection
- **As a** visitor, **I want** >5 reloads/60s to show a friendly error screen instead of crashing.
- AC: Client-side guard counts reloads in sessionStorage; friendly message shown; no white screen.

### F12 — Hero slideshow
- **As a** visitor, **I want to** see 4–5 rotating hero backgrounds (first = latest uploaded image).
- AC: Slides advance every 4–5s; cross-fade; reduced-motion disables autoplay; manual dots work.

---

## 4. Edge Cases (cross-cutting)

- Empty inventory → honest empty state, no fake "0 available" success.
- Network failure during mutation → revert optimistic update, show error toast, keep modal open with entered values.
- Duplicate submission → idempotent where possible; backend validates uniqueness.
- Permission denied → 403 with clear message; UI hides the action for unauthorized roles (cosmetic) + domain gate (real).
- Soft-deleted records → excluded from public catalog; visible in recycle bin for restore.
- Oversized/invalid files → rejected with clear message before upload.
- CNIC/phone masking → role-based; super_admin sees unmasked.

---

## 5. Non-Functional Requirements

- **Performance:** lazy-loaded admin chunks, code-split public sub-pages, image optimization, Vite gzip.
- **Security:** Sanctum tokens in sessionStorage, rate limiting (auth 5/min, videos 10/min, inquiries 10/min, comments 10/min, likes 30/min), CSRF, SQL injection defenses (Eloquent), XSS sanitization, env secret guards.
- **Accessibility:** skip-to-content, keyboard nav, ARIA, reduced-motion, focus management (Escape closes modals).
- **Observability:** structured `AUDIT` log + `audit_logs` table; Laravel Log.
- **Testing:** 101 frontend unit tests + 5 backend PHPUnit; `npm run verify` is the release gate.
- **Deployment:** cPanel/server commands in `DEPLOYMENT.md`; health endpoint `/api/health`.

---

## 6. Out of Scope (v2.0)

- Multi-tenant billing/subscription tiers
- Native mobile apps
- Real-time WebSocket chat
- Payment gateway integration (currently ledger-only)
- Multi-language i18n

---

## 7. Success Metrics

- `npm run verify` and `php artisan test` green on every commit.
- Zero fake success messages (verified by code review + tests).
- Public bundle contains no ERP data/credentials (`check-bundle.mjs`).
- All admin modules render without console errors.
- Hero slideshow first slide = user's uploaded image.
