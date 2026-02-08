<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\LegalArtillery\Argument;
use App\DTOs\LegalArtillery\ArgumentChain;
use InvalidArgumentException;

/**
 * Legacy builder that assembles argument chains from reusable argument types.
 *
 * This preserves the original argument-type model while the primary
 * DevastatingArgumentBuilder now uses fixed, explicit chains.
 */
class LegacyDevastatingArgumentBuilder
{
    /**
     * Supported argument types and their definitions.
     *
     * Each type includes:
     * - provisions: legal provisions supporting this argument type
     * - precedents: case law supporting this argument type
     * - base_strength: default strength rating
     * - applicable_profiles: which document profiles can use this type
     * - text_template: template for generating argument text
     */
    private array $argumentDefinitions = [
        'jurisdictional_error' => [
            'provisions' => [
                'ZKP cl.202 st.1',
                'ZKP cl.206',
                'Ustav cl.29 st.1',
            ],
            'precedents' => [
                'VSRH Kz-123/2018',
                'U-III-3071/2006',
            ],
            'base_strength' => 7,
            'applicable_profiles' => ['ustavni_sud', 'predsjednik_suda', 'ombudsman', 'vdsjt'],
            'text_template' => 'Sud je prekoračio svoju nadležnost postupajući protivno odredbama {provisions}. Vrhovna sudska praksa ({precedents}) jasno utvrđuje da takvo postupanje predstavlja bitnu povredu postupka.',
        ],

        'defense_rights_violation' => [
            'provisions' => [
                'Ustav cl.29 st.2',
                'ZKP cl.64',
                'EKLJP cl.6 st.3',
            ],
            'precedents' => [
                'Salduz v. Turkey (2008)',
                'U-III-2258/2018',
                'VSRH Kz-456/2019',
            ],
            'base_strength' => 8,
            'applicable_profiles' => ['ustavni_sud', 'echr_application', 'ombudsman', 'predsjednik_suda'],
            'text_template' => 'Prava obrane sustavno su povrijeđena. Članak 29. st. 2. Ustava i članak 6. st. 3. EKLJP jamče pravo na obranu. Prema praksi ESLJP u predmetu {precedents}, svako ograničenje tog prava mora biti strogo proporcionalno.',
        ],

        'procedural_irregularity' => [
            'provisions' => [
                'ZKP cl.78',
                'ZKP cl.202 st.3',
                'PZ cl.150 st.4',
            ],
            'precedents' => [
                'VSRH Kz-789/2020',
                'U-III-1234/2017',
            ],
            'base_strength' => 6,
            'applicable_profiles' => ['predsjednik_suda', 'ombudsman', 'ustavni_sud', 'vdsjt', 'zupanije'],
            'text_template' => 'Postupak je proveden uz bitne nepravilnosti koje utječu na zakonitost odluke. Prema {provisions}, svaki propust u postupanju mora biti sankcioniran. Praksa {precedents} potvrđuje da se ovakve nepravilnosti ne mogu konvalidirati.',
        ],

        'constitutional_violation' => [
            'provisions' => [
                'Ustav cl.18 st.1',
                'Ustav cl.29 st.1',
                'Ustav cl.35',
                'UZUSRH cl.62 st.1',
            ],
            'precedents' => [
                'U-III-2258/2018',
                'U-III-3071/2006',
                'U-III-5678/2019',
            ],
            'base_strength' => 9,
            'applicable_profiles' => ['ustavni_sud', 'echr_application'],
            'text_template' => 'Osporeno postupanje predstavlja povredu ustavom zajamčenih prava. Članak 18. st. 1. Ustava jamči pravo na žalbu. Članak 29. jamči pravo na pošteno suđenje. Ustavni sud je u predmetima {precedents} utvrdio da su prava "praktična i djelotvorna, a ne teorijska i iluzorna".',
        ],
    ];

    /**
     * Profile-specific vulnerability assessments.
     */
    private array $profileVulnerabilities = [
        'ustavni_sud' => [
            'Argument iscrpljivanja pravnog puta može biti osporen',
            'Rok za podnošenje ustavne tužbe (30 dana)',
        ],
        'echr_application' => [
            'Rok od 4 mjeseca od konačne odluke',
            'Potrebno dokazati iscrpljivanje domaćih pravnih sredstava',
        ],
        'predsjednik_suda' => [
            'Predsjednik suda nema ovlast ukinuti sudske odluke',
            'Nadzor je ograničen na administrativno postupanje',
        ],
        'ombudsman' => [
            'Preporuke pučkog pravobranitelja nisu pravno obvezujuće',
        ],
    ];

    /**
     * Profile-specific recommendations.
     */
    private array $profileRecommendations = [
        'ustavni_sud' => [
            'Naglasiti nepostojanje djelotvornog pravnog puta',
            'Citirati relevantnu praksu Ustavnog suda',
            'Ukazati na sistemski karakter povrede',
        ],
        'echr_application' => [
            'Koristiti terminologiju ESLJP',
            'Referirati na pilot-presude protiv Hrvatske',
            'Dokazati iscrpljivanje ili nemogućnost pravnog puta',
        ],
        'predsjednik_suda' => [
            'Zatražiti inspekcijski nadzor',
            'Dokumentirati sve nepravilnosti kronološki',
        ],
        'ombudsman' => [
            'Naglasiti sistemsku prirodu problema',
            'Zatražiti posebno izvješće Saboru',
        ],
    ];

    /**
     * Build a complete argument chain for a document profile and case context.
     */
    public function build(DocumentProfile $profile, CaseContext $context): ArgumentChain
    {
        $applicableTypes = $this->getArgumentTypes($profile);
        $arguments = [];

        foreach ($applicableTypes as $type) {
            $arguments[] = $this->buildArgument($type, $context);
        }

        return $this->chainArguments($arguments, $profile->key);
    }

    /**
     * Get applicable argument types for a document profile.
     *
     * @return array<string> List of argument type identifiers
     */
    public function getArgumentTypes(DocumentProfile $profile): array
    {
        $applicableTypes = [];

        foreach ($this->argumentDefinitions as $type => $definition) {
            if (in_array($profile->key, $definition['applicable_profiles'], true)) {
                $applicableTypes[] = $type;
            }
        }

        if (empty($applicableTypes)) {
            $applicableTypes = ['procedural_irregularity'];
        }

        return $applicableTypes;
    }

    /**
     * Build a single argument of the specified type.
     *
     * @throws InvalidArgumentException If the argument type is not supported
     */
    public function buildArgument(string $type, CaseContext $context): Argument
    {
        if (!isset($this->argumentDefinitions[$type])) {
            throw new InvalidArgumentException("Unknown argument type: {$type}");
        }

        $definition = $this->argumentDefinitions[$type];

        $text = $this->interpolateTemplate(
            $definition['text_template'],
            $definition['provisions'],
            $definition['precedents'],
            $context,
        );

        return new Argument(
            type: $type,
            provisions: $definition['provisions'],
            precedents: $definition['precedents'],
            strength: $definition['base_strength'],
            text: $text,
        );
    }

    /**
     * Chain multiple arguments together, calculating combined strength.
     *
     * @param array<Argument> $arguments Arguments to chain
     * @param string|null $profileKey Optional profile key for vulnerabilities/recommendations
     */
    public function chainArguments(array $arguments, ?string $profileKey = null): ArgumentChain
    {
        if (empty($arguments)) {
            return new ArgumentChain(
                arguments: [],
                combinedStrength: 0,
                vulnerabilities: [],
                recommendations: [],
            );
        }

        $combinedStrength = $this->calculateCombinedStrength($arguments);

        $vulnerabilities = $this->getVulnerabilities($profileKey);
        $recommendations = $this->getRecommendations($profileKey);

        return new ArgumentChain(
            arguments: $arguments,
            combinedStrength: $combinedStrength,
            vulnerabilities: $vulnerabilities,
            recommendations: $recommendations,
        );
    }

    /**
     * Calculate combined strength with synergy bonus.
     *
     * @param array<Argument> $arguments
     */
    private function calculateCombinedStrength(array $arguments): int
    {
        if (empty($arguments)) {
            return 0;
        }

        $totalWeight = 0;
        $weightedSum = 0;
        foreach ($arguments as $index => $argument) {
            $weight = 1 + ($index * 0.1);
            $weightedSum += $argument->strength * $weight;
            $totalWeight += $weight;
        }

        $baseStrength = $weightedSum / $totalWeight;

        $uniqueTypes = count(array_unique(array_map(fn(Argument $a) => $a->type, $arguments)));
        $synergyBonus = min($uniqueTypes - 1, 3);

        $combined = (int) round($baseStrength + $synergyBonus);

        return min($combined, 10);
    }

    /**
     * Get vulnerabilities for a profile.
     *
     * @return array<string>
     */
    private function getVulnerabilities(?string $profileKey): array
    {
        if ($profileKey === null || !isset($this->profileVulnerabilities[$profileKey])) {
            return ['Protivnik može osporiti primjenjivost citiranih odredbi'];
        }

        return $this->profileVulnerabilities[$profileKey];
    }

    /**
     * Get recommendations for a profile.
     *
     * @return array<string>
     */
    private function getRecommendations(?string $profileKey): array
    {
        if ($profileKey === null || !isset($this->profileRecommendations[$profileKey])) {
            return ['Dodati relevantnu sudsku praksu', 'Precizirati pravnu osnovu'];
        }

        return $this->profileRecommendations[$profileKey];
    }

    /**
     * Get all argument chains applicable to a specific profile.
     *
     * @param string $profileKey The document profile key
     * @return array<ArgumentChain> Array of argument chains for the profile
     */
    public function getChainsForProfile(string $profileKey): array
    {
        $profile = DocumentProfile::fromConfig($profileKey);
        $context = CaseContext::fromConfig();
        $chain = $this->build($profile, $context);

        return [$chain];
    }

    /**
     * Build a formatted argument injection string for LLM prompts.
     *
     * @param string $profileKey The document profile key
     * @return string Formatted argument chains for prompt injection
     */
    public function buildArgumentInjection(string $profileKey): string
    {
        $chains = $this->getChainsForProfile($profileKey);

        if (empty($chains) || empty($chains[0]->arguments)) {
            return '';
        }

        $lines = ["## Argumentacijski lanci (ukupna snaga: {$chains[0]->combinedStrength}/10)"];

        foreach ($chains as $chain) {
            foreach ($chain->arguments as $i => $arg) {
                $num = $i + 1;
                $lines[] = "\n### Argument {$num}: {$arg->type} (snaga: {$arg->strength}/10)";
                $lines[] = 'Pravni temelji: ' . implode(', ', $arg->provisions);
                $lines[] = 'Sudska praksa: ' . implode(', ', $arg->precedents);
                $lines[] = "Tekst: {$arg->text}";
            }

            if (!empty($chain->vulnerabilities)) {
                $lines[] = "\n### Poznate ranjivosti";
                foreach ($chain->vulnerabilities as $v) {
                    $lines[] = "- {$v}";
                }
            }

            if (!empty($chain->recommendations)) {
                $lines[] = "\n### Preporuke";
                foreach ($chain->recommendations as $r) {
                    $lines[] = "- {$r}";
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get killer summaries for quick reference.
     *
     * @param string $profileKey The document profile key
     * @return array Array of ['name' => string, 'summary' => string]
     */
    public function getKillerSummaries(string $profileKey): array
    {
        $chains = $this->getChainsForProfile($profileKey);
        $summaries = [];

        foreach ($chains as $chain) {
            foreach ($chain->arguments as $arg) {
                $summaries[] = [
                    'name' => ucfirst(str_replace('_', ' ', $arg->type)),
                    'summary' => mb_substr($arg->text, 0, 200) . (mb_strlen($arg->text) > 200 ? '...' : ''),
                ];
            }
        }

        return $summaries;
    }

    /**
     * Interpolate the text template with provisions and precedents.
     */
    private function interpolateTemplate(
        string $template,
        array $provisions,
        array $precedents,
        CaseContext $context,
    ): string {
        $text = $template;

        $provisionsText = implode(', ', $provisions);
        $text = str_replace('{provisions}', $provisionsText, $text);

        $precedentsText = implode(', ', array_slice($precedents, 0, 2));
        $text = str_replace('{precedents}', $precedentsText, $text);

        $text .= " U konkretnom predmetu ({$context->caseNumber}), ";
        $text .= "dana {$context->searchDate} provedena je radnja koja je bila protupravna.";

        return $text;
    }
}
