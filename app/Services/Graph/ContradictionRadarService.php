<?php

namespace App\Services\Graph;

use App\Models\ResearchSession;
use App\Services\GraphDatabaseService;
use InvalidArgumentException;

/**
 * Contradiction Radar Service for proactive legal research alerts.
 *
 * Scans nodes for potential issues:
 * - Direct contradictions (CONTRADICTS relationships)
 * - Superseded law citations
 * - Outdated citations (decision cites law after valid_until)
 *
 * Part of Phase 3: Contradiction Radar feature.
 */
class ContradictionRadarService
{
    public const ALERT_TYPE_DIRECT_CONTRADICTION = 'direct_contradiction';

    public const ALERT_TYPE_SUPERSEDED_LAW = 'superseded_law';

    public const ALERT_TYPE_OUTDATED_CITATION = 'outdated_citation';

    public const ALERT_TYPE_OVERRULED_PRECEDENT = 'overruled_precedent';

    public const ALERT_TYPE_DISTINGUISHED_PRECEDENT = 'distinguished_precedent';

    public const ALERT_TYPE_WEAK_CITATION_CHAIN = 'weak_citation_chain';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CAUTION = 'caution';

    public function __construct(
        protected GraphDatabaseService $graphDb
    ) {}

    /**
     * Scan a node for all alert types (triggered on pin).
     *
     * Orchestrates all detection methods based on node type:
     * - Direct contradictions: All node types
     * - Superseded laws: Decision nodes only
     * - Outdated citations: Decision nodes only
     *
     * @param  string  $nodeId  The node ID to scan
     * @param  string  $nodeType  The type of node (Decision, Law, etc.)
     * @return array<int, array{
     *     id: string,
     *     type: string,
     *     severity: string,
     *     source_node_id: string,
     *     related_node_id: string,
     *     message: string,
     *     dismissed: bool,
     *     created_at: string
     * }> Combined array of all alerts found
     *
     * @throws InvalidArgumentException If nodeId or nodeType is empty
     */
    public function scanNode(string $nodeId, string $nodeType): array
    {
        if (empty(trim($nodeId))) {
            throw new InvalidArgumentException('Node ID cannot be empty');
        }

        if (empty(trim($nodeType))) {
            throw new InvalidArgumentException('Node type cannot be empty');
        }

        $alerts = [];

        // 1. Direct contradictions (all node types)
        $alerts = array_merge($alerts, $this->findDirectContradictions($nodeId));

        // 2. Superseded laws (Decision nodes only)
        if ($nodeType === 'Decision') {
            $alerts = array_merge($alerts, $this->findSupersededLawCitations($nodeId));
        }

        // 3. Outdated citations (Decision nodes only)
        if ($nodeType === 'Decision') {
            $alerts = array_merge($alerts, $this->findOutdatedCitations($nodeId));
        }

        // 4. Overruled precedents (Decision nodes only) - WEAKNESS FINDER
        if ($nodeType === 'Decision') {
            $alerts = array_merge($alerts, $this->findOverruledPrecedentCitations($nodeId));
        }

        // 5. Distinguished precedents (Decision nodes only) - WEAKNESS FINDER
        if ($nodeType === 'Decision') {
            $alerts = array_merge($alerts, $this->findDistinguishedPrecedentCitations($nodeId));
        }

        // 6. Weak citation chains (Decision nodes only) - WEAKNESS FINDER
        if ($nodeType === 'Decision') {
            $alerts = array_merge($alerts, $this->findWeakCitationChains($nodeId));
        }

        return $alerts;
    }

    /**
     * Find direct contradictions for a node (CONTRADICTS relationship).
     *
     * Queries Neo4j for nodes with CONTRADICTS relationships to the given node
     * and returns alerts with deterministic IDs for tracking.
     *
     * @param  string  $nodeId  The node ID to check for contradictions
     * @return array<int, array{
     *     id: string,
     *     type: string,
     *     severity: string,
     *     source_node_id: string,
     *     related_node_id: string,
     *     message: string,
     *     dismissed: bool,
     *     created_at: string
     * }> Array of alert objects
     *
     * @throws InvalidArgumentException If nodeId is empty
     * @throws \RuntimeException If database query fails
     */
    public function findDirectContradictions(string $nodeId): array
    {
        if (empty(trim($nodeId))) {
            throw new InvalidArgumentException('Node ID cannot be empty');
        }

        $cypher = <<<'CYPHER'
            MATCH (pinned)-[:CONTRADICTS]-(contradicting)
            WHERE id(pinned) = $nodeId OR pinned.id = $nodeId
            RETURN
                contradicting.id AS node_id,
                contradicting.case_number AS case_number,
                contradicting.title AS title
        CYPHER;

        try {
            $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to query contradictions for node {$nodeId}: {$e->getMessage()}",
                0,
                $e
            );
        }

        return array_map(fn ($row) => [
            'id' => $this->generateAlertId($nodeId, self::ALERT_TYPE_DIRECT_CONTRADICTION, $row['node_id']),
            'type' => self::ALERT_TYPE_DIRECT_CONTRADICTION,
            'severity' => self::SEVERITY_CRITICAL,
            'source_node_id' => $nodeId,
            'related_node_id' => $row['node_id'],
            'message' => "Decision {$row['case_number']} contradicts this node",
            'dismissed' => false,
            'created_at' => now()->toISOString(),
        ], $results);
    }

    /**
     * Find citations to superseded laws (SUPERSEDED_BY relationship).
     *
     * Queries Neo4j for decisions that cite laws which have been superseded
     * by newer versions. Returns caution-level alerts.
     *
     * @param  string  $nodeId  The decision node ID to check
     * @return array<int, array{
     *     id: string,
     *     type: string,
     *     severity: string,
     *     source_node_id: string,
     *     related_node_id: string,
     *     message: string,
     *     dismissed: bool,
     *     created_at: string
     * }> Array of alert objects
     *
     * @throws InvalidArgumentException If nodeId is empty
     * @throws \RuntimeException If database query fails
     */
    public function findSupersededLawCitations(string $nodeId): array
    {
        if (empty(trim($nodeId))) {
            throw new InvalidArgumentException('Node ID cannot be empty');
        }

        $cypher = <<<'CYPHER'
            MATCH (decision:Decision)-[:CITES]->(oldLaw)-[:SUPERSEDED_BY]->(newLaw)
            WHERE id(decision) = $nodeId OR decision.id = $nodeId
            RETURN
                oldLaw.id AS old_law_id,
                oldLaw.law_number AS old_law_number,
                newLaw.id AS new_law_id,
                newLaw.law_number AS new_law_number
        CYPHER;

        try {
            $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to query superseded laws for node {$nodeId}: {$e->getMessage()}",
                0,
                $e
            );
        }

        return array_map(fn ($row) => [
            'id' => $this->generateAlertId($nodeId, self::ALERT_TYPE_SUPERSEDED_LAW, $row['old_law_id']),
            'type' => self::ALERT_TYPE_SUPERSEDED_LAW,
            'severity' => self::SEVERITY_CAUTION,
            'source_node_id' => $nodeId,
            'related_node_id' => $row['old_law_id'],
            'message' => "Cites {$row['old_law_number']} which was superseded by {$row['new_law_number']}",
            'dismissed' => false,
            'created_at' => now()->toISOString(),
        ], $results);
    }

    /**
     * Find citations to laws after their valid_until date.
     *
     * Queries Neo4j for decisions that cite laws where the decision date
     * is after the law's valid_until date. Returns caution-level alerts.
     *
     * @param  string  $nodeId  The decision node ID to check
     * @return array<int, array{
     *     id: string,
     *     type: string,
     *     severity: string,
     *     source_node_id: string,
     *     related_node_id: string,
     *     message: string,
     *     dismissed: bool,
     *     created_at: string
     * }> Array of alert objects
     *
     * @throws InvalidArgumentException If nodeId is empty
     * @throws \RuntimeException If database query fails
     */
    public function findOutdatedCitations(string $nodeId): array
    {
        if (empty(trim($nodeId))) {
            throw new InvalidArgumentException('Node ID cannot be empty');
        }

        $cypher = <<<'CYPHER'
            MATCH (decision:Decision)-[:CITES]->(law)
            WHERE (id(decision) = $nodeId OR decision.id = $nodeId)
              AND law.valid_until IS NOT NULL
              AND decision.decision_date > law.valid_until
            RETURN
                law.id AS law_id,
                law.law_number AS law_number,
                law.valid_until AS valid_until,
                decision.decision_date AS decision_date
        CYPHER;

        try {
            $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to query outdated citations for node {$nodeId}: {$e->getMessage()}",
                0,
                $e
            );
        }

        return array_map(fn ($row) => [
            'id' => $this->generateAlertId($nodeId, self::ALERT_TYPE_OUTDATED_CITATION, $row['law_id']),
            'type' => self::ALERT_TYPE_OUTDATED_CITATION,
            'severity' => self::SEVERITY_CAUTION,
            'source_node_id' => $nodeId,
            'related_node_id' => $row['law_id'],
            'message' => "Cites {$row['law_number']} which was only valid until {$row['valid_until']}",
            'dismissed' => false,
            'created_at' => now()->toISOString(),
        ], $results);
    }

    /**
     * Find citations to precedents that have been overruled.
     *
     * Queries Neo4j for decisions that cite other decisions which have been
     * explicitly overruled by a later decision. Returns critical-level alerts
     * as this is a significant weakness in the legal argument.
     *
     * @param  string  $nodeId  The decision node ID to check
     * @return array<int, array{
     *     id: string,
     *     type: string,
     *     severity: string,
     *     source_node_id: string,
     *     related_node_id: string,
     *     message: string,
     *     dismissed: bool,
     *     created_at: string,
     *     metadata: array{overruling_decision_id: string, overruling_case_number: string, reason: string|null}
     * }> Array of alert objects
     *
     * @throws InvalidArgumentException If nodeId is empty
     * @throws \RuntimeException If database query fails
     */
    public function findOverruledPrecedentCitations(string $nodeId): array
    {
        if (empty(trim($nodeId))) {
            throw new InvalidArgumentException('Node ID cannot be empty');
        }

        $cypher = <<<'CYPHER'
            MATCH (decision:Decision)-[:CITES]->(cited:Decision)<-[:OVERRULES]-(overruling:Decision)
            WHERE id(decision) = $nodeId OR decision.id = $nodeId
            RETURN
                cited.id AS cited_decision_id,
                cited.case_number AS cited_case_number,
                overruling.id AS overruling_decision_id,
                overruling.case_number AS overruling_case_number,
                overruling.reason AS overruling_reason
        CYPHER;

        try {
            $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to query overruled precedents for node {$nodeId}: {$e->getMessage()}",
                0,
                $e
            );
        }

        return array_map(fn ($row) => [
            'id' => $this->generateAlertId($nodeId, self::ALERT_TYPE_OVERRULED_PRECEDENT, $row['cited_decision_id']),
            'type' => self::ALERT_TYPE_OVERRULED_PRECEDENT,
            'severity' => self::SEVERITY_CRITICAL,
            'source_node_id' => $nodeId,
            'related_node_id' => $row['cited_decision_id'],
            'message' => "Cites {$row['cited_case_number']} which was overruled by {$row['overruling_case_number']}",
            'dismissed' => false,
            'created_at' => now()->toISOString(),
            'metadata' => [
                'overruling_decision_id' => $row['overruling_decision_id'],
                'overruling_case_number' => $row['overruling_case_number'],
                'reason' => $row['overruling_reason'] ?? null,
            ],
        ], $results);
    }

    /**
     * Find citations to precedents that have been distinguished.
     *
     * Queries Neo4j for decisions that cite other decisions which have been
     * distinguished by later courts. This indicates the precedent's applicability
     * has been narrowed or questioned.
     *
     * @param  string  $nodeId  The decision node ID to check
     * @return array<int, array{
     *     id: string,
     *     type: string,
     *     severity: string,
     *     source_node_id: string,
     *     related_node_id: string,
     *     message: string,
     *     dismissed: bool,
     *     created_at: string,
     *     metadata: array{distinguishing_decision_id: string, distinguishing_count: int}
     * }> Array of alert objects
     *
     * @throws InvalidArgumentException If nodeId is empty
     * @throws \RuntimeException If database query fails
     */
    public function findDistinguishedPrecedentCitations(string $nodeId): array
    {
        if (empty(trim($nodeId))) {
            throw new InvalidArgumentException('Node ID cannot be empty');
        }

        $cypher = <<<'CYPHER'
            MATCH (decision:Decision)-[:CITES]->(cited:Decision)<-[:DISTINGUISHES]-(distinguishing:Decision)
            WHERE id(decision) = $nodeId OR decision.id = $nodeId
            WITH cited, distinguishing, decision
            ORDER BY distinguishing.decision_date DESC
            WITH cited, collect(distinguishing)[0] AS latest_distinguishing, count(distinguishing) AS distinguish_count
            RETURN
                cited.id AS cited_decision_id,
                cited.case_number AS cited_case_number,
                latest_distinguishing.id AS distinguishing_decision_id,
                latest_distinguishing.case_number AS distinguishing_case_number,
                distinguish_count AS distinguishing_count
        CYPHER;

        try {
            $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to query distinguished precedents for node {$nodeId}: {$e->getMessage()}",
                0,
                $e
            );
        }

        return array_map(fn ($row) => [
            'id' => $this->generateAlertId($nodeId, self::ALERT_TYPE_DISTINGUISHED_PRECEDENT, $row['cited_decision_id']),
            'type' => self::ALERT_TYPE_DISTINGUISHED_PRECEDENT,
            'severity' => self::SEVERITY_WARNING,
            'source_node_id' => $nodeId,
            'related_node_id' => $row['cited_decision_id'],
            'message' => "Cites {$row['cited_case_number']} which has been distinguished {$row['distinguishing_count']} time(s), most recently by {$row['distinguishing_case_number']}",
            'dismissed' => false,
            'created_at' => now()->toISOString(),
            'metadata' => [
                'distinguishing_decision_id' => $row['distinguishing_decision_id'],
                'distinguishing_count' => $row['distinguishing_count'],
            ],
        ], $results);
    }

    /**
     * Find citations to precedents that have been modified (weakened).
     *
     * Queries Neo4j for decisions that cite other decisions which have been
     * modified by appellate courts. Modifications indicate partial disagreement
     * with the original ruling.
     *
     * Note: "Weak citation chains" refers to citations to modified precedents -
     * cases where the cited authority has been partially altered by later appellate
     * review, reducing its weight as precedent.
     *
     * @param  string  $nodeId  The decision node ID to check
     * @return array<int, array{
     *     id: string,
     *     type: string,
     *     severity: string,
     *     source_node_id: string,
     *     related_node_id: string,
     *     message: string,
     *     dismissed: bool,
     *     created_at: string,
     *     metadata: array{modification_count: int, latest_modifier_id: string}
     * }> Array of alert objects
     *
     * @throws InvalidArgumentException If nodeId is empty
     * @throws \RuntimeException If database query fails
     */
    public function findWeakCitationChains(string $nodeId): array
    {
        if (empty(trim($nodeId))) {
            throw new InvalidArgumentException('Node ID cannot be empty');
        }

        $cypher = <<<'CYPHER'
            MATCH (decision:Decision)-[:CITES]->(cited:Decision)<-[:MODIFIES]-(modifier:Decision)
            WHERE id(decision) = $nodeId OR decision.id = $nodeId
            WITH cited, modifier, decision
            ORDER BY modifier.decision_date DESC
            WITH cited, collect(modifier)[0] AS latest_modifier, count(modifier) AS mod_count
            RETURN
                cited.id AS cited_decision_id,
                cited.case_number AS cited_case_number,
                mod_count AS modification_count,
                latest_modifier.id AS latest_modifier_id,
                latest_modifier.case_number AS latest_modifier_case_number
        CYPHER;

        try {
            $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to query weak citation chains for node {$nodeId}: {$e->getMessage()}",
                0,
                $e
            );
        }

        return array_map(fn ($row) => [
            'id' => $this->generateAlertId($nodeId, self::ALERT_TYPE_WEAK_CITATION_CHAIN, $row['cited_decision_id']),
            'type' => self::ALERT_TYPE_WEAK_CITATION_CHAIN,
            'severity' => self::SEVERITY_CAUTION,
            'source_node_id' => $nodeId,
            'related_node_id' => $row['cited_decision_id'],
            'message' => "Cites {$row['cited_case_number']} which was modified {$row['modification_count']} time(s), last by {$row['latest_modifier_case_number']}",
            'dismissed' => false,
            'created_at' => now()->toISOString(),
            'metadata' => [
                'modification_count' => $row['modification_count'],
                'latest_modifier_id' => $row['latest_modifier_id'],
            ],
        ], $results);
    }

    /**
     * Scan all pinned nodes in a session (on-demand full scan).
     *
     * Iterates through all pinned nodes in the session and runs scanNode()
     * on each one, collecting all alerts and adding them to the session.
     *
     * @param  ResearchSession  $session  The session to scan
     * @return array All alerts found across all pinned nodes
     */
    public function scanSession(ResearchSession $session): array
    {
        $allAlerts = [];
        $pinnedNodes = $session->pinned_nodes ?? [];

        foreach ($pinnedNodes as $node) {
            $nodeId = $node['id'] ?? null;
            $nodeType = $node['type'] ?? 'Unknown';

            if ($nodeId) {
                $alerts = $this->scanNode($nodeId, $nodeType);
                $allAlerts = array_merge($allAlerts, $alerts);
            }
        }

        // Add to session (avoiding duplicates)
        if (! empty($allAlerts)) {
            $this->addAlertsToSession($session, $allAlerts);
        }

        return $allAlerts;
    }

    // ========================================
    // Session Alert Management Methods
    // ========================================

    /**
     * Add alerts to a session, avoiding duplicates.
     *
     * Compares new alerts against existing alerts using a signature based on
     * source_node_id, related_node_id, and type to prevent duplicates.
     *
     * @param  ResearchSession  $session  The session to add alerts to
     * @param  array  $newAlerts  Array of new alert objects
     */
    public function addAlertsToSession(ResearchSession $session, array $newAlerts): void
    {
        $existingAlerts = $session->alerts ?? [];

        // Create a set of existing alert signatures to detect duplicates
        $existingSignatures = array_map(
            fn ($alert) => $this->getAlertSignature($alert),
            $existingAlerts
        );

        foreach ($newAlerts as $alert) {
            $signature = $this->getAlertSignature($alert);
            if (! in_array($signature, $existingSignatures)) {
                $existingAlerts[] = $alert;
                $existingSignatures[] = $signature;
            }
        }

        $session->update(['alerts' => $existingAlerts]);
    }

    /**
     * Dismiss an alert by its ID.
     *
     * Sets the 'dismissed' flag to true for the alert with the given ID.
     *
     * @param  ResearchSession  $session  The session containing the alert
     * @param  string  $alertId  The ID of the alert to dismiss
     */
    public function dismissAlert(ResearchSession $session, string $alertId): void
    {
        $alerts = $session->alerts ?? [];

        foreach ($alerts as &$alert) {
            if ($alert['id'] === $alertId) {
                $alert['dismissed'] = true;
                break;
            }
        }

        $session->update(['alerts' => $alerts]);
    }

    /**
     * Get all active (undismissed) alerts from a session.
     *
     * @param  ResearchSession  $session  The session to get alerts from
     * @return array Array of undismissed alert objects
     */
    public function getActiveAlerts(ResearchSession $session): array
    {
        $alerts = $session->alerts ?? [];

        return array_values(array_filter(
            $alerts,
            fn ($alert) => ! ($alert['dismissed'] ?? false)
        ));
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Generate a deterministic alert ID based on source, type, and related node.
     *
     * This ensures the same alert always has the same ID, enabling proper
     * tracking and dismissal across sessions.
     *
     * @param  string  $sourceNodeId  The source node ID
     * @param  string  $alertType  The type of alert
     * @param  string  $relatedNodeId  The related node ID
     * @return string Deterministic SHA-256 hash ID
     */
    protected function generateAlertId(string $sourceNodeId, string $alertType, string $relatedNodeId): string
    {
        return hash('sha256', "{$sourceNodeId}:{$alertType}:{$relatedNodeId}");
    }

    /**
     * Generate a unique signature for an alert to detect duplicates.
     *
     * Based on source node, related node, and alert type.
     *
     * @param  array  $alert  The alert to generate a signature for
     * @return string Unique signature string
     */
    protected function getAlertSignature(array $alert): string
    {
        return implode(':', [
            $alert['source_node_id'] ?? '',
            $alert['related_node_id'] ?? '',
            $alert['type'] ?? '',
        ]);
    }
}
