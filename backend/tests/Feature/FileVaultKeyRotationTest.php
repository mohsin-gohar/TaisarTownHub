<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Services\FileVaultCrypto;
use App\Services\SecureFileStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\EnterpriseTestCase;

class FileVaultKeyRotationTest extends EnterpriseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        Storage::fake('quarantine');
    }

    public function test_previous_app_key_files_are_reencrypted_with_the_active_key(): void
    {
        $agency = $this->makeAgency('Vault Key Rotation Agency');
        $id = 'doc_rotation_' . bin2hex(random_bytes(5));
        $path = $agency->id . '/2026/11/' . $id . '.pdf';
        $plain = 'KEY-ROTATION-PLAINTEXT-' . bin2hex(random_bytes(16));
        $oldKey = 'base64:old-vault-test-key-32-bytes-000000000';
        $newKey = 'base64:new-vault-test-key-32-bytes-000000000';

        config([
            'app.key' => $oldKey,
            'app.previous_keys' => [],
            'vault.encryption.enabled' => true,
        ]);
        $oldEnvelope = FileVaultCrypto::encrypt($plain);
        Storage::disk('documents')->put($path, $oldEnvelope);

        Document::create([
            'id' => $id,
            'agency_id' => $agency->id,
            'category' => 'other',
            'title' => 'Rotation fixture',
            'original_name' => 'rotation.pdf',
            'stored_name' => $id . '.pdf',
            'disk_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($plain),
            'status' => 'active',
            'uploaded_by' => null,
        ]);

        config([
            'app.key' => $newKey,
            'app.previous_keys' => [$oldKey],
        ]);

        $this->assertSame($plain, SecureFileStore::get('documents', $path));
        $this->assertFalse(FileVaultCrypto::isEncryptedWithCurrentKey($oldEnvelope));

        $this->assertSame(0, Artisan::call('vault:encrypt', ['--disk' => 'documents']));

        $newEnvelope = (string) Storage::disk('documents')->get($path);
        $this->assertNotSame($oldEnvelope, $newEnvelope);
        $this->assertTrue(FileVaultCrypto::isEncryptedWithCurrentKey($newEnvelope));

        // After successful migration, the old key is no longer required to
        // read this file. This is the gate before APP_PREVIOUS_KEYS is removed.
        config(['app.previous_keys' => []]);
        $this->assertSame($plain, SecureFileStore::get('documents', $path));
    }
}
