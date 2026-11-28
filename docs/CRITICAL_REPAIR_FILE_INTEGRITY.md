# Critical Repair File Integrity

Branch: `fix/critical-security-integrity-repairs` @ `2fb1996`

## SecureFileStore fixes
1. storeUpload: check file read result, check Storage::put() result, verify file exists
2. putBytes: check Storage::put() result, verify file exists
3. move: check source exists, check destination write, verify destination exists, only delete source after verification
4. scanStored: check file_put_contents result, skip on failure

## ClamAVScanner fixes
1. fread() failure during file read → return null (scan failure, not break)
2. fread() failure during reply read → return null (not break + partial processing)

## Principle: never delete source before destination verified. Never accept incomplete stream as clean.