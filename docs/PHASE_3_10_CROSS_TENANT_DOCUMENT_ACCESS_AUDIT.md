# Phase 3.10 — Cross-Tenant Document Access Test Audit

**Branch:** `audit/phase-2.11-concurrency-fixes-20261128`  
**Scope:** private document-vault tenant isolation regression coverage.

## Added regression suite

Added `backend/tests/Feature/CrossTenantDocumentAccessTest.php`.

The suite creates two separate agencies, users with document permissions, and private document records. It tests:

1. Agency A's document listing includes its own record but excludes Agency B's title and ID.
2. Agency A receives 404 when it guesses an Agency B document ID for:
   - version history,
   - inline preview,
   - file download,
   - document access logs,
   - share-link metadata,
   - document update,
   - version upload,
   - share creation and revocation,
   - soft delete,
   - restore,
   - permanent delete.
3. Rejected cross-tenant mutations leave Agency B's document owner, title, status, soft-delete state, and stored file unchanged.
4. Agency B can still download its own document after Agency A's unauthorized probe.

## Existing controls reviewed

- `DocumentController::findScoped()` resolves by both `agency_id` and document ID and returns 404 for foreign IDs.
- Download and preview share the same scoped streaming path.
- Version, access-log, and share metadata routes resolve the parent document within the acting agency before reading children.
- Update, version upload, share creation/revocation, delete, restore, and permanent-delete flows scope the parent document to the acting agency.
- Public share URLs remain an intentional capability-based exception: a valid, unexpired, non-revoked share token can be used without tenant login. This is distinct from authenticated cross-tenant document-ID access.

## Verification status

- New test file committed and re-fetched from the target branch for source verification.
- PHPUnit was **not executed** in this environment; runtime pass status is unverified.
- No production database or `main` branch changes.
- This phase adds tests; no controller change was needed based on the scoped lookup paths reviewed.

