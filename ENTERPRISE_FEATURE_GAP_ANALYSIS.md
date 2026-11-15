# ENTERPRISE_FEATURE_GAP_ANALYSIS.md — TaisarTownHub

**Date:** 2026-11-14 · Assessment built from the live inventory (`ENTERPRISE_APPLICATION_INVENTORY.md`),
route/controller inspection, and what the browser/role/workflow testing actually proved.
Rule: **no blind feature addition** — every item below is classified as *present*,
*incomplete*, *duplicated* or *genuinely missing*, then prioritized by business value,
security, dependencies and effort.

## 1. Feature status map

| Capability | Status | Evidence |
|---|---|---|
| Auth + MFA (TOTP) + login history + suspicious-login alerts | **Present** | suites green (`mfa-e2e` 10/10) |
| Agency onboarding → platform verification → workspace | **Present** | W1 gate proven 403 → verified → allowed |
| Staff invitations + granular RBAC (13 roles / 62 perms / 362 grants) | **Present** | 51/51 role matrix |
| Properties CRUD + moderation (submit/approve/reject/feature) + plan quotas | **Present** | W2 28/28 |
| Projects + public detail pages | **Present** | crawl + detail probes |
| Dealers + dealer files (sell-once, commission ledger, sales report) | **Present** | W3 |
| Finance: transactions, payments, schedules, commissions, expenses, void/approve | **Present** | W3 + finance suite |
| Document vault: folders, validation, private storage, shares, access logs | **Present** | W4 |
| CRM leads: pipeline, assign, activities, export | **Present** | W1 lead → CRM |
| Favorites (per account) | **Present** | W1 |
| Subscriptions: plans, quotas, invoices, admin assignment | **Present** (no real gateway by design) | N-02 flow |
| In-app notifications + queued email | **Present** | mail suite 6/6 |
| Support tickets/messages | **Present, untested in UI** (dev table empty) | inventory |
| CMS (hero/testimonials/gallery) | **Present** | catalog payload |
| Audit logs + security events + admin dashboards/reports/settings | **Present** | admin surface |
| Legacy `business_records` store | **Incomplete/Duplicated** — overlaps the new per-tenant tables (properties/dealers/payments…) and is not tenant-scoped | K-01 decision pending |
| Malware scanning of uploads | **Genuinely missing** (MIME/ext/size only) | B-05 |
| SMS / WhatsApp notifications | **Genuinely missing** (no provider) | B-06 |
| Real payment gateway | **Genuinely missing** (checkout stubbed at the gateway boundary) | by design |
| Saved searches & alerts | **Missing** | gap |
| Property comparison | **Missing** | gap |
| Map-based discovery | **Partial** — map embed present on public pages; no cluster/search-on-map | catalog `plots` |
| Advanced search (geo, polygon, amenity facets) | **Missing** (keyword+facet filters only) | properties filters |
| Multi-language / localization | **Missing** (English only) | UI copy |
| Monitoring/observability (APM, uptime, error tracking) | **Missing** (logs + health only) | ops gap |
| Backup/DR automation | **Tooling present** (guide + restore drill PASS); scheduled automation **incomplete** | prior session |
| API integrations (portals, marketplaces) | **Missing** | gap |
| Reporting/analytics depth (funnels, staff performance) | **Partial** — admin reports exist; no lead-conversion funnel | admin reports |

## 2. Prioritized backlog

### P0 — security/data integrity blockers (before production)
| # | Item | Why | Effort |
|---|---|---|---|
| 1 | Decide + execute K-01: tenant-scope or isolate legacy `business_records` (tooling ready; needs owner approval + backup) | cross-tenant visibility of legacy rows | M (already half-built) |
| 2 | Approve production rate-limit profile (K-02: 60/min rejected 70 % under 10-user load) | API unusable under real office load | S (config) |
| 3 | Real upload malware scanning: ClamAV via `php-clamav`/`quarantine` adapter, **fail-closed** (scan-unavailable ⇒ reject) with queued fallback | document vault holds CNIC/ownership files | M |

### P1 — high business value
| # | Item | Notes |
|---|---|---|
| 4 | Payment gateway adapter (bank/card) for subscriptions + deal receipts | contracts already stubbed; start with a local PSP (JazzCash/Easypaisa) + webhook verification |
| 5 | Lead pipeline automation: SLA timers, auto-assign rules, follow-up reminders (queue jobs) | CRM base exists; small surface, high value |
| 6 | Saved searches + email alerts (digest via existing queue+mail) | buyer retention |
| 7 | Lead-conversion funnel + staff performance reports | data already in `inquiries`/`transactions` |
| 8 | Global MFA-required policy decision (B-04) + enforcement | security posture |

### P2 — product depth
| # | Item | Notes |
|---|---|---|
| 9 | Property comparison + shortlist share | pure frontend over existing APIs |
| 10 | Map-cluster property search with viewport filtering | plots data exists; needs API viewport filter |
| 11 | Saved-search/auto-alert scheduling | depends on #6 |
| 12 | Support ticket UI hardening + agent assignment (module exists, untested in UI) | test + small fixes |
| 13 | Media pipeline: server-side image variants/thumbnails | storage + response payload sizes |

### P3 — ops / reach
| # | Item | Notes |
|---|---|---|
| 14 | Monitoring: uptime probe, error tracker (Sentry-class), queue-failure alerts | cheap, high ops value |
| 15 | Scheduled offsite backups + restore verification cron | tooling exists; automate |
| 16 | Localization (Urdu/English) + RTL readiness | UI copy pass |
| 17 | Portal/feed integrations (listings syndication) | API-first design already supports it |

## 3. Explicitly rejected for now

| Item | Reason |
|---|---|
| Fake malware "scanner" (heuristic-only) | would create false assurance; rule: implement a real adapter or fail-closed stub (B-05) |
| Rewriting the view-state SPA into react-router | architecture change without demonstrated need (deep links + 404 already handled) |
| Removing the legacy store before the owner decision | data loss risk; migration plan requires approval |

## 4. Verdict

The platform already covers the core multi-agency SaaS loop (onboarding → RBAC → listings →
CRM → deals → documents → subscriptions) and it is **proven working end-to-end in the
browser/API suites**. The remaining gaps are: legacy-data tenancy decision (K-01), rate-limit
profile (K-02), real malware scanning, a payment gateway, and buyer-side conveniences
(saved searches/comparison/maps). Priorities above are sequenced so nothing in P0 blocks
production and P1 items build directly on proven foundations.
