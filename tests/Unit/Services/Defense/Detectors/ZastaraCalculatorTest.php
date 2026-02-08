<?php

namespace Tests\Unit\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Detectors\ZastaraCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class ZastaraCalculatorTest extends TestCase
{
    private ZastaraCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ZastaraCalculator();
    }

    /**
     * @test
     */
    public function it_implements_defense_tactic_detector_interface(): void
    {
        $this->assertInstanceOf(
            \App\Services\Defense\Contracts\DefenseTacticDetectorInterface::class,
            $this->calculator
        );
    }

    /**
     * @test
     */
    public function it_returns_correct_tactic_identifier(): void
    {
        $this->assertEquals('zastara', $this->calculator->tactic());
    }

    /**
     * @test
     */
    public function it_returns_croatian_label(): void
    {
        $this->assertEquals('Zastara kaznenog progona', $this->calculator->label());
    }

    /**
     * @test
     */
    public function it_requires_dates_with_context_case_references_and_entities(): void
    {
        $requires = $this->calculator->requires();

        $this->assertContains('dates_with_context', $requires);
        $this->assertContains('case_references', $requires);
        $this->assertContains('entities', $requires);
    }

    /**
     * @test
     */
    public function it_returns_info_flag_when_no_charged_offense_found(): void
    {
        $flags = $this->calculator->detect('case-123', [
            'entities' => [],
            'dates_with_context' => [],
        ]);

        $this->assertCount(1, $flags);
        $this->assertEquals(DefenseFlag::SEVERITY_INFO, $flags[0]->severity);
        $this->assertStringContainsString('nije identificirano', $flags[0]->title);
    }

    /**
     * @test
     */
    public function it_returns_info_flag_when_offense_date_not_found(): void
    {
        $flags = $this->calculator->detect('case-123', [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 190. st. 2. KZ'],
                ],
            ],
            'dates_with_context' => [],
        ]);

        $this->assertCount(1, $flags);
        $this->assertEquals(DefenseFlag::SEVERITY_INFO, $flags[0]->severity);
        $this->assertStringContainsString('Datum', $flags[0]->title);
    }

    /**
     * @test
     */
    public function it_detects_expired_absolute_zastara(): void
    {
        // Crime committed 25 years ago with max penalty 3 years
        // cl. 81 KZ: relative = 10 years, absolute = 20 years (both expired)
        $offenseDate = Carbon::now()->subYears(25);

        $flags = $this->calculator->detect('case-123', [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 228. st. 1. KZ'], // Kradja - max 3 years
                ],
            ],
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $offenseDate->format('Y-m-d'),
                            'event_type' => 'zapljena',
                            'context' => 'Test context',
                        ],
                    ],
                ],
            ],
        ]);

        $criticalFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_CRITICAL);

        $this->assertNotEmpty($criticalFlags);
        $flag = array_values($criticalFlags)[0];
        $this->assertStringContainsString('APSOLUTNA ZASTARA ISTEKLA', $flag->title);
        $this->assertStringContainsString('cl. 81.', $flag->legalBasis);
    }

    /**
     * @test
     */
    public function it_detects_expired_relative_zastara(): void
    {
        // Crime committed 15 years ago with max penalty 3 years
        // cl. 81 KZ: relative = 10 years (expired), absolute = 20 years (not expired)
        $offenseDate = Carbon::now()->subYears(15);

        $flags = $this->calculator->detect('case-123', [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 228. st. 1. KZ'], // Kradja - max 3 years
                ],
            ],
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $offenseDate->format('Y-m-d'),
                            'event_type' => 'zapljena',
                            'context' => 'Test context',
                        ],
                    ],
                ],
            ],
        ]);

        $highFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_HIGH);

        $this->assertNotEmpty($highFlags);
        $flag = array_values($highFlags)[0];
        $this->assertStringContainsString('Relativna zastara', $flag->title);
    }

    /**
     * @test
     */
    public function it_reports_approaching_absolute_zastara(): void
    {
        // Crime committed 19 years ago with max penalty 3 years
        // Absolute zastara = 20 years, so it's approaching
        $offenseDate = Carbon::now()->subYears(19)->subMonths(6);

        $flags = $this->calculator->detect('case-123', [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 228. st. 1. KZ'], // Kradja - max 3 years
                ],
            ],
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $offenseDate->format('Y-m-d'),
                            'event_type' => 'zapljena',
                            'context' => 'Test context',
                        ],
                    ],
                ],
            ],
        ]);

        $mediumFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_MEDIUM);

        $this->assertNotEmpty($mediumFlags);
        $flag = array_values($mediumFlags)[0];
        $this->assertStringContainsString('mjeseci', $flag->title);
    }

    /**
     * @test
     */
    public function it_handles_no_limitation_offenses(): void
    {
        // cl. 88-93, 97, 99, 352, 353 KZ have no zastara
        $offenseDate = Carbon::now()->subYears(50);

        $flags = $this->calculator->detect('case-123', [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 88. st. 1. KZ'], // Genocide - no limitation
                ],
            ],
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $offenseDate->format('Y-m-d'),
                            'event_type' => 'zapljena',
                            'context' => 'Test context',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $flags);
        $this->assertEquals(DefenseFlag::SEVERITY_INFO, $flags[0]->severity);
        $this->assertStringContainsString('bez zastare', $flags[0]->title);
    }

    /**
     * @test
     */
    public function it_calculates_correct_zastara_for_various_penalty_ranges(): void
    {
        // Test Article 190 st. 2 (drugs, large quantity) - max 15 years
        // Relative = 25 years, Absolute = 50 years
        $offenseDate = Carbon::now()->subYears(2);

        $flags = $this->calculator->detect('case-123', [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 190. st. 2. KZ'],
                ],
            ],
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $offenseDate->format('Y-m-d'),
                            'event_type' => 'zapljena',
                            'context' => 'Test context',
                        ],
                    ],
                ],
            ],
        ]);

        $infoFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_INFO);

        $this->assertNotEmpty($infoFlags);
        $flag = array_values($infoFlags)[0];

        // Check that evidence contains calculated years
        $this->assertArrayHasKey('relative_years', $flag->evidence);
        $this->assertEquals(25, $flag->evidence['relative_years']);
        $this->assertEquals(50, $flag->evidence['absolute_years']);
    }

    /**
     * @test
     */
    public function it_parses_law_reference_variations(): void
    {
        $offenseDate = Carbon::now()->subYears(2);

        // Test various reference formats
        $testCases = [
            'clanak 190. stavak 2. KZ',
            'cl. 190. st. 2. KZ',
            'clanku 190. st. 2. KZ',
        ];

        foreach ($testCases as $ref) {
            $flags = $this->calculator->detect('case-123', [
                'entities' => [
                    'doc-1' => [
                        'law_references' => [$ref],
                    ],
                ],
                'dates_with_context' => [
                    'doc-1' => [
                        'dates' => [
                            [
                                'date' => $offenseDate->format('Y-m-d'),
                                'event_type' => 'zapljena',
                                'context' => 'Test context',
                            ],
                        ],
                    ],
                ],
            ]);

            // Should not return "offense not identified"
            $hasIdentified = false;
            foreach ($flags as $flag) {
                if (strpos($flag->title, '190') !== false || strpos($flag->description, '190') !== false) {
                    $hasIdentified = true;
                    break;
                }
            }

            $this->assertTrue($hasIdentified, "Failed to parse: $ref");
        }
    }

    /**
     * @test
     */
    public function it_prioritizes_offense_date_by_event_type(): void
    {
        // When multiple dates exist, seizure/arrest dates should be prioritized
        $earlierDate = Carbon::now()->subYears(10);
        $seizureDate = Carbon::now()->subYears(5);

        $flags = $this->calculator->detect('case-123', [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 228. st. 1. KZ'],
                ],
            ],
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $earlierDate->format('Y-m-d'),
                            'event_type' => 'rociste', // Hearing - lower priority
                            'context' => 'Test context',
                        ],
                        [
                            'date' => $seizureDate->format('Y-m-d'),
                            'event_type' => 'zapljena', // Seizure - higher priority
                            'context' => 'Test context',
                        ],
                    ],
                ],
            ],
        ]);

        // Should use seizure date, not the earlier hearing date
        foreach ($flags as $flag) {
            if (isset($flag->evidence['offense_date'])) {
                $this->assertEquals($seizureDate->format('Y-m-d'), $flag->evidence['offense_date']);
                break;
            }
        }
    }

    /**
     * @test
     */
    public function it_returns_info_flag_for_unknown_article(): void
    {
        $offenseDate = Carbon::now()->subYears(2);

        $flags = $this->calculator->detect('case-123', [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 9999. st. 1. KZ'], // Unknown article
                ],
            ],
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $offenseDate->format('Y-m-d'),
                            'event_type' => 'zapljena',
                            'context' => 'Test context',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $flags);
        $this->assertEquals(DefenseFlag::SEVERITY_INFO, $flags[0]->severity);
        $this->assertStringContainsString('nije identificirano', $flags[0]->title);
    }

    /**
     * @test
     */
    public function it_sets_high_confidence_for_critical_flags(): void
    {
        $offenseDate = Carbon::now()->subYears(25);

        $flags = $this->calculator->detect('case-123', [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 228. st. 1. KZ'],
                ],
            ],
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $offenseDate->format('Y-m-d'),
                            'event_type' => 'zapljena',
                            'context' => 'Test context',
                        ],
                    ],
                ],
            ],
        ]);

        $criticalFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_CRITICAL);
        $this->assertNotEmpty($criticalFlags);

        $flag = array_values($criticalFlags)[0];
        $this->assertGreaterThanOrEqual(0.9, $flag->confidence);
    }
}
