<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_videos', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('videoUrl');
            $table->text('thumbnailUrl')->nullable();
            $table->string('agencyId')->nullable();
            $table->string('agencyName');
            $table->string('dealerName')->nullable();
            $table->text('dealerAvatar')->nullable();
            $table->string('phase')->default('Phase 1');
            $table->string('sector')->nullable();
            $table->string('duration')->default('3:00');
            $table->integer('likes')->default(0);
            $table->integer('views')->default(0);
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_videos');
    }
};
