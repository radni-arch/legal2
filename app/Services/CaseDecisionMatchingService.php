<?php

namespace App\Services;

use App\DTOs\MatchingResult;
use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use App\Models\CourtDecision;
use Illuminate\Support\Collection;

class CaseDecisionMatchingService
{
    /**
     * Parse case number into base and year components
     */
    public function parseCaseNumber(string $caseNumber): array
    {
        // Pattern: "Register-Number/Year" e.g., "Pp Prz-75/2025"
        if (preg_match('/^(.+)-(\d+)\/(\d{4})$/', $caseNumber, $matches)) {
            return [
                'base' => $matches[1] . '-' . $matches[2],
                'year' => (int) $matches[3],
            ];
        }

        return [
            'base' => $caseNumber,
            'year' => null,
        ];
    }

    /**
     * Match a single case to available decisions
     */
    public function matchCase(CourtCase $case, string $source): ?CourtCaseDecisionMatch
    {
        $parsed = $this->parseCaseNumber($case->case_number);

        if (!$parsed['base'] || !$parsed['year']) {
            return null;
        }

        // Find matching decisions
        $decisions = CourtDecision::query()
            ->where('case_number', 'LIKE', '%' . $parsed['base'] . '%')
            ->whereYear('decision_date', $parsed['year'])
            ->get();

        if ($decisions->isEmpty()) {
            return null;
        }

        // Score and select best match
        $bestMatch = null;
        $bestScore = 0;

        foreach ($decisions as $decision) {
            $score = $this->calculateConfidence($case, $decision, $parsed);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $decision;
            }
        }

        if (!$bestMatch) {
            return null;
        }

        // Create match record
        return CourtCaseDecisionMatch::create([
            'court_case_id' => $case->id,
            'court_decision_id' => $bestMatch->id,
            'matched_at' => now(),
            'match_type' => 'auto',
            'match_confidence' => $bestScore,
            'match_source' => $source,
            'verification_status' => $bestScore >= 100 ? 'verified' : 'pending',
            'match_criteria' => [
                'case_number' => $parsed['base'],
                'year' => $parsed['year'],
                'court_matched' => $this->courtsMatch($case, $bestMatch),
            ],
        ]);
    }

    /**
     * Calculate match confidence score
     */
    protected function calculateConfidence(CourtCase $case, CourtDecision $decision, array $parsed): int
    {
        $score = 0;

        // Exact case number match
        if (str_contains($decision->case_number ?? '', $parsed['base'])) {
            $score += 60;
        }

        // Year match
        if ($decision->decision_date?->year === $parsed['year']) {
            $score += 20;
        }

        // Court match
        if ($this->courtsMatch($case, $decision)) {
            $score += 20;
        }

        return min($score, 100);
    }

    /**
     * Check if courts match between case and decision
     */
    protected function courtsMatch(CourtCase $case, CourtDecision $decision): bool
    {
        if (!$case->court || !$decision->court) {
            return false;
        }

        // Normalize and compare court names
        $caseCourt = strtolower(trim($case->court->name ?? ''));
        $decisionCourt = strtolower(trim($decision->court ?? ''));

        return str_contains($decisionCourt, $caseCourt) || str_contains($caseCourt, $decisionCourt);
    }

    /**
     * Match unmatched cases in batch
     */
    public function matchUnmatchedCases(int $limit, string $source): MatchingResult
    {
        $result = new MatchingResult();

        $cases = CourtCase::unmatched()
            ->limit($limit)
            ->get();

        foreach ($cases as $case) {
            try {
                $match = $this->matchCase($case, $source);

                if ($match) {
                    $result->recordMatch($match->match_confidence);
                } else {
                    $result->recordNoMatch();
                }
            } catch (\Exception $e) {
                $result->recordError("Case {$case->id}: {$e->getMessage()}");
            }
        }

        return $result;
    }

    /**
     * Full reconciliation - reprocess all cases
     */
    public function reconcileAll(string $source): MatchingResult
    {
        $result = new MatchingResult();

        CourtCase::query()
            ->chunk(100, function ($cases) use ($source, &$result) {
                foreach ($cases as $case) {
                    try {
                        // Skip if already has verified match
                        if ($case->decisionMatches()->where('verification_status', 'verified')->exists()) {
                            continue;
                        }

                        $match = $this->matchCase($case, $source);

                        if ($match) {
                            $result->recordMatch($match->match_confidence);
                        } else {
                            $result->recordNoMatch();
                        }
                    } catch (\Exception $e) {
                        $result->recordError("Case {$case->id}: {$e->getMessage()}");
                    }
                }
            });

        return $result;
    }

    /**
     * Create a manual match
     */
    public function createManualMatch(
        CourtCase $case,
        CourtDecision $decision,
        ?string $notes = null
    ): CourtCaseDecisionMatch {
        return CourtCaseDecisionMatch::create([
            'court_case_id' => $case->id,
            'court_decision_id' => $decision->id,
            'matched_at' => now(),
            'match_type' => 'manual',
            'match_confidence' => 100,
            'match_source' => 'manual',
            'verification_status' => 'verified',
            'match_criteria' => [
                'manual' => true,
                'matched_by' => auth()->id(),
            ],
            'notes' => $notes,
        ]);
    }

    /**
     * Verify a pending match
     */
    public function verifyMatch(CourtCaseDecisionMatch $match): void
    {
        $match->update([
            'verification_status' => 'verified',
        ]);
    }

    /**
     * Reject a match with reason
     */
    public function rejectMatch(CourtCaseDecisionMatch $match, string $reason): void
    {
        $existingNotes = $match->notes ?? '';
        $newNotes = $existingNotes
            ? $existingNotes . "\n\nRejection reason: " . $reason
            : "Rejection reason: " . $reason;

        $match->update([
            'verification_status' => 'rejected',
            'notes' => $newNotes,
        ]);
    }
}
