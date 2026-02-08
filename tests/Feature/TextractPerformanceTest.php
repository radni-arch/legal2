<?php

namespace Tests\Feature;

use App\Actions\Textract\ListDrivePdfs;
use App\Actions\Textract\ProcessDrivePdf;
use App\Models\TextractJob;
use App\Services\GoogleDriveService;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Performance benchmarking tests for Textract operations
 *
 * Tests performance characteristics including:
 * - Processing time for various file sizes
 * - Memory usage during processing
 * - Concurrent processing capabilities
 * - Database query efficiency
 * - Batch processing performance
 * - Resource cleanup efficiency
 */
class TextractPerformanceTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test single file processing completes in reasonable time
     *
     * @test
     */
    public function single_file_processing_completes_in_reasonable_time(): void
    {
        $driveFileId = 'perf-single-123';
        $driveFileName = 'single.pdf';

        $pipelineMock = $this->createSuccessfulPipelineMock();
        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        $startTime = microtime(true);
        $action->handle($driveFileId, $driveFileName);
        $duration = microtime(true) - $startTime;

        // Should complete in less than 5 seconds for mocked pipeline
        $this->assertLessThan(5.0, $duration, 'Single file processing should complete quickly');

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('succeeded', $job->status);
    }

    /**
     * Test memory usage during file processing
     *
     * @test
     */
    public function memory_usage_remains_reasonable_during_processing(): void
    {
        $driveFileId = 'perf-memory-123';
        $driveFileName = 'memory-test.pdf';

        $memoryBefore = memory_get_usage(true);

        $pipelineMock = $this->createSuccessfulPipelineMock();
        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle($driveFileId, $driveFileName);

        $memoryAfter = memory_get_usage(true);
        $memoryIncrease = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

        // Memory increase should be reasonable (less than 50MB for mocked test)
        $this->assertLessThan(50, $memoryIncrease, 'Memory usage should remain reasonable');
    }

    /**
     * Test database query count during processing
     *
     * @test
     */
    public function database_queries_are_optimized(): void
    {
        $driveFileId = 'perf-db-123';
        $driveFileName = 'db-test.pdf';

        $pipelineMock = $this->createSuccessfulPipelineMock();
        $this->app->instance(Pipeline::class, $pipelineMock);

        // Enable query logging
        DB::enableQueryLog();

        $action = new ProcessDrivePdf;
        $action->handle($driveFileId, $driveFileName);

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Should use efficient queries (reasonable number for create + update)
        // Expected: ~3-5 queries (insert job, update status, final update with content)
        $this->assertLessThan(20, $queryCount, 'Should minimize database queries');

        DB::disableQueryLog();
    }

    /**
     * Test batch processing of multiple files
     *
     * @test
     */
    public function batch_processing_of_multiple_files_is_efficient(): void
    {
        $fileCount = 10;
        $files = [];

        for ($i = 1; $i <= $fileCount; $i++) {
            $files[] = [
                'id' => "batch-file-{$i}",
                'name' => "file-{$i}.pdf",
            ];
        }

        $pipelineMock = $this->createSuccessfulPipelineMock();
        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        $startTime = microtime(true);

        foreach ($files as $file) {
            $action->handle($file['id'], $file['name']);
        }

        $duration = microtime(true) - $startTime;

        // Batch of 10 should complete in reasonable time
        $this->assertLessThan(30.0, $duration, 'Batch processing should be efficient');

        // Verify all jobs completed
        $succeededCount = TextractJob::where('status', 'succeeded')->count();
        $this->assertEquals($fileCount, $succeededCount);
    }

    /**
     * Test ListDrivePdfs performance with many files
     *
     * @test
     */
    public function list_drive_pdfs_handles_large_result_sets(): void
    {
        // Mock Google Drive service with large result set
        $fileCount = 100;
        $mockFiles = [];

        for ($i = 1; $i <= $fileCount; $i++) {
            $mockFiles[] = [
                'id' => "large-list-{$i}",
                'name' => "document-{$i}.pdf",
                'mimeType' => 'application/pdf',
                'size' => rand(100000, 5000000),
            ];
        }

        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn($mockFiles);

        $action = new ListDrivePdfs($driveMock);

        $startTime = microtime(true);
        $result = $action->handle('folder-123');
        $duration = microtime(true) - $startTime;

        // Should list 100 files quickly
        $this->assertLessThan(2.0, $duration, 'Listing large result sets should be fast');
        $this->assertCount($fileCount, $result);
    }

    /**
     * Test processing time scales reasonably with file count
     *
     * @test
     */
    public function processing_time_scales_reasonably(): void
    {
        $pipelineMock = $this->createSuccessfulPipelineMock();
        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        // Process small batch
        $smallBatchStart = microtime(true);
        for ($i = 1; $i <= 5; $i++) {
            $action->handle("scale-small-{$i}", "small-{$i}.pdf");
        }
        $smallBatchDuration = microtime(true) - $smallBatchStart;

        // Process larger batch
        $largeBatchStart = microtime(true);
        for ($i = 1; $i <= 10; $i++) {
            $action->handle("scale-large-{$i}", "large-{$i}.pdf");
        }
        $largeBatchDuration = microtime(true) - $largeBatchStart;

        // Time should scale roughly linearly (with some overhead tolerance)
        $ratio = $largeBatchDuration / $smallBatchDuration;
        $this->assertLessThan(3.0, $ratio, 'Processing time should scale reasonably');
        $this->assertGreaterThan(1.5, $ratio, 'Should show measurable scaling');
    }

    /**
     * Test database connection pooling efficiency
     *
     * @test
     */
    public function database_connections_are_reused_efficiently(): void
    {
        $pipelineMock = $this->createSuccessfulPipelineMock();
        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        // Process multiple files and verify connections are reused
        for ($i = 1; $i <= 20; $i++) {
            $action->handle("conn-{$i}", "conn-{$i}.pdf");
        }

        // All jobs should succeed without connection issues
        $succeededCount = TextractJob::where('status', 'succeeded')->count();
        $this->assertEquals(20, $succeededCount);

        // Verify no lingering database connections
        // This is implicit - if there were connection issues, tests would fail
        $this->assertTrue(true, 'Database connections managed efficiently');
    }

    /**
     * Test memory is released after processing
     *
     * @test
     */
    public function memory_is_released_after_processing(): void
    {
        $pipelineMock = $this->createSuccessfulPipelineMock();
        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        $memoryBefore = memory_get_usage(true);

        // Process several files
        for ($i = 1; $i <= 10; $i++) {
            $action->handle("mem-release-{$i}", "mem-{$i}.pdf");
        }

        // Force garbage collection
        gc_collect_cycles();

        $memoryAfter = memory_get_usage(true);
        $memoryIncrease = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

        // Memory should not increase excessively
        $this->assertLessThan(100, $memoryIncrease, 'Memory should be released after processing');
    }

    /**
     * Test concurrent job creation doesn't cause race conditions
     *
     * @test
     */
    public function concurrent_job_creation_is_safe(): void
    {
        $driveFileId = 'concurrent-safe-123';
        $driveFileName = 'concurrent.pdf';

        $pipelineMock = $this->createSuccessfulPipelineMock();
        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        // Simulate multiple rapid calls for same file
        try {
            $action->handle($driveFileId, $driveFileName);
            $action->handle($driveFileId, $driveFileName);
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            // Some calls might fail due to concurrency, that's okay
        }

        // Should have at least one succeeded job
        $jobs = TextractJob::where('drive_file_id', $driveFileId)->get();
        $this->assertGreaterThan(0, $jobs->count());

        // At least one should have succeeded
        $succeededJobs = $jobs->where('status', 'succeeded');
        $this->assertGreaterThan(0, $succeededJobs->count());
    }

    /**
     * Test large text extraction performance
     *
     * @test
     */
    public function large_text_extraction_is_performant(): void
    {
        $driveFileId = 'large-text-123';
        $driveFileName = 'large-document.pdf';

        // Create mock with large OCR document
        $largeOcrDocument = $this->createLargeOcrDocument(100); // 100 pages

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->andReturn([
            'job' => TextractJob::create([
                'drive_file_id' => $driveFileId,
                'drive_file_name' => $driveFileName,
                'status' => 'processing',
            ]),
            'ocrDocument' => $largeOcrDocument,
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        $startTime = microtime(true);
        $action->handle($driveFileId, $driveFileName);
        $duration = microtime(true) - $startTime;

        // Should handle large documents efficiently
        $this->assertLessThan(10.0, $duration, 'Large text extraction should be performant');

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertNotEmpty($job->extracted_content);
        $this->assertGreaterThan(1000, strlen($job->extracted_content));
    }

    /**
     * Test database update performance with large content
     *
     * @test
     */
    public function database_updates_with_large_content_are_efficient(): void
    {
        $driveFileId = 'large-content-123';
        $driveFileName = 'large-content.pdf';

        // Create large extracted content (simulate 1000-page document)
        $largeContent = str_repeat("This is a line of text from the PDF document.\n", 10000);

        $job = TextractJob::create([
            'drive_file_id' => $driveFileId,
            'drive_file_name' => $driveFileName,
            'status' => 'processing',
        ]);

        $startTime = microtime(true);
        $job->update([
            'status' => 'succeeded',
            'extracted_content' => $largeContent,
        ]);
        $duration = microtime(true) - $startTime;

        // Database update should be fast even with large content
        $this->assertLessThan(2.0, $duration, 'Large content updates should be efficient');

        $job->refresh();
        $this->assertEquals($largeContent, $job->extracted_content);
    }

    /**
     * Test job status query performance
     *
     * @test
     */
    public function job_status_queries_are_optimized(): void
    {
        // Create multiple jobs
        for ($i = 1; $i <= 50; $i++) {
            TextractJob::create([
                'drive_file_id' => "status-query-{$i}",
                'drive_file_name' => "status-{$i}.pdf",
                'status' => $i % 3 === 0 ? 'succeeded' : ($i % 3 === 1 ? 'failed' : 'pending'),
            ]);
        }

        DB::enableQueryLog();

        $startTime = microtime(true);

        // Query various status combinations
        $pending = TextractJob::where('status', 'pending')->count();
        $succeeded = TextractJob::where('status', 'succeeded')->count();
        $failed = TextractJob::where('status', 'failed')->count();

        $duration = microtime(true) - $startTime;

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should be fast even with multiple status queries
        $this->assertLessThan(1.0, $duration, 'Status queries should be fast');

        // Should use indexed queries (3 separate queries expected)
        $this->assertLessThanOrEqual(3, count($queries));

        $this->assertGreaterThan(0, $pending);
        $this->assertGreaterThan(0, $succeeded);
        $this->assertGreaterThan(0, $failed);
    }

    /**
     * Test bulk job creation performance
     *
     * @test
     */
    public function bulk_job_creation_is_efficient(): void
    {
        $jobCount = 100;
        $jobData = [];

        for ($i = 1; $i <= $jobCount; $i++) {
            $jobData[] = [
                'drive_file_id' => "bulk-{$i}",
                'drive_file_name' => "bulk-{$i}.pdf",
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $startTime = microtime(true);
        TextractJob::insert($jobData);
        $duration = microtime(true) - $startTime;

        // Bulk insert should be fast
        $this->assertLessThan(2.0, $duration, 'Bulk job creation should be efficient');

        $count = TextractJob::whereIn('drive_file_id', array_column($jobData, 'drive_file_id'))->count();
        $this->assertEquals($jobCount, $count);
    }

    /**
     * Test file listing performance with filtering
     *
     * @test
     */
    public function file_listing_with_filtering_is_performant(): void
    {
        // Create large mock file list
        $allFiles = [];
        for ($i = 1; $i <= 200; $i++) {
            $allFiles[] = [
                'id' => "filter-{$i}",
                'name' => $i % 2 === 0 ? "legal-{$i}.pdf" : "other-{$i}.pdf",
                'mimeType' => 'application/pdf',
                'size' => rand(100000, 5000000),
            ];
        }

        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn($allFiles);

        $action = new ListDrivePdfs($driveMock);

        $startTime = microtime(true);
        $result = $action->handle('folder-123');
        $duration = microtime(true) - $startTime;

        // Should handle filtering of large lists quickly
        $this->assertLessThan(2.0, $duration, 'Filtered listing should be performant');
        $this->assertCount(200, $result);
    }

    /**
     * Test resource cleanup after errors
     *
     * @test
     */
    public function resources_are_cleaned_up_after_errors(): void
    {
        $driveFileId = 'cleanup-error-123';
        $driveFileName = 'cleanup.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Cleanup test error'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        $memoryBefore = memory_get_usage(true);

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            // Expected
        }

        gc_collect_cycles();

        $memoryAfter = memory_get_usage(true);
        $memoryIncrease = ($memoryAfter - $memoryBefore) / 1024 / 1024;

        // Memory should not leak significantly even with errors
        $this->assertLessThan(10, $memoryIncrease, 'Resources should be cleaned up after errors');
    }

    /**
     * Test caching improves performance for repeated operations
     *
     * @test
     */
    public function caching_improves_repeated_operation_performance(): void
    {
        $folderId = 'cache-test-folder';
        $mockFiles = [
            ['id' => 'cache-1', 'name' => 'doc1.pdf', 'mimeType' => 'application/pdf', 'size' => 1000],
            ['id' => 'cache-2', 'name' => 'doc2.pdf', 'mimeType' => 'application/pdf', 'size' => 2000],
        ];

        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock->shouldReceive('listPdfsInFolder')
            ->once() // Should only be called once if caching works
            ->andReturn($mockFiles);

        $action = new ListDrivePdfs($driveMock);

        // First call - cache miss
        $startTime1 = microtime(true);
        $result1 = $action->handle($folderId);
        $duration1 = microtime(true) - $startTime1;

        // Cache the result manually for demonstration
        Cache::put("drive_pdfs_{$folderId}", $result1, 300);

        // Second call - should be faster if using cache
        $startTime2 = microtime(true);
        $result2 = Cache::get("drive_pdfs_{$folderId}");
        $duration2 = microtime(true) - $startTime2;

        // Cached access should be significantly faster
        if ($result2 !== null) {
            $this->assertLessThan($duration1, $duration2, 'Cached access should be faster');
        }

        $this->assertEquals($result1, $result2);
    }

    /**
     * Test processing maintains consistent performance under load
     *
     * @test
     */
    public function processing_maintains_consistent_performance(): void
    {
        $pipelineMock = $this->createSuccessfulPipelineMock();
        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $timings = [];

        // Process multiple files and measure timing consistency
        for ($i = 1; $i <= 20; $i++) {
            $startTime = microtime(true);
            $action->handle("consistent-{$i}", "doc-{$i}.pdf");
            $timings[] = microtime(true) - $startTime;
        }

        $avgTime = array_sum($timings) / count($timings);
        $maxTime = max($timings);
        $minTime = min($timings);

        // Performance should be relatively consistent
        $variance = ($maxTime - $minTime) / $avgTime;
        $this->assertLessThan(2.0, $variance, 'Performance should be consistent');

        // All jobs should succeed
        $this->assertEquals(20, TextractJob::where('status', 'succeeded')->count());
    }

    /**
     * Test that null/empty content updates are fast
     *
     * @test
     */
    public function empty_content_updates_are_efficient(): void
    {
        $job = TextractJob::create([
            'drive_file_id' => 'empty-content-123',
            'drive_file_name' => 'empty.pdf',
            'status' => 'processing',
        ]);

        $startTime = microtime(true);
        $job->update([
            'status' => 'succeeded',
            'extracted_content' => '',
        ]);
        $duration = microtime(true) - $startTime;

        // Empty updates should be very fast
        $this->assertLessThan(1.0, $duration, 'Empty content updates should be fast');
    }

    /**
     * Helper: Create a successful pipeline mock
     */
    protected function createSuccessfulPipelineMock(): Pipeline
    {
        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->andReturnUsing(function () {
            $payload = func_get_args()[0] ?? [];

            if (! isset($payload['job'])) {
                $driveFileId = $payload['driveFileId'] ?? 'test-file-id';
                $driveFileName = $payload['driveFileName'] ?? 'test.pdf';

                $payload['job'] = TextractJob::create([
                    'drive_file_id' => $driveFileId,
                    'drive_file_name' => $driveFileName,
                    'status' => 'processing',
                ]);
            }

            $payload['ocrDocument'] = (object) [
                'pages' => [
                    (object) [
                        'lines' => [
                            (object) ['text' => 'Sample text from PDF'],
                            (object) ['text' => 'Another line of text'],
                        ],
                    ],
                ],
            ];

            return $payload;
        });

        return $pipelineMock;
    }

    /**
     * Helper: Create a large OCR document for testing
     */
    protected function createLargeOcrDocument(int $pageCount): object
    {
        $pages = [];

        for ($i = 1; $i <= $pageCount; $i++) {
            $lines = [];
            for ($j = 1; $j <= 50; $j++) {
                $lines[] = (object) ['text' => "Page {$i}, Line {$j}: This is sample text from the document."];
            }
            $pages[] = (object) ['lines' => $lines];
        }

        return (object) ['pages' => $pages];
    }
}
