<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

/**
 * Service for creating precedent relationships between court decisions
 *
 * Creates relationships that track how decisions relate to prior decisions:
 * - OVERRULES: When a decision explicitly overturns a prior decision
 * - CONFIRMS: When an appellate court upholds a lower court decision
 * - MODIFIES: When an appellate court partially changes a decision
 * - FOLLOWS: When a decision applies precedent from another decision
 * - DISTINGUISHES: When a court explains why a precedent doesn't apply
 */
class PrecedentLinker
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Create an OVERRULES relationship
     *
     * @param  string  $newerId  The newer decision that overrules
     * @param  string  $olderId  The older decision being overruled
     * @param  string|null  $reason  Reason for overruling
     */
    public function overrules(string $newerId, string $olderId, ?string $reason = null): bool
    {
        return $this->createRelationship($newerId, 'OVERRULES', $olderId, [
            'reason' => $reason,
            'decision_date' => now()->toDateString(),
        ]);
    }

    /**
     * Create a CONFIRMS relationship
     *
     * @param  string  $appellateId  The appellate decision that confirms
     * @param  string  $lowerId  The lower court decision being confirmed
     */
    public function confirms(string $appellateId, string $lowerId): bool
    {
        return $this->createRelationship($appellateId, 'CONFIRMS', $lowerId, [
            'decision_date' => now()->toDateString(),
        ]);
    }

    /**
     * Create a MODIFIES relationship
     *
     * @param  string  $appellateId  The appellate decision that modifies
     * @param  string  $lowerId  The lower court decision being modified
     * @param  string|null  $details  Details of the modification
     */
    public function modifies(string $appellateId, string $lowerId, ?string $details = null): bool
    {
        return $this->createRelationship($appellateId, 'MODIFIES', $lowerId, [
            'modification_details' => $details,
            'decision_date' => now()->toDateString(),
        ]);
    }

    /**
     * Create a FOLLOWS relationship
     *
     * @param  string  $laterId  The later decision that follows precedent
     * @param  string  $precedentId  The precedent decision being followed
     * @param  string|null  $reasoning  How the precedent was applied
     */
    public function follows(string $laterId, string $precedentId, ?string $reasoning = null): bool
    {
        return $this->createRelationship($laterId, 'FOLLOWS', $precedentId, [
            'reasoning' => $reasoning,
        ]);
    }

    /**
     * Create a DISTINGUISHES relationship
     *
     * @param  string  $currentId  The current decision
     * @param  string  $priorId  The prior decision being distinguished
     * @param  string|null  $reasoning  Reasoning for the distinction
     */
    public function distinguishes(string $currentId, string $priorId, ?string $reasoning = null): bool
    {
        return $this->createRelationship($currentId, 'DISTINGUISHES', $priorId, [
            'reasoning' => $reasoning,
        ]);
    }

    /**
     * Create a precedent relationship
     */
    protected function createRelationship(
        string $fromId,
        string $relType,
        string $toId,
        array $properties = []
    ): bool {
        try {
            $properties['created_at'] = now()->toIso8601String();

            $this->graph->createRelationship(
                'CourtDecisionDocument',
                $fromId,
                $relType,
                'CourtDecisionDocument',
                $toId,
                $properties
            );

            Log::debug("Created {$relType} relationship", [
                'from' => $fromId,
                'to' => $toId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::warning("Failed to create {$relType} relationship", [
                'from' => $fromId,
                'to' => $toId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
