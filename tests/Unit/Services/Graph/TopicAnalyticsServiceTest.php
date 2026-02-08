<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\TopicAnalyticsService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TopicAnalyticsServiceTest extends TestCase
{
    protected TopicAnalyticsService $service;

    protected $graphMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->service = new TopicAnalyticsService($this->graphMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_detect_topic_spikes_returns_empty_when_no_spikes(): void
    {
        // Arrange - Mock empty baseline data
        DB::shouldReceive('table')
            ->with('topic_decision_counts')
            ->andReturnSelf();

        DB::shouldReceive('whereBetween')
            ->andReturnSelf();

        DB::shouldReceive('select')
            ->andReturnSelf();

        DB::shouldReceive('selectRaw')
            ->andReturnSelf();

        DB::shouldReceive('groupBy')
            ->andReturnSelf();

        DB::shouldReceive('get')
            ->andReturn(collect([]));

        DB::shouldReceive('where')
            ->andReturnSelf();

        Log::shouldReceive('info')->zeroOrMoreTimes();

        // Act
        $spikes = $this->service->detectTopicSpikes();

        // Assert
        $this->assertIsArray($spikes);
        $this->assertEmpty($spikes);
    }

    public function test_detect_topic_spikes_identifies_major_spike(): void
    {
        // Arrange - Mock baseline and current data showing a spike
        $baselineData = collect([
            (object) ['topic_name' => 'drug_charges', 'avg_count' => 10],
        ]);

        $currentData = collect([
            (object) ['topic_name' => 'drug_charges', 'total_count' => 35], // 250% increase
        ]);

        $callCount = 0;
        DB::shouldReceive('table')
            ->with('topic_decision_counts')
            ->andReturnUsing(function () use (&$callCount, $baselineData, $currentData) {
                $mock = Mockery::mock();
                $mock->shouldReceive('whereBetween')->andReturnSelf();
                $mock->shouldReceive('select')->andReturnSelf();
                $mock->shouldReceive('selectRaw')->andReturnSelf();
                $mock->shouldReceive('groupBy')->andReturnSelf();
                $mock->shouldReceive('where')->andReturnSelf();
                $mock->shouldReceive('get')->andReturn($callCount++ === 0 ? $baselineData : $currentData);
                $mock->shouldReceive('keyBy')->andReturn($baselineData->keyBy('topic_name'));

                return $mock;
            });

        Log::shouldReceive('info')->zeroOrMoreTimes();

        // Act
        $spikes = $this->service->detectTopicSpikes();

        // Assert
        $this->assertCount(1, $spikes);
        $this->assertEquals('drug_charges', $spikes[0]['topic_name']);
        $this->assertEquals('major', $spikes[0]['severity']); // >200% increase
        $this->assertGreaterThan(200, $spikes[0]['percent_increase']);
    }

    public function test_detect_topic_spikes_filters_below_minimum_count(): void
    {
        // Arrange - Spike exists but count is too low
        $baselineData = collect([
            (object) ['topic_name' => 'rare_topic', 'avg_count' => 1],
        ]);

        $currentData = collect([
            (object) ['topic_name' => 'rare_topic', 'total_count' => 3], // 200% increase but only 3 total
        ]);

        $callCount = 0;
        DB::shouldReceive('table')
            ->with('topic_decision_counts')
            ->andReturnUsing(function () use (&$callCount, $baselineData, $currentData) {
                $mock = Mockery::mock();
                $mock->shouldReceive('whereBetween')->andReturnSelf();
                $mock->shouldReceive('select')->andReturnSelf();
                $mock->shouldReceive('selectRaw')->andReturnSelf();
                $mock->shouldReceive('groupBy')->andReturnSelf();
                $mock->shouldReceive('where')->andReturnSelf();
                $mock->shouldReceive('get')->andReturn($callCount++ === 0 ? $baselineData : $currentData);
                $mock->shouldReceive('keyBy')->andReturn($baselineData->keyBy('topic_name'));

                return $mock;
            });

        Log::shouldReceive('info')->zeroOrMoreTimes();

        // Act - Default minAbsoluteCount is 5
        $spikes = $this->service->detectTopicSpikes();

        // Assert - Should be filtered out
        $this->assertEmpty($spikes);
    }

    public function test_classify_severity_returns_correct_levels(): void
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('classifySeverity');
        $method->setAccessible(true);

        // Test major (>200%)
        $this->assertEquals('major', $method->invoke($this->service, 250));

        // Test moderate (100-200%)
        $this->assertEquals('moderate', $method->invoke($this->service, 150));

        // Test minor (50-100%)
        $this->assertEquals('minor', $method->invoke($this->service, 75));
    }

    public function test_get_topic_trends_returns_weekly_data(): void
    {
        // Arrange
        $trendData = collect([
            (object) ['topic_name' => 'traffic_violations', 'week_start' => '2025-01-01', 'decision_count' => 10],
            (object) ['topic_name' => 'traffic_violations', 'week_start' => '2025-01-08', 'decision_count' => 15],
        ]);

        DB::shouldReceive('table')
            ->with('topic_decision_counts')
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->with('topic_name', 'traffic_violations')
            ->andReturnSelf();

        DB::shouldReceive('whereBetween')
            ->andReturnSelf();

        DB::shouldReceive('select')
            ->andReturnSelf();

        DB::shouldReceive('selectRaw')
            ->andReturnSelf();

        DB::shouldReceive('groupBy')
            ->andReturnSelf();

        DB::shouldReceive('orderBy')
            ->with('week_start', 'asc')
            ->andReturnSelf();

        DB::shouldReceive('get')
            ->andReturn($trendData);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        // Act
        $trends = $this->service->getTopicTrends('traffic_violations', 12);

        // Assert
        $this->assertIsArray($trends);
        $this->assertCount(2, $trends);
        $this->assertEquals('traffic_violations', $trends[0]['topic_name']);
        $this->assertEquals(10, $trends[0]['decision_count']);
    }

    public function test_get_topic_growth_report_returns_top_growing_topics(): void
    {
        // Arrange - Mock spike detection returning multiple topics
        $baselineData = collect([
            (object) ['topic_name' => 'topic_a', 'avg_count' => 10],
            (object) ['topic_name' => 'topic_b', 'avg_count' => 20],
        ]);

        $currentData = collect([
            (object) ['topic_name' => 'topic_a', 'total_count' => 20], // 100% increase
            (object) ['topic_name' => 'topic_b', 'total_count' => 50], // 150% increase
        ]);

        $callCount = 0;
        DB::shouldReceive('table')
            ->with('topic_decision_counts')
            ->andReturnUsing(function () use (&$callCount, $baselineData, $currentData) {
                $mock = Mockery::mock();
                $mock->shouldReceive('whereBetween')->andReturnSelf();
                $mock->shouldReceive('select')->andReturnSelf();
                $mock->shouldReceive('selectRaw')->andReturnSelf();
                $mock->shouldReceive('groupBy')->andReturnSelf();
                $mock->shouldReceive('where')->andReturnSelf();
                $mock->shouldReceive('get')->andReturn($callCount++ === 0 ? $baselineData : $currentData);
                $mock->shouldReceive('keyBy')->andReturn($baselineData->keyBy('topic_name'));

                return $mock;
            });

        Log::shouldReceive('info')->zeroOrMoreTimes();

        // Act
        $report = $this->service->getTopicGrowthReport(10);

        // Assert
        $this->assertArrayHasKey('top_growing_topics', $report);
        $this->assertArrayHasKey('total_topics_analyzed', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertCount(2, $report['top_growing_topics']);
        // Should be sorted by growth descending
        $this->assertEquals('topic_b', $report['top_growing_topics'][0]['topic_name']);
        $this->assertEquals('topic_a', $report['top_growing_topics'][1]['topic_name']);
    }

    public function test_get_affected_courts_returns_unique_courts_for_topic(): void
    {
        // Arrange
        $topicName = 'drug_charges';
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        // Mock Neo4j query result
        $queryResult = [
            ['court' => 'Vrhovni sud Republike Hrvatske'],
            ['court' => 'Županijski sud u Zagrebu'],
            ['court' => 'Općinski sud u Splitu'],
        ];

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->graphMock->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function ($query) {
                    return str_contains($query, 'MATCH (k:Keyword {term: $topic})<-[:HAS_KEYWORD]-(doc:CourtDecisionDocument)');
                }),
                Mockery::on(function ($params) use ($topicName, $startDate, $endDate) {
                    return $params['topic'] === $topicName
                        && $params['startDate'] === $startDate->toDateString()
                        && $params['endDate'] === $endDate->toDateString();
                })
            )
            ->andReturn($queryResult);

        // Act
        $courts = $this->service->getAffectedCourts($topicName, $startDate, $endDate);

        // Assert
        $this->assertIsArray($courts);
        $this->assertCount(3, $courts);
        $this->assertEquals('Vrhovni sud Republike Hrvatske', $courts[0]);
        $this->assertEquals('Županijski sud u Zagrebu', $courts[1]);
        $this->assertEquals('Općinski sud u Splitu', $courts[2]);
    }

    public function test_get_affected_courts_returns_empty_array_on_exception(): void
    {
        // Arrange
        $topicName = 'invalid_topic';
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->once();

        $this->graphMock->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Neo4j connection failed'));

        // Act
        $courts = $this->service->getAffectedCourts($topicName, $startDate, $endDate);

        // Assert
        $this->assertIsArray($courts);
        $this->assertEmpty($courts);
    }

    public function test_get_affected_courts_filters_null_courts(): void
    {
        // Arrange
        $topicName = 'contract_law';
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        // Mock result with some null courts (should be filtered by query but testing defensive code)
        $queryResult = [
            ['court' => 'Vrhovni sud Republike Hrvatske'],
            ['other_field' => 'value'], // Missing 'court' field
            ['court' => 'Županijski sud u Zagrebu'],
        ];

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->graphMock->shouldReceive('run')
            ->once()
            ->andReturn($queryResult);

        // Act
        $courts = $this->service->getAffectedCourts($topicName, $startDate, $endDate);

        // Assert
        $this->assertIsArray($courts);
        $this->assertCount(2, $courts); // Only records with 'court' field
        $this->assertEquals('Vrhovni sud Republike Hrvatske', $courts[0]);
        $this->assertEquals('Županijski sud u Zagrebu', $courts[1]);
    }
}
