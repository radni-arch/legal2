<?php

namespace Database\Factories;

use App\Models\ResearchSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResearchSessionFactory extends Factory
{
    protected $model = ResearchSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => null,
            'description' => null,
            'viewed_nodes' => [],
            'pinned_nodes' => [],
            'expanded_nodes' => [],
            'alerts' => [],
            'root_node_id' => null,
            'filter_settings' => null,
            'last_activity_at' => now(),
        ];
    }

    public function named(string $name = null): self
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name ?? fake()->sentence(3),
        ]);
    }

    public function withActivity(): self
    {
        return $this->state(fn (array $attributes) => [
            'viewed_nodes' => [
                [
                    'id' => 'node-1',
                    'type' => 'Case',
                    'label' => 'Test Case',
                    'viewed_at' => now()->toISOString(),
                ],
            ],
        ]);
    }
}
