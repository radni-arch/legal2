<?php

namespace Tests\Feature\Fixtures;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\Law;
use App\Services\Graph\DecisionGraphSyncService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * CourtDecisionFixtureGraphProofTest
 *
 * Goal (fixture-based proof):
 * - Take a real-ish decision text fixture and assert that the ingestion + graph sync produces
 *   structurally correct graph entities.
 *
 * Why needed:
 * - Invariants that query existing DB data are great for drift detection.
 * - Fixture-based proofs are stronger: they create known input, run the real sync code, and
 *   assert the resulting DB + Neo4j state. This is closer to an end-to-end "execution proof".
 */
class CourtDecisionFixtureGraphProofTest extends TestCase
{
    use UsesTestDatabase;

    protected array $graphIdsToCleanup = [];

    protected function tearDown(): void
    {
        try {
            /** @var GraphDatabaseService $graph */
            $graph = app(GraphDatabaseService::class);
            if (! empty($this->graphIdsToCleanup) && $graph->isAvailable()) {
                $graph->run('MATCH (n) WHERE n.id IN $ids DETACH DELETE n', [
                    'ids' => array_values(array_unique($this->graphIdsToCleanup)),
                ]);
            }
        } catch (\Throwable $e) {
            // Cleanup is best-effort.
        }

        parent::tearDown();
    }

    private function requireGraphOrSkip(): void
    {
        if (! config('neo4j.sync.enabled', true)) {
            $this->markTestSkipped('Neo4j sync is disabled (neo4j.sync.enabled=false)');
        }

        /** @var GraphDatabaseService $graph */
        $graph = app(GraphDatabaseService::class);
        if (! $graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available for fixture-based graph proofs');
        }
    }

    /** @test */
    public function fixture_decision_sync_creates_expected_graph_nodes_and_relationships(): void
    {
        $this->requireGraphOrSkip();

        if (! Schema::hasTable('court_decisions') || ! Schema::hasTable('court_decision_documents')) {
            $this->markTestSkipped('Decision tables are missing');
        }

        $fixturePath = base_path('tests/Fixtures/odluke/I_Kz-8_2023-7.txt');
        $this->assertFileExists($fixturePath);

        $content = file_get_contents($fixturePath);
        $this->assertNotEmpty($content);

        // Arrange: Seed referenced laws so GraphCitationLinker can create CITES edges.
        // GraphCitationLinker matches "Narodne novine" and extracts numbers like "125/11" and "152/08"
        // then does: DB::table('laws')->where('law_number', $value)->first() before creating CITES.\
        // See GraphCitationLinker::link() for this behavior.
        $lawA = Law::factory()->create([
            'law_number' => '125/11',
            'title' => 'Kazneni zakon (fixture)',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => 'Fixture law content',
        ]);
        $lawB = Law::factory()->create([
            'law_number' => '152/08',
            'title' => 'Zakon o kaznenom postupku (fixture)',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => 'Fixture law content',
        ]);

        $this->graphIdsToCleanup[] = (string) $lawA->id;
        $this->graphIdsToCleanup[] = (string) $lawB->id;

        // Arrange: Create CourtDecision + a single document chunk.
        $decision = CourtDecision::create([
            'id' => (string) Str::ulid(),
            'case_number' => 'I Kž-8/2023-7',
            'title' => 'PRESUDA',
            'court' => 'Visoki kazneni sud Republike Hrvatske',
            'jurisdiction' => 'HR',
            'judge' => 'Ivan Turudić',
            'decision_date' => '2023-04-12',
            'publication_date' => null,
            'decision_type' => 'Presuda',
            'register' => 'Kž',
            'finality' => null,
            'ecli' => null,
            'tags' => ['fixture'],
            'description' => 'Fixture-based decision for graph proof',
        ]);

        $docId = 'fixture:odluke:I-Kz-8-2023-7';
        $decisionDoc = CourtDecisionDocument::create([
            'id' => (string) Str::ulid(),
            'decision_id' => $decision->id,
            'doc_id' => $docId,
            'title' => 'PRESUDA',
            'category' => 'fixture',
            'author' => null,
            'court' => $decision->court,
            'language' => 'hr',
            'tags' => ['fixture'],
            'chunk_index' => 0,
            'content' => $content,
            'metadata' => [
                'src' => 'tests/Fixtures/odluke/I_Kz-8_2023-7.txt',
                'case_number' => $decision->case_number,
            ],
            'source' => 'fixture',
            'source_id' => 'I_Kz-8_2023-7',
            'content_hash' => hash('sha256', $content),
        ]);

        // Act: sync the decision into Neo4j.
        /** @var DecisionGraphSyncService $sync */
        $sync = app(DecisionGraphSyncService::class);
        $sync->sync($decision->id);

        /** @var GraphDatabaseService $graph */
        $graph = app(GraphDatabaseService::class);

        // Assert: CourtDecisionDocument node exists and carries key properties.
        $node = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id}) RETURN d.case_number as case_number, d.court as court, d.judge as judge, d.decision_date as decision_date, d.decision_type as decision_type',
            ['id' => $decisionDoc->id]
        );

        $this->assertCount(1, $node);
        $this->assertSame($decision->case_number, (string) $node[0]->get('case_number'));
        $this->assertSame($decision->court, (string) $node[0]->get('court'));
        $this->assertSame($decision->judge, (string) $node[0]->get('judge'));

        // Assert: DECIDED_BY relationship exists and points to a Court node.
        $courtId = 'court_'.md5($decision->court);
        $this->graphIdsToCleanup[] = $courtId;

        $decidedBy = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})-[:DECIDED_BY]->(c:Court {id: $courtId}) RETURN c.name as name',
            ['docId' => $decisionDoc->id, 'courtId' => $courtId]
        );

        $this->assertCount(1, $decidedBy);
        $this->assertSame($decision->court, (string) $decidedBy[0]->get('name'));

        // Assert: BELONGS_TO_JURISDICTION relationship exists.
        $jurId = 'jurisdiction_'.$decision->jurisdiction;
        $this->graphIdsToCleanup[] = $jurId;

        $belongs = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})-[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction {id: $jurId}) RETURN j.name as name',
            ['docId' => $decisionDoc->id, 'jurId' => $jurId]
        );
        $this->assertCount(1, $belongs);
        $this->assertSame('HR', (string) $belongs[0]->get('name'));

        // Assert: At least one keyword relationship exists (keyword extraction is required for graph RAG).
        $kwCount = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})-[:HAS_KEYWORD]->(k:Keyword) RETURN count(k) as c',
            ['docId' => $decisionDoc->id]
        );
        $this->assertGreaterThan(0, (int) $kwCount[0]->get('c'));

        // Assert: CITES relationship exists to at least one LawDocument (requires that the cited law exists in relational DB).
        $cites = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})-[:CITES]->(l:LawDocument) RETURN count(l) as c',
            ['docId' => $decisionDoc->id]
        );
        $this->assertGreaterThan(0, (int) $cites[0]->get('c'));

        // Track created node ids for cleanup
        $this->graphIdsToCleanup[] = (string) $decisionDoc->id;
    }
}
