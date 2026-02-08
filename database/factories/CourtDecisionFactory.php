<?php

namespace Database\Factories;

use App\Models\CourtDecision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourtDecisionFactory extends Factory
{
    protected $model = CourtDecision::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid()->toString(),
            'case_number' => 'Rev-'.fake()->numberBetween(1000, 9999).'/'.fake()->year(),
            'title' => fake()->sentence(),
            'court' => fake()->randomElement([
                'Vrhovni sud Republike Hrvatske',
                'Visoki trgovački sud',
                'Visoki upravni sud',
                'Županijski sud u Zagrebu',
                'Općinski sud u Zagrebu',
            ]),
            'jurisdiction' => 'HR',
            'judge' => fake()->name(),
            'decision_date' => fake()->dateTimeBetween('-5 years', 'now'),
            'publication_date' => fake()->dateTimeBetween('-5 years', 'now'),
            'decision_type' => fake()->randomElement(['presuda', 'rješenje', 'nalog', 'sentence', 'ruling']),
            'register' => fake()->randomElement(['Rev', 'Gž', 'Gžm', 'Kž', 'P']),
            'finality' => fake()->randomElement(['final', 'konačna', 'pravnomoćna', 'preliminary']),
            'ecli' => fake()->optional(0.3)->regexify('ECLI:HR:[A-Z]{3}:[0-9]{4}:[A-Z0-9]{10}'),
            'tags' => fake()->randomElements(['commercial', 'civil', 'administrative', 'criminal'], fake()->numberBetween(1, 2)),
            'description' => fake()->paragraph(),
        ];
    }

    /**
     * Indicate a Supreme Court decision
     */
    public function supremeCourt(): static
    {
        return $this->state(fn (array $attributes) => [
            'court' => 'Vrhovni sud Republike Hrvatske',
        ]);
    }

    /**
     * Indicate a High Court decision
     */
    public function highCourt(): static
    {
        return $this->state(fn (array $attributes) => [
            'court' => fake()->randomElement(['Visoki trgovački sud', 'Visoki upravni sud']),
        ]);
    }

    /**
     * Indicate a County Court decision
     */
    public function countyCourt(): static
    {
        return $this->state(fn (array $attributes) => [
            'court' => 'Županijski sud u '.fake()->randomElement(['Zagrebu', 'Splitu', 'Rijeci', 'Osijeku']),
        ]);
    }

    /**
     * Indicate a Municipal Court decision
     */
    public function municipalCourt(): static
    {
        return $this->state(fn (array $attributes) => [
            'court' => 'Općinski sud u '.fake()->randomElement(['Zagrebu', 'Splitu', 'Rijeci']),
        ]);
    }

    /**
     * Indicate a final decision
     */
    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'finality' => fake()->randomElement(['final', 'konačna', 'pravnomoćna']),
        ]);
    }

    /**
     * Indicate a preliminary decision
     */
    public function preliminary(): static
    {
        return $this->state(fn (array $attributes) => [
            'finality' => 'preliminary',
        ]);
    }

    /**
     * Indicate a decision with ECLI identifier
     */
    public function withECLI(): static
    {
        return $this->state(fn (array $attributes) => [
            'ecli' => 'ECLI:HR:VSRH:'.date('Y').':'.strtoupper(Str::random(10)),
        ]);
    }

    /**
     * Indicate a recent decision (< 2 years old)
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'publication_date' => fake()->dateTimeBetween('-2 years', 'now'),
        ]);
    }

    /**
     * Indicate an old decision (> 10 years old)
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision_date' => fake()->dateTimeBetween('-20 years', '-10 years'),
            'publication_date' => fake()->dateTimeBetween('-20 years', '-10 years'),
        ]);
    }
}
