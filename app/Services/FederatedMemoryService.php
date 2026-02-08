<?php

namespace App\Services;

use App\Models\AgentVectorMemory;
use App\Services\AI\OpenAIEmbeddingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Federated Memory Service
 *
 * Sprint 5.7: Agent Memory Federation
 *
 * Enables agents to search and share insights across all agent memories:
 * - Search across all agent memories or filter by agent type
 * - Store new insights with embeddings
 * - Track memory access (increment access_count)
 * - Cross-agent memory sharing
 * - Performance optimized for <100ms searches
 */
class FederatedMemoryService
{
    protected OpenAIEmbeddingService $embeddingService;

    public function __construct(OpenAIEmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Search across agent memories
     *
     * @param  string  $query  Search query
     * @param  string|null  $agentType  Filter by specific agent type (null = all agents)
     * @param  int  $limit  Maximum results to return
     * @return array Array of matching memories
     *
     * @throws InvalidArgumentException
     */
    public function searchCrossAgent(string $query, ?string $agentType = null, int $limit = 10): array
    {
        // Validate query
        if (empty(trim($query))) {
            throw new InvalidArgumentException('Search query cannot be empty');
        }

        // Try pgvector similarity search first (if available)
        $results = $this->searchWithVectorSimilarity($query, $agentType, $limit);

        // If pgvector search returned results, use them
        if (! empty($results)) {
            return $results;
        }

        // Fall back to text search if pgvector unavailable or returned no results
        // Build base query
        $queryBuilder = AgentVectorMemory::query();

        // Filter by agent type if specified
        if ($agentType !== null && ! empty($agentType)) {
            $queryBuilder->where('agent_name', $agentType);
        }

        // Use simple text search for performance
        // This keeps search under 100ms even without pgvector
        $queryBuilder->where(function ($q) use ($query) {
            $searchTerms = explode(' ', strtolower($query));

            foreach ($searchTerms as $term) {
                if (! empty($term)) {
                    $q->orWhere('content', 'ILIKE', "%{$term}%");
                }
            }
        });

        // Order by most recently created (for deterministic results)
        $queryBuilder->orderBy('created_at', 'desc');

        // Limit results
        $queryBuilder->limit($limit);

        // Execute query
        $memories = $queryBuilder->get();

        // Increment access count for retrieved memories
        $memoryIds = $memories->pluck('id')->toArray();

        if (! empty($memoryIds)) {
            AgentVectorMemory::whereIn('id', $memoryIds)
                ->increment('access_count');
        }

        // Return as array
        return $memories->map(function ($memory) {
            return [
                'id' => $memory->id,
                'agent_name' => $memory->agent_name,
                'content' => $memory->content,
                'metadata' => $memory->metadata ?? [],
                'access_count' => ($memory->access_count ?? 0) + 1, // +1 for current access
                'created_at' => $memory->created_at,
            ];
        })->toArray();
    }

    /**
     * Store new insight to agent memory
     *
     * @param  string  $agentType  Agent type storing the insight
     * @param  string  $content  Insight content
     * @param  array  $metadata  Additional metadata
     * @return string Memory ID
     *
     * @throws InvalidArgumentException
     */
    public function storeInsight(string $agentType, string $content, array $metadata = []): string
    {
        // Validate agent type
        if (empty(trim($agentType))) {
            throw new InvalidArgumentException('Agent type cannot be empty');
        }

        // Validate content
        if (empty(trim($content))) {
            throw new InvalidArgumentException('Content cannot be empty');
        }

        // Generate embedding for the content
        try {
            $embedding = $this->embeddingService->embed($content);
        } catch (\Exception $e) {
            // Fallback to zero vector if embedding fails
            $embedding = array_fill(0, 1536, 0.0);
        }

        // Extract namespace from metadata if provided
        $namespace = $metadata['namespace'] ?? 'default';
        // Keep namespace in metadata for backward compatibility

        // Create memory record
        $memory = AgentVectorMemory::create([
            'agent_name' => $agentType,
            'namespace' => $namespace,
            'content' => $content,
            'metadata' => $metadata,
            'embedding_vector' => $embedding,
            'access_count' => 0,
        ]);

        return $memory->id;
    }

    /**
     * Get access statistics for agent memories
     *
     * @param  string|null  $agentType  Filter by specific agent type
     * @return array Statistics
     */
    public function getAccessStatistics(?string $agentType = null): array
    {
        $query = AgentVectorMemory::query();

        if ($agentType !== null) {
            $query->where('agent_name', $agentType);
        }

        $totalMemories = $query->count();
        $totalAccesses = $query->sum('access_count');

        // Get most accessed memories
        $mostAccessed = AgentVectorMemory::query()
            ->when($agentType !== null, fn ($q) => $q->where('agent_name', $agentType))
            ->orderBy('access_count', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'agent_name' => $m->agent_name,
                'content' => $m->content,
                'access_count' => $m->access_count,
            ])
            ->toArray();

        // Get breakdown by agent type
        $byAgent = AgentVectorMemory::query()
            ->when($agentType !== null, fn ($q) => $q->where('agent_name', $agentType))
            ->select('agent_name', DB::raw('COUNT(*) as count'), DB::raw('SUM(access_count) as total_accesses'))
            ->groupBy('agent_name')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->agent_name => [
                    'count' => $item->count,
                    'total_accesses' => $item->total_accesses ?? 0,
                ],
            ])
            ->toArray();

        return [
            'total_memories' => $totalMemories,
            'total_accesses' => $totalAccesses ?? 0,
            'most_accessed' => $mostAccessed,
            'by_agent' => $byAgent,
        ];
    }

    /**
     * Search with vector similarity (if pgvector is available)
     *
     * Uses pgvector's cosine similarity operator (<=>)  to find semantically similar memories.
     * Falls back gracefully if pgvector is not available.
     *
     * @param  string  $query  Search query
     * @param  string|null  $agentType  Filter by agent type
     * @param  int  $limit  Result limit
     * @return array Matching memories (ordered by similarity)
     */
    protected function searchWithVectorSimilarity(string $query, ?string $agentType, int $limit): array
    {
        // Check if pgvector extension is available
        try {
            $hasExtension = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
            if (empty($hasExtension)) {
                return []; // Graceful fallback - return empty to trigger text search
            }
        } catch (\Exception $e) {
            return []; // Graceful fallback on error
        }

        // Generate query embedding
        try {
            $queryEmbedding = $this->embeddingService->embed($query);
        } catch (\Exception $e) {
            return []; // Graceful fallback if embedding fails
        }

        // Convert embedding array to pgvector format string
        $embeddingStr = '['.implode(',', $queryEmbedding).']';

        // Build SQL query with pgvector cosine similarity
        $sql = '
            SELECT *, (embedding_vector <=> ?) as distance
            FROM agent_vector_memories
        ';

        $bindings = [$embeddingStr];

        // Add agent filter if specified
        if ($agentType !== null && ! empty($agentType)) {
            $sql .= ' WHERE agent_name = ?';
            $bindings[] = $agentType;
        }

        // Order by distance (lower is more similar) and limit results
        $sql .= ' ORDER BY distance ASC LIMIT ?';
        $bindings[] = $limit;

        // Execute query
        try {
            $results = DB::select($sql, $bindings);
        } catch (\Exception $e) {
            return []; // Graceful fallback on query error
        }

        // Increment access count for retrieved memories
        $memoryIds = array_column($results, 'id');
        if (! empty($memoryIds)) {
            AgentVectorMemory::whereIn('id', $memoryIds)
                ->increment('access_count');
        }

        // Transform results to match expected format
        return array_map(function ($memory) {
            return [
                'id' => $memory->id,
                'agent_name' => $memory->agent_name,
                'content' => $memory->content,
                'metadata' => json_decode($memory->metadata, true) ?? [],
                'access_count' => ($memory->access_count ?? 0) + 1,
                'created_at' => $memory->created_at,
                'distance' => $memory->distance ?? null, // Include similarity score
            ];
        }, $results);
    }
}
