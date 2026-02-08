<?php

namespace App\Console\Commands;

use App\Models\TextractJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Export Textract job results to JSON or CSV
 *
 * Exports extracted content, metadata, and job status.
 */
class TextractExportResults extends Command
{
    protected $signature = 'textract:export-results
                            {jobId : The TextractJob ID to export}
                            {--format=json : Export format (json|csv)}';

    protected $description = 'Export Textract job results to JSON or CSV';

    public function handle(): int
    {
        $jobId = $this->argument('jobId');
        $format = $this->option('format');

        // Validate format
        if (! in_array($format, ['json', 'csv'])) {
            $this->error("Invalid format: {$format}. Use 'json' or 'csv'");

            return Command::FAILURE;
        }

        // Find the job
        $job = TextractJob::find($jobId);

        if (! $job) {
            $this->error("Job not found: {$jobId}");

            return Command::FAILURE;
        }

        $this->info("Exporting results for job: {$jobId}");
        $this->info("Format: {$format}");

        try {
            if ($format === 'json') {
                $this->exportJson($job);
            } else {
                $this->exportCsv($job);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to export results: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function exportJson(TextractJob $job): void
    {
        $data = [
            'job_id' => $job->id,
            'aws_job_id' => $job->job_id,
            'status' => $job->status,
            'file_name' => $job->drive_file_name,
            'drive_file_id' => $job->drive_file_id,
            'case_id' => $job->case_id,
            'extracted_content' => $job->extracted_content,
            'manual_content' => $job->manual_content,
            'manually_edited' => $job->manually_edited,
            'metadata' => $job->metadata,
            'error' => $job->error,
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
        ];

        $fileName = "textract_export_{$job->id}.json";
        Storage::disk('local')->put($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Export completed: storage/app/{$fileName}");
    }

    protected function exportCsv(TextractJob $job): void
    {
        $fileName = "textract_export_{$job->id}.csv";

        // CSV header
        $header = ['job_id', 'aws_job_id', 'status', 'file_name', 'drive_file_id', 'case_id', 'extracted_content_length', 'has_error', 'created_at', 'updated_at'];

        // CSV data row
        $row = [
            $job->id,
            $job->job_id,
            $job->status,
            $job->drive_file_name,
            $job->drive_file_id,
            $job->case_id ?? '',
            strlen($job->extracted_content ?? ''),
            $job->error ? 'yes' : 'no',
            $job->created_at?->toIso8601String(),
            $job->updated_at?->toIso8601String(),
        ];

        // Build CSV content
        $csv = $this->arrayToCsv($header)."\n";
        $csv .= $this->arrayToCsv($row);

        Storage::disk('local')->put($fileName, $csv);

        $this->info("Export completed: storage/app/{$fileName}");
    }

    protected function arrayToCsv(array $fields): string
    {
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $fields);
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return trim($csv);
    }
}
