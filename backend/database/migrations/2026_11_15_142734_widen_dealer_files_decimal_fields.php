<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table("dealer_files", function (Blueprint $table) {
            // Widen decimal fields from (10,2) to (15,2) to support prices up to 1 trillion (100,000 crore)
            $table->decimal("selling_price_kbr", 15, 2)->nullable()->change();
            $table->decimal("cost_price_kbr", 15, 2)->nullable()->change();
            $table->decimal("profit_kbr", 15, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("dealer_files", function (Blueprint $table) {
            $table->decimal("selling_price_kbr", 10, 2)->nullable()->change();
            $table->decimal("cost_price_kbr", 10, 2)->nullable()->change();
            $table->decimal("profit_kbr", 10, 2)->default(0)->change();
        });
    }
};
