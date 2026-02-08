<?php

namespace App\Console\Commands;

use App\Agents\Specialists\PrecedentAnalystAgent;
use App\Agents\Specialists\RiskAnalystAgent;
use App\Agents\Specialists\StrategySpecialistAgent;
use App\Services\Collaboration\SharedAgentContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ValidateAgentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agents:validate
                            {agent? : Agent to validate (precedent|risk|strategy|all)}
                            {--format=text : Output format (text|json)}
                            {--save : Save results to JSON file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate agents against ground truth test data';

    protected array $results = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $agent = $this->argument('agent') ?? 'all';
        $format = $this->option('format');

        $this->info('🧪 Agent Validation Suite');
        $this->info('========================');
        $this->newLine();

        $agents = $this->getAgentsToValidate($agent);

        foreach ($agents as $agentName => $agentClass) {
            $this->info("Testing {$agentName}...");
            $result = $this->validateAgent($agentName, $agentClass);
            $this->results[$agentName] = $result;
            $this->displayResults($result);
            $this->newLine();
        }

        // Overall summary
        $this->displayOverallSummary();

        // Save results if requested
        if ($this->option('save')) {
            $this->saveResults();
        }

        // Output JSON if requested
        if ($format === 'json') {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT));
        }

        return 0;
    }

    /**
     * Get agents to validate
     */
    protected function getAgentsToValidate(string $agent): array
    {
        $allAgents = [
            'PrecedentAnalyst' => PrecedentAnalystAgent::class,
            'RiskAnalyst' => RiskAnalystAgent::class,
            'StrategySpecialist' => StrategySpecialistAgent::class,
        ];

        if ($agent === 'all') {
            return $allAgents;
        }

        $agentMap = [
            'precedent' => 'PrecedentAnalyst',
            'risk' => 'RiskAnalyst',
            'strategy' => 'StrategySpecialist',
        ];

        $agentName = $agentMap[strtolower($agent)] ?? null;

        if (! $agentName || ! isset($allAgents[$agentName])) {
            $this->error("Unknown agent: {$agent}");
            $this->info('Available: precedent, risk, strategy, all');
            exit(1);
        }

        return [$agentName => $allAgents[$agentName]];
    }

    /**
     * Validate an agent against test data
     */
    protected function validateAgent(string $agentName, string $agentClass): array
    {
        $testDir = base_path("tests/Fixtures/AgentValidation/{$agentName}");

        if (! File::exists($testDir)) {
            return [
                'agent' => $agentName,
                'error' => 'Test directory not found',
                'test_count' => 0,
                'passed' => 0,
                'accuracy' => 0,
            ];
        }

        // Load all test files
        $testFiles = File::allFiles($testDir);
        $testCases = [];

        foreach ($testFiles as $file) {
            if ($file->getExtension() === 'json') {
                $testCases[] = json_decode($file->getContents(), true);
            }
        }

        $this->line("  Found {$testCases->count()} test cases");

        // Run agent on each test case
        $passed = 0;
        $total = count($testCases);
        $errors = [];
        $accuracyScores = [];

        foreach ($testCases as $i => $testCase) {
            try {
                $accuracy = $this->runTestCase($agentName, $agentClass, $testCase);
                $accuracyScores[] = $accuracy;

                if ($accuracy >= 0.80) { // 80% accuracy threshold
                    $passed++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'test_id' => $testCase['test_id'] ?? "test_{$i}",
                    'error' => $e->getMessage(),
                ];
            }
        }

        $avgAccuracy = $total > 0 ? array_sum($accuracyScores) / $total : 0;

        return [
            'agent' => $agentName,
            'test_count' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
            'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
            'avg_accuracy' => round($avgAccuracy * 100, 2),
            'errors' => $errors,
            'accuracy_scores' => $accuracyScores,
        ];
    }

    /**
     * Run a single test case
     */
    protected function runTestCase(string $agentName, string $agentClass, array $testCase): float
    {
        // Create shared context
        $context = new SharedAgentContext(
            sessionId: 'validation_'.uniqid(),
            problemStatement: $testCase['input']['problem_statement']
        );

        // Prepare context data
        $this->prepareContext($context, $agentName, $testCase['input']);

        // Instantiate and execute agent
        $agent = app($agentClass);
        $result = $agent->execute($context, ['type' => 'validation']);

        // Calculate accuracy
        return $this->calculateAccuracy($agentName, $result, $testCase['expected_output']);
    }

    /**
     * Prepare shared context with input data
     */
    protected function prepareContext(SharedAgentContext $context, string $agentName, array $input): void
    {
        switch ($agentName) {
            case 'PrecedentAnalyst':
                $context->write('researched_decisions', $input['researched_decisions'] ?? []);
                $context->write('researched_laws', $input['researched_laws'] ?? []);
                break;

            case 'RiskAnalyst':
                // RiskAnalyst needs strategy data - simulate it
                $context->write('legal_arguments', $this->generateMockArguments($input));
                $context->write('strategic_recommendations', ['procedural_steps' => []]);
                $context->write('strongest_precedents', []);
                break;

            case 'StrategySpecialist':
                // StrategySpecialist needs precedent data
                $context->write('strongest_precedents', $input['precedents'] ?? []);
                $context->write('analyzed_laws', $input['laws'] ?? []);
                $context->write('distinguishing_factors', []);
                break;
        }
    }

    /**
     * Generate mock arguments for RiskAnalyst
     */
    protected function generateMockArguments(array $input): array
    {
        $scenarios = $input['scenarios'] ?? [];

        return array_map(function ($scenario, $i) {
            return [
                'title' => "Argument {$i}",
                'argument' => $scenario['description'] ?? 'Test argument',
                'strength_score' => 70,
                'potential_counterarguments' => [],
            ];
        }, $scenarios, array_keys($scenarios));
    }

    /**
     * Calculate accuracy for agent output
     */
    protected function calculateAccuracy(string $agentName, array $result, array $expected): float
    {
        if (! $result['success']) {
            return 0.0;
        }

        switch ($agentName) {
            case 'PrecedentAnalyst':
                return $this->calculatePrecedentAccuracy($result, $expected);

            case 'RiskAnalyst':
                return $this->calculateRiskAccuracy($result, $expected);

            case 'StrategySpecialist':
                return $this->calculateStrategyAccuracy($result, $expected);

            default:
                return 0.0;
        }
    }

    /**
     * Calculate accuracy for PrecedentAnalyst
     */
    protected function calculatePrecedentAccuracy(array $result, array $expected): float
    {
        $scores = [];

        // Check if we have analyzed decisions
        if (empty($result['strongest_precedents'])) {
            return 0.0;
        }

        $analyzedDecision = $result['strongest_precedents'][0] ?? null;

        if (! $analyzedDecision) {
            return 0.0;
        }

        // Score accuracy (within range)
        $actualScore = $analyzedDecision['applicability_score'] ?? 0;
        $expectedScore = $expected['applicability_score'] ?? 0;
        $scoreDiff = abs($actualScore - $expectedScore);
        $scoreAccuracy = max(0, 1 - ($scoreDiff / 100));
        $scores[] = $scoreAccuracy;

        // Binding authority accuracy
        $actualAuthority = $analyzedDecision['binding_authority'] ?? '';
        $expectedAuthority = $expected['binding_authority'] ?? '';
        $scores[] = ($actualAuthority === $expectedAuthority) ? 1.0 : 0.0;

        // Favorable accuracy
        if (isset($expected['favorable']) && isset($analyzedDecision['favorable'])) {
            $scores[] = ($analyzedDecision['favorable'] === $expected['favorable']) ? 1.0 : 0.0;
        }

        return array_sum($scores) / count($scores);
    }

    /**
     * Calculate accuracy for RiskAnalyst
     */
    protected function calculateRiskAccuracy(array $result, array $expected): float
    {
        $scores = [];

        // Risk level accuracy
        $actualLevel = $result['overall_risk_score']['level'] ?? '';
        $expectedLevel = $expected['risk_level'] ?? '';
        $scores[] = ($actualLevel === $expectedLevel) ? 1.0 : 0.0;

        // Risk score accuracy (within 20 points)
        $actualScore = $result['overall_risk_score']['score'] ?? 0;
        $expectedScore = $expected['risk_score'] ?? 0;
        $scoreDiff = abs($actualScore - $expectedScore);
        $scoreAccuracy = max(0, 1 - ($scoreDiff / 100));
        $scores[] = $scoreAccuracy;

        return array_sum($scores) / count($scores);
    }

    /**
     * Calculate accuracy for StrategySpecialist
     */
    protected function calculateStrategyAccuracy(array $result, array $expected): float
    {
        $scores = [];

        // Success probability accuracy
        if (isset($expected['success_probability']) && isset($result['strategic_recommendations']['success_probability'])) {
            $actualProb = $result['strategic_recommendations']['success_probability'];
            $expectedProb = $expected['success_probability'];
            $probDiff = abs($actualProb - $expectedProb);
            $probAccuracy = max(0, 1 - $probDiff);
            $scores[] = $probAccuracy;
        }

        // Argument count accuracy (at least minimum expected)
        if (isset($expected['min_arguments'])) {
            $actualCount = count($result['legal_arguments'] ?? []);
            $minCount = $expected['min_arguments'];
            $scores[] = ($actualCount >= $minCount) ? 1.0 : ($actualCount / $minCount);
        }

        return ! empty($scores) ? array_sum($scores) / count($scores) : 0.0;
    }

    /**
     * Display results for an agent
     */
    protected function displayResults(array $result): void
    {
        $passRate = $result['pass_rate'];
        $accuracy = $result['avg_accuracy'];

        $passRateColor = $passRate >= 90 ? 'green' : ($passRate >= 70 ? 'yellow' : 'red');
        $accuracyColor = $accuracy >= 90 ? 'green' : ($accuracy >= 70 ? 'yellow' : 'red');

        $this->line("  Test Cases: {$result['test_count']}");
        $this->line("  Passed: <fg={$passRateColor}>{$result['passed']}</> / Failed: {$result['failed']}");
        $this->line("  Pass Rate: <fg={$passRateColor}>{$passRate}%</>");
        $this->line("  Avg Accuracy: <fg={$accuracyColor}>{$accuracy}%</>");

        if (! empty($result['errors'])) {
            $this->warn('  Errors: '.count($result['errors']));
            foreach (array_slice($result['errors'], 0, 3) as $error) {
                $this->line("    - {$error['test_id']}: {$error['error']}");
            }
        }
    }

    /**
     * Display overall summary
     */
    protected function displayOverallSummary(): void
    {
        $this->info('Overall Summary');
        $this->info('==============');

        $totalTests = 0;
        $totalPassed = 0;
        $avgAccuracies = [];

        foreach ($this->results as $agentName => $result) {
            $totalTests += $result['test_count'];
            $totalPassed += $result['passed'];
            $avgAccuracies[] = $result['avg_accuracy'];
        }

        $overallPassRate = $totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 2) : 0;
        $overallAccuracy = ! empty($avgAccuracies) ? round(array_sum($avgAccuracies) / count($avgAccuracies), 2) : 0;

        $this->line("Total Tests: {$totalTests}");
        $this->line("Total Passed: {$totalPassed}");
        $this->line("Overall Pass Rate: {$overallPassRate}%");
        $this->line("Overall Accuracy: {$overallAccuracy}%");

        if ($overallAccuracy < 90) {
            $this->newLine();
            $this->warn('⚠️  Overall accuracy below 90% - review needed');
            $this->line('   Action plan should be created to improve accuracy');
        } else {
            $this->newLine();
            $this->info('✅ All agents meeting accuracy targets (≥90%)');
        }
    }

    /**
     * Save results to JSON file
     */
    protected function saveResults(): void
    {
        $timestamp = now()->format('Y-m-d_His');
        $filename = "agent_validation_{$timestamp}.json";
        $path = storage_path("app/validation/{$filename}");

        // Ensure directory exists
        File::ensureDirectoryExists(dirname($path));

        // Save results
        File::put($path, json_encode([
            'timestamp' => now()->toIso8601String(),
            'results' => $this->results,
        ], JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("Results saved to: {$path}");
    }
}
