<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_records', function (Blueprint $table) {
            $table->id();
            $table->string('collection');
            $table->string('record_id');
            $table->json('payload');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['collection', 'record_id']);
            $table->index('collection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_records');
    }
};
