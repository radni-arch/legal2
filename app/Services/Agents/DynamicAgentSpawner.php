<?php

namespace App\Services\Agents;

use Illuminate\Support\Str;

/**
 * Dynamic Agent Spawner Service
 *
 * Sprint 3.5: Dynamic Agent Spawning
 *
 * Spawns agents dynamically based on problem complexity with budget
 * tracking, depth limits, and spawn loop prevention.
 */
class DynamicAgentSpawner
{
    /**
     * Maximum spawn depth to prevent infinite recursion
     */
    const MAX_DEPTH = 3;

    /**
     * Cost estimation accuracy buffer (20%)
     */
    const COST_ESTIMATION_BUFFER = 0.20;

    /**
     * Agent type registry - maps problem types to agent types
     */
    protected array $agentTypeRegistry = [
        'legal_research' => 'ResearchAgent',
        'precedent_analysis' => 'PrecedentAgent',
        'case_analysis' => 'CaseAnalysisAgent',
        'statute_analysis' => 'StatuteAnalysisAgent',
        'strategy_planning' => 'StrategyAgent',
        'risk_assessment' => 'RiskAnalysisAgent',
        'document_analysis' => 'DocumentAnalysisAgent',
        'evidence_review' => 'EvidenceReviewAgent',
        'complex_analysis' => 'ComplexAnalysisAgent',
    ];

    /**
     * Base cost estimates per agent type (tokens)
     */
    protected array $baseCostEstimates = [
        'ResearchAgent' => 1500,
        'PrecedentAgent' => 2000,
        'CaseAnalysisAgent' => 2500,
        'StatuteAnalysisAgent' => 1800,
        'StrategyAgent' => 3000,
        'RiskAnalysisAgent' => 2200,
        'DocumentAnalysisAgent' => 1600,
        'EvidenceReviewAgent' => 1900,
        'ComplexAnalysisAgent' => 4000,
    ];

    /**
     * Spawn history tracking
     */
    protected array $spawnHistory = [];

    /**
     * Spawn an agent dynamically
     *
     * @param  string  $problemType  Type of problem to solve
     * @param  array  $context  Context for the problem
     * @param  array  $budget  Available budget (token_budget, cost_budget)
     * @param  array  $options  Additional options (current_depth, spawn_chain)
     * @return array Spawn result with metadata
     */
    public function spawnAgent(
        string $problemType,
        array $context,
        array $budget,
        array $options = []
    ): array {
        $currentDepth = $options['current_depth'] ?? 0;
        $spawnChain = $options['spawn_chain'] ?? [];

        // Get agent type for problem
        $agentType = $this->getAgentTypeForProblem($problemType);

        if (! $agentType) {
            return [
                'spawned' => false,
                'reason' => 'Unknown problem type: '.$problemType,
            ];
        }

        // Check depth limit
        if ($currentDepth >= self::MAX_DEPTH) {
            return [
                'spawned' => false,
                'reason' => 'Maximum spawn depth limit reached',
                'max_depth' => self::MAX_DEPTH,
            ];
        }

        // Check for spawn loop
        if (in_array($agentType, $spawnChain)) {
            return [
                'spawned' => false,
                'reason' => 'Spawn loop detected: Agent already in spawn chain',
                'agent_type' => $agentType,
                'spawn_chain' => $spawnChain,
            ];
        }

        // Estimate cost
        $estimation = $this->estimateCost($problemType, $context);

        // Check budget
        if (! $this->checkBudget($estimation, $budget)) {
            return [
                'spawned' => false,
                'reason' => 'Insufficient budget for agent spawn',
                'estimated_tokens' => $estimation['estimated_tokens'],
                'estimated_cost' => $estimation['estimated_cost'],
                'available_tokens' => $budget['token_budget'] ?? 0,
                'available_cost' => $budget['cost_budget'] ?? 0,
            ];
        }

        // Create spawn record
        $spawnId = (string) Str::uuid();
        $spawnRecord = [
            'spawn_id' => $spawnId,
            'agent_type' => $agentType,
            'problem_type' => $problemType,
            'spawned' => true,
            'spawned_at' => now()->toISOString(),
            'depth' => $currentDepth,
            'parent_chain' => $spawnChain,
            'estimated_cost' => $estimation['estimated_cost'],
            'estimated_tokens' => $estimation['estimated_tokens'],
            'context' => $context,
        ];

        // Track spawn
        $this->spawnHistory[] = $spawnRecord;

        return $spawnRecord;
    }

    /**
     * Estimate cost for spawning an agent
     *
     * @param  string  $problemType  Type of problem
     * @param  array  $context  Problem context
     * @return array Cost estimation
     */
    public function estimateCost(string $problemType, array $context): array
    {
        $agentType = $this->getAgentTypeForProblem($problemType);

        if (! $agentType) {
            return [
                'estimated_tokens' => 0,
                'estimated_cost' => 0.0,
                'confidence' => 0.0,
            ];
        }

        // Get base estimate
        $baseTokens = $this->baseCostEstimates[$agentType] ?? 2000;

        // Adjust based on context complexity
        $complexity = $context['complexity'] ?? 'medium';
        $multiplier = match ($complexity) {
            'low' => 0.6,
            'medium' => 1.0,
            'high' => 1.5,
            'very_high' => 2.0,
            default => 1.0,
        };

        $estimatedTokens = (int) ($baseTokens * $multiplier);

        // Calculate cost (GPT-4o pricing: ~$0.005 per 1000 tokens average)
        $estimatedCost = ($estimatedTokens / 1000) * 0.005;

        // Confidence based on how well we know the problem type
        $confidence = isset($this->baseCostEstimates[$agentType]) ? 0.85 : 0.60;

        return [
            'estimated_tokens' => $estimatedTokens,
            'estimated_cost' => round($estimatedCost, 4),
            'confidence' => $confidence,
        ];
    }

    /**
     * Check if budget is sufficient
     *
     * @param  array  $estimation  Cost estimation
     * @param  array  $budget  Available budget
     * @return bool True if budget is sufficient
     */
    protected function checkBudget(array $estimation, array $budget): bool
    {
        $tokenBudget = $budget['token_budget'] ?? 0;
        $costBudget = $budget['cost_budget'] ?? 0;

        // Add buffer for estimation accuracy (20%)
        $requiredTokens = $estimation['estimated_tokens'] * (1 + self::COST_ESTIMATION_BUFFER);
        $requiredCost = $estimation['estimated_cost'] * (1 + self::COST_ESTIMATION_BUFFER);

        return $tokenBudget >= $requiredTokens && $costBudget >= $requiredCost;
    }

    /**
     * Get agent type for problem type
     *
     * @param  string  $problemType  Problem type
     * @return string|null Agent type or null if not found
     */
    public function getAgentTypeForProblem(string $problemType): ?string
    {
        return $this->agentTypeRegistry[$problemType] ?? null;
    }

    /**
     * Get the agent type registry
     *
     * @return array Agent type registry
     */
    public function getAgentTypeRegistry(): array
    {
        return $this->agentTypeRegistry;
    }

    /**
     * Get spawn history
     *
     * @return array Spawn history
     */
    public function getSpawnHistory(): array
    {
        return $this->spawnHistory;
    }

    /**
     * Calculate remaining budget
     *
     * @param  array  $totalBudget  Total budget
     * @param  array  $usedBudget  Used budget
     * @return array Remaining budget
     */
    public function calculateRemainingBudget(array $totalBudget, array $usedBudget): array
    {
        $tokenBudget = $totalBudget['token_budget'] ?? 0;
        $costBudget = $totalBudget['cost_budget'] ?? 0;

        $tokensUsed = $usedBudget['tokens_used'] ?? 0;
        $costSpent = $usedBudget['cost_spent'] ?? 0;

        return [
            'token_budget' => max(0, $tokenBudget - $tokensUsed),
            'cost_budget' => max(0, $costBudget - $costSpent),
        ];
    }

    /**
     * Validate spawn request
     *
     * @param  array  $request  Spawn request
     * @return bool True if valid
     */
    public function validateSpawnRequest(array $request): bool
    {
        // Required fields
        $requiredFields = ['problem_type', 'context', 'budget'];

        foreach ($requiredFields as $field) {
            if (! isset($request[$field])) {
                return false;
            }
        }

        // Validate budget structure
        $budget = $request['budget'];
        if (! isset($budget['token_budget']) || ! isset($budget['cost_budget'])) {
            return false;
        }

        return true;
    }

    /**
     * Get spawn recommendation
     *
     * @param  string  $problemType  Problem type
     * @param  array  $context  Context
     * @param  array  $budget  Budget
     * @return array Recommendation
     */
    public function getSpawnRecommendation(string $problemType, array $context, array $budget): array
    {
        $estimation = $this->estimateCost($problemType, $context);
        $canAfford = $this->checkBudget($estimation, $budget);

        $agentType = $this->getAgentTypeForProblem($problemType);

        // Find alternative agents
        $alternatives = [];
        if (! $canAfford) {
            // Suggest cheaper alternatives
            foreach ($this->agentTypeRegistry as $type => $agent) {
                if ($type !== $problemType) {
                    $altEstimation = $this->estimateCost($type, $context);
                    if ($this->checkBudget($altEstimation, $budget)) {
                        $alternatives[] = [
                            'problem_type' => $type,
                            'agent_type' => $agent,
                            'estimated_cost' => $altEstimation['estimated_cost'],
                        ];
                    }
                }
            }
        }

        $reasoning = $canAfford
            ? "Budget is sufficient for spawning {$agentType}"
            : "Budget is insufficient. Estimated cost: \${$estimation['estimated_cost']}, Available: \${$budget['cost_budget']}";

        return [
            'should_spawn' => $canAfford,
            'confidence' => $estimation['confidence'],
            'reasoning' => $reasoning,
            'alternative_agents' => $alternatives,
            'estimated_tokens' => $estimation['estimated_tokens'],
            'estimated_cost' => $estimation['estimated_cost'],
        ];
    }

    /**
     * Reset spawn history (useful for testing)
     */
    public function resetSpawnHistory(): void
    {
        $this->spawnHistory = [];
    }
}
