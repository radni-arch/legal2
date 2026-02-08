<?php

namespace Tests\Unit\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Detectors\NeBisInIdemDetector;
use Tests\TestCase;

class NeBisInIdemDetectorTest extends TestCase
{
    private NeBisInIdemDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new NeBisInIdemDetector();
    }

    /**
     * @test
     */
    public function it_implements_defense_tactic_detector_interface(): void
    {
        $this->assertInstanceOf(
            \App\Services\Defense\Contracts\DefenseTacticDetectorInterface::class,
            $this->detector
        );
    }

    /**
     * @test
     */
    public function it_returns_correct_tactic_identifier(): void
    {
        $this->assertEquals('ne_bis_in_idem', $this->detector->tactic());
    }

    /**
     * @test
     */
    public function it_returns_croatian_label(): void
    {
        $this->assertEquals('Ne bis in idem', $this->detector->label());
    }

    /**
     * @test
     */
    public function it_requires_case_hierarchy_and_related_data(): void
    {
        $requires = $this->detector->requires();

        $this->assertContains('case_hierarchy', $requires);
        $this->assertContains('case_references', $requires);
        $this->assertContains('dates_with_context', $requires);
        $this->assertContains('entities', $requires);
    }

    /**
     * @test
     */
    public function it_returns_empty_array_when_no_hierarchy_data(): void
    {
        $flags = $this->detector->detect('case-123', []);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    /**
     * @test
     */
    public function it_returns_empty_array_when_no_prekrsajni_satellites(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Kv-456/2025',
                    'satellite_case_type' => 'Kv',
                    'relationship' => 'detention_hearing',
                    'evidence' => [],
                ],
            ],
        ]);

        $this->assertEmpty($flags);
    }

    /**
     * @test
     */
    public function it_detects_ne_bis_in_idem_for_pp_prz_satellite(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pp Prz-456/2025',
                    'satellite_case_type' => 'Pp Prz',
                    'relationship' => 'search_warrant',
                    'evidence' => ['doc-1', 'doc-2'],
                ],
            ],
        ]);

        $this->assertNotEmpty($flags);
        $flag = $flags[0];
        $this->assertEquals(DefenseFlag::SEVERITY_HIGH, $flag->severity);
        $this->assertStringContainsString('Ne bis in idem', $flag->title);
        $this->assertStringContainsString('Pp Prz-456/2025', $flag->title);
        $this->assertStringContainsString('K-123/2025', $flag->title);
    }

    /**
     * @test
     */
    public function it_detects_ne_bis_in_idem_for_pp_j_satellite(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pp J-789/2025',
                    'satellite_case_type' => 'Pp J',
                    'relationship' => 'minor_offense',
                    'evidence' => [],
                ],
            ],
        ]);

        $this->assertNotEmpty($flags);
        $flag = $flags[0];
        $this->assertEquals(DefenseFlag::SEVERITY_HIGH, $flag->severity);
        $this->assertStringContainsString('Ne bis in idem', $flag->title);
    }

    /**
     * @test
     */
    public function it_detects_ne_bis_in_idem_for_pn_satellite(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pn-111/2025',
                    'satellite_case_type' => 'Pn',
                    'relationship' => 'minor_offense',
                    'evidence' => [],
                ],
            ],
        ]);

        $this->assertNotEmpty($flags);
        $flag = $flags[0];
        $this->assertEquals(DefenseFlag::SEVERITY_HIGH, $flag->severity);
    }

    /**
     * @test
     */
    public function it_handles_array_format_hierarchy(): void
    {
        // Test with array format instead of object
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pp Prz-456/2025',
                    'satellite_case_type' => 'Pp Prz',
                    'relationship' => 'search_warrant',
                    'evidence' => [],
                ],
            ],
        ]);

        $this->assertNotEmpty($flags);
        $flag = $flags[0];
        $this->assertStringContainsString('Ne bis in idem', $flag->title);
    }

    /**
     * @test
     */
    public function it_includes_legal_basis_references(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pp Prz-456/2025',
                    'satellite_case_type' => 'Pp Prz',
                    'relationship' => 'search_warrant',
                    'evidence' => [],
                ],
            ],
        ]);

        $flag = $flags[0];
        $this->assertStringContainsString('cl. 31. Ustav RH', $flag->legalBasis);
        $this->assertStringContainsString('cl. 12. ZKP', $flag->legalBasis);
    }

    /**
     * @test
     */
    public function it_references_echr_maresti_case(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pp Prz-456/2025',
                    'satellite_case_type' => 'Pp Prz',
                    'relationship' => 'search_warrant',
                    'evidence' => [],
                ],
            ],
        ]);

        $flag = $flags[0];
        $this->assertNotNull($flag->echrBasis);
        $this->assertStringContainsString('Maresti', $flag->echrBasis);
    }

    /**
     * @test
     */
    public function it_includes_evidence_with_case_numbers(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pp Prz-456/2025',
                    'satellite_case_type' => 'Pp Prz',
                    'relationship' => 'search_warrant',
                    'evidence' => ['doc-1', 'doc-2'],
                ],
            ],
        ]);

        $flag = $flags[0];
        $this->assertArrayHasKey('prekrsajni_case', $flag->evidence);
        $this->assertArrayHasKey('kazneni_case', $flag->evidence);
        $this->assertEquals('Pp Prz-456/2025', $flag->evidence['prekrsajni_case']);
        $this->assertEquals('K-123/2025', $flag->evidence['kazneni_case']);
    }

    /**
     * @test
     */
    public function it_detects_multiple_ne_bis_in_idem_cases(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pp Prz-456/2025',
                    'satellite_case_type' => 'Pp Prz',
                    'relationship' => 'search_warrant',
                    'evidence' => [],
                ],
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pp J-789/2025',
                    'satellite_case_type' => 'Pp J',
                    'relationship' => 'minor_offense',
                    'evidence' => [],
                ],
            ],
        ]);

        $this->assertCount(2, $flags);
    }

    /**
     * @test
     */
    public function it_provides_detailed_recommended_action(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pp Prz-456/2025',
                    'satellite_case_type' => 'Pp Prz',
                    'relationship' => 'search_warrant',
                    'evidence' => [],
                ],
            ],
        ]);

        $flag = $flags[0];
        // Should recommend getting the prekrsaj decision
        $this->assertStringContainsString('pravomoćnu odluku', $flag->recommendedAction);
        // Should mention Zolotukhin test
        $this->assertStringContainsString('Zolotukhin', $flag->recommendedAction);
    }

    /**
     * @test
     */
    public function it_sets_moderate_confidence_requiring_manual_verification(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Pp Prz-456/2025',
                    'satellite_case_type' => 'Pp Prz',
                    'relationship' => 'search_warrant',
                    'evidence' => [],
                ],
            ],
        ]);

        $flag = $flags[0];
        // Confidence should be moderate since manual fact comparison is needed
        $this->assertGreaterThanOrEqual(0.5, $flag->confidence);
        $this->assertLessThanOrEqual(0.85, $flag->confidence);
    }

    /**
     * @test
     */
    public function it_ignores_non_prekrsajni_satellites(): void
    {
        $flags = $this->detector->detect('case-123', [
            'case_hierarchy' => [
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Kv-456/2025',
                    'satellite_case_type' => 'Kv',
                    'relationship' => 'detention_hearing',
                    'evidence' => [],
                ],
                (object) [
                    'main_case_number' => 'K-123/2025',
                    'satellite_case_number' => 'Kz-789/2025',
                    'satellite_case_type' => 'Kz',
                    'relationship' => 'appeal',
                    'evidence' => [],
                ],
            ],
        ]);

        // None of these are prekrsajni, so no ne bis in idem flags
        $this->assertEmpty($flags);
    }
}
