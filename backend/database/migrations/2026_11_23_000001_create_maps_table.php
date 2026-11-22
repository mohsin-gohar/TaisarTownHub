<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maps', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->string('sector', 120)->nullable();
            $table->string('phase', 60)->nullable();
            $table->string('category', 40)->default('sector_map');
            $table->text('description')->nullable();
            $table->string('image_path', 500);
            $table->string('image_disk', 40)->default('public');
            $table->string('image_mime', 60)->nullable();
            $table->unsignedBigInteger('image_size_bytes')->nullable();
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->string('image_checksum', 64)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'display_order']);
            $table->index(['category', 'is_active']);
            $table->index('sector');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maps');
    }
};
