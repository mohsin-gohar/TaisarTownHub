# Phase 4 File Storage & ClamAV Audit

Branch: `audit/phase-4-production-readiness` @ `3b800ee`

## Storage architecture
- Private vault: `storage/app/private/documents` (encrypted at rest, AES-256-GCM)
- Public disk: `storage/app/public` (maps, media)
- Quarantine: `storage/app/private/quarantine` (rejected uploads, 30-day retention)

## Validation
- MIME type detection (sniffed, not client-declared)
- File extension validation
- File size limits (50MB nginx, Laravel validation)
- Filename sanitization (path traversal prevention)
- Private/public separation verified

## ClamAV
- Integration code: `ClamAVScanner` service, `MALWARE_SCAN_ENABLED`, `MALWARE_SCAN_FAIL_CLOSED`
- Production: fail-closed (uploads rejected if scanner unavailable)
- Tests: 11 MalwareScanningTest passed (fake-clamd)
- Live scanning: NOT VERIFIED (no ClamAV daemon locally)

## Blocked
- Live ClamAV scanning not verified.
- Storage quota enforcement not tested under load.