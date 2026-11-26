<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secure Document & Media Vault — scan lifecycle + quarantine.
 *
 * Upload lifecycle: UPLOADING → QUARANTINED → SCANNING → APPROVED / REJECTED.
 * Synchronous pipeline, so a stored row is either `scanned_clean` (approved),
 * `skipped` (scanner unavailable while the app runs fail-open) or — when the
 * scanner returns a verdict — never stored in the vault at all (rejected
 * uploads land on the isolated `quarantine` disk + `document_quarantines`
 * table for administrator visibility instead).
 *
 * Legacy rows (written before this migration) default to `scanned_clean`:
 * every pre-existing row was stored by the synchronous scanned pipeline and
 * must remain downloadable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('scan_status', 20)->default('scanned_clean')->after('status');
            $table->json('scan_threats')->nullable()->after('scan_status');
            $table->timestamp('scan_completed_at')->nullable()->after('scan_threats');
            $table->string('scanner', 30)->nullable()->after('scan_completed_at');
            $table->string('storage_disk', 20)->default('documents')->after('disk_path');
        });

        Schema::table('media_files', function (Blueprint $table) {
            $table->string('scan_status', 20)->nullable()->after('status');
            $table->timestamp('scan_completed_at')->nullable()->after('scan_status');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->string('storage_disk', 20)->default('documents')->after('disk_path');
        });

        Schema::create('document_quarantines', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('agency_id', 64);
            $table->string('document_id', 64)->nullable();
            $table->string('original_name')->nullable();
            $table->string('stored_name')->nullable();
            $table->string('disk_path')->nullable(); // relative path on the quarantine disk (NEVER exposed)
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum', 64)->nullable();
            $table->string('reason', 40); // infected | scan_unavailable
            $table->boolean('is_sensitive')->default(false)->index();
            $table->json('threats')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
            $table->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_quarantines');
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn(['storage_disk']);
        });
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn(['scan_status', 'scan_completed_at']);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['scan_status', 'scan_threats', 'scan_completed_at', 'scanner', 'storage_disk']);
        });
    }
};
