<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-attributed video engagement: comments carry the authoring user and
 * likes become a per-user unique row (one like per account per video).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('video_comments', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('agency_video_id');
                $table->index(['user_id'], 'video_comments_user_id_index');
            }
        });

        if (!Schema::hasTable('video_likes')) {
            Schema::create('video_likes', function (Blueprint $table) {
                $table->id();
                $table->string('agency_video_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
                $table->unique(['agency_video_id', 'user_id'], 'video_likes_unique');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('video_likes');

        Schema::table('video_comments', function (Blueprint $table) {
            if (Schema::hasColumn('video_comments', 'user_id')) {
                $table->dropIndex('video_comments_user_id_index');
                $table->dropColumn('user_id');
            }
        });
    }
};
