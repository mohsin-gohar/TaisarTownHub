# Phase 2.4 — Laravel Services Review
Date: 2026-11-28
Repository: `mohsin-gohar/TaisarTownHub`
Branch reviewed: `audit/phase-1-file-inventory-20261128`
Reviewed HEAD: `d2a01d65dc4f5904b7ec9d2934bd30a5e05539dd`

## Scope and method
Read all 7/7 tracked PHP files under `backend/app/Services/` at the reviewed HEAD. Cross-checked the service container binding, malware/vault configuration, relevant model scopes, and selected controller/route integration points. This is a static source review; no PHP runtime, database, ClamAV daemon, storage failure-injection, or concurrency tests were run.

## Exact files reviewed
- `backend/app/Services/AgencyOnboarding.php`
- `backend/app/Services/AgencyPromotionService.php`
- `backend/app/Services/ClamAVScanner.php`
- `backend/app/Services/DisabledMalwareScanner.php`
- `backend/app/Services/FileVaultCrypto.php`
- `backend/app/Services/MalwareScanner.php`
- `backend/app/Services/SecureFileStore.php`

Cross-references:
- `backend/app/Providers/AppServiceProvider.php`
- `backend/config/malware.php`
- `backend/config/vault.php`
- `backend/app/Models/AgencyPromotion.php`
- `backend/app/Support/MalwareScanResult.php` (interface/result contract referenced)
- Relevant document/media controller call sites and API routes.

## Findings

### P1 — SecureFileStore can delete the source before confirming the destination write
File: `backend/app/Services/SecureFileStore.php`, method `move()`.
The destination `Storage::disk($toDisk)->put()` result is ignored and the source is deleted unconditionally afterwards. Laravel/Flysystem disks can return `false` for a failed write when exceptions are not configured. A failed destination write can therefore be followed by deletion of the only source copy. The same class also ignores write results in `storeUpload()` and `putBytes()`, allowing callers to proceed as if bytes were persisted when a disk reports failure.
**Required remediation:** check every write result (or configure and consistently rely on disk `throw => true`); only delete the source after a confirmed destination write and preferably verify destination existence/size/checksum. Add fake-disk failure-injection tests covering failed writes and failed deletes.

### P1 — ClamAV stream read failure can produce a verdict for incomplete bytes
File: `backend/app/Services/ClamAVScanner.php`, method `scanViaClamd()`.
If `fread()` returns `false`, the loop breaks and the code still sends the zero-length INSTREAM terminator. The daemon may return `OK` for the truncated stream, which is then treated as a clean result. A non-EOF empty read also loops without a bounded retry. This violates the scanner contract that a file is clean only after its complete content has been scanned.
**Required remediation:** abort the scan and return an unknown/error result on any read failure or premature empty read; track bytes read against the original file size where possible; never send a successful terminator after a read error. Add a stream-read failure test.

### P2 — Featured-placement capacity and uniqueness checks are race-prone
File: `backend/app/Services/AgencyPromotionService.php`, methods `promote()`, `update()`, `assertSlotAvailable()`, and `reorderCurrent()`.
The duplicate-current-placement check occurs before the transaction, while slot capacity is a plain count with no serialization/locking. Concurrent promotions can both pass the count and exceed the configured maximum, and concurrent requests for one agency can create duplicate current placements. Reordering is also not serialized across concurrent mutations.
**Required remediation:** serialize slot mutations through a dedicated lock row/advisory lock or another database-safe reservation strategy; move per-agency uniqueness checks inside the same transaction and enforce a suitable database uniqueness invariant where the status/time model permits it. Add concurrent transaction tests.

### P2 — Vault operations buffer complete files in PHP memory
File: `backend/app/Services/SecureFileStore.php`, methods `storeUpload()`, `move()`, `get()`, `checksum()`, `scanStored()`, and `response()`.
The implementation reads complete objects into PHP strings; the streamed response callback still fetches/decrypts the whole object before echoing. Large files or concurrent downloads can multiply worker memory use and cause resource exhaustion. This also means the implementation is not actually streaming end-to-end despite the response API.
**Required remediation:** define and enforce a documented maximum logical file size and concurrency budget; implement chunked authenticated-encryption framing and streamed storage/response/scanning (or otherwise constrain object sizes). Preserve compatibility for existing envelope/plaintext files.

### P2 — Scanner availability reports configuration, not actual reachability
File: `backend/app/Services/ClamAVScanner.php`, method `isAvailable()`.
When a socket string is configured, `isAvailable()` returns true without connecting to clamd or validating the endpoint. This can mislead health/diagnostic callers. The CLI availability check also shells out to `which`, while actual scan execution has no enforced process timeout despite the configured timeout property.
**Required remediation:** distinguish configured from reachable/ready status; implement bounded health checks and enforce a timeout for `clamscan` as well as clamd socket I/O.

### P2 — Temporary decrypted scan copy write is not verified
File: `backend/app/Services/SecureFileStore.php`, method `scanStored()`.
The return value from `file_put_contents()` is ignored. A failed or partial temporary write can cause the scanner to inspect incomplete bytes; combine this with the clamd read issue above and an incomplete scan could be mistaken for a clean scan.
**Required remediation:** verify exact bytes written before scanning; unlink and return an unknown/skipped result on failure.

### P3 — Vault envelope parser accepts non-strict base64 input without explicit field validation
File: `backend/app/Services/FileVaultCrypto.php`, method `decrypt()`.
The envelope is authenticated with AES-256-GCM, which is a strong positive control, and authentication failure throws. However, IV/tag base64 decoding is non-strict and lengths are not explicitly validated before OpenSSL is called.
**Recommended remediation:** strict-decode IV and tag, require the expected 12-byte IV and 16-byte GCM tag, and reject malformed envelopes with a controlled exception.

## Positive controls
- AES-256-GCM authenticated encryption is used for vault payloads, with a domain-separated key derived from APP_KEY; authentication failures are not silently accepted.
- Legacy plaintext compatibility is explicitly supported.
- Malware scanning distinguishes clean, infected, and skipped/unknown outcomes; disabled scanning never claims clean.
- The clamd adapter uses the INSTREAM protocol and sends file content rather than a host path.
- Malware scanner selection is centralized through a singleton interface binding.
- Agency onboarding creates a real subscription row and explicitly avoids claiming/enforcing a storage quota in the free-launch configuration.
- Featured placement service centralizes eligibility, expiry, ordering, slot bounds, and free-launch payment behavior.

## Verification and limitations
- Static review: 7/7 service files read.
- Related configuration, provider binding, model scopes and integration points cross-checked.
- No source service files changed in this review.
- No PHPUnit, PHPStan/Psalm, runtime storage test, live ClamAV scan, DB concurrency test, or production file-size/load test run.
- CI intentionally not triggered.
- Findings are review findings requiring targeted regression tests before marking remediation complete.
