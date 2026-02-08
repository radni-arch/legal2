<?php

namespace App\Services\Research;

use App\Contracts\Research\SearchExecutorInterface;
use App\Exceptions\SearchException;
use App\Services\Search\CaseSearchService;
use App\Services\Search\DecisionSearchService;
use App\Services\Search\LawSearchService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Search Executor Service
 *
 * Responsible for executing searches across multiple corpora (laws, decisions, cases).
 * Extracted from AutonomousResearchAgent as part of Phase 2 refactoring.
 *
 * Responsibilities:
 * - Execute searches across multiple corpora
 * - Aggregate search results
 * - Rank and score results
 * - Handle search errors/timeouts
 * - Track search performance
 *
 * Target: 250-300 lines
 */
class SearchExecutorService implements SearchExecutorInterface
{
    /**
     * Performance tracking
     */
    protected array $performanceMetrics = [];

    public function __construct(
        protected LawSearchService $lawSearch,
        protected DecisionSearchService $decisionSearch,
        protected CaseSearchService $caseSearch
    ) {}

    /**
     * Execute searches across all corpora
     *
     * @param  array  $questions  Array of search actions to execute
     *                            Each action has:
     *                            - tool: Tool name
     *                            - params: Parameters for the tool
     * @param  array  $options  Execution options:
     *                          - parallel: Execute searches in parallel (default: false)
     *                          - timeout: Timeout in seconds per search
     *                          - retries: Number of retries on failure
     * @return array Results from all searches with structure:
     *               - tool: Tool that was executed
     *               - params: Parameters used
     *               - result: Search results
     *               - success: Whether the search succeeded
     *               - error: Error message if failed
     */
    public function execute(array $questions, array $options = []): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('SearchExecutor: execute initiated', [
            'questions_count' => count($questions),
            'options' => $options,
            'user_id' => auth()->id(),
        ]);

        try {
            $results = [];

            // Reset performance metrics
            $this->performanceMetrics = [
                'total_searches' => count($questions),
                'successful_searches' => 0,
                'failed_searches' => 0,
                'corpus_breakdown' => [],
            ];

            // Extract options
            $parallel = $options['parallel'] ?? false;
            $timeout = $options['timeout'] ?? 30;
            $retries = $options['retries'] ?? 0;

            Log::debug('SearchExecutor: batch execution started', [
                'actions_count' => count($questions),
                'parallel' => $parallel,
                'timeout' => $timeout,
                'retries' => $retries,
            ]);

            foreach ($questions as $index => $action) {
                $tool = $action['tool'] ?? null;
                $params = $action['params'] ?? [];

                if (! $tool) {
                    $results[] = [
                        'tool' => 'unknown',
                        'params' => $params,
                        'error' => 'Missing tool name',
                        'success' => false,
                    ];
                    $this->performanceMetrics['failed_searches']++;

                    continue;
                }

                try {
                    $actionStart = microtime(true);
                    $result = $this->executeActionWithRetry($tool, $params, $retries, $timeout);
                    $actionTime = microtime(true) - $actionStart;

                    $resultEntry = [
                        'tool' => $tool,
                        'params' => $params,
                        'success' => ! isset($result['error']),
                        'execution_time' => $actionTime,
                    ];

                    // Add either result or error at top level based on success
                    if (! isset($result['error'])) {
                        $resultEntry['result'] = $result;
                        $this->performanceMetrics['successful_searches']++;
                    } else {
                        $resultEntry['error'] = $result['error'];
                        $this->performanceMetrics['failed_searches']++;
                    }

                    $results[] = $resultEntry;

                    // Track corpus breakdown
                    $corpus = $this->getCorpusFromTool($tool);
                    if ($corpus) {
                        if (! isset($this->performanceMetrics['corpus_breakdown'][$corpus])) {
                            $this->performanceMetrics['corpus_breakdown'][$corpus] = [
                                'count' => 0,
                                'total_time' => 0,
                            ];
                        }
                        $this->performanceMetrics['corpus_breakdown'][$corpus]['count']++;
                        $this->performanceMetrics['corpus_breakdown'][$corpus]['total_time'] += $actionTime;
                    }

                } catch (SearchException $e) {
                    Log::error('SearchExecutor: Action execution failed with SearchException', [
                        'tool' => $tool,
                        'error' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'exception_class' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $results[] = [
                        'tool' => $tool,
                        'params' => $params,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'success' => false,
                    ];
                    $this->performanceMetrics['failed_searches']++;
                } catch (\Exception $e) {
                    Log::error('SearchExecutor: Action execution failed with unexpected exception', [
                        'tool' => $tool,
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $results[] = [
                        'tool' => $tool,
                        'params' => $params,
                        'error' => $e->getMessage(),
                        'success' => false,
                    ];
                    $this->performanceMetrics['failed_searches']++;
                }
            }

            $totalTime = (microtime(true) - $startTime) * 1000;
            $this->performanceMetrics['total_time'] = $totalTime;

            Log::info('SearchExecutor: execute completed', [
                'duration_ms' => round($totalTime, 2),
                'successful' => $this->performanceMetrics['successful_searches'],
                'failed' => $this->performanceMetrics['failed_searches'],
                'total_searches' => count($questions),
            ]);

            return $results;

        } catch (SearchException $e) {
            Log::error('SearchExecutor: execute failed with SearchException', [
                'questions_count' => count($questions),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('SearchExecutor: execute failed with unexpected exception', [
                'questions_count' => count($questions),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new SearchException(
                'Search execution failed: '.$e->getMessage(),
                SearchException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Execute action with retry logic
     *
     * @param  string  $tool  Tool name
     * @param  array  $params  Tool parameters
     * @param  int  $retries  Number of retries
     * @param  int  $timeout  Timeout in seconds
     * @return array Search result
     */
    protected function executeActionWithRetry(string $tool, array $params, int $retries, int $timeout): array
    {
        $attempts = 0;
        $maxAttempts = $retries + 1;
        $lastError = null;

        while ($attempts < $maxAttempts) {
            $attempts++;

            $result = $this->executeAction($tool, $params);

            // If successful, return immediately
            if (! isset($result['error'])) {
                return $result;
            }

            // Store the error
            $lastError = $result['error'];

            // If we've exhausted retries, return the error
            if ($attempts >= $maxAttempts) {
                return ['error' => "Failed after {$attempts} attempts: {$lastError}"];
            }

            Log::warning('SearchExecutor: Retry attempt', [
                'tool' => $tool,
                'attempt' => $attempts,
                'max_attempts' => $maxAttempts,
                'error' => $lastError,
            ]);

            // Exponential backoff
            usleep(min(1000000 * pow(2, $attempts - 1), 5000000)); // Max 5 seconds
        }

        return ['error' => 'Unexpected retry loop exit'];
    }

    /**
     * Execute a single action
     *
     * @param  string  $tool  Tool name
     * @param  array  $params  Tool parameters
     * @return array Search result
     */
    public function executeAction(string $tool, array $params): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('SearchExecutor: executeAction initiated', [
            'tool' => $tool,
            'params_keys' => array_keys($params),
            'user_id' => auth()->id(),
        ]);

        try {
            // Validate tool exists
            if (empty($tool)) {
                throw new SearchException(
                    'Tool name is required',
                    SearchException::INVALID_QUERY
                );
            }

            $result = match ($tool) {
                // Law search tools
                'law_vector_search' => $this->lawSearch->search($params['query'] ?? '', $params),
                'law_keyword_search' => $this->searchCorpus('laws', $params['query'] ?? '', array_merge($params, ['search_type' => 'keyword'])),
                'law_hybrid_search' => $this->searchCorpus('laws', $params['query'] ?? '', array_merge($params, ['search_type' => 'hybrid'])),

                // Decision search tools
                'decision_vector_search' => $this->decisionSearch->search($params['query'] ?? '', $params),
                'decision_keyword_search' => $this->searchCorpus('decisions', $params['query'] ?? '', array_merge($params, ['search_type' => 'keyword'])),
                'decision_hybrid_search' => $this->searchCorpus('decisions', $params['query'] ?? '', array_merge($params, ['search_type' => 'hybrid'])),

                // Case search tools
                'case_vector_search' => $this->caseSearch->search($params['query'] ?? '', $params),
                'case_search' => $this->caseSearch->search($params['query'] ?? '', $params),
                'case_document_search' => $this->caseSearch->search($params['query'] ?? '', $params),

                default => throw new SearchException(
                    "Unknown search tool: {$tool}",
                    SearchException::CORPUS_NOT_FOUND
                ),
            };

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('SearchExecutor: executeAction completed', [
                'tool' => $tool,
                'duration_ms' => round($duration, 2),
                'has_results' => ! empty($result),
            ]);

            return $result;

        } catch (SearchException $e) {
            Log::error('SearchExecutor: executeAction failed with SearchException', [
                'tool' => $tool,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => $e->getMessage(), 'error_code' => $e->getCode()];

        } catch (\Exception $e) {
            Log::error('SearchExecutor: executeAction failed with unexpected exception', [
                'tool' => $tool,
                'params' => $params,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Search specific corpus
     *
     * @param  string  $corpus  Corpus name (e.g., 'law', 'decision', 'case')
     * @param  string  $query  Search query
     * @param  array  $options  Search options:
     *                          - search_type: 'vector', 'keyword', 'hybrid'
     *                          - limit: Number of results
     *                          - filters: Additional filters
     * @return array Search results
     */
    public function searchCorpus(string $corpus, string $query, array $options = []): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('SearchExecutor: searchCorpus initiated', [
            'corpus' => $corpus,
            'query_length' => strlen($query),
            'options_keys' => array_keys($options),
            'user_id' => auth()->id(),
        ]);

        try {
            // Validate inputs
            if (empty($corpus)) {
                throw new SearchException(
                    'Corpus name is required',
                    SearchException::CORPUS_NOT_FOUND
                );
            }

            if (empty($query)) {
                throw new SearchException(
                    'Search query is required',
                    SearchException::INVALID_QUERY
                );
            }

            $searchType = $options['search_type'] ?? 'vector';

            Log::debug('SearchExecutor: Searching corpus', [
                'corpus' => $corpus,
                'query' => substr($query, 0, 100),
                'search_type' => $searchType,
            ]);

            $service = match ($corpus) {
                'law', 'laws' => $this->lawSearch,
                'decision', 'decisions' => $this->decisionSearch,
                'case', 'cases' => $this->caseSearch,
                default => throw new SearchException(
                    "Unknown corpus: {$corpus}",
                    SearchException::CORPUS_NOT_FOUND
                ),
            };

            // All services use the same search() method for vector search
            $results = $service->search($query, $options);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('SearchExecutor: searchCorpus completed', [
                'corpus' => $corpus,
                'results_count' => is_array($results) ? count($results) : 0,
                'duration_ms' => round($duration, 2),
            ]);

            return $results;

        } catch (SearchException $e) {
            Log::error('SearchExecutor: searchCorpus failed with SearchException', [
                'corpus' => $corpus,
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => $e->getMessage(), 'error_code' => $e->getCode()];

        } catch (\Exception $e) {
            Log::error('SearchExecutor: searchCorpus failed with unexpected exception', [
                'corpus' => $corpus,
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Aggregate results from multiple searches
     *
     * @param  array  $results  Array of search results
     * @return array Aggregated results
     */
    protected function aggregateResults(array $results): array
    {
        $aggregated = [];

        foreach ($results as $result) {
            if (! isset($result['result']) || ! is_array($result['result'])) {
                continue;
            }

            foreach ($result['result'] as $item) {
                $aggregated[] = $item;
            }
        }

        // Sort by score descending
        usort($aggregated, function ($a, $b) {
            $scoreA = $a['score'] ?? $a['similarity'] ?? 0;
            $scoreB = $b['score'] ?? $b['similarity'] ?? 0;

            return $scoreB <=> $scoreA;
        });

        return $aggregated;
    }

    /**
     * Score results by relevance
     *
     * @param  array  $results  Array of search results
     * @param  string  $query  Original search query
     * @return array Scored results
     */
    protected function scoreResults(array $results, string $query): array
    {
        $scored = [];
        $queryLower = mb_strtolower($query);
        $queryTerms = array_filter(explode(' ', $queryLower));

        foreach ($results as $result) {
            $score = $result['score'] ?? $result['similarity'] ?? 0.0;

            // Boost score if query terms appear in title/content
            $content = mb_strtolower($result['content'] ?? $result['title'] ?? '');

            $termMatchCount = 0;
            foreach ($queryTerms as $term) {
                if (mb_strlen($term) > 3 && mb_strpos($content, $term) !== false) {
                    $termMatchCount++;
                }
            }

            if ($termMatchCount > 0) {
                $boost = 1.0 + (0.1 * $termMatchCount / count($queryTerms));
                $score = min(1.0, $score * $boost);
            }

            $result['score'] = round($score, 4);
            $scored[] = $result;
        }

        // Re-sort by adjusted score
        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $scored;
    }

    /**
     * Get corpus name from tool name
     *
     * @param  string  $tool  Tool name
     * @return string|null Corpus name or null
     */
    protected function getCorpusFromTool(string $tool): ?string
    {
        if (str_starts_with($tool, 'law_')) {
            return 'laws';
        }
        if (str_starts_with($tool, 'decision_')) {
            return 'decisions';
        }
        if (str_starts_with($tool, 'case_')) {
            return 'cases';
        }

        return null;
    }

    /**
     * Get performance metrics
     *
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    /**
     * Reset performance metrics
     */
    public function resetPerformanceMetrics(): void
    {
        $this->performanceMetrics = [];
    }
}
