<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

/**
 * Service for syncing judge entities to the graph database
 *
 * Extracts judge information from court decisions and creates
 * Judge nodes with PRESIDED_BY relationships.
 */
class JudgeGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Sync a judge from a court decision
     *
     * @param  string  $decisionId  The decision node ID
     * @param  string|null  $judgeName  Judge name(s), comma-separated if multiple
     * @param  string  $court  The court where the decision was made
     * @param  string  $role  Default role for first judge
     */
    public function syncJudgeFromDecision(
        string $decisionId,
        ?string $judgeName,
        string $court,
        string $role = 'presiding'
    ): void {
        if (empty($judgeName)) {
            return;
        }

        // Parse multiple judges if comma-separated
        $judges = array_map('trim', explode(',', $judgeName));

        foreach ($judges as $index => $name) {
            if (empty($name)) {
                continue;
            }

            $this->syncSingleJudge($decisionId, $name, $court, $index === 0 ? $role : 'member');
        }
    }

    /**
     * Sync a single judge to the graph
     */
    protected function syncSingleJudge(
        string $decisionId,
        string $name,
        string $court,
        string $role
    ): void {
        $judgeId = $this->generateJudgeId($name, $court);

        try {
            // Create/update judge node
            $this->graph->upsertNode('Judge', $judgeId, [
                'name' => $name,
                'court' => $court,
                'active' => true,
                'updated_at' => now()->toIso8601String(),
            ]);

            // Create relationship to decision
            $this->graph->createRelationship(
                'CourtDecisionDocument',
                $decisionId,
                'PRESIDED_BY',
                'Judge',
                $judgeId,
                [
                    'role' => $role,
                    'created_at' => now()->toIso8601String(),
                ]
            );

            Log::debug('Synced judge to graph', [
                'judge_id' => $judgeId,
                'name' => $name,
                'decision_id' => $decisionId,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to sync judge to graph', [
                'name' => $name,
                'court' => $court,
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a deterministic ID for a judge node.
     *
     * Uses MD5 hash to ensure the same judge (name + court combination)
     * always produces the same ID, enabling deduplication during sync.
     * This is different from database primary keys (ULIDs) which are meant
     * for storage uniqueness. Graph node IDs need to be:
     * - Deterministic: Same input = same output
     * - Stable: ID doesn't change between syncs
     * - Deduplicating: Multiple syncs of same judge create/update one node
     *
     * MD5 collisions are acceptable here because:
     * - Prefix ('judge_') narrows the namespace
     * - Judge count is small (thousands, not billions)
     * - Collision would merge two judges, not cause data loss
     *
     * @param string $name The judge's name
     * @param string $court The court name
     * @return string The deterministic judge ID (e.g., 'judge_abc123def456...')
     */
    protected function generateJudgeId(string $name, string $court): string
    {
        return 'judge_'.md5($name.'_'.$court);
    }

    /**
     * Create court affiliation relationship
     */
    public function affiliateWithCourt(
        string $judgeName,
        string $court,
        ?string $role = null,
        ?string $startDate = null
    ): void {
        $judgeId = $this->generateJudgeId($judgeName, $court);
        $courtId = 'court_'.md5($court);

        try {
            $this->graph->createRelationship(
                'Judge',
                $judgeId,
                'AFFILIATED_WITH',
                'Court',
                $courtId,
                [
                    'role' => $role,
                    'start_date' => $startDate,
                    'created_at' => now()->toIso8601String(),
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to create court affiliation', [
                'judge' => $judgeName,
                'court' => $court,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
