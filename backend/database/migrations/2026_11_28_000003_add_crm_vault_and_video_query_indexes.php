<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for observed CRM, vault folder-count, and public video
 * query shapes. Each index is guarded for safe deploys to partially repaired
 * environments and has an explicit short MySQL-compatible identifier.
 */
return new class extends Migration
{
    private function addIndex(string $tableName, array $columns, string $name): void
    {
        if (!Schema::hasIndex($tableName, $columns)) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $name): void {
                $table->index($columns, $name);
            });
        }
    }

    public function up(): void
    {
        // Lead list: tenant scope + newest-first; optional pipeline status and
        // assignment filters; follow-up due queues.
        $this->addIndex('inquiries', ['agency_id', 'created_at'], 'inquiries_agency_created_idx');
        $this->addIndex('inquiries', ['agency_id', 'lead_status', 'created_at'], 'inquiries_agency_status_created_idx');
        $this->addIndex('inquiries', ['agency_id', 'assigned_agent_id', 'created_at'], 'inquiries_agency_agent_created_idx');
        $this->addIndex('inquiries', ['agency_id', 'follow_up_at'], 'inquiries_agency_followup_idx');

        // Folder list uses a correlated active-document count by folder_id.
        $this->addIndex('documents', ['folder_id', 'status'], 'documents_folder_status_idx');

        // Public video listing optionally filters phase and sorts newest first.
        $this->addIndex('agency_videos', ['phase', 'created_at', 'id'], 'agency_videos_phase_created_idx');

        // Activity timeline is read by inquiry and ordered newest/oldest.
        $this->addIndex('lead_activities', ['inquiry_id', 'created_at'], 'lead_activities_inquiry_created_idx');
    }

    public function down(): void
    {
        $indexes = [
            ['inquiries', 'inquiries_agency_created_idx'],
            ['inquiries', 'inquiries_agency_status_created_idx'],
            ['inquiries', 'inquiries_agency_agent_created_idx'],
            ['inquiries', 'inquiries_agency_followup_idx'],
            ['documents', 'documents_folder_status_idx'],
            ['agency_videos', 'agency_videos_phase_created_idx'],
            ['lead_activities', 'lead_activities_inquiry_created_idx'],
        ];

        foreach ($indexes as [$tableName, $indexName]) {
            $exists = collect(Schema::getIndexes($tableName))
                ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);

            if ($exists) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }
    }
};
