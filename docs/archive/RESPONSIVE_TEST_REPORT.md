# Responsive Test Report

Tested the live homepage in Chromium after the footer email wrap fix:

| CSS viewport | Document client/scroll width | Horizontal overflow |
|---:|---:|---|
| 360 | 352 / 352 | No |
| 390 | 382 / 382 | No |
| 768 | 760 / 760 | No |
| 1024 | 1016 / 1016 | No |
| 1366 | 1358 / 1358 | No |
| 1440 | 1432 / 1432 | No |

The 8px difference between viewport and document client width is the browser scrollbar; scroll width equals client width at all sizes. The earlier 1024px one-pixel overflow came from an unbroken footer email address and is fixed with wrapping. Desktop/mobile screenshots are saved under `frontend/.screenshots/audit/`.

This matrix was run on the homepage only; modal/table responsiveness across every authenticated dashboard module was not exhaustively measured.
