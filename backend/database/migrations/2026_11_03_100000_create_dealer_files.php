<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dealer_files', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['image', 'video', 'pdf']);
            $table->string('url'); // Cloud storage path or data URL
            $table->string('folder'); // Projects|Properties|Videos|Documents|Banners
            $table->decimal('file_size_kb', 8, 2);
            $table->string('dimensions')->nullable();
            $table->string('dealer_id');
            $table->string('uploaded_by');
            $table->enum('status', ['available', 'sold'])->default('available');
            $table->string('sold_by_dealer_id')->nullable();
            $table->decimal('selling_price_kbr', 10, 2)->nullable(); // Price at which sold
            $table->decimal('cost_price_kbr', 10, 2)->nullable(); // Original cost/commissionable price
            $table->decimal('profit_kbr', 10, 2)->default(0);
            $table->string('buyer_name')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->string('buyer_cnic')->nullable();
            $table->timestamps();
$table->softDeletes();

            // Foreign keys
            $table->foreign('dealer_id')->references('id')->on('dealers')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('dealers')->onDelete('cascade');
            $table->foreign('sold_by_dealer_id')->references('id')->on('dealers')->onDelete('set null');

            // Indexes for attribution queries
            $table->index('dealer_id');
            $table->index('uploaded_by');
            $table->index('status');
        });
    }

    public function down(): void {
        Schema::dropIfExists('dealer_files');
    }
};