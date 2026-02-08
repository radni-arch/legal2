<?php

namespace Tests\Feature\Mcp;

use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Feature tests for MCP OpenAI Bridge Controller
 * Tests OpenAI-compatible endpoints for exposing MCP tools
 */
class McpOpenAIControllerTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function info_endpoint_returns_bridge_info(): void
    {
        $response = $this->get('/api/mcp-openai/info');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'service',
            'description',
            'version',
            'endpoints',
        ]);
    }

    /** @test */
    public function info_endpoint_is_public(): void
    {
        config(['mcp.api_token' => 'test-token']);

        // Should work without authentication
        $response = $this->get('/api/mcp-openai/info');

        $response->assertStatus(200);
    }

    /** @test */
    public function tools_endpoint_requires_authentication(): void
    {
        config(['mcp.api_token' => 'test-token']);

        $response = $this->get('/api/mcp-openai/tools');

        $response->assertStatus(401);
    }

    /** @test */
    public function tools_endpoint_lists_all_tools(): void
    {
        config(['mcp.api_token' => 'test-token']);

        $response = $this->getJson('/api/mcp-openai/tools', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'object',
            'data',
        ]);

        $data = $response->json();
        $this->assertEquals('list', $data['object']);
        $this->assertCount(5, $data['data']);

        foreach ($data['data'] as $tool) {
            $this->assertArrayHasKey('id', $tool);
            $this->assertArrayHasKey('type', $tool);
            $this->assertEquals('function', $tool['type']);
            $this->assertArrayHasKey('function', $tool);
        }
    }

    /** @test */
    public function tools_execute_endpoint_requires_authentication(): void
    {
        config(['mcp.api_token' => 'test-token']);

        $response = $this->postJson('/api/mcp-openai/tools/execute', [
            'tool' => 'law_article_by_id',
            'arguments' => ['id' => 'test-id'],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function tools_execute_endpoint_executes_tool(): void
    {
        config(['mcp.api_token' => 'test-token']);

        // Execute a tool that will return an error (article not found)
        $response = $this->postJson('/api/mcp-openai/tools/execute', [
            'tool' => 'law_article_by_id',
            'arguments' => ['id' => 'nonexistent-id'],
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'content',
            'error',
        ]);

        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertNotNull($data['error']);
    }

    /** @test */
    public function tools_execute_validates_tool_name(): void
    {
        config(['mcp.api_token' => 'test-token']);

        $response = $this->postJson('/api/mcp-openai/tools/execute', [
            'tool' => 'nonexistent_tool',
            'arguments' => [],
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Unknown tool', $data['error']);
    }

    /** @test */
    public function chat_completions_endpoint_requires_authentication(): void
    {
        config(['mcp.api_token' => 'test-token']);

        $response = $this->postJson('/api/mcp-openai/chat/completions', [
            'messages' => [
                ['role' => 'user', 'content' => 'Test message'],
            ],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function webhook_endpoint_requires_authentication(): void
    {
        config(['mcp.api_token' => 'test-token']);

        $response = $this->postJson('/api/mcp-openai/webhook', [
            'type' => 'function_call',
            'data' => [],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function endpoints_respect_rate_limiting(): void
    {
        config(['mcp.api_token' => 'test-token']);

        // Test tools endpoint rate limiting
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/mcp-openai/tools', [
                'Authorization' => 'Bearer test-token',
            ]);

            if ($i < 60) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429);
                break;
            }
        }
    }

    /** @test */
    public function tools_have_correct_openai_format(): void
    {
        config(['mcp.api_token' => 'test-token']);

        $response = $this->getJson('/api/mcp-openai/tools', [
            'Authorization' => 'Bearer test-token',
        ]);

        $data = $response->json();

        foreach ($data['data'] as $tool) {
            $this->assertEquals('function', $tool['type']);
            $this->assertArrayHasKey('name', $tool['function']);
            $this->assertArrayHasKey('description', $tool['function']);
            $this->assertArrayHasKey('parameters', $tool['function']);
            $this->assertEquals('object', $tool['function']['parameters']['type']);
            $this->assertArrayHasKey('properties', $tool['function']['parameters']);
        }
    }

    /** @test */
    public function tool_names_are_in_snake_case(): void
    {
        config(['mcp.api_token' => 'test-token']);

        $response = $this->getJson('/api/mcp-openai/tools', [
            'Authorization' => 'Bearer test-token',
        ]);

        $data = $response->json();
        $expectedNames = [
            'odluke_search',
            'odluke_meta',
            'odluke_download',
            'law_articles_search',
            'law_article_by_id',
        ];

        $actualNames = array_map(fn ($t) => $t['function']['name'], $data['data']);

        foreach ($expectedNames as $name) {
            $this->assertContains($name, $actualNames);
        }
    }

    /** @test */
    public function info_endpoint_includes_correct_service_name(): void
    {
        $response = $this->get('/api/mcp-openai/info');

        $data = $response->json();
        $this->assertEquals('MCP-OpenAI Bridge', $data['service']);
    }

    /** @test */
    public function info_endpoint_lists_available_endpoints(): void
    {
        $response = $this->get('/api/mcp-openai/info');

        $data = $response->json();
        $this->assertArrayHasKey('endpoints', $data);
        $this->assertIsArray($data['endpoints']);
        $this->assertNotEmpty($data['endpoints']);
    }
}
