<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CaseSearchTool;
use App\Mcp\Tools\DecisionAnalyzeCitationsTool;
use App\Mcp\Tools\DecisionCompareFactsTool;
use App\Mcp\Tools\DecisionExtractCitationsTool;
use App\Mcp\Tools\DecisionExtractFactsTool;
use App\Mcp\Tools\DecisionFindSimilarTool;
use App\Mcp\Tools\DecisionGetTool;
use App\Mcp\Tools\DecisionSearchByCitedLawTool;
use App\Mcp\Tools\DecisionSearchTool;
use App\Mcp\Tools\LawGetArticleTool;
use App\Mcp\Tools\LawSearchTool;
use App\Tools\OdlukeDownloadTool;
use App\Tools\OdlukeMetaTool;
use App\Tools\OdlukeSearchTool;
use Laravel\Mcp\Server;

class OdlukeServer extends Server
{
    protected string $name = 'Legal Database MCP Server';

    protected string $version = '2.0.0';

    public string $instructions = 'MCP server for Croatian legal data: laws, court decisions, and cases. Provides search and retrieval tools for legal research and case management.';

    public array $tools = [
        // External Odluke API tools
        OdlukeSearchTool::class,
        OdlukeMetaTool::class,
        OdlukeDownloadTool::class,

        // Internal law database tools
        LawSearchTool::class,
        LawGetArticleTool::class,

        // Court decision tools
        DecisionSearchTool::class,
        DecisionGetTool::class,
        DecisionExtractCitationsTool::class,
        DecisionExtractFactsTool::class,
        DecisionFindSimilarTool::class,
        DecisionSearchByCitedLawTool::class,
        DecisionAnalyzeCitationsTool::class,
        DecisionCompareFactsTool::class,

        // Case management tools (private)
        CaseSearchTool::class,
    ];

    // resources / prompts po potrebi...
}
