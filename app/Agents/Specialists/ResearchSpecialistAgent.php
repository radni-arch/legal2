<?php

namespace App\Agents\Specialists;

use App\Services\Collaboration\SharedAgentContext;
use App\Services\DecisionSearchService;
use App\Services\Explainability\ReasoningTraceService;
use App\Services\Graph\GraphResearchEnhancer;
use App\Services\LawSearchService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * Research Specialist Agent
 *
 * Role: Finds relevant laws, court decisions, and legal materials
 * Expertise: Legal research, information retrieval, source validation
 *
 * Sprint 4.3: Enhanced with graph traversal for comprehensive research
 */
class ResearchSpecialistAgent
{
    protected string $name = 'research_specialist';

    protected string $role = 'Legal Researcher';

    protected ?string $rootTraceId = null;

    public function __construct(
        protected LawSearchService $lawSearch,
        protected DecisionSearchService $decisionSearch,
        protected OpenAIService $openai,
        protected ReasoningTraceService $traceService,
        protected ?GraphResearchEnhancer $graphEnhancer = null
    ) {}

    /**
     * Execute research task
     */
    public function execute(SharedAgentContext $context, array $task): array
    {
        $startTime = microtime(true);

        // Start root trace
        $this->rootTraceId = $this->traceService->startTrace(
            operation: 'Legal Research Execution',
            input: [
                'task' => $task,
                'problem_statement' => $context->getProblemStatement(),
            ],
            parentTraceId: null,
            agentType: 'research_specialist',
            stepType: 'research_execution'
        );

        $context->logEvent('research_started', ['task' => $task]);

        $problemStatement = $context->getProblemStatement();

        // 1. Analyze what research is needed
        $researchPlan = $this->planResearch($problemStatement, $task);
        $context->write('research_plan', $researchPlan);

        // 2. Execute search phase (wraps law and decision searches for deeper trace nesting)
        $searchStartTime = microtime(true);
        $searchPhaseTraceId = $this->traceService->startTrace(
            operation: 'Legal Research Search Phase',
            input: ['law_queries' => $researchPlan['law_queries'] ?? [], 'decision_queries' => $researchPlan['decision_queries'] ?? []],
            parentTraceId: $this->rootTraceId,
            agentType: 'research_specialist',
            stepType: 'search_phase'
        );

        // Store search phase trace ID for child searches
        $previousRootTrace = $this->rootTraceId;
        $this->rootTraceId = $searchPhaseTraceId;

        // 2a. Search for relevant laws
        $laws = $this->searchLaws($researchPlan['law_queries'] ?? [$problemStatement]);
        $context->write('researched_laws', $laws);

        // 2b. Search for relevant court decisions
        $decisions = $this->searchDecisions($researchPlan['decision_queries'] ?? [$problemStatement]);
        $context->write('researched_decisions', $decisions);

        // Restore root trace ID
        $this->rootTraceId = $previousRootTrace;

        // End search phase trace
        $searchDurationMs = (int) ((microtime(true) - $searchStartTime) * 1000);
        $this->traceService->endTrace(
            traceId: $searchPhaseTraceId,
            output: ['laws_found' => count($laws), 'decisions_found' => count($decisions)],
            reasoning: 'Executed comprehensive search across law database and court decision database using queries from research plan',
            confidence: 0.88,
            tokensUsed: null,
            durationMs: $searchDurationMs
        );

        // 3. Enhance with graph traversal (Sprint 4.3)
        if ($this->graphEnhancer && config('neo4j.sync.enabled', false)) {
            $graphStartTime = microtime(true);
            $graphTraceId = $this->traceService->startTrace(
                operation: 'Graph-Enhanced Research',
                input: ['laws_count' => count($laws), 'decisions_count' => count($decisions)],
                parentTraceId: $this->rootTraceId,
                agentType: 'research_specialist',
                stepType: 'graph_enhancement'
            );

            $enhanced = $this->graphEnhancer->enhanceResearchResults($laws, $decisions);
            $context->write('graph_enhanced_results', $enhanced);

            $graphDurationMs = (int) ((microtime(true) - $graphStartTime) * 1000);
            $this->traceService->endTrace(
                traceId: $graphTraceId,
                output: [
                    'vector_decisions' => count($enhanced['vector_decisions']),
                    'graph_decisions_citing_laws' => count($enhanced['graph_decisions_citing_laws'] ?? []),
                    'graph_related_decisions' => count($enhanced['graph_related_decisions'] ?? []),
                    'total_decisions' => $enhanced['total_decisions'],
                    'enhancement_percentage' => $enhanced['metrics']['graph_enhancement_percentage'] ?? 0,
                ],
                reasoning: 'Enhanced vector search results using graph traversal to find decisions citing discovered laws and related decisions via citation chains',
                confidence: 0.92,
                tokensUsed: null,
                durationMs: $graphDurationMs
            );

            // Merge graph results into decisions array
            $graphDecisions = array_merge(
                $enhanced['graph_decisions_citing_laws'] ?? [],
                $enhanced['graph_related_decisions'] ?? []
            );

            // Convert graph decision format to match vector search format
            foreach ($graphDecisions as $graphDecision) {
                $decisionId = $graphDecision['decision_id'] ?? $graphDecision['related_decision_id'] ?? null;
                if ($decisionId && ! in_array($decisionId, array_column($decisions, 'id'))) {
                    $decisions[] = [
                        'id' => $decisionId,
                        'case_number' => $graphDecision['case_number'] ?? 'N/A',
                        'score' => 0.75, // Graph-discovered decisions get default score
                        'source' => 'graph',
                    ];
                }
            }
        }

        // 4. Prioritize and filter results
        $prioritized = $this->prioritizeResults($laws, $decisions, $problemStatement);

        // 5. Send findings to other agents
        $context->sendMessage('precedent_analyst', [
            'type' => 'research_complete',
            'laws' => $prioritized['laws'],
            'decisions' => $prioritized['decisions'],
            'summary' => $prioritized['summary'],
        ]);

        $context->logEvent('research_completed', [
            'laws_found' => count($prioritized['laws']),
            'decisions_found' => count($prioritized['decisions']),
        ]);

        // End root trace
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $this->traceService->endTrace(
            traceId: $this->rootTraceId,
            output: [
                'laws_found' => count($laws),
                'decisions_found' => count($decisions),
                'prioritized' => [
                    'laws_count' => count($prioritized['laws']),
                    'decisions_count' => count($prioritized['decisions']),
                ],
            ],
            reasoning: 'Completed legal research by planning strategy, searching laws and decisions, and prioritizing results by relevance and authority',
            confidence: $this->calculateOverallConfidence($laws, $decisions),
            tokensUsed: null,
            durationMs: $durationMs
        );

        return [
            'success' => true,
            'trace_id' => $this->rootTraceId,
            'research_plan' => $researchPlan,
            'laws_found' => count($laws),
            'decisions_found' => count($decisions),
            'prioritized_results' => $prioritized,
            'summary' => $this->generateSummary($prioritized),
        ];
    }

    /**
     * Plan research strategy using LLM
     */
    protected function planResearch(string $problem, array $task): array
    {
        $startTime = microtime(true);

        // Start planning trace
        $planningTraceId = $this->traceService->startTrace(
            operation: 'Research Planning',
            input: ['problem' => $problem, 'task' => $task],
            parentTraceId: $this->rootTraceId,
            agentType: 'research_specialist',
            stepType: 'planning'
        );

        $prompt = <<<PROMPT
You are a Croatian legal research expert. Analyze this legal problem and create a research plan.

Problem: {$problem}
Task: {$task['description']}

Create a research plan with:
1. Key legal concepts to research
2. Specific law queries (Croatian laws to search for)
3. Specific decision queries (court precedents to find)
4. Jurisdiction focus (if applicable)

Respond in JSON format:
{
  "key_concepts": ["concept1", "concept2", ...],
  "law_queries": ["query1", "query2", ...],
  "decision_queries": ["query1", "query2", ...],
  "jurisdiction": "jurisdiction if applicable",
  "reasoning": "brief explanation"
}
PROMPT;

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => 'You are a Croatian legal research expert.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $plan = json_decode($content, true);

            $tokensUsed = $response['usage']['total_tokens'] ?? null;

            // End planning trace
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->traceService->endTrace(
                traceId: $planningTraceId,
                output: $plan,
                reasoning: 'Analyzed legal problem and created targeted research plan with law queries, decision queries, and key concepts to investigate',
                confidence: 0.90,
                tokensUsed: $tokensUsed,
                durationMs: $durationMs
            );

            return $plan;

        } catch (\Exception $e) {
            Log::error('ResearchSpecialistAgent - Planning failed', [
                'error' => $e->getMessage(),
            ]);

            $fallbackPlan = [
                'key_concepts' => ['general legal research'],
                'law_queries' => [$problem],
                'decision_queries' => [$problem],
                'reasoning' => 'Fallback plan due to LLM error',
            ];

            // End planning trace with error
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->traceService->endTrace(
                traceId: $planningTraceId,
                output: $fallbackPlan,
                reasoning: 'Planning failed - using fallback strategy with general queries',
                confidence: 0.50,
                tokensUsed: null,
                durationMs: $durationMs
            );

            return $fallbackPlan;
        }
    }

    /**
     * Search for relevant laws
     */
    protected function searchLaws(array $queries): array
    {
        $groupStartTime = microtime(true);

        // Create law search group trace for deeper nesting
        $lawSearchGroupTraceId = $this->traceService->startTrace(
            operation: 'Law Search Execution',
            input: ['queries' => $queries, 'query_count' => count($queries)],
            parentTraceId: $this->rootTraceId,
            agentType: 'research_specialist',
            stepType: 'law_search_group'
        );

        $allLaws = [];

        foreach ($queries as $query) {
            $startTime = microtime(true);

            // Start law search trace for each query
            $lawSearchTraceId = $this->traceService->startTrace(
                operation: "Law Search: {$query}",
                input: ['query' => $query],
                parentTraceId: $lawSearchGroupTraceId,
                agentType: 'research_specialist',
                stepType: 'law_search'
            );

            try {
                $results = $this->lawSearch->hybridSearch($query, ['limit' => 10]);

                $success = $results['success'] ?? false;
                $foundCount = isset($results['data']) ? count($results['data']) : 0;

                if ($success && isset($results['data'])) {
                    $allLaws = array_merge($allLaws, $results['data']);
                }

                // End law search trace
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $this->traceService->endTrace(
                    traceId: $lawSearchTraceId,
                    output: ['found_count' => $foundCount, 'success' => $success],
                    reasoning: "Searched Croatian law database for '{$query}' using hybrid vector + keyword search to find relevant legal provisions",
                    confidence: $success ? 0.85 : 0.30,
                    tokensUsed: null,
                    durationMs: $durationMs
                );
            } catch (\Exception $e) {
                Log::warning('ResearchSpecialistAgent - Law search failed', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);

                // End law search trace with error
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $this->traceService->endTrace(
                    traceId: $lawSearchTraceId,
                    output: ['error' => $e->getMessage()],
                    reasoning: "Law search failed due to error: {$e->getMessage()}",
                    confidence: 0.0,
                    tokensUsed: null,
                    durationMs: $durationMs
                );
            }
        }

        // Remove duplicates by ID
        $uniqueLaws = [];
        $seen = [];
        foreach ($allLaws as $law) {
            $id = $law['id'] ?? $law['doc_id'] ?? null;
            if ($id && ! isset($seen[$id])) {
                $uniqueLaws[] = $law;
                $seen[$id] = true;
            }
        }

        $topLaws = array_slice($uniqueLaws, 0, 15); // Top 15

        // End law search group trace
        $groupDurationMs = (int) ((microtime(true) - $groupStartTime) * 1000);
        $this->traceService->endTrace(
            traceId: $lawSearchGroupTraceId,
            output: ['unique_laws_found' => count($uniqueLaws), 'top_laws_count' => count($topLaws)],
            reasoning: 'Executed '.count($queries).' law searches and deduplicated results to find most relevant Croatian legal provisions',
            confidence: count($topLaws) > 0 ? 0.85 : 0.30,
            tokensUsed: null,
            durationMs: $groupDurationMs
        );

        return $topLaws;
    }

    /**
     * Search for relevant court decisions
     */
    protected function searchDecisions(array $queries): array
    {
        $groupStartTime = microtime(true);

        // Create decision search group trace for deeper nesting
        $decisionSearchGroupTraceId = $this->traceService->startTrace(
            operation: 'Decision Search Execution',
            input: ['queries' => $queries, 'query_count' => count($queries)],
            parentTraceId: $this->rootTraceId,
            agentType: 'research_specialist',
            stepType: 'decision_search_group'
        );

        $allDecisions = [];

        foreach ($queries as $query) {
            $startTime = microtime(true);

            // Start decision search trace for each query
            $decisionSearchTraceId = $this->traceService->startTrace(
                operation: "Decision Search: {$query}",
                input: ['query' => $query],
                parentTraceId: $decisionSearchGroupTraceId,
                agentType: 'research_specialist',
                stepType: 'decision_search'
            );

            try {
                $results = $this->decisionSearch->hybridSearch($query, ['limit' => 10]);

                $success = $results['success'] ?? false;
                $foundCount = isset($results['data']) ? count($results['data']) : 0;

                if ($success && isset($results['data'])) {
                    $allDecisions = array_merge($allDecisions, $results['data']);
                }

                // End decision search trace
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $this->traceService->endTrace(
                    traceId: $decisionSearchTraceId,
                    output: ['found_count' => $foundCount, 'success' => $success],
                    reasoning: "Searched court decision database for '{$query}' to find relevant precedents from Croatian courts",
                    confidence: $success ? 0.85 : 0.30,
                    tokensUsed: null,
                    durationMs: $durationMs
                );
            } catch (\Exception $e) {
                Log::warning('ResearchSpecialistAgent - Decision search failed', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);

                // End decision search trace with error
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $this->traceService->endTrace(
                    traceId: $decisionSearchTraceId,
                    output: ['error' => $e->getMessage()],
                    reasoning: "Decision search failed due to error: {$e->getMessage()}",
                    confidence: 0.0,
                    tokensUsed: null,
                    durationMs: $durationMs
                );
            }
        }

        // Remove duplicates
        $uniqueDecisions = [];
        $seen = [];
        foreach ($allDecisions as $decision) {
            $id = $decision['id'] ?? null;
            if ($id && ! isset($seen[$id])) {
                $uniqueDecisions[] = $decision;
                $seen[$id] = true;
            }
        }

        $topDecisions = array_slice($uniqueDecisions, 0, 20); // Top 20

        // End decision search group trace
        $groupDurationMs = (int) ((microtime(true) - $groupStartTime) * 1000);
        $this->traceService->endTrace(
            traceId: $decisionSearchGroupTraceId,
            output: ['unique_decisions_found' => count($uniqueDecisions), 'top_decisions_count' => count($topDecisions)],
            reasoning: 'Executed '.count($queries).' decision searches across Croatian court databases and deduplicated results to find most relevant precedents',
            confidence: count($topDecisions) > 0 ? 0.85 : 0.30,
            tokensUsed: null,
            durationMs: $groupDurationMs
        );

        return $topDecisions;
    }

    /**
     * Prioritize and filter results by relevance
     */
    protected function prioritizeResults(array $laws, array $decisions, string $problem): array
    {
        $startTime = microtime(true);

        // Start prioritization trace
        $prioritizationTraceId = $this->traceService->startTrace(
            operation: 'Result Prioritization',
            input: [
                'laws_count' => count($laws),
                'decisions_count' => count($decisions),
                'problem' => $problem,
            ],
            parentTraceId: $this->rootTraceId,
            agentType: 'research_specialist',
            stepType: 'prioritization'
        );

        // Sort laws by relevance score if available
        usort($laws, function ($a, $b) {
            return ($b['score'] ?? $b['similarity'] ?? 0) <=> ($a['score'] ?? $a['similarity'] ?? 0);
        });

        // Sort decisions by relevance and court authority
        usort($decisions, function ($a, $b) {
            $scoreA = ($a['score'] ?? $a['similarity'] ?? 0) * $this->getCourtAuthorityMultiplier($a['court'] ?? '');
            $scoreB = ($b['score'] ?? $b['similarity'] ?? 0) * $this->getCourtAuthorityMultiplier($b['court'] ?? '');

            return $scoreB <=> $scoreA;
        });

        $topLaws = array_slice($laws, 0, 10);
        $topDecisions = array_slice($decisions, 0, 15);

        $result = [
            'laws' => $topLaws,
            'decisions' => $topDecisions,
            'summary' => $this->createResultsSummary($topLaws, $topDecisions),
        ];

        // End prioritization trace
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $this->traceService->endTrace(
            traceId: $prioritizationTraceId,
            output: [
                'top_laws_count' => count($topLaws),
                'top_decisions_count' => count($topDecisions),
            ],
            reasoning: 'Sorted and filtered results by relevance score, applying court authority multipliers (Supreme Court 1.5x, High Court 1.3x, County Court 1.2x) to prioritize higher court precedents',
            confidence: 0.88,
            tokensUsed: null,
            durationMs: $durationMs
        );

        return $result;
    }

    /**
     * Get court authority multiplier for prioritization
     */
    protected function getCourtAuthorityMultiplier(string $court): float
    {
        $court = mb_strtolower($court);

        if (str_contains($court, 'vrhovni sud')) {
            return 1.5; // Supreme Court
        }
        if (str_contains($court, 'visoki')) {
            return 1.3; // High Court
        }
        if (str_contains($court, 'županijski')) {
            return 1.2; // County Court
        }

        return 1.0; // Municipal/other courts
    }

    /**
     * Create summary of research results
     */
    protected function createResultsSummary(array $laws, array $decisions): string
    {
        $lawCount = count($laws);
        $decisionCount = count($decisions);

        $summary = "Research complete: Found {$lawCount} relevant laws and {$decisionCount} court decisions.\n\n";

        if ($lawCount > 0) {
            $summary .= "Key Laws:\n";
            foreach (array_slice($laws, 0, 3) as $i => $law) {
                $title = $law['title'] ?? 'Untitled';
                $number = $law['law_number'] ?? 'N/A';
                $summary .= ($i + 1).". {$title} ({$number})\n";
            }
            $summary .= "\n";
        }

        if ($decisionCount > 0) {
            $summary .= "Key Precedents:\n";
            foreach (array_slice($decisions, 0, 3) as $i => $decision) {
                $court = $decision['court'] ?? 'Unknown Court';
                $caseNum = $decision['case_number'] ?? 'N/A';
                $summary .= ($i + 1).". {$court} - {$caseNum}\n";
            }
        }

        return $summary;
    }

    /**
     * Generate final summary
     */
    protected function generateSummary(array $prioritized): string
    {
        return $prioritized['summary'];
    }

    /**
     * Get agent name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get agent role
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Calculate overall confidence based on research results
     */
    protected function calculateOverallConfidence(array $laws, array $decisions): float
    {
        $lawsFound = count($laws);
        $decisionsFound = count($decisions);

        // Base confidence on whether we found results
        if ($lawsFound === 0 && $decisionsFound === 0) {
            return 0.20; // Low confidence - no results
        }

        if ($lawsFound > 0 && $decisionsFound > 0) {
            return 0.92; // High confidence - found both laws and decisions
        }

        if ($lawsFound > 3 || $decisionsFound > 3) {
            return 0.85; // Good confidence - found multiple of one type
        }

        return 0.70; // Moderate confidence - found some results
    }
}
