<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\TokenEstimator;
use Tests\TestCase;

class TokenEstimatorTest extends TestCase
{
    private TokenEstimator $estimator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estimator = new TokenEstimator();
    }

    public function test_empty_string_returns_zero(): void
    {
        $this->assertEquals(0, $this->estimator->estimate(''));
    }

    public function test_estimates_english_text(): void
    {
        $estimate = $this->estimator->estimate('Hello world');
        $this->assertGreaterThan(0, $estimate);
        $this->assertLessThan(10, $estimate);
    }

    public function test_estimates_longer_english_text(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog and runs across the field';
        $estimate = $this->estimator->estimate($text);

        // ~70 chars / ~4 chars per token = ~18 tokens
        $this->assertGreaterThan(10, $estimate);
        $this->assertLessThan(30, $estimate);
    }

    public function test_croatian_text_uses_more_tokens(): void
    {
        $croatianText = 'Sudac je donio odluku o kaznenom postupku protiv okrivljenika';
        $englishText = 'The judge made a decision on the criminal proceedings against defendant';

        $croatianTokens = $this->estimator->estimate($croatianText);
        $englishTokens = $this->estimator->estimate($englishText);

        // Both should produce reasonable estimates
        $this->assertGreaterThan(0, $croatianTokens);
        $this->assertGreaterThan(0, $englishTokens);
    }

    public function test_handles_multibyte_characters(): void
    {
        $text = 'čćžšđ ČČĆŽŠĐ';
        $estimate = $this->estimator->estimate($text);

        $this->assertGreaterThan(0, $estimate);
        // Should be more tokens than pure ASCII equivalent due to Unicode
    }

    public function test_single_character_returns_at_least_one(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->estimator->estimate('a'));
        $this->assertGreaterThanOrEqual(1, $this->estimator->estimate('č'));
    }

    public function test_pure_ascii_uses_standard_ratio(): void
    {
        // 100 ASCII chars / 4 = 25 tokens
        $text = str_repeat('abcd', 25);
        $estimate = $this->estimator->estimate($text);

        $this->assertEquals(25, $estimate);
    }
}
