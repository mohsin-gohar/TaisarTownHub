<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 hardening — optional TOTP multi-factor authentication + sign-in history.
 *
 * Additive only (no data is dropped or rewritten):
 *  - users.mfa_secret          TOTP shared secret, encrypted at rest by the model cast
 *  - users.mfa_enabled_at      set only after the user confirms a working code
 *  - users.mfa_recovery_codes  encrypted JSON array of hashed single-use codes
 *  - login_histories           append-only trail of every sign-in attempt
 *                              (IP, user agent, outcome, MFA usage, suspicious flag)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mfa_secret')) {
                $table->text('mfa_secret')->nullable();
            }
            if (!Schema::hasColumn('users', 'mfa_enabled_at')) {
                $table->timestamp('mfa_enabled_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'mfa_recovery_codes')) {
                $table->text('mfa_recovery_codes')->nullable();
            }
        });

        if (!Schema::hasTable('login_histories')) {
            Schema::create('login_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('email', 190);
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->boolean('success')->default(false);
                $table->boolean('mfa_used')->default(false);
                $table->boolean('suspicious')->default(false);
                $table->string('reason', 120)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['email', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['mfa_secret', 'mfa_enabled_at', 'mfa_recovery_codes'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('login_histories');
    }
};
