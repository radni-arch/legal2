<?php

namespace Tests\Integration\FixtureProofs;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * CourtDecisionGraphFixtureProofTest
 *
 * Invariant/Goal:
 *  A real-world Croatian court decision (fixture text) must be storable as:
 *   - court_decisions row + at least one court_decision_documents chunk
 *   - and (when Neo4j is enabled/available) syncable into Neo4j with:
 *       - CourtDecisionDocument node containing expected metadata properties
 *       - Court node and DECIDED_BY relationship
 *       - Jurisdiction node and BELONGS_TO_JURISDICTION relationship
 *       - At least one HAS_TAG relationship (rule-based tagging)
 *
 * Reasoning:
 *  This is an execution-based proof that the "decision ingestion → graph sync" path is not brittle.
 *  It prevents regressions where Neo4j sync silently stops creating the core graph structure used
 *  for reasoning and navigation.
 */
class CourtDecisionGraphFixtureProofTest extends TestCase
{
    use UsesTestDatabase;

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

    public function test_fixture_decision_syncs_to_graph_with_expected_core_structure(): void
    {
        $this->requireGraphOrSkip();

        if (! Schema::hasTable('court_decisions') || ! Schema::hasTable('court_decision_documents')) {
            $this->markTestSkipped('court_decisions tables missing');
        }

        $fixturePath = base_path('tests/Fixtures/decisions/ikz-8-2023-7.txt');
        $content = file_get_contents($fixturePath);
        $this->assertNotFalse($content, 'Fixture file must be readable');
        $this->assertNotEmpty(trim($content), 'Fixture content must not be empty');

        $decisionId = (string) Str::ulid();
        $docId = 'I Kž-8/2023-7';

        // Create decision record
        CourtDecision::create([
            'id' => $decisionId,
            'case_number' => $docId,
            'title' => 'I Kž-8/2023-7 - Presuda',
            'court' => 'Visoki kazneni sud Republike Hrvatske',
            'jurisdiction' => 'HR',
            // Keep judge as a string as used by graph sync (property "judge")
            'judge' => 'Ivan Turudić; Tanja Pavelin; Tomislav Juriša',
            'decision_date' => '2023-04-12',
            'decision_type' => 'Presuda',
            'tags' => ['fixture', 'criminal'],
            'description' => 'Fixture-based proof decision for graph sync.',
        ]);

        // Create single chunk document
        $chunkId = (string) Str::ulid();
        CourtDecisionDocument::create([
            'id' => $chunkId,
            'decision_id' => $decisionId,
            'doc_id' => $docId,
            'title' => 'Presuda - I Kž-8/2023-7',
            'category' => 'fixture',
            'author' => 'Visoki kazneni sud RH',
            'court' => 'Visoki kazneni sud Republike Hrvatske',
            'language' => 'hr',
            'tags' => ['fixture'],
            'chunk_index' => 0,
            'content' => $content,
            'metadata' => [
                'fixture' => true,
                'source' => 'tests/Fixtures/decisions/ikz-8-2023-7.txt',
            ],
            'source' => 'fixture',
            'source_id' => $docId,
            'content_hash' => hash('sha256', $content),
            'token_count' => null,
        ]);

        $this->assertDatabaseHas('court_decisions', ['id' => $decisionId]);
        $this->assertDatabaseHas('court_decision_documents', ['id' => $chunkId, 'decision_id' => $decisionId]);

        /** @var GraphDatabaseService $graph */
        $graph = app(GraphDatabaseService::class);
        $graph->initializeSchema();

        /** @var GraphRagOrchestrator $orchestrator */
        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncCourtDecision($decisionId);

        // 1) CourtDecisionDocument node exists with expected key properties
        $node = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id}) RETURN d.decision_id as decision_id, d.case_number as case_number, d.court as court, d.judge as judge, d.decision_date as decision_date',
            ['id' => $chunkId]
        );

        $this->assertCount(1, $node);
        $this->assertSame($decisionId, (string) $node[0]->get('decision_id'));
        $this->assertSame($docId, (string) $node[0]->get('case_number'));
        $this->assertSame('Visoki kazneni sud Republike Hrvatske', (string) $node[0]->get('court'));
        $this->assertNotEmpty((string) $node[0]->get('judge'));
        $this->assertNotEmpty((string) $node[0]->get('decision_date'));

        // 2) Court node exists and decision is linked via DECIDED_BY
        $courtId = 'court_'.md5('Visoki kazneni sud Republike Hrvatske');
        $decidedBy = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})-[:DECIDED_BY]->(c:Court {id: $courtId}) RETURN count(c) as c',
            ['docId' => $chunkId, 'courtId' => $courtId]
        );
        $this->assertSame(1, (int) $decidedBy->first()->get('c'));

        // 3) Jurisdiction node exists and decision is linked via BELONGS_TO_JURISDICTION
        $jur = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})-[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction {id: $jurId}) RETURN count(j) as c',
            ['docId' => $chunkId, 'jurId' => 'jurisdiction_HR']
        );
        $this->assertSame(1, (int) $jur->first()->get('c'));

        // 4) Rule-based tagging creates at least one HAS_TAG relationship.
        // TaggingService uses content keyword patterns and metadata (court/jurisdiction)
        // and applies tags via HAS_TAG.
        $tags = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})-[:HAS_TAG]->(t:Tag) RETURN count(t) as c',
            ['docId' => $chunkId]
        );
        $this->assertGreaterThan(0, (int) $tags->first()->get('c'));

        // 5) Relationship audit: core relationships should have created_at (enforced by GraphDatabaseService)
        $missingCreatedAt = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})-[r:DECIDED_BY|BELONGS_TO_JURISDICTION|HAS_TAG]->() WHERE r.created_at IS NULL RETURN count(r) as c',
            ['docId' => $chunkId]
        );
        $this->assertSame(0, (int) $missingCreatedAt->first()->get('c'));

        // Cleanup: keep DB rows (transactional test DB may roll back), but clean graph nodes if desired.
        // For now, we rely on test environment cleanup/ephemeral DB.
        $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId}) DETACH DELETE d',
            ['docId' => $chunkId]
        );
        $graph->run(
            'MATCH (c:Court {id: $courtId}) DETACH DELETE c',
            ['courtId' => $courtId]
        );
        $graph->run(
            'MATCH (j:Jurisdiction {id: $jurId}) DETACH DELETE j',
            ['jurId' => 'jurisdiction_HR']
        );

        // Do not delete Tag/Keyword nodes: they may be shared across other tests/environments.
    }
}
