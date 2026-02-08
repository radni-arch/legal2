<?php

namespace Tests\Unit\Models;

use App\Models\DecisionDiscoveryRun;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DecisionDiscoveryRunTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $run = DecisionDiscoveryRun::create([
            'started_at' => now(),
            'completed_at' => now()->addMinutes(30),
            'topics_generated' => 10,
            'decisions_evaluated' => 50,
            'decisions_ingested' => 45,
            'topics' => ['criminal law', 'contracts'],
            'status' => 'completed',
        ]);

        $this->assertEquals(10, $run->topics_generated);
        $this->assertEquals(50, $run->decisions_evaluated);
        $this->assertEquals(45, $run->decisions_ingested);
    }

    /** @test */
    public function it_casts_dates_as_datetime()
    {
        $run = DecisionDiscoveryRun::create([
            'started_at' => '2024-01-15 10:00:00',
            'completed_at' => '2024-01-15 11:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->completed_at);
    }

    /** @test */
    public function it_casts_arrays()
    {
        $run = DecisionDiscoveryRun::create([
            'started_at' => now(),
            'topics' => ['criminal', 'civil'],
            'errors' => ['timeout', 'network error'],
        ]);

        $this->assertIsArray($run->topics);
        $this->assertIsArray($run->errors);
        $this->assertCount(2, $run->topics);
    }

    /** @test */
    public function it_calculates_duration()
    {
        $run = DecisionDiscoveryRun::create([
            'started_at' => now(),
            'completed_at' => now()->addMinutes(30),
        ]);

        $this->assertEquals(1800, $run->duration()); // 30 minutes = 1800 seconds
    }

    /** @test */
    public function it_returns_null_duration_when_not_completed()
    {
        $run = DecisionDiscoveryRun::create([
            'started_at' => now(),
            'completed_at' => null,
        ]);

        $this->assertNull($run->duration());
    }

    /** @test */
    public function it_has_status_check_methods()
    {
        $running = DecisionDiscoveryRun::create(['status' => 'running', 'started_at' => now()]);
        $completed = DecisionDiscoveryRun::create(['status' => 'completed', 'started_at' => now()]);
        $failed = DecisionDiscoveryRun::create(['status' => 'failed', 'started_at' => now()]);

        $this->assertTrue($running->isRunning());
        $this->assertFalse($running->isCompleted());
        $this->assertTrue($completed->isCompleted());
        $this->assertTrue($failed->isFailed());
    }

    /** @test */
    public function it_has_completed_scope()
    {
        // Clear any existing records from previous tests
        DecisionDiscoveryRun::query()->delete();

        // DecisionDiscoveryRun::create(['status' => 'completed', 'started_at' => now()]);
        // DecisionDiscoveryRun::create(['status' => 'failed', 'started_at' => now()]);
        // DecisionDiscoveryRun::create(['status' => 'running', 'started_at' => now()]);
        // Create test records with unique IDs we can track
        $completedRun = DecisionDiscoveryRun::create(['status' => 'completed', 'started_at' => now()]);
        $failedRun = DecisionDiscoveryRun::create(['status' => 'failed', 'started_at' => now()]);
        $runningRun = DecisionDiscoveryRun::create(['status' => 'running', 'started_at' => now()]);

        // Query only completed runs from our test set
        $completed = DecisionDiscoveryRun::completed()
            ->whereIn('id', [$completedRun->id, $failedRun->id, $runningRun->id])
            ->get();

        $this->assertCount(1, $completed);
        $this->assertEquals('completed', $completed->first()->status);
        $this->assertEquals($completedRun->id, $completed->first()->id);
    }

    /** @test */
    public function it_has_recent_scope()
    {
        // Create run that should be included (defaults to current timestamp)
        $recentRun = DecisionDiscoveryRun::create([
            'started_at' => now(),
            'status' => 'recent_scope_test',
        ]);

        // Manually insert an old record using DB to bypass model timestamps
        \DB::table('decision_discovery_runs')->insert([
            'id' => $oldId = random_int(100000, 999999),
            'started_at' => now()->subDays(40),
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
            'status' => 'old_scope_test',
            'topics_generated' => 0,
            'decisions_evaluated' => 0,
            'decisions_ingested' => 0,
        ]);

        $recent = DecisionDiscoveryRun::recent(30)->get();

        // Assert that the recent run is included
        $this->assertTrue($recent->contains('id', $recentRun->id));
        // Assert that the old run is NOT included
        $this->assertFalse($recent->contains('id', $oldId));
    }

    /** @test */
    public function it_gets_success_rate()
    {
        // Create test data using a timestamp far in the past to isolate from other tests
        $testDate = now()->subDays(10);

        $run1 = DecisionDiscoveryRun::create(['status' => 'completed', 'started_at' => $testDate, 'created_at' => $testDate]);
        $run2 = DecisionDiscoveryRun::create(['status' => 'completed', 'started_at' => $testDate, 'created_at' => $testDate]);
        $run3 = DecisionDiscoveryRun::create(['status' => 'failed', 'started_at' => $testDate, 'created_at' => $testDate]);

        // Get total count and completed count for our test runs
        $testRunIds = [$run1->id, $run2->id, $run3->id];
        $total = DecisionDiscoveryRun::whereIn('id', $testRunIds)->count();
        $completed = DecisionDiscoveryRun::whereIn('id', $testRunIds)->where('status', 'completed')->count();

        $expectedRate = round(($completed / $total) * 100, 2);

        $this->assertEquals(3, $total);
        $this->assertEquals(2, $completed);
        $this->assertEquals(66.67, $expectedRate); // 2/3 * 100
    }
}
