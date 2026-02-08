<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Graph Embedding Service (Sprint 4.5)
 *
 * Manages Node2Vec graph embeddings for structural similarity search.
 * Wraps Python training script and provides Laravel interface.
 *
 * Features:
 * - Generate Node2Vec embeddings from Neo4j citation graph
 * - Store 128-dimensional embeddings in PostgreSQL
 * - Check embedding status for decisions
 * - Support for hybrid similarity (content + graph structure)
 *
 * Usage:
 *   $service = app(GraphEmbeddingService::class);
 *   $result = $service->generateEmbeddings();
 */
class GraphEmbeddingService
{
    /**
     * Cache whether pgvector is available
     */
    protected ?bool $pgvectorAvailable = null;

    /**
     * Generate graph embeddings by running Python Node2Vec training script
     *
     * @param  array  $options  Optional parameters for training
     * @return array Training result with status and metrics
     */
    public function generateEmbeddings(array $options = []): array
    {
        $startTime = microtime(true);

        Log::info('GraphEmbeddingService - Starting Node2Vec training');

        // Get database configuration
        $pgConfig = config('database.connections.pgsql');
        $neo4jConfig = config('neo4j');

        // Build Python command
        $pythonScript = base_path('scripts/train_graph_embeddings.py');

        if (! file_exists($pythonScript)) {
            Log::error('GraphEmbeddingService - Python script not found', ['path' => $pythonScript]);

            return [
                'success' => false,
                'error' => 'Python training script not found',
            ];
        }

        $command = [
            'python3',
            $pythonScript,
            '--neo4j-uri', $neo4jConfig['uri'] ?? 'bolt://localhost:7687',
            '--neo4j-user', $neo4jConfig['username'] ?? 'neo4j',
            '--neo4j-password', $neo4jConfig['password'] ?? '',
            '--pg-host', $pgConfig['host'] ?? 'localhost',
            '--pg-database', $pgConfig['database'] ?? 'ai_legal_war_machine',
            '--pg-user', $pgConfig['username'] ?? 'postgres',
            '--pg-password', $pgConfig['password'] ?? '',
        ];

        Log::info('GraphEmbeddingService - Executing Python training script');

        // Execute Python script
        $result = Process::timeout(600) // 10 minute timeout
            ->run(implode(' ', array_map('escapeshellarg', $command)));

        $duration = round((microtime(true) - $startTime) * 1000);

        if ($result->successful()) {
            // Parse output to get embedding count
            $output = $result->output();
            preg_match('/Nodes processed: (\d+)/', $output, $matches);
            $embeddingCount = isset($matches[1]) ? (int) $matches[1] : 0;

            Log::info('GraphEmbeddingService - Training completed successfully', [
                'embedding_count' => $embeddingCount,
                'duration_ms' => $duration,
            ]);

            return [
                'success' => true,
                'embedding_count' => $embeddingCount,
                'duration_ms' => $duration,
                'output' => $output,
            ];
        } else {
            Log::error('GraphEmbeddingService - Training failed', [
                'exit_code' => $result->exitCode(),
                'error' => $result->errorOutput(),
                'duration_ms' => $duration,
            ]);

            return [
                'success' => false,
                'error' => $result->errorOutput(),
                'exit_code' => $result->exitCode(),
                'duration_ms' => $duration,
            ];
        }
    }

    /**
     * Check if embeddings exist for a decision
     *
     * @param  string  $decisionId  Decision ID
     * @return bool True if embeddings exist
     */
    public function hasEmbedding(string $decisionId): bool
    {
        return DB::table('decision_graph_embeddings')
            ->where('decision_id', $decisionId)
            ->exists();
    }

    /**
     * Get embedding for a decision
     *
     * @param  string  $decisionId  Decision ID
     * @return array|null Embedding data or null if not found
     */
    public function getEmbedding(string $decisionId): ?array
    {
        $result = DB::table('decision_graph_embeddings')
            ->where('decision_id', $decisionId)
            ->first();

        if (! $result) {
            return null;
        }

        return [
            'decision_id' => $result->decision_id,
            'graph_embedding' => $result->graph_embedding,
            'trained_at' => $result->trained_at,
            'model_version' => $result->model_version,
        ];
    }

    /**
     * Get statistics about graph embeddings
     *
     * @return array Statistics
     */
    public function getStatistics(): array
    {
        $totalEmbeddings = DB::table('decision_graph_embeddings')->count();
        $totalDecisions = DB::table('court_decisions')->count();

        $latestTraining = DB::table('decision_graph_embeddings')
            ->orderBy('trained_at', 'desc')
            ->first();

        $modelVersion = $latestTraining->model_version ?? null;
        $lastTrainedAt = $latestTraining->trained_at ?? null;

        $coverage = $totalDecisions > 0
            ? round(($totalEmbeddings / $totalDecisions) * 100, 2)
            : 0;

        return [
            'total_embeddings' => $totalEmbeddings,
            'total_decisions' => $totalDecisions,
            'coverage_percentage' => $coverage,
            'model_version' => $modelVersion,
            'last_trained_at' => $lastTrainedAt,
        ];
    }

    /**
     * Find similar decisions using graph embeddings (cosine similarity)
     *
     * @param  string  $decisionId  Source decision ID
     * @param  int  $limit  Number of similar decisions to return
     * @return array Similar decisions with similarity scores
     */
    public function findSimilarByGraph(string $decisionId, int $limit = 10): array
    {
        // Get source embedding
        $source = DB::table('decision_graph_embeddings')
            ->where('decision_id', $decisionId)
            ->first();

        if (! $source) {
            return [];
        }

        // Check if pgvector is available (cache the result)
        if ($this->pgvectorAvailable === null) {
            $this->pgvectorAvailable = $this->checkPgvectorAvailability();
        }

        // Use pgvector if available, otherwise fallback
        if ($this->pgvectorAvailable) {
            $results = DB::select(
                '
                SELECT
                    decision_id,
                    1 - (graph_embedding <=> ?::vector) AS similarity
                FROM decision_graph_embeddings
                WHERE decision_id != ?
                ORDER BY graph_embedding <=> ?::vector
                LIMIT ?
                ',
                [$source->graph_embedding, $decisionId, $source->graph_embedding, $limit]
            );

            return array_map(fn ($row) => [
                'decision_id' => $row->decision_id,
                'similarity' => round($row->similarity, 4),
            ], $results);
        } else {
            Log::warning('GraphEmbeddingService - pgvector not available, using fallback similarity');

            return $this->findSimilarByGraphFallback($decisionId, $source->graph_embedding, $limit);
        }
    }

    /**
     * Check if pgvector extension is available in PostgreSQL
     */
    protected function checkPgvectorAvailability(): bool
    {
        try {
            $result = DB::select("SELECT typname FROM pg_type WHERE typname = 'vector'");

            return ! empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Fallback similarity search without pgvector (for testing)
     */
    protected function findSimilarByGraphFallback(string $sourceDecisionId, string $sourceEmbedding, int $limit): array
    {
        // Parse source embedding
        $sourceVector = json_decode($sourceEmbedding, true);
        if (! is_array($sourceVector)) {
            return [];
        }

        // Get all other embeddings
        $candidates = DB::table('decision_graph_embeddings')
            ->where('decision_id', '!=', $sourceDecisionId)
            ->get();

        $similarities = [];
        foreach ($candidates as $candidate) {
            $candidateVector = json_decode($candidate->graph_embedding, true);
            if (! is_array($candidateVector)) {
                continue;
            }

            // Calculate cosine similarity in PHP
            $similarity = $this->cosineSimilarity($sourceVector, $candidateVector);
            $similarities[] = [
                'decision_id' => $candidate->decision_id,
                'similarity' => round($similarity, 4),
            ];
        }

        // Sort by similarity descending
        usort($similarities, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Return top N
        return array_slice($similarities, 0, $limit);
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Delete all graph embeddings (for regeneration)
     *
     * @return int Number of embeddings deleted
     */
    public function clearEmbeddings(): int
    {
        $count = DB::table('decision_graph_embeddings')->count();
        DB::table('decision_graph_embeddings')->truncate();

        Log::info('GraphEmbeddingService - Cleared all embeddings', ['count' => $count]);

        return $count;
    }
}
