<?php

namespace App\Console\Commands;

use App\DTOs\Agent\AgentResult;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\Agent\AgentManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AnalyzeCaseWithAgentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'case:agent-analyze
                            {case_id : The ID of the case to analyze}
                            {--driver= : The agent driver to use}
                            {--fallback : Use fallback chain if primary driver fails}
                            {--sync : Run synchronously (blocking)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze case documents with an AI agent';

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $caseId = $this->argument('case_id');
        $driverName = $this->option('driver');
        $useFallback = $this->option('fallback');
        $sync = $this->option('sync');

        // Find the case
        $case = LegalCase::find($caseId);

        if (!$case) {
            $this->error("Case not found: {$caseId}");
            return 1;
        }

        $this->info('Case Analysis');
        $this->info('=============');
        $this->line("Case: {$case->case_number} - {$case->title}");
        $this->newLine();

        // Get case documents
        $documents = CaseDocument::where('case_id', $caseId)->get();

        if ($documents->isEmpty()) {
            $this->error('No documents found for this case.');
            return 1;
        }

        $this->line("Found {$documents->count()} document(s)");

        // Get file paths from documents
        $filePaths = $this->resolveFilePaths($documents);

        if (empty($filePaths)) {
            $this->error('No document files found on disk.');
            return 1;
        }

        $this->line("Resolved {$filePaths->count()} file path(s)");
        $this->newLine();

        // Determine driver
        if ($useFallback) {
            $this->info('Using fallback chain...');
            return $this->runWithFallback($manager, $caseId, $filePaths->toArray());
        }

        // Use specified or default driver
        $driver = $manager->driver($driverName);
        $driverUsed = $driverName ?? $manager->getDefaultDriver();

        $this->info("Using driver: {$driverUsed}");
        $this->newLine();

        // Run analysis
        return $this->runAnalysis($driver, $caseId, $filePaths->toArray(), $driverUsed);
    }

    /**
     * Resolve file paths from documents.
     *
     * @param \Illuminate\Support\Collection $documents
     * @return \Illuminate\Support\Collection
     */
    protected function resolveFilePaths($documents)
    {
        return $documents
            ->filter(fn($doc) => !empty($doc->source))
            ->map(function ($doc) {
                $source = $doc->source;

                // If path starts with 'storage/', convert to absolute path
                if (str_starts_with($source, 'storage/')) {
                    $source = base_path($source);
                }

                // If not absolute, assume it's relative to storage
                if (!str_starts_with($source, '/')) {
                    $source = storage_path("app/{$source}");
                }

                return $source;
            })
            ->filter(fn($path) => File::exists($path));
    }

    /**
     * Run analysis with a specific driver.
     */
    protected function runAnalysis($driver, string $caseId, array $filePaths, string $driverUsed): int
    {
        $this->info('Starting analysis...');

        $phases = config('agents.analysis.phases', [
            'document_review' => 'Review each document and extract key facts.',
        ]);

        $startTime = microtime(true);

        try {
            $results = $driver->bulkAnalysis($caseId, $filePaths, $phases);
        } catch (\Exception $e) {
            $this->error("Analysis failed: {$e->getMessage()}");
            return 1;
        }

        $totalElapsed = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info('Analysis Results');
        $this->info('================');

        $hasFailures = false;
        $outputFiles = [];

        foreach ($results as $phaseName => $result) {
            /** @var AgentResult $result */
            $status = $result->success ? '<fg=green>SUCCESS</>' : '<fg=red>FAILED</>';
            $this->line("Phase: {$phaseName} - {$status}");
            $this->line("  Driver: {$result->driver}");
            $this->line("  Elapsed: {$result->elapsedSeconds}s");

            if ($result->outputFile) {
                $this->line("  Output: " . basename($result->outputFile));
                $outputFiles[] = $result->outputFile;
            }

            if (!$result->success) {
                $hasFailures = true;
                if ($result->rawError) {
                    $this->error("  Error: {$result->rawError}");
                }
            }

            $this->newLine();
        }

        $this->info("Total elapsed time: {$totalElapsed}s");
        $this->line("Driver used: {$driverUsed}");

        if (!empty($outputFiles)) {
            $this->newLine();
            $this->info('Output files:');
            foreach ($outputFiles as $file) {
                $this->line("  - " . basename($file));
            }
        }

        if ($hasFailures) {
            $this->warn('Some phases failed.');
            return 1;
        }

        $this->newLine();
        $this->info('Analysis completed successfully.');

        return 0;
    }

    /**
     * Run analysis with fallback chain.
     */
    protected function runWithFallback(AgentManager $manager, string $caseId, array $filePaths): int
    {
        $this->info('Using fallback chain...');

        $prompt = "Analyze the case documents and provide a comprehensive analysis.";

        $startTime = microtime(true);

        try {
            $result = $manager->runWithFallback($prompt, $filePaths, [
                'case_id' => $caseId,
            ]);
        } catch (\Exception $e) {
            $this->error("All agents in fallback chain failed: {$e->getMessage()}");
            return 1;
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info('Analysis Results');
        $this->info('================');

        $status = $result->success ? '<fg=green>SUCCESS</>' : '<fg=red>FAILED</>';
        $this->line("Status: {$status}");
        $this->line("Driver: {$result->driver}");
        $this->line("Elapsed: {$result->elapsedSeconds}s");

        if ($result->outputFile) {
            $this->line("Output: " . basename($result->outputFile));
        }

        if (!$result->success) {
            $this->warn('Analysis failed.');
            return 1;
        }

        $this->newLine();
        $this->info('Analysis completed successfully.');

        return 0;
    }
}
