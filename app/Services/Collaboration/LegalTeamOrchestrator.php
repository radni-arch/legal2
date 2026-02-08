<?php

namespace App\Services\Collaboration;

use App\Agents\Specialists\PrecedentAnalystAgent;
use App\Agents\Specialists\ResearchSpecialistAgent;
use App\Agents\Specialists\RiskAnalystAgent;
use App\Agents\Specialists\StrategySpecialistAgent;
use App\Models\AgentCollaboration;
use App\Models\AgentExecution;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * Legal Team Orchestrator
 *
 * Coordinates multiple specialist agents working together on legal problems.
 * Mimics how a real legal team collaborates: research → analysis → strategy → risk assessment
 */
class LegalTeamOrchestrator
{
    protected array $specialists = [];

    protected SharedAgentContext $context;

    public function __construct(
        protected ResearchSpecialistAgent $researchAgent,
        protected PrecedentAnalystAgent $precedentAgent,
        protected StrategySpecialistAgent $strategyAgent,
        protected RiskAnalystAgent $riskAgent,
        protected OpenAIService $openai
    ) {
        $this->specialists = [
            'research_specialist' => $this->researchAgent,
            'precedent_analyst' => $this->precedentAgent,
            'strategy_specialist' => $this->strategyAgent,
            'risk_analyst' => $this->riskAgent,
        ];
    }

    /**
     * Solve a legal problem using the multi-agent team
     *
     * @param  string  $problemStatement  The legal problem to solve
     * @param  array  $options  Options:
     *                          - problem_type: string (optional)
     *                          - context: array (optional additional context)
     *                          - agents: array (optional list of agents to use, defaults to all)
     *                          - execution_mode: 'sequential'|'parallel' (default: sequential)
     */
    public function solveProblem(string $problemStatement, array $options = []): array
    {
        Log::info('LegalTeamOrchestrator - Starting collaboration', [
            'problem' => substr($problemStatement, 0, 100).'...',
            'options' => $options,
        ]);

        // 1. Create collaboration record
        $collaboration = $this->createCollaboration($problemStatement, $options);
        $this->context = new SharedAgentContext($collaboration);

        try {
            // 2. Plan execution
            $executionPlan = $this->planExecution($problemStatement, $options);
            $collaboration->update([
                'execution_plan' => $executionPlan,
                'total_steps' => count($executionPlan['steps']),
            ]);

            $this->context->write('execution_plan', $executionPlan);
            $this->context->logEvent('execution_started', ['plan' => $executionPlan]);

            // 3. Execute agents in sequence
            $agentOutputs = $this->executeAgents($executionPlan);

            // 4. Synthesize final result
            $finalResult = $this->synthesizeResults($agentOutputs, $problemStatement);

            // 5. Mark collaboration as completed
            $collaboration->markCompleted($finalResult, $finalResult['synthesis']);

            Log::info('LegalTeamOrchestrator - Collaboration completed', [
                'collaboration_id' => $collaboration->id,
                'agents_executed' => count($agentOutputs),
                'duration_seconds' => $collaboration->duration_seconds,
            ]);

            return [
                'success' => true,
                'collaboration_id' => $collaboration->id,
                'session_id' => $collaboration->session_id,
                'problem_statement' => $problemStatement,
                'execution_plan' => $executionPlan,
                'agent_outputs' => $agentOutputs,
                'final_result' => $finalResult,
                'synthesis' => $finalResult['synthesis'],
                'metadata' => [
                    'tokens_used' => $collaboration->tokens_used,
                    'cost_spent' => $collaboration->cost_spent,
                    'duration_seconds' => $collaboration->duration_seconds,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('LegalTeamOrchestrator - Collaboration failed', [
                'collaboration_id' => $collaboration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $collaboration->markFailed($e->getMessage());

            return [
                'success' => false,
                'collaboration_id' => $collaboration->id,
                'error' => $e->getMessage(),
                'message' => 'Collaboration failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Create collaboration record
     */
    protected function createCollaboration(string $problemStatement, array $options): AgentCollaboration
    {
        return AgentCollaboration::create([
            'orchestrator' => 'LegalTeamOrchestrator',
            'problem_type' => $options['problem_type'] ?? $this->inferProblemType($problemStatement),
            'problem_statement' => $problemStatement,
            'context' => $options['context'] ?? [],
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Infer problem type from statement
     */
    protected function inferProblemType(string $statement): string
    {
        $statement = mb_strtolower($statement);

        $types = [
            'employment' => ['otkaz', 'posao', 'zaposlenje', 'ugovor o radu', 'mobbing'],
            'contract' => ['ugovor', 'naknada', 'obveza', 'prestacija'],
            'property' => ['vlasništvo', 'nekretnina', 'posjed'],
            'family' => ['razvod', 'skrbništvo', 'alimentacija', 'brak'],
            'criminal' => ['kazneno', 'prekršaj', 'kazna'],
        ];

        foreach ($types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($statement, $keyword)) {
                    return $type;
                }
            }
        }

        return 'general';
    }

    /**
     * Plan agent execution
     */
    protected function planExecution(string $problemStatement, array $options): array
    {
        // Default execution plan: research → precedent → strategy → risk
        $defaultPlan = [
            [
                'agent' => 'research_specialist',
                'task' => [
                    'description' => 'Find relevant laws and court decisions',
                    'dependencies' => [],
                ],
            ],
            [
                'agent' => 'precedent_analyst',
                'task' => [
                    'description' => 'Analyze applicability of precedents',
                    'dependencies' => ['research_specialist'],
                ],
            ],
            [
                'agent' => 'strategy_specialist',
                'task' => [
                    'description' => 'Develop legal strategy and arguments',
                    'dependencies' => ['precedent_analyst'],
                ],
            ],
            [
                'agent' => 'risk_analyst',
                'task' => [
                    'description' => 'Identify risks and weaknesses',
                    'dependencies' => ['strategy_specialist'],
                ],
            ],
        ];

        // Allow custom agent selection
        if (isset($options['agents']) && is_array($options['agents'])) {
            $defaultPlan = array_filter($defaultPlan, function ($step) use ($options) {
                return in_array($step['agent'], $options['agents']);
            });
        }

        return [
            'mode' => $options['execution_mode'] ?? 'sequential',
            'steps' => array_values($defaultPlan),
        ];
    }

    /**
     * Execute agents according to plan
     */
    protected function executeAgents(array $executionPlan): array
    {
        $outputs = [];

        foreach ($executionPlan['steps'] as $index => $step) {
            $agentName = $step['agent'];
            $task = $step['task'];

            Log::info('LegalTeamOrchestrator - Executing agent', [
                'agent' => $agentName,
                'step' => $index + 1,
                'total_steps' => count($executionPlan['steps']),
            ]);

            $output = $this->executeAgent($agentName, $task, $index);
            $outputs[$agentName] = $output;

            if (! ($output['success'] ?? false)) {
                Log::warning('LegalTeamOrchestrator - Agent execution failed', [
                    'agent' => $agentName,
                    'error' => $output['error'] ?? 'Unknown error',
                ]);

                // Continue with other agents even if one fails
            }
        }

        return $outputs;
    }

    /**
     * Execute a single agent
     */
    protected function executeAgent(string $agentName, array $task, int $order): array
    {
        $agent = $this->specialists[$agentName] ?? null;

        if (! $agent) {
            return [
                'success' => false,
                'error' => "Agent '{$agentName}' not found",
            ];
        }

        // Create execution record
        $execution = AgentExecution::create([
            'collaboration_id' => $this->context->getCollaboration()->id,
            'agent_name' => $agentName,
            'agent_role' => method_exists($agent, 'getRole') ? $agent->getRole() : $agentName,
            'execution_order' => $order,
            'status' => 'pending',
            'task_description' => $task['description'] ?? 'No description',
            'input_context' => [
                'problem_statement' => $this->context->getProblemStatement(),
                'task' => $task,
            ],
        ]);

        $this->context->setCurrentExecution($execution);
        $execution->markRunning();

        try {
            // Execute the agent
            $output = $agent->execute($this->context, $task);

            // Mark as completed
            $execution->markCompleted($output);

            return $output;

        } catch (\Exception $e) {
            $execution->markFailed($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Synthesize final result from all agent outputs
     */
    protected function synthesizeResults(array $agentOutputs, string $problemStatement): array
    {
        $synthesis = $this->generateFinalSynthesis($agentOutputs, $problemStatement);

        return [
            'problem_statement' => $problemStatement,
            'agent_contributions' => $this->summarizeContributions($agentOutputs),
            'synthesis' => $synthesis,
            'research_findings' => $agentOutputs['research_specialist'] ?? null,
            'precedent_analysis' => $agentOutputs['precedent_analyst'] ?? null,
            'legal_strategy' => $agentOutputs['strategy_specialist'] ?? null,
            'risk_assessment' => $agentOutputs['risk_analyst'] ?? null,
        ];
    }

    /**
     * Generate final synthesis using LLM
     */
    protected function generateFinalSynthesis(array $agentOutputs, string $problem): string
    {
        $summaries = [];

        foreach ($agentOutputs as $agentName => $output) {
            if (isset($output['summary'])) {
                $summaries[$agentName] = $output['summary'];
            }
        }

        $summariesFormatted = '';
        foreach ($summaries as $agent => $summary) {
            $summariesFormatted .= "\n{$agent}:\n{$summary}\n";
        }

        $prompt = <<<PROMPT
You are a senior Croatian legal advisor synthesizing input from a team of legal specialists.

Problem: {$problem}

Team Analysis:
{$summariesFormatted}

Create a comprehensive executive summary (300-500 words) that:
1. Summarizes the legal situation
2. Key findings from research and precedent analysis
3. Recommended strategy
4. Major risks and how to mitigate them
5. Overall assessment and next steps

Write in clear, professional Croatian legal language.
PROMPT;

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => 'You are a senior Croatian legal advisor.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'temperature' => 0.4,
                'max_tokens' => 1000,
            ]);

            $synthesis = $response['choices'][0]['message']['content'] ?? 'Synthesis generation failed';

            // Track tokens
            if (isset($response['usage']['total_tokens'])) {
                $this->context->addTokensUsed($response['usage']['total_tokens']);
            }

            return $synthesis;

        } catch (\Exception $e) {
            Log::error('LegalTeamOrchestrator - Synthesis generation failed', [
                'error' => $e->getMessage(),
            ]);

            return 'Failed to generate synthesis: '.$e->getMessage();
        }
    }

    /**
     * Summarize agent contributions
     */
    protected function summarizeContributions(array $agentOutputs): array
    {
        $contributions = [];

        foreach ($agentOutputs as $agentName => $output) {
            $contributions[$agentName] = [
                'success' => $output['success'] ?? false,
                'summary' => $output['summary'] ?? 'No summary available',
                'key_data' => $this->extractKeyData($agentName, $output),
            ];
        }

        return $contributions;
    }

    /**
     * Extract key data from agent output
     */
    protected function extractKeyData(string $agentName, array $output): array
    {
        return match ($agentName) {
            'research_specialist' => [
                'laws_found' => $output['laws_found'] ?? 0,
                'decisions_found' => $output['decisions_found'] ?? 0,
            ],
            'precedent_analyst' => [
                'decisions_analyzed' => $output['decisions_analyzed'] ?? 0,
                'strongest_count' => count($output['strongest_precedents'] ?? []),
            ],
            'strategy_specialist' => [
                'arguments_developed' => count($output['legal_arguments'] ?? []),
                'success_probability' => $output['strategic_recommendations']['success_probability'] ?? null,
            ],
            'risk_analyst' => [
                'risk_level' => $output['overall_risk_score']['level'] ?? 'unknown',
                'risk_score' => $output['overall_risk_score']['score'] ?? null,
            ],
            default => [],
        };
    }

    /**
     * Get collaboration by ID
     */
    public function getCollaboration(string $collaborationId): ?AgentCollaboration
    {
        return AgentCollaboration::with('executions')->find($collaborationId);
    }

    /**
     * Get recent collaborations
     */
    public function getRecentCollaborations(int $limit = 10): array
    {
        return AgentCollaboration::with('executions')
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
