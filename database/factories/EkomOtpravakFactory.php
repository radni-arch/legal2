<?php

namespace Database\Factories;

use App\Models\EkomOtpravak;
use Illuminate\Database\Eloquent\Factories\Factory;

class EkomOtpravakFactory extends Factory
{
    protected $model = EkomOtpravak::class;

    public function definition(): array
    {
        return [
            'remote_id' => 'RO'.fake()->unique()->numerify('######'),
            'status' => fake()->randomElement(['kreiran', 'poslan', 'dostavljen', 'istekao_rok']),
            'predmet_remote_id' => 'K-'.fake()->numberBetween(100, 9999).'/'.fake()->year(),
            'vrijeme_slanja_sa_suda' => fake()->dateTimeBetween('-1 month', 'now'),
            'vrijeme_potvrde_primitka' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'primljen_zbog_isteka_roka' => fake()->boolean(20),
            'data' => [
                'tip_dostavnice' => fake()->randomElement(['sudska_odluka', 'poziv', 'obavijest']),
                'stranka' => fake()->company(),
                'sadrzaj' => fake()->sentence(),
            ],
            'last_synced_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
