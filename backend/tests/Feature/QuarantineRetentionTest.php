<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentQuarantine;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Storage;
use Mockery;

class QuarantineRetentionTest extends EnterpriseTestCase
{
    public function test_rejected_upload_metadata_is_retained_when_quarantine_byte_delete_fails(): void
    {
        $agency = $this->makeAgency('Quarantine Retention Agency');
        DocumentQuarantine::create([
            'id' => 'q-retain-1',
            'agency_id' => $agency->id,
            'original_name' => 'infected.pdf',
            'stored_name' => 'random.bin',
            'disk_path' => 'rejected/q-retain-1.bin',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'reason' => 'infected',
            'created_at' => now()->subDays(40),
        ]);

        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->once()->with('rejected/q-retain-1.bin')->andReturn(true);
        $disk->shouldReceive('delete')->once()->with('rejected/q-retain-1.bin')->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('quarantine')->andReturn($disk);

        $this->artisan('vault:purge-quarantine', ['--days' => 30])->assertExitCode(1);

        $this->assertDatabaseHas('document_quarantines', ['id' => 'q-retain-1']);
    }

    public function test_quarantined_document_and_versions_are_retained_when_any_byte_delete_fails(): void
    {
        $agency = $this->makeAgency('Quarantined Document Agency');
        $document = Document::create([
            'id' => 'doc-retain-1',
            'agency_id' => $agency->id,
            'category' => 'other',
            'title' => 'Held document',
            'original_name' => 'held.pdf',
            'stored_name' => 'held.bin',
            'disk_path' => 'quarantine/held.bin',
            'storage_disk' => 'documents',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'version' => 2,
            'status' => 'quarantined',
            'scan_status' => 'rejected',
            'uploaded_by' => null,
        ]);
        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => 1,
            'stored_name' => 'held-v1.bin',
            'disk_path' => 'quarantine/held-v1.bin',
            'storage_disk' => 'documents',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'created_at' => now()->subDays(40),
        ]);
        $document->forceFill(['created_at' => now()->subDays(40)])->save();

        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->once()->with('quarantine/held.bin')->andReturn(true);
        $disk->shouldReceive('delete')->once()->with('quarantine/held.bin')->andReturn(true);
        $disk->shouldReceive('exists')->once()->with('quarantine/held-v1.bin')->andReturn(true);
        $disk->shouldReceive('delete')->once()->with('quarantine/held-v1.bin')->andReturn(false);
        Storage::shouldReceive('disk')->twice()->with('documents')->andReturn($disk);

        $this->artisan('vault:purge-quarantine', ['--days' => 30])->assertExitCode(1);

        $this->assertNotNull(Document::withTrashed()->find($document->id));
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version' => 1]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'document.quarantine_purged',
            'entity_id' => $document->id,
        ]);
    }
}
