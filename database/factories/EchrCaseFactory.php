<?php

namespace Database\Factories;

use App\Models\EchrCase;
use Illuminate\Database\Eloquent\Factories\Factory;

class EchrCaseFactory extends Factory
{
    protected $model = EchrCase::class;

    public function definition(): array
    {
        $states = ['Croatia', 'Poland', 'Russia', 'Turkey', 'Romania', 'Ukraine'];

        return [
            'item_id' => '001-'.$this->faker->unique()->numberBetween(100000, 999999),
            'application_number' => $this->faker->numberBetween(10000, 99999).'/'.$this->faker->numberBetween(10, 25),
            'case_name' => 'CASE OF '.strtoupper($this->faker->lastName()).' v. '.strtoupper($this->faker->randomElement($states)),
            'case_name_short' => $this->faker->lastName().' v. '.$this->faker->randomElement($states),
            'respondent_state' => $this->faker->randomElement($states),
            'document_type' => 'JUDGMENT',
            'importance' => (string) $this->faker->randomElement([1, 2, 3, 4]),
            'judgment_date' => $this->faker->dateTimeBetween('-10 years', 'now'),
            'violations' => [],
            'non_violations' => [],
            'language' => 'ENG',
        ];
    }

    public function croatian(): static
    {
        return $this->state(fn () => ['respondent_state' => 'Croatia']);
    }

    public function important(): static
    {
        return $this->state(fn () => ['importance' => '1']);
    }

    public function withFullText(): static
    {
        return $this->state(fn () => [
            'full_text' => $this->faker->paragraphs(10, true),
            'full_text_downloaded' => true,
        ]);
    }
}
