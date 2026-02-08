<?php

namespace Tests\Feature\Mcp;

use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Feature tests for MCP HTTP Controller
 * Tests the JSON-RPC 2.0 MCP protocol endpoints
 */
class McpHttpControllerTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function info_endpoint_returns_server_info(): void
    {
        $response = $this->get('/mcp/info');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'server',
            'protocol_version',
            'tools',
        ]);
    }

    /** @test */
    public function info_endpoint_lists_all_tools(): void
    {
        $response = $this->get('/mcp/info');

        $data = $response->json();
        $this->assertArrayHasKey('tools', $data);
        $this->assertCount(8, $data['tools']); // Updated: 3 Odluke + 2 old law + 3 new tools

        $toolNames = array_column($data['tools'], 'name');
        // Old tools
        $this->assertContains('odluke-search', $toolNames);
        $this->assertContains('odluke-meta', $toolNames);
        $this->assertContains('odluke-download', $toolNames);
        $this->assertContains('law-articles-search', $toolNames);
        $this->assertContains('law-article-by-id', $toolNames);
        // New tools (Milestone F)
        $this->assertContains('law.search', $toolNames);
        $this->assertContains('law.get_article', $toolNames);
        $this->assertContains('decision.search', $toolNames);
        $this->assertContains('decision.get', $toolNames);
        $this->assertContains('case.search', $toolNames);
    }

    /** @test */
    public function message_endpoint_requires_authentication(): void
    {
        config(['mcp.api_token' => 'test-token']);

        $response = $this->postJson('/mcp/message', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthorized',
        ]);
    }

    /** @test */
    public function message_endpoint_allows_request_with_valid_token(): void
    {
        config(['mcp.api_token' => 'test-token']);

        $response = $this->postJson('/mcp/message', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function message_endpoint_handles_tools_list_method(): void
    {
        config(['mcp.api_token' => null]); // Disable auth for test

        $response = $this->postJson('/mcp/message', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'jsonrpc',
            'id',
            'result' => [
                'tools',
            ],
        ]);

        $data = $response->json();
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(1, $data['id']);
        // McpHttpController exposes 5 OLD tools via JSON-RPC MCP
        $this->assertCount(5, $data['result']['tools']);
    }

    /** @test */
    public function message_endpoint_handles_initialize_method(): void
    {
        config(['mcp.api_token' => null]);

        $response = $this->postJson('/mcp/message', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'id' => 1,
            'params' => [
                'protocolVersion' => '2024-11-05',
                'clientInfo' => [
                    'name' => 'test-client',
                    'version' => '1.0.0',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'jsonrpc',
            'id',
            'result' => [
                'protocolVersion',
                'serverInfo',
                'capabilities',
            ],
        ]);
    }

    /** @test */
    public function message_endpoint_returns_error_for_unknown_method(): void
    {
        config(['mcp.api_token' => null]);

        $response = $this->postJson('/mcp/message', [
            'jsonrpc' => '2.0',
            'method' => 'unknown/method',
            'id' => 1,
        ]);

        $response->assertStatus(200); // JSON-RPC errors return 200
        $response->assertJsonStructure([
            'jsonrpc',
            'id',
            'error' => [
                'code',
                'message',
            ],
        ]);

        $data = $response->json();
        $this->assertEquals(-32601, $data['error']['code']);
        $this->assertStringContainsString('Method not found', $data['error']['message']);
    }

    /** @test */
    public function message_endpoint_handles_tools_call_method(): void
    {
        config(['mcp.api_token' => null]);

        // This will fail because we don't have real data, but we can test the structure
        $response = $this->postJson('/mcp/message', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
            'params' => [
                'name' => 'law-article-by-id',
                'arguments' => [
                    'id' => 'nonexistent-id',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'jsonrpc',
            'id',
            'result',
        ]);

        // Should return error because article doesn't exist
        $data = $response->json();
        $this->assertArrayHasKey('isError', $data['result']);
        $this->assertTrue($data['result']['isError']);
    }

    /** @test */
    public function message_endpoint_respects_rate_limiting(): void
    {
        config(['mcp.api_token' => null]);

        // Make 61 requests (more than the limit of 60 per minute)
        for ($i = 0; $i < 61; $i++) {
            $response = $this->postJson('/mcp/message', [
                'jsonrpc' => '2.0',
                'method' => 'tools/list',
                'id' => $i,
            ]);

            if ($i < 60) {
                $response->assertStatus(200);
            } else {
                // 61st request should be rate limited
                $response->assertStatus(429);
                break;
            }
        }
    }

    /** @test */
    public function info_endpoint_respects_rate_limiting(): void
    {
        // Make 61 requests
        for ($i = 0; $i < 61; $i++) {
            $response = $this->get('/mcp/info');

            if ($i < 60) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429);
                break;
            }
        }
    }
}
