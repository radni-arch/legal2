<?php

namespace Database\Factories;

use App\Models\EoglasnaKeyword;
use Illuminate\Database\Eloquent\Factories\Factory;

class EoglasnaKeywordFactory extends Factory
{
    protected $model = EoglasnaKeyword::class;

    public function definition(): array
    {
        return [
            'query' => $this->faker->words(2, true),
            'scope' => $this->faker->randomElement(['notice', 'court', 'institution', 'court_legal_bankruptcy', 'court_natural_bankruptcy']),
            'deep_scan' => $this->faker->boolean(30),
            'enabled' => $this->faker->boolean(80),
            'last_run_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'last_date_published' => $this->faker->optional()->dateTimeBetween('-60 days', 'now'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function withDeepScan(): static
    {
        return $this->state(fn (array $attributes) => [
            'deep_scan' => true,
        ]);
    }
}
