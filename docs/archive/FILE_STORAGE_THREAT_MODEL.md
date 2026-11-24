# File Storage Threat Model

**Asset:** agency-owner private files — CNIC scans, registry papers, sale agreements, transfer/allotment letters, payment proofs, media.
**System:** TaisarTownHub vault (`documents` + `quarantine` private disks, `DocumentController`/`MediaController`/`DealerFilesController`, MySQL metadata, React admin UI).
**Trust boundaries:** browser ↔ API (Sanctum + MFA), public internet ↔ public endpoints (public media, share tokens), app ↔ disk, app ↔ clamd sidecar, agency ↔ agency (tenancy), platform staff ↔ agencies.

STRIDE-style listing. "Status" reflects commit `8c56b76`.

---

## S — Spoofing / authentication

| Threat | Vector | Status / Control |
|---|---|---|
| Stolen token downloads CNIC scans | Bearer token replay | Sanctum tokens (24h expiry, pruned daily) + MFA middleware on all vault routes; every download is permission-gated and audit-logged (who + ip + UA) |
| Anonymous probing of vault | `/api/agency/documents*` without auth | 401 (verified in `StorageSecurityTest`); no unauthenticated route touches private bytes except the two below |
| Share-token brute force | `GET /api/shares/{token}/download` | Tokens are `Str::random(48)` (≈285 bits), DB stores only SHA-256 hash, rate-limited 30/min, expiry + download cap + revocation |

## T — Tampering

| Threat | Vector | Status / Control |
|---|---|---|
| Executable disguised as document | `shell.php.pdf`, `MZ`-headed `.jpg` | Extension allowlist + sniffed-MIME blocklist + magic bytes at offset 0 (V-1, V-2, V-3 fixed); malware scan on every byte upload incl. versions and media |
| Infected file stored and served | upload with virus | Real-scanner verdict required for "clean"; infected → rejected + isolated on quarantine disk; fail-open hold stays undownloadable (423) until rescan (V-7) |
| Version replacement as bypass | hostile "v2" upload | Versions run the identical pipeline; rejected version leaves the pointer/bytes untouched (V-2) |
| Byte corruption undetected | disk failure / tampering | SHA-256 checksum of plaintext stored per document/version; verified at rescan; DB metadata hidden from API |
| Metadata tampering via mass assignment | crafted PATCH | `$fillable` allowlists; tenancy fields ignored (`WorkspaceHardeningTest` pattern); versions endpoint only changes bytes through the pipeline |

## R — Repudiation

| Threat | Vector | Status / Control |
|---|---|---|
| "I never downloaded those files" | denied access disputes | Every view/preview/download/upload/replace/delete writes `audit_logs` + `document_access_logs` (ip, UA, timestamp, actor); denied attempts now audited too (V-6) |
| Administrator quietly deletes evidence | permanent delete | `permanent_delete` audited; soft-delete retains bytes for audit; quarantine purges audited as `document.quarantine_purged`; logs are agency-scoped read-only via API |
| Log falsification | app-level | Logs contain no file contents, no raw share tokens (hash-only), no disk paths; DB-level hardening (append-only storage) listed in Remaining Risks |

## I — Information disclosure

| Threat | Vector | Status / Control |
|---|---|---|
| Cross-agency file access (IDOR) | guessed doc ids | All queries agency-scoped; foreign ids → 404 (no existence oracle); pinned by dedicated tests |
| Bytes readable on disk | host compromise / backup theft | AES-256-GCM at rest (V-8), key = APP_KEY-derived subkey not stored beside files; legacy plaintext still readable → run `vault:encrypt` (documented; **must** be executed post-deploy for pre-encryption files) |
| Public URL of a private file | symlink / asset() leak | Vault disks `serve=false`, never in `filesystems.links`; no `asset('storage/…')`/`Storage::url()` anywhere; only public-visibility media and hash-gated tokens leave without auth |
| Physical path disclosure | API responses | `disk_path`/`stored_name`/`checksum`/`original_name` in `$hidden` for every model; quarantine endpoint exposes names/sizes only |
| Content-Type confusion / XSS via file | inline preview / public media | Served type is server-sniffed (not client-declared), `nosniff` globally, CSP sandbox, CORP for public media, safe Content-Disposition (V-3, V-10) |
| Open redirect / SSRF via external URLs | crafted `url` fields | HTTPS + public-host + no-credential validation at registration and re-checked at every delivery; legacy bad rows → 410 (V-5) |
| Public chunk leaks private data | frontend bundle | `check:bundle` guard: no private ERP data / demo credentials in public chunks (green) |

## D — Denial of service

| Threat | Vector | Status / Control |
|---|---|---|
| Upload flood | scripted POSTs | Dedicated 20/min upload throttle + 60/min global limiter + per-plan storage quota (402) (V-9, V-4) |
| Quota escape via trash/versions/media | rotate delete/version | Accounting now covers trashed docs, archived versions and media bytes (V-4) |
| Anonymous media hammering | `GET /api/media/{id}/file` | 300/min per IP; streams only public-visibility, scanned-clean rows |
| Quarantine disk fill | repeated failed uploads | 30-day retention purge (scheduled daily) + rejected bytes never enter the vault |
| Share download abuse | hot-linking | 30/min throttle, download caps, expiry, revocation |

## E — Elevation of privilege

| Threat | Vector | Status / Control |
|---|---|---|
| Downloader mints public share link | privilege ladder | Minting/revoke requires `documents.manage_permissions` (`DocumentSharesTest`); sensitive docs additionally require clearance; scan gate prevents sharing unverified bytes (V-7) |
| Dealer identity spoofing | `X-Agency-Id` header | Dealer identity resolved strictly from the authenticated account; spoof → 403 (`DealerFilesAuthorizationTest`) |
| Platform-role abuse inside an agency | cross-agency header | `AgencyContext` only trusts own memberships; capability parity pinned by `CrossAgencyCapabilitiesTest` |
| PHP code execution via uploaded file | web-served upload | Vault bytes are never web-served (no symlink, `serve=false`), stored with random server-side names, content gates reject script payloads, headers `nosniff` |

---

## Residual risks (accepted / blocked)

1. **Production ClamAV not yet exercised** (infrastructure blocked) — fail-closed config keeps the system safe without it; ON by default in `.env.example`.
2. **Key custody** = APP_KEY from environment/secret store; no external KMS/HSM — rotation tooling + verified tests provided.
3. **Backups RPO/RTO documented, not measured** — real restore drill pending infrastructure.
4. **Legacy client-registered URLs** predating the HTTPS guard fail closed at delivery; optional data backfill recommended.
5. **Log storage is DB-based, not WORM** — treat `audit_logs`/`document_access_logs` backups as part of the retention plan.
