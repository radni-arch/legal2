<?php

namespace Tests\Integration;

use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use App\Services\LegalCitations\CaseNumberDetector;
use App\Services\LegalCitations\DateDetector;
use App\Services\LegalCitations\EcliDetector;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use App\Services\LegalCitations\NarodneNovineDetector;
use App\Services\LegalCitations\StatuteCitationDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Fixture-based proofs: Court decision -> DB -> Neo4j -> invariants
 *
 * Why fixture-based proofs:
 * - Reasoning: invariant tests over an empty DB often don't prove pipeline behavior.
 *   A fixture-based test creates realistic rows, executes the actual sync code, and
 *   asserts Neo4j state. This is the closest you can get to E2E correctness while
 *   staying inside PHPUnit.
 */
class FixturesCourtDecisionProofTest extends TestCase
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
            $this->markTestSkipped('Neo4j sync is disabled');
        }

        if (! $this->graph()->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available');
        }
    }

    private function insertRow(string $table, array $data): void
    {
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Missing table {$table}");
        }

        // Only insert columns that exist in this environment/migration set.
        $cols = Schema::getColumnListing($table);
        $filtered = array_intersect_key($data, array_flip($cols));

        DB::table($table)->insert($filtered);
    }

    /**
     * Invariant/goal (fixture-based):
     * - Given a real Croatian decision text + metadata inserted into DB,
     *   running graph sync must produce:
     *   - CourtDecisionDocument node with expected metadata (case_number, court, judge)
     *   - Court node + DECIDED_BY relationship
     *   - Jurisdiction node + BELONGS_TO_JURISDICTION relationship
     */
    public function test_fixture_court_decision_sync_creates_expected_graph_shape(): void
    {
        $this->requireGraphOrSkip();

        $decisionId = (string) Str::ulid();
        $docChunkId = (string) Str::ulid();

        $fixturePath = base_path('tests/Fixtures/decisions/I_Kz-8_2023-7.txt');
        $content = file_get_contents($fixturePath);
        $this->assertNotFalse($content, 'Fixture decision file must exist');
        $this->assertNotEmpty(trim($content));

        // Arrange DB rows.
        $this->insertRow('court_decisions', [
            'id' => $decisionId,
            'title' => 'I Kž-8/2023-7 — Presuda',
            'case_number' => 'I Kž-8/2023-7',
            'court' => 'Visoki kazneni sud Republike Hrvatske',
            'jurisdiction' => 'Republika Hrvatska',
            'judge' => 'Ivan Turudić; Tanja Pavelin; Tomislav Juriša',
            'decision_date' => '2023-04-12',
            'publication_date' => '2023-04-12',
            'decision_type' => 'presuda',
            'register' => null,
            'finality' => null,
            'ecli' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertRow('court_decision_documents', [
            'id' => $docChunkId,
            'decision_id' => $decisionId,
            'doc_id' => 'doc-'.$decisionId,
            'title' => 'I Kž-8/2023-7 — chunk 0',
            'chunk_index' => 0,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'metadata' => json_encode(['fixture' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Act: run the actual sync implementation used by the app.
        /** @var GraphRagOrchestrator $orchestrator */
        $orchestrator = app(GraphRagOrchestrator::class);
        $orchestrator->syncCourtDecision($decisionId);

        // Assert: graph node exists with expected properties.
        $graph = $this->graph();

        $nodeRes = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id})\n'
            .'RETURN d.case_number as case_number, d.court as court, d.judge as judge, d.decision_date as decision_date',
            ['id' => $docChunkId]
        );

        $this->assertCount(1, $nodeRes);
        $this->assertSame('I Kž-8/2023-7', (string) $nodeRes[0]->get('case_number'));
        $this->assertSame('Visoki kazneni sud Republike Hrvatske', (string) $nodeRes[0]->get('court'));
        $this->assertStringContainsString('Ivan Turudić', (string) $nodeRes[0]->get('judge'));

        // Assert: DECIDED_BY exists.
        $courtRel = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id})-[r:DECIDED_BY]->(c:Court)\n'
            .'RETURN c.name as court_name, r.created_at as created_at',
            ['id' => $docChunkId]
        );
        $this->assertCount(1, $courtRel);
        $this->assertSame('Visoki kazneni sud Republike Hrvatske', (string) $courtRel[0]->get('court_name'));
        $this->assertNotEmpty((string) $courtRel[0]->get('created_at'));

        // Assert: BELONGS_TO_JURISDICTION exists.
        $jurRel = $graph->run(
            'MATCH (d:CourtDecisionDocument {id: $id})-[r:BELONGS_TO_JURISDICTION]->(j:Jurisdiction)\n'
            .'RETURN j.name as name, r.created_at as created_at',
            ['id' => $docChunkId]
        );
        $this->assertCount(1, $jurRel);
        $this->assertSame('Republika Hrvatska', (string) $jurRel[0]->get('name'));
        $this->assertNotEmpty((string) $jurRel[0]->get('created_at'));

        // Cleanup graph nodes created by this test (best-effort).
        $courtNodeId = 'court_'.md5('Visoki kazneni sud Republike Hrvatske');
        $jurNodeId = 'jurisdiction_Republika Hrvatska';
        $graph->run('MATCH (n) WHERE n.id IN $ids DETACH DELETE n', [
            'ids' => [$docChunkId, $courtNodeId, $jurNodeId],
        ]);

        // Cleanup DB.
        DB::table('court_decision_documents')->where('id', $docChunkId)->delete();
        DB::table('court_decisions')->where('id', $decisionId)->delete();
    }

    /**
     * Invariant/goal (fixture-based):
     * - The Croatian citation detector must extract the core legal citations that
     *   are present in the sample decision.
     *
     * Reasoning:
     * - This is an execution-proof that downstream systems (graph citation queries,
     *   legal reasoning) can reliably parse the citations.
     */
    public function test_fixture_decision_has_expected_statute_and_case_citations(): void
    {
        $fixturePath = base_path('tests/Fixtures/decisions/I_Kz-8_2023-7.txt');
        $text = file_get_contents($fixturePath);
        $this->assertNotFalse($text);

        $detector = new HrLegalCitationsDetector(
            new StatuteCitationDetector,
            new NarodneNovineDetector,
            new CaseNumberDetector,
            new EcliDetector,
            new DateDetector,
        );

        $all = $detector->detectAll($text);

        $this->assertIsArray($all);
        $this->assertArrayHasKey('statutes', $all);
        $this->assertArrayHasKey('case_numbers', $all);

        $statutes = $all['statutes'] ?? [];
        $cases = $all['case_numbers'] ?? [];

        // Case number must be detectable (I Kž-8/2023-7)
        $caseCanon = array_map(fn ($c) => (string) ($c['canonical'] ?? ''), $cases);
        $this->assertTrue(
            (bool) array_filter($caseCanon, fn ($c) => str_contains($c, 'I Kž-8/2023')),
            'Expected to detect case number I Kž-8/2023-7'
        );

        // Statute citations: must include KZ article 110 and ZKP article 482 (as present in text)
        $hasKz110 = false;
        $hasZkp482 = false;

        foreach ($statutes as $s) {
            $law = mb_strtoupper((string) ($s['law_abbreviation'] ?? ($s['law'] ?? '')), 'UTF-8');
            $article = (string) ($s['article'] ?? '');
            $canonical = (string) ($s['canonical'] ?? '');

            if (($law === 'KZ' || str_contains($canonical, 'KZ')) && $article === '110') {
                $hasKz110 = true;
            }
            if (($law === 'ZKP' || str_contains($canonical, 'ZKP')) && $article === '482') {
                $hasZkp482 = true;
            }
        }

        $this->assertTrue($hasKz110, 'Expected to detect KZ Article 110 from decision text');
        $this->assertTrue($hasZkp482, 'Expected to detect ZKP Article 482 from decision text');
    }
}
