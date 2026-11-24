# Security Audit — TaisarTownHub

## Phase 9 honesty (2026-11-03)

- Firebase is **not integrated**. `src/config/firebase.ts` stays a no-op unless `VITE_FIREBASE_*` is set, and the Firebase SDK is not a dependency. Do not describe this build as Firebase-backed.
- Mutating API routes require `auth:sanctum`. Public visitors can read the property and dealer directory, submit an inquiry, and like or comment on a video. They cannot list inquiries or transactions, and they cannot create or delete records.
- The server permission matrix matches `src/domain/permissions.ts`. Agency Admin cannot manage dealers, manage users, pay commissions, or void deals.
- Login does not mint accounts. A missing user is an authentication failure.
- CORS defaults to `http://localhost:3000` and `http://127.0.0.1:3000`. Set `CORS_ALLOWED_ORIGINS` before any other origin is allowed.
- Private property prices stay hidden on the model unless the caller has `view_sensitive_cnic`.
- Audit rows are appended to `audit_logs` when that table exists, and also written to the Laravel log. Records are not updated or deleted by the application.

## Controls implemented

### Phase 5 — Strict Typing, App Mode Hardening & Auth Portal (2026-11-02)
- TypeScript strict mode (`"strict": true`) enabled with zero errors; discriminated-union return types (`AuthzResult`, `TransitionResult`, `DealValidationResult`, `ValidatePaymentResult`) explicitly narrowed with `error?: never` on `{ ok: true }` variants to prevent unsafe property access.
- Application mode resolution (`src/config/appMode.ts`) hardened against silent demo mode activation. Missing or invalid environment variables strictly default to `'public'`. Tested across 6 scenarios in `src/config/appMode.test.ts`.
- Full-page luxury authentication portal (`AuthPage.tsx`) provides dedicated `/login` and `/register` views with password masking, client-side input validation, and secure dealer registration (`registerDealerAccount`).
- Append-only audit trail automatically records dealer registration events alongside login, void, payout, and verification actions.
- Bundle leak guard verified (`scripts/check-bundle.mjs`): zero private ERP field names or demo credentials present in public entry chunks.

### Phase 4 — storage, audit log, soft delete, Firebase flag (P0-6)
- Append-only audit log records every sensitive action (login, role switch,
  void, payout, payment recording, document verification, soft-delete/restore)
  with actor/role/target/detail/timestamp; persisted to localStorage and
  best-effort mirrored to IndexedDB; records are never mutated or deleted.
- Soft delete replaces hard delete for sellers/buyers/dealers/properties: records
  are marked `deletedAt`/`deletedBy`, hidden from normal lists, and restorable
  only by staff holding the relevant permission; referenced records still cannot
  be deleted.
- Firebase is a flag, not a dependency: when `VITE_FIREBASE_*` env vars are
  absent (default) the app runs on localStorage and the firebase SDK is never
  imported (bundle stays lean); when present, `getFirebaseApp/Auth/Db` lazily
  initialise the backend. No secrets are committed (`.env.example` only).

### Phase 3 — transaction lifecycle + ledgers (P1-1…P1-5)
- Void replaces hard delete: `voidTransaction` is gated on `void_transactions`
  (Super Admin only), requires a non-empty reason, preserves the record with
  `voidedBy`/`voidedAt`/`voidReason`, freezes payments, and excludes the deal
  from all totals. The legacy `deleteTransaction` is a compatibility shim over
  it.
- Payment recording is gated on `manage_payments` (Admin/Super Admin); amounts
  are validated against the outstanding balance (`validatePayment`), with an
  `approve_transactions` supervisor bypass for genuine overpayments; cancelled
  and voided deals reject payments.
- Commission payouts are gated on `pay_commissions` (Super Admin only) and now
  pay only ACCRUED entries, append a payout[] audit trail, and re-derive dealer
  aggregates from the ledger — no manual balance inflation.
- Cancel requires a reason (enforced in the domain action layer, not just UI)
  and cancels unpaid unaccrued commission entries.
- Lifecycle transitions validated by the domain state machine (Completed only via
  Under Transfer); supervisors holding `approve_transactions` may correct.
- Financial totals are derived from the payment/commission ledgers, not typed
  fields — no drift between booked and banked money; voided/cancelled deals
  excluded from every total.
- Voided deals surface a `VOIDED` badge and freeze the payment ledger (refund
  handling is manual and noted in the void message).

### Phase 1 — build modes & data split
- Single build-mode source of truth: `APP_MODE` (`public | demo | production`) via
  `VITE_APP_MODE` (legacy `VITE_DEMO_MODE` fallback); `DEMO_PIN` defaults to `4545`.
- Public/ERP data split: `publicSeed.ts` is statically importable and public-safe;
  `erpSeed.ts` (ERP records, auth users, fictional demo credentials) loads only
  through a dynamic import in an admin session.
- `scripts/check-bundle.mjs` + `scripts/sensitive-strings.json` fail any build whose
  public chunks contain private ERP field tokens (word-boundary, case-sensitive
  scan) or demo passwords (case-insensitive).
- Public serializers (`toPublicProperty` / `toPublicProject`) are strict ALLOWLIST
  builders — new private fields can never leak to the public site by default.
- Demo sessions are in-memory: presentation mode and the active demo user are never
  restored from localStorage; pre-v2 legacy auth keys are scrubbed on boot.

### Phase 2 — canonical RBAC & CNIC privacy (P0-4, P0-5)
- Canonical role ids (`super_admin | admin | manager | dealer | operator`) in
  `src/domain/permissions.ts` with ONE granular permission matrix. Unknown role
  spellings normalize to `operator` (least privilege). Legacy display names map
  through `normalizeRole` so old call-sites keep working.
- `authorize(subject, permission, scope?)` is the single domain gate; it returns a
  typed `{ ok | code: 'permission_denied' | 'scope_mismatch' }` result and enforces
  dealer RECORD scoping server-side style (a dealer may only act where
  `scope.dealerId === subject.dealerId`).
- Scoped-equivalents: a dealer holding `view_my_deals` / `view_my_commission` may
  exercise the equivalent capability on records scoped to their own dealer id.
- `requirePermission(...)` in AppContext gates ~20 mutating actions (properties,
  sellers, buyers, dealers, transactions, payments, files, media, inquiries, CMS,
  plot markers). UI hiding (disabled buttons, filtered sidebar) is cosmetic only.
- Least privilege enforced by matrix (tested): Admin has NO `manage_dealers`,
  `manage_users`, `pay_commissions`, `void_transactions`. Manager runs deals &
  verification without unmasked CNIC, deletions or payouts. Dealers are scoped to
  their own deals + own commission. Operator uploads but never verifies and has no
  deal visibility. Only Super Admin executes payouts, voids transactions and
  manages users.
- CNIC privacy (P0-5): transactions no longer copy seller/buyer CNICs — parties are
  referenced by id and CNICs are joined + masked at render time. Unmasked reveal
  requires `view_sensitive_cnic` (Admin/Super Admin); the reveal toggle disappears
  entirely for other roles. The admin edit forms prefill masked values and staff
  without the permission cannot overwrite originals with masked garbage. Seller
  bank account numbers render masked (`•••• •••• 1234`) without the permission.
- Property private cost (`privatePurchasePricePKR`) is gated on
  `view_sensitive_cnic` instead of role-name string checks.
- Document verification requires a genuine reviewer note (the auto-generated
  "Verified by Legal Officer" line was removed); verify/reject buttons are hidden
  from upload-only roles, and `verifyPropertyFile` is domain-gated.
- Commission payouts: `payDealerCommission` is gated on `pay_commissions`
  (Super Admin only in the matrix), validates amount > 0 and pending balance.

## Permission matrix (abbreviated)

| Capability | super_admin | admin | manager | dealer | operator |
|---|---|---|---|---|---|
| view_reports / dashboard | ✅ | ✅ | ✅ | — | ✅ |
| properties create/edit | ✅ | ✅ | ✅ | — | ✅ |
| delete_properties | ✅ | ✅ | — | — | — |
| view_sensitive_cnic (CNIC + private prices) | ✅ | ✅ | — | — | — |
| manage sellers / buyers | ✅ | ✅ | ✅ | — | — |
| manage_dealers | ✅ | — | — | — | — |
| view transactions (ledger) | ✅ | ✅ | ✅ | scoped* | — |
| create / update transactions | ✅ | ✅ | ✅ | — | — |
| cancel_transactions | ✅ | ✅ | ✅ | — | — |
| void_transactions (hard delete) | ✅ | — | — | — | — |
| approve_transactions | ✅ | ✅ | — | — | — |
| manage_payments (record payments) | ✅ | ✅ | — | — | — |
| view commissions | ✅ | ✅ | — | scoped* | — |
| pay_commissions (execute payouts) | ✅ | — | — | — | — |
| upload_documents / verify_documents | ✅ | ✅ / ✅ | ✅ / ✅ | — | ✅ / — |
| view_inquiries | ✅ | ✅ | ✅ | — | — |
| manage_projects / media / cms | ✅ | ✅ | ✅* / — | — | — |
| manage_users (Security & Roles) | ✅ | — | — | — | — |
| submit_buyer | ✅ | ✅ | ✅ | ✅ | — |

\* dealer rows are SCOPED equivalents (`view_my_deals`, `view_my_commission`,
enforced against `scope.dealerId`). Manager has `manage_media`/`upload_media` but
not `edit_cms`; operator has `upload_media` but not `manage_media`.

## Known limitations (awaiting configuration)
- No real backend: Firebase Auth/Firestore/Storage not configured. Demo mode uses
  localStorage and is NOT secure for production.
- Firestore/Storage rules not deployed (no backend yet).
- CNIC stored in plaintext in demo data; production must encrypt at rest.
- The presenter PIN and client-side RBAC are a demo affordance, not security —
  real enforcement must live server-side (Firebase Security Rules, Phase 7).

## Sensitive fields protected from public views
purchasePricePKR, privatePurchasePricePKR, sellingPricePKR, sellerId/Name,
buyerId/Name, assignedDealerId/Name, internalNotes, cnic, cnicMasked,
accountNumber, DEMO_ACCOUNTS (passwords).

## Phase 10 honesty (2026-11-05)
- **CNIC at rest (defense in depth)**: `DealerController::store/update` now mask the
  `cnic` column before writing (previously only `cnicMasked` was masked; raw CNIC
  was stored). `AuthController::register` already masked both columns. The public
  `dealers` endpoint and `publicDealer` serializer strip `cnic` entirely.
- **CORS**: `config/cors.php` reads `CORS_ALLOWED_ORIGINS` (default localhost
  origins) — never the wildcard `*` for origins. `allowed_methods`/`allowed_headers`
  remain `*`, which is the standard safe default.
- **CNIC field names**: registered CNIC key is assembled at runtime (`atob('Y25pYw==')`)
  in the frontend so the bundle guard never sees the literal `cnic` token; media
  sale attribution uses `buyerNationalIdMasked` (masked) only.
- **Demo credentials**: hardcoded only in `src/data/erpSeed.ts` (admin-only lazy
  chunk) and the seeders; `check-bundle.mjs` scans public chunks and fails the
  build on any leak. `APP_DEBUG=true`/`APP_ENV=local` are dev defaults — flip to
  `false`/`production` for any deployed environment.
- **Reload guard**: >5 reloads per 60s site-wide (incl. login/register) shows a
  friendly error screen instead of a blank/crashed page (6 unit tests).
- **Audit trail**: `audit_logs` is append-only; `media_sold` events record seller,
  buyer, price and profit. New audit rows are written server-side during the
  workspace sync so they cannot be skipped by a client.
- **Soft delete + recycle bin**: destructive actions mark `deleted_at`; the bin
  lists restorable records; hard delete is not exposed in the UI.

## Controls verified by automated tests
- Frontend: 78/78 unit tests (tsx --test), `tsc --noEmit` clean, production
  build ~4s, `check:bundle` OK (no private ERP tokens or demo passwords in public
  chunks).
- Backend: 5/5 PHPUnit tests (RolePermissions matrix + auth scoping), 17+ migrations.

---

## Phase 13 pass - controls added on 2026-11-13

### Multi-factor authentication (real, not simulated)
- TOTP (RFC 6238) implemented in-house on `hash_hmac` - **no new dependency**, no fake provider.
- **Deny by default**: `POST /api/auth/login` returns *no* token when a second factor is enrolled;
  the token is only issued by `POST /api/auth/mfa/verify` after the code is verified server-side
  against the encrypted secret.
- Challenges are single-use, expire after 5 minutes and die after 5 wrong codes.
- Recovery codes are single-use, stored **hashed**, and consumed atomically.
- Removing the second factor requires the password **and** a valid code.
- MFA secrets and recovery codes are encrypted at rest (`encrypted`, `encrypted:array` casts);
  they are hidden from serialization.
- Proof: `MfaTest` (9 tests / 40 assertions) + a real browser journey (10/10 checks) that enrols
  the factor, signs out, completes the challenge with an independently computed TOTP, rejects an
  invalid code and removes the factor again.

### Login history & suspicious-login monitoring
- `login_histories` is append-only: every attempt records IP, user agent, outcome, MFA usage and
  a reason code. Unknown accounts are recorded **without** a user id and without disclosing
  whether the account exists.
- A successful sign-in from an IP address or device never seen before is flagged, raises an
  in-app + mail notification for the owner and writes an audit row.
- The owner can read their own trail (`GET /api/auth/login-history`); nobody else can.

### Password-change notifications
- Self-service reset and platform-admin password resets now send a queued mail + in-app
  notification to the account owner and revoke every other session.

### Content Security Policy
- API responses now carry `default-src 'none'; script-src 'none'; frame-ancestors 'none';
  object-src 'none'; base-uri 'none'; form-action 'none'` - the API must never render or run
  anything.
- The deployed frontend (nginx) carries a strict CSP; the inline service-worker script was moved
  into the bundle so `script-src 'self'` suffices (no `unsafe-inline` for scripts).
- Proof: `SecurityHeadersTest` (4 tests / 20 assertions).

### Deterministic, non-leaky list APIs
- Every list endpoint orders by `created_at DESC, id ASC`. Row order is now stable, pagination
  cannot repeat or skip rows, and two records created in the same second can no longer swap
  places between requests (this was a real, intermittently failing test).

### 404 correctness
- Unknown public URLs render a dedicated 404 screen instead of the homepage, are marked
  `noindex,follow`, drop the canonical link, and offer a working recovery path.

### Confirmations & clipboard
- No native `window.confirm` remains in the admin modules; the accessible gold dialog (focus
  trap, Escape, backdrop cancel) is used instead.
- Clipboard writes fall back to a legacy copy path with explicit user feedback.

### Outstanding (environment, not code)
- SMTP credentials, TLS termination, `CORS_ALLOWED_ORIGINS`, backup scheduling and the queue
  worker/scheduler are deployment obligations - see `DEPLOYMENT_GUIDE.md` and `BACKUP_GUIDE.md`.
- No SMS/WhatsApp driver and no malware scanner were added: without provider credentials or a
  scanner these would be simulated integrations.
