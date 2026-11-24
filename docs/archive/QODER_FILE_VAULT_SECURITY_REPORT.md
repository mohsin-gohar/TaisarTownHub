# QODER File Vault Security Report

## Verified source/runtime controls
- Private document storage outside public web root.
- Tenant-scoped document queries.
- Sensitive classification permission gate.
- MIME/signature and malware lifecycle tests included in backend suite.
- Encryption-at-rest tests and file quarantine tests passed.
- 271/271 backend tests passed.

## Not runtime-proven
No Docker executable or ClamAV daemon was available locally. EICAR scanning, real scanner unavailable behavior and live quarantine lifecycle therefore remain infrastructure-blocked, although feature tests simulate and verify code paths.
