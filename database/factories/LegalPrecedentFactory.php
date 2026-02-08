<?php

namespace Database\Factories;

use App\Models\LegalPrecedent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalPrecedent>
 */
class LegalPrecedentFactory extends Factory
{
    protected $model = LegalPrecedent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $court = fake()->randomElement(['USRH', 'VSRH', 'ECHR']);

        return [
            'case_number' => $this->generateCaseNumber($court),
            'court' => $court,
            'court_full' => $this->getCourtFullName($court),
            'decision_date' => fake()->dateTimeBetween('-20 years', 'now'),
            'published_in' => fake()->optional(0.4)->regexify('NN [0-9]{1,3}/[0-9]{2}'),
            'applicant' => fake()->optional(0.7)->name(),
            'respondent' => fake()->optional(0.7)->randomElement([
                'Republika Hrvatska',
                'Republic of Croatia',
                'Croatia',
            ]),
            'echr_app_number' => $court === 'ECHR' ? fake()->numerify('#####/##') : null,
            'legal_issue' => fake()->sentence(10),
            'key_holding' => fake()->paragraph(2),
            'key_quote' => fake()->paragraph(1),
            'quote_language' => fake()->randomElement(['hr', 'en']),
            'relevance_to_case' => fake()->sentence(8),
            'strength' => fake()->randomElement(['devastating', 'strong', 'moderate']),
            'articles_interpreted' => fake()->randomElements([
                'Ustav RH čl.29',
                'ECHR Art.6',
                'ECHR Art.13',
                'ZKP čl.78',
                'ZS čl.10',
            ], fake()->numberBetween(1, 3)),
            'argument_types' => fake()->randomElements([
                'equality_of_arms',
                'file_access',
                'effective_remedy',
                'fair_trial',
                'due_process',
                'right_to_appeal',
            ], fake()->numberBetween(1, 2)),
            'tags' => fake()->randomElements([
                'ustavni_sud',
                'echr_application',
                'human_rights',
                'criminal_procedure',
                'civil_procedure',
            ], fake()->numberBetween(1, 3)),
            'source_url' => fake()->optional(0.5)->url(),
            'nn_reference' => $court !== 'ECHR' ? fake()->optional(0.4)->regexify('NN [0-9]{1,3}/[0-9]{2}') : null,
        ];
    }

    /**
     * Generate a case number appropriate for the court type.
     */
    private function generateCaseNumber(string $court): string
    {
        return match ($court) {
            'USRH' => 'U-III-' . fake()->numberBetween(100, 9999) . '/' . fake()->year(),
            'VSRH' => 'Rev-' . fake()->numberBetween(100, 999) . '/' . fake()->year(),
            'ECHR' => fake()->lastName() . ' v. Croatia',
            default => 'Case-' . fake()->numerify('####/####'),
        };
    }

    /**
     * Get full court name for the court code.
     */
    private function getCourtFullName(string $court): string
    {
        return match ($court) {
            'USRH' => 'Ustavni sud Republike Hrvatske',
            'VSRH' => 'Vrhovni sud Republike Hrvatske',
            'ECHR' => 'European Court of Human Rights',
            default => 'Unknown Court',
        };
    }

    /**
     * State: Constitutional Court of Croatia (USRH)
     */
    public function constitutionalCourt(): static
    {
        return $this->state(fn (array $attributes) => [
            'court' => 'USRH',
            'court_full' => 'Ustavni sud Republike Hrvatske',
            'case_number' => 'U-III-' . fake()->numberBetween(100, 9999) . '/' . fake()->year(),
            'quote_language' => 'hr',
            'echr_app_number' => null,
        ]);
    }

    /**
     * State: Supreme Court of Croatia (VSRH)
     */
    public function supremeCourt(): static
    {
        return $this->state(fn (array $attributes) => [
            'court' => 'VSRH',
            'court_full' => 'Vrhovni sud Republike Hrvatske',
            'case_number' => 'Rev-' . fake()->numberBetween(100, 999) . '/' . fake()->year(),
            'quote_language' => 'hr',
            'echr_app_number' => null,
        ]);
    }

    /**
     * State: European Court of Human Rights (ECHR)
     */
    public function echr(): static
    {
        return $this->state(fn (array $attributes) => [
            'court' => 'ECHR',
            'court_full' => 'European Court of Human Rights',
            'case_number' => fake()->lastName() . ' v. Croatia',
            'echr_app_number' => fake()->numerify('#####/##'),
            'quote_language' => 'en',
            'nn_reference' => null,
        ]);
    }

    /**
     * State: Recent decision (within last 2 years)
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision_date' => fake()->dateTimeBetween('-2 years', 'now'),
        ]);
    }

    /**
     * State: With specific tags
     */
    public function withTags(array $tags): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => $tags,
        ]);
    }

    /**
     * State: With specific argument types
     */
    public function withArgumentTypes(array $argumentTypes): static
    {
        return $this->state(fn (array $attributes) => [
            'argument_types' => $argumentTypes,
        ]);
    }
}
