<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\LegalFactPattern;
use App\Models\User;
use App\Services\LegalReasoning\FactPatternExtractor;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class FactPatternExtractorTest extends TestCase
{
    use RefreshDatabase;

    protected FactPatternExtractor $extractor;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->extractor = new FactPatternExtractor($this->openAIMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_extracts_facts_from_narrative_successfully()
    {
        // Arrange
        $narrative = 'On January 15, 2024, John Doe signed a contract with ABC Corp to deliver goods worth $50,000. ABC Corp failed to pay after delivery.';

        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'legal_area' => 'contract',
                                'confidence' => 0.85,
                                'structured_facts' => [
                                    'parties' => [
                                        ['name' => 'John Doe', 'role' => 'plaintiff', 'type' => 'individual'],
                                        ['name' => 'ABC Corp', 'role' => 'defendant', 'type' => 'corporation'],
                                    ],
                                    'events' => [
                                        [
                                            'description' => 'Contract signed',
                                            'date' => '2024-01-15',
                                            'significance' => 'Formation of contract',
                                        ],
                                    ],
                                    'legal_issues' => [
                                        [
                                            'issue' => 'Breach of contract - failure to pay',
                                            'area_of_law' => 'contract',
                                        ],
                                    ],
                                    'damages_or_relief_sought' => [
                                        'type' => 'monetary',
                                        'amount' => '$50,000',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        // Act
        $result = $this->extractor->extract($narrative);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('contract', $result['legal_area']);
        $this->assertEquals(0.85, $result['extraction_confidence']);
        $this->assertArrayHasKey('structured_facts', $result);
        $this->assertArrayHasKey('parties', $result['structured_facts']);
        $this->assertCount(2, $result['structured_facts']['parties']);
    }

    /** @test */
    public function it_saves_fact_pattern_to_database_when_requested()
    {
        // Arrange
        $user = User::factory()->create();
        $narrative = 'A legal dispute about property ownership.';

        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'legal_area' => 'property',
                                'confidence' => 0.75,
                                'structured_facts' => [
                                    'parties' => [],
                                    'events' => [],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        // Act
        $factPattern = $this->extractor->extract($narrative, $user->id, ['save' => true]);

        // Assert
        $this->assertInstanceOf(LegalFactPattern::class, $factPattern);
        $this->assertEquals($user->id, $factPattern->user_id);
        $this->assertEquals($narrative, $factPattern->raw_narrative);
        $this->assertEquals('property', $factPattern->legal_area);
        $this->assertEquals(0.75, $factPattern->extraction_confidence);
        $this->assertDatabaseHas('legal_fact_patterns', [
            'user_id' => $user->id,
            'legal_area' => 'property',
        ]);
    }

    /** @test */
    public function it_uses_cache_when_available()
    {
        // Arrange
        $narrative = 'Cached narrative';
        $cacheKey = 'fact_pattern:'.md5($narrative);

        $cachedResult = [
            'success' => true,
            'legal_area' => 'tort',
            'extraction_confidence' => 0.9,
            'structured_facts' => [],
        ];

        Cache::put($cacheKey, $cachedResult, now()->addHour());

        // OpenAI should NOT be called since we're using cache
        $this->openAIMock->shouldNotReceive('chat');

        // Act
        $result = $this->extractor->extract($narrative);

        // Assert
        $this->assertEquals('tort', $result['legal_area']);
        $this->assertEquals(0.9, $result['extraction_confidence']);
    }

    /** @test */
    public function it_handles_llm_failures_gracefully()
    {
        // Arrange
        $narrative = 'Some narrative';

        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('LLM API error'));

        // Act
        $result = $this->extractor->extract($narrative);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('LLM API error', $result['error']);
    }

    /** @test */
    public function it_handles_invalid_json_response()
    {
        // Arrange
        $narrative = 'Some narrative';

        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'invalid json {{{',
                        ],
                    ],
                ],
            ]);

        // Act
        $result = $this->extractor->extract($narrative);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /** @test */
    public function it_batch_extracts_multiple_narratives()
    {
        // Arrange
        $narratives = [
            'narrative1' => 'Contract dispute',
            'narrative2' => 'Personal injury case',
        ];

        $this->openAIMock
            ->shouldReceive('chat')
            ->times(2)
            ->andReturn(
                [
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'legal_area' => 'contract',
                                    'confidence' => 0.8,
                                    'structured_facts' => [],
                                ]),
                            ],
                        ],
                    ],
                ],
                [
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'legal_area' => 'tort',
                                    'confidence' => 0.9,
                                    'structured_facts' => [],
                                ]),
                            ],
                        ],
                    ],
                ]
            );

        // Act
        $result = $this->extractor->batchExtract($narratives);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['extracted']);
        $this->assertEquals(0, $result['failed']);
        $this->assertArrayHasKey('narrative1', $result['results']);
        $this->assertArrayHasKey('narrative2', $result['results']);
    }

    /** @test */
    public function it_compares_two_fact_patterns()
    {
        // Arrange
        $user = User::factory()->create();

        $pattern1 = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Pattern 1',
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'John Doe', 'role' => 'plaintiff'],
                    ['name' => 'ABC Corp', 'role' => 'defendant'],
                ],
                'legal_issues' => [
                    ['area_of_law' => 'breach of contract'],
                ],
            ],
        ]);

        $pattern2 = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Pattern 2',
            'legal_area' => 'contract',
            'extraction_confidence' => 0.85,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'John Doe', 'role' => 'plaintiff'],
                    ['name' => 'XYZ Inc', 'role' => 'defendant'],
                ],
                'legal_issues' => [
                    ['area_of_law' => 'breach of contract'],
                ],
            ],
        ]);

        // Act
        $similarity = $this->extractor->comparePatterns($pattern1, $pattern2);

        // Assert
        $this->assertTrue($similarity['same_legal_area']);
        $this->assertArrayHasKey('party_overlap', $similarity);
        $this->assertArrayHasKey('legal_issue_overlap', $similarity);
        $this->assertArrayHasKey('overall_similarity', $similarity);
        $this->assertGreaterThan(0, $similarity['overall_similarity']);
    }

    /** @test */
    public function it_retrieves_user_fact_patterns()
    {
        // Arrange
        $user = User::factory()->create();

        LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Pattern 1',
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
            'structured_facts' => [],
        ]);

        LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Pattern 2',
            'legal_area' => 'tort',
            'extraction_confidence' => 0.9,
            'structured_facts' => [],
        ]);

        // Act
        $patterns = $this->extractor->getUserFactPatterns($user->id);

        // Assert
        $this->assertCount(2, $patterns);
        $this->assertEquals($user->id, $patterns->first()->user_id);
    }

    /** @test */
    public function it_searches_by_legal_area()
    {
        // Arrange
        $user = User::factory()->create();

        LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Pattern 1',
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
            'structured_facts' => [],
        ]);

        LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Pattern 2',
            'legal_area' => 'tort',
            'extraction_confidence' => 0.9,
            'structured_facts' => [],
        ]);

        // Act
        $patterns = $this->extractor->searchByLegalArea('contract');

        // Assert
        $this->assertCount(1, $patterns);
        $this->assertEquals('contract', $patterns->first()->legal_area);
    }

    /** @test */
    public function it_retrieves_high_confidence_patterns()
    {
        // Arrange
        $user = User::factory()->create();

        LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Low confidence',
            'legal_area' => 'contract',
            'extraction_confidence' => 0.5,
            'structured_facts' => [],
        ]);

        LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'High confidence',
            'legal_area' => 'tort',
            'extraction_confidence' => 0.9,
            'structured_facts' => [],
        ]);

        // Act
        $patterns = $this->extractor->getHighConfidencePatterns(0.7);

        // Assert
        $this->assertCount(1, $patterns);
        $this->assertGreaterThanOrEqual(0.7, $patterns->first()->extraction_confidence);
    }

    /** @test */
    public function it_cleans_markdown_from_json_response()
    {
        // Arrange
        $narrative = 'Test narrative';

        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => "```json\n".json_encode([
                                'legal_area' => 'contract',
                                'confidence' => 0.8,
                                'structured_facts' => [],
                            ])."\n```",
                        ],
                    ],
                ],
            ]);

        // Act
        $result = $this->extractor->extract($narrative);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('contract', $result['legal_area']);
    }

    /** @test */
    public function model_has_helper_methods()
    {
        // Arrange
        $user = User::factory()->create();

        $pattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test',
            'legal_area' => 'contract',
            'extraction_confidence' => 0.85,
            'structured_facts' => [
                'parties' => [['name' => 'John']],
                'events' => [['description' => 'Event 1']],
                'legal_issues' => [['issue' => 'Issue 1']],
            ],
        ]);

        // Assert
        $this->assertTrue($pattern->hasHighConfidence());
        $this->assertFalse($pattern->hasLowConfidence());
        $this->assertCount(1, $pattern->getParties());
        $this->assertCount(1, $pattern->getEvents());
        $this->assertCount(1, $pattern->getLegalIssues());
    }

    /** @test */
    public function model_confidence_levels_are_correct()
    {
        // Arrange
        $user = User::factory()->create();

        $highConfidence = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'High',
            'legal_area' => 'contract',
            'extraction_confidence' => 0.75,
            'structured_facts' => [],
        ]);

        $lowConfidence = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Low',
            'legal_area' => 'contract',
            'extraction_confidence' => 0.4,
            'structured_facts' => [],
        ]);

        // Assert
        $this->assertTrue($highConfidence->hasHighConfidence());
        $this->assertFalse($highConfidence->hasLowConfidence());
        $this->assertFalse($lowConfidence->hasHighConfidence());
        $this->assertTrue($lowConfidence->hasLowConfidence());
    }
}
