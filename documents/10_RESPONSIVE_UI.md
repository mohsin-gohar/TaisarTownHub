# TaisarTownHub — Responsive UI & State Components (Pillar 10)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

> **Historical UI design snapshot:** responsive behavior must be verified against current components and Playwright results; this file is not proof of current browser behavior.


## 1. Design System

- **Palette:** Navy `#0B1220` / `#101C2C`, Gold `#C5A46D` / `#D4AF37`, Ivory `#F9E7BA`. No off-brand colors.
- **Typography:** `font-serif-luxury` (headings), system sans (body).
- **Spacing:** Tailwind scale; mobile-first breakpoints (`sm`, `md`, `lg`, `xl`).
- **Motion:** CSS-driven animations (`animate-fadeIn`, `animate-pageEnter`, `animate-subtleZoom`); `prefers-reduced-motion` respected.

## 2. Component Inventory

### Public
- `HeroSection` — slideshow, search bar, stats, CTAs, scroll indicator, slide dots.
- `Navbar` — responsive nav, mobile drawer, active link.
- `Footer` — links, contact, social.
- `FeaturedProjects`, `FeaturedProperties` — cards with hover.
- `AgenciesSection` — agency cards.
- `Scheme45Guide` — interactive sector map, price trends.
- `ServicesSection` — service cards.
- `MediaGallerySection` — video grid, filters, add modal, comments, likes.
- `AboutSection`, `TestimonialsSection`, `ContactSection`.
- `PropertiesListingPage`, `ProjectsListingPage`, `PropertyDetailPage`, `ProjectDetailPage`.

### Admin
- `AdminLayout` — sidebar, mobile top bar, role indicator, tab routing.
- `AdminOverview` — KPIs, charts, recent leads.
- `AdminProperties`, `AdminProjects`, `AdminPlotLocator`, `AdminTransactions`, `AdminSellers`, `AdminBuyers`, `AdminDealers`, `AdminFileManagement`, `AdminMediaLibrary`, `AdminInquiries`, `AdminCMS`, `AdminSecurityRoles`, `AdminAudit`.

### Common
- `VideoModal` — Escape-close, backdrop click.
- `ToastContainer` / `showToast` — success/error/info/warning.
- `ConfirmDialogHost` — replaces `window.confirm`.
- `StatsCounter` — animated count-up.
- `StatusBadges` — honest status (no demo labels).
- `Skeleton` (where used) — loading placeholders.

## 3. State Components

- **Loading:** `Suspense` fallbacks ("Loading content...", "Loading dashboard..."), skeleton rows in lists.
- **Empty states:** "No Open Inquiries", "No properties match", honest messaging.
- **Error boundary:** `ErrorBoundary` wraps app; shows fallback + retry.
- **Toast feedback:** all mutations surface success/error toasts.

## 4. Accessibility

- Skip-to-content link.
- Keyboard navigation (Tab, Enter, Escape).
- ARIA labels on icon buttons.
- `prefers-reduced-motion` disables autoplay + animations.
- Focus management in modals.

## 5. Responsive Breakpoints

- Mobile-first; sidebar collapses to drawer on `< md`.
- Grids: 1 col mobile → 2/3/4 cols on `md`/`lg`.
- Forms: stacked on mobile, multi-column on `md`+.
- Touch targets ≥ 44px.

## 6. Performance

- Lazy-loaded admin chunks (`React.lazy` + `Suspense`).
- Code-split public sub-pages.
- Image `loading="lazy"`, `decoding="async"`, `fetchPriority="high"` for hero.
- Vite build with gzip; bundle check ensures no ERP data in public chunks.
