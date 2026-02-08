<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Ensure this migration is not wrapped in a transaction so DDL and constraint changes apply immediately
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $casesTable = config('vizra-adk.tables.cases', 'cases');
        $cduTable = config('vizra-adk.tables.cases_documents_uploads', 'cases_documents_uploads');

        // 1) Drop foreign keys referencing cases.id across all known child tables (use raw SQL for robustness)
        // textract_jobs
        DB::statement('ALTER TABLE textract_jobs DROP CONSTRAINT IF EXISTS textract_jobs_case_id_foreign');
        // textract_documents
        DB::statement('ALTER TABLE textract_documents DROP CONSTRAINT IF EXISTS textract_documents_case_id_foreign');
        // evidence (if present)
        if (Schema::hasTable('evidence')) {
            DB::statement('ALTER TABLE evidence DROP CONSTRAINT IF EXISTS evidence_case_id_foreign');
        }
        // cases_documents_uploads (configurable name)
        if (Schema::hasTable($cduTable)) {
            // Default Laravel constraint naming: {table}_case_id_foreign
            $constraint = $cduTable.'_case_id_foreign';
            DB::statement("ALTER TABLE {$cduTable} DROP CONSTRAINT IF EXISTS {$constraint}");
        }

        // 2) Normalize/clean child references (trim, nullify empties, remove orphans)
        // - textract_jobs.case_id is nullable: trim spaces and set to NULL when empty or orphaned
        DB::statement("UPDATE textract_jobs SET case_id = NULL WHERE case_id IS NOT NULL AND btrim(case_id) = ''");
        DB::statement('UPDATE textract_jobs SET case_id = btrim(case_id) WHERE case_id IS NOT NULL');
        DB::statement("UPDATE textract_jobs tj SET case_id = NULL WHERE case_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM {$casesTable} c WHERE c.id = tj.case_id)");

        // - textract_documents.case_id: trim spaces; delete true orphans
        DB::statement('UPDATE textract_documents SET case_id = btrim(case_id)');
        DB::statement("DELETE FROM textract_documents td WHERE NOT EXISTS (SELECT 1 FROM {$casesTable} c WHERE c.id = td.case_id)");

        // - evidence.case_id may be CHAR(26): trim and delete orphans if table exists
        if (Schema::hasTable('evidence')) {
            DB::statement('UPDATE evidence SET case_id = btrim(case_id)');
            DB::statement("DELETE FROM evidence e WHERE NOT EXISTS (SELECT 1 FROM {$casesTable} c WHERE c.id = e.case_id)");
        }
        // - cases_documents_uploads: trim and nullify orphaned (nullable FK)
        if (Schema::hasTable($cduTable)) {
            DB::statement("UPDATE {$cduTable} SET case_id = btrim(case_id::text)::varchar WHERE case_id IS NOT NULL");
            DB::statement("UPDATE {$cduTable} u SET case_id = NULL WHERE case_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM {$casesTable} c WHERE c.id = u.case_id)");
        }

        // 3) Alter parent key type from CHAR(26) to VARCHAR(26)
        if (Schema::hasTable($casesTable)) {
            DB::statement("ALTER TABLE {$casesTable} ALTER COLUMN id TYPE VARCHAR(26)");
        }

        // 4) Align child column lengths to VARCHAR(26) for consistency
        if (Schema::hasTable('textract_jobs')) {
            DB::statement('ALTER TABLE textract_jobs ALTER COLUMN case_id TYPE VARCHAR(26)');
        }
        if (Schema::hasTable('textract_documents')) {
            DB::statement('ALTER TABLE textract_documents ALTER COLUMN case_id TYPE VARCHAR(26)');
        }
        if (Schema::hasTable('evidence')) {
            DB::statement('ALTER TABLE evidence ALTER COLUMN case_id TYPE VARCHAR(26)');
        }
        if (Schema::hasTable($cduTable)) {
            // cases_documents_uploads.case_id was defined as ulid -> change to VARCHAR(26) to keep FK consistent
            DB::statement("ALTER TABLE {$cduTable} ALTER COLUMN case_id TYPE VARCHAR(26)");
        }

        // 5) Recreate foreign keys with cascade behavior
        Schema::table('textract_jobs', function (Blueprint $table) use ($casesTable) {
            $table->foreign('case_id')
                ->references('id')
                ->on($casesTable)
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::table('textract_documents', function (Blueprint $table) use ($casesTable) {
            $table->foreign('case_id')
                ->references('id')
                ->on($casesTable)
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        if (Schema::hasTable('evidence')) {
            Schema::table('evidence', function (Blueprint $table) use ($casesTable) {
                $table->foreign('case_id')
                    ->references('id')
                    ->on($casesTable)
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }
        if (Schema::hasTable($cduTable)) {
            Schema::table($cduTable, function (Blueprint $table) use ($casesTable) {
                $table->foreign('case_id')
                    ->references('id')
                    ->on($casesTable)
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $casesTable = config('vizra-adk.tables.cases', 'cases');
        $cduTable = config('vizra-adk.tables.cases_documents_uploads', 'cases_documents_uploads');

        // Drop FKs before reverting types (raw SQL for robustness)
        DB::statement('ALTER TABLE textract_documents DROP CONSTRAINT IF EXISTS textract_documents_case_id_foreign');
        DB::statement('ALTER TABLE textract_jobs DROP CONSTRAINT IF EXISTS textract_jobs_case_id_foreign');
        if (Schema::hasTable('evidence')) {
            DB::statement('ALTER TABLE evidence DROP CONSTRAINT IF EXISTS evidence_case_id_foreign');
        }
        if (Schema::hasTable($cduTable)) {
            $constraint = $cduTable.'_case_id_foreign';
            DB::statement("ALTER TABLE {$cduTable} DROP CONSTRAINT IF EXISTS {$constraint}");
        }

        // Revert parent type back to CHAR(26)
        if (Schema::hasTable($casesTable)) {
            DB::statement("ALTER TABLE {$casesTable} ALTER COLUMN id TYPE CHAR(26)");
        }

        // Revert child columns
        if (Schema::hasTable('textract_documents')) {
            DB::statement('ALTER TABLE textract_documents ALTER COLUMN case_id TYPE VARCHAR(255)');
        }
        if (Schema::hasTable('textract_jobs')) {
            DB::statement('ALTER TABLE textract_jobs ALTER COLUMN case_id TYPE VARCHAR(255)');
        }
        if (Schema::hasTable('evidence')) {
            DB::statement('ALTER TABLE evidence ALTER COLUMN case_id TYPE CHAR(26)');
        }
        if (Schema::hasTable($cduTable)) {
            DB::statement("ALTER TABLE {$cduTable} ALTER COLUMN case_id TYPE CHAR(26)");
        }

        // Recreate original FKs
        Schema::table('textract_documents', function (Blueprint $table) use ($casesTable) {
            $table->foreign('case_id')
                ->references('id')
                ->on($casesTable)
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::table('textract_jobs', function (Blueprint $table) use ($casesTable) {
            $table->foreign('case_id')
                ->references('id')
                ->on($casesTable)
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        if (Schema::hasTable('evidence')) {
            Schema::table('evidence', function (Blueprint $table) use ($casesTable) {
                $table->foreign('case_id')
                    ->references('id')
                    ->on($casesTable)
                    ->onDelete('cascade');
            });
        }
        if (Schema::hasTable($cduTable)) {
            Schema::table($cduTable, function (Blueprint $table) use ($casesTable) {
                $table->foreign('case_id')->references('id')->on($casesTable)->cascadeOnDelete();
            });
        }
    }
};
