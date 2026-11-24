# FINAL DOCKER / CLAMAV RUNTIME REPORT — TaisarTownHub

**Date:** 2026-11-16
**Code commit:** `a8d217e`

---

## 1. Verdict

**BLOCKED — not executed.**

- `docker` is not installed/available on this Windows host: `docker --version` → `The term 'docker' is not recognized...`
- `docker compose` is likewise unavailable.
- WSL has **no installed distribution** (`Windows Subsystem for Linux has no installed distributions`), so `bash -n backend/docker/entrypoint.sh` could not run.
- `clamd --version` and `clamscan --version` → commands not recognized. No real ClamAV daemon or scanner exists on this host.
- Therefore `docker compose build`, `docker compose up`, container health checks, nginx/TLS checks, and real malware-scan rejection were **not executed**.

No Docker or real-ClamAV success is claimed.

---

## 2. What Was Verified Statically / by Tests

| Item | Evidence |
|---|---|
| Compose defines backend, frontend, MySQL, Redis, ClamAV, worker | `docker-compose.yml` reviewed |
| Backend entrypoint honours compose `command:` and `RUN_MIGRATIONS` | `backend/docker/entrypoint.sh` reviewed; shell syntax **not executable here** |
| Production Dockerfiles exist for backend/frontend | `backend/Dockerfile.prod`, `frontend/Dockerfile.prod` |
| ClamAV INSTREAM protocol | `ClamAVScanner.php`: `zINSTREAM\0`, 4-byte big-endian length-prefixed chunks, `pack('N', 0)` terminator, null-terminated reply; file **content**, not path |
| Fail-closed/fail-open/disabled behaviour | `malware.fail_closed` canonical key (mirrored under `malware.clamav.fail_closed`); disabled scanner never reports “clean” |
| Fake-daemon tests | `MalwareScanningTest`: **11 passed / 47 assertions**; `MalwareUploadEnforcementTest`: **5 passed / 12 assertions** |
| Full backend suite | **210 passed / 1,814 assertions** |

Test coverage includes clean, infected, malformed, empty, unreachable, fail-closed, fail-open, disabled, skipped-never-clean, and canonical-config cases.

---

## 3. Command Evidence

```
docker --version
→ docker : The term 'docker' is not recognized as the name of a cmdlet...
docker compose version
→ docker : The term 'docker' is not recognized...
clamd --version
→ clamd : The term 'clamd' is not recognized...
clamscan --version
→ clamscan : The term 'clamscan' is not recognized...
bash -n backend/docker/entrypoint.sh
→ Windows Subsystem for Linux has no installed distributions. (exit 1)
```

---

## 4. What Must Be Done Before Runtime Sign-Off

1. Install Docker Desktop/Engine with a running Linux engine.
2. Run `docker compose build` and `docker compose up -d`.
3. Verify `/api/health` (including DB), queue worker, Redis, nginx, and MySQL init.
4. Run a **real ClamAV** container and upload: (a) clean file → accepted, (b) EICAR/infected file → rejected, (c) scanner stopped with fail-closed → rejected with 422, (d) fail-open → accepted with warning, never “clean”.
5. Verify container network exposure (ClamAV/MySQL/Redis not public), resource limits, restart policies, and logs.
6. Run `bash -n`/`shellcheck` on the entrypoint in a Linux environment.

Until then, the Docker/ClamAV runtime remains **unverified**.
