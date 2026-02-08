<?php

namespace Tests\Feature\Agents;

use App\Agents\DecisionDiscoveryAgent;
use App\Services\ActiveLearningService;
use App\Services\Explainability\ReasoningTraceService;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;


/**
 * DecisionDiscoveryAgent proving tests: contract-level invariants.
 *
 * Reasoning:g
 * These tests avoid "model intelligence" and instead prove deterministic agent contracts:
 * - Ordering: ingest IDs sorted by score desc.
 * - Max ingest: ingestPerTopic is a hard cap.
 * - No-ingest on API error: when search fails, ingestion must not be attempted.
 *
 * The contracts are observable via method calls and returned stats.
 */
class DecisionDiscoveryAgentContractsTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }


    private function makeTraceMock(): ReasoningTraceService
    {
        $trace = Mockery::mock(ReasoningTraceService::class);
        $trace->shouldReceive('startTrace')->andReturn('trace');
        $trace->shouldReceive('endTrace')->andReturnNull();

        return $trace;
    }

    private function makeLearningMock(): ActiveLearningService
    {
        $learning = Mockery::mock(ActiveLearningService::class);
        $learning->shouldReceive('identifyLearningOpportunity')->andReturnNull();

        return $learning;
    }

    /**
     * Invariant: decisions passed to ingestByIds are ordered by descending score
     * and independent of the original API order.
     *
     * We:
     * - return IDs in order A,B,C,D from collectIdsFromList
     * - score them so that C>A>D>B
     * - assert ingestByIds receives IDs in [C,A,D,B]
     */
    public function test_discover_single_topic_ingests_ids_ordered_by_descending_score(): void
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // collectIdsFromList returns IDs in unsorted order A, B, C, D
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['A', 'B', 'C', 'D'],
                'count' => 4,
                'status' => 200,
            ]);

        // Minimal metadata for each decision
        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(4)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
                'type' => 'Presuda',
                'description' => 'Test description',
            ]);

        // Score batch so that scores are in order C > A > D > B
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                ['id' => 'C', 'score' => 95, 'reasoning' => 'Highly relevant'],
                                ['id' => 'A', 'score' => 90, 'reasoning' => 'Relevant'],
                                ['id' => 'D', 'score' => 85, 'reasoning' => 'Relevant'],
                                ['id' => 'B', 'score' => 80, 'reasoning' => 'Relevant'],
                            ],
                        ]),
                    ],
                ]],
                'usage' => ['total_tokens' => 100],
            ]);

        // Expect ingestByIds to be called exactly once with IDs in score order C, A, D, B
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function (array $ids) {
                return $ids === ['C', 'A', 'D', 'B'];
            }), Mockery::on(function (array $options) {
                // Ensure core options are preserved
                return ($options['sync_graph'] ?? false) === true
                    && ($options['chunk_chars'] ?? null) === 1500
                    && ($options['overlap'] ?? null) === 200;
            }))
            ->andReturn([
                'inserted' => 4,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        // Allow more than enough per-topic ingest so ordering is not constrained by the cap
        $agent->setIngestPerTopic(10);
        $agent->setRelevanceThreshold(70.0);

        $stats = $agent->discoverSingleTopic('nezakonit pretres doma i nezakoniti dokazi');

        $this->assertSame(4, $stats['decisions_evaluated']);
        $this->assertSame(4, $stats['decisions_ingested']);
        $this->assertSame(4, $stats['decisions_attempted']);
        $this->assertSame(0, $stats['ingestion_errors']);
    }

    /**
     * Invariant: per-topic ingest is capped by ingestPerTopic.
     *
     * We:
     * - provide 6 candidates all above threshold
     * - set ingestPerTopic = 3
     * - assert ingestByIds receives exactly 3 IDs
     */
    public function test_discover_single_topic_respects_ingest_per_topic_cap(): void
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // 6 candidate IDs, all will be scored above threshold
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['id1', 'id2', 'id3', 'id4', 'id5', 'id6'],
                'count' => 6,
                'status' => 200,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(6)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
                'type' => 'Presuda',
                'description' => 'Test description',
            ]);

        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                ['id' => 'id1', 'score' => 95, 'reasoning' => 'Relevant'],
                                ['id' => 'id2', 'score' => 94, 'reasoning' => 'Relevant'],
                                ['id' => 'id3', 'score' => 93, 'reasoning' => 'Relevant'],
                                ['id' => 'id4', 'score' => 92, 'reasoning' => 'Relevant'],
                                ['id' => 'id5', 'score' => 91, 'reasoning' => 'Relevant'],
                                ['id' => 'id6', 'score' => 90, 'reasoning' => 'Relevant'],
                            ],
                        ]),
                    ],
                ]],
                'usage' => ['total_tokens' => 100],
            ]);

        // ingestPerTopic = 3, so only 3 IDs should be ingested
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function (array $ids) {
                return count($ids) === 3;
            }), Mockery::type('array'))
            ->andReturn([
                'inserted' => 3,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setIngestPerTopic(3);
        $agent->setRelevanceThreshold(70.0);

        $stats = $agent->discoverSingleTopic('topic with many candidates');

        $this->assertSame(6, $stats['decisions_evaluated']);
        $this->assertSame(3, $stats['decisions_ingested']);
        $this->assertSame(3, $stats['decisions_attempted']);
        $this->assertSame(0, $stats['ingestion_errors']);


        $topic = 'nezakonit pretres doma';

        $ids = ['id_low', 'id_high', 'id_mid'];

        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?...',
                'ids' => $ids,
                'count' => 3,
            ]);

        foreach ($ids as $id) {
            $mockClient->shouldReceive('fetchDecisionMeta')
                ->with($id)
                ->andReturn([
                    'title' => 'Test Decision',
                    'court' => 'Vrhovni sud Republike Hrvatske',
                    'date' => '2024-01-01',
                    'type' => 'Presuda',
                    'description' => 'Pretres doma; ZKP čl. 222; nezakoniti dokazi.',
                ]);
        }

        // Return scores UNSORTED to ensure the agent sorts deterministically.
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id_low', 'score' => 71, 'reasoning' => 'Barely relevant'],
                            ['id' => 'id_high', 'score' => 95, 'reasoning' => 'Highly relevant'],
                            ['id' => 'id_mid', 'score' => 80, 'reasoning' => 'Relevant'],
                        ],
                    ])]],
                ],
            ]);

        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(['id_high', 'id_mid', 'id_low'], Mockery::on(function ($opts) {
                return ($opts['sync_graph'] ?? null) === true
                    && ($opts['chunk_chars'] ?? null) === 1500
                    && ($opts['overlap'] ?? null) === 200;
            }))
            ->andReturn(['inserted' => 3, 'errors' => 0, 'skipped' => 0]);

        $agent = new DecisionDiscoveryAgent(
            $mockClient,
            $mockIngest,
            $mockOpenAI,
            rewriter: null,
            traceService: $this->makeTraceMock(),
            learningService: $this->makeLearningMock(),
        );

        $agent->setDecisionsPerTopic(3);
        $agent->setIngestPerTopic(10);
        $agent->setRelevanceThreshold(70.0);

        $stats = $agent->discoverSingleTopic($topic);

        $this->assertEquals(3, $stats['decisions_evaluated']);
        $this->assertEquals(3, $stats['decisions_ingested']);
        $this->assertEquals(0, $stats['ingestion_errors']);
    }

    /**
     * Invariant: when a tighter maxIngest is provided (via global cap), the
     * effective per-topic ingest is min(ingestPerTopic, maxIngest).
     */
    public function test_discover_single_topic_respects_global_max_ingest_cap(): void
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        $topic = 'pretres doma proporcionalnost';

        $ids = ['id1', 'id2', 'id3', 'id4'];

        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?...',
                'ids' => $ids,
                'count' => 4,
            ]);

        foreach ($ids as $id) {
            $mockClient->shouldReceive('fetchDecisionMeta')
                ->with($id)
                ->andReturn([
                    'title' => 'Test Decision',
                    'court' => 'Županijski sud',
                    'date' => '2024-01-01',
                    'type' => 'Presuda',
                    'description' => 'Pretres doma; ZKP čl. 222.',
                ]);
        }

        // All above threshold, but ingestPerTopic should cap to 2.
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 91, 'reasoning' => 'A'],
                            ['id' => 'id2', 'score' => 88, 'reasoning' => 'B'],
                            ['id' => 'id3', 'score' => 85, 'reasoning' => 'C'],
                            ['id' => 'id4', 'score' => 80, 'reasoning' => 'D'],
                        ],
                    ])]],
                ],
            ]);

        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(['id1', 'id2'], Mockery::any())
            ->andReturn(['inserted' => 2, 'errors' => 0, 'skipped' => 0]);

        $agent = new DecisionDiscoveryAgent(
            $mockClient,
            $mockIngest,
            $mockOpenAI,
            rewriter: null,
            traceService: $this->makeTraceMock(),
            learningService: $this->makeLearningMock(),
        );

        $agent->setDecisionsPerTopic(4);
        $agent->setIngestPerTopic(2);
        $agent->setRelevanceThreshold(70.0);

        $stats = $agent->discoverSingleTopic($topic);

        $this->assertEquals(4, $stats['decisions_evaluated']);
        $this->assertEquals(2, $stats['decisions_ingested']);
    }



    /** @test */
    public function it_does_not_ingest_when_search_api_returns_error(): void
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['id1', 'id2', 'id3', 'id4', 'id5', 'id6', 'id7', 'id8'],
                'count' => 8,
                'status' => 200,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(8)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
                'type' => 'Presuda',
                'description' => 'Test description',
            ]);

        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => array_map(fn ($i) => [
                                'id' => "id{$i}",
                                'score' => 100 - $i,
                                'reasoning' => 'Relevant',
                            ], range(1, 8)),
                        ]),
                    ],
                ]],
                'usage' => ['total_tokens' => 100],
            ]);

        // ingestPerTopic = 10, but global maxDecisionsGlobal = 5, so only 5 IDs should be ingested
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function (array $ids) {
                return count($ids) === 5;
            }), Mockery::type('array'))
            ->andReturn([
                'inserted' => 5,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setIngestPerTopic(10);
        $agent->setRelevanceThreshold(70.0);
        $agent->setMaxDecisionsGlobal(5);

        $stats = $agent->discoverSingleTopic('topic with global cap');

        $this->assertSame(8, $stats['decisions_evaluated']);
        $this->assertSame(5, $stats['decisions_ingested']);
        $this->assertSame(5, $stats['decisions_attempted']);
        $this->assertSame(0, $stats['ingestion_errors']);
    }

    /**
     * Invariant: when Odluke search returns an API error, no ingestion is attempted
     * and an error is recorded.
     */
    public function test_discover_single_topic_handles_api_error_without_ingestion(): void
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => null,
                'error' => 'Timeout',
            ]);

        $mockClient->shouldNotReceive('fetchDecisionMeta');
        $mockOpenAI->shouldNotReceive('chat');
        $mockIngest->shouldNotReceive('ingestByIds');

        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        $stats = $agent->discoverSingleTopic('api error topic');

        $this->assertSame(0, $stats['decisions_evaluated']);
        $this->assertSame(0, $stats['decisions_ingested']);
        $this->assertSame(0, $stats['decisions_attempted']);
        $this->assertSame(1, $stats['ingestion_errors']);
    }

    /**
     * Invariant: when Odluke search returns non-200 status, no ingestion is
     * attempted and an error is recorded.
     */
    public function test_discover_single_topic_handles_non_200_status_without_ingestion(): void
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $topic = 'pretres doma';
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['whatever'],
                'count' => 1,
                'status' => 500,
            ]);

        $mockClient->shouldNotReceive('fetchDecisionMeta');
        $mockOpenAI->shouldNotReceive('chat');
        $mockIngest->shouldNotReceive('ingestByIds');

        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        $stats = $agent->discoverSingleTopic($topic);

        $this->assertSame(0, $stats['decisions_evaluated']);
        $this->assertSame(0, $stats['decisions_ingested']);
        $this->assertSame(0, $stats['decisions_attempted']);
        $this->assertSame(1, $stats['ingestion_errors']);
    }

    /**
     * Invariant: legitimate empty result (no IDs, 200 status) is not an error
     * and does not trigger ingestion.
     */
    public function test_discover_single_topic_treats_empty_results_as_no_error(): void
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => [],
                'count' => 0,
                'status' => 200,
            ]);

        $mockClient->shouldNotReceive('fetchDecisionMeta');
        $mockOpenAI->shouldNotReceive('chat');
        $mockIngest->shouldNotReceive('ingestByIds');

        $agent = new DecisionDiscoveryAgent(
            $mockClient,
            $mockIngest,
            $mockOpenAI,
            rewriter: null,
            traceService: $this->makeTraceMock(),
            learningService: $this->makeLearningMock(),
        );

        $agent->setDecisionsPerTopic(10);
        $agent->setIngestPerTopic(10);
        $agent->setRelevanceThreshold(70.0);

        $stats = $agent->discoverSingleTopic('no results topic');

        $this->assertEquals(0, $stats['decisions_evaluated']);
        $this->assertEquals(0, $stats['decisions_ingested']);
        $this->assertSame(0, $stats['decisions_attempted']);
        $this->assertEquals(1, $stats['ingestion_errors']);
    }
}
