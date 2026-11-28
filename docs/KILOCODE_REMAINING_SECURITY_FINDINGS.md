# KILOCODE Remaining Security Findings

**Date:** 2026-11-28  
**Branch:** `audit/kilocode-phase-1-6-gap-closure`  
**Scope:** Security issues not resolved in Phases 1-6, discovered during gap audit.

---

## HIGH — Google OAuth Session Bootstrap Missing

**Location:** `frontend/src/components/public/AuthPage.tsx:148-167`

### Description
After successful Google OAuth code exchange, the frontend stores the returned token in `sessionStorage` (`tth_token`) and switches the React view to `'admin'`. **It does not reload the page**, so the `AppContext` never re-hydrates the authenticated workspace (permissions, agency context, user profile).

### Contrast: MFA Flow (Correct)
```typescript
// AuthPage.tsx:206-212
const handleMfaVerified = () => {
  clearPendingMfaChallenge();
  setMfaChallenge(null);
  window.location.assign('/admin');  // Full reload → fresh boot with stored token
};
```

### Impact
- User appears "signed in" in UI but API calls may fail (token not sent on first requests)
- Race condition: `setCurrentView('admin')` renders workspace before token is available in `Authorization` header
- No session fixation protection — token stays in `sessionStorage` across tab navigation

### Fix
```typescript
// In AuthPage.tsx exchangeGoogleCode .then():
if (result.success && result.token) {
  setAuthToken(result.token);
  window.location.assign('/admin');  // Force bootstrap
  return;
}
```

---

## HIGH — JWT in sessionStorage (Not httpOnly)

**Location:** `frontend/src/services/api.ts:107-110` (`setAuthToken`)

### Description
Access token stored in `sessionStorage.setItem('tth_token', token)`. Accessible to any JavaScript on the page (XSS → token theft).

### Recommendation
- Use Laravel Sanctum's httpOnly cookie (`sanctum.token` / `XSRF-TOKEN`) for SPA auth
- Set `SESSION_DRIVER=cookie`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_SECURE_COOKIE=true`
- Remove `sessionStorage` token handling; rely on `withCredentials: true` fetch

---

## MEDIUM — Sanctum Token No Idle Timeout

**Location:** `backend/config/sanctum.php` (default) / `.env`

### Description
`expiration: 1440` (24 hours) with **no idle timeout**. A stolen token remains valid for 24h regardless of activity.

### Fix
```php
// config/sanctum.php
'expiration' => 1440,
// Add custom middleware to track last_activity and revoke on idle > 2h
```

---

## MEDIUM — Session Cookie Secure Default Unset

**Location:** `backend/config/session.php`

### Description
`'secure' => env('SESSION_SECURE_COOKIE', false)` — defaults to `false`. On HTTPS production, must be `true` explicitly.

### Fix
```env
SESSION_SECURE_COOKIE=true
```

---

## MEDIUM — PropertyDetailPage Inquiry Form Missing Captcha/Honeypot

**Location:** `frontend/src/pages/PropertyDetailPage.tsx` (inquiry form)

### Description
Public inquiry submission lacks:
- hCaptcha/turnstile challenge (G-11 captcha only on `/api/inquiries` endpoint)
- Honeypot field
- Client-side error state for failed submissions

### Fix
Integrate `solveInquiryCaptcha()` flow (from `EnterpriseTestCase`) into the PropertyDetailPage inquiry form.

---

## LOW — Frontend Version Pins (Supply Chain)

**File:** `frontend/package.json`

| Package | Version | Note |
|---------|---------|------|
| `typescript` | `^7.0.2` | v7 is stub; requires `@typescript/typescript-win32-x64` |
| `vite` | `^8.3.5` | Current major |
| `@types/react` | `^19.3.0` | Matches React 19 |
| `tailwindcss` | `^4.3.3` | v4 alpha/beta |

**Assessment:** Pins are current latest. No known CVEs. TypeScript 7 stub is a build friction, not a vulnerability.

---

## LOW — WhatsApp Float Unmounted (UX/SEC)

**File:** `frontend/src/components/Widgets/WhatsAppCta.tsx`

### Description
WhatsApp CTA float renders but immediately unmounts on page load (React 18 strict mode double-invoke in dev). No user-visible control.

### Fix
Add `useEffect` mount guard or remove if unused.

---

## Summary of Fixes Required

| Priority | Issue | Effort |
|----------|-------|--------|
| HIGH | Google OAuth no reload / session bootstrap | 15 min |
| HIGH | Token in sessionStorage → httpOnly cookie | 2-4 hr (Sanctum config) |
| MEDIUM | Sanctum idle timeout | 30 min |
| MEDIUM | Session secure cookie default | 5 min (env) |
| MEDIUM | PropertyDetailPage inquiry captcha | 1 hr |
| LOW | WhatsApp float unmounted | 15 min |