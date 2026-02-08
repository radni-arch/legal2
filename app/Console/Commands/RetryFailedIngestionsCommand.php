<?php

namespace App\Console\Commands;

use App\Jobs\IngestOdlukeDecision;
use App\Models\FailedIngestion;
use Illuminate\Console\Command;

class RetryFailedIngestionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'odluke:retry-failed
                            {--id= : Specific failed ingestion ID to retry}
                            {--decision-id= : Retry all failed attempts for a specific decision ID}
                            {--status=* : Filter by status (pending, retrying, failed)}
                            {--limit=100 : Maximum number of failures to retry}
                            {--reset : Reset attempt counter before retrying}
                            {--abandon= : Mark specific IDs as abandoned (comma-separated)}
                            {--dry-run : Show what would be retried without actually retrying}
                            {--stats : Show statistics only}';

    /**
     * The console command description.
     */
    protected $description = 'Retry failed Odluke ingestions with exponential backoff and dead-letter handling';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Show statistics
        if ($this->option('stats')) {
            return $this->showStatistics();
        }

        // Abandon specific IDs
        if ($abandonIds = $this->option('abandon')) {
            return $this->abandonIngestions($abandonIds);
        }

        // Build query for retries
        $query = $this->buildQuery();

        // Get count
        $count = $query->count();

        if ($count === 0) {
            $this->info('No failed ingestions found matching criteria.');

            return 0;
        }

        // Show what will be retried
        $this->info("Found {$count} failed ingestion(s) to retry.");

        if ($this->option('dry-run')) {
            $this->showDryRunResults($query);

            return 0;
        }

        // Confirm before proceeding
        if (! $this->confirm('Do you want to proceed with retry?', true)) {
            $this->info('Aborted.');

            return 0;
        }

        // Retry failed ingestions
        return $this->retryFailedIngestions($query);
    }

    /**
     * Build query based on options
     */
    protected function buildQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = FailedIngestion::query();

        // Filter by specific ID
        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        // Filter by decision ID
        if ($decisionId = $this->option('decision-id')) {
            $query->where('decision_id', $decisionId);
        }

        // Filter by status
        if ($statuses = $this->option('status')) {
            $query->whereIn('status', $statuses);
        } else {
            // Default: retry pending and retrying
            $query->whereIn('status', [
                FailedIngestion::STATUS_PENDING,
                FailedIngestion::STATUS_RETRYING,
            ]);
        }

        // Limit results
        $limit = (int) $this->option('limit');
        $query->limit($limit);

        // Order by next retry time
        $query->orderBy('next_retry_at');

        return $query;
    }

    /**
     * Retry failed ingestions
     */
    protected function retryFailedIngestions(\Illuminate\Database\Eloquent\Builder $query): int
    {
        $failures = $query->get();
        $queued = 0;
        $skipped = 0;
        $errors = 0;

        $reset = $this->option('reset');

        $this->output->progressStart($failures->count());

        foreach ($failures as $failure) {
            try {
                // Reset attempt counter if requested
                if ($reset) {
                    $failure->resetRetryCounter();
                    $this->line("Reset attempt counter for decision {$failure->decision_id}");
                }

                // Skip if max attempts reached and not reset
                if (! $reset && $failure->hasReachedMaxAttempts()) {
                    $skipped++;
                    $this->warn("Skipped {$failure->decision_id} (max attempts reached)");

                    continue;
                }

                // Dispatch retry job
                IngestOdlukeDecision::dispatch(
                    $failure->decision_id,
                    $failure->ingestion_options ?? [],
                    (string) $failure->id
                );

                $queued++;

                // Update status
                $failure->update([
                    'status' => FailedIngestion::STATUS_RETRYING,
                    'next_retry_at' => now()->addMinutes(1),
                ]);

                $this->output->progressAdvance();
            } catch (\Throwable $e) {
                $errors++;
                $this->error("Failed to retry {$failure->decision_id}: {$e->getMessage()}");
            }
        }

        $this->output->progressFinish();

        // Summary
        $this->newLine();
        $this->info('Retry Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Queued for retry', $queued],
                ['Skipped', $skipped],
                ['Errors', $errors],
            ]
        );

        return 0;
    }

    /**
     * Show dry run results
     */
    protected function showDryRunResults(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $failures = $query->get();

        $this->info('Dry Run - Following ingestions would be retried:');
        $this->newLine();

        $rows = $failures->map(function ($failure) {
            return [
                $failure->id,
                $failure->decision_id,
                $failure->status_label,
                $failure->failure_reason,
                $failure->attempt_count.'/'.$failure->max_attempts,
                $failure->next_retry_at?->diffForHumans() ?? 'Now',
            ];
        });

        $this->table(
            ['ID', 'Decision ID', 'Status', 'Reason', 'Attempts', 'Next Retry'],
            $rows
        );
    }

    /**
     * Show statistics
     */
    protected function showStatistics(): int
    {
        $stats = FailedIngestion::getStatistics();

        $this->info('Failed Ingestion Statistics:');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Failed Ingestions', $stats['total']],
                ['Pending', $stats['pending']],
                ['Retrying', $stats['retrying']],
                ['Permanently Failed', $stats['failed']],
                ['Succeeded (after retry)', $stats['succeeded']],
                ['Abandoned', $stats['abandoned']],
                ['Ready for Retry', $stats['ready_for_retry']],
            ]
        );

        // Show recent failures
        $this->newLine();
        $this->info('Recent Failures (last 10):');
        $this->newLine();

        $recent = FailedIngestion::orderBy('created_at', 'desc')->limit(10)->get();

        if ($recent->isEmpty()) {
            $this->info('No recent failures.');
        } else {
            $rows = $recent->map(function ($failure) {
                return [
                    $failure->id,
                    $failure->decision_id,
                    $failure->status_label,
                    $failure->failure_reason,
                    $failure->attempt_count.'/'.$failure->max_attempts,
                    $failure->created_at->diffForHumans(),
                ];
            });

            $this->table(
                ['ID', 'Decision ID', 'Status', 'Reason', 'Attempts', 'Age'],
                $rows
            );
        }

        // Show ready for retry
        $ready = FailedIngestion::readyForRetry()->count();
        if ($ready > 0) {
            $this->newLine();
            $this->warn("{$ready} ingestion(s) are ready for retry.");
            $this->info('Run with --decision-id or without --stats to retry them.');
        }

        return 0;
    }

    /**
     * Abandon specific ingestions
     */
    protected function abandonIngestions(string $ids): int
    {
        $idArray = array_map('trim', explode(',', $ids));
        $abandoned = 0;

        foreach ($idArray as $id) {
            $failure = FailedIngestion::find($id);

            if (! $failure) {
                $this->warn("Failed ingestion {$id} not found.");

                continue;
            }

            $failure->markAbandoned('Manually abandoned via CLI');
            $abandoned++;

            $this->info("Abandoned failed ingestion {$id} (decision: {$failure->decision_id})");
        }

        $this->newLine();
        $this->info("Abandoned {$abandoned} ingestion(s).");

        return 0;
    }
}
