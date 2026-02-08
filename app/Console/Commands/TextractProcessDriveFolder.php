<?php

namespace App\Console\Commands;

use App\Actions\Textract\EnsureTextractJob;
use App\Actions\Textract\ListDrivePdfs;
use App\Jobs\ExtractTablesFromTextractJob;
use App\Jobs\ProcessTextractJob;
use App\Models\LegalCase;
use App\Models\TextractBatch;
use App\Models\TextractJob;
use Google\Service\Exception;
use Illuminate\Console\Command;

class TextractProcessDriveFolder extends Command
{
    protected $signature = 'textract:process-drive-folder
                            {folderId?}
                            {--limit=0}
                            {--sync}
                            {--force}
                            {--case=}
                            {--force-textract}
                            {--queue=textract : Queue name for job processing}
                            {--priority=0 : Job priority (higher = more important)}
                            {--batch : Create batch for tracking}
                            {--parallel : Enable parallel processing}
                            {--extract-tables : Extract tables from PDFs}';

    protected $description = 'Process PDFs from Google Drive with distributed queue system';

    /**
     * @throws Exception
     */
    public function handle()
    {
        $folderId = (string) ($this->argument('folderId') ?: env('GOOGLE_DRIVE_FOLDER_ID'));
        if (! $folderId) {
            $this->error('Folder ID nije zadan. Koristite argument ili .env GOOGLE_DRIVE_FOLDER_ID.');

            return Command::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync');
        $forceTextract = (bool) $this->option('force-textract');
        $createBatch = (bool) $this->option('batch');
        $parallel = (bool) $this->option('parallel');
        $extractTables = (bool) $this->option('extract-tables');
        $queueName = (string) $this->option('queue');
        $priority = (int) $this->option('priority');

        $caseId = (string) ($this->option('case') ?? '');
        if ($caseId === '') {
            $this->error('Case ID is required. Provide with --case=CASE_ID');

            return Command::FAILURE;
        }
        if (! LegalCase::query()->where('id', $caseId)->exists()) {
            $this->error('Selected case not found: '.$caseId);

            return Command::FAILURE;
        }

        $this->info("Listing PDFs in Drive folder: {$folderId}");
        $this->info('Mode: '.($sync ? 'SYNC' : ($parallel ? 'PARALLEL QUEUE' : 'SEQUENTIAL QUEUE')));

        if ($createBatch) {
            $this->info('Batch tracking: ENABLED');
        }

        if ($extractTables) {
            $this->info('Table extraction: ENABLED');
        }

        $files = ListDrivePdfs::run($folderId);

        if (empty($files)) {
            $this->info('Nema PDF-ova u folderu.');

            return Command::SUCCESS;
        }

        // Create batch if requested
        $batch = null;
        if ($createBatch) {
            $batch = TextractBatch::create([
                'batch_type' => 'drive_folder',
                'source_identifier' => $folderId,
                'total_files' => min($limit > 0 ? $limit : count($files), count($files)),
                'status' => 'pending',
                'configuration' => [
                    'case_id' => $caseId,
                    'queue_name' => $queueName,
                    'priority' => $priority,
                    'parallel' => $parallel,
                    'extract_tables' => $extractTables,
                    'force_textract' => $forceTextract,
                ],
            ]);

            $this->info("Created batch: {$batch->id}");
            $batch->markProcessing();
        }

        $count = 0;
        $jobIds = [];

        foreach ($files as $f) {
            if ($limit > 0 && $count >= $limit) {
                break;
            }

            $driveId = (string) ($f['id'] ?? '');
            $name = (string) ($f['name'] ?? 'unknown.pdf');

            if ($driveId === '') {
                $this->warn('Skipping file with missing id.');

                continue;
            }

            // Centralized skip/create logic
            $decision = EnsureTextractJob::run($driveId, $name, $force);
            if (! $decision['shouldProcess']) {
                $this->info("SKIP (already done): {$name} ({$driveId})");

                if ($batch) {
                    $batch->incrementProcessed(false);
                }

                continue;
            }

            // Ensure job has the case and queue settings
            /** @var TextractJob $job */
            $job = $decision['job'];
            if ($job) {
                $job->update([
                    'case_id' => $caseId,
                    'queue_name' => $queueName,
                    'priority' => $priority,
                    'batch_id' => $batch?->id,
                    'queued_at' => now(),
                ]);
            }

            $jobIds[] = $job->id;

            if ($sync) {
                // Synchronous processing (legacy)
                $this->info("RUN SYNC: {$name} ({$driveId})");
                app(\App\Actions\Textract\ProcessDrivePdf::class)->handle($driveId, $name, $forceTextract);

                if ($batch) {
                    $batch->incrementProcessed(false);
                }
            } else {
                // Queue-based processing (NEW)
                $this->info("QUEUE [{$queueName}] Priority:{$priority}: {$name} ({$driveId})");

                // Dispatch to queue with priority
                $queueJob = ProcessTextractJob::dispatch($job->id, $batch?->id)
                    ->onQueue($queueName);

                // If parallel, dispatch immediately without delay
                // If sequential, add small delay between jobs
                if (! $parallel) {
                    $queueJob->delay(now()->addSeconds($count * 2));
                }

                // Dispatch table extraction job if requested
                if ($extractTables) {
                    ExtractTablesFromTextractJob::dispatch($job->id)
                        ->onQueue('textract-tables')
                        ->delay(now()->addMinutes(5)); // Wait for main job to complete
                }
            }

            $count++;
        }

        // Store batch statistics
        if ($batch) {
            $batch->update([
                'statistics' => [
                    'job_ids' => $jobIds,
                    'queued_count' => $count,
                    'parallel_mode' => $parallel,
                ],
            ]);
        }

        $this->info("Ukupno obrađeno/poslano u red: {$count}");

        if ($batch) {
            $this->info("Batch ID: {$batch->id}");
            $this->info("Monitor with: php artisan textract:batch-status {$batch->id}");
        }

        if ($parallel) {
            $this->info('Parallel mode: All jobs dispatched simultaneously');
            $this->info("Start workers with: php artisan queue:work --queue={$queueName}");
        }

        return Command::SUCCESS;
    }
}
