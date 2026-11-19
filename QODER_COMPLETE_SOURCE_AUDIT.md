# QODER Complete Source Audit

## Identity
- Branch: `main`
- HEAD: `a53e3c456cf7fe4e14d7ca73690bb5c9300557f3`
- Working tree: modifications remain; no reset, commit or push performed.
- Tracked files: 582

## Verification summary
- Read-only source inventory completed.
- Critical security review completed.
- PHP syntax checks passed for repaired controllers.
- Backend suite: 271 passed, 2152 assertions.
- Frontend verification: TypeScript passed; 133 unit tests passed; production build/prerender/bundle check passed.
- Live public browser subset: 17/18 in parallel run; navbar timeout passed when rerun alone. Public routes remained responsive.
- Docker and Redis runtimes are unavailable on this host.

## Repairs applied
1. Public property queries now force `listing_purpose=sale`.
2. Property creation/update reject `rent`.
3. Public property detail/listing payloads now use an explicit field allowlist.
4. Public catalog no longer publishes Projects or raw private media payloads.
5. Legacy catalog rent properties are excluded.
6. Sensitive document category downgrade now requires `documents.view_sensitive`.
7. Docker MySQL/Redis ports are internal and password fallbacks removed.
8. Agency detail access requires `properties.view` for agency-owned private records.

## Source areas not runtime-executed
- Live MySQL 8, Redis 7, ClamAV, multi-instance Nginx, Lighthouse and distributed load tests require infrastructure unavailable in this environment.
