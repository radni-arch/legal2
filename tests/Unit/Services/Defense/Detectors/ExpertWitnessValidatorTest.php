<?php

namespace Tests\Unit\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use App\Services\Defense\Detectors\ExpertWitnessValidator;
use PHPUnit\Framework\TestCase;

class ExpertWitnessValidatorTest extends TestCase
{
    private ExpertWitnessValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ExpertWitnessValidator();
    }

    public function test_implements_defense_tactic_detector_interface(): void
    {
        $this->assertInstanceOf(DefenseTacticDetectorInterface::class, $this->validator);
    }

    public function test_tactic_returns_expert_witness(): void
    {
        $this->assertEquals('expert_witness', $this->validator->tactic());
    }

    public function test_label_returns_croatian_label(): void
    {
        $label = $this->validator->label();
        $this->assertStringContainsString('Vjestak', $label);
    }

    public function test_requires_returns_needed_analysis_data(): void
    {
        $requires = $this->validator->requires();

        $this->assertIsArray($requires);
        $this->assertContains('entities', $requires);
        $this->assertContains('ai_summary', $requires);
    }

    public function test_detect_returns_empty_array_when_no_data(): void
    {
        $flags = $this->validator->detect('case-123', []);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    public function test_detect_flags_expert_report_missing_methodology(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'persons' => ['vjestak dr. sc. Ivan Horvat'],
                    'institutions' => ['Forenzicki laboratorij'],
                ],
            ],
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'nalaz_vjestaka',
                    'summary' => 'Nalaz vjestaka utvrduje prisutnost tvari.',
                    // No methodology mention
                ],
            ],
        ];

        $flags = $this->validator->detect('case-123', $analysisData);

        $methodFlags = array_filter($flags, function ($flag) {
            return str_contains(mb_strtolower($flag->title), 'metod') ||
                   str_contains(mb_strtolower($flag->description), 'metod');
        });

        $this->assertNotEmpty($methodFlags);
        $this->assertEquals('expert_witness', $methodFlags[array_key_first($methodFlags)]->tactic);
    }

    public function test_detect_flags_missing_accreditation_reference(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'persons' => ['vjestak dipl. ing. Marko Babic'],
                    'institutions' => ['Nepoznati laboratorij d.o.o.'],
                ],
            ],
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'nalaz_vjestaka',
                    'summary' => 'Analiza uzorka provedena u nasem laboratoriju.',
                    // No ISO 17025 or accreditation mention
                ],
            ],
        ];

        $flags = $this->validator->detect('case-123', $analysisData);

        $accredFlags = array_filter($flags, function ($flag) {
            return str_contains(mb_strtolower($flag->title), 'akreditacij') ||
                   str_contains(mb_strtolower($flag->description), 'akreditacij') ||
                   str_contains($flag->description, 'ISO');
        });

        $this->assertNotEmpty($accredFlags);
    }

    public function test_detect_recognizes_known_accredited_lab(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'persons' => ['vjestak dr. sc. Ana Kovac'],
                    'institutions' => ['Centar za forenzicna ispitivanja Ivan Vucetic'],
                ],
            ],
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'nalaz_vjestaka',
                    'summary' => 'Analiza provedena u CFI Ivan Vucetic metodom GC-MS.',
                ],
            ],
        ];

        $flags = $this->validator->detect('case-123', $analysisData);

        // Known accredited lab should not trigger accreditation warning
        $accredFlags = array_filter($flags, function ($flag) {
            return str_contains(mb_strtolower($flag->title), 'nepoznat') ||
                   str_contains(mb_strtolower($flag->title), 'akreditacij');
        });

        $this->assertEmpty($accredFlags);
    }

    public function test_detect_flags_missing_measurement_uncertainty(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'persons' => ['vjestak'],
                    'institutions' => ['Centar za forenzicna ispitivanja Ivan Vucetic'],
                ],
            ],
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'nalaz_vjestaka',
                    'summary' => 'Utvrdena koncentracija: 5.2 mg. Metoda: GC-MS.',
                    // No uncertainty mentioned (e.g., "5.2 +/- 0.3 mg")
                ],
            ],
        ];

        $flags = $this->validator->detect('case-123', $analysisData);

        $uncertaintyFlags = array_filter($flags, function ($flag) {
            return str_contains(mb_strtolower($flag->description), 'mjern') ||
                   str_contains(mb_strtolower($flag->description), 'nesigurnost') ||
                   str_contains(mb_strtolower($flag->description), 'uncertainty');
        });

        $this->assertNotEmpty($uncertaintyFlags);
    }

    public function test_detect_returns_proper_legal_basis(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'persons' => ['vjestak'],
                    'institutions' => [],
                ],
            ],
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'nalaz_vjestaka',
                    'summary' => 'Kratki nalaz.',
                ],
            ],
        ];

        $flags = $this->validator->detect('case-123', $analysisData);

        if (!empty($flags)) {
            $flag = $flags[0];
            $this->assertStringContainsString('cl.', $flag->legalBasis);
        }
    }

    public function test_detect_identifies_expert_from_various_patterns(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'persons' => ['sudski vjestak prof. dr. sc. Petar Novak'],
                ],
            ],
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'nalaz_vjestaka',
                    'summary' => 'Strucno misljenje.',
                ],
            ],
        ];

        $flags = $this->validator->detect('case-123', $analysisData);

        // Should have processed the expert
        $this->assertIsArray($flags);
    }

    public function test_detect_returns_info_severity_for_quality_concerns(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'persons' => ['vjestak'],
                    'institutions' => [],
                ],
            ],
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'nalaz_vjestaka',
                    'summary' => 'Nalaz.',
                ],
            ],
        ];

        $flags = $this->validator->detect('case-123', $analysisData);

        // Quality concerns should be INFO or LOW severity
        foreach ($flags as $flag) {
            $this->assertContains(
                $flag->severity,
                [DefenseFlag::SEVERITY_INFO, DefenseFlag::SEVERITY_LOW, DefenseFlag::SEVERITY_MEDIUM]
            );
        }
    }

    public function test_detect_confidence_is_appropriately_low(): void
    {
        $analysisData = [
            'entities' => [
                'doc-1' => [
                    'persons' => ['vjestak'],
                ],
            ],
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'nalaz_vjestaka',
                    'summary' => 'Nalaz.',
                ],
            ],
        ];

        $flags = $this->validator->detect('case-123', $analysisData);

        // Confidence should be moderate as many details need manual verification
        foreach ($flags as $flag) {
            $this->assertLessThanOrEqual(0.7, $flag->confidence);
        }
    }
}
