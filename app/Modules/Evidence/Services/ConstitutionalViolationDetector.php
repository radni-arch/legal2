<?php

namespace App\Modules\Evidence\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * ConstitutionalViolationDetector
 *
 * Detects violations of Croatian Constitution (Ustav Republike Hrvatske)
 * NN 56/90, 135/97, 08/98, 113/00, 124/00, 28/01, 41/01, 55/01, 76/10, 85/10, 05/14
 */
class ConstitutionalViolationDetector
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Detect constitutional violations in evidence collection
     */
    public function detect(array $evidence, LegalCase $case): array
    {
        Log::info('ConstitutionalViolationDetector - Detecting violations', [
            'case_id' => $case->id,
            'evidence_type' => $evidence['type'] ?? 'unknown',
        ]);

        $violations = [];

        // Check each constitutional protection
        $violations = array_merge($violations, $this->checkPrivacyViolation($evidence));          // Čl. 35
        $violations = array_merge($violations, $this->checkHomeInviolability($evidence));        // Čl. 34
        $violations = array_merge($violations, $this->checkFairTrialRights($evidence, $case));   // Čl. 29
        $violations = array_merge($violations, $this->checkPresumptionOfInnocence($evidence));   // Čl. 28
        $violations = array_merge($violations, $this->checkProhibitionOfTorture($evidence));     // Čl. 23
        $violations = array_merge($violations, $this->checkFreedomOfMovement($evidence));        // Čl. 32
        $violations = array_merge($violations, $this->checkRightToDefense($evidence, $case));    // Čl. 29(3)

        return $violations;
    }

    /**
     * Check for privacy violations (Ustav RH Članak 35)
     * "Zajamčuje se tajnost pisama i svih drugih sredstava općenja"
     */
    protected function checkPrivacyViolation(array $evidence): array
    {
        $violations = [];

        $type = $evidence['type'] ?? '';
        $method = strtolower($evidence['collection_method'] ?? '');

        // Communications interception
        if (in_array($type, ['digital', 'communication', 'wiretap'])) {
            // Check for court order
            if (! isset($evidence['court_order']) || ! $evidence['court_order']) {
                $violations[] = [
                    'article' => 'Ustav RH Članak 35',
                    'violation' => 'Tajnost komunikacija - Communications intercepted without court order',
                    'description' => 'Electronic communications or correspondence accessed without proper judicial authorization',
                    'severity' => 90,
                    'remedy' => 'Suppression of evidence',
                ];
            }
        }

        // Email/SMS/messages
        if (stripos($method, 'email') !== false ||
            stripos($method, 'sms') !== false ||
            stripos($method, 'message') !== false) {
            if (! isset($evidence['warrant'])) {
                $violations[] = [
                    'article' => 'Ustav RH Članak 35',
                    'violation' => 'Neovlašteni pristup komunikacijama',
                    'description' => 'Private communications accessed without authorization',
                    'severity' => 85,
                    'remedy' => 'Evidence exclusion',
                ];
            }
        }

        return $violations;
    }

    /**
     * Check home inviolability (Ustav RH Članak 34)
     * "Dom je nepovrediv"
     */
    protected function checkHomeInviolability(array $evidence): array
    {
        $violations = [];

        $method = strtolower($evidence['collection_method'] ?? '');
        $location = strtolower($evidence['collection_location'] ?? '');

        // Home search without warrant
        if (stripos($method, 'search') !== false || stripos($method, 'pretraga') !== false) {
            if (stripos($location, 'home') !== false ||
                stripos($location, 'kuća') !== false ||
                stripos($location, 'stan') !== false ||
                stripos($location, 'residence') !== false) {

                if (! isset($evidence['search_warrant']) || ! $evidence['search_warrant']) {
                    $violations[] = [
                        'article' => 'Ustav RH Članak 34',
                        'violation' => 'Nepovredi​vost doma - Home search without warrant',
                        'description' => 'Evidence obtained through unlawful home search',
                        'severity' => 95,
                        'remedy' => 'Mandatory suppression',
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * Check fair trial rights (Ustav RH Članak 29)
     * "Svatko ima pravo da zakonom ustanovljeni neovisni i nepristrani sud pravično i u razumnom roku...odluči o njegovim pravima i obvezama"
     */
    protected function checkFairTrialRights(array $evidence, LegalCase $case): array
    {
        $violations = [];

        // Check if evidence was disclosed to defense
        if (! isset($evidence['disclosed_to_defense'])) {
            $violations[] = [
                'article' => 'Ustav RH Članak 29',
                'violation' => 'Pravo na pravično suđenje - Evidence not disclosed to defense',
                'description' => 'Prosecution evidence must be disclosed to defense (Brady material)',
                'severity' => 80,
                'remedy' => 'Disclosure order or suppression',
            ];
        }

        return $violations;
    }

    /**
     * Check presumption of innocence (Ustav RH Članak 28)
     * "Svatko se smatra nevinim dok mu se pravomoćnom sudskom presudom ne utvrdi krivnja"
     */
    protected function checkPresumptionOfInnocence(array $evidence): array
    {
        $violations = [];

        $description = strtolower($evidence['description'] ?? '');

        // Check for presumption of guilt in evidence gathering
        if (stripos($description, 'guilty') !== false ||
            stripos($description, 'kriv') !== false ||
            stripos($description, 'confession forced') !== false) {
            $violations[] = [
                'article' => 'Ustav RH Članak 28',
                'violation' => 'Presumpcija nevinosti - Violation of presumption of innocence',
                'description' => 'Evidence suggests presumption of guilt before conviction',
                'severity' => 75,
                'remedy' => 'Exclusion of prejudicial evidence',
            ];
        }

        return $violations;
    }

    /**
     * Check prohibition of torture (Ustav RH Članak 23)
     * "Nitko ne smije biti podvrgnut mučenju"
     */
    protected function checkProhibitionOfTorture(array $evidence): array
    {
        $violations = [];

        $method = strtolower($evidence['collection_method'] ?? '');

        $tortureKeywords = [
            'torture', 'mučenje', 'cruel', 'okrutno',
            'inhuman', 'nečovječno', 'degrading', 'ponižavajuće',
            'coercion', 'prisila', 'force', 'sila',
        ];

        foreach ($tortureKeywords as $keyword) {
            if (stripos($method, $keyword) !== false) {
                $violations[] = [
                    'article' => 'Ustav RH Članak 23',
                    'violation' => 'Zabrana mučenja - Evidence obtained through torture/coercion',
                    'description' => "Evidence collection involved: {$keyword}",
                    'severity' => 100,
                    'remedy' => 'Absolute exclusion - inadmissible per se',
                ];
                break; // Only report once
            }
        }

        return $violations;
    }

    /**
     * Check freedom of movement (Ustav RH Članak 32)
     */
    protected function checkFreedomOfMovement(array $evidence): array
    {
        $violations = [];

        $method = strtolower($evidence['collection_method'] ?? '');

        // Unlawful detention
        if (stripos($method, 'detention') !== false ||
            stripos($method, 'arrest') !== false ||
            stripos($method, 'pritvor') !== false) {

            if (! isset($evidence['arrest_warrant']) || ! $evidence['arrest_warrant']) {
                $violations[] = [
                    'article' => 'Ustav RH Članak 32',
                    'violation' => 'Sloboda kretanja - Unlawful detention',
                    'description' => 'Evidence obtained during unlawful detention',
                    'severity' => 85,
                    'remedy' => 'Fruit of poisonous tree - exclude all derivative evidence',
                ];
            }
        }

        return $violations;
    }

    /**
     * Check right to defense (Ustav RH Članak 29(3))
     * "Osumnjičeniku i optuženiku...osigurava se pravo na obranu"
     */
    protected function checkRightToDefense(array $evidence, LegalCase $case): array
    {
        $violations = [];

        $type = $evidence['type'] ?? '';

        // Statement without lawyer
        if ($type === 'testimonial' || $type === 'confession') {
            if (! isset($evidence['lawyer_present']) || ! $evidence['lawyer_present']) {
                // Check if accused was informed of right to counsel
                if (! isset($evidence['informed_of_rights']) || ! $evidence['informed_of_rights']) {
                    $violations[] = [
                        'article' => 'Ustav RH Članak 29(3)',
                        'violation' => 'Pravo na obranu - Statement without legal counsel',
                        'description' => 'Accused statement taken without lawyer present or waiver',
                        'severity' => 90,
                        'remedy' => 'Suppression of statement and derivative evidence',
                    ];
                }
            }
        }

        return $violations;
    }
}
