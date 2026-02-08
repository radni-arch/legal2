<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\TextractExportResults;
use App\Models\TextractJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for TextractExportResults command
 *
 * TDD - RED Phase
 */
class TextractExportResultsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Set AWS environment variables for testing
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        // Fake the default disk for export files
        Storage::fake('local');
    }

    /** @test */
    public function it_exports_job_results_in_json_format(): void
    {
        // Create a completed job with content
        $job = TextractJob::factory()->create([
            'case_id' => null,
            'job_id' => 'aws-job-789',
            'status' => 'succeeded',
            'drive_file_name' => 'test-document.pdf',
            'extracted_content' => 'This is the extracted text content from the PDF.',
            'metadata' => [
                'pages' => 5,
                'confidence' => 0.98,
                'processing_time' => 120,
            ],
        ]);

        $expectedFileName = "textract_export_{$job->id}.json";

        // Run the command
        $this->artisan('textract:export-results', [
            'jobId' => $job->id,
            '--format' => 'json',
        ])
            ->expectsOutput("Exporting results for job: {$job->id}")
            ->expectsOutput('Format: json')
            ->expectsOutput("Export completed: storage/app/{$expectedFileName}")
            ->assertExitCode(0);

        // Verify the JSON file was created
        Storage::disk('local')->assertExists($expectedFileName);

        // Verify JSON content
        $jsonContent = Storage::disk('local')->get($expectedFileName);
        $data = json_decode($jsonContent, true);

        $this->assertEquals($job->id, $data['job_id']);
        $this->assertEquals($job->job_id, $data['aws_job_id']);
        $this->assertEquals($job->status, $data['status']);
        $this->assertEquals($job->drive_file_name, $data['file_name']);
        $this->assertEquals($job->extracted_content, $data['extracted_content']);
        $this->assertEquals($job->metadata, $data['metadata']);
    }

    /** @test */
    public function it_exports_job_results_in_csv_format(): void
    {
        // Create a completed job with content
        $job = TextractJob::factory()->create([
            'case_id' => null,
            'job_id' => 'aws-job-999',
            'status' => 'succeeded',
            'drive_file_name' => 'another-document.pdf',
            'extracted_content' => 'CSV export test content.',
            'metadata' => [
                'pages' => 3,
            ],
        ]);

        $expectedFileName = "textract_export_{$job->id}.csv";

        // Run the command
        $this->artisan('textract:export-results', [
            'jobId' => $job->id,
            '--format' => 'csv',
        ])
            ->expectsOutput("Exporting results for job: {$job->id}")
            ->expectsOutput('Format: csv')
            ->expectsOutput("Export completed: storage/app/{$expectedFileName}")
            ->assertExitCode(0);

        // Verify the CSV file was created
        Storage::disk('local')->assertExists($expectedFileName);

        // Verify CSV content
        $csvContent = Storage::disk('local')->get($expectedFileName);
        $lines = explode("\n", trim($csvContent));

        // Check header row
        $this->assertStringContainsString('job_id,aws_job_id,status,file_name', $lines[0]);

        // Check data row
        $this->assertStringContainsString($job->id, $lines[1]);
        $this->assertStringContainsString($job->job_id, $lines[1]);
        $this->assertStringContainsString($job->status, $lines[1]);
        $this->assertStringContainsString($job->drive_file_name, $lines[1]);
    }
}
