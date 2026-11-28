# Phase 1 Frontend Audit

Branch: `fix/symfony-security-advisories-20261127` @ `72c501d`

## Scope
React 19 + TypeScript + Vite + Tailwind. 130 src files, 15 e2e specs.

## Key findings & fixes
1. `frontend/src/domain/transactions.ts` `collectedForTx` — double-counted recorded advance when ledger present. FIXED (legacy advance added only when ledger gross < advance).

## Verification
- TypeScript: clean (tsc, no errors)
- Tests: 173 passed, 0 failed
- Production build: success, 29 prerendered pages
- Bundle check: OK

## Browser QA
- Chromium: 36 passed (cross-browser-smoke 7, maps 5, interactive 9, admin-pages 10, agency-marketplace 5)
- Firefox: 7 passed
- WebKit: 7 passed
- Viewport matrix: 42 passed (7 routes × 6 viewports: 360/390/768/1024/1440/1920)

## SEO
- Meta description, canonical, OG tags, Twitter cards, JSON-LD structured data present
- robots.txt present; sitemap.xml created (was missing)

## Remaining
- Lighthouse performance: 44 (LCP 13.7s, TBT 1940ms — needs optimization)
- Accessibility: 100, SEO: 100, Best Practices: 96