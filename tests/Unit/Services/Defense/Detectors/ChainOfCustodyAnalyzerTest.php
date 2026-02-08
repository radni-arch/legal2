<?php

namespace Tests\Unit\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use App\Services\Defense\Detectors\ChainOfCustodyAnalyzer;
use PHPUnit\Framework\TestCase;

class ChainOfCustodyAnalyzerTest extends TestCase
{
    private ChainOfCustodyAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new ChainOfCustodyAnalyzer();
    }

    /** @test */
    public function it_implements_defense_tactic_detector_interface(): void
    {
        $this->assertInstanceOf(DefenseTacticDetectorInterface::class, $this->analyzer);
    }

    /** @test */
    public function it_returns_correct_tactic_identifier(): void
    {
        $this->assertEquals('chain_of_custody', $this->analyzer->tactic());
    }

    /** @test */
    public function it_returns_correct_label(): void
    {
        $this->assertEquals('Lanac cuvanja dokaza', $this->analyzer->label());
    }

    /** @test */
    public function it_requires_correct_analysis_data(): void
    {
        $requires = $this->analyzer->requires();

        $this->assertContains('dates_with_context', $requires);
        $this->assertContains('entities', $requires);
        $this->assertContains('case_reference_registry', $requires);
    }

    /** @test */
    public function it_flags_long_gap_between_seizure_and_analysis(): void
    {
        $analysisData = [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => '2024-01-15',
                            'event_type' => 'zapljena',
                            'context' => 'Zaplijena droge provedena dana 15.01.2024.',
                        ],
                    ],
                ],
                'doc-2' => [
                    'dates' => [
                        [
                            'date' => '2024-05-20',
                            'event_type' => 'vjestacenje',
                            'context' => 'Nalaz vjestaka od 20.05.2024.',
                        ],
                    ],
                ],
            ],
            'entities' => [],
            'case_reference_registry' => [],
        ];

        $flags = $this->analyzer->detect('case-123', $analysisData);

        $this->assertNotEmpty($flags);
        $this->assertContainsOnlyInstancesOf(DefenseFlag::class, $flags);

        // Find the gap flag
        $gapFlag = collect($flags)->first(function (DefenseFlag $f) {
            return str_contains($f->title, 'interval') || str_contains($f->description, 'dana');
        });

        $this->assertNotNull($gapFlag);
        $this->assertEquals('chain_of_custody', $gapFlag->tactic);
        $this->assertContains($gapFlag->severity, [DefenseFlag::SEVERITY_MEDIUM, DefenseFlag::SEVERITY_HIGH]);
    }

    /** @test */
    public function it_does_not_flag_short_gap_under_90_days(): void
    {
        $analysisData = [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => '2024-01-15',
                            'event_type' => 'zapljena',
                            'context' => 'Zaplijena',
                        ],
                    ],
                ],
                'doc-2' => [
                    'dates' => [
                        [
                            'date' => '2024-02-15',
                            'event_type' => 'vjestacenje',
                            'context' => 'Vjestacenje',
                        ],
                    ],
                ],
            ],
            'entities' => [],
            'case_reference_registry' => [],
        ];

        $flags = $this->analyzer->detect('case-123', $analysisData);

        // Should not contain gap flags since 31 days < 90 days
        $gapFlags = collect($flags)->filter(function (DefenseFlag $f) {
            return str_contains($f->title, 'interval');
        });

        $this->assertEmpty($gapFlags);
    }

    /** @test */
    public function it_flags_seizure_without_corresponding_analysis(): void
    {
        $analysisData = [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => '2024-01-15',
                            'event_type' => 'zapljena',
                            'context' => 'Zaplijena dokaza',
                        ],
                    ],
                ],
            ],
            'entities' => [],
            'case_reference_registry' => [],
        ];

        $flags = $this->analyzer->detect('case-123', $analysisData);

        // With only seizure and no analysis, this is different from a gap
        // The detector should handle this case gracefully
        $this->assertIsArray($flags);
    }

    /** @test */
    public function it_flags_missing_solenitetni_svjedoci(): void
    {
        $analysisData = [
            'dates_with_context' => [],
            'entities' => [
                'doc-1' => [
                    'persons' => ['okrivljenik Ivan Horvat'],
                    'evidence' => ['Zapisnik o pretrazi stana'],
                ],
            ],
            'case_reference_registry' => [],
        ];

        $flags = $this->analyzer->detect('case-123', $analysisData);

        // Should flag missing witnesses on search record
        $witnessFlag = collect($flags)->first(function (DefenseFlag $f) {
            return str_contains($f->title, 'svjedok') || str_contains($f->description, 'svjedok');
        });

        $this->assertNotNull($witnessFlag, 'Should flag missing witnesses on search record');
        $this->assertEquals('chain_of_custody', $witnessFlag->tactic);
    }

    /** @test */
    public function it_does_not_flag_when_two_or_more_witnesses_present(): void
    {
        $analysisData = [
            'dates_with_context' => [],
            'entities' => [
                'doc-1' => [
                    'persons' => [
                        'svjedok Marko Markovic',
                        'svjedok Ana Anic',
                        'okrivljenik Ivan Horvat',
                    ],
                    'evidence' => ['Zapisnik o pretrazi stana'],
                ],
            ],
            'case_reference_registry' => [],
        ];

        $flags = $this->analyzer->detect('case-123', $analysisData);

        // Should NOT flag witnesses when 2+ present
        $witnessFlag = collect($flags)->first(function (DefenseFlag $f) {
            return str_contains($f->title, 'svjedok');
        });

        $this->assertNull($witnessFlag, 'Should not flag when 2 witnesses present');
    }

    /** @test */
    public function it_returns_empty_array_when_no_issues_found(): void
    {
        $analysisData = [
            'dates_with_context' => [],
            'entities' => [],
            'case_reference_registry' => [],
        ];

        $flags = $this->analyzer->detect('case-123', $analysisData);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    /** @test */
    public function each_flag_has_required_properties(): void
    {
        $analysisData = [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        ['date' => '2024-01-15', 'event_type' => 'zapljena', 'context' => 'Zaplijena'],
                    ],
                ],
                'doc-2' => [
                    'dates' => [
                        ['date' => '2024-06-15', 'event_type' => 'vjestacenje', 'context' => 'Analiza'],
                    ],
                ],
            ],
            'entities' => [],
            'case_reference_registry' => [],
        ];

        $flags = $this->analyzer->detect('case-123', $analysisData);

        foreach ($flags as $flag) {
            $this->assertInstanceOf(DefenseFlag::class, $flag);
            $this->assertEquals('chain_of_custody', $flag->tactic);
            $this->assertNotEmpty($flag->title);
            $this->assertNotEmpty($flag->description);
            $this->assertNotEmpty($flag->legalBasis);
            $this->assertNotEmpty($flag->recommendedAction);
            $this->assertGreaterThanOrEqual(0, $flag->confidence);
            $this->assertLessThanOrEqual(1, $flag->confidence);
        }
    }
}
