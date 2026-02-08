<?php

namespace Tests\Unit\Tools;

use App\Services\Mcp\InternalMcpClient;
use App\Tools\OdlukeSearchTool;
use Tests\TestCase;

/**
 * Unit tests for OdlukeSearchTool Vizra ADK wrapper
 */
class OdlukeSearchToolTest extends TestCase
{
    /** @test */
    public function has_correct_name(): void
    {
        $tool = new OdlukeSearchTool;

        $reflection = new \ReflectionClass($tool);
        $property = $reflection->getProperty('name');
        $property->setAccessible(true);

        $this->assertEquals('odluke_search', $property->getValue($tool));
    }

    /** @test */
    public function has_description_from_schema(): void
    {
        $tool = new OdlukeSearchTool;

        $reflection = new \ReflectionClass($tool);
        $property = $reflection->getProperty('description');
        $property->setAccessible(true);

        $description = $property->getValue($tool);
        $this->assertNotEmpty($description);
        $this->assertIsString($description);
    }

    /** @test */
    public function get_input_schema_returns_valid_schema(): void
    {
        $tool = new OdlukeSearchTool;
        $schema = $tool->getInputSchema();

        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('q', $schema['properties']);
        $this->assertArrayHasKey('params', $schema['properties']);
        $this->assertArrayHasKey('limit', $schema['properties']);
        $this->assertArrayHasKey('page', $schema['properties']);
    }

    /** @test */
    public function execute_calls_internal_mcp_client(): void
    {
        $mockClient = $this->createMock(InternalMcpClient::class);
        $mockClient->expects($this->once())
            ->method('callTool')
            ->with('odluke-search', ['q' => 'test', 'limit' => 50])
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Search results']],
                'isError' => false,
            ]);

        $this->app->instance(InternalMcpClient::class, $mockClient);

        $tool = new OdlukeSearchTool;
        $result = $tool->execute(['q' => 'test', 'limit' => 50]);

        $this->assertIsString($result);
        $this->assertEquals('Search results', $result);
    }

    /** @test */
    public function execute_extracts_text_from_mcp_content_format(): void
    {
        $mockClient = $this->createMock(InternalMcpClient::class);
        $mockClient->method('callTool')
            ->willReturn([
                'content' => [
                    ['type' => 'text', 'text' => 'Part 1 '],
                    ['type' => 'text', 'text' => 'Part 2'],
                ],
                'isError' => false,
            ]);

        $this->app->instance(InternalMcpClient::class, $mockClient);

        $tool = new OdlukeSearchTool;
        $result = $tool->execute([]);

        $this->assertEquals('Part 1 Part 2', $result);
    }

    /** @test */
    public function execute_returns_json_when_no_text_in_content(): void
    {
        $mockClient = $this->createMock(InternalMcpClient::class);
        $mockClient->method('callTool')
            ->willReturn([
                'content' => [],
                'isError' => false,
                'someData' => 'value',
            ]);

        $this->app->instance(InternalMcpClient::class, $mockClient);

        $tool = new OdlukeSearchTool;
        $result = $tool->execute([]);

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertArrayHasKey('someData', $decoded);
        $this->assertEquals('value', $decoded['someData']);
    }

    /** @test */
    public function schema_uses_centralized_tool_schemas(): void
    {
        $tool = new OdlukeSearchTool;
        $schema = $tool->getInputSchema();

        // Verify that schema has expected structure from ToolSchemas
        $this->assertArrayHasKey('limit', $schema['properties']);
        $this->assertEquals(1, $schema['properties']['limit']['minimum']);
        $this->assertEquals(500, $schema['properties']['limit']['maximum']);
    }
}
