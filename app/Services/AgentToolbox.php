<?php

namespace App\Services;

use App\Contracts\Services\AgentToolboxInterface;
use App\Exceptions\AgentException;
use App\Models\AgentVectorMemory;
use App\Models\Law;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AgentToolbox provides a comprehensive suite of tools for autonomous agents
 * to research legal topics, query databases, and save insights.
 */
class AgentToolbox implements AgentToolboxInterface
{
    public function __construct(
        protected OpenAIService $openai,
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Vector search across laws, cases, and court decisions
     *
     * @deprecated Use LawSearchService, CaseSearchService, or DecisionSearchService directly
     *
     * @param  string  $query  The search query
     * @param  array  $options  Search options:
     *                          - types: array of 'laws', 'cases', 'decisions' (default: all)
     *                          - limit: max results per type (default: 10)
     *                          - jurisdiction: filter by jurisdiction
     *                          - min_similarity: minimum cosine similarity (default: 0.7)
     * @return array Search results grouped by type
     *
     * @throws AgentException if search fails
     */
    public function vectorSearch(string $query, array $options = []): array
    {
        try {
            Log::info('Agent toolbox vector search initiated', [
                'query' => substr($query, 0, 100),
                'types' => $options['types'] ?? ['laws', 'cases', 'decisions'],
            ]);

            $startTime = microtime(true);
            $types = $options['types'] ?? ['laws', 'cases', 'decisions'];
            $results = [];

            // Search laws using LawSearchService
            if (in_array('laws', $types)) {
                try {
                    $lawService = app(LawSearchService::class);
                    $lawResults = $lawService->vectorSearch($query, $options);
                    $results['laws'] = $lawResults['data'] ?? [];
                } catch (\Exception $e) {
                    Log::warning('Law search failed in agent toolbox', ['error' => $e->getMessage()]);
                    $results['laws'] = [];
                }
            }

            // Search cases using CaseSearchService
            if (in_array('cases', $types)) {
                try {
                    $caseService = app(CaseSearchService::class);
                    $caseResults = $caseService->vectorSearch($query, $options);
                    $results['cases'] = $caseResults['data'] ?? [];
                } catch (\Exception $e) {
                    Log::warning('Case search failed in agent toolbox', ['error' => $e->getMessage()]);
                    $results['cases'] = [];
                }
            }

            // Search court decisions using DecisionSearchService
            if (in_array('decisions', $types)) {
                try {
                    $decisionService = app(DecisionSearchService::class);
                    $decisionResults = $decisionService->vectorSearch($query, $options);
                    $results['decisions'] = $decisionResults['data'] ?? [];
                } catch (\Exception $e) {
                    Log::warning('Decision search failed in agent toolbox', ['error' => $e->getMessage()]);
                    $results['decisions'] = [];
                }
            }

            Log::info('Agent toolbox vector search completed', [
                'result_counts' => array_map('count', $results),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Agent toolbox vector search failed', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Vector search failed: '.$e->getMessage(),
                AgentException::SEARCH_FAILED,
                $e
            );
        }
    }

    /**
     * Search laws by vector similarity
     *
     * @deprecated Use LawSearchService::vectorSearch() instead
     */
    private function searchLaws(array $queryVector, int $limit, ?string $jurisdiction, float $minSimilarity): array
    {
        $driver = DB::connection()->getDriverName();
        $query = DB::table('laws')
            ->select([
                'id',
                'doc_id',
                'title',
                'law_number',
                'jurisdiction',
                'content',
                'chunk_index',
                'metadata',
                'effective_date',
                'promulgation_date',
            ]);

        if ($jurisdiction) {
            $query->where('jurisdiction', $jurisdiction);
        }

        if ($driver === 'pgsql') {
            $vectorLiteral = $this->toPgVectorLiteral($queryVector);
            $query->selectRaw("1 - (embedding <=> '{$vectorLiteral}'::vector) as similarity")
                ->whereRaw("1 - (embedding <=> '{$vectorLiteral}'::vector) >= ?", [$minSimilarity])
                ->orderByRaw("embedding <=> '{$vectorLiteral}'::vector")
                ->limit($limit);
        } else {
            // Fallback for non-PostgreSQL databases
            $query->whereNotNull('embedding')->limit($limit * 3);
        }

        $results = $query->get()->map(function ($law) use ($queryVector, $driver) {
            if ($driver !== 'pgsql') {
                $lawVector = json_decode($law->embedding_vector ?? '[]', true);
                $law->similarity = $this->cosineSimilarity($queryVector, $lawVector);
            }
            $law->metadata = json_decode($law->metadata ?? '{}', true);

            return $law;
        });

        if ($driver !== 'pgsql') {
            $results = $results->filter(fn ($r) => $r->similarity >= $minSimilarity)
                ->sortByDesc('similarity')
                ->take($limit)
                ->values();
        }

        return $results->toArray();
    }

    /**
     * Search case documents by vector similarity
     *
     * @deprecated Use CaseSearchService::vectorSearch() instead
     */
    private function searchCases(array $queryVector, int $limit, ?string $jurisdiction, float $minSimilarity): array
    {
        $driver = DB::connection()->getDriverName();
        $query = DB::table('cases_documents')
            ->select([
                'cases_documents.id',
                'cases_documents.case_id',
                'cases_documents.doc_id',
                'cases_documents.title',
                'cases_documents.content',
                'cases_documents.chunk_index',
                'cases_documents.metadata',
                'cases.case_number',
                'cases.jurisdiction',
                'cases.court',
                'cases.status',
            ])
            ->leftJoin('cases', 'cases_documents.case_id', '=', 'cases.id');

        if ($jurisdiction) {
            $query->where('cases.jurisdiction', $jurisdiction);
        }

        if ($driver === 'pgsql') {
            $vectorLiteral = $this->toPgVectorLiteral($queryVector);
            $query->selectRaw("1 - (cases_documents.embedding <=> '{$vectorLiteral}'::vector) as similarity")
                ->whereRaw("1 - (cases_documents.embedding <=> '{$vectorLiteral}'::vector) >= ?", [$minSimilarity])
                ->orderByRaw("cases_documents.embedding <=> '{$vectorLiteral}'::vector")
                ->limit($limit);
        } else {
            $query->whereNotNull('cases_documents.embedding_vector')->limit($limit * 3);
        }

        $results = $query->get()->map(function ($case) use ($queryVector, $driver) {
            if ($driver !== 'pgsql') {
                $caseVector = json_decode($case->embedding_vector ?? '[]', true);
                $case->similarity = $this->cosineSimilarity($queryVector, $caseVector);
            }
            $case->metadata = json_decode($case->metadata ?? '{}', true);

            return $case;
        });

        if ($driver !== 'pgsql') {
            $results = $results->filter(fn ($r) => $r->similarity >= $minSimilarity)
                ->sortByDesc('similarity')
                ->take($limit)
                ->values();
        }

        return $results->toArray();
    }

    /**
     * Search court decisions by vector similarity
     *
     * @deprecated Use DecisionSearchService::vectorSearch() instead
     */
    private function searchDecisions(array $queryVector, int $limit, ?string $jurisdiction, float $minSimilarity): array
    {
        $driver = DB::connection()->getDriverName();
        $query = DB::table('court_decision_documents')
            ->select([
                'court_decision_documents.id',
                'court_decision_documents.decision_id',
                'court_decision_documents.doc_id',
                'court_decision_documents.title',
                'court_decision_documents.content',
                'court_decision_documents.chunk_index',
                'court_decision_documents.metadata',
                'court_decisions.case_number',
                'court_decisions.court',
                'court_decisions.jurisdiction',
                'court_decisions.decision_date',
                'court_decisions.decision_type',
            ])
            ->leftJoin('court_decisions', 'court_decision_documents.decision_id', '=', 'court_decisions.id');

        if ($jurisdiction) {
            $query->where('court_decisions.jurisdiction', $jurisdiction);
        }

        if ($driver === 'pgsql') {
            $vectorLiteral = $this->toPgVectorLiteral($queryVector);
            $query->selectRaw("1 - (court_decision_documents.embedding <=> '{$vectorLiteral}'::vector) as similarity")
                ->whereRaw("1 - (court_decision_documents.embedding <=> '{$vectorLiteral}'::vector) >= ?", [$minSimilarity])
                ->orderByRaw("court_decision_documents.embedding <=> '{$vectorLiteral}'::vector")
                ->limit($limit);
        } else {
            $query->whereNotNull('court_decision_documents.embedding_vector')->limit($limit * 3);
        }

        $results = $query->get()->map(function ($decision) use ($queryVector, $driver) {
            if ($driver !== 'pgsql') {
                $decisionVector = json_decode($decision->embedding_vector ?? '[]', true);
                $decision->similarity = $this->cosineSimilarity($queryVector, $decisionVector);
            }
            $decision->metadata = json_decode($decision->metadata ?? '{}', true);

            return $decision;
        });

        if ($driver !== 'pgsql') {
            $results = $results->filter(fn ($r) => $r->similarity >= $minSimilarity)
                ->sortByDesc('similarity')
                ->take($limit)
                ->values();
        }

        return $results->toArray();
    }

    /**
     * Look up a specific law by law number and/or jurisdiction
     *
     * @deprecated Use LawSearchService::lookupByNumber() directly
     *
     * @param  string  $lawNumber  The law number (e.g., "NN 94/14")
     * @param  string|null  $jurisdiction  Optional jurisdiction filter
     * @return array Law information including all chunks
     */
    public function lawLookup(string $lawNumber, ?string $jurisdiction = null): array
    {
        $lawService = app(LawSearchService::class);

        return $lawService->lookupByNumber($lawNumber, $jurisdiction);
    }

    /**
     * Look up court decisions by case number, court, or date range
     *
     * @deprecated Use DecisionSearchService::lookupByCriteria() directly
     *
     * @param  array  $criteria  Search criteria:
     *                           - case_number: exact or partial match
     *                           - court: court name
     *                           - jurisdiction: jurisdiction
     *                           - from_date: decision date from (Y-m-d)
     *                           - to_date: decision date to (Y-m-d)
     *                           - decision_type: type of decision
     *                           - limit: max results (default: 20)
     * @return array Court decisions matching criteria
     */
    public function decisionLookup(array $criteria): array
    {
        $decisionService = app(DecisionSearchService::class);

        return $decisionService->lookupByCriteria($criteria);
    }

    /**
     * Query the Neo4j graph database with Cypher
     *
     * @param  string  $cypher  Cypher query
     * @param  array  $parameters  Query parameters
     * @return array Query results
     *
     * @throws AgentException if graph query fails
     */
    public function graphQuery(string $cypher, array $parameters = []): array
    {
        try {
            Log::info('Agent toolbox graph query initiated', [
                'query' => substr($cypher, 0, 200),
                'param_count' => count($parameters),
            ]);

            $startTime = microtime(true);
            $result = $this->graph->run($cypher, $parameters);

            $rows = $this->convertGraphResultToArray($result);

            Log::info('Agent toolbox graph query completed', [
                'row_count' => count($rows),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'success' => true,
                'rows' => $rows,
                'count' => count($rows),
            ];

        } catch (\Exception $e) {
            Log::error('Agent toolbox graph query failed', [
                'query' => substr($cypher, 0, 200),
                'parameters' => $parameters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Graph query failed: '.$e->getMessage(),
                AgentException::GRAPH_QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Fetch content from a web URL
     *
     * @param  string  $url  URL to fetch
     * @param  array  $options  Fetch options:
     *                          - timeout: request timeout in seconds (default: 30)
     *                          - headers: additional headers
     *                          - method: HTTP method (default: GET)
     * @return array Response with content, status, and headers
     *
     * @throws AgentException if web fetch fails
     */
    public function webFetch(string $url, array $options = []): array
    {
        try {
            Log::info('Agent toolbox web fetch initiated', [
                'url' => $url,
                'method' => $options['method'] ?? 'GET',
            ]);

            $startTime = microtime(true);
            $timeout = (int) ($options['timeout'] ?? 30);
            $headers = $options['headers'] ?? [];
            $method = strtoupper($options['method'] ?? 'GET');

            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->send($method, $url);

            Log::info('Agent toolbox web fetch completed', [
                'url' => $url,
                'status' => $response->status(),
                'content_length' => strlen($response->body()),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'success' => true,
                'status' => $response->status(),
                'content' => $response->body(),
                'headers' => $response->headers(),
                'url' => $url,
            ];

        } catch (\Exception $e) {
            Log::error('Agent toolbox web fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Web fetch failed: '.$e->getMessage(),
                AgentException::WEB_FETCH_FAILED,
                $e
            );
        }
    }

    /**
     * Get recent insights from agent vector memory
     *
     * @param  string  $agentName  Name of the agent
     * @param  array  $filters  Filters:
     *                          - objective: filter by research objective (exact or partial match)
     *                          - namespace: filter by memory namespace (default: 'research_insights')
     *                          - source: filter by source
     *                          - limit: max results (default: 10)
     *                          - days: only include insights from last N days (default: 30)
     * @return array Array of insights with content, metadata, and timestamps
     *
     * @throws AgentException if memory retrieval fails
     */
    public function getRecentInsights(string $agentName, array $filters = []): array
    {
        try {
            Log::info('Agent toolbox retrieving recent insights', [
                'agent_name' => $agentName,
                'namespace' => $filters['namespace'] ?? 'research_insights',
            ]);

            $startTime = microtime(true);
            $namespace = $filters['namespace'] ?? 'research_insights';
            $objective = $filters['objective'] ?? null;
            $source = $filters['source'] ?? null;
            $limit = (int) ($filters['limit'] ?? 10);
            $days = (int) ($filters['days'] ?? 30);

            $query = AgentVectorMemory::where('agent_name', $agentName)
                ->where('namespace', $namespace);

            // Filter by objective if provided
            if ($objective) {
                // Use LIKE for partial matching to catch related objectives
                $query->where('objective', 'LIKE', '%'.$objective.'%');
            }

            // Filter by source if provided
            if ($source) {
                $query->where('source', $source);
            }

            // Only include recent insights
            if ($days > 0) {
                $query->where('created_at', '>=', now()->subDays($days));
            }

            // Order by recency and limit
            $memories = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $insights = $this->convertMemoriesToInsights($memories);

            Log::info('Agent toolbox retrieved recent insights', [
                'agent_name' => $agentName,
                'objective' => $objective,
                'namespace' => $namespace,
                'count' => count($insights),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'success' => true,
                'insights' => $insights,
                'count' => count($insights),
            ];

        } catch (\Exception $e) {
            Log::error('Agent toolbox failed to retrieve recent insights', [
                'agent_name' => $agentName,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Failed to retrieve agent memory: '.$e->getMessage(),
                AgentException::MEMORY_RETRIEVAL_FAILED,
                $e
            );
        }
    }

    /**
     * Save an insight or note to agent vector memory
     *
     * @param  string  $agentName  Name of the agent
     * @param  string  $content  Content to save
     * @param  array  $options  Save options:
     *                          - namespace: memory namespace (default: 'insights')
     *                          - metadata: additional metadata
     *                          - source: source reference
     *                          - source_id: source identifier
     *                          - objective: research objective (for filtering)
     * @return array Save result with memory ID
     *
     * @throws AgentException if memory save fails
     */
    public function noteSave(string $agentName, string $content, array $options = []): array
    {
        try {
            Log::info('Agent toolbox saving note to memory', [
                'agent_name' => $agentName,
                'namespace' => $options['namespace'] ?? 'insights',
                'content_length' => strlen($content),
            ]);

            $startTime = microtime(true);
            $namespace = $options['namespace'] ?? 'insights';
            $metadata = $options['metadata'] ?? [];
            $source = $options['source'] ?? 'self_study';
            $sourceId = $options['source_id'] ?? null;
            $objective = $options['objective'] ?? ($metadata['objective'] ?? null);

            // Check for duplicate before generating embedding
            $contentHash = hash('sha256', $content);
            $existing = AgentVectorMemory::where('agent_name', $agentName)
                ->where('content_hash', $contentHash)
                ->first();

            if ($existing) {
                Log::info('Agent toolbox note already exists', [
                    'agent_name' => $agentName,
                    'memory_id' => $existing->id,
                ]);

                return [
                    'success' => true,
                    'id' => $existing->id,
                    'status' => 'already_exists',
                ];
            }

            // Generate embedding for the content
            $vector = $this->generateEmbeddingVector($content);

            // Create new memory
            $memory = $this->createAgentMemory(
                $agentName,
                $content,
                $vector,
                $namespace,
                $objective,
                $metadata,
                $source,
                $sourceId,
                $contentHash
            );

            Log::info('Agent toolbox saved note to memory', [
                'agent_name' => $agentName,
                'memory_id' => $memory->id,
                'namespace' => $namespace,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'success' => true,
                'id' => $memory->id,
                'status' => 'created',
            ];

        } catch (AgentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Agent toolbox failed to save note', [
                'agent_name' => $agentName,
                'content_length' => strlen($content),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Failed to save agent memory: '.$e->getMessage(),
                AgentException::MEMORY_SAVE_FAILED,
                $e
            );
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Convert vector to pgvector literal format
     */
    protected function toPgVectorLiteral(array $vec): string
    {
        $parts = [];
        foreach ($vec as $v) {
            $parts[] = rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
        }

        return '['.implode(',', $parts).']';
    }

    /**
     * Convert vector to pgvector cast literal
     */
    protected function toPgVectorCastLiteral(array $vec): string
    {
        return "'".$this->toPgVectorLiteral($vec)."'::vector";
    }

    /**
     * Calculate L2 norm of a vector
     */
    protected function norm(array $vec): float
    {
        $sum = 0.0;
        foreach ($vec as $v) {
            $sum += ($v * $v);
        }

        return sqrt($sum);
    }

    /**
     * Estimate token count from text
     */
    protected function estimateTokens(string $s): int
    {
        return (int) ceil(strlen($s) / 4);
    }

    /**
     * Convert Neo4j graph result to array
     */
    protected function convertGraphResultToArray($result): array
    {
        $rows = [];
        foreach ($result as $record) {
            $row = [];
            foreach ($record->keys() as $key) {
                $value = $record->get($key);

                // Convert Neo4j types to simple arrays
                if (is_object($value)) {
                    if (method_exists($value, 'toArray')) {
                        $row[$key] = $value->toArray();
                    } elseif (method_exists($value, 'getProperties')) {
                        $row[$key] = $value->getProperties();
                    } else {
                        $row[$key] = (array) $value;
                    }
                } else {
                    $row[$key] = $value;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Convert memory models to insight arrays
     */
    protected function convertMemoriesToInsights($memories): array
    {
        $insights = [];
        foreach ($memories as $memory) {
            $insights[] = [
                'id' => $memory->id,
                'content' => $memory->content,
                'objective' => $memory->objective,
                'metadata' => $memory->metadata,
                'source' => $memory->source,
                'source_id' => $memory->source_id,
                'created_at' => $memory->created_at?->toIso8601String(),
            ];
        }

        return $insights;
    }

    /**
     * Generate embedding vector for content
     *
     * @throws AgentException
     */
    protected function generateEmbeddingVector(string $content): array
    {
        try {
            $model = config('openai.models.embeddings');
            $embedding = $this->openai->embeddings([$content], $model);
            $vector = $embedding['data'][0]['embedding'] ?? null;

            if (! $vector) {
                throw new \Exception('OpenAI returned empty embedding vector');
            }

            return $vector;

        } catch (\Exception $e) {
            Log::error('Embedding generation failed', [
                'content_length' => strlen($content),
                'error' => $e->getMessage(),
            ]);

            throw new AgentException(
                'Failed to generate embedding: '.$e->getMessage(),
                AgentException::EMBEDDING_GENERATION_FAILED,
                $e
            );
        }
    }

    /**
     * Create agent memory record
     *
     * @throws AgentException
     */
    protected function createAgentMemory(
        string $agentName,
        string $content,
        array $vector,
        string $namespace,
        ?string $objective,
        array $metadata,
        string $source,
        ?string $sourceId,
        string $contentHash
    ): AgentVectorMemory {
        try {
            $model = config('openai.models.embeddings');
            $driver = DB::connection()->getDriverName();

            $memory = new AgentVectorMemory([
                'id' => (string) Str::ulid(),
                'agent_name' => $agentName,
                'namespace' => $namespace,
                'objective' => $objective,
                'content' => $content,
                'metadata' => $metadata,
                'source' => $source,
                'source_id' => $sourceId,
                'embedding_provider' => 'openai',
                'embedding_model' => $model,
                'embedding_dimensions' => count($vector),
                'embedding_norm' => $this->norm($vector),
                'content_hash' => $contentHash,
                'token_count' => $this->estimateTokens($content),
            ]);

            if ($driver === 'pgsql') {
                $memory->embedding = DB::raw($this->toPgVectorCastLiteral($vector));
            } else {
                $memory->embedding_vector = $vector;
            }

            $memory->save();

            return $memory;

        } catch (\Exception $e) {
            Log::error('Failed to create agent memory record', [
                'agent_name' => $agentName,
                'namespace' => $namespace,
                'error' => $e->getMessage(),
            ]);

            throw new AgentException(
                'Failed to create memory record: '.$e->getMessage(),
                AgentException::MEMORY_SAVE_FAILED,
                $e
            );
        }
    }
}
