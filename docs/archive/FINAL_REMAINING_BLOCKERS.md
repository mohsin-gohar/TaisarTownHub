# FINAL REMAINING BLOCKERS — TaisarTownHub

**Date:** 2026-11-16
**Code commit:** `a8d217e`

These are the exact items preventing a full production-ready declaration. Each is marked with the evidence observed.

---

## BLK-01 — Docker runtime not executable

- **Evidence:** `docker --version` and `docker compose version` → `The term 'docker' is not recognized...`; WSL reports no installed distribution.
- **Impact:** No image build, no `docker compose up`, no container health/DB/Redis/nginx/worker verification.
- **Required:** Install Docker Desktop/Engine with a Linux engine; run build/up; health-check all services.

## BLK-02 — Real ClamAV daemon not available

- **Evidence:** `clamd --version` / `clamscan --version` → commands not recognized.
- **Impact:** INSTREAM/fail-closed/fail-open logic is proven only against `backend/tests/Support/fake_clamd.php`; no real infected-file rejection was executed.
- **Required:** Run the ClamAV container and test clean, EICAR/infected, scanner-down-fail-closed, and fail-open cases end to end.

## BLK-03 — No live backend/API for the deployed frontend

- **Evidence:** `https://api.taisartownhub.com/api/health` → fetch failed; `https://taisartownhub.vercel.app/api/health` → 404.
- **Impact:** Vercel runs a frontend-only static site with the bundled public catalog. Auth, admin, MFA, contact/inquiry persistence, uploads, and all write workflows cannot work there.
- **Required:** Deploy the Laravel API + MySQL/Redis, set `VITE_API_URL` in Vercel, redeploy, and run authenticated E2E tests.

## BLK-04 — Custom domain unreachable

- **Evidence:** `https://taisartownhub.com` → `Transport error` in multiple attempts; only `taisartownhub.vercel.app` was reachable.
- **Impact:** Canonical/OG URLs and the intended production domain are not validated; DNS/TLS unknown.
- **Required:** Fix DNS/TLS and verify the domain, headers, redirects, and canonical URLs.

## BLK-05 — Browser security matrix not executed

- **Not executed:** public PII walk, dealer file-sale authorization, subscription plan limits, staff owner-role escalation, MFA role-switch.
- **Impact:** Security sign-off is blocked; no claim of a clean live security posture.
- **Required:** Run these against the live backend with real roles/accounts, and archive request/response evidence.

## BLK-06 — Legacy ownership unresolved

- **Evidence:** read-only report: **110 unowned** = **26 confirmed**, **0 ambiguous**, **84 unresolved**.
- **Impact:** Unowned rows stay hidden from agency workspaces; the data-governance problem is contained but not resolved.
- **Required:** Business-approved per-record mapping + verified backup; then a controlled backfill. No automatic stamping.

## BLK-07 — Production infrastructure prerequisites unverified

- **Not verified:** production secrets/keys, MySQL/Redis runtime, queue worker, SMTP delivery, backups/restore, TLS/DNS, monitoring.
- **Impact:** Operational readiness is not proven.
- **Required:** Provision and test each; document rollback.

## BLK-08 — Performance/accessibility/cross-browser not measured

- **Not measured:** Lighthouse, Core Web Vitals, throttled network, Safari/Firefox, real mobile hardware, axe accessibility.
- **Impact:** No performance or cross-browser sign-off.
- **Required:** Run Lighthouse/CWV, cross-browser smoke, and an accessibility audit against the final domain.

## BLK-09 — Gallery video content is a placeholder

- **Evidence:** all six tours play the same local `/sample-video.mp4` (the playing frame is generic footage, not the labelled Scheme 45 walkthrough).
- **Impact:** Functionality is fixed, but the media content is not genuine property footage.
- **Required:** Replace with licensed/produced Scheme 45 walkthrough videos and per-tour posters if the content is meant to be real.

---

## Non-Blockers / Confirmed Working

- Public Vercel routes `/`, `/gallery`, `/agencies`, `/properties` render with images and video playback in Chromium.
- Backend suite 210/1,814; frontend 127/127; tsc/build/check:bundle all pass.
- Finance/tenant isolation suites pass at the code/test level.
- Public bundle contains no private ERP data or demo credentials.
