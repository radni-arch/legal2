<?php

namespace Tests\Evaluation;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\Search\DecisionSearchService;
use App\Services\Search\SearchEmbeddingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * @group evaluation
 * @group retrieval
 *
 * Evaluation-only tests (opt-in): deterministic “gold fixture” retrieval.
 *
 * Run with:
 *   EVAL_TESTS=1 php artisan test --group=evaluation
 */
class DecisionVectorRetrievalEvaluationTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('EVAL_TESTS')) {
            $this->markTestSkipped('Evaluation tests are disabled. Set EVAL_TESTS=1 to enable.');
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires PostgreSQL for pgvector similarity ordering.');
        }

        if (! Schema::hasTable('court_decision_documents') || ! Schema::hasTable('court_decisions')) {
            $this->markTestSkipped('Required decision tables not present.');
        }

        // Specifically exercises Search\DecisionSearchService pgvector path (uses cdd.embedding)
        if (! Schema::hasColumn('court_decision_documents', 'embedding')) {
            $this->markTestSkipped('Requires pgvector embedding column on court_decision_documents.');
        }

        try {
            $hasVector = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
            if (empty($hasVector)) {
                $this->markTestSkipped('pgvector extension not available.');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not verify pgvector extension availability: '.$e->getMessage());
        }
    }

    /** @test */
    public function it_retrieves_the_planted_gold_decision_first_for_illegal_home_search_query(): void
    {
        // Arrange
        $goldDecision = CourtDecision::factory()->create([
            'title' => 'VSRH - nezakoniti pretres doma',
            'court' => 'Vrhovni sud Republike Hrvatske',
            'jurisdiction' => 'HR',
            'decision_type' => 'presuda',
        ]);

        $distractor1 = CourtDecision::factory()->create([
            'title' => 'ŽS - prometni prekršaji',
            'court' => 'Županijski sud u Zagrebu',
            'jurisdiction' => 'HR',
            'decision_type' => 'presuda',
        ]);

        $distractor2 = CourtDecision::factory()->create([
            'title' => 'OS - ugovorno pravo',
            'court' => 'Općinski sud u Zagrebu',
            'jurisdiction' => 'HR',
            'decision_type' => 'rješenje',
        ]);

        $goldEmbedding = $this->unitVector(0);
        $otherEmbedding1 = $this->unitVector(1);
        $otherEmbedding2 = $this->unitVector(2);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $goldDecision->id,
            'content' => 'Pretres doma proveden bez valjanog naloga; nezakoniti dokazi; ZKP čl. 222.',
            'content_hash' => hash('sha256', 'gold'),
            'chunk_index' => 0,
            'embedding' => $goldEmbedding,
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $distractor1->id,
            'content' => 'Prometni prekršaj i kazna.',
            'content_hash' => hash('sha256', 'd1'),
            'chunk_index' => 0,
            'embedding' => $otherEmbedding1,
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $distractor2->id,
            'content' => 'Ugovorna odgovornost i naknada štete.',
            'content_hash' => hash('sha256', 'd2'),
            'chunk_index' => 0,
            'embedding' => $otherEmbedding2,
        ]);

        // Mock query embedding generation (avoids real OpenAI; SearchEmbeddingService normally calls embeddings API)
        $embedder = Mockery::mock(SearchEmbeddingService::class);
        $embedder->shouldReceive('embedQuery')
            ->once()
            ->andReturn($goldEmbedding);

        $service = new DecisionSearchService($embedder);

        // Act
        $results = $service->search('nezakonit pretres doma nezakoniti dokazi', [
            'limit' => 3,
            'threshold' => 0.0,
            'filters' => ['jurisdiction' => 'HR'],
        ]);

        // Assert
        $this->assertCount(3, $results);
        $this->assertEquals('decision', $results[0]['type']);
        $this->assertEquals($goldDecision->id, $results[0]['metadata']['decision_id']);

        $this->assertGreaterThanOrEqual($results[1]['score'], $results[2]['score']);
        $this->assertGreaterThan($results[1]['score'], 0.0);
        $this->assertGreaterThan($results[0]['score'], $results[1]['score']);
    }

    private function unitVector(int $index): array
    {
        $dims = 1536;
        $v = array_fill(0, $dims, 0.0);
        if ($index >= 0 && $index < $dims) {
            $v[$index] = 1.0;
        }
        return $v;
    }
}
