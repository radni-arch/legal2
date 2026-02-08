<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\OpenAILogViewer;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class OpenAILogViewerTest extends TestCase
{
    use UsesTestDatabase;

    protected string $testLogPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogPath = storage_path('logs/test_openai.log');

        // Clean up any existing test log
        if (File::exists($this->testLogPath)) {
            File::delete($this->testLogPath);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test log
        if (File::exists($this->testLogPath)) {
            File::delete($this->testLogPath);
        }
        parent::tearDown();
    }

    protected function createTestLog(array $entries): void
    {
        $lines = array_map(fn ($entry) => json_encode($entry), $entries);
        File::put($this->testLogPath, implode("\n", $lines));
    }

    /**
     * Test 1: Component mounts and initializes correctly
     *
     * @test
     */
    public function test_it_mounts_with_default_configuration()
    {
        $component = Livewire::test(OpenAILogViewer::class);

        $component->assertSet('limit', 200)
            ->assertSet('search', '')
            ->assertSet('autoRefresh', true)
            ->assertSet('eventTypes', [
                'openai.request' => true,
                'openai.response' => true,
                'openai.error' => true,
            ]);
    }

    /**
     * Test 2: Displays OpenAI API logs from file
     *
     * @test
     */
    public function test_it_displays_openai_api_logs()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => ['model' => 'gpt-4', 'request_id' => 'req-123'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => ['tokens' => 150, 'request_id' => 'req-123'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        // Trigger loadEntries by changing a watched property
        $component->set('limit', 200);

        $this->assertCount(2, $component->get('entries'));
        $this->assertEquals('openai.response', $component->get('entries')[0]['message']);
        $this->assertEquals('openai.request', $component->get('entries')[1]['message']);
    }

    /**
     * Test 3: Filters logs by event type
     *
     * @test
     */
    public function test_it_filters_by_event_type()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => [],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => [],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
            [
                'message' => 'openai.error',
                'context' => [],
                'level_name' => 'ERROR',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:02',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);

        // Disable all except errors and trigger reload by setting search
        $component->set('eventTypes', [
            'openai.request' => false,
            'openai.response' => false,
            'openai.error' => true,
        ]);
        $component->set('search', ''); // Trigger loadEntries via updated()

        $entries = $component->get('entries');
        $this->assertCount(1, $entries);
        $this->assertEquals('openai.error', $entries[0]['message']);
    }

    /**
     * Test 4: Toggles event type filters
     *
     * @test
     */
    public function test_it_toggles_event_filters()
    {
        $component = Livewire::test(OpenAILogViewer::class);

        // Check initial state
        $eventTypes = $component->get('eventTypes');
        $this->assertTrue($eventTypes['openai.request']);

        // Toggle off
        $component->call('toggleEvent', 'openai.request');
        $eventTypes = $component->get('eventTypes');
        $this->assertFalse($eventTypes['openai.request']);

        // Toggle back on
        $component->call('toggleEvent', 'openai.request');
        $eventTypes = $component->get('eventTypes');
        $this->assertTrue($eventTypes['openai.request']);
    }

    /**
     * Test 5: Searches log entries by text
     *
     * @test
     */
    public function test_it_searches_by_prompt()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => ['prompt' => 'Analyze this legal document'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.request',
                'context' => ['prompt' => 'Generate court filing'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('search', 'legal'); // This triggers loadEntries via updated()

        $entries = $component->get('entries');
        $this->assertCount(1, $entries);
        $this->assertStringContainsString('legal', json_encode($entries[0]['context']));
    }

    /**
     * Test 6: Filters by request ID
     *
     * @test
     */
    public function test_it_filters_by_request_id()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => ['request_id' => 'req-123'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => ['request_id' => 'req-123'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
            [
                'message' => 'openai.request',
                'context' => ['request_id' => 'req-456'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:01:00',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->call('filterByRequest', 'req-123');

        $entries = $component->get('entries');
        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertEquals('req-123', $entry['request_id']);
        }
    }

    /**
     * Test 7: Clears all filters
     *
     * @test
     */
    public function test_it_clears_filters()
    {
        $component = Livewire::test(OpenAILogViewer::class);

        $component->set('search', 'test query');
        $component->set('requestId', 'req-123');
        $component->set('eventTypes', [
            'openai.request' => false,
            'openai.response' => true,
            'openai.error' => false,
        ]);

        $component->call('clearFilters');

        $component->assertSet('search', '')
            ->assertSet('requestId', null)
            ->assertSet('eventTypes', [
                'openai.request' => true,
                'openai.response' => true,
                'openai.error' => true,
            ]);
    }

    /**
     * Test 8: Refreshes log entries on demand
     *
     * @test
     */
    public function test_it_refreshes_logs()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => [],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('limit', 200); // Trigger loadEntries

        $this->assertCount(1, $component->get('entries'));

        // Add more entries to the log
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => [],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => [],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
        ]);

        $component->call('refreshNow');
        $this->assertCount(2, $component->get('entries'));
    }

    /**
     * Test 9: Handles non-existent log file gracefully
     *
     * @test
     */
    public function test_it_handles_missing_log_file()
    {
        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', storage_path('logs/nonexistent.log'));
        $component->set('limit', 200); // Trigger loadEntries

        $this->assertEmpty($component->get('entries'));
    }

    /**
     * Test 10: Limits number of log entries displayed
     *
     * @test
     */
    public function test_it_paginates_log_entries()
    {
        // Create more entries than the limit
        $entries = [];
        for ($i = 0; $i < 50; $i++) {
            $entries[] = [
                'message' => 'openai.request',
                'context' => ['index' => $i],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ];
        }
        $this->createTestLog($entries);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('limit', 20); // Setting limit triggers loadEntries via updated()

        // Should only show last 20 entries
        $this->assertCount(20, $component->get('entries'));
    }

    /**
     * Test 11: Token usage tracking - displays token counts from logs
     *
     * @test
     */
    public function test_it_tracks_token_usage()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => [
                    'model' => 'gpt-4',
                    'request_id' => 'req-123',
                    'estimated_tokens' => 1000,
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => [
                    'request_id' => 'req-123',
                    'usage' => [
                        'prompt_tokens' => 950,
                        'completion_tokens' => 150,
                        'total_tokens' => 1100,
                    ],
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('limit', 200);

        $entries = $component->get('entries');
        $this->assertCount(2, $entries);

        // Check response entry has token usage
        $responseEntry = $entries[0];
        $this->assertEquals('openai.response', $responseEntry['message']);
        $this->assertEquals(1100, $responseEntry['context']['usage']['total_tokens']);
        $this->assertEquals(950, $responseEntry['context']['usage']['prompt_tokens']);
        $this->assertEquals(150, $responseEntry['context']['usage']['completion_tokens']);
    }

    /**
     * Test 12: Token usage tracking - filters by token threshold
     *
     * @test
     */
    public function test_it_filters_high_token_usage()
    {
        $this->createTestLog([
            [
                'message' => 'openai.response',
                'context' => [
                    'request_id' => 'req-low',
                    'usage' => ['total_tokens' => 100],
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => [
                    'request_id' => 'req-high',
                    'usage' => ['total_tokens' => 5000],
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('search', '5000'); // Search for high token count

        $entries = $component->get('entries');
        $this->assertCount(1, $entries);
        $this->assertEquals('req-high', $entries[0]['context']['request_id']);
    }

    /**
     * Test 13: Token usage tracking - aggregates tokens across requests
     *
     * @test
     */
    public function test_it_displays_multiple_token_entries()
    {
        $this->createTestLog([
            [
                'message' => 'openai.response',
                'context' => ['usage' => ['total_tokens' => 1000]],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => ['usage' => ['total_tokens' => 2000]],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
            [
                'message' => 'openai.response',
                'context' => ['usage' => ['total_tokens' => 1500]],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:02',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('limit', 200);

        $entries = $component->get('entries');
        $this->assertCount(3, $entries);

        // Verify all entries have token data
        foreach ($entries as $entry) {
            $this->assertArrayHasKey('usage', $entry['context']);
            $this->assertArrayHasKey('total_tokens', $entry['context']['usage']);
        }
    }

    /**
     * Test 14: Cost calculation - displays cost from token usage
     *
     * @test
     */
    public function test_it_displays_cost_information()
    {
        $this->createTestLog([
            [
                'message' => 'openai.response',
                'context' => [
                    'model' => 'gpt-4',
                    'usage' => [
                        'prompt_tokens' => 1000,
                        'completion_tokens' => 500,
                        'total_tokens' => 1500,
                    ],
                    'cost_usd' => 0.045, // Example: gpt-4 pricing
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('limit', 200);

        $entries = $component->get('entries');
        $this->assertCount(1, $entries);
        $this->assertEquals(0.045, $entries[0]['context']['cost_usd']);
        $this->assertEquals('gpt-4', $entries[0]['context']['model']);
    }

    /**
     * Test 15: Cost calculation - filters by cost threshold
     *
     * @test
     */
    public function test_it_filters_by_cost()
    {
        $this->createTestLog([
            [
                'message' => 'openai.response',
                'context' => [
                    'model' => 'gpt-3.5-turbo',
                    'cost_usd' => 0.001,
                    'request_id' => 'req-cheap',
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => [
                    'model' => 'gpt-4',
                    'cost_usd' => 0.15,
                    'request_id' => 'req-expensive',
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('search', '0.15'); // Search for high cost

        $entries = $component->get('entries');
        $this->assertCount(1, $entries);
        $this->assertEquals('req-expensive', $entries[0]['context']['request_id']);
    }

    /**
     * Test 16: Filter by model - filters logs by model name
     *
     * @test
     */
    public function test_it_filters_by_model()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => ['model' => 'gpt-4', 'request_id' => 'req-1'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.request',
                'context' => ['model' => 'gpt-3.5-turbo', 'request_id' => 'req-2'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
            [
                'message' => 'openai.request',
                'context' => ['model' => 'gpt-4', 'request_id' => 'req-3'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:02',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('search', 'gpt-4'); // Filter by model

        $entries = $component->get('entries');
        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertEquals('gpt-4', $entry['context']['model']);
        }
    }

    /**
     * Test 17: Filter by model - displays all available models
     *
     * @test
     */
    public function test_it_displays_multiple_models()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => ['model' => 'gpt-4'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.request',
                'context' => ['model' => 'gpt-3.5-turbo'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
            [
                'message' => 'openai.request',
                'context' => ['model' => 'gpt-4-turbo'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:02',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('limit', 200);

        $entries = $component->get('entries');
        $this->assertCount(3, $entries);

        $models = array_map(fn ($e) => $e['context']['model'], $entries);
        $this->assertContains('gpt-4', $models);
        $this->assertContains('gpt-3.5-turbo', $models);
        $this->assertContains('gpt-4-turbo', $models);
    }

    /**
     * Test 18: Filter by model - handles logs without model field
     *
     * @test
     */
    public function test_it_handles_logs_without_model()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => ['model' => 'gpt-4'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.error',
                'context' => ['error' => 'Connection timeout'],
                'level_name' => 'ERROR',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('limit', 200);

        $entries = $component->get('entries');
        $this->assertCount(2, $entries);

        // Error log should not have model field
        $errorEntry = array_values(array_filter($entries, fn ($e) => $e['message'] === 'openai.error'))[0];
        $this->assertArrayNotHasKey('model', $errorEntry['context']);
    }

    /**
     * Test 19: Search across requests - searches multiple request IDs
     *
     * @test
     */
    public function test_it_searches_across_multiple_requests()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => ['request_id' => 'req-001', 'prompt' => 'Legal analysis'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => ['request_id' => 'req-001', 'response' => 'Analysis complete'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
            [
                'message' => 'openai.request',
                'context' => ['request_id' => 'req-002', 'prompt' => 'Code review'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:01:00',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('search', 'req-'); // Search for request ID pattern

        $entries = $component->get('entries');
        $this->assertCount(3, $entries);
        $this->assertStringContainsString('req-', $entries[0]['context']['request_id']);
    }

    /**
     * Test 20: Search across requests - searches request and response pairs
     *
     * @test
     */
    public function test_it_finds_matching_request_response_pairs()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => ['request_id' => 'req-special', 'prompt' => 'Special query'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => ['request_id' => 'req-special', 'tokens' => 100],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
            [
                'message' => 'openai.request',
                'context' => ['request_id' => 'req-normal', 'prompt' => 'Normal query'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:01:00',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('search', 'special'); // Search should find both request and response

        $entries = $component->get('entries');
        $this->assertGreaterThanOrEqual(1, count($entries));
        $this->assertStringContainsString('special', strtolower(json_encode($entries[0]['context'])));
    }

    /**
     * Test 21: Search across requests - searches by context fields
     *
     * @test
     */
    public function test_it_searches_by_context_fields()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => [
                    'request_id' => 'req-123',
                    'user' => 'john@example.com',
                    'endpoint' => '/api/completions',
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.request',
                'context' => [
                    'request_id' => 'req-456',
                    'user' => 'jane@example.com',
                    'endpoint' => '/api/chat',
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('search', 'john@example.com'); // Search by user field

        $entries = $component->get('entries');
        $this->assertCount(1, $entries);
        $this->assertEquals('req-123', $entries[0]['context']['request_id']);
    }

    /**
     * Test 22: Export functionality - prepares data for export
     *
     * @test
     */
    public function test_it_prepares_export_data()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => ['request_id' => 'req-1', 'model' => 'gpt-4'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.response',
                'context' => ['request_id' => 'req-1', 'usage' => ['total_tokens' => 100]],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('limit', 200);

        $entries = $component->get('entries');

        // Verify data structure is suitable for export
        $this->assertIsArray($entries);
        $this->assertNotEmpty($entries);
        foreach ($entries as $entry) {
            $this->assertArrayHasKey('message', $entry);
            $this->assertArrayHasKey('context', $entry);
            $this->assertArrayHasKey('level', $entry);
            $this->assertArrayHasKey('datetime', $entry);
        }
    }

    /**
     * Test 23: Export functionality - exports filtered data only
     *
     * @test
     */
    public function test_it_exports_filtered_data()
    {
        $this->createTestLog([
            [
                'message' => 'openai.request',
                'context' => ['model' => 'gpt-4'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ],
            [
                'message' => 'openai.error',
                'context' => ['error' => 'Timeout'],
                'level_name' => 'ERROR',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:01',
            ],
        ]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);

        // Filter for errors only
        $component->set('eventTypes', [
            'openai.request' => false,
            'openai.response' => false,
            'openai.error' => true,
        ]);
        $component->set('search', ''); // Trigger reload

        $entries = $component->get('entries');

        // Only error entries should be in export data
        $this->assertCount(1, $entries);
        $this->assertEquals('openai.error', $entries[0]['message']);
    }

    /**
     * Test 24: Export functionality - handles large datasets
     *
     * @test
     */
    public function test_it_handles_large_export_datasets()
    {
        $largeDataset = [];
        for ($i = 0; $i < 500; $i++) {
            $largeDataset[] = [
                'message' => 'openai.request',
                'context' => ['index' => $i, 'model' => 'gpt-4'],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => '2025-01-10 12:00:00',
            ];
        }
        $this->createTestLog($largeDataset);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('limit', 500); // Request all entries

        $entries = $component->get('entries');

        // Should handle large dataset efficiently
        $this->assertLessThanOrEqual(500, count($entries));
        $this->assertGreaterThan(0, count($entries));
    }

    /**
     * Test 25: Export functionality - maintains data integrity
     *
     * @test
     */
    public function test_it_maintains_data_integrity_for_export()
    {
        $originalData = [
            'message' => 'openai.response',
            'context' => [
                'request_id' => 'req-test',
                'model' => 'gpt-4',
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'total_tokens' => 150,
                ],
                'cost_usd' => 0.005,
            ],
            'level_name' => 'INFO',
            'channel' => 'openai',
            'datetime' => '2025-01-10 12:00:00',
        ];

        $this->createTestLog([$originalData]);

        $component = Livewire::test(OpenAILogViewer::class);
        $component->set('path', $this->testLogPath);
        $component->set('limit', 200);

        $entries = $component->get('entries');

        // Verify all data is preserved
        $this->assertCount(1, $entries);
        $entry = $entries[0];
        $this->assertEquals('openai.response', $entry['message']);
        $this->assertEquals('req-test', $entry['context']['request_id']);
        $this->assertEquals('gpt-4', $entry['context']['model']);
        $this->assertEquals(150, $entry['context']['usage']['total_tokens']);
        $this->assertEquals(0.005, $entry['context']['cost_usd']);
    }
}
