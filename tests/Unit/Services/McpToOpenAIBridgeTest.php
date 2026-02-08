<?php

namespace Tests\Unit\Services;

use App\Mcp\OdlukeTools;
use App\Services\McpToOpenAIBridge;
use Tests\TestCase;

/**
 * Unit tests for McpToOpenAIBridge service
 */
class McpToOpenAIBridgeTest extends TestCase
{
    protected McpToOpenAIBridge $bridge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bridge = new McpToOpenAIBridge;
    }

    /** @test */
    public function get_openai_functions_returns_array_of_functions(): void
    {
        $functions = $this->bridge->getOpenAIFunctions();

        $this->assertIsArray($functions);
        $this->assertCount(5, $functions);
    }

    /** @test */
    public function get_openai_functions_have_correct_structure(): void
    {
        $functions = $this->bridge->getOpenAIFunctions();

        foreach ($functions as $function) {
            $this->assertEquals('function', $function['type']);
            $this->assertArrayHasKey('function', $function);
            $this->assertArrayHasKey('name', $function['function']);
            $this->assertArrayHasKey('description', $function['function']);
            $this->assertArrayHasKey('parameters', $function['function']);
            $this->assertEquals('object', $function['function']['parameters']['type']);
        }
    }

    /** @test */
    public function get_openai_functions_includes_all_tools(): void
    {
        $functions = $this->bridge->getOpenAIFunctions();
        $names = array_map(fn ($f) => $f['function']['name'], $functions);

        $this->assertContains('odluke_search', $names);
        $this->assertContains('odluke_meta', $names);
        $this->assertContains('odluke_download', $names);
        $this->assertContains('law_articles_search', $names);
        $this->assertContains('law_article_by_id', $names);
    }

    /** @test */
    public function execute_tool_returns_error_for_unknown_tool(): void
    {
        $result = $this->bridge->executeTool('unknown_tool', []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unknown tool', $result['error']);
    }

    /** @test */
    public function execute_tool_handles_odluke_search(): void
    {
        // Mock OdlukeTools
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('search')
            ->with('test query', null, 100, 1, null)
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Search results']],
                'isError' => false,
            ]);

        // Replace OdlukeTools instance in bridge
        $reflection = new \ReflectionClass($this->bridge);
        $property = $reflection->getProperty('odlukeTools');
        $property->setAccessible(true);
        $property->setValue($this->bridge, $mockTools);

        $result = $this->bridge->executeTool('odluke_search', [
            'q' => 'test query',
            'limit' => 100,
            'page' => 1,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Search results', $result['content']);
        $this->assertNull($result['error']);
    }

    /** @test */
    public function execute_tool_handles_odluke_meta(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('meta')
            ->with('test-id', null, null)
            ->willReturn([
                'content' => [['type' => 'text', 'text' => '{"id":"test-id","meta":{}}']],
                'isError' => false,
            ]);

        $reflection = new \ReflectionClass($this->bridge);
        $property = $reflection->getProperty('odlukeTools');
        $property->setAccessible(true);
        $property->setValue($this->bridge, $mockTools);

        $result = $this->bridge->executeTool('odluke_meta', ['id' => 'test-id']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('test-id', $result['content']);
    }

    /** @test */
    public function execute_tool_handles_odluke_download(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('download')
            ->with('test-id', 'pdf', false, null)
            ->willReturn([
                'content' => [['type' => 'text', 'text' => '{"saved":{}}']],
                'isError' => false,
            ]);

        $reflection = new \ReflectionClass($this->bridge);
        $property = $reflection->getProperty('odlukeTools');
        $property->setAccessible(true);
        $property->setValue($this->bridge, $mockTools);

        $result = $this->bridge->executeTool('odluke_download', [
            'id' => 'test-id',
            'format' => 'pdf',
            'save' => false,
        ]);

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function execute_tool_handles_law_articles_search(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('searchLawArticles')
            ->with('test', null, null, 10)
            ->willReturn([
                'content' => [['type' => 'text', 'text' => '{"count":0,"results":[]}']],
                'isError' => false,
            ]);

        $reflection = new \ReflectionClass($this->bridge);
        $property = $reflection->getProperty('odlukeTools');
        $property->setAccessible(true);
        $property->setValue($this->bridge, $mockTools);

        $result = $this->bridge->executeTool('law_articles_search', ['query' => 'test']);

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function execute_tool_handles_law_article_by_id(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('getLawArticleById')
            ->with('test-id')
            ->willReturn([
                'content' => [['type' => 'text', 'text' => '{"id":"test-id"}']],
                'isError' => false,
            ]);

        $reflection = new \ReflectionClass($this->bridge);
        $property = $reflection->getProperty('odlukeTools');
        $property->setAccessible(true);
        $property->setValue($this->bridge, $mockTools);

        $result = $this->bridge->executeTool('law_article_by_id', ['id' => 'test-id']);

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function execute_tool_handles_errors_gracefully(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->method('search')
            ->willThrowException(new \Exception('Test error'));

        $reflection = new \ReflectionClass($this->bridge);
        $property = $reflection->getProperty('odlukeTools');
        $property->setAccessible(true);
        $property->setValue($this->bridge, $mockTools);

        $result = $this->bridge->executeTool('odluke_search', ['q' => 'test']);

        $this->assertFalse($result['success']);
        $this->assertEquals('Test error', $result['error']);
        $this->assertNull($result['content']);
    }

    /** @test */
    public function execute_tool_returns_error_when_tool_reports_error(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->method('search')
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Error message']],
                'isError' => true,
            ]);

        $reflection = new \ReflectionClass($this->bridge);
        $property = $reflection->getProperty('odlukeTools');
        $property->setAccessible(true);
        $property->setValue($this->bridge, $mockTools);

        $result = $this->bridge->executeTool('odluke_search', ['q' => 'test']);

        $this->assertFalse($result['success']);
        $this->assertEquals('Error message', $result['error']);
    }

    /** @test */
    public function get_tool_definitions_returns_correct_format(): void
    {
        $definitions = $this->bridge->getToolDefinitions();

        $this->assertArrayHasKey('object', $definitions);
        $this->assertEquals('list', $definitions['object']);
        $this->assertArrayHasKey('data', $definitions);
        $this->assertCount(5, $definitions['data']);

        foreach ($definitions['data'] as $tool) {
            $this->assertArrayHasKey('id', $tool);
            $this->assertStringStartsWith('tool_', $tool['id']);
            $this->assertEquals('function', $tool['type']);
            $this->assertArrayHasKey('function', $tool);
        }
    }

    /** @test */
    public function process_chat_with_tools_returns_correct_structure(): void
    {
        $messages = [['role' => 'user', 'content' => 'Test message']];
        $tools = $this->bridge->getOpenAIFunctions();

        $result = $this->bridge->processChatWithTools($messages, $tools);

        $this->assertArrayHasKey('tools', $result);
        $this->assertArrayHasKey('tool_choice', $result);
        $this->assertEquals('auto', $result['tool_choice']);
    }
}
