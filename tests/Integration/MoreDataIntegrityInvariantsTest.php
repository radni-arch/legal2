<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Data Integrity / Domain Invariants (More)
 *
 * Reasoning:
 * - These tests encode "must never happen" states across the OCR→embedding→search pipeline.
 * - They are intentionally DB-first: they catch regressions even when UI/tests don't hit the path.
 * - They are written to be environment-tolerant (pgvector vs JSON embeddings, optional tables).
 */
class MoreDataIntegrityInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function textract_jobs_have_valid_status_values(): void
    {
        if (! Schema::hasTable('textract_jobs')) {
            $this->markTestSkipped('textract_jobs table missing');
        }

        // textract_jobs migration documents: queued|uploading|started|succeeded|failed fileciteturn0file8
        // We also allow the pipeline/generic variants pending|processing|completed for compatibility
        // with textract_documents processing_status semantics. fileciteturn2file9
        $allowed = [
            'queued', 'uploading', 'started', 'succeeded', 'failed',
            'pending', 'processing', 'completed',
        ];

        $invalid = DB::table('textract_jobs')
            ->where(function ($q) use ($allowed) {
                $q->whereNull('status')
                    ->orWhere('status', '')
                    ->orWhereNotIn('status', $allowed);
            })
            ->count();

        $this->assertSame(0, $invalid, 'textract_jobs.status must be non-empty and in allowed set');
    }

    /** @test */
    public function textract_documents_processing_state_and_embeddings_are_consistent(): void
    {
        if (! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract_documents table missing');
        }

        // textract_documents defines processing_status: pending|processing|completed|failed fileciteturn2file9
        $allowed = ['pending', 'processing', 'completed', 'failed'];

        $invalidStatus = DB::table('textract_documents')
            ->whereNotIn('processing_status', $allowed)
            ->count();
        $this->assertSame(0, $invalidStatus, 'textract_documents.processing_status must be valid');

        // If "completed", we must have an embedding and embedded_at.
        $completedMissingEmbedding = DB::table('textract_documents')
            ->where('processing_status', 'completed')
            ->where(function ($q) {
                $q->whereNull('embedded_at')
                    ->orWhereNull('embedding');
            })
            ->count();
        $this->assertSame(0, $completedMissingEmbedding, 'completed textract_documents must have embedded_at and embedding');

        // If we have embedded_at, we should have embedding.
        $embeddedAtWithoutEmbedding = DB::table('textract_documents')
            ->whereNotNull('embedded_at')
            ->whereNull('embedding')
            ->count();
        $this->assertSame(0, $embeddedAtWithoutEmbedding, 'textract_documents.embedded_at implies embedding is present');

        // If failed, we should have an error recorded.
        $failedWithoutError = DB::table('textract_documents')
            ->where('processing_status', 'failed')
            ->whereNull('processing_error')
            ->count();
        $this->assertSame(0, $failedWithoutError, 'failed textract_documents must record processing_error');

        // If embedding exists, embedding metadata should be present.
        $embeddingMissingMeta = DB::table('textract_documents')
            ->whereNotNull('embedding')
            ->where(function ($q) {
                $q->whereNull('embedding_provider')
                    ->orWhere('embedding_provider', '')
                    ->orWhereNull('embedding_model')
                    ->orWhere('embedding_model', '')
                    ->orWhereNull('embedding_dimensions')
                    ->orWhere('embedding_dimensions', '<=', 0);
            })
            ->count();
        $this->assertSame(0, $embeddingMissingMeta, 'textract_documents with embedding must have provider/model/dimensions');
    }

    /** @test */
    public function textract_documents_chunk_index_is_unique_per_job(): void
    {
        if (! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract_documents table missing');
        }

        // There is an index on (textract_job_id, chunk_index) fileciteturn2file9.
        // We enforce uniqueness to prevent duplicate chunks corrupting search & reconstruction.
        $dupes = DB::table('textract_documents')
            ->select('textract_job_id', 'chunk_index')
            ->selectRaw('COUNT(*) as c')
            ->groupBy('textract_job_id', 'chunk_index')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->get();

        $this->assertCount(0, $dupes, 'No duplicate textract_documents (textract_job_id, chunk_index) pairs allowed');
    }

    /** @test */
    public function cases_documents_chunk_index_is_unique_per_case_and_doc_id_when_present(): void
    {
        $table = config('vizra-adk.tables.cases_documents', 'cases_documents');
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("{$table} table missing");
        }

        // cases_documents defines an index (case_id, doc_id, chunk_index). fileciteturn1file1
        // We enforce uniqueness when case_id/doc_id exist to avoid duplicated chunks.
        $dupes = DB::table($table)
            ->whereNotNull('case_id')
            ->whereNotNull('doc_id')
            ->select('case_id', 'doc_id', 'chunk_index')
            ->selectRaw('COUNT(*) as c')
            ->groupBy('case_id', 'doc_id', 'chunk_index')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->get();

        $this->assertCount(0, $dupes, 'No duplicate cases_documents (case_id, doc_id, chunk_index) allowed');
    }

    /** @test */
    public function vector_store_records_have_valid_sha256_content_hash_when_content_present(): void
    {
        // The CaseDocument model auto-fills content_hash as sha256(content). fileciteturn1file5
        // Laws also compute content_hash on create. fileciteturn1file14
        // CourtDecision documents enforce unique(decision_id, content_hash). fileciteturn1file4

        $targets = [
            ['table' => config('vizra-adk.tables.cases_documents', 'cases_documents'), 'contentCol' => 'content', 'hashCol' => 'content_hash', 'limit' => 200],
            ['table' => config('vizra-adk.tables.court_decision_documents', 'court_decision_documents'), 'contentCol' => 'content', 'hashCol' => 'content_hash', 'limit' => 200],
            ['table' => config('vizra-adk.tables.laws', 'laws'), 'contentCol' => 'content', 'hashCol' => 'content_hash', 'limit' => 50],
        ];

        foreach ($targets as $t) {
            $table = $t['table'];
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, $t['contentCol']) || ! Schema::hasColumn($table, $t['hashCol'])) {
                continue;
            }

            $rows = DB::table($table)
                ->select('id', $t['contentCol'].' as content', $t['hashCol'].' as content_hash')
                ->whereNotNull($t['contentCol'])
                ->limit($t['limit'])
                ->get();

            foreach ($rows as $row) {
                $content = (string) ($row->content ?? '');
                $hash = (string) ($row->content_hash ?? '');

                if ($content === '') {
                    // Some tables allow nullable content (e.g., cases_documents) fileciteturn1file1.
                    // If content is empty, we don't enforce hash.
                    continue;
                }

                $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash, "{$table}.content_hash must be sha256 hex (row {$row->id})");
                $this->assertSame(hash('sha256', $content), $hash, "{$table}.content_hash must equal sha256(content) (row {$row->id})");
            }
        }

        $this->assertTrue(true); // makes PHPUnit happy if all tables are missing in some env
    }

    /** @test */
    public function embedding_metadata_is_present_when_embedding_exists_across_vector_stores(): void
    {
        // laws table: embedding_provider/model/dimensions are required by schema. fileciteturn1file15
        // cases_documents: provider/model/dims nullable, but model sets defaults on create. fileciteturn1file5
        // court_decision_documents: provider/model/dims required by schema. fileciteturn1file4

        $this->assertEmbeddingMetaForTable(config('vizra-adk.tables.laws', 'laws'));
        $this->assertEmbeddingMetaForTable(config('vizra-adk.tables.cases_documents', 'cases_documents'));
        $this->assertEmbeddingMetaForTable(config('vizra-adk.tables.court_decision_documents', 'court_decision_documents'));
    }

    /** @test */
    public function fulltext_tsvector_is_populated_when_present(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Full-text tsvector invariant applies to PostgreSQL only');
        }

        // Migration adds content_tsv + trigger for laws, court_decision_documents, cases_documents. fileciteturn2file1
        foreach ([
            config('vizra-adk.tables.laws', 'laws'),
            config('vizra-adk.tables.court_decision_documents', 'court_decision_documents'),
            config('vizra-adk.tables.cases_documents', 'cases_documents'),
        ] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'content_tsv')) {
                continue;
            }

            $invalid = DB::table($table)
                ->whereNull('content_tsv')
                ->whereRaw("COALESCE(title,'') <> '' OR COALESCE(content,'') <> ''")
                ->count();

            $this->assertSame(0, $invalid, "{$table}.content_tsv must be non-null when content/title exists");
        }

        $this->assertTrue(true);
    }

    /** @test */
    public function batch_processing_counters_are_self_consistent(): void
    {
        // textract_batches + embedding_batches are created in distributed processing migration. fileciteturn0file13

        if (Schema::hasTable('textract_batches')) {
            $invalid = DB::table('textract_batches')
                ->whereRaw('(processed_files + failed_files) > total_files')
                ->count();
            $this->assertSame(0, $invalid, 'textract_batches: processed_files+failed_files must not exceed total_files');

            $completedInvalid = DB::table('textract_batches')
                ->where('status', 'completed')
                ->where(function ($q) {
                    $q->whereNull('completed_at')
                        ->orWhereRaw('(processed_files + failed_files) <> total_files');
                })
                ->count();
            $this->assertSame(0, $completedInvalid, 'textract_batches: completed must have completed_at and all files accounted for');
        }

        if (Schema::hasTable('embedding_batches')) {
            $invalid = DB::table('embedding_batches')
                ->whereRaw('(processed_items + failed_items) > total_items')
                ->count();
            $this->assertSame(0, $invalid, 'embedding_batches: processed_items+failed_items must not exceed total_items');

            $completedInvalid = DB::table('embedding_batches')
                ->where('status', 'completed')
                ->where(function ($q) {
                    $q->whereNull('completed_at')
                        ->orWhereRaw('(processed_items + failed_items) <> total_items');
                })
                ->count();
            $this->assertSame(0, $completedInvalid, 'embedding_batches: completed must have completed_at and all items accounted for');

            $processingInvalid = DB::table('embedding_batches')
                ->whereIn('status', ['processing', 'completed', 'failed'])
                ->whereNull('started_at')
                ->count();
            $this->assertSame(0, $processingInvalid, 'embedding_batches: processing/completed/failed should have started_at');
        }

        $this->assertTrue(true);
    }

    /** @test */
    public function agent_runs_respect_budget_and_time_caps_when_set(): void
    {
        if (! Schema::hasTable('agent_runs')) {
            $this->markTestSkipped('agent_runs table missing');
        }

        // Columns added in migration: token_budget/tokens_used/cost_budget/cost_spent/time_limit_seconds/... fileciteturn2file10
        $has = fn (string $col) => Schema::hasColumn('agent_runs', $col);

        if ($has('token_budget') && $has('tokens_used')) {
            $over = DB::table('agent_runs')
                ->whereNotNull('token_budget')
                ->whereRaw('tokens_used > token_budget')
                ->count();
            $this->assertSame(0, $over, 'agent_runs.tokens_used must not exceed token_budget when set');
        }

        if ($has('cost_budget') && $has('cost_spent')) {
            $over = DB::table('agent_runs')
                ->whereNotNull('cost_budget')
                ->whereRaw('cost_spent > cost_budget')
                ->count();
            $this->assertSame(0, $over, 'agent_runs.cost_spent must not exceed cost_budget when set');
        }

        if ($has('current_iteration') && $has('max_iterations')) {
            $over = DB::table('agent_runs')
                ->whereRaw('current_iteration > max_iterations')
                ->count();
            $this->assertSame(0, $over, 'agent_runs.current_iteration must not exceed max_iterations');
        }

        if ($has('status') && $has('started_at')) {
            $missingStartedAt = DB::table('agent_runs')
                ->whereIn('status', ['running', 'completed', 'failed'])
                ->whereNull('started_at')
                ->count();
            $this->assertSame(0, $missingStartedAt, 'agent_runs.started_at must be set when running/completed/failed');
        }

        if ($has('status') && $has('completed_at')) {
            $missingCompletedAt = DB::table('agent_runs')
                ->whereIn('status', ['completed', 'failed'])
                ->whereNull('completed_at')
                ->count();
            $this->assertSame(0, $missingCompletedAt, 'agent_runs.completed_at must be set when completed/failed');
        }

        if ($has('elapsed_seconds') && $has('time_limit_seconds')) {
            $over = DB::table('agent_runs')
                ->whereNotNull('time_limit_seconds')
                ->whereNotNull('elapsed_seconds')
                ->whereRaw('elapsed_seconds > time_limit_seconds')
                ->count();
            $this->assertSame(0, $over, 'agent_runs.elapsed_seconds must not exceed time_limit_seconds when set');
        }
    }

    /** @test */
    public function agent_vector_memories_have_non_negative_access_count_and_valid_hash(): void
    {
        $table = config('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("{$table} table missing");
        }

        // access_count added with default 0. fileciteturn1file11
        if (Schema::hasColumn($table, 'access_count')) {
            $neg = DB::table($table)->where('access_count', '<', 0)->count();
            $this->assertSame(0, $neg, 'agent_vector_memories.access_count must be >= 0');
        }

        // content_hash is indexed and unique per agent_name, must be SHA-256 hex. fileciteturn0file1
        if (Schema::hasColumn($table, 'content_hash')) {
            $rows = DB::table($table)
                ->select('id', 'content_hash')
                ->whereNotNull('content_hash')
                ->limit(200)
                ->get();

            foreach ($rows as $row) {
                $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $row->content_hash, "agent_vector_memories.content_hash invalid (row {$row->id})");
            }
        }

        if (Schema::hasColumn($table, 'embedding_dimensions')) {
            $badDims = DB::table($table)->where('embedding_dimensions', '<=', 0)->count();
            $this->assertSame(0, $badDims, 'agent_vector_memories.embedding_dimensions must be > 0');
        }
    }

    /** @test */
    public function decision_graph_embeddings_are_not_half_baked_when_present(): void
    {
        // decision_graph_embeddings stores Node2Vec embeddings for decisions. fileciteturn2file0
        if (! Schema::hasTable('decision_graph_embeddings')) {
            $this->markTestSkipped('decision_graph_embeddings table missing');
        }

        // Enforce: trained_at implies graph_embedding is present.
        $halfBaked = DB::table('decision_graph_embeddings')
            ->whereNotNull('trained_at')
            ->whereNull('graph_embedding')
            ->count();

        $this->assertSame(0, $halfBaked, 'decision_graph_embeddings.trained_at implies graph_embedding must be present');

        // Enforce: trained_at implies model_version is present.
        $missingVersion = DB::table('decision_graph_embeddings')
            ->whereNotNull('trained_at')
            ->where(function ($q) {
                $q->whereNull('model_version')->orWhere('model_version', '');
            })
            ->count();

        $this->assertSame(0, $missingVersion, 'decision_graph_embeddings.trained_at implies model_version must be set');
    }

    private function assertEmbeddingMetaForTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $embeddingCol = null;
        if (Schema::hasColumn($table, 'embedding')) {
            $embeddingCol = 'embedding';
        } elseif (Schema::hasColumn($table, 'embedding_vector')) {
            $embeddingCol = 'embedding_vector';
        }

        // If there's no embedding column in this environment, nothing to enforce.
        if ($embeddingCol === null) {
            return;
        }

        $missingMeta = DB::table($table)
            ->whereNotNull($embeddingCol)
            ->where(function ($q) {
                // Some tables store JSON/text; "" should count as missing.
                $q->whereNull('embedding_provider')
                    ->orWhere('embedding_provider', '')
                    ->orWhereNull('embedding_model')
                    ->orWhere('embedding_model', '')
                    ->orWhereNull('embedding_dimensions')
                    ->orWhere('embedding_dimensions', '<=', 0);
            })
            ->count();

        $this->assertSame(0, $missingMeta, "{$table}: embedding implies embedding_provider/model/dimensions");
    }
}
