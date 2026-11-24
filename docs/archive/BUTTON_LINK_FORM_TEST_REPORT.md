# Button, Link and Form Test Report

## Browser-tested interactions
- Public navigation to Properties and Agencies.
- Login and registration route/forms render with accessible account-email/password inputs.
- Hero property search carries selected sector criteria to `/properties` (regression test passes).
- Legacy Projects navigation URLs redirect to sale listings.
- 404 recovery links render and navigate.
- Sale-only public navigation contains no Rent or Projects links.
- Contact page controls and registration controls are present in E2E checks.
- Public routes, authorization boundaries, API health/catalog and share-token negative path are included in the 67-test Playwright suite.

## Findings fixed
- Hero search was previously wired to a no-op; filter criteria are now passed into the listing page.
- Public Properties controls no longer expose a rent/all-purpose mode.
- Contact details on the home section now come from CMS values and use `tel:`/`mailto:` links.

## Coverage limits
The suite does not click every footer/social link, every dashboard modal/table action, or submit real registration/contact transactions. External messages and transactions were not sent. Such workflows require controlled accounts/data and remain outside the verified click set.
