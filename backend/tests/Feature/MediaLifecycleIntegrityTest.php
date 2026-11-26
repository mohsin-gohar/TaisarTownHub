<?php

namespace Tests\Feature;

use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;
use Mockery;

class MediaLifecycleIntegrityTest extends EnterpriseTestCase
{
    private function mediaRecord($agency, array $overrides = []): MediaFile
    {
        return MediaFile::create(array_merge([
            'agency_id' => $agency->id,
            'title' => 'Media fixture',
            'type' => 'image',
            'folder' => 'general',
            'visibility' => 'private',
            'status' => 'available',
            'url' => 'https://cdn.example.com/fixture.jpg',
            'disk_path' => null,
            'storage_disk' => null,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'scan_status' => 'scanned_clean',
            'buyer_name' => 'Buyer Private Name',
            'buyer_phone' => '+923001234567',
            'buyer_national_id_masked' => '*****-*******-1234',
            'sold_price_pk' => '100.00',
            'cost_price_pk' => '80.00',
            'profit_pk' => '20.00',
        ], $overrides));
    }

    public function test_media_listing_redacts_buyer_pii_and_financial_fields_for_property_viewers(): void
    {
        [$viewer, $agency] = $this->agencyUser(['properties.view'], 'agent');
        $media = $this->mediaRecord($agency);
        $viewerHeaders = $this->authHeaders($this->tokenFor($viewer));

        $this->getJson('/api/agency/media', $viewerHeaders)
            ->assertOk()
            ->assertJsonMissingPath('media.data.0.buyer_name')
            ->assertJsonMissingPath('media.data.0.buyer_phone')
            ->assertJsonMissingPath('media.data.0.sold_price_pk')
            ->assertJsonMissingPath('media.data.0.cost_price_pk')
            ->assertJsonMissingPath('media.data.0.profit_pk');

        [$manager] = $this->agencyUser(['manage_media', 'finance.view', 'documents.view_sensitive'], 'agency_manager', $agency);
        $managerHeaders = $this->authHeaders($this->tokenFor($manager));

        $this->getJson('/api/agency/media', $managerHeaders)
            ->assertOk()
            ->assertJsonPath('media.data.0.id', $media->id)
            ->assertJsonPath('media.data.0.buyer_name', 'Buyer Private Name')
            ->assertJsonPath('media.data.0.sold_price_pk', 100);
    }

    public function test_public_media_requires_an_active_agency_and_available_status(): void
    {
        [$user, $agency] = $this->agencyUser(['manage_media'], 'agency_manager');
        $media = $this->mediaRecord($agency, ['visibility' => 'public', 'status' => 'available']);

        $agency->forceFill(['status' => 'pending'])->save();
        $this->getJson("/api/media/{$media->id}/file")->assertNotFound();

        $agency->forceFill(['status' => 'active'])->save();
        $media->forceFill(['status' => 'sold'])->save();
        $this->getJson("/api/media/{$media->id}/file")->assertNotFound();
    }

    public function test_media_delete_retains_metadata_when_storage_does_not_confirm_delete(): void
    {
        [$user, $agency] = $this->agencyUser(['manage_media'], 'agency_manager');
        $headers = $this->authHeaders($this->tokenFor($user));
        $path = $agency->id . '/2026/11/private-media.pdf';
        $media = $this->mediaRecord($agency, [
            'type' => 'document',
            'url' => null,
            'disk_path' => $path,
            'storage_disk' => 'documents',
            'mime_type' => 'application/pdf',
        ]);

        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->once()->with($path)->andReturn(true);
        $disk->shouldReceive('delete')->once()->with($path)->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('documents')->andReturn($disk);

        $this->deleteJson("/api/agency/media/{$media->id}", [], $headers)->assertStatus(503);

        $this->assertDatabaseHas('media_files', [
            'id' => $media->id,
            'agency_id' => $agency->id,
        ]);
    }

    public function test_media_sale_is_idempotent_and_uses_exact_paisa_arithmetic(): void
    {
        [$user, $agency] = $this->agencyUser(['manage_media'], 'agency_manager');
        $headers = $this->authHeaders($this->tokenFor($user));
        $media = $this->mediaRecord($agency, ['visibility' => 'public']);

        $payload = [
            'selling_price_pk' => '100.50',
            'cost_price_pk' => '80.25',
            'buyer_name' => 'Buyer',
            'buyer_national_id_masked' => '*****-*******-1234',
        ];

        $this->postJson("/api/agency/media/{$media->id}/sell", $payload, $headers)
            ->assertOk()
            ->assertJsonPath('media.status', 'sold')
            ->assertJsonPath('media.visibility', 'private');

        $this->assertDatabaseHas('media_files', [
            'id' => $media->id,
            'sold_price_pk' => '100.50',
            'cost_price_pk' => '80.25',
            'profit_pk' => '20.25',
            'status' => 'sold',
            'visibility' => 'private',
        ]);

        $this->postJson("/api/agency/media/{$media->id}/sell", $payload, $headers)
            ->assertStatus(422);
    }
}
