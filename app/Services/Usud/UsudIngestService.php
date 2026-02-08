<?php

namespace App\Services\Usud;

use App\Jobs\IngestUsudDecision;
use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\CourtDecisionDocumentUpload;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\IngestPipelineService;
use App\Services\OcrService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UsudIngestService
{
    public function __construct(
        protected UsudClient $client,
        protected IngestPipelineService $pipeline,
        protected CourtDecisionVectorStoreService $decisionVectors,
        protected ?OcrService $ocr = null,
        protected ?\App\Services\GraphDatabaseService $graphDb = null,
    ) {}

    public function searchList(string $query): array
    {
        return $this->client->search($query);
    }

    public function filterAlreadyIngestedIds(array $sourceIds): array
    {
        if (empty($sourceIds)) {
            return [];
        }

        $sourceIds = array_filter(array_map('trim', $sourceIds), fn ($id) => $id !== '');
        if (empty($sourceIds)) {
            return [];
        }

        $normalized = array_map(fn ($id) => $this->sourceId($id), $sourceIds);

        $table = (new CourtDecisionDocument)->getTable();
        $existingIds = DB::table($table)
            ->whereIn('source_id', $normalized)
            ->distinct()
            ->pluck('source_id')
            ->toArray();

        $existingIds = array_map(fn ($id) => $this->stripSourcePrefix($id), $existingIds);
        $newIds = array_diff($sourceIds, $existingIds);

        if (count($existingIds) > 0) {
            Log::info('Filtered already-ingested USUD decision IDs', [
                'total_input' => count($sourceIds),
                'already_ingested' => count($existingIds),
                'to_process' => count($newIds),
            ]);
        }

        return array_values($newIds);
    }

    public function ingestByIds(array $ids, array $options = []): array
    {
        $useQueue = (bool) ($options['queue'] ?? false);

        if ($useQueue) {
            return $this->ingestByIdsQueued($ids, $options);
        }

        return $this->ingestByIdsSync($ids, $options);
    }

    public function ingestByIdsQueued(array $ids, array $options = []): array
    {
        $queued = 0;
        $errors = 0;

        $queueName = $options['queue_name'] ?? 'default';

        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }

            try {
                IngestUsudDecision::dispatch($id, $options)->onQueue($queueName);
                $queued++;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Failed to queue USUD decision for ingestion', [
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

    protected function ingestByIdsSync(array $ids, array $options = []): array
    {
        $model = $options['model'] ?? config('openai.models.embeddings');
        $chunkChars = (int) ($options['chunk_chars'] ?? 1500);
        $overlap = (int) ($options['overlap'] ?? 200);
        $dry = (bool) ($options['dry'] ?? false);
        $syncGraph = (bool) ($options['sync_graph'] ?? config('usud.sync_graph', false));
        $force = (bool) ($options['force'] ?? false);

        $inserted = 0;
        $count = 0;
        $errors = 0;
        $skipped = 0;
        $wouldChunks = 0;
        $graphSynced = 0;
        $graphErrors = 0;

        $originalCount = count($ids);
        if (! $force && ! $dry) {
            $alreadyIngestedIds = $this->getAlreadyIngestedIds($ids);
            if (count($alreadyIngestedIds) > 0) {
                $ids = $this->filterAlreadyIngestedIds($ids);
            }
        }

        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }

            $count++;

            try {
                $detail = $this->client->fetchDecisionDetail($id, true);
                if (isset($detail['error'])) {
                    $errors++;
                    Log::warning('USUD detail fetch failed', [
                        'id' => $id,
                        'error' => $detail['error'],
                    ]);
                    continue;
                }

                $pdfUrl = $detail['pdf_url'] ?? null;
                if (! $pdfUrl) {
                    $filename = $this->filenameFromCaseNumber($detail['case_number'] ?? null);
                    if ($filename) {
                        $pdfUrl = $this->client->buildPdfUrl($id, $filename);
                    }
                }

                if (! $pdfUrl) {
                    $errors++;
                    Log::warning('USUD decision missing PDF link', ['id' => $id]);
                    continue;
                }

                $decision = $this->upsertDecisionFromMeta($detail);

                if (! $dry && isset($detail['html'])) {
                    $this->persistSource($id, $detail['html'], 'html', $decision);
                }

                $pdfRes = $this->client->downloadPdf($pdfUrl);
                if (! ($pdfRes['ok'] ?? false) || ! is_string($pdfRes['bytes'] ?? null)) {
                    $errors++;
                    Log::warning('USUD PDF download failed', [
                        'id' => $id,
                        'status' => $pdfRes['status'] ?? null,
                        'url' => $pdfUrl,
                    ]);
                    continue;
                }

                $uploadId = null;
                $pdfPath = $this->tempFile($pdfRes['bytes'], '.pdf');

                if (! $dry) {
                    $rel = $this->storePdfUpload($decision, $id, $pdfRes['bytes'], $pdfUrl);
                    $uploadId = $rel['upload_id'] ?? null;
                    $pdfPath = $rel['abs_path'] ?? $pdfPath;
                    $this->persistSource($id, $pdfRes['bytes'], 'pdf', $decision);
                }

                $this->ocr = $this->ocr ?? new OcrService();
                $text = $this->ocr->extractTextFromPdf($pdfPath);
                $text = trim(preg_replace('/\s+/u', ' ', $text ?? ''));

                if ($text === '') {
                    $skipped++;
                    Log::warning('USUD ingestion skipped due to empty text', ['id' => $id]);
                    continue;
                }

                $docId = $this->docId($id);
                $sourceId = $this->sourceId($id);

                $res = $this->chunkAndEmbed($text, $detail, [
                    'decision_id' => (string) $decision->id,
                    'doc_id' => $docId,
                    'source' => $detail['detail_url'] ?? null,
                    'source_id' => $sourceId,
                    'upload_id' => $uploadId,
                    'model' => $model,
                    'chunk_chars' => $chunkChars,
                    'overlap' => $overlap,
                    'dry' => $dry,
                ]);

                $wouldChunks += (int) ($res['would_chunks'] ?? 0);
                $currentInserted = (int) ($res['inserted'] ?? 0);
                $inserted += $currentInserted;

                if ($syncGraph && ! $dry && $this->graphDb && $currentInserted > 0) {
                    try {
                        $this->graphDb->storeDecisionInGraph($detail, $docId);
                        $graphSynced++;
                    } catch (\Throwable $graphError) {
                        $graphErrors++;
                        Log::warning('USUD graph sync failed (vector ingestion succeeded)', [
                            'id' => $id,
                            'doc_id' => $docId,
                            'error' => $graphError->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('USUD ingest failed', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }

        return [
            'ids_processed' => $count,
            'ids_total' => $originalCount,
            'inserted' => $inserted,
            'would_chunks' => $wouldChunks,
            'errors' => $errors,
            'skipped' => $skipped,
            'graph_synced' => $graphSynced,
            'graph_errors' => $graphErrors,
            'model' => $model,
            'dry' => $dry,
        ];
    }

    protected function upsertDecisionFromMeta(array $m): CourtDecision
    {
        $caseNumber = trim((string) ($m['case_number'] ?? '')) ?: null;
        $title = trim((string) ($m['title'] ?? '')) ?: 'Ustavni sud odluka';
        $court = 'USUD';

        $attrs = [
            'title' => $title,
            'court' => $court,
            'jurisdiction' => 'HR',
            'judge' => null,
            'decision_date' => $m['decision_date'] ?? null,
            'decision_type' => $m['decision_type'] ?? null,
            'case_number' => $caseNumber,
            'tags' => array_filter([
                $m['decision_type'] ?? null,
                $caseNumber ? 'predmet '.$caseNumber : null,
            ]),
            'description' => null,
        ];

        $decision = null;
        if ($caseNumber) {
            $decision = CourtDecision::query()
                ->where('case_number', $caseNumber)
                ->where('court', $court)
                ->first();
        }
        if (! $decision && $title) {
            $decision = CourtDecision::query()
                ->where('title', $title)
                ->where('court', $court)
                ->first();
        }

        if ($decision) {
            $decision->fill($attrs)->save();

            return $decision;
        }

        $payload = array_merge(['id' => (string) Str::ulid()], (array) $attrs);
        $decision = new CourtDecision($payload);
        $decision->save();

        return $decision;
    }

    protected function buildMetadata(array $m, string $docId, int $chunkIndex): array
    {
        return [
            'id' => $docId,
            'usud_id' => $m['id'] ?? null,
            'case_number' => $m['case_number'] ?? null,
            'decision_date' => $m['decision_date'] ?? null,
            'decision_type' => $m['decision_type'] ?? null,
            'title' => $m['title'] ?? null,
            'detail_url' => $m['detail_url'] ?? null,
            'pdf_url' => $m['pdf_url'] ?? null,
            'chunk' => $chunkIndex,
        ];
    }

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

    protected function persistSource(string $id, string $bytes, string $ext, CourtDecision $decision): string
    {
        $dir = $this->decisionBaseDir($decision).'/sources';
        $path = $dir.'/'.$id.'.'.$ext;
        Storage::put($path, $bytes);

        return $path;
    }

    protected function storePdfUpload(CourtDecision $decision, string $id, string $bytes, string $sourceUrl): array
    {
        $dir = $this->decisionBaseDir($decision).'/pdfs';
        $fileName = 'decision-'.$id.'.pdf';
        $rel = $dir.'/'.$fileName;
        Storage::put($rel, $bytes);
        $abs = Storage::path($rel);

        $upload = CourtDecisionDocumentUpload::create([
            'id' => (string) Str::ulid(),
            'decision_id' => (string) $decision->id,
            'doc_id' => $this->docId($id),
            'disk' => 'local',
            'local_path' => $rel,
            'original_filename' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size' => @filesize($abs) ?: null,
            'sha256' => @hash_file('sha256', $abs) ?: null,
            'source_url' => $sourceUrl,
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

    protected function tempFile(string $bytes, string $suffix): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'usud_');
        $path = $tmp.$suffix;
        @unlink($tmp);
        file_put_contents($path, $bytes);

        return $path;
    }

    protected function sourceId(string $id): string
    {
        return 'usud:'.$id;
    }

    protected function stripSourcePrefix(string $id): string
    {
        return str_starts_with($id, 'usud:') ? substr($id, 5) : $id;
    }

    protected function docId(string $id): string
    {
        return 'usud-'.$id;
    }

    protected function getAlreadyIngestedIds(array $sourceIds): array
    {
        if (empty($sourceIds)) {
            return [];
        }

        $sourceIds = array_filter(array_map('trim', $sourceIds), fn ($id) => $id !== '');
        if (empty($sourceIds)) {
            return [];
        }

        $normalized = array_map(fn ($id) => $this->sourceId($id), $sourceIds);

        $table = (new CourtDecisionDocument)->getTable();

        $existing = DB::table($table)
            ->whereIn('source_id', $normalized)
            ->distinct()
            ->pluck('source_id')
            ->toArray();

        return array_map(fn ($id) => $this->stripSourcePrefix($id), $existing);
    }

    protected function filenameFromCaseNumber(?string $caseNumber): ?string
    {
        if (! $caseNumber) {
            return null;
        }

        $file = str_replace('/', '-', $caseNumber);
        $file = preg_replace('/\s+/', '', $file ?? '');
        $file = preg_replace('/[^A-Za-z0-9\-]/', '', $file ?? '');

        return $file !== '' ? $file.'.pdf' : null;
    }
}
