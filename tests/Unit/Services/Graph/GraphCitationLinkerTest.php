<?php

namespace Tests\Unit\Services\Graph;

use App\Exceptions\GraphException;
use App\Services\Graph\GraphCitationLinker;
use App\Services\GraphDatabaseService;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for GraphCitationLinker::unlinkAll()
 *
 * Step 1: RED phase - All tests should FAIL
 * Step 2: GREEN phase - Implement unlinkAll() to make tests pass
 * Step 3: REFACTOR - Clean up and optimize
 *
 * Sprint 12.5 - Worker A: Graph Citation Unlinking
 */
class GraphCitationLinkerTest extends TestCase
{
    protected $graphMock;

    protected GraphCitationLinker $linker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->linker = new GraphCitationLinker($this->graphMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: Verify Relationship Deletion Count
    // ========================================

    /**
     * Test that unlinkAll() deletes CITES and REFERENCES relationships
     * and returns the count of deleted relationships.
     *
     * @test
     */
    public function test_unlink_all_deletes_cites_relationships(): void
    {
        // Arrange: Mock the graph service to return specific relationship counts
        $this->graphMock
            ->shouldReceive('query')
            ->once()
            ->with(
                Mockery::on(function ($query) {
                    // Verify the Cypher query deletes both CITES and REFERENCES relationships
                    return str_contains($query, 'MATCH (n:Decision {id: $nodeId})')
                        && str_contains($query, '-[r:CITES|REFERENCES]-')
                        && str_contains($query, 'DELETE r')
                        && str_contains($query, 'RETURN count(r)');
                }),
                ['nodeId' => 'decision-123']
            )
            ->andReturn([
                ['count(r)' => 5], // 5 relationships deleted
            ]);

        // Act: Call unlinkAll()
        $deletedCount = $this->linker->unlinkAll('Decision', 'decision-123');

        // Assert: Should return 5
        $this->assertEquals(5, $deletedCount);
    }

    // ========================================
    // Test 2: Graceful Handling - Neo4j Unavailable
    // ========================================

    /**
     * Test that unlinkAll() returns 0 when Neo4j is unavailable
     * instead of throwing an exception.
     *
     * @test
     */
    public function test_unlink_all_returns_zero_when_neo4j_unavailable(): void
    {
        // Arrange: Mock Neo4j connection failure
        $this->graphMock
            ->shouldReceive('query')
            ->once()
            ->andThrow(new \Exception('Connection refused'));

        // Mock Log facade to verify warning is logged
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'GraphCitationLinker::unlinkAll - Neo4j unavailable',
                Mockery::on(function ($context) {
                    return $context['node_type'] === 'Decision'
                        && $context['node_id'] === 'decision-456'
                        && $context['error'] === 'Connection refused';
                })
            );

        // Act: Call unlinkAll() when Neo4j is down
        $deletedCount = $this->linker->unlinkAll('Decision', 'decision-456');

        // Assert: Should return 0 gracefully
        $this->assertEquals(0, $deletedCount);
    }

    // ========================================
    // Test 3: Error Handling - Deletion Failure
    // ========================================

    /**
     * Test that unlinkAll() throws GraphException when relationship
     * deletion fails (not connection issue).
     *
     * @test
     */
    public function test_unlink_all_throws_exception_on_failure(): void
    {
        // Arrange: Mock a query failure (not connection issue)
        $this->graphMock
            ->shouldReceive('query')
            ->once()
            ->andThrow(new \RuntimeException('Constraint violation'));

        // Mock Log facade to verify error is logged
        Log::shouldReceive('error')
            ->once()
            ->with(
                'GraphCitationLinker::unlinkAll - Failed to delete relationships',
                Mockery::on(function ($context) {
                    return $context['node_type'] === 'Decision'
                        && $context['node_id'] === 'decision-789'
                        && $context['error'] === 'Constraint violation';
                })
            );

        // Assert: Should throw GraphException
        $this->expectException(GraphException::class);
        $this->expectExceptionCode(GraphException::RELATIONSHIP_DELETE_FAILED);
        $this->expectExceptionMessage('Failed to delete relationships for Decision:decision-789');

        // Act: Call unlinkAll()
        $this->linker->unlinkAll('Decision', 'decision-789');
    }

    // ========================================
    // Test 4: Edge Case - Node With No Relationships
    // ========================================

    /**
     * Test that unlinkAll() handles nodes with no relationships
     * and returns 0 without errors.
     *
     * @test
     */
    public function test_unlink_all_handles_nodes_with_no_relationships(): void
    {
        // Arrange: Mock the graph service to return 0 deleted relationships
        $this->graphMock
            ->shouldReceive('query')
            ->once()
            ->with(
                Mockery::on(function ($query) {
                    return str_contains($query, 'MATCH (n:Law {id: $nodeId})')
                        && str_contains($query, '-[r:CITES|REFERENCES]-')
                        && str_contains($query, 'DELETE r')
                        && str_contains($query, 'RETURN count(r)');
                }),
                ['nodeId' => 'law-empty']
            )
            ->andReturn([
                ['count(r)' => 0], // No relationships to delete
            ]);

        // Mock Log facade to verify info is logged
        Log::shouldReceive('info')
            ->once()
            ->with(
                'GraphCitationLinker::unlinkAll - Relationships deleted',
                Mockery::on(function ($context) {
                    return $context['node_type'] === 'Law'
                        && $context['node_id'] === 'law-empty'
                        && $context['deleted_count'] === 0
                        && isset($context['duration_ms']);
                })
            );

        // Act: Call unlinkAll() on node with no relationships
        $deletedCount = $this->linker->unlinkAll('Law', 'law-empty');

        // Assert: Should return 0
        $this->assertEquals(0, $deletedCount);
    }

    // ========================================
    // Test 5: Enhanced Citation Detection with HrLegalCitationsDetector
    // ========================================

    /**
     * Test that link() extracts law citations by name without NN number
     * using the integrated HrLegalCitationsDetector.
     *
     * @test
     */
    public function test_link_extracts_law_citations_by_name_without_nn_number(): void
    {
        // Arrange: Content with law citation by name (no NN number)
        $content = "Sud nalazi da je tuženik postupio protivno članku 286. stavak 4. Zakona o sigurnosti prometa na cestama.";

        // Mock HrLegalCitationsDetector to return statute citations
        $mockDetector = Mockery::mock(HrLegalCitationsDetector::class);
        $mockDetector->shouldReceive('detectAll')
            ->once()
            ->andReturn([
                'statutes' => [
                    [
                        'law' => 'ZSPC',
                        'article' => '286',
                        'paragraph' => '4',
                        'canonical' => 'ZSPC:čl.286 st.4',
                    ],
                ],
                'case_numbers' => [],
                'ecli' => [],
                'narodne_novine' => [],
                'dates' => [],
            ]);

        // Mock DB lookup to find the law by title match
        DB::shouldReceive('table->where->first')
            ->andReturn((object) [
                'id' => 'law-zspc-1',
                'doc_id' => 'zspc-doc-1',
                'title' => 'Zakon o sigurnosti prometa na cestama',
                'law_number' => '67/08',
            ]);

        // Expect upsertNode for the law
        $this->graphMock->shouldReceive('upsertNode')
            ->once()
            ->with('LawDocument', 'law-zspc-1', Mockery::on(fn ($props) => $props['title'] === 'Zakon o sigurnosti prometa na cestama'
            ));

        // Expect createRelationship with article info
        $this->graphMock->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                'decision-1',
                'CITES',
                'LawDocument',
                'law-zspc-1',
                Mockery::on(fn ($props) => isset($props['article']) && $props['article'] === '286'
                )
            );

        // Act: Create linker with mocked detector
        $linker = new GraphCitationLinker($this->graphMock, $mockDetector);
        $linker->link('CourtDecisionDocument', 'decision-1', $content);

        // Assert: Mockery verifies expectations
        $this->assertTrue(true);
    }

    /**
     * Test that link() integrates both HrLegalCitationsDetector and legacy patterns
     *
     * @test
     */
    public function test_link_combines_detector_and_legacy_patterns(): void
    {
        // Arrange: Content with NN citation (legacy pattern)
        $content = "Primjenjuje se Zakon o radu (NN 93/14).";

        // Mock HrLegalCitationsDetector - returns NN citation
        $mockDetector = Mockery::mock(HrLegalCitationsDetector::class);
        $mockDetector->shouldReceive('detectAll')
            ->once()
            ->andReturn([
                'statutes' => [],
                'case_numbers' => [],
                'ecli' => [],
                'narodne_novine' => [
                    ['issues' => ['93/14']],
                ],
                'dates' => [],
            ]);

        // Mock DB lookup - finds law by NN number
        DB::shouldReceive('table')
            ->andReturnSelf();
        DB::shouldReceive('where')
            ->andReturnSelf();
        DB::shouldReceive('first')
            ->andReturn((object) [
                'id' => 'law-zor-1',
                'doc_id' => 'zor-doc-1',
                'title' => 'Zakon o radu',
                'law_number' => '93/14',
            ]);

        // Expect relationship creation for ZOR via NN pattern
        $this->graphMock->shouldReceive('upsertNode')
            ->atLeast()->once()
            ->with('LawDocument', 'law-zor-1', Mockery::any());
        $this->graphMock->shouldReceive('createRelationship')
            ->atLeast()->once();

        // Act
        $linker = new GraphCitationLinker($this->graphMock, $mockDetector);
        $linker->link('CourtDecisionDocument', 'decision-2', $content);

        // Assert
        $this->assertTrue(true);
    }
}
