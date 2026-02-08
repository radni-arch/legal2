<?php

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        $providers = ['gemini', 'mistral', 'openrouter'];
        $provider = $this->faker->randomElement($providers);

        return [
            'name' => $this->faker->words(2, true).'-key',
            'provider' => $provider,
            'api_key' => 'test_'.$this->faker->sha256(),
            'model' => null,
            'rpm_limit' => 10,
            'rpd_limit' => 100,
            'tpm_limit' => 250000,
            'tpd_limit' => 0,
            'rpm_used' => 0,
            'rpd_used' => 0,
            'tpm_used' => 0,
            'tpd_used' => 0,
            'is_active' => true,
            'supports_pdf' => true,
            'supports_vision' => true,
            'priority' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function gemini(): self
    {
        return $this->state(['provider' => 'gemini', 'model' => 'gemini-2.5-flash-preview-05-20']);
    }

    public function mistral(): self
    {
        return $this->state(['provider' => 'mistral', 'model' => 'mistral-small-latest']);
    }

    public function openrouter(): self
    {
        return $this->state(['provider' => 'openrouter', 'model' => 'google/gemini-2.0-flash-exp:free']);
    }

    public function exhausted(): self
    {
        return $this->state(['rpd_used' => 100, 'rpd_limit' => 100]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
