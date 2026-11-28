# Phase 4 Lighthouse Performance Audit

Branch: `audit/phase-4-production-readiness` @ `3b800ee`

## Measured (production build via `serve dist`)
| Metric | Score/Value |
|---|---|
| Performance | 44 |
| Accessibility | 100 |
| Best Practices | 96 |
| SEO | 100 |
| FCP | 1.7s |
| LCP | 13.7s |
| CLS | 0.002 |
| TBT | 1940ms |

## Top opportunity
- unused-javascript: 460ms savings

## Notes
- Performance 44 is low; LCP 13.7s and TBT 1940ms are the main issues.
- Accessibility and SEO scores are excellent.
- Optimization (code splitting, lazy loading, image optimization) not yet implemented.
- Dev-mode Lighthouse (earlier) showed Performance 37; production build improved to 44.