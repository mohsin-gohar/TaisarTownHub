<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('plotNumber')->nullable();
            $table->string('fileNumber')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->bigInteger('pricePKR')->default(0);
            $table->string('formattedPrice')->nullable();
            $table->string('location')->nullable();
            $table->string('phase')->default('Phase 1');
            $table->string('sector')->nullable();
            $table->string('block')->nullable();
            $table->integer('sizeSqYards')->default(120);
            $table->integer('roadWidthFeet')->nullable();
            $table->string('type')->default('Residential Plot');
            $table->string('status')->default('Available');
            $table->boolean('isFeatured')->default(false);
            $table->boolean('possessionReady')->default(false);
            $table->boolean('mdaApproved')->default(true);
            $table->boolean('westOpen')->default(false);
            $table->boolean('cornerPlot')->default(false);
            $table->boolean('parkFacing')->default(false);
            $table->boolean('mainBoulevard')->default(false);
            $table->json('images')->nullable();
            $table->json('features')->nullable();
            $table->string('contactPerson')->nullable();
            $table->string('contactPhone')->nullable();
            $table->string('assignedDealerId')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
