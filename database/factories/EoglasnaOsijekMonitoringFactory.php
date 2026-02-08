<?php

namespace Database\Factories;

use App\Models\EoglasnaOsijekMonitoring;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EoglasnaOsijekMonitoringFactory extends Factory
{
    protected $model = EoglasnaOsijekMonitoring::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'oib' => $this->faker->numerify('###########'),
            'street' => $this->faker->streetName(),
            'street_number' => $this->faker->numberBetween(1, 200),
            'city' => $this->faker->city(),
            'zip' => $this->faker->numberBetween(10000, 99999),
            'public_url' => $this->faker->url(),
            'notice_documents_download_url' => $this->faker->optional()->url(),
            'notice_type' => $this->faker->randomElement(['court', 'institution']),
            'title' => $this->faker->sentence(8),
            'expiration_date' => $this->faker->optional()->date(),
            'date_published' => $this->faker->dateTimeBetween('-90 days', 'now'),
            'notice_source_type' => $this->faker->randomElement(['court', 'institution']),
            'court_code' => $this->faker->optional()->numerify('####'),
            'court_name' => $this->faker->randomElement([
                'Općinski sud u Osijeku',
                'Općinski sud u Zagrebu',
                'Županijski sud u Osijeku',
                'Županijski sud u Zagrebu',
            ]),
            'court_type' => $this->faker->optional()->randomElement(['municipal', 'county']),
            'case_number' => $this->faker->bothify('Ovr-####/####'),
            'case_type' => $this->faker->optional()->word(),
            'institution_name' => $this->faker->optional()->company(),
            'institution_notice_type' => $this->faker->optional()->word(),
            'participants' => $this->faker->optional()->randomElement([
                json_encode([['name' => 'Test', 'oib' => '12345678901']]),
                null,
            ]),
            'notice_documents' => $this->faker->optional()->randomElement([
                ['doc1.pdf', 'doc2.pdf'],
                null,
            ]),
            'court_notice_details' => $this->faker->optional()->randomElement([
                ['detail' => 'test'],
                null,
            ]),
            'raw' => [
                'source' => 'test',
                'data' => 'raw data',
            ],
            'first_seen_at' => $this->faker->dateTimeBetween('-90 days', 'now'),
            'last_seen_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
