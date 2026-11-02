<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('fullName');
            $table->string('cnic')->nullable();
            $table->string('cnicMasked')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('agencyAffiliation')->nullable();
            $table->string('agencyOrBranch')->nullable();
            $table->string('role')->nullable();
            $table->string('commissionType')->default('Fixed');
            $table->decimal('commissionRate', 10, 2)->default(0);
            $table->bigInteger('totalEarnedPKR')->default(0);
            $table->bigInteger('pendingCommissionPKR')->default(0);
            $table->integer('assignedPropertiesCount')->default(0);
            $table->integer('completedDealsCount')->default(0);
            $table->integer('dealsClosed')->default(0);
            $table->string('status')->default('Active');
            $table->text('avatar')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealers');
    }
};
