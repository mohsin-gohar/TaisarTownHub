<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('fullName');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('subject')->default('Property Consultation');
            $table->text('message');
            $table->string('preferredPropertyOrProject')->nullable();
            $table->string('budgetRange')->nullable();
            $table->string('status')->default('Pending');
            $table->text('adminNotes')->nullable();
            $table->string('source')->default('Web Portal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
