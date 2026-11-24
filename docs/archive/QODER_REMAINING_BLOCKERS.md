# QODER Remaining Blockers

| Item | Status | Required action |
|---|---|---|
| Docker/Compose runtime | BLOCKED | Run on a Docker host and execute `docker compose up -d --build` |
| Redis runtime | BLOCKED | Provision Redis with TLS/password/persistence and test cache/session/queue |
| MySQL 8 runtime | BLOCKED locally | Run disposable MySQL migrations and concurrency suite |
| ClamAV runtime | BLOCKED | Run clean/EICAR/unavailable/quarantine tests in Docker |
| Nginx multi-instance | BLOCKED | Start two backend instances and verify sticky-stateless behavior |
| Load testing | BLOCKED | Run 10/50/100/250 virtual-user suites |
| Lighthouse/CWV | BLOCKED | Run deployed production-like Lighthouse and field-CWV data |
| Authenticated role workflows | INCOMPLETE | Provision approved test accounts and data for every role |
| Legacy record ownership | DECISION REQUIRED | Assign or platform-mark unresolved legacy tenant records |
| Backup restore | BLOCKED | Execute real encrypted backup/restore in production-like infrastructure |

No 100% or production-ready claim is made.
