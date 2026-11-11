<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Location & category structures for Taisar Town (Sectors/Blocks) and
 * property categories. Seeded from existing property data + curated list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phase')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('sector_id', 64)->index();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sector_id', 'slug']);
            $table->index('slug');
            $table->foreign('sector_id')->references('id')->on('sectors')->cascadeOnDelete();
        });

        Schema::create('property_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name');
            $table->string('group', 40);       // residential|commercial|rent
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_categories');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('sectors');
    }
};
