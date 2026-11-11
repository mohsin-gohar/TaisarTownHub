<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Login protection, profile and status fields on users. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status', 20)->default('active')->after('phone'); // active|suspended
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'failed_attempts')) {
                $table->unsignedInteger('failed_attempts')->default(0);
            }
            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable();
            }

            $table->index('role');
            $table->index('dealer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['dealer_id']);
            $table->dropColumn(['phone', 'status', 'avatar', 'last_login_at', 'failed_attempts', 'locked_until']);
        });
    }
};
