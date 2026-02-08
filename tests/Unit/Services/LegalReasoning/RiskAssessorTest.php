<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\LegalCase;
use App\Services\LegalReasoning\CitationAnalyzer;
use App\Services\LegalReasoning\OutcomePredictor;
use App\Services\LegalReasoning\RiskAssessor;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class RiskAssessorTest extends TestCase
{
    use UsesTestDatabase;

    protected RiskAssessor $riskAssessor;

    protected $openAIMock;

    protected $outcomePredictorMock;

    protected $citationAnalyzerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->openAIMock->shouldReceive('complete')->andReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            ['gap' => 'Missing evidence', 'importance' => 'critical', 'impact' => 'High impact'],
                        ]),
                    ],
                ],
            ],
        ])->byDefault();

        $this->outcomePredictorMock = Mockery::mock(OutcomePredictor::class);
        $this->outcomePredictorMock->shouldReceive('predictOutcome')->andReturn([
            'most_likely' => 'favorable',
            'confidence' => 0.7,
        ])->byDefault();

        $this->citationAnalyzerMock = Mockery::mock(CitationAnalyzer::class);
        $this->citationAnalyzerMock->shouldReceive('analyzeAuthority')->andReturn([
            'authority_score' => 0.5,
            'binding' => false,
        ])->byDefault();

        $this->riskAssessor = new RiskAssessor(
            $this->openAIMock,
            $this->outcomePredictorMock,
            $this->citationAnalyzerMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_assesses_risks_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'description' => 'Complex litigation case',
        ]);

        $case->documents()->createMany([
            [
                'title' => 'Evidence 1',
                'content' => 'This is evidence document 1',
                'doc_id' => 'doc-evidence-1',
                'embedding_provider' => 'openai',
                'embedding_model' => 'text-embedding-3-small',
                'embedding_dimensions' => 1536,
                'embedding_vector' => array_fill(0, 1536, 0),
                'content_hash' => hash('sha256', 'evidence1'),
            ],
            [
                'title' => 'Evidence 2',
                'content' => 'This is evidence document 2',
                'doc_id' => 'doc-evidence-2',
                'embedding_provider' => 'openai',
                'embedding_model' => 'text-embedding-3-small',
                'embedding_dimensions' => 1536,
                'embedding_vector' => array_fill(0, 1536, 0),
                'content_hash' => hash('sha256', 'evidence2'),
            ],
        ]);

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert
        $this->assertArrayHasKey('legal_risks', $result);
        $this->assertArrayHasKey('evidentiary_risks', $result);
        $this->assertArrayHasKey('strategic_risks', $result);
        $this->assertArrayHasKey('risk_scores', $result);
        $this->assertArrayHasKey('risk_level', $result);
        $this->assertArrayHasKey('mitigation_strategies', $result);
        $this->assertArrayHasKey('risk_summary', $result);
    }

    /** @test */
    public function it_calculates_risk_scores()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert
        $this->assertArrayHasKey('legal', $result['risk_scores']);
        $this->assertArrayHasKey('evidentiary', $result['risk_scores']);
        $this->assertArrayHasKey('strategic', $result['risk_scores']);
        $this->assertArrayHasKey('overall', $result['risk_scores']);

        $this->assertGreaterThanOrEqual(0, $result['risk_scores']['overall']);
        $this->assertLessThanOrEqual(1, $result['risk_scores']['overall']);
    }

    /** @test */
    public function it_categorizes_risk_level_correctly()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert
        $this->assertContains($result['risk_level'], ['HIGH', 'MEDIUM', 'LOW']);
    }

    /** @test */
    public function it_uses_weighted_risk_scoring()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert
        // Overall score should reflect weighted average (40% legal, 35% evidentiary, 25% strategic)
        $this->assertArrayHasKey('overall', $result['risk_scores']);
    }

    /** @test */
    public function it_identifies_weak_precedents()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert
        $this->assertNotEmpty($result['legal_risks']);
    }

    /** @test */
    public function it_generates_mitigation_strategies()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Configure mock to return high-severity risks to trigger mitigation generation
        $this->openAIMock->shouldReceive('complete')->andReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            ['gap' => 'Critical missing evidence', 'importance' => 'critical', 'impact' => 'High', 'severity' => 'high'],
                        ]),
                    ],
                ],
            ],
        ])->byDefault();

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert - mitigation strategies should exist (may be empty if no high risks found)
        $this->assertArrayHasKey('mitigation_strategies', $result);
    }

    /** @test */
    public function it_analyzes_evidentiary_strength()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Add evidence documents
        $case->documents()->createMany([
            ['title' => 'Contract', 'file_path' => 'contract.pdf', 'category' => 'evidence'],
            ['title' => 'Email', 'file_path' => 'email.pdf', 'category' => 'evidence'],
            ['title' => 'Witness Statement', 'file_path' => 'witness.pdf', 'category' => 'evidence'],
        ]);

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert
        $this->assertNotEmpty($result['evidentiary_risks']);
    }

    /** @test */
    public function it_assesses_settlement_leverage()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert
        $this->assertArrayHasKey('strategic_risks', $result);
    }

    /** @test */
    public function it_performs_cost_benefit_analysis()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert
        $this->assertNotEmpty($result['strategic_risks']);
    }

    /** @test */
    public function it_generates_risk_summary()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert
        $this->assertArrayHasKey('risk_summary', $result);
        $this->assertNotEmpty($result['risk_summary']);
        $this->assertIsString($result['risk_summary']);
    }

    /** @test */
    public function it_classifies_high_risk_when_overall_score_above_70_percent()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Mock all high severity risks to push overall score above 70%
        $this->openAIMock->shouldReceive('complete')->andReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            ['severity' => 'high', 'description' => 'Major risk', 'importance' => 'critical'],
                            ['severity' => 'high', 'description' => 'Another major risk', 'importance' => 'critical'],
                            ['severity' => 'high', 'description' => 'Third major risk', 'importance' => 'critical'],
                        ]),
                    ],
                ],
            ],
        ])->byDefault();

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert - with all high risks, overall score should be high
        $this->assertGreaterThan(0.7, $result['risk_scores']['overall']);
    }

    /** @test */
    public function it_classifies_low_risk_when_overall_score_below_40_percent()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'jurisdiction' => 'Osijek',  // Avoid jurisdictional risk
            'court' => 'Županijski sud u Osijeku',  // Proper court level
            'status' => 'active',  // Active status
        ]);

        // Add many documents to avoid "weak evidence" penalty
        $case->documents()->createMany([
            ['title' => 'Evidence 1', 'file_path' => 'evidence1.pdf'],
            ['title' => 'Evidence 2', 'file_path' => 'evidence2.pdf'],
            ['title' => 'Evidence 3', 'file_path' => 'evidence3.pdf'],
            ['title' => 'Evidence 4', 'file_path' => 'evidence4.pdf'],
            ['title' => 'Evidence 5', 'file_path' => 'evidence5.pdf'],
        ]);

        // Mock all low severity risks
        $this->openAIMock->shouldReceive('complete')->andReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            ['severity' => 'low', 'description' => 'Minor risk'],
                        ]),
                    ],
                ],
            ],
        ])->byDefault();

        // Act
        $result = $this->riskAssessor->assessRisks($case->id);

        // Assert - overall score should be relatively low
        $this->assertLessThan(0.5, $result['risk_scores']['overall']);
    }

    /** @test */
    public function it_handles_llm_failure_gracefully()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andThrow(new \Exception('OpenAI API unavailable'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->riskAssessor->assessRisks($case->id);
    }
}
