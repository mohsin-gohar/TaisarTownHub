# TaisarTownHub — Independent Verification Update

**Date:** 2026-11-27  
**Reviewed repository:** `mohsin-gohar/TaisarTownHub`  
**Baseline main commit:** `e2765a7b42b58437320be39d66ea0f0af59965d0`  
**Repair branch:** `fix/public-video-walkthrough-route-20261127`  
**Pull request:** [#24 — Harden tenant data access and fix public marketplace UI](https://github.com/mohsin-gohar/TaisarTownHub/pull/24)

## Release verdict

**Not approved for production sign-off yet.** The repair branch contains verified security and public-UI fixes, and earlier CI/Docker runs passed. The latest permission and Vercel proxy changes are still awaiting CI. The live backend API origin did not respond during the read-only health check, so the live agency and map experiences remain unavailable.

## Repository audit coverage

- The current baseline Git tree contains **564 tracked files (blob entries)**. The tree response contained 640 total entries including directories.
- The existing `docs/COMPLETE_FILE_AUDIT_LEDGER.md` says 706 tracked files and explicitly marks audit coverage **In progress**; numerous file entries remain unchecked.
- The release checklist's claim that 706 files were individually reviewed is therefore not supported by the ledger or the current baseline tree.
- **A complete line-by-line review of every repository file has not been completed.** This report does not claim otherwise.

## Verified code defects fixed on PR #24

### Tenant and sensitive-data access

1. `PropertyController::show` exposed confidential property financial fields to ordinary owning-agency members without a finance permission.
2. An agency-scoped permission could be used against another agency's public property to expose its confidential purchase/selling prices and internal notes.
3. `pending_changes` was not hidden by the Property model and could appear in responses outside the moderation workflow.
4. `LeadController::export` checked `crm.export` but did not apply the assigned-agent row scope used by the CRM list. A member with export permission but without `crm.assign_lead` could export another agent's assigned leads.

Fixes on PR #24:
- Property financial fields require `finance.view` in the owning agency (or an intentional legacy platform sensitive-data grant).
- Cross-agency property requests receive only the public payload.
- `pending_changes` is hidden by default and exposed only to users with `properties.approve`.
- CRM CSV export applies the own/unassigned lead scope unless the user has `crm.assign_lead`.

Regression tests cover the owning-agency detail permission, cross-agency privacy, moderator-only staged changes, and CRM export isolation.

### Public UI and deployment

- Agency directory showed both an API failure message and a contradictory no-results message. The repair branch suppresses the false empty state when loading failed.
- The video gallery had no honest empty state while its bundled seed videos were placeholders. The repair branch explains that no verified tours are published yet.
- Footer social icons pointed to generic social-network homepages when no official profile URL was configured. The repair branch hides unconfigured/root links.
- Hero search filters were compressed into six desktop columns, clipping long labels. The repair branch uses three readable desktop columns.
- `/video-walkthroughs` is now a backward-compatible alias for the gallery route.
- Playwright now tests 390px mobile navigation/no-overflow and desktop filter widths.
- `vercel.json` now routes `/api/:path*` to `https://api.taisartownhub.com/api/:path*` before the SPA catch-all. This prevents API requests from being served the frontend `index.html`; the Laravel API host must still be online.

## Verification evidence

### Passing runs (earlier branch heads)

- **CI at `9ebff378`:** backend suite passed **425 tests / 3,716 assertions** against MySQL 8; frontend verification and OpenAPI parsing passed; Playwright Chromium passed **52 tests**.
- **Docker CI at `9ebff378`:** backend and frontend images built; migrations and backend health check passed; MySQL backup and isolated restore drill passed; full-stack integration smoke tests passed.
- **CI at `1b4701c`:** frontend verification and Playwright Chromium passed, including the mobile navigation/no-horizontal-overflow and desktop search-filter-width tests.

### Latest head still pending

At the time of this report, latest branch head is `c571814f1a4906a376dd6988dc3784924cd2c9ec`. It includes the final `finance.view` permission alignment and Vercel API rewrite. Its CI, Docker CI, and Vercel deployment checks are pending; results from earlier commits are not represented as verification of this latest head.

## Live website observations

Read-only browser checks of `https://taisartownhub.vercel.app/` found:
- Dark navy and champagne-gold visual theme is consistent across the public pages.
- The public agency directory displays **“Could not load agencies right now.”** and, before the PR fix, also showed a misleading no-results message.
- The public maps page cannot load its map data.
- The video gallery has no verified real video tours; bundled `/sample-video.mp4` entries are placeholders and are correctly filtered from public display.
- Hero filter labels were clipped on desktop; addressed on PR #24.
- The navbar source contains a responsive mobile drawer and the footer grid uses responsive breakpoints. The browser automation environment could not resize its viewport; actual 390px checks are instead covered by the Playwright CI test.

A direct read-only browser check of `https://api.taisartownhub.com/api/health` returned `ERR_EMPTY_RESPONSE`. The live agency and map failures therefore remain a backend/API availability blocker. Vercel's branch preview is protected by Deployment Protection and could not be inspected without the Vercel connection.

## Required before production release

1. Complete CI, Docker CI, and Vercel checks on the latest PR head.
2. Connect Vercel and the actual backend hosting provider; inspect the production project, `VITE_API_URL`, API deployment, logs, DNS, TLS, CORS, database, storage, queues, and health checks.
3. Restore the API origin and verify `/api/health`, `/api/agencies`, `/api/maps`, authentication, agency workspace, and private-document authorization in the live deployment.
4. Verify the 11 map image files expected by `MapSeeder` exist in the deployed public storage. These image files are not tracked in the current repository tree.
5. Reconcile the 706-file historical ledger against the current 564-file tree and finish the remaining file-by-file audit.
6. Only then approve and deploy the PR; no merge to `main` or production deployment has been performed by this review.

## Important scope note

The checks above are evidence-based but **do not constitute a claim that every file, every role, every route, every button, or every production integration has been exhaustively verified**. The remaining work is explicitly listed rather than hidden behind a 100% completion claim.
