<?php

namespace Database\Factories;

use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LegalCaseFactory extends Factory
{
    protected $model = LegalCase::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'case_number' => 'CASE-'.fake()->year().'-'.fake()->numberBetween(1000, 9999),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'jurisdiction' => 'HR',
            'filing_date' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'tags' => fake()->randomElements(
                ['contract', 'commercial', 'civil', 'labor', 'administrative', 'tax'],
                fake()->numberBetween(1, 3)
            ),
            'court' => fake()->randomElement([
                'Vrhovni sud Republike Hrvatske',
                'Visoki trgovački sud',
                'Županijski sud u Zagrebu',
                'Općinski sud u Zagrebu',
            ]),
            'status' => fake()->randomElement(['active', 'pending', 'closed', 'archived']),
        ];
    }

    /**
     * Indicate an active case
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',

        ]);
    }

    /**
     * Indicate a closed case
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'id' => $this->faker->uuid(),
            'case_number' => $this->faker->unique()->numerify('K-####/####'),
            'title' => $this->faker->sentence(),
            'client_name' => $this->faker->name(),
            'opponent_name' => $this->faker->name(),
            'court' => $this->faker->randomElement(['VSRH', 'VTS', 'VTZ', 'VTSS', 'ŽS Zagreb', 'ŽS Split']),
            'jurisdiction' => $this->faker->randomElement(['criminal', 'civil', 'administrative']),
            'judge' => $this->faker->name(),
            'filing_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            // 'status' => $this->faker->randomElement(['active', 'pending', 'closed', 'appealed']),
            'tags' => $this->faker->randomElements(['criminal', 'civil', 'fraud', 'theft', 'assault'], rand(1, 3)),
            'description' => $this->faker->paragraph(),
        ]);
    }

    //    /**
    //     * Indicate a closed case
    //     */
    //    public function closed(): static
    //    {
    //        return $this->state(function (array $attributes) {
    //            $filingDate = $attributes['filing_date'] ?? now()->subMonths(6);
    //
    //            return [
    //                'status' => 'closed',
    //                'close_date' => fake()->dateTimeBetween($filingDate, 'now'),
    //                'outcome' => fake()->randomElement([
    //                    'Ruling in favor of plaintiff',
    //                    'Ruling in favor of defendant',
    //                    'Settlement reached',
    //                    'Case dismissed',
    //                ]),
    //            ];
    //        });
    //    }

    /**
     * Indicate a pending case
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'filing_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate a case with Croatian parties
     */
    public function croatian(): static
    {
        $croatianFirstNames = ['Marko', 'Ivana', 'Petar', 'Ana', 'Josip', 'Maja', 'Ivan', 'Luka'];
        $croatianLastNames = ['Horvat', 'Marković', 'Kovačević', 'Babić', 'Novak', 'Jurić', 'Knežević'];

        return $this->state(fn (array $attributes) => [
            'court' => fake()->randomElement([
                'Visoki trgovački sud Republike Hrvatske',
                'Vrhovni sud Republike Hrvatske',
                'Općinski sud u Zagrebu',
                'Županijski sud u Zagrebu',
            ]),
            'title' => 'Građanski postupak - '.fake()->randomElement([
                'Tužba zbog povrede ugovora',
                'Radni spor',
                'Ugovor o najmu',
                'Naknada štete',
            ]),
        ]);
    }

    /**
     * Indicate a commercial case
     */
    public function commercial(): static
    {
        return $this->state(fn (array $attributes) => [
            'court' => 'High Commercial Court of the Republic of Croatia',
            'tags' => ['commercial', 'contract', 'business'],
            'title' => 'Commercial Dispute - '.fake()->companySuffix(),
        ]);
    }

    /**
     * Indicate a labor law case
     */
    public function labor(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => ['labor', 'employment', 'workplace'],
            'title' => 'Labor Dispute - '.fake()->randomElement([
                'Wrongful Termination',
                'Wage Dispute',
                'Discrimination Claim',
                'Contract Violation',
            ]),
        ]);
    }

    /**
     * Indicate a case in EU jurisdiction
     */
    public function eu(): static
    {
        return $this->state(fn (array $attributes) => [
            'jurisdiction' => 'EU',
            'court' => fake()->randomElement([
                'Court of Justice of the European Union',
                'European Court of Human Rights',
            ]),
            'tags' => ['EU', 'international', 'cross-border'],
        ]);
    }

    /**
     * Indicate a high-priority urgent case
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => array_merge($attributes['tags'] ?? [], ['urgent', 'priority']),
            'filing_date' => now()->subDays(7),
        ]);
    }

    /**
     * Indicate a case with outcome
     */
    public function withOutcome(?string $outcome = null): static
    {
        return $this->state(function (array $attributes) use ($outcome) {
            $filingDate = $attributes['filing_date'] ?? now()->subMonths(6);

            return [
                'status' => 'closed',
                'outcome' => $outcome ?? 'Ruling in favor of plaintiff',
            ];
        });
    }

    /**
     * Indicate a recent case
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'filing_date' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate an old case
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'filing_date' => fake()->dateTimeBetween('-5 years', '-2 years'),
        ]);
    }

    /**
     * Indicate a criminal case
     */
    public function criminal(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => array_merge($attributes['tags'] ?? [], ['criminal']),
        ]);
    }

    /**
     * Indicate a civil case
     */
    public function civil(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => array_merge($attributes['tags'] ?? [], ['civil']),
        ]);
    }

    public function withEvidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => 'Case with substantial evidence including physical and digital evidence.',
        ]);
    }
}
