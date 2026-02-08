<?php

namespace App\Services\AI;

use App\Contracts\AI\AnalysisServiceInterface;
use App\Contracts\AI\CacheServiceInterface;
use App\Contracts\AI\ChatServiceInterface;
use App\Exceptions\AnalysisException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OpenAI-powered legal text analysis service
 *
 * Provides specialized analysis capabilities for Croatian legal documents:
 * - Legal text analysis with structured output
 * - Court decision summarization
 * - Citation extraction (ZKP, Ustav RH, Kazneni zakon)
 * - Document classification
 * - Entity extraction
 */
class OpenAIAnalysisService implements AnalysisServiceInterface
{
    protected string $defaultModel;

    protected int $cacheTtl;

    public function __construct(
        protected ChatServiceInterface $chat,
        protected ?CacheServiceInterface $cache = null
    ) {
        $this->defaultModel = config('openai.models.chat', 'gpt-4o');
        $this->cacheTtl = config('openai.cache.ttl', 3600);
    }

    /**
     * Analyze legal text with structured output
     *
     * @param  string  $text  Legal text to analyze
     * @param  array  $options  Analysis options:
     *                          - cache: bool (default true) - Cache results
     *                          - model: string - Override default model
     *                          - temperature: float (default 0.3) - Response randomness
     *                          - topics: array - Specific topics to analyze
     * @return array Analysis results with structured data
     *
     * @throws \Throwable
     */
    public function analyzeLegalText(string $text, array $options = []): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('OpenAIAnalysisService: analyzeLegalText initiated', [
            'text_length' => strlen($text),
            'options_keys' => array_keys($options),
            'model' => $options['model'] ?? $this->defaultModel,
            'use_cache' => $options['cache'] ?? true,
            'user_id' => auth()->id(),
        ]);

        try {
            $cacheKey = $this->generateCacheKey('analyze_legal', [
                'text' => md5($text),
                'options' => $options,
            ]);

            $useCache = $options['cache'] ?? true;

            if ($useCache && $cached = Cache::get($cacheKey)) {
                Log::info('OpenAIAnalysisService: analyzeLegalText cache hit', [
                    'cache_key' => $cacheKey,
                ]);

                return $cached;
            }

            $model = $options['model'] ?? $this->defaultModel;
            $temperature = $options['temperature'] ?? 0.3;
            $topics = $options['topics'] ?? [];

            // Build analysis prompt
            $topicsStr = empty($topics)
                ? 'sve relevantne pravne teme'
                : implode(', ', $topics);

            $systemPrompt = <<<'SYSTEM'
Vi ste stručni pravni analitičar specijaliziran za hrvatsko pravo.
Analizirajte tekst i vratite strukturirane podatke u JSON formatu.

Obavezno uključite:
1. main_topic - glavna pravna tema
2. legal_issues - popis relevantnih pravnih pitanja
3. cited_laws - citirani zakoni s člancima
4. key_points - ključne točke analize
5. confidence - razina pouzdanosti analize (0-1)
6. summary - sažetak analize
SYSTEM;

            $userPrompt = <<<USER
Analizirajte sljedeći pravni tekst fokusirajući se na: {$topicsStr}

TEKST:
{$text}

Vratite analizu u JSON formatu.
USER;

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->chat->chat($messages, $model, [
                'temperature' => $temperature,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $analysis = json_decode($content, true);

            if (! is_array($analysis)) {
                throw new \RuntimeException('Failed to parse analysis response');
            }

            // Ensure required fields
            $result = array_merge([
                'main_topic' => null,
                'legal_issues' => [],
                'cited_laws' => [],
                'key_points' => [],
                'confidence' => 0.0,
                'summary' => '',
                'usage' => $response['usage'] ?? [],
            ], $analysis);

            if ($useCache) {
                Cache::put($cacheKey, $result, $this->cacheTtl);
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('OpenAIAnalysisService: analyzeLegalText completed', [
                'main_topic' => $result['main_topic'],
                'confidence' => $result['confidence'],
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'cache_hit' => false,
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (AnalysisException $e) {
            Log::error('OpenAIAnalysisService: analyzeLegalText failed with AnalysisException', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('OpenAIAnalysisService: analyzeLegalText failed with unexpected exception', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'text_length' => strlen($text),
            ]);

            throw new AnalysisException(
                'Legal text analysis failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Summarize text to specified length
     *
     * @param  string  $text  Text to summarize
     * @param  int  $maxLength  Maximum summary length in characters
     * @return string Summary
     *
     * @throws \Throwable
     */
    public function summarize(string $text, int $maxLength = 500): string
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('OpenAIAnalysisService: summarize initiated', [
            'text_length' => strlen($text),
            'max_length' => $maxLength,
            'user_id' => auth()->id(),
        ]);

        try {
            $cacheKey = $this->generateCacheKey('summarize', [
                'text' => md5($text),
                'max_length' => $maxLength,
            ]);

            if ($cached = Cache::get($cacheKey)) {
                Log::info('OpenAIAnalysisService: summarize cache hit');

                return $cached;
            }

            // Calculate target word count (rough: 5 chars per word)
            $targetWords = (int) ($maxLength / 5);

            $systemPrompt = <<<SYSTEM
Vi ste stručni pravni asistent. Sažmite tekst zadržavajući sve ključne pravne činjenice.
Sažetak mora biti na hrvatskom jeziku i ne smije biti duži od {$targetWords} riječi.
SYSTEM;

            $userPrompt = <<<USER
Sažmite sljedeći tekst:

{$text}
USER;

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->chat->chat($messages, $this->defaultModel, [
                'temperature' => 0.3,
                'max_tokens' => $targetWords * 2, // Allow some overhead
            ]);

            $summary = $response['choices'][0]['message']['content'] ?? '';

            // Trim to max length if needed
            if (strlen($summary) > $maxLength) {
                $summary = substr($summary, 0, $maxLength - 3).'...';
            }

            Cache::put($cacheKey, $summary, $this->cacheTtl);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('OpenAIAnalysisService: summarize completed', [
                'summary_length' => strlen($summary),
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'duration_ms' => round($duration, 2),
            ]);

            return $summary;

        } catch (AnalysisException $e) {
            Log::error('OpenAIAnalysisService: summarize failed with AnalysisException', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('OpenAIAnalysisService: summarize failed with unexpected exception', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'text_length' => strlen($text),
            ]);

            throw new AnalysisException(
                'Text summarization failed: '.$e->getMessage(),
                AnalysisException::SUMMARIZATION_FAILED,
                $e
            );
        }
    }

    /**
     * Extract structured information from text according to schema
     *
     * @param  string  $text  Text to analyze
     * @param  array  $schema  Expected output schema with field descriptions
     * @return array Extracted information matching schema
     *
     * @throws \Throwable
     */
    public function extractStructuredData(string $text, array $schema): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('OpenAIAnalysisService: extractStructuredData initiated', [
            'text_length' => strlen($text),
            'schema_fields' => array_keys($schema),
            'user_id' => auth()->id(),
        ]);

        try {
            $cacheKey = $this->generateCacheKey('extract_structured', [
                'text' => md5($text),
                'schema' => md5(json_encode($schema)),
            ]);

            if ($cached = Cache::get($cacheKey)) {
                Log::info('OpenAIAnalysisService: extractStructuredData cache hit');

                return $cached;
            }

            // Build schema description
            $schemaDesc = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $systemPrompt = <<<'SYSTEM'
Vi ste stručni asistent za ekstrakciju strukturiranih podataka iz pravnih tekstova.
Ekstrairajte podatke prema zadanoj shemi i vratite ih u JSON formatu.
SYSTEM;

            $userPrompt = <<<USER
Ekstrairajte strukturirane podatke iz sljedećeg teksta prema ovoj shemi:

SHEMA:
{$schemaDesc}

TEKST:
{$text}

Vratite podatke u JSON formatu koji točno odgovara shemi.
USER;

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->chat->chat($messages, $this->defaultModel, [
                'temperature' => 0.2, // Lower temperature for extraction
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $extracted = json_decode($content, true);

            if (! is_array($extracted)) {
                throw new \RuntimeException('Failed to parse extracted data');
            }

            Cache::put($cacheKey, $extracted, $this->cacheTtl);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('OpenAIAnalysisService: extractStructuredData completed', [
                'extracted_fields' => array_keys($extracted),
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'duration_ms' => round($duration, 2),
            ]);

            return $extracted;

        } catch (AnalysisException $e) {
            Log::error('OpenAIAnalysisService: extractStructuredData failed with AnalysisException', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('OpenAIAnalysisService: extractStructuredData failed with unexpected exception', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'schema_fields' => array_keys($schema),
            ]);

            throw new AnalysisException(
                'Structured data extraction failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Summarize court decision
     *
     * @param  string  $decisionText  Full court decision text
     * @param  array  $options  Summarization options
     * @return string Summary focusing on key legal points
     *
     * @throws \Throwable
     */
    public function summarizeDecision(string $decisionText, array $options = []): string
    {
        $maxLength = $options['max_length'] ?? 500;

        $systemPrompt = <<<'SYSTEM'
Vi ste stručni pravni analitičar specijaliziran za sudske odluke.
Sažmite odluku fokusirajući se na:
1. Sud i vrsta odluke
2. Ključna pravna pitanja
3. Odluka suda i obrazloženje
4. Citirani pravni propisi
SYSTEM;

        return $this->summarize($decisionText, $maxLength);
    }

    /**
     * Extract legal citations from text
     *
     * Extracts citations to Croatian legal sources:
     * - ZKP (Zakon o kaznenom postupku)
     * - Ustav RH (Ustav Republike Hrvatske)
     * - Kazneni zakon
     * - Other laws referenced by NN numbers
     *
     * @param  string  $text  Text containing citations
     * @return array Array of extracted citations with metadata
     *
     * @throws \Throwable
     */
    public function extractCitations(string $text): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('OpenAIAnalysisService: extractCitations initiated', [
            'text_length' => strlen($text),
            'user_id' => auth()->id(),
        ]);

        try {
            $cacheKey = $this->generateCacheKey('extract_citations', [
                'text' => md5($text),
            ]);

            if ($cached = Cache::get($cacheKey)) {
                Log::info('OpenAIAnalysisService: extractCitations cache hit');

                return $cached;
            }

            $systemPrompt = <<<'SYSTEM'
Vi ste stručnjak za hrvatsko pravo. Ekstrairajte sve citiranje zakona iz teksta.
Vratite listu u JSON formatu sa sljedećim poljima za svaki citat:
- type: tip zakona (ZKP, Ustav RH, Kazneni zakon, NN, itd.)
- article: broj članka
- paragraph: stavak (ako postoji)
- item: točka (ako postoji)
- raw: originalni tekst citiranja
SYSTEM;

            $userPrompt = <<<USER
Ekstrairajte sva citiranja zakona iz sljedećeg teksta:

{$text}

Vratite rezultat u JSON formatu sa poljem "citations" koje sadrži listu citata.
USER;

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->chat->chat($messages, $this->defaultModel, [
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $result = json_decode($content, true);

            $citations = $result['citations'] ?? [];

            Cache::put($cacheKey, $citations, $this->cacheTtl);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('OpenAIAnalysisService: extractCitations completed', [
                'citations_count' => count($citations),
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'duration_ms' => round($duration, 2),
            ]);

            return $citations;

        } catch (AnalysisException $e) {
            Log::error('OpenAIAnalysisService: extractCitations failed with AnalysisException', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('OpenAIAnalysisService: extractCitations failed with unexpected exception', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'text_length' => strlen($text),
            ]);

            throw new AnalysisException(
                'Citation extraction failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Classify document into predefined categories
     *
     * @param  string  $text  Document text
     * @param  array  $categories  List of possible categories
     * @return string Best matching category
     *
     * @throws \Throwable
     */
    public function classifyDocument(string $text, array $categories): string
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('OpenAIAnalysisService: classifyDocument initiated', [
            'text_length' => strlen($text),
            'categories_count' => count($categories),
            'categories' => $categories,
            'user_id' => auth()->id(),
        ]);

        try {
            $cacheKey = $this->generateCacheKey('classify', [
                'text' => md5($text),
                'categories' => md5(json_encode($categories)),
            ]);

            if ($cached = Cache::get($cacheKey)) {
                Log::info('OpenAIAnalysisService: classifyDocument cache hit', [
                    'category' => $cached,
                ]);

                return $cached;
            }

            $categoriesStr = implode(', ', $categories);

            $systemPrompt = <<<'SYSTEM'
Vi ste stručnjak za klasifikaciju pravnih dokumenata.
Klasificirajte dokument u jednu od zadanih kategorija.
Vratite samo naziv kategorije, ništa drugo.
SYSTEM;

            $userPrompt = <<<USER
Klasificirajte sljedeći dokument u jednu od ovih kategorija: {$categoriesStr}

DOKUMENT:
{$text}

Odgovorite samo sa nazivom kategorije.
USER;

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->chat->chat($messages, $this->defaultModel, [
                'temperature' => 0.1, // Very low for classification
                'max_tokens' => 50,
            ]);

            $category = trim($response['choices'][0]['message']['content'] ?? '');

            // Validate category
            if (! in_array($category, $categories)) {
                // Try fuzzy match
                foreach ($categories as $validCategory) {
                    if (stripos($category, $validCategory) !== false) {
                        $category = $validCategory;
                        break;
                    }
                }
            }

            Cache::put($cacheKey, $category, $this->cacheTtl);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('OpenAIAnalysisService: classifyDocument completed', [
                'category' => $category,
                'is_valid_category' => in_array($category, $categories),
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'duration_ms' => round($duration, 2),
            ]);

            return $category;

        } catch (AnalysisException $e) {
            Log::error('OpenAIAnalysisService: classifyDocument failed with AnalysisException', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('OpenAIAnalysisService: classifyDocument failed with unexpected exception', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'categories' => $categories,
            ]);

            throw new AnalysisException(
                'Document classification failed: '.$e->getMessage(),
                AnalysisException::CLASSIFICATION_FAILED,
                $e
            );
        }
    }

    /**
     * Extract legal entities from text
     *
     * @param  string  $text  Text to analyze
     * @return array Extracted entities (courts, laws, parties, etc.)
     *
     * @throws \Throwable
     */
    public function extractEntities(string $text): array
    {
        $schema = [
            'courts' => 'List of court names mentioned',
            'laws' => 'List of laws referenced',
            'case_numbers' => 'List of case/decision numbers',
            'parties' => 'List of parties involved (plaintiff, defendant, etc.)',
            'dates' => 'Important dates mentioned',
            'locations' => 'Locations mentioned',
        ];

        return $this->extractStructuredData($text, $schema);
    }

    /**
     * Generate cache key for analysis operations
     *
     * @param  string  $operation  Operation name
     * @param  array  $params  Parameters
     * @return string Cache key
     */
    protected function generateCacheKey(string $operation, array $params): string
    {
        ksort($params);
        $paramHash = md5(json_encode($params));

        return "openai_analysis:{$operation}:{$paramHash}";
    }
}
