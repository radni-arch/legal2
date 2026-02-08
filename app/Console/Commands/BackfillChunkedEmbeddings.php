<?php

namespace App\Console\Commands;

use App\Jobs\RegenerateTextractEmbeddings;
use App\Models\TextractJob;
use Illuminate\Console\Command;

/**
 * Task 1.4: Migration & Backfill
 *
 * Backfills chunked embeddings for TextractJobs that have 'synced' status
 * but no TextractDocument children (they only have the old single-vector format).
 */
class BackfillChunkedEmbeddings extends Command
{
    protected $signature = 'textract:backfill-embeddings
        {--dry-run : Show what would be processed without dispatching jobs}
        {--limit= : Limit the number of jobs to process}
        {--delay=0 : Delay in seconds between job dispatches}';

    protected $description = 'Backfill chunked embeddings for TextractJobs missing TextractDocument records';

    public function handle(): int
    {
        $query = TextractJob::where('embedding_status', 'synced')
            ->whereDoesntHave('documents')
            ->where(function ($q) {
                $q->where('status', 'completed')
                    ->orWhere('status', 'succeeded');
            });

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $jobs = $query->get();

        $this->info("Found {$jobs->count()} jobs needing chunked embedding backfill");

        if ($jobs->isEmpty()) {
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run mode - no jobs will be dispatched');
            foreach ($jobs as $job) {
                $this->line("Would process: TextractJob #{$job->id} - {$job->drive_file_name}");
            }

            return Command::SUCCESS;
        }

        $delay = (int) $this->option('delay');

        foreach ($jobs as $index => $job) {
            if ($delay > 0) {
                RegenerateTextractEmbeddings::dispatch($job->id)
                    ->delay(now()->addSeconds($delay * $index));
            } else {
                RegenerateTextractEmbeddings::dispatch($job->id);
            }
        }

        $this->info("Dispatched {$jobs->count()} embedding regeneration jobs");

        return Command::SUCCESS;
    }
}
