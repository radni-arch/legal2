<?php

namespace Tests\Unit\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use App\Services\Defense\Detectors\ProportionalityChecker;
use PHPUnit\Framework\TestCase;

class ProportionalityCheckerTest extends TestCase
{
    private ProportionalityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new ProportionalityChecker();
    }

    public function test_implements_defense_tactic_detector_interface(): void
    {
        $this->assertInstanceOf(DefenseTacticDetectorInterface::class, $this->checker);
    }

    public function test_tactic_returns_proportionality(): void
    {
        $this->assertEquals('proportionality', $this->checker->tactic());
    }

    public function test_label_returns_croatian_label(): void
    {
        $this->assertStringContainsString('Razmjernost', $this->checker->label());
    }

    public function test_requires_returns_needed_analysis_data(): void
    {
        $requires = $this->checker->requires();

        $this->assertIsArray($requires);
        $this->assertContains('entities', $requires);
        $this->assertContains('dates_with_context', $requires);
    }

    public function test_detect_returns_empty_array_when_no_data(): void
    {
        $flags = $this->checker->detect('case-123', []);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    public function test_detect_flags_surveillance_for_minor_offense(): void
    {
        // Offense under 10 years max (e.g., cl. 190. st. 4 - possession = max 3 years)
        // but surveillance/wiretapping was used
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 190. st. 4. KZ'], // Max 3 years
                ],
                'doc-2' => [
                    'measures' => ['posebne dokazne radnje', 'prisluskivanje'],
                ],
            ],
            'dates_with_context' => [
                'doc-2' => [
                    'dates' => [
                        ['event_type' => 'prisluskivanje', 'date' => '2024-01-15'],
                    ],
                ],
            ],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        $this->assertNotEmpty($flags);
        $this->assertInstanceOf(DefenseFlag::class, $flags[0]);
        $this->assertEquals('proportionality', $flags[0]->tactic);
        $this->assertStringContainsString('prisluskivanje', mb_strtolower($flags[0]->title));
    }

    public function test_detect_flags_home_search_for_possession_offense(): void
    {
        // Possession offense (st. 4) with invasive home search
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 190. st. 4. KZ'], // Possession only
                ],
            ],
            'dates_with_context' => [
                'doc-2' => [
                    'dates' => [
                        ['event_type' => 'pretraga', 'date' => '2024-01-15', 'context' => 'pretraga stana'],
                    ],
                ],
            ],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        $proportionalityFlags = array_filter($flags, function ($flag) {
            return str_contains(mb_strtolower($flag->title), 'pretraga') ||
                   str_contains(mb_strtolower($flag->description), 'pretraga');
        });

        $this->assertNotEmpty($proportionalityFlags);
    }

    public function test_detect_does_not_flag_surveillance_for_serious_offense(): void
    {
        // Serious offense (organized trafficking, max 12+ years) - surveillance is proportionate
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 190. st. 3. KZ'], // Organized, max 12 years
                ],
                'doc-2' => [
                    'measures' => ['posebne dokazne radnje', 'prisluskivanje'],
                ],
            ],
            'dates_with_context' => [
                'doc-2' => [
                    'dates' => [
                        ['event_type' => 'prisluskivanje', 'date' => '2024-01-15'],
                    ],
                ],
            ],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        // Should not flag surveillance for serious offense
        $surveillanceFlags = array_filter($flags, function ($flag) {
            return str_contains(mb_strtolower($flag->title), 'prisluskivanje');
        });

        $this->assertEmpty($surveillanceFlags);
    }

    public function test_detect_flags_multiple_invasive_measures_for_minor_offense(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 228. st. 1. KZ'], // Theft, max 3 years
                ],
            ],
            'dates_with_context' => [
                'doc-2' => [
                    'dates' => [
                        ['event_type' => 'pretraga', 'date' => '2024-01-15'],
                        ['event_type' => 'prisluskivanje', 'date' => '2024-01-10'],
                    ],
                ],
            ],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        // Should flag disproportionate measures
        $this->assertNotEmpty($flags);
    }

    public function test_detect_returns_proper_legal_basis(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 190. st. 4. KZ'],
                ],
            ],
            'dates_with_context' => [
                'doc-2' => [
                    'dates' => [
                        ['event_type' => 'prisluskivanje', 'date' => '2024-01-15'],
                    ],
                ],
            ],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        if (!empty($flags)) {
            $flag = $flags[0];
            $this->assertStringContainsString('cl.', $flag->legalBasis);
            $this->assertIsArray($flag->evidence);
        }
    }

    public function test_detect_evaluates_offense_severity_correctly(): void
    {
        // Test with a known offense penalty mapping
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 236. st. 1. KZ'], // Fraud, max 3 years
                ],
            ],
            'dates_with_context' => [
                'doc-2' => [
                    'dates' => [
                        ['event_type' => 'prisluskivanje', 'date' => '2024-01-15'],
                    ],
                ],
            ],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        // Minor offense + surveillance = disproportionate
        $this->assertNotEmpty($flags);
        $this->assertEquals(DefenseFlag::SEVERITY_MEDIUM, $flags[0]->severity);
    }

    public function test_detect_returns_confidence_level(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'law_references' => ['cl. 190. st. 4. KZ'],
                ],
            ],
            'dates_with_context' => [
                'doc-2' => [
                    'dates' => [
                        ['event_type' => 'prisluskivanje', 'date' => '2024-01-15'],
                    ],
                ],
            ],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        if (!empty($flags)) {
            $this->assertGreaterThan(0, $flags[0]->confidence);
            $this->assertLessThanOrEqual(1, $flags[0]->confidence);
        }
    }
}
