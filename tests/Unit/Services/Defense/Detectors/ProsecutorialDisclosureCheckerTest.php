<?php

namespace Tests\Unit\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use App\Services\Defense\Detectors\ProsecutorialDisclosureChecker;
use PHPUnit\Framework\TestCase;

class ProsecutorialDisclosureCheckerTest extends TestCase
{
    private ProsecutorialDisclosureChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new ProsecutorialDisclosureChecker();
    }

    /** @test */
    public function it_implements_defense_tactic_detector_interface(): void
    {
        $this->assertInstanceOf(DefenseTacticDetectorInterface::class, $this->checker);
    }

    /** @test */
    public function it_returns_correct_tactic_identifier(): void
    {
        $this->assertEquals('prosecutorial_disclosure', $this->checker->tactic());
    }

    /** @test */
    public function it_returns_correct_label(): void
    {
        $this->assertEquals('Obveza razotkrivanja dokaza (cl. 9./184. ZKP)', $this->checker->label());
    }

    /** @test */
    public function it_requires_correct_analysis_data(): void
    {
        $requires = $this->checker->requires();

        $this->assertContains('case_reference_registry', $requires);
        $this->assertContains('entities', $requires);
    }

    /** @test */
    public function it_flags_missing_references_from_registry(): void
    {
        $analysisData = [
            'case_reference_registry' => [
                [
                    'reference_type' => 'klasa',
                    'reference_value' => 'UP/I-034-04/23-01/123',
                    'status' => 'missing',
                ],
                [
                    'reference_type' => 'urbroj',
                    'reference_value' => '511-12-02-03/23-2',
                    'status' => 'missing',
                ],
                [
                    'reference_type' => 'broj',
                    'reference_value' => 'K-45/2023',
                    'status' => 'present',
                ],
            ],
            'entities' => [],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        $this->assertNotEmpty($flags);
        $this->assertContainsOnlyInstancesOf(DefenseFlag::class, $flags);

        // Find the missing references flag
        $missingFlag = collect($flags)->first(function (DefenseFlag $f) {
            return str_contains($f->title, 'referenci') || str_contains($f->description, 'reference');
        });

        $this->assertNotNull($missingFlag);
        $this->assertEquals('prosecutorial_disclosure', $missingFlag->tactic);
    }

    /** @test */
    public function it_sets_severity_based_on_missing_count(): void
    {
        // Many missing (>3) should be HIGH severity
        $analysisData = [
            'case_reference_registry' => [
                ['reference_type' => 'klasa', 'reference_value' => 'ref-1', 'status' => 'missing'],
                ['reference_type' => 'urbroj', 'reference_value' => 'ref-2', 'status' => 'missing'],
                ['reference_type' => 'broj', 'reference_value' => 'ref-3', 'status' => 'missing'],
                ['reference_type' => 'broj', 'reference_value' => 'ref-4', 'status' => 'missing'],
            ],
            'entities' => [],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        $missingFlag = collect($flags)->first(function (DefenseFlag $f) {
            return str_contains($f->title, 'referenci');
        });

        $this->assertNotNull($missingFlag);
        $this->assertEquals(DefenseFlag::SEVERITY_HIGH, $missingFlag->severity);
    }

    /** @test */
    public function it_counts_witnesses_in_entities(): void
    {
        $analysisData = [
            'case_reference_registry' => [],
            'entities' => [
                'doc-1' => [
                    'persons' => [
                        'svjedok Marko Markovic',
                        'svjedok Ana Anic',
                        'okrivljenik Ivan Horvat',
                    ],
                ],
                'doc-2' => [
                    'persons' => [
                        'svjedok Pero Peric',
                    ],
                ],
            ],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        // Should report witness count as INFO
        $witnessFlag = collect($flags)->first(function (DefenseFlag $f) {
            return str_contains($f->title, 'svjedok') || str_contains($f->description, 'svjedok');
        });

        $this->assertNotNull($witnessFlag);
        $this->assertEquals(DefenseFlag::SEVERITY_INFO, $witnessFlag->severity);
        $this->assertArrayHasKey('witness_count', $witnessFlag->evidence);
    }

    /** @test */
    public function it_does_not_flag_when_all_references_present(): void
    {
        $analysisData = [
            'case_reference_registry' => [
                ['reference_type' => 'klasa', 'reference_value' => 'ref-1', 'status' => 'present'],
                ['reference_type' => 'urbroj', 'reference_value' => 'ref-2', 'status' => 'present'],
            ],
            'entities' => [],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        // Should NOT have missing reference flags
        $missingFlag = collect($flags)->first(function (DefenseFlag $f) {
            return str_contains($f->title, 'referenci');
        });

        $this->assertNull($missingFlag);
    }

    /** @test */
    public function it_handles_object_style_registry_entries(): void
    {
        // Test with object-style data (as might come from database)
        $entry = new \stdClass();
        $entry->reference_type = 'klasa';
        $entry->reference_value = 'UP/I-034-04/23-01/123';
        $entry->status = 'missing';

        $analysisData = [
            'case_reference_registry' => [$entry],
            'entities' => [],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        $missingFlag = collect($flags)->first(function (DefenseFlag $f) {
            return str_contains($f->title, 'referenci');
        });

        $this->assertNotNull($missingFlag);
    }

    /** @test */
    public function it_returns_empty_array_when_no_issues(): void
    {
        $analysisData = [
            'case_reference_registry' => [],
            'entities' => [],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    /** @test */
    public function each_flag_has_required_properties(): void
    {
        $analysisData = [
            'case_reference_registry' => [
                ['reference_type' => 'klasa', 'reference_value' => 'ref-1', 'status' => 'missing'],
            ],
            'entities' => [
                'doc-1' => [
                    'persons' => ['svjedok Test Witness'],
                ],
            ],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        foreach ($flags as $flag) {
            $this->assertInstanceOf(DefenseFlag::class, $flag);
            $this->assertEquals('prosecutorial_disclosure', $flag->tactic);
            $this->assertNotEmpty($flag->title);
            $this->assertNotEmpty($flag->description);
            $this->assertNotEmpty($flag->legalBasis);
            $this->assertNotEmpty($flag->recommendedAction);
            $this->assertGreaterThanOrEqual(0, $flag->confidence);
            $this->assertLessThanOrEqual(1, $flag->confidence);
        }
    }

    /** @test */
    public function it_references_correct_legal_basis(): void
    {
        $analysisData = [
            'case_reference_registry' => [
                ['reference_type' => 'klasa', 'reference_value' => 'ref-1', 'status' => 'missing'],
            ],
            'entities' => [],
        ];

        $flags = $this->checker->detect('case-123', $analysisData);

        foreach ($flags as $flag) {
            // Should reference ZKP articles 9 or 184
            $hasCorrectBasis = str_contains($flag->legalBasis, 'cl. 9') ||
                              str_contains($flag->legalBasis, 'cl. 184') ||
                              str_contains($flag->legalBasis, '9. ZKP') ||
                              str_contains($flag->legalBasis, '184. ZKP');
            $this->assertTrue($hasCorrectBasis, "Legal basis should reference cl. 9 or 184 ZKP");
        }
    }
}
