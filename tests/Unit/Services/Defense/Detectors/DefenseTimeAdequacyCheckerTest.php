<?php

namespace Tests\Unit\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Detectors\DefenseTimeAdequacyChecker;
use Carbon\Carbon;
use Tests\TestCase;

class DefenseTimeAdequacyCheckerTest extends TestCase
{
    private DefenseTimeAdequacyChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new DefenseTimeAdequacyChecker();
    }

    /**
     * @test
     */
    public function it_implements_defense_tactic_detector_interface(): void
    {
        $this->assertInstanceOf(
            \App\Services\Defense\Contracts\DefenseTacticDetectorInterface::class,
            $this->checker
        );
    }

    /**
     * @test
     */
    public function it_returns_correct_tactic_identifier(): void
    {
        $this->assertEquals('defense_time_adequacy', $this->checker->tactic());
    }

    /**
     * @test
     */
    public function it_returns_croatian_label(): void
    {
        $this->assertEquals('Pripremljenost obrane / razumni rok', $this->checker->label());
    }

    /**
     * @test
     */
    public function it_requires_dates_with_context(): void
    {
        $requires = $this->checker->requires();

        $this->assertContains('dates_with_context', $requires);
    }

    /**
     * @test
     */
    public function it_returns_empty_array_when_no_date_data(): void
    {
        $flags = $this->checker->detect('case-123', []);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    /**
     * @test
     */
    public function it_detects_search_before_warrant_critical_violation(): void
    {
        // Search happened BEFORE warrant was issued
        $searchDate = Carbon::now()->subDays(10);
        $warrantDate = Carbon::now()->subDays(5);

        $flags = $this->checker->detect('case-123', [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $warrantDate->format('Y-m-d'),
                            'event_type' => 'nalog_izdavanje',
                            'context' => 'Nalog za pretragu',
                        ],
                    ],
                ],
                'doc-2' => [
                    'dates' => [
                        [
                            'date' => $searchDate->format('Y-m-d'),
                            'event_type' => 'pretraga',
                            'context' => 'Pretraga stana',
                        ],
                    ],
                ],
            ],
        ]);

        $criticalFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_CRITICAL);

        $this->assertNotEmpty($criticalFlags);
        $flag = array_values($criticalFlags)[0];
        $this->assertStringContainsString('PRETRAGA PRIJE NALOGA', $flag->title);
        $this->assertStringContainsString('cl. 246.', $flag->legalBasis);
    }

    /**
     * @test
     */
    public function it_detects_insufficient_indictment_to_hearing_time(): void
    {
        // Only 5 days between delivery and hearing (minimum should be 8)
        $deliveryDate = Carbon::now()->subDays(10);
        $hearingDate = Carbon::now()->subDays(5);

        $flags = $this->checker->detect('case-123', [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $deliveryDate->format('Y-m-d'),
                            'event_type' => 'dostava',
                            'context' => 'Dostava optuznice',
                        ],
                        [
                            'date' => $hearingDate->format('Y-m-d'),
                            'event_type' => 'rociste',
                            'context' => 'Glavna rasprava',
                        ],
                    ],
                ],
            ],
        ]);

        $highFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_HIGH);

        $this->assertNotEmpty($highFlags);
        $flag = array_values($highFlags)[0];
        $this->assertStringContainsString('Nedovoljan rok', $flag->title);
        $this->assertStringContainsString('cl. 374. ZKP', $flag->legalBasis);
    }

    /**
     * @test
     */
    public function it_detects_arrest_to_examination_exceeds_48h(): void
    {
        // Arrest to judicial examination took 3 days (should be max 2)
        $arrestDate = Carbon::now()->subDays(10);
        $examinationDate = Carbon::now()->subDays(7);

        $flags = $this->checker->detect('case-123', [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $arrestDate->format('Y-m-d'),
                            'event_type' => 'uhicenje',
                            'context' => 'Uhicenje osumnjicenika',
                        ],
                        [
                            'date' => $examinationDate->format('Y-m-d'),
                            'event_type' => 'ispitivanje',
                            'context' => 'Sudsko ispitivanje',
                        ],
                    ],
                ],
            ],
        ]);

        $criticalFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_CRITICAL);

        $this->assertNotEmpty($criticalFlags);
        $flag = array_values($criticalFlags)[0];
        $this->assertStringContainsString('Nedovoljan rok', $flag->title);
        $this->assertStringContainsString('cl. 112. ZKP', $flag->legalBasis);
    }

    /**
     * @test
     */
    public function it_detects_echr_clear_violation_for_very_long_proceedings(): void
    {
        // Proceedings lasting 16 years (clear violation threshold is 15)
        $earliestDate = Carbon::now()->subYears(16);
        $latestDate = Carbon::now();

        $flags = $this->checker->detect('case-123', [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $earliestDate->format('Y-m-d'),
                            'event_type' => 'prijava',
                            'context' => 'Kaznena prijava',
                        ],
                        [
                            'date' => $latestDate->format('Y-m-d'),
                            'event_type' => 'rociste',
                            'context' => 'Nastavak rasprave',
                        ],
                    ],
                ],
            ],
        ]);

        $highFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_HIGH);

        $this->assertNotEmpty($highFlags);
        $flag = array_values($highFlags)[0];
        $this->assertStringContainsString('16 godina', $flag->title);
        $this->assertStringContainsString('ECHR', $flag->echrBasis);
    }

    /**
     * @test
     */
    public function it_detects_echr_warning_for_proceedings_approaching_violation(): void
    {
        // Proceedings lasting 6 years (warning threshold is 5)
        $earliestDate = Carbon::now()->subYears(6);
        $latestDate = Carbon::now();

        $flags = $this->checker->detect('case-123', [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $earliestDate->format('Y-m-d'),
                            'event_type' => 'prijava',
                            'context' => 'Kaznena prijava',
                        ],
                        [
                            'date' => $latestDate->format('Y-m-d'),
                            'event_type' => 'rociste',
                            'context' => 'Nastavak rasprave',
                        ],
                    ],
                ],
            ],
        ]);

        $mediumFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_MEDIUM);

        $this->assertNotEmpty($mediumFlags);
        $flag = array_values($mediumFlags)[0];
        $this->assertStringContainsString('6 godina', $flag->title);
        $this->assertStringContainsString('ECHR', $flag->echrBasis);
    }

    /**
     * @test
     */
    public function it_does_not_flag_adequate_indictment_to_hearing_time(): void
    {
        // 15 days between delivery and hearing (minimum is 8, so this is adequate)
        $deliveryDate = Carbon::now()->subDays(20);
        $hearingDate = Carbon::now()->subDays(5);

        $flags = $this->checker->detect('case-123', [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $deliveryDate->format('Y-m-d'),
                            'event_type' => 'dostava',
                            'context' => 'Dostava optuznice',
                        ],
                        [
                            'date' => $hearingDate->format('Y-m-d'),
                            'event_type' => 'rociste',
                            'context' => 'Glavna rasprava',
                        ],
                    ],
                ],
            ],
        ]);

        // Should not have HIGH flags for inadequate time
        $highFlags = array_filter($flags, fn($f) =>
            $f->severity === DefenseFlag::SEVERITY_HIGH &&
            strpos($f->title, 'Nedovoljan rok') !== false
        );

        $this->assertEmpty($highFlags);
    }

    /**
     * @test
     */
    public function it_handles_events_from_multiple_documents(): void
    {
        // Events spread across documents
        $warrantDate = Carbon::now()->subDays(5);
        $searchDate = Carbon::now()->subDays(3);

        $flags = $this->checker->detect('case-123', [
            'dates_with_context' => [
                'doc-warrant' => [
                    'dates' => [
                        [
                            'date' => $warrantDate->format('Y-m-d'),
                            'event_type' => 'nalog_izdavanje',
                            'context' => 'Nalog za pretragu',
                        ],
                    ],
                ],
                'doc-search' => [
                    'dates' => [
                        [
                            'date' => $searchDate->format('Y-m-d'),
                            'event_type' => 'pretraga',
                            'context' => 'Pretraga provedena',
                        ],
                    ],
                ],
            ],
        ]);

        // Warrant before search is valid, should not flag
        $criticalFlags = array_filter($flags, fn($f) =>
            $f->severity === DefenseFlag::SEVERITY_CRITICAL &&
            strpos($f->title, 'PRETRAGA PRIJE NALOGA') !== false
        );

        $this->assertEmpty($criticalFlags);
    }

    /**
     * @test
     */
    public function it_includes_evidence_with_document_ids(): void
    {
        $searchDate = Carbon::now()->subDays(10);
        $warrantDate = Carbon::now()->subDays(5);

        $flags = $this->checker->detect('case-123', [
            'dates_with_context' => [
                'doc-warrant' => [
                    'dates' => [
                        [
                            'date' => $warrantDate->format('Y-m-d'),
                            'event_type' => 'nalog_izdavanje',
                            'context' => 'Nalog za pretragu',
                        ],
                    ],
                ],
                'doc-search' => [
                    'dates' => [
                        [
                            'date' => $searchDate->format('Y-m-d'),
                            'event_type' => 'pretraga',
                            'context' => 'Pretraga provedena',
                        ],
                    ],
                ],
            ],
        ]);

        $criticalFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_CRITICAL);
        $this->assertNotEmpty($criticalFlags);

        $flag = array_values($criticalFlags)[0];
        $this->assertArrayHasKey('warrant_doc', $flag->evidence);
        $this->assertArrayHasKey('search_doc', $flag->evidence);
    }

    /**
     * @test
     */
    public function it_sets_appropriate_confidence_levels(): void
    {
        $searchDate = Carbon::now()->subDays(10);
        $warrantDate = Carbon::now()->subDays(5);

        $flags = $this->checker->detect('case-123', [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $warrantDate->format('Y-m-d'),
                            'event_type' => 'nalog_izdavanje',
                            'context' => 'Nalog',
                        ],
                        [
                            'date' => $searchDate->format('Y-m-d'),
                            'event_type' => 'pretraga',
                            'context' => 'Pretraga',
                        ],
                    ],
                ],
            ],
        ]);

        $criticalFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_CRITICAL);
        $this->assertNotEmpty($criticalFlags);

        $flag = array_values($criticalFlags)[0];
        // Critical flags should have high confidence
        $this->assertGreaterThanOrEqual(0.85, $flag->confidence);
    }

    /**
     * @test
     */
    public function it_references_echr_case_law(): void
    {
        $deliveryDate = Carbon::now()->subDays(5);
        $hearingDate = Carbon::now()->subDays(2);

        $flags = $this->checker->detect('case-123', [
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        [
                            'date' => $deliveryDate->format('Y-m-d'),
                            'event_type' => 'dostava',
                            'context' => 'Dostava',
                        ],
                        [
                            'date' => $hearingDate->format('Y-m-d'),
                            'event_type' => 'rociste',
                            'context' => 'Rociste',
                        ],
                    ],
                ],
            ],
        ]);

        $highFlags = array_filter($flags, fn($f) => $f->severity === DefenseFlag::SEVERITY_HIGH);
        $this->assertNotEmpty($highFlags);

        $flag = array_values($highFlags)[0];
        $this->assertNotNull($flag->echrBasis);
        $this->assertStringContainsString('Dvorski', $flag->echrBasis);
    }
}
