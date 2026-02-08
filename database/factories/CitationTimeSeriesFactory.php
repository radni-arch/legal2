<?php

namespace Database\Factories;

use App\Models\CitationTimeSeries;
use App\Models\CourtDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

class CitationTimeSeriesFactory extends Factory
{
    protected $model = CitationTimeSeries::class;

    public function definition(): array
    {
        $periodStart = $this->faker->dateTimeBetween('-1 year', 'now');
        $periodEnd = (clone $periodStart)->modify('+1 month');

        return [
            'decision_id' => CourtDecision::factory(),
            'period_type' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'yearly']),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'citation_count' => $this->faker->numberBetween(0, 100),
            'incoming_citations' => $this->faker->numberBetween(0, 50),
            'outgoing_citations' => $this->faker->numberBetween(0, 20),
            'avg_citation_importance' => $this->faker->randomFloat(3, 0, 1),
            'citing_courts' => [],
            'top_citing_decisions' => [],
        ];
    }
}
