# PHASE 7.3 — CRM, Leads and Inquiries Audit

**Date:** 2026-11-28  
**Branch:** `audit/phase-7-agency-erp-workflows` @ `1a899db`

---

## Scope

Public inquiry creation; agency lead creation; lead list/detail; assignment/reassignment; status lifecycle; follow-ups/activities; inquiry→lead relationships; search/filter/pagination; CSV export + permission validation; staff-specific visibility; notifications; cross-agency isolation; dual-write/partial persistence.

## Verification evidence

| Check | Result |
|-------|--------|
| `CrmLeadsTest` | 33 PASS / **2 FAIL** (CSV export scope, formula injection) |
| `InquiryCaptchaTest` | **PASS** ✅ |
| `FavoritesNotificationsTest` | **PASS** ✅ |

## Findings

- Lead/CRM tenant scoping and assignment behaviours pass.
- Two genuine failures in `CrmLeadsTest` CSV export assertions.

## Status

**PARTIALLY GREEN**.

## Notes / overlaps

- `InquiryController.php` (OpenCode) treated read-only; no edits made.
