<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agency membership: the ONLY source of truth for "which agency does this
 * user act for" and "which agency role (and permissions) does this user hold".
 * agency_id on requests is NEVER trusted for authorization (see Phase 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_members', function (Blueprint $table) {
            $table->id();
            $table->string('agency_id', 64)->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_id', 64)->index();
            $table->string('status', 20)->default('active'); // active|invited|deactivated
            $table->json('permissions')->nullable();          // extra grants/revocations for this member
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'user_id']);
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();

            $table->index(['agency_id', 'status']);
        });

        // Complete the invitation -> role link now that roles exists.
        Schema::table('agency_invitations', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agency_invitations', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
        Schema::dropIfExists('agency_members');
    }
};
