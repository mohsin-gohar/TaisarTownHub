<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('dealNumber')->unique();
            $table->string('propertyId');
            $table->string('propertyTitle');
            $table->string('plotNumber')->nullable();
            $table->string('sector')->nullable();
            $table->string('sellerName');
            $table->string('buyerName');
            $table->string('dealerId')->nullable();
            $table->string('dealerName')->nullable();
            $table->string('transactionType')->default('Sale');
            $table->bigInteger('agreementAmountPKR')->default(0);
            $table->bigInteger('advancePaymentPKR')->default(0);
            $table->bigInteger('remainingBalancePKR')->default(0);
            $table->bigInteger('commissionAmountPKR')->default(0);
            $table->string('commissionStatus')->default('Pending');
            $table->string('dealStatus')->default('Agreement Signed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
