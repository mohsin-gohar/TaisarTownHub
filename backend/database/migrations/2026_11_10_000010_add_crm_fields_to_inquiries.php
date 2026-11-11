<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CRM: agency scoping + lead pipeline on inquiries, plus lead activity history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('agency_id', 64)->nullable()->after('id');
            $table->foreign('agency_id')->references('id')->on('agencies')->nullOnDelete();
            $table->string('property_id')->nullable()->after('agency_id');
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->after('property_id')->constrained('users')->nullOnDelete();
            $table->string('lead_status', 20)->default('new')->after('status'); // new|contacted|interested|negotiation|converted|lost
            $table->timestamp('follow_up_at')->nullable()->after('lead_status');
            $table->timestamp('converted_at')->nullable()->after('follow_up_at');

            $table->index(['agency_id', 'lead_status']);
            $table->index('property_id');
            $table->index('follow_up_at');
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->string('agency_id', 64)->index();
            $table->string('inquiry_id')->index();
            $table->string('type', 30)->default('note'); // note|followup|call|whatsapp|email|status_change|assignment
            $table->text('body')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['agency_id', 'created_at']);
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign('inquiry_id')->references('id')->on('inquiries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'lead_status']);
            $table->dropIndex(['property_id']);
            $table->dropIndex(['follow_up_at']);
            $table->dropForeign(['agency_id']);
            $table->dropForeign(['property_id']);
            $table->dropForeign(['assigned_agent_id']);
            $table->dropColumn([
                'agency_id', 'property_id', 'assigned_agent_id', 'lead_status',
                'follow_up_at', 'converted_at',
            ]);
        });
    }
};
