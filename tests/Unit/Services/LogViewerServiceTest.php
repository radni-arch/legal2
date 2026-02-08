<?php

namespace Tests\Unit\Services;

use App\Services\LogViewerService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogViewerServiceTest extends TestCase
{
    private LogViewerService $service;

    private string $testLogDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LogViewerService();
        $this->testLogDir = storage_path('logs/test-logs');

        // Ensure test directory exists
        if (! File::isDirectory($this->testLogDir)) {
            File::makeDirectory($this->testLogDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test logs
        if (File::isDirectory($this->testLogDir)) {
            File::deleteDirectory($this->testLogDir);
        }
        parent::tearDown();
    }

    // ========================================
    // tail() Method Tests
    // ========================================

    /** @test */
    public function tail_returns_empty_array_for_nonexistent_file(): void
    {
        $result = $this->service->tail('/nonexistent/path/file.log');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function tail_returns_empty_array_for_empty_file(): void
    {
        $filepath = $this->testLogDir.'/empty.log';
        File::put($filepath, '');

        $result = $this->service->tail($filepath);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function tail_returns_last_n_lines_from_file(): void
    {
        $filepath = $this->testLogDir.'/test.log';
        $lines = [];
        for ($i = 1; $i <= 100; $i++) {
            $lines[] = "Line $i";
        }
        File::put($filepath, implode("\n", $lines));

        $result = $this->service->tail($filepath, 10);

        $this->assertCount(10, $result);
        $this->assertStringContainsString('Line 91', $result[0]);
        $this->assertStringContainsString('Line 100', $result[9]);
    }

    /** @test */
    public function tail_returns_all_lines_when_file_has_fewer_than_requested(): void
    {
        $filepath = $this->testLogDir.'/small.log';
        File::put($filepath, "Line 1\nLine 2\nLine 3");

        $result = $this->service->tail($filepath, 100);

        $this->assertCount(3, $result);
    }

    /**
     * @test
     *
     * This test verifies that tail() has safeguards against very large files
     * with minimal newlines (e.g., binary or single-line JSON log files).
     * Without a max buffer limit, such files could cause execution timeouts.
     */
    public function tail_handles_file_with_very_long_lines_efficiently(): void
    {
        $filepath = $this->testLogDir.'/long-lines.log';

        // Create a file with very long lines (10KB each) - mimics problematic log format
        $longLine = str_repeat('x', 10000);
        $content = $longLine."\n".$longLine."\n".$longLine;
        File::put($filepath, $content);

        $startTime = microtime(true);
        $result = $this->service->tail($filepath, 200);
        $elapsed = microtime(true) - $startTime;

        // Should complete in under 1 second, not timeout at 30 seconds
        $this->assertLessThan(1.0, $elapsed, 'tail() should handle long lines efficiently');
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    /**
     * @test
     *
     * Test that tail() doesn't hang when processing a large file looking for more
     * lines than exist. The $lineCount variable bug would cause infinite loop attempts.
     */
    public function tail_completes_quickly_when_requesting_more_lines_than_exist(): void
    {
        $filepath = $this->testLogDir.'/medium.log';

        // Create file with 50 lines
        $lines = [];
        for ($i = 1; $i <= 50; $i++) {
            $lines[] = str_repeat('a', 100)." - Line $i";
        }
        File::put($filepath, implode("\n", $lines));

        $startTime = microtime(true);
        $result = $this->service->tail($filepath, 500); // Request 500, only 50 exist
        $elapsed = microtime(true) - $startTime;

        // Should complete quickly
        $this->assertLessThan(1.0, $elapsed, 'tail() should not hang looking for more lines');
        $this->assertCount(50, $result);
    }

    /**
     * @test
     *
     * Regression test: Ensure tail() respects a maximum buffer size to prevent
     * memory exhaustion on extremely large log files.
     */
    public function tail_respects_max_buffer_size_on_large_single_line_content(): void
    {
        $filepath = $this->testLogDir.'/huge-line.log';

        // Create a 5MB single-line file (no newlines until end)
        // This would cause the old implementation to buffer the entire file
        $hugeContent = str_repeat('x', 5 * 1024 * 1024);
        File::put($filepath, $hugeContent."\nLast line");

        $startTime = microtime(true);
        $result = $this->service->tail($filepath, 10);
        $elapsed = microtime(true) - $startTime;

        // Should complete in reasonable time, not timeout
        $this->assertLessThan(5.0, $elapsed, 'tail() should not timeout on large single-line files');
        $this->assertIsArray($result);
        // Should return at least the last line and potentially truncated content
        $this->assertGreaterThanOrEqual(1, count($result));
    }

    // ========================================
    // parseLine() Method Tests
    // ========================================

    /** @test */
    public function parseLine_returns_empty_type_for_empty_string(): void
    {
        $result = $this->service->parseLine('');

        $this->assertEquals('empty', $result['type']);
        $this->assertNull($result['parsed']);
    }

    /** @test */
    public function parseLine_parses_json_format(): void
    {
        $json = '{"level":"INFO","message":"Test"}';
        $result = $this->service->parseLine($json);

        $this->assertEquals('json', $result['type']);
        $this->assertEquals(['level' => 'INFO', 'message' => 'Test'], $result['parsed']);
    }

    /** @test */
    public function parseLine_parses_laravel_log_format(): void
    {
        $line = '[2025-01-15 10:30:00] production.ERROR: Something failed';
        $result = $this->service->parseLine($line);

        $this->assertEquals('laravel', $result['type']);
        $this->assertEquals('2025-01-15 10:30:00', $result['parsed']['datetime']);
        $this->assertEquals('production', $result['parsed']['environment']);
        $this->assertEquals('ERROR', $result['parsed']['level']);
        $this->assertEquals('Something failed', $result['parsed']['message']);
    }

    /** @test */
    public function parseLine_returns_plain_type_for_unknown_format(): void
    {
        $line = 'Some random text that is not a log';
        $result = $this->service->parseLine($line);

        $this->assertEquals('plain', $result['type']);
        $this->assertNull($result['parsed']);
        $this->assertEquals($line, $result['raw']);
    }

    // ========================================
    // formatSize() Method Tests
    // ========================================

    /** @test */
    public function formatSize_formats_bytes_correctly(): void
    {
        $this->assertEquals('100 B', $this->service->formatSize(100));
        $this->assertEquals('1 KB', $this->service->formatSize(1024));
        $this->assertEquals('1.5 KB', $this->service->formatSize(1536));
        $this->assertEquals('1 MB', $this->service->formatSize(1024 * 1024));
        $this->assertEquals('1 GB', $this->service->formatSize(1024 * 1024 * 1024));
    }
}
