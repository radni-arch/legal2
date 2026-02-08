<?php

namespace Tests\Unit\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use App\Services\Defense\Detectors\JudicialBiasDetector;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class JudicialBiasDetectorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ClaudeAnalysisService $claudeService;
    private JudicialBiasDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->claudeService = Mockery::mock(ClaudeAnalysisService::class);
        $this->detector = new JudicialBiasDetector($this->claudeService);
    }

    public function test_implements_defense_tactic_detector_interface(): void
    {
        $this->assertInstanceOf(DefenseTacticDetectorInterface::class, $this->detector);
    }

    public function test_tactic_returns_judicial_bias(): void
    {
        $this->assertEquals('judicial_bias', $this->detector->tactic());
    }

    public function test_label_returns_croatian_label(): void
    {
        $this->assertStringContainsString('Nepristranost', $this->detector->label());
    }

    public function test_requires_returns_needed_analysis_data(): void
    {
        $requires = $this->detector->requires();

        $this->assertIsArray($requires);
        $this->assertContains('ai_key_facts', $requires);
        $this->assertContains('ai_summary', $requires);
    }

    public function test_detect_returns_empty_array_when_insufficient_data(): void
    {
        $flags = $this->detector->detect('case-123', []);

        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    public function test_detect_flags_high_denial_rate_statistical_phase(): void
    {
        // Simulate 10 defense motions with 9 denials (90%)
        $analysisData = [
            'ai_key_facts' => [
                'doc-1' => [
                    'key_facts' => [
                        ['claim' => 'Prijedlog obrane za izdvajanje dokaza odbijen.'],
                        ['claim' => 'Prijedlog obrane za ispitivanje svjedoka odbijen.'],
                        ['claim' => 'Prijedlog obrane za psihijatrijsko vjestacenje odbijen.'],
                        ['claim' => 'Prijedlog obrane za izuzece suca odbijen.'],
                        ['claim' => 'Prijedlog branitelja za odgodu rocista odbijen.'],
                    ],
                ],
                'doc-2' => [
                    'key_facts' => [
                        ['claim' => 'Prijedlog obrane za uvid u spis odbijen.'],
                        ['claim' => 'Prijedlog okrivljenika za slobodu odbijen.'],
                        ['claim' => 'Prijedlog branitelja za preispitivanje mjere odbijen.'],
                        ['claim' => 'Prijedlog obrane za novo svjedocenje odbijen.'],
                        ['claim' => 'Prijedlog obrane za ukidanje pritvora usvojen.'], // 1 approval
                    ],
                ],
            ],
        ];

        $flags = $this->detector->detect('case-123', $analysisData);

        $this->assertNotEmpty($flags);
        $this->assertInstanceOf(DefenseFlag::class, $flags[0]);
        $this->assertEquals('judicial_bias', $flags[0]->tactic);
        $this->assertEquals(DefenseFlag::SEVERITY_MEDIUM, $flags[0]->severity);
        $this->assertStringContainsString('90%', $flags[0]->title);
        $this->assertEquals(9, $flags[0]->evidence['denials']);
        $this->assertEquals(1, $flags[0]->evidence['approvals']);
    }

    public function test_detect_does_not_flag_when_denial_rate_below_threshold(): void
    {
        // 3 out of 5 denied (60%) - below 85% threshold
        $analysisData = [
            'ai_key_facts' => [
                'doc-1' => [
                    'key_facts' => [
                        ['claim' => 'Prijedlog obrane za izdvajanje dokaza odbijen.'],
                        ['claim' => 'Prijedlog obrane za ispitivanje svjedoka odbijen.'],
                        ['claim' => 'Prijedlog obrane za psihijatrijsko vjestacenje odbijen.'],
                        ['claim' => 'Prijedlog branitelja za odgodu rocista usvojen.'],
                        ['claim' => 'Prijedlog obrane za uvid u spis usvojen.'],
                    ],
                ],
            ],
        ];

        $flags = $this->detector->detect('case-123', $analysisData);

        // Should not have a statistical bias flag
        $statisticalFlags = array_filter($flags, function ($flag) {
            return str_contains($flag->title, '%') && str_contains($flag->title, 'Odbijeno');
        });

        $this->assertEmpty($statisticalFlags);
    }

    public function test_detect_requires_minimum_motions_for_statistical_analysis(): void
    {
        // Only 2 motions - below minimum of 3
        $analysisData = [
            'ai_key_facts' => [
                'doc-1' => [
                    'key_facts' => [
                        ['claim' => 'Prijedlog obrane za izdvajanje dokaza odbijen.'],
                        ['claim' => 'Prijedlog obrane za ispitivanje svjedoka odbijen.'],
                    ],
                ],
            ],
        ];

        $flags = $this->detector->detect('case-123', $analysisData);

        // Should not have a statistical bias flag with only 2 motions
        $statisticalFlags = array_filter($flags, function ($flag) {
            return str_contains($flag->title, '%') && str_contains($flag->title, 'Odbijeno');
        });

        $this->assertEmpty($statisticalFlags);
    }

    public function test_detect_ai_text_similarity_phase_flags_high_similarity(): void
    {
        $analysisData = [
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'optuznica',
                    'summary' => 'Optuzenik je navodno pocinio kazneno djelo iz cl. 190. KZ.',
                ],
                'doc-2' => [
                    'document_type' => 'presuda',
                    'summary' => 'Sud utvrduje da je optuzenik pocinio kazneno djelo iz cl. 190. KZ.',
                ],
            ],
            'ai_key_facts' => [],
        ];

        // Mock Claude service response with high similarity
        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'similarity_percent' => 85,
                    'copied_sections' => ['Cinjenicni opis djela'],
                    'independent_reasoning' => [],
                ],
            ]);

        $flags = $this->detector->detect('case-123', $analysisData);

        $similarityFlags = array_filter($flags, function ($flag) {
            return str_contains($flag->title, 'slicno');
        });

        $this->assertNotEmpty($similarityFlags);
        $flag = array_values($similarityFlags)[0];
        $this->assertEquals('judicial_bias', $flag->tactic);
        $this->assertEquals(DefenseFlag::SEVERITY_HIGH, $flag->severity);
        $this->assertEquals(85, $flag->evidence['similarity_percent']);
    }

    public function test_detect_ai_text_similarity_phase_does_not_flag_low_similarity(): void
    {
        $analysisData = [
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'optuznica',
                    'summary' => 'Optuzenik je navodno pocinio kazneno djelo.',
                ],
                'doc-2' => [
                    'document_type' => 'presuda',
                    'summary' => 'Sud utvrduje da optuzenik nije kriv.',
                ],
            ],
            'ai_key_facts' => [],
        ];

        // Mock Claude service response with low similarity
        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'similarity_percent' => 30,
                    'copied_sections' => [],
                    'independent_reasoning' => ['Sud je proveo vlastitu analizu'],
                ],
            ]);

        $flags = $this->detector->detect('case-123', $analysisData);

        $similarityFlags = array_filter($flags, function ($flag) {
            return str_contains($flag->title, 'slicno');
        });

        $this->assertEmpty($similarityFlags);
    }

    public function test_detect_gracefully_handles_claude_api_errors(): void
    {
        $analysisData = [
            'ai_summary' => [
                'doc-1' => [
                    'document_type' => 'optuznica',
                    'summary' => 'Test summary.',
                ],
                'doc-2' => [
                    'document_type' => 'presuda',
                    'summary' => 'Test ruling.',
                ],
            ],
            'ai_key_facts' => [],
        ];

        // Mock Claude service throwing exception
        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andThrow(new \RuntimeException('API error'));

        // Should not throw, just skip AI phase
        $flags = $this->detector->detect('case-123', $analysisData);

        $this->assertIsArray($flags);
    }

    public function test_detect_severity_is_high_when_similarity_above_85(): void
    {
        $analysisData = [
            'ai_summary' => [
                'doc-1' => ['document_type' => 'optuznica', 'summary' => 'Test.'],
                'doc-2' => ['document_type' => 'presuda', 'summary' => 'Test.'],
            ],
            'ai_key_facts' => [],
        ];

        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'similarity_percent' => 90,
                    'copied_sections' => ['Section 1'],
                    'independent_reasoning' => [],
                ],
            ]);

        $flags = $this->detector->detect('case-123', $analysisData);

        $similarityFlags = array_filter($flags, fn($f) => str_contains($f->title, 'slicno'));
        $flag = array_values($similarityFlags)[0];

        $this->assertEquals(DefenseFlag::SEVERITY_HIGH, $flag->severity);
    }

    public function test_detect_severity_is_medium_when_similarity_between_70_and_85(): void
    {
        $analysisData = [
            'ai_summary' => [
                'doc-1' => ['document_type' => 'optuznica', 'summary' => 'Test.'],
                'doc-2' => ['document_type' => 'presuda', 'summary' => 'Test.'],
            ],
            'ai_key_facts' => [],
        ];

        $this->claudeService
            ->shouldReceive('analyzeJson')
            ->once()
            ->andReturn([
                'parsed' => [
                    'similarity_percent' => 75,
                    'copied_sections' => ['Section 1'],
                    'independent_reasoning' => [],
                ],
            ]);

        $flags = $this->detector->detect('case-123', $analysisData);

        $similarityFlags = array_filter($flags, fn($f) => str_contains($f->title, 'slicno'));
        $flag = array_values($similarityFlags)[0];

        $this->assertEquals(DefenseFlag::SEVERITY_MEDIUM, $flag->severity);
    }
}
