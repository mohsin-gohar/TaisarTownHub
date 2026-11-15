# RBAC_MATRIX.md — TaisarTownHub

**Generated from the live database** (`php artisan platform:inventory`, dev SQLite instance,
2026-11-13). Every grant below is a real row in `role_permissions`; nothing here is aspirational.

| Fact | Value |
|---|---|
| Permissions in the catalogue | **62** (`permissions` table) |
| Roles (system) | **13** — 7 agency-scoped + 6 platform-scoped |
| Role→permission grants | **362** (`role_permissions` table) |
| Enforcement point (server) | `App\Http\Controllers\Api\AuthorizesApi` → `requirePermission()`, `requireAny()`, `can()` |
| Tenancy resolution | `App\Http\Middleware\AgencyContext` → `User::resolveAgencyId()` (membership-derived, never client-supplied) |
| Frontend mirror | `frontend/src/domain/permissions.ts` (visibility only — never a security control) |

---

## 1. Role catalogue

### Platform scope (`scope = platform`, `agency_id = NULL`)

| Role key | Grants | Purpose |
|---|---:|---|
| `super_admin` | 62 | Full control: users, agencies, roles, subscriptions, platform settings, audit, all agency capabilities |
| `admin` | 60 | Platform operations minus `admin.manage_users` and `admin.manage_platform_settings` |
| `manager` | 20 | Read-heavy platform management (approvals, reports, uploads, sensitive document reads) |
| `moderator` | 13 | Listing moderation + verification decisions + security events |
| `operator` | 12 | Listing create/edit/upload + support tickets |
| `support` | 3 | Support desk only (`admin.support_tickets`, `agency.view`, `legacy.view_reports`) |

### Agency scope (`scope = agency`, `agency_id = NULL` → shared system role, assigned per membership)

| Role key | Grants | Purpose |
|---|---:|---|
| `agency_owner` | 50 | Everything inside the agency incl. staff, settings, sensitive documents, finance |
| `agency_manager` | 49 | Same as owner minus `agency.manage_settings` |
| `property_manager` | 27 | Listing lifecycle + documents + CRM (no finance writes) |
| `agent` | 22 | Create/publish listings, own leads, own documents, own commission view |
| `accountant` | 20 | Finance read/write + payments + reports, no listing writes |
| `document_manager` | 16 | Vault only (upload, versions, permissions, restore, sensitive view) |
| `viewer` | 8 | Read-only (properties, CRM, documents, reports) |

---

## 2. Capability groups

| Group | Keys | Enforced by |
|---|---|---|
| `admin.*` (9) | `manage_agencies`, `manage_platform_settings`, `manage_roles`, `manage_subscriptions`, `manage_users`, `support_tickets`, `view_audit_logs`, `view_reports`, `view_security_events` | `AdminUserController`, `AdminAgencyController`, `AdminSystemController`, `SupportController`, `SubscriptionController` |
| `agency.*` (4) | `manage_settings`, `manage_staff`, `update`, `view` | `AgencyController`, `AgencyStaffController` |
| `properties.*` (8) | `approve`, `assign`, `create`, `delete`, `feature`, `publish`, `update`, `view` | `PropertyController` (+ `properties.approve` for the moderation desk) |
| `documents.*` (8) | `delete`, `download`, `manage_permissions`, `restore`, `update`, `upload`, `view`, `view_sensitive` | `DocumentController` (private disk, authenticated streaming) |
| `crm.*` (6) | `assign_lead`, `create_lead`, `delete_lead`, `export`, `update_lead`, `view` | `LeadController`, `InquiryController` |
| `finance.*` (5) | `create_transaction`, `manage_payments`, `update_transaction`, `view`, `view_reports` | `FinanceController`, `PaymentController`, `TransactionController` |
| `legacy.*` (22) | Legacy ERP aliases (`create_properties`, `manage_sellers`, `view_my_deals`, …) | Kept so the existing ERP screens keep their historical gates while the new capability keys are adopted |

> `legacy.*` keys exist so the pre-Phase-3 frontend gates (`view_properties`, `manage_sellers`, …)
> keep working unchanged. They are granted **in addition** to the modern `properties.*` / `crm.*` /
> `finance.*` keys, never instead of them.

---

## 3. Cross-agency behaviour (verified by tests)

| Situation | Result | Test |
|---|---|---|
| Member of agency A requests agency B via `X-Agency-Id` | Denied — the header is ignored, the membership decides | `AgencyContextTest` |
| Platform user **without** `admin.manage_agencies` sets `X-Agency-Id` | Denied (403) | `AgencyContextTest` |
| Platform user **with** `admin.manage_agencies` enters agency B | Allowed, and the action is audited as `cross_agency` | `CrossAgencyCapabilitiesTest` |
| Client posts `agency_id` in a request body | Stripped/ignored — tenancy is server-stamped | `WorkspaceHardeningTest` |
| Cross-agency record id (properties, leads, finance, documents, tickets) | 404 (enumeration-safe), not 403 | `PropertyLifecycleTest`, `CrmLeadsTest`, `SupportTicketsTest`, `DocumentSharesTest` |

---

## 4. Frontend permission-aware navigation

`frontend/src/components/admin/AdminLayout.tsx` filters the sidebar by the **resolved** permission
set (legacy matrix for the base roles + `enterprise_permissions` from `GET /api/auth/me`).
The UI is therefore a convenience layer only — every action behind it re-checks the permission on
the server, and the sidebar hides modules the backend would reject.

Second factor (Phase 6): `GET /api/auth/mfa/status`, `POST /api/auth/mfa/setup|enable|disable`
and `GET /api/auth/login-history` are exposed to the signed-in account only; the token is never
issued before the second factor is satisfied.
