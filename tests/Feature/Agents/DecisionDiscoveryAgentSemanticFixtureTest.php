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
 * "Impossible" domain-level test (deterministic):
 *
 * Manufacture a topic + 5 candidate decisions where only 1 is a true match.
 * Then assert the DecisionDiscoveryAgent ingests ONLY the correct one.
 *
 * This is not testing LLM intelligence; it's testing the agent contract:
 *  - it must obey the scoring result
 *  - it must enforce relevance threshold
 *  - it must ingest only top candidates
 */
class DecisionDiscoveryAgentSemanticFixtureTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_single_relevant_decision_is_selected_and_ingested_for_illegal_search_topic(): void
    {
        $topic = 'nezakonit pretres doma rezultirao nezakonitim dokazima';

        // ---- Mocks ----
        $client = Mockery::mock(OdlukeClient::class);
        $ingest = Mockery::mock(OdlukeIngestService::class);
        $openai = Mockery::mock(OpenAIService::class);

        // Trace service is DB-backed; mock it to keep test deterministic & fast.
        $trace = Mockery::mock(ReasoningTraceService::class);
        $trace->shouldReceive('startTrace')->andReturn('trace-id');
        $trace->shouldReceive('endTrace')->andReturn(true);

        // Active learning would create DB records; mock to prevent side effects.
        $learning = Mockery::mock(ActiveLearningService::class);
        $learning->shouldReceive('identifyLearningOpportunity')->byDefault();

        // ---- Candidate IDs ----
        $ids = ['dec-A', 'dec-B', 'dec-C', 'dec-D', 'dec-E'];

        $client->shouldReceive('collectIdsFromList')
            ->once()
            ->with(Mockery::on(function ($q) {
                // translateTopicToQuery expands the topic; we only assert it retained core intent
                $q = mb_strtolower((string) $q);
                return str_contains($q, 'pretres') || str_contains($q, 'nezakonit') || str_contains($q, 'dokaz');
            }), null, 5, 1)
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=test',
                'ids' => $ids,
                'count' => count($ids),
            ]);

        // Metadata is used only for prompt formatting; still worth providing realistic signals.
        foreach ($ids as $id) {
            $client->shouldReceive('fetchDecisionMeta')
                ->with($id)
                ->andReturn([
                    'title' => "Decision {$id}",
                    'court' => $id === 'dec-C' ? 'Vrhovni sud Republike Hrvatske' : 'Općinski sud',
                    'date' => '2024-01-01',
                    'type' => $id === 'dec-C' ? 'Presuda' : 'Rješenje',
                    'description' => $id === 'dec-C'
                        ? 'Pretres doma, nezakoniti dokazi, isključenje dokaza, ZKP čl. 222'
                        : 'Irrelevant civil matter',
                ]);
        }

        // LLM scoring output: ONLY dec-C above threshold.
        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                ['id' => 'dec-A', 'score' => 15, 'reasoning' => 'Not related'],
                                ['id' => 'dec-B', 'score' => 20, 'reasoning' => 'Not related'],
                                ['id' => 'dec-C', 'score' => 95, 'reasoning' => 'Directly about illegal home search and unlawful evidence'],
                                ['id' => 'dec-D', 'score' => 40, 'reasoning' => 'Tangential'],
                                ['id' => 'dec-E', 'score' => 10, 'reasoning' => 'Noise'],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]);

        $ingest->shouldReceive('ingestByIds')
            ->once()
            ->with(['dec-C'], Mockery::on(function ($opts) {
                return ($opts['sync_graph'] ?? null) === true;
            }))
            ->andReturn([
                'inserted' => 1,
                'errors' => 0,
                'skipped' => 0,
            ]);

        // ---- Execute ----
        $agent = new DecisionDiscoveryAgent($client, $ingest, $openai, null, $trace, $learning);
        $agent->setDecisionsPerTopic(5)
            ->setIngestPerTopic(5)
            ->setRelevanceThreshold(70.0);

        $stats = $agent->discoverSingleTopic($topic);

        // ---- Assertions ----
        $this->assertEquals(1, $stats['topics_generated']);
        $this->assertEquals(5, $stats['decisions_evaluated']);
        $this->assertEquals(1, $stats['decisions_ingested']);
        $this->assertEquals(1, $stats['decisions_attempted']);
    }
}
