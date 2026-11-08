# TaisarTownHub — Database Seeders & Realistic Factories (Pillar 5)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. Seed Data Source

Primary seed: `backend/database/seed-data/workspace.json` (65KB) — a single JSON document containing all collections:
- `properties` (35 rows), `projects` (4), `dealers` (15), `sellers` (3), `buyers` (3), `transactions` (3), `media` (7), `videos` (6), `inquiries` (2), `subscribers` (1), `testimonials` (2), `plots` (8), `cms` (1 default).

Loaded by the seeder into `business_records` (collection + record_id + payload) and into typed tables (`properties`, `dealers`, `transactions`, `projects`, `inquiries`, `agency_videos`, `video_comments`).

## 2. Factory Pattern (Laravel)

Located in `backend/database/factories/` (where present) and inline in seeders. Factories use Faker for realistic PK data:

```php
// Example: Property factory
Property::factory()->count(35)->create([
    'phase' => fake()->randomElement(['Phase 1','Phase 2','Phase 3']),
    'sector' => fake()->randomElement(['Sector 70-D','Sector 63','Sector 78','Sector 84']),
    'type' => fake()->randomElement(['Residential Plot','Commercial Plot','House / Villa']),
    'status' => fake()->randomElement(['Available','Sold','Booked']),
    'price_pkr' => fake()->numberBetween(1500000, 15000000),
]);
```

## 3. Master Seeder Flow

```php
// DatabaseSeeder::run()
1. Create users (super_admin, admin, manager, dealer, operator) with hashed passwords.
2. Load workspace.json → sync each collection via BusinessRecord::put().
3. Hydrate typed tables (properties, dealers, transactions, projects, inquiries, agency_videos, video_comments).
4. Create audit_log entries for seed actions.
5. Assign dealer_id to dealer-role users.
```

## 4. Realistic Data Characteristics

- **PKR pricing:** Lacs/Crores formatted (e.g., PKR 78 Lacs, PKR 1.90 Crore).
- **Sectors:** Real MDA Scheme 45 sectors (70-D, 63, 78, 80, 84, 50, 30-A, …).
- **Names:** Pakistani names (Muhammad, Chaudhry, Sheikh, Haji, Dr., …).
- **Phone:** +92 3XX XXXXXXX format.
- **CNIC:** 13-digit masked in public payloads; unmasked only for super_admin.
- **Commissions:** percentage or fixed, per dealer.
- **Media sales:** some assets marked sold with buyer attribution + profit.

## 5. Staff Credentials (seeded)

| Role | Email | Password |
|---|---|---|
| Super Admin | superadmin@taisartownhub.com | Super@Admin2026! |
| Agency Admin | admin@taisartownhub.com | Admin@Taisar2026 |
| Branch Manager | manager@taisartownhub.com | Manager@2026 |
| Property Dealer | dealer@taisartownhub.com | Dealer@2026 |
| Data Entry Operator | operator@taisartownhub.com | Operator@2026 |

> Demo-only credentials; rotate before production.

## 6. Idempotency & Reset

```bash
php artisan migrate:fresh --seed   # full reset (dev only)
php artisan db:seed                # seed only (idempotent via updateOrCreate)
```

The workspace sync uses `BusinessRecord::put()` (updateOrCreate on collection+record_id), so re-seeding updates rather than duplicates.

## 7. Multi-Tenant Simulation

Each collection is scoped by `collection` + `record_id`. The `business_records` table acts as a flexible document store, allowing new collections without schema changes. Tenant isolation is enforced at the application layer (role-based filtering) rather than DB-level row security, suitable for the current single-agency SaaS scope.
