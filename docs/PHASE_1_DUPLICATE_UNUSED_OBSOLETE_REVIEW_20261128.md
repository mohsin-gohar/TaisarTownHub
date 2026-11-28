# TaisarTownHub — Duplicate, Unused & Obsolete File Review

**Review date:** 2026-11-28  
**Repository:** `mohsin-gohar/TaisarTownHub`  
**Reviewed snapshot:** `ae306f81a08d781be3ef9c1f1a490b24b3010b78`  
**Scope:** All 566 tracked file paths inventoried at the snapshot; focused content/reference checks for duplicate blobs, deployment/configuration files, scripts, generated artifacts, and historical documentation.

## Executive result

- **Exact duplicate content:** one group of 8 identical Laravel `.gitignore` placeholder files. This is expected template content, not duplicate application code.
- **Confirmed obsolete file:** root `Dockerfile.prod`. It self-identifies as deprecated and the README, deployment guide, and Compose configuration direct operators to the per-service Dockerfiles instead.
- **Likely obsolete generated artifact:** `.screenshots/api-properties.json`. It is a saved PowerShell `Invoke-WebRequest` response transcript from `127.0.0.1:8080`, not an application fixture or source file; no code reference was found in the targeted reference checks.
- **Unreferenced/manual QA utilities:** several one-off browser/visual scripts have no npm script or workflow entry found in the reviewed configuration. They remain manually runnable and are not automatically safe to delete.
- **Historical documentation:** multiple audit/readiness reports describe older commits and metrics. Some carry explicit historical warnings; the old file ledger and older readiness report should not be treated as current release evidence.
- **No files were deleted or modified in this task.** This is an evidence-based identification pass; destructive cleanup is deferred until the remaining repository review is complete.

## 1. Exact duplicate content

The following eight paths have the same Git blob SHA and identical contents:

- `backend/bootstrap/cache/.gitignore`
- `backend/storage/app/private/documents/.gitignore`
- `backend/storage/app/public/.gitignore`
- `backend/storage/framework/cache/data/.gitignore`
- `backend/storage/framework/sessions/.gitignore`
- `backend/storage/framework/testing/.gitignore`
- `backend/storage/framework/views/.gitignore`
- `backend/storage/logs/.gitignore`

**Disposition:** Keep. These are standard Laravel storage/cache directory placeholders. Identical content is intentional; removing them could cause empty runtime directories to disappear from Git checkouts.

No duplicate application source blobs were found in the 566-file tree. The duplicate scan compared Git blob SHA values across every tracked file.

## 2. Confirmed obsolete / deprecated

| Path | Evidence | Recommendation |
|---|---|---|
| `Dockerfile.prod` (repository root) | File header says “DEPRECATED / NOT AUTHORITATIVE — DO NOT BUILD THIS FILE”; README and deployment guide say to use `backend/Dockerfile.prod` and `frontend/Dockerfile.prod`; Compose builds the per-service images. | Remove after confirming no external deployment job still references it, or move to an explicitly named archive if historical retention is required. |
| `docs/PRODUCTION_READINESS.md` | Dated 2026-11-23; refers to commit `6c92d52`, older test counts and blocked deployment items. The 2026-11-27 `docs/STATUS.md` and `docs/RELEASE_CHECKLIST.md` are newer and provide a later baseline. | Mark prominently as historical or archive; do not use it for current release decisions. |
| `docs/FINAL_ENTERPRISE_AUDIT.md` | Explicitly labelled historical; dated 2026-11-23; reports 706 files and ~17% line-by-line coverage. | Retain as history, but link to the current inventory/review and do not treat its verdict as current. |
| `docs/FINAL_COMPLETION_REPORT.md` | Explicitly labelled historical; describes the 2026-11-25 snapshot at commit `372a00a`. | Retain as history; not a current sign-off. |
| `docs/COMPLETE_FILE_AUDIT_LEDGER.md` | Historical ledger refers to 706/701 files and many unchecked “READ” items; current inventory snapshot has 566 tracked blobs. | Reconcile or supersede with the current inventory and a real review-status ledger. Do not count it as proof that every file was reviewed. |

## 3. Likely unused generated artifact

| Path | Evidence | Recommendation |
|---|---|---|
| `.screenshots/api-properties.json` | Contents are a formatted PowerShell web-response transcript (headers, forms, parsed HTML, response body) from local host `127.0.0.1:8080`; it is not valid JSON despite the extension. No source-code reference was found in targeted searches. | Remove from source control or replace with a deliberate, sanitized test fixture if a test genuinely consumes it. |

This is the strongest candidate for cleanup after a full repository-wide reference check.

## 4. Standalone QA scripts — no automatic entry point found

The following scripts are useful one-off tools, but the reviewed frontend `package.json` does not expose dedicated npm scripts for them, and targeted searches did not find workflow invocation for these names:

- `frontend/scripts/qa-audit.mts`
- `frontend/scripts/qa-theme.mts`
- `frontend/scripts/shot-current.mts`
- `frontend/scripts/shot-mobile-parts.mts`
- `frontend/scripts/browser-admin-interactions.mts`
- `frontend/scripts/browser-audit.mts`
- `frontend/scripts/browser-interactions.mts`
- `frontend/scripts/mfa-e2e.mts`

Notes:
- The `qa-*` and `shot-*` scripts default to hard-coded Windows paths under `C:/Users/mohsi/AppData/Local/Temp/opencode`; this makes their default output location machine-specific.
- Browser audit scripts document direct `npx tsx ...` invocation and write generated output under ignored `frontend/audit/`; absence from npm/CI does not make them dead code.
- `frontend/scripts/verify-fixes.mts` is explicitly a focused re-verification for defects found by `browser-interactions.mts`, so it is not a duplicate.
- Other scripts such as `security-probe.mts`, `load-test.mts`, `mail-flow-e2e.mts`, `role-matrix-e2e.mts`, and `concurrency-booking.mts` are documented in the changelog or have direct companion references. Keep them as diagnostic tooling unless a later execution audit proves otherwise.

**Disposition:** Do not delete these scripts based only on missing npm aliases. Add documented package aliases or move clearly machine-specific experiments to a dedicated audit-tools area in a later cleanup pass.

## 5. Mirrored configuration — intentional, but drift-prone

| Paths | Finding | Recommendation |
|---|---|---|
| `vercel.json` and `frontend/vercel.json` | Both define the SPA fallback, API proxy and legacy gallery redirect. They support deployment when Vercel's project root is either repository root or `frontend/`. They are not byte-identical: root config includes an explicit install command; frontend config has an additional legacy X-XSS header. | Keep both while both project-root deployment modes are supported. Align security headers and add/retain parity tests so the copies cannot drift. |
| Root `package.json` and `frontend/package.json` | Root scripts delegate to the frontend workspace; the frontend package owns React/Vite dependencies and actual scripts. | Keep; this is a workspace wrapper, not an accidental duplicate. |
| `backend/package.json` | Supports Laravel's own Vite assets and is invoked by the backend Composer setup script; backend resources include CSS/JS and the Laravel welcome view. | Keep unless the backend setup flow is intentionally retired. |
| `backend/.gitignore`, `frontend/.gitignore`, root `.gitignore` | Overlapping patterns are scoped to separate subprojects and root-level generated artifacts. | Keep; consolidate only if the rules can be simplified without changing ignore coverage. |

## 6. Files that look obsolete but are actively used

These were checked to avoid false-positive cleanup:

- `frontend/public/sample-video.mp4` is referenced by `frontend/src/data/agencyVideosSeed.ts`, `frontend/src/data/publicSeed.ts`, and `frontend/src/components/public/MediaGallerySection.tsx`.
- `frontend/public/hero-slide-2.jpg` through `hero-slide-5.jpg`, and `frontend/public/image.png`, are referenced by `frontend/src/components/public/HeroSection.tsx`.
- `backend/resources/views/welcome.blade.php` is served by the root route in `backend/routes/web.php`; it is not an unreferenced template.
- `backend/routes/api_definitions.php` is loaded by `backend/routes/api.php` for both legacy and versioned API prefixes.
- The storage `.gitignore` files are intentional directory placeholders, not junk duplicates.

## 7. Cleanup decision register

| Priority | Item | Action |
|---|---|---|
| High | Root `Dockerfile.prod` | Confirm external references, then remove/archive; do not use for deployments. |
| High | `.screenshots/api-properties.json` | Confirm no external/local workflow dependency, then remove or convert into a sanitized fixture. |
| Medium | `docs/PRODUCTION_READINESS.md` and `docs/COMPLETE_FILE_AUDIT_LEDGER.md` | Mark historical/superseded and link current evidence. |
| Medium | `frontend/scripts/qa-*.mts`, `shot-*.mts` | Replace hard-coded output defaults with portable temp/output configuration; document or consolidate. |
| Low | Mirrored Vercel config | Keep both; enforce parity for shared rewrite/security policy. |
| None | Eight identical storage `.gitignore` placeholders | Keep. |

## Method and limitations

- Enumerated all tracked blobs from the recursive Git tree at the reviewed commit; GitHub reported the tree was not truncated.
- Compared blob SHA values to detect byte-identical tracked files.
- Inspected package scripts, route entry points, Compose build contexts, Vercel deployment configuration, selected script headers, and targeted repository references.
- GitHub code search and path/reference checks are not equivalent to a full compiler/bundler dependency graph. “No reference found” is therefore not treated as proof of dead code.
- This task identifies candidates; it does not authorize deleting potentially operational files.
- CI was not triggered.
