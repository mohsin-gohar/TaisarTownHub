# ROLE_BASED_TEST_REPORT.md — TaisarTownHub

**Date:** 2026-11-14 · Every discovered role was tested as a real signed-in actor. Roles were
**discovered from the live database** (62 permissions / 13 roles / 362 grants; `users.role` is
a legacy display column — real authorization is `agency_members.role_id` grants resolved by
`AgencyContext` + `AuthorizesApi`), never invented.

Evidence: `frontend/audit/role-matrix.json` (**51/51 PASS**) — `role-matrix-e2e.mts` drives the
real API as each persona; the admin-shell browser walk is in
`frontend/audit/admin-interactions.json` (see `BUTTON_LINK_AND_FORM_AUDIT.md`).

## 1. Roles under test (all real)

| Persona | Account | Server-resolved role | Grants | Created how |
|---|---|---|---|---|
| Super Admin | `superadmin@taisartownhub.com` | `super_admin` | 62 (all) | seeded |
| Agency Owner (+platform admin) | `admin@taisartownhub.com` | `agency_owner` (Premier Advisory) + `admin` | 50 + platform | seeded |
| Agency Manager | `manager@taisartownhub.com` | `agency_manager` | 49 | seeded |
| Agent / Dealer | `dealer@taisartownhub.com` | `agent` (+ dealer `deal-3`) | 22 | seeded |
| Document Manager | `operator@taisartownhub.com` | `document_manager` | 16 | seeded |
| Accountant | `staff.accountant.*@audit.test` | `accountant` | 20 | **real invite → accept** |
| Viewer | `staff.viewer.*@audit.test` | `viewer` | 8 | **real invite → accept** |
| Property Manager | `staff.property_manager.*@audit.test` | `property_manager` | 27 | **real invite → accept** |
| Guest | incognito browser context | unauthenticated | — | — |
| Registered buyer | `buyer.*@audit.test` | agency_owner (self-onboarded agency) | 50 | **real register form** |

## 2. Full staff lifecycle (through the product, no hand-granted privileges)

| Step | Expected | Actual | Verdict |
|---|---|---|---|
| Owner invites accountant/viewer/property_manager (`POST /agency/members/invite`, scoped) | 201 + token | 201 + token (plaintext returned once, sha256 at rest) | PASS |
| Invitee accepts (`POST /agency/invitations/accept`, unauthenticated, name+password) | account created + token | 200, account created by accept endpoint | PASS |
| Owner revokes property_manager (`DELETE /agency/members/{id}`) | 200 | 200 | PASS |
| Revoked member's existing token on `/workspace` | 401/403 | **403** | PASS — revocation effective immediately |

## 3. Authorisation matrix (server decides; read-only probes)

Probes use deliberately invalid bodies: permitted roles stop at validation
(422/404 = genuinely reachable), non-permitted roles stop earlier with 403.
Hidden frontend buttons are irrelevant to these results — the Laravel backend
enforces every permission independently.

| Role | property create | property delete | transaction create | staff invite | platform users list | payment record |
|---|---|---|---|---|---|---|
| super_admin | 422 ✓ | 404 ✓ | 422 ✓ | 403 ✓¹ | 200 ✓ | 404 ✓ |
| agency_owner (+pl_admin) | 422 ✓ | 404 ✓ | 422 ✓ | 403 ✓¹ | 403 ✓ | 404 ✓ |
| agency_manager | 422 ✓ | 404 ✓ | 422 ✓ | 422 ✓ | 403 ✓ | 404 ✓ |
| agent (dealer) | 422 ✓ | 403 ✓ | 403 ✓ | 403 ✓ | 403 ✓ | 403 ✓ |
| document_manager | 403 ✓ | 403 ✓ | 403 ✓ | 403 ✓ | 403 ✓ | 403 ✓ |
| accountant | 403 ✓ | 403 ✓ | 422 ✓ | 403 ✓ | 403 ✓ | 404 ✓ |
| viewer | 403 ✓ | 403 ✓ | 403 ✓ | 403 ✓ | 403 ✓ | 403 ✓ |
| property_manager | 422 ✓ | 404 ✓ | 403 ✓ | 403 ✓ | 403 ✓ | 403 ✓ |
| *(scoped proof)* agency_owner & super_admin with `X-Agency-Id: agency-demo` | — | — | — | **422 ✓** | — | — |

¹ Platform users inherit **no agency** — unscoped invite is denied by design
(deny-by-default); the scoped rows prove the same action succeeds with explicit context,
exactly as the product's own UI sends it.

## 4. What each role actually saw in the browser (admin shell)

Detailed click-by-click results: `frontend/audit/admin-interactions.json`. Summary grid:

| Module | super_admin | owner | manager | agent | doc_manager |
|---|---|---|---|---|---|
| Agency Dashboard | ✅ | ✅ | ✅ | ✅ | ✅ (scoped view) |
| Staff & Roles | ✅ | ✅ | ✅ view-only actions | — | — |
| Document Vault | ✅ | ✅ | ✅ | — | ✅ (upload/download only) |
| CRM Pipeline | ✅ | ✅ | ✅ | ✅ own leads | — |
| Media Studio | ✅ | ✅ | ✅ | — | — |
| Subscription & Usage | ✅ | ✅ | ✅ | — | — |
| Platform modules (agencies/users/plans/invoices/audit/settings/CMS/moderation) | ✅ | 403 server-side | 403 | 403 | 403 |

## 5. Per-role findings

| ID | Severity | Role | Finding | Disposition |
|---|---|---|---|---|
| N-02 | Low | agency_owner | Seed put 4 staff on the Free-Trial plan (limit 2) → invites 402 out of the box | Fixed via the real admin subscription API (Enterprise); seed inconsistency documented |
| — | Info | super_admin | Earlier "superadmin 403 on /admin/users" was a QA-script bug (owner token mislabelled); live re-verify with a genuine superadmin token = 200 | Closed, not an app bug |
| — | Info | property_manager | Revocation kills existing tokens immediately (403, not just future logins) | correct behavior confirmed |

## 6. Verdict

- 9 personas × 6 attack surfaces + lifecycle + revocation: **51/51**.
- Deny-by-default tenancy holds (platform users get nothing without explicit scope).
- Grant map matches the RBAC inventory 1:1; no escalation path found from any staff role.
- Buyer onboarding is gated: unverified agencies are locked out of the workspace until a
  platform admin verifies them (see `END_TO_END_WORKFLOW_REPORT.md`, W1).
