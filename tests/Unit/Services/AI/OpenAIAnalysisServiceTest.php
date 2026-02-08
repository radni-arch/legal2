<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\OpenAIAnalysisService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OpenAIAnalysisServiceTest extends TestCase
{
    protected OpenAIAnalysisService $service;

    protected OpenAIService $openaiService;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure OpenAI settings
        Config::set('openai.api_key', 'test-key');
        Config::set('openai.base_url', 'https://api.openai.com/v1');
        Config::set('openai.models.chat', 'gpt-4o');
        Config::set('openai.cache.ttl', 3600);

        $this->openaiService = app(OpenAIService::class);
        $this->service = new OpenAIAnalysisService($this->openaiService);

        // Clear cache before each test
        Cache::flush();
    }

    // ========== ANALYZE LEGAL TEXT TESTS ==========

    /** @test */
    public function it_analyzes_legal_text_basic()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'main_topic' => 'pretraga stana',
                                'legal_issues' => ['nezakonita pretraga', 'povreda prava na privatnost'],
                                'cited_laws' => ['ZKP članak 240', 'Ustav RH članak 34'],
                                'key_points' => ['Pretraga bez naredbe', 'Kršenje procesnih pravila'],
                                'confidence' => 0.9,
                                'summary' => 'Analiza nezakonite pretrage stana',
                            ]),
                        ],
                    ],
                ],
                'usage' => ['total_tokens' => 200],
            ], 200),
        ]);

        $result = $this->service->analyzeLegalText('Tekst o pretrazi stana...');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('main_topic', $result);
        $this->assertArrayHasKey('legal_issues', $result);
        $this->assertArrayHasKey('cited_laws', $result);
        $this->assertEquals('pretraga stana', $result['main_topic']);
        $this->assertCount(2, $result['legal_issues']);
    }

    /** @test */
    public function it_returns_structured_legal_analysis_data()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'main_topic' => 'kazneno pravo',
                        'legal_issues' => ['narkotici', 'proporcionalnost kazne'],
                        'cited_laws' => ['Kazneni zakon članak 173'],
                        'key_points' => ['Posjedovanje droge', 'Potrebna kazna'],
                        'confidence' => 0.85,
                        'summary' => 'Analiza slučaja posjedovanja narkotika',
                    ])],
                ]],
                'usage' => ['total_tokens' => 150],
            ], 200),
        ]);

        $result = $this->service->analyzeLegalText('Text about drug possession...');

        $this->assertEquals('kazneno pravo', $result['main_topic']);
        $this->assertContains('narkotici', $result['legal_issues']);
        $this->assertContains('Kazneni zakon članak 173', $result['cited_laws']);
        $this->assertEquals(0.85, $result['confidence']);
        $this->assertIsString($result['summary']);
    }

    /** @test */
    public function it_caches_legal_text_analysis_results()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'main_topic' => 'test',
                        'legal_issues' => [],
                        'cited_laws' => [],
                        'key_points' => [],
                        'confidence' => 0.9,
                        'summary' => 'Test',
                    ])],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $text = 'Same legal text';

        // First call - should hit API
        $result1 = $this->service->analyzeLegalText($text);

        // Second call - should use cache
        $result2 = $this->service->analyzeLegalText($text);

        // API should only be called once
        Http::assertSentCount(1);

        $this->assertEquals($result1, $result2);
    }

    /** @test */
    public function it_respects_cache_disable_option()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'main_topic' => 'test',
                        'legal_issues' => [],
                        'cited_laws' => [],
                        'key_points' => [],
                        'confidence' => 0.9,
                        'summary' => 'Test',
                    ])],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $text = 'Same legal text';

        // First call with cache disabled
        $result1 = $this->service->analyzeLegalText($text, ['cache' => false]);

        // Second call with cache disabled
        $result2 = $this->service->analyzeLegalText($text, ['cache' => false]);

        // API should be called twice
        Http::assertSentCount(2);
    }

    /** @test */
    public function it_accepts_custom_analysis_topics()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'main_topic' => 'pretraga',
                        'legal_issues' => ['proporcionalnost'],
                        'cited_laws' => [],
                        'key_points' => [],
                        'confidence' => 0.8,
                        'summary' => 'Test',
                    ])],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $this->service->analyzeLegalText('Text', [
            'topics' => ['proporcionalnost', 'pretraga stana'],
        ]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            $userMessage = collect($body['messages'])->firstWhere('role', 'user');

            return str_contains($userMessage['content'], 'proporcionalnost');
        });
    }

    /** @test */
    public function it_handles_analysis_errors_gracefully()
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'API Error']], 500),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Legal text analysis failed', \Mockery::type('array'));

        $this->expectException(\Throwable::class);

        $this->service->analyzeLegalText('Text');
    }

    // ========== SUMMARIZE TESTS ==========

    /** @test */
    public function it_summarizes_text_basic()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Sažetak: Sud je odbio žalbu...'],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $summary = $this->service->summarize('Very long decision text...');

        $this->assertIsString($summary);
        $this->assertStringContainsString('Sažetak', $summary);
    }

    /** @test */
    public function it_respects_max_length_in_summarization()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => str_repeat('Very long summary text ', 100)],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $maxLength = 200;
        $summary = $this->service->summarize('Text', $maxLength);

        $this->assertLessThanOrEqual($maxLength, strlen($summary));
    }

    /** @test */
    public function it_caches_summaries()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Summary text'],
                ]],
                'usage' => ['total_tokens' => 50],
            ], 200),
        ]);

        $text = 'Same text to summarize';

        $summary1 = $this->service->summarize($text);
        $summary2 = $this->service->summarize($text);

        Http::assertSentCount(1);
        $this->assertEquals($summary1, $summary2);
    }

    /** @test */
    public function it_handles_summarization_errors()
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'Error']], 500),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Text summarization failed', \Mockery::type('array'));

        $this->expectException(\Throwable::class);

        $this->service->summarize('Text');
    }

    // ========== EXTRACT STRUCTURED DATA TESTS ==========

    /** @test */
    public function it_extracts_structured_data_according_to_schema()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'case_number' => 'Pp-123/2024',
                        'court' => 'Županijski sud u Osijeku',
                        'date' => '2024-03-15',
                    ])],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $schema = [
            'case_number' => 'Case/decision number',
            'court' => 'Court name',
            'date' => 'Decision date',
        ];

        $result = $this->service->extractStructuredData('Decision text...', $schema);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('case_number', $result);
        $this->assertArrayHasKey('court', $result);
        $this->assertArrayHasKey('date', $result);
        $this->assertEquals('Pp-123/2024', $result['case_number']);
    }

    /** @test */
    public function it_caches_structured_data_extraction()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode(['field' => 'value'])],
                ]],
                'usage' => ['total_tokens' => 50],
            ], 200),
        ]);

        $text = 'Same text';
        $schema = ['field' => 'description'];

        $result1 = $this->service->extractStructuredData($text, $schema);
        $result2 = $this->service->extractStructuredData($text, $schema);

        Http::assertSentCount(1);
        $this->assertEquals($result1, $result2);
    }

    /** @test */
    public function it_handles_invalid_json_in_extraction()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Invalid JSON response'],
                ]],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse extracted data');

        $this->service->extractStructuredData('Text', ['field' => 'desc']);
    }

    // ========== SUMMARIZE DECISION TESTS ==========

    /** @test */
    public function it_summarizes_court_decisions()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Županijski sud u Osijeku odlučio je...'],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $summary = $this->service->summarizeDecision('Long court decision...');

        $this->assertIsString($summary);
        $this->assertStringContainsString('sud', mb_strtolower($summary));
    }

    /** @test */
    public function it_accepts_custom_max_length_for_decisions()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => str_repeat('Text ', 200)],
                ]],
            ], 200),
        ]);

        $summary = $this->service->summarizeDecision('Decision', ['max_length' => 300]);

        $this->assertLessThanOrEqual(300, strlen($summary));
    }

    // ========== EXTRACT CITATIONS TESTS ==========

    /** @test */
    public function it_extracts_citations_basic()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'citations' => [
                            ['type' => 'ZKP', 'article' => '240', 'paragraph' => null, 'item' => null, 'raw' => 'ZKP članak 240'],
                            ['type' => 'Ustav RH', 'article' => '34', 'paragraph' => '2', 'item' => null, 'raw' => 'Ustav RH članak 34 stavak 2'],
                        ],
                    ])],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $citations = $this->service->extractCitations('Text mentioning ZKP 240 and Ustav RH 34...');

        $this->assertIsArray($citations);
        $this->assertCount(2, $citations);
        $this->assertEquals('ZKP', $citations[0]['type']);
        $this->assertEquals('240', $citations[0]['article']);
    }

    /** @test */
    public function it_extracts_zkp_citations()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'citations' => [
                            ['type' => 'ZKP', 'article' => '240', 'paragraph' => '1', 'item' => null, 'raw' => 'ZKP 240 stavak 1'],
                        ],
                    ])],
                ]],
            ], 200),
        ]);

        $citations = $this->service->extractCitations('Prema ZKP 240 stavak 1...');

        $this->assertNotEmpty($citations);
        $this->assertEquals('ZKP', $citations[0]['type']);
    }

    /** @test */
    public function it_extracts_ustav_citations()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'citations' => [
                            ['type' => 'Ustav RH', 'article' => '34', 'paragraph' => null, 'item' => null, 'raw' => 'Ustav RH članak 34'],
                        ],
                    ])],
                ]],
            ], 200),
        ]);

        $citations = $this->service->extractCitations('Ustav RH članak 34 garantira...');

        $this->assertNotEmpty($citations);
        $this->assertEquals('Ustav RH', $citations[0]['type']);
    }

    /** @test */
    public function it_extracts_kazneni_zakon_citations()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'citations' => [
                            ['type' => 'Kazneni zakon', 'article' => '173', 'paragraph' => '2', 'item' => '1', 'raw' => 'Kazneni zakon članak 173 stavak 2 točka 1'],
                        ],
                    ])],
                ]],
            ], 200),
        ]);

        $citations = $this->service->extractCitations('Kazneni zakon članak 173...');

        $this->assertNotEmpty($citations);
        $this->assertEquals('Kazneni zakon', $citations[0]['type']);
        $this->assertEquals('173', $citations[0]['article']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_citations_found()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode(['citations' => []])],
                ]],
            ], 200),
        ]);

        $citations = $this->service->extractCitations('Text without any citations');

        $this->assertIsArray($citations);
        $this->assertEmpty($citations);
    }

    /** @test */
    public function it_caches_citation_extraction()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode(['citations' => []])],
                ]],
            ], 200),
        ]);

        $text = 'Same text';

        $citations1 = $this->service->extractCitations($text);
        $citations2 = $this->service->extractCitations($text);

        Http::assertSentCount(1);
        $this->assertEquals($citations1, $citations2);
    }

    // ========== CLASSIFY DOCUMENT TESTS ==========

    /** @test */
    public function it_classifies_document_into_category()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Kazneno pravo'],
                ]],
            ], 200),
        ]);

        $categories = ['Kazneno pravo', 'Građansko pravo', 'Upravno pravo'];

        $category = $this->service->classifyDocument('Text about criminal law...', $categories);

        $this->assertIsString($category);
        $this->assertEquals('Kazneno pravo', $category);
    }

    /** @test */
    public function it_handles_fuzzy_category_matching()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Ovo je Kazneno pravo slučaj'],
                ]],
            ], 200),
        ]);

        $categories = ['Kazneno pravo', 'Građansko pravo'];

        $category = $this->service->classifyDocument('Text', $categories);

        // Should match "Kazneno pravo" despite extra text
        $this->assertEquals('Kazneno pravo', $category);
    }

    /** @test */
    public function it_caches_document_classification()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Category A'],
                ]],
            ], 200),
        ]);

        $text = 'Same text';
        $categories = ['Category A', 'Category B'];

        $cat1 = $this->service->classifyDocument($text, $categories);
        $cat2 = $this->service->classifyDocument($text, $categories);

        Http::assertSentCount(1);
        $this->assertEquals($cat1, $cat2);
    }

    // ========== EXTRACT ENTITIES TESTS ==========

    /** @test */
    public function it_extracts_legal_entities()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'courts' => ['Županijski sud u Osijeku'],
                        'laws' => ['ZKP', 'Ustav RH'],
                        'case_numbers' => ['Pp-123/2024'],
                        'parties' => ['Tužitelj: Država', 'Optuženik: Ivan Horvat'],
                        'dates' => ['2024-03-15'],
                        'locations' => ['Osijek'],
                    ])],
                ]],
            ], 200),
        ]);

        $entities = $this->service->extractEntities('Court decision text...');

        $this->assertIsArray($entities);
        $this->assertArrayHasKey('courts', $entities);
        $this->assertArrayHasKey('laws', $entities);
        $this->assertArrayHasKey('case_numbers', $entities);
        $this->assertContains('Županijski sud u Osijeku', $entities['courts']);
    }

    // ========== CACHE KEY GENERATION TESTS ==========

    /** @test */
    public function it_generates_consistent_cache_keys()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($this->service, 'operation', ['param' => 'value']);
        $key2 = $method->invoke($this->service, 'operation', ['param' => 'value']);

        $this->assertEquals($key1, $key2);
    }

    /** @test */
    public function it_generates_different_keys_for_different_operations()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($this->service, 'operation1', ['param' => 'value']);
        $key2 = $method->invoke($this->service, 'operation2', ['param' => 'value']);

        $this->assertNotEquals($key1, $key2);
    }

    /** @test */
    public function it_generates_different_keys_for_different_params()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($this->service, 'operation', ['param' => 'value1']);
        $key2 = $method->invoke($this->service, 'operation', ['param' => 'value2']);

        $this->assertNotEquals($key1, $key2);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
