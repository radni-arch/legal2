<?php

namespace Database\Factories;

use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use App\Models\CourtDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourtCaseDecisionMatchFactory extends Factory
{
    protected $model = CourtCaseDecisionMatch::class;

    public function definition(): array
    {
        return [
            'court_case_id' => CourtCase::factory(),
            'court_decision_id' => CourtDecision::factory(),
            'matched_at' => now(),
            'match_type' => 'automatic',
            'match_confidence' => $this->faker->numberBetween(50, 100),
            'match_source' => 'system',
            'verification_status' => 'pending',
            'match_criteria' => [
                'case_number' => true,
                'date_proximity' => true,
            ],
            'notes' => null,
        ];
    }
}
