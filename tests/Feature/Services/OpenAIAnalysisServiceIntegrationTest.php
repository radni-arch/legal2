<?php

namespace Tests\Feature\Services;

use App\Contracts\AI\AnalysisServiceInterface;
use App\Contracts\AI\ChatServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration tests for OpenAIAnalysisService
 *
 * Tests the full integration of the analysis service with:
 * - ChatServiceInterface for LLM calls
 * - Cache for response caching
 * - Croatian legal citation extraction
 */
class OpenAIAnalysisServiceIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected AnalysisServiceInterface $analysisService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analysisService = app(AnalysisServiceInterface::class);

        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test analysis service delegates to chat service and uses cache
     *
     * Flow: Legal text → Analysis service → Chat service → Cache → OpenAI → Parse → Return
     *
     * Verifies:
     * - Service correctly calls chat service with proper prompts
     * - Response is parsed and structured correctly
     * - Cache is used for duplicate requests
     * - HTTP calls are minimized through caching
     */
    public function test_analysis_service_uses_chat_and_cache(): void
    {
        // Fake HTTP responses from OpenAI API
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-test123',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'main_topic' => 'Kazneni postupak',
                                'legal_issues' => [
                                    'Pravo na pravično suđenje',
                                    'Dokazna sredstva',
                                ],
                                'cited_laws' => [
                                    'ZKP Članak 9',
                                    'Ustav RH Članak 29',
                                ],
                                'key_points' => [
                                    'Sud mora osigurati objektivnost u postupku',
                                    'Dokazi moraju biti zakonito pribavljeni',
                                ],
                                'confidence' => 0.92,
                                'summary' => 'Analiza kaznenog postupka s naglaskom na pravičnost i zakonitost dokaza',
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 150,
                    'completion_tokens' => 200,
                    'total_tokens' => 350,
                ],
            ]),
        ]);

        // First call - should make HTTP request
        $result = $this->analysisService->analyzeLegalText(
            'Test legal text about criminal procedure and evidence admissibility under Croatian law'
        );

        // Assert result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('main_topic', $result);
        $this->assertArrayHasKey('legal_issues', $result);
        $this->assertArrayHasKey('cited_laws', $result);
        $this->assertArrayHasKey('key_points', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('summary', $result);

        // Assert result content
        $this->assertEquals('Kazneni postupak', $result['main_topic']);
        $this->assertCount(2, $result['legal_issues']);
        $this->assertContains('Pravo na pravično suđenje', $result['legal_issues']);
        $this->assertContains('Dokazna sredstva', $result['legal_issues']);

        // Assert citations
        $this->assertCount(2, $result['cited_laws']);
        $this->assertContains('ZKP Članak 9', $result['cited_laws']);
        $this->assertContains('Ustav RH Članak 29', $result['cited_laws']);

        // Assert key points
        $this->assertCount(2, $result['key_points']);
        $this->assertContains('Sud mora osigurati objektivnost u postupku', $result['key_points']);

        // Assert confidence
        $this->assertEquals(0.92, $result['confidence']);
        $this->assertGreaterThan(0.0, $result['confidence']);
        $this->assertLessThanOrEqual(1.0, $result['confidence']);

        // Assert summary
        $this->assertNotEmpty($result['summary']);
        $this->assertStringContainsString('kaznenog postupka', $result['summary']);

        // Verify HTTP was called once
        Http::assertSentCount(1);

        // Second call with same text - should be cached
        $result2 = $this->analysisService->analyzeLegalText(
            'Test legal text about criminal procedure and evidence admissibility under Croatian law'
        );

        // Assert same result
        $this->assertEquals($result, $result2);

        // Verify HTTP was NOT called again (still 1 total)
        Http::assertSentCount(1);
    }

    /**
     * Test citation extraction for Croatian legal references
     *
     * Should recognize and extract:
     * - ZKP (Zakon o kaznenom postupku)
     * - KZ / Kazneni zakon (Criminal Code)
     * - Ustav RH / Ustav Republike Hrvatske (Croatian Constitution)
     * - NN XX/YY (Narodne Novine - Official Gazette)
     * - Other Croatian laws
     *
     * Verifies:
     * - All Croatian citation formats are recognized
     * - Citations are properly structured
     * - Articles, paragraphs, and items are extracted
     */
    public function test_analysis_service_extracts_croatian_citations(): void
    {
        // Test text with various Croatian legal citations
        $testText = <<<'TEXT'
Prema ZKP Članak 9, stavak 1, državno odvjetništvo i sud dužni su sa jednakom pažnjom
utvrditi i okolnosti koje terete okrivljenika i okolnosti koje mu idu u korist.

Kazneni zakon Članak 87, stavak 2 određuje kaznu za kazneno djelo.

Sukladno Ustavu Republike Hrvatske Članak 29, stavak 1, svatko ima pravo na slobodu i
osobnu sigurnost.

Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13,
145/13, 152/14, 70/17, 126/19, 126/19) regulira kazneni postupak u Republici Hrvatskoj.

ZKP Članak 215 regulira pretres stana.
TEXT;

        // Fake OpenAI response with extracted citations
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-citations123',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'citations' => [
                                    [
                                        'type' => 'ZKP',
                                        'article' => '9',
                                        'paragraph' => '1',
                                        'item' => null,
                                        'raw' => 'ZKP Članak 9, stavak 1',
                                    ],
                                    [
                                        'type' => 'Kazneni zakon',
                                        'article' => '87',
                                        'paragraph' => '2',
                                        'item' => null,
                                        'raw' => 'Kazneni zakon Članak 87, stavak 2',
                                    ],
                                    [
                                        'type' => 'Ustav RH',
                                        'article' => '29',
                                        'paragraph' => '1',
                                        'item' => null,
                                        'raw' => 'Ustavu Republike Hrvatske Članak 29, stavak 1',
                                    ],
                                    [
                                        'type' => 'NN',
                                        'article' => null,
                                        'paragraph' => null,
                                        'item' => null,
                                        'raw' => 'NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 126/19',
                                    ],
                                    [
                                        'type' => 'ZKP',
                                        'article' => '215',
                                        'paragraph' => null,
                                        'item' => null,
                                        'raw' => 'ZKP Članak 215',
                                    ],
                                ],
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 200,
                    'completion_tokens' => 250,
                    'total_tokens' => 450,
                ],
            ]),
        ]);

        // Extract citations
        $citations = $this->analysisService->extractCitations($testText);

        // Assert citations array
        $this->assertIsArray($citations);
        $this->assertCount(5, $citations);

        // Test ZKP Članak 9, stavak 1
        $zkp9 = collect($citations)->firstWhere('article', '9');
        $this->assertNotNull($zkp9);
        $this->assertEquals('ZKP', $zkp9['type']);
        $this->assertEquals('9', $zkp9['article']);
        $this->assertEquals('1', $zkp9['paragraph']);
        $this->assertNull($zkp9['item']);
        $this->assertStringContainsString('ZKP Članak 9', $zkp9['raw']);

        // Test Kazneni zakon Članak 87, stavak 2
        $kz87 = collect($citations)->where('type', 'Kazneni zakon')->where('article', '87')->first();
        $this->assertNotNull($kz87);
        $this->assertEquals('Kazneni zakon', $kz87['type']);
        $this->assertEquals('87', $kz87['article']);
        $this->assertEquals('2', $kz87['paragraph']);
        $this->assertStringContainsString('Kazneni zakon Članak 87', $kz87['raw']);

        // Test Ustav RH Članak 29, stavak 1
        $ustav29 = collect($citations)->where('type', 'Ustav RH')->where('article', '29')->first();
        $this->assertNotNull($ustav29);
        $this->assertEquals('Ustav RH', $ustav29['type']);
        $this->assertEquals('29', $ustav29['article']);
        $this->assertEquals('1', $ustav29['paragraph']);
        $this->assertStringContainsString('Ustavu Republike Hrvatske Članak 29', $ustav29['raw']);

        // Test NN (Narodne Novine) reference
        $nn = collect($citations)->firstWhere('type', 'NN');
        $this->assertNotNull($nn);
        $this->assertEquals('NN', $nn['type']);
        $this->assertNull($nn['article']); // NN references don't have articles
        $this->assertStringContainsString('NN 152/08', $nn['raw']);

        // Test ZKP Članak 215 (without paragraph)
        $zkp215 = collect($citations)->where('type', 'ZKP')->where('article', '215')->first();
        $this->assertNotNull($zkp215);
        $this->assertEquals('ZKP', $zkp215['type']);
        $this->assertEquals('215', $zkp215['article']);
        $this->assertNull($zkp215['paragraph']); // No paragraph specified
        $this->assertStringContainsString('ZKP Članak 215', $zkp215['raw']);

        // Verify all citations have required fields
        foreach ($citations as $citation) {
            $this->assertArrayHasKey('type', $citation);
            $this->assertArrayHasKey('article', $citation);
            $this->assertArrayHasKey('paragraph', $citation);
            $this->assertArrayHasKey('item', $citation);
            $this->assertArrayHasKey('raw', $citation);
        }

        // Verify HTTP was called once
        Http::assertSentCount(1);

        // Test caching - second call should not make HTTP request
        $citations2 = $this->analysisService->extractCitations($testText);

        $this->assertEquals($citations, $citations2);
        Http::assertSentCount(1); // Still 1 (cached)
    }

    /**
     * Test that analysis service handles cache option correctly
     */
    public function test_analysis_service_respects_cache_option(): void
    {
        $responsePayload1 = [
            'id' => 'chatcmpl-nocache-1',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'main_topic' => 'Test 1',
                            'legal_issues' => [],
                            'cited_laws' => [],
                            'key_points' => [],
                            'confidence' => 0.9,
                            'summary' => 'Test summary 1',
                        ]),
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 100,
                'total_tokens' => 200,
            ],
        ];

        $responsePayload2 = [
            'id' => 'chatcmpl-nocache-2',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'main_topic' => 'Test 2',
                            'legal_issues' => [],
                            'cited_laws' => [],
                            'key_points' => [],
                            'confidence' => 0.85,
                            'summary' => 'Test summary 2',
                        ]),
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 110,
                'completion_tokens' => 110,
                'total_tokens' => 220,
            ],
        ];

        // Set up fake to respond to multiple requests with different responses
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push($responsePayload1, 200)
                ->push($responsePayload2, 200),
        ]);

        // First call with cache disabled and unique text
        $result1 = $this->analysisService->analyzeLegalText(
            'First unique test text for cache option test',
            ['cache' => false]
        );

        $this->assertEquals('Test 1', $result1['main_topic']);
        Http::assertSentCount(1);

        // Second call with different text and cache disabled - should make new HTTP request
        // Even though both have cache=false, they have different texts so different cache keys
        $result2 = $this->analysisService->analyzeLegalText(
            'Second unique test text for cache option test',
            ['cache' => false]
        );

        // Should have made 2 HTTP requests (no caching, different texts)
        Http::assertSentCount(2);

        // Results should be different (different texts, different responses)
        $this->assertEquals('Test 2', $result2['main_topic']);
        $this->assertNotEquals($result1['main_topic'], $result2['main_topic']);
    }
}
