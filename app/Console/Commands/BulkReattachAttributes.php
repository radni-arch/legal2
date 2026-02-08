<?php

namespace App\Console\Commands;

use App\Models\VectorDocument;
use App\Services\CatalogService;
use Illuminate\Console\Command;

class BulkReattachAttributes extends Command
{
    protected $signature = 'vs:bulk-reattach
        {--vs= : Target vector store ID (filters to specific store)}
        {--dry : Dry run, show what would be uploaded}
        {--sleep=150 : Pause between API calls in ms}';

    protected $description = 'Bulk upload and attach tagged documents to vector stores from database';

    public function handle(CatalogService $catalog): int
    {
        $sleepMs = (int) $this->option('sleep');
        $dry = (bool) $this->option('dry');
        $vsId = $this->option('vs') ?: null;

        // Get documents that need upload from database
        $query = VectorDocument::needsUpload();

        if ($vsId) {
            $query = $query->forStore($vsId);
        }

        $docs = $query->get();

        if ($docs->isEmpty()) {
            $this->info('No documents need upload.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($docs->count());
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($docs as $doc) {
            if ($dry) {
                $this->line(" [DRY] Would upload: {$doc->file_name} to {$doc->vector_store_id}");
            } else {
                try {
                    $catalog->uploadAndAttach($doc);
                    $this->line(" Uploaded: {$doc->file_name}");
                    $successCount++;
                } catch (\Exception $e) {
                    $this->error(" Failed: {$doc->file_name} - ".$e->getMessage());
                    $failCount++;
                }
            }
            $bar->advance();
            usleep(max(0, $sleepMs) * 1000);
        }

        $bar->finish();
        $this->newLine();

        if ($dry) {
            $this->info("Done. Would process {$docs->count()} documents.");
        } else {
            $this->info("Done. Uploaded: {$successCount}, Failed: {$failCount}, Total: {$docs->count()}");
        }

        return self::SUCCESS;
    }
}
