<?php

namespace App\Console\Commands;

use App\Models\CourtDecision;
use App\Services\Graph\DecisionGraphSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to re-sync court decisions to the Neo4j graph database.
 *
 * This command is useful for:
 * - Backfilling existing decisions after schema changes
 * - Re-syncing specific decisions after data corrections
 * - Rebuilding graph relationships after citations detector updates
 */
class GraphResyncCommand extends Command
{
    protected $signature = 'graph:resync
        {--decision-id= : ID of a specific decision to sync}
        {--all : Sync all decisions}
        {--batch=100 : Number of decisions to process per batch}
        {--dry-run : Show what would be synced without actually syncing}';

    protected $description = 'Re-sync court decisions to the Neo4j graph database';

    public function __construct(
        protected DecisionGraphSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $decisionId = $this->option('decision-id');
        $all = $this->option('all');
        $batchSize = (int) $this->option('batch');
        $dryRun = $this->option('dry-run');

        if (! $decisionId && ! $all) {
            $this->error('Please provide --decision-id or --all');

            return self::FAILURE;
        }

        if ($decisionId) {
            return $this->syncSingleDecision($decisionId, $dryRun);
        }

        return $this->syncAllDecisions($batchSize, $dryRun);
    }

    protected function syncSingleDecision(string $decisionId, bool $dryRun): int
    {
        $decision = CourtDecision::find($decisionId);

        if (! $decision) {
            $this->error("Decision not found: {$decisionId}");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Would sync decision: {$decision->case_number} ({$decision->id})");

            return self::SUCCESS;
        }

        try {
            $this->syncService->sync($decision->id);
            $this->info("Successfully synced 1 decision: {$decision->case_number}");

            Log::info('Graph resync completed', [
                'decision_id' => $decision->id,
                'case_number' => $decision->case_number,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to sync decision: {$e->getMessage()}");

            Log::error('Graph resync failed', [
                'decision_id' => $decision->id,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    protected function syncAllDecisions(int $batchSize, bool $dryRun): int
    {
        $total = CourtDecision::count();

        if ($total === 0) {
            $this->warn('No decisions found to sync');

            return self::SUCCESS;
        }

        $this->info("Found {$total} decisions to sync (batch size: {$batchSize})");

        if ($dryRun) {
            $this->info("Dry run - would sync {$total} decisions");

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        CourtDecision::query()
            ->orderBy('created_at')
            ->chunk($batchSize, function ($decisions) use (&$synced, &$failed, $progressBar) {
                foreach ($decisions as $decision) {
                    try {
                        $this->syncService->sync($decision->id);
                        $synced++;
                    } catch (\Exception $e) {
                        $failed++;
                        Log::warning('Graph resync failed for decision', [
                            'decision_id' => $decision->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Successfully synced {$synced} decisions");

        if ($failed > 0) {
            $this->warn("Failed to sync {$failed} decisions (check logs for details)");
        }

        Log::info('Batch graph resync completed', [
            'synced' => $synced,
            'failed' => $failed,
            'total' => $total,
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
