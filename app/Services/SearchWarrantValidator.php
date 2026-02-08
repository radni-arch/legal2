<?php

namespace App\Services;

use App\Models\CourtCase;
use App\Models\CaseDocument;

/**
 * Validates and confirms search warrant cases based on document patterns
 *
 * Pp Prz cases can contain various document types. This class identifies
 * patterns that confirm a case is specifically a home search warrant
 * (naredba za pretres doma) vs other Pp Prz proceedings.
 */
class SearchWarrantValidator
{
    /**
     * Decision types that strongly indicate a search warrant
     */
    const WARRANT_DECISION_TYPES = [
        '/Zahtjev.*pretrag/iu',
        '/Nalog.*pretrag/iu',
        '/Zahtjev.*pretres/iu',
        'Naredba',
        'Nalog - pretrage',
        'Naredba za pretres',
        'Naredba za pretragu',
        'Nalog za pretres',
        'Nalog za pretragu',
        'Naredba - pretres',
        'Naredba - pretraga',
    ];

    /**
     * Document names that indicate search warrant proceedings
     */
    const WARRANT_DOCUMENT_PATTERNS = [
        '/Zahtjev.*pretrage/iu',
        '/Nalog.*pretrage/iu',
        '/naredba.*pretres/iu',
        '/nalog.*pretres/iu',
        '/nalog.*pretrag/iu',
        '/naredba.*pretrag/iu',
        '/pretres.*dom/iu',
        '/pretres.*stan/iu',
        '/pretres.*prostorij/iu',
        '/pretres.*vozil/iu',
        '/članak\s*159/iu',      // čl. 159 Prekršajnog zakona
        '/čl\.\s*159/iu',
        '/159\.\s*PZ/iu',
    ];

    /**
     * Document names that indicate it's NOT a search warrant
     * (other Pp Prz proceedings)
     */
    const NON_WARRANT_PATTERNS = [
        '/privremeno\s*oduzimanje/iu',
        '/zadržavanje/iu',
        '/uhićenje/iu',
        '/dovođenje/iu',
        '/jamčevina/iu',
        '/istražni\s*zatvor/iu',
        '/mjere?\s*opreza/iu',
        '/zabrana.*pristup/iu',
        '/zabrana.*približavanj/iu',
    ];

    /**
     * Police unit patterns that typically request search warrants
     */
    const SEARCH_WARRANT_REQUESTERS = [
        'S.O.K.O.',     // SOKO - organized crime
        'SOKO',
        'P.N.U.S.K.O.K.', // PNUSKOK
        'PNUSKOK',
        'P.U.',         // Policijska uprava
        'P.P.',         // Policijska postaja
        'K.P.',         // Kriminalistička policija
        'G.K.P.',       // Granična kriminalistička policija
    ];

    /**
     * Confidence levels
     */
    const CONFIDENCE_HIGH = 'high';
    const CONFIDENCE_MEDIUM = 'medium';
    const CONFIDENCE_LOW = 'low';
    const CONFIDENCE_UNLIKELY = 'unlikely';

    /**
     * Validate if a case is a search warrant based on all available data
     *
     * @param CourtCase $case
     * @return array{is_warrant: bool, confidence: string, reasons: array, score: int}
     */
    public function validate(CourtCase $case): array
    {
        $score = 0;
        $reasons = [];

        // Check register type (Pp Prz is mandatory)
        if (!$this->isPpPrzRegister($case)) {
            return [
                'is_warrant' => false,
                'confidence' => self::CONFIDENCE_UNLIKELY,
                'reasons' => ['Not a Pp Prz register case'],
                'score' => 0,
            ];
        }
        $score += 10;
        $reasons[] = 'Pp Prz register (+10)';

        // Check decision type
        $decisionScore = $this->scoreDecisionType($case);
        $score += $decisionScore['score'];
        if ($decisionScore['reason']) {
            $reasons[] = $decisionScore['reason'];
        }

        // Check documents
        $documentScore = $this->scoreDocuments($case);
        $score += $documentScore['score'];
        $reasons = array_merge($reasons, $documentScore['reasons']);

        // Check requester (police unit)
        $requesterScore = $this->scoreRequester($case);
        $score += $requesterScore['score'];
        if ($requesterScore['reason']) {
            $reasons[] = $requesterScore['reason'];
        }

        // Check for negative indicators
        $negativeScore = $this->scoreNegativeIndicators($case);
        $score += $negativeScore['score'];
        $reasons = array_merge($reasons, $negativeScore['reasons']);

        // Check processing time (search warrants are typically same-day)
        if ($case->is_same_day) {
            $score += 5;
            $reasons[] = 'Same-day processing (+5)';
        }

        // Determine confidence level
        $confidence = $this->determineConfidence($score);

        $hasEvidence = $this->hasExplicitSearchEvidence($case);
        if (!$hasEvidence) {
            $reasons[] = 'GATING: No explicit pretres/pretraga/čl.159 evidence';
        }

        // Kandidat (za analitiku / ručni review)
        $isCandidate = ($score >= 30);

        // Confirmed (za javnu statistiku)
        $isConfirmed = $isCandidate && $hasEvidence && ($score >= 60);

        // VAŽNO: vraćamo is_warrant kao CONFIRMED (da se applyValidation ponaša ispravno)
        return [
            'is_warrant' => $isConfirmed,
            'confidence' => $confidence,
            'reasons' => $reasons,
            'score' => $score,

            // opcionalno (ne smeta ako ga ne koristiš):
            'is_candidate' => $isCandidate,
            'has_evidence' => $hasEvidence,
        ];


//        return [
//            'is_warrant' => $score >= 30,
//            'confidence' => $confidence,
//            'reasons' => $reasons,
//            'score' => $score,
//        ];
    }

    /**
     * Check if case is in Pp Prz register
     */
    protected function isPpPrzRegister(CourtCase $case): bool
    {
        $caseNumber = $case->case_number ?? '';
        return (bool) preg_match('/Pp\s*Prz/i', $caseNumber);
    }

    /**
     * True samo ako u podacima postoji eksplicitni trag da je riječ o pretrazi/pretresu
     * (ili referenca na čl. 159). Ovo je "gating" za confirmed.
     */
    protected function hasExplicitSearchEvidence(CourtCase $case): bool
    {
        $parts = [];

        $parts[] = (string)($case->decision_type ?? '');

        foreach (($case->documents ?? collect()) as $doc) {
            $parts[] = (string)($doc->document_name ?? '');
            $parts[] = (string)($doc->document_kind ?? '');

            // Ako imaš u modelu, dobro je dodati i podnositelja:
            $parts[] = (string)($doc->submitted_by ?? $doc->podnositelj ?? '');
        }

        $haystack = mb_strtolower(implode(' ', array_filter($parts)));

        // Eksplicitno: pretraga/pretres
        if (preg_match('/pretrag|pretres/iu', $haystack)) {
            return true;
        }

        // Eksplicitno: čl. 159 / 159. PZ
        if (preg_match('/čl\.?\s*159\b|članak\s*159\b|159\.\s*pz\b/iu', $haystack)) {
            return true;
        }

        return false;
    }

    /**
     * Score based on decision type
     */
    protected function scoreDecisionType(CourtCase $case): array
    {
        $decisionType = $case->decision_type ?? '';

        foreach (self::WARRANT_DECISION_TYPES as $type) {
            if (stripos($decisionType, $type) !== false) {
                return [
                    'score' => 30,
                    'reason' => "Decision type matches '{$type}' (+30)",
                ];
            }
        }

        // Partial matches
        if (preg_match('/naredba|nalog/i', $decisionType)) {
            return [
                'score' => 15,
                'reason' => "Decision type contains order keyword (+15)",
            ];
        }

        return ['score' => 0, 'reason' => null];
    }

    /**
     * Score based on document names and content
     */
    protected function scoreDocuments(CourtCase $case): array
    {
        $score = 0;
        $reasons = [];

        $documents = $case->documents ?? collect();

        foreach ($documents as $doc) {
            $docName = $doc->document_name ?? $doc->document_kind ?? '';
            // Check positive patterns
            foreach (self::WARRANT_DOCUMENT_PATTERNS as $pattern) {
                if (preg_match($pattern, $docName)) {
                    $score += 20;
                    $reasons[] = "Document '{$docName}' matches warrant pattern (+20)";
                    break; // Only count once per document
                }
            }

            // Check for Article 159 reference (strongest indicator)
            if (preg_match('/čl\.?\s*159\b|članak\s*159\b|159\.\s*pz\b/iu', $docName)) {
                $score += 25;
                $reasons[] = "Document references Article 159 (+25)";
            }
        }

        // Cap document score at 50
        $score = min($score, 50);

        return ['score' => $score, 'reasons' => $reasons];
    }

    protected function scoreRequester(CourtCase $case): array
    {
        // 1) FIRST: iz pismena/dokumenata (često preciznije od stranki)
        foreach (($case->documents ?? collect()) as $doc) {
            $submitter = (string)($doc->submitted_by ?? $doc->podnositelj ?? '');
            if (!$submitter) continue;

            foreach (self::SEARCH_WARRANT_REQUESTERS as $pattern) {
                if (stripos($submitter, $pattern) !== false) {
                    return [
                        'score' => 15,
                        'reason' => "Requester '{$submitter}' (from documents) is typical warrant requester (+15)",
                    ];
                }
            }

            if (preg_match('/P\.P\.|PP|P\.U\.|PU|SOKO|PNUSKOK|USKOK|MUP/i', $submitter)) {
                return [
                    'score' => 10,
                    'reason' => "Requester '{$submitter}' (from documents) appears to be police/institution (+10)",
                ];
            }
        }

        // 2) FALLBACK: iz stranki (kako već imaš)
        $parties = $case->parties ?? collect();

        foreach ($parties as $party) {
            $name = $party->name ?? '';
            $role = $party->role ?? '';

            // Check if party is a requester/applicant
            if (!preg_match('/podnositelj|tužitelj|predlagatelj/i', $role)) {
                continue;
            }

            // Check against known search warrant requesters
            foreach (self::SEARCH_WARRANT_REQUESTERS as $pattern) {
                if (stripos($name, $pattern) !== false) {
                    return [
                        'score' => 15,
                        'reason' => "Requester '{$name}' is typical warrant requester (+15)",
                    ];
                }
            }

            // Generic police indicator
            if (preg_match('/polic|MUP/i', $name)) {
                return [
                    'score' => 10,
                    'reason' => "Requester appears to be police (+10)",
                ];
            }
        }

        return ['score' => 0, 'reason' => null];
    }

    /**
     * Score negative indicators (things that suggest it's NOT a search warrant)
     */
    protected function scoreNegativeIndicators(CourtCase $case): array
    {
        $score = 0;
        $reasons = [];

        $documents = $case->documents ?? collect();
        $decisionType = $case->decision_type ?? '';

        // Check documents for non-warrant patterns
        foreach ($documents as $doc) {
            $docName = $doc->document_name ?? '';

            foreach (self::NON_WARRANT_PATTERNS as $pattern) {
                if (preg_match($pattern, $docName)) {
                    $score -= 30;
                    $reasons[] = "Document '{$docName}' suggests non-warrant proceeding (-30)";
                    break;
                }
            }
        }

        // Check decision type for non-warrant indicators
        foreach (self::NON_WARRANT_PATTERNS as $pattern) {
            if (preg_match($pattern, $decisionType)) {
                $score -= 30;
                $reasons[] = "Decision type suggests non-warrant proceeding (-30)";
                break;
            }
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    /**
     * Determine confidence level based on score
     */
    protected function determineConfidence(int $score): string
    {
        if ($score >= 60) {
            return self::CONFIDENCE_HIGH;
        } elseif ($score >= 40) {
            return self::CONFIDENCE_MEDIUM;
        } elseif ($score >= 20) {
            return self::CONFIDENCE_LOW;
        }
        return self::CONFIDENCE_UNLIKELY;
    }

    /**
     * Validate multiple cases and return statistics
     *
     * @param iterable $cases
     * @return array
     */
    public function validateBatch(iterable $cases): array
    {
        $results = [
            'total' => 0,
            'confirmed_warrants' => 0,
            'high_confidence' => 0,
            'medium_confidence' => 0,
            'low_confidence' => 0,
            'unlikely' => 0,
            'by_reason' => [],
        ];

        foreach ($cases as $case) {
            $validation = $this->validate($case);
            $results['total']++;

            if ($validation['is_warrant']) {
                $results['confirmed_warrants']++;
            }

            $results[$validation['confidence'] === self::CONFIDENCE_HIGH ? 'high_confidence' :
                    ($validation['confidence'] === self::CONFIDENCE_MEDIUM ? 'medium_confidence' :
                    ($validation['confidence'] === self::CONFIDENCE_LOW ? 'low_confidence' : 'unlikely'))]++;

            foreach ($validation['reasons'] as $reason) {
                // Extract reason category
                $category = preg_replace('/\s*\([+-]\d+\)$/', '', $reason);
                $results['by_reason'][$category] = ($results['by_reason'][$category] ?? 0) + 1;
            }
        }

        return $results;
    }

    /**
     * Get warrant type classification based on documents
     *
     * @param CourtCase $case
     * @return string|null
     */
    public function classifyWarrantType(CourtCase $case): ?string
    {
        $documents = $case->documents ?? collect();
        $allText = $documents->pluck('document_name')->implode(' ');
        $allText .= ' ' . ($case->decision_type ?? '');

        if (preg_match('/stan|dom|kuć|prostorij/iu', $allText)) {
            return 'home_search';
        }

        if (preg_match('/vozil|automobil|auto/iu', $allText)) {
            return 'vehicle_search';
        }

        if (preg_match('/poslovn|ured|firma|tvrtk/iu', $allText)) {
            return 'business_search';
        }

        if (preg_match('/osob/iu', $allText)) {
            return 'person_search';
        }

        return '';
    }
}
