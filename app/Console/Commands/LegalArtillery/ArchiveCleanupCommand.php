<?php

namespace App\Console\Commands\LegalArtillery;

use App\Models\DocumentGenerationRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ArchiveCleanupCommand extends Command
{
    protected $signature = 'legal:archive-cleanup
        {--dry-run : Show what would be cleaned without deleting}
        {--days= : Override retention days}';

    protected $description = 'Clean up old document generation runs based on retention policy';

    public function handle(): int
    {
        $archiveDays = $this->option('days') ?? config('legal-artillery.retention.archive_days', 365);
        $failedDays = config('legal-artillery.retention.cleanup_failed_days', 30);
        $isDryRun = $this->option('dry-run');

        $this->info(($isDryRun ? '[DRY RUN] ' : '').'Archive cleanup starting...');

        // 1. Archive old completed runs
        $cutoff = now()->subDays($archiveDays);
        $oldRuns = DocumentGenerationRun::where('status', 'completed')
            ->where('created_at', '<', $cutoff)
            ->get();

        $this->info("Found {$oldRuns->count()} completed runs older than {$archiveDays} days");

        $archived = 0;
        foreach ($oldRuns as $run) {
            if (! $isDryRun) {
                $this->archiveRun($run);
                $archived++;
            } else {
                $this->line("  Would archive: {$run->id} ({$run->document_type}, {$run->created_at})");
            }
        }

        // 2. Clean up old failed runs
        $failedCutoff = now()->subDays($failedDays);
        $failedRuns = DocumentGenerationRun::where('status', 'failed')
            ->where('created_at', '<', $failedCutoff)
            ->get();

        $this->info("Found {$failedRuns->count()} failed runs older than {$failedDays} days");

        $cleaned = 0;
        foreach ($failedRuns as $run) {
            if (! $isDryRun) {
                $this->cleanupRun($run);
                $cleaned++;
            } else {
                $this->line("  Would cleanup: {$run->id} ({$run->document_type}, {$run->created_at})");
            }
        }

        $this->info("Archived: {$archived}, Cleaned: {$cleaned}");

        Log::info('LegalArtillery: Archive cleanup', [
            'archived' => $archived,
            'cleaned' => $cleaned,
            'dry_run' => $isDryRun,
        ]);

        return self::SUCCESS;
    }

    private function archiveRun(DocumentGenerationRun $run): void
    {
        $archivePath = config('legal-artillery.retention.archive_path', storage_path('app/legal-artillery/archive'));
        File::ensureDirectoryExists($archivePath);

        // Save run data as JSON archive
        $archiveFile = "{$archivePath}/{$run->id}.json";
        $data = [
            'run' => $run->toArray(),
            'iterations' => $run->iterations->toArray(),
            'context' => $run->context?->toArray(),
            'archived_at' => now()->toIso8601String(),
            'document_hash' => $run->final_document ? hash('sha256', $run->final_document) : null,
        ];

        file_put_contents($archiveFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Clear the large text field but keep the run record
        $run->update(['final_document' => null]);

        Log::info('LegalArtillery: Run archived', ['run_id' => $run->id, 'archive' => $archiveFile]);
    }

    private function cleanupRun(DocumentGenerationRun $run): void
    {
        // Delete DOCX file if exists
        $docxPath = $run->model_config['docx_path'] ?? null;
        if ($docxPath && file_exists($docxPath)) {
            unlink($docxPath);
        }

        // Delete iterations
        $run->iterations()->delete();

        // Delete context
        $run->context()?->delete();

        // Delete the run
        $run->delete();

        Log::info('LegalArtillery: Failed run cleaned', ['run_id' => $run->id]);
    }
}
