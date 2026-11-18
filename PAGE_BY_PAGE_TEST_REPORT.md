# Page-by-Page Test Report

Browsered routes at `http://127.0.0.1:3001` (HTTP 200 SPA shell unless noted):

| Route | Observed result |
|---|---|
| `/` | Homepage, hero, agency-first content; rendered |
| `/properties` | Sale listing/filter page; empty state is explicit when API returns 0 rows |
| `/agencies` | Agency directory; API-backed empty/loading states rendered |
| `/agencies/unknown-audit-slug` | Agency-not-found state; no crash |
| `/guide` | Scheme 45 area guide |
| `/gallery` | Video walkthrough page |
| `/services` | Services page |
| `/about` | About page |
| `/contact` | Contact page |
| `/login` | Auth portal with labeled email/password fields |
| `/register` | Agency registration form |
| `/admin-login` | Unauthenticated admin portal flow |
| `/admin` | Protected route resolves to sign-in when logged out |
| `/reset-password?token=invalid&email=qa%40example.test` | Password reset route rendered |
| `/verify-email?token=invalid&email=qa%40example.test` | Verification route rendered |
| `/projects` | Redirects to `/properties?purpose=sale` |
| `/project-detail` | Redirects to `/properties?purpose=sale` |
| unknown route | Dedicated 404 recovery page, `noindex,follow` |

Playwright latest final suite: **63 passed, 4 failed**. The 63 passing cases covered public routes, sale-only navigation, agency directory/profile, security checks, guest protection, and manager/dealer/operator dashboard shells. The 4 failures were:

1. Super Admin demo login.
2. Agency Admin demo login.
3. Login -> Admin Dashboard with the unverified Super Admin fixture.
4. Protected-route access after logout timed out because no verified logout/session fixture completed.

Routes were cold-loaded in browser automation. Authenticated dashboard modules beyond the role-shell and security cases were not exhaustively walked.
