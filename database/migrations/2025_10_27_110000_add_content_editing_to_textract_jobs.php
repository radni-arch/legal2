<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds content editing and sync tracking fields to textract_jobs table.
     */
    public function up(): void
    {
        Schema::table('textract_jobs', function (Blueprint $table) {
            // Content storage fields
            $table->longText('extracted_content')->nullable()->after('metadata');
            $table->longText('manual_content')->nullable()->after('extracted_content');

            // Edit tracking
            $table->boolean('manually_edited')->default(false)->after('manual_content');
            $table->timestamp('content_edited_at')->nullable()->after('manually_edited');
            $table->unsignedBigInteger('edited_by')->nullable()->after('content_edited_at');

            // Sync status tracking for external systems
            $table->string('embedding_status')->default('pending')->after('edited_by')
                ->comment('Status: pending|processing|synced|failed');
            $table->string('graph_sync_status')->default('pending')->after('embedding_status')
                ->comment('Status: pending|processing|synced|failed');

            // Sync timestamps
            $table->timestamp('embedding_synced_at')->nullable()->after('graph_sync_status');
            $table->timestamp('graph_synced_at')->nullable()->after('embedding_synced_at');

            // Foreign key for edited_by (assumes users table exists)
            $table->foreign('edited_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for frequently queried fields
            $table->index('manually_edited');
            $table->index('embedding_status');
            $table->index('graph_sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('textract_jobs', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['edited_by']);

            // Drop indexes
            $table->dropIndex(['manually_edited']);
            $table->dropIndex(['embedding_status']);
            $table->dropIndex(['graph_sync_status']);

            // Drop columns
            $table->dropColumn([
                'extracted_content',
                'manual_content',
                'manually_edited',
                'content_edited_at',
                'edited_by',
                'embedding_status',
                'graph_sync_status',
                'embedding_synced_at',
                'graph_synced_at',
            ]);
        });
    }
};
