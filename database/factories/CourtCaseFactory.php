<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\CourtCase;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourtCaseFactory extends Factory
{
    protected $model = CourtCase::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2020, 2024);
        $number = $this->faker->numberBetween(1, 999);
        $register = 'Pp Prz';

        return [
            'case_number' => "{$register}-{$number}/{$year}",
            'register' => $register,
            'number' => $number,
            'year' => $year,
            'court_id' => function () {
                return Court::firstOrCreate(
                    ['external_id' => 999],
                    [
                        'name' => 'Test Court',
                        'code' => 'TEST',
                        'level' => 1,
                    ]
                )->id;
            },
            'judge_name' => $this->faker->name(),
            'case_type' => 'Test Case',
            'decision_type' => 'Nalog',
            'is_search_warrant' => true,
            'date_filed' => now(),
            'date_decision' => now(),
        ];
    }
}
