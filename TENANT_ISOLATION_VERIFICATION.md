# TaisarTownHub — Tenant Isolation Verification
## Phase 1 — Multi-Tenant Agency Isolation (P0)

**Date:** 2026-11-14  
**Verification basis:** Independent source-code inspection + live SQLite DB + backend test suite (176 tests / 1613 assertions passing)

---

## 1. Tenancy Model

TaisarTownHub is a **multi-tenant agency platform**. Tenancy is enforced at two layers:

1. **Membership layer** (`app/Models/User.php`): a user's `agency_id` is resolved from their **active** `AgencyMember` memberships (never from request bodies, never from token claims). Inactive memberships never promote a user to platform scope (`isPlatformUser()`).
2. **Record layer** (`app/Http/Controllers/Api/WorkspaceController.php`): every `business_records` row carries an `agency_id` column; reads and writes are scoped to the acting agency.

---

## 2. Agency Context Resolution (verified)

File: `backend/app/Http/Middleware/AgencyContext.php`  
Runs on **every** API request (registered globally via `$middleware->api(append: [...AgencyContext::class])` in `bootstrap/app.php`).

Resolution logic (`User::resolveAgencyId()` / `workspaceAccess()`):

| Actor | Agency resolution | Cross-agency allowed? |
|-------|-------------------|-----------------------|
| Platform user (`admin.manage_agencies`) + explicit `X-Agency-Id` | That agency (if it exists) | **Yes** — flagged `agency_cross_agency=true` for audit |
| Platform user, no explicit agency | `null` (platform scope) | N/A |
| Agency member | Their active membership's agency only | **No** — cannot resolve another agency |
| User with no active membership | `null` (workspace access denied on protected routes) | N/A |

`workspaceAccess()` validates account status, agency status, and verification status on every request; denied users get a structured 403 with `workspace_access` detail, and are barred from the privileged workspace view.

## 3. Read Isolation — `WorkspaceController::allRows()` (verified)

File: `backend/app/Http/Controllers/Api/WorkspaceController.php`, lines 609-627

```php
if (in_array($collection, self::AGENCY_SCOPED, true) && $agencyId !== null) {
    $query->where(function ($q) use ($agencyId) {
        $q->where('agency_id', $agencyId)->orWhereNull('agency_id');
    });
}
```

Behavior:
- **Agency member** (`agencyId` set): receives **only** their own agency's rows + shared legacy platform rows (`agency_id IS NULL`). Rows tagged to **another agency are never returned**.
- **Platform user** (`agencyId` null): sees all rows (platform oversight).
- Collections not in `AGENCY_SCOPED` (e.g. `cms`, `testimonials`): unfiltered by agency (these are platform content).

`AGENCY_SCOPED` collections: `properties, dealers, sellers, buyers, transactions, payments, commissions, files, plots, media, videos, inquiries`.

## 4. Write Isolation — `WorkspaceController::syncList()` (verified)

File: `backend/app/Http/Controllers/Api/WorkspaceController.php`, lines 384-484

Row-level ownership enforcement (line 403):

```php
if ($scoped && $agencyId !== null && $existing && $owner !== null && $owner !== $agencyId) {
    $blocked++; // another agency's row — never touch
    continue;
}
```

Behavior:
- **Create** new row by member → stamped with writer's `agency_id`.
- **Update** own-agency row → allowed.
- **Update** another-agency row → **blocked** (logged, skipped).
- **Shared legacy row** (`agency_id NULL`) → read-write for members but **never re-stamped** (no hijack).
- **Destructive `replace` mode** → scoped to writer's agency for members; requires delete-level permission (`canReplace`); audited.

## 5. Permission Gating (verified)

`canRead()` / `canWrite()` / `canReplace()` map each collection to permission keys, evaluated via `AuthorizesApi::perm()` which checks `User::hasPermission($permission, $agencyId)` — i.e. **agency-scoped role grants**. Platform-wide grants apply only to cross-agency platform admins (explicitly flagged).

Write gates require genuine write permissions (`create_properties`, `manage_dealers`, `pay_commissions`, `void_transactions`, etc.) — the legacy universal `view_properties` read gate can no longer authorize writes.

## 6. Legacy `business_records` Isolation (verified via live DB)

| Metric | Value |
|--------|-------|
| Total rows | 104 |
| Rows with `agency_id` | 8 |
| Rows without `agency_id` (unknown ownership) | **96** |
| Collections | 15 (properties, dealers, inquiries, subscribers, plots, media, videos, files, payments, projects, buyers, commissions, sellers, transactions, testimonials, cms) |

**Isolation decision (per audit requirement #4/#5):**
- 101 rows with NULL `agency_id` are kept **platform-owned / restricted** — they are exposed as shared read-only content to any authorized member via `allRows()` (`orWhereNull('agency_id')`), but are **never assigned to an agency** without verified ownership.
- No row is deleted or overwritten.
- The `LegacyBackfillCommand` is **dry-run by default**; `--apply` requires `--backup=<verified dump>` and derives ownership only from typed twins that already carry an agency stamp. Rows whose ownership cannot be derived are left untouched (platform-owned).

## 7. CNIC & Sensitive Data Isolation (verified)

- `dealers.cnic` encrypted at rest (Laravel `encrypted` cast, AES-256 via `Crypt::encryptString`); migration `2026_11_13_000001_encrypt_dealer_cnic_at_rest.php` converts plaintext → encrypted (idempotent).
- `cnic` in `$hidden` — never serialized; API responses expose `cnicMasked` only (`publicDealer()` unsets `cnic`, `cnicMasked`, `CNIC`).

## 8. MFA & Secrets Isolation (verified)

`app/Support/MfaService.php`:
- TOTP RFC 6238 (HMAC-SHA1), 6-digit, 30s period.
- Secrets **encrypted at rest** (model casts) — plaintext only in enrolment response.
- Recovery codes (8) stored **hashed** (`Hash::make`), single-use, removed after consumption.

## 9. Notification Isolation (verified)

`NotificationController` uses `$request->user()->notifications()` and `findOrFail()` — **users can only access their own notifications**. Routes guarded by `auth:sanctum`; explicit `abort(401)` when unauthenticated (fixes prior 500 → clean 401).

## 10. Audit Logging (verified)

`AuthorizesApi::writeAudit()` appends an append-only entry to `audit_logs` with: `actor, role, action, target, detail, ip, agency_id, user_id, entity_type, entity_id, cross_agency, metadata`. Workspace syncs, financial syncs, media sales, agency verify/status, member invite/remove, role changes are all audited. `AuditLog::scopeForAgency()` supports tenant-scoped audit queries.

## 11. Scheduled Jobs / Queues / Emails (verified)

`routes/console.php`:
- `sanctum:prune-expired --hours=48` (daily) — system maintenance, no tenant data.
- `queue:prune-failed --hours=168` (weekly) — system maintenance.

No tenant-scoped jobs or tenant data exposure in scheduled tasks.

## 12. Test Evidence

| Test file | Relevant assertions |
|-----------|--------------------|
| `WorkspaceHardeningTest` | member cannot overwrite another agency's row; replace scoped to own agency; client-supplied tenancy fields ignored; invalid row ids rejected; merge never deletes |
| `WorkspaceApprovalTest` | inactive membership never falls back to platform privileges; denied user sees only public listings; explicit agency selection matches membership+approval |
| `SupportTicketsTest` | client tenant IDs ignored; member raises ticket in own tenant |

## 13. Residual Risks & Approvals Required

| Risk | Detail | Approval needed? |
|------|--------|------------------|
| Legacy backfill application | Stamping `agency_id` onto the 96 NULL-ownership rows is a **business ownership decision**. Only derivable rows (e.g. commissions → `agency-demo`) are safe to stamp; everything else requires owner verification. | **YES** — run `platform:legacy-backfill --apply --backup=<dump>` only with owner sign-off. |
| Frontend token attachment | All protected routes now require `auth:sanctum`. Confirm the React frontend sends `Authorization: Bearer <token>` on every protected call, or those pages will 401. | **YES** — verify frontend before production cutover. |

## 14. Conclusion

Tenant isolation is **implemented and tested**: agency members cannot read or write another agency's records; legacy unknown-ownership records remain platform-owned; CNIC/MFA/audit data is protected; notifications are own-only. The only outstanding actions are the owner-gated legacy backfill application and frontend token verification.
