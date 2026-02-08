<?php

namespace App\Services\Graph;

use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * Contradiction Detection Service (Sprint 4.6)
 *
 * Automated LLM-based detection of contradictions between legal decisions.
 *
 * Features:
 * - Uses GPT-4o to analyze decision pairs for contradictions
 * - Returns confidence scores and contradiction types
 * - Assesses severity (low/medium/high)
 * - Supports batch processing for efficiency
 * - Temporal context awareness
 *
 * Usage:
 *   $service = app(ContradictionDetectionService::class);
 *   $result = $service->detectContradiction($decision1, $decision2);
 *
 *   if ($result['is_contradiction'] && $result['confidence'] > 0.75) {
 *       // Create CONTRADICTS relationship
 *   }
 */
class ContradictionDetectionService
{
    public function __construct(
        public OpenAIService $openai
    ) {}

    /**
     * Detect contradiction between two decisions using LLM
     *
     * @param  string  $decision1  First decision text or summary
     * @param  string  $decision2  Second decision text or summary
     * @param  array  $metadata  Optional temporal context (decision dates, etc.)
     * @return array Contradiction analysis result
     *
     * @throws \RuntimeException If LLM response is invalid or incomplete
     */
    public function detectContradiction(
        string $decision1,
        string $decision2,
        array $metadata = []
    ): array {
        Log::info('ContradictionDetectionService - Analyzing decision pair', [
            'decision1_length' => strlen($decision1),
            'decision2_length' => strlen($decision2),
            'has_metadata' => ! empty($metadata),
        ]);

        // Build LLM prompt
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($decision1, $decision2, $metadata);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        // Call LLM with low temperature for consistency
        $response = $this->openai->chat($messages, 'gpt-4o', [
            'temperature' => 0.1,
        ]);

        $content = $response['content'] ?? '';

        // Parse JSON response
        $result = json_decode($content, true);

        if (! $result) {
            Log::error('ContradictionDetectionService - Invalid JSON response', [
                'response' => $content,
            ]);
            throw new \RuntimeException('Failed to parse LLM response for contradiction detection');
        }

        // Validate required fields
        $requiredFields = ['is_contradiction', 'confidence', 'explanation', 'severity'];
        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $result)) {
                Log::error('ContradictionDetectionService - Incomplete response', [
                    'missing_field' => $field,
                    'response' => $result,
                ]);
                throw new \RuntimeException('Incomplete LLM response: missing '.$field);
            }
        }

        Log::info('ContradictionDetectionService - Analysis complete', [
            'is_contradiction' => $result['is_contradiction'],
            'confidence' => $result['confidence'],
            'type' => $result['contradiction_type'] ?? null,
        ]);

        return [
            'is_contradiction' => (bool) $result['is_contradiction'],
            'confidence' => (float) $result['confidence'],
            'contradiction_type' => $result['contradiction_type'] ?? null,
            'explanation' => (string) $result['explanation'],
            'severity' => (string) $result['severity'],
        ];
    }

    /**
     * Detect contradictions in batch (source decision vs multiple candidates)
     *
     * @param  string  $sourceDecision  Source decision text
     * @param  array  $candidates  Associative array [decision_id => decision_text]
     * @return array Results keyed by decision_id
     */
    public function detectContradictionsInBatch(
        string $sourceDecision,
        array $candidates
    ): array {
        Log::info('ContradictionDetectionService - Batch analysis', [
            'candidate_count' => count($candidates),
        ]);

        $results = [];

        foreach ($candidates as $candidateId => $candidateText) {
            try {
                $results[$candidateId] = $this->detectContradiction(
                    $sourceDecision,
                    $candidateText
                );
            } catch (\Exception $e) {
                Log::error('ContradictionDetectionService - Batch item failed', [
                    'candidate_id' => $candidateId,
                    'error' => $e->getMessage(),
                ]);

                // Store error result
                $results[$candidateId] = [
                    'is_contradiction' => false,
                    'confidence' => 0.0,
                    'contradiction_type' => null,
                    'explanation' => 'Analysis failed: '.$e->getMessage(),
                    'severity' => 'low',
                    'error' => true,
                ];
            }
        }

        Log::info('ContradictionDetectionService - Batch complete', [
            'total_analyzed' => count($results),
            'contradictions_found' => count(array_filter($results, fn ($r) => $r['is_contradiction'])),
        ]);

        return $results;
    }

    /**
     * Build system prompt for contradiction detection
     */
    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a Croatian legal expert specializing in detecting contradictions between court decisions.

Your task is to analyze two court decision texts and determine if they contain contradictory legal conclusions, reasoning, or factual findings.

**Contradiction Types:**
- `legal_conclusion`: Decisions reach opposite legal conclusions on the same issue
- `factual_finding`: Decisions make contradictory factual findings about the same events
- `legal_reasoning`: Decisions use contradictory legal reasoning or interpretation
- `procedural_ruling`: Decisions make opposite procedural rulings

**Important Distinctions:**
- Temporal evolution is NOT a contradiction (e.g., newer decision updating older standard)
- Exceptions are NOT contradictions (e.g., general rule + exception case)
- Different applications of same principle are NOT contradictions
- Obiter dicta (non-binding remarks) vs. ratio decidendi (binding holding) distinctions matter

**Severity Levels:**
- `high`: Direct contradiction on core legal holding that would create precedent conflict
- `medium`: Contradictory reasoning or application that may confuse lower courts
- `low`: Minor inconsistencies, dicta contradictions, or easily distinguishable cases

**Response Format:**
Return valid JSON with these exact fields:
{
  "is_contradiction": true/false,
  "confidence": 0.0-1.0 (how confident you are in this assessment),
  "contradiction_type": "legal_conclusion" | "factual_finding" | "legal_reasoning" | "procedural_ruling" | null,
  "explanation": "Brief explanation of your reasoning (2-3 sentences)",
  "severity": "low" | "medium" | "high"
}

**Confidence Guidelines:**
- 0.9-1.0: Clear, unambiguous contradiction
- 0.75-0.89: Strong evidence of contradiction
- 0.5-0.74: Moderate evidence, some ambiguity
- Below 0.5: Weak evidence, probably not a contradiction
PROMPT;
    }

    /**
     * Build user prompt with decision texts and optional metadata
     */
    protected function buildUserPrompt(
        string $decision1,
        string $decision2,
        array $metadata
    ): string {
        $prompt = "**Decision 1:**\n{$decision1}\n\n**Decision 2:**\n{$decision2}";

        // Add temporal context if provided
        if (isset($metadata['older_decision_date']) && isset($metadata['newer_decision_date'])) {
            $prompt .= "\n\n**Temporal Context:**\n";
            $prompt .= "Decision 1 Date: {$metadata['older_decision_date']}\n";
            $prompt .= "Decision 2 Date: {$metadata['newer_decision_date']}\n";
            $prompt .= '(Consider whether this might be temporal evolution rather than contradiction)';
        }

        // Add other metadata
        if (isset($metadata['jurisdiction_1']) && isset($metadata['jurisdiction_2'])) {
            $prompt .= "\n\n**Jurisdictional Context:**\n";
            $prompt .= "Decision 1 Jurisdiction: {$metadata['jurisdiction_1']}\n";
            $prompt .= "Decision 2 Jurisdiction: {$metadata['jurisdiction_2']}\n";
        }

        if (isset($metadata['court_level_1']) && isset($metadata['court_level_2'])) {
            $prompt .= "\n\n**Court Hierarchy:**\n";
            $prompt .= "Decision 1 Court: {$metadata['court_level_1']}\n";
            $prompt .= "Decision 2 Court: {$metadata['court_level_2']}\n";
            $prompt .= '(Higher court decisions may overrule lower court decisions - not contradictions)';
        }

        $prompt .= "\n\nAnalyze these decisions for contradictions and return your assessment as JSON.";

        return $prompt;
    }
}
