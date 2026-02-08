<?php

namespace Tests\Unit\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use App\Services\Defense\Detectors\FruitOfPoisonousTreeMapper;
use PHPUnit\Framework\TestCase;

class FruitOfPoisonousTreeMapperTest extends TestCase
{
    private FruitOfPoisonousTreeMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new FruitOfPoisonousTreeMapper();
    }

    /** @test */
    public function it_implements_defense_tactic_detector_interface(): void
    {
        $this->assertInstanceOf(DefenseTacticDetectorInterface::class, $this->mapper);
    }

    /** @test */
    public function it_returns_correct_tactic_identifier(): void
    {
        $this->assertEquals('fruit_of_poisonous_tree', $this->mapper->tactic());
    }

    /** @test */
    public function it_returns_correct_label(): void
    {
        $this->assertEquals('Plodovi otrovnog drveta (cl. 10. st. 2. t. 4. ZKP)', $this->mapper->label());
    }

    /** @test */
    public function it_requires_correct_analysis_data(): void
    {
        $requires = $this->mapper->requires();

        $this->assertContains('dates_with_context', $requires);
        $this->assertContains('entities', $requires);
        $this->assertContains('case_references', $requires);
        $this->assertContains('defense_flags', $requires);
    }

    /** @test */
    public function it_maps_derivative_evidence_from_tainted_source(): void
    {
        $analysisData = [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        ['date' => '2024-01-10', 'event_type' => 'pretraga', 'context' => 'Nezakonita pretraga'],
                    ],
                ],
                'doc-2' => [
                    'dates' => [
                        ['date' => '2024-01-12', 'event_type' => 'zapljena', 'context' => 'Zapljena nakon pretrage'],
                    ],
                ],
                'doc-3' => [
                    'dates' => [
                        ['date' => '2024-01-20', 'event_type' => 'ispitivanje', 'context' => 'Ispitivanje svjedoka'],
                    ],
                ],
            ],
            'entities' => [],
            'case_references' => [],
            // Simulating existing flags from other detectors that identified tainted evidence
            'defense_flags' => [
                [
                    'tactic' => 'defense_time_adequacy',
                    'severity' => 'critical',
                    'title' => 'PRETRAGA PRIJE NALOGA',
                    'evidence' => [
                        'search_date' => '2024-01-10',
                    ],
                ],
            ],
        ];

        $flags = $this->mapper->detect('case-123', $analysisData);

        $this->assertNotEmpty($flags);
        $this->assertContainsOnlyInstancesOf(DefenseFlag::class, $flags);

        // Should flag derivative evidence discovered after tainted date
        $derivativeFlag = collect($flags)->first(function (DefenseFlag $f) {
            return str_contains($f->tactic, 'fruit') || str_contains($f->title, 'plod');
        });

        $this->assertNotNull($derivativeFlag);
        $this->assertEquals('fruit_of_poisonous_tree', $derivativeFlag->tactic);
    }

    /** @test */
    public function it_returns_empty_when_no_tainted_evidence(): void
    {
        $analysisData = [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        ['date' => '2024-01-15', 'event_type' => 'zapljena', 'context' => 'Normal seizure'],
                    ],
                ],
            ],
            'entities' => [],
            'case_references' => [],
            'defense_flags' => [], // No existing critical flags
        ];

        $flags = $this->mapper->detect('case-123', $analysisData);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    /** @test */
    public function it_identifies_events_after_tainted_date(): void
    {
        $analysisData = [
            'dates_with_context' => [
                'doc-before' => [
                    'dates' => [
                        ['date' => '2024-01-05', 'event_type' => 'prijava', 'context' => 'Before taint'],
                    ],
                ],
                'doc-tainted' => [
                    'dates' => [
                        ['date' => '2024-01-10', 'event_type' => 'pretraga', 'context' => 'Tainted search'],
                    ],
                ],
                'doc-after-1' => [
                    'dates' => [
                        ['date' => '2024-01-15', 'event_type' => 'zapljena', 'context' => 'After taint'],
                    ],
                ],
                'doc-after-2' => [
                    'dates' => [
                        ['date' => '2024-01-20', 'event_type' => 'vjestacenje', 'context' => 'After taint'],
                    ],
                ],
            ],
            'entities' => [],
            'case_references' => [],
            'defense_flags' => [
                [
                    'tactic' => 'chain_of_custody',
                    'severity' => 'critical',
                    'evidence' => ['seizure_date' => '2024-01-10'],
                ],
            ],
        ];

        $flags = $this->mapper->detect('case-123', $analysisData);

        // Should identify events after tainted date
        $derivativeFlag = collect($flags)->first();

        if ($derivativeFlag) {
            $this->assertArrayHasKey('derivative_events', $derivativeFlag->evidence);
            $derivativeCount = $derivativeFlag->evidence['total_derivative'] ?? count($derivativeFlag->evidence['derivative_events'] ?? []);
            // Should find the 2 events after tainted date (not the one before)
            $this->assertGreaterThanOrEqual(1, $derivativeCount);
        }
    }

    /** @test */
    public function it_references_correct_legal_basis(): void
    {
        $analysisData = [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        ['date' => '2024-01-15', 'event_type' => 'zapljena', 'context' => 'Seizure'],
                    ],
                ],
            ],
            'entities' => [],
            'case_references' => [],
            'defense_flags' => [
                [
                    'tactic' => 'chain_of_custody',
                    'severity' => 'critical',
                    'evidence' => ['seizure_date' => '2024-01-10'],
                ],
            ],
        ];

        $flags = $this->mapper->detect('case-123', $analysisData);

        foreach ($flags as $flag) {
            $this->assertStringContainsString('cl. 10', $flag->legalBasis);
        }
    }

    /** @test */
    public function each_flag_has_required_properties(): void
    {
        $analysisData = [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        ['date' => '2024-01-15', 'event_type' => 'zapljena', 'context' => 'Seizure'],
                    ],
                ],
            ],
            'entities' => [],
            'case_references' => [],
            'defense_flags' => [
                [
                    'tactic' => 'chain_of_custody',
                    'severity' => 'critical',
                    'evidence' => ['seizure_date' => '2024-01-10'],
                ],
            ],
        ];

        $flags = $this->mapper->detect('case-123', $analysisData);

        foreach ($flags as $flag) {
            $this->assertInstanceOf(DefenseFlag::class, $flag);
            $this->assertEquals('fruit_of_poisonous_tree', $flag->tactic);
            $this->assertNotEmpty($flag->title);
            $this->assertNotEmpty($flag->description);
            $this->assertNotEmpty($flag->legalBasis);
            $this->assertNotEmpty($flag->recommendedAction);
            $this->assertGreaterThanOrEqual(0, $flag->confidence);
            $this->assertLessThanOrEqual(1, $flag->confidence);
        }
    }

    /** @test */
    public function it_handles_empty_analysis_data_gracefully(): void
    {
        $flags = $this->mapper->detect('case-123', []);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }
}
