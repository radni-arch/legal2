<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\HrLegalCitationsDetector;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates quality checks before a document can be approved.
 * Combines completeness, citation detection, and validation scoring.
 */
class QualityGate
{
    public function __construct(
        private readonly ArgumentValidator $validator,
        private readonly HrLegalCitationsDetector $citationDetector,
    ) {}

    /**
     * Run all quality checks on a document.
     *
     * @return array{
     *     passed: bool,
     *     quality_score: float,
     *     completeness: array,
     *     citations: array,
     *     blockers: array<string>,
     *     warnings: array<string>
     * }
     */
    public function evaluate(string $content, DocumentProfile $profile, CaseContext $context): array
    {
        $blockers = [];
        $warnings = [];

        // 1. Completeness check (fast, local)
        $completeness = $this->validator->validateCompleteness($content, $profile);
        if (!$completeness['complete']) {
            $blockers[] = 'Missing sections: ' . implode(', ', $completeness['missing_sections']);
        }

        // 2. Citation detection (fast, local)
        $citations = $this->citationDetector->detectAll($content);
        $citationCount = 0;
        foreach ($citations as $type => $items) {
            $citationCount += count($items);
        }

        if ($citationCount === 0) {
            $blockers[] = 'No legal citations found in document';
        }

        // Check profile legal basis is cited
        $legalBasis = array_values(array_filter(array_merge(
            $profile->legalBasis,
            [$context->legalBasisWarrant],
        ), fn($item) => is_string($item) && trim($item) !== ''));
        $missingBasis = $this->checkLegalBasisCoverage($content, $legalBasis);
        if (!empty($missingBasis)) {
            $requireLegalBasis = config('legal-artillery.grounding.require_legal_basis', true);
            $message = 'Profile legal basis not found in text: ' . implode(', ', $missingBasis);
            if ($requireLegalBasis) {
                $blockers[] = $message;
            } else {
                $warnings[] = $message;
            }
        }

        // 2.1 Case number + key date validation
        $caseChecks = $this->checkCaseNumbersAndDates($content, $context);
        if (!empty($caseChecks['missing_case_numbers'])) {
            $blockers[] = 'Missing case references: ' . implode(', ', $caseChecks['missing_case_numbers']);
        }
        if (!empty($caseChecks['missing_dates'])) {
            $warnings[] = 'Missing key dates: ' . implode(', ', $caseChecks['missing_dates']);
        }
        if (!empty($caseChecks['missing_context'])) {
            $warnings[] = 'Missing case metadata in context: ' . implode(', ', $caseChecks['missing_context']);
        }
        if (($caseChecks['dates_found'] ?? 0) === 0) {
            $blockers[] = 'No key dates referenced in document';
        }

        // 2.2 Required core documents list (critical attachments)
        $missingCoreDocs = $this->checkRequiredCoreDocuments($content, $profile);
        if (!empty($missingCoreDocs)) {
            $message = 'Missing required core documents: ' . implode(', ', $missingCoreDocs);
            if ($profile->requiresAttachments) {
                $blockers[] = $message;
            } else {
                $warnings[] = $message;
            }
        }

        // 2.3 Formal request structure validation
        $missingStructure = $this->checkFormalRequestStructure($content, $profile, $context);
        if (!empty($missingStructure)) {
            $warnings[] = 'Formal request structure missing: ' . implode(', ', $missingStructure);
        }

        // 3. Content length check
        $wordCount = str_word_count($content);
        if ($wordCount < 100) {
            $blockers[] = "Document too short: {$wordCount} words (minimum 100)";
        }

        // 4. Calculate quality score
        $qualityScore = $this->calculateScore($completeness, $citationCount, $wordCount, count($blockers));

        // 5. Score threshold
        $minScore = config('legal-artillery.grounding.min_quality_score', 5.0);
        if ($qualityScore < $minScore) {
            $blockers[] = "Quality score {$qualityScore}/10 below minimum {$minScore}";
        }

        $passed = empty($blockers);

        Log::info('QualityGate: Evaluation complete', [
            'profile' => $profile->key,
            'passed' => $passed,
            'score' => $qualityScore,
            'citations' => $citationCount,
            'blockers' => count($blockers),
        ]);

        return [
            'passed' => $passed,
            'quality_score' => $qualityScore,
            'completeness' => $completeness,
            'citations' => [
                'total' => $citationCount,
                'by_type' => array_map(fn($items) => count($items), $citations),
                'details' => $citations,
            ],
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check which legal basis items are missing in the document.
     */
    private function checkLegalBasisCoverage(string $content, array $legalBasis): array
    {
        $missing = [];

        $contentNormalized = $this->normalizeContent($content);

        foreach ($legalBasis as $basis) {
            // Extract article number for flexible matching
            if (preg_match('/[cč]l\.?\s*(\d+)/i', $basis, $m)) {
                $articleNum = $m[1];
                // Check if article number appears in content
                if (!preg_match('/[cč]l(?:anak|anku|ankom|anka|\.)\s*' . $articleNum . '/i', $contentNormalized)) {
                    $missing[] = $basis;
                }
            } elseif (!str_contains($contentNormalized, $this->normalizeContent($basis))) {
                $missing[] = $basis;
            }
        }

        return $missing;
    }

    /**
     * Validate presence of case numbers and key dates in the document.
     *
     * @return array{missing_case_numbers: array<string>, missing_dates: array<string>, dates_found: int, missing_context: array<string>}
     */
    private function checkCaseNumbersAndDates(string $content, CaseContext $context): array
    {
        $missingCaseNumbers = [];
        $missingDates = [];
        $missingContext = [];
        $datesFound = 0;
        $contentNormalized = $this->normalizeContent($content);

        if (trim($context->caseNumber) === '') {
            $missingContext[] = 'case_number';
        } elseif (!$this->containsValue($contentNormalized, $context->caseNumber)) {
            $missingCaseNumbers[] = $context->caseNumber;
        }

        if (trim($context->criminalCaseNumber) !== '' && !$this->containsValue($contentNormalized, $context->criminalCaseNumber)) {
            $missingCaseNumbers[] = $context->criminalCaseNumber;
        }

        $keyDates = [
            'search_date' => $context->searchDate,
            'archive_date' => $context->archiveDate,
            'denial_date' => $context->denialDate,
            'county_court_response_date' => $context->countyCourtResponseDate,
        ];

        foreach ($keyDates as $label => $dateValue) {
            if (trim($dateValue) === '') {
                $missingContext[] = $label;
                continue;
            }

            if ($this->dateMatches($contentNormalized, $dateValue)) {
                $datesFound++;
                continue;
            }

            $missingDates[] = $label . ' (' . $dateValue . ')';
        }

        return [
            'missing_case_numbers' => $missingCaseNumbers,
            'missing_dates' => $missingDates,
            'dates_found' => $datesFound,
            'missing_context' => $missingContext,
        ];
    }

    /**
     * Check required core documents (critical attachments) are listed.
     */
    private function checkRequiredCoreDocuments(string $content, DocumentProfile $profile): array
    {
        $missing = [];
        $contentNormalized = $this->normalizeContent($content);
        $documents = config('legal-artillery-documents.documents', []);

        foreach ($documents as $document) {
            $profiles = $document['profiles'] ?? [];
            $isRelevant = in_array('all', $profiles, true) || in_array($profile->key, $profiles, true);
            if (!($document['critical'] ?? false) || !$isRelevant) {
                continue;
            }

            $filename = $document['filename'] ?? null;
            $descriptionHr = $document['description_hr'] ?? null;
            $descriptionEn = $document['description_en'] ?? null;

            $matches = false;
            foreach ([$filename, $descriptionHr, $descriptionEn] as $candidate) {
                if ($candidate && $this->containsValue($contentNormalized, $candidate)) {
                    $matches = true;
                    break;
                }
            }

            if (!$matches) {
                $missing[] = $descriptionHr ?: ($filename ?: ($document['id'] ?? 'unknown'));
            }
        }

        return $missing;
    }

    /**
     * Validate that the document follows a formal request structure.
     */
    private function checkFormalRequestStructure(string $content, DocumentProfile $profile, CaseContext $context): array
    {
        $missing = [];
        $contentNormalized = $this->normalizeContent($content);

        $recipientTitle = $profile->recipient['title'] ?? '';
        $recipientInstitution = $profile->recipient['institution'] ?? '';
        $recipientMatched = false;

        foreach ([$recipientTitle, $recipientInstitution] as $recipient) {
            if ($recipient && $this->containsValue($contentNormalized, $recipient)) {
                $recipientMatched = true;
                break;
            }
        }

        if (!$recipientMatched) {
            $missing[] = 'recipient heading';
        }

        if (!preg_match('/\b(predmet|subject)\b/i', $contentNormalized)) {
            $missing[] = 'subject line';
        }

        if (!preg_match('/\b(zahtjev|zahtijev|prijedlog|molim|trazim|zahtijevam)\b/i', $contentNormalized)) {
            $missing[] = 'request statement';
        }

        $senderName = $context->sender->name ?? '';
        $signatureMatched = preg_match('/\b(s postovanjem|postovanjem|respectfully)\b/i', $contentNormalized) === 1
            || ($senderName && $this->containsValue($contentNormalized, $senderName));

        if (!$signatureMatched) {
            $missing[] = 'signature block';
        }

        return $missing;
    }

    private function normalizeContent(string $content): string
    {
        return Str::ascii(mb_strtolower($content));
    }

    private function containsValue(string $contentNormalized, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return str_contains($contentNormalized, $this->normalizeContent($value));
    }

    private function dateMatches(string $contentNormalized, string $dateValue): bool
    {
        $dateValue = trim($dateValue);
        if ($dateValue === '') {
            return false;
        }

        if (str_contains($contentNormalized, $this->normalizeContent($dateValue))) {
            return true;
        }

        $date = Carbon::parse($dateValue);
        $formats = [
            'Y-m-d',
            'd.m.Y',
            'd.m.Y.',
            'd. m. Y.',
            'd. m. Y',
            'd.m. Y.',
        ];

        foreach ($formats as $format) {
            $formatted = $date->format($format);
            if (str_contains($contentNormalized, $this->normalizeContent($formatted))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate composite quality score (0-10).
     */
    private function calculateScore(array $completeness, int $citationCount, int $wordCount, int $blockerCount): float
    {
        $score = 0.0;

        // Completeness contributes 3 points
        $score += ($completeness['completeness_score'] / 100) * 3;

        // Citations contribute 3 points (capped at 5+ citations = full score)
        $score += min($citationCount / 5, 1.0) * 3;

        // Content length contributes 2 points (capped at 500+ words = full)
        $score += min($wordCount / 500, 1.0) * 2;

        // No blockers bonus: 2 points
        if ($blockerCount === 0) {
            $score += 2;
        }

        return round(min($score, 10.0), 1);
    }
}
