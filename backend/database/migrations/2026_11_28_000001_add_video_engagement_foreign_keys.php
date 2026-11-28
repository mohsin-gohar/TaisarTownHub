<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Complete referential integrity for video comments and likes.
 *
 * The original engagement migration created numeric user_id columns and a
 * unique key, but omitted foreign keys to users and agency_videos. Orphaned
 * comments/likes would therefore survive hard deletes and could reference
 * nonexistent records.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Preserve comments if a legacy user row was already removed.
        DB::table('video_comments')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', DB::table('users')->select('id'))
            ->update(['user_id' => null]);

        // Do not silently discard engagement history. Require orphaned video
        // references to be reconciled before installing cascading constraints.
        $orphanComments = DB::table('video_comments as comments')
            ->leftJoin('agency_videos as videos', 'videos.id', '=', 'comments.agency_video_id')
            ->whereNull('videos.id')
            ->exists();

        $orphanLikes = DB::table('video_likes as likes')
            ->leftJoin('agency_videos as videos', 'videos.id', '=', 'likes.agency_video_id')
            ->whereNull('videos.id')
            ->exists();

        if ($orphanComments || $orphanLikes) {
            throw new RuntimeException(
                'Cannot add video engagement foreign keys: orphaned agency_video_id references must be reconciled first.'
            );
        }

        Schema::table('video_comments', function (Blueprint $table): void {
            $table->foreign('agency_video_id', 'video_comments_video_fk')
                ->references('id')->on('agency_videos')->cascadeOnDelete();
            $table->foreign('user_id', 'video_comments_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('video_likes', function (Blueprint $table): void {
            $table->foreign('agency_video_id', 'video_likes_video_fk')
                ->references('id')->on('agency_videos')->cascadeOnDelete();
            $table->foreign('user_id', 'video_likes_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('video_likes', function (Blueprint $table): void {
            $table->dropForeign('video_likes_video_fk');
            $table->dropForeign('video_likes_user_fk');
        });

        Schema::table('video_comments', function (Blueprint $table): void {
            $table->dropForeign('video_comments_video_fk');
            $table->dropForeign('video_comments_user_fk');
        });
    }
};
