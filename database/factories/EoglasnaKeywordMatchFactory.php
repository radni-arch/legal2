<?php

namespace Database\Factories;

use App\Models\EoglasnaKeyword;
use App\Models\EoglasnaKeywordMatch;
use App\Models\EoglasnaNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

class EoglasnaKeywordMatchFactory extends Factory
{
    protected $model = EoglasnaKeywordMatch::class;

    public function definition(): array
    {
        return [
            'keyword_id' => EoglasnaKeyword::factory(),
            'notice_uuid' => function () {
                return EoglasnaNotice::factory()->create()->uuid;
            },
            'matched_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
        ];
    }
}
