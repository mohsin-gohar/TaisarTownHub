# Phase 1 Test Evidence

Branch: `fix/symfony-security-advisories-20261127` @ `72c501d`
Date: 2026-11-28

## Backend (PHPUnit)
- Command: `php artisan test` (MySQL, isolated test DB)
- Result: **541 passed, 0 failed, 4531 assertions, exit 0**
- Duration: ~180s

## Frontend
- Command: `npm run verify`
- Result: **173 tests passed, 0 failed, exit 0** (tsc clean, build + 29 prerendered pages)

## Browser (Playwright)
- Chromium: 36 passed (cross-browser-smoke 7, maps 5, interactive 9, admin-pages 10, agency-marketplace 5)
- Firefox: 7 passed, 0 failed
- WebKit: 7 passed, 0 failed
- Viewport matrix: 42 passed (7 routes × 6 viewports: 360/390/768/1024/1440/1920)

## CI
- Run #639 on 5fbf3e0: success (https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45851241905)

## Lighthouse (production build)
- Performance: 44, Accessibility: 100, Best Practices: 96, SEO: 100
- FCP: 1.7s, LCP: 13.7s, CLS: 0.002, TBT: 1940ms

## Blocked
- Docker builds, live ClamAV, TLS/SMTP/DNS, third-party keys, N+1 audit, deeper SEO.