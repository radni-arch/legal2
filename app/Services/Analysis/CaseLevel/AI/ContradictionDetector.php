<?php

namespace App\Services\Analysis\CaseLevel\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\CaseLevel\AI\Contracts\ClaudeClientInterface;
use Illuminate\Support\Facades\Log;

/**
 * Contradiction Detector - AI-powered cross-document contradiction analysis.
 *
 * Feeds key facts from ALL documents in a case to Claude, asking it to identify
 * contradictions, inconsistencies, and conflicts between statements, dates, and claims.
 *
 * Returns structured JSON with:
 * - Contradiction pairs (which facts contradict each other)
 * - Severity ratings (high/medium/low)
 * - Contradiction types (timeline_conflict, statement_conflict, procedural_irregularity)
 * - Investigation points (what to verify to resolve contradictions)
 */
class ContradictionDetector
{
    private const ANALYSIS_TYPE = 'contradictions';
    private const MIN_DOCUMENTS_FOR_ANALYSIS = 2;

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
     * Analyze a case for contradictions between document facts.
     *
     * @param string $caseId The case identifier
     * @return array Analysis results with structure:
     *               ['results' => ['contradictions' => array, ...], 'metadata' => array]
     */
    public function analyze(string $caseId): array
    {
        $startTime = microtime(true);

        Log::info("ContradictionDetector: Starting analysis for case {$caseId}");

        // Gather all key_facts analyses for this case
        $keyFactsAnalyses = $this->gatherKeyFactsAnalyses($caseId);
        $documentsAnalyzed = $keyFactsAnalyses->count();

        // Build document metadata map
        $documentMetadata = $this->buildDocumentMetadataMap($keyFactsAnalyses);
        $documentIds = array_keys($documentMetadata);

        // Early return if no key facts exist
        if ($documentsAnalyzed === 0) {
            Log::info("ContradictionDetector: No key_facts found for case {$caseId}");
            return $this->buildEmptyResult($documentsAnalyzed, $startTime);
        }

        // Early return if insufficient documents for meaningful contradiction analysis
        if ($documentsAnalyzed < self::MIN_DOCUMENTS_FOR_ANALYSIS) {
            Log::info("ContradictionDetector: Insufficient documents ({$documentsAnalyzed}) for case {$caseId}");
            return $this->buildEmptyResult($documentsAnalyzed, $startTime, 'insufficient_documents');
        }

        // Build the facts payload for Claude
        $factsPayload = $this->buildFactsPayload($keyFactsAnalyses, $documentMetadata);

        // Call Claude API for contradiction analysis
        $claudeResponse = $this->claudeClient->analyzeForContradictions($factsPayload, [
            'case_id' => $caseId,
            'document_count' => $documentsAnalyzed,
        ]);

        // Process and categorize the results
        $processedResults = $this->processClaudeResponse($claudeResponse, $documentMetadata);

        $processingTime = round(microtime(true) - $startTime, 4);

        Log::info("ContradictionDetector: Completed analysis for case {$caseId}", [
            'contradictions_found' => count($processedResults['contradictions']),
            'processing_time_seconds' => $processingTime,
        ]);

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
     * Gather all completed key_facts analyses for documents in the case.
     */
    private function gatherKeyFactsAnalyses(string $caseId)
    {
        return DocumentAnalysis::whereHas('caseDocument', function ($query) use ($caseId) {
            $query->where('case_id', $caseId);
        })
            ->where('analysis_type', DocumentAnalysis::TYPE_KEY_FACTS)
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->with('caseDocument:id,title,category,case_id')
            ->get();
    }

    /**
     * Build a map of document ID to metadata.
     */
    private function buildDocumentMetadataMap($analyses): array
    {
        $map = [];
        foreach ($analyses as $analysis) {
            $doc = $analysis->caseDocument;
            if ($doc) {
                // Cast ID to string to handle ULID objects
                $docId = (string) $doc->id;
                $map[$docId] = [
                    'id' => $docId,
                    'title' => $doc->title ?? "Document {$docId}",
                    'category' => $doc->category ?? 'unknown',
                ];
            }
        }
        return $map;
    }

    /**
     * Build the facts payload for Claude API.
     */
    private function buildFactsPayload($analyses, array $documentMetadata): array
    {
        $payload = [];

        foreach ($analyses as $analysis) {
            $docId = (string) $analysis->case_document_id;
            $docMeta = $documentMetadata[$docId] ?? ['id' => $docId, 'title' => "Document {$docId}"];
            $keyFacts = $analysis->results['key_facts'] ?? [];

            $payload[] = [
                'document_id' => $docId,
                'document_title' => $docMeta['title'],
                'category' => $docMeta['category'] ?? 'unknown',
                'key_facts' => $keyFacts,
            ];
        }

        return $payload;
    }

    /**
     * Process Claude response and categorize contradictions.
     */
    private function processClaudeResponse(array $response, array $documentMetadata): array
    {
        $contradictions = $response['contradictions'] ?? [];
        $summary = $response['summary'] ?? '';

        // Enrich contradictions with document metadata
        $enrichedContradictions = array_map(function ($contradiction) use ($documentMetadata) {
            return $this->enrichContradiction($contradiction, $documentMetadata);
        }, $contradictions);

        // Categorize by severity and type
        $bySeverity = $this->categorizeBy($enrichedContradictions, 'severity');
        $byType = $this->categorizeBy($enrichedContradictions, 'type');

        return [
            'contradictions' => $enrichedContradictions,
            'by_severity' => $bySeverity,
            'by_type' => $byType,
            'total_count' => count($enrichedContradictions),
            'summary' => $summary,
        ];
    }

    /**
     * Enrich a contradiction with full document metadata.
     */
    private function enrichContradiction(array $contradiction, array $documentMetadata): array
    {
        // Enrich fact_a with document info
        if (isset($contradiction['fact_a']['document_id'])) {
            $docId = (string) $contradiction['fact_a']['document_id'];
            if (isset($documentMetadata[$docId])) {
                $contradiction['fact_a']['document_title'] = $documentMetadata[$docId]['title'];
                $contradiction['fact_a']['category'] = $documentMetadata[$docId]['category'];
            }
        }

        // Enrich fact_b with document info
        if (isset($contradiction['fact_b']['document_id'])) {
            $docId = (string) $contradiction['fact_b']['document_id'];
            if (isset($documentMetadata[$docId])) {
                $contradiction['fact_b']['document_title'] = $documentMetadata[$docId]['title'];
                $contradiction['fact_b']['category'] = $documentMetadata[$docId]['category'];
            }
        }

        return $contradiction;
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
    private function buildEmptyResult(int $documentsAnalyzed, float $startTime, ?string $skipReason = null): array
    {
        $metadata = [
            'documents_analyzed' => $documentsAnalyzed,
            'processing_time_seconds' => round(microtime(true) - $startTime, 4),
        ];

        if ($skipReason) {
            $metadata['skip_reason'] = $skipReason;
        }

        return [
            'results' => [
                'contradictions' => [],
                'by_severity' => [],
                'by_type' => [],
                'total_count' => 0,
                'summary' => '',
            ],
            'metadata' => $metadata,
        ];
    }
}
