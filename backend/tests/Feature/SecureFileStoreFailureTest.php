<?php

namespace Tests\Feature;

use App\Services\DisabledMalwareScanner;
use App\Services\FileVaultCrypto;
use App\Services\SecureFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SecureFileStoreFailureTest extends TestCase
{
    public function test_upload_throws_when_private_disk_write_fails(): void
    {
        config(['vault.encryption.enabled' => false]);

        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->with('agency/file.bin', 'payload')->andReturn(false);
        $disk->shouldReceive('exists')->once()->with('agency/file.bin')->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('documents')->andReturn($disk);

        $this->expectException(RuntimeException::class);
        SecureFileStore::storeUpload(
            'documents',
            'agency/file.bin',
            UploadedFile::fake()->createWithContent('file.pdf', 'payload')
        );
    }

    public function test_move_never_deletes_source_when_destination_write_fails(): void
    {
        config(['vault.encryption.enabled' => false]);

        $source = Mockery::mock();
        $source->shouldReceive('exists')->once()->with('agency/source.bin')->andReturn(true);
        $source->shouldReceive('get')->once()->with('agency/source.bin')->andReturn('plain bytes');
        $source->shouldNotReceive('delete');

        $target = Mockery::mock();
        $target->shouldReceive('put')->once()->with('agency/target.bin', 'plain bytes')->andReturn(false);
        $target->shouldReceive('exists')->once()->with('agency/target.bin')->andReturn(false);
        $target->shouldNotReceive('delete');

        Storage::shouldReceive('disk')->with('documents')->once()->andReturn($source);
        Storage::shouldReceive('disk')->with('quarantine')->once()->andReturn($target);

        $this->expectException(RuntimeException::class);
        SecureFileStore::move('documents', 'agency/source.bin', 'quarantine', 'agency/target.bin');
    }

    public function test_rescan_returns_skipped_when_vault_bytes_cannot_be_decrypted(): void
    {
        config(['vault.encryption.enabled' => true]);
        Storage::fake('documents');
        Storage::disk('documents')->put('agency/broken.bin', FileVaultCrypto::ENVELOPE . 'malformed');

        $result = SecureFileStore::scanStored('documents', 'agency/broken.bin', new DisabledMalwareScanner());

        $this->assertFalse($result->isClean);
        $this->assertTrue($result->skipped);
        $this->assertSame('vault_read_failed', $result->skipReason);
    }
}
