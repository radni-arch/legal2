<?php

namespace App\Agents;

use App\Models\DecisionDiscoveryRun;
use App\Services\ActiveLearningService;
use App\Services\Explainability\ReasoningTraceService;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use App\Services\OpenAIService;
use App\Services\QueryRewriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vizra\VizraADK\Agents\BaseLlmAgent;

/**
 * Autonomous agent that discovers and ingests interesting court decisions
 *
 * This agent:
 * 1. Uses LLM to generate research topics
 * 2. Searches odluke.sudovi.hr for decisions on those topics
 * 3. Uses LLM to score decisions for relevance and importance
 * 4. Autonomously ingests top-scoring decisions
 *
 * Framework: Vizra ADK (BaseLlmAgent)
 */
class DecisionDiscoveryAgent extends BaseLlmAgent
{
    // Service dependencies (lazy-loaded via app() when not injected)
    protected ?OdlukeClient $client = null;

    protected ?OdlukeIngestService $ingest = null;

    protected ?OpenAIService $openai = null;

    protected ?QueryRewriter $rewriter = null;

    protected ?ReasoningTraceService $traceService = null;

    protected ?ActiveLearningService $learningService = null;

    // Vizra framework properties
    protected string $name = 'decision_discovery_agent';

    protected string $description = 'Autonomous agent that discovers and ingests important Croatian court decisions';

    protected ?string $provider = 'openai';

    protected string $model = 'gpt-4o-mini';

    protected int $maxSteps = 10;

    protected bool $showInChatUi = false;

    protected array $tools = []; // Uses direct service calls instead of tools

    // Discovery configuration
    protected int $topicsPerRun = 5;

    protected int $decisionsPerTopic = 50;

    protected int $ingestPerTopic = 10;

    protected float $relevanceThreshold = 70.0; // 0-100 score

    protected ?int $maxDecisionsGlobal = null; // Global cap across all topics

    public function __construct(
        ?OdlukeClient $client = null,
        ?OdlukeIngestService $ingest = null,
        ?OpenAIService $openai = null,
        ?QueryRewriter $rewriter = null,
        ?ReasoningTraceService $traceService = null,
        ?ActiveLearningService $learningService = null
    ) {
        // Allow zero-arg instantiation (Vizra ChatInterface) with lazy container resolution
        $this->client = $client;
        $this->ingest = $ingest;
        $this->openai = $openai;
        $this->rewriter = $rewriter;
        $this->traceService = $traceService;
        $this->learningService = $learningService;

        // Call parent constructor to initialize framework
        parent::__construct();

        // Build agent instructions
        $this->instructions = $this->buildInstructions();
    }

    /**
     * Resolve a service dependency, lazy-loading from the container if not injected.
     */
    protected function getClient(): OdlukeClient
    {
        if ($this->client === null) {
            $this->client = app(OdlukeClient::class);
        }

        return $this->client;
    }

    protected function getIngest(): OdlukeIngestService
    {
        if ($this->ingest === null) {
            $this->ingest = app(OdlukeIngestService::class);
        }

        return $this->ingest;
    }

    protected function getOpenai(): OpenAIService
    {
        if ($this->openai === null) {
            $this->openai = app(OpenAIService::class);
        }

        return $this->openai;
    }

    protected function getRewriter(): QueryRewriter
    {
        if ($this->rewriter === null) {
            $this->rewriter = new QueryRewriter($this->getOpenai());
        }

        return $this->rewriter;
    }

    protected function getTraceService(): ReasoningTraceService
    {
        if ($this->traceService === null) {
            $this->traceService = app(ReasoningTraceService::class);
        }

        return $this->traceService;
    }

    protected function getLearningService(): ActiveLearningService
    {
        if ($this->learningService === null) {
            $this->learningService = app(ActiveLearningService::class);
        }

        return $this->learningService;
    }

    /**
     * Build agent instructions for LLM.
     */
    private function buildInstructions(): string
    {
        return <<<'INSTRUCTIONS'
You are an autonomous Croatian legal research agent specialized in discovering important court decisions.

Your mission:
1. Generate research topics covering important areas of Croatian law
2. Search odluke.sudovi.hr for relevant court decisions
3. Evaluate decisions for relevance, authority, and importance
4. Autonomously ingest top-quality decisions into the knowledge base

Evaluation criteria:
- Relevance to Croatian legal practice
- Court authority (Vrhovni sud > Županijski sud > Općinski sud)
- Decision type (Presuda > Rješenje)
- Recency and legal significance
- Coverage of important legal areas (employment, contracts, property, consumer protection)

You operate autonomously with minimal human intervention. Be selective and prioritize quality over quantity.
INSTRUCTIONS;
    }

    /**
     * Main discovery loop
     *
     * @param  string|null  $topicFilter  Optional topic filter to restrict discovery to a specific topic
     */
    public function discover(?string $topicFilter = null): array
    {
        Log::info('Starting autonomous decision discovery', [
            'topic_filter' => $topicFilter,
        ]);

        // Create run record
        $run = DecisionDiscoveryRun::create([
            'started_at' => now(),
            'status' => 'running',
            'topic_filter' => $topicFilter,
        ]);

        $startTime = microtime(true);
        $stats = [
            'topics_generated' => 0,
            'decisions_found' => 0,
            'decisions_evaluated' => 0,
            'decisions_ingested' => 0,
            'decisions_attempted' => 0,
            'ingestion_errors' => 0,
            'decisions_skipped' => 0,
            'errors' => [],
        ];

        try {
            // 1. Generate research topics
            $topics = $this->generateResearchTopics();
            $stats['topics_generated'] = count($topics);

            Log::info('Generated research topics', ['count' => count($topics), 'topics' => $topics]);

            // 2. For each topic, discover and ingest decisions
            foreach ($topics as $topic) {
                // Check if global limit reached
                if ($this->maxDecisionsGlobal !== null && $stats['decisions_ingested'] >= $this->maxDecisionsGlobal) {
                    Log::info('Global decision limit reached, stopping discovery', [
                        'limit' => $this->maxDecisionsGlobal,
                        'ingested' => $stats['decisions_ingested'],
                    ]);
                    break;
                }

                try {
                    // Calculate remaining slots for this topic
                    $remainingSlots = $this->maxDecisionsGlobal !== null
                        ? $this->maxDecisionsGlobal - $stats['decisions_ingested']
                        : null;

                    $result = $this->discoverForTopic($topic, $remainingSlots);

                    $stats['decisions_found'] += $result['found'];
                    $stats['decisions_evaluated'] += $result['evaluated'];
                    $stats['decisions_ingested'] += $result['ingested'];
                    $stats['decisions_attempted'] += $result['attempted'];
                    $stats['ingestion_errors'] += $result['errors'];
                    $stats['decisions_skipped'] += $result['skipped'];

                } catch (\Exception $e) {
                    Log::error('Error discovering for topic', [
                        'topic' => $topic,
                        'error' => $e->getMessage(),
                    ]);

                    $stats['errors'][] = [
                        'topic' => $topic,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $duration = microtime(true) - $startTime;

            Log::info('Discovery completed', [
                'duration_seconds' => round($duration, 2),
                'stats' => $stats,
            ]);

            // Calculate derived statistics
            $statistics = [
                'topics_generated' => $stats['topics_generated'],
                'decisions_found' => $stats['decisions_found'],
                'decisions_evaluated' => $stats['decisions_evaluated'],
                'decisions_ingested' => $stats['decisions_ingested'],
                'duration_seconds' => round($duration, 2),
                'error_count' => count($stats['errors']),
                'success_rate' => $stats['decisions_found'] > 0
                    ? round(($stats['decisions_ingested'] / $stats['decisions_found']) * 100, 2)
                    : 0,
                'evaluation_rate' => $stats['decisions_found'] > 0
                    ? round(($stats['decisions_evaluated'] / $stats['decisions_found']) * 100, 2)
                    : 0,
            ];

            // Update run record with success
            $run->update([
                'completed_at' => now(),
                'status' => 'completed',
                'topics_generated' => $stats['topics_generated'],
                'decisions_found' => $stats['decisions_found'],
                'decisions_evaluated' => $stats['decisions_evaluated'],
                'decisions_ingested' => $stats['decisions_ingested'],
                'duration_seconds' => round($duration, 2),
                'topics' => $topics,
                'errors' => $stats['errors'],
                'statistics' => [
                    'decisions_attempted' => $stats['decisions_attempted'],
                    'ingestion_errors' => $stats['ingestion_errors'],
                    'decisions_skipped' => $stats['decisions_skipped'],
                    'ingestion_success_rate' => $stats['decisions_attempted'] > 0
                        ? round(($stats['decisions_ingested'] / $stats['decisions_attempted']) * 100, 2)
                        : 100,
                ],
            ]);

            return $stats;

        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Discovery failed', [
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 2),
            ]);

            // Update run record with failure
            $run->update([
                'completed_at' => now(),
                'status' => 'failed',
                'duration_seconds' => round($duration, 2),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Discover decisions for a single specific topic
     *
     * @param  string  $topic  The specific topic to discover
     * @return array Statistics about the discovery run
     */
    public function discoverSingleTopic(string $topic): array
    {
        Log::info('Starting single-topic decision discovery', ['topic' => $topic]);

        // Create run record
        $run = DecisionDiscoveryRun::create([
            'started_at' => now(),
            'status' => 'running',
        ]);

        $startTime = microtime(true);
        $stats = [
            'topics_generated' => 1,
            'decisions_evaluated' => 0,
            'decisions_ingested' => 0,
            'decisions_attempted' => 0,
            'ingestion_errors' => 0,
            'decisions_skipped' => 0,
            'errors' => [],
        ];

        try {
            // Calculate remaining slots if global limit is set
            $remainingSlots = $this->maxDecisionsGlobal;

            // Discover for the single topic
            $result = $this->discoverForTopic($topic, $remainingSlots);

            $stats['decisions_evaluated'] = $result['evaluated'];
            $stats['decisions_ingested'] = $result['ingested'];
            $stats['decisions_attempted'] = $result['attempted'];
            $stats['ingestion_errors'] = $result['errors'];
            $stats['decisions_skipped'] = $result['skipped'];

            $duration = microtime(true) - $startTime;

            Log::info('Single-topic discovery completed', [
                'topic' => $topic,
                'duration_seconds' => round($duration, 2),
                'stats' => $stats,
            ]);

            // Update run record with success
            $run->update([
                'completed_at' => now(),
                'status' => 'completed',
                'topics_generated' => 1,
                'decisions_evaluated' => $stats['decisions_evaluated'],
                'decisions_ingested' => $stats['decisions_ingested'],
                'topics' => [$topic],
                'errors' => $stats['errors'],
                'statistics' => [
                    'decisions_attempted' => $stats['decisions_attempted'],
                    'ingestion_errors' => $stats['ingestion_errors'],
                    'decisions_skipped' => $stats['decisions_skipped'],
                    'ingestion_success_rate' => $stats['decisions_attempted'] > 0
                        ? round(($stats['decisions_ingested'] / $stats['decisions_attempted']) * 100, 2)
                        : 100,
                ],
            ]);

            return $stats;

        } catch (\Exception $e) {
            Log::error('Single-topic discovery failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            // Update run record with failure
            $run->update([
                'completed_at' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate research topics using LLM
     */
    protected function generateResearchTopics(): array
    {
        // Sprint 2.5: Start reasoning trace for topic generation
        $traceId = $this->getTraceService()->startTrace(
            'generate_research_topics',
            ['topics_per_run' => $this->topicsPerRun],
            null,
            'decision_discovery_agent',
            'topic_generation'
        );

        // Check cache first (topics valid for 1 week)
        // Include topicsPerRun in cache key to avoid returning wrong number of topics
        $cacheKey = 'decision_discovery:topics:'.date('Y-W').':'.$this->topicsPerRun;

        if (Cache::has($cacheKey)) {
            Log::info('Using cached research topics', [
                'topics_per_run' => $this->topicsPerRun,
            ]);

            $topics = Cache::get($cacheKey);

            // Sprint 2.5: End trace for cached result
            $this->getTraceService()->endTrace(
                $traceId,
                ['topics' => $topics, 'source' => 'cache'],
                'Used cached topics from previous week',
                1.0
            );

            return $topics;
        }

        Log::info('Generating new research topics via LLM', [
            'topics_per_run' => $this->topicsPerRun,
        ]);

        $prompt = <<<PROMPT
You are a Croatian legal researcher. Identify the {$this->topicsPerRun} most important areas of Croatian law where new court decisions should be monitored.

CRITERIA:
- Areas with frequent litigation
- Emerging legal issues
- Topics with recent legislative changes
- Areas important for employment, contract, or property law
- Mix of civil and criminal law topics

EXAMPLES:
- Nezakonit otkaz (Unlawful termination)
- Ugovorna odgovornost (Contractual liability)
- Potrošačka zaštita (Consumer protection)
- Vlasničkopravni sporovi (Property disputes)

Respond with JSON object containing a "topics" array of {$this->topicsPerRun} Croatian legal topics (search-friendly phrases):
{"topics": ["topic 1", "topic 2", ...]}
PROMPT;

        try {
            $response = $this->getOpenai()->chat([
                ['role' => 'system', 'content' => 'You are an expert in Croatian law and legal research.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.8, // Higher for topic diversity
            ]);

            $content = $response['choices'][0]['message']['content'] ?? null;

            if (! $content) {
                throw new \Exception('Empty response from LLM');
            }

            $data = json_decode($content, true);

            $topics = $data['topics'] ?? [];

            if (empty($topics)) {
                throw new \Exception('LLM returned no topics');
            }

            Log::info('LLM generated topics', ['topics' => $topics]);

            // Cache for 1 week
            Cache::put($cacheKey, $topics, now()->addWeek());

            // Sprint 2.5: End trace with success
            $this->getTraceService()->endTrace(
                $traceId,
                ['topics' => $topics, 'source' => 'llm'],
                "Generated {$this->topicsPerRun} research topics using LLM",
                0.9
            );

            return $topics;

        } catch (\Exception $e) {
            Log::error('Topic generation failed', ['error' => $e->getMessage()]);

            // Fallback to predefined topics
            $fallbackTopics = [
                'Radno pravo',
                'Ugovorno pravo',
                'Potrošačka zaštita',
                'Vlasničkopravni sporovi',
                'Obvezno pravo',
            ];

            Log::warning('Using fallback topics', ['topics' => $fallbackTopics]);

            $topics = array_slice($fallbackTopics, 0, $this->topicsPerRun);

            // Sprint 2.5: End trace with fallback
            $this->getTraceService()->endTrace(
                $traceId,
                ['topics' => $topics, 'source' => 'fallback', 'error' => $e->getMessage()],
                "LLM generation failed, used fallback topics: {$e->getMessage()}",
                0.5
            );

            return $topics;
        }
    }

    /**
     * Discover and ingest decisions for a specific topic
     *
     * @param  string  $topic  The topic to discover
     * @param  int|null  $maxIngest  Maximum decisions to ingest for this topic (overrides ingestPerTopic if lower)
     */
    protected function discoverForTopic(string $topic, ?int $maxIngest = null): array
    {
        Log::info('Discovering decisions for topic', [
            'topic' => $topic,
            'max_ingest' => $maxIngest,
        ]);

        // 1. Translate topic to search query
        $query = $this->translateTopicToQuery($topic);

        // 2. Search odluke.sudovi.hr
        $searchResult = $this->getClient()->collectIdsFromList(
            $query,
            null,
            $this->decisionsPerTopic,
            1
        );

        // Extract IDs from response (collectIdsFromList returns ['url', 'ids', 'count'] or ['url', 'ids', 'error'])
        $decisionIds = $searchResult['ids'] ?? [];
        $foundCount = count($decisionIds);

        // Check for API errors and distinguish from legitimately empty results
        if (isset($searchResult['error'])) {
            Log::error('API error during decision search', [
                'topic' => $topic,
                'error' => $searchResult['error'],
                'url' => $searchResult['url'] ?? null,
            ]);

            return ['evaluated' => 0, 'ingested' => 0, 'attempted' => 0, 'errors' => 1, 'skipped' => 0];
        }

        if (isset($searchResult['status']) && $searchResult['status'] !== 200) {
            Log::warning('Search returned non-200 status', [
                'topic' => $topic,
                'status' => $searchResult['status'],
                'url' => $searchResult['url'] ?? null,
            ]);

            return ['evaluated' => 0, 'ingested' => 0, 'attempted' => 0, 'errors' => 1, 'skipped' => 0];
        }

        if (empty($decisionIds)) {
            Log::info('No decisions found for topic (legitimate empty result)', [
                'topic' => $topic,
                'url' => $searchResult['url'] ?? null,
            ]);

            return ['evaluated' => 0, 'ingested' => 0, 'attempted' => 0, 'errors' => 0, 'skipped' => 0];
        }

        Log::info('Found decisions for topic', [
            'topic' => $topic,
            'count' => $foundCount,
            'search_url' => $searchResult['url'] ?? null,
        ]);

        // 3. Fetch metadata for all decisions
        $metadata = [];
        foreach ($decisionIds as $id) {
            try {
                $meta = $this->getClient()->fetchDecisionMeta($id);
                if ($meta) {
                    $metadata[$id] = $meta;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch metadata', [
                    'decision_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($metadata)) {
            Log::warning('No metadata fetched for topic', ['topic' => $topic]);

            return ['evaluated' => 0, 'ingested' => 0, 'attempted' => 0, 'errors' => 0, 'skipped' => 0];
        }

        // 4. Score decisions for relevance
        $scored = $this->scoreDecisions($metadata, $topic);

        // 5. Filter by threshold
        $topDecisions = array_filter($scored, function ($item) {
            return $item['score'] >= $this->relevanceThreshold;
        });

        if (empty($topDecisions)) {
            Log::info('No decisions met relevance threshold', [
                'topic' => $topic,
                'threshold' => $this->relevanceThreshold,
                'evaluated' => count($scored),
            ]);

            return ['evaluated' => count($metadata), 'ingested' => 0, 'attempted' => 0, 'errors' => 0, 'skipped' => 0];
        }

        // 6. Sort by score and take top N
        usort($topDecisions, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Determine how many to ingest: minimum of ingestPerTopic and maxIngest (if set)
        $ingestCount = $this->ingestPerTopic;
        if ($maxIngest !== null) {
            $ingestCount = min($ingestCount, $maxIngest);
        }

        $topDecisions = array_slice($topDecisions, 0, $ingestCount);

        $topIds = array_column($topDecisions, 'id');

        Log::info('Ingesting top decisions', [
            'topic' => $topic,
            'count' => count($topIds),
            'threshold' => $this->relevanceThreshold,
            'top_scores' => array_column(array_slice($topDecisions, 0, 3), 'score'),
        ]);

        // 7. Ingest selected decisions
        $ingestResult = ['inserted' => 0, 'errors' => 0, 'skipped' => 0];

        if (! empty($topIds)) {
            $ingestResult = $this->getIngest()->ingestByIds($topIds, [
                'sync_graph' => true,
                'chunk_chars' => 1500,
                'overlap' => 200,
            ]);

            Log::info('Ingestion completed', [
                'topic' => $topic,
                'attempted' => count($topIds),
                'inserted' => $ingestResult['inserted'] ?? 0,
                'errors' => $ingestResult['errors'] ?? 0,
                'skipped' => $ingestResult['skipped'] ?? 0,
            ]);
        }

        return [
            'found' => $foundCount,
            'evaluated' => count($metadata),
            'ingested' => $ingestResult['inserted'] ?? 0,
            'attempted' => count($topIds),
            'errors' => $ingestResult['errors'] ?? 0,
            'skipped' => $ingestResult['skipped'] ?? 0,
        ];
    }

    /**
     * Translate Croatian legal topic to search query
     */
    protected function translateTopicToQuery(string $topic): string
    {
        $normalized = trim(preg_replace('~\s+~u', ' ', $topic));
        if ($normalized === '') {
            return $topic;
        }

        // Always include the original topic as the primary phrase
        $phrases = [$normalized];

        // Heuristic synonym and reformulation expansion for common Croatian legal topics
        $lower = mb_strtolower($normalized);

        // Generic legal qualifiers
        if (str_contains($lower, 'nezakonit')) {
            $phrases[] = str_replace('nezakonit', 'protuzakonit', $lower);
            $phrases[] = str_replace('nezakonit', 'protupravan', $lower);
        }

        // Employment law (Zakon o radu) - terminations
        if (str_contains($lower, 'otkaz')) {
            $phrases[] = 'otkaz ugovora o radu';
            $phrases[] = 'raskid ugovora o radu';
            $phrases[] = 'prestanak radnog odnosa';
            $phrases[] = 'Zakon o radu';
        }

        // Contract law
        if (str_contains($lower, 'ugovorna odgovornost')) {
            $phrases[] = 'povreda ugovorne obveze';
            $phrases[] = 'odgovornost za štetu';
            $phrases[] = 'naknada štete';
            $phrases[] = 'Zakon o obveznim odnosima';
        }
        if (str_contains($lower, 'ugovor')) {
            $phrases[] = 'povreda ugovorne obveze';
            $phrases[] = 'neispunjenje ugovora';
        }

        // Consumer protection
        if (str_contains($lower, 'potroša')) { // potrošač / potrošačka
            $phrases[] = 'zaštita potrošača';
            $phrases[] = 'Zakon o zaštiti potrošača';
        }

        // Property disputes
        if (str_contains($lower, 'vlasni') || str_contains($lower, 'posjed')) {
            $phrases[] = 'pravo vlasništva';
            $phrases[] = 'smetanje posjeda';
            $phrases[] = 'vlasničkopravni spor';
        }

        // Criminal procedure (common for discovery use-cases)
        if (str_contains($lower, 'pretres') || str_contains($lower, 'pretraga')) {
            $phrases[] = 'Zakon o kaznenom postupku';
            $phrases[] = 'ZKP čl. 215';
            $phrases[] = 'ZKP čl. 217';
        }

        // Try LLM-based query rewriting (cached inside service); non-fatal on errors
        try {
            $variants = $this->getRewriter()->rewrite($normalized, 'hr');
            foreach ($variants as $v) {
                if (is_string($v)) {
                    $v = trim($v);
                }
                if (is_string($v) && $v !== '' && mb_strtolower($v) !== $lower) {
                    $phrases[] = $v;
                }
            }
        } catch (\Throwable $e) {
            // ignore – heuristics already provide improvements
        }

        // De-duplicate, keep order (original first)
        $unique = [];
        foreach ($phrases as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (! in_array($p, $unique, true)) {
                $unique[] = $p;
            }
        }

        // Limit the breadth to avoid overly long queries
        $unique = array_slice($unique, 0, 8);

        // Build quoted OR expression accepted by odluke.sudovi.hr
        $quoted = array_map(fn ($p) => '"'.$p.'"', $unique);

        return implode(' OR ', $quoted);
    }

    /**
     * Score decisions using LLM
     * Returns array of ['id' => '...', 'score' => 0-100, 'reasoning' => '...']
     */
    protected function scoreDecisions(array $metadata, string $topic): array
    {
        if (empty($metadata)) {
            return [];
        }

        // Sprint 2.5: Start reasoning trace for topic scoring
        $topicTraceId = $this->getTraceService()->startTrace(
            'score_decisions_for_topic',
            ['topic' => $topic, 'decision_count' => count($metadata)],
            null,
            'decision_discovery_agent',
            'topic_scoring'
        );

        Log::info('Scoring decisions via LLM', [
            'topic' => $topic,
            'count' => count($metadata),
        ]);

        // Batch decisions into groups of 10 for LLM scoring
        $batches = array_chunk($metadata, 10, true);
        $scored = [];

        foreach ($batches as $batchIndex => $batch) {
            Log::debug('Scoring batch', [
                'topic' => $topic,
                'batch' => $batchIndex + 1,
                'total_batches' => count($batches),
                'batch_size' => count($batch),
            ]);

            $batchScored = $this->scoreBatch($batch, $topic, $topicTraceId);
            $scored = array_merge($scored, $batchScored);
        }

        Log::info('Decision scoring complete', [
            'topic' => $topic,
            'scored_count' => count($scored),
        ]);

        // Sprint 2.5: End topic scoring trace
        $scoredCount = count($scored);
        $avgScore = $scoredCount > 0 ? array_sum(array_column($scored, 'score')) / $scoredCount : 0;
        $this->getTraceService()->endTrace(
            $topicTraceId,
            ['scored_count' => $scoredCount, 'average_score' => $avgScore],
            "Scored $scoredCount decisions for topic '$topic' with average score ".round($avgScore, 2),
            0.85
        );

        return $scored;
    }

    /**
     * Score a batch of decisions
     *
     * Sprint 2.5: Added $parentTraceId parameter for nested tracing
     */
    protected function scoreBatch(array $batch, string $topic, ?string $parentTraceId = null): array
    {
        // Sprint 2.5: Start batch scoring trace
        $batchTraceId = $this->getTraceService()->startTrace(
            'score_batch',
            ['batch_size' => count($batch), 'topic' => $topic],
            $parentTraceId,
            'decision_discovery_agent',
            'batch_scoring'
        );
        // Format batch for LLM
        $formatted = "Topic: {$topic}\n\nDecisions to score:\n\n";

        foreach ($batch as $id => $meta) {
            $formatted .= "ID: {$id}\n";
            $formatted .= 'Title: '.($meta['title'] ?? 'N/A')."\n";
            $formatted .= 'Court: '.($meta['court'] ?? 'N/A')."\n";
            $formatted .= 'Date: '.($meta['date'] ?? 'N/A')."\n";
            $formatted .= 'Type: '.($meta['type'] ?? 'N/A')."\n";
            $formatted .= 'Description: '.substr($meta['description'] ?? '', 0, 200)."...\n\n";
        }

        $prompt = <<<PROMPT
Score each court decision for relevance to the topic on a scale of 0-100.

SCORING GUIDELINES:
- 90-100: Highly relevant, directly addresses topic, from authoritative court
- 70-89: Relevant, related to topic, useful precedent
- 50-69: Somewhat relevant, tangentially related
- 0-49: Not relevant or low quality

Consider:
- Relevance to topic
- Court authority (Vrhovni sud > Županijski > Općinski)
- Decision type (Presuda > Rješenje > other)
- Recency (newer decisions preferred)

{$formatted}

Respond with JSON object containing a "scores" array:
{{"scores": [
  {{"id": "...", "score": 85, "reasoning": "Highly relevant Supreme Court ruling on topic X"}},
  ...
]}}
PROMPT;

        try {
            $response = $this->getOpenai()->chat([
                ['role' => 'system', 'content' => 'You are a Croatian legal expert evaluating court decisions.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3, // Lower for consistent scoring
            ]);

            $content = $response['choices'][0]['message']['content'] ?? null;

            if (! $content) {
                throw new \Exception('Empty response from LLM');
            }

            $data = json_decode($content, true);

            $scores = $data['scores'] ?? [];

            if (empty($scores)) {
                throw new \Exception('LLM returned no scores');
            }

            // Sprint 2.5 & 5.1: Process each score - create traces and flag learning opportunities
            foreach ($scores as $scoreData) {
                $scoreValue = $scoreData['score'] ?? 50;
                $confidence = $this->calculateConfidenceFromScore($scoreValue);

                // Sprint 2.5: Create individual decision traces
                $decisionTraceId = $this->getTraceService()->startTrace(
                    'score_decision',
                    [
                        'decision_id' => $scoreData['id'],
                        'topic' => $topic,
                        'metadata' => $batch[$scoreData['id']] ?? null,
                    ],
                    $batchTraceId,
                    'decision_discovery_agent',
                    'decision_scoring'
                );

                $this->getTraceService()->endTrace(
                    $decisionTraceId,
                    [
                        'decision_id' => $scoreData['id'],
                        'score' => $scoreValue,
                        'topic' => $topic,
                    ],
                    $scoreData['reasoning'] ?? 'No reasoning provided',
                    $confidence
                );

                // Sprint 5.1: Flag low-confidence scores as learning opportunities
                if ($scoreValue < $this->relevanceThreshold) {
                    $normalizedConfidence = $scoreValue / 100; // Normalize to 0-1
                    $sourceId = crc32($scoreData['id'] ?? 'unknown');

                    $this->getLearningService()->identifyLearningOpportunity(
                        opportunityType: 'decision_discovery',
                        sourceType: 'decision_score',
                        sourceId: $sourceId,
                        aiOutput: [
                            'id' => $scoreData['id'] ?? 'unknown',
                            'score' => $scoreValue,
                            'reasoning' => $scoreData['reasoning'] ?? '',
                            'topic' => $topic,
                        ],
                        confidence: $normalizedConfidence,
                        threshold: $this->relevanceThreshold / 100,
                        uncertaintyReason: "Low confidence score: {$normalizedConfidence} (threshold: {$this->relevanceThreshold})"
                    );
                }
            }

            // Sprint 2.5: End batch trace
            $batchSize = count($batch);
            $this->getTraceService()->endTrace(
                $batchTraceId,
                ['scores' => $scores],
                "Scored batch of $batchSize decisions",
                0.85
            );

            return $scores;

        } catch (\Exception $e) {
            Log::error('Decision scoring failed', [
                'topic' => $topic,
                'batch_size' => count($batch),
                'error' => $e->getMessage(),
            ]);

            // Fallback: score all at 50 (neutral)
            $fallback = [];
            foreach ($batch as $id => $meta) {
                $fallback[] = [
                    'id' => $id,
                    'score' => 50.0,
                    'reasoning' => 'Scoring failed, using neutral score',
                ];

                // Sprint 2.5: Create trace for fallback score
                $fallbackTraceId = $this->getTraceService()->startTrace(
                    'score_decision',
                    ['decision_id' => $id, 'topic' => $topic],
                    $batchTraceId,
                    'decision_discovery_agent',
                    'decision_scoring'
                );

                $this->getTraceService()->endTrace(
                    $fallbackTraceId,
                    ['decision_id' => $id, 'score' => 50.0, 'topic' => $topic],
                    'Scoring failed, using neutral score: '.$e->getMessage(),
                    0.3
                );
            }

            // Sprint 2.5: End batch trace with error
            $this->getTraceService()->endTrace(
                $batchTraceId,
                ['error' => $e->getMessage()],
                "Batch scoring failed: {$e->getMessage()}",
                0.3
            );

            return $fallback;
        }
    }

    /**
     * Sprint 5.6: Calculate calibrated confidence using multi-factor approach
     *
     * Uses ConfidenceCalibrator service for sophisticated confidence calculation
     *
     * @param  float  $score  Decision score (0-100)
     * @param  array  $context  Additional context for factor calculation
     * @return float Calibrated confidence (0-1)
     */
    protected function calculateConfidenceFromScore(float $score, array $context = []): float
    {
        /** @var \App\Services\ConfidenceCalibrator $calibrator */
        $calibrator = app(\App\Services\ConfidenceCalibrator::class);

        // Prepare factors for calibration
        $factors = [
            // Citation count: based on number of related decisions (if available)
            'citation_count' => $context['citation_count'] ?? max(0, (int) ($score / 10)),

            // Citation quality: derived from score quality
            'citation_quality' => $score / 100,

            // Data freshness: use context or default to reasonable value
            'data_freshness' => $context['data_freshness'] ?? 30,

            // Consensus score: if multiple sources agree
            'consensus_score' => $context['consensus_score'] ?? ($score / 100),

            // LLM confidence: simple score-based confidence
            'llm_confidence' => $this->simpleLLMConfidence($score),
        ];

        try {
            $result = $calibrator->calculate($factors);

            return $result['confidence'];
        } catch (\Exception $e) {
            // Fallback to simple confidence calculation
            return $this->simpleLLMConfidence($score);
        }
    }

    /**
     * Sprint 5.6: Simple LLM confidence for fallback
     *
     * @param  float  $score  Decision score (0-100)
     * @return float Simple confidence (0-1)
     */
    protected function simpleLLMConfidence(float $score): float
    {
        // Map 0-100 score to 0-1 confidence
        if ($score >= 90) {
            return 0.9 + (($score - 90) / 100);
        } elseif ($score >= 70) {
            return 0.7 + (($score - 70) / 100);
        } elseif ($score >= 50) {
            return 0.5 + (($score - 50) / 100);
        } else {
            return 0.3 + ($score / 250);
        }
    }

    /**
     * Configuration setters
     */
    public function setTopicsPerRun(int $count): self
    {
        $this->topicsPerRun = $count;

        return $this;
    }

    public function setDecisionsPerTopic(int $count): self
    {
        $this->decisionsPerTopic = $count;

        return $this;
    }

    public function setIngestPerTopic(int $count): self
    {
        $this->ingestPerTopic = $count;

        return $this;
    }

    public function setRelevanceThreshold(float $threshold): self
    {
        $this->relevanceThreshold = $threshold;

        return $this;
    }

    public function setMaxDecisionsGlobal(?int $max): self
    {
        $this->maxDecisionsGlobal = $max;

        return $this;
    }

    /**
     * Configuration getters
     */
    public function getTopicsPerRun(): int
    {
        return $this->topicsPerRun;
    }

    public function getDecisionsPerTopic(): int
    {
        return $this->decisionsPerTopic;
    }

    public function getIngestPerTopic(): int
    {
        return $this->ingestPerTopic;
    }

    public function getRelevanceThreshold(): float
    {
        return $this->relevanceThreshold;
    }

    /**
     * Discover court decisions with environment-aware execution.
     */
    public static function discoverWithEnvDetection(?int $maxDecisions = null, ?string $topic = null): mixed
    {
        return \App\Jobs\ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection($maxDecisions, $topic);
    }

    /**
     * Check if discovery job is currently running.
     */
    public static function isRunning(): bool
    {
        $recentRun = DB::table('decision_discovery_runs')
            ->where('status', 'running')
            ->where('created_at', '>', now()->subHours(2))
            ->exists();

        return $recentRun;
    }

    /**
     * Get latest discovery run statistics.
     */
    public static function getLatestRunStats(): ?array
    {
        $run = DB::table('decision_discovery_runs')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        return $run ? [
            'completed_at' => $run->completed_at,
            'decisions_found' => $run->decisions_found,
            'decisions_ingested' => $run->decisions_ingested,
            'topics_generated' => $run->topics_generated,
            'duration_seconds' => $run->duration_seconds,
        ] : null;
    }
}
