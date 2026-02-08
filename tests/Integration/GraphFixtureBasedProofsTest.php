<?php

namespace Tests\Integration;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\Law;
use App\Services\Graph\DecisionGraphSyncService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Fixture-based proof tests
 *
 * Why fixtures?
 * - Invariants catch drift in existing data.
 * - Fixtures prove that a realistic input (a real-ish decision text) flows through
 *   sync code paths and materializes the expected graph structures.
 */
class GraphFixtureBasedProofsTest extends TestCase
{
    use UsesTestDatabase;

    private function graph(): GraphDatabaseService
    {
        /** @var GraphDatabaseService $svc */
        $svc = app(GraphDatabaseService::class);

        return $svc;
    }

    private function requireGraphOrSkip(): void
    {
        if (! config('neo4j.sync.enabled', true)) {
            $this->markTestSkipped('Neo4j sync is disabled (neo4j.sync.enabled=false)');
        }

        if (! $this->graph()->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available for fixture proofs');
        }
    }

    /**
     * Invariant/Goal:
     * A realistic Croatian criminal appeal decision should, after sync, produce:
     * - CourtDecisionDocument node with correct metadata properties,
     * - Court node + DECIDED_BY relationship,
     * - Jurisdiction node + BELONGS_TO_JURISDICTION relationship,
     * - CITES relationships to LawDocument nodes for NN citations when matching law rows exist,
     * - At least one HAS_KEYWORD relationship (keyword extraction executed).
     *
     * Reasoning:
     * - This is an execution proof that the decision->graph pipeline works end-to-end,
     *   including citation parsing via GraphCitationLinker and keyword extraction via GraphKeywordLinker.
     */
    public function test_fixture_court_decision_sync_materializes_expected_graph_structures(): void
    {
        $this->requireGraphOrSkip();

        // Keep test isolated from hybrid keyword extraction (may depend on optional extractor).
        Config::set('keywords.use_hybrid', false);

        if (! Schema::hasTable('laws') || ! Schema::hasTable('court_decisions') || ! Schema::hasTable('court_decision_documents')) {
            $this->markTestSkipped('Required tables missing for fixture proof');
        }

        $graph = $this->graph();
        $graph->initializeSchema();

        $uniqueToken = 'fixturetokenx_'.Str::lower(Str::random(12));
        $keywordId = 'keyword_'.md5($uniqueToken);

        // --- Arrange: minimal referenced laws so citation linker can resolve NN citations.
        $kz = new Law([
            'id' => (string) Str::ulid(),
            'doc_id' => 'KZ11_fixture',
            'title' => 'Kazneni zakon (KZ/11) [fixture]',
            // GraphCitationLinker matches Narodne novine + (\d+/\d+)
            'law_number' => '125/11',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => 'Članak 110. (fixture).',
        ]);
        $kz->save();

        $zkp = new Law([
            'id' => (string) Str::ulid(),
            'doc_id' => 'ZKP08_fixture',
            'title' => 'Zakon o kaznenom postupku (ZKP/08) [fixture]',
            'law_number' => '152/08',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => 'Članak 474. (fixture).',
        ]);
        $zkp->save();

        // --- Arrange: CourtDecision + CourtDecisionDocument with provided sample content.
        $decision = new CourtDecision([
            'id' => (string) Str::ulid(),
            'case_number' => 'I Kž-8/2023-7',
            'title' => 'Presuda — I Kž-8/2023-7',
            'court' => 'Visoki kazneni sud Republike Hrvatske',
            'jurisdiction' => 'HR',
            'judge' => 'Ivan Turudić',
            'decision_date' => '2023-04-12',
            'decision_type' => 'Presuda',
        ]);
        $decision->save();

        $doc = new CourtDecisionDocument([
            'id' => (string) Str::ulid(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-'.$decision->case_number,
            'title' => $decision->title,
            'category' => 'odluke_fixture',
            'court' => $decision->court,
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => $this->fixtureDecisionText($uniqueToken),
            'metadata' => [
                'tags' => ['fixture'],
                // Keep metadata aligned with TaggingService optional extraction
                'jurisdiction' => $decision->jurisdiction,
            ],
            'source' => 'fixture',
            'source_id' => $decision->id,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            // embedding intentionally omitted -> similarity linker should be no-op
        ]);
        $doc->content_hash = hash('sha256', $doc->content);
        $doc->save();

        // --- Act: sync into graph.
        /** @var DecisionGraphSyncService $sync */
        $sync = app(DecisionGraphSyncService::class);
        $sync->sync($decision->id);

        // --- Assert: graph node exists and carries metadata.
        $nodeRes = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id}) RETURN d.case_number as case_number, d.court as court, d.judge as judge, d.decision_date as decision_date',
            ['id' => $doc->id]
        );

        $this->assertCount(1, $nodeRes);
        $this->assertSame('I Kž-8/2023-7', (string) $nodeRes[0]->get('case_number'));
        $this->assertSame('Visoki kazneni sud Republike Hrvatske', (string) $nodeRes[0]->get('court'));
        $this->assertSame('Ivan Turudić', (string) $nodeRes[0]->get('judge'));
        $this->assertSame('2023-04-12', (string) $nodeRes[0]->get('decision_date'));

        // --- Assert: Court node + DECIDED_BY.
        $courtRes = $graph->run(
            'MATCH (:CourtDecisionDocument {id: $id})-[:DECIDED_BY]->(c:Court) RETURN c.name as name',
            ['id' => $doc->id]
        );
        $this->assertCount(1, $courtRes);
        $this->assertSame('Visoki kazneni sud Republike Hrvatske', (string) $courtRes[0]->get('name'));

        // --- Assert: Jurisdiction relationship exists (decision has jurisdiction).
        $jurRes = $graph->run(
            'MATCH (:CourtDecisionDocument {id: $id})-[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction) RETURN j.name as name',
            ['id' => $doc->id]
        );
        $this->assertCount(1, $jurRes);
        $this->assertSame('HR', (string) $jurRes[0]->get('name'));

        // --- Assert: Keyword extraction ran (at least one keyword relationship).
        $kwRes = $graph->run(
            'MATCH (:CourtDecisionDocument {id: $id})-[:HAS_KEYWORD]->(k:Keyword) RETURN count(k) as c',
            ['id' => $doc->id]
        );
        $this->assertGreaterThan(0, (int) $kwRes[0]->get('c'));

        // --- Assert: Our unique token became a keyword (isolation to avoid collisions).
        $uniqueKwRes = $graph->run(
            'MATCH (:CourtDecisionDocument {id: $id})-[:HAS_KEYWORD]->(k:Keyword {id: $kwId}) RETURN k.name as name',
            ['id' => $doc->id, 'kwId' => $keywordId]
        );
        $this->assertCount(1, $uniqueKwRes);
        $this->assertSame($uniqueToken, (string) $uniqueKwRes[0]->get('name'));

        // --- Assert: Citations produced CITES edges to our inserted laws.
        $citeRes = $graph->run(
            'MATCH (:CourtDecisionDocument {id: $id})-[:CITES]->(l:LawDocument) RETURN collect(l.law_number) as nums',
            ['id' => $doc->id]
        );
        $nums = $citeRes[0]->get('nums');
        $this->assertIsArray($nums);
        $this->assertContains('125/11', $nums);
        $this->assertContains('152/08', $nums);

        // --- Assert: Core edges have created_at (GraphDatabaseService enforces this).
        $createdAtRes = $graph->run(
            'MATCH (:CourtDecisionDocument {id: $id})-[r:DECIDED_BY|BELONGS_TO_JURISDICTION|CITES]->()\n'
            .'RETURN count(r) as total, sum(CASE WHEN r.created_at IS NULL THEN 1 ELSE 0 END) as missing',
            ['id' => $doc->id]
        );
        $this->assertSame(0, (int) $createdAtRes[0]->get('missing'));
        $this->assertGreaterThan(0, (int) $createdAtRes[0]->get('total'));

        // --- Cleanup (avoid graph pollution)
        // Remove the decision doc node and its relationships; keep shared nodes (Court/Jurisdiction/Tags) intact.
        $graph->run('MATCH (d:CourtDecisionDocument {id: $id}) DETACH DELETE d', ['id' => $doc->id]);

        // Delete the unique keyword node created by this test.
        $graph->run('MATCH (k:Keyword {id: $id}) DETACH DELETE k', ['id' => $keywordId]);

        // Delete inserted law nodes for the fixture.
        $graph->run('MATCH (l:LawDocument) WHERE l.id IN $ids DETACH DELETE l', ['ids' => [$kz->id, $zkp->id]]);
    }

    private function fixtureDecisionText(string $uniqueToken): string
    {
        // Minimal excerpt with the essential metadata patterns we want to prove:
        // - Court name & judge names (stored on node properties by sync)
        // - Narodne novine citations (picked up by GraphCitationLinker regex)
        // - Case number patterns (also a citation pattern in GraphCitationLinker, but we focus on law citations here)
        return <<<TXT
Poslovni broj: I Kž-8/2023-7
U IME REPUBLIKE HRVATSKE
PRESUDA
Visoki kazneni sud Republike Hrvatske, u vijeću sastavljenom od sudaca Ivana Turudića,
... u kaznenom predmetu zbog kaznenog djela iz članka 110. u vezi članka 34. Kaznenog zakona
("Narodne novine" broj 125/11., 144/12., 56/15. i 61/15.-ispravak – dalje: KZ/11.).

Na temelju članka 189. stavak 1. Zakona o kaznenom postupku ("Narodne novine" broj 152/08.
– dalje: ZKP/08.) ...

{$uniqueToken}
TXT;
    }
}
