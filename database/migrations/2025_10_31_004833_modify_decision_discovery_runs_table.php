<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decision_discovery_runs', function (Blueprint $table) {
            $table->integer('decisions_found')->nullable();
            $table->string('topic_filter')->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->json('statistics')->nullable();
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        // Drop indexes first - check if exists
        if ($this->indexExists('decision_discovery_runs', 'decision_discovery_runs_status_index')) {
            Schema::table('decision_discovery_runs', function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        }

        if ($this->indexExists('decision_discovery_runs', 'decision_discovery_runs_created_at_index')) {
            Schema::table('decision_discovery_runs', function (Blueprint $table) {
                $table->dropIndex(['created_at']);
            });
        }

        // Drop columns
        Schema::table('decision_discovery_runs', function (Blueprint $table) {
            $table->dropColumn(['decisions_found', 'topic_filter', 'duration_seconds', 'statistics']);
        });
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
