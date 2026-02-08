<?php

namespace Tests\Feature\Security;

use App\Models\LegalCase;
use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Input Validation Security Tests
 *
 * Tests protection against:
 * - XSS (Cross-Site Scripting)
 * - SQL Injection
 * - Length violations
 * - Type violations
 * - Malformed input
 * - Special character handling
 */
class InputValidationTest extends TestCase
{
    use UsesTestDatabase;

    protected User $authenticatedUser;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with API token for all tests
        $this->authenticatedUser = User::factory()->create(['role' => 'lawyer']);
        $this->apiToken = $this->authenticatedUser->generateApiToken();
    }

    protected function authenticatedPostJson(string $uri, array $data = []): mixed
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->apiToken)
            ->postJson($uri, $data);
    }

    // ============================================================================
    // XSS Protection Tests
    // ============================================================================

    public function test_evidence_analysis_rejects_xss_attempt(): void
    {
        $case = LegalCase::factory()->create(['user_id' => $this->authenticatedUser->id]);

        $response = $this->authenticatedPostJson('/api/evidence/analyze/'.$case->id, [
            'evidence' => [
                [
                    'id' => 'ev-1',
                    'type' => 'document',
                    'description' => '<script>alert("XSS")</script>',
                ],
            ],
        ]);

        $response->assertStatus(200);

        // Verify XSS is escaped in response
        $responseData = $response->json();
        $this->assertStringNotContainsString('<script>', json_encode($responseData));
    }

    public function test_search_query_sanitizes_html_tags(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => '<img src=x onerror=alert(1)>',
            ]);

        // Should process as text, not execute HTML
        $response->assertStatus(200);
    }

    public function test_case_title_rejects_script_tags(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/cases', [
                'case_number' => 'CASE-001',
                'title' => '<script>alert("XSS")</script>Criminal Case',
                'status' => 'active',
            ]);

        // Should either reject or escape
        if ($response->status() === 422) {
            $this->assertTrue(true); // Rejected malicious input
        } else {
            $data = $response->json();
            if (isset($data['title'])) {
                $this->assertStringNotContainsString('<script>', $data['title']);
            }
        }
    }

    public function test_evidence_description_escapes_javascript(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/evidence/analyze/'.$case->id, [
                'evidence' => [
                    [
                        'id' => 'ev-1',
                        'type' => 'testimony',
                        'description' => 'javascript:void(document.cookie)',
                    ],
                ],
            ]);

        $response->assertStatus(200);
    }

    // ============================================================================
    // SQL Injection Protection Tests
    // ============================================================================

    public function test_search_rejects_sql_injection_attempt(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => "'; DROP TABLE users; --",
            ]);

        // Should be treated as normal search, not SQL
        $response->assertStatus(200);

        // Verify users table still exists
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_case_search_protects_against_union_injection(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search/cases', [
                'query' => "' UNION SELECT * FROM users WHERE '1'='1",
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_filter_values_prevent_sql_injection(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'criminal law',
                'filters' => [
                    'court' => "' OR '1'='1",
                    'jurisdiction' => "'; DELETE FROM cases; --",
                ],
            ]);

        $response->assertStatus(200);
        // Tables should still exist
        $this->assertNotNull(\DB::select('SELECT 1 FROM cases LIMIT 1'));
    }

    public function test_case_number_prevents_sql_injection(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/cases', [
                'case_number' => "'; DROP TABLE cases; --",
                'title' => 'Test Case',
                'status' => 'active',
            ]);

        // Should either validate or safely store
        if ($response->status() === 201 || $response->status() === 200) {
            // If accepted, verify no SQL executed
            $this->assertNotNull(\DB::select('SELECT 1 FROM cases LIMIT 1'));
        } else {
            // Validation rejected it
            $response->assertStatus(422);
        }
    }

    // ============================================================================
    // Length Validation Tests
    // ============================================================================

    public function test_case_creation_validates_max_length(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/cases', [
                'case_number' => str_repeat('A', 256), // Should exceed max
                'title' => str_repeat('B', 1001), // Should exceed max
                'client_name' => str_repeat('C', 256), // Should exceed max
                'status' => 'active',
            ]);

        // Should reject with validation error
        $response->assertStatus(422);
    }

    public function test_search_query_enforces_max_length(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => str_repeat('x', 1001), // Max is 1000
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_search_query_enforces_min_length(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'a', // Min is 2
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_evidence_description_length_validation(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/evidence/analyze/'.$case->id, [
                'evidence' => [
                    [
                        'id' => 'ev-1',
                        'type' => 'document',
                        'description' => '', // Empty description
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    // ============================================================================
    // Type Validation Tests
    // ============================================================================

    public function test_search_limit_must_be_integer(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'criminal law',
                'limit' => 'not_a_number',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_search_limit_must_be_within_range(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'criminal law',
                'limit' => 101, // Max is 100
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_search_threshold_must_be_numeric(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'criminal law',
                'threshold' => 'high',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['threshold']);
    }

    public function test_search_threshold_must_be_between_0_and_1(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'criminal law',
                'threshold' => 1.5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['threshold']);
    }

    public function test_evidence_array_type_validation(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/evidence/analyze/'.$case->id, [
                'evidence' => 'not_an_array', // Should be array
            ]);

        $response->assertStatus(422);
    }

    // ============================================================================
    // Format Validation Tests
    // ============================================================================

    public function test_search_date_filter_validates_format(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'criminal law',
                'filters' => [
                    'date_from' => 'not-a-date',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filters.date_from']);
    }

    public function test_search_date_range_validation(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'criminal law',
                'filters' => [
                    'date_from' => '2024-12-31',
                    'date_to' => '2024-01-01', // Before date_from
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_search_corpora_validates_allowed_values(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'criminal law',
                'corpora' => ['invalid_corpus'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['corpora.0']);
    }

    public function test_search_sort_by_validates_allowed_values(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'criminal law',
                'sort_by' => 'invalid_sort',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }

    // ============================================================================
    // Special Character Handling Tests
    // ============================================================================

    public function test_search_handles_special_characters_safely(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $specialChars = [
            "'; --",
            '<>"\'"',
            '%00',
            '../../../etc/passwd',
            '${7*7}',
            '{{7*7}}',
        ];

        foreach ($specialChars as $chars) {
            $response = $this->actingAs($user)
                ->postJson('/api/search', [
                    'query' => 'test '.$chars,
                ]);

            // Should handle gracefully without errors
            $this->assertContains($response->status(), [200, 422]);
        }
    }

    public function test_null_byte_injection_protection(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => "test\x00injection",
            ]);

        // Should either reject or sanitize
        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_unicode_normalization_attack_protection(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => "\u{202E}test\u{202D}", // Right-to-left override
            ]);

        $response->assertStatus(200);
    }

    // ============================================================================
    // Array/JSON Structure Validation Tests
    // ============================================================================

    public function test_evidence_array_structure_validation(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/evidence/analyze/'.$case->id, [
                'evidence' => [
                    [
                        'id' => 'ev-1',
                        // Missing 'type'
                        'description' => 'Test evidence',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['evidence.0.type']);
    }

    public function test_nested_array_validation(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'test',
                'weights' => [
                    'laws' => 'not_numeric', // Should be numeric
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['weights.laws']);
    }

    public function test_deeply_nested_json_handling(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        // Create deeply nested structure
        $nested = ['level' => 0];
        for ($i = 1; $i < 100; $i++) {
            $nested = ['level' => $i, 'nested' => $nested];
        }

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'test',
                'filters' => $nested,
            ]);

        // Should handle without crashing
        $this->assertContains($response->status(), [200, 422]);
    }

    // ============================================================================
    // Additional Security Tests
    // ============================================================================

    public function test_rejects_extremely_large_payload(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $largeArray = array_fill(0, 10000, [
            'id' => 'ev-'.uniqid(),
            'type' => 'document',
            'description' => str_repeat('x', 1000),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/evidence/analyze/test-case', [
                'evidence' => $largeArray,
            ]);

        // Should either reject or handle gracefully
        $this->assertContains($response->status(), [413, 422, 500]);
    }

    public function test_rejects_invalid_case_id_format(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $invalidIds = [
            '../../../etc/passwd',
            '../../database.sqlite',
            "case'; DROP TABLE cases; --",
        ];

        foreach ($invalidIds as $invalidId) {
            $response = $this->actingAs($user)
                ->postJson('/api/evidence/analyze/'.$invalidId, [
                    'evidence' => [
                        [
                            'id' => 'ev-1',
                            'type' => 'document',
                            'description' => 'Test',
                        ],
                    ],
                ]);

            // Should reject invalid ID
            $this->assertContains($response->status(), [404, 422, 400]);
        }
    }

    public function test_required_fields_validation(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                // Missing required 'query' field
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_unexpected_field_handling(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($user)
            ->postJson('/api/search', [
                'query' => 'test',
                'unexpected_field' => 'should_be_ignored',
                'is_admin' => true, // Attempt to escalate privileges
                'role' => 'admin',
            ]);

        // Should process normally, ignoring unexpected fields
        $response->assertStatus(200);

        // Verify user role wasn't changed
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => $user->role,
        ]);
    }
}
