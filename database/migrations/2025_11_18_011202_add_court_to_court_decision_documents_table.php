<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.court_decision_documents', 'court_decision_documents');

        // Skip if table doesn't exist yet (will be created by create_court_decision_documents_table migration)
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'court')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('court')->nullable()->after('author')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.court_decision_documents', 'court_decision_documents');

        // Skip if table doesn't exist
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (Schema::hasColumn($tableName, 'court')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('court');
            });
        }
    }
};
