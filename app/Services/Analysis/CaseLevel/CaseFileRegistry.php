<?php

namespace App\Services\Analysis\CaseLevel;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CaseFileRegistry
{
    /**
     * Build/rebuild the reference registry for a case.
     *
     * Cross-references all CaseReferenceExtractor results to determine:
     * - Which references are PRESENT (a document has this as its own ref)
     * - Which are only REFERENCED (mentioned in other docs but no source doc)
     * - Which are MISSING (referenced but not present in case file)
     */
    public function build(string $caseId): array
    {
        $startTime = microtime(true);

        // Gather all case_references analysis results
        $analyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->where('analysis_type', 'case_references')
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->with('caseDocument')
            ->get();

        // Phase 1: Collect all references and which documents they appear in
        $registry = []; // key: "type|value" → data

        foreach ($analyses as $analysis) {
            $docId = $analysis->case_document_id;
            $results = $analysis->results;

            foreach (['klasa', 'urbroj', 'broj', 'case_numbers'] as $refGroup) {
                foreach ($results[$refGroup] ?? [] as $ref) {
                    $type = $ref['type'] ?? $refGroup;
                    $value = $ref['value'];
                    $key = $type . '|' . mb_strtolower($value);

                    if (!isset($registry[$key])) {
                        $registry[$key] = [
                            'reference_type' => $type,
                            'reference_value' => $value,
                            'sub_type' => $ref['sub_type'] ?? null,
                            'found_in_documents' => [],
                            'is_source_of_documents' => [],
                            'total_mentions' => 0,
                        ];
                    }

                    $registry[$key]['found_in_documents'][] = $docId;
                    $registry[$key]['total_mentions'] += ($ref['mentions'] ?? 1);
                }
            }

            // KLASA-URBROJ pairs
            foreach ($results['klasa_urbroj_pairs'] ?? [] as $klasa => $urbroj) {
                $klasaKey = "klasa|" . mb_strtolower($klasa);
                $urbrojKey = "urbroj|" . mb_strtolower($urbroj);

                if (isset($registry[$klasaKey])) {
                    $registry[$klasaKey]['paired_urbroj'] = $urbroj;
                }
                if (isset($registry[$urbrojKey])) {
                    $registry[$urbrojKey]['paired_klasa'] = $klasa;
                }
            }
        }

        // Phase 2: Determine which documents are the SOURCE of each reference
        // A document is the "source" if the ref is in the document's header/first 500 chars
        // vs just being mentioned in the body
        foreach ($analyses as $analysis) {
            $docId = $analysis->case_document_id;
            $results = $analysis->results;

            foreach (['klasa', 'urbroj', 'broj', 'case_numbers'] as $refGroup) {
                foreach ($results[$refGroup] ?? [] as $ref) {
                    // Heuristic: if found within first 500 chars, it's the document's own reference
                    if (($ref['position'] ?? 999) < 500) {
                        $key = ($ref['type'] ?? $refGroup) . '|' . mb_strtolower($ref['value']);
                        if (isset($registry[$key])) {
                            $registry[$key]['is_source_of_documents'][] = $docId;
                        }
                    }
                }
            }
        }

        // Phase 3: Determine status
        $output = [];
        $missing = [];
        $present = [];

        foreach ($registry as $key => $data) {
            $data['found_in_documents'] = array_unique($data['found_in_documents']);
            $data['is_source_of_documents'] = array_unique($data['is_source_of_documents']);

            if (!empty($data['is_source_of_documents'])) {
                $data['status'] = 'present';
                $present[] = $data;
            } else {
                $data['status'] = 'missing';
                $missing[] = $data;
            }

            $output[] = $data;
        }

        // Phase 4: Persist to case_reference_registry table
        DB::table('case_reference_registry')->where('case_id', $caseId)->delete();

        foreach ($output as $row) {
            DB::table('case_reference_registry')->insert([
                'case_id' => $caseId,
                'reference_type' => $row['reference_type'],
                'reference_value' => $row['reference_value'],
                'sub_type' => $row['sub_type'],
                'status' => $row['status'],
                'found_in_documents' => json_encode($row['found_in_documents']),
                'is_source_of_documents' => json_encode($row['is_source_of_documents']),
                'paired_klasa' => $row['paired_klasa'] ?? null,
                'paired_urbroj' => $row['paired_urbroj'] ?? null,
                'total_mentions' => $row['total_mentions'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'results' => [
                'total_references' => count($output),
                'present' => count($present),
                'missing' => count($missing),
                'missing_references' => $missing,
                'present_references' => $present,
                'references_by_type' => collect($output)->groupBy('reference_type')
                    ->map(fn($group) => [
                        'total' => $group->count(),
                        'present' => $group->where('status', 'present')->count(),
                        'missing' => $group->where('status', 'missing')->count(),
                    ])->toArray(),
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'documents_analyzed' => $analyses->count(),
            ],
        ];
    }
}
