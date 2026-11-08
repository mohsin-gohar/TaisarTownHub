# TaisarTownHub — Service Layer & Business Logic (Pillar 6)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. Design Principle

Business logic is **decoupled from controllers** into dedicated Service / Action classes with strict return types. Controllers remain thin: validate → call service → return JSON.

## 2. Service Inventory

| Service | Location | Responsibility |
|---|---|---|
| `WorkspaceService` | (in WorkspaceController + helpers) | Collection sync, soft-delete, media-sale audit |
| `MediaSaleService` | `auditNewMediaSales()` | Records newly-sold media assets with buyer attribution + profit |
| `CommissionService` | (in TransactionController) | Calculates dealer commission from type/value |
| `PermissionService` | `domain/permissions.ts` (FE) + `RolePermissions.php` (BE) | RBAC gate |
| `AuditService` | `AuthorizesApi::writeAudit()` | Append-only audit log writer |
| `CatalogService` | `WorkspaceController::catalog()` | Public-safe payload mapping (PII stripping) |

## 3. Key Business Rules

### Commission calculation
```php
function calculateCommission(float $dealAmount, string $type, float $value): float {
    return $type === 'percentage' ? $dealAmount * ($value / 100) : $value;
}
```
- No dealer selected → 0 commission (never phantom amounts).
- Commission status: Pending → Paid (manual payout).

### Media sale attribution (anti-fraud)
```php
// Before sync, for each media row with soldAt:
if ($existing && empty($existing->payload['soldAt'])) {
    AuditLog::create([
        'action' => 'media_sold',
        'target' => $row['id'],
        'detail' => "\"{$title}\" sold for PKR {$price} (profit PKR {$profit}) to {$buyer} by {$seller}",
    ]);
}
```
- Append-only; never deleted.
- Profit = soldPrice - cost (default 30% margin if cost blank).

### Soft-delete sync
```php
private function syncList(string $collection, array $rows): void {
    $ids = array_column($rows, 'id');
    foreach ($rows as $row) {
        if ($collection === 'videos') $row = $this->mergeVideo($row); // merge likes/views/comments
        BusinessRecord::put($collection, $row['id'], $row);
    }
    if (count($ids) > 0) {
        BusinessRecord::where('collection', $collection)
            ->whereNotIn('record_id', $ids)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
    }
}
```

### Video like merge
```php
$row['likes'] = max($old['likes'] ?? 0, $row['likes'] ?? 0); // never decrement
// comments merged by id (dedup)
```

## 4. Return Type Discipline

Service methods return typed results:
- `addProperty(...) : { success: bool, error?: string }`
- `addAgencyVideo(...) : Promise<{ ok: bool }>`
- `requirePermission(...) : bool` (shows toast on failure)

## 5. Transaction Safety

- Multi-step writes (deal + payments) wrapped in DB transactions where atomicity required.
- Audit writes are best-effort (try/catch) so a failed audit never blocks the business action.
- Workspace sync is idempotent via `updateOrCreate`.

## 6. Frontend Service Layer

`src/services/api.ts` — typed fetch wrapper:
- `apiFetch(path, options)` injects Bearer token + Content-Type.
- All methods return `null` on failure (never throw to UI) — UI decides toast.
- `getAuthToken()` / `setAuthToken()` use sessionStorage (not localStorage) for XSS resilience.
- `API_BASE_URL` from `VITE_API_URL` env (default `http://127.0.0.1:8080/api`).

## 7. Separation of Concerns

- **Controllers:** HTTP in/out, auth, validation delegation.
- **Models:** relationships, casts, scopes, accessors.
- **Services/Actions:** business rules, calculations, audit.
- **Frontend context:** state + CRUD actions + toasts; components are presentational.
- **Domain (`src/domain/permissions.ts`):** single canonical RBAC matrix — never duplicated.
