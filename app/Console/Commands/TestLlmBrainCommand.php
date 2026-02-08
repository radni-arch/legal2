<?php

namespace App\Console\Commands;

use App\Examples\LlmBrainExamples;
use Illuminate\Console\Command;

class TestLlmBrainCommand extends Command
{
    protected $signature = 'llm:brain-test
                           {example? : Which example to run (1-10, or "all")}
                           {--query= : Query string for examples that need it}
                           {--id= : ID for examples that need it}';

    protected $description = 'Test LLM Brain examples (Graph + AI integration)';

    public function handle(LlmBrainExamples $examples): int
    {
        if (! config('neo4j.sync.enabled')) {
            $this->error('Neo4j is disabled. Enable it in .env to use LLM Brain features.');
            $this->info('Set NEO4J_ENABLED=true and run: php artisan graph:init');

            return self::FAILURE;
        }

        $example = $this->argument('example') ?? 'menu';

        if ($example === 'menu' || $example === null) {
            return $this->showMenu($examples);
        }

        if ($example === 'all') {
            return $this->runAll($examples);
        }

        return $this->runExample((int) $example, $examples);
    }

    protected function showMenu(LlmBrainExamples $examples): int
    {
        $this->info('=== LLM Brain Examples ===');
        $this->newLine();

        $this->line('Choose an example to run:');
        $this->newLine();

        $this->table(
            ['#', 'Example', 'Description'],
            [
                [1, 'Hybrid Retrieval', 'Vector + Graph search'],
                [2, 'Natural Language Query', 'Convert NL to Cypher'],
                [3, 'Time Travel', 'Query law at specific date'],
                [4, 'Reasoning Chains', 'Multi-hop graph traversal'],
                [5, 'Contradiction Detection', 'Find & explain contradictions'],
                [6, 'Graph-Enhanced Chat', 'Chat with graph knowledge'],
                [7, 'Structural Similarity', 'Node2Vec embeddings'],
                [8, 'Influence Analysis', 'PageRank most influential'],
                [9, 'Topic Exploration', 'Explore topic relationships'],
                [10, 'Counterfactual', 'What-if analysis'],
            ]
        );

        $this->newLine();
        $this->info('Usage:');
        $this->line('  php artisan llm:brain-test 1 --query="proportionality in searches"');
        $this->line('  php artisan llm:brain-test 2 --query="Find Supreme Court decisions from 2023"');
        $this->line('  php artisan llm:brain-test 3 --id=law-123 --query="2020-06-15"');
        $this->line('  php artisan llm:brain-test all');

        return self::SUCCESS;
    }

    protected function runExample(int $number, LlmBrainExamples $examples): int
    {
        $this->info("Running Example {$number}...");
        $this->newLine();

        try {
            $result = match ($number) {
                1 => $this->runExample1($examples),
                2 => $this->runExample2($examples),
                3 => $this->runExample3($examples),
                4 => $this->runExample4($examples),
                5 => $this->runExample5($examples),
                6 => $this->runExample6($examples),
                7 => $this->runExample7($examples),
                8 => $this->runExample8($examples),
                9 => $this->runExample9($examples),
                10 => $this->runExample10($examples),
                default => throw new \RuntimeException("Example {$number} not found")
            };

            $this->displayResult($result);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function runAll(LlmBrainExamples $examples): int
    {
        $this->info('Running all examples...');
        $this->newLine();

        for ($i = 1; $i <= 10; $i++) {
            $this->info("=== Example {$i} ===");
            $this->runExample($i, $examples);
            $this->newLine();

            if ($i < 10) {
                sleep(2); // Pause between examples
            }
        }

        return self::SUCCESS;
    }

    // ==================== EXAMPLE RUNNERS ====================

    protected function runExample1(LlmBrainExamples $examples): array
    {
        $query = $this->option('query') ?? 'proportionality in home searches';
        $this->line("Query: {$query}");

        return $examples->hybridRetrieval($query);
    }

    protected function runExample2(LlmBrainExamples $examples): array
    {
        $query = $this->option('query') ?? 'Find Supreme Court decisions from last year';
        $this->line("Query: {$query}");

        return $examples->naturalLanguageQuery($query);
    }

    protected function runExample3(LlmBrainExamples $examples): array
    {
        $lawId = $this->option('id') ?? 'law-123';
        $date = $this->option('query') ?? '2020-06-15';
        $this->line("Law: {$lawId}, Date: {$date}");

        return $examples->timeTravelQuery($lawId, $date);
    }

    protected function runExample4(LlmBrainExamples $examples): array
    {
        $caseId = $this->option('id') ?? 'case-123';
        $lawId = $this->option('query') ?? 'law-456';
        $this->line("From case: {$caseId} to law: {$lawId}");

        return $examples->findReasoningChain($caseId, $lawId);
    }

    protected function runExample5(LlmBrainExamples $examples): array
    {
        $decisionId = $this->option('id') ?? 'decision-123';
        $this->line("Analyzing decision: {$decisionId}");

        return $examples->detectAndExplainContradictions($decisionId);
    }

    protected function runExample6(LlmBrainExamples $examples): array
    {
        $message = $this->option('query') ?? 'What are the legal requirements for home searches?';
        $this->line("Message: {$message}");

        return $examples->chatWithGraphKnowledge($message);
    }

    protected function runExample7(LlmBrainExamples $examples): array
    {
        $caseId = $this->option('id') ?? 'case-123';
        $this->line("Finding cases structurally similar to: {$caseId}");

        return $examples->findStructurallySimilarCases($caseId);
    }

    protected function runExample8(LlmBrainExamples $examples): array
    {
        $this->line('Analyzing influence using PageRank...');

        return $examples->findInfluentialEntities();
    }

    protected function runExample9(LlmBrainExamples $examples): array
    {
        $topic = $this->option('query') ?? 'proportionality';
        $this->line("Exploring topic: {$topic}");

        return $examples->exploreTopic($topic);
    }

    protected function runExample10(LlmBrainExamples $examples): array
    {
        $lawId = $this->option('id') ?? 'law-123';
        $this->line("What if law {$lawId} didn't exist?");

        return $examples->counterfactualAnalysis($lawId);
    }

    // ==================== DISPLAY HELPERS ====================

    protected function displayResult(array $result): void
    {
        $this->info('Result:');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
