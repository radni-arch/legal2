<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\DataQualityService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Sprint 8.4: Data Quality Service Tests
 *
 * Tests for automated data quality checks including:
 * - Duplicate node detection
 * - Orphan node detection
 * - Data inconsistency detection
 * - Quality score calculation
 * - Quality report generation
 */
class DataQualityServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected $graphMock;

    protected DataQualityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock GraphDatabaseService
        $this->graphMock = Mockery::mock(GraphDatabaseService::class);

        // Create service with mocked graph database
        $this->service = new DataQualityService($this->graphMock);

        // Clear cache before each test
        Cache::flush();

        // Prevent actual emails from being sent
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper method to create a mock result object that behaves like Neo4j results
     */
    protected function createMockResult(array $data): object
    {
        $mock = Mockery::mock();
        $mock->shouldReceive('get')->andReturnUsing(function ($key) use ($data) {
            return $data[$key] ?? null;
        });

        return $mock;
    }

    /**
     * Helper method to create a collection of mock results with first() support
     */
    protected function createMockResultCollection(array $data): array
    {
        $collection = [];
        foreach ($data as $item) {
            $collection[] = $this->createMockResult($item);
        }

        // Create a mock that supports first()
        $mock = Mockery::mock();
        $mock->shouldReceive('first')->andReturn($this->createMockResult($data[0] ?? []));

        // Support iteration
        return $collection;
    }

    // ========================================
    // Duplicate Detection Tests
    // ========================================

    /** @test */
    public function it_detects_duplicate_nodes_when_neo4j_available()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        // Mock duplicate Case nodes
        $caseResult = $this->createMockResult([
            'key' => 'K-123/2024',
            'duplicates' => [
                ['id' => 1, 'properties' => ['case_number' => 'K-123/2024', 'title' => 'Case A']],
                ['id' => 2, 'properties' => ['case_number' => 'K-123/2024', 'title' => 'Case B']],
            ],
        ]);

        $this->graphMock->shouldReceive('run')
            ->with(Mockery::on(function ($statement) {
                return strpos($statement, 'MATCH (n:Case)') !== false;
            }))
            ->andReturn([$caseResult]);

        // Mock other node types returning empty
        $this->graphMock->shouldReceive('run')->andReturn([]);

        $result = $this->service->detectDuplicateNodes();

        $this->assertEquals(1, $result['total_duplicate_groups']);
        $this->assertEquals(2, $result['total_duplicate_nodes']);
        $this->assertCount(1, $result['duplicates']);
        $this->assertEquals('Case', $result['duplicates'][0]['node_type']);
        $this->assertEquals('K-123/2024', $result['duplicates'][0]['key_value']);
    }

    /** @test */
    public function it_returns_empty_when_no_duplicates_found()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('run')->andReturn([]);

        $result = $this->service->detectDuplicateNodes();

        $this->assertEquals(0, $result['total_duplicate_groups']);
        $this->assertEquals(0, $result['total_duplicate_nodes']);
        $this->assertEmpty($result['duplicates']);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_for_duplicate_detection()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(false);

        $result = $this->service->detectDuplicateNodes();

        $this->assertEquals(0, $result['total_duplicate_groups']);
        $this->assertEquals(0, $result['total_duplicate_nodes']);
        $this->assertEmpty($result['duplicates']);
    }

    /** @test */
    public function it_filters_duplicates_by_node_type()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        // Only mock Case type query
        $caseResult = $this->createMockResult([
            'key' => 'K-456/2024',
            'duplicates' => [
                ['id' => 3, 'properties' => ['case_number' => 'K-456/2024']],
                ['id' => 4, 'properties' => ['case_number' => 'K-456/2024']],
            ],
        ]);

        $this->graphMock->shouldReceive('run')
            ->with(Mockery::on(function ($statement) {
                return strpos($statement, 'MATCH (n:Case)') !== false;
            }))
            ->andReturn([$caseResult]);

        $result = $this->service->detectDuplicateNodes('Case');

        $this->assertEquals(1, $result['total_duplicate_groups']);
        $this->assertEquals('Case', $result['duplicates'][0]['node_type']);
    }

    // ========================================
    // Orphan Detection Tests
    // ========================================

    /** @test */
    public function it_detects_orphan_nodes_when_neo4j_available()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        // Mock orphan Case nodes
        $orphanResult = $this->createMockResult([
            'node_id' => 100,
            'node_type' => 'Case',
            'properties' => ['case_number' => 'K-789/2024', 'title' => 'Orphan Case'],
        ]);

        $this->graphMock->shouldReceive('run')
            ->with(Mockery::on(function ($statement) {
                return strpos($statement, 'MATCH (n:Case)') !== false && strpos($statement, 'NOT (n)--()') !== false;
            }))
            ->andReturn([$orphanResult]);

        // Mock other types returning empty
        $this->graphMock->shouldReceive('run')->andReturn([]);

        $result = $this->service->detectOrphanNodes();

        $this->assertEquals(1, $result['total_orphans']);
        $this->assertCount(1, $result['orphans']);
        $this->assertEquals(100, $result['orphans'][0]['node_id']);
        $this->assertEquals('Case', $result['orphans'][0]['node_type']);
    }

    /** @test */
    public function it_returns_empty_when_no_orphans_found()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('run')->andReturn([]);

        $result = $this->service->detectOrphanNodes();

        $this->assertEquals(0, $result['total_orphans']);
        $this->assertEmpty($result['orphans']);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_for_orphan_detection()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(false);

        $result = $this->service->detectOrphanNodes();

        $this->assertEquals(0, $result['total_orphans']);
        $this->assertEmpty($result['orphans']);
    }

    // ========================================
    // Inconsistency Detection Tests
    // ========================================

    /** @test */
    public function it_detects_inconsistent_data_when_neo4j_available()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        // Mock Case with missing title
        $inconsistencyResult = $this->createMockResult([
            'node_id' => 200,
            'node_type' => 'Case',
            'missing_property' => 'title',
            'properties' => ['case_number' => 'K-999/2024'],
        ]);

        $this->graphMock->shouldReceive('run')
            ->with(Mockery::on(function ($statement) {
                return strpos($statement, 'MATCH (n:Case)') !== false && strpos($statement, 'title') !== false;
            }))
            ->andReturn([$inconsistencyResult]);

        // Mock other queries returning empty
        $this->graphMock->shouldReceive('run')->andReturn([]);

        $result = $this->service->detectInconsistentData();

        $this->assertEquals(1, $result['total_inconsistent']);
        $this->assertCount(1, $result['inconsistencies']);
        $this->assertEquals(200, $result['inconsistencies'][0]['node_id']);
        $this->assertEquals('title', $result['inconsistencies'][0]['missing_property']);
    }

    /** @test */
    public function it_returns_empty_when_no_inconsistencies_found()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('run')->andReturn([]);

        $result = $this->service->detectInconsistentData();

        $this->assertEquals(0, $result['total_inconsistent']);
        $this->assertEmpty($result['inconsistencies']);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_for_inconsistency_detection()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(false);

        $result = $this->service->detectInconsistentData();

        $this->assertEquals(0, $result['total_inconsistent']);
        $this->assertEmpty($result['inconsistencies']);
    }

    // ========================================
    // Duplicate Merging Tests
    // ========================================

    /** @test */
    public function it_merges_duplicate_nodes_successfully()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        $duplicateGroup = [
            'node_type' => 'Case',
            'key_property' => 'case_number',
            'key_value' => 'K-111/2024',
            'duplicate_count' => 2,
            'node_ids' => [10, 11],
            'nodes' => [
                ['id' => 10, 'properties' => ['case_number' => 'K-111/2024', 'title' => 'First']],
                ['id' => 11, 'properties' => ['case_number' => 'K-111/2024', 'title' => 'Second']],
            ],
        ];

        // Mock successful merge result
        $mergeResult = Mockery::mock();
        $mergeResult->shouldReceive('first')->andReturnSelf();
        $mergeResult->shouldReceive('get')->with('merged_count')->andReturn(1);

        $this->graphMock->shouldReceive('run')
            ->with(Mockery::any(), Mockery::any())
            ->andReturn($mergeResult);

        $result = $this->service->mergeDuplicateNodes($duplicateGroup);

        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['kept_node_id']);
        $this->assertEquals(1, $result['merged_count']);
    }

    /** @test */
    public function it_handles_merge_errors_gracefully()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        $duplicateGroup = [
            'node_type' => 'Case',
            'key_property' => 'case_number',
            'key_value' => 'K-222/2024',
            'duplicate_count' => 2,
            'node_ids' => [20, 21],
            'nodes' => [
                ['id' => 20, 'properties' => []],
                ['id' => 21, 'properties' => []],
            ],
        ];

        // Mock exception during merge
        $this->graphMock->shouldReceive('run')
            ->andThrow(new \Exception('Merge failed'));

        $result = $this->service->mergeDuplicateNodes($duplicateGroup);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Merge failed', $result['error']);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_for_merging()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(false);

        $duplicateGroup = [
            'node_type' => 'Case',
            'duplicates' => [
                ['id' => 30, 'properties' => []],
                ['id' => 31, 'properties' => []],
            ],
        ];

        $result = $this->service->mergeDuplicateNodes($duplicateGroup);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not available', $result['error']);
    }

    // ========================================
    // Quality Score Tests
    // ========================================

    /** @test */
    public function it_calculates_quality_score_correctly()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        // Mock total nodes = 100
        $totalResult = Mockery::mock();
        $totalResult->shouldReceive('first')->andReturnSelf();
        $totalResult->shouldReceive('get')->with('total')->andReturn(100);

        $this->graphMock->shouldReceive('run')
            ->with(Mockery::on(function ($statement) {
                return strpos($statement, 'count(n)') !== false;
            }))
            ->andReturn($totalResult);

        // Mock detectDuplicateNodes, detectOrphanNodes, detectInconsistentData via run()
        // These will be called internally, so mock all other run() calls to return empty
        $this->graphMock->shouldReceive('run')->andReturn([]);

        $result = $this->service->calculateQualityScore();

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('total_nodes', $result);
        $this->assertArrayHasKey('metrics', $result);

        // With no issues, score should be 100
        $this->assertEquals(100, $result['score']);
        $this->assertEquals(100, $result['total_nodes']);
    }

    /** @test */
    public function it_returns_perfect_score_when_no_issues()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        // Mock 100 nodes
        $totalResult = Mockery::mock();
        $totalResult->shouldReceive('first')->andReturnSelf();
        $totalResult->shouldReceive('get')->with('total')->andReturn(100);

        $this->graphMock->shouldReceive('run')
            ->with(Mockery::on(function ($statement) {
                return strpos($statement, 'count(n)') !== false;
            }))
            ->andReturn($totalResult);

        // Mock no duplicates, orphans, or inconsistencies
        $this->graphMock->shouldReceive('run')->andReturn([]);

        $result = $this->service->calculateQualityScore();

        $this->assertEquals(100, $result['score']);
        $this->assertEquals(100, $result['metrics']['duplicate_score']);
        $this->assertEquals(100, $result['metrics']['orphan_score']);
        $this->assertEquals(100, $result['metrics']['consistency_score']);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_for_quality_score()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(false);

        $result = $this->service->calculateQualityScore();

        $this->assertEquals(0, $result['score']);
        $this->assertEquals(0, $result['total_nodes']);
    }

    // ========================================
    // Quality Report Tests
    // ========================================

    /** @test */
    public function it_generates_quality_report_successfully()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        // Mock total nodes
        $totalResult = Mockery::mock();
        $totalResult->shouldReceive('first')->andReturnSelf();
        $totalResult->shouldReceive('get')->with('total')->andReturn(150);

        $this->graphMock->shouldReceive('run')
            ->with(Mockery::on(function ($statement) {
                return strpos($statement, 'count(n)') !== false;
            }))
            ->andReturn($totalResult);

        // Mock all other queries to return empty
        $this->graphMock->shouldReceive('run')->andReturn([]);

        $report = $this->service->generateQualityReport(false);

        $this->assertArrayHasKey('overall_quality_score', $report);
        $this->assertArrayHasKey('metrics', $report);
        $this->assertArrayHasKey('duplicates', $report);
        $this->assertArrayHasKey('orphans', $report);
        $this->assertArrayHasKey('inconsistencies', $report);
        $this->assertArrayHasKey('statistics', $report);
        $this->assertArrayHasKey('recommendations', $report);
        $this->assertArrayHasKey('generated_at', $report);

        $this->assertEquals(100, $report['overall_quality_score']);
        $this->assertEquals(150, $report['total_nodes']);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_for_report()
    {
        $this->graphMock->shouldReceive('isAvailable')->andReturn(false);

        $report = $this->service->generateQualityReport(false);

        $this->assertEquals(0, $report['overall_quality_score']);
        $this->assertEquals(0, $report['total_nodes']);
        $this->assertEmpty($report['duplicates']['groups']);
        $this->assertEmpty($report['orphans']['nodes']);
        $this->assertEmpty($report['inconsistencies']['nodes']);
    }
}
