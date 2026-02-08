<?php

namespace Tests\Integration;

use App\Models\AgentVectorMemory;
use App\Services\FederatedMemoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TDD Tests for FederatedMemoryService
 *
 * Sprint 5.7: Agent Memory Federation
 *
 * Tests federated memory search across all agent memories:
 * - Search across agent memories
 * - Store new insights
 * - Access tracking (increment access_count)
 * - Cross-agent memory sharing
 * - Performance: search <100ms
 *
 * Following strict TDD: Tests written FIRST, will fail until implementation.
 */
class FederatedMemoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected FederatedMemoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI API for embedding generation (offline testing)
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ], 200),
        ]);

        $this->service = app(FederatedMemoryService::class);
    }

    /** @test */
    public function it_can_search_across_all_agent_memories()
    {
        // Create memories from different agents
        AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Proportionality test for home searches requires evidence of serious crime',
            'metadata' => ['topic' => 'proportionality', 'source' => 'case_law'],
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'research_agent',
            'content' => 'Home search warrants must specify exact locations and items to be searched',
            'metadata' => ['topic' => 'warrants', 'source' => 'law'],
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'precedent_analyzer',
            'content' => 'Proportionality analysis includes harm to suspect vs public interest',
            'metadata' => ['topic' => 'proportionality', 'source' => 'doctrine'],
        ]);

        // Search across all agents
        $results = $this->service->searchCrossAgent('proportionality home search', null, 10);

        // Should return memories from multiple agents
        $this->assertNotEmpty($results);
        $this->assertIsArray($results);

        // Should contain memories with relevant content
        $agentNames = collect($results)->pluck('agent_name')->unique();
        $this->assertGreaterThan(1, $agentNames->count(), 'Should find memories from multiple agents');
    }

    /** @test */
    public function it_can_filter_by_specific_agent_type()
    {
        // Create memories from different agents
        AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Case law about proportionality',
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'research_agent',
            'content' => 'Research about proportionality principles',
        ]);

        // Search only decision_discovery agent
        $results = $this->service->searchCrossAgent('proportionality', 'decision_discovery', 10);

        // All results should be from decision_discovery
        $this->assertNotEmpty($results);

        foreach ($results as $result) {
            $this->assertEquals('decision_discovery', $result['agent_name']);
        }
    }

    /** @test */
    public function it_respects_result_limit()
    {
        // Create many memories
        AgentVectorMemory::factory()->count(20)->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Legal principle about proportionality test',
        ]);

        // Search with limit of 5
        $results = $this->service->searchCrossAgent('proportionality', null, 5);

        // Should return exactly 5 results
        $this->assertCount(5, $results);
    }

    /** @test */
    public function it_can_store_new_insights()
    {
        $insight = 'Proportionality test requires balancing individual rights against public interest';
        $metadata = [
            'topic' => 'proportionality',
            'confidence' => 0.85,
            'source' => 'court_decision',
        ];

        $memoryId = $this->service->storeInsight('research_agent', $insight, $metadata);

        // Should return memory ID
        $this->assertNotNull($memoryId);
        $this->assertIsString($memoryId);

        // Memory should exist in database
        $memory = AgentVectorMemory::find($memoryId);
        $this->assertNotNull($memory);
        $this->assertEquals('research_agent', $memory->agent_name);
        $this->assertEquals($insight, $memory->content);
        $this->assertEquals($metadata, $memory->metadata);
    }

    /** @test */
    public function it_increments_access_count_on_retrieval()
    {
        // Create a memory
        $memory = AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Important legal insight about proportionality',
            'access_count' => 0,
        ]);

        // Search for it (should retrieve it)
        $results = $this->service->searchCrossAgent('proportionality', null, 10);

        // Access count should increment
        $memory->refresh();
        $this->assertGreaterThan(0, $memory->access_count, 'Access count should increment on retrieval');
    }

    /** @test */
    public function it_tracks_multiple_accesses()
    {
        // Create a memory
        $memory = AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Popular insight about proportionality test',
            'access_count' => 5,
        ]);

        // Access it multiple times
        for ($i = 0; $i < 3; $i++) {
            $this->service->searchCrossAgent('proportionality', null, 10);
        }

        // Access count should increase by 3
        $memory->refresh();
        $this->assertGreaterThanOrEqual(8, $memory->access_count, 'Access count should increment with each retrieval');
    }

    /** @test */
    public function it_validates_cross_agent_memory_sharing()
    {
        // Agent A stores an insight
        $insightId = $this->service->storeInsight(
            'decision_discovery',
            'Proportionality principle from ECHR case law',
            ['source' => 'echr', 'confidence' => 0.90]
        );

        // Agent B should be able to find it (search across all agents)
        $results = $this->service->searchCrossAgent('proportionality ECHR', null, 10);

        // Searching across all agents should find decision_discovery's insight
        // This tests true cross-agent memory sharing
        $foundInsight = false;
        foreach ($results as $result) {
            if ($result['id'] === $insightId || $result['content'] === 'Proportionality principle from ECHR case law') {
                $foundInsight = true;
                break;
            }
        }

        $this->assertTrue($foundInsight, 'Agent B should be able to find Agent A\'s insights');
    }

    /** @test */
    public function it_returns_relevant_metadata_with_results()
    {
        AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Proportionality test framework',
            'metadata' => [
                'topic' => 'proportionality',
                'confidence' => 0.85,
                'source' => 'case_law',
                'date' => '2025-01-15',
            ],
        ]);

        $results = $this->service->searchCrossAgent('proportionality', null, 10);

        $this->assertNotEmpty($results);

        // Each result should include key fields
        foreach ($results as $result) {
            $this->assertArrayHasKey('id', $result);
            $this->assertArrayHasKey('agent_name', $result);
            $this->assertArrayHasKey('content', $result);
            $this->assertArrayHasKey('metadata', $result);
            $this->assertArrayHasKey('access_count', $result);
        }
    }

    /** @test */
    public function it_handles_empty_search_results_gracefully()
    {
        // Search for something that doesn't exist
        $results = $this->service->searchCrossAgent('nonexistent topic xyz', null, 10);

        // Should return empty array, not null or error
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function it_validates_required_parameters_for_search()
    {
        $this->expectException(\InvalidArgumentException::class);

        // Empty query should throw exception
        $this->service->searchCrossAgent('', null, 10);
    }

    /** @test */
    public function it_validates_required_parameters_for_store()
    {
        $this->expectException(\InvalidArgumentException::class);

        // Empty agent type should throw exception
        $this->service->storeInsight('', 'Some content', []);
    }

    /** @test */
    public function it_performs_search_within_100ms()
    {
        // Create multiple memories for realistic test
        AgentVectorMemory::factory()->count(50)->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Various legal insights about proportionality and other topics',
        ]);

        $startTime = microtime(true);

        $results = $this->service->searchCrossAgent('proportionality', null, 10);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Should complete within 100ms
        $this->assertLessThan(100, $duration, "Search took {$duration}ms, should be < 100ms");

        // Should still return valid results
        $this->assertNotEmpty($results);
    }

    /** @test */
    public function it_returns_most_relevant_results_first()
    {
        // Create memories with varying relevance
        AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Proportionality test is the primary framework for evaluating home searches',
            'metadata' => ['relevance' => 'high'],
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'research_agent',
            'content' => 'Some tangentially related concept',
            'metadata' => ['relevance' => 'low'],
        ]);

        $results = $this->service->searchCrossAgent('proportionality test home search', null, 10);

        // First result should be most relevant (contains exact match)
        $this->assertNotEmpty($results);

        $firstResult = $results[0];
        $this->assertStringContainsString('proportionality', strtolower($firstResult['content']));
    }

    /** @test */
    public function it_stores_insights_with_embeddings()
    {
        $memoryId = $this->service->storeInsight(
            'research_agent',
            'New insight about proportionality principles',
            ['topic' => 'proportionality']
        );

        $memory = AgentVectorMemory::find($memoryId);

        // Should have embedding generated
        $this->assertNotNull($memory->embedding_vector);
        $this->assertIsArray($memory->embedding_vector);
        $this->assertGreaterThan(0, count($memory->embedding_vector));
    }

    /** @test */
    public function it_supports_namespaces_for_memory_organization()
    {
        // Store insights in different namespaces
        $id1 = $this->service->storeInsight(
            'decision_discovery',
            'Proportionality in criminal law',
            ['namespace' => 'criminal_law']
        );

        $id2 = $this->service->storeInsight(
            'decision_discovery',
            'Proportionality in administrative law',
            ['namespace' => 'administrative_law']
        );

        // Both should be stored
        $this->assertNotNull($id1);
        $this->assertNotNull($id2);

        $memory1 = AgentVectorMemory::find($id1);
        $memory2 = AgentVectorMemory::find($id2);

        // Should have correct namespaces
        $this->assertArrayHasKey('namespace', $memory1->metadata);
        $this->assertArrayHasKey('namespace', $memory2->metadata);
    }

    /** @test */
    public function it_provides_access_statistics()
    {
        // Create memories with varying access counts
        AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Popular insight',
            'access_count' => 50,
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'research_agent',
            'content' => 'Rarely accessed insight',
            'access_count' => 2,
        ]);

        $stats = $this->service->getAccessStatistics();

        // Should return statistics
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_memories', $stats);
        $this->assertArrayHasKey('total_accesses', $stats);
        $this->assertArrayHasKey('most_accessed', $stats);
    }
}
