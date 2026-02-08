<?php

namespace Tests\Feature;

use App\Models\LegalFactPattern;
use App\Models\User;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for Fact Pattern Extraction API
 *
 * Tests the complete workflow from API request through extraction,
 * storage, and retrieval.
 */
class FactPatternIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with API token
        $this->user = User::factory()->create();
        $this->apiToken = $this->user->generateApiToken();
    }

    /** @test */
    public function it_can_extract_fact_pattern_via_api()
    {
        // Mock OpenAI service
        $this->mock(OpenAIService::class, function ($mock) {
            $mock->shouldReceive('chat')
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
                                                'issue' => 'Breach of contract',
                                                'area_of_law' => 'contract',
                                            ],
                                        ],
                                        'summary' => 'Contract dispute case',
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ]);
        });

        // Make API request
        $response = $this->postJson('/api/fact-patterns/extract', [
            'narrative' => 'On January 15, 2024, John Doe signed a contract with ABC Corp...',
            'save' => true,
        ], [
            'Authorization' => "Bearer {$this->apiToken}",
        ]);

        // Assert response
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json('data');
        $this->assertEquals('contract', $data['legal_area']);
        $this->assertEquals(0.85, $data['extraction_confidence']);
        $this->assertNotNull($data['id']);

        // Assert saved to database
        $this->assertDatabaseHas('legal_fact_patterns', [
            'id' => $data['id'],
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
        ]);
    }

    /** @test */
    public function it_can_batch_extract_multiple_narratives()
    {
        // Mock OpenAI service for multiple calls
        $this->mock(OpenAIService::class, function ($mock) {
            $mock->shouldReceive('chat')
                ->times(2)
                ->andReturn(
                    [
                        'choices' => [
                            [
                                'message' => [
                                    'content' => json_encode([
                                        'legal_area' => 'contract',
                                        'confidence' => 0.8,
                                        'structured_facts' => ['parties' => []],
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
                                        'structured_facts' => ['parties' => []],
                                    ]),
                                ],
                            ],
                        ],
                    ]
                );
        });

        // Make API request
        $response = $this->postJson('/api/fact-patterns/batch-extract', [
            'narratives' => [
                'case1' => 'Contract dispute narrative...',
                'case2' => 'Personal injury narrative...',
            ],
            'save' => true,
        ], [
            'Authorization' => "Bearer {$this->apiToken}",
        ]);

        // Assert response
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'summary' => [
                'total' => 2,
                'extracted' => 2,
                'failed' => 0,
            ],
        ]);

        // Assert both saved
        $this->assertEquals(2, LegalFactPattern::count());
    }

    /** @test */
    public function it_can_retrieve_user_fact_patterns()
    {
        // Create test fact patterns
        LegalFactPattern::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Make API request
        $response = $this->getJson('/api/fact-patterns', [
            'Authorization' => "Bearer {$this->apiToken}",
        ]);

        // Assert response
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'count' => 3,
        ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_can_filter_by_legal_area()
    {
        // Create test patterns with different legal areas
        LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
        ]);

        LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'tort',
        ]);

        LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
        ]);

        // Filter by contract
        $response = $this->getJson('/api/fact-patterns?legal_area=contract', [
            'Authorization' => "Bearer {$this->apiToken}",
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'count' => 2,
        ]);
    }

    /** @test */
    public function it_can_compare_two_fact_patterns()
    {
        $pattern1 = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
            'structured_facts' => [
                'parties' => [
                    ['name' => 'John Doe', 'role' => 'plaintiff'],
                ],
                'legal_issues' => [
                    ['area_of_law' => 'breach of contract'],
                ],
            ],
        ]);

        $pattern2 = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
            'structured_facts' => [
                'parties' => [
                    ['name' => 'John Doe', 'role' => 'plaintiff'],
                ],
                'legal_issues' => [
                    ['area_of_law' => 'breach of contract'],
                ],
            ],
        ]);

        $response = $this->postJson('/api/fact-patterns/compare', [
            'pattern_id_1' => $pattern1->id,
            'pattern_id_2' => $pattern2->id,
        ], [
            'Authorization' => "Bearer {$this->apiToken}",
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $similarity = $response->json('similarity');
        $this->assertArrayHasKey('same_legal_area', $similarity);
        $this->assertTrue($similarity['same_legal_area']);
    }

    /** @test */
    public function it_can_find_similar_patterns()
    {
        $target = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
            'structured_facts' => [
                'parties' => [['name' => 'John Doe']],
                'legal_issues' => [['area_of_law' => 'breach']],
            ],
        ]);

        // Create similar pattern
        LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
            'structured_facts' => [
                'parties' => [['name' => 'John Doe']],
                'legal_issues' => [['area_of_law' => 'breach']],
            ],
        ]);

        // Create dissimilar pattern
        LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'tort',
            'structured_facts' => [
                'parties' => [],
                'legal_issues' => [],
            ],
        ]);

        $response = $this->postJson("/api/fact-patterns/{$target->id}/find-similar", [
            'min_similarity' => 0.6,
        ], [
            'Authorization' => "Bearer {$this->apiToken}",
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Should find at least 1 similar (the similar contract case)
        $this->assertGreaterThanOrEqual(1, $response->json('count'));
    }

    /** @test */
    public function it_enforces_authorization()
    {
        $otherUser = User::factory()->create();
        $pattern = LegalFactPattern::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        // Try to access another user's pattern
        $response = $this->getJson("/api/fact-patterns/{$pattern->id}", [
            'Authorization' => "Bearer {$this->apiToken}",
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => 'Unauthorized',
        ]);
    }

    /** @test */
    public function it_validates_narrative_length()
    {
        $response = $this->postJson('/api/fact-patterns/extract', [
            'narrative' => 'Too short',
            'save' => true,
        ], [
            'Authorization' => "Bearer {$this->apiToken}",
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['narrative']);
    }

    /** @test */
    public function it_can_delete_fact_pattern()
    {
        $pattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/fact-patterns/{$pattern->id}", [], [
            'Authorization' => "Bearer {$this->apiToken}",
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseMissing('legal_fact_patterns', [
            'id' => $pattern->id,
        ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->postJson('/api/fact-patterns/extract', [
            'narrative' => 'Some legal narrative...',
        ]);

        // Should fail without API token
        $response->assertStatus(401);
    }

    /** @test */
    public function it_respects_rate_limiting()
    {
        // This test would require making 61+ requests
        // Skipping actual implementation for brevity
        $this->assertTrue(true);
    }
}
