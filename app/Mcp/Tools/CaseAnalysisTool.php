<?php

namespace App\Mcp\Tools;

use App\Services\CaseSearchService;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Case Analysis Tool
 *
 * Provides comprehensive case analysis including:
 * - Case strength assessment
 * - Risk analysis
 * - Timeline reconstruction
 * - Evidence evaluation
 *
 * Sprint 12.5 - Worker B: Missing MCP Tools
 */
class CaseAnalysisTool extends Tool
{
    protected string $name = 'case.analyze';

    protected string $title = 'Analyze Case [PRIVATE]';

    protected string $description = 'Analyze legal case with various analysis types: strength assessment, risk analysis, timeline reconstruction, or evidence evaluation. PRIVATE: Requires authentication.';

    /**
     * Constructor
     */
    public function __construct(
        protected CaseSearchService $searchService
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'case_id' => $schema->string()->description('Case ID (ULID) to analyze'),
            'analysis_type' => $schema->string()->enum(['strength', 'risk', 'timeline', 'evidence'])
                ->default('strength')->description('Type of analysis: strength (case strength assessment), risk (risk analysis), timeline (chronological reconstruction), evidence (evidence evaluation)'),
            'include_recommendations' => $schema->boolean()->default(true)
                ->description('Include actionable recommendations in the analysis'),
            'depth' => $schema->string()->enum(['summary', 'detailed', 'comprehensive'])
                ->default('detailed')->description('Depth of analysis: summary (high-level), detailed (standard), comprehensive (exhaustive)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $caseId = $request->get('case_id', '');
        $analysisType = $request->get('analysis_type', 'strength');

        if (empty($caseId)) {
            return Response::text(json_encode([
                'error' => 'case_id is required',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        try {
            // Delegate to service for case analysis
            $result = $this->searchService->analyzeCase($caseId, $request->all());

            return Response::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return Response::text(json_encode([
                'error' => 'Failed to analyze case',
                'message' => $e->getMessage(),
                'case_id' => $caseId,
                'analysis_type' => $analysisType,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    public function description(): string
    {
        return 'Analyze legal case with multiple analysis types: assess case strength, identify risks, reconstruct timeline, or evaluate evidence. Returns structured analysis with insights and recommendations.';
    }
}
