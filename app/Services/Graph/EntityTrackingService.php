<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Entity Tracking Service (Sprint 8.1)
 *
 * Detects and tracks new entities (prosecutors, judges, keywords, courts)
 * as they first appear in the system, enabling attorneys to stay informed
 * about changes in the legal landscape.
 *
 * Features:
 * - Detect new entities within specified time periods
 * - Calculate relevance scores based on decision impact
 * - Tag emerging entities in Neo4j graph
 * - Generate weekly emergence reports
 * - Store entities in PostgreSQL for analytics
 *
 * Usage:
 *   $service = app(EntityTrackingService::class);
 *   $newProsecutors = $service->detectNewEntities('prosecutor', 7);
 */
class EntityTrackingService
{
    /**
     * Entity type weights for relevance scoring
     */
    protected const ENTITY_WEIGHTS = [
        'prosecutor' => 1.0,  // Highest priority
        'judge' => 0.9,
        'court' => 0.7,
        'keyword' => 0.5,     // Lowest priority
    ];

    public function __construct(
        protected GraphDatabaseService $graphDb
    ) {}

    /**
     * Detect new entities of specified type in last N days
     *
     * Queries Neo4j for entities that first appeared within the time window.
     * Entities must have first_seen_at timestamp set by ingestion pipeline.
     *
     * @param  string  $entityType  Entity type (prosecutor, judge, keyword, court)
     * @param  int  $days  Number of days to look back
     * @return array New entities with metadata
     */
    public function detectNewEntities(string $entityType, int $days = 7): array
    {
        try {
            $cutoffDate = Carbon::now()->subDays($days);

            // Map entity type to Neo4j label
            $label = $this->getNodeLabel($entityType);

            // Query Neo4j for new entities
            $cypher = "
                MATCH (e:{$label})
                WHERE e.first_seen_at IS NOT NULL
                  AND datetime(e.first_seen_at) >= datetime(\$cutoffDate)
                OPTIONAL MATCH (e)<-[:HAS_PROSECUTOR|HAS_JUDGE|HAS_KEYWORD]-(d:Decision)
                WITH e, count(DISTINCT d) as decision_count
                RETURN
                    e.id as entity_id,
                    e.name as entity_name,
                    e.first_seen_at as first_seen_at,
                    decision_count
                ORDER BY decision_count DESC
            ";

            $results = $this->graphDb->run($cypher, [
                'cutoffDate' => $cutoffDate->toIso8601String(),
            ]);

            return array_map(function ($row) use ($entityType) {
                return [
                    'entity_type' => $entityType,
                    'entity_id' => $row->entity_id ?? 'unknown',
                    'entity_name' => $row->entity_name ?? 'Unknown',
                    'first_seen_at' => $row->first_seen_at ?? now()->toDateTimeString(),
                    'decision_count' => $row->decision_count ?? 1,
                ];
            }, $results);
        } catch (\Exception $e) {
            Log::error('EntityTrackingService - Failed to detect new entities', [
                'entity_type' => $entityType,
                'days' => $days,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Calculate relevance score for an entity
     *
     * Score is based on:
     * - Entity type weight (prosecutors > judges > courts > keywords)
     * - Decision count (normalized to 0-1 scale, capped at 50 decisions)
     *
     * @param  array  $entity  Entity data
     * @return float Relevance score (0.00-1.00)
     */
    public function calculateRelevanceScore(array $entity): float
    {
        $entityType = $entity['entity_type'];
        $decisionCount = $entity['decision_count'] ?? 1;

        // Get type weight
        $typeWeight = self::ENTITY_WEIGHTS[$entityType] ?? 0.5;

        // Normalize decision count (cap at 50 for scaling)
        $normalizedCount = min($decisionCount / 50, 1.0);

        // Combined score: 60% type weight, 40% decision count
        $score = ($typeWeight * 0.6) + ($normalizedCount * 0.4);

        // Ensure score is between 0.00 and 1.00
        return round(max(0.0, min(1.0, $score)), 2);
    }

    /**
     * Tag emerging entities in Neo4j with :Emerging label
     *
     * Adds :Emerging label to entities so they can be easily queried
     * and displayed in graph visualizations.
     *
     * @param  array  $entities  List of entities to tag
     * @return int Number of entities tagged
     */
    public function tagEmergingEntities(array $entities): int
    {
        $tagged = 0;

        foreach ($entities as $entity) {
            try {
                $label = $this->getNodeLabel($entity['entity_type']);

                $cypher = "
                    MATCH (e:{$label} {id: \$entityId})
                    SET e:Emerging
                    SET e.emerging_tagged_at = datetime()
                    RETURN e
                ";

                $this->graphDb->run($cypher, [
                    'entityId' => $entity['entity_id'],
                ]);

                $tagged++;
            } catch (\Exception $e) {
                Log::warning('EntityTrackingService - Failed to tag entity', [
                    'entity' => $entity,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('EntityTrackingService - Tagged emerging entities', [
            'total' => count($entities),
            'tagged' => $tagged,
        ]);

        return $tagged;
    }

    /**
     * Store emerging entities in PostgreSQL database
     *
     * Stores entities for reporting and analytics. Skips duplicates
     * based on entity_type + entity_id unique constraint.
     *
     * @param  array  $entities  List of entities to store
     * @return int Number of entities stored
     */
    public function storeEmergingEntities(array $entities): int
    {
        $stored = 0;

        foreach ($entities as $entity) {
            try {
                // Check if entity already exists
                $exists = DB::table('emerging_entities')
                    ->where('entity_type', $entity['entity_type'])
                    ->where('entity_id', $entity['entity_id'])
                    ->exists();

                if ($exists) {
                    continue; // Skip duplicate
                }

                // Calculate relevance score
                $relevanceScore = $this->calculateRelevanceScore($entity);

                // Insert into database
                DB::table('emerging_entities')->insert([
                    'entity_type' => $entity['entity_type'],
                    'entity_id' => $entity['entity_id'],
                    'entity_name' => $entity['entity_name'],
                    'first_seen_at' => $entity['first_seen_at'],
                    'decision_count' => $entity['decision_count'] ?? 1,
                    'detected_at' => now(),
                    'relevance_score' => $relevanceScore,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stored++;
            } catch (\Exception $e) {
                Log::error('EntityTrackingService - Failed to store entity', [
                    'entity' => $entity,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('EntityTrackingService - Stored emerging entities', [
            'total' => count($entities),
            'stored' => $stored,
        ]);

        return $stored;
    }

    /**
     * Generate weekly emergence report
     *
     * Retrieves entities detected in the last N days and generates
     * a summary report with statistics and top entities.
     *
     * @param  int  $days  Number of days to include in report
     * @return array Report with summary, entities by type, and top entities
     */
    public function getEntityEmergenceReport(int $days = 7): array
    {
        $cutoffDate = Carbon::now()->subDays($days);

        // Get all entities from the period
        $entities = DB::table('emerging_entities')
            ->where('detected_at', '>=', $cutoffDate)
            ->orderBy('relevance_score', 'desc')
            ->get();

        // Group by entity type
        $byType = $entities->groupBy('entity_type')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Top 10 entities by relevance score
        $topEntities = $entities->take(10)->map(function ($entity) {
            return [
                'entity_id' => $entity->entity_id,
                'entity_type' => $entity->entity_type,
                'entity_name' => $entity->entity_name,
                'relevance_score' => $entity->relevance_score,
                'decision_count' => $entity->decision_count,
                'first_seen_at' => $entity->first_seen_at,
            ];
        })->toArray();

        return [
            'period' => "{$days} days",
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_new_entities' => $entities->count(),
                'period_start' => $cutoffDate->toDateString(),
                'period_end' => now()->toDateString(),
            ],
            'entities_by_type' => $byType,
            'top_entities' => $topEntities,
        ];
    }

    /**
     * Map entity type to Neo4j node label
     *
     * @param  string  $entityType  Entity type
     * @return string Neo4j label
     */
    protected function getNodeLabel(string $entityType): string
    {
        return match ($entityType) {
            'prosecutor' => 'Prosecutor',
            'judge' => 'Judge',
            'court' => 'Court',
            'keyword' => 'Keyword',
            default => 'Entity',
        };
    }
}
