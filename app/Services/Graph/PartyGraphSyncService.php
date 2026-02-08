<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

/**
 * Service for syncing party entities to the graph database
 *
 * Creates Party nodes representing case participants (plaintiffs, defendants, etc.)
 * with HAS_PARTY relationships to court decisions.
 */
class PartyGraphSyncService
{
    /**
     * Legal entity suffixes that indicate a company/organization
     */
    protected array $legalEntitySuffixes = [
        'd.o.o.',
        'd.d.',
        'j.d.o.o.',
        'obrt',
        'zadruga',
        'udruga',
        'ustanova',
        'zavod',
        'Institut',
        'banka',
        'osiguranje',
        'fond',
    ];

    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Sync a party from a court decision
     *
     * @param  string  $decisionId  The decision node ID
     * @param  string|null  $partyName  Party name
     * @param  string  $role  Party role (plaintiff, defendant, etc.)
     * @param  string|null  $outcome  Party outcome (prevailed, lost, etc.)
     */
    public function syncParty(
        string $decisionId,
        ?string $partyName,
        string $role,
        ?string $outcome = null
    ): void {
        if (empty($partyName)) {
            return;
        }

        $partyId = $this->generatePartyId($partyName, $role);
        $partyType = $this->detectPartyType($partyName);

        try {
            // Create/update party node
            $this->graph->upsertNode('Party', $partyId, [
                'name' => $partyName,
                'role' => $role,
                'party_type' => $partyType,
                'normalized_name' => $this->normalizeName($partyName),
                'updated_at' => now()->toIso8601String(),
            ]);

            // Create relationship to decision
            $this->graph->createRelationship(
                'CourtDecisionDocument',
                $decisionId,
                'HAS_PARTY',
                'Party',
                $partyId,
                [
                    'role' => $role,
                    'outcome' => $outcome,
                    'created_at' => now()->toIso8601String(),
                ]
            );

            Log::debug('Synced party to graph', [
                'party_id' => $partyId,
                'name' => $partyName,
                'role' => $role,
                'decision_id' => $decisionId,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to sync party to graph', [
                'name' => $partyName,
                'role' => $role,
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a deterministic ID for a party node.
     *
     * Uses MD5 hash to ensure the same party (name + role combination)
     * always produces the same ID, enabling deduplication during sync.
     * This is different from database primary keys (ULIDs) which are meant
     * for storage uniqueness. Graph node IDs need to be:
     * - Deterministic: Same input = same output
     * - Stable: ID doesn't change between syncs
     * - Deduplicating: Multiple syncs of same party create/update one node
     *
     * Note: Role is included in the hash because the same entity may appear
     * in different roles (e.g., plaintiff in one case, defendant in another).
     * Each role represents a distinct graph node identity.
     *
     * MD5 collisions are acceptable here because:
     * - Prefix ('party_') narrows the namespace
     * - Party count per role is manageable (thousands, not billions)
     * - Collision would merge two parties, not cause data loss
     *
     * @param string $name The party's name
     * @param string $role The party's role (plaintiff, defendant, etc.)
     * @return string The deterministic party ID (e.g., 'party_abc123def456...')
     */
    protected function generatePartyId(string $name, string $role): string
    {
        return 'party_'.md5($name.'_'.$role);
    }

    /**
     * Detect if party is a natural person or legal entity
     */
    protected function detectPartyType(string $name): string
    {
        foreach ($this->legalEntitySuffixes as $suffix) {
            if (stripos($name, $suffix) !== false) {
                return 'legal_entity';
            }
        }

        // Check for state body patterns
        if (preg_match('/Republika\s+Hrvatska|Ministarstvo|Grad\s+|Općina\s+|Županija/i', $name)) {
            return 'state_body';
        }

        return 'natural_person';
    }

    /**
     * Normalize party name for matching
     */
    protected function normalizeName(string $name): string
    {
        // Convert to lowercase and remove extra whitespace
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return $normalized;
    }
}
