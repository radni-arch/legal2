<?php

namespace App\Services\Odluke;

use App\Contracts\Ingest\OdlukeIngestServiceInterface;
use App\Exceptions\IngestException;
use App\Jobs\IngestOdlukeDecision;
use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\CourtDecisionDocumentUpload;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\Graph\ContradictionPipelineService;
use App\Services\IngestPipelineService;
use App\Services\MetadataBuilder;
use App\Services\OcrService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OdlukeIngestService implements OdlukeIngestServiceInterface
{
    public function __construct(
        protected OdlukeClient $client,
        protected IngestPipelineService $pipeline,
        protected CourtDecisionVectorStoreService $decisionVectors,
        protected ?OcrService $ocr = null,
        protected ?MetadataBuilder $meta = null,
        protected ?\App\Services\GraphDatabaseService $graphDb = null,
        protected ?ContradictionPipelineService $contradictionPipeline = null,
    ) {}

    /**
     * Filter out source IDs that have already been ingested.
     *
     * Checks the court_decision_documents table for existing source_id entries.
     * This prevents duplicate HTTP fetches, embedding API calls, and graph sync.
     *
     * @param  array  $sourceIds  Array of odluke source IDs to check
     * @return array  Array of source IDs that have NOT been ingested yet
     */
    public function filterAlreadyIngestedIds(array $sourceIds): array
    {
        if (empty($sourceIds)) {
            return [];
        }

        // Normalize and filter empty IDs
        $sourceIds = array_filter(array_map('trim', $sourceIds), fn ($id) => $id !== '');

        if (empty($sourceIds)) {
            return [];
        }

        // Query existing source_ids from court_decision_documents
        $table = (new CourtDecisionDocument)->getTable();
        $existingIds = DB::table($table)
            ->whereIn('source_id', $sourceIds)
            ->distinct()
            ->pluck('source_id')
            ->toArray();

        // Return only IDs that don't exist
        $newIds = array_diff($sourceIds, $existingIds);

        if (count($existingIds) > 0) {
            Log::info('Filtered already-ingested decision IDs', [
                'total_input' => count($sourceIds),
                'already_ingested' => count($existingIds),
                'to_process' => count($newIds),
            ]);
        }

        return array_values($newIds);
    }

    /**
     * Get source IDs that have already been ingested.
     *
     * @param  array  $sourceIds  Array of odluke source IDs to check
     * @return array  Array of source IDs that HAVE been ingested
     */
    public function getAlreadyIngestedIds(array $sourceIds): array
    {
        if (empty($sourceIds)) {
            return [];
        }

        $sourceIds = array_filter(array_map('trim', $sourceIds), fn ($id) => $id !== '');

        if (empty($sourceIds)) {
            return [];
        }

        $table = (new CourtDecisionDocument)->getTable();

        return DB::table($table)
            ->whereIn('source_id', $sourceIds)
            ->distinct()
            ->pluck('source_id')
            ->toArray();
    }

    /**
     * Ingest decisions by IDs with optional queue support and retry logic.
     *
     * Options:
     * - model: Embedding model (default: config)
     * - chunk_chars: Chunk size (default: 1500)
     * - overlap: Overlap size (default: 200)
     * - prefer: 'auto'|'html'|'pdf' (default: 'auto')
     * - dry: Dry run mode (default: false)
     * - sync_graph: Sync to Neo4j (default: env ODLUKE_SYNC_GRAPH)
     * - queue: Use queue with retry logic (default: false)
     * - queue_name: Queue name (default: 'default')
     * - max_attempts: Max retry attempts (default: 5)
     *
     * @param  array  $ids  Decision IDs to ingest
     * @param  array  $options  Ingestion options
     * @return array Result summary
     */
    public function ingestByIds(array $ids, array $options = []): array
    {
        $useQueue = (bool) ($options['queue'] ?? false);

        if ($useQueue) {
            return $this->ingestByIdsQueued($ids, $options);
        }

        return $this->ingestByIdsSync($ids, $options);
    }

    /**
     * Queue decisions for ingestion with retry logic
     */
    public function ingestByIdsQueued(array $ids, array $options = []): array
    {
        $queued = 0;
        $errors = 0;

        $queueName = $options['queue_name'] ?? 'default';
        $maxAttempts = (int) ($options['max_attempts'] ?? 5);

        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }

            try {
                // Dispatch job with retry logic
                IngestOdlukeDecision::dispatch($id, $options)
                    ->onQueue($queueName);

                $queued++;

                Log::info('Decision queued for ingestion', [
                    'decision_id' => $id,
                    'queue' => $queueName,
                    'max_attempts' => $maxAttempts,
                ]);
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Failed to queue decision for ingestion', [
                    'decision_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'ids_processed' => count($ids),
            'queued' => $queued,
            'errors' => $errors,
            'queue' => $queueName,
            'mode' => 'queued',
        ];
    }

    /**
     * Synchronous ingestion (original implementation)
     * Fetch HTML (preferred) or PDF, extract text, chunk and embed into court_decision_documents.
     */
    protected function ingestByIdsSync(array $ids, array $options = []): array
    {
        $model = $options['model'] ?? config('openai.models.embeddings');
        $chunkChars = (int) ($options['chunk_chars'] ?? 1500);
        $overlap = (int) ($options['overlap'] ?? 200);
        $prefer = $options['prefer'] ?? 'auto';
        $dry = (bool) ($options['dry'] ?? false);
        $syncGraph = (bool) ($options['sync_graph'] ?? config('odluke.sync_graph', env('ODLUKE_SYNC_GRAPH', true)));
        $force = (bool) ($options['force'] ?? false);

        $inserted = 0;
        $count = 0;
        $errors = 0;
        $skipped = 0;
        $wouldChunks = 0;
        $graphSynced = 0;
        $graphErrors = 0;
        $contradictionsChecked = 0;
        $contradictionsFound = 0;
        $alreadyIngested = 0;

        // Filter out already-ingested IDs unless force is enabled
        $originalCount = count($ids);
        if (! $force && ! $dry) {
            $alreadyIngestedIds = $this->getAlreadyIngestedIds($ids);
            $alreadyIngested = count($alreadyIngestedIds);

            if ($alreadyIngested > 0) {
                $ids = $this->filterAlreadyIngestedIds($ids);
                Log::info('Skipping already-ingested decisions', [
                    'original_count' => $originalCount,
                    'already_ingested' => $alreadyIngested,
                    'to_process' => count($ids),
                    'skipped_ids' => array_slice($alreadyIngestedIds, 0, 10), // Log first 10 for debugging
                ]);
            }
        }

        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $count++;
            try {
                $meta = $this->client->fetchDecisionMeta($id) ?? [];
                $decision = $this->upsertDecisionFromMeta($meta);

                $htmlRes = null;
                $pdfRes = null;
                $text = '';
                $uploadId = null;
                $docId = $meta['ecli'] ?? $id;

                if ($prefer !== 'pdf') {
                    $htmlRes = $this->client->downloadHtml($id);
                    if (($htmlRes['ok'] ?? false) && is_string($htmlRes['bytes'])) {
                        $text = $this->htmlToText($htmlRes['bytes']);
                        $this->persistSource($id, $htmlRes['bytes'], 'html', $decision);
                    } else {
                        // Log HTML download failure for visibility
                        Log::warning('Odluke HTML download failed', [
                            'id' => $id,
                            'status' => $htmlRes['status'] ?? null,
                            'url' => $htmlRes['url'] ?? $this->client->downloadHtmlUrl($id),
                        ]);
                    }
                }
                if ($text === '' && $prefer !== 'html') {
                    $pdfRes = $this->client->downloadPdf($id);
                    if (($pdfRes['ok'] ?? false) && is_string($pdfRes['bytes'])) {
                        $rel['abs_path'] = $this->persistSource($id, $pdfRes['bytes'], 'pdf', $decision);
                        if (! $dry) {
                            $this->ocr = new OcrService();
                            $rel = $this->storePdfUpload($decision, $id, $pdfRes['bytes']);
                            $uploadId = $rel['upload_id'] ?? null;
                        }
                        if ($this->ocr) {
                            $ex = $this->ocr->extractTextFromPdf($rel['abs_path'] ?? $this->tempFile($pdfRes['bytes'], '.pdf'));
                            if (is_string($ex)) {
                                $text = $ex;
                            }
                        }
                    } else {
                        // Log PDF download failure for visibility
                        Log::warning('Odluke PDF download failed', [
                            'id' => $id,
                            'status' => $pdfRes['status'] ?? null,
                            'url' => $pdfRes['url'] ?? $this->client->downloadPdfUrl($id),
                        ]);
                    }
                }

                $text = trim(preg_replace('/\s+/u', ' ', $text ?? ''));
                if ($text === '') {
                    $skipped++;
                    // Explicitly log when no text could be extracted from either source
                    Log::warning('Odluke ingestion skipped due to empty text after downloads', [
                        'id' => $id,
                        'prefer' => $prefer,
                        'html_status' => $htmlRes['status'] ?? null,
                        'pdf_status' => $pdfRes['status'] ?? null,
                    ]);

                    continue;
                }

                // Delegate chunking + embedding to shared method
                $res = $this->chunkAndEmbed($text, $meta, [
                    'decision_id' => (string) $decision->id,
                    'doc_id' => $docId,
                    'source' => $meta['src'] ?? $this->client->downloadHtmlUrl($id),
                    'source_id' => $id,
                    'upload_id' => $uploadId,
                    'model' => $model,
                    'chunk_chars' => $chunkChars,
                    'overlap' => $overlap,
                    'dry' => $dry,
                ]);

                $wouldChunks += (int) ($res['would_chunks'] ?? 0);
                $currentInserted = (int) ($res['inserted'] ?? 0);
                $inserted += $currentInserted;

                // Sync to graph database if requested (after successful vector ingestion)
                if ($syncGraph && ! $dry && $this->graphDb && $currentInserted > 0) {
                    try {
                        $this->graphDb->storeDecisionInGraph($meta, $docId);
                        $graphSynced++;
                    } catch (\Throwable $graphError) {
                        // Log but don't block vector ingestion
                        $graphErrors++;
                        Log::warning('Graph sync failed for decision (vector ingestion succeeded)', [
                            'id' => $id,
                            'doc_id' => $docId,
                            'ecli' => $meta['ecli'] ?? null,
                            'error' => $graphError->getMessage(),
                        ]);
                    }
                }

                // Check for contradictions (after successful vector ingestion)
                if (! $dry && $this->contradictionPipeline && $currentInserted > 0) {
                    try {
                        $contradictionsChecked++;
                        $contradictionResult = $this->contradictionPipeline->checkNewDecision(
                            (string) $decision->id,
                            $options
                        );

                        if (($contradictionResult['contradictions_found'] ?? 0) > 0) {
                            $contradictionsFound += $contradictionResult['contradictions_found'];
                        }
                    } catch (\Throwable $contradictionError) {
                        // Log but don't block ingestion
                        Log::warning('Contradiction check failed for decision (ingestion succeeded)', [
                            'id' => $id,
                            'decision_id' => $decision->id,
                            'error' => $contradictionError->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('Odluke ingest failed', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }

        return [
            'ids_processed' => $count,
            'ids_total' => $originalCount,
            'already_ingested' => $alreadyIngested,
            'inserted' => $inserted,
            'would_chunks' => $wouldChunks,
            'errors' => $errors,
            'skipped' => $skipped,
            'graph_synced' => $graphSynced,
            'graph_errors' => $graphErrors,
            'contradictions_checked' => $contradictionsChecked,
            'contradictions_found' => $contradictionsFound,
            'model' => $model,
            'dry' => $dry,
        ];
    }

    /** Persist original source for traceability under storage/app/court_decisions/{decision}/sources/{id}.{ext} */
    protected function persistSource(string $id, string $bytes, string $ext, CourtDecision $decision): string
    {
        $dir = $this->decisionBaseDir($decision).'/sources';
        $path = $dir.'/'.$id.'.'.$ext;
        Storage::put($path, $bytes);
        return $path;
    }

    protected function storePdfUpload(CourtDecision $decision, string $id, string $bytes): array
    {
        $dir = $this->decisionBaseDir($decision).'/pdfs';
        $fileName = 'decision-'.$id.'.pdf';
        $rel = $dir.'/'.$fileName;
        Storage::put($rel, $bytes);
        $abs = Storage::path($rel);

        $upload = CourtDecisionDocumentUpload::create([
            'id' => (string) Str::ulid(),
            'decision_id' => (string) $decision->id,
            'doc_id' => $id,
            'disk' => 'local',
            'local_path' => $rel,
            'original_filename' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size' => @filesize($abs) ?: null,
            'sha256' => @hash_file('sha256', $abs) ?: null,
            'source_url' => $this->client->downloadPdfUrl($id) ?? null,
            'uploaded_at' => now(),
            'status' => 'stored',
        ]);

        return ['upload_id' => (string) $upload->id, 'rel_path' => $rel, 'abs_path' => $abs];
    }

    protected function decisionBaseDir(CourtDecision $decision): string
    {
        $court = Str::slug((string) ($decision->court ?? 'court'));
        $num = Str::slug((string) ($decision->case_number ?? (string) $decision->id));

        return 'court_decisions/'.$court.'/'.$num;
    }

    protected function htmlToText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text ?? ''));
    }

    protected function tempFile(string $bytes, string $suffix): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'odluke_');
        $path = $tmp.$suffix;
        @unlink($tmp);
        file_put_contents($path, $bytes);

        return $path;
    }

    protected function upsertDecisionFromMeta(array $m): CourtDecision
    {
        $caseNumber = trim((string) ($m['broj_odluke'] ?? '')) ?: null;
        $court = trim((string) ($m['sud'] ?? '')) ?: null;
        $ecli = trim((string) ($m['ecli'] ?? '')) ?: null;

        $attrs = [
            'title' => (string) ($m['vrsta_odluke'] ?? 'Sudska odluka'),
            'court' => $court,
            'jurisdiction' => 'HR',
            'judge' => null,
            'decision_date' => $m['datum_odluke'] ?? null,
            'publication_date' => $m['datum_objave'] ?? null,
            'decision_type' => $m['vrsta_odluke'] ?? null,
            'register' => $m['upisnik'] ?? null,
            'finality' => $m['pravomocnost'] ?? null,
            'ecli' => $ecli,
            'tags' => array_filter([
                $m['vrsta_odluke'] ?? null,
                $m['upisnik'] ?? null,
                $m['pravomocnost'] ?? null,
            ]),
            'description' => null,
        ];

        // Try to find existing by ECLI first, then by case_number + court
        $decision = null;
        if ($ecli) {
            $decision = CourtDecision::query()->where('ecli', $ecli)->first();
        }
        if (! $decision && $caseNumber && $court) {
            $decision = CourtDecision::query()->where('case_number', $caseNumber)->where('court', $court)->first();
        }

        if ($decision) {
            $decision->fill($attrs)->save();

            return $decision;
        }

        $payload = array_merge(['id' => (string) Str::ulid(), 'case_number' => $caseNumber], (array) $attrs);
        $decision = new CourtDecision($payload);
        $decision->save();

        return $decision;
    }

    protected function buildMetadata(array $m, string $id, int $chunkIndex): array
    {
        $ctx = [
            'id' => $id,
            'broj_odluke' => $m['broj_odluke'] ?? null,
            'sud' => $m['sud'] ?? null,
            'datum_odluke' => $m['datum_odluke'] ?? null,
            'pravomocnost' => $m['pravomocnost'] ?? null,
            'datum_objave' => $m['datum_objave'] ?? null,
            'upisnik' => $m['upisnik'] ?? null,
            'vrsta_odluke' => $m['vrsta_odluke'] ?? null,
            'ecli' => $m['ecli'] ?? null,
            'src' => $m['src'] ?? $this->client->downloadHtmlUrl($id),
            'chunk' => $chunkIndex,
        ];

        return $ctx;
    }

    /**
     * Offline ingestion from a plain text body representing a decision. Useful for validation.
     */
    public function ingestText(string $text, array $meta = [], array $options = []): array
    {
        $model = $options['model'] ?? config('openai.models.embeddings');
        $chunkChars = (int) ($options['chunk_chars'] ?? 1500);
        $overlap = (int) ($options['overlap'] ?? 200);
        $dry = (bool) ($options['dry'] ?? false);

        $text = trim(preg_replace('/\s+/u', ' ', $text ?? ''));
        if ($text === '') {
            return ['ids_processed' => 0, 'inserted' => 0, 'would_chunks' => 0, 'errors' => 0, 'skipped' => 1, 'model' => $model, 'dry' => $dry];
        }

        // Upsert decision
        $decision = $this->upsertDecisionFromMeta($meta);
        $docId = $meta['ecli'] ?? ('offline-'.substr(sha1($text), 0, 12));

        // Delegate chunking + embedding to shared method
        $res = $this->chunkAndEmbed($text, $meta, [
            'decision_id' => (string) $decision->id,
            'doc_id' => $docId,
            'source' => $meta['src'] ?? null,
            'source_id' => $docId,
            'model' => $model,
            'chunk_chars' => $chunkChars,
            'overlap' => $overlap,
            'dry' => $dry,
        ]);

        return [
            'ids_processed' => 1,
            'inserted' => (int) ($res['inserted'] ?? 0),
            'would_chunks' => (int) ($res['would_chunks'] ?? 0),
            'errors' => 0,
            'skipped' => 0,
            'model' => $model,
            'dry' => $dry,
        ];
    }

    /**
     * Fetch metadata for a list of decision IDs, returning a stable structure
     * used by MCP tools.
     */
    public function getMetadataForIds(array $ids, ?string $baseUrl = null): array
    {
        $client = $baseUrl ? $this->client->withBaseUrl($baseUrl) : $this->client;
        $result = [];
        foreach ($ids as $one) {
            $one = trim((string) $one);
            if ($one === '') {
                continue;
            }
            $meta = $client->fetchDecisionMeta($one);
            if (! $meta) {
                $result[] = ['id' => $one, 'error' => 'Neuspješan dohvat'];

                continue;
            }
            $basename = $client->buildBaseFileName($meta, $one);
            $result[] = [
                'id' => $one,
                'meta' => $meta,
                'basename' => $basename,
                'download_pdf_url' => $client->downloadPdfUrl($one),
                'download_html_url' => $client->downloadHtmlUrl($one),
            ];
        }

        return $result;
    }

    /**
     * Download a decision in html/pdf/both, optionally saving to disk. Returns
     * a stable structure used by MCP tools.
     * Options: format ('pdf'|'html'|'both'), save (bool), base_url (string|null)
     */
    public function download(string $id, array $options = []): array
    {
        $format = in_array(($options['format'] ?? 'pdf'), ['pdf', 'html', 'both'], true) ? $options['format'] : 'pdf';
        $save = (bool) ($options['save'] ?? false);
        $baseUrl = $options['base_url'] ?? null;

        $client = $baseUrl ? $this->client->withBaseUrl($baseUrl) : $this->client;

        $outDir = config('odluke.out_dir') ?: storage_path('app/odluke');
        if ($save && ! is_dir($outDir)) {
            if (! @mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
                return [
                    'id' => $id,
                    'meta' => null,
                    'download_pdf_url' => $client->downloadPdfUrl($id),
                    'download_html_url' => $client->downloadHtmlUrl($id),
                    'saved' => [],
                    'errors' => ['io' => 'Failed to create output directory: '.$outDir],
                ];
            }
        }

        $meta = $client->fetchDecisionMeta($id) ?? [];
        $basename = $client->buildBaseFileName($meta, $id);

        $result = [
            'id' => $id,
            'meta' => $meta,
            'download_pdf_url' => $client->downloadPdfUrl($id),
            'download_html_url' => $client->downloadHtmlUrl($id),
            'saved' => [],
            'errors' => [],
        ];

        if (in_array($format, ['pdf', 'both'], true)) {
            $pdf = $client->downloadPdf($id);
            if ($pdf['ok'] ?? false) {
                if ($save) {
                    $path = rtrim($outDir, '/').'/'.$basename.'.pdf';
                    $bytesWritten = @file_put_contents($path, $pdf['bytes']);
                    if ($bytesWritten === false) {
                        $result['errors']['pdf'] = 'Failed to write file: '.$path;
                    } else {
                        $result['saved']['pdf'] = $path;
                    }
                } else {
                    $result['pdf'] = [
                        'content_type' => $pdf['content_type'] ?? null,
                        'bytes' => strlen($pdf['bytes'] ?? ''),
                    ];
                }
            } else {
                $result['errors']['pdf'] = 'HTTP '.($pdf['status'] ?? '??');
            }
        }

        if (in_array($format, ['html', 'both'], true)) {
            $html = $client->downloadHtml($id);
            if ($html['ok'] ?? false) {
                if ($save) {
                    $path = rtrim($outDir, '/').'/'.$basename.'.html';
                    $bytesWritten = @file_put_contents($path, $html['bytes']);
                    if ($bytesWritten === false) {
                        $result['errors']['html'] = 'Failed to write file: '.$path;
                    } else {
                        $result['saved']['html'] = $path;
                    }
                } else {
                    $result['html'] = [
                        'content_type' => $html['content_type'] ?? null,
                        'bytes' => strlen($html['bytes'] ?? ''),
                    ];
                }
            } else {
                $result['errors']['html'] = 'HTTP '.($html['status'] ?? '??');
            }
        }

        return $result;
    }

    /**
     * Shared helper: chunk text and (optionally) embed+store into decision vectors.
     * Options must include: decision_id (string), doc_id (string). Optional: source, source_id, upload_id, model, chunk_chars, overlap, dry
     * Returns: ['would_chunks' => int, 'inserted' => int]
     * @throws IngestException
     */
    private function chunkAndEmbed(string $text, array $meta, array $options = []): array
    {
        $decisionId = (string) ($options['decision_id'] ?? '');
        $docId = (string) ($options['doc_id'] ?? '');
        if ($decisionId === '' || $docId === '') {
            throw new \InvalidArgumentException('chunkAndEmbed requires decision_id and doc_id');
        }

        $model = $options['model'] ?? config('openai.models.embeddings');
        $chunkChars = (int) ($options['chunk_chars'] ?? 1500);
        $overlap = (int) ($options['overlap'] ?? 200);
        $dry = (bool) ($options['dry'] ?? false);

        $chunks = $this->pipeline->chunkText($text, $chunkChars, $overlap);
        $would = count($chunks);
        if ($would === 0) {
            return ['would_chunks' => 0, 'inserted' => 0];
        }

        if ($dry) {
            return ['would_chunks' => $would, 'inserted' => 0];
        }

        $source = $options['source'] ?? null;
        $sourceId = $options['source_id'] ?? null;
        $uploadId = $options['upload_id'] ?? null;

        $docs = [];
        foreach ($chunks as $ci => $content) {
            $docs[] = [
                'content' => $content,
                'metadata' => $this->buildMetadata($meta, $docId, $ci),
                'chunk_index' => $ci,
                'source' => $source,
                'source_id' => $sourceId,
            ];
        }

        $res = $this->decisionVectors->ingest($decisionId, $docId, $docs, [
            'model' => $model,
            'provider' => 'openai',
            'upload_id' => $uploadId,
        ]);

        return [
            'would_chunks' => $would,
            'inserted' => (int) ($res['inserted'] ?? 0),
        ];
    }
}
