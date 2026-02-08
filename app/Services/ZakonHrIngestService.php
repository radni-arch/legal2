<?php

namespace App\Services;

use App\Contracts\Ingest\ZakonHrIngestServiceInterface;
use App\Exceptions\IngestException;
use App\Jobs\GenerateLawMetadata;
use App\Models\IngestedLaw;
use App\Models\LawUpload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ZakonHrIngestService implements ZakonHrIngestServiceInterface
{
    public function __construct(
        protected LawParser $parser,
        protected LawVectorStoreService $vectorStore,
        protected PdfRenderer $renderer,
        protected PdfMerger $merger,
        protected OpenAIService $openai,
    ) {}

    /**
     * Ingest one or more zakon.hr pages by URL. Fetches HTML, splits into articles, renders PDFs per article, merges full, and embeds into laws.
     * Options: model, title, date, dry
     *
     * Progress Feedback Implementation:
     * - Dispatches LawImportProgress events during ingestion
     * - Events broadcast to 'law-imports' channel for real-time UI updates
     * - Livewire components can listen via Echo: Echo.channel('law-imports').listen('.import.progress', ...)
     *
     * @throws IngestException if complete ingestion fails
     */
    public function ingestUrls(array $urls, array $options = []): array
    {
        try {
            Log::info('ZakonHR batch ingestion initiated', [
                'url_count' => count($urls),
                'dry_run' => $options['dry'] ?? false,
            ]);

            $startTime = microtime(true);

            $model = $options['model'] ?? config('openai.models.embeddings');
            $dry = (bool) ($options['dry'] ?? false);

            $totalArticles = 0;
            $totalInserted = 0;
            $errors = 0;
            $articleErrors = 0;
            $processed = 0;
            $wouldChunks = 0;
            $totalUrls = count($urls);

            // Dispatch initial progress event
            event(new \App\Events\LawImportProgress('started', 0, $totalUrls, [
                'dry_run' => $dry,
                'started_at' => now()->toIso8601String(),
            ]));

            foreach ($urls as $idx => $url) {
                $url = trim((string) $url);
                if ($url === '') {
                    continue;
                }

                // Dispatch URL processing progress event
                event(new \App\Events\LawImportProgress('processing_url', $idx + 1, $totalUrls, [
                    'url' => $url,
                    'processed' => $processed,
                ]));

                // Check cache to avoid re-processing recently imported laws
                $cacheKey = 'law_import_'.md5($url);
                if (! $dry && Cache::has($cacheKey)) {
                    Log::info('Skipping recently imported law', [
                        'url' => $url,
                        'progress' => ($idx + 1).'/'.$totalUrls,
                    ]);
                    $processed++;

                    continue;
                }

                try {
                    Log::info('Starting law import', [
                        'url' => $url,
                        'progress' => ($idx + 1).'/'.$totalUrls,
                    ]);

                    $html = Http::retry(2, 250)->get($url)->throw()->body();
                    $title = $options['title'] ?? $this->extractTitle($html) ?? 'Zakon (zakon.hr)';
                    $pubDate = $options['date'] ?? ($this->extractPublishedDate($html) ?? null);
                    $title = Str::replace(' -  Zakon.hr - ', '-', $title);
                    $slug = Str::slug($title);
                    $titleSnake = Str::snake($title);
                    // Remove -_zakon_hr suffix if present
                    $titleSnake = preg_replace('/-?_?zakon_?hr$/i', '', $titleSnake);
                    $dateDir = ($pubDate ?: date('Y-m-d'));
                    $baseDir = 'hr-laws/zakonhr/'.$slug.'/'.$dateDir;
                    Storage::put($baseDir.'/source.html', $html);

                    // doc_id stable group identifier
                    $docId = 'zakonhr-'.$slug.'-'.$dateDir;

                    $articles = $this->parser->splitIntoArticles($html);
                    if (empty($articles)) {
                        $processed++;

                        continue;
                    }

                    // Create or reuse parent IngestedLaw (skip in dry runs)
                    $ingested = null;
                    if (! $dry) {
                        $ingested = $this->ensureIngestedLaw($docId, $titleSnake, $url, [
                            'source' => 'zakon.hr',
                            'date_published' => $pubDate,
                            'slug' => $slug,
                        ]);
                    }

                    $docs = [];
                    $articlePdfAbs = [];
                    foreach ($articles as $idxx => $art) {
                        try {
                            $plain = $this->htmlToText($art['html'] ?? '');
                            if ($plain === '') {
                                continue;
                            }

                            $articleNumber = (string) ($art['number'] ?? ($idxx + 1));
                            $docIdNew = $docId.'-clanak-'.$articleNumber;

                            // Render per-article PDF
                            $pdfFileName = $titleSnake.' - clanak-'.$articleNumber.'.pdf';
                            $pdfRelPath = $baseDir.'/'.$pdfFileName;
                            if (! $dry) {
                                $this->renderer->renderArticle([
                                    'law_title' => $titleSnake,
                                    'law_eli' => '',
                                    'law_pub_date' => $pubDate ?: '',
                                    'article_number' => $articleNumber,
                                    'article_html' => $art['html'],
                                    'generated_at' => gmdate('c'),
                                    'generator_version' => '1.0.0',
                                    'search_tags' => array_values(array_filter([$titleSnake, 'članak '.$articleNumber, $pubDate])),
                                ], Storage::path($pdfRelPath));
                                $this->recordLawUpload($ingested?->id, $docIdNew, $pdfRelPath, $pdfFileName, $url);
                                $articlePdfAbs[] = Storage::path($pdfRelPath);
                            }

                            $docs[] = [
                                'content' => $plain,
                                'metadata' => [
                                    'article_number' => $articleNumber,
                                    'heading_chain' => $art['heading_chain'] ?? [],
                                    'file_name' => $pdfFileName,
                                    'chunk_index' => $idxx,
                                ],
                                'law_meta' => [
                                    'title' => $titleSnake,
                                    'jurisdiction' => 'HR',
                                    'country' => 'HR',
                                    'language' => 'hr',
                                    'promulgation_date' => $pubDate,
                                    'source_url' => $url,
                                    'version' => 'as-published',
                                    'tags' => $art['heading_chain'] ?? [],
                                ],
                            ];
                            $wouldChunks++;
                            $totalArticles++;
                        } catch (\Throwable $e) {
                            $articleErrors++;
                            Log::warning('ZakonHR article processing failed', [
                                'law_id' => $ingested?->id,
                                'doc_id' => $docId,
                                'article_index' => $idxx,
                                'article_number' => $art['number'] ?? ($idxx + 1),
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Merge full PDF from article PDFs
                    if (! $dry && ! empty($articlePdfAbs)) {
                        $fullRel = $baseDir.'/full.pdf';
                        try {
                            $this->merger->merge($articlePdfAbs, Storage::path($fullRel));
                            $this->recordLawUpload($ingested?->id, $docId, $fullRel, $titleSnake.' - full.pdf', $url);
                        } catch (\Throwable $e) {
                            Log::warning('Failed merging full law PDF', ['url' => $url, 'err' => $e->getMessage()]);
                        }

                        // Free memory after PDF operations before proceeding to embeddings
                        unset($articlePdfAbs);
                        gc_collect_cycles();
                    }

                    if (! empty($docs) && ! $dry) {
                        Log::info('Generating embeddings and inserting into vector store', [
                            'doc_id' => $docId,
                            'chunks' => count($docs),
                            'progress' => ($idx + 1).'/'.$totalUrls,
                        ]);

                        $res = $this->vectorStore->ingest($docId, $docs, [
                            'model' => $model,
                            'provider' => 'openai',
                            'ingested_law_id' => $ingested?->id,
                        ]);
                        $totalInserted += (int) ($res['inserted'] ?? 0);

                        // Cache this URL to prevent duplicate imports for 24 hours
                        Cache::put($cacheKey, now()->toIso8601String(), now()->addDay());

                        Log::info('Law import completed', [
                            'url' => $url,
                            'title' => $title,
                            'articles' => count($articles),
                            'inserted' => $res['inserted'] ?? 0,
                            'progress' => ($idx + 1).'/'.$totalUrls,
                        ]);

                        // Dispatch AI metadata generation job (once per law, not per article!)
                        if ($ingested && ! empty($docs)) {
                            $this->dispatchMetadataGeneration($ingested->id, $docs);
                        }
                    }
                    $processed++;

                    // Dispatch URL completion event
                    event(new \App\Events\LawImportProgress('url_completed', $processed, $totalUrls, [
                        'url' => $url,
                        'articles' => count($articles ?? []),
                        'inserted' => $totalInserted,
                    ]));
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error('ZakonHR URL ingest failed', [
                        'url' => $url,
                        'progress' => ($idx + 1).'/'.$totalUrls,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue with remaining URLs
                }
            }

            Log::info('ZakonHR batch ingestion completed', [
                'url_count' => $totalUrls,
                'processed' => $processed,
                'errors' => $errors,
                'article_errors' => $articleErrors,
                'total_articles' => $totalArticles,
                'total_inserted' => $totalInserted,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            // Dispatch completion event
            event(new \App\Events\LawImportProgress('completed', $totalUrls, $totalUrls, [
                'processed' => $processed,
                'articles' => $totalArticles,
                'inserted' => $totalInserted,
                'errors' => $errors,
                'article_errors' => $articleErrors,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]));

            return [
                'urls_processed' => $processed,
                'articles_seen' => $totalArticles,
                'inserted' => $totalInserted,
                'would_chunks' => $wouldChunks,
                'errors' => $errors,
                'article_errors' => $articleErrors,
                'model' => $model,
                'dry' => $dry,
            ];

        } catch (IngestException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('ZakonHR batch ingestion failed', [
                'url_count' => count($urls),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                'Batch ingestion failed: '.$e->getMessage(),
                IngestException::PIPELINE_FAILED,
                $e
            );
        }
    }

    /**
     * Offline/inline HTML ingestion for validation or custom HTML sources.
     *
     * @throws IngestException if ingestion fails
     */
    public function ingestHtml(string $html, array $options = [], string $sourceUrl = 'offline://zakonhr-sample'): array
    {
        try {
            Log::info('ZakonHR HTML ingestion initiated', [
                'html_length' => strlen($html),
                'dry_run' => $options['dry'] ?? false,
            ]);

            $startTime = microtime(true);

            $model = $options['model'] ?? config('openai.models.embeddings');
            $dry = (bool) ($options['dry'] ?? false);

            $title = $options['title'] ?? ($this->extractTitle($html) ?? 'Zakon (offline)');
            $title = Str::replace(' -  Zakon.hr', '', $title);
            $slug = Str::slug($title);
            $titleSnake = Str::snake($title);
            // Remove -_zakon_hr suffix if present
            $titleSnake = preg_replace('/-?_?zakon_?hr$/i', '', $titleSnake);
            $datePub = $options['date'] ?? ($this->extractPublishedDate($html) ?? null);
            $dateDir = ($datePub ?: date('Y-m-d'));
            $baseDir = 'hr-laws/zakonhr/'.$slug.'/'.$dateDir;
            Storage::put($baseDir.'/source.html', $html);

            $docId = 'zakonhr-'.$slug.'-'.$dateDir;

            $articles = $this->parser->splitIntoArticles($html);
            $docs = [];
            $wouldChunks = 0;
            $totalArticles = 0;
            $totalInserted = 0;
            $articleErrors = 0;
            $articlePdfAbs = [];

            // Ensure IngestedLaw (skip when dry)
            $ingested = null;
            if (! $dry) {
                $ingested = $this->ensureIngestedLaw($docId, $titleSnake, $sourceUrl, [
                    'source' => 'zakon.hr',
                    'date_published' => $datePub,
                    'slug' => $slug,
                ]);
            }

            foreach ($articles as $idx => $art) {
                try {
                    $plain = $this->htmlToText($art['html'] ?? '');
                    if ($plain === '') {
                        continue;
                    }
                    $articleNumber = (string) ($art['number'] ?? ($idx + 1));
                    $pdfFileName = $titleSnake.' - clanak-'.$articleNumber.'.pdf';
                    if (! $dry) {
                        $pdfRel = $baseDir.'/'.$pdfFileName;
                        $this->renderer->renderArticle([
                            'law_title' => $titleSnake,
                            'law_eli' => '',
                            'law_pub_date' => $datePub ?: '',
                            'article_number' => $articleNumber,
                            'article_html' => $art['html'],
                            'generated_at' => gmdate('c'),
                            'generator_version' => '1.0.0',
                            'search_tags' => array_values(array_filter([$titleSnake, 'članak '.$articleNumber, $datePub])),
                        ], Storage::path($pdfRel));
                        $this->recordLawUpload($ingested?->id, $docId.'-clanak-'.$articleNumber, $pdfRel, $pdfFileName, $sourceUrl);
                        $articlePdfAbs[] = Storage::path($pdfRel);
                    }

                    $docs[] = [
                        'content' => $plain,
                        'metadata' => [
                            'article_number' => $articleNumber,
                            'heading_chain' => $art['heading_chain'] ?? [],
                            'file_name' => $pdfFileName,
                            'chunk_index' => $idx,
                        ],
                        'law_meta' => [
                            'title' => $titleSnake,
                            'jurisdiction' => 'HR',
                            'country' => 'HR',
                            'language' => 'hr',
                            'promulgation_date' => $datePub,
                            'source_url' => $sourceUrl,
                            'version' => 'offline',
                            'tags' => $art['heading_chain'] ?? [],
                        ],
                    ];
                    $wouldChunks++;
                    $totalArticles++;
                } catch (\Throwable $e) {
                    $articleErrors++;
                    Log::warning('ZakonHR article processing failed (offline)', [
                        'law_id' => $ingested?->id,
                        'doc_id' => $docId,
                        'article_index' => $idx,
                        'article_number' => $art['number'] ?? ($idx + 1),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (! $dry && ! empty($articlePdfAbs)) {
                $fullRel = $baseDir.'/full.pdf';
                try {
                    $this->merger->merge($articlePdfAbs, Storage::path($fullRel));
                    $this->recordLawUpload($ingested?->id, $docId, $fullRel, $titleSnake.' - full.pdf', $sourceUrl);
                } catch (\Throwable $e) {
                    Log::warning('Failed merging full law PDF (offline)', ['err' => $e->getMessage()]);
                }

                // Free memory after PDF operations before proceeding to embeddings
                unset($articlePdfAbs);
                gc_collect_cycles();
            }

            if (! empty($docs) && ! $dry) {
                $res = $this->ingestToVectorStore($docId, $docs, [
                    'model' => $model,
                    'provider' => 'openai',
                    'ingested_law_id' => $ingested?->id,
                ]);
                $totalInserted += (int) ($res['inserted'] ?? 0);

                // Dispatch AI metadata generation job (once per law, not per article!)
                if ($ingested && ! empty($docs)) {
                    $this->dispatchMetadataGeneration($ingested->id, $docs);
                }
            }

            Log::info('ZakonHR HTML ingestion completed', [
                'articles' => $totalArticles,
                'inserted' => $totalInserted,
                'article_errors' => $articleErrors,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'urls_processed' => 1,
                'articles_seen' => $totalArticles,
                'inserted' => $totalInserted,
                'would_chunks' => $wouldChunks,
                'errors' => 0,
                'article_errors' => $articleErrors,
                'model' => $model,
                'dry' => $dry,
            ];

        } catch (IngestException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('ZakonHR HTML ingestion failed', [
                'html_length' => strlen($html),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IngestException(
                'HTML ingestion failed: '.$e->getMessage(),
                IngestException::PIPELINE_FAILED,
                $e
            );
        }
    }

    protected function htmlToText(string $html): string
    {
        // Remove script/style blocks before stripping tags
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text ?? '');
        // Light cleanup of common noise patterns
        $text = preg_replace('/Copyright\s+©?\s+[^ ]+/', '', $text);

        return trim($text);
    }

    protected function extractPublishedDate(string $html): ?string
    {
        if (preg_match('/na\h+snazi\h+od\h*:?\h*(?:<[^>]>\h)*((?:0?[1-9]|[12]\d|3[01]).(?:0?[1-9]|1[0-2]).\d{4}).?/iu', $html, $m)) {
            $date = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $this->validateAndNormalizeDate($date);
        }

        return null;
    }

    /**
     * Validates and normalizes a date string to prevent strtotime issues
     * Returns normalized date in Y-m-d format or null if invalid
     */
    protected function validateAndNormalizeDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        // Try to parse the date
        $timestamp = @strtotime($date);

        // Validate timestamp - strtotime returns false on failure or negative timestamp
        // Also check it's not the Unix epoch (1970-01-01) which indicates parsing failure
        if ($timestamp === false || $timestamp < 0) {
            Log::warning('Invalid date format encountered', ['date' => $date]);

            return null;
        }

        // Ensure the year is reasonable (between 1900 and current year + 10)
        $year = (int) date('Y', $timestamp);
        $currentYear = (int) date('Y');
        if ($year < 1900 || $year > ($currentYear + 10)) {
            Log::warning('Date year out of reasonable range', ['date' => $date, 'year' => $year]);

            return null;
        }

        // Return normalized date in Y-m-d format
        return date('Y-m-d', $timestamp);
    }

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return null;
    }

    protected function recordLawUpload(?string $ingestedLawId, string $docId, string $relPath, string $originalName, ?string $sourceUrl = null): void
    {
        $disk = 'local';
        $abs = Storage::path($relPath);
        $size = is_file($abs) ? @filesize($abs) : null;
        $sha = is_file($abs) ? @hash_file('sha256', $abs) : null;

        LawUpload::create([
            'id' => (string) Str::ulid(),
            'doc_id' => $docId,
            'ingested_law_id' => $ingestedLawId,
            'disk' => $disk,
            'local_path' => $relPath,
            'original_filename' => $originalName,
            'mime_type' => 'application/pdf',
            'file_size' => $size,
            'sha256' => $sha,
            'source_url' => $sourceUrl,
            'downloaded_at' => now(),
            'status' => 'stored',
        ]);
    }

    /**
     * Ensure IngestedLaw record exists
     *
     * @throws IngestException
     */
    protected function ensureIngestedLaw(string $docId, string $title, ?string $sourceUrl = null, array $meta = []): IngestedLaw
    {
        try {
            $existing = IngestedLaw::query()
                ->where('doc_id', $docId)
                ->orWhere(function ($q) use ($sourceUrl) {
                    if ($sourceUrl) {
                        $q->where('source_url', $sourceUrl);
                    }
                })
                ->first();

            if ($existing) {
                Log::debug('Found existing IngestedLaw', ['doc_id' => $docId, 'id' => $existing->id]);

                return $existing;
            }

            // Generate enhanced aliases and keywords
            $enhancedMeta = $this->generateEnhancedMetadata($title, $sourceUrl, $meta);

            $law = IngestedLaw::create([
                'id' => (string) Str::ulid(),
                'doc_id' => $docId,
                'title' => (string) $title,
                'jurisdiction' => 'HR',
                'country' => 'HR',
                'language' => 'hr',
                'source_url' => $sourceUrl,
                'aliases' => $enhancedMeta['aliases'] ?? [],
                'keywords' => $enhancedMeta['keywords'] ?? [],
                'keywords_text' => implode(' ', $enhancedMeta['keywords'] ?? []),
                'metadata' => array_merge($meta, $enhancedMeta),
                'ingested_at' => now(),
            ]);

            Log::info('Created IngestedLaw record', ['doc_id' => $docId, 'id' => $law->id]);

            return $law;

        } catch (\Exception $e) {
            Log::error('Failed to ensure IngestedLaw record', [
                'doc_id' => $docId,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            throw new IngestException(
                'Failed to create law record: '.$e->getMessage(),
                IngestException::LAW_CREATION_FAILED,
                $e
            );
        }
    }

    /**
     * Generate enhanced metadata using OpenAI for law ingestion
     */
    protected function generateEnhancedMetadata(string $title, ?string $sourceUrl, array $baseMeta = []): array
    {
        $datePublished = $baseMeta['date_published'] ?? null;
        try {
            $prompt = <<<PROMPT
Extract metadata from this Croatian law title and information:

Title: {$title}
Source URL: {$sourceUrl}
Published Date: {$datePublished}

Generate a JSON response with:
1. "aliases": Array of alternative names/abbreviations (e.g., full name, common abbreviations, Croatian and English names)
2. "keywords": Array of relevant keywords (in Croatian, related to the law's subject matter)
3. "law_code": Short code if identifiable (e.g., "ZKP", "OZ", "NN")
4. "law_code_alias": Array of law code variations

Example format:
{
  "aliases": ["Zakon o kaznenom postupku", "ZKP RH", "Criminal Procedure Act"],
  "keywords": ["kazneni postupak", "sud", "optužnica", "pretraga", "dokazi"],
  "law_code": "ZKP",
  "law_code_alias": ["ZKP RH", "Zakon o kaznenom postupku"]
}

Return only valid JSON, no markdown formatting.
PROMPT;

            $response = $this->openai->chat([
                ['role' => 'system', 'content' => 'You are a legal metadata extraction assistant. Always respond with valid JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ], config('openai.models.chat', 'gpt-4o-mini'), [
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            // Remove markdown code fences if present
            $content = preg_replace('/^```json\s*|\s*```$/m', '', trim($content));
            $generated = json_decode($content, true);

            if (! is_array($generated)) {
                throw new \Exception('Invalid JSON response from OpenAI');
            }

            return [
                'aliases' => array_values(array_unique(array_filter($generated['aliases'] ?? [$title]))),
                'keywords' => array_values(array_unique(array_filter($generated['keywords'] ?? []))),
                'law_code' => $generated['law_code'] ?? null,
                'law_code_alias' => $generated['law_code_alias'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to generate enhanced metadata', ['title' => $title, 'error' => $e->getMessage()]);

            // Fallback to simple metadata
            return [
                'aliases' => [$title],
                'keywords' => [Str::before($title, '_'), 'zakon.hr'],
                'law_code' => null,
                'law_code_alias' => [],
            ];
        }
    }

    /* Dispatch metadata generation job with environment-aware queue selection.
    * Uses sync queue for development, async (database) for production.
    *
    * This is the CORRECT approach: Call OpenAI ONCE per law, not per article!
    */
    protected function dispatchMetadataGeneration(string $ingestedLawId, array $docs): void
    {
        // Extract article data needed for metadata generation
        $articles = [];
        foreach ($docs as $doc) {
            $articles[] = [
                'content' => $doc['content'] ?? '',
                'article_number' => $doc['metadata']['article_number'] ?? null,
                'heading_chain' => $doc['metadata']['heading_chain'] ?? [],
            ];
        }

        // Environment-aware queue selection:
        // - Development: 'sync' (execute immediately, easier debugging)
        // - Production: 'database' (async, better performance)
        $queueConnection = app()->environment('production') ? 'database' : 'sync';

        GenerateLawMetadata::dispatch($ingestedLawId, $articles)
            ->onConnection($queueConnection)
            ->onQueue('metadata-generation');

        Log::info('ZakonHrIngestService: Dispatched metadata generation job', [
            'ingested_law_id' => $ingestedLawId,
            'article_count' => count($articles),
            'queue_connection' => $queueConnection,
        ]);
    }

    /**
     * Fetch HTML from URL with retry logic
     *
     * @throws IngestException
     */
    protected function fetchLawHtml(string $url): string
    {
        try {
            $response = Http::retry(2, 250)->get($url);

            if (! $response->successful()) {
                throw new \Exception('HTTP request failed with status '.$response->status());
            }

            return $response->body();

        } catch (\Exception $e) {
            Log::error('Failed to fetch law HTML from URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new IngestException(
                'Failed to fetch law from URL: '.$e->getMessage(),
                IngestException::HTTP_FETCH_FAILED,
                $e
            );
        }
    }

    /**
     * Process articles and render PDFs
     *
     * @throws IngestException
     */
    protected function processArticlesAndRenderPdfs(
        array $articles,
        string $docId,
        string $titleSnake,
        string $baseDir,
        ?string $pubDate,
        ?string $url,
        ?IngestedLaw $ingested,
        bool $dry
    ): array {
        try {
            $docs = [];
            $articlePdfAbs = [];
            $articleErrors = 0;

            foreach ($articles as $idx => $art) {
                try {
                    $plain = $this->htmlToText($art['html'] ?? '');
                    if ($plain === '') {
                        continue;
                    }

                    $articleNumber = (string) ($art['number'] ?? ($idx + 1));
                    $docIdNew = $docId.'-clanak-'.$articleNumber;
                    $pdfFileName = $titleSnake.' - clanak-'.$articleNumber.'.pdf';
                    $pdfRelPath = $baseDir.'/'.$pdfFileName;

                    if (! $dry) {
                        $this->renderArticlePdf([
                            'law_title' => $titleSnake,
                            'law_eli' => '',
                            'law_pub_date' => $pubDate ?: '',
                            'article_number' => $articleNumber,
                            'article_html' => $art['html'],
                            'generated_at' => gmdate('c'),
                            'generator_version' => '1.0.0',
                            'search_tags' => array_values(array_filter([$titleSnake, 'članak '.$articleNumber, $pubDate])),
                        ], Storage::path($pdfRelPath));

                        $this->recordLawUpload($ingested?->id, $docIdNew, $pdfRelPath, $pdfFileName, $url);
                        $articlePdfAbs[] = Storage::path($pdfRelPath);
                    }

                    $docs[] = [
                        'content' => $plain,
                        'metadata' => [
                            'article_number' => $articleNumber,
                            'heading_chain' => $art['heading_chain'] ?? [],
                            'file_name' => $pdfFileName,
                            'chunk_index' => $idx,
                        ],
                        'law_meta' => [
                            'title' => $titleSnake,
                            'jurisdiction' => 'HR',
                            'country' => 'HR',
                            'language' => 'hr',
                            'promulgation_date' => $pubDate,
                            'source_url' => $url,
                            'version' => 'as-published',
                            'tags' => $art['heading_chain'] ?? [],
                        ],
                    ];
                } catch (\Throwable $e) {
                    $articleErrors++;
                    Log::warning('ZakonHR article processing failed', [
                        'law_id' => $ingested?->id,
                        'doc_id' => $docId,
                        'article_index' => $idx,
                        'article_number' => $art['number'] ?? ($idx + 1),
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other articles
                }
            }

            return [
                'docs' => $docs,
                'article_pdfs' => $articlePdfAbs,
                'article_errors' => $articleErrors,
            ];

        } catch (\Exception $e) {
            Log::error('Article processing failed', [
                'doc_id' => $docId,
                'error' => $e->getMessage(),
            ]);

            throw new IngestException(
                'Failed to process articles: '.$e->getMessage(),
                IngestException::ARTICLE_PARSING_FAILED,
                $e
            );
        }
    }

    /**
     * Render article PDF with error handling
     *
     * @throws IngestException
     */
    protected function renderArticlePdf(array $articleData, string $outputPath): void
    {
        try {
            $this->renderer->renderArticle($articleData, $outputPath);
        } catch (\Exception $e) {
            Log::error('PDF rendering failed', [
                'article_number' => $articleData['article_number'] ?? 'unknown',
                'output_path' => $outputPath,
                'error' => $e->getMessage(),
            ]);

            throw new IngestException(
                'Failed to render PDF for article: '.$e->getMessage(),
                IngestException::PDF_RENDER_FAILED,
                $e
            );
        }
    }

    /**
     * Merge article PDFs into full PDF
     */
    protected function mergeArticlePdfs(array $articlePdfAbs, string $outputPath, string $url): void
    {
        try {
            $this->merger->merge($articlePdfAbs, $outputPath);
        } catch (\Throwable $e) {
            Log::warning('Failed merging full law PDF', [
                'url' => $url,
                'pdf_count' => count($articlePdfAbs),
                'error' => $e->getMessage(),
            ]);
            // Non-critical failure - don't throw
        }
    }

    /**
     * Ingest documents into vector store
     *
     * @throws IngestException
     */
    protected function ingestToVectorStore(string $docId, array $docs, array $options): array
    {
        try {
            return $this->vectorStore->ingest($docId, $docs, $options);
        } catch (\Exception $e) {
            Log::error('Vector store ingestion failed', [
                'doc_id' => $docId,
                'chunk_count' => count($docs),
                'error' => $e->getMessage(),
            ]);

            throw new IngestException(
                'Failed to ingest to vector store: '.$e->getMessage(),
                IngestException::VECTOR_STORE_FAILED,
                $e
            );
        }
    }
}
