<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4.1: Add temporal fields to laws table for temporal legal reasoning
 *
 * Adds fields for tracking law evolution over time:
 * - valid_from: Start date when this law version becomes valid
 * - valid_until: End date when this law version is no longer valid (null = current)
 * - version: Version number for tracking law revisions
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Add temporal fields for law versioning
            if (! Schema::hasColumn($tableName, 'valid_from')) {
                $table->date('valid_from')->nullable()->after('promulgation_date');
            }
            if (! Schema::hasColumn($tableName, 'valid_until')) {
                $table->date('valid_until')->nullable()->after('valid_from');
            }
            if (Schema::hasColumn($tableName, 'version')) {
                $table->string('version')->nullable()->change();
            } else {
                $table->string('version')->nullable()->after('valid_until');
            }

            // Add index for temporal queries (efficient date range searches)
            $connection = Schema::getConnection();
            $indexes = $connection->getSchemaBuilder()->getIndexListing($tableName);
            if (! in_array('idx_law_temporal', $indexes)) {
                $table->index(['law_number', 'valid_from', 'valid_until'], 'idx_law_temporal');
            }
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            // Drop index first
            $table->dropIndex('idx_law_temporal');

            // Drop temporal fields
            $table->dropColumn(['valid_from', 'valid_until', 'version']);
        });
    }
};
