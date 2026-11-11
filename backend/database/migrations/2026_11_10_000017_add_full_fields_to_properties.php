<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complete the properties table so it can hold every field the property
 * management UI edits (public detail fields + private ERP fields).
 * All nullable/additive; existing rows unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $add = [
                'streetNumber' => 'string',
                'sizeUnit' => 'string',
                'frontFeet' => 'integer',
                'depthFeet' => 'integer',
                'roadWidthFt' => 'integer',
                'isRoadFacing' => 'boolean',
                'is100ftRoadFacing' => 'boolean',
                'videoUrl' => 'text',
                'bedrooms' => 'integer',
                'bathrooms' => 'integer',
                'purchasePricePKR' => 'bigInteger',
                'sellingPricePKR' => 'bigInteger',
                'internalNotes' => 'text',
                'sellerId' => 'string',
                'sellerName' => 'string',
                'buyerId' => 'string',
                'buyerName' => 'string',
                'deletedBy' => 'string',
            ];
            foreach ($add as $col => $type) {
                if (!Schema::hasColumn('properties', $col)) {
                    $table->{$type}($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $cols = [
                'streetNumber', 'sizeUnit', 'frontFeet', 'depthFeet', 'roadWidthFt',
                'isRoadFacing', 'is100ftRoadFacing', 'videoUrl', 'bedrooms', 'bathrooms',
                'purchasePricePKR', 'sellingPricePKR', 'internalNotes',
                'sellerId', 'sellerName', 'buyerId', 'buyerName', 'deletedBy',
            ];
            $existing = array_filter($cols, fn ($c) => Schema::hasColumn('properties', $c));
            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }
};
