<?php

namespace Tests\Feature\Graph;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Fixture-based proof: court decision -> graph sync
 *
 * Invariant / Goal (plain language):
 * - Given a real-ish Croatian decision text fixture, when we persist it into
 *   court_decisions + court_decision_documents and run GraphRagOrchestrator::syncCourtDecision,
 *   then Neo4j must contain:
 *   - A CourtDecisionDocument node with correct metadata
 *   - A Court node and DECIDED_BY edge
 *   - A Jurisdiction node and BELONGS_TO_JURISDICTION edge
 *   - At least one CITES edge to a LawDocument when a matching law_number exists in DB
 *
 * Reasoning:
 * - This is an execution proof that the court decision ingestion + graph projection
 *   works end-to-end (DB -> Graph) and preserves key metadata needed for legal defense.
 */
class DecisionGraphSyncFixtureProofTest extends TestCase
{
    use UsesTestDatabase;

    private ?string $decisionId = null;

    private ?string $docChunkId = null;

    private ?string $lawId = null;

    private ?string $courtId = null;

    private ?string $jurNodeId = null;

    protected function tearDown(): void
    {
        // Attempt graph cleanup (never block test suite)
        try {
            /** @var GraphDatabaseService $graph */
            $graph = app(GraphDatabaseService::class);
            if ($graph->isAvailable()) {
                $ids = array_values(array_filter([
                    $this->docChunkId,
                    $this->decisionId,
                    $this->lawId,
                    $this->courtId,
                    $this->jurNodeId,
                ]));

                if (! empty($ids)) {
                    // Delete by id for any label we might have created.
                    // (Some labels share IDs; this is OK.)
                    $graph->run('MATCH (n) WHERE n.id IN $ids DETACH DELETE n', ['ids' => $ids]);
                }
            }
        } catch (\Throwable $e) {
            // ignore
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
            $this->markTestSkipped('Neo4j is not available for fixture-based graph proof');
        }
    }

    public function test_fixture_court_decision_syncs_to_graph_with_expected_nodes_and_edges(): void
    {
        $this->requireGraphOrSkip();

        /**
         * Fixture extracted from user-provided decision text:
         * - Case number: I Kž-8/2023-7
         * - Court: Visoki kazneni sud Republike Hrvatske
         * - Date: 2023-04-12
         * - Type: PRESUDA
         * - Judge (panel president): Ivan Turudić
         * - Cites: Narodne novine 125/11 (KZ/11)
         */
        $caseNumber = 'I Kž-8/2023-7';
        $court = 'Visoki kazneni sud Republike Hrvatske';
        $judge = 'Ivan Turudić';
        $jurisdiction = 'HR';
        $decisionDate = '2023-04-12';
        $decisionType = 'Presuda';

        // Ensure at least one law exists for deterministic citation linking.
        // GraphCitationLinker looks up DB::table('laws')->where('law_number', <citation>)
        // and creates (source)-[:CITES]->(LawDocument).
        // It extracts NN citation values like "125/11" (from "Narodne novine 125/11").
        $this->lawId = (string) Str::ulid();
        DB::table('laws')->insert([
            'id' => $this->lawId,
            'doc_id' => 'law-kz-125-11',
            'title' => 'Kazneni zakon (KZ/11)',
            'law_number' => '125/11',
            'jurisdiction' => $jurisdiction,
            'country' => 'HR',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => 'Kazneni zakon (fixture minimal content)',
            'content_hash' => hash('sha256', 'Kazneni zakon (fixture minimal content)'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert decision + one document chunk with the fixture content.
        $this->decisionId = (string) Str::ulid();

        /** @var CourtDecision $decision */
        $decision = CourtDecision::factory()->create([
            'id' => $this->decisionId,
            'case_number' => $caseNumber,
            'title' => 'Presuda - '.$caseNumber,
            'court' => $court,
            'jurisdiction' => $jurisdiction,
            'judge' => $judge,
            'decision_date' => $decisionDate,
            'decision_type' => $decisionType,
            'ecli' => null,
        ]);

        $content = <<<TXT
Poslovni broj: I Kž-8/2023-7
U IME REPUBLIKE HRVATSKE
PRESUDA
Visoki kazneni sud Republike Hrvatske, u vijeću sastavljenom od sudaca Ivana Turudića, ...
... zbog kaznenog djela iz članka 110. u vezi članka 34. Kaznenog zakona ("Narodne novine" broj 125/11., 144/12., 56/15. i 61/15.-ispravak – dalje: KZ/11.) ...
U Zagrebu 12. travnja 2023.
TXT;

        $this->docChunkId = (string) Str::ulid();

        /** @var CourtDecisionDocument $doc */
        $doc = CourtDecisionDocument::factory()->create([
            'id' => $this->docChunkId,
            'decision_id' => $decision->id,
            'doc_id' => 'doc-'.$caseNumber,
            'title' => 'Presuda - '.$caseNumber,
            'category' => 'decision',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            // Deterministic embedding
            'embedding' => array_fill(0, 1536, 0.0),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
        ]);

        // Act: sync to graph using the primary orchestrator.
        /** @var GraphRagOrchestrator $orchestrator */
        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncCourtDecision($decision->id);

        /** @var GraphDatabaseService $graph */
        $graph = app(GraphDatabaseService::class);

        // Assert: CourtDecisionDocument node exists and carries key metadata.
        $nodeRes = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id})\n'
            .'RETURN d.case_number as case_number, d.court as court, d.judge as judge, d.decision_date as decision_date, d.decision_type as decision_type, d.jurisdiction as jurisdiction, d.chunk_index as chunk_index',
            ['id' => $doc->id]
        );

        $this->assertGreaterThan(0, count($nodeRes), 'CourtDecisionDocument node should exist in Neo4j');
        $this->assertSame($caseNumber, (string) $nodeRes[0]->get('case_number'));
        $this->assertSame($court, (string) $nodeRes[0]->get('court'));
        $this->assertStringContainsString('Ivan', (string) $nodeRes[0]->get('judge'));
        $this->assertSame($jurisdiction, (string) $nodeRes[0]->get('jurisdiction'));
        $this->assertSame(0, (int) $nodeRes[0]->get('chunk_index'));

        // Assert: Court node exists and DECIDED_BY edge exists.
        $this->courtId = 'court_'.md5($court);
        $courtRes = $graph->run(
            'MATCH (c:Court {id: $id}) RETURN c.name as name',
            ['id' => $this->courtId]
        );
        $this->assertGreaterThan(0, count($courtRes), 'Court node should exist');
        $this->assertSame($court, (string) $courtRes[0]->get('name'));

        $edgeRes = $graph->run(
            'MATCH (:CourtDecisionDocument {id: $docId})-[r:DECIDED_BY]->(:Court {id: $courtId}) RETURN count(r) as c',
            ['docId' => $doc->id, 'courtId' => $this->courtId]
        );
        $this->assertSame(1, (int) $edgeRes[0]->get('c'), 'DECIDED_BY edge should exist');

        // Assert: Jurisdiction node exists and BELONGS_TO_JURISDICTION edge exists.
        $this->jurNodeId = 'jurisdiction_'.$jurisdiction;
        $jurRes = $graph->run(
            'MATCH (j:Jurisdiction {id: $id}) RETURN j.name as name',
            ['id' => $this->jurNodeId]
        );
        $this->assertGreaterThan(0, count($jurRes), 'Jurisdiction node should exist');

        $jurEdgeRes = $graph->run(
            'MATCH (:CourtDecisionDocument {id: $docId})-[r:BELONGS_TO_JURISDICTION]->(:Jurisdiction {id: $jurId}) RETURN count(r) as c',
            ['docId' => $doc->id, 'jurId' => $this->jurNodeId]
        );
        $this->assertSame(1, (int) $jurEdgeRes[0]->get('c'), 'BELONGS_TO_JURISDICTION edge should exist');

        // Assert: At least one citation edge exists to the inserted law.
        // This is the core "pulled from fixture" invariant: the decision mentions Narodne novine 125/11.
        $citeRes = $graph->run(
            'MATCH (:CourtDecisionDocument {id: $docId})-[r:CITES]->(:LawDocument {id: $lawId}) RETURN count(r) as c',
            ['docId' => $doc->id, 'lawId' => $this->lawId]
        );
        $this->assertGreaterThanOrEqual(1, (int) $citeRes[0]->get('c'), 'Decision should CITES the referenced law when it exists in DB');
    }
}
