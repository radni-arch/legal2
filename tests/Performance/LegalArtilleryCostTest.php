<?php

namespace Tests\Performance;

use App\Services\LegalArtillery\LlmClient;
use Tests\TestCase;

class LegalArtilleryCostTest extends TestCase
{
    public function test_token_budget_checks_are_fast(): void
    {
        $client = new LlmClient('anthropic', 'test-model', 1024);
        $client->setBudget(100000);

        $iterations = 10000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $client->getRemainingBudget();
            $client->isBudgetExceeded();
        }

        $durationMs = (microtime(true) - $start) * 1000;

        $this->assertLessThan(
            250,
            $durationMs,
            "Budget checks took {$durationMs}ms for {$iterations} iterations"
        );
    }
}
