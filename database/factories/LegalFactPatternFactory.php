<?php

namespace Database\Factories;

use App\Models\LegalFactPattern;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LegalFactPatternFactory extends Factory
{
    protected $model = LegalFactPattern::class;

    public function definition(): array
    {
        $legalArea = fake()->randomElement(['contract', 'tort', 'property', 'criminal', 'family', 'employment', 'administrative']);

        return [
            'user_id' => User::factory(),
            'raw_narrative' => $this->generateNarrative($legalArea),
            'legal_area' => $legalArea,
            'extraction_confidence' => fake()->randomFloat(2, 0.5, 1.0),
            'structured_facts' => $this->generateStructuredFacts($legalArea),
        ];
    }

    /**
     * Generate a realistic narrative based on legal area
     */
    protected function generateNarrative(string $legalArea): string
    {
        $narratives = [
            'contract' => 'On '.fake()->date().', I entered into a contract with '.fake()->company().' for services totaling '.fake()->numberBetween(10000, 500000).' HRK. They failed to deliver as promised and have not responded to my communications.',
            'tort' => 'On '.fake()->date().', I was injured due to the negligence of '.fake()->name().'. I sustained injuries requiring medical treatment and have incurred expenses of approximately '.fake()->numberBetween(5000, 100000).' HRK.',
            'property' => 'I own property at '.fake()->address().'. There is a dispute with my neighbor '.fake()->name().' regarding the boundary line and access rights that has been ongoing since '.fake()->date().'.',
            'employment' => 'I was employed by '.fake()->company().' from '.fake()->date().' until '.fake()->date().' when I was wrongfully terminated. I believe the termination was discriminatory and violated my employment contract.',
            'family' => 'I am seeking divorce from my spouse '.fake()->name().'. We have been married since '.fake()->date().' and have '.fake()->numberBetween(0, 3).' children. There are disputes regarding property division and custody.',
            'criminal' => 'I have been charged with an offense that occurred on '.fake()->date().'. I maintain my innocence and need legal representation for the upcoming proceedings.',
            'administrative' => 'The administrative agency '.fake()->company().' issued a decision on '.fake()->date().' that adversely affects my business. I need to challenge this decision through appropriate legal channels.',
        ];

        return $narratives[$legalArea] ?? fake()->paragraph(5);
    }

    /**
     * Generate structured facts based on legal area
     */
    protected function generateStructuredFacts(string $legalArea): array
    {
        return [
            'parties' => [
                [
                    'name' => fake()->name(),
                    'role' => 'plaintiff',
                    'type' => fake()->randomElement(['individual', 'corporation', 'government']),
                ],
                [
                    'name' => fake()->name(),
                    'role' => 'defendant',
                    'type' => fake()->randomElement(['individual', 'corporation', 'government']),
                ],
            ],
            'events' => [
                [
                    'description' => 'Initial incident occurred',
                    'date' => fake()->date(),
                    'significance' => 'Triggered the legal dispute',
                ],
                [
                    'description' => 'Attempted resolution',
                    'date' => fake()->date(),
                    'significance' => 'Failed negotiation',
                ],
            ],
            'legal_issues' => [
                [
                    'issue' => $this->getLegalIssue($legalArea),
                    'area_of_law' => $legalArea,
                    'elements' => ['Element 1', 'Element 2', 'Element 3'],
                    'potential_claims' => ['Claim 1', 'Claim 2'],
                ],
            ],
            'facts_favorable_to_plaintiff' => [
                fake()->sentence(),
                fake()->sentence(),
            ],
            'facts_favorable_to_defendant' => [
                fake()->sentence(),
            ],
            'disputed_facts' => [
                fake()->sentence(),
                fake()->sentence(),
            ],
            'undisputed_facts' => [
                fake()->sentence(),
            ],
            'damages_or_relief_sought' => [
                'type' => 'monetary',
                'description' => 'Compensatory damages',
                'amount' => fake()->numberBetween(10000, 1000000).' HRK',
            ],
            'procedural_posture' => [
                'stage' => fake()->randomElement(['pre-filing', 'filed', 'discovery', 'trial', 'appeal']),
                'jurisdiction' => 'Croatia',
                'court' => fake()->randomElement(['Municipal Court', 'County Court', 'High Commercial Court']),
                'deadlines' => [],
            ],
            'legal_questions' => [
                fake()->sentence().'?',
            ],
            'relevant_laws' => [
                [
                    'law_type' => 'statute',
                    'citation' => 'Article '.fake()->numberBetween(1, 500),
                    'description' => fake()->sentence(),
                ],
            ],
            'evidence' => [
                [
                    'type' => fake()->randomElement(['documentary', 'testimonial', 'physical', 'expert']),
                    'description' => fake()->sentence(),
                    'strength' => fake()->randomElement(['strong', 'moderate', 'weak']),
                    'availability' => fake()->randomElement(['available', 'needs_discovery', 'unknown']),
                ],
            ],
            'timeline' => [
                [
                    'date' => fake()->date(),
                    'event' => fake()->sentence(),
                ],
            ],
            'key_terms' => [
                fake()->word(),
                fake()->word(),
            ],
            'summary' => fake()->paragraph(2),
        ];
    }

    /**
     * Get a legal issue based on area
     */
    protected function getLegalIssue(string $legalArea): string
    {
        $issues = [
            'contract' => 'Breach of contract - failure to perform',
            'tort' => 'Negligence causing personal injury',
            'property' => 'Boundary dispute and easement rights',
            'employment' => 'Wrongful termination',
            'family' => 'Divorce and property division',
            'criminal' => 'Criminal charges',
            'administrative' => 'Administrative decision challenge',
        ];

        return $issues[$legalArea] ?? 'Legal dispute';
    }

    /**
     * High confidence extraction
     */
    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'extraction_confidence' => fake()->randomFloat(2, 0.7, 1.0),
        ]);
    }

    /**
     * Low confidence extraction
     */
    public function lowConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'extraction_confidence' => fake()->randomFloat(2, 0.1, 0.49),
        ]);
    }

    /**
     * Medium confidence extraction
     */
    public function mediumConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'extraction_confidence' => fake()->randomFloat(2, 0.5, 0.69),
        ]);
    }

    /**
     * Contract law area
     */
    public function contract(): static
    {
        return $this->state(fn (array $attributes) => [
            'legal_area' => 'contract',
            'raw_narrative' => $this->generateNarrative('contract'),
            'structured_facts' => $this->generateStructuredFacts('contract'),
        ]);
    }

    /**
     * Tort law area
     */
    public function tort(): static
    {
        return $this->state(fn (array $attributes) => [
            'legal_area' => 'tort',
            'raw_narrative' => $this->generateNarrative('tort'),
            'structured_facts' => $this->generateStructuredFacts('tort'),
        ]);
    }

    /**
     * Property law area
     */
    public function property(): static
    {
        return $this->state(fn (array $attributes) => [
            'legal_area' => 'property',
            'raw_narrative' => $this->generateNarrative('property'),
            'structured_facts' => $this->generateStructuredFacts('property'),
        ]);
    }
}
