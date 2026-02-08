<?php

namespace Tests\Unit\Services;

use App\Mcp\OdlukeTools;
use App\Services\Mcp\InternalMcpClient;
use Tests\TestCase;

/**
 * Unit tests for InternalMcpClient service
 */
class InternalMcpClientTest extends TestCase
{
    protected InternalMcpClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new InternalMcpClient;
    }

    /** @test */
    public function call_tool_handles_odluke_search(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('search')
            ->with('test', null, 100, 1, null)
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Results']],
                'isError' => false,
            ]);

        $reflection = new \ReflectionClass($this->client);
        $property = $reflection->getProperty('tools');
        $property->setAccessible(true);
        $property->setValue($this->client, $mockTools);

        $result = $this->client->callTool('odluke-search', [
            'q' => 'test',
            'limit' => 100,
            'page' => 1,
        ]);

        $this->assertFalse($result['isError']);
        $this->assertEquals('Results', $result['content'][0]['text']);
    }

    /** @test */
    public function call_tool_handles_odluke_meta(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('meta')
            ->with('id1', ['id2'], null)
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Meta data']],
                'isError' => false,
            ]);

        $reflection = new \ReflectionClass($this->client);
        $property = $reflection->getProperty('tools');
        $property->setAccessible(true);
        $property->setValue($this->client, $mockTools);

        $result = $this->client->callTool('odluke-meta', [
            'id' => 'id1',
            'ids' => ['id2'],
        ]);

        $this->assertFalse($result['isError']);
    }

    /** @test */
    public function call_tool_handles_odluke_download(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('download')
            ->with('test-id', 'pdf', true, null)
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Downloaded']],
                'isError' => false,
            ]);

        $reflection = new \ReflectionClass($this->client);
        $property = $reflection->getProperty('tools');
        $property->setAccessible(true);
        $property->setValue($this->client, $mockTools);

        $result = $this->client->callTool('odluke-download', [
            'id' => 'test-id',
            'format' => 'pdf',
            'save' => true,
        ]);

        $this->assertFalse($result['isError']);
    }

    /** @test */
    public function call_tool_handles_law_articles_search(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('searchLawArticles')
            ->with('test', 'NN 123/20', 'Title', 20)
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Laws']],
                'isError' => false,
            ]);

        $reflection = new \ReflectionClass($this->client);
        $property = $reflection->getProperty('tools');
        $property->setAccessible(true);
        $property->setValue($this->client, $mockTools);

        $result = $this->client->callTool('law-articles-search', [
            'query' => 'test',
            'law_number' => 'NN 123/20',
            'title' => 'Title',
            'limit' => 20,
        ]);

        $this->assertFalse($result['isError']);
    }

    /** @test */
    public function call_tool_handles_law_article_by_id(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('getLawArticleById')
            ->with('article-id')
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Article']],
                'isError' => false,
            ]);

        $reflection = new \ReflectionClass($this->client);
        $property = $reflection->getProperty('tools');
        $property->setAccessible(true);
        $property->setValue($this->client, $mockTools);

        $result = $this->client->callTool('law-article-by-id', [
            'id' => 'article-id',
        ]);

        $this->assertFalse($result['isError']);
    }

    /** @test */
    public function call_tool_returns_error_for_unknown_tool(): void
    {
        $result = $this->client->callTool('unknown-tool', []);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('Unknown tool', $result['content'][0]['text']);
    }

    /** @test */
    public function call_tool_handles_exceptions_gracefully(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->method('search')
            ->willThrowException(new \Exception('Test exception'));

        $reflection = new \ReflectionClass($this->client);
        $property = $reflection->getProperty('tools');
        $property->setAccessible(true);
        $property->setValue($this->client, $mockTools);

        $result = $this->client->callTool('odluke-search', ['q' => 'test']);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('Test exception', $result['content'][0]['text']);
    }

    /** @test */
    public function call_tool_uses_default_values_when_arguments_missing(): void
    {
        $mockTools = $this->createMock(OdlukeTools::class);
        $mockTools->expects($this->once())
            ->method('search')
            ->with(null, null, 100, 1, null)
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Results']],
                'isError' => false,
            ]);

        $reflection = new \ReflectionClass($this->client);
        $property = $reflection->getProperty('tools');
        $property->setAccessible(true);
        $property->setValue($this->client, $mockTools);

        // Call with empty arguments
        $result = $this->client->callTool('odluke-search', []);

        $this->assertFalse($result['isError']);
    }

    /** @test */
    public function list_tools_returns_all_available_tools(): void
    {
        $tools = $this->client->listTools();

        $this->assertIsArray($tools);
        $this->assertArrayHasKey('tools', $tools);
        // Note: InternalMcpClient currently only exposes 5 old tools via OdlukeTools
        // The new tools (law.search, law.get_article, decision.search, decision.get, case.search)
        // are available via HTTP REST API (McpToolsController) but not yet via InternalMcpClient
        $this->assertCount(5, $tools['tools']);

        $toolNames = array_map(fn ($t) => $t['name'], $tools['tools']);
        $this->assertContains('odluke-search', $toolNames);
        $this->assertContains('odluke-meta', $toolNames);
        $this->assertContains('odluke-download', $toolNames);
        $this->assertContains('law-articles-search', $toolNames);
        $this->assertContains('law-article-by-id', $toolNames);
    }

    /** @test */
    public function list_tools_includes_input_schemas(): void
    {
        $tools = $this->client->listTools();

        foreach ($tools['tools'] as $tool) {
            $this->assertArrayHasKey('name', $tool);
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('inputSchema', $tool);
            $this->assertEquals('object', $tool['inputSchema']['type']);
        }
    }
}
