<?php

namespace App\Modules\Topics;

use App\Models\LegalCase;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * TopicAnalyzer (Base Class for All Abuse Topics)
 *
 * Abstract base class for analyzing specific types of prosecutorial abuse.
 * Each topic (home searches, drug charges, bail, etc.) extends this class.
 *
 * DESIGN PATTERN:
 * This follows the Strategy pattern - each topic is a different strategy
 * for detecting and analyzing prosecutorial abuse.
 *
 * TOPICS SUPPORTED:
 * 1. Home Search Abuse (HomeSearchAbuseDetector)
 * 2. Drug Charge Severity Abuse (DrugChargeAbuseDetector)
 * 3. Bail Denial Patterns (BailAbuseDetector) - future
 * 4. Pre-trial Detention Abuse (DetentionAbuseDetector) - future
 * 5. Witness Intimidation (WitnessAbuseDetector) - future
 * 6. ... (easily extensible)
 *
 * COMMON QUESTIONS ALL TOPICS ANSWER:
 * - How common is this abuse?
 * - Which regions are worse? (Osijek vs Zadar)
 * - Which prosecutors/judges are repeat offenders?
 * - What are the legal violations?
 * - What defense strategies work?
 * - What statistical evidence exists?
 *
 * HOW TO ADD A NEW TOPIC:
 * 1. Extend TopicAnalyzer
 * 2. Implement abstract methods
 * 3. Define detection criteria
 * 4. Register in TopicRegistry
 * 5. Done!
 *
 * EXAMPLE NEW TOPIC:
 * class DrugChargeAbuseDetector extends TopicAnalyzer {
 *     protected string $topicName = 'drug_charge_severity';
 *     protected string $topicDescription = 'Overcharging in drug cases';
 *     // ... implement methods
 * }
 */
abstract class TopicAnalyzer
{
    /** @var string Unique topic identifier */
    protected string $topicName;

    /** @var string Human-readable description */
    protected string $topicDescription;

    /** @var array Croatian legal framework for this topic */
    protected array $legalFramework = [];

    public function __construct(
        protected OpenAIService $openAI,
        protected OdlukeSearchAgent $odlukeAgent
    ) {}

    /**
     * Analyze a specific case for this topic
     *
     * @param  LegalCase  $case  The legal case
     * @param  array  $topicSpecificData  Topic-specific data
     * @return array Analysis result
     */
    abstract public function analyzeCase(LegalCase $case, array $topicSpecificData): array;

    /**
     * Get statistical analysis for this topic
     *
     * @param  array  $criteria  Search criteria (year, region, etc.)
     * @return array Statistical analysis
     */
    abstract public function getStatistics(array $criteria): array;

    /**
     * Detect abuse patterns for this topic
     *
     * @param  array  $data  Topic-specific data
     * @return array Detected patterns
     */
    abstract protected function detectPatterns(array $data): array;

    /**
     * Generate defense strategy for this topic
     *
     * @param  array  $analysis  Case analysis
     * @return array Defense strategies
     */
    abstract protected function generateDefenseStrategy(array $analysis): array;

    /**
     * Compare geographic regions for this topic
     *
     * @param  string  $region1  First region (e.g., "Osijek")
     * @param  string  $region2  Second region (e.g., "Zadar")
     * @param  int  $year  Year to compare
     * @return array Regional comparison
     */
    public function compareRegions(string $region1, string $region2, int $year): array
    {
        Log::info("TopicAnalyzer: Comparing regions for {$this->topicName}", [
            'region1' => $region1,
            'region2' => $region2,
            'year' => $year,
        ]);

        // Get statistics for both regions
        $stats1 = $this->getStatistics(['region' => $region1, 'year' => $year]);
        $stats2 = $this->getStatistics(['region' => $region2, 'year' => $year]);

        // Calculate differences
        $comparison = [
            'topic' => $this->topicName,
            'year' => $year,
            'region1' => [
                'name' => $region1,
                'statistics' => $stats1,
            ],
            'region2' => [
                'name' => $region2,
                'statistics' => $stats2,
            ],
            'differences' => $this->calculateDifferences($stats1, $stats2),
            'worse_region' => $this->determineWorseRegion($stats1, $stats2, $region1, $region2),
            'analysis' => $this->generateRegionalAnalysis($stats1, $stats2, $region1, $region2),
        ];

        return $comparison;
    }

    /**
     * Search odluke.sudovi.hr for cases related to this topic
     *
     * @param  array  $criteria  Search criteria
     * @return array Search results
     */
    protected function searchCases(array $criteria): array
    {
        // Merge topic-specific search terms with criteria
        $searchCriteria = array_merge($criteria, [
            'keywords' => $this->getSearchKeywords(),
            'legal_articles' => $this->legalFramework,
        ]);

        return $this->odlukeAgent->searchHomeSearchCases($searchCriteria);
    }

    /**
     * Get search keywords for this topic
     *
     * @return array Keywords to search for
     */
    abstract protected function getSearchKeywords(): array;

    /**
     * Calculate statistical differences between regions
     *
     * @param  array  $stats1  Region 1 statistics
     * @param  array  $stats2  Region 2 statistics
     * @return array Differences
     */
    protected function calculateDifferences(array $stats1, array $stats2): array
    {
        $differences = [];

        // Find common metrics
        $metrics = array_intersect_key($stats1, $stats2);

        foreach ($metrics as $metric => $value1) {
            $value2 = $stats2[$metric];

            if (is_numeric($value1) && is_numeric($value2)) {
                $difference = $value1 - $value2;
                $percentChange = $value2 != 0 ? (($value1 - $value2) / $value2) * 100 : 0;

                $differences[$metric] = [
                    'region1_value' => $value1,
                    'region2_value' => $value2,
                    'absolute_difference' => $difference,
                    'percent_change' => round($percentChange, 1),
                    'significant' => abs($percentChange) > 20, // 20% threshold
                ];
            }
        }

        return $differences;
    }

    /**
     * Determine which region is worse
     *
     * @param  array  $stats1  Region 1 statistics
     * @param  array  $stats2  Region 2 statistics
     * @param  string  $region1  Region 1 name
     * @param  string  $region2  Region 2 name
     * @return array Worse region determination
     */
    abstract protected function determineWorseRegion(
        array $stats1,
        array $stats2,
        string $region1,
        string $region2
    ): array;

    /**
     * Generate regional analysis
     *
     * @param  array  $stats1  Region 1 statistics
     * @param  array  $stats2  Region 2 statistics
     * @param  string  $region1  Region 1 name
     * @param  string  $region2  Region 2 name
     * @return string Analysis text
     */
    protected function generateRegionalAnalysis(
        array $stats1,
        array $stats2,
        string $region1,
        string $region2
    ): string {
        $stats1Json = json_encode($stats1, JSON_PRETTY_PRINT);
        $stats2Json = json_encode($stats2, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Compare prosecutorial practices between two Croatian regions for: {$this->topicDescription}

Region 1: {$region1}
Statistics: {$stats1Json}

Region 2: {$region2}
Statistics: {$stats2Json}

Analyze:
1. Which region shows more prosecutorial abuse?
2. What are the key differences?
3. What might explain regional disparities?
4. What defense arguments can be made based on this disparity?

Provide 2-3 paragraph analysis in Croatian or English.
PROMPT;

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a Croatian legal analyst comparing regional prosecutorial practices. Focus on identifying patterns of abuse and disparities.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o', [
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

            return trim($response['choices'][0]['message']['content']);

        } catch (\Exception $e) {
            Log::error('TopicAnalyzer: AI analysis failed', [
                'error' => $e->getMessage(),
            ]);

            return "Regionalna analiza pokazuje razlike između {$region1} i {$region2}. ".
                   'Potrebna je dodatna analiza za konačne zaključke.';
        }
    }

    /**
     * Get topic name
     *
     * @return string Topic name
     */
    public function getTopicName(): string
    {
        return $this->topicName;
    }

    /**
     * Get topic description
     *
     * @return string Topic description
     */
    public function getTopicDescription(): string
    {
        return $this->topicDescription;
    }

    /**
     * Get legal framework
     *
     * @return array Legal framework
     */
    public function getLegalFramework(): array
    {
        return $this->legalFramework;
    }

    /**
     * Get all supported topics
     *
     * @return array Available topics
     */
    public static function getAvailableTopics(): array
    {
        return [
            'home_search_abuse' => [
                'class' => 'App\\Modules\\HomeSearch\\Services\\HomeSearchAbuseDetector',
                'description' => 'Disproportionate home search warrants for minor offenses',
                'questions' => [
                    'How many home searches for misdemeanors in {year}?',
                    'Is Osijek worse than national average?',
                    'Which judges issue most warrants for minor offenses?',
                ],
            ],
            'drug_charge_severity' => [
                'class' => 'App\\Modules\\Topics\\Analyzers\\DrugChargeAbuseDetector',
                'description' => 'Overcharging in drug cases (dealing charges for personal use amounts)',
                'questions' => [
                    'How many dealing charges for <50g cannabis?',
                    'Is Osijek worse than Zadar for drug overcharging?',
                    'What percentage of drug cases are overcharged?',
                ],
            ],
            'bail_denial' => [
                'class' => 'App\\Modules\\Topics\\Analyzers\\BailAbuseDetector',
                'description' => 'Excessive bail denial or unreasonable amounts',
                'questions' => [
                    'Bail denial rate by offense severity?',
                    'Average bail amounts by region?',
                    'Which prosecutors request highest bail?',
                ],
                'status' => 'planned',
            ],
            'pretrial_detention' => [
                'class' => 'App\\Modules\\Topics\\Analyzers\\DetentionAbuseDetector',
                'description' => 'Excessive pre-trial detention for minor offenses',
                'questions' => [
                    'Average pre-trial detention length?',
                    'Detention rate for misdemeanors?',
                    'Regional disparities?',
                ],
                'status' => 'planned',
            ],
            'witness_intimidation' => [
                'class' => 'App\\Modules\\Topics\\Analyzers\\WitnessAbuseDetector',
                'description' => 'Prosecutorial intimidation of defense witnesses',
                'questions' => [
                    'Frequency of witness tampering allegations?',
                    'Which prosecutors most often charge witnesses?',
                    'Success rate of intimidation charges?',
                ],
                'status' => 'planned',
            ],
        ];
    }
}
