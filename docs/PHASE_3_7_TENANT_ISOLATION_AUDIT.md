# Phase 3.7 — Tenant Isolation Audit

**Repository:** `mohsin-gohar/TaisarTownHub`  
**Branch:** `audit/phase-2.11-concurrency-fixes-20261128`  
**Scope:** request tenant resolution, authorization fallback, record-level ownership, cross-tenant reads/writes/deletes, legacy workspace data, and regression coverage.

## Executive result

The audit reviewed the tenant-context middleware and permission resolver, shared API authorization helpers, API route groups, tenant-sensitive controller query paths, and existing isolation feature tests.

One concrete authorization gap was found and fixed: agency-scoped users with `manage_dealers` could update only dealers belonging to their agency, but the dealer soft-delete endpoint did not apply the same ownership check. A member could therefore soft-delete a dealer belonging to another agency if they obtained its ID.

The delete endpoint now returns an enumeration-safe 404 for a dealer outside the acting agency. Platform users operating without an agency context retain their platform-level permission path. Delete audit records now explicitly include the dealer's agency and entity identity.

## Fix committed

### Dealer deletion ownership enforcement

File: `backend/app/Http/Controllers/Api/DealerController.php`

- Resolve acting agency exclusively from `AgencyContext` request attributes.
- If an agency context exists, require `dealers.agency_id === actingAgencyId`.
- Reject foreign or unlinked dealers with 404 before soft deletion.
- Keep platform-wide deletion available only through the existing `manage_dealers` permission path when no agency context is active.
- Stamp agency/entity metadata into the deletion audit event.

Regression tests added to `backend/tests/Feature/DealerAgencyLinkTest.php`:
- An agency manager cannot delete another agency's dealer; row remains undeleted.
- An agency manager can delete a dealer belonging to their own agency.

## Isolation controls reviewed

### Tenant context and permissions

- `AgencyContext`: derives requested context from active membership or explicit platform `admin.manage_agencies`; rejects protected requests when workspace access is not approved; does not trust body tenant IDs.
- `User::workspaceAccess()`: checks current account, membership, and agency status on each request; inactive memberships do not silently become platform access.
- `User::permissionsFor()`: agency permissions come from the active membership and role; requesting an unrelated agency yields no agency permissions.
- `AuthorizesApi`: agency-scoped permissions are checked in the resolved agency; platform grants are only used as a fallback when the request is explicitly cross-agency and the context marks it as such.
- API route definitions: protected workspace routes require Sanctum and MFA middleware; public routes are intentionally public-read or public-submission surfaces.

### Record-level isolation

- Properties: update/delete/submit/visibility operations use record ownership checks; a tenant member cannot manage a property whose `agency_id` differs from the resolved agency. Approval/rejection is a platform moderation capability.
- CRM inquiries/leads: agency reads and record mutations use agency-scoped query builders; lead assignments are checked against active agency membership.
- Finance: transaction, payment, schedule, commission, and expense paths use agency-scoped parent/child lookups; dealer attribution is checked against the transaction agency. Existing cross-agency regression coverage verifies reads and mutations return 404.
- Documents and folders: queries and mutations resolve the acting agency first; folder/property references are validated against that same agency; share-token downloads are a deliberately separate, capability-token-based public sharing path with expiry/revocation controls.
- Media: list, update, delete, and property association paths scope records and referenced properties to the acting agency.
- Agency staff and roles: member, invitation, and role lookups are agency-scoped; role resolution rejects roles outside the acting agency or allowed shared roles.
- Support tickets: agency tickets are visible only in the matching active agency context; platform-only tickets are private to their creator; staff-wide access requires the support permission.
- Workspace sync: existing tenant matrix tests cover 13 agency-scoped collections, foreign-row overwrite attempts, replace-mode deletion, and unowned legacy rows. Platform-global collections are separately read-only to agency roles.
- Saved searches and favorites: ownership is user-scoped rather than agency-scoped, as intended.
- Public agency, property, dealer, project, video and map endpoints are intentionally public surfaces; sensitive property/finance/document fields are separately restricted or omitted from public payloads.

## Existing isolation tests reviewed

- `AgencyContextTest`: active membership resolution, foreign agency header denial, platform access, workspace read isolation.
- `CrossAgencyCapabilitiesTest`: platform cross-agency capabilities do not leak into ordinary agency-member permissions.
- `WorkspaceTenantMatrixTest`: 13 collection read/write/replace isolation cases.
- `LegacyPlatformOwnershipTest` and `LegacyTenancyResolverTest`: unowned legacy rows remain hidden/unwritable until an approved ownership mapping is applied.
- `FinanceIsolationTest`: cross-agency deal/payment/commission/expense protections.
- `FileVaultSecurityTest`, `DocumentSharesTest`, `AgencyStorageOversightTest`: document access, shares, and platform oversight boundaries.
- `DealerAgencyLinkTest`: dealer ownership assignment and the new delete regression cases.

## Deliberate exceptions

- Public marketplace records are not tenant-private once approved and published; their payload serializers exclude sensitive fields.
- Platform administrators with explicit platform permissions may intentionally operate cross-agency. Such access is not treated as a tenant-member bypass.
- Public video comments/likes and share-token downloads are public interaction/capability flows; attribution and share-token checks are enforced separately.
- Legacy rows with no trustworthy agency ownership remain platform-owned/unassigned rather than being guessed into a tenant.

## Residual risks / follow-up

1. `InquiryController::index` permits the platform/no-agency legacy view path when a caller has the corresponding platform-level inquiry permission. This is a deliberate global capability path, but its permission grant must remain platform-only; agency roles must never be allowed to receive it through custom permissions.
2. The legacy dealer ownership fallback is based on active user membership where `dealers.agency_id` is null. It is intentionally retained for historical records; completing the dealer-agency backfill will reduce ambiguity.
3. Public dealer/project/video directory endpoints expose public display fields across agencies by design. Any future move to private tenant-only directories must change route policy and serializer together.
4. Composite database constraints that guarantee child and parent rows share the same `agency_id` remain a separate data-reconciliation task (identified in Phase 3.6). Application checks alone cannot prevent every inconsistent direct database write.
5. Full dynamic route-by-route adversarial testing, database-backed PHPUnit execution, and browser/API runtime verification were not available in this session.

## Exit checklist

- [x] Review tenant context resolution and explicit cross-agency rules.
- [x] Review permission resolution and agency/platform fallback.
- [x] Review tenant-sensitive controller query and mutation paths.
- [x] Review workspace legacy ownership and replace-mode boundaries.
- [x] Fix dealer cross-agency soft-delete authorization gap.
- [x] Add foreign-dealer deletion regression tests.
- [x] Record deliberate public/platform exceptions and residual risks.
- [ ] Run `php artisan test --filter=DealerAgencyLinkTest`.
- [ ] Run tenant isolation feature suites on the configured MySQL database.
- [ ] Run adversarial cross-tenant API tests against a deployed staging environment.
- [ ] Reconcile tenant mismatches before adding composite tenant foreign keys.

**Phase 3.7 status: source-level audit and identified fix committed; runtime test verification remains OPEN.**
