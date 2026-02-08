<?php

namespace Tests\Unit\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use App\Services\Defense\Detectors\ConstitutionalViolationScanner;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ConstitutionalViolationScannerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ClaudeAnalysisService $claudeService;
    private ConstitutionalViolationScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->claudeService = Mockery::mock(ClaudeAnalysisService::class);
        $this->scanner = new ConstitutionalViolationScanner($this->claudeService);
    }

    public function test_implements_defense_tactic_detector_interface(): void
    {
        $this->assertInstanceOf(DefenseTacticDetectorInterface::class, $this->scanner);
    }

    public function test_tactic_returns_constitutional_violation(): void
    {
        $this->assertEquals('constitutional_violation', $this->scanner->tactic());
    }

    public function test_label_returns_croatian_label(): void
    {
        $label = $this->scanner->label();
        $this->assertStringContainsString('Ustav', $label);
    }

    public function test_requires_returns_needed_analysis_data(): void
    {
        $requires = $this->scanner->requires();

        $this->assertIsArray($requires);
        $this->assertContains('ai_key_facts', $requires);
        $this->assertContains('dates_with_context', $requires);
    }

    public function test_detect_returns_empty_array_when_no_data(): void
    {
        $flags = $this->scanner->detect('case-123', []);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    public function test_detect_identifies_dvorski_pattern_lawyer_blocked(): void
    {
        $analysisData = [
            'ai_key_facts' => [
                'doc-1' => [
                    'key_facts' => [
                        ['claim' => 'Okrivljenik je zatrazio branitelja prilikom uhicenja.'],
                        ['claim' => 'Ispitivanje je provedeno bez prisutnosti branitelja.'],
                    ],
                ],
            ],
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        ['event_type' => 'uhicenje', 'date' => '2024-01-15'],
                        ['event_type' => 'ispitivanje', 'date' => '2024-01-15'],
                    ],
                ],
            ],
        ];

        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'violations' => [
                        [
                            'pattern' => 'Dvorski',
                            'description' => 'Branitelj nije omogucen tijekom ispitivanja nakon uhicenja.',
                            'constitutional_basis' => 'cl. 29. st. 2. t. 3. Ustav RH',
                            'echr_basis' => 'Dvorski v. Croatia [GC] (2015)',
                            'severity' => 'high',
                            'confidence' => 0.85,
                        ],
                    ],
                ],
            ]);

        $flags = $this->scanner->detect('case-123', $analysisData);

        $this->assertNotEmpty($flags);
        $this->assertInstanceOf(DefenseFlag::class, $flags[0]);
        $this->assertEquals('constitutional_violation', $flags[0]->tactic);
        $this->assertStringContainsString('Dvorski', $flags[0]->echrBasis ?? $flags[0]->description);
    }

    public function test_detect_identifies_matanovic_pattern_surveillance_not_disclosed(): void
    {
        $analysisData = [
            'ai_key_facts' => [
                'doc-1' => [
                    'key_facts' => [
                        ['claim' => 'Prisluskivanje je provedeno ali obrana nije obavijestena.'],
                    ],
                ],
            ],
            'dates_with_context' => [],
        ];

        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'violations' => [
                        [
                            'pattern' => 'Matanovic',
                            'description' => 'Posebne dokazne radnje nisu otkrivene obrani.',
                            'constitutional_basis' => 'cl. 29. st. 2. Ustav RH',
                            'echr_basis' => 'Matanovic v. Croatia (2017)',
                            'severity' => 'high',
                            'confidence' => 0.75,
                        ],
                    ],
                ],
            ]);

        $flags = $this->scanner->detect('case-123', $analysisData);

        $this->assertNotEmpty($flags);
        $matanovicFlags = array_filter($flags, function ($f) {
            return str_contains($f->echrBasis ?? '', 'Matanovic') ||
                   str_contains($f->description, 'Matanovic');
        });
        $this->assertNotEmpty($matanovicFlags);
    }

    public function test_detect_identifies_kirincic_pattern_excessive_duration(): void
    {
        $analysisData = [
            'ai_key_facts' => [],
            'dates_with_context' => [
                'doc-1' => [
                    'dates' => [
                        ['event_type' => 'prijava', 'date' => '2010-01-01'],
                        ['event_type' => 'presuda', 'date' => '2024-01-01'],
                    ],
                ],
            ],
        ];

        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'violations' => [
                        [
                            'pattern' => 'Kirincic',
                            'description' => 'Postupak traje 14 godina - prekomjerno trajanje.',
                            'constitutional_basis' => 'cl. 29. st. 1. Ustav RH',
                            'echr_basis' => 'Kirincic i dr. v. Hrvatske (2020)',
                            'severity' => 'high',
                            'confidence' => 0.9,
                        ],
                    ],
                ],
            ]);

        $flags = $this->scanner->detect('case-123', $analysisData);

        $this->assertNotEmpty($flags);
    }

    public function test_detect_returns_multiple_violations(): void
    {
        $analysisData = [
            'ai_key_facts' => [
                'doc-1' => [
                    'key_facts' => [
                        ['claim' => 'Vise povreda.'],
                    ],
                ],
            ],
            'dates_with_context' => [],
        ];

        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'violations' => [
                        [
                            'pattern' => 'Violation1',
                            'description' => 'First violation',
                            'constitutional_basis' => 'cl. 29. Ustav RH',
                            'echr_basis' => 'Case1 v. Croatia',
                            'severity' => 'high',
                            'confidence' => 0.8,
                        ],
                        [
                            'pattern' => 'Violation2',
                            'description' => 'Second violation',
                            'constitutional_basis' => 'cl. 35. Ustav RH',
                            'echr_basis' => 'Case2 v. Croatia',
                            'severity' => 'medium',
                            'confidence' => 0.7,
                        ],
                    ],
                ],
            ]);

        $flags = $this->scanner->detect('case-123', $analysisData);

        $this->assertCount(2, $flags);
    }

    public function test_detect_gracefully_handles_api_errors(): void
    {
        $analysisData = [
            'ai_key_facts' => [
                'doc-1' => ['key_facts' => [['claim' => 'Test']]],
            ],
            'dates_with_context' => [],
        ];

        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andThrow(new \RuntimeException('API error'));

        // Should not throw, just return empty array
        $flags = $this->scanner->detect('case-123', $analysisData);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    public function test_detect_returns_no_flags_when_ai_finds_no_violations(): void
    {
        $analysisData = [
            'ai_key_facts' => [
                'doc-1' => ['key_facts' => [['claim' => 'Postupak je uredan.']]],
            ],
            'dates_with_context' => [],
        ];

        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'violations' => [],
                ],
            ]);

        $flags = $this->scanner->detect('case-123', $analysisData);

        $this->assertEmpty($flags);
    }

    public function test_detect_maps_ai_severity_to_defense_flag_severity(): void
    {
        $analysisData = [
            'ai_key_facts' => [
                'doc-1' => ['key_facts' => [['claim' => 'Test']]],
            ],
            'dates_with_context' => [],
        ];

        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'violations' => [
                        [
                            'pattern' => 'Critical',
                            'description' => 'Critical violation',
                            'constitutional_basis' => 'cl. 29. Ustav RH',
                            'echr_basis' => null,
                            'severity' => 'critical',
                            'confidence' => 0.95,
                        ],
                    ],
                ],
            ]);

        $flags = $this->scanner->detect('case-123', $analysisData);

        $this->assertNotEmpty($flags);
        $this->assertEquals(DefenseFlag::SEVERITY_CRITICAL, $flags[0]->severity);
    }

    public function test_detect_preserves_ai_confidence_scores(): void
    {
        $analysisData = [
            'ai_key_facts' => [
                'doc-1' => ['key_facts' => [['claim' => 'Test']]],
            ],
            'dates_with_context' => [],
        ];

        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'violations' => [
                        [
                            'pattern' => 'Test',
                            'description' => 'Test violation',
                            'constitutional_basis' => 'cl. 29. Ustav RH',
                            'echr_basis' => null,
                            'severity' => 'medium',
                            'confidence' => 0.73,
                        ],
                    ],
                ],
            ]);

        $flags = $this->scanner->detect('case-123', $analysisData);

        $this->assertEquals(0.73, $flags[0]->confidence);
    }
}
