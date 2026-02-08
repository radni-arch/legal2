<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

/**
 * Service for syncing extracted legal principles to Neo4j graph
 *
 * Task A.3 - Connects LegalPrincipleExtractor to graph database
 *
 * Responsibilities:
 * - Extract legal principles from court decision text
 * - Create LegalPrinciple nodes in Neo4j
 * - Create ESTABLISHES relationships (landmark cases)
 * - Create APPLIES relationships (normal cases)
 */
class LegalPrincipleGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected LegalPrincipleExtractor $extractor
    ) {}

    /**
     * Sync legal principles from a court decision
     *
     * @param  string  $decisionId  The court decision ID
     * @param  string  $text  The decision text content
     * @param  bool  $isLandmarkCase  Whether this is a landmark case (uses ESTABLISHES)
     * @return array Array of synced principle IDs
     */
    public function syncPrinciplesFromDecision(
        string $decisionId,
        string $text,
        bool $isLandmarkCase = false
    ): array {
        // Extract principles from text
        $extracted = $this->extractor->extract($text);

        // Handle empty extractions
        if ($extracted['count'] === 0) {
            return [];
        }

        $synced = [];

        // Process each extracted principle
        foreach ($extracted['principles'] as $principle) {
            try {
                $principleId = $this->createOrFindPrinciple($principle);

                // Create relationship (ESTABLISHES for landmark, APPLIES for normal)
                $relType = $isLandmarkCase ? 'ESTABLISHES' : 'APPLIES';
                $this->graph->createRelationship(
                    'CourtDecisionDocument',
                    $decisionId,
                    $relType,
                    'LegalPrinciple',
                    $principleId,
                    ['created_at' => now()->toIso8601String()]
                );

                $synced[] = $principleId;

                Log::debug('Synced legal principle to graph', [
                    'principle' => $principle['pattern_matched'],
                    'principle_id' => $principleId,
                    'decision_id' => $decisionId,
                    'relationship' => $relType,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to sync legal principle to graph', [
                    'principle' => $principle['pattern_matched'],
                    'decision_id' => $decisionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $synced;
    }

    /**
     * Create or find a legal principle node
     *
     * @param  array  $principle  Extracted principle data
     * @return string The principle ID
     */
    protected function createOrFindPrinciple(array $principle): string
    {
        // Generate deterministic ID based on principle name
        $principleId = $this->generatePrincipleId($principle['pattern_matched']);

        // Determine category based on pattern matched
        $category = $this->determinePrincipleCategory($principle['pattern_matched']);

        // Create principle node (upsert will reuse existing node with same ID)
        $this->graph->upsertNode('LegalPrinciple', $principleId, [
            'name' => $principle['pattern_matched'],
            'description' => $principle['context'],
            'category' => $category,
            'status' => 'active',
        ]);

        return $principleId;
    }

    /**
     * Generate a deterministic ID for a legal principle node.
     *
     * Uses MD5 hash to ensure the same principle name always produces
     * the same ID, enabling deduplication during sync. This is different
     * from database primary keys (ULIDs) which are meant for storage
     * uniqueness. Graph node IDs need to be:
     * - Deterministic: Same input = same output
     * - Stable: ID doesn't change between syncs
     * - Deduplicating: Multiple syncs of same principle create/update one node
     *
     * MD5 collisions are acceptable here because:
     * - Prefix ('principle_') narrows the namespace
     * - Legal principle count is relatively small (hundreds to thousands)
     * - Collision would merge two principles, not cause data loss
     * - The legal domain has bounded complexity (not infinite principles)
     *
     * @param  string  $name  The principle name/pattern
     * @return string Deterministic principle ID (e.g., 'principle_abc123def456...')
     */
    protected function generatePrincipleId(string $name): string
    {
        return 'principle_'.md5($name);
    }

    /**
     * Determine the category of a legal principle based on pattern
     *
     * @param  string  $pattern  The matched pattern
     * @return string Category: procedural, substantive, or evidentiary
     */
    protected function determinePrincipleCategory(string $pattern): string
    {
        $pattern = mb_strtolower($pattern);

        // Procedural indicators
        if (str_contains($pattern, 'postupak') || str_contains($pattern, 'procesn')) {
            return 'procedural';
        }

        // Evidentiary indicators
        if (str_contains($pattern, 'dokaz') || str_contains($pattern, 'dokaziv')) {
            return 'evidentiary';
        }

        // Default to substantive
        return 'substantive';
    }
}
