<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\LegalArtillery\ArgumentChain;
use App\DTOs\LegalArtillery\ChainValidationResult;
use App\DTOs\LegalArtillery\ValidationResult;
use App\Models\LegalProvision;
use App\Services\HrLegalCitationsDetector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Validates generated legal documents using LLM.
 *
 * Evaluates citation accuracy, argument strength, logical coherence,
 * and returns a score with improvement suggestions.
 *
 * Verdicts: WEAK, MODERATE, STRONG, DEVASTATING, FIRE_READY
 * Score threshold for FIRE_READY: 8+
 */
class ArgumentValidator
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly DevastatingArgumentBuilder $argumentBuilder,
    ) {}

    // =========================================================================
    // Task 14 Methods - DocumentProfile + CaseContext based validation
    // =========================================================================

    /**
     * Validate a generated document using DocumentProfile and CaseContext.
     *
     * @param string $content The document content to validate
     * @param DocumentProfile $profile The document profile configuration
     * @param CaseContext $context The case context data
     * @return ValidationResult Validation result with scores, issues, and suggestions
     */
    public function validateDocument(string $content, DocumentProfile $profile, CaseContext $context): ValidationResult
    {
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildProfileValidationPrompt($content, $profile, $context);

        Log::info('ArgumentValidator: Starting document validation', [
            'profile' => $profile->key,
            'content_length' => strlen($content),
        ]);

        $response = $this->llm->generate($systemPrompt, $userPrompt, 2048);

        return $this->parseToValidationResult($response);
    }

    /**
     * Get the validation prompt template for a given profile.
     *
     * @param DocumentProfile $profile The document profile
     * @return string The validation prompt template
     */
    public function getValidationPrompt(DocumentProfile $profile): string
    {
        $legalBasisList = implode("\n", array_map(fn($b) => "- {$b}", $profile->legalBasis));
        $structureList = implode("\n", array_map(fn($s) => "- {$s}", $this->getRequiredSectionLabels($profile)));

        return <<<PROMPT
## PROVJERA DOKUMENTA: {$profile->name}

### 1. TOCNOST CITATA
Provjerite svaku pravnu odredbu:
- Je li clanak, stavak i tocka ispravno navedena?
- Je li naziv zakona tocan?
- Pravni temelji profila:
{$legalBasisList}

### 2. SNAGA ARGUMENATA
Ocijenite snagu svakog argumenta:
- Je li argument logicki utemeljen?
- Ima li podrsku u pravnoj odredbi ili presudi?
- Jesu li argumenti uvjerljivi za ovaj tip dokumenta?

### 3. LOGICKA KOHERENCIJA
Provjerite logiku dokumenta:
- Jesu li zakljucci izvedeni iz premisa?
- Postoji li logicka rupa koju protivnik moze iskoristiti?
- Je li redoslijed argumenata smislen?

### 4. TON I STIL
Usporedite ton s profilom "{$profile->tone}":
- Je li ton konzistentan kroz cijeli dokument?
- Ima li neprimjerenih emocionalnih izljeva?
- Je li stil odgovarajuci za primatelja?

### 5. PROVJERA CINJENICA
Provjerite da dokument NE sadrzi:
- Izmisljene pravne odredbe koje ne postoje
- Nepostojece presude ili fabricirane reference
- Lazne ili fabricirane cinjenice

### 6. KONKRETNOST ZAHTJEVA
Provjerite zahtjeve u dokumentu:
- Jesu li zahtjevi konkretni i specificni?
- Jesu li mjerljivi (mogu se provjeriti)?
- Jesu li provedivi?

### OCEKIVANE SEKCIJE
{$structureList}

### FORMAT ODGOVORA
Vrati JSON:
{
    "overall_score": <1-10>,
    "citation_accuracy": <1-10>,
    "argument_strength": <1-10>,
    "logical_coherence": <1-10>,
    "verdict": "<WEAK|MODERATE|STRONG|DEVASTATING|FIRE_READY>",
    "issues": [
        {"severity": "<minor|major|critical>", "description": "Opis problema"}
    ],
    "improvements": ["Prijedlog 1", "Prijedlog 2"],
    "strengths": ["Snaga 1", "Snaga 2"]
}

OCJENE:
- WEAK (1-3) = dokument ima logicke rupe, nedostaje praksa
- MODERATE (4-5) = solidno, ali nedovoljno precizno
- STRONG (6-7) = svi citati tocni, argumenti logicni
- DEVASTATING (8) = svaki argument vodi u nokaut
- FIRE_READY (9-10) = DEVASTATING + stilski besprijekoran
PROMPT;
    }

    // =========================================================================
    // Completeness Validation - Fast, local checks (no LLM)
    // =========================================================================

    /**
     * Validate that all required sections from the profile structure are present.
     * This is a fast, local check (no LLM call).
     *
     * @param string $content The document content
     * @param DocumentProfile $profile The document profile
     * @return array{complete: bool, missing_sections: array<string>, present_sections: array<string>, completeness_score: float}
     */
    public function validateCompleteness(string $content, DocumentProfile $profile): array
    {
        $requiredSections = $this->getRequiredSectionKeys($profile);
        $contentLower = mb_strtolower($content);

        $present = [];
        $missing = [];

        // Map section keys to expected heading patterns (Croatian + English for ECHR)
        $sectionPatterns = [
            'heading' => ['zaglavlje', 'naslov', 'predmet'],
            'heading_constitutional' => ['ustavna tuzba', 'ustavni sud', 'zaglavlje'],
            'identification' => ['podnositelj', 'identifikacija', 'osobni podatci', 'oib'],
            'facts' => ['cinjenice', 'cinjenicno stanje', 'opis dogadaja'],
            'facts_chronology' => ['cinjenice', 'cinjenicno stanje', 'kronologija cinjenica', 'kronologija'],
            'legal_arguments' => ['pravni argumenti', 'pravna osnova', 'pravno obrazlozenje'],
            'requests' => ['zahtjev', 'prijedlog', 'zahtjevi', 'molimo'],
            'signature' => ['potpis', 'podnositelj zahtjeva', 's postovanjem'],
            'legal_basis' => ['pravni temelj', 'pravna osnova', 'zakonska osnova'],
            'timeline' => ['kronologija', 'vremenski slijed', 'kronoloski pregled'],
            'evidence' => ['dokazi', 'dokazni prijedlozi', 'prilozi'],
            'conclusion' => ['zakljucak', 'zaključak'],
            'case_reference' => ['broj predmeta', 'poslovni broj', 'spis', 'predmet broj'],
            'legal_remedy_demand' => ['pravni lijek', 'pravo na zalbu', 'uputa o pravnom lijeku'],
            'connection_criminal_misdemeanor' => ['kazneni postupak', 'prekrsajni postupak', 'veza kaznenog'],
            'disclosure_demand' => ['zahtjev za dostavu', 'zahtjev za uvid', 'pribavljanje spisa'],
            'consequences_warning' => ['upozorenje', 'posljedice', 'pravne posljedice'],
            'motion_context' => ['kontekst prijedloga', 'obrazlozenje prijedloga', 'razlozi prijedloga'],
            'evidence_connection' => ['veza s dokazima', 'dokazna veza', 'povezanost dokaza'],
            'specific_requests' => ['konkretni zahtjevi', 'specificni zahtjevi', 'zahtjev'],
            'narrative_chronology' => ['kronologija', 'kronoloski prikaz', 'tijek dogadaja'],
            'rights_violations' => ['povrede prava', 'krsenje prava', 'ugrozenost prava'],
            'specific_complaint' => ['konkretna prituzba', 'specificna prituzba', 'predmet prituzbe'],
            'requested_action' => ['trazena radnja', 'zahtijevana radnja', 'zahtjev za postupanje'],
            'attachments_list' => ['prilozi', 'popratna dokumentacija', 'prilog'],
            'subject_complaint' => ['predmet prituzbe', 'predmet prigovora', 'predmet'],
            'administrative_irregularities' => ['nepravilnosti', 'administrativne nepravilnosti', 'upravne nepravilnosti'],
            'requested_measures' => ['trazene mjere', 'zahtijevane mjere', 'prijedlog mjera'],
            'challenged_acts' => ['pobijani akti', 'osporavane odluke', 'osporavani akti'],
            'constitutional_provisions_violated' => ['povrijedjene ustavne odredbe', 'ustavne povrede', 'povrede ustava'],
            'factual_background' => ['cinjenicno stanje', 'cinjenice', 'cinjenicna pozadina'],
            'constitutional_arguments' => ['ustavnopravni argumenti', 'ustavni argumenti', 'ustavna argumentacija'],
            'article_62_justification' => ['clanak 62', 'cl.62', 'obrazlozenje cl.62'],
            'proposed_measures' => ['predlozene mjere', 'prijedlog mjera', 'trazene mjere'],
            'evidence_identification' => ['identifikacija dokaza', 'oznaka dokaza', 'opis dokaza'],
            'exclusion_grounds' => ['razlozi iskljucenja', 'razlozi izdvajanja', 'osnova za iskljucenje'],
            'defense_rights_violation' => ['povreda prava obrane', 'krsenje prava obrane', 'prava obrane'],
            'constitutional_dimension' => ['ustavna dimenzija', 'ustavnopravni aspekt', 'ustavna razina'],
            'specific_request' => ['konkretni zahtjev', 'specificni zahtjev', 'zahtjev'],
            // ECHR sections (English)
            'echr_header' => ['european court', 'echr', 'application'],
            'applicant_details' => ['applicant', 'applicant details', 'personal details'],
            'respondent_state' => ['respondent', 'respondent state', 'republic of croatia'],
            'statement_of_facts' => ['statement of facts', 'facts', 'factual background'],
            'domestic_proceedings' => ['domestic proceedings', 'domestic remedies', 'national proceedings'],
            'alleged_violations' => ['alleged violations', 'violations', 'breaches'],
            'article_6_arguments' => ['article 6', 'fair trial', 'right to a fair trial'],
            'article_8_arguments' => ['article 8', 'private life', 'respect for home'],
            'article_13_arguments' => ['article 13', 'effective remedy'],
            'exhaustion_of_remedies' => ['exhaustion', 'domestic remedies exhausted'],
            'timeliness' => ['timeliness', 'time limit', 'within time'],
            'relief_sought' => ['relief sought', 'relief', 'just satisfaction'],
            'declaration' => ['declaration', 'hereby declare', 'solemnly declare'],
            'annexes' => ['annexes', 'appendices', 'attachments'],
        ];

        foreach ($requiredSections as $section) {
            $sectionKey = is_string($section) ? $section : ($section['key'] ?? '');
            $patterns = $sectionPatterns[$sectionKey] ?? [$sectionKey];

            $found = false;
            foreach ($patterns as $pattern) {
                if (mb_strpos($contentLower, mb_strtolower($pattern)) !== false) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                $present[] = $sectionKey;
            } else {
                $missing[] = $sectionKey;
            }
        }

        $total = count($requiredSections);
        $score = $total > 0 ? (count($present) / $total) * 100 : 100;

        return [
            'complete' => empty($missing),
            'missing_sections' => $missing,
            'present_sections' => $present,
            'completeness_score' => round($score, 1),
        ];
    }

    /**
     * Build a source map from assembled context for validation.
     *
     * Extracts required attachments and optional evidence references from the
     * enhanced context and merges with any explicitly provided source_map data.
     *
     * @param array $assembledContext
     * @return array<int, array{label: string, required: bool, type: string, aliases?: array<int, string>}>
     */
    public function buildSourceMap(array $assembledContext): array
    {
        $sourceMap = [];
        $seen = [];

        $addSource = function (string $label, bool $required, string $type, array $aliases = []) use (&$sourceMap, &$seen): void {
            $key = mb_strtolower(trim($label));
            if ($key === '') {
                return;
            }

            if (isset($seen[$key])) {
                $index = $seen[$key];
                $sourceMap[$index]['required'] = $sourceMap[$index]['required'] || $required;
                $sourceMap[$index]['aliases'] = array_values(array_unique(array_merge(
                    $sourceMap[$index]['aliases'] ?? [],
                    $aliases
                )));
                return;
            }

            $sourceMap[] = [
                'label' => $label,
                'required' => $required,
                'type' => $type,
                'aliases' => $aliases,
            ];
            $seen[$key] = count($sourceMap) - 1;
        };

        $customMap = $assembledContext['source_map'] ?? [];
        if (is_array($customMap)) {
            foreach ($customMap as $entry) {
                if (is_string($entry)) {
                    $addSource($entry, true, 'custom');
                    continue;
                }

                if (!is_array($entry)) {
                    continue;
                }

                $label = $entry['label'] ?? $entry['title'] ?? $entry['name'] ?? null;
                if (!$label || !is_string($label)) {
                    continue;
                }
                $aliases = isset($entry['aliases']) && is_array($entry['aliases']) ? $entry['aliases'] : [];
                $required = (bool) ($entry['required'] ?? true);
                $type = $entry['type'] ?? 'custom';
                $addSource($label, $required, $type, $aliases);
            }
        }

        $enhanced = $assembledContext['enhanced_context'] ?? [];
        $attachments = $enhanced['attachments_list'] ?? [];
        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (is_string($attachment) && $attachment !== '') {
                    $addSource($attachment, true, 'attachment');
                }
            }
        }

        $evidence = $enhanced['evidence'] ?? [];
        if (is_array($evidence)) {
            foreach ($evidence as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $label = $item['title'] ?? $item['description'] ?? null;
                if ($label && is_string($label)) {
                    $addSource($label, false, 'evidence');
                }
            }
        }

        return $sourceMap;
    }

    /**
     * Validate that required sources are mentioned in the draft.
     *
     * @param string $content The draft content to validate
     * @param array<int, array{label: string, required: bool, type?: string, aliases?: array<int, string>}> $sourceMap
     * @return array{
     *     complete: bool,
     *     required_sources: array<int, string>,
     *     mentioned_sources: array<int, string>,
     *     missing_sources: array<int, string>,
     *     missing_optional_sources: array<int, string>,
     *     coverage_score: float
     * }
     */
    public function validateSourceMap(string $content, array $sourceMap): array
    {
        $contentLower = mb_strtolower($content);
        $requiredSources = [];
        $mentionedSources = [];
        $missingSources = [];
        $missingOptionalSources = [];

        foreach ($sourceMap as $source) {
            if (!is_array($source) || empty($source['label']) || !is_string($source['label'])) {
                continue;
            }

            $label = $source['label'];
            $required = (bool) ($source['required'] ?? true);
            $aliases = isset($source['aliases']) && is_array($source['aliases']) ? $source['aliases'] : [];
            $terms = array_merge([$label], $aliases);

            if ($required) {
                $requiredSources[] = $label;
            }

            $found = false;
            foreach ($terms as $term) {
                if (!is_string($term) || $term === '') {
                    continue;
                }
                if (mb_stripos($contentLower, mb_strtolower($term)) !== false) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                $mentionedSources[] = $label;
            } elseif ($required) {
                $missingSources[] = $label;
            } else {
                $missingOptionalSources[] = $label;
            }
        }

        $requiredCount = count($requiredSources);
        $coverageScore = $requiredCount > 0
            ? round((($requiredCount - count($missingSources)) / $requiredCount) * 100, 1)
            : 100.0;

        return [
            'complete' => empty($missingSources),
            'required_sources' => array_values(array_unique($requiredSources)),
            'mentioned_sources' => array_values(array_unique($mentionedSources)),
            'missing_sources' => array_values(array_unique($missingSources)),
            'missing_optional_sources' => array_values(array_unique($missingOptionalSources)),
            'coverage_score' => $coverageScore,
        ]; 
    }   
   
    /**
     * Resolve required sections using profile overrides when provided.
     *
     * @param DocumentProfile $profile The document profile
     * @return array The required sections list
     */
    private function getRequiredSectionKeys(DocumentProfile $profile): array
    {
        $requiredSections = !empty($profile->requiredSections)
            ? $profile->requiredSections
            : $profile->structure;

        $keys = [];
        foreach ($requiredSections as $section) {
            if (is_string($section) && $section !== '') {
                $keys[] = $section;
                continue;
            }

            if (!is_array($section)) {
                continue;
            }

            $key = $section['key'] ?? $section['id'] ?? null;
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Resolve required section labels for display purposes.
     *
     * @param DocumentProfile $profile The document profile
     * @return array<int, string>
     */
    private function getRequiredSectionLabels(DocumentProfile $profile): array
    {
        $requiredSections = !empty($profile->requiredSections)
            ? $profile->requiredSections
            : $profile->structure;

        $labels = [];
        foreach ($requiredSections as $section) {
            if (is_string($section) && $section !== '') {
                $labels[] = $section;
                continue;
            }

            if (!is_array($section)) {
                continue;
            }

            $label = $section['label'] ?? $section['name'] ?? $section['title'] ?? null;
            if (is_string($label) && $label !== '') {
                $labels[] = $label;
                continue;
            }

            $key = $section['key'] ?? $section['id'] ?? null;
            if (is_string($key) && $key !== '') {
                $labels[] = $key;
            }
        }

        return $labels;
    }

    /**
     * Check if a document is FIRE_READY (complete + high quality).
     * Returns true only if completeness passes AND quality score >= 8.
     *
     * @param string $content The document content
     * @param DocumentProfile $profile The document profile
     * @param CaseContext $context The case context
     * @return array{fire_ready: bool, completeness: array, quality: ?ValidationResult, blockers: array<string>}
     */
    public function isFireReady(string $content, DocumentProfile $profile, CaseContext $context): array
    {
        $blockers = [];

        // Check completeness first (fast, no LLM)
        $completeness = $this->validateCompleteness($content, $profile);
        if (!$completeness['complete']) {
            $blockers[] = 'Missing sections: ' . implode(', ', $completeness['missing_sections']);
        }

        // Only run LLM validation if completeness passes (save API costs)
        $quality = null;
        if ($completeness['complete']) {
            $quality = $this->validateDocument($content, $profile, $context);
            if ($quality->score < 8) {
                $blockers[] = "Quality score too low: {$quality->score}/10 (need 8+)";
            }
            if ($quality->verdict !== 'FIRE_READY' && $quality->verdict !== 'DEVASTATING') {
                $blockers[] = "Verdict not ready: {$quality->verdict} (need DEVASTATING or FIRE_READY)";
            }
        }

        return [
            'fire_ready' => empty($blockers),
            'completeness' => $completeness,
            'quality' => $quality,
            'blockers' => $blockers,
        ];
    }

    // =========================================================================
    // Local Citation Validation (no LLM) - Sprint 2D
    // =========================================================================

    /**
     * Validate legal citations in content against the database.
     *
     * Uses HrLegalCitationsDetector to find all legal citations locally,
     * then checks each detected statute against the legal_provisions table.
     *
     * @param string $content The document content to validate
     * @return array{
     *     detected_citations: array,
     *     verified: array,
     *     unverified: array,
     *     coverage_score: float
     * }
     */
    public function validateCitations(string $content): array
    {
        if (empty(trim($content))) {
            return [
                'detected_citations' => [],
                'verified' => [],
                'unverified' => [],
                'coverage_score' => 100,
            ];
        }

        $detector = new HrLegalCitationsDetector();
        $detected = $detector->detectAll($content);

        // Extract statute citations to verify against DB
        $statutes = $detected['statutes'] ?? [];

        if (empty($statutes)) {
            return [
                'detected_citations' => [],
                'verified' => [],
                'unverified' => [],
                'coverage_score' => 100,
            ];
        }

        $verified = [];
        $unverified = [];

        foreach ($statutes as $statute) {
            $lawShort = $statute['law'] ?? null;
            $article = $statute['article'] ?? null;

            if (!$lawShort || !$article) {
                continue;
            }

            $query = LegalProvision::where('law_short', $lawShort)
                ->where('article', $article);

            if (!empty($statute['paragraph'])) {
                $query->where('paragraph', $statute['paragraph']);
            }

            $provision = $query->first();

            $citationEntry = [
                'law' => $lawShort,
                'article' => $article,
                'paragraph' => $statute['paragraph'] ?? null,
                'canonical' => $statute['canonical'] ?? "{$lawShort} cl.{$article}",
            ];

            if ($provision) {
                $citationEntry['provision_id'] = $provision->id;
                $citationEntry['law_name'] = $provision->law_name;
                $verified[] = $citationEntry;
            } else {
                $unverified[] = $citationEntry;
            }
        }

        $total = count($verified) + count($unverified);
        $coverageScore = $total > 0 ? round((count($verified) / $total) * 100, 1) : 100;

        return [
            'detected_citations' => array_merge($verified, $unverified),
            'verified' => $verified,
            'unverified' => $unverified,
            'coverage_score' => $coverageScore,
        ];
    }

    // =========================================================================
    // Legacy Methods - String profileKey based validation
    // =========================================================================

    /**
     * Validate a generated document (legacy method).
     *
     * @param string $content The document content to validate
     * @param string $profileKey The document profile key (e.g., 'predsjednik_suda')
     * @return array{
     *     overall_score: int,
     *     citation_accuracy: int,
     *     argument_strength: int,
     *     logical_coherence: int,
     *     verdict: string,
     *     issues: array<array{severity: string, description: string}>,
     *     improvements: array<string>,
     *     chain_evaluation: array{
     *         logical_continuity: int,
     *         knockout_inevitability: int,
     *         vulnerabilities: array<string>
     *     }
     * }
     */
    public function validate(string $content, string $profileKey): array
    {
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildValidationPrompt($content, $profileKey);

        $response = $this->llm->generate($systemPrompt, $userPrompt, 2048);

        return $this->parseValidationResponse($response);
    }

    /**
     * Validate an argument chain for logical coherence and strength.
     *
     * @param ArgumentChain $chain The argument chain to validate
     * @return ChainValidationResult Validation result with scores and analysis
     */
    public function validateArgumentChain(ArgumentChain $chain): ChainValidationResult
    {
        $systemPrompt = $this->buildChainValidationSystemPrompt();
        $userPrompt = $this->buildChainValidationPrompt($chain);

        $response = $this->llm->generate($systemPrompt, $userPrompt, 2048);

        return $this->parseChainValidationResponse($response);
    }

    /**
     * Check citation accuracy against known legal provisions.
     *
     * @param string $content The document content to check
     * @param Collection $provisions Collection of LegalProvision objects
     * @return array{citations_found: array, accuracy_score: int, issues: array<string>}
     */
    public function checkCitationAccuracy(string $content, Collection $provisions): array
    {
        if (empty(trim($content))) {
            return [
                'citations_found' => [],
                'accuracy_score' => 100,
                'issues' => [],
            ];
        }

        $systemPrompt = $this->buildCitationCheckSystemPrompt();
        $userPrompt = $this->buildCitationCheckPrompt($content, $provisions);

        $response = $this->llm->generate($systemPrompt, $userPrompt, 2048);

        return $this->parseCitationCheckResponse($response);
    }

    /**
     * Check precedent usage against known legal precedents.
     *
     * @param string $content The document content to check
     * @param Collection $precedents Collection of LegalPrecedent objects
     * @return array{precedents_found: array, usage_score: int, issues: array<string>}
     */
    public function checkPrecedentUsage(string $content, Collection $precedents): array
    {
        if (empty(trim($content))) {
            return [
                'precedents_found' => [],
                'usage_score' => 100,
                'issues' => [],
            ];
        }

        $systemPrompt = $this->buildPrecedentCheckSystemPrompt();
        $userPrompt = $this->buildPrecedentCheckPrompt($content, $precedents);

        $response = $this->llm->generate($systemPrompt, $userPrompt, 2048);

        return $this->parsePrecedentCheckResponse($response);
    }

    /**
     * Assess the "devastation level" of a document.
     *
     * @param string $content The document content to assess
     * @return int Devastation level from 1 (weak) to 10 (devastating)
     */
    public function assessDevastationLevel(string $content): int
    {
        $systemPrompt = $this->buildDevastationAssessmentSystemPrompt();
        $userPrompt = $this->buildDevastationAssessmentPrompt($content);

        $response = $this->llm->generate($systemPrompt, $userPrompt, 1024);

        return $this->parseDevastationAssessmentResponse($response);
    }

    // =========================================================================
    // System Prompts
    // =========================================================================

    private function buildSystemPrompt(): string
    {
        return <<<SYSTEM
Ti si strogi pravni recenzent specijaliziran za hrvatski pravni sustav.

Tvoj zadatak je evaluirati pravne dokumente po sljedecim kriterijima:
1. TOCNOST CITATA - Je li svaka pravna odredba citirana tocno?
2. TOCNOST PRESUDA - Je li svaka presuda ispravno navedena?
3. SNAGA ARGUMENATA - Jesu li argumenti logicki jaki i uvjerljivi?
4. LOGICKA KOHERENCIJA - Je li dokument logicki konzistentan?
5. PROVJERA FABRICIRANIH CINJENICA - Ne smije biti izmisljenih odredbi ili presuda
6. KONKRETNOST ZAHTJEVA - Zahtjevi moraju biti specificni i mjerljivi

Ocjene:
- WEAK = dokument ima logicke rupe, nedostaje praksa
- MODERATE = solidno, ali nedovoljno precizno
- STRONG = svi citati tocni, argumenti logicni
- DEVASTATING = svaki argument vodi u nokaut
- FIRE_READY = DEVASTATING + stilski besprijekoran

Odgovori ISKLJUCIVO u JSON formatu.
SYSTEM;
    }

    private function buildChainValidationSystemPrompt(): string
    {
        return <<<SYSTEM
Ti si logicni analiticar specijaliziran za evaluaciju pravnih argumenata.

Tvoj zadatak je analizirati lanac argumenata i odrediti:
1. Je li svaki korak logicki slijedi iz prethodnog?
2. Je li zakljucak neizbjezan iz premisa?
3. Postoji li rupa koju protivnik moze iskoristiti?
4. Koliko je jak svaki pojedinacni argument?

Ocijeni lanac na skali 0-100 gdje:
- 0-30: WEAK - logicke rupe, protivnik lako odgovara
- 31-50: MODERATE - solidno, ali nedovoljno precizno
- 51-70: STRONG - argumenti logicni, tesko pobijivo
- 71-85: DEVASTATING - nokaut neizbjezan
- 86-100: FIRE_READY - savrseno

Odgovori ISKLJUCIVO u JSON formatu.
SYSTEM;
    }

    private function buildCitationCheckSystemPrompt(): string
    {
        return <<<SYSTEM
Ti si pravni revizor specijaliziran za provjeru tocnosti pravnih citata.

Tvoj zadatak je:
1. Identificirati sve pravne citate u tekstu (clanak, stavak, zakon)
2. Provjeriti postoje li u danoj bazi odredbi
3. Prijaviti netocne ili nepostojece citate

Odgovori ISKLJUCIVO u JSON formatu.
SYSTEM;
    }

    private function buildPrecedentCheckSystemPrompt(): string
    {
        return <<<SYSTEM
Ti si pravni revizor specijaliziran za provjeru sudske prakse.

Tvoj zadatak je:
1. Identificirati sve reference na sudske presude u tekstu
2. Provjeriti postoje li u danoj bazi presuda
3. Prijaviti netocne ili nepostojece reference
4. Ocijeniti je li presuda ispravno primijenjena u kontekstu

Odgovori ISKLJUCIVO u JSON formatu.
SYSTEM;
    }

    private function buildDevastationAssessmentSystemPrompt(): string
    {
        return <<<SYSTEM
Ti si strateski pravni savjetnik specijaliziran za procjenu snage pravnih dokumenata.

Tvoj zadatak je ocijeniti "devastirajuci potencijal" dokumenta - koliko je tesko
protivniku odgovoriti na argumente. Ocijeni na skali 1-10:

1-3: WEAK - protivnik lako pobija, ima ocigljedne rupe
4-6: MODERATE - solidno, ali postoje slabosti
7-8: STRONG - tesko pobijivo, dobra pravna osnova
9-10: DEVASTATING - protivnik nema odgovor, nokaut neizbjezan

Faktori za procjenu:
- legal_foundation: Koliko su jaki pravni temelji?
- logical_chain: Je li logicki lanac neoboriv?
- precedent_support: Podrzava li sudska praksa argumente?
- opponent_rebuttal_difficulty: Koliko je tesko protivniku odgovoriti?

Odgovori ISKLJUCIVO u JSON formatu.
SYSTEM;
    }

    // =========================================================================
    // User Prompts
    // =========================================================================

    private function buildProfileValidationPrompt(string $content, DocumentProfile $profile, CaseContext $context): string
    {
        $validationPrompt = $this->getValidationPrompt($profile);
        $contextVars = $context->toTemplateVars();

        return <<<PROMPT
{$validationPrompt}

## KONTEKST PREDMETA
- Broj predmeta: {$contextVars['case_number']}
- Datum pretrage: {$contextVars['search_date']}
- Sudac: {$contextVars['judge']}
- Podnositelj: {$contextVars['sender_name']}

## DOKUMENT ZA PREGLED:

{$content}
PROMPT;
    }

    private function buildValidationPrompt(string $content, string $profileKey): string
    {
        $chains = $this->argumentBuilder->getChainsForProfile($profileKey);
        $chainsSummary = $this->formatChainsForValidation($chains);

        return <<<PROMPT
Pregledaj ovaj pravni dokument tipa: {$profileKey}

## DOKUMENT ZA PREGLED:

{$content}

## ARGUMENTACIJSKI LANCI ZA PROFIL
{$chainsSummary}

## ZADATAK:

Ocijeni dokument i vrati JSON:
{
    "overall_score": <1-10>,
    "citation_accuracy": <1-10>,
    "argument_strength": <1-10>,
    "logical_coherence": <1-10>,
    "chain_evaluation": {
        "logical_continuity": <1-10>,
        "knockout_inevitability": <1-10>,
        "vulnerabilities": [
            "Opis ranjivosti ili logicke rupe"
        ]
    },
    "verdict": "<WEAK|MODERATE|STRONG|DEVASTATING|FIRE_READY>",
    "issues": [
        {"severity": "<minor|major|critical>", "description": "Opis problema"}
    ],
    "improvements": [
        "Konkretni prijedlog poboljsanja 1",
        "Konkretni prijedlog poboljsanja 2"
    ],
    "strengths": [
        "Snaga dokumenta 1",
        "Snaga dokumenta 2"
    ]
}

Posebno provjeri:
- Logicku kontinuitet svakog argumentacijskog lanca.
- Je li "nokaut" (zakljucak) neizbjezan iz premisa.
- Identificiraj ranjivosti koje protivnik moze iskoristiti.
PROMPT;
    }

    private function buildChainValidationPrompt(ArgumentChain $chain): string
    {
        $argumentsText = '';
        foreach ($chain->arguments as $index => $argument) {
            $argumentsText .= "\n### Argument {$index}:\n";
            $argumentsText .= "Tip: {$argument->type}\n";
            $argumentsText .= "Tekst: {$argument->text}\n";
            $argumentsText .= "Odredbe: " . implode(', ', $argument->provisions) . "\n";
            $argumentsText .= "Presude: " . implode(', ', $argument->precedents) . "\n";
            $argumentsText .= "Snaga: {$argument->strength}/10\n";
        }

        $vulnerabilities = implode("\n- ", $chain->vulnerabilities);
        $recommendations = implode("\n- ", $chain->recommendations);

        return <<<PROMPT
Analiziraj sljedeci lanac argumenata:

## LANAC ARGUMENATA
Ukupna snaga: {$chain->combinedStrength}/10
Broj argumenata: {$chain->count()}

{$argumentsText}

## POZNATE RANJIVOSTI
- {$vulnerabilities}

## PREPORUKE
- {$recommendations}

## ZADATAK

Ocijeni lanac i vrati JSON:
{
    "chain_score": <0-100>,
    "argument_scores": [
        {"step": <broj>, "score": <0-100>, "reasoning": "Objasnjenje"}
    ],
    "weak_links": [
        {"step": <broj>, "issue": "Opis problema"}
    ],
    "improvement_priority": [
        "Najvaznija poboljsanja po prioritetu"
    ]
}
PROMPT;
    }

    /**
     * Format argument chains for validation prompts.
     *
     * @param array<ArgumentChain> $chains
     */
    private function formatChainsForValidation(array $chains): string
    {
        if (empty($chains)) {
            return 'Nema dostupnih lanaca za ovaj profil.';
        }

        $sections = [];
        foreach ($chains as $chainIndex => $chain) {
            $sections[] = "### Lanac " . ($chainIndex + 1);
            $sections[] = "Ukupna snaga: {$chain->combinedStrength}/10";
            $sections[] = "Broj argumenata: {$chain->count()}";

            foreach ($chain->arguments as $index => $argument) {
                $step = $index + 1;
                $sections[] = "- Korak {$step}: {$argument->type} (snaga {$argument->strength}/10)";
                $sections[] = "  Tekst: {$argument->text}";
                $sections[] = "  Odredbe: " . implode(', ', $argument->provisions);
                $sections[] = "  Presude: " . implode(', ', $argument->precedents);
            }

            if (!empty($chain->vulnerabilities)) {
                $sections[] = "Ranjivosti: " . implode('; ', $chain->vulnerabilities);
            }

            if (!empty($chain->recommendations)) {
                $sections[] = "Preporuke: " . implode('; ', $chain->recommendations);
            }
        }

        return implode("\n", $sections);
    }

    private function buildCitationCheckPrompt(string $content, Collection $provisions): string
    {
        $provisionsText = $provisions->map(function ($p) {
            return "- ID:{$p->id} | {$p->law} cl.{$p->article} st.{$p->paragraph}";
        })->implode("\n");

        return <<<PROMPT
Provjeri tocnost citata u sljedecem dokumentu.

## DOSTUPNE ODREDBE (baza):
{$provisionsText}

## DOKUMENT ZA PROVJERU:
{$content}

## ZADATAK

Identificiraj sve pravne citate i provjeri postoje li u bazi. Vrati JSON:
{
    "citations_found": [
        {
            "article": "<broj clanka>",
            "paragraph": "<broj stavka>",
            "law": "<kratica zakona>",
            "valid": <true/false>,
            "provision_id": <ID iz baze ili null ako ne postoji>,
            "error": "<opis greske ako nije validan>"
        }
    ],
    "accuracy_score": <0-100>,
    "issues": [
        "Opis problema s citatom"
    ]
}
PROMPT;
    }

    private function buildPrecedentCheckPrompt(string $content, Collection $precedents): string
    {
        $precedentsText = $precedents->map(function ($p) {
            return "- ID:{$p->id} | {$p->case_number} ({$p->court})";
        })->implode("\n");

        return <<<PROMPT
Provjeri tocnost referenci na sudske presude u sljedecem dokumentu.

## DOSTUPNE PRESUDE (baza):
{$precedentsText}

## DOKUMENT ZA PROVJERU:
{$content}

## ZADATAK

Identificiraj sve reference na presude i provjeri postoje li u bazi. Vrati JSON:
{
    "precedents_found": [
        {
            "case_number": "<broj predmeta>",
            "valid": <true/false>,
            "precedent_id": <ID iz baze ili null ako ne postoji>,
            "usage_correct": <true/false - je li presuda ispravno primijenjena>,
            "error": "<opis greske ako nije validan>"
        }
    ],
    "usage_score": <0-100>,
    "issues": [
        "Opis problema s referencom"
    ]
}
PROMPT;
    }

    private function buildDevastationAssessmentPrompt(string $content): string
    {
        return <<<PROMPT
Ocijeni "devastirajuci potencijal" sljedeceg pravnog dokumenta.

## DOKUMENT:
{$content}

## ZADATAK

Analiziraj dokument i vrati JSON:
{
    "devastation_level": <1-10>,
    "factors": {
        "legal_foundation": <1-10>,
        "logical_chain": <1-10>,
        "precedent_support": <1-10>,
        "opponent_rebuttal_difficulty": <1-10>
    },
    "assessment": "Kratka ocjena snage dokumenta"
}
PROMPT;
    }

    // =========================================================================
    // Response Parsers
    // =========================================================================

    private function parseToValidationResult(string $response): ValidationResult
    {
        $json = $this->extractJson($response);
        $decoded = json_decode($json, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('ArgumentValidator: Failed to parse LLM response', [
                'error' => json_last_error_msg(),
                'response_preview' => substr($response, 0, 200),
            ]);

            return ValidationResult::parseError('Failed to parse validation response: ' . json_last_error_msg());
        }

        $score = $decoded['overall_score'] ?? 0;
        $isValid = $score >= ValidationResult::VALIDITY_THRESHOLD;

        return new ValidationResult(
            isValid: $isValid,
            score: $score,
            issues: $decoded['issues'] ?? [],
            suggestions: $decoded['improvements'] ?? [],
            strengths: $decoded['strengths'] ?? [],
            verdict: $decoded['verdict'] ?? 'WEAK',
            citationAccuracy: $decoded['citation_accuracy'] ?? 0,
            argumentStrength: $decoded['argument_strength'] ?? 0,
            logicalCoherence: $decoded['logical_coherence'] ?? 0,
        );
    }

    private function parseValidationResponse(string $response): array
    {
        $json = $this->extractJson($response);
        $decoded = json_decode($json, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                'overall_score' => 0,
                'citation_accuracy' => 0,
                'argument_strength' => 0,
                'logical_coherence' => 0,
                'chain_evaluation' => [
                    'logical_continuity' => 0,
                    'knockout_inevitability' => 0,
                    'vulnerabilities' => [],
                ],
                'verdict' => 'WEAK',
                'issues' => [
                    ['severity' => 'critical', 'description' => 'Failed to parse validation response'],
                ],
                'improvements' => [],
                'strengths' => [],
            ];
        }

        return [
            'overall_score' => $decoded['overall_score'] ?? 0,
            'citation_accuracy' => $decoded['citation_accuracy'] ?? 0,
            'argument_strength' => $decoded['argument_strength'] ?? 0,
            'logical_coherence' => $decoded['logical_coherence'] ?? 0,
            'chain_evaluation' => $decoded['chain_evaluation'] ?? [
                'logical_continuity' => 0,
                'knockout_inevitability' => 0,
                'vulnerabilities' => [],
            ],
            'verdict' => $decoded['verdict'] ?? 'WEAK',
            'issues' => $decoded['issues'] ?? [],
            'improvements' => $decoded['improvements'] ?? [],
            'strengths' => $decoded['strengths'] ?? [],
        ];
    }

    private function parseChainValidationResponse(string $response): ChainValidationResult
    {
        $json = $this->extractJson($response);
        $decoded = json_decode($json, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return ChainValidationResult::failed();
        }

        return ChainValidationResult::fromArray($decoded);
    }

    private function parseCitationCheckResponse(string $response): array
    {
        $json = $this->extractJson($response);
        $decoded = json_decode($json, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                'citations_found' => [],
                'accuracy_score' => 0,
                'issues' => ['Failed to parse citation check response'],
            ];
        }

        return [
            'citations_found' => $decoded['citations_found'] ?? [],
            'accuracy_score' => $decoded['accuracy_score'] ?? 0,
            'issues' => $decoded['issues'] ?? [],
        ];
    }

    private function parsePrecedentCheckResponse(string $response): array
    {
        $json = $this->extractJson($response);
        $decoded = json_decode($json, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                'precedents_found' => [],
                'usage_score' => 0,
                'issues' => ['Failed to parse precedent check response'],
            ];
        }

        return [
            'precedents_found' => $decoded['precedents_found'] ?? [],
            'usage_score' => $decoded['usage_score'] ?? 0,
            'issues' => $decoded['issues'] ?? [],
        ];
    }

    private function parseDevastationAssessmentResponse(string $response): int
    {
        $json = $this->extractJson($response);
        $decoded = json_decode($json, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return 1;
        }

        $level = $decoded['devastation_level'] ?? 1;

        return max(1, min(10, (int) $level));
    }

    private function extractJson(string $text): string
    {
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            return $matches[0];
        }
        return $text;
    }
}
