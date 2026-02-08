<?php

namespace Tests\Unit\Mcp;

use App\Mcp\ToolSchemas;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for centralized ToolSchemas class
 */
class ToolSchemasTest extends TestCase
{
    public function test_all_returns_array_of_schemas(): void
    {
        $schemas = ToolSchemas::all();

        $this->assertIsArray($schemas);
        $this->assertCount(5, $schemas);
        $this->assertArrayHasKey('odluke-search', $schemas);
        $this->assertArrayHasKey('odluke-meta', $schemas);
        $this->assertArrayHasKey('odluke-download', $schemas);
        $this->assertArrayHasKey('law-articles-search', $schemas);
        $this->assertArrayHasKey('law-article-by-id', $schemas);
    }

    public function test_schema_structure_is_valid(): void
    {
        $schema = ToolSchemas::get('odluke-search');

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('description', $schema);
        $this->assertArrayHasKey('inputSchema', $schema);

        $this->assertEquals('odluke-search', $schema['name']);
        $this->assertIsString($schema['description']);
        $this->assertIsArray($schema['inputSchema']);
        $this->assertEquals('object', $schema['inputSchema']['type']);
        $this->assertArrayHasKey('properties', $schema['inputSchema']);
    }

    public function test_get_returns_correct_schema_for_odluke_search(): void
    {
        $schema = ToolSchemas::get('odluke-search');

        $this->assertEquals('odluke-search', $schema['name']);
        $this->assertArrayHasKey('q', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('params', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('limit', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('page', $schema['inputSchema']['properties']);
    }

    public function test_get_returns_correct_schema_for_odluke_meta(): void
    {
        $schema = ToolSchemas::get('odluke-meta');

        $this->assertEquals('odluke-meta', $schema['name']);
        $this->assertArrayHasKey('id', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('ids', $schema['inputSchema']['properties']);
    }

    public function test_get_returns_correct_schema_for_odluke_download(): void
    {
        $schema = ToolSchemas::get('odluke-download');

        $this->assertEquals('odluke-download', $schema['name']);
        $this->assertArrayHasKey('id', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('format', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('save', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('required', $schema['inputSchema']);
        $this->assertEquals(['id'], $schema['inputSchema']['required']);
    }

    public function test_get_returns_correct_schema_for_law_articles_search(): void
    {
        $schema = ToolSchemas::get('law-articles-search');

        $this->assertEquals('law-articles-search', $schema['name']);
        $this->assertArrayHasKey('query', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('law_number', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('title', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('limit', $schema['inputSchema']['properties']);
    }

    public function test_get_returns_correct_schema_for_law_article_by_id(): void
    {
        $schema = ToolSchemas::get('law-article-by-id');

        $this->assertEquals('law-article-by-id', $schema['name']);
        $this->assertArrayHasKey('id', $schema['inputSchema']['properties']);
        $this->assertArrayHasKey('required', $schema['inputSchema']);
        $this->assertEquals(['id'], $schema['inputSchema']['required']);
    }

    public function test_get_returns_null_for_unknown_tool(): void
    {
        $schema = ToolSchemas::get('non-existent-tool');

        $this->assertNull($schema);
    }

    public function test_to_openai_function_converts_correctly(): void
    {
        $function = ToolSchemas::toOpenAIFunction('odluke-search');

        $this->assertIsArray($function);
        $this->assertEquals('function', $function['type']);
        $this->assertArrayHasKey('function', $function);
        $this->assertEquals('odluke_search', $function['function']['name']); // snake_case
        $this->assertIsString($function['function']['description']);
        $this->assertArrayHasKey('parameters', $function['function']);
    }

    public function test_to_openai_function_converts_name_to_snake_case(): void
    {
        $functions = [
            'odluke-search' => 'odluke_search',
            'odluke-meta' => 'odluke_meta',
            'odluke-download' => 'odluke_download',
            'law-articles-search' => 'law_articles_search',
            'law-article-by-id' => 'law_article_by_id',
        ];

        foreach ($functions as $toolName => $expectedName) {
            $function = ToolSchemas::toOpenAIFunction($toolName);
            $this->assertEquals($expectedName, $function['function']['name']);
        }
    }

    public function test_to_openai_function_returns_null_for_unknown_tool(): void
    {
        $function = ToolSchemas::toOpenAIFunction('non-existent-tool');

        $this->assertNull($function);
    }

    public function test_all_openai_functions_returns_correct_count(): void
    {
        $functions = ToolSchemas::allOpenAIFunctions();

        $this->assertIsArray($functions);
        $this->assertCount(5, $functions);
    }

    public function test_all_openai_functions_have_correct_structure(): void
    {
        $functions = ToolSchemas::allOpenAIFunctions();

        foreach ($functions as $function) {
            $this->assertEquals('function', $function['type']);
            $this->assertArrayHasKey('function', $function);
            $this->assertArrayHasKey('name', $function['function']);
            $this->assertArrayHasKey('description', $function['function']);
            $this->assertArrayHasKey('parameters', $function['function']);
        }
    }

    public function test_schemas_have_proper_validation_constraints(): void
    {
        $schemas = ToolSchemas::all();

        // Check odluke-search has proper limits
        $odlukeSearch = $schemas['odluke-search'];
        $this->assertEquals(1, $odlukeSearch['inputSchema']['properties']['limit']['minimum']);
        $this->assertEquals(500, $odlukeSearch['inputSchema']['properties']['limit']['maximum']);

        // Check law-articles-search has proper limits
        $lawSearch = $schemas['law-articles-search'];
        $this->assertEquals(1, $lawSearch['inputSchema']['properties']['limit']['minimum']);
        $this->assertEquals(100, $lawSearch['inputSchema']['properties']['limit']['maximum']);
    }

    public function test_format_enum_is_correct_for_download(): void
    {
        $schema = ToolSchemas::get('odluke-download');

        $this->assertArrayHasKey('enum', $schema['inputSchema']['properties']['format']);
        $this->assertEquals(['pdf', 'html', 'both'], $schema['inputSchema']['properties']['format']['enum']);
    }
}
