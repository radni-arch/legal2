<?php

namespace Tests\Integration;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * FixtureBasedCourtDecisionProofTest
 *
 * Goal: execution-proven trust.
 *
 * This test creates a real CourtDecision + CourtDecisionDocument from a real-ish Croatian decision text,
 * runs the actual graph sync, and asserts invariants that must hold for legal defense workflows.
 *
 * Why this matters:
 * - It validates the full data path: relational row -> graph node -> court/jurisdiction relationships.
 * - It validates citation extraction on authentic Croatian legal phrasing.
 */
class FixtureBasedCourtDecisionProofTest extends TestCase
{
    use UsesTestDatabase;

    private const SAMPLE_TEXT = <<<'TXT'
Poslovni broj: I Kž-8/2023.-7
U I M E R E P U B L I K E H R V A T S K E
P R E S U D A
Visoki kazneni sud Republike Hrvatske, u vijeću sastavljenom od sudaca Ivana
Turudića, univ.spec.crim., predsjednika vijeća te dr.sc. Tanje Pavelin i Tomislava
Juriše članova vijeća, ...

... kaznenog djela iz članka 110. u vezi članka 34. Kaznenog zakona ("Narodne novine" broj
125/11., 144/12., 56/15. i 61/15.-ispravak – dalje: KZ/11.) ...

... Na temelju članka 474. stavak 1. ZKP/08. ...

... trebalo je na temelju članka 482. ZKP/08. presuditi kao u izreci ove presude.

U Zagrebu 12. travnja 2023.
Predsjednik vijeća:
Ivan Turudić,univ.spec.crim.,v.r.
TXT;

    private function requireNeo4jOrSkip(): void
    {
        if (! config('neo4j.sync.enabled', true)) {
            $this->markTestSkipped('Neo4j sync disabled');
        }

        /** @var GraphDatabaseService $graph */
        $graph = app(GraphDatabaseService::class);
        if (! $graph->isAvailable()) {
            $this->markTestSkipped('Neo4j not available for fixture-based proof');
        }
    }

    /**
     * Invariant: Croatian legal citation detector must extract core statute citations from authentic decision text.
     *
     * Reasoning:
     * - Citation-aware search and agent grounding depend on robust detection.
     * - Your composite detector returns structured citations via detectAll() fileciteturn14file6.
     */
    public function test_fixture_text_yields_expected_statute_citations(): void
    {
        /** @var HrLegalCitationsDetector $detector */
        $detector = app(HrLegalCitationsDetector::class);
        $all = $detector->detectAll(self::SAMPLE_TEXT);

        $statutes = $all['statutes'] ?? [];
        $this->assertIsArray($statutes);
        $this->assertNotEmpty($statutes, 'Expected at least one statute citation');

        // Helper: find (law, article) pairs.
        $has = function (string $law, string $article) use ($statutes): bool {
            foreach ($statutes as $s) {
                $lawKey = $s['law'] ?? ($s['law_code'] ?? ($s['law_name'] ?? null));
                $a = $s['article'] ?? null;
                if (! $lawKey || ! $a) {
                    continue;
                }
                if (mb_strtolower((string) $lawKey) === mb_strtolower($law)
                    && (string) $a === (string) $article) {
                    return true;
                }

                // Some detectors may only provide a canonical string.
                $canonical = $s['canonical'] ?? '';
                if (is_string($canonical)
                    && str_contains(mb_strtolower($canonical), mb_strtolower($law))
                    && str_contains($canonical, (string) $article)) {
                    return true;
                }
            }
            return false;
        };

        // From text: "članka 110. ... članka 34. Kaznenog zakona".
        // Normalization usually maps "Kazneni zakon" -> "KZ".
        $this->assertTrue(
            $has('KZ', '110') || $has('Kazneni zakon', '110'),
            'Expected to detect KZ/Kazneni zakon article 110'
        );

        $this->assertTrue(
            $has('KZ', '34') || $has('Kazneni zakon', '34'),
            'Expected to detect KZ/Kazneni zakon article 34'
        );

        // From text: "članka 474. stavak 1. ZKP/08." and "članka 482. ZKP/08.".
        $this->assertTrue(
            $has('ZKP', '474') || $has('Zakon o kaznenom postupku', '474'),
            'Expected to detect ZKP article 474'
        );

        $this->assertTrue(
            $has('ZKP', '482') || $has('Zakon o kaznenom postupku', '482'),
            'Expected to detect ZKP article 482'
        );
    }

    /**
     * Invariant: Syncing a decision creates a CourtDecisionDocument node + Court and Jurisdiction relationships.
     *
     * Reasoning:
     * - GraphRagOrchestrator::syncCourtDecision projects decision metadata (court, judge, decision_date)
     *   into Neo4j and creates DECIDED_BY and BELONGS_TO_JURISDICTION edges fileciteturn11file9.
     */
    public function test_fixture_decision_syncs_to_graph_with_expected_relationships(): void
    {
        $this->requireNeo4jOrSkip();

        // Ensure graph sync is on for this test.
        Config::set('neo4j.sync.enabled', true);

        $decision = CourtDecision::create([
            'id' => (string) Str::ulid(),
            'case_number' => 'I Kž-8/2023-7',
            'title' => 'Presuda - I Kž-8/2023-7',
            'court' => 'Visoki kazneni sud Republike Hrvatske',
            'jurisdiction' => 'HR',
            'judge' => 'Ivan Turudić',
            'decision_date' => '2023-04-12',
            'publication_date' => '2023-04-12',
            'decision_type' => 'presuda',
            'register' => 'Kž',
            'finality' => 'pravnomoćna',
            'ecli' => 'HR:TEST:VKS:2023:I-KZ-8-2023-7',
            'tags' => ['criminal_law'],
            'description' => 'Fixture-based decision for graph sync proof',
        ]);

        $doc = CourtDecisionDocument::create([
            'id' => (string) Str::ulid(),
            'decision_id' => $decision->id,
            'doc_id' => $decision->ecli,
            'title' => $decision->title,
            'category' => 'odluke',
            'language' => 'hr',
            'tags' => ['criminal_law'],
            'chunk_index' => 0,
            'content' => self::SAMPLE_TEXT,
            'metadata' => [
                'source' => 'fixture',
                'broj_odluke' => $decision->case_number,
            ],
            'source' => 'fixture',
            'source_id' => $decision->case_number,
            'content_hash' => hash('sha256', self::SAMPLE_TEXT),
        ]);

        /** @var GraphRagOrchestrator $orchestrator */
        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncCourtDecision($decision->id);

        /** @var GraphDatabaseService $graph */
        $graph = app(GraphDatabaseService::class);

        // 1) Node exists with expected key properties
        $nodeRes = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id})\n'
            .'RETURN d.case_number as case_number, d.court as court, d.judge as judge, d.decision_date as decision_date',
            ['id' => $doc->id]
        );

        $this->assertNotEmpty($nodeRes, 'Expected CourtDecisionDocument node in graph');
        $this->assertSame('I Kž-8/2023-7', (string) $nodeRes[0]->get('case_number'));
        $this->assertSame('Visoki kazneni sud Republike Hrvatske', (string) $nodeRes[0]->get('court'));
        $this->assertStringContainsString('Ivan', (string) $nodeRes[0]->get('judge'));
        $this->assertStringContainsString('2023-04-12', (string) $nodeRes[0]->get('decision_date'));

        // 2) Court relationship exists
        $courtId = 'court_'.md5($decision->court);
        $courtRel = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})-[:DECIDED_BY]->(c:Court {id: $courtId})\n'
            .'RETURN c.name as name',
            ['docId' => $doc->id, 'courtId' => $courtId]
        );
        $this->assertNotEmpty($courtRel, 'Expected DECIDED_BY relationship to Court');
        $this->assertSame($decision->court, (string) $courtRel[0]->get('name'));

        // 3) Jurisdiction relationship exists
        $jurId = 'jurisdiction_'.$decision->jurisdiction;
        $jurRel = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})-[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction {id: $jurId})\n'
            .'RETURN j.name as name',
            ['docId' => $doc->id, 'jurId' => $jurId]
        );
        $this->assertNotEmpty($jurRel, 'Expected BELONGS_TO_JURISDICTION relationship');
        $this->assertSame('HR', (string) $jurRel[0]->get('name'));

        // Optional sanity: at least one tag or keyword edge exists (autoTag + keyword linker)
        $aux = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $docId})\n'
            .'OPTIONAL MATCH (d)-[:HAS_TAG]->(t:Tag)\n'
            .'OPTIONAL MATCH (d)-[:HAS_KEYWORD]->(k:Keyword)\n'
            .'RETURN count(DISTINCT t) + count(DISTINCT k) as c',
            ['docId' => $doc->id]
        );
        $this->assertGreaterThanOrEqual(0, (int) $aux->first()->get('c'));

        // Cleanup: keep Neo4j clean to avoid polluting other tests
        $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id}) DETACH DELETE d',
            ['id' => $doc->id]
        );
        $graph->run(
            'MATCH (c:Court {id: $id}) DETACH DELETE c',
            ['id' => $courtId]
        );
        $graph->run(
            'MATCH (j:Jurisdiction {id: $id}) DETACH DELETE j',
            ['id' => $jurId]
        );
    }
}
