<?php

namespace App\Services\Analysis\CaseLevel;

use App\Models\DocumentAnalysis;

class CrossReferenceAnalyzer
{
    public function analyze(string $caseId): array
    {
        $entityAnalyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->where('analysis_type', DocumentAnalysis::TYPE_ENTITIES)
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->get();

        $referenceMap = [
            'case_numbers' => [],
            'laws' => [],
            'courts' => [],
            'institutions' => [],
        ];

        foreach ($entityAnalyses as $analysis) {
            $docId = $analysis->case_document_id;
            $entities = $analysis->results['entities'] ?? [];

            foreach (['case_numbers', 'laws', 'courts', 'institutions'] as $type) {
                foreach ($entities[$type] ?? [] as $entity) {
                    $key = $entity['case_number'] ?? $entity['law'] ?? $entity['court'] ?? $entity['institution'] ?? null;
                    if ($key) {
                        $referenceMap[$type][$key][] = $docId;
                    }
                }
            }
        }

        // Find cross-references (entities appearing in 2+ documents)
        $crossRefs = [];
        foreach ($referenceMap as $type => $entries) {
            foreach ($entries as $key => $docIds) {
                $uniqueDocs = array_unique($docIds);
                if (count($uniqueDocs) >= 2) {
                    $crossRefs[] = [
                        'type' => $type,
                        'value' => $key,
                        'document_ids' => array_values($uniqueDocs),
                        'total_mentions' => count($docIds),
                    ];
                }
            }
        }

        // Sort by number of documents (most cross-referenced first)
        usort($crossRefs, fn($a, $b) => count($b['document_ids']) <=> count($a['document_ids']));

        return [
            'results' => [
                'cross_references' => $crossRefs,
                'total_cross_refs' => count($crossRefs),
            ],
            'metadata' => [
                'documents_analyzed' => $entityAnalyses->count(),
            ],
        ];
    }
}
