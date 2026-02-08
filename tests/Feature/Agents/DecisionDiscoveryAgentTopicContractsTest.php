<?php

namespace Tests\Feature\Agents;

use App\Agents\DecisionDiscoveryAgent;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Domain-level contract tests for DecisionDiscoveryAgent single-topic discovery.
 *
 * These tests exercise discoverSingleTopic()/discoverForTopic() directly with
 * small, deterministic fixtures to prove that:
 * - decisions are ingested in descending score order
 * - per-topic ingest limits are respected (ingestPerTopic and maxIngest)
 * - Odluke API errors/non-200 responses never trigger ingestion
 * - legitimate empty results are treated as a non-error "no results" state
 */
class DecisionDiscoveryAgentTopicContractsTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_ingests_decisions_in_descending_score_order_for_single_topic(): void
    {
        // Arrange
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Odluke returns IDs in unsorted order
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['A', 'B', 'C', 'D'],
                'count' => 4,
                'status' => 200,
            ]);

        // Minimal metadata for each ID
        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(4)
            ->andReturnUsing(function (string $id) {
                return [
                    'title' => "Decision {$id}",
                    'court' => 'Vrhovni sud',
                    'date' => '2024-01-01',
                    'type' => 'Presuda',
                    'description' => 'Test description',
                ];
            });

        // LLM scoring: C highest, then A, then D, then B
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
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
                    ],
                ],
            ]);

        // Assert ingestion is called with IDs ordered by score: C, A, D, B
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function (array $ids) {
                return $ids === ['C', 'A', 'D', 'B'];
            }), Mockery::on(function (array $options) {
                // Verify core options are as expected
                return ($options['sync_graph'] ?? null) === true
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

        // Act
        $stats = $agent->discoverSingleTopic('nezakonit pretres doma i nezakoniti dokazi');

        // Assert
        $this->assertSame(4, $stats['decisions_evaluated']);
        $this->assertSame(4, $stats['decisions_ingested']);
        $this->assertSame(4, $stats['decisions_attempted']);
        $this->assertSame(0, $stats['ingestion_errors']);
        $this->assertSame(0, $stats['decisions_skipped']);
    }

    /** @test */
    public function it_respects_ingest_per_topic_cap_for_single_topic(): void
    {
        // Arrange
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Six candidate IDs, all above threshold
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
            ]);

        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'scores' => array_map(fn ($i) => [
                                    'id' => "id{$i}",
                                    'score' => 90 - $i, // all above 70
                                    'reasoning' => 'Relevant',
                                ], range(1, 6)),
                            ]),
                        ],
                    ],
                ],
            ]);

        // Expect only 3 ingested due to ingestPerTopic cap
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function (array $ids) {
                return count($ids) === 3;
            }), Mockery::any())
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

        // Act
        $stats = $agent->discoverSingleTopic('radno pravo - otkaz ugovora o radu');

        // Assert
        $this->assertSame(6, $stats['decisions_evaluated']);
        $this->assertSame(3, $stats['decisions_ingested']);
        $this->assertSame(3, $stats['decisions_attempted']);
        $this->assertSame(0, $stats['ingestion_errors']);
    }

    /** @test */
    public function it_respects_per_topic_max_ingest_derived_from_global_limit(): void
    {
        // Arrange
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
            ]);

        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'scores' => array_map(fn ($i) => [
                                    'id' => "id{$i}",
                                    'score' => 90 - $i,
                                    'reasoning' => 'Relevant',
                                ], range(1, 8)),
                            ]),
                        ],
                    ],
                ],
            ]);

        // Global limit 5 < ingestPerTopic (default 10) so maxIngest per-topic should be 5
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function (array $ids) {
                return count($ids) === 5;
            }), Mockery::any())
            ->andReturn([
                'inserted' => 5,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setMaxDecisionsGlobal(5);

        // Act
        $stats = $agent->discoverSingleTopic('nezakonit otkaz - proporcionalnost');

        // Assert
        $this->assertSame(8, $stats['decisions_evaluated']);
        $this->assertSame(5, $stats['decisions_ingested']);
        $this->assertSame(5, $stats['decisions_attempted']);
        $this->assertSame(0, $stats['ingestion_errors']);
    }

    /** @test */
    public function it_does_not_ingest_when_odluke_returns_api_error_for_topic(): void
    {
        // Arrange
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => [],
                'error' => 'Timeout',
            ]);

        // No metadata or ingestion should be attempted
        $mockClient->shouldNotReceive('fetchDecisionMeta');
        $mockIngest->shouldNotReceive('ingestByIds');
        $mockOpenAI->shouldNotReceive('chat');

        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        // Act
        $stats = $agent->discoverSingleTopic('odluke api error test');

        // Assert
        $this->assertSame(0, $stats['decisions_evaluated']);
        $this->assertSame(0, $stats['decisions_ingested']);
        $this->assertSame(0, $stats['decisions_attempted']);
        $this->assertSame(1, $stats['ingestion_errors']);
        $this->assertSame(0, $stats['decisions_skipped']);
    }

    /** @test */
    public function it_does_not_ingest_when_odluke_returns_non_200_status_for_topic(): void
    {
        // Arrange
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['ignored-id'],
                'count' => 1,
                'status' => 500,
            ]);

        // No metadata or ingestion should be attempted
        $mockClient->shouldNotReceive('fetchDecisionMeta');
        $mockIngest->shouldNotReceive('ingestByIds');
        $mockOpenAI->shouldNotReceive('chat');

        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        // Act
        $stats = $agent->discoverSingleTopic('odluke non-200 status test');

        // Assert
        $this->assertSame(0, $stats['decisions_evaluated']);
        $this->assertSame(0, $stats['decisions_ingested']);
        $this->assertSame(0, $stats['decisions_attempted']);
        $this->assertSame(1, $stats['ingestion_errors']);
        $this->assertSame(0, $stats['decisions_skipped']);
    }

    /** @test */
    public function it_treats_legitimate_empty_results_as_non_error_state(): void
    {
        // Arrange
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => [],
                'count' => 0,
                'status' => 200,
            ]);

        // No metadata, scoring, or ingestion
        $mockClient->shouldNotReceive('fetchDecisionMeta');
        $mockOpenAI->shouldNotReceive('chat');
        $mockIngest->shouldNotReceive('ingestByIds');

        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        // Act
        $stats = $agent->discoverSingleTopic('topic-with-no-results');

        // Assert
        $this->assertSame(0, $stats['decisions_evaluated']);
        $this->assertSame(0, $stats['decisions_ingested']);
        $this->assertSame(0, $stats['decisions_attempted']);
        $this->assertSame(0, $stats['ingestion_errors']);
        $this->assertSame(0, $stats['decisions_skipped']);
    }
}
