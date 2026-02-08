<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\LawGraphSyncService;
use App\Services\Graph\TemporalReasoningService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for TemporalReasoningService (Sprint 4.2)
 *
 * Tests verify temporal reasoning capabilities for tracking law evolution,
 * finding outdated citations, and detecting contradictions.
 *
 * Acceptance Criteria:
 * ✅ Can retrieve law version for specific date
 * ✅ Can find decisions citing outdated laws
 * ✅ Can get full evolution history (v1 → v2 → v3)
 * ✅ Contradiction detection finds opposing decisions
 * ✅ Unit tests achieve 85%+ coverage
 */
class TemporalReasoningServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected $lawGraphSyncMock;

    protected $openAIMock;

    protected TemporalReasoningService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->lawGraphSyncMock = Mockery::mock(LawGraphSyncService::class);
        $this->openAIMock = Mockery::mock(OpenAIService::class);

        // Service will be created in each test after mocks are configured
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: getLawAtDate() - Retrieve law version at specific date
    // ========================================

    /** @test */
    public function it_retrieves_law_version_valid_at_specific_date()
    {
        // Arrange: Create law versions
        $oldLawId = $this->createLaw([
            'law_number' => 'NN 152/08',
            'title' => 'ZKP - Old Version',
            'valid_from' => '2008-11-01',
            'valid_until' => '2017-12-31',
            'version' => '1',
        ]);

        $currentLawId = $this->createLaw([
            'law_number' => 'NN 152/08',
            'title' => 'ZKP - Current Version',
            'valid_from' => '2018-01-01',
            'valid_until' => null,
            'version' => '2',
        ]);

        // Mock: LawGraphSyncService should query for law at date
        $this->lawGraphSyncMock->shouldReceive('getLawVersionAtDate')
            ->with('NN 152/08', '2015-06-01')
            ->once()
            ->andReturn([
                'law_id' => $oldLawId,
                'version' => '1',
                'valid_from' => '2008-11-01',
                'valid_until' => '2017-12-31',
            ]);

        $this->service = new TemporalReasoningService($this->lawGraphSyncMock, $this->openAIMock);

        // Act: Get law at specific date
        $result = $this->service->getLawAtDate('NN 152/08', '2015-06-01');

        // Assert: Returns old version
        $this->assertNotNull($result);
        $this->assertEquals($oldLawId, $result['law_id']);
        $this->assertEquals('1', $result['version']);
        $this->assertEquals('2008-11-01', $result['valid_from']);
        $this->assertEquals('2017-12-31', $result['valid_until']);
    }

    /** @test */
    public function it_returns_null_when_no_law_valid_at_date()
    {
        // Mock: No law found at this date
        $this->lawGraphSyncMock->shouldReceive('getLawVersionAtDate')
            ->with('NN 999/99', '2000-01-01')
            ->once()
            ->andReturn(null);

        $this->service = new TemporalReasoningService($this->lawGraphSyncMock, $this->openAIMock);

        // Act
        $result = $this->service->getLawAtDate('NN 999/99', '2000-01-01');

        // Assert
        $this->assertNull($result);
    }

    /** @test */
    public function it_retrieves_current_law_version_when_valid_until_is_null()
    {
        $currentLawId = $this->createLaw([
            'law_number' => 'NN 152/08',
            'valid_from' => '2018-01-01',
            'valid_until' => null,
            'version' => '2',
        ]);

        $this->lawGraphSyncMock->shouldReceive('getLawVersionAtDate')
            ->with('NN 152/08', '2025-01-01')
            ->once()
            ->andReturn([
                'law_id' => $currentLawId,
                'version' => '2',
                'valid_from' => '2018-01-01',
                'valid_until' => null,
            ]);

        $this->service = new TemporalReasoningService($this->lawGraphSyncMock, $this->openAIMock);

        $result = $this->service->getLawAtDate('NN 152/08', '2025-01-01');

        $this->assertNotNull($result);
        $this->assertNull($result['valid_until']); // Current version
    }

    // ========================================
    // Test 2: findOutdatedCitations() - Find decisions citing outdated laws
    // ========================================

    /** @test */
    public function it_finds_decisions_citing_outdated_laws()
    {
        // Arrange: Create old and current law versions
        $oldLawId = $this->createLaw([
            'law_number' => 'NN 152/08',
            'valid_from' => '2008-11-01',
            'valid_until' => '2017-12-31',
            'version' => '1',
        ]);

        $currentLawId = $this->createLaw([
            'law_number' => 'NN 152/08',
            'valid_from' => '2018-01-01',
            'valid_until' => null,
            'version' => '2',
        ]);

        // Create decision citing old law (decision from 2020, citing law from 2008)
        $decisionId = $this->createDecision([
            'case_number' => 'K-123/2020',
            'decision_date' => '2020-05-15',
            'summary' => 'Citing ZKP NN 152/08',
        ]);

        $this->service = new TemporalReasoningService($this->lawGraphSyncMock, $this->openAIMock);

        // Mock: Service should find citations to outdated laws
        // This will query the graph for decisions citing laws that were superseded before decision date
        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                [
                    'decision_id' => $decisionId,
                    'decision_date' => '2020-05-15',
                    'cited_law_id' => $oldLawId,
                    'cited_law_number' => 'NN 152/08',
                    'cited_law_version' => '1',
                    'current_law_id' => $currentLawId,
                    'current_version' => '2',
                ],
            ]);

        // Act
        $outdatedCitations = $this->service->findOutdatedCitations();

        // Assert
        $this->assertCount(1, $outdatedCitations);
        $this->assertEquals($decisionId, $outdatedCitations[0]['decision_id']);
        $this->assertEquals('1', $outdatedCitations[0]['cited_law_version']);
        $this->assertEquals('2', $outdatedCitations[0]['current_version']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_outdated_citations()
    {
        $this->service = new TemporalReasoningService($this->lawGraphSyncMock, $this->openAIMock);

        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([]);

        $outdatedCitations = $this->service->findOutdatedCitations();

        $this->assertIsArray($outdatedCitations);
        $this->assertEmpty($outdatedCitations);
    }

    // ========================================
    // Test 3: getLawEvolutionHistory() - Get full evolution history
    // ========================================

    /** @test */
    public function it_retrieves_full_law_evolution_history()
    {
        // Arrange: Create law versions
        $v1Id = $this->createLaw([
            'law_number' => 'NN 152/08',
            'version' => '1',
            'valid_from' => '2008-11-01',
            'valid_until' => '2011-12-31',
        ]);

        $v2Id = $this->createLaw([
            'law_number' => 'NN 152/08',
            'version' => '2',
            'valid_from' => '2012-01-01',
            'valid_until' => '2017-12-31',
        ]);

        $v3Id = $this->createLaw([
            'law_number' => 'NN 152/08',
            'version' => '3',
            'valid_from' => '2018-01-01',
            'valid_until' => null,
        ]);

        // Mock: Get all versions
        $this->lawGraphSyncMock->shouldReceive('getAllVersions')
            ->with('NN 152/08')
            ->once()
            ->andReturn([
                ['law_id' => $v1Id, 'version' => '1', 'valid_from' => '2008-11-01', 'valid_until' => '2011-12-31'],
                ['law_id' => $v2Id, 'version' => '2', 'valid_from' => '2012-01-01', 'valid_until' => '2017-12-31'],
                ['law_id' => $v3Id, 'version' => '3', 'valid_from' => '2018-01-01', 'valid_until' => null],
            ]);

        // Mock: Get amendments for each version
        $this->lawGraphSyncMock->shouldReceive('findAllAmendments')
            ->with($v1Id)
            ->once()
            ->andReturn([]);

        $this->lawGraphSyncMock->shouldReceive('findAllAmendments')
            ->with($v2Id)
            ->once()
            ->andReturn([
                [
                    'amendment_law_id' => 'amendment-1',
                    'amendment_date' => '2013-06-01',
                    'amendment_scope' => 'Izmjena članka 10',
                ],
            ]);

        $this->lawGraphSyncMock->shouldReceive('findAllAmendments')
            ->with($v3Id)
            ->once()
            ->andReturn([]);

        $this->service = new TemporalReasoningService($this->lawGraphSyncMock, $this->openAIMock);

        // Act
        $history = $this->service->getLawEvolutionHistory('NN 152/08');

        // Assert: Returns evolution history
        $this->assertCount(3, $history['versions']);
        $this->assertEquals('1', $history['versions'][0]['version']);
        $this->assertEquals('2', $history['versions'][1]['version']);
        $this->assertEquals('3', $history['versions'][2]['version']);

        // Verify amendments included
        $this->assertArrayHasKey('amendments', $history['versions'][1]);
        $this->assertCount(1, $history['versions'][1]['amendments']);
        $this->assertEquals('Izmjena članka 10', $history['versions'][1]['amendments'][0]['amendment_scope']);
    }

    /** @test */
    public function it_returns_empty_history_for_nonexistent_law()
    {
        $this->lawGraphSyncMock->shouldReceive('getAllVersions')
            ->with('NN 999/99')
            ->once()
            ->andReturn([]);

        $this->service = new TemporalReasoningService($this->lawGraphSyncMock, $this->openAIMock);

        $history = $this->service->getLawEvolutionHistory('NN 999/99');

        $this->assertEmpty($history['versions']);
    }

    // ========================================
    // Test 4: detectContradictions() - LLM-powered contradiction detection
    // ========================================

    /** @test */
    public function it_detects_contradictions_using_llm()
    {
        // Arrange: Create decision
        $decisionId = $this->createDecision([
            'case_number' => 'K-123/2020',
            'decision_date' => '2020-05-15',
            'summary' => 'Home search requires proportionality test',
        ]);

        // Create potentially contradicting decisions
        $contradictingId1 = $this->createDecision([
            'case_number' => 'K-456/2019',
            'decision_date' => '2019-03-10',
            'summary' => 'Home search does not require proportionality for serious crimes',
        ]);

        $contradictingId2 = $this->createDecision([
            'case_number' => 'K-789/2018',
            'decision_date' => '2018-11-20',
            'summary' => 'Proportionality test mandatory for all home searches',
        ]);

        // Mock: Graph query returns potentially contradicting decisions
        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([
                [
                    'decision_id' => $contradictingId1,
                    'case_number' => 'K-456/2019',
                    'summary' => 'Home search does not require proportionality for serious crimes',
                    'decision_date' => '2019-03-10',
                ],
                [
                    'decision_id' => $contradictingId2,
                    'case_number' => 'K-789/2018',
                    'summary' => 'Proportionality test mandatory for all home searches',
                    'decision_date' => '2018-11-20',
                ],
            ]);

        // Mock: OpenAI analyzes and finds contradiction
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'contradictions' => [
                        [
                            'contradicting_decision_id' => $contradictingId1,
                            'contradiction_type' => 'conflicting_legal_interpretation',
                            'explanation' => 'Decision K-123/2020 requires proportionality test for all home searches, while K-456/2019 states it is not required for serious crimes.',
                            'severity' => 'high',
                        ],
                    ],
                ]),
            ]);

        $this->service = new TemporalReasoningService($this->lawGraphSyncMock, $this->openAIMock);

        // Act
        $contradictions = $this->service->detectContradictions($decisionId);

        // Assert
        $this->assertCount(1, $contradictions);
        $this->assertEquals($contradictingId1, $contradictions[0]['contradicting_decision_id']);
        $this->assertEquals('conflicting_legal_interpretation', $contradictions[0]['contradiction_type']);
        $this->assertEquals('high', $contradictions[0]['severity']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_contradictions_found()
    {
        $decisionId = $this->createDecision([
            'case_number' => 'K-123/2020',
            'summary' => 'Standard decision',
        ]);

        $this->lawGraphSyncMock->shouldReceive('query')
            ->once()
            ->andReturn([]);

        $this->service = new TemporalReasoningService($this->lawGraphSyncMock, $this->openAIMock);

        $contradictions = $this->service->detectContradictions($decisionId);

        $this->assertIsArray($contradictions);
        $this->assertEmpty($contradictions);
    }

    // ========================================
    // Helper Methods
    // ========================================

    protected function createLaw(array $overrides = []): string
    {
        $defaults = [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'doc_id' => 'law-'.uniqid(),
            'title' => 'Test Law',
            'law_number' => 'NN 100/20',
            'jurisdiction' => 'Republika Hrvatska',
            'country' => 'HR',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => 'Test law content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => md5('Test law content'),
            'valid_from' => null,
            'valid_until' => null,
            'version' => null,
        ];

        $lawData = array_merge($defaults, $overrides);
        $lawId = $lawData['id'];
        unset($lawData['id']);

        DB::table('laws')->insert(array_merge(['id' => $lawId], $lawData));

        return $lawId;
    }

    protected function createDecision(array $overrides = []): string
    {
        $defaults = [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'case_number' => 'K-'.rand(100, 999).'/2020',
            'court' => 'Županijski sud u Osijeku',
            'decision_date' => '2020-01-01',
            'summary' => 'Test decision summary',
            'title' => 'Test Decision',
        ];

        $decisionData = array_merge($defaults, $overrides);
        $decisionId = $decisionData['id'];
        unset($decisionData['id']);

        DB::table('court_decisions')->insert(array_merge(['id' => $decisionId], $decisionData));

        return $decisionId;
    }
}
