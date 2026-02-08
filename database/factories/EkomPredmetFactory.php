<?php

namespace Database\Factories;

use App\Models\EkomPredmet;
use Illuminate\Database\Eloquent\Factories\Factory;

class EkomPredmetFactory extends Factory
{
    protected $model = EkomPredmet::class;

    public function definition(): array
    {
        return [
            'remote_id' => 'RPRED'.fake()->unique()->numerify('######'),
            'oznaka' => 'K-'.fake()->numberBetween(100, 9999).'/'.fake()->year(),
            'status' => fake()->randomElement(['otvoren', 'aktivan', 'u_tijeku', 'zatvoren']),
            'sud_remote_id' => 'SUD'.fake()->numberBetween(1, 100),
            'do_not_disturb' => fake()->boolean(10),
            'data' => [
                'naziv_predmeta' => fake()->sentence(3),
                'sudac' => 'Dr. '.fake()->firstName().' '.fake()->lastName(),
                'datum_otvaranja' => fake()->date(),
                'stranka_tuzitelj' => fake()->company(),
                'stranka_tuzenik' => fake()->company(),
                'vrijednost_spora' => fake()->optional()->randomFloat(2, 1000, 500000),
                'stranke' => [
                    'tuzitelj' => fake()->company(),
                    'tuzenik' => fake()->company(),
                ],
            ],
            'last_synced_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
