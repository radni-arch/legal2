<?php

namespace Tests\Feature\Jobs;

use App\Agents\DecisionDiscoveryAgent;
use App\Jobs\ExecuteDecisionDiscoveryJob;
use App\Models\DecisionDiscoveryRun;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ExecuteDecisionDiscoveryJobTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_sets_global_max_decisions_on_agent()
    {
        // Mock dependencies
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Mock topic generation
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => ['Topic 1', 'Topic 2', 'Topic 3'],
                    ])]],
                ],
            ]);

        // Mock decision searching - will be called for first 2 topics only
        $mockClient->shouldReceive('collectIdsFromList')
            ->times(2) // Should stop after 2 topics when limit of 15 is reached
            ->andReturn(['id1', 'id2', 'id3', 'id4', 'id5']);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(10) // 5 decisions * 2 topics
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        // Mock scoring
        $mockOpenAI->shouldReceive('chat')
            ->times(2)
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 90, 'reasoning' => 'Relevant'],
                            ['id' => 'id2', 'score' => 85, 'reasoning' => 'Relevant'],
                            ['id' => 'id3', 'score' => 80, 'reasoning' => 'Relevant'],
                            ['id' => 'id4', 'score' => 75, 'reasoning' => 'Relevant'],
                            ['id' => 'id5', 'score' => 72, 'reasoning' => 'Relevant'],
                        ],
                    ])]],
                ],
            ]);

        // Mock ingestion - first topic ingests 10, second topic ingests 5 to reach limit of 15
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 10; // First topic: all 5 above threshold, limited by ingestPerTopic
            }), Mockery::any());

        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 5; // Second topic: only 5 to reach global limit of 15
            }), Mockery::any());

        // Bind mocks
        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        // Create and configure agent
        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setIngestPerTopic(10); // Per-topic limit

        $this->app->instance(DecisionDiscoveryAgent::class, $agent);

        // Execute job with maxDecisions = 15
        $job = new ExecuteDecisionDiscoveryJob(maxDecisions: 15);
        $job->handle($agent);

        // Verify discovery run was created
        $this->assertDatabaseHas('decision_discovery_runs', [
            'status' => 'completed',
            'topics_generated' => 3,
            'decisions_ingested' => 15, // Should stop at 15, not 30 (10 per topic * 3 topics)
        ]);
    }

    /** @test */
    public function it_does_not_set_global_limit_when_null()
    {
        // Mock dependencies
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Mock topic generation
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => ['Topic 1'],
                    ])]],
                ],
            ]);

        // Mock decision searching
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn(['id1', 'id2', 'id3', 'id4', 'id5']);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(5)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        // Mock scoring
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 90, 'reasoning' => 'Relevant'],
                            ['id' => 'id2', 'score' => 85, 'reasoning' => 'Relevant'],
                            ['id' => 'id3', 'score' => 80, 'reasoning' => 'Relevant'],
                            ['id' => 'id4', 'score' => 75, 'reasoning' => 'Relevant'],
                            ['id' => 'id5', 'score' => 72, 'reasoning' => 'Relevant'],
                        ],
                    ])]],
                ],
            ]);

        // Mock ingestion - should use default ingestPerTopic (10)
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 5; // All 5 decisions above threshold
            }), Mockery::any());

        // Bind mocks
        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        // Create agent
        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $this->app->instance(DecisionDiscoveryAgent::class, $agent);

        // Execute job WITHOUT maxDecisions
        $job = new ExecuteDecisionDiscoveryJob(maxDecisions: null);
        $job->handle($agent);

        // Verify discovery run
        $this->assertDatabaseHas('decision_discovery_runs', [
            'status' => 'completed',
            'decisions_ingested' => 5, // Uses normal ingestPerTopic behavior
        ]);
    }

    /** @test */
    public function it_stops_immediately_when_max_decisions_is_zero()
    {
        // Mock dependencies
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Mock topic generation
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => ['Topic 1', 'Topic 2'],
                    ])]],
                ],
            ]);

        // Should not search for any decisions since limit is already 0
        $mockClient->shouldNotReceive('collectIdsFromList');
        $mockIngest->shouldNotReceive('ingestByIds');

        // Bind mocks
        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        // Create agent
        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $this->app->instance(DecisionDiscoveryAgent::class, $agent);

        // Execute job with maxDecisions = 0
        $job = new ExecuteDecisionDiscoveryJob(maxDecisions: 0);
        $job->handle($agent);

        // Verify no decisions were ingested
        $this->assertDatabaseHas('decision_discovery_runs', [
            'status' => 'completed',
            'topics_generated' => 2,
            'decisions_ingested' => 0,
        ]);
    }

    /** @test */
    public function dispatch_with_env_detection_uses_global_max_in_sync_mode()
    {
        // Set to dev environment
        $this->app['env'] = 'local';

        // Mock dependencies
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Mock topic generation
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => ['Topic 1', 'Topic 2'],
                    ])]],
                ],
            ]);

        // Mock decision searching - should stop after first topic when limit of 5 is reached
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn(['id1', 'id2', 'id3', 'id4', 'id5']);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(5)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        // Mock scoring
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 90, 'reasoning' => 'Relevant'],
                            ['id' => 'id2', 'score' => 85, 'reasoning' => 'Relevant'],
                            ['id' => 'id3', 'score' => 80, 'reasoning' => 'Relevant'],
                            ['id' => 'id4', 'score' => 75, 'reasoning' => 'Relevant'],
                            ['id' => 'id5', 'score' => 72, 'reasoning' => 'Relevant'],
                        ],
                    ])]],
                ],
            ]);

        // Mock ingestion
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 5; // Should ingest exactly 5 (global limit)
            }), Mockery::any());

        // Bind mocks
        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        // Execute via static method with maxDecisions
        $result = ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(maxDecisions: 5);

        $this->assertEquals('sync', $result['mode']);
        $this->assertEquals(5, $result['stats']['decisions_ingested']);
    }

    /** @test */
    public function it_uses_specific_topic_when_provided()
    {
        // Mock dependencies
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Should NOT generate topics since we're providing one
        // Mock decision searching for the specific topic
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->with(Mockery::on(function ($q) {
                return str_contains($q, 'nezakonit otkaz');
            }), null, 50, 1)
            ->andReturn(['id1', 'id2', 'id3']);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(3)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        // Mock scoring (only once, not for topic generation)
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 90, 'reasoning' => 'Relevant'],
                            ['id' => 'id2', 'score' => 85, 'reasoning' => 'Relevant'],
                            ['id' => 'id3', 'score' => 75, 'reasoning' => 'Relevant'],
                        ],
                    ])]],
                ],
            ]);

        // Mock ingestion
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 3;
            }), Mockery::any());

        // Bind mocks
        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        // Create agent
        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $this->app->instance(DecisionDiscoveryAgent::class, $agent);

        // Execute job WITH specific topic
        $job = new ExecuteDecisionDiscoveryJob(maxDecisions: null, topic: 'nezakonit otkaz');
        $job->handle($agent);

        // Verify discovery run
        $this->assertDatabaseHas('decision_discovery_runs', [
            'status' => 'completed',
            'topics_generated' => 1,
            'decisions_ingested' => 3,
        ]);

        // Verify the topic was stored
        $run = DecisionDiscoveryRun::where('status', 'completed')->latest()->first();
        $this->assertEquals(['nezakonit otkaz'], $run->topics);
    }

    /** @test */
    public function it_respects_max_decisions_with_specific_topic()
    {
        // Mock dependencies
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Mock decision searching
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn(['id1', 'id2', 'id3', 'id4', 'id5', 'id6', 'id7', 'id8']);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(8)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        // Mock scoring
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => array_map(fn ($i) => [
                            'id' => "id{$i}",
                            'score' => 90 - $i,
                            'reasoning' => 'Relevant',
                        ], range(1, 8)),
                    ])]],
                ],
            ]);

        // Mock ingestion - should only ingest 5 (global limit)
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 5;
            }), Mockery::any());

        // Bind mocks
        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        // Create agent
        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $this->app->instance(DecisionDiscoveryAgent::class, $agent);

        // Execute job with both maxDecisions and specific topic
        $job = new ExecuteDecisionDiscoveryJob(maxDecisions: 5, topic: 'test topic');
        $job->handle($agent);

        // Verify discovery run
        $this->assertDatabaseHas('decision_discovery_runs', [
            'status' => 'completed',
            'topics_generated' => 1,
            'decisions_evaluated' => 8,
            'decisions_ingested' => 5, // Capped at 5
        ]);
    }

    /** @test */
    public function dispatch_with_env_detection_uses_specific_topic_in_sync_mode()
    {
        // Set to dev environment
        $this->app['env'] = 'local';

        // Mock dependencies
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Should NOT generate topics
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->with(Mockery::on(function ($q) {
                return str_contains($q, 'ugovorna odgovornost');
            }), null, 50, 1)
            ->andReturn(['id1', 'id2']);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(2)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        // Mock scoring
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 85, 'reasoning' => 'Relevant'],
                            ['id' => 'id2', 'score' => 75, 'reasoning' => 'Relevant'],
                        ],
                    ])]],
                ],
            ]);

        // Mock ingestion
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 2;
            }), Mockery::any());

        // Bind mocks
        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        // Execute via static method with specific topic
        $result = ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
            maxDecisions: null,
            topic: 'ugovorna odgovornost'
        );

        $this->assertEquals('sync', $result['mode']);
        $this->assertEquals(1, $result['stats']['topics_generated']);
        $this->assertEquals(2, $result['stats']['decisions_ingested']);
    }

    /** @test */
    public function it_uses_multi_topic_discovery_when_no_topic_provided()
    {
        // Mock dependencies
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Mock topic generation (should be called)
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => ['Topic 1'],
                    ])]],
                ],
            ]);

        // Mock decision searching
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn(['id1']);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->once()
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        // Mock scoring
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 85, 'reasoning' => 'Relevant'],
                        ],
                    ])]],
                ],
            ]);

        // Mock ingestion
        $mockIngest->shouldReceive('ingestByIds')
            ->once();

        // Bind mocks
        $this->app->instance(OdlukeClient::class, $mockClient);
        $this->app->instance(OdlukeIngestService::class, $mockIngest);
        $this->app->instance(OpenAIService::class, $mockOpenAI);

        // Create agent
        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $this->app->instance(DecisionDiscoveryAgent::class, $agent);

        // Execute job WITHOUT specific topic (should use discover())
        $job = new ExecuteDecisionDiscoveryJob(maxDecisions: null, topic: null);
        $job->handle($agent);

        // Verify discovery run used multi-topic discovery
        $this->assertDatabaseHas('decision_discovery_runs', [
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function it_resets_max_decisions_global_to_prevent_state_leakage()
    {
        // This test simulates agent instance reuse (e.g., singleton or cached instance)

        // Mock dependencies
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Create a single agent instance that will be reused
        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        // ===== First Job: maxDecisions = 5 =====

        // Mock topic generation
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => ['Topic 1'],
                    ])]],
                ],
            ]);

        // Mock decision searching
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn(['id1', 'id2', 'id3', 'id4', 'id5', 'id6', 'id7', 'id8']);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(8)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        // Mock scoring
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => array_map(fn ($i) => [
                            'id' => "id{$i}",
                            'score' => 90 - $i,
                            'reasoning' => 'Relevant',
                        ], range(1, 8)),
                    ])]],
                ],
            ]);

        // Mock ingestion - should ingest exactly 5 (global limit)
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 5;
            }), Mockery::any());

        // Execute first job with maxDecisions = 5
        $job1 = new ExecuteDecisionDiscoveryJob(maxDecisions: 5);
        $job1->handle($agent);

        // Verify maxDecisionsGlobal was set to 5
        $this->assertDatabaseHas('decision_discovery_runs', [
            'status' => 'completed',
            'decisions_ingested' => 5,
        ]);

        // ===== Second Job: maxDecisions = null (should reset to default) =====

        // Mock topic generation for second run
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => ['Topic 2'],
                    ])]],
                ],
            ]);

        // Mock decision searching
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn(['id9', 'id10', 'id11', 'id12', 'id13', 'id14', 'id15', 'id16']);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(8)
            ->andReturn([
                'title' => 'Test Decision 2',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-02',
            ]);

        // Mock scoring
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => array_map(fn ($i) => [
                            'id' => 'id'.($i + 8),
                            'score' => 90 - $i,
                            'reasoning' => 'Relevant',
                        ], range(1, 8)),
                    ])]],
                ],
            ]);

        // Mock ingestion - should ingest 8 (NOT limited to 5 from previous job)
        // Because maxDecisions=null should reset maxDecisionsGlobal to null
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 8; // All 8 decisions, not capped at 5
            }), Mockery::any());

        // Execute second job with maxDecisions = null (should reset limit)
        $job2 = new ExecuteDecisionDiscoveryJob(maxDecisions: null);
        $job2->handle($agent);

        // Verify that the second run ingested 8 decisions (not limited by the previous job's 5)
        $latestRun = DecisionDiscoveryRun::where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        $this->assertEquals(8, $latestRun->decisions_ingested);
    }
}
