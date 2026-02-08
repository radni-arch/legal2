<?php

use App\Mcp\OdlukeTools;
use App\Services\UnifiedSearchService;
use Laravel\Mcp\Facades\Mcp;

// ====================================================================
// CROATIAN LEGAL TOOLS - MCP REGISTRATION
// ====================================================================
// This file registers all Croatian legal research tools via MCP protocol
// for external access (Claude Desktop, API clients, etc.)
// ====================================================================

// ---------------------------------------------------------------------
// 1. LAW SEARCH TOOL
// ---------------------------------------------------------------------
Mcp::tool(function (string $query, ?string $law_number = null, ?string $title = null, int $limit = 10): array {
    $tools = app(OdlukeTools::class);

    return $tools->searchLawArticles($query, $law_number, $title, $limit);
})
    ->name('law_search')
    ->description('Search Croatian laws and legal articles by content, law number (e.g., "NN 94/14"), or title. Returns matching law articles with metadata including doc_id, title, law_number, jurisdiction, and article content.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'query' => [
                'type' => 'string',
                'description' => 'Search query for law content (e.g., "radni odnos", "ugovor o radu", "otkaz")',
            ],
            'law_number' => [
                'type' => 'string',
                'description' => 'Optional filter by law number (e.g., "NN 94/14", "149/09")',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Optional filter by law title (e.g., "Zakon o radu")',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 10,
                'description' => 'Maximum number of results to return',
            ],
        ],
        'required' => ['query'],
    ]);

// ---------------------------------------------------------------------
// 2. LAW ARTICLE BY ID TOOL
// ---------------------------------------------------------------------
Mcp::tool(function (string $id): array {
    $tools = app(OdlukeTools::class);

    return $tools->getLawArticleById($id);
})
    ->name('law_get_article')
    ->description('Get full content of a specific law article by its database ID. Use this after law_search to retrieve complete article text, metadata, parent law info, and all related fields.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Database ID of the law article (obtained from law_search results)',
            ],
        ],
        'required' => ['id'],
    ]);

// ---------------------------------------------------------------------
// 3. COURT DECISION SEARCH TOOL
// ---------------------------------------------------------------------
Mcp::tool(function (string $q, ?string $params = null, int $limit = 100, int $page = 1, ?string $base_url = null): array {
    $tools = app(OdlukeTools::class);

    return $tools->search($q, $params, $limit, $page, $base_url);
})
    ->name('decision_search')
    ->description('Search Croatian court decisions on odluke.sudovi.hr database. Returns decision IDs and basic metadata. Use decision_get_metadata for full details of each decision.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'q' => [
                'type' => 'string',
                'description' => 'Search query for court decisions (e.g., "radni spor", "otkaz ugovora", "naknada štete")',
            ],
            'params' => [
                'type' => 'string',
                'description' => 'Optional additional query parameters for filtering',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 100,
                'description' => 'Results per page',
            ],
            'page' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 1,
                'description' => 'Page number for pagination',
            ],
            'base_url' => [
                'type' => 'string',
                'description' => 'Optional custom base URL for the court decision database',
            ],
        ],
        'required' => ['q'],
    ]);

// ---------------------------------------------------------------------
// 4. DECISION METADATA TOOL
// ---------------------------------------------------------------------
Mcp::tool(function (?string $id = null, ?array $ids = null, ?string $base_url = null): array {
    $tools = app(OdlukeTools::class);

    return $tools->meta($id, $ids, $base_url);
})
    ->name('decision_get_metadata')
    ->description('Get detailed metadata for one or more court decisions including court name, judge, decision date, case number, ECLI identifier, and other structured data.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Single decision ID (GUID) from odluke.sudovi.hr',
            ],
            'ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Array of decision IDs for batch retrieval',
            ],
            'base_url' => [
                'type' => 'string',
                'description' => 'Optional custom base URL for the court decision database',
            ],
        ],
        // Note: Either id OR ids is required, but not enforced by schema
    ]);

// ---------------------------------------------------------------------
// 5. DECISION DOWNLOAD TOOL
// ---------------------------------------------------------------------
Mcp::tool(function (string $id, string $format = 'pdf', bool $save = false, ?string $base_url = null): array {
    $tools = app(OdlukeTools::class);

    return $tools->download($id, $format, $save, $base_url);
})
    ->name('decision_download')
    ->description('Download a court decision in PDF or HTML format. Can optionally save to local storage in storage/app/odluke directory.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Decision ID (GUID) from odluke.sudovi.hr',
            ],
            'format' => [
                'type' => 'string',
                'enum' => ['pdf', 'html', 'both'],
                'default' => 'pdf',
                'description' => 'Download format: pdf, html, or both',
            ],
            'save' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Whether to save the file to local storage (storage/app/odluke)',
            ],
            'base_url' => [
                'type' => 'string',
                'description' => 'Optional custom base URL for the court decision database',
            ],
        ],
        'required' => ['id'],
    ]);

// ---------------------------------------------------------------------
// 6. UNIFIED LEGAL SEARCH TOOL
// ---------------------------------------------------------------------
Mcp::tool(function (string $query, ?array $corpora = null, ?string $jurisdiction = null, int $limit = 10): array {
    $searchService = app(UnifiedSearchService::class);

    $results = $searchService->search($query, [
        'corpora' => $corpora ?? ['laws', 'decisions', 'cases'],
        'filters' => array_filter([
            'jurisdiction' => $jurisdiction,
        ]),
        'limit' => $limit,
    ]);

    // Return results in MCP format
    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]],
        'isError' => false,
    ];
})
    ->name('legal_search')
    ->description('Unified hybrid search across all legal corpora (laws, court decisions, case documents) using vector similarity + keyword matching + citation detection. Best for complex legal research queries.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'query' => [
                'type' => 'string',
                'description' => 'Legal research query (e.g., "What are the grounds for termination of employment?", "pravni lijekovi u upravnom postupku")',
            ],
            'corpora' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['laws', 'decisions', 'cases']],
                'description' => 'Which legal corpora to search. Defaults to all: ["laws", "decisions", "cases"]',
                'default' => ['laws', 'decisions', 'cases'],
            ],
            'jurisdiction' => [
                'type' => 'string',
                'description' => 'Filter by jurisdiction code (e.g., "HR" for Croatia)',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 50,
                'default' => 10,
                'description' => 'Maximum total results to return',
            ],
        ],
        'required' => ['query'],
    ]);

// ---------------------------------------------------------------------
// DEBUG LOGGING (LOCAL ENVIRONMENT ONLY)
// ---------------------------------------------------------------------
if (app()->environment('local')) {
    \Illuminate\Support\Facades\Log::info('MCP Legal Tools Registered', [
        'tools' => [
            'law_search' => 'Search Croatian laws by content/number/title',
            'law_get_article' => 'Get specific law article by ID',
            'decision_search' => 'Search court decisions on odluke.sudovi.hr',
            'decision_get_metadata' => 'Get decision metadata by ID(s)',
            'decision_download' => 'Download decision PDF/HTML',
            'legal_search' => 'Unified hybrid search across all corpora',
        ],
        'count' => 6,
        'timestamp' => now()->toIso8601String(),
    ]);
}
