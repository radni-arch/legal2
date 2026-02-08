<?php

namespace App\Services\Analysis\CaseLevel\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\CaseLevel\AI\Contracts\ClaudeClientInterface;
use Illuminate\Support\Facades\Log;

/**
 * Gap Analyzer - AI-powered case file gap detection.
 *
 * Identifies what's MISSING from the case file:
 * - Expected documents not present (warrants, witness statements, etc.)
 * - Time periods not covered in documentation
 * - Procedural steps not documented (notifications, appeals, etc.)
 *
 * Specifically tuned for Croatian criminal defense, referencing:
 * - ZKP (Zakon o kaznenom postupku) procedural requirements
 * - Standard document chains in Croatian criminal proceedings
 * - Detention review timelines, appeal deadlines, etc.
 */
class GapAnalyzer
{
    private const ANALYSIS_TYPE = 'gaps';

    public function __construct(
        private ClaudeClientInterface $claudeClient
    ) {}

    /**
     * Get the analysis type identifier.
     */
    public function analysisType(): string
    {
        return self::ANALYSIS_TYPE;
    }

    /**
     * Analyze a case for gaps in documentation.
     *
     * @param string $caseId The case identifier
     * @return array Analysis results with structure:
     *               ['results' => ['gaps' => array, 'timeline_gaps' => array, 'procedural_gaps' => array], 'metadata' => array]
     */
    public function analyze(string $caseId): array
    {
        $startTime = microtime(true);

        Log::info("GapAnalyzer: Starting analysis for case {$caseId}");

        // Gather all documents and their analyses for this case
        $documents = $this->gatherCaseDocuments($caseId);
        $documentsAnalyzed = $documents->count();

        // Gather all completed analyses for these documents
        $analysesData = $this->gatherAllAnalyses($documents);

        // Build the case data payload for Claude
        $caseData = $this->buildCaseDataPayload($caseId, $documents, $analysesData);

        // Early return if no documents exist
        if ($documentsAnalyzed === 0) {
            Log::info("GapAnalyzer: No documents found for case {$caseId}");
            return $this->buildEmptyResult($documentsAnalyzed, $startTime);
        }

        // Call Claude API for gap analysis
        $claudeResponse = $this->claudeClient->analyzeForGaps($caseData, [
            'case_id' => $caseId,
            'document_count' => $documentsAnalyzed,
            'jurisdiction' => 'HR', // Croatian jurisdiction
        ]);

        // Process and categorize the results
        $processedResults = $this->processClaudeResponse($claudeResponse);

        $processingTime = round(microtime(true) - $startTime, 4);

        Log::info("GapAnalyzer: Completed analysis for case {$caseId}", [
            'gaps_found' => count($processedResults['gaps']),
            'timeline_gaps_found' => count($processedResults['timeline_gaps']),
            'procedural_gaps_found' => count($processedResults['procedural_gaps']),
            'processing_time_seconds' => $processingTime,
        ]);

        $documentIds = $documents->pluck('id')->map(fn($id) => (string) $id)->toArray();

        return [
            'results' => $processedResults,
            'metadata' => [
                'documents_analyzed' => $documentsAnalyzed,
                'document_ids' => $documentIds,
                'processing_time_seconds' => $processingTime,
                'model' => config('services.claude.model', 'claude-sonnet-4-5-20250929'),
            ],
        ];
    }

    /**
     * Gather all documents for the case.
     */
    private function gatherCaseDocuments(string $caseId)
    {
        return CaseDocument::where('case_id', $caseId)
            ->select('id', 'title', 'category', 'case_id', 'metadata', 'created_at')
            ->get();
    }

    /**
     * Gather all completed analyses for the given documents.
     */
    private function gatherAllAnalyses($documents): array
    {
        $documentIds = $documents->pluck('id');

        $analyses = DocumentAnalysis::whereIn('case_document_id', $documentIds)
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->get();

        // Group by document and type
        $grouped = [];
        foreach ($analyses as $analysis) {
            $docId = (string) $analysis->case_document_id;
            $type = $analysis->analysis_type;

            if (!isset($grouped[$docId])) {
                $grouped[$docId] = [];
            }
            $grouped[$docId][$type] = $analysis->results;
        }

        return $grouped;
    }

    /**
     * Build the case data payload for Claude API.
     */
    private function buildCaseDataPayload(string $caseId, $documents, array $analysesData): array
    {
        $documentsPayload = [];

        foreach ($documents as $doc) {
            $docId = (string) $doc->id;
            $docAnalyses = $analysesData[$docId] ?? [];

            $documentsPayload[] = [
                'document_id' => $docId,
                'title' => $doc->title ?? "Document {$docId}",
                'category' => $doc->category ?? 'unknown',
                'created_at' => $doc->created_at?->toDateString(),
                'metadata' => $doc->metadata ?? [],
                'analyses' => $docAnalyses,
            ];
        }

        return [
            'case_id' => $caseId,
            'documents' => $documentsPayload,
            'analyses' => $analysesData,
            'jurisdiction' => 'HR',
            'legal_context' => [
                'criminal_procedure_law' => 'ZKP (Zakon o kaznenom postupku)',
                'relevant_articles' => [
                    'detention' => ['art. 123', 'art. 130', 'art. 131'],
                    'search' => ['art. 240', 'art. 241', 'art. 244'],
                    'evidence' => ['art. 10', 'art. 11'],
                ],
            ],
        ];
    }

    /**
     * Process Claude response and categorize gaps.
     */
    private function processClaudeResponse(array $response): array
    {
        $gaps = $response['gaps'] ?? [];
        $timelineGaps = $response['timeline_gaps'] ?? [];
        $proceduralGaps = $response['procedural_gaps'] ?? [];
        $summary = $response['summary'] ?? '';

        // Combine all gaps for severity categorization
        $allGaps = array_merge(
            array_map(fn($g) => array_merge($g, ['gap_category' => 'document']), $gaps),
            array_map(fn($g) => array_merge($g, ['gap_category' => 'timeline']), $timelineGaps),
            array_map(fn($g) => array_merge($g, ['gap_category' => 'procedural']), $proceduralGaps)
        );

        $bySeverity = $this->categorizeBy($allGaps, 'severity');
        $byType = $this->categorizeBy($allGaps, 'type');

        return [
            'gaps' => $gaps,
            'timeline_gaps' => $timelineGaps,
            'procedural_gaps' => $proceduralGaps,
            'by_severity' => $bySeverity,
            'by_type' => $byType,
            'total_count' => count($allGaps),
            'summary' => $summary,
        ];
    }

    /**
     * Categorize items by a specific field.
     */
    private function categorizeBy(array $items, string $field): array
    {
        $categories = [];
        foreach ($items as $item) {
            $value = $item[$field] ?? 'unknown';
            $categories[$value] = ($categories[$value] ?? 0) + 1;
        }
        return $categories;
    }

    /**
     * Build an empty result structure.
     */
    private function buildEmptyResult(int $documentsAnalyzed, float $startTime): array
    {
        return [
            'results' => [
                'gaps' => [],
                'timeline_gaps' => [],
                'procedural_gaps' => [],
                'by_severity' => [],
                'by_type' => [],
                'total_count' => 0,
                'summary' => '',
            ],
            'metadata' => [
                'documents_analyzed' => $documentsAnalyzed,
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
            ],
        ];
    }
}
