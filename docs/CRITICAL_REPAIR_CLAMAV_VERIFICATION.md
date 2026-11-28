# Critical Repair ClamAV Verification

Branch: `fix/critical-security-integrity-repairs` @ `2fb1996`

## Fixes
- fread() failure during file streaming → return null (scan failure)
- fread() failure during reply reading → return null (not partial processing)
- Incomplete stream never reported as clean
- Malformed scanner responses follow fail-closed behavior

## Tests
- MalwareScanningTest: 11 passed (fake-clamd)
- Live scanning: NOT VERIFIED (no daemon)