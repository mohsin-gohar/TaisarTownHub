<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email verification tokens for SPA-based verification. Only the SHA-256
 * hash of the token is stored; the plain token lives only inside the email
 * link and expires after 60 minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token_hash', 64);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
