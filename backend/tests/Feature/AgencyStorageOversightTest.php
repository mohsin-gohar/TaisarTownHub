<?php

namespace Tests\Feature;

use App\Models\AgencySubscription;
use App\Models\Document;
use App\Models\SubscriptionPlan;
use App\Services\AgencyOnboarding;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\EnterpriseTestCase;

/**
 * Storage is monitored for oversight in this release; it is not quota-enforced.
 *
 * These tests lock in both halves: usage is still measured accurately, and no
 * configured storage value can block an upload or suspend an agency.
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

    public function test_new_agency_does_not_receive_a_5gb_storage_trial(): void
    {
        $plan = SubscriptionPlan::create([
            'key' => 'starter_test_' . bin2hex(random_bytes(3)),
            'name' => 'Starter Workspace',
            'price' => 0,
            'limits' => ['storage_mb' => 100, 'listings' => 10],
            'is_default' => true,
        ]);

        $agency = $this->makeAgency('New Starter Agency');
        $subscription = AgencyOnboarding::ensureStarterSubscription($agency);

        $this->assertSame($plan->id, $subscription->plan_id);
        // The temporary 5 GB trial is not applied; the plan's own (non-5GB)
        // setting is preserved.
        $this->assertSame(100, $agency->fresh()->limits()['storage_mb']);
        $this->assertNotSame(5120, $agency->fresh()->limits()['storage_mb']);

        // Idempotent: a second call never duplicates or downgrades the row.
        $again = AgencyOnboarding::ensureStarterSubscription($agency->fresh());
        $this->assertSame($subscription->id, $again->id);
        $this->assertSame(1, AgencySubscription::where('agency_id', $agency->id)->count());
    }

    public function test_storage_usage_endpoint_reports_monitoring_bytes_without_a_quota(): void
    {
        $agency = $this->makeAgency('Quota Agency');
        $this->activeDocument($agency->id, 2048);
        [$user] = $this->agencyUser(['documents.view'], 'document_manager', $agency);

        $body = $this->getJson('/api/agency/documents/storage-usage', $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('storage_bytes', 2048)
            ->assertJsonPath('unlimited', true)
            ->assertJsonPath('monitoring_only', true)
            ->json();

        $this->assertArrayNotHasKey('storage_mb_limit', $body);
    }

    public function test_uploads_are_not_rejected_by_removed_storage_quota(): void
    {
        Storage::fake('documents');
        $agency = $this->makeAgency('No Quota Agency');
        [$user] = $this->agencyUser(['documents.view', 'documents.upload'], 'document_manager', $agency);
        $headers = $this->authHeaders($this->tokenFor($user));

        // The temporary storage quota is removed; a file that would previously
        // have exceeded a tiny quota is still subject to normal validation only.
        $file = UploadedFile::fake()->create('allowed.pdf', 1536, 'application/pdf');
        $this->post('/api/agency/documents', ['file' => $file, 'title' => 'Allowed'], $headers)
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertSame(1, Document::where('agency_id', $agency->id)->count());
    }

    public function test_admin_can_inspect_metadata_without_quota_extension(): void
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
        $this->assertSame(4096, $row['storage_used_bytes']);
        $this->assertArrayNotHasKey('storage_limit_mb', $row);
        $this->assertArrayNotHasKey('over_quota', $row);
        $this->assertSame('monitor_only', $list['summary']['storage_mode']);

        $detail = $this->getJson("/api/admin/agencies-oversight/{$agency->id}", $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json();

        $this->assertSame(4096, $detail['storage']['document_bytes']);
        $this->assertSame($document->id, $detail['documents'][0]['id']);
        $this->assertArrayNotHasKey('disk_path', $detail['documents'][0]);
        $this->assertArrayNotHasKey('quota_history', $detail);
        $this->assertArrayNotHasKey('storage_limit_mb', $detail['storage']);

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        // Quota extension is intentionally unavailable in this release.
    }

    public function test_legacy_plan_storage_value_never_blocks_uploads_or_suspends_the_agency(): void
    {
        Storage::fake('documents');
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

        [$user] = $this->agencyUser(['documents.view', 'documents.upload'], 'document_manager', $agency);
        $headers = $this->authHeaders($this->tokenFor($user));

        $file = UploadedFile::fake()->create('over-legacy-limit.pdf', 1536, 'application/pdf');
        $this->post('/api/agency/documents', ['file' => $file, 'title' => 'Still allowed'], $headers)
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertSame(2, Document::where('agency_id', $agency->id)->count());
        $this->assertSame('active', $agency->fresh()->status, 'storage state never suspends the agency automatically');
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
        $this->assertSame(0, $agency->fresh()->limits()['storage_mb']);
    }

    public function test_oversight_storage_aggregation_query_count_does_not_grow_per_agency(): void
    {
        [, $headers] = $this->platformAdmin();

        foreach (['Storage Batch A', 'Storage Batch B', 'Storage Batch C'] as $name) {
            $agency = $this->makeAgency($name);
            $this->activeDocument($agency->id, 2048);
        }

        $aggregateQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$aggregateQueries): void {
            $sql = strtolower(ltrim($query->sql));
            if (str_contains($sql, 'sum(')
                && (str_contains($sql, 'from `documents`')
                    || str_contains($sql, 'from `document_versions`')
                    || str_contains($sql, 'from `media_files`'))) {
                $aggregateQueries[] = $sql;
            }
        });

        $response = $this->getJson('/api/admin/agencies-oversight', $headers)->assertOk();
        $this->assertCount(3, $response->json('agencies'));
        $this->assertSame(3, count($aggregateQueries), 'storage totals must use three grouped aggregate queries for the page, not three per agency');

        foreach ($response->json('agencies') as $row) {
            $this->assertSame(2048, $row['storage_used_bytes']);
        }
    }

}
