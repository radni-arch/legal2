<?php

namespace Tests\Unit\Services;

use App\Services\OpenAIService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIEmbeddingsAndAnalysisTest extends TestCase
{
    protected OpenAIService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure OpenAI settings
        Config::set('openai.api_key', 'test-key-12345');
        Config::set('openai.base_url', 'https://api.openai.com/v1');
        Config::set('openai.organization', null);
        Config::set('openai.project', null);
        Config::set('openai.models.embeddings', 'text-embedding-3-small');
        Config::set('openai.models.chat', 'gpt-4o');

        $this->service = app(OpenAIService::class);
    }

    // ========== EMBEDDING TESTS ==========

    /** @test */
    public function it_generates_embedding_vector_for_text()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => array_fill(0, 1536, 0.1),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => [
                    'prompt_tokens' => 5,
                    'total_tokens' => 5,
                ],
            ], 200),
        ]);

        $embedding = $this->service->createEmbedding('pretraga stana');

        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding);
        $this->assertEquals(0.1, $embedding[0]);
    }

    /** @test */
    public function it_uses_text_embedding_3_small_model_by_default()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ], 200),
        ]);

        $this->service->embeddings('test');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['model'] === 'text-embedding-3-small';
        });
    }

    /** @test */
    public function it_sends_correct_embedding_request_structure()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ], 200),
        ]);

        $this->service->embeddings('test text');

        Http::assertSent(function ($request) {
            $this->assertEquals('POST', $request->method());
            $this->assertStringContainsString('/embeddings', (string) $request->url());
            $this->assertEquals('Bearer test-key-12345', $request->header('Authorization')[0] ?? null);

            $body = json_decode($request->body(), true);
            $this->assertEquals('test text', $body['input']);
            $this->assertEquals('text-embedding-3-small', $body['model']);

            return true;
        });
    }

    /** @test */
    public function it_generates_batch_embeddings()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => array_fill(0, 1536, 0.1),
                        'index' => 0,
                    ],
                    [
                        'object' => 'embedding',
                        'embedding' => array_fill(0, 1536, 0.2),
                        'index' => 1,
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => [
                    'prompt_tokens' => 10,
                    'total_tokens' => 10,
                ],
            ], 200),
        ]);

        $texts = ['text 1', 'text 2'];
        $result = $this->service->embeddings($texts);

        $this->assertCount(2, $result['data']);
        $this->assertCount(1536, $result['data'][0]['embedding']);
        $this->assertCount(1536, $result['data'][1]['embedding']);
        $this->assertEquals(0.1, $result['data'][0]['embedding'][0]);
        $this->assertEquals(0.2, $result['data'][1]['embedding'][0]);
    }

    /** @test */
    public function it_handles_embedding_api_errors_gracefully()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid API key',
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_api_key',
                ],
            ], 401),
        ]);

        $this->expectException(\Throwable::class);

        $this->service->embeddings('test');
    }

    /** @test */
    public function it_includes_usage_statistics_in_embedding_response()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
                'usage' => [
                    'prompt_tokens' => 8,
                    'total_tokens' => 8,
                ],
            ], 200),
        ]);

        $result = $this->service->embeddings('pretraga doma naredba');

        $this->assertArrayHasKey('usage', $result);
        $this->assertEquals(8, $result['usage']['prompt_tokens']);
        $this->assertEquals(8, $result['usage']['total_tokens']);
    }

    /** @test */
    public function it_supports_custom_embedding_models()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'object' => 'list',
                'data' => [
                    ['embedding' => array_fill(0, 3072, 0.1)],
                ],
            ], 200),
        ]);

        $this->service->embeddings('test', 'text-embedding-3-large');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['model'] === 'text-embedding-3-large';
        });
    }

    // ========== LEGAL ANALYSIS TESTS ==========

    /** @test */
    public function it_analyzes_legal_text_with_structured_output()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'main_topic' => 'pretraga stana',
                                'legal_issues' => ['Ustav RH članak 34', 'ZKP članak 240'],
                                'summary' => 'Analysis of home search legality',
                                'relevant_laws' => ['ZKP', 'Ustav RH'],
                                'confidence' => 0.85,
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 150,
                    'completion_tokens' => 50,
                    'total_tokens' => 200,
                ],
            ], 200),
        ]);

        $messages = [
            ['role' => 'system', 'content' => 'You are a legal analyst. Respond with JSON.'],
            ['role' => 'user', 'content' => 'Analyze this legal text about home search'],
        ];

        $response = $this->service->chat($messages, null, ['response_format' => ['type' => 'json_object']]);

        $this->assertArrayHasKey('choices', $response);
        $this->assertNotEmpty($response['choices']);

        $content = json_decode($response['choices'][0]['message']['content'], true);

        $this->assertArrayHasKey('main_topic', $content);
        $this->assertArrayHasKey('legal_issues', $content);
        $this->assertEquals('pretraga stana', $content['main_topic']);
        $this->assertContains('Ustav RH članak 34', $content['legal_issues']);
    }

    /** @test */
    public function it_summarizes_court_decisions()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Summary: Županijski sud u Osijeku je prihvatio žalbu i poništio presudu prvostupanjskog suda zbog povrede postupka. Sud je utvrdio da je pretraga stana bila nezakonita jer nije postojao opravdan razlog za sumnju.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 500,
                    'completion_tokens' => 80,
                    'total_tokens' => 580,
                ],
            ], 200),
        ]);

        $longDecisionText = str_repeat('Long court decision text about illegal home search. ', 50);

        $messages = [
            ['role' => 'system', 'content' => 'Sažmi sudsku odluku na hrvatskom jeziku.'],
            ['role' => 'user', 'content' => $longDecisionText],
        ];

        $response = $this->service->chat($messages);

        $summary = $response['choices'][0]['message']['content'];

        $this->assertIsString($summary);
        $this->assertStringContainsString('Summary', $summary);
        $this->assertStringContainsString('sud', mb_strtolower($summary));
    }

    /** @test */
    public function it_extracts_citations_from_legal_text()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'citations' => [
                                    ['type' => 'ZKP', 'article' => '240', 'paragraph' => null],
                                    ['type' => 'Ustav RH', 'article' => '34', 'paragraph' => '2'],
                                    ['type' => 'Kazneni zakon', 'article' => '89', 'paragraph' => '1'],
                                ],
                                'confidence' => 0.92,
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $messages = [
            ['role' => 'system', 'content' => 'Extract legal citations in JSON format.'],
            ['role' => 'user', 'content' => 'Text referencing ZKP 240, Ustav RH 34 stavak 2, and Kazneni zakon članak 89 stavak 1'],
        ];

        $response = $this->service->chat($messages, null, ['response_format' => ['type' => 'json_object']]);

        $content = json_decode($response['choices'][0]['message']['content'], true);

        $this->assertArrayHasKey('citations', $content);
        $this->assertCount(3, $content['citations']);

        $this->assertEquals('ZKP', $content['citations'][0]['type']);
        $this->assertEquals('240', $content['citations'][0]['article']);

        $this->assertEquals('Ustav RH', $content['citations'][1]['type']);
        $this->assertEquals('34', $content['citations'][1]['article']);
        $this->assertEquals('2', $content['citations'][1]['paragraph']);
    }

    /** @test */
    public function it_identifies_legal_topics_in_croatian()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'topics' => [
                                    'kazneno pravo',
                                    'pretraga stana',
                                    'povreda prava na privatnost',
                                    'nedopušteni dokazi',
                                ],
                                'legal_area' => 'kazneno procesno pravo',
                                'urgency' => 'high',
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $messages = [
            ['role' => 'system', 'content' => 'Identify legal topics in Croatian.'],
            ['role' => 'user', 'content' => 'Tekst o nezakonitoj pretrazi stana i povredi ustavnih prava optuženog.'],
        ];

        $response = $this->service->chat($messages, null, ['response_format' => ['type' => 'json_object']]);

        $content = json_decode($response['choices'][0]['message']['content'], true);

        $this->assertArrayHasKey('topics', $content);
        $this->assertContains('pretraga stana', $content['topics']);
        $this->assertEquals('kazneno procesno pravo', $content['legal_area']);
    }

    // ========== GROUNDED CHAT COMPLETION TESTS ==========

    /** @test */
    public function it_creates_grounded_chat_completion_with_high_confidence()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Prema članku 240 ZKP-a [1], pretraga stana mora biti zasnovana na opravdanoj sumnji. U ovom slučaju, prema odluci Županijskog suda u Osijeku [2], nije bilo dovoljno osnova za pretresu.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 800,
                    'completion_tokens' => 100,
                    'total_tokens' => 900,
                ],
            ], 200),
        ]);

        $retrievedChunks = [
            [
                'content' => 'ZKP članak 240: Pretraga stana se može odrediti samo ako postoji opravdana sumnja...',
                'confidence' => 0.9,
                'title' => 'Zakon o kaznenom postupku',
                'corpus' => 'laws',
                'law_number' => '152/08',
                'doc_id' => 'zkp-240',
            ],
            [
                'content' => 'Županijski sud u Osijeku je poništio naredbu za pretresu stana...',
                'confidence' => 0.85,
                'title' => 'Odluka Kž-123/2024',
                'corpus' => 'court_decision_documents',
                'doc_id' => 'kz-123-2024',
            ],
        ];

        $result = $this->service->groundedChatCompletion(
            'Je li pretraga stana bila zakonita?',
            $retrievedChunks,
            ['min_confidence' => 0.5]
        );

        $this->assertTrue($result['grounded']);
        $this->assertGreaterThan(0.8, $result['confidence']);
        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('citations', $result);
        $this->assertCount(2, $result['citations']);
        $this->assertStringContainsString('ZKP', $result['answer']);
    }

    /** @test */
    public function it_refuses_with_low_confidence()
    {
        $retrievedChunks = [
            [
                'content' => 'Irrelevant content about traffic violations...',
                'confidence' => 0.3,
                'title' => 'Some unrelated document',
                'corpus' => 'laws',
                'doc_id' => 'irrelevant',
            ],
        ];

        $result = $this->service->groundedChatCompletion(
            'Je li pretraga stana bila zakonita?',
            $retrievedChunks,
            ['min_confidence' => 0.5]
        );

        $this->assertFalse($result['grounded']);
        $this->assertLessThan(0.5, $result['confidence']);
        $this->assertArrayHasKey('refusal', $result);
        $this->assertArrayHasKey('clarifications', $result);
        $this->assertStringContainsString('ne mogu pružiti pouzdan odgovor', $result['refusal']);
    }

    /** @test */
    public function it_builds_grounded_prompt_with_citations()
    {
        $chunks = [
            [
                'content' => 'Law content here',
                'confidence' => 0.9,
                'title' => 'ZKP',
                'corpus' => 'laws',
                'law_number' => '152/08',
            ],
            [
                'content' => 'Decision content here',
                'confidence' => 0.85,
                'title' => 'Court Decision',
                'corpus' => 'court_decision_documents',
                'doc_id' => 'decision-1',
            ],
        ];

        $prompt = $this->service->buildGroundedPrompt('Test query', $chunks);

        $this->assertStringContainsString('Test query', $prompt);
        $this->assertStringContainsString('[1]', $prompt);
        $this->assertStringContainsString('[2]', $prompt);
        $this->assertStringContainsString('Law content here', $prompt);
        $this->assertStringContainsString('Decision content here', $prompt);
        $this->assertStringContainsString('Zakon (NN 152/08)', $prompt);
    }

    /** @test */
    public function it_limits_chunks_by_token_budget()
    {
        $chunks = array_fill(0, 50, [
            'content' => str_repeat('Very long content ', 500), // ~2000 tokens per chunk
            'confidence' => 0.9,
            'title' => 'Test',
            'corpus' => 'laws',
        ]);

        $prompt = $this->service->buildGroundedPrompt('Test query', $chunks, [
            'max_tokens' => 4000, // Should fit only ~2 chunks
        ]);

        // Count how many chunk numbers appear
        preg_match_all('/\[\d+\]/', $prompt, $matches);
        $chunkCount = count(array_unique($matches[0]));

        // Should have significantly fewer than 50 chunks
        $this->assertLessThan(10, $chunkCount);
    }

    /** @test */
    public function it_formats_sources_correctly()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('formatSource');
        $method->setAccessible(true);

        // Test law source
        $lawChunk = [
            'corpus' => 'laws',
            'law_number' => '152/08',
            'title' => 'Zakon o kaznenom postupku',
        ];
        $lawSource = $method->invoke($this->service, $lawChunk);
        $this->assertEquals('Zakon (NN 152/08)', $lawSource);

        // Test case source
        $caseChunk = [
            'corpus' => 'cases_documents',
            'doc_id' => 'case-123',
            'title' => 'Predmet Pp-123/2024',
        ];
        $caseSource = $method->invoke($this->service, $caseChunk);
        $this->assertEquals('Predmet case-123', $caseSource);

        // Test decision source
        $decisionChunk = [
            'corpus' => 'court_decision_documents',
            'doc_id' => 'kz-456',
            'title' => 'Odluka Kž-456/2024',
        ];
        $decisionSource = $method->invoke($this->service, $decisionChunk);
        $this->assertEquals('Sudska odluka kz-456', $decisionSource);
    }

    /** @test */
    public function it_sets_appropriate_temperature_for_legal_analysis()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Response']]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $chunks = [
            [
                'content' => 'Legal content',
                'confidence' => 0.9,
                'title' => 'Law',
                'corpus' => 'laws',
            ],
        ];

        $this->service->groundedChatCompletion('Question?', $chunks);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            // Should use low temperature (0.3) for factual legal responses
            return isset($body['temperature']) && $body['temperature'] <= 0.5;
        });
    }
}
