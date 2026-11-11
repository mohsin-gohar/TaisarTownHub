<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agency media studio. Media marked `visibility=public` may be referenced by
 * public listings; `private` media never leaves the authenticated agency API.
 * Sale-attribution fields preserve the existing "Mark as sold" workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('agency_id', 64)->index();
            $table->string('property_id')->nullable();
            $table->string('folder')->default('General');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 30)->default('image'); // image|video|document|banner|brochure|floor_plan
            $table->string('original_name')->nullable();
            $table->string('stored_name')->nullable();
            $table->string('disk_path')->nullable();      // private/local disk relative path (hidden from API)
            $table->string('url')->nullable();            // external URL or generated public URL
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('thumbnail_url')->nullable();
            $table->string('dimensions')->nullable();
            $table->string('visibility', 10)->default('private'); // public|private
            $table->json('tags')->nullable();
            $table->string('status', 20)->default('available');   // available|sold
            $table->timestamp('sold_at')->nullable();
            $table->decimal('sold_price_pk', 14, 2)->nullable();
            $table->decimal('cost_price_pk', 14, 2)->nullable();
            $table->decimal('profit_pk', 14, 2)->default(0);
            $table->string('buyer_name')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->string('buyer_national_id_masked')->nullable();
            $table->string('sold_by')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'visibility']);
            $table->index(['agency_id', 'type']);
            $table->index(['agency_id', 'status']);
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
