<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Test API Authentication for all endpoints requiring api.token middleware
 *
 * This test suite verifies that all 19 endpoints in the reasoning, analytics,
 * strategy, and misconduct modules return 401 Unauthorized when called without
 * a valid API token.
 */
class AuthenticationTest extends TestCase
{
    /**
     * Test that all reasoning endpoints require authentication
     *
     * @return void
     */
    public function test_reasoning_endpoints_require_authentication()
    {
        // Reasoning endpoints (5 total)
        $endpoints = [
            ['POST', '/api/reasoning/analyze-conflict'],
            ['POST', '/api/reasoning/resolve-conflict'],
            ['POST', '/api/reasoning/authority-score'],
            ['POST', '/api/reasoning/parse-logic'],
            ['POST', '/api/reasoning/apply-deductive'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    /**
     * Test that all analytics endpoints require authentication
     *
     * @return void
     */
    public function test_analytics_endpoints_require_authentication()
    {
        // Analytics endpoints (5 total)
        $endpoints = [
            ['POST', '/api/analytics/predict-outcome/1'],
            ['POST', '/api/analytics/estimate-duration/1'],
            ['POST', '/api/analytics/comprehensive/1'],
            ['POST', '/api/analytics/analyze-impact/1'],
            ['POST', '/api/analytics/batch-predict'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    /**
     * Test that all strategy endpoints require authentication
     *
     * @return void
     */
    public function test_strategy_endpoints_require_authentication()
    {
        // Strategy endpoints (5 total)
        $endpoints = [
            ['POST', '/api/strategy/build/1'],
            ['POST', '/api/strategy/comprehensive/1'],
            ['POST', '/api/strategy/generate-arguments/1'],
            ['POST', '/api/strategy/assess-risks/1'],
            ['POST', '/api/strategy/action-plan/1'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    /**
     * Test that all misconduct endpoints require authentication
     *
     * @return void
     */
    public function test_misconduct_endpoints_require_authentication()
    {
        // Misconduct endpoints (4 total)
        $endpoints = [
            ['POST', '/api/misconduct/analyze/1'],
            ['POST', '/api/misconduct/dismissal-motion/1'],
            ['POST', '/api/misconduct/complaint/1'],
            ['POST', '/api/misconduct/appeal/1'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    /**
     * Test all 19 endpoints in a single comprehensive test
     *
     * @return void
     */
    public function test_all_19_endpoints_require_authentication()
    {
        // All 19 endpoints that should require authentication
        $endpoints = [
            // Reasoning (5)
            ['POST', '/api/reasoning/analyze-conflict'],
            ['POST', '/api/reasoning/resolve-conflict'],
            ['POST', '/api/reasoning/authority-score'],
            ['POST', '/api/reasoning/parse-logic'],
            ['POST', '/api/reasoning/apply-deductive'],

            // Analytics (5)
            ['POST', '/api/analytics/predict-outcome/1'],
            ['POST', '/api/analytics/estimate-duration/1'],
            ['POST', '/api/analytics/comprehensive/1'],
            ['POST', '/api/analytics/analyze-impact/1'],
            ['POST', '/api/analytics/batch-predict'],

            // Strategy (5)
            ['POST', '/api/strategy/build/1'],
            ['POST', '/api/strategy/comprehensive/1'],
            ['POST', '/api/strategy/generate-arguments/1'],
            ['POST', '/api/strategy/assess-risks/1'],
            ['POST', '/api/strategy/action-plan/1'],

            // Misconduct (4)
            ['POST', '/api/misconduct/analyze/1'],
            ['POST', '/api/misconduct/dismissal-motion/1'],
            ['POST', '/api/misconduct/complaint/1'],
            ['POST', '/api/misconduct/appeal/1'],
        ];

        $this->assertCount(19, $endpoints, 'Expected exactly 19 endpoints');

        $failedEndpoints = [];
        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            if ($response->status() !== 401) {
                $failedEndpoints[] = "$method $uri returned {$response->status()} instead of 401";
            }
        }

        $this->assertEmpty(
            $failedEndpoints,
            "The following endpoints did not return 401:\n".implode("\n", $failedEndpoints)
        );
    }

    /**
     * Test that authenticated requests can access endpoints
     * (This test requires a valid API token to be configured)
     *
     * @return void
     */
    public function test_authenticated_requests_can_access_endpoints()
    {
        // Skip if no API token is configured for testing
        if (! env('TEST_API_TOKEN')) {
            $this->markTestSkipped('TEST_API_TOKEN not configured');
        }

        $token = env('TEST_API_TOKEN');

        // Test a few representative endpoints with valid token
        $response = $this->withHeaders([
            'X-API-Token' => $token,
        ])->json('POST', '/api/reasoning/analyze-conflict');

        // Should NOT return 401 (may return 422 for validation or 500 for missing data, but not 401)
        $this->assertNotEquals(401, $response->status(),
            'Authenticated request should not return 401');
    }
}
