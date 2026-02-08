<?php

namespace Database\Factories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        return [
            'external_id' => $this->faker->unique()->numberBetween(1000, 99999),
            'name' => $this->faker->randomElement([
                'Opcinski sud u Osijeku',
                'Opcinski sud u Zagrebu',
                'Opcinski sud u Splitu',
                'Opcinski sud u Rijeci',
                'Zupanijski sud u Osijeku',
            ]),
            'code' => $this->faker->regexify('[A-Z]{2,4}'),
            'level' => $this->faker->numberBetween(1, 3),
            'county' => $this->faker->randomElement([
                'Osjecko-baranjska',
                'Grad Zagreb',
                'Splitsko-dalmatinska',
                'Primorsko-goranska',
            ]),
            'population' => $this->faker->numberBetween(10000, 500000),
        ];
    }

    /**
     * Define a municipal (level 1) court.
     */
    public function municipal(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 1,
        ]);
    }

    /**
     * Define a county (level 2) court.
     */
    public function county(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 2,
        ]);
    }
}
