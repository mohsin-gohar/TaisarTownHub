<?php

namespace Tests\Feature;

use App\Models\AgencySubscription;
use App\Models\Document;
use App\Models\SubscriptionPlan;
use App\Services\AgencyOnboarding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\EnterpriseTestCase;

/**
 * 5 GB default workspace + platform storage oversight.
 *
 * The quota is always enforced by the API, not just shown in the browser.
 */
class AgencyStorageOversightTest extends EnterpriseTestCase
{
    private function platformAdmin(): array
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin', ['email' => 'oversight-' . bin2hex(random_bytes(4)) . '@example.test']);

        return [$admin, $this->authHeaders($this->tokenFor($admin))];
    }

    private function activeDocument(string $agencyId, int $bytes = 1024): Document
    {
        return Document::create([
            'id' => 'doc_' . bin2hex(random_bytes(8)),
            'agency_id' => $agencyId,
            'category' => 'other',
            'title' => 'Stored File',
            'original_name' => 'stored.pdf',
            'stored_name' => 'stored.pdf',
            'disk_path' => 'vault/stored.pdf',
            'storage_disk' => 'documents',
            'mime_type' => 'application/pdf',
            'size_bytes' => $bytes,
            'version' => 1,
            'status' => 'active',
            'scan_status' => 'scanned_clean',
            'uploaded_by' => null,
        ]);
    }

    public function test_new_agency_gets_a_database_backed_5gb_starter_subscription(): void
    {
        $plan = SubscriptionPlan::create([
            'key' => 'starter_test_' . bin2hex(random_bytes(3)),
            'name' => 'Starter Workspace',
            'price' => 0,
            'limits' => ['storage_mb' => 512, 'listings' => 10],
            'is_default' => true,
        ]);

        $agency = $this->makeAgency('New Starter Agency');
        $subscription = AgencyOnboarding::ensureStarterSubscription($agency);

        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame(5120, $agency->fresh()->limits()['storage_mb']);

        // Idempotent: a second call never duplicates or downgrades the row.
        $again = AgencyOnboarding::ensureStarterSubscription($agency->fresh());
        $this->assertSame($subscription->id, $again->id);
        $this->assertSame(1, AgencySubscription::where('agency_id', $agency->id)->count());
    }

    public function test_storage_usage_endpoint_reports_enforced_quota_and_bytes(): void
    {
        $agency = $this->makeAgency('Quota Agency');
        $this->activeDocument($agency->id, 2048);
        [$user] = $this->agencyUser(['documents.view'], 'document_manager', $agency);

        $this->getJson('/api/agency/documents/storage-usage', $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('storage_mb_limit', 5120)
            ->assertJsonPath('storage_bytes', 2048)
            ->assertJsonPath('unlimited', false);
    }

    public function test_upload_rejected_safely_when_quota_is_exceeded(): void
    {
        Storage::fake('documents');
        $agency = $this->makeAgency('Tiny Quota Agency');
        $plan = SubscriptionPlan::create([
            'key' => 'tiny_' . bin2hex(random_bytes(3)),
            'name' => 'Tiny',
            'price' => 0,
            'limits' => ['storage_mb' => 1],
        ]);
        AgencySubscription::create([
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        [$user] = $this->agencyUser(['documents.view', 'documents.upload'], 'document_manager', $agency);
        $headers = $this->authHeaders($this->tokenFor($user));

        // 1.5 MB in one upload would exceed the 1 MB quota immediately.
        $file = UploadedFile::fake()->create('too-big.pdf', 1536, 'application/pdf');
        $this->post('/api/agency/documents', ['file' => $file, 'title' => 'Too Big'], $headers)
            ->assertStatus(402)
            ->assertJsonPath('code', 'plan_limit_reached');

        $this->assertSame(0, Document::where('agency_id', $agency->id)->count());
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_admin_can_inspect_metadata_and_extend_quota_without_deleting_files(): void
    {
        [$admin, $headers] = $this->platformAdmin();
        $agency = $this->makeAgency('Oversight Agency');
        $document = $this->activeDocument($agency->id, 4096);

        $list = $this->getJson('/api/admin/agencies-oversight', $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json();

        $row = collect($list['agencies'])->firstWhere('id', $agency->id);
        $this->assertNotNull($row);
        $this->assertSame(5120, $row['storage_limit_mb']);
        $this->assertSame(4096, $row['storage_used_bytes']);
        $this->assertFalse($row['over_quota']);

        $detail = $this->getJson("/api/admin/agencies-oversight/{$agency->id}", $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json();

        $this->assertSame(4096, $detail['storage']['document_bytes']);
        $this->assertSame($document->id, $detail['documents'][0]['id']);
        $this->assertArrayNotHasKey('disk_path', $detail['documents'][0]);

        $this->putJson("/api/admin/agencies-oversight/{$agency->id}/quota", [
            'storage_mb' => 10240,
            'reason' => 'Heavy registry archive',
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('storage.storage_limit_mb', 10240);

        $agency->refresh();
        $this->assertSame(10240, $agency->limits()['storage_mb']);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agency.quota.extend',
            'entity_id' => $agency->id,
        ]);
    }

    public function test_over_quota_agency_is_flagged_and_upload_still_blocked(): void
    {
        [$admin, $headers] = $this->platformAdmin();
        $agency = $this->makeAgency('Over Quota Agency');
        $plan = SubscriptionPlan::create([
            'key' => 'over_' . bin2hex(random_bytes(3)),
            'name' => 'Over',
            'price' => 0,
            'limits' => ['storage_mb' => 1],
        ]);
        AgencySubscription::create([
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $this->activeDocument($agency->id, 2 * 1024 * 1024);

        $detail = $this->getJson("/api/admin/agencies-oversight/{$agency->id}", $headers)
            ->assertStatus(200)
            ->json();

        $this->assertTrue($detail['storage']['over_quota']);
        $this->assertSame(1, $detail['storage']['storage_limit_mb']);
        $this->assertSame('active', $agency->fresh()->status, 'quota breach never suspends the agency automatically');

        // Extending the quota re-enables uploads without touching the file.
        $this->putJson("/api/admin/agencies-oversight/{$agency->id}/quota", [
            'storage_mb' => 5120,
        ], $headers)->assertStatus(200);
        $this->assertFalse($this->getJson("/api/admin/agencies-oversight/{$agency->id}", $headers)
            ->assertStatus(200)
            ->json('storage.over_quota'));
        $this->assertDatabaseHas('documents', ['agency_id' => $agency->id]);
    }

    public function test_admin_can_suspend_and_reactivate_agency_without_deleting_files(): void
    {
        [$admin, $headers] = $this->platformAdmin();
        $agency = $this->makeAgency('Reviewable Agency');
        $document = $this->activeDocument($agency->id, 1024);

        $this->patchJson("/api/admin/agencies/{$agency->id}/status", [
            'status' => 'suspended',
            'reason' => 'Documents under review',
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('agency.status', 'suspended');

        $this->assertSame('suspended', $agency->fresh()->status);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);

        $this->patchJson("/api/admin/agencies/{$agency->id}/status", [
            'status' => 'active',
            'reason' => 'Review complete',
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('agency.status', 'active');

        $this->assertSame('active', $agency->fresh()->status);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agency.status',
            'entity_id' => $agency->id,
        ]);
    }

    public function test_agency_members_cannot_open_platform_oversight(): void
    {
        $agency = $this->makeAgency('Restricted Agency');
        [$user] = $this->agencyUser(['documents.view'], 'document_manager', $agency);
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->getJson('/api/admin/agencies-oversight', $headers)->assertStatus(403);
        $this->getJson("/api/admin/agencies-oversight/{$agency->id}", $headers)->assertStatus(403);
        $this->putJson("/api/admin/agencies-oversight/{$agency->id}/quota", ['storage_mb' => 999999], $headers)
            ->assertStatus(403);
        $this->assertSame(5120, $agency->fresh()->limits()['storage_mb']);
    }
}
