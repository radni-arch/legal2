<?php

namespace App\Services\LegalArtillery;

use App\Models\LegalCase;
use Illuminate\Support\Facades\Log;

/**
 * Bridges case data into the Legal Artillery document generation pipeline.
 * Assembles case facts, timeline, evidence IDs, and related data.
 */
class CaseBridge
{
    /**
     * Assemble case context for document generation.
     *
     * @param int|string $caseId
     * @param array $evidenceIds Optional specific evidence IDs to include
     * @return array{
     *     case_id: string,
     *     case_number: ?string,
     *     court: ?string,
     *     facts: array,
     *     timeline: array,
     *     evidence_ids: array,
     *     parties: array,
     *     warnings: array<string>
     * }
     */
    public function assemble(int|string $caseId, array $evidenceIds = []): array
    {
        $case = LegalCase::find($caseId);
        $warnings = [];

        if (!$case) {
            Log::warning('CaseBridge: Case not found', ['case_id' => $caseId]);

            return [
                'case_id' => (string) $caseId,
                'case_number' => null,
                'court' => null,
                'facts' => [],
                'timeline' => [],
                'evidence_ids' => [],
                'parties' => [],
                'warnings' => ['Case not found: '.$caseId],
            ];
        }

        // Gather facts from case description
        $facts = [];
        if ($case->description) {
            $facts[] = $case->description;
        }

        // Build timeline from case events/dates
        $timeline = [];
        if ($case->created_at) {
            $timeline[] = ['date' => $case->created_at->toDateString(), 'event' => 'Case created'];
        }
        if ($case->filing_date) {
            $timeline[] = ['date' => $case->filing_date->toDateString(), 'event' => 'Filing date'];
        }

        // Collect evidence IDs
        $allEvidenceIds = $evidenceIds;
        if (empty($allEvidenceIds)) {
            // Load evidence from case relationship
            if (method_exists($case, 'evidence')) {
                $allEvidenceIds = $case->evidence()->pluck('id')->toArray();
            } elseif (method_exists($case, 'documents')) {
                $allEvidenceIds = $case->documents()->pluck('id')->toArray();
            }
        }

        if (empty($allEvidenceIds)) {
            $warnings[] = 'No evidence found for case '.$case->case_number;
        }

        // Parties - use client_name and opponent_name from the model
        $parties = [];
        if ($case->client_name) {
            $parties['client'] = $case->client_name;
        }
        if ($case->opponent_name) {
            $parties['opponent'] = $case->opponent_name;
        }

        Log::info('CaseBridge: Assembled case context', [
            'case_id' => $caseId,
            'facts' => count($facts),
            'evidence' => count($allEvidenceIds),
            'warnings' => count($warnings),
        ]);

        return [
            'case_id' => (string) $case->id,
            'case_number' => $case->case_number ?? null,
            'court' => $case->court ?? null,
            'facts' => $facts,
            'timeline' => $timeline,
            'evidence_ids' => $allEvidenceIds,
            'parties' => $parties,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if a case has sufficient data for document generation.
     *
     * @return array{sufficient: bool, missing: array<string>}
     */
    public function checkSufficiency(int|string $caseId): array
    {
        $case = LegalCase::find($caseId);
        $missing = [];

        if (!$case) {
            return ['sufficient' => false, 'missing' => ['Case not found']];
        }

        if (!$case->case_number) {
            $missing[] = 'Case number';
        }

        if (!$case->description) {
            $missing[] = 'Case description or notes';
        }

        return [
            'sufficient' => empty($missing),
            'missing' => $missing,
        ];
    }
}
