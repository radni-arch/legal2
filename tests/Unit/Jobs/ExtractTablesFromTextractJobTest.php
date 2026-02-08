<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExtractTablesFromTextractJob;
use App\Models\TextractJob;
use App\Services\Textract\TableExtractorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ExtractTablesFromTextractJobTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_should_queue_interface(): void
    {
        $job = new ExtractTablesFromTextractJob('job-id-123');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_stores_job_id(): void
    {
        $job = new ExtractTablesFromTextractJob('textract-456');

        $reflection = new \ReflectionClass($job);
        $jobId = $reflection->getProperty('jobId');
        $jobId->setAccessible(true);

        $this->assertEquals('textract-456', $jobId->getValue($job));
    }

    /** @test */
    public function it_extracts_tables_and_updates_metadata(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-123',
            'drive_file_name' => 'document.pdf',
            'status' => 'succeeded',
        ]);

        Log::shouldReceive('info')->times(2);

        $tables = [
            ['row' => 1, 'col' => 1, 'text' => 'Header 1'],
            ['row' => 1, 'col' => 2, 'text' => 'Header 2'],
        ];

        $mockExtractor = Mockery::mock(TableExtractorService::class);
        $mockExtractor->shouldReceive('extractTables')
            ->once()
            ->with(Mockery::type(TextractJob::class))
            ->andReturn($tables);

        $job = new ExtractTablesFromTextractJob($textractJob->id);
        $job->handle($mockExtractor);

        $textractJob->refresh();
        $this->assertArrayHasKey('tables', $textractJob->metadata);
        $this->assertCount(2, $textractJob->metadata['tables']);
        $this->assertEquals(2, $textractJob->metadata['table_count']);
    }

    /** @test */
    public function it_handles_job_not_found_gracefully(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('ExtractTablesFromTextractJob - Job not found', Mockery::type('array'));
        Log::shouldReceive('info')->andReturn(null);

        $mockExtractor = Mockery::mock(TableExtractorService::class);
        $mockExtractor->shouldReceive('extractTables')->never();

        $job = new ExtractTablesFromTextractJob(999999);
        $job->handle($mockExtractor);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_handles_no_tables_found(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-456',
            'drive_file_name' => 'no-tables.pdf',
            'status' => 'succeeded',
        ]);

        Log::shouldReceive('info')->times(2);

        $mockExtractor = Mockery::mock(TableExtractorService::class);
        $mockExtractor->shouldReceive('extractTables')
            ->once()
            ->andReturn([]);

        $job = new ExtractTablesFromTextractJob($textractJob->id);
        $job->handle($mockExtractor);

        $textractJob->refresh();
        $this->assertEmpty($textractJob->metadata ?? []);
    }

    /** @test */
    public function it_logs_error_on_extraction_failure(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-789',
            'drive_file_name' => 'error.pdf',
            'status' => 'succeeded',
        ]);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $mockExtractor = Mockery::mock(TableExtractorService::class);
        $mockExtractor->shouldReceive('extractTables')
            ->once()
            ->andThrow(new \Exception('Extraction failed'));

        $job = Mockery::mock(ExtractTablesFromTextractJob::class.'[attempts]', [$textractJob->id]);
        $job->shouldReceive('attempts')
            ->andReturn(2); // Last attempt

        $job->handle($mockExtractor);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_rethrows_exception_if_not_last_attempt(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-retry',
            'drive_file_name' => 'retry.pdf',
            'status' => 'succeeded',
        ]);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $mockExtractor = Mockery::mock(TableExtractorService::class);
        $mockExtractor->shouldReceive('extractTables')
            ->once()
            ->andThrow(new \Exception('Temporary failure'));

        $job = Mockery::mock(ExtractTablesFromTextractJob::class.'[attempts]', [$textractJob->id]);
        $job->shouldReceive('attempts')
            ->andReturn(1); // Not last attempt

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Temporary failure');

        $job->handle($mockExtractor);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        ExtractTablesFromTextractJob::dispatch('job-queue-test');

        Queue::assertPushed(ExtractTablesFromTextractJob::class);
    }

    /** @test */
    public function it_has_correct_tags(): void
    {
        $job = new ExtractTablesFromTextractJob('tag-test-id');

        $tags = $job->tags();

        $this->assertContains('textract', $tags);
        $this->assertContains('tables', $tags);
        $this->assertContains('job:tag-test-id', $tags);
    }

    /** @test */
    public function it_has_2_retry_attempts(): void
    {
        $job = new ExtractTablesFromTextractJob('retry-test');

        $this->assertEquals(2, $job->tries);
    }

    /** @test */
    public function it_has_600_second_timeout(): void
    {
        $job = new ExtractTablesFromTextractJob('timeout-test');

        $this->assertEquals(600, $job->timeout);
    }

    /** @test */
    public function it_stores_extraction_timestamp_in_metadata(): void
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-timestamp',
            'drive_file_name' => 'timestamp.pdf',
            'status' => 'succeeded',
        ]);

        Log::shouldReceive('info')->times(2);

        $mockExtractor = Mockery::mock(TableExtractorService::class);
        $mockExtractor->shouldReceive('extractTables')
            ->once()
            ->andReturn([['data' => 'table']]);

        $job = new ExtractTablesFromTextractJob($textractJob->id);
        $job->handle($mockExtractor);

        $textractJob->refresh();
        $this->assertArrayHasKey('tables_extracted_at', $textractJob->metadata);
        $this->assertNotEmpty($textractJob->metadata['tables_extracted_at']);
    }
}
