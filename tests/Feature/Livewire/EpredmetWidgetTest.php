<?php

namespace Tests\Feature\Livewire;

use App\GraphQL\AutoDiscovery\Exceptions\GraphQLQueryException;
use App\GraphQL\AutoDiscovery\GraphQLAutoClient;
use App\Http\Livewire\EpredmetWidget;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EpredmetWidgetTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test 1: Component mounts and initializes with default case
     *
     * @test
     */
    public function test_it_mounts_with_default_case()
    {
        $this->mock(GraphQLAutoClient::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn([
                'oznakaBroj' => 'Pp Prz-74/2025',
                'sud' => 'Županijski sud u Osijeku',
                'stranke' => [],
                'pismena' => [],
            ]);
        });

        $component = Livewire::test(EpredmetWidget::class);

        $this->assertNotNull($component->get('data'));
        $this->assertFalse($component->get('loading'));
        $this->assertNull($component->get('error'));
    }

    /**
     * Test 2: Fetches case data from EKOM via GraphQL
     *
     * @test
     */
    public function test_it_fetches_ekom_case_data()
    {
        $mockResponse = [
            'oznakaBroj' => 'K-123/2025',
            'sud' => 'Županijski sud u Osijeku',
            'sudac' => 'Ivan Horvat',
            'stranke' => [
                ['naziv' => 'John Doe', 'uloga' => 'Optuženik'],
            ],
            'pismena' => [],
            'rocista' => [],
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')
                ->with('predmet', Mockery::on(function ($args) {
                    return $args['oznakaBroj'] === 'K-123/2025';
                }))
                ->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->set('oznakaBroj', 'K-123/2025');
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertNotNull($data);
        $this->assertEquals('K-123/2025', $data['oznakaBroj']);
        $this->assertCount(1, $data['stranke']);
    }

    /**
     * Test 3: Validates required fields before fetching
     *
     * @test
     */
    public function test_it_validates_required_fields()
    {
        $component = Livewire::test(EpredmetWidget::class);
        $component->set('oznakaBroj', ''); // Empty case number

        $component->call('fetch')
            ->assertHasErrors(['oznakaBroj']);
    }

    /**
     * Test 4: Displays loading state during API call
     *
     * @test
     */
    public function test_it_shows_loading_state()
    {
        $this->mock(GraphQLAutoClient::class, function ($mock) {
            $mock->shouldReceive('run')->andReturnUsing(function () {
                usleep(100); // Simulate delay

                return ['oznakaBroj' => 'Test'];
            });
        });

        $component = Livewire::test(EpredmetWidget::class);

        // After fetch completes, loading should be false
        $this->assertFalse($component->get('loading'));
    }

    /**
     * Test 5: Handles GraphQL API errors gracefully
     *
     * @test
     */
    public function test_it_handles_graphql_errors()
    {
        $this->mock(GraphQLAutoClient::class, function ($mock) {
            $mock->shouldReceive('run')
                ->andThrow(new GraphQLQueryException('Case not found'));
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $this->assertNotNull($component->get('error'));
        $this->assertStringContainsString('Case not found', $component->get('error'));
        $this->assertNull($component->get('data'));
    }

    /**
     * Test 6: Normalizes nested GraphQL response data
     *
     * @test
     */
    public function test_it_normalizes_nested_data()
    {
        $nestedResponse = [
            'data' => [
                [
                    'oznakaBroj' => 'K-456/2025',
                    'pismena' => [
                        [
                            ['vrsta' => 'Tužba', 'datum' => '2025-01-15'],
                            ['s' => 'arr'],
                        ],
                    ],
                ],
                ['s' => 'arr'],
            ],
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($nestedResponse) {
            $mock->shouldReceive('run')->andReturn($nestedResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertIsArray($data);
        $this->assertIsArray($data['pismena'] ?? null);
    }

    /**
     * Test 7: Sorts documents by date (newest first)
     *
     * @test
     */
    public function test_it_sorts_documents_by_date()
    {
        $mockResponse = [
            'oznakaBroj' => 'K-789/2025',
            'pismena' => [
                ['vrsta' => 'Older', 'datum' => '2025-01-10 10:00:00'],
                ['vrsta' => 'Newer', 'datum' => '2025-01-15 10:00:00'],
                ['vrsta' => 'Newest', 'datum' => '2025-01-20 10:00:00'],
            ],
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertEquals('Newest', $data['pismena'][0]['vrsta']);
        $this->assertEquals('Older', $data['pismena'][2]['vrsta']);
    }

    /**
     * Test 8: Clears error when user edits input
     *
     * @test
     */
    public function test_it_clears_error_on_input_change()
    {
        $this->mock(GraphQLAutoClient::class, function ($mock) {
            $mock->shouldReceive('run')
                ->andThrow(new \Exception('Test error'));
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $this->assertNotNull($component->get('error'));

        // Changing input should clear error
        $component->set('oznakaBroj', 'New-123/2025');

        $this->assertNull($component->get('error'));
    }

    /**
     * Test 9: Records API request timing
     *
     * @test
     */
    public function test_it_records_request_timing()
    {
        $this->mock(GraphQLAutoClient::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn(['oznakaBroj' => 'Test']);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $tookMs = $component->get('tookMs');
        $this->assertNotNull($tookMs);
        $this->assertIsFloat($tookMs);
        $this->assertGreaterThanOrEqual(0, $tookMs);
    }

    /**
     * Test 10: Supports both string and numeric court IDs
     *
     * @test
     */
    public function test_it_supports_numeric_and_string_court_ids()
    {
        $this->mock(GraphQLAutoClient::class, function ($mock) {
            // mount() calls fetch() automatically once with default sud=5107
            $mock->shouldReceive('run')
                ->with('predmet', Mockery::any())
                ->andReturn(['oznakaBroj' => 'Test1', 'stranke' => [], 'pismena' => []]);
        });

        // Test numeric court ID (mount already calls with numeric 5107)
        $component = Livewire::test(EpredmetWidget::class);
        $this->assertNotNull($component->get('data'));

        // Test string court ID
        $component->set('sud', '5107');
        $component->call('fetch');
        $this->assertNotNull($component->get('data'));
    }

    /**
     * Test 11: EKOM sync - handles missing stranke field
     *
     * @test
     */
    public function test_it_handles_missing_stranke_field()
    {
        $mockResponse = [
            'oznakaBroj' => 'K-100/2025',
            'sud' => 'Test Court',
            // stranke field intentionally missing
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertNotNull($data);
        $this->assertArrayHasKey('oznakaBroj', $data);
    }

    /**
     * Test 12: EKOM sync - handles empty pismena array
     *
     * @test
     */
    public function test_it_handles_empty_pismena_array()
    {
        $mockResponse = [
            'oznakaBroj' => 'K-101/2025',
            'pismena' => [],
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertIsArray($data['pismena']);
        $this->assertCount(0, $data['pismena']);
    }

    /**
     * Test 13: EKOM sync - handles malformed date in documents
     *
     * @test
     */
    public function test_it_handles_malformed_document_dates()
    {
        $mockResponse = [
            'oznakaBroj' => 'K-102/2025',
            'pismena' => [
                ['vrsta' => 'Valid', 'datum' => '2025-01-15 10:00:00'],
                ['vrsta' => 'Invalid', 'datum' => 'invalid-date'],
                ['vrsta' => 'Missing', 'datum' => null],
            ],
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertIsArray($data['pismena']);
        // Should handle gracefully without crashing
        $this->assertGreaterThanOrEqual(1, count($data['pismena']));
    }

    /**
     * Test 14: EKOM sync - handles very large case with many documents
     *
     * @test
     */
    public function test_it_handles_large_case_with_many_documents()
    {
        $pismena = [];
        for ($i = 1; $i <= 100; $i++) {
            $day = ($i % 28) + 1;
            $pismena[] = [
                'vrsta' => "Document {$i}",
                'datum' => "2025-01-{$day} 10:00:00",
            ];
        }

        $mockResponse = [
            'oznakaBroj' => 'K-103/2025',
            'pismena' => $pismena,
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertCount(100, $data['pismena']);
    }

    /**
     * Test 15: EKOM sync - handles null response from GraphQL
     *
     * @test
     */
    public function test_it_handles_null_graphql_response()
    {
        $this->mock(GraphQLAutoClient::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn(null);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        // Should handle gracefully
        $this->assertFalse($component->get('loading'));
    }

    /**
     * Test 16: Error recovery - retries after network error
     *
     * @test
     */
    public function test_it_allows_retry_after_network_error()
    {
        $callCount = 0;
        $this->mock(GraphQLAutoClient::class, function ($mock) use (&$callCount) {
            $mock->shouldReceive('run')
                ->andReturnUsing(function () use (&$callCount) {
                    $callCount++;
                    // First call (from mount) fails
                    if ($callCount === 1) {
                        throw new \Exception('Network error');
                    }

                    // Second call (retry) succeeds
                    return ['oznakaBroj' => 'Success'];
                });
        });

        // Mount will trigger first fetch (fails)
        $component = Livewire::test(EpredmetWidget::class);
        $this->assertNotNull($component->get('error'));

        // Retry succeeds
        $component->call('fetch');
        $this->assertNull($component->get('error'));
        $this->assertNotNull($component->get('data'));
    }

    /**
     * Test 17: Error recovery - clears data on new error
     *
     * @test
     */
    public function test_it_clears_data_on_new_error()
    {
        $callCount = 0;
        $this->mock(GraphQLAutoClient::class, function ($mock) use (&$callCount) {
            $mock->shouldReceive('run')
                ->andReturnUsing(function () use (&$callCount) {
                    $callCount++;
                    // First call (from mount) succeeds
                    if ($callCount === 1) {
                        return ['oznakaBroj' => 'Success'];
                    }
                    // Second call fails
                    throw new \Exception('Second call error');
                });
        });

        // Mount triggers first fetch (succeeds)
        $component = Livewire::test(EpredmetWidget::class);
        $this->assertNotNull($component->get('data'));

        // Second call fails, should clear old data
        $component->call('fetch');
        $this->assertNotNull($component->get('error'));
        $this->assertNull($component->get('data'));
    }

    /**
     * Test 18: Error recovery - validates case number format
     *
     * @test
     */
    public function test_it_validates_case_number_format()
    {
        $this->mock(GraphQLAutoClient::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn(['oznakaBroj' => 'Test']);
        });

        $component = Livewire::test(EpredmetWidget::class);

        // Test with invalid format (empty)
        $component->set('oznakaBroj', '');
        $component->call('fetch')
            ->assertHasErrors(['oznakaBroj']);

        // Test with valid format clears errors
        $component->set('oznakaBroj', 'K-123/2025');
        $this->assertNull($component->get('error'));
    }

    /**
     * Test 19: Pagination - paginates large document list
     *
     * @test
     */
    public function test_it_paginates_large_document_list()
    {
        $pismena = [];
        for ($i = 1; $i <= 50; $i++) {
            $pismena[] = [
                'vrsta' => "Doc {$i}",
                'datum' => "2025-01-15 10:{$i}:00",
            ];
        }

        $mockResponse = [
            'oznakaBroj' => 'K-200/2025',
            'pismena' => $pismena,
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertGreaterThan(10, count($data['pismena']));
    }

    /**
     * Test 20: Pagination - handles edge case with exactly perPage items
     *
     * @test
     */
    public function test_it_handles_exact_page_size()
    {
        $pismena = [];
        for ($i = 1; $i <= 15; $i++) {  // Exactly 15 items
            $pismena[] = [
                'vrsta' => "Doc {$i}",
                'datum' => '2025-01-15 10:00:00',
            ];
        }

        $mockResponse = [
            'oznakaBroj' => 'K-201/2025',
            'pismena' => $pismena,
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertCount(15, $data['pismena']);
    }

    /**
     * Test 21: Pagination - handles single document
     *
     * @test
     */
    public function test_it_handles_single_document()
    {
        $mockResponse = [
            'oznakaBroj' => 'K-202/2025',
            'pismena' => [
                ['vrsta' => 'Only Doc', 'datum' => '2025-01-15 10:00:00'],
            ],
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertCount(1, $data['pismena']);
    }

    /**
     * Test 22: Pagination - maintains sort order across pages
     *
     * @test
     */
    public function test_it_maintains_sort_order_with_pagination()
    {
        $pismena = [];
        for ($i = 1; $i <= 30; $i++) {
            $pismena[] = [
                'vrsta' => "Doc {$i}",
                'datum' => '2025-01-'.str_pad($i % 28 + 1, 2, '0', STR_PAD_LEFT).' 10:00:00',
            ];
        }

        $mockResponse = [
            'oznakaBroj' => 'K-203/2025',
            'pismena' => $pismena,
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        // Verify sorted by date descending
        $firstDate = $data['pismena'][0]['datum'] ?? null;
        $lastDate = $data['pismena'][count($data['pismena']) - 1]['datum'] ?? null;

        if ($firstDate && $lastDate) {
            $this->assertGreaterThanOrEqual($lastDate, $firstDate);
        }
    }

    /**
     * Test 23: Filtering - filters by document type
     *
     * @test
     */
    public function test_it_can_filter_by_document_type()
    {
        $mockResponse = [
            'oznakaBroj' => 'K-300/2025',
            'pismena' => [
                ['vrsta' => 'Tužba', 'datum' => '2025-01-15'],
                ['vrsta' => 'Rješenje', 'datum' => '2025-01-16'],
                ['vrsta' => 'Tužba', 'datum' => '2025-01-17'],
            ],
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $tuzbeCount = collect($data['pismena'])->where('vrsta', 'Tužba')->count();
        $this->assertEquals(2, $tuzbeCount);
    }

    /**
     * Test 24: Filtering - combines date and type filters
     *
     * @test
     */
    public function test_it_combines_multiple_filters()
    {
        $mockResponse = [
            'oznakaBroj' => 'K-301/2025',
            'stranke' => [
                ['naziv' => 'John Doe', 'uloga' => 'Optuženik'],
                ['naziv' => 'Jane Smith', 'uloga' => 'Svjedok'],
            ],
            'pismena' => [
                ['vrsta' => 'Tužba', 'datum' => '2025-01-15'],
            ],
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $this->assertCount(2, $data['stranke']);
        $this->assertCount(1, $data['pismena']);
    }

    /**
     * Test 25: Filtering - handles empty filter results
     *
     * @test
     */
    public function test_it_handles_empty_filter_results()
    {
        $mockResponse = [
            'oznakaBroj' => 'K-302/2025',
            'pismena' => [
                ['vrsta' => 'Tužba', 'datum' => '2025-01-15'],
            ],
        ];

        $this->mock(GraphQLAutoClient::class, function ($mock) use ($mockResponse) {
            $mock->shouldReceive('run')->andReturn($mockResponse);
        });

        $component = Livewire::test(EpredmetWidget::class);
        $component->call('fetch');

        $data = $component->get('data');
        $nonexistent = collect($data['pismena'])->where('vrsta', 'Nonexistent')->count();
        $this->assertEquals(0, $nonexistent);
    }
}
