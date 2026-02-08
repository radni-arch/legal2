<?php

namespace App\Console\Commands;

use App\Jobs\RegenerateTextractEmbeddings;
use App\Models\TextractJob;
use Illuminate\Console\Command;

/**
 * Batch dispatch embedding regeneration for multiple TextractJobs.
 *
 * This command is useful for bulk operations like backfill or re-processing
 * documents after embedding model changes. Jobs are dispatched with staggered
 * delays to avoid overwhelming the queue.
 *
 * Usage examples:
 *   - Process all completed jobs: php artisan textract:batch-regenerate-embeddings
 *   - Dry run to see what would be processed: php artisan textract:batch-regenerate-embeddings --dry-run
 *   - Filter by case: php artisan textract:batch-regenerate-embeddings --case=123
 *   - Limit to 50 jobs with 10s delay: php artisan textract:batch-regenerate-embeddings --limit=50 --delay=10
 */
class BatchRegenerateEmbeddings extends Command
{
    protected $signature = 'textract:batch-regenerate-embeddings
                            {--case= : Process all jobs for a specific case}
                            {--status=* : Filter by job status (default: completed, succeeded)}
                            {--limit= : Maximum jobs to process}
                            {--delay=5 : Seconds between job dispatches}
                            {--dry-run : Show what would be processed}';

    protected $description = 'Batch dispatch embedding regeneration for multiple TextractJobs';

    public function handle(): int
    {
        $query = TextractJob::query();

        // Filter by case_id if specified
        if ($caseId = $this->option('case')) {
            $query->where('case_id', $caseId);
        }

        // Filter by status (default: completed and succeeded)
        $statuses = $this->option('status');
        if (empty($statuses)) {
            $statuses = ['completed', 'succeeded'];
        }
        $query->whereIn('status', $statuses);

        // Apply limit if specified
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $jobs = $query->get();
        $delay = (int) $this->option('delay');

        $this->info("Found {$jobs->count()} jobs to process");

        if ($jobs->isEmpty()) {
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'File', 'Status'],
                $jobs->map(fn ($j) => [$j->id, $j->drive_file_name, $j->status])
            );

            return Command::SUCCESS;
        }

        $dispatched = 0;
        foreach ($jobs as $index => $job) {
            RegenerateTextractEmbeddings::dispatch($job->id)
                ->delay(now()->addSeconds($delay * $index));
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} jobs with {$delay}s stagger");

        return Command::SUCCESS;
    }
}
