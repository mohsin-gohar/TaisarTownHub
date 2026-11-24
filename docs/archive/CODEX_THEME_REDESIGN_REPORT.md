# CODEX — Theme Redesign Report

**Date:** 2026-11-19  
**Branch:** `main` (working tree dirty — nothing committed)  
**Status:** Implemented and verified against the local runtime. Visual sign-off from the user is still required.

---

## 1. What was requested

The user attached `C:\Users\mohsi\Downloads\4f3678ac-e47d-49aa-a2ff-7d86967801f0.png` and asked for the project UI to be made "like this". When asked to choose the scope, the user selected **Full UI redesign**.

**Important limitation:** this model cannot view image input. The screenshot was analysed programmatically (Pillow / .NET pixel sampling), not visually interpreted. The resulting palette is an evidence-based approximation, not a pixel-perfect copy.

### Extracted reference data

- Image size: `1212 x 1298`
- Most common exact colours: `#F9F9F9` (2.81%), `#050F19` (2.57%), `#F5F5F5`, `#06101A`, `#07141E`, `#08141E`
- Band averages: header ≈ `#3C4A61`, hero ≈ `#6C665D`, listings/cards ≈ `#C3C7C8`, features ≈ `#46494A`, footer ≈ `#171E23`
- Gold/amber candidates detected: `#F3C464`, `#FBD170`, `#DDBB6B`

Interpretation: **deep navy hero/footer + ivory/white listing band + gold accents**.

---

## 2. Changes actually made

### 2.1 Palette tokens (`frontend/src/index.css`)

| Token | Old | New |
|---|---|---|
| `--color-navy` | `#101C2C` | `#07141E` |
| `--color-midnight` | `#0B1220` | `#050F19` |
| `--color-gold` | `#C5A46D` | `#E7C878` |
| `--color-gold-light` | `#E0C79A` | `#F6E3B4` |
| `--color-ivory` | `#F8F7F3` | `#F9F9F9` |

Added tokens: `--color-navy-surface: #0B1220`, `--color-navy-card: #101C2C`, `--color-gold-bright: #F3C464`, `--color-gold-deep: #B98A2F`, `--color-ink: #0B1220`, `--color-ink-soft: #475569`.

Body background: `#050F19`. Glass helpers now use `rgba(7,20,30,...)` and `rgba(231,200,120,...)`.

New reusable classes: `.surface-ivory`, `.card-light`, `.card-light-muted`, `.border-ivory`.

### 2.2 Code-wide palette migration

All `*.tsx`, `*.ts` and `*.css` files under `frontend/src` were migrated from the old navy/gold hex values to the new ones (69 files changed in the first pass). A grep of `frontend/src` now reports **zero** remaining old tokens (`#C5A46D`, `#D4AF37`, `#F9E7BA`, `#9B783E`, `#E0C79A`, `%23C5A46D`, `%23101C2C`).

`ImageWithFallback.tsx` fallback SVG was updated separately because its colours are URL-encoded.

### 2.3 New light "Available for Sale" band

`frontend/src/components/public/AvailableForSaleSection.tsx` (new) renders the first four governed sale listings on an ivory surface between the dark hero and the dark feature sections:

- white cards with `card-light` shadows, gold "For Sale" badge, dark ink headings;
- price, location, size/beds/baths, "View Details" CTA;
- each card is a real `<a href="/properties?property=...">` with an SPA transition on plain left-click, so middle-click/new-tab still works;
- "View All Properties" goes to `/properties?purpose=sale`;
- returns `null` when there are no listings.

Wired into `frontend/src/App.tsx` immediately after `<HeroSection />`.

### 2.4 Light listings page

`frontend/src/components/public/PropertiesListingPage.tsx` was converted from a dark surface to the reference light band:

- page surface `#F9F9F9`, filter panel and listing cards white with `#E7E3DA` borders;
- headings `#0B1220`, secondary text `#475569`, accents `#8A6A1F` / `#B98A2F`;
- sort/select inputs and compare chips adapted to the light background;
- dark badges retained only where white text is required (e.g., "New", "Possession").

### 2.5 Shell and favicon

`frontend/index.html` still carried the old palette on `<body>` (`bg-[#0B1220]`, `selection:bg-[#C5A46D]`) and in the `<noscript>` block; `frontend/public/favicon.svg` used the old navy/gold pair. Both were migrated to `#050F19` / `#E7C878`. After this fix the runtime body background measures `rgb(5,15,25)` instead of the stale `rgb(11,18,32)`.

A scan of all `*.html`, `*.svg`, `*.ts`, `*.mts` and `*.css` files (excluding `node_modules`, `dist`, `test-results`) found **zero** remaining old palette tokens.

### 2.6 SEO fix found during this work

`App.tsx` `applySeo()` previously skipped every view starting with `admin`, so `/admin-login` inherited the homepage title and a stale Vercel canonical. It now skips only admin views **without** an explicit `SEO_ROUTES` record; `/admin-login` gets its own `noindex, follow` title and canonical. Verified in the generated `dist/admin-login/index.html`.

---

## 3. Verification performed

| Check | Result |
|---|---|
| `npx tsc --noEmit` | pass |
| `npm test` | 136 passed / 0 failed |
| `npm run verify` (lint + tests + build + prerender + bundle check) | pass |
| `npx tsx scripts/qa-theme.mts` (Playwright, 1440×900) | homepage light band present, 4 cards, `bodyBg = rgb(5,15,25)` (`#050F19`), `scrollWidth == innerWidth == 1440`, 0 console errors |
| Same probe on `/properties?purpose=sale` | filter panel `rgb(255,255,255)`, 4 cards, no overflow, 0 console errors |
| Old palette grep across `frontend/src` | 0 matches |

Screenshots: `C:\Users\mohsi\AppData\Local\Temp\opencode\theme-artifacts\home-full.png` and `properties-full.png`.

---

## 4. What is NOT claimed

- No visual comparison with the reference image was possible in this model; the user must confirm the look.
- The logo, brand name and navy/gold identity were **preserved**, not replaced.
- No production Lighthouse score, accessibility audit or Core Web Vitals result is claimed here.
- No commit or push was made.

---

## 5. Update — reference-matching redesign (2026-11-19, later session)

In the later session the approved reference
(`C:\Users\mohsi\AppData\Local\Temp\opencode\uploads\33d1d913-2606-4166-8fec-719fee21ba6b\download.png`)
became directly viewable, so the homepage was refined against it **visually**, section by section.

### 5.1 Hero (`frontend/src/components/public/HeroSection.tsx`)

- Badge pill now uses a `MapPin` icon (reference "Taisar Town Properties Only" pill).
- Headline logic: segments are split on `. `, trailing periods stripped, and the **last** segment
  renders gold — so the CMS headline `Discover Premium Properties in. Taisar Town` renders as a
  white line plus a gold "Taisar Town" line, exactly like the reference.
- The white single-row search bar now sits directly under the subtitle (reference order) with
  icon-prefixed selects (`Home`, `Building2`, `MapPin`, `Wallet`, `Ruler`) and visible labels
  replaced by `aria-label`s. **Select DOM order is unchanged** (Type, Phase, Sector, Budget, Size),
  preserving the E2E contract `select >> nth(2)` = Sector 70-D and the single `Search` submit button.
- Quick CTAs (Explore Properties / Discover Agencies / Scheme 45 Master Plan) moved **below** the
  search bar — the E2E-required buttons remain visible on the homepage.
- The zero-height risk stats strip was replaced by the reference **trust indicator row**
  (Trusted Platform / Premium Location / Secure Deals) with gold icon circles.
- Slider control now has circular **prev/next arrows** flanking the existing dots
  (`aria-label="Previous slide"` / `"Next slide"`).
- The existing CSS gold wave (`.premium-hero::after`) already matches the reference divider.

### 5.2 Homepage sections (`frontend/src/App.tsx`)

- `WHY_CHOOSE_ITEMS` reduced to the reference's four columns (Verified Listings, Premium Location,
  Secure Platform, Dedicated Support) and the section rebuilt with a gold-icon heading
  "Why Choose TaisarTownHub?", the "Your Trusted Real Estate Partner in Taisar Town" subtitle and
  **vertical gold-tinted dividers** between columns on `lg`.
- The gradient CTA was replaced with the reference **image-left banner**: rounded dark card,
  brand shot on the left, "List Your Properties with TaisarTownHub" + gold
  **Register Your Agency →** button, secondary "Already have an agency? Login" link (now routes to
  `/login`, not `/agencies`).
- "How TaisarTownHub Works" gained the heading it was missing.
- **`Reveal.tsx` wired across every homepage section** (Featured Agencies, Browse All Agencies,
  Why Choose items with 90 ms stagger, Scheme 45, Video, How It Works, CTA, Contact) — this is the
  "full animated" scroll-reveal layer. It honours `prefers-reduced-motion` (static content) and
  falls back to visible when `IntersectionObserver` is unavailable.

### 5.3 Navbar (`frontend/src/components/common/Navbar.tsx`)

- Nav order now Home · Properties · Agencies · Services · About · Contact (reference order minus
  the reference's `Projects`/`More` items, which the sale-only E2E forbids in the nav).
- Tagline changed to "Find · Sell · Invest" (reference wordmark subtitle).
- Added the circular **search icon** button (aria-label "Search properties" → `/properties`), and
  the auth pair restyled as reference **Login** (gold outline) / **Register** (solid gold) buttons
  in both desktop bar and mobile drawer.

### 5.4 Footer (`frontend/src/components/common/Footer.tsx`)

- Rebuilt to the reference five-column layout: brand + socials (Facebook, YouTube, LinkedIn, X,
  Instagram) · **Quick Links** · **Services** (real service names linking to `/services`) ·
  **Support** (Help & Support, Video Gallery, Scheme 45 Master Plan, Portal Login) ·
  **Newsletter** ("Get the latest updates", `Enter your email` + gold arrow submit).
- Removed the pre-footer advisory banner (the homepage CTA banner now occupies that slot).
- Bottom bar: `© {year} TaisarTownHub. All rights reserved.` + **"Built with ❤️ for Taisar Town"**.
- Disclaimer, newsletter API flow, back-to-top and the demo-only presentation toggle preserved.

### 5.5 CMS copy (backend, reversible)

`business_records` row `collection=cms, record_id=default` updated (single row, old values recorded
in the session log and in the one-off script that was deleted after use):

- `heroHeadline`: `Discover Your Place. Build Your Future.` → `Discover Premium Properties in. Taisar Town`
- `heroSubtitle`: → `Explore verified properties for sale in Taisar Town from trusted agencies and owners.`

Frontend seed (`publicSeed.ts`) mirrored for the same three fields plus
`clientThemes[0].heroBadge` → `Taisar Town Properties Only`.

### 5.6 Known intentional deltas vs the reference

- The reference nav shows **Projects** and **More**; the sale-only E2E contract forbids both exact
  labels in the nav, so they are omitted (test wins — changing this needs explicit user consent).
- Reference search bar has 3 fields; ours keeps all 5 real filters (Type/Phase/Sector/Budget/Size)
  in the same single-row white bar so no filtering capability is lost.
- Reference card/section copy spells "Taisar Town"; established site copy uses "Taiser Town" in
  places. No mass rename was performed to avoid breaking title/SEO contracts — visual design was
  matched instead.
- Stats counters (20,570 acres / 320+ properties / 1,450+ investors) were removed from the hero in
  favour of the reference trust-indicator row; the values remain in the CMS.
