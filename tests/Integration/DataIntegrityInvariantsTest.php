<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Data Integrity / Domain Invariant Tests
 *
 * Reasoning:
 * - This project is an agentic/OCR/graph RAG system where the biggest risk is *silent* data corruption
 *   (half-written embeddings, orphan rows, inconsistent budgets/checkpoints, etc.).
 * - These tests encode non-negotiable truths about storage and pipeline state.
 * - They are intentionally "DB-first": they catch issues regardless of where they originate (UI, jobs, agents).
 *
 * Notes:
 * - Many invariants are conditional (only enforced if the relevant table/columns exist)
 *   because the repo supports multiple environments and optional features.
 *
 * @group invariants
 */
class DataIntegrityInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    private function t(string $configKey, string $default): string
    {
        return (string) config($configKey, $default);
    }

    private function assertNoOrphans(string $childTable, string $childFk, string $parentTable, string $parentPk = 'id'): void
    {
        if (! Schema::hasTable($childTable) || ! Schema::hasTable($parentTable)) {
            $this->markTestSkipped("Missing tables: {$childTable} or {$parentTable}");
        }
        if (! Schema::hasColumn($childTable, $childFk) || ! Schema::hasColumn($parentTable, $parentPk)) {
            $this->markTestSkipped("Missing columns for orphan check: {$childTable}.{$childFk} or {$parentTable}.{$parentPk}");
        }

        $orphans = DB::table($childTable.' as c')
            ->leftJoin($parentTable.' as p', 'c.'.$childFk, '=', 'p.'.$parentPk)
            ->whereNotNull('c.'.$childFk)
            ->whereNull('p.'.$parentPk)
            ->count();

        $this->assertSame(0, $orphans, "Found {$orphans} orphan rows in {$childTable}.{$childFk} → {$parentTable}.{$parentPk}");
    }

    private function assertColumnNotEmpty(string $table, string $column, ?callable $additionalWhere = null, string $message = ''): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            $this->markTestSkipped("Missing {$table}.{$column}");
        }

        $q = DB::table($table);
        if ($additionalWhere) {
            $additionalWhere($q);
        }

        $bad = (clone $q)
            ->where(function ($w) use ($column) {
                $w->whereNull($column)->orWhere($column, '=', '');
            })
            ->count();

        $this->assertSame(0, $bad, $message !== '' ? $message : "{$table}.{$column} must not be NULL/empty (bad={$bad})");
    }

    /**
     * Invariant: Agent vector memories are deduplicated by (agent_name, content_hash).
     *
     * Reasoning:
     * - Dedup is foundational for memory reuse and cost control.
     * - Table enforces unique(agent_name, content_hash) in migration. fileciteturn1file2
     */
    public function test_agent_vector_memories_have_no_duplicate_hash_per_agent(): void
    {
        $table = $this->t('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped('agent_vector_memories table missing');
        }
        if (! Schema::hasColumn($table, 'agent_name') || ! Schema::hasColumn($table, 'content_hash')) {
            $this->markTestSkipped('agent_vector_memories missing agent_name/content_hash');
        }

        $dupes = DB::table($table)
            ->select('agent_name', 'content_hash', DB::raw('COUNT(*) as c'))
            ->groupBy('agent_name', 'content_hash')
            ->having('c', '>', 1)
            ->count();

        $this->assertSame(0, $dupes, 'Duplicate (agent_name, content_hash) rows detected in agent_vector_memories');
    }

    /**
     * Invariant: AgentVectorMemory rows must have sane bookkeeping fields.
     *
     * Reasoning:
     * - access_count is used for popularity stats and UI. It must never go negative. fileciteturn1file0
     * - content_hash must be present (used for dedup) and be 64 chars (sha256).
     */
    public function test_agent_vector_memory_bookkeeping_is_sane(): void
    {
        $table = $this->t('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped('agent_vector_memories table missing');
        }

        if (Schema::hasColumn($table, 'access_count')) {
            $neg = DB::table($table)->where('access_count', '<', 0)->count();
            $this->assertSame(0, $neg, 'agent_vector_memories.access_count must be >= 0');
        }

        if (Schema::hasColumn($table, 'content_hash')) {
            $badHash = DB::table($table)
                ->whereNull('content_hash')
                ->orWhere('content_hash', '=', '')
                ->orWhereRaw('LENGTH(content_hash) != 64')
                ->count();
            $this->assertSame(0, $badHash, 'agent_vector_memories.content_hash must be non-empty sha256 (len=64)');
        }
    }

    /**
     * Invariant: Agent run budgets and checkpoint flags are internally consistent.
     *
     * Reasoning:
     * - Runs enforce token/cost/time caps (stop conditions in agent loop). fileciteturn1file12 fileciteturn0file8
     * - Broken bookkeeping causes runaway cost or stuck resume.
     */
    public function test_agent_runs_budget_and_checkpoint_consistency(): void
    {
        if (! Schema::hasTable('agent_runs')) {
            $this->markTestSkipped('agent_runs table missing');
        }

        // tokens_used <= token_budget if budget provided
        if (Schema::hasColumn('agent_runs', 'token_budget') && Schema::hasColumn('agent_runs', 'tokens_used')) {
            $over = DB::table('agent_runs')
                ->whereNotNull('token_budget')
                ->whereColumn('tokens_used', '>', 'token_budget')
                ->count();
            $this->assertSame(0, $over, 'agent_runs.tokens_used must not exceed token_budget');
        }

        // cost_spent <= cost_budget if budget provided
        if (Schema::hasColumn('agent_runs', 'cost_budget') && Schema::hasColumn('agent_runs', 'cost_spent')) {
            $over = DB::table('agent_runs')
                ->whereNotNull('cost_budget')
                ->whereColumn('cost_spent', '>', 'cost_budget')
                ->count();
            $this->assertSame(0, $over, 'agent_runs.cost_spent must not exceed cost_budget');
        }

        // completed_at implies started_at
        if (Schema::hasColumn('agent_runs', 'completed_at') && Schema::hasColumn('agent_runs', 'started_at')) {
            $bad = DB::table('agent_runs')
                ->whereNotNull('completed_at')
                ->whereNull('started_at')
                ->count();
            $this->assertSame(0, $bad, 'agent_runs.completed_at set but started_at is NULL');
        }

        // can_resume implies checkpoint_state and last_checkpoint_at
        if (Schema::hasColumn('agent_runs', 'can_resume') && Schema::hasColumn('agent_runs', 'checkpoint_state') && Schema::hasColumn('agent_runs', 'last_checkpoint_at')) {
            $bad = DB::table('agent_runs')
                ->where('can_resume', true)
                ->where(function ($q) {
                    $q->whereNull('checkpoint_state')->orWhereNull('last_checkpoint_at');
                })
                ->count();

            $this->assertSame(0, $bad, 'agent_runs.can_resume=true requires checkpoint_state and last_checkpoint_at');
        }
    }

    /**
     * Invariant: Textract chunk rows reference real jobs and have valid processing status.
     *
     * Reasoning:
     * - textract_documents is the backbone of OCR chunking + embeddings. fileciteturn0file2
     */
    public function test_textract_documents_referential_and_status_integrity(): void
    {
        if (! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract_documents missing');
        }

        // FK: textract_documents.textract_job_id -> textract_jobs.id
        $this->assertNoOrphans('textract_documents', 'textract_job_id', 'textract_jobs', 'id');

        // chunk_index must be >= 0
        if (Schema::hasColumn('textract_documents', 'chunk_index')) {
            $bad = DB::table('textract_documents')->where('chunk_index', '<', 0)->count();
            $this->assertSame(0, $bad, 'textract_documents.chunk_index must be >= 0');
        }

        // processing_status in {pending,processing,completed,failed}
        if (Schema::hasColumn('textract_documents', 'processing_status')) {
            $bad = DB::table('textract_documents')
                ->whereNotIn('processing_status', ['pending', 'processing', 'completed', 'failed'])
                ->count();
            $this->assertSame(0, $bad, 'textract_documents.processing_status must be one of pending|processing|completed|failed');
        }

        // embedded_at implies embedding metadata exists
        if (Schema::hasColumn('textract_documents', 'embedded_at')) {
            $q = DB::table('textract_documents')->whereNotNull('embedded_at');

            if (Schema::hasColumn('textract_documents', 'embedding_model')) {
                $bad = (clone $q)->whereNull('embedding_model')->count();
                $this->assertSame(0, $bad, 'textract_documents.embedded_at set but embedding_model is NULL');
            }

            if (Schema::hasColumn('textract_documents', 'embedding_dimensions')) {
                $bad = (clone $q)->whereNull('embedding_dimensions')->orWhere('embedding_dimensions', '<=', 0)->count();
                $this->assertSame(0, $bad, 'textract_documents.embedded_at set but embedding_dimensions missing/invalid');
            }

            // embedding vector column may be either embedding (pgvector) or embedding_vector (json)
            $hasEmbedding = Schema::hasColumn('textract_documents', 'embedding') || Schema::hasColumn('textract_documents', 'embedding_vector') || Schema::hasColumn('textract_documents', 'embedding');
            if ($hasEmbedding) {
                $bad = (clone $q)->where(function ($w) {
                    if (Schema::hasColumn('textract_documents', 'embedding')) {
                        $w->whereNull('embedding');
                    }
                    if (Schema::hasColumn('textract_documents', 'embedding_vector')) {
                        $w->orWhereNull('embedding_vector');
                    }
                })->count();

                // NOTE: we allow 0 bad rows; if your pipeline intentionally sets embedded_at without vector,
                // relax this check.
                $this->assertSame(0, $bad, 'textract_documents.embedded_at set but embedding is NULL');
            }
        }
    }

    /**
     * Invariant: Decision graph embeddings are not half-written.
     *
     * Reasoning:
     * - decision_graph_embeddings stores Node2Vec graph vectors to support hybrid search. fileciteturn0file1
     * - A trained_at timestamp without a vector implies partial writes / failed jobs.
     */
    public function test_decision_graph_embeddings_not_half_written(): void
    {
        if (! Schema::hasTable('decision_graph_embeddings')) {
            $this->markTestSkipped('decision_graph_embeddings table missing');
        }

        // FK to court_decisions is present in migration, but we still assert no orphans (defensive).
        $this->assertNoOrphans('decision_graph_embeddings', 'decision_id', 'court_decisions', 'id');

        // trained_at implies graph_embedding is not null
        if (Schema::hasColumn('decision_graph_embeddings', 'trained_at')) {
            // vector column added via raw SQL; use information_schema to check it exists
            $hasVectorCol = false;
            if (DB::connection()->getDriverName() === 'pgsql') {
                $hasVectorCol = ! empty(DB::select("SELECT 1 FROM information_schema.columns WHERE table_name = 'decision_graph_embeddings' AND column_name = 'graph_embedding'"));
            } else {
                $hasVectorCol = Schema::hasColumn('decision_graph_embeddings', 'graph_embedding');
            }

            if ($hasVectorCol) {
                $bad = DB::table('decision_graph_embeddings')
                    ->whereNotNull('trained_at')
                    ->whereNull('graph_embedding')
                    ->count();
                $this->assertSame(0, $bad, 'decision_graph_embeddings.trained_at set but graph_embedding is NULL');
            }
        }
    }

    /**
     * Invariant: If PostgreSQL fulltext columns are enabled, they must exist and be wired via triggers.
     *
     * Reasoning:
     * - Full-text search uses content_tsv and a trigger to keep it in sync. fileciteturn1file19
     * - If the trigger is missing, search degrades silently.
     */
    public function test_pgsql_fulltext_columns_and_triggers_exist_when_enabled(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Fulltext trigger invariant applies to PostgreSQL only');
        }

        $tables = [
            $this->t('vizra-adk.tables.laws', 'laws'),
            $this->t('vizra-adk.tables.court_decision_documents', 'court_decision_documents'),
            $this->t('vizra-adk.tables.cases_documents', 'cases_documents'),
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Column exists
            $this->assertTrue(Schema::hasColumn($table, 'content_tsv'), "{$table}.content_tsv must exist (fulltext migration)");

            // Trigger exists (tsvectorupdate)
            $trigger = DB::selectOne(
                'SELECT 1 FROM pg_trigger WHERE tgname = ? AND tgrelid = ?::regclass',
                ['tsvectorupdate', $table]
            );
            $this->assertNotNull($trigger, "{$table} must have trigger tsvectorupdate");

            // Function exists ({table}_tsvector_update_trigger)
            $fn = $table.'_tsvector_update_trigger';
            $fnExists = DB::selectOne('SELECT 1 FROM pg_proc WHERE proname = ?', [$fn]);
            $this->assertNotNull($fnExists, "PostgreSQL function {$fn} must exist");
        }
    }

    /**
     * Invariant: Case uploads reconstructed from OCR must not create empty content rows.
     *
     * Reasoning:
     * - PersistReconstructedStep constructs fullText from OCR lines and then stores chunks or fallback doc. fileciteturn4file7 fileciteturn4file15
     * - Empty content implies broken OCR reconstruction or broken mapping.
     */
    public function test_case_documents_never_have_empty_content_when_present(): void
    {
        $table = $this->t('vizra-adk.tables.cases_documents', 'cases_documents');
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'content')) {
            $this->markTestSkipped('cases_documents.content missing');
        }

        // Only check rows that claim to be OCR-related (category or tags may exist; we keep this generic)
        $q = DB::table($table);
        if (Schema::hasColumn($table, 'category')) {
            $q->whereIn('category', ['textract', 'case', 'decision', 'law'])->orWhereNull('category');
        }

        $bad = (clone $q)
            ->where(function ($w) {
                $w->whereNull('content')->orWhere('content', '=', '');
            })
            ->count();

        $this->assertSame(0, $bad, 'cases_documents.content must not be NULL/empty');
    }

    /**
     * Invariant: Laws that have temporal fields must satisfy basic temporal logic.
     *
     * Reasoning:
     * - Temporal legal reasoning relies on valid_from/valid_until fields. fileciteturn0file15
     */
    public function test_law_temporal_fields_are_coherent_when_present(): void
    {
        $laws = $this->t('vizra-adk.tables.laws', 'laws');
        if (! Schema::hasTable($laws)) {
            $this->markTestSkipped('laws table missing');
        }

        if (! Schema::hasColumn($laws, 'valid_from') || ! Schema::hasColumn($laws, 'valid_until')) {
            $this->markTestSkipped('Temporal fields not enabled on laws table');
        }

        // valid_until must be >= valid_from when both present
        $bad = DB::table($laws)
            ->whereNotNull('valid_from')
            ->whereNotNull('valid_until')
            ->whereColumn('valid_until', '<', 'valid_from')
            ->count();

        $this->assertSame(0, $bad, 'laws.valid_until must be >= laws.valid_from when both are present');
    }
}
