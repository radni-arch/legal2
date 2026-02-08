<?php

namespace App\Services;

use App\Services\Agents\IterationControllerService;
use App\Services\Research\InsightExtractorService;
use App\Services\Research\ResearchCheckpointService;
use App\Services\Research\ResearchEvaluatorService;
use App\Services\Research\ResearchPlannerService;
use App\Services\Research\SearchExecutorService;
use App\Tools\Research\CaseSearchTool;
use App\Tools\Research\CaseVectorSearchTool;
use App\Tools\Research\DecisionHybridSearchTool;
use App\Tools\Research\DecisionKeywordSearchTool;
use App\Tools\Research\DecisionLookupTool;
use App\Tools\Research\DecisionVectorSearchTool;
use App\Tools\Research\GraphQueryTool;
use App\Tools\Research\LawHybridSearchTool;
use App\Tools\Research\LawKeywordSearchTool;
use App\Tools\Research\LawLookupTool;
use App\Tools\Research\LawVectorSearchTool;
use App\Tools\Research\NoteSaveTool;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Log;
use Vizra\VizraADK\Agents\BaseLlmAgent;

/**
 * Research Orchestrator
 *
 * Full-featured autonomous research agent built on Vizra SDK.
 * Provides LLM-powered planning, evaluation, and insight extraction for Croatian legal research.
 *
 * Features:
 * - Extends BaseLlmAgent for Vizra SDK integration
 * - LLM-based research planning (ResearchPlannerService)
 * - 12 specialized search tools (law, decision, case, graph, note)
 * - LLM-based quality evaluation (ResearchEvaluatorService)
 * - Insight extraction with citations (InsightExtractorService)
 * - Checkpoint/resume capability (ResearchCheckpointService)
 * - Event dispatching for real-time updates
 *
 * Architecture:
 * - Tools: 12 Vizra SDK tools for search operations
 * - Services: Planning, Evaluation, Insight Extraction, Checkpointing
 * - Events: ResearchIterationCompleted, NewInsightDiscovered
 */
class ResearchOrchestrator extends BaseLlmAgent
{
    protected string $name = 'research_orchestrator';

    protected string $description = 'Autonomous legal research agent with LLM-powered planning and evaluation';

    protected ?string $provider = 'openai';

    protected string $model = 'gpt-4o-mini';

    protected int $maxSteps = 20;

    protected bool $showInChatUi = false;

    protected int $checkpointFrequency = 2;

    /**
     * 12 Research Tools for Vizra SDK
     *
     * Law Tools (4):
     * - LawVectorSearchTool: Semantic search across laws
     * - LawKeywordSearchTool: Keyword-based law search
     * - LawHybridSearchTool: Combined vector + keyword search
     * - LawLookupTool: Direct law lookup by ID
     *
     * Decision Tools (4):
     * - DecisionVectorSearchTool: Semantic search across court decisions
     * - DecisionKeywordSearchTool: Keyword-based decision search
     * - DecisionHybridSearchTool: Combined vector + keyword search
     * - DecisionLookupTool: Direct decision lookup by ID
     *
     * Case Tools (2):
     * - CaseVectorSearchTool: Semantic search across case documents
     * - CaseSearchTool: General case search
     *
     * Other Tools (2):
     * - GraphQueryTool: Neo4j graph database queries
     * - NoteSaveTool: Save research notes
     */
    protected array $tools = [
        LawVectorSearchTool::class,
        LawKeywordSearchTool::class,
        LawHybridSearchTool::class,
        LawLookupTool::class,
        DecisionVectorSearchTool::class,
        DecisionKeywordSearchTool::class,
        DecisionHybridSearchTool::class,
        DecisionLookupTool::class,
        CaseVectorSearchTool::class,
        CaseSearchTool::class,
        GraphQueryTool::class,
        NoteSaveTool::class,
    ];

    /**
     * Default research limits
     */
    protected array $defaultLimits = [
        'max_iterations' => 5,
        'quality_threshold' => 85,
        'token_budget' => null, // No limit by default
        'time_budget' => null,  // No limit by default
    ];

    public function __construct(
        protected SearchExecutorService $searchExecutor,
        protected IterationControllerService $iterationController,
        protected ResearchPlannerService $planner,
        protected ResearchEvaluatorService $evaluator,
        protected InsightExtractorService $insightExtractor,
        protected ResearchCheckpointService $checkpoint
    ) {
        parent::__construct();
        $this->instructions = $this->buildInstructions();
    }

    /**
     * Execute autonomous research
     *
     * This is the main entry point for research operations, maintaining backward compatibility
     * with the previous ResearchOrchestrator API while using the new Vizra SDK architecture.
     *
     * @param  string  $query  Research question
     * @param  array  $options  Options:
     *                          - limits: Override default limits
     *                          - search_corpora: Which corpora to search (default: all)
     * @return array Research results with:
     *               - success: Whether research completed successfully
     *               - answer: Synthesized answer
     *               - quality_score: Quality score (0-100)
     *               - iterations: Number of iterations performed
     *               - stopped_reason: Why iteration stopped
     *               - total_tokens: Total tokens used
     *               - total_time_s: Total time in seconds
     *               - search_results: Aggregated search results
     *               - insights: Extracted insights
     */
    public function research(string $query, array $options = []): array
    {
        $startTime = microtime(true);
        $limits = array_merge($this->defaultLimits, $options['limits'] ?? []);

        $this->iterationController->reset();

        $iteration = 0;
        $qualityScore = 0;
        $allSearchResults = [];
        $allInsights = [];

        Log::info('ResearchOrchestrator: Starting research', [
            'query' => $query,
            'limits' => $limits,
        ]);

        while ($this->iterationController->shouldContinue($iteration, $qualityScore, $limits)) {
            $iteration++;

            Log::info('ResearchOrchestrator: Starting iteration', ['iteration' => $iteration]);

            // Step 1: Plan next actions via LLM
            $plan = $this->planner->planNextIteration(
                $this->createTempRun($query, $iteration, $limits),
                $allSearchResults
            );

            // Check if LLM recommends stopping
            if ($plan['should_stop'] ?? false) {
                Log::info('ResearchOrchestrator: LLM recommends stopping', [
                    'iteration' => $iteration,
                    'reason' => $plan['reasoning'],
                ]);
                break;
            }

            // Step 2: Execute planned actions
            $searchResults = $this->searchExecutor->execute($plan['actions'] ?? []);

            $allSearchResults[] = [
                'iteration' => $iteration,
                'plan' => $plan,
                'results' => $searchResults,
            ];

            // Step 3: Evaluate iteration via LLM
            $evaluation = $this->evaluator->evaluateIteration(
                $this->createTempRun($query, $iteration, $limits),
                $searchResults
            );

            $qualityScore = $evaluation['quality_score'];

            // Step 4: Extract insights
            if (!empty($evaluation['insights'])) {
                foreach ($evaluation['insights'] as $insight) {
                    $allInsights[] = $insight;
                    // Note: NewInsightDiscovered event dispatched by ResearchService
                    // when called with a real AgentRun (via executeRun)
                }
            }

            // Step 5: Track resource usage
            $this->iterationController->trackUsage([
                'tokens' => $evaluation['tokens_used'] ?? 0,
                'time' => microtime(true) - $startTime,
            ]);

            // Note: ResearchIterationCompleted event dispatched by ResearchService
            // when called with a real AgentRun (via executeRun). The standalone
            // research() method doesn't have a persisted AgentRun for broadcasting.

            // Step 6: Checkpoint if needed
            if ($iteration % $this->checkpointFrequency === 0) {
                $this->checkpoint->saveCheckpoint(
                    $this->createTempRun($query, $iteration, $limits),
                    [
                        'iterations' => $allSearchResults,
                        'insights' => $allInsights,
                        'quality_score' => $qualityScore,
                    ]
                );
            }

            Log::info('ResearchOrchestrator: Iteration complete', [
                'iteration' => $iteration,
                'quality_score' => $qualityScore,
            ]);
        }

        $totalTime = microtime(true) - $startTime;

        // Synthesize final answer via LLM
        $answer = $this->synthesizeAnswer($query, $allSearchResults, $allInsights);

        $qualityThreshold = $limits['quality_threshold'] ?? 85;
        $success = !empty($allSearchResults) && $qualityScore >= $qualityThreshold;

        Log::info('ResearchOrchestrator: Research complete', [
            'success' => $success,
            'iterations' => $iteration,
            'quality_score' => $qualityScore,
            'insights_count' => count($allInsights),
        ]);

        return [
            'success' => $success,
            'answer' => $answer,
            'quality_score' => $qualityScore,
            'iterations' => $iteration,
            'stopped_reason' => $this->iterationController->getStopReason(),
            'total_tokens' => $this->iterationController->getTotalTokens(),
            'total_time_s' => round($totalTime, 2),
            'search_results' => $allSearchResults,
            'insights' => $allInsights,
        ];
    }

    /**
     * Create a temporary AgentRun object for service methods
     *
     * Services like ResearchPlannerService and ResearchEvaluatorService expect
     * an AgentRun model. This creates a temporary one for those services.
     *
     * @param  string  $query  Research query
     * @param  int  $iteration  Current iteration
     * @param  array  $limits  Research limits
     * @return AgentRun Temporary run object
     */
    protected function createTempRun(string $query, int $iteration, array $limits): AgentRun
    {
        $run = new AgentRun();
        $run->objective = $query;
        $run->current_iteration = $iteration;
        $run->max_iterations = $limits['max_iterations'] ?? 5;
        $run->threshold = $limits['quality_threshold'] ?? 85;
        $run->tokens_used = 0;
        $run->cost_spent = 0;

        return $run;
    }

    /**
     * Synthesize final answer from research results
     *
     * Combines all search results and extracted insights into a coherent answer.
     * Future enhancement: Use LLM for sophisticated answer synthesis.
     *
     * @param  string  $query  Original research query
     * @param  array  $results  All search results from all iterations
     * @param  array  $insights  Extracted insights
     * @return string Synthesized answer
     */
    protected function synthesizeAnswer(string $query, array $results, array $insights): string
    {
        // Format insights
        $insightsSummary = '';
        foreach ($insights as $insight) {
            $title = $insight['title'] ?? 'Untitled';
            $summary = $insight['summary'] ?? 'No summary';
            $legalBasis = $insight['legal_basis'] ?? '';

            $insightsSummary .= "- **{$title}**: {$summary}";
            if (!empty($legalBasis)) {
                $insightsSummary .= " ({$legalBasis})";
            }
            $insightsSummary .= "\n";
        }

        // Count total results
        $totalResults = array_reduce($results, function ($carry, $iteration) {
            return $carry + count($iteration['results'] ?? []);
        }, 0);

        return sprintf(
            "Research on '%s' completed.\n\n".
            "**Summary:** Found %d relevant sources across %d iterations.\n\n".
            "**Key Insights:**\n%s\n".
            "Research synthesized from Croatian legal statutes, court decisions, and case law.",
            $query,
            $totalResults,
            count($results),
            $insightsSummary ?: "- No specific insights extracted"
        );
    }

    /**
     * Build agent instructions
     *
     * These instructions guide the agent's behavior when using BaseLlmAgent's execute() method.
     * For the research() method, these are informational only.
     *
     * @return string Agent instructions
     */
    protected function buildInstructions(): string
    {
        return <<<INSTRUCTIONS
You are an autonomous legal research agent specializing in Croatian law.

Your capabilities:
- Search Croatian laws, court decisions, and case documents
- Extract legal insights with proper citations
- Evaluate research quality and identify gaps
- Plan strategic research iterations

When planning research:
1. Analyze the objective and previous findings
2. Identify gaps in knowledge
3. Select appropriate search tools
4. Provide clear rationale for each action

Always cite legal sources properly (e.g., "ZKP čl. 240, st. 2").
INSTRUCTIONS;
    }

    /**
     * Get default research limits
     *
     * @return array Default limits
     */
    public function getDefaultLimits(): array
    {
        return $this->defaultLimits;
    }

    /**
     * Set default research limits
     *
     * @param  array  $limits  New default limits
     */
    public function setDefaultLimits(array $limits): void
    {
        $this->defaultLimits = array_merge($this->defaultLimits, $limits);
    }
}
