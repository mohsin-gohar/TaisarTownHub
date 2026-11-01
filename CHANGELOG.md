# Changelog

## Unreleased
### Security
- Removed empty/global password login; per-account demo credentials only.
- Auth flags no longer trusted from localStorage.
- Role switching restricted to demo mode.
- Expanded RBAC permission map.

### Fixed
- Commission accounting (earned vs paid vs pending).
- Financial reporting (deal volume vs collected revenue).
- Delete guards for referenced sellers/buyers/dealers.
- Build cssMinify switched to esbuild.
- Navbar navigation missing between 640-1024px, link overflow at 1024-1200px, and drawer a11y (Escape, scroll-lock, backdrop, aria) — rewritten: inline nav only at xl, drawer below xl, constant header height per breakpoint.

### Added
- Public property serializer (toPublicProperty).
- userHasPermission helper.
- Documentation: AUDIT_REPORT, BUG_FIX_REPORT, SECURITY_AUDIT, DATABASE_SCHEMA, FIREBASE_SETUP, DEPLOYMENT, TEST_REPORT.

## Principal Audit Pass
- Implemented sidebar permission filtering, transaction lifecycle guards, plot locator badge, expanded code splitting, demo banner, media validation, field visibility, and public data separation.

## Premium UI/UX Pass (Phase 1-6)

### Visual & Motion
- Fixed silently missing CSS keyframes (animate-fadeIn / slideIn / subtleZoom) and added full premium motion system with prefers-reduced-motion support.
- Hero: word-by-word staggered headline, rAF parallax background, scroll-fade content, trust badge row (avatars + 4.9 rating), scroll-down indicator, LCP image fetchPriority=high.
- Animated count-up statistics (hero stats strip) via IntersectionObserver, no runtime deps.
- Page transitions (CSS page-enter keyed by view), scroll-aware navbar (densify on scroll + animated active underline), mobile drawer with backdrop blur.
- Toast: slide-in + auto-dismiss progress bar (synced to 4.5s timer) + type icons + aria-live.

### Components Added
- Lightbox (keyboard nav, video slot, thumbnail strip) wired into PropertyDetailPage + ProjectDetailPage.
- EmptyState standard empty states: property search, projects listing (public + back to search), admin inquiries, admin transactions.
- FavoriteHeart wishlist toggle persisted in localStorage (taisartownhub_v2_favorites) on property cards.
- Reveal scroll-triggered reveal wrappers across Featured properties/projects grids, Services cards, About core-values/team.

### Cards
- Property cards: per-sq-yd price reference, Hot Deal / Sold status badges, lazy+async images, wishlist heart.
- Project cards: h-full reveal wrappers, "View Project" hover CTA.

### Admin
- Overview KPI: quarter trend chip. Transactions & Inquiries get premium empty states.
- Admin overview verified rendered with DEMO MODE banner.

### Performance & A11y & SEO
- Removed need for 'motion' runtime; all animations CSS/IO-based. Main bundle 89.98KB gzip (was 106KB), total ~103KB under 800KB budget.
- Skip-to-content link, focus-visible rings, aria labels on icon buttons, aria-live toasts.
- Per-view document title + meta description (App.tsx), canonical/OG/Twitter tags + JSON-LD RealEstateAgent schema (index.html).
- All listing/detail images lazily loaded + decoding=async.

### Verified
- lint: 0 errors; tests: 10/10; build success; browser smoke test (home, properties, property detail lightbox keyboard nav, projects, admin login+overview) with 0 console errors.
- Navbar verified at 375 / 768 / 1180 / 1440 px (multi-iframe responsive probe): inline nav only >=1280px, drawer + backdrop + Escape close + body scroll-lock below xl, active-link aria-current, constant header height 64/80px, admin icon-only vs labeled states, zero console errors.
