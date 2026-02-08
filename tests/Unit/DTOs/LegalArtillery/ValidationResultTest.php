<?php

namespace Tests\Unit\DTOs\LegalArtillery;

use App\DTOs\LegalArtillery\ValidationResult;
use Tests\TestCase;

class ValidationResultTest extends TestCase
{
    public function test_can_create_validation_result(): void
    {
        $result = new ValidationResult(
            isValid: true,
            score: 8,
            issues: [['severity' => 'minor', 'description' => 'Test issue']],
            suggestions: ['Test suggestion'],
            strengths: ['Test strength'],
            verdict: 'STRONG',
            citationAccuracy: 9,
            argumentStrength: 8,
            logicalCoherence: 7,
        );

        $this->assertTrue($result->isValid);
        $this->assertEquals(8, $result->score);
        $this->assertEquals('STRONG', $result->verdict);
        $this->assertCount(1, $result->issues);
        $this->assertCount(1, $result->suggestions);
        $this->assertCount(1, $result->strengths);
        $this->assertEquals(9, $result->citationAccuracy);
        $this->assertEquals(8, $result->argumentStrength);
        $this->assertEquals(7, $result->logicalCoherence);
    }

    public function test_to_array(): void
    {
        $result = new ValidationResult(
            isValid: true,
            score: 8,
            issues: [['severity' => 'minor', 'description' => 'Test issue']],
            suggestions: ['Test suggestion'],
            strengths: ['Test strength'],
            verdict: 'STRONG',
            citationAccuracy: 9,
            argumentStrength: 8,
            logicalCoherence: 7,
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('is_valid', $array);
        $this->assertArrayHasKey('score', $array);
        $this->assertArrayHasKey('verdict', $array);
        $this->assertArrayHasKey('issues', $array);
        $this->assertArrayHasKey('suggestions', $array);
        $this->assertArrayHasKey('strengths', $array);
        $this->assertArrayHasKey('citation_accuracy', $array);
        $this->assertArrayHasKey('argument_strength', $array);
        $this->assertArrayHasKey('logical_coherence', $array);
        $this->assertTrue($array['is_valid']);
        $this->assertEquals(8, $array['score']);
    }

    public function test_from_array(): void
    {
        $data = [
            'is_valid' => true,
            'score' => 8,
            'issues' => [['severity' => 'minor', 'description' => 'Issue']],
            'suggestions' => ['Suggestion 1'],
            'strengths' => ['Strength 1', 'Strength 2'],
            'verdict' => 'STRONG',
            'citation_accuracy' => 9,
            'argument_strength' => 7,
            'logical_coherence' => 8,
        ];

        $result = ValidationResult::fromArray($data);

        $this->assertTrue($result->isValid);
        $this->assertEquals(8, $result->score);
        $this->assertEquals('STRONG', $result->verdict);
        $this->assertCount(1, $result->issues);
        $this->assertCount(1, $result->suggestions);
        $this->assertCount(2, $result->strengths);
    }

    public function test_from_array_uses_overall_score_alias(): void
    {
        $data = [
            'overall_score' => 7,
            'verdict' => 'STRONG',
            'citation_accuracy' => 8,
            'argument_strength' => 6,
            'logical_coherence' => 7,
        ];

        $result = ValidationResult::fromArray($data);

        $this->assertEquals(7, $result->score);
        $this->assertTrue($result->isValid); // 7 >= 6
    }

    public function test_from_array_uses_improvements_as_suggestions(): void
    {
        $data = [
            'score' => 7,
            'improvements' => ['Improve A', 'Improve B'],
        ];

        $result = ValidationResult::fromArray($data);

        $this->assertCount(2, $result->suggestions);
        $this->assertEquals('Improve A', $result->suggestions[0]);
    }

    public function test_parse_error(): void
    {
        $result = ValidationResult::parseError('Failed to parse JSON');

        $this->assertFalse($result->isValid);
        $this->assertEquals(0, $result->score);
        $this->assertEquals('PARSE_ERROR', $result->verdict);
        $this->assertCount(1, $result->issues);
        $this->assertEquals('critical', $result->issues[0]['severity']);
        $this->assertStringContainsString('Failed to parse JSON', $result->issues[0]['description']);
    }

    public function test_is_fire_ready(): void
    {
        $fireReady = new ValidationResult(
            isValid: true,
            score: 9,
            issues: [],
            suggestions: [],
            strengths: ['Perfect'],
            verdict: 'FIRE_READY',
            citationAccuracy: 10,
            argumentStrength: 9,
            logicalCoherence: 9,
        );

        $this->assertTrue($fireReady->isFireReady());

        $notFireReady = new ValidationResult(
            isValid: true,
            score: 7,
            issues: [],
            suggestions: [],
            strengths: ['Good'],
            verdict: 'STRONG',
            citationAccuracy: 8,
            argumentStrength: 7,
            logicalCoherence: 7,
        );

        $this->assertFalse($notFireReady->isFireReady());
    }

    public function test_has_critical_issues(): void
    {
        $withCritical = new ValidationResult(
            isValid: false,
            score: 4,
            issues: [
                ['severity' => 'critical', 'description' => 'Major problem'],
                ['severity' => 'minor', 'description' => 'Small issue'],
            ],
            suggestions: [],
            strengths: [],
            verdict: 'WEAK',
            citationAccuracy: 3,
            argumentStrength: 4,
            logicalCoherence: 5,
        );

        $this->assertTrue($withCritical->hasCriticalIssues());

        $withoutCritical = new ValidationResult(
            isValid: true,
            score: 7,
            issues: [['severity' => 'minor', 'description' => 'Small issue']],
            suggestions: [],
            strengths: [],
            verdict: 'STRONG',
            citationAccuracy: 8,
            argumentStrength: 7,
            logicalCoherence: 7,
        );

        $this->assertFalse($withoutCritical->hasCriticalIssues());
    }

    public function test_get_issue_counts(): void
    {
        $result = new ValidationResult(
            isValid: false,
            score: 4,
            issues: [
                ['severity' => 'critical', 'description' => 'Critical 1'],
                ['severity' => 'critical', 'description' => 'Critical 2'],
                ['severity' => 'major', 'description' => 'Major 1'],
                ['severity' => 'minor', 'description' => 'Minor 1'],
                ['severity' => 'minor', 'description' => 'Minor 2'],
                ['severity' => 'minor', 'description' => 'Minor 3'],
            ],
            suggestions: [],
            strengths: [],
            verdict: 'WEAK',
            citationAccuracy: 3,
            argumentStrength: 4,
            logicalCoherence: 5,
        );

        $counts = $result->getIssueCounts();

        $this->assertEquals(2, $counts['critical']);
        $this->assertEquals(1, $counts['major']);
        $this->assertEquals(3, $counts['minor']);
    }

    public function test_validity_threshold(): void
    {
        // Score 5 is below threshold (6)
        $lowScore = ValidationResult::fromArray(['score' => 5]);
        $this->assertFalse($lowScore->isValid);

        // Score 6 is at threshold
        $atThreshold = ValidationResult::fromArray(['score' => 6]);
        $this->assertTrue($atThreshold->isValid);

        // Score 7 is above threshold
        $highScore = ValidationResult::fromArray(['score' => 7]);
        $this->assertTrue($highScore->isValid);
    }

    public function test_verdict_calculation_from_score(): void
    {
        // Score 3 = WEAK
        $weak = ValidationResult::fromArray(['score' => 3]);
        $this->assertEquals('WEAK', $weak->verdict);

        // Score 5 = MODERATE
        $moderate = ValidationResult::fromArray(['score' => 5]);
        $this->assertEquals('MODERATE', $moderate->verdict);

        // Score 7 = STRONG
        $strong = ValidationResult::fromArray(['score' => 7]);
        $this->assertEquals('STRONG', $strong->verdict);

        // Score 8 = DEVASTATING
        $devastating = ValidationResult::fromArray(['score' => 8]);
        $this->assertEquals('DEVASTATING', $devastating->verdict);

        // Score 9 = FIRE_READY
        $fireReady = ValidationResult::fromArray(['score' => 9]);
        $this->assertEquals('FIRE_READY', $fireReady->verdict);
    }
}
