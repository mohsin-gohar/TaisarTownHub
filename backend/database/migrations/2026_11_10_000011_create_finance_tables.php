<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial records: agency scoping on transactions + payments,
 * installment schedules and commissions. All money uses decimal(14,2).
 * Append-only semantics: no soft deletes on payments/commissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('agency_id', 64)->nullable()->after('id');
            $table->foreign('agency_id')->references('id')->on('agencies')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('agency_id')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['agency_id', 'dealStatus']);
            $table->index('propertyId');
            $table->index('dealerId');
        });

        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->string('agency_id', 64)->index();
            $table->string('transaction_id');
            $table->decimal('amount', 14, 2);
            $table->string('type', 30)->default('advance'); // token|advance|installment|refund
            $table->string('method', 30)->default('cash');  // cash|bank_transfer|cheque|online|other
            $table->string('reference')->nullable();
            $table->string('receipt_no')->nullable()->unique();
            $table->timestamp('received_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'received_at']);
            $table->index(['agency_id', 'received_at']);
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
        });

        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('agency_id', 64)->index();
            $table->string('transaction_id');
            $table->unsignedInteger('installment_no');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending'); // pending|paid|overdue|cancelled
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['transaction_id', 'installment_no']);
            $table->index(['agency_id', 'status']);
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->string('agency_id', 64)->index();
            $table->string('transaction_id');
            $table->string('dealer_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // agent beneficiary
            $table->string('beneficiary_type', 20)->default('dealer'); // agency|dealer|agent
            $table->decimal('amount', 14, 2);
            $table->decimal('rate', 6, 2)->nullable();
            $table->string('status', 20)->default('pending'); // pending|accrued|paid|cancelled
            $table->timestamp('accrued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->index('transaction_id');
            $table->index('dealer_id');
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            $table->foreign('dealer_id')->references('id')->on('dealers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('payment_schedules');
        Schema::dropIfExists('transaction_payments');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'dealStatus']);
            $table->dropIndex(['propertyId']);
            $table->dropIndex(['dealerId']);
            $table->dropForeign(['agency_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'agency_id', 'created_by', 'updated_by', 'voided_at', 'void_reason',
                'voided_by', 'approved_at', 'approved_by',
            ]);
        });
    }
};
