# CODEX — Diagnostic Scripts and Their Side Effects

These scripts live under `frontend/scripts/`. They are diagnostic harnesses, **not** part of `npm run verify`. Several write real records to the local backend; none target production.

| Script | Side effects | Default API / URL | Cleanup |
|---|---|---|---|
| `qa-audit.mts` | Read-only browser audit; writes screenshots/JSON to `C:\Users\mohsi\AppData\Local\Temp\opencode\qa-artifacts` | `QA_BASE_URL=http://127.0.0.1:4173` | No DB writes |
| `qa-theme.mts` | Read-only theme probe + full-page screenshots in `...\theme-artifacts` | `QA_BASE_URL=http://127.0.0.1:4173` | No DB writes |
| `qa-contact.mts` | **Creates a real inquiry** through the public contact form | `QA_BASE_URL=http://127.0.0.1:4173` | None. Printed `QA_CONTACT_EMAIL=...`; the one probe row from this work was removed manually. Do not run against production. |
| `load-test.mts` | Logs in, creates a probe property and transaction rows, measures public/auth endpoints | `PROBE_API` defaults to `:8090/api` — pass `http://127.0.0.1:8080/api` locally | Best-effort: voids generated transactions and hides the generated property. Partial failures can leave rows. |
| `security-probe.mts` | Registers two real agencies, creates a listing, a document and a lead, then attacks tenancy boundaries | `PROBE_API` defaults to `:8090/api` — pass `http://127.0.0.1:8080/api` locally | Hides the property (`visibility=private`), deletes the lead, soft-deletes the document, suspends both agencies. Auth attempts use TEST-NET-3 IPs so the shared limiter is untouched. |
| `workflows-e2e.mts` | Creates a W2 listing, a buyer probe account and an inquiry | `PROBE_API` defaults to `:8090/api` — pass `http://127.0.0.1:8080/api` locally | Hides generated W2 listing and suspends the buyer probe agency. |

**Rule:** run the mutating scripts only against a disposable local stack, record the generated IDs/emails printed in the output, and verify cleanup. They are intentionally kept in the repository as reproducible evidence harnesses, but they must never be pointed at a hosted environment.
