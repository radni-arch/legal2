<?php

namespace App\Console\Commands;

use App\Models\TextractBatch;
use Illuminate\Console\Command;

class TextractBatchStatus extends Command
{
    protected $signature = 'textract:batch-status {batchId?} {--all} {--watch}';

    protected $description = 'Monitor Textract batch processing status';

    public function handle()
    {
        $batchId = $this->argument('batchId');
        $all = $this->option('all');
        $watch = $this->option('watch');

        if ($watch) {
            $this->watchBatch($batchId);
        } elseif ($all) {
            $this->showAllBatches();
        } elseif ($batchId) {
            $this->showBatchDetails($batchId);
        } else {
            $this->showRecentBatches();
        }

        return Command::SUCCESS;
    }

    protected function watchBatch(string $batchId)
    {
        $this->info("Watching batch: {$batchId} (Press Ctrl+C to stop)");

        while (true) {
            $this->line("\033[2J\033[;H"); // Clear screen

            $batch = TextractBatch::find($batchId);

            if (! $batch) {
                $this->error("Batch not found: {$batchId}");

                return;
            }

            $this->displayBatch($batch, true);

            if ($batch->status === 'completed' || $batch->status === 'failed') {
                $this->info("\nBatch finished with status: {$batch->status}");
                break;
            }

            sleep(2);
        }
    }

    protected function showBatchDetails(string $batchId)
    {
        $batch = TextractBatch::with('jobs')->find($batchId);

        if (! $batch) {
            $this->error("Batch not found: {$batchId}");

            return;
        }

        $this->displayBatch($batch, true);

        // Show jobs table
        if ($batch->jobs->count() > 0) {
            $this->info("\nJobs in batch:");

            $headers = ['ID', 'File Name', 'Status', 'Queue', 'Worker', 'Duration'];
            $rows = [];

            foreach ($batch->jobs as $job) {
                $duration = $job->processing_started_at && $job->updated_at
                    ? $job->processing_started_at->diffInSeconds($job->updated_at).'s'
                    : '-';

                $rows[] = [
                    substr($job->id, 0, 8),
                    substr($job->file_name ?? 'N/A', 0, 30),
                    $job->status,
                    $job->queue_name,
                    $job->worker_id ?? '-',
                    $duration,
                ];
            }

            $this->table($headers, $rows);
        }
    }

    protected function showRecentBatches()
    {
        $batches = TextractBatch::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No batches found.');

            return;
        }

        $this->info('Recent Batches (last 10):');

        $headers = ['ID', 'Type', 'Status', 'Progress', 'Success Rate', 'Started', 'Duration'];
        $rows = [];

        foreach ($batches as $batch) {
            $duration = $batch->started_at && $batch->completed_at
                ? $batch->started_at->diffInSeconds($batch->completed_at).'s'
                : ($batch->started_at ? $batch->started_at->diffForHumans() : '-');

            $rows[] = [
                substr($batch->id, 0, 8),
                $batch->batch_type,
                $this->colorizeStatus($batch->status),
                $batch->progress.'%',
                $batch->success_rate.'%',
                $batch->started_at ? $batch->started_at->format('Y-m-d H:i') : '-',
                $duration,
            ];
        }

        $this->table($headers, $rows);
    }

    protected function showAllBatches()
    {
        $batches = TextractBatch::orderBy('created_at', 'desc')->get();

        if ($batches->isEmpty()) {
            $this->info('No batches found.');

            return;
        }

        $this->info("All Batches ({$batches->count()} total):");
        $this->showRecentBatches();
    }

    protected function displayBatch(TextractBatch $batch, bool $detailed = false)
    {
        $this->info("Batch: {$batch->id}");
        $this->line("Type: {$batch->batch_type}");
        $this->line('Status: '.$this->colorizeStatus($batch->status));
        $this->line("Source: {$batch->source_identifier}");

        $this->newLine();

        // Progress bar
        $progressBar = $this->getProgressBar($batch->progress);
        $this->line("Progress: {$progressBar} {$batch->progress}%");

        $this->newLine();

        // Statistics
        $this->line("Total Files: {$batch->total_files}");
        $this->line("Processed: {$batch->processed_files}");
        $this->line("Failed: {$batch->failed_files}");
        $this->line("Success Rate: {$batch->success_rate}%");

        if ($detailed && $batch->started_at) {
            $this->newLine();
            $this->line("Started: {$batch->started_at->format('Y-m-d H:i:s')}");

            if ($batch->completed_at) {
                $this->line("Completed: {$batch->completed_at->format('Y-m-d H:i:s')}");
                $duration = $batch->started_at->diffInSeconds($batch->completed_at);
                $this->line("Duration: {$duration} seconds");
            } else {
                $elapsed = $batch->started_at->diffInSeconds(now());
                $this->line("Elapsed: {$elapsed} seconds");

                if ($batch->processed_files > 0) {
                    $avgTime = $elapsed / $batch->processed_files;
                    $remaining = ($batch->total_files - $batch->processed_files) * $avgTime;
                    $this->line('Estimated remaining: '.round($remaining).' seconds');
                }
            }
        }

        if ($detailed && $batch->configuration) {
            $this->newLine();
            $this->line('Configuration:');
            foreach ($batch->configuration as $key => $value) {
                $this->line("  {$key}: ".(is_bool($value) ? ($value ? 'true' : 'false') : $value));
            }
        }
    }

    protected function getProgressBar(float $progress): string
    {
        $total = 50;
        $filled = (int) ($progress / 100 * $total);
        $empty = $total - $filled;

        return '['.str_repeat('=', $filled).str_repeat(' ', $empty).']';
    }

    protected function colorizeStatus(string $status): string
    {
        return match ($status) {
            'pending' => "<fg=yellow>{$status}</>",
            'processing' => "<fg=blue>{$status}</>",
            'completed' => "<fg=green>{$status}</>",
            'failed' => "<fg=red>{$status}</>",
            default => $status,
        };
    }
}
