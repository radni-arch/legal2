<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * DataIntegrityInvariantsMoreTest
 *
 * Reasoning:
 * - These are NOT "unit" tests. They are executable invariants that prove the database and pipeline
 *   states remain structurally valid.
 * - They aim to catch the most common "AI codebase" failure modes: half-written records,
 *   inconsistent statuses, orphan rows, missing embeddings, and broken retry queues.
 *
 * Notes:
 * - Tests are defensive across environments (pgvector vs JSON vectors; optional columns).
 * - They fail loudly when an invariant is violated, and skip only if a table truly does not exist.
 */
class DataIntegrityInvariantsMoreTest extends TestCase
{
    use UsesTestDatabase;

    private function requireTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Table '{$table}' does not exist - schema not initialized.");
        }
    }

    private function embeddingColumnFor(string $table): ?string
    {
        if (Schema::hasColumn($table, 'embedding')) {
            return 'embedding';
        }
        if (Schema::hasColumn($table, 'embedding_vector')) {
            return 'embedding_vector';
        }

        return null;
    }

    private function assertSha256Like(?string $hash, string $context): void
    {
        if ($hash === null || $hash === '') {
            $this->fail("Missing content_hash/sha256 for {$context}");
        }
        $this->assertSame(64, strlen($hash), "Hash must be 64 chars for {$context}");
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/i', $hash, "Hash must look hex for {$context}");
    }

    private function decodeVector(mixed $raw): array
    {
        // JSON columns are returned as string in many drivers.
        if (is_array($raw)) {
            return $raw;
        }
        if ($raw === null) {
            return [];
        }
        if (is_string($raw)) {
            $raw = trim($raw);

            // pgvector sometimes comes back as "[0,0,0]".
            if (str_starts_with($raw, '[') && str_ends_with($raw, ']')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Invariant: TextractDocument processing_status must be one of pending|processing|completed|failed
     * and completed rows must have embedded_at and a non-empty embedding vector.
     *
     * Why: Prevents "processed" OCR chunks with no embedding and no timestamp, which breaks similarity search.
     */
    public function test_textract_documents_status_and_embedding_consistency(): void
    {
        $this->requireTable('textract_documents');

        $allowed = ['pending', 'processing', 'completed', 'failed'];

        $rows = DB::table('textract_documents')->select([
            'id',
            'textract_job_id',
            'chunk_index',
            'processing_status',
            'embedded_at',
            'embedding',
            'embedding_dimensions',
        ])->limit(500)->get();

        foreach ($rows as $r) {
            $this->assertTrue(in_array($r->processing_status, $allowed, true), "Invalid processing_status {$r->processing_status} for textract_documents.id={$r->id}");

            // chunk_index is an order field; it should never be negative.
            $this->assertGreaterThanOrEqual(0, (int) $r->chunk_index, "Negative chunk_index for textract_documents.id={$r->id}");

            if ($r->processing_status === 'completed') {
                $this->assertNotNull($r->embedded_at, "Completed chunk must have embedded_at for textract_documents.id={$r->id}");

                $vec = $this->decodeVector($r->embedding);
                $this->assertNotEmpty($vec, "Completed chunk must have non-empty embedding for textract_documents.id={$r->id}");

                if (! empty($r->embedding_dimensions)) {
                    // Don't require exact match (some tests use small mock vectors), but it must be coherent.
                    $this->assertGreaterThan(0, (int) $r->embedding_dimensions, "embedding_dimensions must be >0 for textract_documents.id={$r->id}");
                }
            }
        }
    }

    /**
     * Invariant: For each textract_job_id, chunk_index should start at 0 and be gap-free (contiguous).
     *
     * Why: Reconstructing full documents from chunks assumes contiguous ordering.
     */
    public function test_textract_documents_chunk_indices_are_contiguous_per_job(): void
    {
        $this->requireTable('textract_documents');

        $jobIds = DB::table('textract_documents')
            ->select('textract_job_id')
            ->groupBy('textract_job_id')
            ->orderBy('textract_job_id')
            ->limit(50)
            ->pluck('textract_job_id');

        foreach ($jobIds as $jobId) {
            $indices = DB::table('textract_documents')
                ->where('textract_job_id', $jobId)
                ->orderBy('chunk_index')
                ->pluck('chunk_index')
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();

            if (count($indices) === 0) {
                continue;
            }

            $this->assertSame(0, $indices[0], "First chunk_index must be 0 for textract_job_id={$jobId}");

            for ($i = 1; $i < count($indices); $i++) {
                $this->assertSame(
                    $indices[$i - 1] + 1,
                    $indices[$i],
                    "Non-contiguous chunk_index for textract_job_id={$jobId} at position {$i}"
                );
            }
        }
    }

    /**
     * Invariant: AgentVectorMemory rows must have stable dedupe hash and non-negative access_count.
     * Also: if embedding fields exist, there must be an embedding vector stored.
     *
     * Why: Federated memory search relies on content_hash uniqueness and on embeddings existing.
     */
    public function test_agent_vector_memories_hash_and_embedding_consistency(): void
    {
        $table = config('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');
        $this->requireTable($table);

        $embedCol = $this->embeddingColumnFor($table);
        $this->assertNotNull($embedCol, "Expected embedding column on {$table}");

        $rows = DB::table($table)
            ->select(['id', 'agent_name', 'namespace', 'content_hash', 'access_count', 'embedding_provider', 'embedding_model', 'embedding_dimensions', $embedCol])
            ->limit(500)
            ->get();

        foreach ($rows as $r) {
            $this->assertNotEmpty($r->agent_name, "agent_name must be non-empty for {$table}.id={$r->id}");
            $this->assertNotEmpty($r->namespace, "namespace must be non-empty for {$table}.id={$r->id}");

            $this->assertSha256Like($r->content_hash, "{$table}.id={$r->id}");

            $this->assertGreaterThanOrEqual(0, (int) ($r->access_count ?? 0), "access_count must be >= 0 for {$table}.id={$r->id}");

            // If embedding metadata exists, embedding itself must exist.
            if (! empty($r->embedding_provider) || ! empty($r->embedding_model) || ! empty($r->embedding_dimensions)) {
                $vec = $this->decodeVector($r->{$embedCol});
                $this->assertNotEmpty($vec, "Embedding vector must not be empty for {$table}.id={$r->id}");
            }
        }
    }

    /**
     * Invariant: neo4j_retry_queue has coherent status/error fields.
     *
     * Why: Your whole Neo4j reliability story depends on this queue being trustworthy.
     */
    public function test_neo4j_retry_queue_status_error_invariants(): void
    {
        $this->requireTable('neo4j_retry_queue');

        $allowed = ['pending', 'retrying', 'completed', 'failed', 'dead_letter'];

        $rows = DB::table('neo4j_retry_queue')
            ->select(['id', 'status', 'attempts', 'max_attempts', 'last_error', 'failed_at', 'operation_type'])
            ->limit(500)
            ->get();

        foreach ($rows as $r) {
            $this->assertTrue(in_array($r->status, $allowed, true), "Invalid neo4j_retry_queue.status={$r->status} for id={$r->id}");
            $this->assertGreaterThanOrEqual(0, (int) $r->attempts, "attempts must be >=0 for neo4j_retry_queue.id={$r->id}");
            $this->assertGreaterThan(0, (int) $r->max_attempts, "max_attempts must be >0 for neo4j_retry_queue.id={$r->id}");
            $this->assertLessThanOrEqual((int) $r->max_attempts, (int) $r->attempts, "attempts should not exceed max_attempts for neo4j_retry_queue.id={$r->id}");

            if ($r->status === 'completed') {
                $this->assertTrue($r->last_error === null || $r->last_error === '', "completed items must not have last_error for neo4j_retry_queue.id={$r->id}");
                $this->assertNull($r->failed_at, "completed items must not have failed_at for neo4j_retry_queue.id={$r->id}");
            }

            if ($r->status === 'failed' || $r->status === 'dead_letter') {
                $this->assertNotEmpty($r->last_error, "{$r->status} items must have last_error for neo4j_retry_queue.id={$r->id}");
                $this->assertNotNull($r->failed_at, "{$r->status} items must have failed_at for neo4j_retry_queue.id={$r->id}");
            }
        }
    }

    /**
     * Invariant: ai_reasoning_traces confidence must be in [0, 1] when present;
     * parent_trace_id must reference an existing trace_id (or be null).
     *
     * Why: explainability/audit layer must not silently degrade into broken trace trees.
     */
    public function test_ai_reasoning_traces_parent_and_confidence_invariants(): void
    {
        $this->requireTable('ai_reasoning_traces');

        $rows = DB::table('ai_reasoning_traces')
            ->select(['trace_id', 'parent_trace_id', 'confidence'])
            ->limit(1000)
            ->get();

        // Build a set of trace IDs for quick membership checks.
        $traceIds = DB::table('ai_reasoning_traces')->pluck('trace_id')->flip();

        foreach ($rows as $r) {
            $this->assertNotEmpty($r->trace_id, 'trace_id must not be empty');

            if (! empty($r->parent_trace_id)) {
                $this->assertTrue(isset($traceIds[$r->parent_trace_id]), "parent_trace_id {$r->parent_trace_id} must exist");
            }

            if ($r->confidence !== null) {
                $conf = (float) $r->confidence;
                $this->assertGreaterThanOrEqual(0.0, $conf, "confidence must be >=0 for trace_id={$r->trace_id}");
                $this->assertLessThanOrEqual(1.0, $conf, "confidence must be <=1 for trace_id={$r->trace_id}");
            }
        }
    }

    /**
     * Invariant: agent_trace_spans state machine must be coherent.
     *
     * Why: tracing is critical for debugging agentic failures; broken spans destroy post-mortems.
     */
    public function test_agent_trace_spans_state_machine_invariants(): void
    {
        $table = config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans');
        $this->requireTable($table);

        $allowed = ['running', 'success', 'error'];

        $rows = DB::table($table)
            ->select(['span_id', 'status', 'start_time', 'end_time', 'duration_ms', 'error_message'])
            ->limit(1000)
            ->get();

        foreach ($rows as $r) {
            $this->assertTrue(in_array($r->status, $allowed, true), "Invalid agent_trace_spans.status={$r->status} for span_id={$r->span_id}");

            if ($r->status === 'running') {
                $this->assertNull($r->end_time, "running spans must not have end_time for span_id={$r->span_id}");
            }

            if ($r->status === 'success') {
                $this->assertNotNull($r->end_time, "success spans must have end_time for span_id={$r->span_id}");
                $this->assertNotNull($r->duration_ms, "success spans must have duration_ms for span_id={$r->span_id}");
                $this->assertGreaterThanOrEqual(0, (int) $r->duration_ms, "duration_ms must be >=0 for span_id={$r->span_id}");
                $this->assertTrue($r->error_message === null || $r->error_message === '', "success spans must not have error_message for span_id={$r->span_id}");
            }

            if ($r->status === 'error') {
                $this->assertNotEmpty($r->error_message, "error spans must have error_message for span_id={$r->span_id}");
            }
        }
    }

    /**
     * Invariant: court_decision_documents must have:
     * - valid decision_id (FK)
     * - non-empty content
     * - valid 64-char content_hash
     * - embedding vector present in configured column
     *
     * Why: decision retrieval quality depends on these rows being consistent.
     */
    public function test_court_decision_documents_embedding_and_hash_invariants(): void
    {
        $table = config('vizra-adk.tables.court_decision_documents', 'court_decision_documents');
        $this->requireTable($table);

        $embedCol = $this->embeddingColumnFor($table);
        $this->assertNotNull($embedCol, "Expected embedding column on {$table}");

        $rows = DB::table($table)
            ->select(['id', 'decision_id', 'doc_id', 'content', 'content_hash', 'embedding_provider', 'embedding_model', 'embedding_dimensions', $embedCol])
            ->limit(500)
            ->get();

        foreach ($rows as $r) {
            $this->assertNotEmpty($r->decision_id, "decision_id must not be empty for {$table}.id={$r->id}");
            $this->assertNotEmpty($r->doc_id, "doc_id must not be empty for {$table}.id={$r->id}");
            $this->assertNotEmpty(trim((string) $r->content), "content must not be empty for {$table}.id={$r->id}");

            $this->assertSha256Like($r->content_hash, "{$table}.id={$r->id}");

            // embedding metadata is required by schema; ensure non-empty strings.
            $this->assertNotEmpty($r->embedding_provider, "embedding_provider must not be empty for {$table}.id={$r->id}");
            $this->assertNotEmpty($r->embedding_model, "embedding_model must not be empty for {$table}.id={$r->id}");
            $this->assertGreaterThan(0, (int) $r->embedding_dimensions, "embedding_dimensions must be >0 for {$table}.id={$r->id}");

            $vec = $this->decodeVector($r->{$embedCol});
            $this->assertNotEmpty($vec, "Embedding vector must not be empty for {$table}.id={$r->id}");
        }
    }

    /**
     * Invariant: upload tables sha256 must be valid if present; and stored items must have local_path.
     *
     * Why: download/re-ingest paths depend on consistent storage metadata.
     */
    public function test_upload_tables_have_consistent_file_metadata(): void
    {
        $uploads = [
            'law_uploads' => config('vizra-adk.tables.law_uploads', 'law_uploads'),
            'cases_documents_uploads' => config('vizra-adk.tables.cases_documents_uploads', 'cases_documents_uploads'),
            'court_decision_document_uploads' => config('vizra-adk.tables.court_decision_document_uploads', 'court_decision_document_uploads'),
        ];

        foreach ($uploads as $label => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->select(['id', 'doc_id', 'local_path', 'sha256', 'status'])
                ->limit(500)
                ->get();

            foreach ($rows as $r) {
                $this->assertNotEmpty($r->doc_id, "doc_id must not be empty for {$table}.id={$r->id}");

                if (! empty($r->sha256)) {
                    $this->assertSha256Like($r->sha256, "{$table}.id={$r->id}");
                }

                // local_path is required on law_uploads and court_decision_document_uploads; optional on cases_documents_uploads.
                if (in_array($label, ['law_uploads', 'court_decision_document_uploads'], true) || ($r->status === 'stored')) {
                    $this->assertNotEmpty($r->local_path, "local_path must be present for {$table}.id={$r->id}");
                }
            }
        }
    }
}
