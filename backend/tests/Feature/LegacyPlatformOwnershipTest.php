<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;
use App\Models\Role;
use App\Models\User;

/**
 * BLK-06 — Option B: unresolved legacy rows are platform-owned.
 *
 * Legacy `business_records` without a canonical owner (agency_id NULL) must be
 * invisible and untouchable to every agency workspace (read AND sync-write)
 * until a human-approved mapping is applied with `platform:legacy-backfill`.
 * Two agencies must each see zero legacy rows they do not own.
 */
class LegacyPlatformOwnershipTest extends EnterpriseTestCase
{
    private function workspaceReader(): array
    {
        $user = $this->makeUser('agent');
        $agency = $this->makeAgency('Legacy Probe Agency');
        $role = $this->makeRole('agency', 'agent', [
            'view_properties', 'view_transactions', 'view_inquiries', 'crm.view',
        ]);
        $this->makeMember($user, $agency, $role);

        return [$user, $agency];
    }

    public function test_two_agencies_each_see_zero_legacy_rows_they_do_not_own(): void
    {
        [$userA, $agencyA] = $this->workspaceReader();
        [$userB, $agencyB] = $this->workspaceReader();

        // Legacy unowned rows across collections (the BLK-06 population shape).
        foreach (['properties', 'transactions', 'dealers', 'videos', 'files'] as $i => $collection) {
            BusinessRecord::put($collection, "legacy-{$i}", ['id' => "legacy-{$i}", 'title' => 'Legacy row']);
        }
        // Plus rows that ARE owned — these must remain visible to their owner.
        BusinessRecord::put('properties', 'owned-a', ['id' => 'owned-a', 'title' => 'A owns']);
        BusinessRecord::where('collection', 'properties')->where('record_id', 'owned-a')
            ->update(['agency_id' => $agencyA->id]);

        foreach ([['A', $userA, $agencyA], ['B', $userB, $agencyB]] as [, $user, $agency]) {
            $body = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
                ->assertStatus(200)
                ->json();

            foreach (['properties', 'transactions', 'dealers', 'videos', 'files'] as $collection) {
                $rows = collect($body[$collection] ?? [])->pluck('id')->all();
                $leaked = array_values(array_intersect($rows, ['legacy-0', 'legacy-1', 'legacy-2', 'legacy-3', 'legacy-4']));
                $this->assertSame(
                    [],
                    $leaked,
                    "[{$agency->name}] legacy platform-owned rows leaked into the {$collection} workspace"
                );
            }
        }

        // Owned rows stay visible to the owning agency only.
        $bodyA = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($userA)))->json();
        $this->assertContains('owned-a', collect($bodyA['properties'] ?? [])->pluck('id')->all());
        $bodyB = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($userB)))->json();
        $this->assertNotContains('owned-a', collect($bodyB['properties'] ?? [])->pluck('id')->all());
    }

    public function test_agency_sync_cannot_claim_or_overwrite_a_platform_owned_legacy_row(): void
    {
        [$user, $agency] = $this->workspaceReader();
        $writer = $this->makeUser('manager');
        $roleW = $this->makeRole('agency', 'manager', [
            'view_properties', 'create_properties', 'edit_properties', 'properties.create', 'properties.update',
        ]);
        $this->makeMember($writer, $agency, $roleW);

        BusinessRecord::put('properties', 'legacy-claim', ['id' => 'legacy-claim', 'title' => 'Legacy row']);
        BusinessRecord::put('properties', 'legacy-delete', ['id' => 'legacy-delete', 'title' => 'Legacy row 2']);

        // Merge sync attempting to claim the row.
        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [
                ['id' => 'legacy-claim', 'title' => 'Hijacked title'],
            ]],
        ], $this->authHeaders($this->tokenFor($writer)))->assertStatus(200);

        $row = BusinessRecord::where('collection', 'properties')->where('record_id', 'legacy-claim')->first();
        $this->assertSame('Legacy row', $row->payload['title'], 'payload must not be overwritten');
        $this->assertNull($row->agency_id, 'agency must not stamp a platform-owned row (no co-claim)');

        // Replace mode must not soft-delete legacy rows either.
        $this->putJson('/api/workspace', [
            'collections' => ['properties' => []],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($writer)))->assertStatus(200);

        $this->assertDatabaseHas('business_records', [
            'collection' => 'properties', 'record_id' => 'legacy-delete', 'deleted_at' => null,
        ]);
    }

    public function test_backfilled_legacy_rows_reappear_for_their_owner_only(): void
    {
        [$userA, $agencyA] = $this->workspaceReader();
        [$userB, $agencyB] = $this->workspaceReader();

        BusinessRecord::put('properties', 'legacy-backfilled', ['id' => 'legacy-backfilled', 'title' => 'Legacy']);
        // Human-approved mapping outcome: the row is stamped to agency A.
        BusinessRecord::where('collection', 'properties')->where('record_id', 'legacy-backfilled')
            ->update(['agency_id' => $agencyA->id]);

        $bodyA = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($userA)))->json();
        $bodyB = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($userB)))->json();

        $this->assertContains('legacy-backfilled', collect($bodyA['properties'] ?? [])->pluck('id')->all());
        $this->assertNotContains('legacy-backfilled', collect($bodyB['properties'] ?? [])->pluck('id')->all());
    }
}
