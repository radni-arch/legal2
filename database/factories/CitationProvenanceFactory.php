<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CitationProvenance>
 */
class CitationProvenanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $laws = ['ZKP', 'Kazneni zakon', 'Ustav RH', 'Zakon o Državnom odvjetništvu'];
        $sourceTypes = ['law_database', 'constitution', 'criminal_code', 'court_decision', 'legal_journal'];
        $verificationStatuses = ['pending', 'verified', 'failed'];

        $law = fake()->randomElement($laws);
        $article = fake()->numberBetween(1, 500);

        return [
            'citation_text' => "{$law} Članak {$article}",
            'source_type' => fake()->randomElement($sourceTypes),
            'source_identifier' => strtolower(str_replace(' ', '-', $law)).'-'.$article,
            'source_url' => 'https://narodne-novine.nn.hr/clanci/'.fake()->uuid(),
            'citation_metadata' => [
                'law_name' => $law,
                'article' => (string) $article,
                'paragraph' => fake()->optional()->numberBetween(1, 10),
                'enacted_date' => fake()->date('Y-m-d'),
                'language' => 'hr',
                'jurisdiction' => 'Croatia',
            ],
            'verification_status' => fake()->randomElement($verificationStatuses),
            'confidence_score' => fake()->randomFloat(2, 0.7, 0.99),
            'verification_notes' => fake()->optional()->sentence(),
            'verified_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the citation is linked to a trace.
     */
    public function withTrace(string $traceId): static
    {
        return $this->state(fn (array $attributes) => [
            'trace_id' => $traceId,
        ]);
    }

    /**
     * Indicate that the citation is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'verified',
            'verified_at' => now(),
            'confidence_score' => fake()->randomFloat(2, 0.90, 0.99),
        ]);
    }

    /**
     * Indicate that the citation is pending verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'pending',
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the citation is from a specific law.
     */
    public function fromLaw(string $lawName, int $article): static
    {
        return $this->state(fn (array $attributes) => [
            'citation_text' => "{$lawName} Članak {$article}",
            'source_identifier' => strtolower(str_replace(' ', '-', $lawName)).'-'.$article,
            'citation_metadata' => array_merge($attributes['citation_metadata'] ?? [], [
                'law_name' => $lawName,
                'article' => (string) $article,
            ]),
        ]);
    }
}
