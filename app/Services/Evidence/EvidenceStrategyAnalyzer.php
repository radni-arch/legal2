<?php

namespace App\Services\Evidence;

use App\Models\LegalFactPattern;
use App\Services\DecisionSearchService;
use App\Services\LegalReasoning\FactPatternExtractor;
use Illuminate\Support\Facades\Log;

/**
 * Evidence Strategy Analyzer
 *
 * Analyzes fact patterns to develop comprehensive evidence collection
 * and presentation strategies. Integrates with the evidence module to
 * identify what evidence is needed, how to obtain it, and how to present it.
 *
 * Use Case: After extracting facts from a case, automatically generate
 * a complete evidence collection plan with specific action items.
 */
class EvidenceStrategyAnalyzer
{
    public function __construct(
        protected FactPatternExtractor $factExtractor,
        protected DecisionSearchService $decisionSearch
    ) {}

    /**
     * Analyze fact pattern and generate evidence strategy
     *
     * @param  string  $factPatternId  UUID of fact pattern
     * @return array Comprehensive evidence strategy
     */
    public function analyzeEvidenceNeeds(string $factPatternId): array
    {
        $factPattern = $this->factExtractor->getFactPattern($factPatternId);

        if (! $factPattern) {
            throw new \Exception('Fact pattern not found');
        }

        Log::info('EvidenceStrategyAnalyzer - Starting analysis', [
            'fact_pattern_id' => $factPatternId,
            'legal_area' => $factPattern->legal_area,
        ]);

        $facts = $factPattern->structured_facts;

        // Analyze what evidence exists
        $existingEvidence = $this->analyzeExistingEvidence($facts);

        // Identify evidence gaps
        $evidenceGaps = $this->identifyEvidenceGaps($facts);

        // Generate discovery plan
        $discoveryPlan = $this->generateDiscoveryPlan($facts, $evidenceGaps);

        // Find precedents about evidence requirements
        $evidencePrecedents = $this->findEvidencePrecedentsInternal($factPattern);

        // Generate evidence presentation strategy
        $presentationStrategy = $this->generatePresentationStrategy($facts, $existingEvidence);

        // Identify corroboration needs
        $corroborationNeeds = $this->identifyCorroborationNeeds($facts);

        // Calculate evidence strength score
        $strengthScore = $this->calculateEvidenceStrength($facts);

        return [
            'fact_pattern_id' => $factPatternId,
            'legal_area' => $factPattern->legal_area,
            'overall_strength_score' => $strengthScore,
            'existing_evidence' => $existingEvidence,
            'evidence_gaps' => $evidenceGaps,
            'discovery_plan' => $discoveryPlan,
            'corroboration_needs' => $corroborationNeeds,
            'presentation_strategy' => $presentationStrategy,
            'evidence_precedents' => $evidencePrecedents,
            'recommendations' => $this->generateRecommendations($facts, $strengthScore, $evidenceGaps),
        ];
    }

    /**
     * Analyze existing evidence from fact pattern
     */
    protected function analyzeExistingEvidence(array $facts): array
    {
        $evidence = $facts['evidence'] ?? [];

        $analyzed = [
            'total_count' => count($evidence),
            'by_type' => [],
            'by_strength' => [
                'strong' => [],
                'moderate' => [],
                'weak' => [],
            ],
            'by_availability' => [
                'available' => [],
                'needs_discovery' => [],
                'unknown' => [],
            ],
            'critical_evidence' => [],
        ];

        foreach ($evidence as $item) {
            $type = $item['type'] ?? 'unknown';
            $strength = $item['strength'] ?? 'unknown';
            $availability = $item['availability'] ?? 'unknown';

            // Count by type
            if (! isset($analyzed['by_type'][$type])) {
                $analyzed['by_type'][$type] = 0;
            }
            $analyzed['by_type'][$type]++;

            // Categorize by strength
            if (isset($analyzed['by_strength'][$strength])) {
                $analyzed['by_strength'][$strength][] = $item;
            }

            // Categorize by availability
            if (isset($analyzed['by_availability'][$availability])) {
                $analyzed['by_availability'][$availability][] = $item;
            }

            // Identify critical evidence
            if ($strength === 'strong' && $availability === 'available') {
                $analyzed['critical_evidence'][] = $item;
            }
        }

        return $analyzed;
    }

    /**
     * Identify gaps in evidence collection
     */
    protected function identifyEvidenceGaps(array $facts): array
    {
        $missingEvidence = [];
        $weakEvidence = [];

        // Check for disputed facts without evidence
        $disputedFacts = $facts['disputed_facts'] ?? [];
        foreach ($disputedFacts as $disputedFact) {
            $missingEvidence[] = [
                'type' => 'disputed_fact_without_evidence',
                'description' => "Need evidence to support: {$disputedFact}",
                'priority' => 'high',
                'suggested_evidence_types' => ['documentary', 'testimonial'],
            ];
        }

        // Check for events without documentation
        $events = $facts['events'] ?? [];
        foreach ($events as $event) {
            $missingEvidence[] = [
                'type' => 'undocumented_event',
                'description' => "Need documentation for: {$event['description']}",
                'event_date' => $event['date'] ?? null,
                'priority' => 'medium',
                'suggested_evidence_types' => ['documentary', 'testimonial', 'physical'],
            ];
        }

        // Check for parties without contact information
        $parties = $facts['parties'] ?? [];
        foreach ($parties as $party) {
            if ($party['role'] !== 'plaintiff') {
                $missingEvidence[] = [
                    'type' => 'party_information_gap',
                    'description' => "Need complete information for: {$party['name']}",
                    'priority' => 'low',
                    'suggested_evidence_types' => ['documentary'],
                ];
            }
        }

        // Check for financial claims without documentation
        $damages = $facts['damages_or_relief_sought'] ?? [];
        if (($damages['type'] ?? '') === 'monetary') {
            $missingEvidence[] = [
                'type' => 'financial_documentation_gap',
                'description' => 'Need financial records to support damages claim',
                'priority' => 'high',
                'suggested_evidence_types' => ['documentary', 'expert'],
            ];
        }

        // Check for legal issues without evidence
        $legalIssues = $facts['legal_issues'] ?? [];
        foreach ($legalIssues as $issue) {
            $elements = $issue['elements'] ?? [];
            foreach ($elements as $element) {
                $missingEvidence[] = [
                    'type' => 'element_without_evidence',
                    'description' => "Need evidence to prove element: {$element}",
                    'legal_issue' => $issue['issue'] ?? 'Unknown',
                    'priority' => 'high',
                    'suggested_evidence_types' => ['documentary', 'testimonial', 'expert'],
                ];
            }
        }

        // Identify weak evidence that needs corroboration
        $evidence = $facts['evidence'] ?? [];
        foreach ($evidence as $item) {
            if (($item['strength'] ?? '') === 'weak' || ($item['availability'] ?? '') === 'unknown') {
                $weakEvidence[] = [
                    'description' => $item['description'] ?? 'Weak evidence item',
                    'type' => $item['type'] ?? 'unknown',
                    'reason' => ($item['strength'] ?? '') === 'weak' ? 'Weak strength' : 'Availability unknown',
                    'recommendation' => 'Obtain corroborating evidence or improve availability',
                ];
            }
        }

        return [
            'missing_evidence' => $missingEvidence,
            'weak_evidence' => $weakEvidence,
            'total_gaps' => count($missingEvidence) + count($weakEvidence),
        ];
    }

    /**
     * Generate discovery plan based on gaps
     */
    protected function generateDiscoveryPlan(array $facts, array $gaps): array
    {
        $plan = [
            'interrogatories' => [],
            'document_requests' => [],
            'deposition_targets' => [],
            'expert_witnesses_needed' => [],
            'timeline' => [],
        ];

        $missingEvidence = $gaps['missing_evidence'] ?? [];

        // Generate interrogatories
        foreach ($missingEvidence as $gap) {
            if ($gap['type'] === 'disputed_fact_without_evidence') {
                $plan['interrogatories'][] = [
                    'question' => "Provide all facts supporting your position regarding: {$gap['description']}",
                    'purpose' => 'Establish factual basis for disputed claim',
                ];
            }
        }

        // Add interrogatories for disputed facts
        $disputedFacts = $facts['disputed_facts'] ?? [];
        foreach ($disputedFacts as $fact) {
            $plan['interrogatories'][] = [
                'question' => "Describe in detail all facts and circumstances relating to: {$fact}",
                'purpose' => 'Clarify disputed factual allegations',
            ];
        }

        // Generate document requests
        $damages = $facts['damages_or_relief_sought'] ?? [];
        if (($damages['type'] ?? '') === 'monetary') {
            $plan['document_requests'][] = [
                'request' => 'All financial records related to claimed damages',
                'category' => 'financial',
                'deadline' => '30 days from service',
            ];
        }

        // Add document requests for events
        $events = $facts['events'] ?? [];
        foreach ($events as $event) {
            $plan['document_requests'][] = [
                'request' => "All documents related to: {$event['description']}",
                'category' => 'event_documentation',
                'deadline' => '30 days from service',
            ];
        }

        // Add document requests for disputed facts
        foreach ($disputedFacts as $fact) {
            $plan['document_requests'][] = [
                'request' => "All documents, communications, and records relating to: {$fact}",
                'category' => 'disputed_fact_documentation',
                'deadline' => '30 days from service',
            ];
        }

        // Identify deposition targets
        $parties = $facts['parties'] ?? [];
        foreach ($parties as $party) {
            if ($party['role'] === 'defendant' || $party['role'] === 'witness') {
                $plan['deposition_targets'][] = [
                    'name' => $party['name'],
                    'role' => $party['role'],
                    'topics' => $this->generateDepositionTopics($facts, $party),
                ];
            }
        }

        // Identify expert witness needs based on legal area
        $legalArea = $facts['legal_area'] ?? '';
        if (in_array($legalArea, ['medical', 'construction', 'financial'])) {
            $plan['expert_witnesses_needed'][] = [
                'type' => ucfirst($legalArea).' expert',
                'purpose' => 'Establish industry standards and damages',
                'deadline' => '60 days before trial',
            ];
        }

        // Identify expert witness needs based on legal issues
        $legalIssues = $facts['legal_issues'] ?? [];
        foreach ($legalIssues as $issue) {
            $issueType = strtolower($issue['issue'] ?? '');
            if (str_contains($issueType, 'medical') || str_contains($issueType, 'malpractice')) {
                $plan['expert_witnesses_needed'][] = [
                    'type' => 'Medical expert',
                    'purpose' => 'Establish standard of care and causation',
                    'specialty' => 'Medical professional in relevant specialty',
                    'deadline' => '60 days before trial',
                ];
            } elseif (str_contains($issueType, 'expert') || str_contains($issueType, 'testimony')) {
                $plan['expert_witnesses_needed'][] = [
                    'type' => 'Subject matter expert',
                    'purpose' => 'Provide expert testimony on technical matters',
                    'deadline' => '60 days before trial',
                ];
            }
        }

        // Create timeline with proper structure
        $plan['timeline'] = [
            'phase_1' => [
                'description' => 'Initial Discovery',
                'duration' => '30 days',
                'activities' => ['Serve interrogatories', 'Serve document requests', 'Initial case assessment'],
            ],
            'phase_2' => [
                'description' => 'Document Review',
                'duration' => '30 days',
                'activities' => ['Review produced documents', 'Identify gaps', 'Request supplemental documents'],
            ],
            'phase_3' => [
                'description' => 'Depositions',
                'duration' => '60 days',
                'activities' => ['Schedule depositions', 'Prepare questions', 'Conduct depositions'],
            ],
            'phase_4' => [
                'description' => 'Expert Witnesses',
                'duration' => '45 days',
                'activities' => ['Retain experts', 'Provide case materials', 'Obtain expert reports'],
            ],
        ];

        return $plan;
    }

    /**
     * Find precedents about evidence requirements (public method for direct access)
     */
    public function findEvidencePrecedents(string $factPatternId): array
    {
        $factPattern = $this->factExtractor->getFactPattern($factPatternId);

        if (! $factPattern) {
            throw new \Exception('Fact pattern not found');
        }

        return $this->findEvidencePrecedentsInternal($factPattern);
    }

    /**
     * Find precedents about evidence requirements (internal method)
     */
    protected function findEvidencePrecedentsInternal(LegalFactPattern $factPattern): array
    {
        $legalArea = $factPattern->legal_area;
        $facts = $factPattern->structured_facts;
        $legalIssues = $facts['legal_issues'] ?? [];

        $similarCases = [];
        $admissibilityStandards = [];
        $relevantRulings = [];

        try {
            // Search for similar cases with evidence issues
            $query = "evidence requirements {$legalArea} burden of proof";
            $results = $this->decisionSearch->search($query, [
                'limit' => 5,
                'use_vector' => true,
            ]);

            foreach ($results['results'] ?? [] as $result) {
                $similarCases[] = [
                    'case_number' => $result['case_number'] ?? 'Unknown',
                    'court' => $result['court'] ?? 'Unknown',
                    'relevance' => 'Evidence standards and requirements',
                    'summary' => $result['summary'] ?? '',
                    'year' => $result['year'] ?? 'Unknown',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to find evidence precedents', [
                'error' => $e->getMessage(),
            ]);
        }

        // Generate admissibility standards based on legal area and issues
        $admissibilityStandards = $this->generateAdmissibilityStandards($legalArea, $legalIssues);

        // Generate relevant rulings based on evidence types in fact pattern
        $evidence = $facts['evidence'] ?? [];
        foreach ($evidence as $item) {
            $type = $item['type'] ?? 'unknown';
            if ($type === 'expert') {
                $relevantRulings[] = [
                    'standard' => 'Daubert/Frye Standard',
                    'description' => 'Expert testimony must be based on reliable principles and methods',
                    'application' => 'Applies to expert witness testimony',
                ];
            }
            if ($type === 'testimonial') {
                $relevantRulings[] = [
                    'standard' => 'Hearsay Rule',
                    'description' => 'Out-of-court statements offered for truth generally inadmissible',
                    'application' => 'Review testimony for hearsay exceptions',
                ];
            }
            if ($type === 'documentary') {
                $relevantRulings[] = [
                    'standard' => 'Authentication Requirement',
                    'description' => 'Documents must be authenticated before admission',
                    'application' => 'Establish chain of custody and authenticity',
                ];
            }
        }

        // Add default rulings if none found
        if (empty($relevantRulings)) {
            $relevantRulings[] = [
                'standard' => 'Relevance Standard',
                'description' => 'Evidence must be relevant to be admissible',
                'application' => 'All evidence must have tendency to prove or disprove material fact',
            ];
        }

        return [
            'similar_cases' => $similarCases,
            'admissibility_standards' => $admissibilityStandards,
            'relevant_rulings' => $relevantRulings,
        ];
    }

    /**
     * Generate admissibility standards based on legal area
     */
    protected function generateAdmissibilityStandards(string $legalArea, array $legalIssues): array
    {
        $standards = [];

        // General relevance standard
        $standards[] = [
            'name' => 'Relevance',
            'description' => 'Evidence must be relevant to a material issue in the case',
            'cite' => 'FRE 401-402',
        ];

        // Area-specific standards
        if ($legalArea === 'tort' || in_array($legalArea, ['medical', 'negligence'])) {
            $standards[] = [
                'name' => 'Expert Testimony Standard',
                'description' => 'Expert testimony must assist trier of fact and be based on reliable methodology',
                'cite' => 'FRE 702, Daubert v. Merrell Dow Pharmaceuticals',
            ];
        }

        // Issue-specific standards
        foreach ($legalIssues as $issue) {
            $issueType = strtolower($issue['issue'] ?? '');
            if (str_contains($issueType, 'admissibility')) {
                $standards[] = [
                    'name' => 'Authentication',
                    'description' => 'Evidence must be authenticated or identified',
                    'cite' => 'FRE 901',
                ];
            }
        }

        // Default if no specific standards
        if (count($standards) === 1) {
            $standards[] = [
                'name' => 'Hearsay Exclusion',
                'description' => 'Out-of-court statements offered for truth are generally inadmissible',
                'cite' => 'FRE 802',
            ];
            $standards[] = [
                'name' => 'Best Evidence Rule',
                'description' => 'Original documents required to prove contents',
                'cite' => 'FRE 1002',
            ];
        }

        return $standards;
    }

    /**
     * Generate evidence presentation strategy
     */
    protected function generatePresentationStrategy(array $facts, array $existingEvidence): array
    {
        $strategy = [
            'key_evidence' => [],
            'supporting_evidence' => [],
            'evidence_order' => [],
            'trial_strategy' => [],
            'visual_aids_needed' => [],
            'technology_requirements' => [],
        ];

        // Identify key evidence (strong and available)
        $strongEvidence = $existingEvidence['by_strength']['strong'] ?? [];
        $criticalEvidence = $existingEvidence['critical_evidence'] ?? [];

        $strategy['key_evidence'] = ! empty($criticalEvidence) ? $criticalEvidence : $strongEvidence;

        // Identify supporting evidence (moderate strength)
        $moderateEvidence = $existingEvidence['by_strength']['moderate'] ?? [];
        $strategy['supporting_evidence'] = $moderateEvidence;

        // Build evidence order
        $evidenceOrder = [];

        // Start with strongest documentary evidence
        if (! empty($strongEvidence)) {
            foreach (array_slice($strongEvidence, 0, 2) as $evidence) {
                $evidenceOrder[] = [
                    'type' => $evidence['type'] ?? 'documentary',
                    'description' => $evidence['description'] ?? 'Strong evidence',
                    'timing' => 'opening',
                    'purpose' => 'Establish credibility immediately',
                ];
            }
        }

        // Build chronological sequence for core evidence based on events
        $events = $facts['events'] ?? [];
        foreach ($events as $event) {
            $evidenceOrder[] = [
                'type' => 'chronological',
                'description' => $event['description'] ?? 'Event',
                'date' => $event['date'] ?? null,
                'timing' => 'case_in_chief',
                'purpose' => 'Establish sequence of events',
            ];
        }

        // Add testimonial evidence
        $testimonialEvidence = array_filter($existingEvidence['by_type'] ?? [], function ($count, $type) {
            return $type === 'testimonial' && $count > 0;
        }, ARRAY_FILTER_USE_BOTH);

        if (! empty($testimonialEvidence)) {
            $evidenceOrder[] = [
                'type' => 'testimonial',
                'description' => 'Witness testimony',
                'timing' => 'case_in_chief',
                'purpose' => 'Corroborate documentary evidence',
            ];
        }

        // Add expert evidence
        $expertEvidence = array_filter($existingEvidence['by_type'] ?? [], function ($count, $type) {
            return $type === 'expert' && $count > 0;
        }, ARRAY_FILTER_USE_BOTH);

        if (! empty($expertEvidence)) {
            $evidenceOrder[] = [
                'type' => 'expert',
                'description' => 'Expert testimony',
                'timing' => 'case_in_chief',
                'purpose' => 'Establish causation and damages',
            ];
        }

        // End with impact evidence
        $damages = $facts['damages_or_relief_sought'] ?? [];
        $evidenceOrder[] = [
            'type' => 'damages',
            'description' => $damages['description'] ?? 'Damages evidence',
            'timing' => 'closing',
            'purpose' => 'Demonstrate harm and justify relief',
        ];

        $strategy['evidence_order'] = $evidenceOrder;

        // Create comprehensive trial strategy
        $strategy['trial_strategy'] = [
            'opening' => [
                'approach' => 'Start with strongest evidence to establish credibility',
                'key_points' => [
                    'Present most compelling documentary evidence first',
                    'Set narrative framework for entire case',
                    'Preview key testimony to come',
                ],
                'evidence_to_reference' => array_slice($strategy['key_evidence'], 0, 3),
            ],
            'case_in_chief' => [
                'approach' => 'Build case chronologically with supporting evidence',
                'key_points' => [
                    'Present events in chronological order',
                    'Use witnesses to corroborate documents',
                    'Introduce expert testimony to support technical claims',
                    'Address disputed facts head-on',
                ],
                'evidence_sequence' => array_filter($evidenceOrder, function ($item) {
                    return ($item['timing'] ?? '') === 'case_in_chief';
                }),
            ],
            'closing' => [
                'approach' => 'Synthesize all evidence to demonstrate case strength',
                'key_points' => [
                    'Recap strongest evidence presented',
                    'Connect evidence to legal elements required',
                    'Emphasize damages and need for relief',
                    'Address weaknesses proactively',
                ],
                'evidence_to_emphasize' => array_merge(
                    array_slice($strategy['key_evidence'], 0, 2),
                    [['type' => 'damages', 'description' => 'Impact evidence']]
                ),
            ],
        ];

        // Suggest visual aids
        $strategy['visual_aids_needed'] = [
            'Timeline chart showing sequence of events',
            'Financial impact diagram',
            'Key document excerpts enlarged for display',
            'Demonstrative exhibits for complex concepts',
        ];

        // Technology needs
        $strategy['technology_requirements'] = [
            'Presentation software',
            'Document camera or visualizer',
            'Large display screen',
            'Audio/video playback capability',
        ];

        return $strategy;
    }

    /**
     * Identify corroboration needs
     */
    protected function identifyCorroborationNeeds(array $facts): array
    {
        $uncorroboratedTestimony = [];
        $disputedFactsNeedingSupport = [];
        $recommendations = [];

        // Identify testimonial evidence that needs corroboration
        $evidence = $facts['evidence'] ?? [];
        foreach ($evidence as $item) {
            if (($item['type'] ?? '') === 'testimonial') {
                $uncorroboratedTestimony[] = [
                    'description' => $item['description'] ?? 'Testimonial evidence',
                    'strength' => $item['strength'] ?? 'unknown',
                    'needs_corroboration' => true,
                    'suggested_corroboration' => 'Documentary evidence or additional witnesses',
                ];
            }
        }

        // Identify disputed facts that need support
        $disputedFacts = $facts['disputed_facts'] ?? [];
        foreach ($disputedFacts as $fact) {
            $disputedFactsNeedingSupport[] = [
                'fact' => $fact,
                'current_support' => 'insufficient',
                'recommended_evidence' => ['documentary', 'testimonial', 'expert'],
                'priority' => 'high',
            ];
        }

        // Generate recommendations
        if (! empty($uncorroboratedTestimony)) {
            $recommendations[] = [
                'category' => 'testimonial_corroboration',
                'recommendation' => 'Obtain corroborating evidence for all testimonial statements',
                'action_items' => [
                    'Seek documentary evidence that supports witness accounts',
                    'Identify additional witnesses who can corroborate testimony',
                    'Consider expert testimony where applicable',
                ],
            ];
        }

        if (! empty($disputedFactsNeedingSupport)) {
            $recommendations[] = [
                'category' => 'disputed_facts',
                'recommendation' => 'Strengthen evidence for disputed facts through discovery',
                'action_items' => [
                    'Issue targeted interrogatories',
                    'Request production of relevant documents',
                    'Schedule depositions of key witnesses',
                ],
            ];
        }

        // Check for single-source evidence
        $favorableFacts = $facts['facts_favorable_to_plaintiff'] ?? [];
        if (! empty($favorableFacts)) {
            foreach ($favorableFacts as $fact) {
                $recommendations[] = [
                    'category' => 'favorable_facts',
                    'recommendation' => "Corroborate favorable fact: {$fact}",
                    'action_items' => [
                        'Obtain witness testimony or documentary evidence',
                        'Consider expert analysis if applicable',
                    ],
                ];
            }
        }

        return [
            'uncorroborated_testimony' => $uncorroboratedTestimony,
            'disputed_facts_needing_support' => $disputedFactsNeedingSupport,
            'recommendations' => $recommendations,
            'total_items_needing_corroboration' => count($uncorroboratedTestimony) + count($disputedFactsNeedingSupport),
        ];
    }

    /**
     * Calculate overall evidence strength score
     */
    protected function calculateEvidenceStrength(array $facts): float
    {
        $evidence = $facts['evidence'] ?? [];
        $totalEvidence = count($evidence);

        // If there's no evidence at all, start with very low base score
        if ($totalEvidence === 0) {
            $score = 0.2; // Very low base score for cases with no evidence
        } else {
            $score = 0.5; // Start neutral if there's some evidence
        }

        // Count strong vs weak evidence
        $strongCount = 0;
        $weakCount = 0;
        $availableCount = 0;
        $unavailableCount = 0;

        foreach ($evidence as $item) {
            if (($item['strength'] ?? '') === 'strong') {
                $strongCount++;
            }
            if (($item['strength'] ?? '') === 'weak') {
                $weakCount++;
            }
            if (($item['availability'] ?? '') === 'available') {
                $availableCount++;
            }
            if (in_array($item['availability'] ?? '', ['unknown', 'needs_discovery'])) {
                $unavailableCount++;
            }
        }

        // Strong evidence increases score
        $score += min(0.3, $strongCount * 0.1);

        // Weak evidence decreases score
        $score -= min(0.2, $weakCount * 0.05);

        // Available evidence increases score
        $score += min(0.2, $availableCount * 0.05);

        // Unavailable evidence decreases score
        $score -= min(0.15, $unavailableCount * 0.04);

        // Disputed facts without evidence decrease score
        $disputedCount = count($facts['disputed_facts'] ?? []);
        $score -= min(0.25, $disputedCount * 0.04);

        // Undisputed facts increase score
        $undisputedCount = count($facts['undisputed_facts'] ?? []);
        $score += min(0.15, $undisputedCount * 0.02);

        return max(0, min(1, $score));
    }

    /**
     * Generate recommendations
     */
    protected function generateRecommendations(array $facts, float $strengthScore, array $gaps): array
    {
        $recommendations = [];

        $totalGaps = $gaps['total_gaps'] ?? 0;
        $missingEvidence = $gaps['missing_evidence'] ?? [];

        if ($strengthScore < 0.5) {
            $recommendations[] = [
                'priority' => 'urgent',
                'recommendation' => 'Evidence is currently weak. Immediate action needed to strengthen case.',
                'actions' => [
                    'Conduct thorough discovery',
                    'Identify and interview witnesses',
                    'Obtain expert opinions',
                ],
            ];
        }

        if ($totalGaps > 5) {
            $recommendations[] = [
                'priority' => 'high',
                'recommendation' => 'Multiple evidence gaps identified. Systematic collection plan needed.',
                'actions' => [
                    'Create prioritized evidence collection checklist',
                    'Assign collection responsibilities',
                    'Set deadlines for each item',
                ],
            ];
        }

        // Check for missing key evidence types
        $evidence = $facts['evidence'] ?? [];
        $hasDocumentary = collect($evidence)->contains('type', 'documentary');
        $hasTestimonial = collect($evidence)->contains('type', 'testimonial');

        if (! $hasDocumentary) {
            $recommendations[] = [
                'priority' => 'high',
                'recommendation' => 'No documentary evidence identified. Essential for most cases.',
                'actions' => [
                    'Request all relevant documents from client',
                    'Issue document production requests to opposing party',
                    'Subpoena third-party records if necessary',
                ],
            ];
        }

        if (! $hasTestimonial) {
            $recommendations[] = [
                'priority' => 'medium',
                'recommendation' => 'No testimonial evidence identified. Consider witness interviews.',
                'actions' => [
                    'Identify potential witnesses',
                    'Conduct preliminary interviews',
                    'Prepare witness statements',
                ],
            ];
        }

        return $recommendations;
    }

    /**
     * Generate deposition topics for a party
     */
    protected function generateDepositionTopics(array $facts, array $party): array
    {
        $topics = [];

        // Topics based on events
        $events = $facts['events'] ?? [];
        foreach ($events as $event) {
            $topics[] = "Knowledge of and involvement in: {$event['description']}";
        }

        // Topics based on disputed facts
        $disputedFacts = $facts['disputed_facts'] ?? [];
        foreach ($disputedFacts as $fact) {
            $topics[] = "Position and evidence regarding: {$fact}";
        }

        // Topics based on legal issues
        $legalIssues = $facts['legal_issues'] ?? [];
        foreach ($legalIssues as $issue) {
            $issueDescription = $issue['issue'] ?? 'legal issue';
            $topics[] = "Facts relevant to: {$issueDescription}";
        }

        return array_slice($topics, 0, 10); // Top 10 topics
    }

    /**
     * Generate detailed evidence checklist
     */
    public function generateEvidenceChecklist(string $factPatternId): array
    {
        $analysis = $this->analyzeEvidenceNeeds($factPatternId);

        $checklist = [
            'case_id' => $factPatternId,
            'generated_at' => now()->toISOString(),
            'items' => [],
        ];

        // Add existing evidence items
        foreach ($analysis['existing_evidence']['by_availability']['available'] ?? [] as $evidence) {
            $checklist['items'][] = [
                'status' => 'complete',
                'description' => $evidence['description'] ?? 'Evidence item',
                'type' => $evidence['type'] ?? 'unknown',
                'priority' => 'n/a',
            ];
        }

        // Add gap items from missing evidence
        $missingEvidence = $analysis['evidence_gaps']['missing_evidence'] ?? [];
        foreach ($missingEvidence as $gap) {
            $checklist['items'][] = [
                'status' => 'pending',
                'description' => $gap['description'],
                'type' => $gap['type'],
                'priority' => $gap['priority'],
                'deadline' => $this->calculateDeadline($gap['priority']),
            ];
        }

        // Add gap items from weak evidence
        $weakEvidence = $analysis['evidence_gaps']['weak_evidence'] ?? [];
        foreach ($weakEvidence as $gap) {
            $checklist['items'][] = [
                'status' => 'pending',
                'description' => $gap['description'].' - '.$gap['reason'],
                'type' => $gap['type'],
                'priority' => 'medium',
                'deadline' => $this->calculateDeadline('medium'),
            ];
        }

        return $checklist;
    }

    /**
     * Calculate deadline based on priority
     */
    protected function calculateDeadline(string $priority): string
    {
        return match ($priority) {
            'high' => now()->addDays(7)->toDateString(),
            'medium' => now()->addDays(14)->toDateString(),
            'low' => now()->addDays(30)->toDateString(),
            default => now()->addDays(14)->toDateString(),
        };
    }
}
