# Phase 2 — Policy & Authorization Audit

No `Policies/` directory exists; authorization is centralized in `AuthorizesApi` + `App\Support\RolePermissions` / `Permissions`. Roles verified: Super Admin, Admin, Manager, Operator (platform); Agency Owner, Agency Manager, Dealer, Agent (agency).

## Findings
| # | Location | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | Permissions::platformRoleGrants | Permissions.php:199-201 | HIGH | `super_admin`/`admin` resolve to ALL catalog keys (admin excludes only 2) — broad bypass surface; intentional but powerful |
| 2 | Permissions legacy roles | Permissions.php:210-224 | HIGH | Legacy `manager`/`operator` platform roles receive `properties.approve`, `properties.delete`, `admin.view_audit_logs` — verify least privilege |
| 3 | RolePermissions::forRole/allows | RolePermissions.php:50-58 | LOW | `forRole()` falls back to operator for unknown roles but `allows()` falls back to `[]` — inconsistent semantics |
| 4 | AuthorizesApi::can | AuthorizesApi.php:54 | MEDIUM | Defaults `workspace_access.allowed` to true when attribute absent |
| 5 | AgencyContext crossAgency | AgencyContext.php:49 | MEDIUM | Holders of `admin.manage_agencies` retain platform-wide grants across tenants |

## Object-level authorization (verified present)
- `PropertyController::assertCanManage` + `assertDealerInAgency` (PropertyController.php:886-917) — row-level ownership + cross-agency FK checks.
- `TransactionController` (70-98,150-163), `FinanceController::findScoped` (44-65,1085-1100), `DocumentController` (1055+), `MediaController` (134-136,285-287), `LeadController` (109-111,365-385) — correct object-level scoping.
- `DealerController::destroy` cross-tenant gap — FIXED this phase.

## No frontend reliance
Authorization is enforced server-side via `AuthorizesApi`; the frontend `permissions` flags mirror `can()` and never grant access on their own. Positive.

## Remaining
Items 1-5 documented in PHASE_2_REMAINING_ISSUES. No privilege escalation introduced; the broad admin grants are by design but flagged for least-privilege review.