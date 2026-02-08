<?php

namespace App\Mcp\Tools;

use App\Services\LawSearchService;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Statutory Interpretation Tool
 *
 * Provides statutory interpretation including:
 * - Legislative history analysis
 * - Statutory construction methods
 * - Conflicting provisions resolution
 * - Regulatory framework mapping
 *
 * Sprint 12.5 - Worker B: Missing MCP Tools
 */
class StatutoryInterpretationTool extends Tool
{
    protected string $name = 'statute.interpret';

    protected string $title = 'Interpret Statute';

    protected string $description = 'Interpret statutes with various operations: analyze legislative history, apply statutory construction methods, resolve conflicting provisions, or map regulatory framework.';

    /**
     * Constructor
     */
    public function __construct(
        protected LawSearchService $searchService
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'statute' => $schema->string()->description('Statute reference to interpret (e.g., "ZKP Članak 9", "Kazneni zakon Članak 87")'),
            'operation' => $schema->string()->enum(['history', 'construction', 'conflicts', 'framework'])
                ->default('history')->description('Type of operation: history (legislative history), construction (statutory construction), conflicts (resolve conflicts), framework (regulatory framework)'),
            'method' => $schema->string()->enum(['textualist', 'purposivist', 'originalist', 'pragmatic'])
                ->default('textualist')->description('Construction method (used with construction operation)'),
            'related_statute' => $schema->string()
                ->description('Related statute for conflicts operation'),
            'include_precedents' => $schema->boolean()->default(true)
                ->description('Include relevant precedents in the analysis'),
        ];
    }

    public function handle(Request $request): Response
    {
        $statute = $request->get('statute', '');
        $operation = $request->get('operation', 'history');

        if (empty($statute)) {
            return Response::text(json_encode([
                'error' => 'statute is required',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        try {
            // Delegate to service for statutory interpretation
            $result = $this->searchService->interpretStatute($statute, $request->all());

            return Response::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return Response::text(json_encode([
                'error' => 'Failed to interpret statute',
                'message' => $e->getMessage(),
                'statute' => $statute,
                'operation' => $operation,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    public function description(): string
    {
        return 'Interpret statutes with multiple operations: analyze legislative history, apply statutory construction methods, resolve conflicting provisions, or map regulatory frameworks. Returns structured interpretation analysis.';
    }
}
