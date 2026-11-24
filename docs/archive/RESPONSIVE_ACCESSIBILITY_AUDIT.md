# Responsive and Accessibility Audit

**Branch:** `fix/complete-audit-20261117` · **Date:** 2026-11-17 · **Status: NOT TESTED in this environment**

## Required matrix (not executed)

Widths 320, 360, 375, 390, 430, 768, 1024, 1280, 1366, 1440, 1920 px — horizontal overflow, clipped controls, mobile drawer, tables/forms on mobile, modal containment, keyboard navigation, focus visibility, labels/accessible names, contrast, reduced motion, WCAG 2.2 AA.

## Why not executed

The in-app browser is connected but its window is not visible in this session; screenshots require a visible tab, and accessibility snapshots returned an empty `main` on some pages in the earlier increment. No Lighthouse binary/API was available. No scores or pass/fail claims are made.

## Evidence that does exist

- Prior increment browser verification used labelled controls and semantic roles (`role="dialog"`, `role="alertdialog"`, `aria-pressed`, `aria-label`) for saved searches, comparison and support flows, and confirmed keyboard-operable buttons and body scroll locking.
- Production build/prerender is green; no new frontend source changes were made in this increment.

## Required follow-up

Run the matrix on a visible desktop browser with Lighthouse and record actual scores; fix any overflow/contrast/focus findings before claiming WCAG 2.2 AA. Until then this section is BLOCKED, not passed.
