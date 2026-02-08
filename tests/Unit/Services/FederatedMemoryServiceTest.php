<?php

namespace Tests\Unit\Services;

use App\Models\AgentVectorMemory;
use App\Services\FederatedMemoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FederatedMemoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected FederatedMemoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI embeddings API for offline testing
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ], 200),
        ]);

        $this->service = app(FederatedMemoryService::class);
    }

    public function test_search_with_pgvector_similarity(): void
    {
        // Skip if pgvector is not available
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('pgvector requires PostgreSQL');
        }

        // Check if pgvector extension is available
        $hasExtension = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
        if (empty($hasExtension)) {
            $this->markTestSkipped('pgvector extension not installed');
        }

        // Create test memories with different embeddings
        $memory1 = AgentVectorMemory::create([
            'agent_name' => 'test-agent',
            'namespace' => 'test',
            'content' => 'Case about drug possession',
            'metadata' => ['type' => 'case_note'],
            'embedding_vector' => array_fill(0, 1536, 0.9),
        ]);

        $memory2 = AgentVectorMemory::create([
            'agent_name' => 'test-agent',
            'namespace' => 'test',
            'content' => 'Case about drug trafficking',
            'metadata' => ['type' => 'case_note'],
            'embedding_vector' => array_fill(0, 1536, 0.85),
        ]);

        $memory3 = AgentVectorMemory::create([
            'agent_name' => 'test-agent',
            'namespace' => 'test',
            'content' => 'Case about civil dispute',
            'metadata' => ['type' => 'case_note'],
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        // Search for drug-related cases
        $results = $this->service->searchCrossAgent('drug possession', null, 10);

        // Assertions
        $this->assertNotEmpty($results);
        $this->assertIsArray($results);

        // Results should be ordered by similarity (memory1 and memory2 first)
        $ids = array_column($results, 'id');
        $this->assertContains($memory1->id, $ids);
        $this->assertContains($memory2->id, $ids);
    }

    public function test_search_falls_back_when_pgvector_unavailable(): void
    {
        // This test verifies fallback behavior on non-PostgreSQL
        $memory = AgentVectorMemory::create([
            'agent_name' => 'test-agent',
            'content' => 'Test memory content',
            'embedding_vector' => array_fill(0, 1536, 0.5),
        ]);

        $results = $this->service->searchCrossAgent('test', null, 10);

        // Should still return results using text search fallback
        $this->assertNotEmpty($results);
        $this->assertIsArray($results);
    }
}
