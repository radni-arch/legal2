<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\TextractProcessBatch;
use App\Jobs\ProcessTextractJob;
use App\Models\TextractBatch;
use App\Models\TextractJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for TextractProcessBatch command
 *
 * TDD - RED Phase
 */
class TextractProcessBatchCommandTest extends TestCase
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

        // Fake the default disk for CSV files
        Storage::fake('local');

        // Fake the queue
        Queue::fake();
    }

    /** @test */
    public function it_processes_batch_from_csv_file(): void
    {
        // Create a CSV file with 3 entries (case_id can be null or empty)
        $csvContent = "drive_file_id,drive_file_name,case_id\n";
        $csvContent .= "file-001,document1.pdf,\n";
        $csvContent .= "file-002,document2.pdf,\n";
        $csvContent .= "file-003,document3.pdf,\n";

        Storage::disk('local')->put('batch.csv', $csvContent);

        // Run the command
        $this->artisan('textract:process-batch', ['--file' => 'batch.csv'])
            ->expectsOutput('Reading batch file: batch.csv')
            ->expectsOutput('Found 3 entries in batch file')
            ->expectsOutputToContain('Created batch with ID:')
            ->expectsOutput('Dispatched 3 jobs to queue')
            ->assertExitCode(0);

        // Verify batch was created
        $this->assertDatabaseCount('textract_batches', 1);

        $batch = TextractBatch::first();
        $this->assertEquals('csv_file', $batch->batch_type);
        $this->assertEquals(3, $batch->total_files);
        $this->assertEquals('pending', $batch->status);

        // Verify jobs were created
        $this->assertDatabaseCount('textract_jobs', 3);

        // Verify jobs are linked to the batch
        $jobs = TextractJob::where('batch_id', $batch->id)->get();
        $this->assertCount(3, $jobs);

        // Verify first job has correct data
        $firstJob = $jobs->first();
        $this->assertEquals('file-001', $firstJob->drive_file_id);
        $this->assertEquals('document1.pdf', $firstJob->drive_file_name);
        $this->assertNull($firstJob->case_id);

        // Verify jobs were dispatched to queue
        Queue::assertPushed(ProcessTextractJob::class, 3);
    }

    /** @test */
    public function it_handles_invalid_csv_file(): void
    {
        // Run the command with non-existent file
        $this->artisan('textract:process-batch', ['--file' => 'nonexistent.csv'])
            ->expectsOutput('Batch file not found: nonexistent.csv')
            ->assertExitCode(1);

        // Verify no batch or jobs were created
        $this->assertDatabaseCount('textract_batches', 0);
        $this->assertDatabaseCount('textract_jobs', 0);
    }
}
