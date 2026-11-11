<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRIVATE DOCUMENT VAULT (most sensitive tables in the platform).
 *
 * Files live on the private `documents` disk (storage/app/private/documents)
 * which is outside the web root and never served statically. Access happens
 * only through authenticated, policy-checked, audit-logged endpoints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('agency_id', 64)->index();
            $table->string('property_id')->nullable(); // properties.id (varchar 256)
            $table->string('parent_id', 64)->nullable()->index();
            $table->string('name');
            $table->string('path');                    // materialised path, unique per agency
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agency_id', 'path']);
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('document_folders')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('agency_id', 64)->index();
            $table->string('folder_id', 64)->nullable();
            $table->string('property_id')->nullable();
            $table->string('category', 60)->default('other'); // cnic|registry|allotment_letter|transfer_letter|ownership|sale_agreement|payment_receipt|booking_form|map_site_plan|image|video|pdf|other
            $table->string('title');
            $table->string('original_name');
            $table->string('stored_name');            // randomised server-side name
            $table->string('disk_path');              // relative path on private disk (NEVER exposed via API)
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64)->nullable(); // sha256
            $table->boolean('is_sensitive')->default(false); // cnic/registry/ownership etc.
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('active'); // active|trashed
            $table->json('tags')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'property_id']);
            $table->index(['agency_id', 'category']);
            $table->index(['agency_id', 'folder_id']);
            $table->index(['agency_id', 'checksum']);
            $table->foreign('agency_id')->references('id')->on('agencies')->restrictOnDelete();
            $table->foreign('folder_id')->references('id')->on('document_folders')->nullOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->string('document_id', 64)->index();
            $table->unsignedInteger('version');
            $table->string('stored_name');
            $table->string('disk_path');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'version']);
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
        });

        Schema::create('document_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('document_id', 64)->index();
            $table->string('agency_id', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30); // view|preview|download|upload|update|delete|restore|permanent_delete
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['agency_id', 'created_at']);
            $table->index(['document_id', 'created_at']);
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_logs');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_folders');
    }
};
