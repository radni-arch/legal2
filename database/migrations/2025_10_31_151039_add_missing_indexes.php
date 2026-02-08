<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds missing indexes to improve query performance on frequently
     * filtered and sorted columns across multiple tables.
     */
    public function up(): void
    {
        // 1. Index on agent_runs.status for filtering running/completed/failed runs
        if (!$this->indexExists('agent_runs', 'agent_runs_status_index')) {
            Schema::table('agent_runs', function (Blueprint $table) {
                $table->index('status');
            });
        }

        // 2. Index on decision_discovery_runs.status for monitoring runs
        if (!$this->indexExists('decision_discovery_runs', 'decision_discovery_runs_status_index')) {
            Schema::table('decision_discovery_runs', function (Blueprint $table) {
                $table->index('status');
            });
        }

        // 3. Index on textract_jobs.status for job status filtering
        if (!$this->indexExists('textract_jobs', 'textract_jobs_status_index')) {
            Schema::table('textract_jobs', function (Blueprint $table) {
                $table->index('status');
            });
        }

        // 4. Index on eoglasna_notices.notice_type for notice filtering
        if (!$this->indexExists('eoglasna_notices', 'eoglasna_notices_notice_type_index')) {
            Schema::table('eoglasna_notices', function (Blueprint $table) {
                $table->index('notice_type');
            });
        }

        // 5. Index on eoglasna_notices.expiration_date for date range queries
        if (!$this->indexExists('eoglasna_notices', 'eoglasna_notices_expiration_date_index')) {
            Schema::table('eoglasna_notices', function (Blueprint $table) {
                $table->index('expiration_date');
            });
        }

        // 6. Index on textract_documents.embedded_at for tracking embedding generation
        if (!$this->indexExists('textract_documents', 'textract_documents_embedded_at_index')) {
            Schema::table('textract_documents', function (Blueprint $table) {
                $table->index('embedded_at');
            });
        }

        // 7. Index on cases.status for case filtering
        $casesTable = config('vizra-adk.tables.cases', 'cases');
        if (!$this->indexExists($casesTable, 'cases_status_index')) {
            Schema::table($casesTable, function (Blueprint $table) {
                $table->index('status');
            });
        }

        // 8. Index on cases.filing_date for date range queries and sorting
        if (!$this->indexExists($casesTable, $casesTable . '_filing_date_index')) {
            Schema::table($casesTable, function (Blueprint $table) {
                $table->index('filing_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $casesTable = config('vizra-adk.tables.cases', 'cases');

        // Drop indexes in reverse order - check if exists first
        if ($this->indexExists($casesTable, 'cases_filing_date_index')) {
            Schema::table($casesTable, function (Blueprint $table) {
                $table->dropIndex(['filing_date']);
            });
        }

        // Check if index exists before trying to drop it
        if ($this->indexExists($casesTable, 'cases_status_index')) {
            Schema::table($casesTable, function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        }

        if ($this->indexExists('textract_documents', 'textract_documents_embedded_at_index')) {
            Schema::table('textract_documents', function (Blueprint $table) {
                $table->dropIndex(['embedded_at']);
            });
        }

        if ($this->indexExists('eoglasna_notices', 'eoglasna_notices_expiration_date_index')) {
            Schema::table('eoglasna_notices', function (Blueprint $table) {
                $table->dropIndex(['expiration_date']);
            });
        }

        if ($this->indexExists('eoglasna_notices', 'eoglasna_notices_notice_type_index')) {
            Schema::table('eoglasna_notices', function (Blueprint $table) {
                $table->dropIndex(['notice_type']);
            });
        }

        if ($this->indexExists('textract_jobs', 'textract_jobs_status_index')) {
            Schema::table('textract_jobs', function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        }

        if ($this->indexExists('decision_discovery_runs', 'decision_discovery_runs_status_index')) {
            Schema::table('decision_discovery_runs', function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        }

        if ($this->indexExists('agent_runs', 'agent_runs_status_index')) {
            Schema::table('agent_runs', function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        }
    }

    /**
     * Check if an index exists on a table (PostgreSQL specific)
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$table, $indexName]
        );

        return count($result) > 0;
    }
};
