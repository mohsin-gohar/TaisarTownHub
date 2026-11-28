# Phase 6 Final Regression Evidence

Branch: `release/phase-6-production-deployment` @ `f0209e8`
Date: 2026-11-28

## Backend (PHPUnit)
- Command: `php artisan test`
- Result: **466 passed, 0 failed, 3960 assertions, exit 0**

## Frontend
- Command: `npm run verify`
- Result: **160 tests passed, 0 failed, exit 0** (tsc clean, build + 29 prerendered pages)

## Browser (Playwright)
- Chromium: 36 passed (cross-browser-smoke 7, maps 5, interactive 9, admin-pages 10, agency-marketplace 5)
- Firefox: 7 passed
- WebKit: 7 passed
- Viewport matrix: 42 passed (7 routes × 6 viewports)

## Lighthouse (production build)
- Performance: 44, Accessibility: 100, Best Practices: 96, SEO: 100

## CI
- Run #639 on 5fbf3e0: success