<?php

namespace Tests\Unit\DTOs;

use App\DTOs\MatchingResult;
use Tests\TestCase;

class MatchingResultTest extends TestCase
{
    public function test_matching_result_tracks_statistics(): void
    {
        $result = new MatchingResult();

        $result->recordMatch(100);
        $result->recordMatch(80);
        $result->recordMatch(60);
        $result->recordNoMatch();
        $result->recordError('Test error');

        $this->assertEquals(3, $result->matchedCount);
        $this->assertEquals(1, $result->unmatchedCount);
        $this->assertEquals(1, $result->errorCount);
        $this->assertEquals(4, $result->processedCount);
        $this->assertContains('Test error', $result->errors);
    }

    public function test_matching_result_to_array(): void
    {
        $result = new MatchingResult();
        $result->recordMatch(100);

        $array = $result->toArray();

        $this->assertArrayHasKey('matched', $array);
        $this->assertArrayHasKey('unmatched', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('processed', $array);
    }
}
