<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Private document vault + media privacy: bytes live on the private disk,
 * authenticated policy-checked streaming, and agency isolation.
 */
class StorageSecurityTest extends EnterpriseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        Storage::fake('quarantine');
        // Deterministic clean verdicts so the storage/privacy flows under
        // test are independent of whether a local ClamAV binary exists.
        // (Verdict-dependent behaviour has its own suite in
        // MalwareUploadEnforcementTest / MalwareScanningTest.)
        $this->app->instance(
            \App\Services\MalwareScanner::class,
            new class implements \App\Services\MalwareScanner {
                public function scan(string $filePath): \App\Support\MalwareScanResult
                {
                    return \App\Support\MalwareScanResult::clean('clamav');
                }

                public function isAvailable(): bool
                {
                    return true;
                }
            }
        );
    }

    private function documentPermissions(bool $sensitive = false): array
    {
        $permissions = [
            'documents.view', 'documents.upload', 'documents.download',
            'documents.update', 'documents.delete', 'view_private_documents',
            'manage_media', 'upload_media', 'properties.view', 'view_properties',
        ];
        if ($sensitive) {
            $permissions[] = 'documents.view_sensitive';
            $permissions[] = 'view_sensitive_cnic';
        }

        return $permissions;
    }

    private function uploadDocument($user, array $extra = [], string $name = 'registry-deed.pdf')
    {
        return $this->post('/api/agency/documents', array_merge([
            'file' => UploadedFile::fake()->create($name, 40, 'application/pdf'),
            'title' => 'Registry Deed',
            'category' => 'other',
        ], $extra), $this->authHeaders($this->tokenFor($user)));
    }

    public function test_document_upload_is_stored_privately_and_streams_authenticated(): void
    {
        [$user] = $this->agencyUser($this->documentPermissions());

        $response = $this->uploadDocument($user)->assertStatus(201)->assertJsonPath('success', true);
        $document = $response->json('document');
        $documentId = $document['id'];
        $diskPath = Document::find($documentId)->disk_path;

        $this->assertNotEmpty($diskPath);
        Storage::disk('documents')->assertExists($diskPath);
        $this->assertDatabaseHas('documents', ['id' => $documentId, 'status' => 'active']);
        $this->assertDatabaseHas('document_access_logs', [
            'document_id' => $documentId,
            'action' => 'upload',
        ]);

        // Downloads are streamed only through the authenticated endpoint.
        $download = $this->get("/api/agency/documents/{$documentId}/download", $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200);
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));

        $this->getJson("/api/agency/documents/{$documentId}/preview", $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200);

        $this->assertDatabaseHas('document_access_logs', [
            'document_id' => $documentId,
            'action' => 'download',
        ]);
    }

    public function test_anonymous_requests_cannot_touch_the_document_vault(): void
    {
        [$user] = $this->agencyUser($this->documentPermissions());
        $documentId = $this->uploadDocument($user)->json('document.id');

        $this->asGuest();
        $this->getJson("/api/agency/documents/{$documentId}/download")->assertStatus(401);
        $this->getJson('/api/agency/documents')->assertStatus(401);
    }

    public function test_documents_are_isolated_between_agencies(): void
    {
        [$userA] = $this->agencyUser($this->documentPermissions());
        [$userB] = $this->agencyUser($this->documentPermissions());

        $documentId = $this->uploadDocument($userA)->json('document.id');

        $this->getJson("/api/agency/documents/{$documentId}/download", $this->authHeaders($this->tokenFor($userB)))
            ->assertStatus(404);

        $listB = $this->getJson('/api/agency/documents', $this->authHeaders($this->tokenFor($userB)))
            ->assertStatus(200)
            ->json('documents');
        $this->assertNotContains($documentId, array_column($listB, 'id'));
    }

    public function test_sensitive_documents_require_the_sensitive_permission(): void
    {
        [$clerk, $agency] = $this->agencyUser($this->documentPermissions(false));

        // Uploading a sensitive category without permission is forbidden.
        $this->uploadDocument($clerk, ['category' => 'cnic'], 'cnic-front.jpg')->assertStatus(403);

        // A permitted user uploads it.
        [$manager] = $this->agencyUser($this->documentPermissions(true), 'manager', $agency);
        $document = $this->uploadDocument($manager, ['category' => 'cnic'], 'cnic-front.jpg')
            ->assertStatus(201)
            ->json('document');
        $this->assertTrue((bool) $document['is_sensitive']);

        // Same agency, but no sensitive permission → cannot download.
        $this->getJson("/api/agency/documents/{$document['id']}/download", $this->authHeaders($this->tokenFor($clerk)))
            ->assertStatus(403);
    }

    public function test_unsupported_document_types_are_rejected(): void
    {
        [$user] = $this->agencyUser($this->documentPermissions());

        $this->uploadDocument($user, [], 'malware.exe')->assertStatus(422);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_public_media_is_served_only_when_visibility_is_public(): void
    {
        [$user] = $this->agencyUser($this->documentPermissions());

        $public = $this->post('/api/agency/media', [
            'file' => UploadedFile::fake()->create('banner.jpg', 20, 'image/jpeg'),
            'title' => 'Public Banner',
            'visibility' => 'public',
            'type' => 'image',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->json('media');

        $private = $this->post('/api/agency/media', [
            'file' => UploadedFile::fake()->create('secret.jpg', 20, 'image/jpeg'),
            'title' => 'Private Photo',
            'visibility' => 'private',
            'type' => 'image',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->json('media');

        $this->asGuest();
        $publicResponse = $this->get("/api/media/{$public['id']}/file")
            ->assertStatus(200)
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');
        $this->assertStringContainsString(
            'inline',
            (string) $publicResponse->headers->get('content-disposition'),
            'Public marketing media must be embeddable inline.'
        );
        $this->get("/api/media/{$private['id']}/file")->assertStatus(404);

        // Private media is only streamed to the owning agency.
        $this->get("/api/agency/media/{$private['id']}/file", $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200);

        [$rival] = $this->agencyUser($this->documentPermissions());
        $this->get("/api/agency/media/{$private['id']}/file", $this->authHeaders($this->tokenFor($rival)))
            ->assertStatus(404);
    }

    public function test_media_upload_rejects_unsupported_types(): void
    {
        [$user] = $this->agencyUser($this->documentPermissions());

        $this->post('/api/agency/media', [
            'file' => UploadedFile::fake()->create('shell.php', 2, 'application/x-php'),
            'title' => 'Not allowed',
            'visibility' => 'public',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(422);

        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_document_delete_is_soft_and_file_survives_for_audit(): void
    {
        [$user] = $this->agencyUser($this->documentPermissions());
        $document = $this->uploadDocument($user)->json('document');

        $this->deleteJson("/api/agency/documents/{$document['id']}", [], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200);

        $this->assertSoftDeleted('documents', ['id' => $document['id']]);
        Storage::disk('documents')->assertExists(Document::withTrashed()->find($document['id'])->disk_path);
        $this->assertNotNull(Document::withTrashed()->find($document['id'])->deleted_at);
    }
}
