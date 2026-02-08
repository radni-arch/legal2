<?php

namespace Tests\Unit\Services;

use App\Services\LogViewerService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogViewerServiceFilterTest extends TestCase
{
    private LogViewerService $service;

    private string $testLogFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LogViewerService();
        $this->testLogFile = storage_path('logs/test-filter.log');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testLogFile)) {
            File::delete($this->testLogFile);
        }
        parent::tearDown();
    }

    private function writeTestLog(string $content): void
    {
        File::put($this->testLogFile, $content);
    }

    private function buildLogLines(): string
    {
        return implode("\n", [
            '[2026-01-31 05:06:54] production.ERROR: Database connection failed',
            '[2026-01-31 05:07:10] production.WARNING: Slow query detected',
            '[2026-01-31 05:07:30] production.INFO: User logged in',
            '[2026-01-31 05:08:00] production.ERROR: Redis timeout',
            '[2026-01-31 05:08:15] production.DEBUG: Cache miss for key user:123',
            '[2026-01-31 05:09:00] production.WARNING: Memory usage high',
            '[2026-01-31 05:09:30] production.ERROR: Queue worker died',
        ]);
    }

    /** @test */
    public function test_filter_by_level_returns_only_error_entries(): void
    {
        $this->writeTestLog($this->buildLogLines());

        $results = $this->service->filterByLevel($this->testLogFile, 'ERROR');

        $this->assertCount(3, $results);
        foreach ($results as $entry) {
            $this->assertEquals('laravel', $entry['type']);
            $this->assertEquals('ERROR', $entry['parsed']['level']);
        }
    }

    /** @test */
    public function test_filter_by_level_returns_warning_entries(): void
    {
        $this->writeTestLog($this->buildLogLines());

        $results = $this->service->filterByLevel($this->testLogFile, 'WARNING');

        $this->assertCount(2, $results);
        foreach ($results as $entry) {
            $this->assertEquals('WARNING', $entry['parsed']['level']);
        }
    }

    /** @test */
    public function test_filter_by_level_is_case_insensitive(): void
    {
        $this->writeTestLog($this->buildLogLines());

        $upper = $this->service->filterByLevel($this->testLogFile, 'ERROR');
        $lower = $this->service->filterByLevel($this->testLogFile, 'error');
        $mixed = $this->service->filterByLevel($this->testLogFile, 'Error');

        $this->assertCount(3, $upper);
        $this->assertCount(3, $lower);
        $this->assertCount(3, $mixed);
    }

    /** @test */
    public function test_filter_by_level_respects_limit(): void
    {
        $this->writeTestLog($this->buildLogLines());

        $results = $this->service->filterByLevel($this->testLogFile, 'ERROR', 2);

        // Should return last 2 (most recent) ERROR entries
        $this->assertCount(2, $results);
        $this->assertStringContainsString('Redis timeout', $results[0]['parsed']['message']);
        $this->assertStringContainsString('Queue worker died', $results[1]['parsed']['message']);
    }

    /** @test */
    public function test_filter_by_level_returns_empty_for_no_matches(): void
    {
        $this->writeTestLog($this->buildLogLines());

        $results = $this->service->filterByLevel($this->testLogFile, 'CRITICAL');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function test_filter_by_level_returns_empty_for_missing_file(): void
    {
        $results = $this->service->filterByLevel('/nonexistent/path/missing.log', 'ERROR');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function test_filter_by_multiple_levels(): void
    {
        $this->writeTestLog($this->buildLogLines());

        $results = $this->service->filterByLevels($this->testLogFile, ['ERROR', 'WARNING']);

        $this->assertCount(5, $results);
        foreach ($results as $entry) {
            $this->assertContains($entry['parsed']['level'], ['ERROR', 'WARNING']);
        }
    }
}
