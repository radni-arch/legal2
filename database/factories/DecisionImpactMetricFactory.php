<?php

namespace Database\Factories;

use App\Models\CourtDecision;
use App\Models\DecisionImpactMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DecisionImpactMetricFactory extends Factory
{
    protected $model = DecisionImpactMetric::class;

    public function definition(): array
    {
        $citationCount = fake()->numberBetween(0, 50);
        $authorityScore = fake()->randomFloat(3, 0, 1);
        $precedentStrength = fake()->randomFloat(3, 0, 1);

        return [
            'id' => Str::ulid()->toString(),
            'decision_id' => CourtDecision::factory(),
            'citation_count' => $citationCount,
            'direct_citations' => $citationCount,
            'indirect_citations' => fake()->numberBetween(0, 20),
            'authority_score' => $authorityScore,
            'precedent_strength' => $precedentStrength,
            'influence_score' => ($authorityScore * 0.6) + ($precedentStrength * 0.4),
            'citations_over_time' => [
                '2023-01' => fake()->numberBetween(0, 5),
                '2023-02' => fake()->numberBetween(0, 5),
                '2023-03' => fake()->numberBetween(0, 5),
            ],
            'citation_velocity' => fake()->randomFloat(2, 0, 5),
            'temporal_decay_factor' => fake()->randomFloat(3, 0.5, 1.0),
            'jurisdictional_spread' => [
                'HR' => $citationCount,
            ],
            'jurisdictions_count' => 1,
            'citing_courts' => [
                'Vrhovni sud Republike Hrvatske',
                'Županijski sud u Zagrebu',
            ],
            'higher_court_citations' => fake()->numberBetween(0, 10),
            'same_court_citations' => fake()->numberBetween(0, 20),
            'lower_court_citations' => fake()->numberBetween(0, 20),
            'influential_cases' => [
                [
                    'id' => Str::ulid()->toString(),
                    'case_number' => 'Rev-'.fake()->numberBetween(1000, 9999).'/'.fake()->year(),
                    'title' => fake()->sentence(),
                    'court' => 'Vrhovni sud Republike Hrvatske',
                    'decision_date' => now()->subMonths(6)->toDateString(),
                ],
            ],
            'impact_summary' => 'This decision has moderate authority in the legal system.',
            'last_calculated_at' => now(),
            'last_citation_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate high authority metrics
     */
    public function highAuthority(): static
    {
        return $this->state(fn (array $attributes) => [
            'citation_count' => fake()->numberBetween(20, 100),
            'authority_score' => fake()->randomFloat(3, 0.7, 1.0),
            'precedent_strength' => fake()->randomFloat(3, 0.7, 1.0),
            'influence_score' => fake()->randomFloat(3, 0.7, 1.0),
        ]);
    }

    /**
     * Indicate low authority metrics
     */
    public function lowAuthority(): static
    {
        return $this->state(fn (array $attributes) => [
            'citation_count' => fake()->numberBetween(0, 5),
            'authority_score' => fake()->randomFloat(3, 0, 0.3),
            'precedent_strength' => fake()->randomFloat(3, 0, 0.3),
            'influence_score' => fake()->randomFloat(3, 0, 0.3),
        ]);
    }

    /**
     * Indicate recently updated metrics
     */
    public function recentlyUpdated(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_calculated_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }

    /**
     * Indicate stale metrics (> 30 days)
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_calculated_at' => now()->subDays(fake()->numberBetween(31, 90)),
        ]);
    }
}
