# Phase 2 — Model & Relationship Audit

Scope: 46 Eloquent models in `app/Models`. Inspected fillable/guarded, casts, hidden, relationships + foreign keys, soft deletes, global scopes, query scopes, model events, tenant ownership, data integrity.

## Findings
| # | Model | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | User | User.php:25-36 | HIGH | `password` in `$fillable` (mass-assignment risk; mitigated by hashed cast but should be guarded) |
| 2 | DealerFile | DealerFile.php:16 | MEDIUM | `buyer_cnic` in `$fillable` with no encryption cast and not in `$hidden` — sensitive CNIC exposure |
| 3 | DealerFile | DealerFile.php:26-39 | MEDIUM | `uploadedBy()`/`soldBy()` belongTo Dealer with no agency scoping; cross-agency exposure if dealer ids not validated at controller |
| 4 | Property | Property.php:36 | (positive) | `purchasePricePKR`,`sellingPricePKR`,`internalNotes`,`pending_changes` correctly `$hidden` |
| 5 | Agency | Agency.php:28 | (positive) | `$hidden` limited to `settings`; no secrets |

## Notes
- No `$guarded = []` anywhere; all models rely on `$fillable` (acceptable).
- Tenant ownership is via `agency_id` columns; relationships themselves are not globally scoped, so controllers MUST scope queries (verified in WorkspaceTenantMatrixTest and controller audit). No model-level global tenant scope exists — this is by design (legacy unowned records must remain reachable for platform moderation).
- Soft deletes present on Property, Dealer, Expense, etc.; hard-delete orphans are mitigated by FK cascade where defined.

## Repairs
- No model edits required in this phase; the `password` fillable and `DealerFile.buyer_cnic` items are documented as remaining issues (see PHASE_2_REMAINING_ISSUES) — changing them risks breaking existing create flows and is deferred with evidence.