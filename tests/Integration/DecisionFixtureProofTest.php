<?php

namespace Tests\Integration;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use App\Services\LegalCitations\StatuteCitationDetector;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * DecisionFixtureProofTest
 *
 * Goal:
 * - Provide a fixture-based, execution-proof test that a real-ish Croatian court decision text
 *   can be ingested into Postgres, and (when Neo4j is available) projected into Neo4j with
 *   correct metadata and relationships.
 *
 * Why this matters:
 * - This turns "we think the pipeline works" into a repeatable proof.
 * - Uses a stable fixture to detect regressions in ingestion schema, embeddings plumbing,
 *   and graph sync mapping.
 */
class DecisionFixtureProofTest extends TestCase
{
    use UsesTestDatabase;

    private function fixtureText(): string
    {
        $path = base_path('tests/Fixtures/decisions/ikz-8-2023-7.txt');
        $txt = File::exists($path) ? File::get($path) : '';
        $this->assertNotEmpty($txt, 'Fixture decision text must exist and be non-empty');

        return $txt;
    }

    private function mockEmbeddings(int $dims = 1536): void
    {
        // Mirror the style used across tests: mock OpenAIService::embeddings to avoid network.
        $mock = Mockery::mock(OpenAIService::class);
        $mock->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, $dims, 0.01)],
                ],
            ]);

        $this->app->instance(OpenAIService::class, $mock);
    }

    /**
     * Invariant: A real decision fixture can be ingested and results in:
     * - 1 CourtDecision row with stable metadata
     * - >=1 CourtDecisionDocument chunk rows with content_hash + embedding
     *
     * Reasoning:
     * - If this fails, your most important retrieval corpus (court decisions) is broken.
     */
    public function test_fixture_decision_ingests_to_relational_db_with_embeddings(): void
    {
        $this->mockEmbeddings();

        if (! Schema::hasTable((new CourtDecision)->getTable())) {
            $this->markTestSkipped('court_decisions table missing');
        }
        if (! Schema::hasTable((new CourtDecisionDocument)->getTable())) {
            $this->markTestSkipped('court_decision_documents table missing');
        }

        $decisionId = (string) Str::ulid();

        $decision = new CourtDecision([
            'id' => $decisionId,
            'case_number' => 'I Kž-8/2023-7',
            'title' => 'PRESUDA',
            'court' => 'Visoki kazneni sud Republike Hrvatske',
            'jurisdiction' => 'HR',
            'judge' => 'Ivan Turudić',
            'decision_date' => '2023-04-12',
            'decision_type' => 'Presuda',
            'tags' => ['kazneno', 'presuda'],
        ]);
        $decision->save();

        $svc = app(CourtDecisionVectorStoreService::class);

        $text = $this->fixtureText();

        $res = $svc->ingest(
            decisionId: $decisionId,
            docId: 'I Kž-8/2023-7',
            docs: [
                [
                    'content' => $text,
                    'metadata' => [
                        // Minimal metadata; can be expanded later
                        'source' => 'fixture',
                        'poslovni_broj' => 'I Kž-8/2023-7',
                    ],
                    'chunk_index' => 0,
                ],
            ],
            options: [
                'model' => 'test-embed',
                'provider' => 'openai',
            ]
        );

        $this->assertGreaterThanOrEqual(1, (int) ($res['inserted'] ?? 0), 'At least one chunk must be inserted');

        // Relational assertions
        $this->assertDatabaseHas('court_decisions', [
            'id' => $decisionId,
            'case_number' => 'I Kž-8/2023-7',
            'court' => 'Visoki kazneni sud Republike Hrvatske',
            'jurisdiction' => 'HR',
        ]);

        $doc = CourtDecisionDocument::query()->where('decision_id', $decisionId)->orderBy('chunk_index')->first();
        $this->assertNotNull($doc, 'Expected at least one court_decision_documents row');
        $this->assertSame(0, (int) $doc->chunk_index);
        $this->assertNotEmpty($doc->content_hash);

        // Embedding must exist (column name differs by driver; model returns attribute)
        $this->assertNotEmpty($doc->embedding, 'Embedding must be present for ingested decision document');

        // Content must contain known markers from the fixture
        $this->assertStringContainsString('I Kž-8/2023', $doc->content);
        $this->assertStringContainsString('Visoki kazneni sud Republike Hrvatske', $doc->content);
        $this->assertStringContainsString('članka 110.', $doc->content);
        $this->assertStringContainsString('ZKP/08', $doc->content);
    }

    /**
     * Invariant: Statute citation detector finds Croatian law/article citations in the fixture.
     *
     * Reasoning:
     * - Legal RAG and provenance must have reliable citation extraction.
     * - This is independent of Neo4j availability and avoids LLM dependence.
     */
    public function test_fixture_decision_has_detectable_statute_citations(): void
    {
        $text = $this->fixtureText();

        $detector = app(StatuteCitationDetector::class);
        $citations = $detector->detectAll($text);

        $this->assertIsArray($citations);
        $this->assertNotEmpty($citations, 'Expected at least one detected citation');

        // Assert we detect at least one KZ article and one ZKP article by heuristic search over results.
        $flat = json_encode($citations, JSON_UNESCAPED_UNICODE);
        $this->assertNotFalse($flat);

        // KZ/11, čl. 110 and 34 should be detectable from the fixture text.
        $this->assertStringContainsString('110', $flat);
        $this->assertStringContainsString('34', $flat);

        // ZKP articles are present as well.
        $this->assertStringContainsString('474', $flat);
        $this->assertStringContainsString('189', $flat);
    }

    /**
     * Invariant: When Neo4j is available, syncing a decision projects it into the graph with:
     * - CourtDecisionDocument node carrying judge/court/case_number metadata
     * - DECIDED_BY edge to Court
     *
     * Reasoning:
     * - Graph reasoning depends on these edges for traversal and filtering.
     */
    public function test_fixture_decision_projects_to_graph_with_expected_relationships_when_neo4j_available(): void
    {
        if (! config('neo4j.sync.enabled', true)) {
            $this->markTestSkipped('Neo4j sync disabled');
        }

        /** @var GraphDatabaseService $graph */
        $graph = app(GraphDatabaseService::class);
        if (! $graph->isAvailable()) {
            $this->markTestSkipped('Neo4j not available');
        }

        $this->mockEmbeddings();

        // Create + ingest (same as previous test) so we have DB rows.
        $decisionId = (string) Str::ulid();
        $decision = new CourtDecision([
            'id' => $decisionId,
            'case_number' => 'I Kž-8/2023-7',
            'title' => 'PRESUDA',
            'court' => 'Visoki kazneni sud Republike Hrvatske',
            'jurisdiction' => 'HR',
            'judge' => 'Ivan Turudić',
            'decision_date' => '2023-04-12',
            'decision_type' => 'Presuda',
        ]);
        $decision->save();

        $svc = app(CourtDecisionVectorStoreService::class);
        $svc->ingest(
            decisionId: $decisionId,
            docId: 'I Kž-8/2023-7',
            docs: [[
                'content' => $this->fixtureText(),
                'metadata' => ['source' => 'fixture'],
                'chunk_index' => 0,
            ]],
            options: ['model' => 'test-embed']
        );

        $doc = CourtDecisionDocument::query()->where('decision_id', $decisionId)->first();
        $this->assertNotNull($doc);

        // Explicitly sync decision -> graph using orchestrator (expects decisionId).
        /** @var GraphRagOrchestrator $orchestrator */
        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncCourtDecision($decisionId);

        // Assert graph node exists with correct key properties.
        $node = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id}) RETURN d.decision_id as decision_id, d.case_number as case_number, d.court as court, d.judge as judge',
            ['id' => (string) $doc->id]
        );

        $this->assertCount(1, $node);
        $this->assertSame($decisionId, (string) $node[0]->get('decision_id'));
        $this->assertSame('I Kž-8/2023-7', (string) $node[0]->get('case_number'));
        $this->assertSame('Visoki kazneni sud Republike Hrvatske', (string) $node[0]->get('court'));
        $this->assertSame('Ivan Turudić', (string) $node[0]->get('judge'));

        // Assert court relationship exists.
        $rel = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id})-[:DECIDED_BY]->(c:Court) RETURN c.name as name',
            ['id' => (string) $doc->id]
        );

        $this->assertCount(1, $rel);
        $this->assertSame('Visoki kazneni sud Republike Hrvatske', (string) $rel[0]->get('name'));
    }
}
