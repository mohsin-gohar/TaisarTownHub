# FINAL BROWSER E2E REPORT — TaisarTownHub

**Date:** 2026-11-16
**Tooling:** Playwright 1.65.0 + Chromium 1254, headless; viewport 1366×900 for production checks.
**Targets:** `https://taisartownhub.vercel.app`, `http://127.0.0.1:3000` (Vite dev + Laravel API), `http://127.0.0.1:4173` (production preview).

---

## 1. Headline Result

The deployed Vercel site went from **blank body / 0 images / 0 videos** to fully rendered pages with working gallery video playback. The failure was a pre-mount JavaScript throw, not missing CSS or data.

### Root cause

```
Error: VITE_API_URL is required in production. Set it in your build environment.
    at https://taisartownhub.vercel.app/assets/jsx-runtime-*.js
```

`frontend/src/services/api.ts` threw when Vercel built without `VITE_API_URL`. React never mounted; `#root` stayed empty. Fixed in `a8d217e`.

---

## 2. Production Verification (after redeploy)

Deployed bundle: `/assets/index-CJYz7gLM.js`; CSS `/assets/index-ABOJN43V.css`.

| Route | Body height | Text length | Images | Broken | Video cards | Agency cards | Property links | Page errors |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| `/` | 12,876 | 20,301 | 41 | 0 | 6 | 33 | 12 | 0 |
| `/gallery` | 2,292 | 4,606 | 14 | 0 | 6 | 6 | 0 | 0 |
| `/agencies` | 2,075 | 3,491 | 8 | 0 | 0 | 6 | 0 | 0 |
| `/properties` | 5,836 | 13,267 | 24 | 0 | 0 | 22 | 44 | 0 |

Console output: only the expected `VITE_API_URL is not set ... using the bundled public catalog.` diagnostic. No HTTP 4xx/5xx asset failures, no request failures.

### Gallery video playback (deployed)

- Clicked the first thumbnail → `[role="dialog"]` opened.
- Source: `https://taisartownhub.vercel.app/sample-video.mp4`.
- `readyState: 4`, `duration: 10.03`, `currentTime: 1.46`, `paused: false`, `error: null`.
- Screenshot shows the player controls and a playing frame.

Screenshots (absolute paths):

- `C:\Users\mohsi\AppData\Local\Temp\opencode\qa-shots\deployed-fixed-home.png`
- `C:\Users\mohsi\AppData\Local\Temp\opencode\qa-shots\deployed-fixed-gallery.png`
- `C:\Users\mohsi\AppData\Local\Temp\opencode\qa-shots\deployed-fixed-agencies.png`
- `C:\Users\mohsi\AppData\Local\Temp\opencode\qa-shots\deployed-fixed-properties.png`
- Before: `deployed-gallery.png` (blank).
- Local: `local-gallery.png` (fully rendered).

---

## 3. Local Browser Verification

| Check | Evidence |
|---|---|
| Full route sweep | 16 routes including `/gallery`, `/agencies`, `/properties`, `/admin`, `/admin-login`, 404; correct titles, no overflow, no broken images. |
| Gallery (API-backed dev) | 6 video cards, 14 images, 0 broken; modal video `readyState 4`, `currentTime 1.68`, duration 10.03, no error. |
| Agencies (API-backed dev) | 23 agency cards, 15 images, 0 broken. |
| Properties (API-backed dev) | 38 property links, 21 images, 0 broken. |
| Production preview (no API fallback) | 6 videos, 14 images, playback `readyState 4` / `currentTime 2.08`, 6 agency cards, 44 property links, 0 broken. |
| Responsive | 320, 375, 390, 430, 768, 1024, 1366, 1440, 1920 px: no horizontal overflow; logo 40/44 px; nav hidden below 1280 px; burger works; hero 36 px mobile / 72 px desktop. |
| Mobile drawer | Opens, locks body scroll, navigates to `/projects`, closes, releases scroll lock. |
| Property search | `Sector 70` reduced property links 38 → 10. |
| Property detail | `/properties?property=prop-11`: correct heading/title/3 images; installment buttons switch; empty inquiry blocks and focuses name. |
| Inquiry submit | Success toast; persisted row verified and deleted. |
| Contact form | Empty submit blocked/focused name; valid submit success toast. No QA-marked row remained; user-entered row untouched. |

---

## 4. Caveats

- All six video tours use the same local `/sample-video.mp4` placeholder (the screenshot shows generic footage, not the labelled Scheme 45 walkthrough). Functionality is verified; content is placeholder.
- Images/fonts are external (Unsplash/Google Fonts); no offline check.
- Only Chromium was tested; Safari/Firefox and real mobile hardware were not.
- The Vercel deployment is frontend-only: auth/admin/write APIs and form persistence cannot work there without a backend.
- No accessibility audit (axe) or Lighthouse run was executed.

---

## 5. Verdict

**Public marketing pages and gallery media: PASS on Chromium, local and deployed.**

**Full browser E2E sign-off: BLOCKED** on the live backend, mobile/Safari coverage, and the placeholder video content.
