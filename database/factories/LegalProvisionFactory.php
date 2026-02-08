<?php

namespace Database\Factories;

use App\Models\LegalProvision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LegalProvision>
 */
class LegalProvisionFactory extends Factory
{
    protected $model = LegalProvision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $laws = [
            ['name' => 'Prekrsajni zakon', 'short' => 'PZ'],
            ['name' => 'Zakon o kaznenom postupku', 'short' => 'ZKP'],
            ['name' => 'Ustav Republike Hrvatske', 'short' => 'Ustav'],
            ['name' => 'Zakon o sudovima', 'short' => 'ZS'],
            ['name' => 'Zakon o pravu na pristup informacijama', 'short' => 'ZPPI'],
            ['name' => 'Zakon o puckom pravobranitelju', 'short' => 'ZPP'],
        ];

        $law = fake()->randomElement($laws);

        return [
            'law_name' => $law['name'],
            'law_short' => $law['short'],
            'article' => (string) fake()->numberBetween(1, 500),
            'paragraph' => fake()->optional(0.7)->numberBetween(1, 10),
            'point' => fake()->optional(0.3)->numberBetween(1, 5),
            'title' => fake()->optional(0.8)->sentence(),
            'full_text' => fake()->paragraph(),
            'interpretation' => fake()->optional(0.6)->paragraph(),
            'tags' => fake()->randomElements([
                'file_access',
                'predsjednik_suda',
                'ombudsman',
                'ministarstvo_pravosudja',
                'defence_rights',
                'constitutional',
                'ustavni_sud',
                'counter_argument',
                'dorh_production',
                'kazneni_sud_motion',
                'izdvajanje_dokaza',
                'evidence_exclusion',
            ], fake()->numberBetween(1, 4)),
            'source_url' => fake()->optional(0.5)->url(),
        ];
    }

    /**
     * Indicate the provision is for Prekrsajni zakon (PZ).
     */
    public function prekrsajniZakon(): static
    {
        return $this->state(fn (array $attributes) => [
            'law_name' => 'Prekrsajni zakon',
            'law_short' => 'PZ',
        ]);
    }

    /**
     * Indicate the provision is for Zakon o kaznenom postupku (ZKP).
     */
    public function kazneniPostupak(): static
    {
        return $this->state(fn (array $attributes) => [
            'law_name' => 'Zakon o kaznenom postupku',
            'law_short' => 'ZKP',
        ]);
    }

    /**
     * Indicate the provision is for Ustav RH.
     */
    public function ustav(): static
    {
        return $this->state(fn (array $attributes) => [
            'law_name' => 'Ustav Republike Hrvatske',
            'law_short' => 'Ustav',
            'tags' => ['constitutional', 'ustavni_sud'],
        ]);
    }

    /**
     * Indicate provision relates to file access.
     */
    public function fileAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => array_merge($attributes['tags'] ?? [], ['file_access']),
        ]);
    }

    /**
     * Indicate provision relates to defence rights.
     */
    public function defenceRights(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => array_merge($attributes['tags'] ?? [], ['defence_rights']),
        ]);
    }

    /**
     * Indicate provision is for evidence exclusion arguments.
     */
    public function evidenceExclusion(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => array_merge($attributes['tags'] ?? [], ['izdvajanje_dokaza', 'evidence_exclusion']),
        ]);
    }
}
