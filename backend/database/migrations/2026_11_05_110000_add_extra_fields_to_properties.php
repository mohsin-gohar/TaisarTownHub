<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'contactWhatsApp')) {
                $table->string('contactWhatsApp')->nullable()->after('contactPhone');
            }
            if (!Schema::hasColumn('properties', 'agencyName')) {
                $table->string('agencyName')->nullable()->after('assignedDealerId');
            }
            if (!Schema::hasColumn('properties', 'createdDate')) {
                $table->string('createdDate')->nullable()->after('agencyName');
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['contactWhatsApp', 'agencyName', 'createdDate']);
        });
    }
};
