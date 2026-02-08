<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class AiPricingConfigTest extends TestCase
{
    public function test_pricing_config_has_required_models(): void
    {
        $pricing = config('ai_pricing.models');

        $this->assertNotNull($pricing);
        $this->assertArrayHasKey('gpt-4o-mini', $pricing);
        $this->assertArrayHasKey('gpt-4o', $pricing);
        $this->assertArrayHasKey('text-embedding-3-small', $pricing);
    }

    public function test_model_has_input_and_output_costs(): void
    {
        $gpt4oMini = config('ai_pricing.models.gpt-4o-mini');

        $this->assertArrayHasKey('input_per_1m', $gpt4oMini);
        $this->assertArrayHasKey('output_per_1m', $gpt4oMini);
        $this->assertIsFloat($gpt4oMini['input_per_1m']);
    }

    public function test_default_model_is_set(): void
    {
        $default = config('ai_pricing.default_model');
        $this->assertEquals('gpt-4o-mini', $default);
    }

    public function test_cost_calculation_matches_previous_hardcoded_value(): void
    {
        $tokensUsed = 1000;
        $inputRate = config('ai_pricing.models.gpt-4o-mini.input_per_1m');
        $cost = ($tokensUsed / 1_000_000) * $inputRate;

        $this->assertEqualsWithDelta(0.00015, $cost, 0.00001);
    }
}
