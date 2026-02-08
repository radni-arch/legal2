<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class AnalyzeCourtDecisions extends Command
{
    protected $signature = 'court:analyze 
                            {--input=court_decision_links.txt : Input file with PDF URLs}
                            {--output=court_analysis_results : Output directory for results}
                            {--batch-size=5 : Number of concurrent requests}
                            {--max-retries=3 : Maximum retry attempts per request}
                            {--retry-delay=5 : Initial delay between retries in seconds}
                            {--dry-run : Only download PDFs without API calls}';

    protected $description = 'Analyze Croatian court decisions for evidence exclusion patterns using OpenRouter API';

    private string $apiKey;
    private string $apiUrl = 'https://openrouter.ai/api/v1/responses';
    private string $model = 'tngtech/deepseek-r1t2-chimera:free';
    
    private array $stats = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
    ];

    private string $systemPrompt = 'You are DeepSeek R1T2 Chimera (free), a large language model from tngtech.

Formatting Rules:
- Use Markdown for lists, tables, and styling.
- Use ```code fences``` for all code blocks.
- Format file names, paths, and function names with `inline code` backticks.
- **For all mathematical expressions, you must use dollar-sign delimiters. Use $...$ for inline math and $$...$$ for block math. Do not use (...) or [...] delimiters.**
- Koristiš Hrvatski jezik po defaultu!';

    private string $analysisPrompt = 'Analyze the Croatian court decision.

This is an illegal evidence exclusion decision. Extract:

1. **Case identification:**
   - Case code (e.g., I Kž-123/2024, Kv-45/2023)
   - Court name
   - Decision date
   - Decision type (presuda/rješenje)

2. **Evidence exclusion details:**
   - What evidence was proposed for exclusion
   - Type of evidence (witness testimony, search records, surveillance data, etc.)
   - Legal basis cited by movant (usually čl. 10. ZKP)
   - Arguments for exclusion
   - Prosecution\'s counter-arguments
   - Court\'s legal reasoning
   - Final decision (granted/rejected/partial)

3. **Legal framework:**
   - ZKP articles cited
   - Constitutional provisions
   - ECHR articles if mentioned
   - Precedents cited

4. **Procedural context:**
   - What stage of proceedings
   - If appeal: what was lower court\'s decision

5. **Key quotes:** Extract 2-3 most important sentences

Return ONLY valid JSON matching this structure (no markdown code fences):

{
  "case_code": "I Kž 123/2024",
  "court": "Visoki kazneni sud Republike Hrvatske",
  "decision_date": "2024-05-15",
  "decision_type": "rješenje",
  "summary": "Kratki sažetak slučaja, 4-5 rečenica.",
  "evidence_exclusion": {
    "proposed_exclusions": [
      {
        "evidence_type": "zapisnik o pretresu",
        "description": "Zapisnik o pretrazi dome",
        "legal_basis_cited": "čl. 10. st. 2. toč. 2. ZKP",
        "argument": "Pretraga je obavljena bez važečeg naloga"
      }
    ],
    "prosecution_arguments": ["Sažetak protuargumenata tužiteljstva"],
    "defense_arguments": ["Sažetak argumenata obrane"],
    "court_reasoning": "Court\'s legal analysis and reasoning",
    "decision": "granted|rejected|partially_granted",
    "excluded_evidence": ["List of evidence actually excluded"],
    "retained_evidence": ["List of evidence retained as lawful"]
  },
  "legal_references": {
    "zkp_articles": ["čl. 10.", "čl. 86.", "čl. 332."],
    "constitution_articles": ["čl. 34."],
    "echr_articles": ["čl. 6.", "čl. 8."],
    "cited_precedents": ["VSRH I Kž 100/2020"]
  },
  "procedural_context": {
    "stage": "optužno vijeće|rasprava|žalba",
    "lower_court_decision": "Description if appeal",
    "appellant": "okrivljenik|državni odvjetnik"
  },
  "key_quotes": ["Important quotes from the decision"],
  "practical_implications": "Brief note on precedent value"
}';

    public function handle(): int
    {
        $this->apiKey = config('services.openrouter.api_key') ?: env('OPENROUTER_API_KEY');
        
        if (!$this->apiKey) {
            $this->error('OpenRouter API key not configured. Set OPENROUTER_API_KEY in .env');
            return Command::FAILURE;
        }

        $inputFile = $this->option('input');
        $outputDir = $this->option('output');
        $batchSize = (int) $this->option('batch-size');
        $isDryRun = $this->option('dry-run');

        // Validate input file
        if (!File::exists($inputFile)) {
            $this->error("Input file not found: {$inputFile}");
            return Command::FAILURE;
        }

        // Create output directory
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // Read URLs
        $urls = collect(File::lines($inputFile))
            ->map(fn($line) => trim($line))
            ->filter(fn($line) => !empty($line) && str_starts_with($line, 'http'))
            ->values();

        $this->stats['total'] = $urls->count();
        $this->info("Found {$this->stats['total']} URLs to process");

        if ($this->stats['total'] === 0) {
            $this->warn('No valid URLs found in input file');
            return Command::SUCCESS;
        }

        // Process in batches
        $progressBar = $this->output->createProgressBar($this->stats['total']);
        $progressBar->start();

        $allResults = [];

        $urls->chunk($batchSize)->each(function (Collection $batch) use ($outputDir, $isDryRun, $progressBar, &$allResults) {
            $batchResults = $this->processBatch($batch, $outputDir, $isDryRun);
            $allResults = array_merge($allResults, $batchResults);
            $progressBar->advance($batch->count());
        });

        $progressBar->finish();
        $this->newLine(2);

        // Aggregate and save final results
        $this->aggregateResults($allResults, $outputDir);

        // Print summary
        $this->printSummary();

        return $this->stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function processBatch(Collection $urls, string $outputDir, bool $isDryRun): array
    {
        $results = [];

        foreach ($urls as $url) {
            $result = $this->processUrl($url, $outputDir, $isDryRun);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    private function processUrl(string $url, string $outputDir, bool $isDryRun): ?array
    {
        $documentId = $this->extractDocumentId($url);
        $outputFile = "{$outputDir}/{$documentId}.json";

        // Skip if already processed
        if (File::exists($outputFile)) {
            $this->stats['skipped']++;
            $this->line(" Skipping {$documentId} (already processed)");
            return json_decode(File::get($outputFile), true);
        }

        // Download PDF
        $pdfData = $this->downloadPdf($url);
        if ($pdfData === null) {
            $this->stats['failed']++;
            return null;
        }

        if ($isDryRun) {
            $this->info(" Downloaded {$documentId} (" . strlen($pdfData) . " bytes)");
            return null;
        }

        // Analyze via API
        $result = $this->analyzeDocument($documentId, $pdfData, $url);
        
        if ($result !== null) {
            // Save individual result
            File::put($outputFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->stats['success']++;
        } else {
            $this->stats['failed']++;
        }

        return $result;
    }

    private function extractDocumentId(string $url): string
    {
        if (preg_match('/id=([a-f0-9-]+)/i', $url, $matches)) {
            return $matches[1];
        }
        return md5($url);
    }

    private function downloadPdf(string $url): ?string
    {
        $maxRetries = (int) $this->option('max-retries');
        $retryDelay = (int) $this->option('retry-delay');

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(60)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36',
                        'Accept' => 'application/pdf',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $body = $response->body();
                    
                    // Verify it's a PDF
                    if (str_starts_with($body, '%PDF')) {
                        return $body;
                    }
                    
                    $this->warn(" Response is not a valid PDF");
                }

                $this->warn(" Download failed (attempt {$attempt}/{$maxRetries}): HTTP {$response->status()}");

            } catch (\Exception $e) {
                $this->warn(" Download error (attempt {$attempt}/{$maxRetries}): {$e->getMessage()}");
            }

            if ($attempt < $maxRetries) {
                $delay = $retryDelay * pow(2, $attempt - 1); // Exponential backoff
                sleep($delay);
            }
        }

        $this->error(" Failed to download PDF after {$maxRetries} attempts");
        return null;
    }

    private function analyzeDocument(string $documentId, string $pdfData, string $sourceUrl): ?array
    {
        $maxRetries = (int) $this->option('max-retries');
        $retryDelay = (int) $this->option('retry-delay');

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
                            'filename' => "{$documentId}.pdf",
                        ],
                    ],
                ],
            ],
            'plugins' => [
                [
                    'id' => 'file-parser',
                    'pdf' => [
                        'engine' => 'pdf-text',
                    ],
                ],
            ],
            'stream' => false,
            'reasoning' => [
                'enabled' => true,
            ],
            'max_output_tokens' => 0,
        ];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(300)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => "Bearer {$this->apiKey}",
                        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36',
                        'X-Title' => 'Court Decision Analyzer',
                    ])
                    ->post($this->apiUrl, $requestBody);

                if ($response->successful()) {
                    $data = $response->json();
                    return $this->parseApiResponse($data, $documentId, $sourceUrl);
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After', $retryDelay * pow(2, $attempt));
                    $this->warn(" Rate limited, waiting {$retryAfter}s...");
                    sleep((int) $retryAfter);
                    continue;
                }

                $this->warn(" API error (attempt {$attempt}/{$maxRetries}): HTTP {$response->status()}");
                $this->line("   Response: " . substr($response->body(), 0, 200));

            } catch (\Exception $e) {
                $this->warn(" API error (attempt {$attempt}/{$maxRetries}): {$e->getMessage()}");
            }

            if ($attempt < $maxRetries) {
                $delay = $retryDelay * pow(2, $attempt - 1);
                sleep($delay);
            }
        }

        $this->error(" Failed to analyze {$documentId} after {$maxRetries} attempts");
        return null;
    }

    private function parseApiResponse(array $response, string $documentId, string $sourceUrl): ?array
    {
        if (!empty($response['error'])) {
            $this->error(" API returned error: " . json_encode($response['error']));
            return null;
        }

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

            if ($output['type'] === 'message' && $output['role'] === 'assistant') {
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
            $this->warn(" No output text in API response");
            return null;
        }

        // Parse JSON from output text
        $analysis = $this->extractJsonFromText($outputText);

        return [
            'document_id' => $documentId,
            'source_url' => $sourceUrl,
            'processed_at' => Carbon::now()->toIso8601String(),
            'reasoning' => $reasoning,
            'analysis' => $analysis,
            'annotations' => $annotations,
            'usage' => $response['usage'] ?? null,
            'raw_output' => $outputText,
        ];
    }

    private function extractJsonFromText(string $text): ?array
    {
        // Try to extract JSON from markdown code block
        if (preg_match('/```json?\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $jsonStr = trim($matches[1]);
        } else {
            // Try to find raw JSON object
            if (preg_match('/\{[\s\S]*\}/m', $text, $matches)) {
                $jsonStr = $matches[0];
            } else {
                $jsonStr = $text;
            }
        }

        $decoded = json_decode($jsonStr, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warn(" JSON parse error: " . json_last_error_msg());
            return null;
        }

        return $decoded;
    }

    private function aggregateResults(array $results, string $outputDir): void
    {
        if (empty($results)) {
            $this->warn('No results to aggregate');
            return;
        }

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

        // Full results with reasoning
        $fullResultsFile = "{$outputDir}/full_results_{$timestamp}.json";
        File::put($fullResultsFile, json_encode([
            'generated_at' => Carbon::now()->toIso8601String(),
            'total_documents' => count($results),
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Full results saved to: {$fullResultsFile}");

        // Analysis-only summary (without reasoning for easier review)
        $summaryResults = collect($results)->map(function ($result) {
            return [
                'document_id' => $result['document_id'],
                'source_url' => $result['source_url'],
                'analysis' => $result['analysis'],
            ];
        })->toArray();

        $summaryFile = "{$outputDir}/analysis_summary_{$timestamp}.json";
        File::put($summaryFile, json_encode([
            'generated_at' => Carbon::now()->toIso8601String(),
            'total_documents' => count($summaryResults),
            'results' => $summaryResults,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Analysis summary saved to: {$summaryFile}");

        // Generate statistics
        $this->generateStatistics($results, $outputDir, $timestamp);
    }

    private function generateStatistics(array $results, string $outputDir, string $timestamp): void
    {
        $stats = [
            'total_analyzed' => count($results),
            'decisions_by_outcome' => [
                'granted' => 0,
                'rejected' => 0,
                'partially_granted' => 0,
                'unknown' => 0,
            ],
            'courts' => [],
            'zkp_articles' => [],
            'echr_articles' => [],
            'evidence_types' => [],
        ];

        foreach ($results as $result) {
            $analysis = $result['analysis'] ?? [];

            // Decision outcome
            $decision = $analysis['evidence_exclusion']['decision'] ?? 'unknown';
            $stats['decisions_by_outcome'][$decision] = ($stats['decisions_by_outcome'][$decision] ?? 0) + 1;

            // Courts
            $court = $analysis['court'] ?? 'unknown';
            $stats['courts'][$court] = ($stats['courts'][$court] ?? 0) + 1;

            // ZKP articles
            foreach ($analysis['legal_references']['zkp_articles'] ?? [] as $article) {
                $stats['zkp_articles'][$article] = ($stats['zkp_articles'][$article] ?? 0) + 1;
            }

            // ECHR articles
            foreach ($analysis['legal_references']['echr_articles'] ?? [] as $article) {
                $stats['echr_articles'][$article] = ($stats['echr_articles'][$article] ?? 0) + 1;
            }

            // Evidence types
            foreach ($analysis['evidence_exclusion']['proposed_exclusions'] ?? [] as $exclusion) {
                $type = $exclusion['evidence_type'] ?? 'unknown';
                $stats['evidence_types'][$type] = ($stats['evidence_types'][$type] ?? 0) + 1;
            }
        }

        // Sort by frequency
        arsort($stats['courts']);
        arsort($stats['zkp_articles']);
        arsort($stats['echr_articles']);
        arsort($stats['evidence_types']);

        $statsFile = "{$outputDir}/statistics_{$timestamp}.json";
        File::put($statsFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Statistics saved to: {$statsFile}");
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('           ANALYSIS COMPLETE           ');
        $this->info('═══════════════════════════════════════');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total URLs', $this->stats['total']],
                ['Successfully analyzed', $this->stats['success']],
                ['Skipped (cached)', $this->stats['skipped']],
                ['Failed', $this->stats['failed']],
            ]
        );

        $successRate = $this->stats['total'] > 0 
            ? round(($this->stats['success'] + $this->stats['skipped']) / $this->stats['total'] * 100, 1)
            : 0;
        
        $this->info("Success rate: {$successRate}%");
    }
}
