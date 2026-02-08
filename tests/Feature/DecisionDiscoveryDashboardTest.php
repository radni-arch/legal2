<?php

namespace Tests\Feature;

use App\Http\Livewire\DecisionDiscoveryDashboard;
use App\Models\DecisionDiscoveryRun;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive tests for DecisionDiscoveryDashboard Livewire component
 *
 * Tests cover:
 * - Component rendering
 * - Data display and statistics
 * - Pagination functionality
 * - Success rate calculations
 * - Edge cases and empty states
 * - Performance with large datasets
 */
class DecisionDiscoveryDashboardTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test component renders successfully with no data
     *
     * @test
     */
    public function it_renders_successfully_with_no_data(): void
    {
        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.decision-discovery-dashboard');
    }

    /**
     * Test component displays empty state when no runs exist
     *
     * @test
     */
    public function it_displays_empty_state_when_no_runs(): void
    {
        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertSet('latestRun', null)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total_runs'] === 0
                    && $stats['total_decisions_ingested'] === 0
                    && $stats['total_decisions_evaluated'] === 0
                    && $stats['success_rate'] === 0;
            });
    }

    /**
     * Test component displays latest run
     *
     * @test
     */
    public function it_displays_latest_run(): void
    {
        $oldRun = DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(5),
            'completed_at' => now()->subDays(5)->addHours(2),
            'status' => 'completed',
            'decisions_evaluated' => 50,
            'decisions_ingested' => 40,
            'topics_generated' => 10,
        ]);

        $latestRun = DecisionDiscoveryRun::create([
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHours(1),
            'status' => 'completed',
            'decisions_evaluated' => 100,
            'decisions_ingested' => 80,
            'topics_generated' => 20,
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('latestRun', function ($run) use ($latestRun) {
                return $run->id === $latestRun->id;
            });
    }

    /**
     * Test component displays paginated runs
     *
     * @test
     */
    public function it_displays_paginated_runs(): void
    {
        // Create 25 runs (more than 1 page)
        for ($i = 0; $i < 25; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subDays($i),
                'status' => 'completed',
                'decisions_evaluated' => rand(10, 100),
                'decisions_ingested' => rand(5, 80),
            ]);
        }

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('runs', function ($runs) {
                return $runs->total() === 25 && $runs->perPage() === 20;
            });
    }

    /**
     * Test pagination works correctly
     *
     * @test
     */
    public function it_paginates_runs_correctly(): void
    {
        // Create 30 runs
        for ($i = 0; $i < 30; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subDays($i),
                'status' => 'completed',
                'decisions_evaluated' => $i + 1,
            ]);
        }

        $component = Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('runs', function ($runs) {
                return $runs->count() === 20; // First page
            });

        // Navigate to page 2
        $component->call('gotoPage', 2)
            ->assertViewHas('runs', function ($runs) {
                return $runs->count() === 10; // Second page
            });
    }

    /**
     * Test statistics are calculated correctly
     *
     * @test
     */
    public function it_calculates_statistics_correctly(): void
    {
        DecisionDiscoveryRun::create([
            'started_at' => now()->subDay(),
            'completed_at' => now(),
            'status' => 'completed',
            'decisions_evaluated' => 100,
            'decisions_ingested' => 80,
            'topics_generated' => 20,
        ]);

        DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2)->addHours(1),
            'status' => 'completed',
            'decisions_evaluated' => 50,
            'decisions_ingested' => 40,
            'topics_generated' => 10,
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total_runs'] === 2
                    && $stats['total_decisions_evaluated'] === 150
                    && $stats['total_decisions_ingested'] === 120
                    && $stats['success_rate'] === 100.0; // Both completed
            });
    }

    /**
     * Test success rate calculation with mixed statuses
     *
     * @test
     */
    public function it_calculates_success_rate_with_mixed_statuses(): void
    {
        // 3 completed
        for ($i = 0; $i < 3; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subDays($i),
                'completed_at' => now()->subDays($i)->addHours(1),
                'status' => 'completed',
                'decisions_evaluated' => 50,
                'decisions_ingested' => 40,
            ]);
        }

        // 1 failed
        DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(4),
            'completed_at' => now()->subDays(4)->addHours(1),
            'status' => 'failed',
            'error_message' => 'Test error',
        ]);

        // 1 running
        DecisionDiscoveryRun::create([
            'started_at' => now()->subHours(1),
            'status' => 'running',
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                // Success rate: 3 completed / 5 total = 60%
                return $stats['success_rate'] === 60.0;
            });
    }

    /**
     * Test success rate with only failures
     *
     * @test
     */
    public function it_calculates_zero_success_rate_with_all_failures(): void
    {
        for ($i = 0; $i < 3; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subDays($i),
                'completed_at' => now()->subDays($i)->addHours(1),
                'status' => 'failed',
                'error_message' => 'Test error',
            ]);
        }

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['success_rate'] === 0.0;
            });
    }

    /**
     * Test runs are ordered by most recent first
     *
     * @test
     */
    public function it_orders_runs_by_most_recent_first(): void
    {
        $firstRun = DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(5),
            'status' => 'completed',
            'decisions_evaluated' => 10,
        ]);

        $secondRun = DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(3),
            'status' => 'completed',
            'decisions_evaluated' => 20,
        ]);

        $thirdRun = DecisionDiscoveryRun::create([
            'started_at' => now()->subDay(),
            'status' => 'completed',
            'decisions_evaluated' => 30,
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('runs', function ($runs) use ($thirdRun, $secondRun, $firstRun) {
                return $runs[0]->id === $thirdRun->id
                    && $runs[1]->id === $secondRun->id
                    && $runs[2]->id === $firstRun->id;
            });
    }

    /**
     * Test component handles running status
     *
     * @test
     */
    public function it_displays_currently_running_runs(): void
    {
        DecisionDiscoveryRun::create([
            'started_at' => now()->subHours(2),
            'status' => 'running',
            'decisions_evaluated' => 25,
        ]);

        DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(1),
            'completed_at' => now()->subDays(1)->addHours(1),
            'status' => 'completed',
            'decisions_evaluated' => 50,
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('runs', function ($runs) {
                return $runs->count() === 2;
            })
            ->assertViewHas('latestRun', function ($run) {
                return $run->status === 'running';
            });
    }

    /**
     * Test component displays failed runs
     *
     * @test
     */
    public function it_displays_failed_runs_with_errors(): void
    {
        DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(1),
            'completed_at' => now()->subDays(1)->addHours(1),
            'status' => 'failed',
            'error_message' => 'API connection failed',
            'errors' => ['error1', 'error2'],
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('latestRun', function ($run) {
                return $run->status === 'failed'
                    && $run->error_message === 'API connection failed';
            });
    }

    /**
     * Test statistics with null values are handled
     *
     * @test
     */
    public function it_handles_null_values_in_statistics(): void
    {
        // Create run with minimal data
        DecisionDiscoveryRun::create([
            'started_at' => now(),
            'status' => 'running',
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return isset($stats['total_runs'])
                    && isset($stats['total_decisions_ingested'])
                    && isset($stats['total_decisions_evaluated'])
                    && isset($stats['success_rate']);
            });
    }

    /**
     * Test component with large dataset
     *
     * @test
     */
    public function it_handles_large_datasets_efficiently(): void
    {
        // Create 100 runs
        for ($i = 0; $i < 100; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subDays($i),
                'completed_at' => now()->subDays($i)->addHours(rand(1, 5)),
                'status' => $i % 10 === 0 ? 'failed' : 'completed',
                'decisions_evaluated' => rand(10, 200),
                'decisions_ingested' => rand(5, 150),
                'topics_generated' => rand(1, 50),
            ]);
        }

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total_runs'] === 100;
            })
            ->assertViewHas('runs', function ($runs) {
                return $runs->total() === 100 && $runs->perPage() === 20;
            });
    }

    /**
     * Test statistics aggregation with various numbers
     *
     * @test
     */
    public function it_aggregates_statistics_correctly(): void
    {
        DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(1),
            'completed_at' => now()->subDays(1)->addHours(2),
            'status' => 'completed',
            'decisions_evaluated' => 150,
            'decisions_ingested' => 120,
            'topics_generated' => 30,
        ]);

        DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2)->addHours(3),
            'status' => 'completed',
            'decisions_evaluated' => 200,
            'decisions_ingested' => 180,
            'topics_generated' => 40,
        ]);

        DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(3),
            'completed_at' => now()->subDays(3)->addHours(1),
            'status' => 'completed',
            'decisions_evaluated' => 100,
            'decisions_ingested' => 90,
            'topics_generated' => 20,
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total_runs'] === 3
                    && $stats['total_decisions_evaluated'] === 450
                    && $stats['total_decisions_ingested'] === 390;
            });
    }

    /**
     * Test component with runs having no completion date
     *
     * @test
     */
    public function it_handles_runs_without_completion_date(): void
    {
        DecisionDiscoveryRun::create([
            'started_at' => now()->subHours(2),
            'status' => 'running',
            'decisions_evaluated' => 25,
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('latestRun', function ($run) {
                return $run->completed_at === null;
            });
    }

    /**
     * Test success rate rounds correctly
     *
     * @test
     */
    public function it_rounds_success_rate_to_one_decimal(): void
    {
        // 2 completed out of 3 total = 66.666...%
        DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(1),
            'completed_at' => now()->subDays(1)->addHours(1),
            'status' => 'completed',
            'decisions_evaluated' => 50,
        ]);

        DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2)->addHours(1),
            'status' => 'completed',
            'decisions_evaluated' => 60,
        ]);

        DecisionDiscoveryRun::create([
            'started_at' => now()->subDays(3),
            'completed_at' => now()->subDays(3)->addHours(1),
            'status' => 'failed',
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['success_rate'] === 66.7;
            });
    }

    /**
     * Test component with topics data
     *
     * @test
     */
    public function it_displays_runs_with_topics(): void
    {
        DecisionDiscoveryRun::create([
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHours(2),
            'status' => 'completed',
            'decisions_evaluated' => 100,
            'decisions_ingested' => 80,
            'topics_generated' => 25,
            'topics' => [
                'Contract Law',
                'Employment Disputes',
                'Intellectual Property',
            ],
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('latestRun', function ($run) {
                return $run->topics_generated === 25
                    && is_array($run->topics)
                    && count($run->topics) === 3;
            });
    }

    /**
     * Test component with errors array
     *
     * @test
     */
    public function it_displays_runs_with_errors_array(): void
    {
        DecisionDiscoveryRun::create([
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHours(1),
            'status' => 'failed',
            'error_message' => 'Multiple errors occurred',
            'errors' => [
                'API rate limit exceeded',
                'Network timeout',
                'Invalid response format',
            ],
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('latestRun', function ($run) {
                return $run->status === 'failed'
                    && is_array($run->errors)
                    && count($run->errors) === 3;
            });
    }

    /**
     * Test pagination maintains state
     *
     * @test
     */
    public function it_maintains_pagination_state(): void
    {
        // Create 50 runs
        for ($i = 0; $i < 50; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subDays($i),
                'status' => 'completed',
                'decisions_evaluated' => rand(10, 100),
            ]);
        }

        $component = Livewire::test(DecisionDiscoveryDashboard::class);

        // Navigate to page 2
        $component->call('gotoPage', 2)
            ->assertViewHas('runs', function ($runs) {
                return $runs->currentPage() === 2;
            });

        // Navigate to page 3
        $component->call('gotoPage', 3)
            ->assertViewHas('runs', function ($runs) {
                return $runs->currentPage() === 3;
            });
    }

    /**
     * Test component performance with statistics calculation
     *
     * @test
     */
    public function it_calculates_statistics_efficiently(): void
    {
        // Create 50 runs with varied data
        for ($i = 0; $i < 50; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subDays($i),
                'completed_at' => now()->subDays($i)->addHours(rand(1, 5)),
                'status' => ['completed', 'failed', 'running'][rand(0, 2)],
                'decisions_evaluated' => rand(10, 200),
                'decisions_ingested' => rand(5, 150),
                'topics_generated' => rand(1, 50),
            ]);
        }

        $startTime = microtime(true);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertStatus(200);

        $duration = microtime(true) - $startTime;

        // Should render in less than 2 seconds even with 50 runs
        $this->assertLessThan(2.0, $duration, 'Dashboard should render efficiently');
    }

    /**
     * Test empty topics array
     *
     * @test
     */
    public function it_handles_empty_topics_array(): void
    {
        DecisionDiscoveryRun::create([
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHours(1),
            'status' => 'completed',
            'decisions_evaluated' => 50,
            'decisions_ingested' => 40,
            'topics_generated' => 0,
            'topics' => [],
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('latestRun', function ($run) {
                return is_array($run->topics) && count($run->topics) === 0;
            });
    }

    /**
     * Test component with zero decisions
     *
     * @test
     */
    public function it_handles_runs_with_zero_decisions(): void
    {
        DecisionDiscoveryRun::create([
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHours(1),
            'status' => 'completed',
            'decisions_evaluated' => 0,
            'decisions_ingested' => 0,
            'topics_generated' => 0,
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total_decisions_evaluated'] === 0
                    && $stats['total_decisions_ingested'] === 0;
            });
    }

    /**
     * Test component refreshes data
     *
     * @test
     */
    public function it_refreshes_data_on_render(): void
    {
        $component = Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total_runs'] === 0;
            });

        // Create a new run
        DecisionDiscoveryRun::create([
            'started_at' => now(),
            'status' => 'running',
            'decisions_evaluated' => 10,
        ]);

        // Re-render component
        $component->call('$refresh')
            ->assertViewHas('stats', function ($stats) {
                return $stats['total_runs'] === 1;
            });
    }

    /**
     * Test calculateSuccessRate method directly
     *
     * @test
     */
    public function it_calculates_success_rate_accurately(): void
    {
        // 7 completed, 3 failed = 70% success rate
        for ($i = 0; $i < 7; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subDays($i),
                'completed_at' => now()->subDays($i)->addHours(1),
                'status' => 'completed',
                'decisions_evaluated' => 50,
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subDays(7 + $i),
                'completed_at' => now()->subDays(7 + $i)->addHours(1),
                'status' => 'failed',
            ]);
        }

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['success_rate'] === 70.0;
            });
    }

    /**
     * Test component view structure
     *
     * @test
     */
    public function it_passes_correct_data_to_view(): void
    {
        DecisionDiscoveryRun::create([
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHours(2),
            'status' => 'completed',
            'decisions_evaluated' => 100,
            'decisions_ingested' => 80,
        ]);

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('latestRun')
            ->assertViewHas('runs')
            ->assertViewHas('stats')
            ->assertViewHas('stats', function ($stats) {
                return isset($stats['total_runs'])
                    && isset($stats['total_decisions_ingested'])
                    && isset($stats['total_decisions_evaluated'])
                    && isset($stats['success_rate']);
            });
    }

    /**
     * Test with concurrent running jobs
     *
     * @test
     */
    public function it_handles_multiple_concurrent_running_jobs(): void
    {
        // Create 3 running jobs
        for ($i = 0; $i < 3; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subHours($i + 1),
                'status' => 'running',
                'decisions_evaluated' => rand(10, 50),
            ]);
        }

        // Create some completed jobs
        for ($i = 0; $i < 5; $i++) {
            DecisionDiscoveryRun::create([
                'started_at' => now()->subDays($i + 1),
                'completed_at' => now()->subDays($i + 1)->addHours(2),
                'status' => 'completed',
                'decisions_evaluated' => rand(50, 100),
            ]);
        }

        Livewire::test(DecisionDiscoveryDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total_runs'] === 8
                    && $stats['success_rate'] === 62.5; // 5 completed out of 8 = 62.5%
            })
            ->assertViewHas('latestRun', function ($run) {
                return $run->status === 'running';
            });
    }
}
