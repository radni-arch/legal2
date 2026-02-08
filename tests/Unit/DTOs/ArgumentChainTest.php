<?php

namespace Tests\Unit\DTOs;

use App\DTOs\ArgumentChain;
use PHPUnit\Framework\TestCase;

class ArgumentChainTest extends TestCase
{
    public function test_can_be_constructed_with_required_properties(): void
    {
        $steps = [
            ['label' => 'Step 1', 'argument' => 'First argument'],
            ['label' => 'Step 2', 'argument' => 'Second argument'],
        ];

        $chain = new ArgumentChain(
            name: 'exhaustion_impossibility',
            displayName: 'Ne mozete iscrpiti nepostojece',
            steps: $steps,
            killerSummary: 'Court cannot require exhaustion of nonexistent remedies.',
            profileKeys: ['supreme_court', 'constitutional_court'],
        );

        $this->assertEquals('exhaustion_impossibility', $chain->name);
        $this->assertEquals('Ne mozete iscrpiti nepostojece', $chain->displayName);
        $this->assertCount(2, $chain->steps);
        $this->assertEquals('Court cannot require exhaustion of nonexistent remedies.', $chain->killerSummary);
        $this->assertEquals(['supreme_court', 'constitutional_court'], $chain->profileKeys);
    }

    public function test_fromArray_creates_instance_correctly(): void
    {
        $data = [
            'name' => 'fruit_of_poison_tree',
            'display_name' => 'Plod otrovnog drveta',
            'steps' => [
                ['label' => 'Premise', 'argument' => 'Invalid warrant'],
                ['label' => 'Conclusion', 'argument' => 'Evidence inadmissible'],
            ],
            'killer_summary' => 'All evidence from invalid search is fruit of poisoned tree.',
            'profile_keys' => ['county_court'],
        ];

        $chain = ArgumentChain::fromArray($data);

        $this->assertEquals('fruit_of_poison_tree', $chain->name);
        $this->assertEquals('Plod otrovnog drveta', $chain->displayName);
        $this->assertCount(2, $chain->steps);
        $this->assertEquals('All evidence from invalid search is fruit of poisoned tree.', $chain->killerSummary);
        $this->assertEquals(['county_court'], $chain->profileKeys);
    }

    public function test_toArray_returns_correct_structure(): void
    {
        $steps = [
            ['label' => 'A', 'argument' => 'Arg A'],
        ];

        $chain = new ArgumentChain(
            name: 'test_chain',
            displayName: 'Test Chain',
            steps: $steps,
            killerSummary: 'Test summary',
            profileKeys: ['profile1'],
        );

        $array = $chain->toArray();

        $this->assertEquals('test_chain', $array['name']);
        $this->assertEquals('Test Chain', $array['display_name']);
        $this->assertEquals($steps, $array['steps']);
        $this->assertEquals('Test summary', $array['killer_summary']);
        $this->assertEquals(['profile1'], $array['profile_keys']);
    }

    public function test_appliesToProfile_returns_true_for_matching_profile(): void
    {
        $chain = new ArgumentChain(
            name: 'test',
            displayName: 'Test',
            steps: [],
            killerSummary: 'Test',
            profileKeys: ['supreme_court', 'county_court'],
        );

        $this->assertTrue($chain->appliesToProfile('supreme_court'));
        $this->assertTrue($chain->appliesToProfile('county_court'));
    }

    public function test_appliesToProfile_returns_false_for_non_matching_profile(): void
    {
        $chain = new ArgumentChain(
            name: 'test',
            displayName: 'Test',
            steps: [],
            killerSummary: 'Test',
            profileKeys: ['supreme_court'],
        );

        $this->assertFalse($chain->appliesToProfile('constitutional_court'));
    }

    public function test_formatForLLM_returns_formatted_string(): void
    {
        $chain = new ArgumentChain(
            name: 'test_chain',
            displayName: 'Test Chain Display',
            steps: [
                ['label' => 'Premise 1', 'argument' => 'First premise argument'],
                ['label' => 'Premise 2', 'argument' => 'Second premise argument'],
                ['label' => 'Conclusion', 'argument' => 'Final conclusion'],
            ],
            killerSummary: 'This is the killer summary.',
            profileKeys: ['test_profile'],
        );

        $formatted = $chain->formatForLLM();

        $this->assertStringContainsString('Test Chain Display', $formatted);
        $this->assertStringContainsString('Premise 1', $formatted);
        $this->assertStringContainsString('First premise argument', $formatted);
        $this->assertStringContainsString('Premise 2', $formatted);
        $this->assertStringContainsString('Second premise argument', $formatted);
        $this->assertStringContainsString('Conclusion', $formatted);
        $this->assertStringContainsString('Final conclusion', $formatted);
        $this->assertStringContainsString('This is the killer summary.', $formatted);
    }
}
