<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\UsesTestDatabase;

class McpToolsAccessibilityTest extends TestCase
{
    use UsesTestDatabase;

    public function test_mcp_decision_search_tool_exists()
    {
        $toolClass = 'App\\Mcp\\Tools\\DecisionSearchTool';
        $this->assertTrue(class_exists($toolClass), 'DecisionSearchTool class should exist');
        $this->assertTrue(method_exists($toolClass, 'handle'), 'Tool should have handle method');
    }

    public function test_mcp_citation_extraction_tool_exists()
    {
        $toolFile = app_path('Mcp/Tools/DecisionExtractCitationsTool.php');
        $this->assertFileExists($toolFile, 'CitationExtractionTool file should exist');
    }

    public function test_mcp_law_search_tool_exists()
    {
        $toolClass = 'App\\Mcp\\Tools\\LawSearchTool';
        $this->assertTrue(class_exists($toolClass), 'LawSearchTool class should exist');
    }

    public function test_mcp_api_routes_registered()
    {
        $routes = collect(\Route::getRoutes())->filter(function ($route) {
            return str_starts_with($route->uri(), 'mcp/');
        });

        $this->assertGreaterThan(0, $routes->count(), 'MCP routes should be registered');
    }
}
