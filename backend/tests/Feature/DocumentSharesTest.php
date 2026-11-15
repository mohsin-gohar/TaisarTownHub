<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\EnterpriseTestCase;

/**
 * G-10 — public share links for vault documents.
 *
 * Tokens are capabilities: the API stores only the SHA-256 hash, the plain
 * token is returned exactly once, and expiry / revocation / download caps
 * are enforced on every public hit. Minting and revoking links requires the
 * "manage document sharing" permission — plain downloaders cannot mint
 * public access to the vault.
 */
class DocumentSharesTest extends EnterpriseTestCase
{
    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $this->agency = $this->makeAgency('Share Agency');
    }

    private function makeDocument(bool $sensitive = false): Document
    {
        $id = 'doc_' . bin2hex(random_bytes(6));
        $path = $this->agency->id . '/2026/01/' . $id . '.pdf';
        Storage::disk('documents')->put($path, 'SECRET-PDF-' . $id);

        return Document::create([
            'id' => $id,
            'agency_id' => $this->agency->id,
            'category' => $sensitive ? 'registry' : 'other',
            'title' => 'Share Doc ' . $id,
            'original_name' => $id . '.pdf',
            'stored_name' => $id . '.pdf',
            'disk_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 1234,
            'is_sensitive' => $sensitive,
            'status' => 'active',
        ]);
    }

    private function sharer(array $perms = ['documents.view', 'documents.download', 'documents.manage_permissions']): User
    {
        [$user] = $this->agencyUser($perms, 'document_manager', $this->agency);

        return $user;
    }

    private function createShare(Document $doc, User $user, array $payload = []): string
    {
        $response = $this->postJson('/api/agency/documents/' . $doc->id . '/shares', $payload, $this->authHeaders($this->tokenFor($user)));
        $response->assertStatus(201);

        return (string) $response->json('token');
    }

    public function test_share_creation_returns_the_token_once_and_stores_only_its_hash(): void
    {
        $user = $this->sharer();
        $doc = $this->makeDocument();

        $response = $this->postJson('/api/agency/documents/' . $doc->id . '/shares', [
            'expires_in_days' => 7,
            'max_downloads' => 5,
            'note' => 'For buyer handover',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $token = (string) $response->json('token');
        $this->assertNotSame('', $token);
        $this->assertTrue(
            \Carbon\Carbon::parse($response->json('share.expires_at'))->greaterThan(now()->addDays(6)),
            'expires_at must be about a week out'
        );

        // Only the hash is persisted — the plain token must not exist anywhere.
        $row = DB::table('document_shares')->where('id', $response->json('share.id'))->first();
        $this->assertSame(hash('sha256', $token), $row->token_hash);
        $this->assertStringNotContainsString($token, (string) $row->token_hash);
        $this->assertSame(5, $row->max_downloads);

        // Listing never leaks the token or its hash.
        $list = $this->getJson('/api/agency/documents/' . $doc->id . '/shares', $this->authHeaders($this->tokenFor($user)))
            ->assertOk();
        $this->assertStringNotContainsString($token, $list->getContent());
        $this->assertStringNotContainsString((string) $row->token_hash, $list->getContent());
        $this->assertSame(1, count($list->json('shares')));
        $this->assertSame('For buyer handover', $list->json('shares.0.note'));
    }

    public function test_minting_and_revoking_shares_require_the_sharing_permission(): void
    {
        $downloader = $this->sharer(['documents.view', 'documents.download']);
        $doc = $this->makeDocument();

        $this->postJson('/api/agency/documents/' . $doc->id . '/shares', [], $this->authHeaders($this->tokenFor($downloader)))
            ->assertStatus(403);

        // The downloader also cannot revoke someone else's share.
        $manager = $this->sharer();
        $token = $this->createShare($doc, $manager);
        $shareId = DB::table('document_shares')->where('token_hash', hash('sha256', $token))->value('id');

        $this->deleteJson('/api/agency/documents/' . $doc->id . '/shares/' . $shareId, [], $this->authHeaders($this->tokenFor($downloader)))
            ->assertStatus(403);

        // …while the manager can revoke, and revocation kills the token.
        $this->deleteJson('/api/agency/documents/' . $doc->id . '/shares/' . $shareId, [], $this->authHeaders($this->tokenFor($manager)))
            ->assertOk();
        $this->assertNotNull(DB::table('document_shares')->where('id', $shareId)->value('revoked_at'));

        $this->getJson('/api/shares/' . $token . '/download')->assertStatus(410);
    }

    public function test_sensitive_document_shares_require_the_view_sensitive_permission(): void
    {
        $user = $this->sharer(['documents.view', 'documents.download', 'documents.manage_permissions']);
        $doc = $this->makeDocument(true);

        $this->postJson('/api/agency/documents/' . $doc->id . '/shares', [], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(403);
        $this->assertSame(0, DB::table('document_shares')->count());

        // Grant view_sensitive; now the share can be minted.
        [$user] = $this->agencyUser(
            ['documents.view', 'documents.download', 'documents.manage_permissions', 'documents.view_sensitive'],
            'document_manager',
            $this->agency
        );
        $token = $this->createShare($doc, $user);
        $this->getJson('/api/shares/' . $token . '/download')->assertOk();
    }

    public function test_public_download_serves_the_file_and_counts_hits(): void
    {
        $user = $this->sharer();
        $doc = $this->makeDocument();
        $token = $this->createShare($doc, $user);

        // Streamed downloads are asserted via headers (existing vault convention).
        $response = $this->get('/api/shares/' . $token . '/download')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString($doc->id . '.pdf', (string) $response->headers->get('content-disposition'));

        $row = DB::table('document_shares')->where('token_hash', hash('sha256', $token))->first();
        $this->assertSame(1, (int) $row->download_count);
        $this->assertNotNull($row->last_downloaded_at);
    }

    public function test_expired_and_download_capped_links_stop_working(): void
    {
        $user = $this->sharer();
        $doc = $this->makeDocument();

        // Expired link.
        $expiredToken = $this->createShare($doc, $user);
        DocumentShare::where('token_hash', hash('sha256', $expiredToken))
            ->update(['expires_at' => now()->subHour()]);
        $this->getJson('/api/shares/' . $expiredToken . '/download')->assertStatus(410);

        // Download cap: exactly max_downloads successes, then exhausted.
        $cappedToken = $this->createShare($doc, $user, ['max_downloads' => 1]);
        $this->get('/api/shares/' . $cappedToken . '/download')->assertOk();
        $this->getJson('/api/shares/' . $cappedToken . '/download')->assertStatus(410);
        $this->assertSame(1, (int) DB::table('document_shares')->where('token_hash', hash('sha256', $cappedToken))->value('download_count'));
    }

    public function test_unknown_or_malformed_tokens_are_rejected_and_links_are_tenant_scoped(): void
    {
        $user = $this->sharer();
        $doc = $this->makeDocument();
        $this->createShare($doc, $user);

        // Malformed (too short to be a token).
        $this->getJson('/api/shares/nope/download')->assertStatus(404);
        // Well-formed but unknown.
        $this->getJson('/api/shares/' . str_repeat('a', 48) . '/download')->assertStatus(410);

        // Another agency's staff cannot see, mint or revoke this document's shares.
        [$other] = $this->agencyUser(['documents.view', 'documents.download', 'documents.manage_permissions'], 'document_manager');
        $this->getJson('/api/agency/documents/' . $doc->id . '/shares', $this->authHeaders($this->tokenFor($other)))
            ->assertStatus(404);
        $this->postJson('/api/agency/documents/' . $doc->id . '/shares', [], $this->authHeaders($this->tokenFor($other)))
            ->assertStatus(404);
    }
}
