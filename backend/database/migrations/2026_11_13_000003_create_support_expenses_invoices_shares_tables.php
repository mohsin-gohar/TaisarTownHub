<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G-7/G-10 — remaining enterprise tables, one additive migration:
 *
 *  - document_shares       G-10: hashed-token, expiring, revocable public
 *                          download links for vault documents.
 *  - support_tickets       Module 15: platform support desk (agency users
 *                          raise tickets, platform staff respond).
 *  - support_messages      Threaded, immutable messages per ticket.
 *  - expenses              Agency overhead ledger (soft-deletable, audited).
 *  - subscription_invoices Billing artifacts per plan/period (paid invoices
 *                          are immutable — verified transitions only).
 *
 * Conventions mirror the vault/subscription migrations: string(64) surrogate
 * PKs, agency FKs cascadeOnDelete (agencies soft-delete in Eloquent anyway),
 * users FKs constrained nullOnDelete, money as bigInteger PKR.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ------------------- G-10: document shares ------------------- */
        Schema::create('document_shares', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('document_id', 64)->index();
            $table->string('agency_id', 64)->index();
            $table->char('token_hash', 64)->unique(); // sha256 hex — plain token never stored
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('max_downloads')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('note', 255)->nullable(); // share purpose, shown to viewers
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'created_at']);
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
        });

        /* ------------------- Module 15: support desk ------------------- */
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('agency_id', 64)->nullable()->index(); // null = platform-side account
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // creator
            $table->string('subject');
            $table->string('category', 40)->default('general'); // general|billing|technical|document|finance
            $table->string('priority', 20)->default('normal');  // low|normal|high|urgent
            $table->string('status', 20)->default('open');      // open|pending|resolved|closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // author
            $table->boolean('is_staff')->default(false); // platform staff reply
            $table->text('body');
            $table->timestamp('created_at')->useCurrent(); // immutable, no updates

            $table->index(['ticket_id', 'created_at']);
            $table->foreign('ticket_id')->references('id')->on('support_tickets')->cascadeOnDelete();
        });

        /* ------------------- Agency expenses ------------------- */
        Schema::create('expenses', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('agency_id', 64)->index();
            $table->date('expense_date');
            $table->string('category', 60)->default('other'); // office|marketing|salaries|utilities|transport|legal|other
            $table->bigInteger('amount_pkr');
            $table->string('description', 500)->nullable();
            $table->string('payment_method', 40)->default('cash'); // cash|bank_transfer|cheque|card|online|other
            $table->string('reference', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'expense_date']);
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
        });

        /* ------------------- Subscription invoices ------------------- */
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('invoice_number')->unique(); // INV-YYYY-NNNNNN, generated server-side
            $table->string('agency_id', 64)->index();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->restrictOnDelete();
            $table->bigInteger('amount_pkr')->default(0);
            $table->bigInteger('tax_pkr')->default(0);
            $table->bigInteger('total_pkr')->default(0);
            $table->char('currency', 3)->default('PKR');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status', 20)->default('pending'); // pending|paid|void
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('document_shares');
    }
};
