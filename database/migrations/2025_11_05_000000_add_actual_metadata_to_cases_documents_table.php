<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 'actual' column to store comprehensive legal metadata extracted via LegalMetadataExtractor.
     * This provides the same rich metadata as TextractJob, including citations, courts, parties,
     * document classification, and content analysis.
     */
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.cases_documents', 'cases_documents');

        // Skip if table doesn't exist yet (will be created by create_cases_documents_table migration)
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // JSON column for comprehensive legal metadata (citations, courts, parties, etc.)
            if (! Schema::hasColumn($tableName, 'actual')) {
                $table->json('actual')->nullable()->after('metadata');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.cases_documents', 'cases_documents');

        // Skip if table doesn't exist
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (Schema::hasColumn($tableName, 'actual')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('actual');
            });
        }
    }
};
