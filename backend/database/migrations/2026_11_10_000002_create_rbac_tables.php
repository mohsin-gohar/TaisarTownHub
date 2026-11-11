<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC tables: permissions (catalog), roles (platform + agency-scoped,
 * including custom roles created by agency owners), role_permissions pivot.
 *
 * Platform-level permissions carry is_platform = 1 and can NEVER be granted
 * through an agency-scoped role (enforced in App\Support\Permissions and in
 * the role store service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('label');
            $table->string('group', 60); // agency|properties|documents|crm|finance|admin
            $table->boolean('is_platform')->default(false);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('scope', 20)->default('agency'); // platform|agency
            $table->string('agency_id', 64)->nullable();     // null = system role shared by all agencies
            $table->string('key', 60);
            $table->string('name');
            $table->boolean('is_system')->default(false);
            $table->json('permissions');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scope', 'agency_id']);
            $table->unique(['scope', 'agency_id', 'key']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role_id', 64)->index();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
