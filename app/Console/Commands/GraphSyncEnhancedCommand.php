<?php

namespace App\Console\Commands;

use App\Models\CourtDecision;
use App\Services\Graph\DecisionGraphSyncService;
use Illuminate\Console\Command;

class GraphSyncEnhancedCommand extends Command
{
    protected $signature = 'graph:sync-enhanced
                            {--limit= : Limit number of decisions to sync}
                            {--dry-run : Preview without syncing}';

    protected $description = 'Sync court decisions to graph with enhanced entity extraction';

    public function __construct(protected DecisionGraphSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');

        // Build query for decisions to sync
        $query = CourtDecision::query();

        // Calculate total count respecting limit
        $totalDecisions = CourtDecision::count();
        $totalCount = $limit ? min((int) $limit, $totalDecisions) : $totalDecisions;

        if ($limit) {
            $query->limit((int) $limit);
        }

        if ($totalCount === 0) {
            $this->info('No court decisions found to sync');

            return self::SUCCESS;
        }

        // Dry run mode - just preview
        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
            $this->info("Would sync {$totalCount} decision(s)");

            return self::SUCCESS;
        }

        // Actual sync with chunking for memory efficiency
        $this->info('Starting enhanced graph sync...');

        $synced = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        // Use chunk to process in batches (100 at a time)
        $query->chunk(100, function ($decisions) use (&$synced, &$errors, $progressBar) {
            foreach ($decisions as $decision) {
                try {
                    $this->syncService->sync($decision->id);
                    $synced++;
                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("Error syncing decision {$decision->id}: {$e->getMessage()}");
                    $progressBar->display();
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->info("Synced: {$synced}");

        if ($errors > 0) {
            $this->warn("Errors: {$errors}");
        } else {
            $this->info("Errors: {$errors}");
        }

        $this->info('Enhanced sync completed successfully!');

        return self::SUCCESS;
    }
}
