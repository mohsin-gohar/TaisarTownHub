# Security Audit — TaisarTownHub

## Controls implemented

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
