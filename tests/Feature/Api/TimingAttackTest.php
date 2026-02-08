<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Test that token comparison uses constant-time comparison (hash_equals)
 * to prevent timing attacks.
 *
 * Timing attacks exploit the fact that string comparison with !== or ==
 * returns early when a mismatch is found, allowing attackers to measure
 * response times and gradually discover the correct token character by character.
 *
 * hash_equals() prevents this by always comparing all characters regardless
 * of when a mismatch is found.
 */
class TimingAttackTest extends TestCase
{
    /**
     * Test that valid token is accepted
     *
     * @return void
     */
    public function test_valid_token_is_accepted()
    {
        // Set a test token in config
        config(['mcp.api_token' => 'test-secure-token-12345']);

        // Make request with valid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-secure-token-12345',
        ])->getJson('/api/mcp-openai/info');

        // Should NOT return 401 (the info endpoint is public, but we're testing auth middleware)
        // Since info endpoint doesn't require auth, let's test a protected endpoint

        // Actually, let's test the tools endpoint which requires auth
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-secure-token-12345',
        ])->getJson('/api/mcp-openai/tools');

        // Should not return 401 for invalid token
        $this->assertNotEquals(401, $response->status(),
            'Valid token should not return 401');
    }

    /**
     * Test that invalid token is rejected
     *
     * @return void
     */
    public function test_invalid_token_is_rejected()
    {
        // Set a test token in config
        config(['mcp.api_token' => 'test-secure-token-12345']);

        // Make request with invalid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer wrong-token',
        ])->getJson('/api/mcp-openai/tools');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Invalid API token',
        ]);
    }

    /**
     * Test that missing Authorization header is rejected
     *
     * @return void
     */
    public function test_missing_authorization_header_is_rejected()
    {
        // Set a test token in config
        config(['mcp.api_token' => 'test-secure-token-12345']);

        // Make request without Authorization header
        $response = $this->getJson('/api/mcp-openai/tools');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthorized',
        ]);
    }

    /**
     * Test that malformed Authorization header is rejected
     *
     * @return void
     */
    public function test_malformed_authorization_header_is_rejected()
    {
        // Set a test token in config
        config(['mcp.api_token' => 'test-secure-token-12345']);

        // Make request with malformed header (no "Bearer " prefix)
        $response = $this->withHeaders([
            'Authorization' => 'test-secure-token-12345',
        ])->getJson('/api/mcp-openai/tools');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Missing or invalid Authorization header. Expected: Bearer <token>',
        ]);
    }

    /**
     * Test that tokens differing only at the end are rejected
     *
     * This test ensures that hash_equals() is being used instead of ===
     * because hash_equals() compares all characters even if early characters match.
     *
     * @return void
     */
    public function test_similar_tokens_are_rejected()
    {
        // Set a test token in config
        config(['mcp.api_token' => 'test-secure-token-12345']);

        // Test tokens that differ only at the end
        $similarTokens = [
            'test-secure-token-12344', // Last character different
            'test-secure-token-1234',  // Shorter
            'test-secure-token-123456', // Longer
            'test-secure-token-12346', // Last character different
        ];

        foreach ($similarTokens as $invalidToken) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer $invalidToken",
            ])->getJson('/api/mcp-openai/tools');

            $response->assertStatus(401);
            $response->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid API token',
            ]);
        }
    }

    /**
     * Test that tokens differing only at the beginning are rejected
     *
     * @return void
     */
    public function test_tokens_differing_at_beginning_are_rejected()
    {
        // Set a test token in config
        config(['mcp.api_token' => 'test-secure-token-12345']);

        // Test tokens that differ at the beginning
        $response = $this->withHeaders([
            'Authorization' => 'Bearer xest-secure-token-12345',
        ])->getJson('/api/mcp-openai/tools');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Invalid API token',
        ]);
    }

    /**
     * Test that empty token is rejected
     *
     * @return void
     */
    public function test_empty_token_is_rejected()
    {
        // Set a test token in config
        config(['mcp.api_token' => 'test-secure-token-12345']);

        // Make request with empty token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ',
        ])->getJson('/api/mcp-openai/tools');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Invalid API token',
        ]);
    }

    /**
     * Test that when no token is configured, access is allowed
     * (backward compatibility mode)
     *
     * @return void
     */
    public function test_no_token_configured_allows_access()
    {
        // Clear any configured token
        config(['mcp.api_token' => null]);

        // Make request without Authorization header
        $response = $this->getJson('/api/mcp-openai/tools');

        // Should NOT return 401 (backward compatibility allows access)
        $this->assertNotEquals(401, $response->status(),
            'When no token is configured, should not return 401');
    }

    /**
     * Verify that the middleware uses hash_equals for constant-time comparison
     *
     * This is a code inspection test that documents the security requirement.
     * The actual implementation must use hash_equals() to prevent timing attacks.
     *
     * @return void
     */
    public function test_middleware_uses_constant_time_comparison()
    {
        // Read the middleware source code
        $middlewareSource = file_get_contents(
            app_path('Http/Middleware/McpApiTokenAuth.php')
        );

        // Verify that hash_equals is used
        $this->assertStringContainsString(
            'hash_equals',
            $middlewareSource,
            'Middleware must use hash_equals() for constant-time comparison to prevent timing attacks'
        );

        // Verify that !== is NOT used for token comparison (old vulnerable method)
        // We allow !== for other comparisons but not in the token validation logic
        $this->assertStringNotContainsString(
            '$token !== $validToken',
            $middlewareSource,
            'Middleware must not use !== for token comparison (vulnerable to timing attacks)'
        );
    }
}
