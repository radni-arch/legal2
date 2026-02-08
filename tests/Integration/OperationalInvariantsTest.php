<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Operational invariants (data integrity tripwires).
 *
 * Rationale:
 * - Fast-growing agentic/RAG systems tend to fail via "half-written" state: counters drift,
 *   batches marked completed without all items processed, budget fields go negative, duplicates
 *   bypass expected uniqueness assumptions, etc.
 * - These tests encode those assumptions as executable checks.
 *
 * Notes:
 * - Tests are defensive: they skip if a table/column is not present in the current environment.
 * - They are intended to run frequently (CI) without external dependencies.
 */
class OperationalInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    private function requireTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Table '{$table}' does not exist in this environment");
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function countOrphans(string $childTable, string $childFk, string $parentTable, string $parentPk = 'id'): int
    {
        return (int) DB::table($childTable.' as c')
            ->leftJoin($parentTable.' as p', 'c.'.$childFk, '=', 'p.'.$parentPk)
            ->whereNotNull('c.'.$childFk)
            ->whereNull('p.'.$parentPk)
            ->count();
    }

    /**
     * Invariant: textract_batches counters never exceed total_files.
     */
    public function test_textract_batches_counters_are_consistent(): void
    {
        $this->requireTable('textract_batches');

        $bad = DB::table('textract_batches')
            ->whereRaw('(processed_files + failed_files) > total_files')
            ->count();

        $this->assertSame(0, (int) $bad, 'textract_batches has rows where processed+failed exceeds total_files');

        // If a batch is completed, it must account for all files.
        $completedBad = DB::table('textract_batches')
            ->where('status', 'completed')
            ->whereRaw('(processed_files + failed_files) != total_files')
            ->count();

        $this->assertSame(0, (int) $completedBad, 'textract_batches completed rows must satisfy processed+failed == total_files');

        // If completed_at is set, started_at must be set too.
        if ($this->hasColumn('textract_batches', 'started_at') && $this->hasColumn('textract_batches', 'completed_at')) {
            $timeBad = DB::table('textract_batches')
                ->whereNotNull('completed_at')
                ->where(function ($q) {
                    $q->whereNull('started_at')->orWhereColumn('completed_at', '<', 'started_at');
                })
                ->count();

            $this->assertSame(0, (int) $timeBad, 'textract_batches with completed_at must have started_at and completed_at >= started_at');
        }
    }

    /**
     * Invariant: textract_jobs.status is within expected set; succeeded jobs have no error, failed jobs have an error.
     */
    public function test_textract_jobs_status_and_error_consistency(): void
    {
        $this->requireTable('textract_jobs');

        // Status values per initial migration: queued|uploading|started|succeeded|failed
        $allowed = ['queued', 'uploading', 'started', 'succeeded', 'failed'];

        if ($this->hasColumn('textract_jobs', 'status')) {
            $badStatus = DB::table('textract_jobs')
                ->whereNotIn('status', $allowed)
                ->count();

            $this->assertSame(0, (int) $badStatus, 'textract_jobs contains unexpected status values');
        }

        if ($this->hasColumn('textract_jobs', 'status') && $this->hasColumn('textract_jobs', 'error')) {
            $succeededHasError = DB::table('textract_jobs')
                ->where('status', 'succeeded')
                ->whereNotNull('error')
                ->whereRaw("trim(error) <> ''")
                ->count();

            $this->assertSame(0, (int) $succeededHasError, 'textract_jobs succeeded rows should not have an error');

            $failedMissingError = DB::table('textract_jobs')
                ->where('status', 'failed')
                ->where(function ($q) {
                    $q->whereNull('error')->orWhereRaw("trim(error) = ''");
                })
                ->count();

            $this->assertSame(0, (int) $failedMissingError, 'textract_jobs failed rows should have a non-empty error');
        }

        // If textract_jobs.batch_id exists, it must reference textract_batches.id
        if ($this->hasColumn('textract_jobs', 'batch_id') && Schema::hasTable('textract_batches')) {
            $orphans = $this->countOrphans('textract_jobs', 'batch_id', 'textract_batches', 'id');
            $this->assertSame(0, $orphans, 'textract_jobs has batch_id referencing missing textract_batches rows');
        }
    }

    /**
     * Invariant: embedding_batches counters never exceed totals; completed batches account for all items.
     */
    public function test_embedding_batches_counters_are_consistent(): void
    {
        $this->requireTable('embedding_batches');

        $bad = DB::table('embedding_batches')
            ->whereRaw('(processed_items + failed_items) > total_items')
            ->count();

        $this->assertSame(0, (int) $bad, 'embedding_batches has rows where processed+failed exceeds total_items');

        $completedBad = DB::table('embedding_batches')
            ->where('status', 'completed')
            ->whereRaw('(processed_items + failed_items) != total_items')
            ->count();

        $this->assertSame(0, (int) $completedBad, 'embedding_batches completed rows must satisfy processed+failed == total_items');

        // Non-negative budgets
        foreach (['tokens_used', 'cost'] as $col) {
            if ($this->hasColumn('embedding_batches', $col)) {
                $neg = DB::table('embedding_batches')->where($col, '<', 0)->count();
                $this->assertSame(0, (int) $neg, "embedding_batches.{$col} must never be negative");
            }
        }
    }

    /**
     * Invariant: agent_runs budget fields never go negative and never exceed budgets when budgets are set.
     */
    public function test_agent_runs_budget_invariants(): void
    {
        $this->requireTable('agent_runs');

        foreach (['tokens_used', 'cost_spent'] as $col) {
            if ($this->hasColumn('agent_runs', $col)) {
                $neg = DB::table('agent_runs')->where($col, '<', 0)->count();
                $this->assertSame(0, (int) $neg, "agent_runs.{$col} must never be negative");
            }
        }

        if ($this->hasColumn('agent_runs', 'token_budget') && $this->hasColumn('agent_runs', 'tokens_used')) {
            $over = DB::table('agent_runs')
                ->whereNotNull('token_budget')
                ->whereColumn('tokens_used', '>', 'token_budget')
                ->count();

            $this->assertSame(0, (int) $over, 'agent_runs.tokens_used must not exceed token_budget when set');
        }

        if ($this->hasColumn('agent_runs', 'cost_budget') && $this->hasColumn('agent_runs', 'cost_spent')) {
            $over = DB::table('agent_runs')
                ->whereNotNull('cost_budget')
                ->whereColumn('cost_spent', '>', 'cost_budget')
                ->count();

            $this->assertSame(0, (int) $over, 'agent_runs.cost_spent must not exceed cost_budget when set');
        }

        if ($this->hasColumn('agent_runs', 'started_at') && $this->hasColumn('agent_runs', 'completed_at')) {
            $timeBad = DB::table('agent_runs')
                ->whereNotNull('completed_at')
                ->where(function ($q) {
                    $q->whereNull('started_at')->orWhereColumn('completed_at', '<', 'started_at');
                })
                ->count();

            $this->assertSame(0, (int) $timeBad, 'agent_runs with completed_at must have started_at and completed_at >= started_at');
        }
    }

    /**
     * Invariant: agent_vector_memories must not contain duplicates by (agent_name, content_hash), and access_count >= 0.
     */
    public function test_agent_vector_memories_uniqueness_and_counters(): void
    {
        $this->requireTable('agent_vector_memories');

        // uniqueness expected by migration
        if ($this->hasColumn('agent_vector_memories', 'agent_name') && $this->hasColumn('agent_vector_memories', 'content_hash')) {
            $dupes = DB::table('agent_vector_memories')
                ->select('agent_name', 'content_hash', DB::raw('COUNT(*) as c'))
                ->groupBy('agent_name', 'content_hash')
                ->having('c', '>', 1)
                ->count();

            $this->assertSame(0, (int) $dupes, 'agent_vector_memories contains duplicate (agent_name, content_hash) rows');

            $badHash = DB::table('agent_vector_memories')
                ->whereNull('content_hash')
                ->orWhereRaw('length(content_hash) <> 64')
                ->count();

            $this->assertSame(0, (int) $badHash, 'agent_vector_memories.content_hash must be non-null and length 64');
        }

        if ($this->hasColumn('agent_vector_memories', 'access_count')) {
            $neg = DB::table('agent_vector_memories')->where('access_count', '<', 0)->count();
            $this->assertSame(0, (int) $neg, 'agent_vector_memories.access_count must never be negative');
        }

        // Embedding dimension sanity: if embedding_dimensions exists, it must be > 0
        if ($this->hasColumn('agent_vector_memories', 'embedding_dimensions')) {
            $badDims = DB::table('agent_vector_memories')
                ->whereNotNull('embedding_dimensions')
                ->where('embedding_dimensions', '<=', 0)
                ->count();

            $this->assertSame(0, (int) $badDims, 'agent_vector_memories.embedding_dimensions must be positive when set');
        }
    }

    /**
     * Invariant: laws table must not contain duplicate (doc_id, content_hash) pairs; temporal fields must be consistent.
     */
    public function test_laws_uniqueness_and_temporal_consistency(): void
    {
        $this->requireTable('laws');

        if ($this->hasColumn('laws', 'doc_id') && $this->hasColumn('laws', 'content_hash')) {
            // unique(['doc_id','content_hash']) is expected
            $dupes = DB::table('laws')
                ->select('doc_id', 'content_hash', DB::raw('COUNT(*) as c'))
                ->groupBy('doc_id', 'content_hash')
                ->having('c', '>', 1)
                ->count();

            $this->assertSame(0, (int) $dupes, 'laws contains duplicate (doc_id, content_hash) rows');

            $badHash = DB::table('laws')
                ->whereNull('content_hash')
                ->orWhereRaw('length(content_hash) <> 64')
                ->count();

            $this->assertSame(0, (int) $badHash, 'laws.content_hash must be non-null and length 64');
        }

        // Temporal reasoning fields: valid_from <= valid_until (when both are present)
        if ($this->hasColumn('laws', 'valid_from') && $this->hasColumn('laws', 'valid_until')) {
            $badTemporal = DB::table('laws')
                ->whereNotNull('valid_from')
                ->whereNotNull('valid_until')
                ->whereColumn('valid_until', '<', 'valid_from')
                ->count();

            $this->assertSame(0, (int) $badTemporal, 'laws.valid_until must be >= valid_from when both are set');
        }
    }

    /**
     * Invariant: court_decision_documents are unique by (decision_id, content_hash) and never orphaned.
     */
    public function test_court_decision_documents_integrity(): void
    {
        $this->requireTable('court_decision_documents');

        if (Schema::hasTable('court_decisions') && $this->hasColumn('court_decision_documents', 'decision_id')) {
            $orphans = $this->countOrphans('court_decision_documents', 'decision_id', 'court_decisions', 'id');
            $this->assertSame(0, $orphans, 'court_decision_documents contains decision_id referencing missing court_decisions');
        }

        if ($this->hasColumn('court_decision_documents', 'decision_id') && $this->hasColumn('court_decision_documents', 'content_hash')) {
            $dupes = DB::table('court_decision_documents')
                ->select('decision_id', 'content_hash', DB::raw('COUNT(*) as c'))
                ->groupBy('decision_id', 'content_hash')
                ->having('c', '>', 1)
                ->count();

            $this->assertSame(0, (int) $dupes, 'court_decision_documents contains duplicate (decision_id, content_hash) rows');
        }
    }

    /**
     * Invariant: decision_graph_embeddings must reference existing court_decisions and have graph_embedding populated.
     */
    public function test_decision_graph_embeddings_integrity(): void
    {
        $this->requireTable('decision_graph_embeddings');

        if (Schema::hasTable('court_decisions') && $this->hasColumn('decision_graph_embeddings', 'decision_id')) {
            $orphans = $this->countOrphans('decision_graph_embeddings', 'decision_id', 'court_decisions', 'id');
            $this->assertSame(0, $orphans, 'decision_graph_embeddings contains decision_id referencing missing court_decisions');
        }

        // The graph_embedding column is added via raw SQL; if present, enforce non-null.
        if ($this->hasColumn('decision_graph_embeddings', 'graph_embedding')) {
            $missing = DB::table('decision_graph_embeddings')->whereNull('graph_embedding')->count();
            $this->assertSame(0, (int) $missing, 'decision_graph_embeddings.graph_embedding must not be null');
        }

        // If trained_at is set, model_version should be present
        if ($this->hasColumn('decision_graph_embeddings', 'trained_at') && $this->hasColumn('decision_graph_embeddings', 'model_version')) {
            $bad = DB::table('decision_graph_embeddings')
                ->whereNotNull('trained_at')
                ->where(function ($q) {
                    $q->whereNull('model_version')->orWhereRaw("trim(model_version) = ''");
                })
                ->count();

            $this->assertSame(0, (int) $bad, 'decision_graph_embeddings with trained_at must have non-empty model_version');
        }
    }
}
