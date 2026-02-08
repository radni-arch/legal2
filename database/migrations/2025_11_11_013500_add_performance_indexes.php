<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Performance indexes to optimize agent queries
     */
    public function up(): void
    {
        // Index for court_decisions table
        if (Schema::hasTable('court_decisions')) {
            Schema::table('court_decisions', function (Blueprint $table) {
                // Index for decision search by court and date
                if (! $this->indexExists('court_decisions', 'court_decisions_court_date_index')) {
                    $table->index(['court', 'decision_date'], 'court_decisions_court_date_index');
                }

                if (! $this->indexExists('court_decisions', 'court_decisions_ecli_index')) {
                    $table->index('ecli', 'court_decisions_ecli_index');
                }

                // Index for case number lookups
                if (! $this->indexExists('court_decisions', 'court_decisions_case_number_index')) {
                    $table->index('case_number', 'court_decisions_case_number_index');
                }
            });
        }

        // Index for laws table
        if (Schema::hasTable('laws')) {
            Schema::table('laws', function (Blueprint $table) {
                // Index for law number lookups
                if (! $this->indexExists('laws', 'laws_law_number_index')) {
                    $table->index('law_number', 'laws_law_number_index');
                }

                // Composite index for commonly queried combinations
                if (! $this->indexExists('laws', 'laws_law_number_article_index')) {
                    $table->index(['law_number'], 'laws_law_number_article_index');
                }
            });
        }

        // Index for agent_runs table
        if (Schema::hasTable('agent_runs')) {
            Schema::table('agent_runs', function (Blueprint $table) {
                // Index for finding recent runs by agent type
                if (! $this->indexExists('agent_runs', 'agent_runs_agent_type_created_index')) {
                    $table->index(['created_at'], 'agent_runs_agent_type_created_index');
                }

                // Index for status filtering
                if (! $this->indexExists('agent_runs', 'agent_runs_status_index')) {
                    $table->index('status', 'agent_runs_status_index');
                }
            });
        }

        // Index for agent_communications table
        if (Schema::hasTable('agent_communications')) {
            Schema::table('agent_communications', function (Blueprint $table) {
                // Index for finding messages by recipient
                //                if (! $this->indexExists('agent_communications', 'agent_communications_to_read_index')) {
                //                    $table->index(['to_agent', 'read_at'], 'agent_communications_to_read_index');
                //                }

                // Index for session lookup
                if (! $this->indexExists('agent_communications', 'agent_communications_communication_index')) {
                    $table->index('communication_id', 'agent_communications_communication_index');
                }
            });
        }

        // Index for reasoning_traces table
        if (Schema::hasTable('reasoning_traces')) {
            Schema::table('reasoning_traces', function (Blueprint $table) {
                // Index for finding traces by parent
                if (! $this->indexExists('reasoning_traces', 'reasoning_traces_parent_index')) {
                    $table->index('parent_trace_id', 'reasoning_traces_parent_index');
                }

                // Index for agent type filtering
                if (! $this->indexExists('reasoning_traces', 'reasoning_traces_agent_type_index')) {
                    $table->index('agent_type', 'reasoning_traces_agent_type_index');
                }

                // Composite index for common query patterns
                if (! $this->indexExists('reasoning_traces', 'reasoning_traces_operation_created_index')) {
                    $table->index(['operation', 'created_at'], 'reasoning_traces_operation_created_index');
                }
            });
        }

        // Index for vector search optimization (pgvector)
        if (DB::getDriverName() === 'pgsql') {
            // Create IVFFlat index for court_decision_embeddings if not exists
            $this->createPgVectorIndex(
                'court_decision_embeddings',
                'embedding',
                'court_decision_embeddings_embedding_idx'
            );

            // Create IVFFlat index for law_embeddings if not exists
            $this->createPgVectorIndex(
                'law_embeddings',
                'embedding',
                'law_embeddings_embedding_idx'
            );
        }

        // Index for benchmark_runs table
        if (Schema::hasTable('benchmark_runs')) {
            Schema::table('benchmark_runs', function (Blueprint $table) {
                // Index for finding runs by benchmark and commit
                if (! $this->indexExists('benchmark_runs', 'benchmark_runs_class_commit_index')) {
                    $table->index(['benchmark_class', 'git_commit_hash', 'git_branch'], 'benchmark_runs_class_commit_index');
                }

                // Index for recent successful runs
                if (! $this->indexExists('benchmark_runs', 'benchmark_runs_status_created_index')) {
                    $table->index(['status', 'created_at'], 'benchmark_runs_status_created_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        if (Schema::hasTable('benchmark_runs')) {
            Schema::table('benchmark_runs', function (Blueprint $table) {
                $table->dropIndex('benchmark_runs_class_commit_index');
                $table->dropIndex('benchmark_runs_status_created_index');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS court_decision_embeddings_embedding_idx');
            DB::statement('DROP INDEX IF EXISTS law_embeddings_embedding_idx');
        }

        if (Schema::hasTable('reasoning_traces')) {
            Schema::table('reasoning_traces', function (Blueprint $table) {
                $table->dropIndex('reasoning_traces_parent_index');
                $table->dropIndex('reasoning_traces_agent_type_index');
                $table->dropIndex('reasoning_traces_operation_created_index');
            });
        }

        if (Schema::hasTable('agent_communications')) {
            Schema::table('agent_communications', function (Blueprint $table) {
                $table->dropIndex('agent_communications_to_read_index');
                $table->dropIndex('agent_communications_session_index');
            });
        }

        if (Schema::hasTable('agent_runs')) {
            Schema::table('agent_runs', function (Blueprint $table) {
                $table->dropIndex('agent_runs_agent_type_created_index');
                $table->dropIndex('agent_runs_status_index');
            });
        }

        if (Schema::hasTable('laws')) {
            Schema::table('laws', function (Blueprint $table) {
                $table->dropIndex('laws_law_number_index');
                $table->dropIndex('laws_law_number_article_index');
            });
        }

        if (Schema::hasTable('court_decisions')) {
            Schema::table('court_decisions', function (Blueprint $table) {
                $table->dropIndex('court_decisions_court_date_index');
                $table->dropIndex('court_decisions_ecli_index');
                $table->dropIndex('court_decisions_case_number_index');
            });
        }
    }

    /**
     * Check if index exists
     */
    protected function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $indexes = $connection->getSchemaBuilder()->getIndexListing($table);
        $indexes = array_flip($indexes);

        return isset($indexes[$index]);
    }

    /**
     * Create pgvector IVFFlat index for similarity search
     */
    protected function createPgVectorIndex(string $table, string $column, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        // Check if index exists
        $exists = DB::selectOne('
            SELECT 1
            FROM pg_indexes
            WHERE tablename = ? AND indexname = ?
        ', [$table, $indexName]);

        if (! $exists) {
            // Create IVFFlat index with 100 lists for faster similarity search
            // Using cosine distance (vector_cosine_ops)
            DB::statement("
                CREATE INDEX {$indexName}
                ON {$table}
                USING ivfflat ({$column} vector_cosine_ops)
                WITH (lists = 100)
            ");
        }
    }
};
