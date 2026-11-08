# TaisarTownHub — Authentication & RBAC Middleware (Pillar 8)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. Authentication

- **Mechanism:** Laravel Sanctum (token-based).
- **Token storage:** Frontend stores in `sessionStorage` (not localStorage) — cleared on tab close, XSS-resilient.
- **Endpoints:**
  - `POST /api/auth/login` (throttle 5/min) → returns `{ token, user }`.
  - `POST /api/auth/register` (throttle 3/min).
  - `GET /api/auth/me` (auth:sanctum) → current user.
  - `POST /api/auth/logout` → revokes token.
- **Presenter PIN gate:** Demo builds only; 3 consecutive failures lock for 30s; covers login modal + role-switch paths.

## 2. Roles

Canonical role ids (single source of truth in `src/domain/permissions.ts` and `RolePermissions.php`):

| Role id | Label | Scope |
|---|---|---|
| `super_admin` | Super Admin | Full control, unmasked CNICs/prices, staff mgmt, DB migration |
| `admin` | Agency Admin | Property/Project CRUD, sellers/buyers, staff, payouts, CMS |
| `manager` | Branch Manager | Projects, media, videos, reports |
| `dealer` | Property Dealer | Own deals & commissions, upload docs |
| `operator` | Data Entry Operator | Upload docs only, never verifies |

## 3. Permission Matrix (excerpt)

| Permission | super | admin | manager | dealer | operator |
|---|---|---|---|---|---|
| view_properties | ✓ | ✓ | ✓ | ✓ | ✓ |
| create/edit/delete_properties | ✓ | ✓ | — | — | — |
| view_transactions | ✓ | ✓ | ✓ | — (view_my_deals) | — |
| create/update/void_transactions | ✓ | ✓ | — | — | — |
| manage_sellers/buyers/dealers | ✓ | ✓ | — | — | — |
| manage_media/upload_media | ✓ | ✓ | ✓ | — | — |
| verify_documents | ✓ | ✓ | — | — | ✓ (upload only) |
| edit_cms | ✓ | ✓ | — | — | — |
| manage_users | ✓ | — | — | — | — |
| view_sensitive_cnic | ✓ | ✓ | — | — | — |

Full matrix: `src/domain/permissions.ts` (frontend) and `app/Support/RolePermissions.php` (backend).

## 4. RBAC Enforcement

**Backend:** `AuthorizesApi::requireAny(Request, array $permissions)` — aborts 403 if none held. Called in every mutating controller method.

**Frontend:** `authorize(currentUser, permission, scope)` returns `{ ok, error }`. `requirePermission()` shows toast on failure. UI also filters sidebar by `hasAnyPermission(activeRole, wanted)` (cosmetic; domain gate is real).

**Scoping:** dealers may act only on their own records (dealerId scope enforced in domain layer).

## 5. Permission Union

Explicit `permissions` array on `AuthUser` is unioned with role matrix; `'all'` grants everything. Allows per-user overrides without new roles.

## 6. Session/Token Management

- Token in `sessionStorage` (cleared on tab close).
- `setAuthToken(null)` on logout.
- `getAuthToken()` returns null safely (try/catch for private mode).
- Backend revokes token on logout (`$request->user()->currentAccessToken()->delete()`).

## 7. Middleware Stack

```php
// routes/api.php
Route::post('/auth/login', ...)->middleware('throttle:5,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/workspace', ...);
    Route::put('/workspace', ...);
    // ...
});
```

- `auth:sanctum` — token validation.
- `throttle:X,Y` — rate limiting per IP+user.
- Custom `AuthorizesApi` trait — permission gate inside controllers.

## 8. Security Notes

- Never store tokens in localStorage (XSS).
- PIN gate is a speed bump, not real access control — demo deployments must also use Vercel password protection + fictional data.
- Super-admin-only actions (staff mgmt, DB migration) gated by `manage_users`.
- CNICs masked in public payloads; unmasked only via `view_sensitive_cnic`.
- Private purchase prices & margins never sent to public catalog.
