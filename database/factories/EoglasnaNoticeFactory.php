<?php

namespace Database\Factories;

use App\Models\EoglasnaNotice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EoglasnaNoticeFactory extends Factory
{
    protected $model = EoglasnaNotice::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'public_url' => $this->faker->url(),
            'notice_documents_download_url' => $this->faker->optional()->url(),
            'notice_type' => $this->faker->randomElement(['court', 'institution']),
            'title' => $this->faker->sentence(8),
            'expiration_date' => $this->faker->optional()->date(),
            'date_published' => $this->faker->dateTimeBetween('-90 days', 'now'),
            'notice_source_type' => $this->faker->randomElement(['court', 'institution']),
            'court_code' => $this->faker->optional()->numerify('####'),
            'court_name' => $this->faker->optional()->randomElement([
                'Općinski sud u Osijeku',
                'Općinski sud u Zagrebu',
                'Županijski sud u Osijeku',
            ]),
            'court_type' => $this->faker->optional()->randomElement(['municipal', 'county']),
            'case_number' => $this->faker->bothify('???-####/####'),
            'case_type' => $this->faker->optional()->word(),
            'institution_name' => $this->faker->optional()->company(),
            'institution_notice_type' => $this->faker->optional()->word(),
            'participants' => $this->faker->optional()->randomElement([
                json_encode([['name' => 'Test Participant', 'oib' => '12345678901']]),
                null,
            ]),
            'notice_documents' => $this->faker->optional()->randomElement([
                ['document1.pdf', 'document2.pdf'],
                null,
            ]),
            'court_notice_details' => $this->faker->optional()->randomElement([
                ['detail' => 'test value'],
                null,
            ]),
            'raw' => [
                'source' => 'eoglasna',
                'original_data' => 'test',
            ],
            'first_seen_at' => $this->faker->dateTimeBetween('-90 days', 'now'),
            'last_seen_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
