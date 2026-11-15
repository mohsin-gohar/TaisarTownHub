<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Encryption at rest for `dealers.cnic` (full national IDs stored by dealer
 * management). The `Dealer` model now casts `cnic` to `encrypted`; this
 * migration converts pre-existing PLAINTEXT rows so the cast can decrypt
 * them. Already-encrypted values (base64 JSON payloads, "eyJ" prefix) and
 * masked values are left untouched. Idempotent — safe to re-run.
 *
 * Rollback: values remain decryptable with the same APP_KEY; re-running is a
 * no-op, so no destructive down() is provided (per backup/rollback policy).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('dealers')
            ->whereNotNull('cnic')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $value = (string) $row->cnic;
                    if ($value === '' || str_starts_with($value, 'eyJ')) {
                        continue; // empty or already encrypted
                    }
                    DB::table('dealers')
                        ->where('id', $row->id)
                        ->update(['cnic' => Crypt::encryptString($value)]);
                }
            });
    }

    public function down(): void
    {
        // No-op: do not silently decrypt/destroy data at rest.
    }
};
