<?php

namespace App\Modules\Evidence\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * ContextAnalyzer
 *
 * Analyzes full context of evidence to identify prosecutor's selective presentation
 * and provide legitimate recontextualization opportunities for defense.
 *
 * ETHICAL FRAMEWORK:
 * ✅ Identifies prosecutor's selective use of evidence
 * ✅ Reveals omitted context that changes interpretation
 * ✅ Provides alternative interpretations based on ACTUAL evidence
 * ✅ Counters cherry-picking with full factual context
 * ❌ Does NOT fabricate context
 * ❌ Does NOT distort facts
 * ❌ Does NOT create false evidence
 *
 * Types of Selective Presentation Detected:
 * 1. Partial messages (SMS/email) - showing only incriminating excerpts
 * 2. Cherry-picked timestamps - ignoring exculpatory timeline
 * 3. Out-of-context media (photos/videos) - misleading framing
 * 4. Partial witness statements - omitting clarifying context
 * 5. Selective financial records - hiding legitimate transactions
 *
 * Legal Basis (Croatian Law):
 * - ZKP Članak 9 - Objektivnost (prosecution must present both incriminating and exculpatory evidence)
 * - ZKP Članak 331 - Slobodna ocjena dokaza (free evaluation of evidence requires full context)
 * - Ustav RH Članak 29 - Pravo na pravično suđenje (fair trial requires complete picture)
 *
 * Examples of Legitimate Recontextualization:
 * - SMS "I'll get the stuff" → Full conversation shows "stuff" = groceries
 * - Photo at scene → Metadata shows 2 hours before crime
 * - "He was angry" → Full statement: friendly sports argument
 * - $5000 withdrawal → Bank records show loan repayment
 * - "Running from scene" → Full context: running to catch bus
 */
class ContextAnalyzer
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Analyze full context of evidence to identify selective presentation
     *
     * @param  array  $evidence  Evidence item to analyze
     * @param  LegalCase  $case  The legal case
     * @return array Context analysis with recontextualization opportunities
     */
    public function analyzeContext(array $evidence, LegalCase $case): array
    {
        Log::info('ContextAnalyzer: Starting context analysis', [
            'evidence_id' => $evidence['id'] ?? 'unknown',
            'evidence_type' => $evidence['type'] ?? 'unknown',
            'case_id' => $case->id,
        ]);

        // Extract how prosecution presented this evidence
        $prosecutionPresentation = $this->extractProsecutionPresentation($evidence);

        // Extract full available context
        $fullContext = $this->extractFullContext($evidence, $case);

        // Detect selective presentation
        $selectivePresentation = $this->detectSelectivePresentation($evidence, $case);

        // Identify what was omitted
        $omittedContext = $this->identifyOmittedContext($evidence, $case, $prosecutionPresentation, $fullContext);

        // Find recontextualization opportunities
        $recontextualizationOpportunities = $this->findRecontextualizationOpportunities(
            $evidence,
            $prosecutionPresentation,
            $fullContext,
            $omittedContext
        );

        $result = [
            'evidence_id' => $evidence['id'] ?? uniqid('ev_'),
            'evidence_type' => $evidence['type'] ?? 'unknown',
            'prosecution_presentation' => $prosecutionPresentation,
            'full_context' => $fullContext,
            'selective_presentation' => $selectivePresentation,
            'omitted_context' => $omittedContext,
            'recontextualization_opportunities' => $recontextualizationOpportunities,
            'analysis_timestamp' => now()->toIso8601String(),
        ];

        Log::info('ContextAnalyzer: Analysis complete', [
            'evidence_id' => $evidence['id'] ?? 'unknown',
            'selective_presentation_detected' => $selectivePresentation['detected'] ?? false,
            'opportunities_found' => count($recontextualizationOpportunities),
        ]);

        return $result;
    }

    /**
     * Extract how prosecution presented this evidence
     *
     * @param  array  $evidence  Evidence item
     * @return array Prosecution's presentation
     */
    protected function extractProsecutionPresentation(array $evidence): array
    {
        return [
            'description' => $evidence['prosecution_description'] ?? $evidence['description'] ?? '',
            'excerpt_shown' => $evidence['prosecution_excerpt'] ?? null,
            'emphasis' => $evidence['prosecution_emphasis'] ?? null,
            'timeline_framing' => $evidence['prosecution_timeline'] ?? null,
            'interpretation' => $evidence['prosecution_interpretation'] ?? null,
        ];
    }

    /**
     * Extract full available context from evidence
     *
     * @param  array  $evidence  Evidence item
     * @param  LegalCase  $case  The legal case
     * @return array Full context
     */
    protected function extractFullContext(array $evidence, LegalCase $case): array
    {
        $context = [
            'full_content' => $evidence['full_content'] ?? $evidence['content'] ?? '',
            'metadata' => $evidence['metadata'] ?? [],
            'timestamps' => $evidence['timestamps'] ?? [],
            'surrounding_evidence' => [],
            'related_documents' => [],
        ];

        // Get surrounding evidence from the case
        if (isset($evidence['collected_at'])) {
            $context['surrounding_evidence'] = $this->getSurroundingEvidence($case, $evidence['collected_at']);
        }

        // Get related documents
        if (isset($evidence['id'])) {
            $context['related_documents'] = $this->getRelatedDocuments($case, $evidence['id']);
        }

        return $context;
    }

    /**
     * Detect selective presentation by prosecution
     *
     * Detects 5 types:
     * 1. Partial messages (SMS/email) - only incriminating part shown
     * 2. Cherry-picked timestamps - ignoring exculpatory timeline
     * 3. Out-of-context media (photos/videos) - misleading framing
     * 4. Partial witness statements - omitting clarifying context
     * 5. Selective financial records - hiding legitimate transactions
     *
     * @param  array  $evidence  Evidence item
     * @param  LegalCase  $case  The legal case
     * @return array Selective presentation analysis
     */
    protected function detectSelectivePresentation(array $evidence, LegalCase $case): array
    {
        Log::info('ContextAnalyzer: Detecting selective presentation', [
            'evidence_id' => $evidence['id'] ?? 'unknown',
            'evidence_type' => $evidence['type'] ?? 'unknown',
        ]);

        $evidenceType = $evidence['type'] ?? 'unknown';
        $prosecutionDesc = $evidence['prosecution_description'] ?? $evidence['description'] ?? '';
        $fullContent = $evidence['full_content'] ?? $evidence['content'] ?? '';
        $metadata = $evidence['metadata'] ?? [];

        // Build prompt for AI analysis
        $prompt = $this->buildSelectivePresentationPrompt($evidenceType, $prosecutionDesc, $fullContent, $metadata);

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a Croatian defense expert analyzing evidence for selective presentation by prosecution. You identify what was shown vs. what was omitted, and why the omission matters for fair trial rights under Croatian law (ZKP Članak 9, Ustav RH Članak 29).'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ]);

            $analysis = json_decode($response['choices'][0]['message']['content'], true);

            Log::info('ContextAnalyzer: Selective presentation detection complete', [
                'evidence_id' => $evidence['id'] ?? 'unknown',
                'detected' => $analysis['selective_presentation_detected'] ?? false,
                'type' => $analysis['type'] ?? 'none',
            ]);

            return [
                'detected' => $analysis['selective_presentation_detected'] ?? false,
                'type' => $analysis['type'] ?? null,
                'severity' => $analysis['severity'] ?? 0,
                'what_prosecutor_showed' => $analysis['what_prosecutor_showed'] ?? '',
                'what_prosecutor_omitted' => $analysis['what_prosecutor_omitted'] ?? '',
                'why_omission_matters' => $analysis['why_omission_matters'] ?? '',
                'legal_basis' => $analysis['legal_basis'] ?? 'ZKP Članak 9 - Objektivnost',
                'fair_trial_violation' => $analysis['fair_trial_violation'] ?? false,
            ];

        } catch (\Exception $e) {
            Log::error('ContextAnalyzer: OpenAI API error in selective presentation detection', [
                'evidence_id' => $evidence['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            // Return conservative default (no selective presentation detected)
            return [
                'detected' => false,
                'type' => null,
                'severity' => 0,
                'error' => 'Analysis failed - unable to detect selective presentation',
            ];
        }
    }

    /**
     * Build prompt for selective presentation detection
     *
     * @param  string  $evidenceType  Type of evidence
     * @param  string  $prosecutionDesc  Prosecution's description
     * @param  string  $fullContent  Full evidence content
     * @param  array  $metadata  Evidence metadata
     * @return string AI prompt
     */
    protected function buildSelectivePresentationPrompt(
        string $evidenceType,
        string $prosecutionDesc,
        string $fullContent,
        array $metadata
    ): string {
        $metadataStr = 'None';
        if (! empty($metadata)) {
            $encoded = json_encode($metadata, JSON_PRETTY_PRINT);
            $metadataStr = ($encoded !== false) ? $encoded : 'Error encoding metadata';
        }

        return <<<PROMPT
Analyze this evidence for selective presentation by Croatian prosecution:

Evidence Type: {$evidenceType}

Prosecution's Presentation:
{$prosecutionDesc}

Full Available Evidence:
{$fullContent}

Metadata (timestamps, locations, etc.):
{$metadataStr}

Detect if prosecutor is engaging in selective presentation by:

1. **Partial Messages** (SMS/email/chat):
   - Showing only incriminating excerpt
   - Omitting prior/following messages that clarify meaning
   - Example: "I'll get the stuff" (omits: "stuff" = groceries)

2. **Cherry-Picked Timestamps**:
   - Highlighting specific times that seem incriminating
   - Ignoring exculpatory timeline evidence
   - Example: Photo "at scene" (omits: 2 hours before crime per metadata)

3. **Out-of-Context Media** (photos/videos):
   - Framing that misleads about what's actually shown
   - Omitting context visible in full media
   - Example: "Defendant with suspicious package" (omits: it's a pizza box)

4. **Partial Witness Statements**:
   - Quoting only incriminating portion
   - Omitting clarifying context from same statement
   - Example: "He was angry" (omits: "in a friendly sports argument")

5. **Selective Financial Records**:
   - Showing suspicious transactions only
   - Hiding legitimate explanations visible in full records
   - Example: "$5000 withdrawal" (omits: loan repayment to brother)

Return JSON analysis:
{
    "selective_presentation_detected": boolean,
    "type": "partial_message|cherry_picked_timeline|out_of_context_media|partial_statement|selective_records|none",
    "severity": 0-100 (how misleading is the omission),
    "what_prosecutor_showed": "Specific excerpt/aspect prosecution emphasized",
    "what_prosecutor_omitted": "Specific context that was left out",
    "why_omission_matters": "How the omitted context changes interpretation",
    "legal_basis": "ZKP Članak 9 (or other relevant Croatian law)",
    "fair_trial_violation": boolean (does omission violate Ustav RH Čl. 29)
}

IMPORTANT:
- Only detect selective presentation if there's ACTUAL omitted context in the evidence
- Do NOT fabricate context that isn't in the evidence
- Base analysis ONLY on provided evidence and metadata
- If full context matches prosecution's presentation, return "selective_presentation_detected": false
PROMPT;
    }

    /**
     * Identify omitted context that changes interpretation
     *
     * @param  array  $evidence  Evidence item
     * @param  LegalCase  $case  The legal case
     * @param  array  $prosecutionPresentation  Prosecution's presentation
     * @param  array  $fullContext  Full available context
     * @return array Omitted context
     */
    protected function identifyOmittedContext(
        array $evidence,
        LegalCase $case,
        array $prosecutionPresentation,
        array $fullContext
    ): array {
        Log::info('ContextAnalyzer: Identifying omitted context', [
            'evidence_id' => $evidence['id'] ?? 'unknown',
        ]);

        $omissions = [];

        // Compare prosecution's presentation to full context
        $prosecutionShowed = $prosecutionPresentation['description'];
        $fullAvailable = $fullContext['full_content'];

        if (empty($fullAvailable) || $prosecutionShowed === $fullAvailable) {
            return [
                'omissions_found' => false,
                'omissions' => [],
            ];
        }

        // Use AI to identify specific omissions
        $prompt = <<<PROMPT
Compare prosecution's presentation to full available evidence and identify omitted context:

Prosecution Showed:
{$prosecutionShowed}

Full Available Evidence:
{$fullAvailable}

Identify specific omissions:
1. What facts/context exist in full evidence but not in prosecution's presentation?
2. How does each omitted fact change the interpretation?
3. Why did prosecutor likely omit each fact?

Return JSON array of omissions:
[
    {
        "omitted_fact": "Specific fact that was left out",
        "where_in_full_evidence": "Quote from full evidence showing this fact",
        "how_it_changes_interpretation": "Why this fact matters for defense",
        "prosecutor_motivation": "Why prosecutor likely omitted this",
        "exculpatory_value": 0-100 (how helpful to defense)
    }
]

CRITICAL:
- Only identify omissions that ACTUALLY exist in the full evidence
- Do NOT invent facts not present in evidence
- Quote directly from full evidence to support each omission
PROMPT;

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a defense expert identifying omitted context. You only report omissions that ACTUALLY exist in the provided evidence. You never fabricate or invent context.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = json_decode($response['choices'][0]['message']['content'], true);

            if (! is_array($result)) {
                Log::warning('ContextAnalyzer: Invalid JSON response from OpenAI');

                return [
                    'omissions_found' => false,
                    'omissions' => [],
                    'error' => 'Invalid JSON response',
                ];
            }

            $omissions = $result['omissions'] ?? [];

            Log::info('ContextAnalyzer: Omitted context identified', [
                'evidence_id' => $evidence['id'] ?? 'unknown',
                'omissions_count' => count($omissions),
            ]);

            // Calculate total exculpatory value safely
            $exculpatoryValues = array_map(fn ($o) => $o['exculpatory_value'] ?? 0, $omissions);

            return [
                'omissions_found' => ! empty($omissions),
                'omissions_count' => count($omissions),
                'omissions' => $omissions,
                'total_exculpatory_value' => array_sum($exculpatoryValues),
            ];

        } catch (\Exception $e) {
            Log::error('ContextAnalyzer: OpenAI API error in omitted context identification', [
                'evidence_id' => $evidence['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'omissions_found' => false,
                'omissions' => [],
                'error' => 'Analysis failed',
            ];
        }
    }

    /**
     * Find recontextualization opportunities for defense
     *
     * @param  array  $evidence  Evidence item
     * @param  array  $prosecutionPresentation  Prosecution's presentation
     * @param  array  $fullContext  Full available context
     * @param  array  $omittedContext  Identified omissions
     * @return array Recontextualization opportunities
     */
    protected function findRecontextualizationOpportunities(
        array $evidence,
        array $prosecutionPresentation,
        array $fullContext,
        array $omittedContext
    ): array {
        Log::info('ContextAnalyzer: Finding recontextualization opportunities', [
            'evidence_id' => $evidence['id'] ?? 'unknown',
        ]);

        if (! ($omittedContext['omissions_found'] ?? false)) {
            return [];
        }

        $opportunities = [];

        // For each omission, create a recontextualization opportunity
        foreach ($omittedContext['omissions'] ?? [] as $omission) {
            $opportunities[] = [
                'type' => 'context_restoration',
                'prosecution_narrative' => $prosecutionPresentation['description'],
                'defense_recontextualization' => $this->generateDefenseRecontextualization(
                    $prosecutionPresentation['description'],
                    $omission
                ),
                'supporting_evidence' => $omission['where_in_full_evidence'] ?? '',
                'exculpatory_value' => $omission['exculpatory_value'] ?? 0,
                'legal_argument' => $this->generateLegalArgument($omission),
                'croatian_law_basis' => 'ZKP Članak 9 - Objektivnost, ZKP Članak 331 - Slobodna ocjena dokaza',
            ];
        }

        // Sort by exculpatory value (most helpful first)
        usort($opportunities, fn ($a, $b) => $b['exculpatory_value'] <=> $a['exculpatory_value']);

        Log::info('ContextAnalyzer: Recontextualization opportunities found', [
            'evidence_id' => $evidence['id'] ?? 'unknown',
            'opportunities_count' => count($opportunities),
        ]);

        return $opportunities;
    }

    /**
     * Generate defense recontextualization based on omitted fact
     *
     * @param  string  $prosecutionNarrative  Prosecution's narrative
     * @param  array  $omission  Omitted fact details
     * @return string Defense recontextualization
     */
    protected function generateDefenseRecontextualization(string $prosecutionNarrative, array $omission): string
    {
        $omittedFact = $omission['omitted_fact'] ?? '';
        $howItChanges = $omission['how_it_changes_interpretation'] ?? '';

        $prompt = <<<PROMPT
Generate a defense recontextualization that restores omitted context:

Prosecution's Narrative:
"{$prosecutionNarrative}"

Omitted Fact:
{$omittedFact}

How It Changes Interpretation:
{$howItChanges}

Generate a concise defense recontextualization (2-3 sentences) that:
1. Acknowledges the evidence exists
2. Provides the full context that prosecutor omitted
3. Explains how full context changes the interpretation
4. Uses factual, non-argumentative tone

Example formats:
- "While prosecution shows [excerpt], the full [message/statement/record] reveals [omitted context], which indicates [defense interpretation]."
- "The complete [evidence type] shows [omitted fact] that prosecution did not present, changing the meaning from [prosecution view] to [defense view]."
- "Prosecution emphasized [shown part], but the surrounding [context] clarifies that [alternative interpretation]."

Return only the recontextualization text (no JSON, no extra formatting).
PROMPT;

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a defense attorney writing factual recontextualizations. You restore omitted context without distorting facts.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'temperature' => 0.3,
                'max_tokens' => 200,
            ]);

            return trim($response['choices'][0]['message']['content']);

        } catch (\Exception $e) {
            Log::error('ContextAnalyzer: Error generating recontextualization', [
                'error' => $e->getMessage(),
            ]);

            return "Full context of evidence shows: {$omittedFact}. This changes the interpretation because: {$howItChanges}";
        }
    }

    /**
     * Generate legal argument for recontextualization
     *
     * @param  array  $omission  Omitted fact details
     * @return string Legal argument
     */
    protected function generateLegalArgument(array $omission): string
    {
        $omittedFact = $omission['omitted_fact'] ?? '';
        $howItChanges = $omission['how_it_changes_interpretation'] ?? '';

        return <<<ARGUMENT
Prema ZKP Članku 9, državno odvjetništvo dužno je voditi računa ne samo o okolnostima koje terete okrivljenika, već i o onima koje ga oslobađaju ili olakšavaju njegovu odgovornost.

Prikrivanjem konteksta ("{$omittedFact}"), tužiteljstvo krši načelo objektivnosti i pravo okrivljenika na pravično suđenje (Ustav RH Članak 29).

Potpuni kontekst pokazuje: {$howItChanges}

Sukladno ZKP Članku 331 (slobodna ocjena dokaza), sud mora cijeniti dokaze u njihovom punom kontekstu, a ne na temelju selektivne prezentacije tužiteljstva.
ARGUMENT;
    }

    /**
     * Get surrounding evidence from case timeline
     *
     * @param  LegalCase  $case  The legal case
     * @param  string  $timestamp  Reference timestamp
     * @return array Surrounding evidence
     */
    protected function getSurroundingEvidence(LegalCase $case, string $timestamp): array
    {
        $surrounding = [];

        try {
            // Get evidence collected within 24 hours before/after
            $referenceTime = \Carbon\Carbon::parse($timestamp);
            $beforeTime = $referenceTime->copy()->subHours(24);
            $afterTime = $referenceTime->copy()->addHours(24);
        } catch (\Exception $e) {
            Log::warning('ContextAnalyzer: Invalid timestamp format', [
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
            ]);

            return $surrounding;
        }

        // Check if evidence relationship is loaded
        if (! $case->relationLoaded('evidence')) {
            Log::debug('ContextAnalyzer: Evidence relationship not loaded, loading now');
            $case->load('evidence');
        }

        if ($case->evidence && $case->evidence->isNotEmpty()) {
            foreach ($case->evidence as $ev) {
                if (empty($ev->collected_at)) {
                    continue;
                }

                try {
                    $collectedAt = \Carbon\Carbon::parse($ev->collected_at);

                    if ($collectedAt->between($beforeTime, $afterTime)) {
                        $surrounding[] = [
                            'id' => $ev->id,
                            'type' => $ev->evidence_type ?? 'unknown',
                            'description' => $ev->description ?? '',
                            'collected_at' => $ev->collected_at,
                            'time_difference' => $collectedAt->diffInHours($referenceTime).' hours',
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('ContextAnalyzer: Invalid evidence collected_at format', [
                        'evidence_id' => $ev->id,
                        'collected_at' => $ev->collected_at,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }
        }

        return $surrounding;
    }

    /**
     * Get related documents from case
     *
     * @param  LegalCase  $case  The legal case
     * @param  string  $evidenceId  Evidence ID
     * @return array Related documents
     */
    protected function getRelatedDocuments(LegalCase $case, string $evidenceId): array
    {
        $related = [];

        if ($case->documents && $case->documents->isNotEmpty()) {
            foreach ($case->documents as $doc) {
                $content = $doc->content ?? '';
                $title = $doc->title ?? '';

                // Check if document specifically mentions this evidence ID or title
                // More selective than just searching for "evidence"
                if (stripos($content, $evidenceId) !== false ||
                    stripos($title, $evidenceId) !== false) {
                    $related[] = [
                        'id' => $doc->id,
                        'title' => $title,
                        'document_type' => $doc->document_type ?? 'unknown',
                        'filed_at' => $doc->filed_at ?? null,
                    ];
                }
            }
        }

        return array_slice($related, 0, 5); // Limit to 5 most relevant
    }
}
