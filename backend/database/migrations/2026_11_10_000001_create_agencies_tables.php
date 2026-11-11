<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant core: agencies table + agency invitations + dealers.agency_id.
 * All additive. Existing free-text agency strings (dealers.agencyAffiliation)
 * are backfilled in migration 2026_11_10_000016.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_url')->nullable();
            $table->string('cover_url')->nullable();
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->text('office_address')->nullable();
            $table->string('city')->default('Karachi');
            $table->string('registration_number')->nullable();
            $table->string('website')->nullable();
            $table->string('status', 20)->default('pending');       // pending|active|suspended|rejected
            $table->string('verification_status', 20)->default('unverified'); // unverified|pending|verified|rejected
            $table->string('verification_notes')->nullable();
            $table->string('verification_document_id', 64)->nullable(); // -> documents.id (app-level, circular FK avoided)
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('verification_status');
        });

        Schema::create('agency_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('agency_id', 64)->index();
            $table->string('email');
            $table->string('role_id', 64)->index(); // -> roles.id (FK added after roles table exists)
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending|accepted|expired|revoked
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->index(['agency_id', 'status']);
        });

        // Agency ownership for the legacy dealers table (nullable, backfilled later).
        Schema::table('dealers', function (Blueprint $table) {
            if (!Schema::hasColumn('dealers', 'agency_id')) {
                $table->string('agency_id', 64)->nullable()->after('id');
                $table->index('agency_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropIndex(['agency_id']);
            $table->dropColumn('agency_id');
        });
        Schema::dropIfExists('agency_invitations');
        Schema::dropIfExists('agencies');
    }
};
