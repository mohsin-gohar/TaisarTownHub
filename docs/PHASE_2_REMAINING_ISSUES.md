# Phase 2 — Remaining Issues

Items below are evidence-backed and NOT yet repaired (documented with file:line). None are hidden or marked complete.

## CRITICAL (highest priority follow-ups)
1. **AgencyPromotionService::promote** — duplicate-placement `exists()` check runs outside the `DB::transaction` (AgencyPromotionService.php:126 vs :134). Concurrent requests can create duplicate featured placements. Fix: move the check inside the transaction with `lockForUpdate()`.
2. **SecureFileStore::move** — decrypt→encrypt→delete-source with no transaction/lock (SecureFileStore.php:51-56). Crash leaves duplicate copies. Fix: add locking + reconcile step.

## HIGH
3. **InquiryController::findScopedForAgent / index** — when `agencyId()` is null, no `agency_id` filter (InquiryController.php:321-340, 35-48). A non-platform holder of `crm.update_lead`/`crm.delete_lead` without agency context could act on another agency's inquiry. Fix: require agency context or deny.
4. **ProjectController::index/show** — no auth / no tenant scope (ProjectController.php:14-44). Verify intended public behavior; add scoping if not.
5. **TransactionController::store** — `create_transactions` permission not in RBAC catalogue (TransactionController.php:67). Gate may be vacuous or permanently blocking.
6. **Permissions broad admin grants** — `super_admin`/`admin` resolve to all catalog keys (Permissions.php:199-201); legacy manager/operator receive approve/delete (210-224). Least-privilege review.
7. **AgencyPromotionService::reorder/expireDue** — read without lockForUpdate / get outside transaction (252, 271-276). Lost-update / double-expiry races.
8. **SecureFileStore::scanStored / storeUpload** — plaintext to OS temp (81-86); whole file in memory (33,44,125).
9. **User model** — `password` in `$fillable` (User.php:25-36). Guard it.
10. **DealerFile** — `buyer_cnic` fillable, no encryption/hidden (DealerFile.php:16).

## MEDIUM
11. DealerFilesController::index LIKE wildcard not escaped (DealerFilesController.php:36).
12. FinanceController::storeSchedules loop without transaction (837-890).
13. InquiryController dual-write without transaction (226-315).
14. MediaController/MapController store file before DB row (orphan on failure).
15. PropertyController::feature operator precedence (825).
16. Inconsistent response envelopes (Property/Dealer/Project/AgencyVideo/Finance).
17. FinanceController::index duplicate `transactions`+`data` keys (114-124).
18. WorkspaceController nested row validation gap (402-453).
19. Middleware MFA suffix bypass (EnsureMfaVerified.php:40); `can()` default-allow (AuthorizesApi.php:54); crossAgency broad grants (AgencyContext.php:49).
20. ClamAVScanner failClosed default false (ClamAVScanner.php:38) — production config overrides to true.
21. FileVaultCrypto single-round SHA-256 key derivation (FileVaultCrypto.php:106-115).
22. AgencyPromotionService N+1 updates + 5x count() (96-115,257,277,336).
23. **OpenAPI**: 106 undocumented operations (see PHASE_2_OPENAPI_RECONCILIATION.md).

## LOW
24. AgencyController::current 6x count() (304-311).
25. PropertyController status rule missing `string` (390); features.* item rules missing (281,401).
26. DealerController avatar no max (94).
27. transactionType enum mismatch Transaction vs Finance.
28. SecurityHeaders no HSTS preload; CSP unsafe-inline.
29. MFA TTL hardcoded 24h.
30. FinanceController::destroyPayment unscoped transaction lookup (808).
31. Service logging gaps (SecureFileStore/FileVaultCrypto no success/audit logs).

## External blockers (carried from Phase 1, not code)
- ClamAV, Docker, TLS, SMTP, DNS (`taisartownhub.com`), third-party keys — documented in Phase 1 reports; not resolvable in this environment.