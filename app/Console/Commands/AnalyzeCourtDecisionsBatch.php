<?php

namespace App\Console\Commands;

use App\Services\ApiRotator\ApiKeyRotatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Pool;
use Carbon\Carbon;
use Throwable;

class AnalyzeCourtDecisionsBatch extends Command
{
    protected $signature = 'court:analyze-batch
                            {--input=court_decision_links.txt : Input file with PDF URLs}
                            {--output=court_analysis_results : Output directory for results}
                            {--concurrency=3 : Number of concurrent API requests}
                            {--download-concurrency=10 : Number of concurrent PDF downloads}
                            {--max-retries=3 : Maximum retry attempts per request}
                            {--retry-delay=5 : Initial delay between retries in seconds}
                            {--skip-download : Skip PDF download, use cached files}
                            {--dry-run : Only download PDFs without API calls}
                            {--resume : Resume from last checkpoint}
                            {--limit= : Limit number of documents to process}
                            {--prefer-gemini : Prefer Gemini API over others}
                            {--prefer-mistral : Prefer Mistral API over others (default)}';

    protected $description = 'Batch analyze Croatian court decisions with smart API key rotation';

    private ApiKeyRotatorService $rotator;
    private string $pdfCacheDir;

    private array $stats = [
        'total' => 0,
        'downloaded' => 0,
        'analyzed' => 0,
        'cached' => 0,
        'failed_download' => 0,
        'failed_analysis' => 0,
        'rotator_calls' => 0,
        'rotator_failures' => 0,
    ];

    private string $lastJsonError = '';

    public function __construct(ApiKeyRotatorService $rotator)
    {
        parent::__construct();
        $this->rotator = $rotator;
    }

    private string $systemPrompt = 'You are an expert legal analysis assistant for Croatian criminal procedure.

Output contract:
- Output must be EXACTLY one valid JSON object and nothing else.
- Do NOT use Markdown, code fences, headings, or commentary.
- Use double quotes for all JSON strings/keys.
- Do not include trailing commas.
- Never output NaN/Infinity.
- If a field is unknown or not stated, use null (or [] for lists).
- Do not invent facts; only use information present in the document.
- Property values are strictly in Croatian.!';

    private string $analysisPrompt = 'Analyze the Croatian court decision (rješenje/presuda) about exclusion of illegal evidence (nezakoniti dokazi).

Task: Extract structured information and return STRICT JSON matching the schema below.

Hard rules:
- Return ONLY the JSON object. No extra text. No Markdown. No code fences.
- Output must start with { and end with }.
- Include ALL keys from the schema. Do not add extra keys.
- Strings must be properly JSON-escaped (e.g., " becomes \").
- Dates must be ISO format YYYY-MM-DD when available; otherwise null.
- For unknown/not mentioned: use null (scalars/objects) or [] (arrays).
- For enums, use exactly one of the allowed values.
- Before answering, validate that the output is valid JSON.

Schema (JSON object):
{
  "case_code": string|null,
  "court": string|null,
  "decision_date": string|null,
  "decision_type": "presuda"|"rješenje"|null,
  "summary": string|null,
  "evidence_exclusion": {
    "proposed_exclusions": [
      {
        "evidence_type": string|null,
        "description": string|null,
        "legal_basis_cited": string|null,
        "argument": string|null
      }
    ],
    "prosecution_arguments": string[],
    "defense_arguments": string[],
    "court_reasoning": string|null,
    "decision": "granted"|"requalified"|"rejected"|"partially_granted"|"unrelated"|null,
    "excluded_evidence": string[],
    "retained_evidence": string[]
  },
  "legal_references": {
    "zkp_articles": string[],
    "constitution_articles": string[],
    "echr_articles": string[],
    "cited_precedents": string[]
  },
  "procedural_context": {
    "stage": "optužno vijeće"|"rasprava"|"žalba"|null,
    "lower_court_decision": string|null,
    "appellant": "okrivljenik"|"državni odvjetnik"|null
  },
  "key_quotes": string[],
  "practical_implications": string|null
}

Content guidance:
- summary: 4–5 sentences.
- key_quotes: 1–3 short quotations/sentences copied from the decision when possible.
- zkp_articles/constitution_articles/echr_articles: list only what is explicitly cited (e.g., "čl. 10. st. 2. t. 2. ZKP").';

    public function handle(): int
    {
        // Check available PDF-capable keys from rotator
        $status = $this->rotator->getStatus();
        $availableKeys = collect($status)->filter(fn($s) => $s['available'])->count();

        // For PDF processing, we need PDF-capable keys specifically
        $pdfCapableKey = $this->rotator->getAvailableKey('pdf');

        $this->info("Available API keys: {$availableKeys}");

        if (!$this->option('dry-run')) {
            if ($availableKeys === 0) {
                $this->error('No API keys available. Add keys with: php artisan apikey:manage add');
                return Command::FAILURE;
            }

            if (!$pdfCapableKey) {
                $this->error('No PDF-capable keys available. Ensure keys have supports_pdf=true.');
                return Command::FAILURE;
            }

            $this->info("PDF-capable key ready: {$pdfCapableKey->name} ({$pdfCapableKey->provider})");
        }

        $inputFile = $this->option('input');
        $outputDir = $this->option('output');
        $this->pdfCacheDir = "{$outputDir}/pdf_cache";

        // Create directories
        foreach ([$outputDir, $this->pdfCacheDir] as $dir) {
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }

        // Read URLs
        $urls = $this->loadUrls($inputFile);

        if ($urls->isEmpty()) {
            $this->warn('No valid URLs found in input file');
            return Command::SUCCESS;
        }

        // Apply limit if specified
        if ($limit = $this->option('limit')) {
            $urls = $urls->take((int) $limit);
        }

        $this->stats['total'] = $urls->count();
        $this->info("Processing {$this->stats['total']} documents");
        $this->newLine();

        // Phase 1: Download all PDFs
        $this->info('Phase 1: Downloading PDFs...');
        $downloadedDocs = $this->downloadAllPdfs($urls);

        if ($this->option('dry-run')) {
            $this->printSummary();
            return Command::SUCCESS;
        }

        // Phase 2: Analyze documents via API (using rotator for smart key rotation)
        $this->newLine();
        $this->info('Phase 2: Analyzing documents via API with smart rotation...');
        $results = $this->analyzeAllDocuments($downloadedDocs, $outputDir);

        // Phase 3: Aggregate results
        $this->newLine();
        $this->info('Phase 3: Aggregating results...');
        $this->aggregateResults($results, $outputDir);

        $this->printSummary();

        return $this->stats['failed_analysis'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function loadUrls(string $inputFile): Collection
    {
        if (!File::exists($inputFile)) {
            $this->error("Input file not found: {$inputFile}");
            return collect();
        }

        return collect(File::lines($inputFile))
            ->map(fn($line) => trim($line))
            ->filter(fn($line) => !empty($line) && str_starts_with($line, 'http'))
            ->unique()
            ->values();
    }

    private function downloadAllPdfs(Collection $urls): Collection
    {
        $concurrency = (int) $this->option('download-concurrency');
        $skipDownload = $this->option('skip-download');

        $documents = collect();
        $progressBar = $this->output->createProgressBar($urls->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->setMessage('Downloading...');
        $progressBar->start();

        $urls->chunk($concurrency)->each(function (Collection $batch) use ($skipDownload, $progressBar, &$documents) {
            if ($skipDownload) {
                // Use cached files only
                foreach ($batch as $url) {
                    $docId = $this->extractDocumentId($url);
                    $cachedPath = "{$this->pdfCacheDir}/{$docId}.pdf";

                    if (File::exists($cachedPath)) {
                        $documents->push([
                            'id' => $docId,
                            'url' => $url,
                            'path' => $cachedPath,
                            'cached' => true,
                        ]);
                        $this->stats['cached']++;
                    } else {
                        $this->stats['failed_download']++;
                    }
                    $progressBar->advance();
                }
            } else {
                // Download in parallel using HTTP Pool
                $responses = Http::pool(function (Pool $pool) use ($batch) {
                    foreach ($batch as $url) {
                        $docId = $this->extractDocumentId($url);
                        $pool->as($docId)
                            ->timeout(60)
                            ->retry(3, 1000, function ($exception, $request) {
                                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
                            })
                            ->withHeaders([
                                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
                                'Accept' => 'application/pdf',
                            ])
                            ->get($url);
                    }
                });

                foreach ($batch as $url) {
                    $docId = $this->extractDocumentId($url);
                    $cachedPath = "{$this->pdfCacheDir}/{$docId}.pdf";

                    // Check cache first
                    if (File::exists($cachedPath)) {
                        $documents->push([
                            'id' => $docId,
                            'url' => $url,
                            'path' => $cachedPath,
                            'cached' => true,
                        ]);
                        $this->stats['cached']++;
                        $progressBar->advance();
                        continue;
                    }

                    $response = $responses[$docId] ?? null;

                    if ($response && $response->successful()) {
                        $body = $response->body();

                        if (str_starts_with($body, '%PDF')) {
                            File::put($cachedPath, $body);
                            $documents->push([
                                'id' => $docId,
                                'url' => $url,
                                'path' => $cachedPath,
                                'cached' => false,
                            ]);
                            $this->stats['downloaded']++;
                        } else {
                            $progressBar->setMessage("Invalid PDF: {$docId}");
                            $this->stats['failed_download']++;
                        }
                    } else {
                        $progressBar->setMessage("Failed: {$docId}");
                        $this->stats['failed_download']++;
                    }

                    $progressBar->advance();
                }
            }

            // Small delay between batches to avoid rate limiting
            usleep(500000); // 0.5 seconds
        });

        $progressBar->finish();
        $this->newLine();

        return $documents;
    }

    private function analyzeAllDocuments(Collection $documents, string $outputDir): array
    {
        $results = [];
        $batchId = 'batch-' . now()->format('Ymd-His');

        $progressBar = $this->output->createProgressBar($documents->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->setMessage('Analyzing...');
        $progressBar->start();

        // Process sequentially with smart key rotation
        foreach ($documents as $doc) {
            $outputFile = "{$outputDir}/{$doc['id']}.json";

            // Check if already analyzed
            if (File::exists($outputFile)) {
                $result = json_decode(File::get($outputFile), true);
                if ($result && !empty($result['analysis'])) {
                    $results[] = $result;
                    $progressBar->setMessage("Cached: {$doc['id']}");
                    $progressBar->advance();
                    continue;
                }
            }

            $progressBar->setMessage("Analyzing: {$doc['id']}");

            $result = $this->analyzeDocumentWithRotator($doc, $batchId);

            if ($result !== null) {
                File::put($outputFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $results[] = $result;
                $this->stats['analyzed']++;
                $this->stats['rotator_calls']++;
            } else {
                $this->stats['failed_analysis']++;
                $this->stats['rotator_failures']++;
            }

            $progressBar->advance();

            // Minimal delay - the rotator handles rate limiting
            usleep(500000); // 0.5 seconds
        }

        $progressBar->finish();
        $this->newLine();

        return $results;
    }

    /**
     * Analyze a document using the ApiKeyRotatorService
     */
    private function analyzeDocumentWithRotator(array $doc, string $batchId): ?array
    {
        $result = $this->rotator->analyzeDocument(
            pdfPath: $doc['path'],
            systemPrompt: $this->systemPrompt,
            userPrompt: $this->analysisPrompt,
            taskType: 'pdf',
            documentId: $doc['id'],
            batchId: $batchId
        );

        if ($result['success']) {
            // Extract content - may already be parsed or may be raw
            $content = $result['data']['content'] ?? $result['data'];

            // If content is a string, try to parse it as JSON
            if (is_string($content)) {
                $analysis = $this->extractJsonFromText($content);
            } else {
                $analysis = $content;
            }

            return [
                'document_id' => $doc['id'],
                'source_url' => $doc['url'],
                'processed_at' => Carbon::now()->toIso8601String(),
                'api_provider' => $result['provider'] ?? 'unknown',
                'model' => $result['model'] ?? 'unknown',
                'analysis' => $analysis,
                'usage' => $result['data']['usage'] ?? null,
            ];
        }

        $this->warn("   Analysis failed: " . ($result['error'] ?? 'Unknown error'));
        return null;
    }

    /**
     * Compute a conservative max_output_tokens value so we don't exceed the model/provider context window.
     *
     * Some providers will default to a large max_tokens when max_output_tokens=0, which can overflow
     * the remaining context once the PDF text is included.
     */
    private function computeSafeMaxOutputTokens(string $base64Pdf): int
    {
        // We don't know the exact prompt-tokenization here. Use a conservative heuristic.
        // Base64 is ~4 chars per 3 bytes; tokenization varies, so we intentionally overestimate.
        $approxInputTokens = (int) ceil(strlen($base64Pdf) / 4);

        // Conservative context limit for this model/provider.
        // (The provider error cited 163840 tokens for the selected model.)
        $contextLimit = 163840;

        // Reserve space for system+user instructions, tool wrappers, and any provider overhead.
        $reservedForNonPdf = 8000;

        // Ensure at least some output budget, but never exceed remaining context.
        $remaining = $contextLimit - $reservedForNonPdf - $approxInputTokens;
        if ($remaining <= 0) {
            // If the PDF is huge, request a minimal completion rather than error.
            return 256;
        }

        // Cap the completion to keep responses reasonably sized and JSON-friendly.
        return max(256, min(4096, $remaining));
    }

    private function analyzeDocument(array $doc, bool $preferGemini = false, bool $preferMistral = true): ?array
    {
        // Mistral as primary (default)
        if ($preferMistral) {
            if ($this->mistralApiKey) {
                $result = $this->analyzeWithMistral($doc);
                if ($result !== null) {
                    return $result;
                }
                $this->warn("   Mistral failed, trying Gemini...");
            }

            // Try Gemini as second option
            if ($this->geminiApiKey) {
                $result = $this->analyzeWithGemini($doc);
                if ($result !== null) {
                    return $result;
                }
                $this->warn("   Gemini failed, trying OpenRouter...");
            }

            // If both fail and we have OpenRouter, try that
            if ($this->apiKey) {
                return $this->analyzeWithOpenRouter($doc);
            }
            return null;
        }

        // Gemini as primary
        if ($preferGemini) {
            if ($this->geminiApiKey) {
                $result = $this->analyzeWithGemini($doc);
                if ($result !== null) {
                    return $result;
                }
                $this->warn("   Gemini failed, trying Mistral...");
            }

            // Try Mistral as second option
            if ($this->mistralApiKey) {
                $result = $this->analyzeWithMistral($doc);
                if ($result !== null) {
                    return $result;
                }
                $this->warn("   Mistral failed, trying OpenRouter...");
            }

            // If both fail and we have OpenRouter, try that
            if ($this->apiKey) {
                return $this->analyzeWithOpenRouter($doc);
            }
            return null;
        }

        // OpenRouter as primary, fallback to Gemini then Mistral on rate limit
        return $this->analyzeWithOpenRouter($doc);
    }

    /**
     * Analyze document using OpenRouter API
     */
    private function analyzeWithOpenRouter(array $doc): ?array
    {
        $maxRetries = (int) $this->option('max-retries');
        $retryDelay = (int) $this->option('retry-delay');

        $pdfData = File::get($doc['path']);
        $base64Pdf = base64_encode($pdfData);

        $requestBody = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt,
                ],
                [
                    'role' => 'user',
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->analysisPrompt,
                        ],
                        [
                            'type' => 'input_file',
                            'file_data' => "data:application/pdf;base64,{$base64Pdf}",
                            'filename' => "{$doc['id']}.pdf",
                        ],
                    ],
                ],
            ],
            'plugins' => [
                [
                    'id' => 'file-parser',
                    'pdf' => ['engine' => 'pdf-text'],
                ],
            ],
            'stream' => false,
            'max_output_tokens' => $this->computeSafeMaxOutputTokens($base64Pdf),
        ];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(300)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => "Bearer {$this->apiKey}",
                        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
                        'X-Title' => 'Court Decision Analyzer',
                    ])
                    ->post($this->apiUrl, $requestBody);

                if ($response->successful()) {
                    $data = $response->json();

                    if (!empty($data['error'])) {
                        $this->warn("   API error: " . json_encode($data['error']));
                        continue;
                    }

                    $this->stats['openrouter_calls']++;
                    $result = $this->parseOpenRouterResponse($data, $doc);
                    if ($result) {
                        $result['api_provider'] = 'openrouter';
                    }
                    return $result;
                }

                // Handle rate limiting - try Gemini then Mistral fallback
                if ($response->status() === 429) {
                    $res = $response->body();
                    $this->warn("   OpenRouter rate limited: {$res}");

                    // Try Gemini as fallback if available
                    if ($this->geminiApiKey) {
                        $this->info("   Falling back to Gemini API...");
                        $this->stats['gemini_fallbacks']++;
                        $result = $this->analyzeWithGemini($doc);
                        if ($result !== null) {
                            return $result;
                        }
                        $this->warn("   Gemini fallback failed.");
                    }

                    // Try Mistral as second fallback if available
                    if ($this->mistralApiKey) {
                        $this->info("   Falling back to Mistral API...");
                        $this->stats['mistral_fallbacks']++;
                        $result = $this->analyzeWithMistral($doc);
                        if ($result !== null) {
                            return $result;
                        }
                        $this->warn("   Mistral fallback also failed.");
                    }

                    // If no fallbacks or all failed, wait and retry OpenRouter
                    $retryAfter = $response->header('Retry-After');
                    if ($retryAfter) {
                        $retryAfter = (int) $retryAfter;
                    } else {
                        $retryAfter = $retryDelay * pow(2, $attempt);
                    }
                    // Ensure minimum wait of 5 seconds
                    $retryAfter = max(5, $retryAfter);
                    $this->warn("   Waiting {$retryAfter}s before retry...");
                    sleep($retryAfter);
                    continue;
                }

                // Handle server errors
                if ($response->status() >= 500) {
                    $delay = $retryDelay * pow(2, $attempt - 1);
                    $this->warn("   Server error {$response->status()}, retrying in {$delay}s...");
                    sleep($delay);
                    continue;
                }

                $this->warn("   API error: HTTP {$response->status()}.  Response: " . $response->body());

            } catch (Throwable $e) {
                $delay = $retryDelay * pow(2, $attempt - 1);
                $this->warn("   Exception: {$e->getMessage()}, retrying in {$delay}s...");
                sleep($delay);
            }
        }

        return null;
    }

    /**
     * Analyze document using Google Gemini API directly
     */
    private function analyzeWithGemini(array $doc): ?array
    {
        $maxRetries = (int) $this->option('max-retries');
        $retryDelay = (int) $this->option('retry-delay');

        $pdfData = File::get($doc['path']);
        $base64Pdf = base64_encode($pdfData);

        // Gemini API request format
        $requestBody = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => 'application/pdf',
                                'data' => $base64Pdf,
                            ],
                        ],
                        [
                            'text' => $this->analysisPrompt,
                        ],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    [
                        'text' => $this->systemPrompt,
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'topP' => 0.95,
                'maxOutputTokens' => 65536,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = "{$this->geminiApiUrl}/{$this->geminiModel}:generateContent?key={$this->geminiApiKey}";

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(300)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, $requestBody);

                if ($response->successful()) {
                    $data = $response->json();

                    // Check for API-level errors
                    if (!empty($data['error'])) {
                        $this->warn("   Gemini API error: " . json_encode($data['error']));
                        continue;
                    }

                    $this->stats['gemini_calls']++;
                    $result = $this->parseGeminiResponse($data, $doc);
                    if ($result) {
                        $result['api_provider'] = 'gemini';
                    }
                    return $result;
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? ($retryDelay * pow(2, $attempt)));
                    $this->warn("   Gemini rate limited, waiting {$retryAfter}s...");
                    sleep($retryAfter);
                    continue;
                }

                // Handle quota exceeded (403 with specific error)
                if ($response->status() === 403) {
                    $body = $response->json();
                    if (str_contains(json_encode($body), 'RESOURCE_EXHAUSTED')) {
                        $this->warn("   Gemini quota exhausted.");
                        return null; // Don't retry, quota is done
                    }
                }

                // Handle server errors
                if ($response->status() >= 500) {
                    $delay = $retryDelay * pow(2, $attempt - 1);
                    $this->warn("   Gemini server error {$response->status()}, retrying in {$delay}s...");
                    sleep($delay);
                    continue;
                }

                $this->warn("   Gemini API error: HTTP {$response->status()}. Response: " . $response->body());

            } catch (Throwable $e) {
                $delay = $retryDelay * pow(2, $attempt - 1);
                $this->warn("   Gemini exception: {$e->getMessage()}, retrying in {$delay}s...");
                sleep($delay);
            }
        }

        return null;
    }

    /**
     * Parse Gemini API response format
     */
    private function parseGeminiResponse(array $response, array $doc): ?array
    {
        $outputText = null;

        // Check for blocked content or safety issues
        if (!empty($response['promptFeedback']['blockReason'])) {
            $this->warn("   Gemini blocked: " . $response['promptFeedback']['blockReason']);
            return null;
        }

        // Extract text from Gemini response structure
        // Response format: { candidates: [{ content: { parts: [{ text: "..." }] } }] }
        $candidates = $response['candidates'] ?? [];

        if (empty($candidates)) {
            $this->warn("   No candidates in Gemini response");
            $this->warn("   Response keys: " . implode(', ', array_keys($response)));
            if (isset($response['error'])) {
                $this->warn("   Error: " . json_encode($response['error']));
            }
            return null;
        }

        // Check finish reason
        $finishReason = $candidates[0]['finishReason'] ?? null;
        if ($finishReason && !in_array($finishReason, ['STOP', 'MAX_TOKENS'])) {
            $this->warn("   Gemini finish reason: {$finishReason}");
        }

        $content = $candidates[0]['content'] ?? [];
        $parts = $content['parts'] ?? [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $outputText = $part['text'];
                break;
            }
        }

        if (!$outputText) {
            $this->warn("   No text content in Gemini response");
            $this->warn("   Parts: " . json_encode($parts));
            return null;
        }

        $analysis = $this->extractJsonFromText($outputText);

        if (!$analysis) {
            $this->warn("   Failed to parse JSON from Gemini response");
            $this->warn("   Raw text (first 500 chars): " . substr($outputText, 0, 500));
            $jsonError = json_last_error_msg();
            $this->warn("   JSON error: {$jsonError}");
            return null;
        }

        return [
            'document_id' => $doc['id'],
            'source_url' => $doc['url'],
            'processed_at' => Carbon::now()->toIso8601String(),
            'reasoning' => null, // Gemini doesn't provide reasoning in standard response
            'analysis' => $analysis,
            'annotations' => [],
            'usage' => $response['usageMetadata'] ?? null,
        ];
    }

    /**
     * Analyze document using Mistral API
     */
    private function analyzeWithMistral(array $doc): ?array
    {
        $maxRetries = (int) $this->option('max-retries');
        $retryDelay = (int) $this->option('retry-delay');

        $pdfData = File::get($doc['path']);
        $base64Pdf = base64_encode($pdfData);

        // Mistral API request format using document_url with base64 data URI
        $requestBody = [
            'model' => $this->mistralModel,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'document_url',
                            'document_url' => "data:application/pdf;base64,{$base64Pdf}",
                        ],
                        [
                            'type' => 'text',
                            'text' => $this->analysisPrompt,
                        ],
                    ],
                ],
            ],
            'temperature' => 0.1,
            'top_p' => 0.95,
            'response_format' => [
                'type' => 'json_object',
            ],
        ];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(300)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => "Bearer {$this->mistralApiKey}",
                    ])
                    ->post($this->mistralApiUrl, $requestBody);

                if ($response->successful()) {
                    $data = $response->json();

                    // Check for API-level errors
                    if (!empty($data['error'])) {
                        $this->warn("   Mistral API error: " . json_encode($data['error']));
                        continue;
                    }

                    $this->stats['mistral_calls']++;
                    $result = $this->parseMistralResponse($data, $doc);
                    if ($result) {
                        $result['api_provider'] = 'mistral';
                    }
                    return $result;
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? ($retryDelay * pow(2, $attempt)));
                    $this->warn("   Mistral rate limited, waiting {$retryAfter}s...");
                    sleep($retryAfter);
                    continue;
                }

                // Handle server errors
                if ($response->status() >= 500) {
                    $delay = $retryDelay * pow(2, $attempt - 1);
                    $this->warn("   Mistral server error {$response->status()}, retrying in {$delay}s...");
                    sleep($delay);
                    continue;
                }

                $this->warn("   Mistral API error: HTTP {$response->status()}. Response: " . $response->body());

            } catch (Throwable $e) {
                $delay = $retryDelay * pow(2, $attempt - 1);
                $this->warn("   Mistral exception: {$e->getMessage()}, retrying in {$delay}s...");
                sleep($delay);
            }
        }

        return null;
    }

    /**
     * Parse Mistral API response format
     */
    private function parseMistralResponse(array $response, array $doc): ?array
    {
        $outputText = null;

        // Extract text from Mistral response structure
        // Response format: { choices: [{ message: { content: "..." } }] }
        $choices = $response['choices'] ?? [];

        if (empty($choices)) {
            $this->warn("   No choices in Mistral response");
            return null;
        }

        $message = $choices[0]['message'] ?? [];
        $outputText = $message['content'] ?? null;

        // Check finish reason
        $finishReason = $choices[0]['finish_reason'] ?? null;
        if ($finishReason && !in_array($finishReason, ['stop', 'length'])) {
            $this->warn("   Mistral finish reason: {$finishReason}");
        }

        if (!$outputText) {
            $this->warn("   No content in Mistral response");
            return null;
        }

        $analysis = $this->extractJsonFromText($outputText);

        if (!$analysis) {
            $this->warn("   Failed to parse JSON from Mistral response");
            $this->warn("   Raw text (first 500 chars): " . substr($outputText, 0, 500));
            return null;
        }

        return [
            'document_id' => $doc['id'],
            'source_url' => $doc['url'],
            'processed_at' => Carbon::now()->toIso8601String(),
            'reasoning' => null,
            'analysis' => $analysis,
            'annotations' => [],
            'usage' => $response['usage'] ?? null,
        ];
    }

    private function extractDocumentId(string $url): string
    {
        if (preg_match('/id=([a-f0-9-]+)/i', $url, $matches)) {
            return $matches[1];
        }
        return md5($url);
    }

    private function parseOpenRouterResponse(array $response, array $doc): ?array
    {
        $reasoning = null;
        $outputText = null;
        $annotations = [];

        foreach ($response['output'] ?? [] as $output) {
            if ($output['type'] === 'reasoning') {
                foreach ($output['content'] ?? [] as $content) {
                    if ($content['type'] === 'reasoning_text') {
                        $reasoning = $content['text'];
                    }
                }
            }

            if (($output['type'] ?? null) === 'message' && ($output['role'] ?? null) === 'assistant') {
                foreach ($output['content'] ?? [] as $content) {
                    if ($content['type'] === 'output_text') {
                        $outputText = $content['text'];
                    }
                    if (!empty($content['annotations'])) {
                        $annotations = $content['annotations'];
                    }
                }
            }
        }

        if (!$outputText) {
            return null;
        }

        $analysis = $this->extractJsonFromText($outputText);

        return [
            'document_id' => $doc['id'],
            'source_url' => $doc['url'],
            'processed_at' => Carbon::now()->toIso8601String(),
            'reasoning' => $reasoning,
            'analysis' => $analysis,
            'annotations' => $annotations,
            'usage' => $response['usage'] ?? null,
        ];
    }

    private function extractJsonFromText(string $text): ?array
    {
        // Remove markdown code fences
        $text = preg_replace('/```json?\s*\n?/i', '', $text);
        $text = preg_replace('/\n?```/', '', $text);
        $text = trim($text);

        // First try: direct parse if it's already clean JSON
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Second try: find JSON object in text
        if (preg_match('/\{[\s\S]*\}/m', $text, $matches)) {
            $jsonStr = $matches[0];

            // Fix common JSON issues:
            // 1. Remove trailing commas before } or ]
            $jsonStr = preg_replace('/,(\s*[}\]])/', '$1', $jsonStr);

            $decoded = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            // 2. If control character error, sanitize the string values
            if (json_last_error() === JSON_ERROR_CTRL_CHAR) {
                $jsonStr = $this->sanitizeJsonControlChars($jsonStr);
                $decoded = json_decode($jsonStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }

            // Store the error for debugging
            $this->lastJsonError = json_last_error_msg();
        }

        return null;
    }

    /**
     * Sanitize control characters inside JSON string values
     */
    private function sanitizeJsonControlChars(string $json): string
    {
        // This regex-based approach processes the JSON and escapes control characters
        // inside string values while preserving the JSON structure

        $result = '';
        $inString = false;
        $escape = false;
        $len = strlen($json);

        for ($i = 0; $i < $len; $i++) {
            $char = $json[$i];
            $ord = ord($char);

            if ($escape) {
                $result .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\' && $inString) {
                $escape = true;
                $result .= $char;
                continue;
            }

            if ($char === '"') {
                $inString = !$inString;
                $result .= $char;
                continue;
            }

            // If we're inside a string and hit a control character, escape it
            if ($inString && $ord < 32) {
                switch ($ord) {
                    case 9:  // tab
                        $result .= '\\t';
                        break;
                    case 10: // newline
                        $result .= '\\n';
                        break;
                    case 13: // carriage return
                        $result .= '\\r';
                        break;
                    default:
                        // Other control chars: use unicode escape
                        $result .= sprintf('\\u%04x', $ord);
                }
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    private function aggregateResults(array $results, string $outputDir): void
    {
        if (empty($results)) {
            return;
        }

        $timestamp = Carbon::now()->format('Y-m-d_His');

        // Full results
        $fullPath = "{$outputDir}/batch_results_{$timestamp}.json";
        File::put($fullPath, json_encode([
            'generated_at' => Carbon::now()->toIso8601String(),
            'total' => count($results),
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Summary without reasoning
        $summaryPath = "{$outputDir}/batch_summary_{$timestamp}.json";
        $summary = collect($results)->map(fn($r) => [
            'document_id' => $r['document_id'],
            'source_url' => $r['source_url'],
            'api_provider' => $r['api_provider'] ?? 'unknown',
            'case_code' => $r['analysis']['case_code'] ?? null,
            'court' => $r['analysis']['court'] ?? null,
            'decision' => $r['analysis']['evidence_exclusion']['decision'] ?? null,
            'summary' => $r['analysis']['summary'] ?? null,
        ])->toArray();

        File::put($summaryPath, json_encode([
            'generated_at' => Carbon::now()->toIso8601String(),
            'total' => count($summary),
            'decisions' => $summary,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Statistics
        $this->generateStatistics($results, $outputDir, $timestamp);

        $this->info("Results saved to: {$outputDir}/");
    }

    private function generateStatistics(array $results, string $outputDir, string $timestamp): void
    {
        $stats = [
            'total' => count($results),
            'by_outcome' => [],
            'by_court' => [],
            'by_evidence_type' => [],
            'by_api_provider' => [],
            'zkp_articles' => [],
        ];

        foreach ($results as $result) {
            $analysis = $result['analysis'] ?? [];

            // Outcome
            $outcome = $analysis['evidence_exclusion']['decision'] ?? 'unknown';
            $stats['by_outcome'][$outcome] = ($stats['by_outcome'][$outcome] ?? 0) + 1;

            // Court
            $court = $analysis['court'] ?? 'unknown';
            $stats['by_court'][$court] = ($stats['by_court'][$court] ?? 0) + 1;

            // API Provider
            $provider = $result['api_provider'] ?? 'unknown';
            $stats['by_api_provider'][$provider] = ($stats['by_api_provider'][$provider] ?? 0) + 1;

            // Evidence types
            foreach ($analysis['evidence_exclusion']['proposed_exclusions'] ?? [] as $ex) {
                $type = $ex['evidence_type'] ?? 'unknown';
                $stats['by_evidence_type'][$type] = ($stats['by_evidence_type'][$type] ?? 0) + 1;
            }

            // ZKP articles
            foreach ($analysis['legal_references']['zkp_articles'] ?? [] as $art) {
                $stats['zkp_articles'][$art] = ($stats['zkp_articles'][$art] ?? 0) + 1;
            }
        }

        arsort($stats['by_court']);
        arsort($stats['by_evidence_type']);
        arsort($stats['zkp_articles']);

        File::put(
            "{$outputDir}/statistics_{$timestamp}.json",
            json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('╔═══════════════════════════════════════╗');
        $this->info('║         BATCH ANALYSIS COMPLETE       ║');
        $this->info('╚═══════════════════════════════════════╝');

        $this->table(
            ['Phase', 'Status', 'Count'],
            [
                ['Total documents', '', $this->stats['total']],
                ['', '', ''],
                ['Downloads', 'New', $this->stats['downloaded']],
                ['', 'Cached', $this->stats['cached']],
                ['', 'Failed', $this->stats['failed_download']],
                ['', '', ''],
                ['Analysis', 'Success', $this->stats['analyzed']],
                ['', 'Failed', $this->stats['failed_analysis']],
                ['', '', ''],
                ['API Rotator', 'Calls', $this->stats['rotator_calls']],
                ['', 'Failures', $this->stats['rotator_failures']],
            ]
        );

        $successRate = $this->stats['total'] > 0
            ? round($this->stats['analyzed'] / $this->stats['total'] * 100, 1)
            : 0;

        $this->info("Overall success rate: {$successRate}%");
    }
}
