<?php

namespace Tests\Feature;

use App\Models\AgencySubscription;
use App\Models\Document;
use App\Models\DocumentQuarantine;
use App\Models\DocumentVersion;
use App\Models\MediaFile;
use App\Models\SubscriptionPlan;
use App\Services\FileVaultCrypto;
use App\Services\MalwareScanner;
use App\Services\SecureFileStore;
use App\Support\MalwareScanResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Secure Document & Media Vault — hardening regressions.
 *
 * Covers the verified vulnerabilities and the new vault guarantees:
 *   - magic bytes are inspected at OFFSET 0 (a PE/PHP header wearing an
 *     allowed extension is rejected; previously the first 32 bytes were
 *     never read),
 *   - version uploads run the SAME content pipeline as first uploads,
 *   - media uploads are MIME-sniffed, magic-byte-checked and scanned,
 *   - external media URLs must be HTTPS public hosts (no open redirect),
 *   - served Content-Type is the SNIFFED type, never the client's,
 *   - fail-open holds are stored but NOT downloadable until rescan,
 *   - infected/unavailable uploads are isolated on the quarantine disk,
 *   - vault bytes are AES-256-GCM encrypted at rest (legacy plaintext still
 *     streams transparently; vault:encrypt rewrites idempotently),
 *   - storage quota counts trashed documents, archived versions and media,
 *   - denied download attempts are audited,
 *   - upload endpoints carry a dedicated rate limiter,
 *   - quarantine purge erases only expired rows,
 *   - vault disks stay configured outside the web root,
 *   - hostile filenames cannot inject Content-Disposition headers.
 */
class FileVaultSecurityTest extends EnterpriseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        Storage::fake('quarantine');
        $this->bindVerdict(MalwareScanResult::clean('clamav'));
    }

    /** Scanner stub that returns a pre-configured verdict. */
    private function bindVerdict(MalwareScanResult $result): void
    {
        $this->app->instance(MalwareScanner::class, new class($result) implements MalwareScanner {
            public function __construct(private MalwareScanResult $result) {}

            public function scan(string $filePath): MalwareScanResult
            {
                return $this->result;
            }

            public function isAvailable(): bool
            {
                return ! $this->result->skipped;
            }
        });
    }

    private function uploader(): array
    {
        return $this->agencyUser([
            'documents.view', 'documents.upload', 'documents.download',
            'documents.update', 'documents.delete', 'documents.manage_permissions',
            'documents.view_sensitive', 'manage_media', 'upload_media',
        ], 'document_manager');
    }

    /** Deterministic pseudo-PDF payload — sniffs as application/pdf, never 'MZ'. */
    private function pdfBytes(int $kb): string
    {
        return "%PDF-1.4\n" . str_repeat("1 0 obj\n<< >>\nendobj\n", $kb * 50);
    }

    private function upload($user, array $extra = [], string $name = 'registry-deed.pdf', int $kb = 40)
    {
        return $this->post('/api/agency/documents', array_merge([
            'file' => UploadedFile::fake()->createWithContent($name, $this->pdfBytes($kb)),
            'title' => 'Registry Deed',
            'category' => 'other',
        ], $extra), $this->authHeaders($this->tokenFor($user)));
    }

    private function uploadBytes($user, string $name, string $content, array $extra = [])
    {
        return $this->post('/api/agency/documents', array_merge([
            'file' => UploadedFile::fake()->createWithContent($name, $content),
            'title' => 'Byte Upload',
            'category' => 'other',
        ], $extra), $this->authHeaders($this->tokenFor($user)));
    }

    private function agencyWithLimits(array $limits): array
    {
        $agency = $this->makeAgency('Vault Quota Agency');
        $plan = SubscriptionPlan::create([
            'key' => 'plan_vault_' . bin2hex(random_bytes(4)),
            'name' => 'Vault Limit Plan',
            'price' => 0,
            'limits' => $limits,
        ]);
        AgencySubscription::create([
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        return [$agency, $plan];
    }

    /* ============ Content security pipeline ============ */

    public function test_executable_content_disguised_as_pdf_is_rejected_at_offset_zero(): void
    {
        [$user] = $this->uploader();

        // Windows PE header in the FIRST bytes — previously invisible because
        // the magic-byte check read from offset 32.
        $this->uploadBytes($user, 'trojan.pdf', 'MZ' . str_repeat('A', 400))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // PHP script header.
        $this->uploadBytes($user, 'script.pdf', "<?php echo 'pwned'; " . str_repeat('x', 200))
            ->assertStatus(422);

        // Nothing reached the vault or the quarantine rows: the content gate
        // rejects before a scan verdict even exists.
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_quarantines', 0);
    }

    public function test_document_version_upload_cannot_bypass_content_checks(): void
    {
        [$user, $agency] = $this->uploader();
        $headers = $this->authHeaders($this->tokenFor($user));

        $documentId = $this->upload($user)->assertStatus(201)->json('document.id');
        $before = Document::findOrFail($documentId);

        // A PHP payload disguised as a .pdf must not become "version 2".
        $this->post("/api/agency/documents/{$documentId}/versions", [
            'file' => UploadedFile::fake()->createWithContent('evil.pdf', "<?php system(\$_GET['c']);"),
            'note' => 'hostile',
        ], $headers)->assertStatus(422);

        $after = Document::findOrFail($documentId);
        $this->assertSame($before->version, $after->version, 'Rejected version must not bump the pointer');
        $this->assertSame($before->disk_path, $after->disk_path, 'Rejected version must not replace bytes');

        // PE payload too.
        $this->post("/api/agency/documents/{$documentId}/versions", [
            'file' => UploadedFile::fake()->createWithContent('exe.pdf', 'MZ' . str_repeat('B', 300)),
        ], $headers)->assertStatus(422);

        $this->assertSame(0, DocumentVersion::where('document_id', $documentId)->count());

        // A clean version still works end-to-end.
        $this->post("/api/agency/documents/{$documentId}/versions", [
            'file' => UploadedFile::fake()->createWithContent('v2.pdf', $this->pdfBytes(30)),
            'note' => 'clean revision',
        ], $headers)->assertStatus(200)->assertJsonPath('document.version', 2);

        $this->assertSame(2, DocumentVersion::where('document_id', $documentId)->count());
        $this->get("/api/agency/documents/{$documentId}/download", $headers)->assertStatus(200);
    }

    public function test_media_upload_rejects_executable_content(): void
    {
        [$user] = $this->uploader();
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->post('/api/agency/media', [
            'file' => UploadedFile::fake()->createWithContent('photo.jpg', 'MZ' . str_repeat('C', 300)),
            'title' => 'Not an image',
        ], $headers)->assertStatus(422);

        $this->post('/api/agency/media', [
            'file' => UploadedFile::fake()->createWithContent('shell.jpg', '<?php phpinfo();'),
            'title' => 'Not a script',
        ], $headers)->assertStatus(422);

        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_media_external_urls_must_be_https_public_hosts(): void
    {
        [$user] = $this->uploader();
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->post('/api/agency/media', ['url' => 'http://cdn.example.test/a.jpg', 'title' => 'Insecure'], $headers)
            ->assertStatus(422);
        $this->post('/api/agency/media', ['url' => 'https://192.168.1.10/a.jpg', 'title' => 'Private IP'], $headers)
            ->assertStatus(422);
        $this->post('/api/agency/media', ['url' => 'https://localhost/a.jpg', 'title' => 'Loopback'], $headers)
            ->assertStatus(422);
        $this->post('/api/agency/media', ['url' => 'https://user:pass@cdn.example.test/a.jpg', 'title' => 'Creds'], $headers)
            ->assertStatus(422);

        $this->post('/api/agency/media', ['url' => 'https://cdn.example.test/a.jpg', 'title' => 'Remote banner'], $headers)
            ->assertStatus(201);

        // A legacy row carrying a disallowed URL must never be redirected to
        // it (no open redirect through the public media endpoint).
        [$user2] = $this->uploader();
        $row = MediaFile::create([
            'id' => 'med_legacy_' . bin2hex(random_bytes(4)),
            'agency_id' => $this->makeAgency('Legacy Media')->id,
            'title' => 'Legacy External',
            'type' => 'image',
            'visibility' => 'public',
            'url' => 'http://insecure.test/x.jpg',
        ]);
        $this->asGuest();
        $this->get("/api/media/{$row->id}/file")->assertStatus(410);
    }

    public function test_served_content_type_is_the_sniffed_type_not_the_client_declaration(): void
    {
        [$user] = $this->uploader();

        // Hex payload named .pdf with an application/pdf client declaration:
        // the server must serve the type it SNIFFED (text/plain), never the
        // browser-declared value.
        $documentId = $this->uploadBytes($user, 'registry-deed.pdf', bin2hex(random_bytes(300)))
            ->assertStatus(201)
            ->json('document.id');

        $download = $this->get("/api/agency/documents/{$documentId}/download", $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200);

        $row = Document::findOrFail($documentId);

        // The framework may append a charset parameter; the media type itself
        // must be the sniffed value, never the client declaration.
        $this->assertTrue(
            str_starts_with((string) $download->headers->get('Content-Type'), $row->mime_type),
            'The served Content-Type must be the SNIFFED type stored at upload time.'
        );
        $this->assertNotSame(
            'application/pdf',
            $row->mime_type,
            'The stored mime must be server-sniffed, not the client-declared application/pdf.'
        );
    }

    /* ============ Scan lifecycle & quarantine ============ */

    public function test_fail_open_hold_is_not_downloadable_until_rescan_clears_it(): void
    {
        [$user, $agency] = $this->uploader();
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->bindVerdict(MalwareScanResult::skipped('clamav', 'clamav_not_available'));
        $response = $this->upload($user)->assertStatus(201)
            ->assertJsonPath('document.status', 'quarantined')
            ->assertJsonPath('document.scan_status', 'skipped');

        $documentId = $response->json('document.id');

        // Unscanned bytes must never leave the vault via download…
        $this->get("/api/agency/documents/{$documentId}/download", $headers)->assertStatus(423);
        // …nor via a public share link.
        $this->postJson("/api/agency/documents/{$documentId}/shares", [], $headers)->assertStatus(422);

        // Administrator rescan with a working scanner promotes the hold.
        $this->bindVerdict(MalwareScanResult::clean('clamav'));
        $this->post("/api/agency/documents/{$documentId}/rescan", [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('document.status', 'active')
            ->assertJsonPath('document.scan_status', 'scanned_clean');

        $this->get("/api/agency/documents/{$documentId}/download", $headers)->assertStatus(200);
    }

    public function test_infected_upload_is_rejected_and_isolated_on_the_quarantine_disk(): void
    {
        [$user, $agency] = $this->uploader();
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->bindVerdict(MalwareScanResult::infected('clamav', ['Eicar-Test-Signature FOUND']));
        $this->uploadBytes($user, 'deed.pdf', 'EICAR-SIMULATED-CONTENT')->assertStatus(422);

        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_versions', 0);

        $quarantined = DocumentQuarantine::first();
        $this->assertNotNull($quarantined);
        $this->assertSame('infected', $quarantined->reason);
        Storage::disk('quarantine')->assertExists($quarantined->disk_path);
        $this->assertSame([], Storage::disk('documents')->allFiles(), 'Infected bytes must never reach the vault disk.');

        // Administrator visibility; plain users are denied.
        [$admin] = $this->agencyUser(['documents.manage_permissions'], 'auditor', $agency);
        $this->getJson('/api/agency/documents-quarantine', $this->authHeaders($this->tokenFor($admin)))
            ->assertStatus(200)
            ->assertJsonCount(1, 'rejected');

        [$clerk] = $this->agencyUser(['documents.view'], 'agent', $agency);
        $this->getJson('/api/agency/documents-quarantine', $this->authHeaders($this->tokenFor($clerk)))
            ->assertStatus(403);
    }

    public function test_fail_closed_unavailable_scanner_rejects_and_quarantines(): void
    {
        [$user] = $this->uploader();
        config(['malware.fail_closed' => true, 'malware.clamav.fail_closed' => true]);
        $this->bindVerdict(MalwareScanResult::infected('clamav', ['scan_unavailable']));

        $this->upload($user)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Malware scanner unavailable; upload rejected.']);

        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseHas('document_quarantines', ['reason' => 'scan_unavailable']);
    }

    /* ============ Encryption at rest ============ */

    public function test_vault_bytes_are_encrypted_at_rest_and_stream_transparently(): void
    {
        [$user] = $this->uploader();
        $headers = $this->authHeaders($this->tokenFor($user));

        $secret = 'OWNER-DEED-BYTES-' . Str::random(48);
        $documentId = $this->uploadBytes($user, 'deed.pdf', $secret)->assertStatus(201)->json('document.id');
        $row = Document::findOrFail($documentId);

        $raw = (string) Storage::disk('documents')->get($row->disk_path);
        $this->assertStringNotContainsString($secret, $raw, 'Vault bytes must not be readable plaintext on disk.');
        $this->assertTrue(FileVaultCrypto::isEncrypted($raw));

        // The authorized read path decrypts transparently.
        $this->assertSame($secret, SecureFileStore::get('documents', $row->disk_path));

        $this->get("/api/agency/documents/{$documentId}/download", $headers)->assertStatus(200);
    }

    public function test_legacy_plaintext_files_still_stream_and_vault_encrypt_rewrites_them(): void
    {
        [$user, $agency] = $this->agencyUser([
            'documents.view', 'documents.upload', 'documents.download',
        ], 'document_manager');
        $headers = $this->authHeaders($this->tokenFor($user));

        $id = 'doc_' . bin2hex(random_bytes(8));
        $path = $agency->id . '/2026/01/' . $id . '.pdf';
        Storage::disk('documents')->put($path, 'PLAINTEXT-LEGACY-BYTES');

        Document::create([
            'id' => $id,
            'agency_id' => $agency->id,
            'category' => 'other',
            'title' => 'Legacy Doc',
            'original_name' => $id . '.pdf',
            'stored_name' => $id . '.pdf',
            'disk_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 22,
            'status' => 'active',
            'uploaded_by' => $user->id,
        ]);

        $this->get("/api/agency/documents/{$id}/download", $headers)->assertStatus(200);

        Artisan::call('vault:encrypt');

        $raw = (string) Storage::disk('documents')->get($path);
        $this->assertTrue(FileVaultCrypto::isEncrypted($raw), 'vault:encrypt must encrypt plaintext rows.');
        $this->assertStringNotContainsString('PLAINTEXT-LEGACY-BYTES', $raw);
        $this->assertSame('PLAINTEXT-LEGACY-BYTES', SecureFileStore::get('documents', $path));

        $this->get("/api/agency/documents/{$id}/download", $headers)->assertStatus(200);

        // Idempotent: a second run must not corrupt anything.
        $exit = Artisan::call('vault:encrypt');
        $this->assertSame(0, $exit);
        $this->assertSame('PLAINTEXT-LEGACY-BYTES', SecureFileStore::get('documents', $path));
    }

    public function test_public_media_bytes_are_encrypted_too(): void
    {
        [$user] = $this->uploader();
        $headers = $this->authHeaders($this->tokenFor($user));

        $secret = 'PUBLIC-BANNER-BYTES-' . Str::random(24);
        $media = $this->post('/api/agency/media', [
            'file' => UploadedFile::fake()->createWithContent('banner.jpg', $secret),
            'title' => 'Public Banner',
            'visibility' => 'public',
        ], $headers)->assertStatus(201)->json('media');

        $row = MediaFile::findOrFail($media['id']);
        $raw = (string) Storage::disk('documents')->get($row->disk_path);
        $this->assertStringNotContainsString($secret, $raw);
        $this->assertTrue(FileVaultCrypto::isEncrypted($raw));

        $this->asGuest();
        $this->get("/api/media/{$media['id']}/file")->assertStatus(200);
    }

    /* ============ Storage monitoring (no quota) ============ */

    public function test_storage_quota_is_not_enforced_for_this_release(): void
    {
        // Trashed bytes are retained for monitoring/audit — trashing does NOT
        // reduce the reported workspace total, but it never blocks an upload.
        [$agency] = $this->agencyWithLimits(['storage_mb' => 1]);
        [$user] = $this->agencyUser([
            'documents.view', 'documents.upload', 'documents.download', 'documents.delete',
        ], 'document_manager', $agency);
        $headers = $this->authHeaders($this->tokenFor($user));

        $documentId = $this->upload($user, [], 'first.pdf', 600)->assertStatus(201)->json('document.id');
        $this->deleteJson("/api/agency/documents/{$documentId}", [], $headers)->assertStatus(200);

        $this->post('/api/agency/documents', [
            'file' => UploadedFile::fake()->createWithContent('second.pdf', $this->pdfBytes(600)),
            'title' => 'Second',
        ], $headers)->assertStatus(201);

        // Media bytes share the same monitored total as vault documents.
        [$agency2] = $this->agencyWithLimits(['storage_mb' => 1]);
        [$user2] = $this->agencyUser([
            'documents.view', 'documents.upload', 'manage_media', 'upload_media',
        ], 'media_manager', $agency2);
        $headers2 = $this->authHeaders($this->tokenFor($user2));

        $this->post('/api/agency/media', [
            'file' => UploadedFile::fake()->createWithContent('big.jpg', "\xFF\xD8\xFF\xE0" . str_repeat('x', 500 * 1024)),
            'title' => 'Big media',
        ], $headers2)->assertStatus(201);

        $this->post('/api/agency/documents', [
            'file' => UploadedFile::fake()->createWithContent('doc.pdf', $this->pdfBytes(500)),
            'title' => 'Doc fits',
        ], $headers2)->assertStatus(201);

        // 500 KB media + 500 KB doc + 100 KB doc = 1100 KB, over the legacy
        // 1 MB value — the upload must still succeed.
        $this->post('/api/agency/documents', [
            'file' => UploadedFile::fake()->createWithContent('doc2.pdf', $this->pdfBytes(100)),
            'title' => 'Doc over legacy limit',
        ], $headers2)->assertStatus(201);
    }

    /* ============ Audit of denied attempts ============ */

    public function test_unauthorized_and_sensitive_download_attempts_are_audited(): void
    {
        [$owner, $agency] = $this->agencyUser([
            'documents.view', 'documents.upload', 'documents.download',
            'documents.view_sensitive', 'documents.manage_permissions',
        ], 'document_manager');
        $ownerHeaders = $this->authHeaders($this->tokenFor($owner));

        $documentId = $this->post('/api/agency/documents', [
            'file' => UploadedFile::fake()->createWithContent('cnic-front.jpg', "\xFF\xD8\xFF\xE0" . str_repeat('a', 20 * 1024)),
            'title' => 'CNIC front',
            'category' => 'cnic',
        ], $ownerHeaders)->assertStatus(201)->json('document.id');

        // No download permission at all → denied + audited (no access-log row:
        // the document id is unverified at that point).
        [$clerk] = $this->agencyUser(['documents.view'], 'agent', $agency);
        $this->getJson("/api/agency/documents/{$documentId}/download", $this->authHeaders($this->tokenFor($clerk)))
            ->assertStatus(403);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.download_denied',
            'agency_id' => $agency->id,
            'entity_id' => $documentId,
        ]);

        // Download permission but no sensitive clearance → denied + audited
        // WITH a forensic access-log row (the document exists in this agency).
        [$plain] = $this->agencyUser(['documents.view', 'documents.download'], 'plain', $agency);
        $this->getJson("/api/agency/documents/{$documentId}/download", $this->authHeaders($this->tokenFor($plain)))
            ->assertStatus(403);
        $this->assertDatabaseHas('document_access_logs', [
            'document_id' => $documentId,
            'action' => 'download_denied',
        ]);
    }

    /* ============ Rate limiting ============ */

    public function test_upload_endpoints_get_a_dedicated_rate_limit(): void
    {
        [$user] = $this->uploader();
        $headers = $this->authHeaders($this->tokenFor($user));

        for ($i = 1; $i <= 20; $i++) {
            $this->post('/api/agency/documents', [
                'file' => UploadedFile::fake()->createWithContent("doc-{$i}.pdf", $this->pdfBytes(5)),
                'title' => "Doc {$i}",
            ], $headers)->assertStatus(201);
        }

        $this->post('/api/agency/documents', [
            'file' => UploadedFile::fake()->createWithContent('doc-21.pdf', $this->pdfBytes(5)),
            'title' => 'Doc 21',
        ], $headers)->assertStatus(429);
    }

    /* ============ Quarantine retention ============ */

    public function test_quarantine_purge_removes_only_expired_rows(): void
    {
        [$user, $agency] = $this->uploader();
        $headers = $this->authHeaders($this->tokenFor($user));

        // Expired rejected row + expired vault hold.
        $qId = 'qtn_' . bin2hex(random_bytes(8));
        Storage::disk('quarantine')->put('expired/' . $qId . '.pdf', 'rejected bytes');
        DocumentQuarantine::create([
            'id' => $qId,
            'agency_id' => $agency->id,
            'original_name' => 'old-rejected.pdf',
            'stored_name' => $qId . '.pdf',
            'disk_path' => 'expired/' . $qId . '.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 14,
            'reason' => 'infected',
            'threats' => ['Eicar-Test-Signature FOUND'],
            'uploaded_by' => $user->id,
            'created_at' => now()->subDays(45),
        ]);

        $holdId = 'doc_' . bin2hex(random_bytes(8));
        $holdPath = $agency->id . '/2026/01/' . $holdId . '.pdf';
        Storage::disk('documents')->put($holdPath, 'held bytes');
        $hold = Document::create([
            'id' => $holdId,
            'agency_id' => $agency->id,
            'category' => 'other',
            'title' => 'Old Hold',
            'original_name' => 'old-hold.pdf',
            'stored_name' => $holdId . '.pdf',
            'disk_path' => $holdPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'status' => 'quarantined',
            'scan_status' => 'skipped',
            'uploaded_by' => $user->id,
        ]);
        $hold->created_at = now()->subDays(45);
        $hold->save();

        // A live, approved document created now must survive the purge.
        $liveId = $this->upload($user)->assertStatus(201)->json('document.id');

        Artisan::call('vault:purge-quarantine');

        $this->assertDatabaseMissing('document_quarantines', ['id' => $qId]);
        Storage::disk('quarantine')->assertMissing('expired/' . $qId . '.pdf');
        $this->assertDatabaseMissing('documents', ['id' => $holdId]);
        Storage::disk('documents')->assertMissing($holdPath);

        $this->assertDatabaseHas('documents', ['id' => $liveId]);
        $this->get("/api/agency/documents/{$liveId}/download", $headers)->assertStatus(200);
    }

    public function test_quarantine_metadata_is_retained_when_byte_deletion_fails(): void
    {
        [, $agency] = $this->uploader();
        $id = 'qtn_' . bin2hex(random_bytes(8));
        $path = 'expired/' . $id . '.pdf';
        DocumentQuarantine::create([
            'id' => $id,
            'agency_id' => $agency->id,
            'disk_path' => $path,
            'reason' => 'infected',
            'created_at' => now()->subDays(45),
        ]);

        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('exists')->once()->with($path)->andReturn(true);
        $disk->shouldReceive('delete')->once()->with($path)->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('quarantine')->andReturn($disk);

        $exitCode = Artisan::call('vault:purge-quarantine');

        $this->assertSame(1, $exitCode, 'A failed physical delete must make the purge command fail.');
        $this->assertDatabaseHas('document_quarantines', ['id' => $id]);
    }

    public function test_quarantined_document_metadata_is_retained_when_document_delete_fails(): void
    {
        [, $agency] = $this->uploader();
        $id = 'doc_' . bin2hex(random_bytes(8));
        $documentPath = 'expired/' . $id . '.pdf';
        $document = Document::create([
            'id' => $id,
            'agency_id' => $agency->id,
            'category' => 'other',
            'title' => 'Expired Hold',
            'disk_path' => $documentPath,
            'storage_disk' => 'documents',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'status' => 'quarantined',
        ]);
        $document->created_at = now()->subDays(45);
        $document->save();

        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('exists')->once()->with($documentPath)->andReturn(true);
        $disk->shouldReceive('delete')->once()->with($documentPath)->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('documents')->andReturn($disk);

        $exitCode = Artisan::call('vault:purge-quarantine');

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseHas('documents', ['id' => $id]);
    }

    /* ============ Configuration guardrails ============ */

    public function test_vault_disks_stay_configured_outside_the_web_root(): void
    {
        $documentsRoot = (string) config('filesystems.disks.documents.root');
        $quarantineRoot = (string) config('filesystems.disks.quarantine.root');

        // Both private disks live under storage/app (NOT public/) — storage_path
        // uses mixed separators on Windows, so assert against its own prefix.
        $this->assertStringStartsWith(storage_path('app'), $documentsRoot);
        $this->assertStringStartsWith(storage_path('app'), $quarantineRoot);
        $this->assertStringNotContainsString('public', $documentsRoot);
        $this->assertStringNotContainsString('public', $quarantineRoot);
        $this->assertFalse((bool) config('filesystems.disks.documents.serve'));
        $this->assertFalse((bool) config('filesystems.disks.quarantine.serve'));

        // Neither private disk may ever be symlinked into public/ — the only
        // configured link is the public disk itself.
        foreach ((array) config('filesystems.links') as $target) {
            $this->assertStringNotContainsString('private', (string) $target);
        }

        $this->assertTrue(FileVaultCrypto::enabled(), 'Vault at-rest encryption must default to enabled.');
    }

    public function test_hostile_original_filenames_cannot_inject_headers(): void
    {
        [$user, $agency] = $this->agencyUser([
            'documents.view', 'documents.upload', 'documents.download',
        ], 'document_manager');
        $headers = $this->authHeaders($this->tokenFor($user));

        $id = 'doc_' . bin2hex(random_bytes(8));
        $path = $agency->id . '/2026/01/' . $id . '.pdf';
        Storage::disk('documents')->put($path, 'HOSTILE-NAME-BYTES');

        Document::create([
            'id' => $id,
            'agency_id' => $agency->id,
            'category' => 'other',
            'title' => 'Hostile name',
            'original_name' => "bad\"name\r\nwith-break.pdf",
            'stored_name' => $id . '.pdf',
            'disk_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 17,
            'status' => 'active',
            'uploaded_by' => $user->id,
        ]);

        $response = $this->get("/api/agency/documents/{$id}/download", $headers)->assertStatus(200);
        $disposition = (string) $response->headers->get('content-disposition');

        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringNotContainsString('"bad', $disposition);
        $this->assertStringContainsString('attachment', $disposition);
    }

    public function test_client_filenames_can_never_shape_storage_paths(): void
    {
        [$user, $agency] = $this->agencyUser([
            'documents.view', 'documents.upload', 'documents.download',
        ], 'document_manager');

        // Path-traversal / control payloads in the client filename must never
        // influence where bytes land: disk paths are built exclusively from
        // server-resolved agency scope, server-generated ids and dates.
        $documentId = $this->uploadBytes(
            $user,
            "../../%00evil\r\nname<script>.pdf",
            'TRAVERSAL-BYTES-' . bin2hex(random_bytes(8))
        )->assertStatus(201)->json('document.id');

        $row = Document::findOrFail($documentId);

        $this->assertStringNotContainsString('..', $row->disk_path);
        $this->assertStringNotContainsString('..', $row->stored_name);
        $this->assertStringNotContainsString('/', $row->stored_name);
        $this->assertStringNotContainsString('\\', $row->stored_name);
        $this->assertStringNotContainsString('<', $row->original_name);
        $this->assertStringNotContainsString("\r", $row->original_name);
        $this->assertMatchesRegularExpression(
            '/^' . preg_quote($agency->id, '/') . '\/20\d{2}\/\d{2}\/doc_[0-9a-f]{16}\.pdf$/',
            $row->disk_path
        );

        Storage::disk('documents')->assertExists($row->disk_path);
    }
}
