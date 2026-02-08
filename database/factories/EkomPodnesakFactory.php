<?php

namespace Database\Factories;

use App\Models\EkomPodnesak;
use Illuminate\Database\Eloquent\Factories\Factory;

class EkomPodnesakFactory extends Factory
{
    protected $model = EkomPodnesak::class;

    public function definition(): array
    {
        $kreiranje = fake()->dateTimeBetween('-1 month', 'now');
        $slanje = fake()->optional()->dateTimeBetween($kreiranje, 'now');
        $zaprimanje = $slanje ? fake()->optional()->dateTimeBetween($slanje, 'now') : null;

        return [
            'remote_id' => 'RP'.fake()->unique()->numerify('######'),
            'status' => fake()->randomElement(['kreiran', 'poslan', 'zaprimljen', 'u_obradi']),
            'sud_remote_id' => 'SUD'.fake()->numberBetween(1, 100),
            'vrsta_podneska_remote_id' => 'VP'.fake()->numberBetween(1, 50),
            'vrijeme_kreiranja' => $kreiranje,
            'vrijeme_slanja' => $slanje,
            'vrijeme_zaprimanja' => $zaprimanje,
            'data' => [
                'privitak' => fake()->optional()->word().'.pdf',
                'napomena' => fake()->optional()->sentence(),
                'stranka' => fake()->company(),
                'predmet' => fake()->sentence(3),
            ],
            'last_synced_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
