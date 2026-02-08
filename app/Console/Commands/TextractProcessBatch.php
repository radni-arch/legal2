<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTextractJob;
use App\Models\TextractBatch;
use App\Models\TextractJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Process a batch of Textract jobs from CSV file
 *
 * CSV Format: drive_file_id,drive_file_name,case_id
 */
class TextractProcessBatch extends Command
{
    protected $signature = 'textract:process-batch
                            {--file= : CSV file path (relative to storage/app)}
                            {--queue=textract : Queue name for job processing}';

    protected $description = 'Process a batch of Textract jobs from CSV file';

    public function handle(): int
    {
        $filePath = $this->option('file');

        if (! $filePath) {
            $this->error('Please provide a CSV file using --file option');

            return Command::FAILURE;
        }

        // Check if file exists
        if (! Storage::disk('local')->exists($filePath)) {
            $this->error("Batch file not found: {$filePath}");

            return Command::FAILURE;
        }

        $this->info("Reading batch file: {$filePath}");

        // Read CSV file
        $csvContent = Storage::disk('local')->get($filePath);
        $lines = array_filter(explode("\n", trim($csvContent)));

        // Parse CSV
        $header = str_getcsv(array_shift($lines));
        $entries = [];

        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) === count($header)) {
                $entries[] = array_combine($header, $data);
            }
        }

        if (empty($entries)) {
            $this->error('No valid entries found in CSV file');

            return Command::FAILURE;
        }

        $totalEntries = count($entries);
        $this->info("Found {$totalEntries} entries in batch file");

        // Create batch record
        $batch = TextractBatch::create([
            'batch_type' => 'csv_file',
            'source_identifier' => $filePath,
            'total_files' => count($entries),
            'processed_files' => 0,
            'failed_files' => 0,
            'status' => 'pending',
            'configuration' => [
                'file' => $filePath,
                'queue' => $this->option('queue'),
            ],
        ]);

        $this->info("Created batch with ID: {$batch->id}");

        $queueName = $this->option('queue');
        $dispatchedCount = 0;

        // Create jobs for each entry
        foreach ($entries as $entry) {
            $job = TextractJob::create([
                'drive_file_id' => $entry['drive_file_id'] ?? null,
                'drive_file_name' => $entry['drive_file_name'] ?? null,
                'case_id' => ! empty($entry['case_id']) ? $entry['case_id'] : null,
                'status' => 'queued',
                'batch_id' => $batch->id,
                'queue_name' => $queueName,
            ]);

            // Dispatch job to queue
            ProcessTextractJob::dispatch($job->id)->onQueue($queueName);
            $dispatchedCount++;
        }

        $this->info("Dispatched {$dispatchedCount} jobs to queue");

        return Command::SUCCESS;
    }
}
