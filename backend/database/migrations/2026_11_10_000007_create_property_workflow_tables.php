<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Property workflow history, user favorites and view analytics. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_status_history', function (Blueprint $table) {
            $table->id();
            $table->string('property_id');            // matches properties.id (varchar 256)
            $table->string('agency_id', 64)->nullable()->index();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['property_id', 'created_at']);
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::create('property_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('property_id');
            $table->timestamps();

            $table->unique(['user_id', 'property_id']);
            $table->index('property_id');
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::create('property_views', function (Blueprint $table) {
            $table->id();
            $table->string('property_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['property_id', 'created_at']);
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_views');
        Schema::dropIfExists('property_favorites');
        Schema::dropIfExists('property_status_history');
    }
};
