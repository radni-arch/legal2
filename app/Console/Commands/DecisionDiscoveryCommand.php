<?php

namespace App\Console\Commands;

use App\Agents\DecisionDiscoveryAgent;
use App\Models\DecisionDiscoveryRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled command that runs autonomous decision discovery
 *
 * This command wraps DecisionDiscoveryAgent::discover() and provides:
 * - CLI interface for manual execution
 * - Scheduled execution via Kernel.php
 * - Logging summary via DecisionDiscoveryRun model
 * - Queue job integration for retry mechanism
 */
class DecisionDiscoveryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decisions:discovery
                          {--queue : Run via queue job for retry mechanism}
                          {--topics=5 : Number of topics to generate}
                          {--per-topic=50 : Decisions to evaluate per topic}
                          {--ingest=10 : Top decisions to ingest per topic}
                          {--threshold=70 : Relevance threshold (0-100)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run autonomous decision discovery (scheduled daily at 03:00)';

    /**
     * Execute the console command.
     */
    public function handle(DecisionDiscoveryAgent $agent): int
    {
        $this->info('Starting Decision Discovery...');
        $this->newLine();

        // Check if should dispatch to queue
        if ($this->option('queue')) {
            return $this->handleViaQueue();
        }

        try {
            // Configure agent based on options
            $agent->setTopicsPerRun((int) $this->option('topics'))
                ->setDecisionsPerTopic((int) $this->option('per-topic'))
                ->setIngestPerTopic((int) $this->option('ingest'))
                ->setRelevanceThreshold((float) $this->option('threshold'));

            $this->info('Configuration:');
            $this->table(
                ['Setting', 'Value'],
                [
                    ['Topics per run', $agent->getTopicsPerRun()],
                    ['Decisions per topic', $agent->getDecisionsPerTopic()],
                    ['Ingest per topic', $agent->getIngestPerTopic()],
                    ['Relevance threshold', $agent->getRelevanceThreshold()],
                ]
            );
            $this->newLine();

            // Execute discovery
            $this->info('Running discovery agent...');
            $stats = $agent->discover();

            // Display results
            $this->newLine();
            $this->info('Discovery completed successfully!');
            $this->newLine();

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Topics Generated', $stats['topics_generated']],
                    ['Decisions Evaluated', $stats['decisions_evaluated']],
                    ['Decisions Ingested', $stats['decisions_ingested']],
                    ['Errors', count($stats['errors'])],
                ]
            );

            // Show latest run summary
            $this->displayLatestRunSummary();

            // Show errors if any
            if (! empty($stats['errors'])) {
                $this->newLine();
                $this->warn('Errors encountered during discovery:');
                foreach ($stats['errors'] as $error) {
                    $this->line("  - {$error['topic']}: {$error['error']}");
                }
            }

            $this->newLine();
            $this->info('✓ Discovery run logged to decision_discovery_runs table');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('Discovery failed: '.$e->getMessage());

            Log::error('DecisionDiscoveryCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Handle execution via queue job
     */
    protected function handleViaQueue(): int
    {
        $this->info('Dispatching to queue...');

        try {
            \App\Jobs\RunDecisionDiscovery::dispatch(
                topics: (int) $this->option('topics'),
                perTopic: (int) $this->option('per-topic'),
                ingest: (int) $this->option('ingest'),
                threshold: (float) $this->option('threshold')
            );

            $this->info('✓ Job dispatched to queue successfully');
            $this->line('  Monitor with: php artisan queue:work');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to dispatch job: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Display latest run summary from database
     */
    protected function displayLatestRunSummary(): void
    {
        $latestRun = DecisionDiscoveryRun::query()
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $latestRun) {
            return;
        }

        $this->newLine();
        $this->info('Latest Run Summary:');
        $this->line("  Status: {$latestRun->status}");
        $this->line("  Started: {$latestRun->started_at}");

        if ($latestRun->completed_at) {
            $this->line("  Completed: {$latestRun->completed_at}");
            $this->line("  Duration: {$latestRun->duration()} seconds");
        }

        if ($latestRun->isCompleted()) {
            $this->line('  Success Rate: '.DecisionDiscoveryRun::getSuccessRate().'%');
        }

        if ($latestRun->isFailed() && $latestRun->error_message) {
            $this->warn("  Error: {$latestRun->error_message}");
        }
    }
}
