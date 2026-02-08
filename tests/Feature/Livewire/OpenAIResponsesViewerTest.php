<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\OpenAIResponsesViewer;
use App\Services\OpenAIService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class OpenAIResponsesViewerTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test 1: Component mounts with default date range
     *
     * @test
     */
    public function test_it_initializes_with_default_date_range()
    {
        $this->mock(OpenAIService::class, function ($mock) {
            $mock->shouldReceive('getResponses')->andReturn(['data' => []]);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);

        // Should default to last 7 days
        $this->assertNotNull($component->get('from'));
        $this->assertNotNull($component->get('to'));
        $this->assertEquals(20, $component->get('limit'));
        $this->assertEquals('desc', $component->get('order'));
    }

    /**
     * Test 2: Displays cached OpenAI responses
     *
     * @test
     */
    public function test_it_displays_cached_responses()
    {
        $mockData = [
            'data' => [
                [
                    'id' => 'resp-123',
                    'model' => 'gpt-4',
                    'created' => 1704902400,
                    'message' => ['input_text' => 'Test prompt'],
                    'output_text' => 'Test response',
                ],
            ],
        ];

        $this->mock(OpenAIService::class, function ($mock) use ($mockData) {
            $mock->shouldReceive('getResponses')->andReturn($mockData);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);

        $items = $component->get('items');
        $this->assertCount(1, $items);
        $this->assertEquals('resp-123', $items[0]['id']);
        $this->assertEquals('gpt-4', $items[0]['model']);
    }

    /**
     * Test 3: Filters responses by date range
     *
     * @test
     */
    public function test_it_filters_by_date_range()
    {
        $this->mock(OpenAIService::class, function ($mock) {
            $mock->shouldReceive('getResponses')
                ->with(Mockery::on(function ($query) {
                    return isset($query['created_after']) && isset($query['created_before']);
                }), Mockery::any())
                ->andReturn(['data' => []]);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);
        $component->set('from', '2025-01-01');
        $component->set('to', '2025-01-10');

        $this->assertNotNull($component->get('items'));
    }

    /**
     * Test 4: Validates date range (from must be before to)
     *
     * @test
     */
    public function test_it_validates_date_range()
    {
        $this->mock(OpenAIService::class, function ($mock) {
            $mock->shouldReceive('getResponses')->andReturn(['data' => []]);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);
        $component->set('from', '2025-01-10');
        $component->set('to', '2025-01-01'); // To is before from

        $this->assertEquals('Invalid range: from date is after to date.', $component->get('error'));
    }

    /**
     * Test 5: Searches responses by text
     *
     * @test
     */
    public function test_it_searches_responses()
    {
        $mockData = [
            'data' => [
                [
                    'id' => 'resp-1',
                    'model' => 'gpt-4',
                    'created' => 1704902400,
                    'message' => ['input_text' => 'Legal document analysis'],
                    'output_text' => 'Analysis result',
                ],
                [
                    'id' => 'resp-2',
                    'model' => 'gpt-4',
                    'created' => 1704902500,
                    'message' => ['input_text' => 'Code review request'],
                    'output_text' => 'Code looks good',
                ],
            ],
        ];

        $this->mock(OpenAIService::class, function ($mock) use ($mockData) {
            $mock->shouldReceive('getResponses')->andReturn($mockData);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);
        $component->set('search', 'legal');

        $items = $component->get('items');
        $this->assertCount(1, $items);
        $this->assertEquals('resp-1', $items[0]['id']);
    }

    /**
     * Test 6: Paginates responses with limit
     *
     * @test
     */
    public function test_it_paginates_responses()
    {
        $this->mock(OpenAIService::class, function ($mock) {
            $mock->shouldReceive('getResponses')
                ->with(Mockery::on(function ($query) {
                    return $query['limit'] === 50;
                }), Mockery::any())
                ->andReturn(['data' => []]);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);
        $component->set('limit', 50);

        $this->assertNotNull($component->get('items'));
    }

    /**
     * Test 7: Orders responses by creation date
     *
     * @test
     */
    public function test_it_orders_responses()
    {
        $this->mock(OpenAIService::class, function ($mock) {
            $mock->shouldReceive('getResponses')
                ->with(Mockery::on(function ($query) {
                    return $query['order'] === 'asc';
                }), Mockery::any())
                ->andReturn(['data' => []]);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);
        $component->set('order', 'asc');

        $this->assertNotNull($component->get('items'));
    }

    /**
     * Test 8: Refreshes response data on demand
     *
     * @test
     */
    public function test_it_refreshes_responses()
    {
        $callCount = 0;
        $this->mock(OpenAIService::class, function ($mock) use (&$callCount) {
            $mock->shouldReceive('getResponses')->andReturnUsing(function () use (&$callCount) {
                $callCount++;

                return ['data' => []];
            });
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);
        $initialCount = $callCount;

        $component->call('refreshNow');

        $this->assertGreaterThan($initialCount, $callCount);
    }

    /**
     * Test 9: Handles API errors gracefully
     *
     * @test
     */
    public function test_it_handles_api_errors()
    {
        $this->mock(OpenAIService::class, function ($mock) {
            $mock->shouldReceive('getResponses')
                ->andThrow(new \Exception('API connection failed'));
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);

        $this->assertStringContainsString('Failed to load OpenAI responses', $component->get('error'));
    }

    /**
     * Test 10: Clips long text content
     *
     * @test
     */
    public function test_it_clips_long_text()
    {
        $longText = str_repeat('a', 5000);
        $mockData = [
            'data' => [
                [
                    'id' => 'resp-123',
                    'model' => 'gpt-4',
                    'created' => 1704902400,
                    'message' => ['input_text' => $longText],
                    'output_text' => $longText,
                ],
            ],
        ];

        $this->mock(OpenAIService::class, function ($mock) use ($mockData) {
            $mock->shouldReceive('getResponses')->andReturn($mockData);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);

        $items = $component->get('items');
        $this->assertCount(1, $items);
        // Input should be clipped to 2000 chars
        $this->assertLessThanOrEqual(2000, mb_strlen($items[0]['input_text']));
        // Output should be clipped to 4000 chars
        $this->assertLessThanOrEqual(4000, mb_strlen($items[0]['output_text']));
        $this->assertStringEndsWith('…', $items[0]['input_text']);
    }

    /**
     * Test 11: Performance - handles large response sets efficiently
     *
     * @test
     */
    public function test_it_handles_large_response_sets()
    {
        // Generate 100 mock responses
        $largeDataset = [];
        for ($i = 0; $i < 100; $i++) {
            $largeDataset[] = [
                'id' => "resp-{$i}",
                'model' => $i % 2 === 0 ? 'gpt-4' : 'gpt-3.5-turbo',
                'created' => 1704902400 + ($i * 10),
                'message' => ['input_text' => "Prompt {$i}"],
                'output_text' => "Response {$i}",
            ];
        }

        $this->mock(OpenAIService::class, function ($mock) use ($largeDataset) {
            $mock->shouldReceive('getResponses')->andReturn(['data' => $largeDataset]);
        });

        $startTime = microtime(true);
        $component = Livewire::test(OpenAIResponsesViewer::class);
        $endTime = microtime(true);

        $items = $component->get('items');

        // Should handle 100 items efficiently (< 3 seconds)
        $this->assertLessThan(3, $endTime - $startTime);
        $this->assertCount(100, $items);
        $this->assertEquals('resp-0', $items[0]['id']);
    }

    /**
     * Test 12: Performance - handles very large text content
     *
     * @test
     */
    public function test_it_handles_very_large_text_content()
    {
        $veryLongInput = str_repeat('A', 50000); // 50KB input
        $veryLongOutput = str_repeat('B', 100000); // 100KB output

        $mockData = [
            'data' => [
                [
                    'id' => 'resp-large',
                    'model' => 'gpt-4',
                    'created' => 1704902400,
                    'message' => ['input_text' => $veryLongInput],
                    'output_text' => $veryLongOutput,
                ],
            ],
        ];

        $this->mock(OpenAIService::class, function ($mock) use ($mockData) {
            $mock->shouldReceive('getResponses')->andReturn($mockData);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);

        $items = $component->get('items');
        $this->assertCount(1, $items);

        // Should clip large text efficiently
        $this->assertLessThanOrEqual(2000, mb_strlen($items[0]['input_text']));
        $this->assertLessThanOrEqual(4000, mb_strlen($items[0]['output_text']));

        // Memory should not explode - if we get here without timeout, it passed
        $this->assertTrue(true);
    }

    /**
     * Test 13: Performance - filters large datasets efficiently
     *
     * @test
     */
    public function test_it_filters_large_datasets_efficiently()
    {
        $largeDataset = [];
        for ($i = 0; $i < 200; $i++) {
            $largeDataset[] = [
                'id' => "resp-{$i}",
                'model' => 'gpt-4',
                'created' => 1704902400 + ($i * 10),
                'message' => ['input_text' => $i < 10 ? "Target query {$i}" : "Normal query {$i}"],
                'output_text' => "Response {$i}",
            ];
        }

        $this->mock(OpenAIService::class, function ($mock) use ($largeDataset) {
            $mock->shouldReceive('getResponses')->andReturn(['data' => $largeDataset]);
        });

        $startTime = microtime(true);
        $component = Livewire::test(OpenAIResponsesViewer::class);
        $component->set('search', 'Target'); // Filter to only 10 items
        $endTime = microtime(true);

        $items = $component->get('items');

        // Filtering should be fast (< 2 seconds)
        $this->assertLessThan(2, $endTime - $startTime);
        $this->assertEquals(10, count($items));

        // All results should contain "Target"
        foreach ($items as $item) {
            $this->assertStringContainsString('Target', $item['input_text']);
        }
    }

    /**
     * Test 14: Performance - handles rapid date range changes
     *
     * @test
     */
    public function test_it_handles_rapid_filter_changes()
    {
        $this->mock(OpenAIService::class, function ($mock) {
            $mock->shouldReceive('getResponses')->andReturn(['data' => []]);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);

        // Simulate rapid filter changes
        $startTime = microtime(true);
        for ($i = 1; $i <= 10; $i++) {
            $component->set('from', "2025-01-0{$i}");
            $component->set('to', '2025-01-'.($i + 1));
        }
        $endTime = microtime(true);

        // Should handle 10 filter changes in < 2 seconds
        $this->assertLessThan(2, $endTime - $startTime);
        $this->assertNotNull($component->get('items'));
    }

    /**
     * Test 15: Performance - memory efficient with large response objects
     *
     * @test
     */
    public function test_it_is_memory_efficient_with_large_responses()
    {
        $largeDataset = [];
        for ($i = 0; $i < 50; $i++) {
            $largeDataset[] = [
                'id' => "resp-{$i}",
                'model' => 'gpt-4',
                'created' => 1704902400 + ($i * 100),
                'message' => [
                    'input_text' => str_repeat("Word{$i} ", 1000), // ~6KB each
                ],
                'output_text' => str_repeat("Output{$i} ", 2000), // ~14KB each
                'raw_data' => array_fill(0, 100, "Extra data {$i}"), // Additional bulk
            ];
        }

        $this->mock(OpenAIService::class, function ($mock) use ($largeDataset) {
            $mock->shouldReceive('getResponses')->andReturn(['data' => $largeDataset]);
        });

        $memoryBefore = memory_get_usage(true);
        $component = Livewire::test(OpenAIResponsesViewer::class);
        $items = $component->get('items');
        $memoryAfter = memory_get_usage(true);

        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

        $this->assertCount(50, $items);

        // Should not use excessive memory (< 50MB for 50 large items)
        $this->assertLessThan(50, $memoryUsed);

        // Verify clipping is working
        foreach ($items as $item) {
            $this->assertLessThanOrEqual(2000, mb_strlen($item['input_text']));
            $this->assertLessThanOrEqual(4000, mb_strlen($item['output_text']));
        }
    }

    /**
     * Test 16: Performance - handles concurrent operations
     *
     * @test
     */
    public function test_it_handles_concurrent_operations()
    {
        $mockData = ['data' => array_map(fn ($i) => [
            'id' => "resp-{$i}",
            'model' => 'gpt-4',
            'created' => 1704902400,
            'message' => ['input_text' => "Prompt {$i}"],
            'output_text' => "Response {$i}",
        ], range(1, 30))];

        $this->mock(OpenAIService::class, function ($mock) use ($mockData) {
            $mock->shouldReceive('getResponses')->andReturn($mockData);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);

        // Perform multiple operations
        $component->set('search', 'Prompt');
        $component->set('limit', 20);
        $component->set('order', 'asc');
        $component->call('refreshNow');

        $items = $component->get('items');

        // Should handle all operations without errors
        $this->assertNotEmpty($items);
        $this->assertNull($component->get('error'));
    }

    /**
     * Test 17: Performance - pagination with large result sets
     *
     * @test
     */
    public function test_it_paginates_large_result_sets_efficiently()
    {
        $largeDataset = array_map(fn ($i) => [
            'id' => "resp-{$i}",
            'model' => 'gpt-4',
            'created' => 1704902400 + $i,
            'message' => ['input_text' => "Query {$i}"],
            'output_text' => "Result {$i}",
        ], range(1, 500));

        $this->mock(OpenAIService::class, function ($mock) use ($largeDataset) {
            $mock->shouldReceive('getResponses')
                ->with(Mockery::on(function ($query) {
                    return $query['limit'] === 100;
                }), Mockery::any())
                ->andReturn(['data' => array_slice($largeDataset, 0, 100)]);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);
        $component->set('limit', 100);

        $items = $component->get('items');

        // Should respect limit and handle large sets
        $this->assertCount(100, $items);
        $this->assertEquals('resp-1', $items[0]['id']);
    }

    /**
     * Test 18: Performance - search with complex patterns
     *
     * @test
     */
    public function test_it_searches_complex_patterns_efficiently()
    {
        $complexData = [];
        for ($i = 0; $i < 100; $i++) {
            $complexData[] = [
                'id' => "resp-{$i}",
                'model' => $i % 3 === 0 ? 'gpt-4' : 'gpt-3.5-turbo',
                'created' => 1704902400,
                'message' => [
                    'input_text' => json_encode([
                        'query' => "Complex query {$i}",
                        'metadata' => ['user' => "user{$i}@example.com", 'tags' => ["tag{$i}", 'common']],
                    ]),
                ],
                'output_text' => "Complex response with JSON data {$i}",
            ];
        }

        $this->mock(OpenAIService::class, function ($mock) use ($complexData) {
            $mock->shouldReceive('getResponses')->andReturn(['data' => $complexData]);
        });

        $startTime = microtime(true);
        $component = Livewire::test(OpenAIResponsesViewer::class);
        $component->set('search', 'user0@example.com'); // Search in complex JSON
        $endTime = microtime(true);

        $items = $component->get('items');

        // Complex search should still be fast (< 1 second)
        $this->assertLessThan(1, $endTime - $startTime);
        $this->assertGreaterThanOrEqual(1, count($items));
    }

    /**
     * Test 19: Performance - handles empty results efficiently
     *
     * @test
     */
    public function test_it_handles_empty_results_efficiently()
    {
        $this->mock(OpenAIService::class, function ($mock) {
            $mock->shouldReceive('getResponses')->andReturn(['data' => []]);
        });

        $startTime = microtime(true);
        $component = Livewire::test(OpenAIResponsesViewer::class);
        $endTime = microtime(true);

        $items = $component->get('items');

        // Empty results should be instant (< 0.5 seconds)
        $this->assertLessThan(0.5, $endTime - $startTime);
        $this->assertEmpty($items);
        $this->assertNull($component->get('error'));
    }

    /**
     * Test 20: Performance - handles malformed data gracefully
     *
     * @test
     */
    public function test_it_handles_malformed_data_gracefully()
    {
        $malformedData = [
            'data' => [
                ['id' => 'resp-1', 'model' => 'gpt-4'], // Missing required fields
                ['created' => 1704902400, 'output_text' => 'Test'], // Missing id and model
                ['id' => 'resp-3', 'model' => 'gpt-4', 'created' => 1704902400, 'message' => ['input_text' => 'Valid']],
                null, // Completely invalid
                ['id' => 'resp-5'], // Minimal data
            ],
        ];

        $this->mock(OpenAIService::class, function ($mock) use ($malformedData) {
            $mock->shouldReceive('getResponses')->andReturn($malformedData);
        });

        $component = Livewire::test(OpenAIResponsesViewer::class);

        $items = $component->get('items');

        // Should handle malformed data without crashing
        $this->assertIsArray($items);
        $this->assertNull($component->get('error'));

        // Valid entries should be processed
        $validItems = array_filter($items, fn ($item) => ! empty($item['id']));
        $this->assertGreaterThanOrEqual(1, count($validItems));
    }
}
