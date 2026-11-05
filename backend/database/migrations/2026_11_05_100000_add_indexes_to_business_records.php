<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_records', function (Blueprint $table) {
            $table->index(['collection', 'deleted_at'], 'br_collection_deleted_idx');
            $table->index(['collection', 'updated_at'], 'br_collection_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('business_records', function (Blueprint $table) {
            $table->dropIndex('br_collection_deleted_idx');
            $table->dropIndex('br_collection_updated_idx');
        });
    }
};
