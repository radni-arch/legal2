<?php

namespace App\Services\Analysis\CaseLevel;

/**
 * Builds the document identity matrix for a case.
 *
 * This service assembles document identities from extraction results,
 * detecting which documents are present vs missing based on:
 * - Case number suffixes (gaps in sequence = missing documents)
 * - KLASA/URBROJ references
 * - Cross-document references
 *
 * Full implementation: Task 39 in case-document-analysis-plan-v4
 *
 * @see \App\Models\DocumentIdentity
 */
class DocumentIdentityBuilder
{
    /**
     * Build the complete identity matrix for a case.
     *
     * @param string $caseId The case identifier
     * @return array{
     *     total_identities: int,
     *     present: int,
     *     missing: int,
     *     by_case_number: array<string, array{
     *         prefix: ?string,
     *         role: ?string,
     *         institution: ?string,
     *         total_documents: int,
     *         present: int,
     *         missing: int,
     *         suffixes: array<int, array{status: string, klasa: ?string, urbroj: ?string, date: ?string, type: ?string, doc_id: ?int}>
     *     }>,
     *     by_klasa: array<string, array>,
     *     processing_time_seconds: float
     * }
     */
    public function build(string $caseId): array
    {
        // TODO: Full implementation in Task 39
        // For now, return empty structure
        return [
            'total_identities' => 0,
            'present' => 0,
            'missing' => 0,
            'by_case_number' => [],
            'by_klasa' => [],
            'processing_time_seconds' => 0.0,
        ];
    }
}
