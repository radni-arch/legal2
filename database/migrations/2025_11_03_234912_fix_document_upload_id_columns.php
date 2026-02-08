<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change upload_id from CHAR(26) to VARCHAR(26) to prevent space padding
        if (Schema::hasTable('cases_documents')) {
            DB::statement('ALTER TABLE cases_documents ALTER COLUMN upload_id TYPE VARCHAR(26)');
        }
        if (Schema::hasTable('court_decision_documents')) {
            DB::statement('ALTER TABLE court_decision_documents ALTER COLUMN upload_id TYPE VARCHAR(26)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert upload_id back to CHAR(26)
        if (Schema::hasTable('cases_documents')) {
            DB::statement('ALTER TABLE cases_documents ALTER COLUMN upload_id TYPE CHAR(26)');
        }
        if (Schema::hasTable('court_decision_documents')) {
            DB::statement('ALTER TABLE court_decision_documents ALTER COLUMN upload_id TYPE CHAR(26)');
        }
    }
};
