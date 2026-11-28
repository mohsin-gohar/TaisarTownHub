<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backfill existing data into the enterprise model. ADDITIVE ONLY:
 *  - agencies created from distinct dealers.agencyAffiliation strings
 *  - dealers.agency_id, properties.agency_id linked
 *  - all existing listings marked approved/public (public site unchanged)
 *  - business_records properties/transactions/payments/commissions mirrored
 *    into the relational tables (single source of truth going forward)
 *  - sectors/blocks/categories seeded from existing data
 *  - platform staff + dealer users linked through agency_members
 *
 * Re-run safe: everything uses updateOrCreate / existence guards.
 */
return new class extends Migration
{
    private function slugify(string $value): string
    {
        $slug = Str::slug($value);
        return $slug !== '' ? $slug : 'item';
    }

    public function up(): void
    {
        $now = now();

        /* ---------- 1. Agencies from legacy agency strings ---------- */
        $agencyNames = DB::table('dealers')
            ->whereNull('deleted_at')
            ->whereNotNull('agencyAffiliation')
            ->distinct()
            ->pluck('agencyAffiliation')
            ->filter(fn ($n) => trim((string) $n) !== '')
            ->values();

        $slugUsed = DB::table('agencies')->pluck('slug')->flip();
        $nameToId = [];
        foreach ($agencyNames as $name) {
            $id = 'agy_' . substr(sha1($name), 0, 16);
            $existingAgency = DB::table('agencies')->where('id', $id)->first();

            if ($existingAgency) {
                // A retry must preserve an existing slug, status, verification
                // decision and timestamps. Do not silently re-verify an agency.
                $slug = $existingAgency->slug;
            } else {
                $base = $this->slugify($name);
                $slug = $base;
                $i = 2;
                while (isset($slugUsed[$slug])) {
                    $slug = $base . '-' . $i++;
                }
                $slugUsed[$slug] = true;

                DB::table('agencies')->insert([
                    'id' => $id,
                    'name' => $name,
                    'slug' => $slug,
                    'email' => null,
                    'city' => 'Karachi',
                    'status' => 'active',
                    'verification_status' => 'verified',
                    'verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $nameToId[$name] = $id;
        }

        // Only fill an empty legacy ownership link; never overwrite a later
        // explicit reassignment if this backfill is retried after interruption.
        foreach ($nameToId as $name => $agencyId) {
            DB::table('dealers')
                ->where('agencyAffiliation', $name)
                ->whereNull('agency_id')
                ->update(['agency_id' => $agencyId]);
        }

        // users with a dealer link become agency members (system role: agent)
        $agentRole = $this->systemRoleKey('agent');
        $dealerUsers = DB::table('users')->whereNotNull('dealer_id')->get();
        foreach ($dealerUsers as $user) {
            $dealer = DB::table('dealers')->where('id', $user->dealer_id)->first();
            if (!$dealer || empty($dealer->agency_id)) {
                continue;
            }
            // Insert-only: a re-run must never reset an owner/manager role or
            // re-activate a member that was later deactivated.
            DB::table('agency_members')->insertOrIgnore([
                'agency_id' => $dealer->agency_id,
                'user_id' => $user->id,
                'role_id' => $agentRole,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        /* ---------- 2. Sectors & blocks from existing data ---------- */
        $sectorNames = collect()
            ->merge(DB::table('properties')->whereNull('deleted_at')->pluck('sector'))
            ->merge(DB::table('business_records')->where('collection', 'properties')->pluck('payload')
                ->map(fn ($p) => json_decode($p, true)['sector'] ?? null))
            ->filter(fn ($s) => is_string($s) && trim($s) !== '')
            ->unique()
            ->values();

        $sectorIdByName = [];
        foreach ($sectorNames as $name) {
            $slug = $this->slugify($name);
            $id = 'sec_' . substr(sha1($slug), 0, 16);
            $sectorIdByName[$name] = $id;
            DB::table('sectors')->insertOrIgnore([
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'phase' => null,
                'sort_order' => 0,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $blockPairs = collect()
            ->merge(DB::table('properties')->whereNull('deleted_at')->get(['sector', 'block']))
            ->merge(DB::table('business_records')->where('collection', 'properties')->pluck('payload')
                ->map(function ($p) {
                    $d = json_decode($p, true);
                    return (object) ['sector' => $d['sector'] ?? null, 'block' => $d['block'] ?? null];
                }))
            ->filter(fn ($r) => !empty($r->sector) && !empty($r->block))
            ->unique(fn ($r) => $r->sector . '|' . $r->block);

        foreach ($blockPairs as $r) {
            if (!isset($sectorIdByName[$r->sector])) {
                continue;
            }
            $slug = $this->slugify($r->block);
            $id = 'blk_' . substr(sha1($sectorIdByName[$r->sector] . '|' . $slug), 0, 16);
            DB::table('blocks')->insertOrIgnore([
                'id' => $id,
                'sector_id' => $sectorIdByName[$r->sector],
                'name' => $r->block,
                'slug' => $slug,
                'sort_order' => 0,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        /* ---------- 3. Link existing properties table rows ---------- */
        foreach (DB::table('properties')->get() as $prop) {
            $updates = [];
            if (empty($prop->agency_id) && !empty($prop->assignedDealerId)) {
                $dealer = DB::table('dealers')->where('id', $prop->assignedDealerId)->first();
                if ($dealer && !empty($dealer->agency_id)) {
                    $updates['agency_id'] = $dealer->agency_id;
                }
            }
            if (empty($prop->published_at)) {
                $updates['published_at'] = $prop->created_at ?? $now;
            }
            if (!empty($prop->sector) && empty($prop->sector_id) && isset($sectorIdByName[$prop->sector])) {
                $updates['sector_id'] = $sectorIdByName[$prop->sector];
            }
            if ($updates) {
                DB::table('properties')->where('id', $prop->id)->update($updates);
            }
        }

        /* ---------- 4. Mirror business_records properties → properties table ---------- */
        $categoryByType = [
            'Residential Plot' => 'residential_plot',
            'Commercial Plot' => 'commercial_plot',
            'House' => 'house',
            'Shop' => 'shop',
            'Apartment' => 'apartment',
            'Office' => 'office',
        ];
        $slugTaken = DB::table('properties')->whereNotNull('slug')->pluck('slug')->flip();

        $brProps = DB::table('business_records')->where('collection', 'properties')->get();
        foreach ($brProps as $record) {
            $p = json_decode($record->payload, true) ?: [];
            if (empty($p['id']) || empty($p['title'])) {
                continue;
            }
            $dealerId = $p['assignedDealerId'] ?? null;
            $agencyId = $record->agency_id ?? null;
            if (!$agencyId && $dealerId) {
                $agencyId = DB::table('dealers')->where('id', $dealerId)->value('agency_id');
            }
            if (!$agencyId && !empty($p['agencyName'])) {
                $agencyId = DB::table('agencies')->where('name', $p['agencyName'])->value('id');
            }

            $slugBase = $this->slugify($p['title']);
            $slug = $slugBase;
            $i = 2;
            while (isset($slugTaken[$slug])) {
                $slug = $slugBase . '-' . $i++;
            }
            $slugTaken[$slug] = true;

            $categoryId = null;
            $typeKey = $categoryByType[$p['type'] ?? ''] ?? null;
            if ($typeKey) {
                $categoryId = DB::table('property_categories')->where('key', $typeKey)->value('id');
            }

            $row = [
                'title' => $p['title'],
                'description' => $p['description'] ?? null,
                'pricePKR' => (int) ($p['pricePKR'] ?? 0),
                'formattedPrice' => $p['formattedPrice'] ?? null,
                'location' => $p['location'] ?? null,
                'phase' => $p['phase'] ?? 'Phase 1',
                'sector' => $p['sector'] ?? null,
                'block' => $p['block'] ?? null,
                'sizeSqYards' => (int) ($p['sizeSqYards'] ?? 120),
                'roadWidthFeet' => isset($p['roadWidthFeet']) ? (int) $p['roadWidthFeet'] : null,
                'type' => $p['type'] ?? 'Residential Plot',
                'status' => $p['status'] ?? 'Available',
                'isFeatured' => !empty($p['isFeatured']) ? 1 : 0,
                'possessionReady' => !empty($p['possessionReady']) ? 1 : 0,
                'mdaApproved' => !empty($p['mdaApproved']) ? 1 : 0,
                'westOpen' => !empty($p['westOpen']) ? 1 : 0,
                'cornerPlot' => !empty($p['cornerPlot']) ? 1 : 0,
                'parkFacing' => !empty($p['parkFacing']) ? 1 : 0,
                'mainBoulevard' => !empty($p['mainBoulevard']) ? 1 : 0,
                'images' => json_encode($p['images'] ?? []),
                'features' => json_encode($p['features'] ?? []),
                'contactPerson' => $p['contactPerson'] ?? null,
                'contactPhone' => $p['contactPhone'] ?? null,
                'contactWhatsApp' => $p['contactWhatsApp'] ?? null,
                'assignedDealerId' => $dealerId,
                'agencyName' => $p['agencyName'] ?? null,
                'createdDate' => $p['createdDate'] ?? null,
                'plotNumber' => $p['plotNumber'] ?? null,
                'fileNumber' => $p['fileNumber'] ?? null,
                'agency_id' => $agencyId,
                'approval_status' => 'approved',
                'visibility' => 'public',
                'featured_status' => !empty($p['isFeatured']) ? 'granted' : null,
                'listing_purpose' => 'sale',
                'slug' => $slug,
                'category_id' => $categoryId,
                'sector_id' => $sectorIdByName[$p['sector'] ?? ''] ?? null,
                'published_at' => $p['createdDate'] ?? $now,
                'updated_at' => $now,
            ];
            if (!empty($p['deletedAt'])) {
                $row['deleted_at'] = $p['deletedAt'];
            }
            if (!isset($row['created_at'])) {
                $row['created_at'] = $p['createdDate'] ?? $now;
            }

            $exists = DB::table('properties')->where('id', $p['id'])->exists();
            if ($exists) {
                // Do not clobber rows already living in the relational table;
                // only fill gaps (never overwrite a non-null value with null).
                // Never rewrite an existing public slug: it is a stable URL.
                $existingProperty = DB::table('properties')->where('id', $p['id'])->first();
                $patch = [];
                foreach (['agency_id', 'sector_id', 'published_at'] as $column) {
                    if (empty($existingProperty->{$column}) && ($row[$column] ?? null) !== null) {
                        $patch[$column] = $row[$column];
                    }
                }
                if ($patch) {
                    DB::table('properties')->where('id', $p['id'])->update($patch);
                }
            } else {
                DB::table('properties')->insert(array_merge(['id' => $p['id']], $row));
            }
            // Keep the JSON mirror agency-scoped too (same source until Phase 4).
            if ($agencyId && !$record->agency_id) {
                DB::table('business_records')->where('id', $record->id)->update(['agency_id' => $agencyId]);
            }
        }

        /* ---------- 5. Mirror business_records transactions → transactions table ---------- */
        foreach (DB::table('business_records')->where('collection', 'transactions')->get() as $record) {
            $t = json_decode($record->payload, true) ?: [];
            if (empty($t['id']) || empty($t['dealNumber'])) {
                continue;
            }
            $agencyId = null;
            if (!empty($t['dealerId'])) {
                $agencyId = DB::table('dealers')->where('id', $t['dealerId'])->value('agency_id');
            }
            $row = [
                'propertyId' => $t['propertyId'] ?? null,
                'propertyTitle' => $t['propertyTitle'] ?? 'Unknown property',
                'plotNumber' => $t['plotNumber'] ?? null,
                'sector' => $t['sector'] ?? null,
                'sellerName' => $t['sellerName'] ?? 'Unknown',
                'buyerName' => $t['buyerName'] ?? 'Unknown',
                'dealerId' => $t['dealerId'] ?? null,
                'dealerName' => $t['dealerName'] ?? null,
                'transactionType' => $t['transactionType'] ?? 'Sale',
                'agreementAmountPKR' => (int) ($t['agreementAmountPKR'] ?? 0),
                'advancePaymentPKR' => (int) ($t['advancePaymentPKR'] ?? 0),
                'remainingBalancePKR' => (int) ($t['remainingBalancePKR'] ?? 0),
                'commissionAmountPKR' => (int) ($t['commissionAmountPKR'] ?? 0),
                'commissionStatus' => $t['commissionStatus'] ?? 'Pending',
                'dealStatus' => $t['status'] ?? ($t['dealStatus'] ?? 'Agreement Signed'),
                'agency_id' => $agencyId,
                'updated_at' => $now,
            ];
            if ($existing = DB::table('transactions')->where('id', $t['id'])->first()) {
                // Gap-fill only. Stale business_records JSON must never
                // overwrite newer finance edits (status, tenant, amounts).
                $patch = [];
                foreach ($row as $key => $value) {
                    if ($key === 'updated_at' || $value === null) {
                        continue;
                    }
                    if ($existing->{$key} === null || $existing->{$key} === '') {
                        $patch[$key] = $value;
                    }
                }
                if ($patch) {
                    DB::table('transactions')->where('id', $t['id'])->update($patch);
                }
            } else {
                DB::table('transactions')->insert(array_merge([
                    'id' => $t['id'],
                    'dealNumber' => $t['dealNumber'],
                    'created_at' => $now,
                ], $row));
            }
            if ($agencyId && !$record->agency_id) {
                DB::table('business_records')->where('id', $record->id)->update(['agency_id' => $agencyId]);
            }
        }

        /* ---------- 6. Mirror payments & commissions ---------- */
        foreach (DB::table('business_records')->where('collection', 'payments')->get() as $record) {
            $p = json_decode($record->payload, true) ?: [];
            if (empty($p['id']) || empty($p['transactionId'])) {
                continue;
            }
            $txn = DB::table('transactions')->where('id', $p['transactionId'])->first();
            if (!$txn) {
                continue;
            }
            $methodMap = ['Pay Order' => 'bank_transfer', 'Cash' => 'cash', 'Cheque' => 'cheque', 'Bank Transfer' => 'bank_transfer'];
            $row = [
                'agency_id' => $txn->agency_id,
                'transaction_id' => $txn->id,
                'amount' => (float) ($p['amountPKR'] ?? 0),
                'type' => 'advance',
                'method' => $methodMap[$p['method'] ?? ''] ?? 'other',
                'reference' => (string) $p['id'],
                'received_at' => ($p['paidAt'] ?? null) ? substr($p['paidAt'], 0, 10) : $now,
                'notes' => trim(($p['note'] ?? '') . ' Recorded by ' . ($p['recordedBy'] ?? 'unknown')),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (!DB::table('transaction_payments')->where('reference', (string) $p['id'])->exists()) {
                DB::table('transaction_payments')->insert($row);
            }
        }

        foreach (DB::table('business_records')->where('collection', 'commissions')->get() as $record) {
            $c = json_decode($record->payload, true) ?: [];
            if (empty($c['id']) || empty($c['transactionId'])) {
                continue;
            }
            $txn = DB::table('transactions')->where('id', $c['transactionId'])->first();
            if (!$txn) {
                continue;
            }
            $statusMap = ['Pending Accrual' => 'pending', 'Accrued' => 'accrued', 'Paid' => 'paid', 'Cancelled' => 'cancelled'];
            $row = [
                'agency_id' => $txn->agency_id,
                'transaction_id' => $txn->id,
                'dealer_id' => $c['dealerId'] ?? null,
                'beneficiary_type' => 'dealer',
                'amount' => (float) ($c['amountPKR'] ?? 0),
                'status' => $statusMap[$c['status'] ?? ''] ?? 'pending',
                'notes' => $c['note'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            // A transaction can legitimately have several beneficiaries.
            // Deduplicate on the beneficiary + amount, not just transaction id.
            $duplicate = DB::table('commissions')
                ->where('transaction_id', $txn->id)
                ->where('amount', $row['amount'])
                ->when($row['dealer_id'] !== null,
                    fn ($q) => $q->where('dealer_id', $row['dealer_id']),
                    fn ($q) => $q->whereNull('dealer_id'));
            if (!$duplicate->exists()) {
                DB::table('commissions')->insert($row);
            }
            DB::table('business_records')->where('id', $record->id)->update(['agency_id' => $txn->agency_id]);
        }

        /* ---------- 7. Mirror missing inquiries ---------- */
        foreach (DB::table('business_records')->where('collection', 'inquiries')->get() as $record) {
            $i = json_decode($record->payload, true) ?: [];
            if (empty($i['id']) || empty($i['phone'])) {
                continue;
            }
            if (DB::table('inquiries')->where('id', $i['id'])->exists()) {
                continue;
            }
            $propertyId = null;
            if (!empty($i['preferredPropertyOrProject'])) {
                $propertyId = DB::table('properties')->where('title', $i['preferredPropertyOrProject'])->value('id');
            }
            DB::table('inquiries')->insert([
                'id' => $i['id'],
                'fullName' => $i['fullName'] ?? 'Unknown',
                'email' => $i['email'] ?? null,
                'phone' => $i['phone'],
                'subject' => $i['subject'] ?? 'Property Consultation',
                'message' => $i['message'] ?? '',
                'preferredPropertyOrProject' => $i['preferredPropertyOrProject'] ?? null,
                'budgetRange' => $i['budgetRange'] ?? null,
                'status' => $i['status'] ?? 'Pending',
                'adminNotes' => $i['adminNotes'] ?? null,
                'source' => $i['source'] ?? 'Web Portal',
                'property_id' => $propertyId,
                'lead_status' => 'new',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($propertyId) {
                $pid = DB::table('properties')->where('id', $propertyId)->value('agency_id');
                if ($pid) {
                    DB::table('inquiries')->where('id', $i['id'])->update(['agency_id' => $pid]);
                    DB::table('business_records')->where('id', $record->id)->update(['agency_id' => $pid]);
                }
            }
        }
    }

    public function down(): void
    {
        // Data backfill is intentionally not reversible; forward migrations only.
    }

    /**
     * Resolve (or lazily create) a system agency role row id by key.
     * The full RBAC seeding happens in EnterpriseSeeder; this keeps the
     * backfill working even if the seeder has not run yet.
     */
    private function systemRoleKey(string $key): string
    {
        $id = DB::table('roles')->where('scope', 'agency')->whereNull('agency_id')->where('key', $key)->value('id');
        if ($id) {
            return $id;
        }
        $names = [
            'agency_owner' => 'Agency Owner', 'agency_manager' => 'Agency Manager',
            'property_manager' => 'Property Manager', 'agent' => 'Agent',
            'document_manager' => 'Document Manager', 'accountant' => 'Accountant', 'viewer' => 'Viewer',
        ];
        $roleId = 'role_' . $key;
        DB::table('roles')->insert([
            'id' => $roleId,
            'scope' => 'agency',
            'agency_id' => null,
            'key' => $key,
            'name' => $names[$key] ?? $key,
            'is_system' => 1,
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $roleId;
    }
};
