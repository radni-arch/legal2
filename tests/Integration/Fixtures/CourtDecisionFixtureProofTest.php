<?php

namespace Tests\Integration\Fixtures;

use App\Models\CourtDecision;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * CourtDecisionFixtureProofTest
 *
 * Fixture-based proof for a real Croatian criminal decision text.
 *
 * Why this exists:
 * - Invariant tests catch drift in existing data.
 * - Fixture-based proofs verify that the *pipeline itself* can ingest real-world legal text,
 *   persist correct metadata, chunk documents, and extract legal citations.
 *
 * We keep this proof self-contained and offline:
 * - No network (no odluke.sudovi.hr calls)
 * - No OpenAI calls (mocked vector store)
 * - Optional Neo4j projection (skips if Neo4j unavailable)
 */
class CourtDecisionFixtureProofTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Invariant/Goal: A representative decision can be ingested offline and persists:
     * - CourtDecision row with correct key metadata (case_number, court, decision_date, jurisdiction)
     * - CourtDecisionDocument chunks with non-empty content + metadata
     * - Citation detector can extract core statute citations (KZ/11 + ZKP/08)
     * - Optional: graph projection creates Court + Jurisdiction relationships
     */
    public function test_fixture_offline_decision_ingestion_persists_and_extracts_citations(): void
    {
        if (! Schema::hasTable('court_decisions') || ! Schema::hasTable('court_decision_documents')) {
            $this->markTestSkipped('court_decisions / court_decision_documents tables are missing');
        }

        // --- Fixture (provided by user) ---
        $text = <<<'TEXT'
Poslovni broj: I Kž-8/2023.-7
U I M E R E P U B L I K E H R V A T S K E
P R E S U D A
Visoki kazneni sud Republike Hrvatske, u vijeću sastavljenom od sudaca Ivana
Turudića, univ.spec.crim., predsjednika vijeća te dr.sc. Tanje Pavelin i Tomislava
Juriše članova vijeća...
...
protiv optužene I.Š.H., zbog kaznenog djela iz članka 110. u vezi članka 34. Kaznenog zakona ("Narodne novine" broj
125/11., 144/12., 56/15. i 61/15.-ispravak – dalje: KZ/11.)...
...
Na temelju članka 189. stavak 1. Zakona o kaznenom postupku ("Narodne
novine" broj 152/08.... dalje: ZKP/08.)...
...
Kako navodi podnesenih žalbi optuženice i državnog odvjetnika nisu osnovani,
trebalo je na temelju članka 482. ZKP/08. presuditi kao u izreci ove presude.
U Zagrebu 12. travnja 2023.
Predsjednik vijeća:
Ivan Turudić,univ.spec.crim.,v.r.
TEXT;

        // Meta keys expected by OdlukeIngestService::upsertDecisionFromMeta() (odluke.sudovi.hr style)
        $meta = [
            'id' => 'fixture-ikz-8-2023-7',
            'broj_odluke' => 'I Kž-8/2023-7',
            'sud' => 'Visoki kazneni sud Republike Hrvatske',
            'datum_odluke' => '2023-04-12',
            'vrsta_odluke' => 'Presuda',
            // Avoid network fallback in metadata builder
            'src' => 'fixture://I-Kz-8-2023-7',
            // Stable doc_id grouping
            'ecli' => 'ECLI:HR:VKS:2023:I-KZ-8-2023-7',
            'upisnik' => 'Kž',
            'pravomocnost' => 'pravomoćno',
        ];

        // --- Mock vector store to keep test offline and deterministic ---
        // OdlukeIngestService depends on CourtDecisionVectorStoreService concretely.
        $mockVectors = Mockery::mock(CourtDecisionVectorStoreService::class);
        $mockVectors->shouldReceive('ingest')
            ->once()
            ->withArgs(function ($decisionId, $docId, $docs, $options) use ($meta) {
                // Basic expectations about chunking
                return is_string($decisionId)
                    && $decisionId !== ''
                    && $docId === $meta['ecli']
                    && is_array($docs)
                    && count($docs) > 0
                    && isset($docs[0]['content'])
                    && isset($docs[0]['metadata'])
                    && ($options['provider'] ?? null) === 'openai';
            })
            ->andReturnUsing(function ($decisionId, $docId, $docs) {
                $table = config('vizra-adk.tables.court_decision_documents', 'court_decision_documents');

                $insertedIds = [];
                foreach ($docs as $i => $d) {
                    $id = (string) Str::ulid();
                    $insertedIds[] = $id;

                    DB::table($table)->insert([
                        'id' => $id,
                        'decision_id' => $decisionId,
                        'doc_id' => $docId,
                        'title' => 'Presuda',
                        'category' => 'odluke_fixture',
                        'author' => null,
                        'court' => null,
                        'language' => 'hr',
                        'tags' => json_encode(['fixture']),
                        'chunk_index' => $d['chunk_index'] ?? $i,
                        'content' => (string) $d['content'],
                        'metadata' => json_encode($d['metadata'] ?? []),
                        'source' => $d['source'] ?? null,
                        'source_id' => $d['source_id'] ?? null,
                        'embedding_provider' => 'fixture',
                        'embedding_model' => 'fixture',
                        'embedding_dimensions' => null,
                        'embedding_norm' => null,
                        'content_hash' => hash('sha256', (string) $d['content']),
                        'token_count' => null,
                        'embedding' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return [
                    'count' => count($docs),
                    'inserted' => count($docs),
                    'inserted_ids' => $insertedIds,
                ];
            });

        $this->app->instance(CourtDecisionVectorStoreService::class, $mockVectors);

        /** @var OdlukeIngestService $svc */
        $svc = app(OdlukeIngestService::class);

        $res = $svc->ingestText($text, $meta, [
            'model' => 'fixture-embed',
            'chunk_chars' => 800,
            'overlap' => 0,
            'dry' => false,
        ]);

        $this->assertSame(1, $res['ids_processed']);
        $this->assertGreaterThan(0, $res['would_chunks']);
        $this->assertSame($res['would_chunks'], $res['inserted']);

        // --- DB assertions (core business correctness) ---
        $decision = CourtDecision::query()->where('case_number', $meta['broj_odluke'])->first();
        $this->assertNotNull($decision);
        $this->assertSame($meta['sud'], $decision->court);
        $this->assertSame('HR', $decision->jurisdiction);
        $this->assertSame('Presuda', $decision->decision_type);
        $this->assertSame('2023-04-12', optional($decision->decision_date)->format('Y-m-d'));

        // Decision must have at least one associated chunk document.
        $docsTable = config('vizra-adk.tables.court_decision_documents', 'court_decision_documents');
        $docCount = DB::table($docsTable)->where('decision_id', $decision->id)->count();
        $this->assertGreaterThan(0, $docCount);

        // Each chunk must have non-empty content, correct doc_id grouping, and metadata provenance.
        $chunks = DB::table($docsTable)
            ->where('decision_id', $decision->id)
            ->orderBy('chunk_index')
            ->get();

        foreach ($chunks as $chunk) {
            $this->assertSame($meta['ecli'], $chunk->doc_id);
            $this->assertNotEmpty($chunk->content);

            $chunkMeta = is_string($chunk->metadata) ? json_decode($chunk->metadata, true) : (array) $chunk->metadata;
            $this->assertIsArray($chunkMeta);
            $this->assertSame($meta['broj_odluke'], $chunkMeta['broj_odluke'] ?? null);
            $this->assertSame($meta['sud'], $chunkMeta['sud'] ?? null);
            $this->assertSame($meta['datum_odluke'], $chunkMeta['datum_odluke'] ?? null);
        }

        // --- Citation extraction proof (pulling an invariant out of the fixture text) ---
        // Canonical statute format is built as: "{LAW}:čl.{article} st.{paragraph} ..." per StatuteCitationDetector::buildCanonical().
        /** @var HrLegalCitationsDetector $detector */
        $detector = app(HrLegalCitationsDetector::class);
        $citations = $detector->detectAll($text);

        $canon = array_column($citations['statutes'] ?? [], 'canonical');
        $canon = array_filter(array_map('strval', $canon));

        // Must contain at least the core citations explicitly present in the text.
        $this->assertTrue(
            (bool) preg_grep('/^KZ\/?11:čl\.110\b/u', $canon),
            'Expected KZ/11 čl.110 citation missing'
        );
        $this->assertTrue(
            (bool) preg_grep('/^KZ\/?11:čl\.34\b/u', $canon),
            'Expected KZ/11 čl.34 citation missing'
        );
        $this->assertTrue(
            (bool) preg_grep('/^ZKP\/?08:čl\.482\b/u', $canon),
            'Expected ZKP/08 čl.482 citation missing'
        );

        // --- Optional graph projection proof ---
        if (config('neo4j.sync.enabled', true)) {
            $graph = app(GraphDatabaseService::class);
            if ($graph->isAvailable()) {
                /** @var GraphRagOrchestrator $orchestrator */
                $orchestrator = app(GraphRagOrchestrator::class);
                $orchestrator->syncCourtDecision((string) $decision->id);

                // Verify at least one CourtDecisionDocument node exists.
                $g = $graph->run(
                    'MATCH (d:CourtDecisionDocument {decision_id: $id}) RETURN count(d) as c',
                    ['id' => (string) $decision->id]
                );
                $this->assertGreaterThan(0, (int) $g->first()->get('c'));

                // If court is present, ensure DECIDED_BY relationship exists.
                $rel = $graph->run(
                    'MATCH (d:CourtDecisionDocument {decision_id: $id})-[:DECIDED_BY]->(:Court) RETURN count(d) as c',
                    ['id' => (string) $decision->id]
                );
                $this->assertGreaterThan(0, (int) $rel->first()->get('c'));
            }
        }
    }
}
