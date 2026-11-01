# Security Audit — TaisarTownHub

## Controls implemented
- No empty-password or global-password login.
- Per-account demo credentials only; no password stored in browser storage.
- Auth flags not trusted from localStorage; session starts unauthenticated.
- Role switching restricted to DEMO MODE (presentation mode or VITE_DEMO_MODE).
- Centralised RBAC permission map (ROLE_PERMISSIONS) + hasPermission/userHasPermission.
- CNIC masking utility (maskCNIC).
- Public property serializer (toPublicProperty) strips private ERP fields.
- Delete guards prevent removing sellers/buyers/dealers referenced by transactions.
- Commission payout validates amount, prevents overpayment and duplicate payouts, requires pay_commissions/manage_payments permission.

## Known limitations (awaiting configuration)
- No real backend: Firebase Auth/Firestore/Storage not configured. Demo mode uses localStorage and is NOT secure for production.
- Firestore/Storage rules not deployed (no backend yet).
- CNIC stored in plaintext in demo data; production must encrypt at rest.

## Sensitive fields protected from public views
purchasePricePKR, privatePurchasePricePKR, sellingPricePKR, sellerId/Name, buyerId/Name, assignedDealerId/Name, internalNotes.
