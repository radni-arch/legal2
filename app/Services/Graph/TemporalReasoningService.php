<?php

namespace App\Services\Graph;

use App\Services\OpenAIService;

/**
 * Temporal Reasoning Service for Law Evolution Tracking (Sprint 4.2)
 *
 * Provides capabilities for:
 * - Retrieving law versions at specific dates
 * - Finding decisions citing outdated laws
 * - Tracking law evolution history
 * - Detecting contradictions between decisions using LLM
 *
 * This service enables PrecedentAnalystAgent to avoid citing outdated laws
 * and identify conflicting legal interpretations.
 */
class TemporalReasoningService
{
    public function __construct(
        protected LawGraphSyncService $lawGraphSync,
        protected OpenAIService $openAI
    ) {}

    /**
     * Get law version that was valid at a specific date
     *
     * @param  string  $lawNumber  Law number (e.g., 'NN 152/08')
     * @param  string  $date  Date in YYYY-MM-DD format
     * @return array|null Law version data if found, null otherwise
     */
    public function getLawAtDate(string $lawNumber, string $date): ?array
    {
        return $this->lawGraphSync->getLawVersionAtDate($lawNumber, $date);
    }

    /**
     * Find all decisions citing outdated (superseded) laws
     *
     * Returns decisions that cite law versions that were already superseded
     * at the time of the decision.
     *
     * @return array Array of outdated citation records
     */
    public function findOutdatedCitations(): array
    {
        // Cypher query to find decisions citing laws that were superseded before decision date
        $cypher = <<<'CYPHER'
            MATCH (decision:Decision)-[:CITES]->(citedLaw:LawDocument)-[:SUPERSEDED_BY]->(currentLaw:LawDocument)
            WHERE decision.decision_date > citedLaw.valid_until
            RETURN
                decision.id AS decision_id,
                decision.decision_date AS decision_date,
                citedLaw.id AS cited_law_id,
                citedLaw.law_number AS cited_law_number,
                citedLaw.version AS cited_law_version,
                currentLaw.id AS current_law_id,
                currentLaw.version AS current_version
            ORDER BY decision.decision_date DESC
        CYPHER;

        return $this->lawGraphSync->query($cypher);
    }

    /**
     * Get full evolution history of a law (all versions and amendments)
     *
     * Returns chronological history showing:
     * - All versions (v1 → v2 → v3)
     * - Amendments to each version
     * - Timeline of changes
     *
     * @param  string  $lawNumber  Law number (e.g., 'NN 152/08')
     * @return array Evolution history with versions and amendments
     */
    public function getLawEvolutionHistory(string $lawNumber): array
    {
        // Get all versions
        $versions = $this->lawGraphSync->getAllVersions($lawNumber);

        // Enrich each version with its amendments
        foreach ($versions as &$version) {
            $version['amendments'] = $this->lawGraphSync->findAllAmendments($version['law_id']);
        }

        return [
            'law_number' => $lawNumber,
            'versions' => $versions,
        ];
    }

    /**
     * Detect contradictions between a decision and other decisions using LLM
     *
     * Uses OpenAI to analyze decision content and identify conflicting
     * legal interpretations in other decisions.
     *
     * @param  string  $decisionId  The decision ID to analyze
     * @return array Array of contradictions found
     */
    public function detectContradictions(string $decisionId): array
    {
        // Query graph for potentially contradicting decisions
        // (decisions with similar topics/keywords but potentially different conclusions)
        $cypher = <<<'CYPHER'
            MATCH (decision:Decision)
            WHERE decision.id = $decision_id
            MATCH (other:Decision)
            WHERE other.id <> decision.id
              AND other.decision_date IS NOT NULL
            RETURN
                other.id AS decision_id,
                other.case_number AS case_number,
                other.summary AS summary,
                other.decision_date AS decision_date
            LIMIT 10
        CYPHER;

        $potentialContradictions = $this->lawGraphSync->query($cypher, ['decision_id' => $decisionId]);

        if (empty($potentialContradictions)) {
            return [];
        }

        // Get the original decision content
        $decision = \DB::table('court_decisions')->where('id', $decisionId)->first();

        if (! $decision) {
            return [];
        }

        // Use LLM to analyze for contradictions
        $prompt = $this->buildContradictionAnalysisPrompt($decision, $potentialContradictions);

        $response = $this->openAI->chat([
            ['role' => 'user', 'content' => $prompt],
        ]);

        $analysis = json_decode($response['content'], true);

        return $analysis['contradictions'] ?? [];
    }

    /**
     * Build prompt for LLM contradiction analysis
     *
     * @param  object  $decision  The main decision
     * @param  array  $potentialContradictions  Potentially contradicting decisions
     * @return string Formatted prompt
     */
    protected function buildContradictionAnalysisPrompt($decision, array $potentialContradictions): string
    {
        $prompt = "Analyze the following court decision for contradictions with other decisions.\n\n";
        $prompt .= "**Main Decision ({$decision->case_number}):**\n";
        $prompt .= "{$decision->summary}\n\n";
        $prompt .= "**Other Decisions to Compare:**\n\n";

        foreach ($potentialContradictions as $i => $other) {
            $prompt .= ($i + 1).". **{$other['case_number']}** ({$other['decision_date']}):\n";
            $prompt .= "   {$other['summary']}\n\n";
        }

        $prompt .= "\n**Task:** Identify any contradictions in legal interpretation or conclusions.\n\n";
        $prompt .= "Return JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "contradictions": ['."\n";
        $prompt .= "    {\n";
        $prompt .= '      "contradicting_decision_id": "decision_id",'."\n";
        $prompt .= '      "contradiction_type": "conflicting_legal_interpretation|contradictory_conclusions|inconsistent_application",'."\n";
        $prompt .= '      "explanation": "Brief explanation of the contradiction",'."\n";
        $prompt .= '      "severity": "high|medium|low"'."\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";
        $prompt .= 'If no contradictions found, return: {"contradictions": []}';

        return $prompt;
    }
}
