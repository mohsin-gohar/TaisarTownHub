<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;
use App\Models\User;

/**
 * Destructive workspace replace must never be triggered by an empty or
 * malformed payload. A Super Admin replace is platform-wide, so an accidental
 * `[]` previously soft-deleted every row in the collection.
 */
class WorkspaceReplaceGuardTest extends EnterpriseTestCase
{
    private function deleteCapableMember(): array
    {
        [$user, $agency] = $this->agencyUser([
            'properties.view', 'properties.create', 'properties.update', 'properties.delete',
            'view_properties', 'create_properties', 'edit_properties', 'delete_properties',
        ]);

        return [$user, $agency];
    }

    private function superAdmin(): User
    {
        $this->seedPlatformRole('super_admin');

        return $this->makeUser('super_admin');
    }

    private function ownRow(string $id, string $title, ?string $agencyId): void
    {
        BusinessRecord::put('properties', $id, ['id' => $id, 'title' => $title]);
        if ($agencyId !== null) {
            BusinessRecord::where('collection', 'properties')->where('record_id', $id)
                ->update(['agency_id' => $agencyId]);
        }
    }

    private function deletedAt(string $id)
    {
        return BusinessRecord::where('collection', 'properties')->where('record_id', $id)->value('deleted_at');
    }

    public function test_empty_replace_is_skipped_for_an_agency_member_with_delete_rights(): void
    {
        [$user, $agency] = $this->deleteCapableMember();
        $other = $this->makeAgency('Other Agency');
        $this->ownRow('own-row', 'Own', $agency->id);
        $this->ownRow('foreign-row', 'Foreign', $other->id);

        $response = $this->putJson('/api/workspace', [
            'collections' => ['properties' => []],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $response->assertJsonPath('skipped.0', 'properties');
        $this->assertNull($this->deletedAt('own-row'));
        $this->assertNull($this->deletedAt('foreign-row'));
    }

    public function test_empty_replace_is_skipped_for_a_platform_super_admin(): void
    {
        $admin = $this->superAdmin();
        $agency = $this->makeAgency('Owned Agency');
        $this->ownRow('platform-own', 'Owned', $agency->id);
        $this->ownRow('platform-legacy', 'Legacy', null);

        $response = $this->putJson('/api/workspace', [
            'collections' => ['properties' => []],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($admin)))->assertOk();

        $response->assertJsonPath('skipped.0', 'properties');
        $this->assertNull($this->deletedAt('platform-own'));
        $this->assertNull($this->deletedAt('platform-legacy'));
    }

    public function test_a_payload_whose_rows_are_all_invalid_is_treated_as_empty(): void
    {
        [$user, $agency] = $this->deleteCapableMember();
        $this->ownRow('survivor', 'Survivor', $agency->id);

        $response = $this->putJson('/api/workspace', [
            'collections' => ['properties' => [
                ['id' => 'bad id with spaces!', 'title' => 'Rejected'],
            ]],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $response->assertJsonPath('skipped.0', 'properties');
        $this->assertNull($this->deletedAt('survivor'));
    }

    public function test_replace_with_only_foreign_rows_cannot_wipe_own_collection(): void
    {
        [$user, $agency] = $this->deleteCapableMember();
        $other = $this->makeAgency('Other Agency');
        $this->ownRow('own-survivor', 'Own survivor', $agency->id);
        $this->ownRow('foreign-row', 'Foreign row', $other->id);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [
                ['id' => 'foreign-row', 'title' => 'Hijacked'],
            ]],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertNull($this->deletedAt('own-survivor'), 'unauthorized-only replace must not delete own rows');
        $this->assertNull($this->deletedAt('foreign-row'));
        $this->assertSame(
            'Foreign row',
            BusinessRecord::where('collection', 'properties')->where('record_id', 'foreign-row')->first()->payload['title']
        );
    }

    public function test_platform_wide_replace_requires_explicit_confirmation(): void
    {
        $admin = $this->superAdmin();
        $this->ownRow('keep-row', 'Keep', null);
        $this->ownRow('victim-row', 'Victim', null);

        $response = $this->putJson('/api/workspace', [
            'collections' => ['properties' => [
                ['id' => 'keep-row', 'title' => 'Keep'],
            ]],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($admin)))->assertOk();

        $response->assertJsonPath('skipped.0', 'properties');
        $this->assertNull($this->deletedAt('victim-row'), 'unconfirmed platform replace must not delete');

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [
                ['id' => 'keep-row', 'title' => 'Keep'],
            ]],
            'mode' => 'replace',
            'confirm_platform_replace' => true,
        ], $this->authHeaders($this->tokenFor($admin)))->assertOk();

        $this->assertNull($this->deletedAt('keep-row'));
        $this->assertNotNull($this->deletedAt('victim-row'), 'confirmed platform replace removes absent rows');
    }

    public function test_empty_replace_is_always_refused_even_with_confirmations(): void
    {
        $admin = $this->superAdmin();
        $this->ownRow('one', 'One', null);
        $this->ownRow('two', 'Two', null);

        $response = $this->putJson('/api/workspace', [
            'collections' => ['properties' => []],
            'mode' => 'replace',
            'confirm_platform_replace' => true,
            'confirm_empty_replace' => true,
        ], $this->authHeaders($this->tokenFor($admin)))->assertOk();

        $response->assertJsonPath('skipped.0', 'properties');
        $this->assertNull($this->deletedAt('one'), 'empty replace must never delete rows');
        $this->assertNull($this->deletedAt('two'), 'empty replace must never delete rows');
    }
}
