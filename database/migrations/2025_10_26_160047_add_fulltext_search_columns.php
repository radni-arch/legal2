<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add tsvector columns and GIN indexes for full-text search
     * across laws, court_decision_documents, and cases_documents tables.
     */
    public function up(): void
    {
        // Only proceed if using PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'laws' => config('vizra-adk.tables.laws', 'laws'),
            'court_decision_documents' => config('vizra-adk.tables.court_decision_documents', 'court_decision_documents'),
            'cases_documents' => config('vizra-adk.tables.cases_documents', 'cases_documents'),
        ];

        $grammar = DB::connection()->getQueryGrammar();

        foreach ($tables as $key => $tableName) {
            $wrappedTable = $grammar->wrapTable($tableName);
            $wrappedColumn = $grammar->wrap('content_tsv');

            // 1) Add the tsvector column via raw SQL (Schema builder has no tsvector type)
            if (! Schema::hasColumn($tableName, 'content_tsv')) {
                DB::statement("ALTER TABLE {$wrappedTable} ADD COLUMN {$wrappedColumn} tsvector");
            }

            // 2) Backfill existing rows
            DB::statement("
        UPDATE {$wrappedTable}
        SET {$wrappedColumn} = to_tsvector('simple', COALESCE(title, '') || ' ' || COALESCE(content, ''))
    ");

            // 3) GIN index on tsvector
            DB::statement("
        CREATE INDEX IF NOT EXISTS {$tableName}_content_tsv_idx
        ON {$wrappedTable} USING GIN ({$wrappedColumn})
    ");

            // 4) Trigger to keep the column in sync
            DB::statement("
        CREATE OR REPLACE FUNCTION {$tableName}_tsvector_update_trigger() RETURNS trigger AS $$
        BEGIN
            NEW.content_tsv := to_tsvector('simple', COALESCE(NEW.title, '') || ' ' || COALESCE(NEW.content, ''));
            RETURN NEW;
        END
        $$ LANGUAGE plpgsql;
    ");

            DB::statement("DROP TRIGGER IF EXISTS tsvectorupdate ON {$wrappedTable}");

            DB::statement("
        CREATE TRIGGER tsvectorupdate BEFORE INSERT OR UPDATE
        ON {$wrappedTable} FOR EACH ROW
        EXECUTE FUNCTION {$tableName}_tsvector_update_trigger();
    ");
        }
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'laws' => config('vizra-adk.tables.laws', 'laws'),
            'court_decision_documents' => config('vizra-adk.tables.court_decision_documents', 'court_decision_documents'),
            'cases_documents' => config('vizra-adk.tables.cases_documents', 'cases_documents'),
        ];

        $grammar = DB::connection()->getQueryGrammar();

        foreach ($tables as $tableName) {
            // Skip if table doesn't exist
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $wrappedTable = $grammar->wrapTable($tableName);
            $wrappedColumn = $grammar->wrap('content_tsv');

            DB::statement("DROP TRIGGER IF EXISTS tsvectorupdate ON {$wrappedTable}");
            DB::statement("DROP FUNCTION IF EXISTS {$tableName}_tsvector_update_trigger()");
            DB::statement("DROP INDEX IF EXISTS {$tableName}_content_tsv_idx");

            if (Schema::hasColumn($tableName, 'content_tsv')) {
                DB::statement("ALTER TABLE {$wrappedTable} DROP COLUMN {$wrappedColumn}");
            }
        }
    }
};
