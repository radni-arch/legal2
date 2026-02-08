<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LogFileControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiToken = Str::random(60);
        $this->user = User::factory()->create([
            'api_token' => $this->apiToken,
        ]);
    }

    private function apiHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->apiToken];
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
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_get_entries_requires_auth(): void
    {
        $response = $this->getJson('/api/logs/entries');
        $response->assertStatus(401);
    }

    public function test_get_entries_returns_entries_from_default_file(): void
    {
        // Ensure laravel.log exists
        $logPath = storage_path('logs/laravel.log');
        if (! File::exists($logPath)) {
            File::put($logPath, "[2026-01-31 05:06:54] testing.INFO: Test entry\n");
        }

        $response = $this->getJson('/api/logs/entries?lines=10', $this->apiHeaders());

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => ['entries', 'total_returned', 'file', 'filter'],
            ]);
        $this->assertEquals('laravel.log', $response->json('data.file'));
    }

    public function test_get_entries_filters_by_level(): void
    {
        $tempLog = storage_path('logs/test-ctrl-filter.log');
        File::put($tempLog, implode("\n", [
            '[2026-01-31 05:06:54] production.ERROR: Test error message',
            '[2026-01-31 05:06:55] production.WARNING: Test warning message',
            '[2026-01-31 05:06:56] production.INFO: Test info message',
        ]));

        try {
            $response = $this->getJson('/api/logs/entries?level=ERROR&file=test-ctrl-filter.log', $this->apiHeaders());

            $response->assertStatus(200);
            $data = $response->json('data');
            $this->assertGreaterThanOrEqual(1, $data['total_returned']);

            foreach ($data['entries'] as $entry) {
                if (isset($entry['parsed']['level'])) {
                    $this->assertEquals('ERROR', $entry['parsed']['level']);
                }
            }
        } finally {
            File::delete($tempLog);
        }
    }

    public function test_get_entries_filters_by_multiple_levels(): void
    {
        $tempLog = storage_path('logs/test-ctrl-multi.log');
        File::put($tempLog, implode("\n", [
            '[2026-01-31 05:06:54] production.ERROR: Test error',
            '[2026-01-31 05:06:55] production.WARNING: Test warning',
            '[2026-01-31 05:06:56] production.DEBUG: Test debug',
            '[2026-01-31 05:06:57] production.INFO: Test info',
        ]));

        try {
            $response = $this->getJson('/api/logs/entries?level=ERROR,WARNING&file=test-ctrl-multi.log', $this->apiHeaders());

            $response->assertStatus(200);
            $data = $response->json('data');
            $this->assertEquals(2, $data['total_returned']);
        } finally {
            File::delete($tempLog);
        }
    }

    public function test_get_entries_validates_lines_max(): void
    {
        $response = $this->getJson('/api/logs/entries?lines=9999', $this->apiHeaders());
        // ApiResponse::validationError returns 400
        $response->assertStatus(400);
    }

    public function test_get_entries_rejects_path_traversal_in_file(): void
    {
        $response = $this->getJson('/api/logs/entries?file=../../etc/passwd', $this->apiHeaders());
        // ApiResponse::validationError returns 400
        $response->assertStatus(400);
    }

    public function test_get_entries_returns_404_for_nonexistent_file(): void
    {
        $response = $this->getJson('/api/logs/entries?file=nonexistent.log', $this->apiHeaders());
        $response->assertStatus(404);
    }
}
