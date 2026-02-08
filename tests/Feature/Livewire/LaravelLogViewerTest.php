<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LaravelLogViewer;
use App\Services\LogViewerService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive Test Suite for LaravelLogViewer Component
 *
 * Tests all functionality:
 * - Component mounting and initialization
 * - Log file loading and selection
 * - Log entry filtering by level (error, warning, info, etc.)
 * - Search functionality
 * - Log refresh functionality
 * - Download and delete operations
 * - Entry parsing and display
 * - Auto-refresh toggle
 * - Filter clearing
 */
class LaravelLogViewerTest extends TestCase
{
    use UsesTestDatabase;

    // ========================================
    // Component Mounting & Initialization Tests
    // ========================================

    /** @test */
    public function it_can_mount_and_loads_log_files()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->once()
            ->andReturn([
                'laravel.log' => [
                    'name' => 'laravel.log',
                    'path' => '/var/www/storage/logs/laravel.log',
                    'size' => 1024,
                    'modified' => now()->timestamp,
                ],
            ]);

        $mockService->shouldReceive('readPage')
            ->once()
            ->andReturn([
                'lines' => ['[2025-11-09 10:00:00] local.INFO: Test log entry'],
                'totalLines' => 1,
                'totalPages' => 1,
            ]);

        $mockService->shouldReceive('parseLine')
            ->once()
            ->andReturn([
                'type' => 'laravel',
                'raw' => '[2025-11-09 10:00:00] local.INFO: Test log entry',
                'parsed' => [
                    'datetime' => '2025-11-09 10:00:00',
                    'environment' => 'local',
                    'level' => 'INFO',
                    'message' => 'Test log entry',
                ],
            ]);

        $mockService->shouldReceive('formatSize')
            ->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        Livewire::test(LaravelLogViewer::class)
            ->assertSuccessful()
            ->assertViewIs('livewire.laravel-log-viewer')
            ->assertSet('logFiles', function ($files) {
                return count($files) === 1 && isset($files['laravel.log']);
            })
            ->assertSet('selectedFile', 'laravel.log');
    }

    /** @test */
    public function it_displays_empty_state_when_no_log_files_exist()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->once()
            ->andReturn([]);

        $this->app->instance(LogViewerService::class, $mockService);

        Livewire::test(LaravelLogViewer::class)
            ->assertSet('logFiles', [])
            ->assertSet('selectedFile', null)
            ->assertSet('entries', []);
    }

    // ========================================
    // File Selection Tests
    // ========================================

    /** @test */
    public function it_can_select_a_different_log_file()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->andReturn([
                'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
                'other.log' => ['name' => 'other.log', 'path' => '/path/other.log', 'size' => 2048, 'modified' => 456],
            ]);

        $mockService->shouldReceive('readPage')->andReturn(['lines' => [], 'totalLines' => 0, 'totalPages' => 0]);
        $mockService->shouldReceive('parseLine')->andReturn(['type' => 'empty', 'raw' => '', 'parsed' => null]);
        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        Livewire::test(LaravelLogViewer::class)
            ->call('selectFile', 'other.log')
            ->assertSet('selectedFile', 'other.log');
    }

    // ========================================
    // Filtering Tests
    // ========================================

    /** @test */
    public function it_can_filter_by_log_level()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->andReturn([
                'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
            ]);

        $mockService->shouldReceive('readPage')
            ->andReturn([
                'lines' => [
                    '[2025-11-09 10:00:00] local.ERROR: Error message',
                    '[2025-11-09 10:01:00] local.INFO: Info message',
                    '[2025-11-09 10:02:00] local.ERROR: Another error',
                ],
                'totalLines' => 3,
                'totalPages' => 1,
            ]);

        $mockService->shouldReceive('parseLine')
            ->andReturnUsing(function ($line) {
                if (str_contains($line, 'ERROR')) {
                    return [
                        'type' => 'laravel',
                        'raw' => $line,
                        'parsed' => ['datetime' => '2025-11-09 10:00:00', 'environment' => 'local', 'level' => 'ERROR', 'message' => 'Error message'],
                    ];
                }

                return [
                    'type' => 'laravel',
                    'raw' => $line,
                    'parsed' => ['datetime' => '2025-11-09 10:00:00', 'environment' => 'local', 'level' => 'INFO', 'message' => 'Info message'],
                ];
            });

        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        $component = Livewire::test(LaravelLogViewer::class)
            ->set('levelFilter', 'error');

        // Should only show error entries (2 out of 3)
        $component->assertSet('entries', function ($entries) {
            return count($entries) === 2;
        });
    }

    /** @test */
    public function it_can_search_log_messages()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->andReturn([
                'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
            ]);

        $mockService->shouldReceive('readPage')
            ->andReturn([
                'lines' => [
                    '[2025-11-09 10:00:00] local.INFO: Database query executed',
                    '[2025-11-09 10:01:00] local.INFO: User logged in',
                    '[2025-11-09 10:02:00] local.INFO: Database connection failed',
                ],
                'totalLines' => 3,
                'totalPages' => 1,
            ]);

        $mockService->shouldReceive('parseLine')
            ->andReturnUsing(function ($line) {
                preg_match('/local\.(\w+): (.+)/', $line, $matches);

                return [
                    'type' => 'laravel',
                    'raw' => $line,
                    'parsed' => ['datetime' => '2025-11-09 10:00:00', 'environment' => 'local', 'level' => strtoupper($matches[1] ?? 'INFO'), 'message' => $matches[2] ?? ''],
                ];
            });

        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        $component = Livewire::test(LaravelLogViewer::class)
            ->set('search', 'database');

        // Should only show entries containing "database" (2 out of 3)
        $component->assertSet('entries', function ($entries) {
            return count($entries) === 2;
        });
    }

    /** @test */
    public function it_can_clear_filters()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->andReturn([
                'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
            ]);

        $mockService->shouldReceive('readPage')->andReturn(['lines' => [], 'totalLines' => 0, 'totalPages' => 0]);
        $mockService->shouldReceive('parseLine')->andReturn(['type' => 'empty', 'raw' => '', 'parsed' => null]);
        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        Livewire::test(LaravelLogViewer::class)
            ->set('search', 'test query')
            ->set('levelFilter', 'error')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('levelFilter', 'all');
    }

    // ========================================
    // Refresh Tests
    // ========================================

    /** @test */
    public function it_can_refresh_logs_manually()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->times(2) // Once on mount, once on refresh
            ->andReturn([
                'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
            ]);

        $mockService->shouldReceive('readPage')->andReturn(['lines' => [], 'totalLines' => 0, 'totalPages' => 0]);
        $mockService->shouldReceive('parseLine')->andReturn(['type' => 'empty', 'raw' => '', 'parsed' => null]);
        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        Livewire::test(LaravelLogViewer::class)
            ->call('refreshNow')
            ->assertSuccessful();
    }

    /** @test */
    public function it_can_toggle_auto_refresh()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')->andReturn([]);

        $this->app->instance(LogViewerService::class, $mockService);

        Livewire::test(LaravelLogViewer::class)
            ->assertSet('autoRefresh', false)
            ->set('autoRefresh', true)
            ->assertSet('autoRefresh', true);
    }

    // ========================================
    // File Operations Tests
    // ========================================

    /** @test */
    public function it_can_delete_a_log_file()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->twice()
            ->andReturn(
                // First call (mount)
                [
                    'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
                    'old.log' => ['name' => 'old.log', 'path' => '/path/old.log', 'size' => 512, 'modified' => 100],
                ],
                // Second call (after delete)
                [
                    'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
                ]
            );

        $mockService->shouldReceive('deleteLogFile')
            ->with('old.log')
            ->once()
            ->andReturn(true);

        $mockService->shouldReceive('readPage')->andReturn(['lines' => [], 'totalLines' => 0, 'totalPages' => 0]);
        $mockService->shouldReceive('parseLine')->andReturn(['type' => 'empty', 'raw' => '', 'parsed' => null]);
        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        Livewire::test(LaravelLogViewer::class)
            ->call('deleteFile', 'old.log')
            ->assertSet('logFiles', function ($files) {
                return count($files) === 1 && ! isset($files['old.log']);
            });
    }

    /** @test */
    public function it_switches_to_another_file_when_deleting_selected_file()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->twice()
            ->andReturn(
                // First call
                [
                    'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
                    'selected.log' => ['name' => 'selected.log', 'path' => '/path/selected.log', 'size' => 512, 'modified' => 100],
                ],
                // After delete
                [
                    'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
                ]
            );

        $mockService->shouldReceive('deleteLogFile')
            ->with('selected.log')
            ->once()
            ->andReturn(true);

        $mockService->shouldReceive('readPage')->andReturn(['lines' => [], 'totalLines' => 0, 'totalPages' => 0]);
        $mockService->shouldReceive('parseLine')->andReturn(['type' => 'empty', 'raw' => '', 'parsed' => null]);
        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        Livewire::test(LaravelLogViewer::class)
            ->set('selectedFile', 'selected.log')
            ->call('deleteFile', 'selected.log')
            ->assertSet('selectedFile', 'laravel.log'); // Should switch to remaining file
    }

    /** @test */
    public function it_can_download_a_log_file()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->andReturn([
                'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
            ]);

        $mockService->shouldReceive('getLogContent')
            ->with('laravel.log')
            ->once()
            ->andReturn('Log file content here');

        $mockService->shouldReceive('readPage')->andReturn(['lines' => [], 'totalLines' => 0, 'totalPages' => 0]);
        $mockService->shouldReceive('parseLine')->andReturn(['type' => 'empty', 'raw' => '', 'parsed' => null]);
        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        $response = Livewire::test(LaravelLogViewer::class)
            ->call('downloadFile', 'laravel.log')
            ->assertSuccessful();

        // The response should be a download
        $this->assertNotNull($response);
    }

    // ========================================
    // Entry Parsing Tests
    // ========================================

    /** @test */
    public function it_parses_and_displays_log_entries()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->andReturn([
                'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
            ]);

        $mockService->shouldReceive('readPage')
            ->once()
            ->andReturn([
                'lines' => ['[2025-11-09 10:00:00] local.INFO: Test message'],
                'totalLines' => 1,
                'totalPages' => 1,
            ]);

        $mockService->shouldReceive('parseLine')
            ->with('[2025-11-09 10:00:00] local.INFO: Test message')
            ->once()
            ->andReturn([
                'type' => 'laravel',
                'raw' => '[2025-11-09 10:00:00] local.INFO: Test message',
                'parsed' => [
                    'datetime' => '2025-11-09 10:00:00',
                    'environment' => 'local',
                    'level' => 'INFO',
                    'message' => 'Test message',
                ],
            ]);

        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        $component = Livewire::test(LaravelLogViewer::class);

        $component->assertSet('entries', function ($entries) {
            return count($entries) === 1
                && $entries[0]['type'] === 'laravel'
                && $entries[0]['level'] === 'info';
        });
    }

    /** @test */
    public function it_respects_per_page_setting()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->andReturn([
                'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
            ]);

        $mockService->shouldReceive('readPage')->andReturn(['lines' => [], 'totalLines' => 0, 'totalPages' => 0]);
        $mockService->shouldReceive('parseLine')->andReturn(['type' => 'empty', 'raw' => '', 'parsed' => null]);
        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        Livewire::test(LaravelLogViewer::class)
            ->assertSet('perPage', 50)
            ->set('perPage', 100)
            ->assertSet('perPage', 100)
            ->assertSet('currentPage', 1);
    }

    /** @test */
    public function it_skips_empty_log_lines()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->andReturn([
                'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
            ]);

        $mockService->shouldReceive('readPage')
            ->andReturn([
                'lines' => [
                    '[2025-11-09 10:00:00] local.INFO: Valid entry',
                    '',
                    '   ',
                    '[2025-11-09 10:01:00] local.INFO: Another valid entry',
                ],
                'totalLines' => 4,
                'totalPages' => 1,
            ]);

        $mockService->shouldReceive('parseLine')
            ->andReturnUsing(function ($line) {
                if (trim($line) === '') {
                    return ['type' => 'empty', 'raw' => $line, 'parsed' => null];
                }

                return [
                    'type' => 'laravel',
                    'raw' => $line,
                    'parsed' => ['datetime' => '2025-11-09 10:00:00', 'environment' => 'local', 'level' => 'INFO', 'message' => 'Valid entry'],
                ];
            });

        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        $component = Livewire::test(LaravelLogViewer::class);

        // Should only have 2 entries (empty lines skipped)
        $component->assertSet('entries', function ($entries) {
            return count($entries) === 2;
        });
    }

    /** @test */
    public function it_displays_entries_in_reverse_order_latest_first()
    {
        $mockService = Mockery::mock(LogViewerService::class);
        $mockService->shouldReceive('getLogFiles')
            ->andReturn([
                'laravel.log' => ['name' => 'laravel.log', 'path' => '/path/laravel.log', 'size' => 1024, 'modified' => 123],
            ]);

        $mockService->shouldReceive('readPage')
            ->andReturn([
                'lines' => [
                    '[2025-11-09 10:00:00] local.INFO: First entry',
                    '[2025-11-09 10:01:00] local.INFO: Second entry',
                    '[2025-11-09 10:02:00] local.INFO: Third entry',
                ],
                'totalLines' => 3,
                'totalPages' => 1,
            ]);

        $mockService->shouldReceive('parseLine')
            ->andReturnUsing(function ($line) {
                return [
                    'type' => 'laravel',
                    'raw' => $line,
                    'parsed' => ['datetime' => '2025-11-09 10:00:00', 'environment' => 'local', 'level' => 'INFO', 'message' => $line],
                ];
            });

        $mockService->shouldReceive('formatSize')->andReturn('1.00 KB');

        $this->app->instance(LogViewerService::class, $mockService);

        $component = Livewire::test(LaravelLogViewer::class);

        $component->assertSet('entries', function ($entries) {
            // Latest should be first
            return str_contains($entries[0]['raw'], 'Third entry')
                && str_contains($entries[2]['raw'], 'First entry');
        });
    }
}
