# TaisarTownHub — Full CRUD Controllers & API Endpoints (Pillar 9)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. API Base

`{APP_URL}/api` (default `http://127.0.0.1:8080/api`). All responses JSON. Pagination via `?page=` where applicable.

## 2. Endpoint Catalog

### Health
| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/health` | no | `{ status, application, database }` |

### Auth
| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/auth/login` | no (throttle 5/min) | `{ email, password }` → `{ token, user }` |
| POST | `/auth/register` | no (throttle 3/min) | |
| GET | `/auth/me` | sanctum | current user |
| POST | `/auth/logout` | sanctum | revoke token |

### Public
| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/catalog` | no | projects, properties, dealers (masked), videos, plots, media, testimonials, cms |
| POST | `/subscribers` | no (throttle 10/min) | newsletter subscribe |
| POST | `/inquiries` | no (throttle 10/min) | public inquiry submit |
| GET | `/properties` | no | list (filterable) |
| GET | `/properties/{id}` | no | detail |
| GET | `/dealers` | no | list |
| GET | `/dealers/{id}` | no | detail |
| GET | `/videos` | no | list (phase filter) |
| GET | `/videos/{id}` | no | detail + views++ |
| POST | `/videos/{id}/like` | no (throttle 30/min) | append-only like |
| POST | `/videos/{id}/comments` | no (throttle 10/min) | add comment |
| GET | `/projects` | no | list |
| GET | `/projects/{id}` | no | detail |

### Authenticated (sanctum)
| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/workspace` | view_properties | all collections |
| PUT | `/workspace` | per-collection | sync collections |
| POST | `/properties` | create_properties | create |
| PUT/PATCH | `/properties/{id}` | edit_properties | update |
| DELETE | `/properties/{id}` | delete_properties | soft delete |
| POST | `/dealers` | manage_dealers | create |
| PUT/PATCH | `/dealers/{id}` | manage_dealers | update |
| DELETE | `/dealers/{id}` | manage_dealers | soft delete |
| GET | `/dealer-files` | upload_documents/verify_documents | list |
| POST | `/dealer-files` | upload_documents | upload |
| POST | `/dealer-files/{id}/sell` | verify_documents | mark sold |
| GET | `/dealers/{dealerId}/sales-report` | pay_commissions | per-dealer report |
| GET | `/sales-report` | pay_commissions | all report |
| POST | `/videos` | upload_media/manage_media | publish video |
| GET | `/inquiries` | view_inquiries | list |
| PATCH | `/inquiries/{id}/status` | view_inquiries | update status |
| GET | `/transactions` | view_transactions/view_my_deals | list (scoped for dealers) |
| POST | `/transactions` | create_transactions | create deal |

## 3. Response Conventions

- Success: `200 OK` with JSON body (resource or `{ success: true }`).
- Created: `201 Created`.
- Validation error: `422 Unprocessable Entity` with `{ message, errors: { field: [reasons] } }`.
- Unauthorized: `401` (no/invalid token).
- Forbidden: `403` (permission denied).
- Not found: `404`.
- Rate limited: `429`.

## 4. Controller Pattern

```php
class PropertyController extends Controller {
    use AuthorizesApi;
    public function index(Request $r) {
        $this->requireAny($r, ['view_properties']);
        return Property::whereNull('deleted_at')->paginate(20);
    }
    public function store(Request $r) {
        $this->requireAny($r, ['create_properties']);
        $data = $r->validate([...]);
        $p = Property::create($data);
        $this->writeAudit($r, 'create_property', $p->id, $p->title);
        return response()->json($p, 201);
    }
    // update, destroy (soft), restore
}
```

## 5. Filtering & Pagination

- List endpoints accept `?phase=`, `?sector=`, `?status=`, `?search=`, `?page=`, `?per_page=`.
- Default page size 20; max 100.
- Sort via `?sort=field&order=asc|desc` (whitelisted fields only).

## 6. Frontend API Client

`src/services/api.ts` wraps all endpoints with typed methods returning `null` on failure. Components never call `fetch` directly.
