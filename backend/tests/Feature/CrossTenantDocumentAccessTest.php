<?php

namespace Tests\\Feature;

use App\\Models\\Document;
use Illuminate\\Support\\Facades\\Storage;

/**
 * Phase 3.10 — cross-tenant document vault access.
 *
 * Agency-scoped document IDs are treated as untrusted input. Every private
 * document operation must resolve the document inside the caller's agency.
 */
class CrossTenantDocumentAccessTest extends EnterpriseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        Storage::fake('quarantine');
    }

    private function documentFor($agency, string $id, string $title = 'Private tenant document'): Document
    {
        $path = $agency->id . '/2026/11/' . $id . '.pdf';
        Storage::disk('documents')->put($path, '%PDF-1.4 ' . $id);

        return Document::create([
            'id' => $id,
            'agency_id' => $agency->id,
            'category' => 'other',
            'title' => $title,
            'original_name' => $id . '.pdf',
            'stored_name' => $id . '.pdf',
            'disk_path' => $path,
            'storage_disk' => 'documents',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'checksum' => hash('sha256', $id),
            'is_sensitive' => false,
            'version' => 1,
            'status' => 'active',
            'scan_status' => 'scanned_clean',
            'uploaded_by' => null,
        ]);
    }

    private function vaultHeaders($user): array
    {
        return $this->authHeaders($this->tokenFor($user));
    }

    public function test_document_listing_never_returns_another_agencys_documents(): void
    {
        $agencyA = $this->makeAgency('Vault Agency A');
        $agencyB = $this->makeAgency('Vault Agency B');
        [$userA] = $this->agencyUser(['documents.view'], 'document_viewer', $agencyA);

        $this->documentFor($agencyA, 'doc_agency_a_private', 'A private deed');
        $this->documentFor($agencyB, 'doc_agency_b_private', 'B confidential deed');

        $response = $this->getJson('/api/agency/documents', $this->vaultHeaders($userA))
            ->assertOk()
            ->assertJsonPath('success', true);

        $ids = array_column($response->json('documents'), 'id');
        $this->assertContains('doc_agency_a_private', $ids);
        $this->assertNotContains('doc_agency_b_private', $ids);
        $this->assertStringNotContainsString('B confidential deed', $response->getContent());
    }

    public function test_foreign_document_id_is_denied_for_all_private_vault_operations(): void
    {
        $agencyA = $this->makeAgency('Vault Requester Agency');
        $agencyB = $this->makeAgency('Vault Owner Agency');
        [$userA] = $this->agencyUser([
            'documents.view',
            'documents.download',
            'documents.upload',
            'documents.update',
            'documents.delete',
            'documents.restore',
            'documents.manage_permissions',
        ], 'document_manager', $agencyA);

        $foreign = $this->documentFor($agencyB, 'doc_foreign_tenant_001', 'Agency B confidential record');
        $headers = $this->vaultHeaders($userA);
        $id = $foreign->id;

        // Reads: metadata, version history, preview, download and access logs.
        $this->getJson('/api/agency/documents', $headers)->assertOk();
        $this->getJson('/api/agency/documents/' . $id . '/versions', $headers)->assertNotFound();
        $this->get('/api/agency/documents/' . $id . '/preview', $headers)->assertNotFound();
        $this->get('/api/agency/documents/' . $id . '/download', $headers)->assertNotFound();
        $this->getJson('/api/agency/documents/' . $id . '/access-logs', $headers)->assertNotFound();
        $this->getJson('/api/agency/documents/' . $id . '/shares', $headers)->assertNotFound();

        // Writes and lifecycle operations must not mutate the foreign row.
        $this->patchJson('/api/agency/documents/' . $id, [
            'title' => 'Agency A takeover attempt',
        ], $headers)->assertNotFound();

        $this->postJson('/api/agency/documents/' . $id . '/versions', [], $headers)
            ->assertNotFound();

        $this->postJson('/api/agency/documents/' . $id . '/shares', [], $headers)
            ->assertNotFound();

        $this->deleteJson('/api/agency/documents/' . $id . '/shares/foreign-share-id', [], $headers)
            ->assertNotFound();

        $this->deleteJson('/api/agency/documents/' . $id, [], $headers)
            ->assertNotFound();

        $this->postJson('/api/agency/documents/' . $id . '/restore', [], $headers)
            ->assertNotFound();

        $this->deleteJson('/api/agency/documents/' . $id . '/permanent', [], $headers)
            ->assertNotFound();

        $unchanged = Document::whereKey($id)->firstOrFail();
        $this->assertSame($agencyB->id, $unchanged->agency_id);
        $this->assertSame('Agency B confidential record', $unchanged->title);
        $this->assertSame('active', $unchanged->status);
        $this->assertNull($unchanged->deleted_at);
        Storage::disk('documents')->assertExists($unchanged->disk_path);
    }

    public function test_agency_b_can_still_access_its_own_document_after_agency_a_probes_it(): void
    {
        $agencyA = $this->makeAgency('Vault Probe Agency A');
        $agencyB = $this->makeAgency('Vault Owner Agency B');
        [$userA] = $this->agencyUser([
            'documents.view', 'documents.download', 'documents.manage_permissions',
        ], 'document_manager', $agencyA);
        [$userB] = $this->agencyUser([
            'documents.view', 'documents.download', 'documents.manage_permissions',
        ], 'document_manager', $agencyB);

        $document = $this->documentFor($agencyB, 'doc_b_survives_probe', 'B protected deed');

        $this->get('/api/agency/documents/' . $document->id . '/download', $this->vaultHeaders($userA))
            ->assertNotFound();

        $this->get('/api/agency/documents/' . $document->id . '/download', $this->vaultHeaders($userB))
            ->assertOk();

        $this->assertSame($agencyB->id, Document::findOrFail($document->id)->agency_id);
    }
}
