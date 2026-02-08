<?php

namespace Tests\Unit\Agents;

use App\Agents\DecisionDiscoveryAgent;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;

class DecisionDiscoveryAgentTest extends TestCase
{
    /** @test */
    public function it_generates_research_topics_via_llm()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => [
                            'nezakonit otkaz',
                            'ugovorna odgovornost',
                            'potrošačka zaštita',
                        ],
                    ])]],
                ],
            ]);

        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('generateResearchTopics');
        $method->setAccessible(true);

        $topics = $method->invoke($agent);

        $this->assertIsArray($topics);
        $this->assertCount(3, $topics);
        $this->assertEquals('nezakonit otkaz', $topics[0]);
    }

    /** @test */
    public function it_searches_for_decisions_on_topic()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->with(Mockery::on(function ($q) {
                return is_string($q) && str_contains($q, 'nezakonit otkaz');
            }), null, 50, 1)
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=nezakonit+otkaz',
                'ids' => ['id1', 'id2', 'id3'],
                'count' => 3,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(3)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 2,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        // Expect 2 calls: 1 from QueryRewriter.rewrite(), 1 from scoreDecisions()
        $mockOpenAI->shouldReceive('chat')
            ->twice()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 85, 'reasoning' => 'Relevant'],
                            ['id' => 'id2', 'score' => 75, 'reasoning' => 'Relevant'],
                            ['id' => 'id3', 'score' => 60, 'reasoning' => 'Somewhat'],
                        ],
                    ])]],
                ],
            ]);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setDecisionsPerTopic(50)
            ->setIngestPerTopic(10)
            ->setRelevanceThreshold(70);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        $result = $method->invoke($agent, 'nezakonit otkaz');

        $this->assertEquals(3, $result['found']);
        $this->assertEquals(3, $result['evaluated']);
        $this->assertEquals(2, $result['ingested']); // Only 2 above threshold of 70
        $this->assertEquals(2, $result['attempted']); // Attempted to ingest 2
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(0, $result['skipped']);
    }

    /** @test */
    public function it_scores_decisions_via_llm()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 90, 'reasoning' => 'Highly relevant'],
                            ['id' => 'id2', 'score' => 45, 'reasoning' => 'Not relevant'],
                        ],
                    ])]],
                ],
            ]);

        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        $metadata = [
            'id1' => ['title' => 'Decision 1', 'court' => 'Vrhovni sud'],
            'id2' => ['title' => 'Decision 2', 'court' => 'Općinski sud'],
        ];

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('scoreDecisions');
        $method->setAccessible(true);

        $scored = $method->invoke($agent, $metadata, 'test topic');

        $this->assertCount(2, $scored);
        $this->assertEquals(90, $scored[0]['score']);
        $this->assertEquals(45, $scored[1]['score']);
    }

    /** @test */
    public function it_filters_by_relevance_threshold()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        $agent->setRelevanceThreshold(75);

        $this->assertEquals(75, $agent->getRelevanceThreshold());
    }

    /** @test */
    public function it_respects_global_max_decisions_limit_per_topic()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['id1', 'id2', 'id3', 'id4', 'id5'],
                'count' => 5,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(5)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        // Should only ingest 3 decisions (the remaining slots from global limit)
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 3;
            }), Mockery::any())
            ->andReturn([
                'inserted' => 3,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice() // Called for QueryRewriter and scoring
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

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setIngestPerTopic(10) // Normally would ingest 10
            ->setRelevanceThreshold(70);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        // Pass maxIngest = 3 to simulate remaining slots
        $result = $method->invoke($agent, 'test topic', 3);

        $this->assertEquals(5, $result['evaluated']);
        $this->assertEquals(3, $result['ingested']); // Capped at 3, not 10
    }

    /** @test */
    public function it_uses_ingest_per_topic_when_no_global_limit()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['id1', 'id2', 'id3', 'id4', 'id5', 'id6', 'id7', 'id8', 'id9', 'id10'],
                'count' => 10,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(10)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        // Should ingest 5 decisions (ingestPerTopic setting)
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 5;
            }), Mockery::any())
            ->andReturn([
                'inserted' => 5,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice() // Called for QueryRewriter and scoring
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => array_map(fn ($i) => [
                            'id' => "id{$i}",
                            'score' => 90 - $i,
                            'reasoning' => 'Relevant',
                        ], range(1, 10)),
                    ])]],
                ],
            ]);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setIngestPerTopic(5)
            ->setRelevanceThreshold(70);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        // No maxIngest passed, should use ingestPerTopic
        $result = $method->invoke($agent, 'test topic', null);

        $this->assertEquals(10, $result['evaluated']);
        $this->assertEquals(5, $result['ingested']); // Uses ingestPerTopic
    }

    /** @test */
    public function it_stops_when_max_ingest_is_zero()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['id1', 'id2', 'id3'],
                'count' => 3,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(3)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        // Should not ingest any decisions when maxIngest is 0

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice() // Called for QueryRewriter and scoring
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 90, 'reasoning' => 'Relevant'],
                            ['id' => 'id2', 'score' => 85, 'reasoning' => 'Relevant'],
                            ['id' => 'id3', 'score' => 80, 'reasoning' => 'Relevant'],
                        ],
                    ])]],
                ],
            ]);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setIngestPerTopic(10)
            ->setRelevanceThreshold(70);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        // maxIngest = 0 means global limit already reached
        $result = $method->invoke($agent, 'test topic', 0);

        $this->assertEquals(3, $result['evaluated']);
        $this->assertEquals(0, $result['ingested']); // Should ingest nothing
    }

    /** @test */
    public function it_tracks_ingestion_errors_accurately()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['id1', 'id2', 'id3', 'id4', 'id5'],
                'count' => 5,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(5)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        // Mock ingestion to return partial success (some errors, some skipped)
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 2,  // Only 2 successfully ingested
                'errors' => 2,    // 2 had errors
                'skipped' => 1,   // 1 was skipped
            ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice() // Called for QueryRewriter and scoring
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

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setIngestPerTopic(10)
            ->setRelevanceThreshold(70);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        $result = $method->invoke($agent, 'test topic');

        // Verify accurate stats
        $this->assertEquals(5, $result['evaluated']);
        $this->assertEquals(2, $result['ingested']);  // Actual successful insertions
        $this->assertEquals(5, $result['attempted']); // Tried to ingest all 5
        $this->assertEquals(2, $result['errors']);    // Tracks errors from service
        $this->assertEquals(1, $result['skipped']);   // Tracks skipped from service
    }

    /** @test */
    public function it_tracks_complete_ingestion_failure()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['id1', 'id2', 'id3'],
                'count' => 3,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(3)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        // Mock complete ingestion failure (all errors)
        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 0,  // Nothing ingested
                'errors' => 3,    // All failed
                'skipped' => 0,
            ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice() // Called for QueryRewriter and scoring
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 90, 'reasoning' => 'Relevant'],
                            ['id' => 'id2', 'score' => 85, 'reasoning' => 'Relevant'],
                            ['id' => 'id3', 'score' => 80, 'reasoning' => 'Relevant'],
                        ],
                    ])]],
                ],
            ]);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setRelevanceThreshold(70);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        $result = $method->invoke($agent, 'test topic');

        // Verify accurate stats showing complete failure
        $this->assertEquals(3, $result['evaluated']);
        $this->assertEquals(0, $result['ingested']);  // Nothing successfully ingested
        $this->assertEquals(3, $result['attempted']); // Tried to ingest 3
        $this->assertEquals(3, $result['errors']);    // All 3 failed
        $this->assertEquals(0, $result['skipped']);
    }

    /** @test */
    public function it_discovers_for_single_topic()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->with(Mockery::on(function ($q) {
                return is_string($q) && str_contains($q, 'nezakonit otkaz');
            }), null, 50, 1)
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=nezakonit+otkaz',
                'ids' => ['id1', 'id2', 'id3'],
                'count' => 3,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(3)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 2; // 2 decisions above threshold
            }), Mockery::any())
            ->andReturn([
                'inserted' => 2,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        // Should NOT call topic generation - using provided topic
        $mockOpenAI->shouldReceive('chat')
            ->twice() // Called for QueryRewriter and scoring (not topic generation)
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'id1', 'score' => 85, 'reasoning' => 'Relevant'],
                            ['id' => 'id2', 'score' => 75, 'reasoning' => 'Relevant'],
                            ['id' => 'id3', 'score' => 60, 'reasoning' => 'Somewhat'],
                        ],
                    ])]],
                ],
            ]);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setDecisionsPerTopic(50)
            ->setIngestPerTopic(10)
            ->setRelevanceThreshold(70);

        $stats = $agent->discoverSingleTopic('nezakonit otkaz');

        $this->assertEquals(1, $stats['topics_generated']);
        $this->assertEquals(3, $stats['decisions_evaluated']);
        $this->assertEquals(2, $stats['decisions_ingested']); // Only 2 above threshold of 70
    }

    /** @test */
    public function it_respects_global_limit_in_single_topic_discovery()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => ['id1', 'id2', 'id3', 'id4', 'id5', 'id6', 'id7', 'id8'],
                'count' => 8,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(8)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        // Should only ingest 3 decisions (global limit)
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->with(Mockery::on(function ($ids) {
                return count($ids) === 3;
            }), Mockery::any())
            ->andReturn([
                'inserted' => 3,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice() // Called for QueryRewriter and scoring
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

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setIngestPerTopic(10)
            ->setRelevanceThreshold(70)
            ->setMaxDecisionsGlobal(3); // Global limit

        $stats = $agent->discoverSingleTopic('test topic');

        $this->assertEquals(1, $stats['topics_generated']);
        $this->assertEquals(8, $stats['decisions_evaluated']);
        $this->assertEquals(3, $stats['decisions_ingested']); // Capped at 3 by global limit
    }

    /** @test */
    public function it_uses_different_cache_keys_for_different_topics_per_run()
    {
        // Clear cache before test
        \Illuminate\Support\Facades\Cache::flush();

        // First request for 3 topics
        $mockOpenAI1 = Mockery::mock(OpenAIService::class);
        $mockOpenAI1->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => [
                            'topic 1',
                            'topic 2',
                            'topic 3',
                        ],
                    ])]],
                ],
            ]);

        $mockClient1 = Mockery::mock(OdlukeClient::class);
        $mockIngest1 = Mockery::mock(OdlukeIngestService::class);

        $agent1 = new DecisionDiscoveryAgent($mockClient1, $mockIngest1, $mockOpenAI1);
        $agent1->setTopicsPerRun(3);

        $reflection1 = new \ReflectionClass($agent1);
        $method1 = $reflection1->getMethod('generateResearchTopics');
        $method1->setAccessible(true);

        $topics1 = $method1->invoke($agent1);

        $this->assertCount(3, $topics1);

        // Second request for 5 topics - should call LLM again (different cache key)
        $mockOpenAI2 = Mockery::mock(OpenAIService::class);
        $mockOpenAI2->shouldReceive('chat')
            ->once() // Should call LLM because cache key is different
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => [
                            'topic A',
                            'topic B',
                            'topic C',
                            'topic D',
                            'topic E',
                        ],
                    ])]],
                ],
            ]);

        $mockClient2 = Mockery::mock(OdlukeClient::class);
        $mockIngest2 = Mockery::mock(OdlukeIngestService::class);

        $agent2 = new DecisionDiscoveryAgent($mockClient2, $mockIngest2, $mockOpenAI2);
        $agent2->setTopicsPerRun(5);

        $reflection2 = new \ReflectionClass($agent2);
        $method2 = $reflection2->getMethod('generateResearchTopics');
        $method2->setAccessible(true);

        $topics2 = $method2->invoke($agent2);

        $this->assertCount(5, $topics2);
        $this->assertEquals('topic A', $topics2[0]);
    }

    /** @test */
    public function it_uses_cached_topics_when_topics_per_run_matches()
    {
        // Clear cache before test
        \Illuminate\Support\Facades\Cache::flush();

        // First call - should hit LLM
        $mockOpenAI1 = Mockery::mock(OpenAIService::class);
        $mockOpenAI1->shouldReceive('chat')
            ->once() // Only called once
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => [
                            'cached topic 1',
                            'cached topic 2',
                            'cached topic 3',
                            'cached topic 4',
                        ],
                    ])]],
                ],
            ]);

        $mockClient1 = Mockery::mock(OdlukeClient::class);
        $mockIngest1 = Mockery::mock(OdlukeIngestService::class);

        $agent1 = new DecisionDiscoveryAgent($mockClient1, $mockIngest1, $mockOpenAI1);
        $agent1->setTopicsPerRun(4);

        $reflection1 = new \ReflectionClass($agent1);
        $method1 = $reflection1->getMethod('generateResearchTopics');
        $method1->setAccessible(true);

        $topics1 = $method1->invoke($agent1);

        $this->assertCount(4, $topics1);
        $this->assertEquals('cached topic 1', $topics1[0]);

        // Second call with same topicsPerRun - should use cache, NOT call LLM
        $mockOpenAI2 = Mockery::mock(OpenAIService::class);
        // Should NOT receive any chat calls - using cache

        $mockClient2 = Mockery::mock(OdlukeClient::class);
        $mockIngest2 = Mockery::mock(OdlukeIngestService::class);

        $agent2 = new DecisionDiscoveryAgent($mockClient2, $mockIngest2, $mockOpenAI2);
        $agent2->setTopicsPerRun(4); // Same as first call

        $reflection2 = new \ReflectionClass($agent2);
        $method2 = $reflection2->getMethod('generateResearchTopics');
        $method2->setAccessible(true);

        $topics2 = $method2->invoke($agent2);

        // Should return exact same cached topics
        $this->assertCount(4, $topics2);
        $this->assertEquals('cached topic 1', $topics2[0]);
        $this->assertEquals($topics1, $topics2);
    }

    /** @test */
    public function it_returns_correct_number_of_topics_from_cache_for_each_request_size()
    {
        // Clear cache before test
        \Illuminate\Support\Facades\Cache::flush();

        // Generate and cache 3 topics
        $mockOpenAI3 = Mockery::mock(OpenAIService::class);
        $mockOpenAI3->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => ['3-topic A', '3-topic B', '3-topic C'],
                    ])]],
                ],
            ]);

        $agent3 = new DecisionDiscoveryAgent(
            Mockery::mock(OdlukeClient::class),
            Mockery::mock(OdlukeIngestService::class),
            $mockOpenAI3
        );
        $agent3->setTopicsPerRun(3);

        $reflection = new \ReflectionClass($agent3);
        $method = $reflection->getMethod('generateResearchTopics');
        $method->setAccessible(true);

        $topics3 = $method->invoke($agent3);
        $this->assertCount(3, $topics3);

        // Generate and cache 7 topics
        $mockOpenAI7 = Mockery::mock(OpenAIService::class);
        $mockOpenAI7->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'topics' => ['7-topic A', '7-topic B', '7-topic C', '7-topic D', '7-topic E', '7-topic F', '7-topic G'],
                    ])]],
                ],
            ]);

        $agent7 = new DecisionDiscoveryAgent(
            Mockery::mock(OdlukeClient::class),
            Mockery::mock(OdlukeIngestService::class),
            $mockOpenAI7
        );
        $agent7->setTopicsPerRun(7);

        $topics7 = $method->invoke($agent7);
        $this->assertCount(7, $topics7);

        // Request 3 topics again - should get cached 3 topics, not 7
        $agentVerify3 = new DecisionDiscoveryAgent(
            Mockery::mock(OdlukeClient::class),
            Mockery::mock(OdlukeIngestService::class),
            Mockery::mock(OpenAIService::class) // No chat() expectation - should use cache
        );
        $agentVerify3->setTopicsPerRun(3);

        $verifyTopics3 = $method->invoke($agentVerify3);
        $this->assertCount(3, $verifyTopics3);
        $this->assertEquals('3-topic A', $verifyTopics3[0]);

        // Request 7 topics again - should get cached 7 topics, not 3
        $agentVerify7 = new DecisionDiscoveryAgent(
            Mockery::mock(OdlukeClient::class),
            Mockery::mock(OdlukeIngestService::class),
            Mockery::mock(OpenAIService::class) // No chat() expectation - should use cache
        );
        $agentVerify7->setTopicsPerRun(7);

        $verifyTopics7 = $method->invoke($agentVerify7);
        $this->assertCount(7, $verifyTopics7);
        $this->assertEquals('7-topic A', $verifyTopics7[0]);
    }

    /** @test */
    public function it_handles_api_errors_during_search()
    {
        // Mock client to return error response
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/test',
                'ids' => [],
                'error' => 'Connection timeout',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        // Should return early with error count
        $result = $method->invoke($agent, 'test topic');

        $this->assertEquals(0, $result['evaluated']);
        $this->assertEquals(0, $result['ingested']);
        $this->assertEquals(0, $result['attempted']);
        $this->assertEquals(1, $result['errors']); // Error tracked
        $this->assertEquals(0, $result['skipped']);
    }

    /** @test */
    public function it_handles_non_200_status_during_search()
    {
        // Mock client to return non-200 status
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/test',
                'ids' => [],
                'status' => 503, // Service unavailable
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        // Should return early with error count
        $result = $method->invoke($agent, 'test topic');

        $this->assertEquals(0, $result['evaluated']);
        $this->assertEquals(0, $result['ingested']);
        $this->assertEquals(0, $result['attempted']);
        $this->assertEquals(1, $result['errors']); // Error tracked
        $this->assertEquals(0, $result['skipped']);
    }

    /** @test */
    public function it_handles_legitimate_empty_search_results()
    {
        // Mock client to return successful but empty response
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/test',
                'ids' => [], // Empty but successful
                'count' => 0,
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockOpenAI = Mockery::mock(OpenAIService::class);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        // Should return with no errors (legitimate empty result)
        $result = $method->invoke($agent, 'test topic');

        $this->assertEquals(0, $result['evaluated']);
        $this->assertEquals(0, $result['ingested']);
        $this->assertEquals(0, $result['attempted']);
        $this->assertEquals(0, $result['errors']); // No error - legitimate empty
        $this->assertEquals(0, $result['skipped']);
    }

    /** @test */
    public function it_distinguishes_between_errors_and_empty_results_in_stats()
    {
        // Test that error responses increment error count but empty results don't

        // Scenario 1: API error
        $mockClient1 = Mockery::mock(OdlukeClient::class);
        $mockClient1->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn(['url' => 'test', 'ids' => [], 'error' => 'timeout']);

        $agent1 = new DecisionDiscoveryAgent(
            $mockClient1,
            Mockery::mock(OdlukeIngestService::class),
            Mockery::mock(OpenAIService::class)
        );

        $reflection = new \ReflectionClass($agent1);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        $result1 = $method->invoke($agent1, 'topic');
        $this->assertEquals(1, $result1['errors'], 'API error should increment error count');

        // Scenario 2: Empty but successful
        $mockClient2 = Mockery::mock(OdlukeClient::class);
        $mockClient2->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn(['url' => 'test', 'ids' => [], 'count' => 0]);

        $agent2 = new DecisionDiscoveryAgent(
            $mockClient2,
            Mockery::mock(OdlukeIngestService::class),
            Mockery::mock(OpenAIService::class)
        );

        $result2 = $method->invoke($agent2, 'topic');
        $this->assertEquals(0, $result2['errors'], 'Empty result should NOT increment error count');
    }
}
