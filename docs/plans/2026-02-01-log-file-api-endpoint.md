# Log File API Endpoint Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create an API endpoint that provides log file event listing from `laravel.log`, filterable by level (ERROR, WARNING, DEBUG, etc.), so AI agents can consume recent errors and autonomously debug/fix issues.

**Architecture:** Extend the existing `LogViewerService` with a `filterByLevel()` method that uses efficient tail + grep-style filtering. Create a new `LogFileController` in the Api namespace with endpoints for listing log entries and available log files. Protect with `api.token` middleware and rate limiting.

**Tech Stack:** Laravel 11, existing `LogViewerService`, `ApiResponse`, `api.token` middleware

---

### Task 1: Add `filterByLevel` method to LogViewerService

**Files:**
- Modify: `app/Services/LogViewerService.php`
- Test: `tests/Unit/Services/LogViewerServiceFilterTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\LogViewerService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogViewerServiceFilterTest extends TestCase
{
    private LogViewerService $service;
    private string $testLogPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LogViewerService();
        $this->testLogPath = storage_path('logs/test-filter.log');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testLogPath)) {
            File::delete($this->testLogPath);
        }
        parent::tearDown();
    }

    private function createTestLog(string $content): void
    {
        File::put($this->testLogPath, $content);
    }

    public function test_filter_by_level_returns_only_error_entries(): void
    {
        $this->createTestLog(implode("\n", [
            '[2026-01-31 05:06:54] production.ERROR: Pusher error: connection failed',
            '[2026-01-31 05:06:55] production.WARNING: Circuit breaker recorded failure',
            '[2026-01-31 05:06:56] production.ERROR: VectorStoreManager failed',
            '[2026-01-31 05:06:57] production.INFO: Request completed',
        ]));

        $results = $this->service->filterByLevel($this->testLogPath, 'ERROR', 100);

        $this->assertCount(2, $results);
        $this->assertStringContainsString('Pusher error', $results[0]['raw']);
        $this->assertStringContainsString('VectorStoreManager', $results[1]['raw']);
        $this->assertEquals('ERROR', $results[0]['parsed']['level']);
    }

    public function test_filter_by_level_returns_warning_entries(): void
    {
        $this->createTestLog(implode("\n", [
            '[2026-01-31 05:06:54] production.ERROR: Some error',
            '[2026-01-31 05:06:55] production.WARNING: Some warning',
            '[2026-01-31 05:06:56] production.DEBUG: Debug info',
        ]));

        $results = $this->service->filterByLevel($this->testLogPath, 'WARNING', 100);

        $this->assertCount(1, $results);
        $this->assertEquals('WARNING', $results[0]['parsed']['level']);
    }

    public function test_filter_by_level_is_case_insensitive(): void
    {
        $this->createTestLog(implode("\n", [
            '[2026-01-31 05:06:54] production.ERROR: Some error',
            '[2026-01-31 05:06:55] production.WARNING: Some warning',
        ]));

        $results = $this->service->filterByLevel($this->testLogPath, 'error', 100);

        $this->assertCount(1, $results);
        $this->assertEquals('ERROR', $results[0]['parsed']['level']);
    }

    public function test_filter_by_level_respects_limit(): void
    {
        $lines = [];
        for ($i = 0; $i < 50; $i++) {
            $lines[] = "[2026-01-31 05:{$i}:00] production.ERROR: Error number {$i}";
        }
        $this->createTestLog(implode("\n", $lines));

        $results = $this->service->filterByLevel($this->testLogPath, 'ERROR', 10);

        $this->assertCount(10, $results);
        // Should return the LAST 10 (most recent)
        $this->assertStringContainsString('Error number 49', $results[9]['raw']);
    }

    public function test_filter_by_level_returns_empty_for_no_matches(): void
    {
        $this->createTestLog('[2026-01-31 05:06:54] production.INFO: All good');

        $results = $this->service->filterByLevel($this->testLogPath, 'ERROR', 100);

        $this->assertCount(0, $results);
    }

    public function test_filter_by_level_returns_empty_for_missing_file(): void
    {
        $results = $this->service->filterByLevel('/nonexistent/file.log', 'ERROR', 100);

        $this->assertCount(0, $results);
    }

    public function test_filter_by_multiple_levels(): void
    {
        $this->createTestLog(implode("\n", [
            '[2026-01-31 05:06:54] production.ERROR: Some error',
            '[2026-01-31 05:06:55] production.WARNING: Some warning',
            '[2026-01-31 05:06:56] production.DEBUG: Debug info',
            '[2026-01-31 05:06:57] production.INFO: Info message',
        ]));

        $results = $this->service->filterByLevels($this->testLogPath, ['ERROR', 'WARNING'], 100);

        $this->assertCount(2, $results);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh LogViewerServiceFilterTest`
Expected: FAIL - method `filterByLevel` does not exist

**Step 3: Write minimal implementation**

Add to `app/Services/LogViewerService.php`:

```php
/**
 * Filter log entries by level (ERROR, WARNING, DEBUG, etc.)
 * Reads from end of file for efficiency (most recent entries first).
 *
 * @param string $filepath Full path to log file
 * @param string $level Log level to filter (case-insensitive)
 * @param int $limit Max entries to return
 * @return array<int, array{raw: string, parsed: array|null, type: string}>
 */
public function filterByLevel(string $filepath, string $level, int $limit = 50): array
{
    $level = strtoupper($level);
    // Read a large chunk from end to find enough matching entries
    $tailLines = $this->tail($filepath, $limit * 20);
    $matches = [];

    foreach ($tailLines as $line) {
        $parsed = $this->parseLine($line);
        if ($parsed['type'] === 'laravel'
            && isset($parsed['parsed']['level'])
            && strtoupper($parsed['parsed']['level']) === $level
        ) {
            $matches[] = $parsed;
        }
    }

    // Return last $limit entries (most recent)
    return array_slice($matches, -$limit);
}

/**
 * Filter log entries by multiple levels.
 *
 * @param string $filepath Full path to log file
 * @param array<string> $levels Log levels to filter (case-insensitive)
 * @param int $limit Max entries to return
 * @return array<int, array{raw: string, parsed: array|null, type: string}>
 */
public function filterByLevels(string $filepath, array $levels, int $limit = 50): array
{
    $levels = array_map('strtoupper', $levels);
    $tailLines = $this->tail($filepath, $limit * 20);
    $matches = [];

    foreach ($tailLines as $line) {
        $parsed = $this->parseLine($line);
        if ($parsed['type'] === 'laravel'
            && isset($parsed['parsed']['level'])
            && in_array(strtoupper($parsed['parsed']['level']), $levels, true)
        ) {
            $matches[] = $parsed;
        }
    }

    return array_slice($matches, -$limit);
}
```

**Step 4: Run test to verify it passes**

Run: `./scripts/run-focused-tests.sh LogViewerServiceFilterTest`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Unit/Services/LogViewerServiceFilterTest.php app/Services/LogViewerService.php
git commit -m "feat: add filterByLevel and filterByLevels to LogViewerService"
```

---

### Task 2: Create LogFileController

**Files:**
- Create: `app/Http/Controllers/Api/LogFileController.php`
- Test: `tests/Feature/Api/LogFileControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogFileControllerTest extends TestCase
{
    private User $user;
    private string $testLogPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['api_token' => 'test-token-logfile']);
        $this->testLogPath = storage_path('logs/laravel.log');
    }

    private function apiHeaders(): array
    {
        return ['Authorization' => 'Bearer test-token-logfile'];
    }

    public function test_list_log_files_requires_auth(): void
    {
        $response = $this->getJson('/api/logs/files');
        $response->assertStatus(401);
    }

    public function test_list_log_files_returns_files(): void
    {
        $response = $this->getJson('/api/logs/files', $this->apiHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['name', 'size', 'modified'],
                ],
            ]);
    }

    public function test_get_entries_returns_recent_entries(): void
    {
        // Ensure there's at least some content in laravel.log
        $response = $this->getJson('/api/logs/entries?lines=10', $this->apiHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'entries',
                    'total_returned',
                    'file',
                ],
            ]);
    }

    public function test_get_entries_filters_by_level(): void
    {
        // Write test content to a temp log
        $tempLog = storage_path('logs/test-api.log');
        File::put($tempLog, implode("\n", [
            '[2026-01-31 05:06:54] production.ERROR: Test error message',
            '[2026-01-31 05:06:55] production.WARNING: Test warning message',
            '[2026-01-31 05:06:56] production.INFO: Test info message',
        ]));

        $response = $this->getJson('/api/logs/entries?level=ERROR&file=test-api.log', $this->apiHeaders());

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['total_returned']);

        // All entries should be ERROR level
        foreach ($data['entries'] as $entry) {
            if ($entry['parsed']) {
                $this->assertEquals('ERROR', $entry['parsed']['level']);
            }
        }

        File::delete($tempLog);
    }

    public function test_get_entries_filters_by_multiple_levels(): void
    {
        $tempLog = storage_path('logs/test-api-multi.log');
        File::put($tempLog, implode("\n", [
            '[2026-01-31 05:06:54] production.ERROR: Test error',
            '[2026-01-31 05:06:55] production.WARNING: Test warning',
            '[2026-01-31 05:06:56] production.DEBUG: Test debug',
            '[2026-01-31 05:06:57] production.INFO: Test info',
        ]));

        $response = $this->getJson('/api/logs/entries?level=ERROR,WARNING&file=test-api-multi.log', $this->apiHeaders());

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(2, $data['total_returned']);

        File::delete($tempLog);
    }

    public function test_get_entries_validates_lines_parameter(): void
    {
        $response = $this->getJson('/api/logs/entries?lines=99999', $this->apiHeaders());

        $response->assertStatus(422);
    }

    public function test_get_entries_rejects_path_traversal(): void
    {
        $response = $this->getJson('/api/logs/entries?file=../../etc/passwd', $this->apiHeaders());

        $response->assertStatus(422);
    }

    public function test_get_entries_rejects_nonexistent_file(): void
    {
        $response = $this->getJson('/api/logs/entries?file=nonexistent.log', $this->apiHeaders());

        $response->assertStatus(404);
    }

    public function test_get_entries_default_is_laravel_log(): void
    {
        $response = $this->getJson('/api/logs/entries', $this->apiHeaders());

        $response->assertStatus(200);
        $this->assertEquals('laravel.log', $response->json('data.file'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh LogFileControllerTest`
Expected: FAIL - route not found / controller missing

**Step 3: Write the controller**

Create `app/Http/Controllers/Api/LogFileController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ErrorCode;
use App\Services\LogViewerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LogFileController extends Controller
{
    public function __construct(
        protected LogViewerService $logViewer
    ) {}

    /**
     * List available log files
     *
     * GET /api/logs/files
     */
    public function files(): JsonResponse
    {
        $files = $this->logViewer->getLogFiles();

        $data = array_values(array_map(fn ($f) => [
            'name' => $f['name'],
            'size' => $this->logViewer->formatSize($f['size']),
            'size_bytes' => $f['size'],
            'modified' => date('Y-m-d H:i:s', $f['modified']),
        ], $files));

        return ApiResponse::success($data, 'Log files retrieved');
    }

    /**
     * Get log entries with optional level filtering
     *
     * GET /api/logs/entries?level=ERROR&lines=100&file=laravel.log
     */
    public function entries(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_\-\.]+\.log$/'],
            'level' => ['nullable', 'string', 'max:100'],
            'lines' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('Invalid parameters', $validator->errors());
        }

        $filename = $request->input('file', 'laravel.log');
        $level = $request->input('level');
        $lines = (int) $request->input('lines', 100);

        $filepath = storage_path('logs/' . $filename);

        // Security: verify file is within logs directory
        if (!File::exists($filepath)) {
            return ApiResponse::notFound("Log file '{$filename}' not found");
        }

        $realPath = realpath($filepath);
        $logsDir = realpath(storage_path('logs'));
        if (!$realPath || !str_starts_with($realPath, $logsDir)) {
            return ApiResponse::notFound("Log file '{$filename}' not found");
        }

        if ($level) {
            $levels = array_map('trim', explode(',', $level));
            if (count($levels) === 1) {
                $entries = $this->logViewer->filterByLevel($filepath, $levels[0], $lines);
            } else {
                $entries = $this->logViewer->filterByLevels($filepath, $levels, $lines);
            }
        } else {
            $rawLines = $this->logViewer->tail($filepath, $lines);
            $entries = array_map(fn ($line) => $this->logViewer->parseLine($line), $rawLines);
        }

        return ApiResponse::success([
            'entries' => $entries,
            'total_returned' => count($entries),
            'file' => $filename,
            'filter' => $level,
        ], 'Log entries retrieved');
    }
}
```

**Step 4: Register routes (Task 3 below), then run tests**

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/LogFileController.php tests/Feature/Api/LogFileControllerTest.php
git commit -m "feat: add LogFileController with entries and files endpoints"
```

---

### Task 3: Register API Routes

**Files:**
- Modify: `routes/api.php`

**Step 1: Add the route group**

Add to end of `routes/api.php` (before honeypot routes):

```php
/*
|--------------------------------------------------------------------------
| Log File API Routes
|--------------------------------------------------------------------------
|
| Log file viewing endpoints for agent-driven error debugging.
| Allows AI agents to consume recent errors from laravel.log.
|
*/

Route::prefix('logs')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    Route::get('/files', [\App\Http\Controllers\Api\LogFileController::class, 'files']);
    Route::get('/entries', [\App\Http\Controllers\Api\LogFileController::class, 'entries']);
});
```

**Step 2: Run tests to verify**

Run: `./scripts/run-focused-tests.sh LogFileControllerTest`
Expected: PASS

**Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat: register log file API routes"
```

---

### Task 4: Run all tests and verify

**Step 1: Run unit tests**

Run: `./scripts/run-focused-tests.sh LogViewerServiceFilterTest`
Expected: All pass

**Step 2: Run feature tests**

Run: `./scripts/run-focused-tests.sh LogFileControllerTest`
Expected: All pass

**Step 3: Commit final state and push**

```bash
git push -u origin claude/log-file-api-endpoint-8GHTa
```

---

## API Usage Examples

### List log files
```bash
curl -H "Authorization: Bearer <token>" https://host/api/logs/files
```

### Get recent errors
```bash
curl -H "Authorization: Bearer <token>" "https://host/api/logs/entries?level=ERROR&lines=50"
```

### Get errors and warnings
```bash
curl -H "Authorization: Bearer <token>" "https://host/api/logs/entries?level=ERROR,WARNING&lines=100"
```

### Get raw tail (no filter)
```bash
curl -H "Authorization: Bearer <token>" "https://host/api/logs/entries?lines=200"
```

### Read specific log file
```bash
curl -H "Authorization: Bearer <token>" "https://host/api/logs/entries?file=laravel-2026-01-31.log&level=ERROR"
```
