<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * AdditionalDataIntegrityInvariantsTest
 *
 * Purpose:
 *  - Encode cross-cutting, domain-critical invariants as executable tests.
 *  - These are "tripwires" for silent data corruption and half-written pipeline states.
 *
 * Design principles:
 *  - Prefer DB-level invariants that catch whole classes of bugs.
 *  - Be schema-adaptive (pgvector vs JSON columns, configurable table names).
 *  - Keep queries bounded/fast (sample verification for expensive checks like hashes).
 */
class AdditionalDataIntegrityInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    private function table(string $configKey, string $default): string
    {
        return (string) config($configKey, $default);
    }

    private function hasCol(string $table, string $col): bool
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumn($table, $col);
        } catch (\Throwable) {
            return false;
        }
    }

    private function assertNoDuplicateGroups(string $table, array $groupBy, array $whereNotNull = []): void
    {
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Table not present: {$table}");
        }

        $q = DB::table($table)
            ->select(array_merge($groupBy, [DB::raw('COUNT(*) as c')]));

        foreach ($whereNotNull as $col) {
            if ($this->hasCol($table, $col)) {
                $q->whereNotNull($col);
            }
        }

        $duplicates = $q
            ->groupBy($groupBy)
            ->having('c', '>', 1)
            ->limit(1)
            ->get();

        $this->assertCount(0, $duplicates, "Found duplicate groups in {$table} for keys: ".implode(', ', $groupBy));
    }

    /**
     * Invariant: TextractJob status values must be known and internally consistent.
     *
     * Reasoning:
     * - textract_jobs is the backbone of your OCR pipeline; invalid states lead to stuck jobs,
     *   repeated processing, and missing downstream artifacts.
     *
     * Based on the base migration: status defaults and semantics are known (queued|uploading|started|succeeded|failed).
     */
    public function test_textract_jobs_status_and_sync_fields_are_consistent(): void
    {
        if (! Schema::hasTable('textract_jobs')) {
            $this->markTestSkipped('textract_jobs table not present');
        }

        // status must be one of allowed values
        $invalidStatus = DB::table('textract_jobs')
            ->whereNotIn('status', ['queued', 'uploading', 'started', 'succeeded', 'failed', 'analyzing', 'processing'])
            ->count();
        $this->assertSame(0, $invalidStatus, 'textract_jobs contains unexpected status values');

        // failed must have error
        if ($this->hasCol('textract_jobs', 'error')) {
            $failedMissingError = DB::table('textract_jobs')
                ->where('status', 'failed')
                ->where(function ($q) {
                    $q->whereNull('error')->orWhere('error', '');
                })
                ->count();
            $this->assertSame(0, $failedMissingError, 'textract_jobs status=failed must have non-empty error');
        }

        // succeeded must have effective content (manual if edited, else extracted)
        if ($this->hasCol('textract_jobs', 'manually_edited') && $this->hasCol('textract_jobs', 'manual_content') && $this->hasCol('textract_jobs', 'extracted_content')) {
            $succeededMissingContent = DB::table('textract_jobs')
                ->where('status', 'succeeded')
                ->where(function ($q) {
                    $q
                        // manually edited => manual_content required
                        ->where(function ($qq) {
                            $qq->where('manually_edited', true)
                                ->where(function ($qqq) {
                                    $qqq->whereNull('manual_content')->orWhere('manual_content', '');
                                });
                        })
                        // not manually edited => extracted_content required
                        ->orWhere(function ($qq) {
                            $qq->where('manually_edited', false)
                                ->where(function ($qqq) {
                                    $qqq->whereNull('extracted_content')->orWhere('extracted_content', '');
                                });
                        });
                })
                ->count();

            $this->assertSame(0, $succeededMissingContent, 'textract_jobs status=succeeded must have effective content');
        }

        // manually_edited implies content_edited_at is set (edited_by may be nulled by FK on user delete)
        if ($this->hasCol('textract_jobs', 'manually_edited') && $this->hasCol('textract_jobs', 'content_edited_at')) {
            $editedMissingTimestamp = DB::table('textract_jobs')
                ->where('manually_edited', true)
                ->whereNull('content_edited_at')
                ->count();
            $this->assertSame(0, $editedMissingTimestamp, 'textract_jobs manually_edited=true must have content_edited_at');
        }

        // embedding_status / graph_sync_status consistency
        if ($this->hasCol('textract_jobs', 'embedding_status')) {
            $invalidEmbeddingStatus = DB::table('textract_jobs')
                ->whereNotIn('embedding_status', ['pending', 'processing', 'synced', 'failed'])
                ->count();
            $this->assertSame(0, $invalidEmbeddingStatus, 'textract_jobs contains unexpected embedding_status values');

            if ($this->hasCol('textract_jobs', 'embedding_synced_at')) {
                $syncedWithoutTime = DB::table('textract_jobs')
                    ->where('embedding_status', 'synced')
                    ->whereNull('embedding_synced_at')
                    ->count();
                $this->assertSame(0, $syncedWithoutTime, 'embedding_status=synced must have embedding_synced_at');
            }
        }

        if ($this->hasCol('textract_jobs', 'graph_sync_status')) {
            $invalidGraphStatus = DB::table('textract_jobs')
                ->whereNotIn('graph_sync_status', ['pending', 'processing', 'synced', 'failed'])
                ->count();
            $this->assertSame(0, $invalidGraphStatus, 'textract_jobs contains unexpected graph_sync_status values');

            if ($this->hasCol('textract_jobs', 'graph_synced_at')) {
                $syncedWithoutTime = DB::table('textract_jobs')
                    ->where('graph_sync_status', 'synced')
                    ->whereNull('graph_synced_at')
                    ->count();
                $this->assertSame(0, $syncedWithoutTime, 'graph_sync_status=synced must have graph_synced_at');
            }

            // If graph is processing or synced, embeddings should already be synced.
            if ($this->hasCol('textract_jobs', 'embedding_status')) {
                $graphWithoutEmbeddings = DB::table('textract_jobs')
                    ->whereIn('graph_sync_status', ['processing', 'synced'])
                    ->where('embedding_status', '!=', 'synced')
                    ->count();
                $this->assertSame(0, $graphWithoutEmbeddings, 'Graph sync should not advance unless embedding_status=synced');
            }
        }
    }

    /**
     * Invariant: TextractDocument processing_status must be valid and consistent.
     *
     * Reasoning:
     * - textract_documents is used for semantic search; half-written rows break retrieval and graph sync.
     *
     * Based on migration: processing_status is pending|processing|completed|failed.
     */
    public function test_textract_documents_status_embedding_and_error_consistency(): void
    {
        if (! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('textract_documents table not present');
        }

        $invalidStatus = DB::table('textract_documents')
            ->whereNotIn('processing_status', ['pending', 'processing', 'completed', 'failed'])
            ->count();
        $this->assertSame(0, $invalidStatus, 'textract_documents has invalid processing_status values');

        // Failed must have processing_error
        if ($this->hasCol('textract_documents', 'processing_error')) {
            $failedMissingError = DB::table('textract_documents')
                ->where('processing_status', 'failed')
                ->where(function ($q) {
                    $q->whereNull('processing_error')->orWhere('processing_error', '');
                })
                ->count();
            $this->assertSame(0, $failedMissingError, 'textract_documents processing_status=failed must have processing_error');
        }

        // Completed must have embedded_at + embedding fields
        if ($this->hasCol('textract_documents', 'embedded_at')) {
            $completedMissingEmbeddedAt = DB::table('textract_documents')
                ->where('processing_status', 'completed')
                ->whereNull('embedded_at')
                ->count();
            $this->assertSame(0, $completedMissingEmbeddedAt, 'textract_documents completed must have embedded_at');
        }

        if ($this->hasCol('textract_documents', 'embedding')) {
            $completedMissingEmbedding = DB::table('textract_documents')
                ->where('processing_status', 'completed')
                ->whereNull('embedding')
                ->count();
            $this->assertSame(0, $completedMissingEmbedding, 'textract_documents completed must have embedding');
        }

        if ($this->hasCol('textract_documents', 'embedding_dimensions')) {
            $completedMissingDims = DB::table('textract_documents')
                ->where('processing_status', 'completed')
                ->whereNull('embedding_dimensions')
                ->count();
            $this->assertSame(0, $completedMissingDims, 'textract_documents completed must have embedding_dimensions');
        }

        // No duplicate (textract_job_id, chunk_index)
        $this->assertNoDuplicateGroups('textract_documents', ['textract_job_id', 'chunk_index']);

        // Referential: textract_documents.textract_job_id must exist in textract_jobs
        $orphans = DB::table('textract_documents as td')
            ->leftJoin('textract_jobs as tj', 'td.textract_job_id', '=', 'tj.id')
            ->whereNull('tj.id')
            ->count();
        $this->assertSame(0, $orphans, 'textract_documents must not reference missing textract_jobs');

        // If cases table exists, enforce case_id referential integrity
        $casesTable = $this->table('vizra-adk.tables.cases', 'cases');
        if (Schema::hasTable($casesTable) && $this->hasCol('textract_documents', 'case_id')) {
            $caseOrphans = DB::table('textract_documents as td')
                ->leftJoin($casesTable.' as c', 'td.case_id', '=', 'c.id')
                ->whereNull('c.id')
                ->count();
            $this->assertSame(0, $caseOrphans, 'textract_documents.case_id must reference an existing case');
        }
    }

    /**
     * Invariant: Upload records must not be "stored" without physical identifiers.
     *
     * Reasoning:
     * - Legal evidence uploads must always have stable identity for dedupe/audit (sha256) and a path.
     */
    public function test_case_document_uploads_have_required_fields_for_stored_and_failed_states(): void
    {
        $uploadsTable = $this->table('vizra-adk.tables.cases_documents_uploads', 'cases_documents_uploads');
        if (! Schema::hasTable($uploadsTable)) {
            $this->markTestSkipped("Table not present: {$uploadsTable}");
        }

        // stored => local_path + sha256 + file_size + doc_id
        $badStored = DB::table($uploadsTable)
            ->where('status', 'stored')
            ->where(function ($q) {
                $q->whereNull('local_path')->orWhere('local_path', '')
                  ->orWhereNull('sha256')->orWhere('sha256', '')
                  ->orWhereNull('doc_id')->orWhere('doc_id', '')
                  ->orWhereNull('file_size')->orWhere('file_size', '<=', 0);
            })
            ->count();
        $this->assertSame(0, $badStored, 'cases_documents_uploads status=stored must have local_path, sha256, doc_id, file_size');

        // sha256 must look like sha-256 hex
        $badSha = DB::table($uploadsTable)
            ->where('status', 'stored')
            ->whereNotNull('sha256')
            ->whereRaw('LENGTH(sha256) != 64')
            ->count();
        $this->assertSame(0, $badSha, 'cases_documents_uploads stored sha256 must be 64 chars');

        // failed => error
        if ($this->hasCol($uploadsTable, 'error')) {
            $failedMissingError = DB::table($uploadsTable)
                ->where('status', 'failed')
                ->where(function ($q) {
                    $q->whereNull('error')->orWhere('error', '');
                })
                ->count();
            $this->assertSame(0, $failedMissingError, 'cases_documents_uploads status=failed must have error');
        }

        // If case_id is set, it must reference an existing case.
        $casesTable = $this->table('vizra-adk.tables.cases', 'cases');
        if (Schema::hasTable($casesTable) && $this->hasCol($uploadsTable, 'case_id')) {
            $caseOrphans = DB::table($uploadsTable.' as u')
                ->leftJoin($casesTable.' as c', 'u.case_id', '=', 'c.id')
                ->whereNotNull('u.case_id')
                ->whereNull('c.id')
                ->count();
            $this->assertSame(0, $caseOrphans, 'cases_documents_uploads.case_id must reference an existing case when set');
        }
    }

    /**
     * Invariant: Law uploads must be uniquely identified by (doc_id, sha256).
     *
     * Reasoning:
     * - Prevent duplicate law PDFs causing duplicate/contradictory ingestions.
     * - There is a DB-level unique index; this test catches legacy data issues / disabled constraints.
     */
    public function test_law_uploads_have_no_duplicate_doc_id_sha256_pairs_and_valid_paths(): void
    {
        $lawUploads = $this->table('vizra-adk.tables.law_uploads', 'law_uploads');
        if (! Schema::hasTable($lawUploads)) {
            $this->markTestSkipped("Table not present: {$lawUploads}");
        }

        // local_path is NOT NULL per schema; ensure non-empty
        $emptyPath = DB::table($lawUploads)->whereNull('local_path')->orWhere('local_path', '')->count();
        $this->assertSame(0, $emptyPath, 'law_uploads.local_path must be non-empty');

        // stored => sha256 present
        $badStored = DB::table($lawUploads)
            ->where('status', 'stored')
            ->where(function ($q) {
                $q->whereNull('sha256')->orWhere('sha256', '');
            })
            ->count();
        $this->assertSame(0, $badStored, 'law_uploads status=stored must have sha256');

        // No duplicate doc_id+sha256 among non-null sha256
        $this->assertNoDuplicateGroups($lawUploads, ['doc_id', 'sha256'], ['sha256']);

        // If ingested_law_id exists, it must reference an ingested law
        $ingested = $this->table('vizra-adk.tables.ingested_laws', 'ingested_laws');
        if (Schema::hasTable($ingested) && $this->hasCol($lawUploads, 'ingested_law_id')) {
            $orphans = DB::table($lawUploads.' as u')
                ->leftJoin($ingested.' as i', 'u.ingested_law_id', '=', 'i.id')
                ->whereNotNull('u.ingested_law_id')
                ->whereNull('i.id')
                ->count();
            $this->assertSame(0, $orphans, 'law_uploads.ingested_law_id must reference an existing ingested_laws row when set');
        }
    }

    /**
     * Invariant: content_hash columns must match SHA-256(content) for dedupe correctness.
     *
     * Reasoning:
     * - Your ingestion layers rely on content_hash for idempotency and deduplication.
     * - If content_hash drifts from content, you get silent duplication or wrong updates.
     */
    public function test_content_hash_matches_content_sha256_for_key_tables(): void
    {
        $checks = [
            // cases_documents: content nullable, content_hash nullable
            [
                'table' => $this->table('vizra-adk.tables.cases_documents', 'cases_documents'),
                'id_col' => 'id',
                'content_col' => 'content',
                'hash_col' => 'content_hash',
                'where' => function ($q) {
                    $q->whereNotNull('content')->whereNotNull('content_hash');
                },
            ],
            // laws
            [
                'table' => $this->table('vizra-adk.tables.laws', 'laws'),
                'id_col' => 'id',
                'content_col' => 'content',
                'hash_col' => 'content_hash',
                'where' => function ($q) {
                    $q->whereNotNull('content')->whereNotNull('content_hash');
                },
            ],
            // court_decision_documents: content + content_hash required by schema
            [
                'table' => $this->table('vizra-adk.tables.court_decision_documents', 'court_decision_documents'),
                'id_col' => 'id',
                'content_col' => 'content',
                'hash_col' => 'content_hash',
                'where' => function ($q) {
                    $q->whereNotNull('content')->whereNotNull('content_hash');
                },
            ],
            // agent_vector_memories: content_hash required
            [
                'table' => $this->table('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories'),
                'id_col' => 'id',
                'content_col' => 'content',
                'hash_col' => 'content_hash',
                'where' => function ($q) {
                    $q->whereNotNull('content')->whereNotNull('content_hash');
                },
            ],
        ];

        foreach ($checks as $spec) {
            $table = $spec['table'];
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! ($this->hasCol($table, $spec['id_col']) && $this->hasCol($table, $spec['content_col']) && $this->hasCol($table, $spec['hash_col']))) {
                continue;
            }

            $q = DB::table($table)->select([$spec['id_col'], $spec['content_col'], $spec['hash_col']]);
            ($spec['where'])($q);

            // Bound the check: sample up to 200 rows
            $rows = $q->orderBy($spec['id_col'])->limit(200)->get();

            foreach ($rows as $row) {
                $content = (string) ($row->{$spec['content_col']} ?? '');
                $expected = hash('sha256', $content);
                $actual = (string) ($row->{$spec['hash_col']} ?? '');

                $this->assertSame(
                    $expected,
                    $actual,
                    "{$table} row {$row->{$spec['id_col']}} has content_hash mismatch"
                );
            }
        }

        // If we didn't assert anything because DB is empty, the test still passes (valid state).
        $this->assertTrue(true);
    }

    /**
     * Invariant: No duplicate chunk indexes within document groups for search tables.
     *
     * Reasoning:
     * - Duplicate chunk indexes break deterministic reconstruction and confuse UI pagination.
     */
    public function test_no_duplicate_chunk_index_per_doc_group_in_vector_tables(): void
    {
        $casesDocs = $this->table('vizra-adk.tables.cases_documents', 'cases_documents');
        if (Schema::hasTable($casesDocs)) {
            $this->assertNoDuplicateGroups($casesDocs, ['case_id', 'doc_id', 'chunk_index']);
        }

        $decisionDocs = $this->table('vizra-adk.tables.court_decision_documents', 'court_decision_documents');
        if (Schema::hasTable($decisionDocs)) {
            $this->assertNoDuplicateGroups($decisionDocs, ['decision_id', 'doc_id', 'chunk_index']);
        }
    }

    /**
     * Invariant: Embedding metadata should not be half-written.
     *
     * Reasoning:
     * - Your vector search assumes embedding vectors correspond to stated provider/model/dimensions.
     * - Half-written metadata is a classic source of "works sometimes" failures.
     */
    public function test_embedding_metadata_is_consistent_when_embedding_present(): void
    {
        // cases_documents: embedding_* fields nullable by schema, but CaseDocument model defaults them.
        $casesDocs = $this->table('vizra-adk.tables.cases_documents', 'cases_documents');
        if (Schema::hasTable($casesDocs)) {
            $hasEmbedding = $this->hasCol($casesDocs, 'embedding') ? 'embedding' : null;
            $hasEmbeddingVector = $this->hasCol($casesDocs, 'embedding_vector') ? 'embedding_vector' : null;

            if ($hasEmbedding || $hasEmbeddingVector) {
                $q = DB::table($casesDocs);
                if ($hasEmbedding) {
                    $q->orWhereNotNull('embedding');
                }
                if ($hasEmbeddingVector) {
                    $q->orWhereNotNull('embedding_vector');
                }

                // If any embeddings exist, they must have provider/model/dimensions.
                $badMeta = $q->where(function ($qq) {
                        $qq->whereNull('embedding_provider')->orWhere('embedding_provider', '')
                           ->orWhereNull('embedding_model')->orWhere('embedding_model', '')
                           ->orWhereNull('embedding_dimensions')->orWhere('embedding_dimensions', '<=', 0);
                    })
                    ->count();

                $this->assertSame(0, $badMeta, 'cases_documents rows with embeddings must have embedding_provider/model/dimensions');

                // If provider is openai and model is text-embedding-3-small, dims should be 1536.
                $badDims = DB::table($casesDocs)
                    ->where('embedding_provider', 'openai')
                    ->where('embedding_model', 'text-embedding-3-small')
                    ->whereNotNull('embedding_dimensions')
                    ->where('embedding_dimensions', '!=', 1536)
                    ->count();

                $this->assertSame(0, $badDims, 'cases_documents openai text-embedding-3-small must have embedding_dimensions=1536');
            }
        }

        // court_decision_documents: schema has NOT NULL embedding metadata in the migration.
        $decisionDocs = $this->table('vizra-adk.tables.court_decision_documents', 'court_decision_documents');
        if (Schema::hasTable($decisionDocs)) {
            $badMeta = DB::table($decisionDocs)
                ->where(function ($q) {
                    $q->whereNull('embedding_provider')->orWhere('embedding_provider', '')
                      ->orWhereNull('embedding_model')->orWhere('embedding_model', '')
                      ->orWhereNull('embedding_dimensions')->orWhere('embedding_dimensions', '<=', 0);
                })
                ->count();

            $this->assertSame(0, $badMeta, 'court_decision_documents must have embedding metadata');
        }

        // agent_vector_memories: embedding dimensions defaults to 1536.
        $memTable = $this->table('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');
        if (Schema::hasTable($memTable)) {
            $badDims = DB::table($memTable)
                ->whereNotNull('embedding_dimensions')
                ->where('embedding_dimensions', '<=', 0)
                ->count();
            $this->assertSame(0, $badDims, 'agent_vector_memories embedding_dimensions must be positive');

            if ($this->hasCol($memTable, 'access_count')) {
                $negAccess = DB::table($memTable)->where('access_count', '<', 0)->count();
                $this->assertSame(0, $negAccess, 'agent_vector_memories access_count must never be negative');
            }

            // Unique constraint is expected: (agent_name, content_hash)
            $this->assertNoDuplicateGroups($memTable, ['agent_name', 'content_hash']);
        }
    }

    /**
     * Invariant: Laws temporal fields must define a coherent time interval.
     *
     * Reasoning:
     * - Temporal legal reasoning depends on valid_from/valid_until ordering.
     */
    public function test_law_temporal_fields_are_ordered_when_present(): void
    {
        $laws = $this->table('vizra-adk.tables.laws', 'laws');
        if (! Schema::hasTable($laws)) {
            $this->markTestSkipped('laws table not present');
        }

        if ($this->hasCol($laws, 'valid_from') && $this->hasCol($laws, 'valid_until')) {
            $bad = DB::table($laws)
                ->whereNotNull('valid_from')
                ->whereNotNull('valid_until')
                ->whereRaw('valid_from > valid_until')
                ->count();

            $this->assertSame(0, $bad, 'laws.valid_from must be <= valid_until when both are set');
        }

        // doc_id should be present for ingested chunks
        if ($this->hasCol($laws, 'doc_id')) {
            $missingDocId = DB::table($laws)
                ->where(function ($q) {
                    $q->whereNull('doc_id')->orWhere('doc_id', '');
                })
                ->count();

            $this->assertSame(0, $missingDocId, 'laws.doc_id must be non-empty');
        }
    }

    /**
     * Invariant: IngestedLaw must have unique doc_id and stable provenance.
     *
     * Reasoning:
     * - Everything downstream (laws chunks, uploads, MCP mapping) relies on stable doc_id.
     */
    public function test_ingested_laws_doc_id_is_unique_and_not_empty(): void
    {
        $ingested = $this->table('vizra-adk.tables.ingested_laws', 'ingested_laws');
        if (! Schema::hasTable($ingested)) {
            $this->markTestSkipped("Table not present: {$ingested}");
        }

        // doc_id must not be empty
        $empty = DB::table($ingested)
            ->where(function ($q) {
                $q->whereNull('doc_id')->orWhere('doc_id', '');
            })
            ->count();
        $this->assertSame(0, $empty, 'ingested_laws.doc_id must be non-empty');

        // enforce uniqueness even if DB constraint is missing/disabled
        $this->assertNoDuplicateGroups($ingested, ['doc_id']);

        // If source_url exists, keep it non-empty when set
        if ($this->hasCol($ingested, 'source_url')) {
            $bad = DB::table($ingested)
                ->whereNotNull('source_url')
                ->where('source_url', '')
                ->count();
            $this->assertSame(0, $bad, 'ingested_laws.source_url must not be empty string when provided');
        }
    }
}
