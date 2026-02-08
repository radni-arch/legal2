<?php

namespace Tests\Unit\Modules\Evidence;

use App\Models\LegalCase;
use App\Modules\Evidence\Services\EvidenceAdmissibilityChecker;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EvidenceAdmissibilityCheckerTest extends TestCase
{
    use UsesTestDatabase;

    protected EvidenceAdmissibilityChecker $checker;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->checker = new EvidenceAdmissibilityChecker($this->openAIMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_checks_evidence_admissibility_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'physical',
            'description' => 'Weapon found at crime scene',
            'obtained_by' => 'Police Officer',
            'collection_method' => 'Legal search with warrant',
            'warrant' => true,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01 10:00:00', 'handler' => 'Officer Smith'],
                ['timestamp' => '2024-01-01 12:00:00', 'handler' => 'Evidence Room'],
            ],
            'authenticated' => true,
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Evidence directly relates to the crime');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertArrayHasKey('admissible', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('recommendation', $result);
        $this->assertTrue($result['admissible']);
        $this->assertEmpty($result['issues']);
        $this->assertEquals(100, $result['confidence']);
    }

    /** @test */
    public function it_fails_lawfulness_check_for_evidence_without_warrant()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'physical',
            'description' => 'Evidence seized without warrant',
            'obtained_by' => 'Police',
            'collection_method' => 'search',
            'warrant' => false,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertNotEmpty($result['issues']);
        $this->assertStringContainsString('warrant', strtolower($result['issues'][0]['description']));
        $this->assertEquals(80, $result['issues'][0]['severity']);
    }

    /** @test */
    public function it_fails_lawfulness_check_for_warrantless_search()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'digital',
            'description' => 'Phone data',
            'obtained_by' => 'Police',
            'collection_method' => 'warrantless search',
            'warrant' => true,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertNotEmpty($result['issues']);
        $this->assertStringContainsString('authorization', strtolower($result['issues'][0]['description']));
    }

    /** @test */
    public function it_detects_coercion_keywords_english()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'testimonial',
            'description' => 'Statement obtained through intimidation',
            'collection_method' => 'Interview under duress',
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertNotEmpty($result['issues']);
        $this->assertEquals(100, $result['issues'][0]['severity']);
        $this->assertStringContainsString('coercion', strtolower($result['issues'][0]['description']));
    }

    /** @test */
    public function it_detects_coercion_keywords_croatian()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'testimonial',
            'description' => 'Iskaz dobiven prisilom',
            'collection_method' => 'Intervju s prijetnjom',
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertNotEmpty($result['issues']);
        $this->assertEquals(100, $result['issues'][0]['severity']);
    }

    /** @test */
    public function it_fails_chain_of_custody_when_empty()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'physical',
            'description' => 'Evidence without chain',
            'obtained_by' => 'Police',
            'warrant' => true,
            'chain_of_custody' => [],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertStringContainsString('chain of custody', strtolower($result['issues'][0]['description']));
        $this->assertEquals(75, $result['issues'][0]['severity']);
    }

    /** @test */
    public function it_fails_chain_of_custody_when_insufficient()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'physical',
            'description' => 'Evidence with single handler',
            'obtained_by' => 'Police',
            'warrant' => true,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertStringContainsString('insufficient', strtolower($result['issues'][0]['description']));
    }

    /** @test */
    public function it_fails_chain_of_custody_when_missing_required_fields()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'physical',
            'description' => 'Evidence with incomplete chain',
            'obtained_by' => 'Police',
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer 1'],
                ['handler' => 'Officer 2'], // Missing timestamp
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertNotEmpty($result['issues']);
    }

    /** @test */
    public function it_checks_relevance_using_llm()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'title' => 'Theft Case',
            'description' => 'Stolen property from store',
        ]);
        $evidence = [
            'type' => 'physical',
            'description' => 'Stolen merchandise',
            'obtained_by' => 'Police',
            'collection_method' => 'Legal search with warrant',
            'warrant' => true,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer 1'],
                ['timestamp' => '2024-01-02', 'handler' => 'Evidence Room'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Evidence directly connects to the stolen items');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertTrue($result['checks']['relevance']['passed']);
        $this->assertEquals(0, $result['checks']['relevance']['severity']);
    }

    /** @test */
    public function it_fails_relevance_check_when_not_relevant()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'title' => 'Assault Case',
            'description' => 'Physical assault at bar',
        ]);
        $evidence = [
            'type' => 'documentary',
            'description' => 'Unrelated contract document',
            'obtained_by' => 'Police',
            'collection_method' => 'Seized',
            'warrant' => true,
            'authenticated' => true,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer 1'],
                ['timestamp' => '2024-01-02', 'handler' => 'Evidence Room'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(false, 'Evidence has no connection to the assault case');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertFalse($result['checks']['relevance']['passed']);
        $this->assertEquals(60, $result['checks']['relevance']['severity']);
    }

    /** @test */
    public function it_checks_documentary_evidence_authentication()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'documentary',
            'description' => 'Contract document',
            'obtained_by' => 'Attorney',
            'authenticated' => false,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Attorney'],
                ['timestamp' => '2024-01-02', 'handler' => 'Evidence Room'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertStringContainsString('authentication', strtolower($result['issues'][0]['description']));
        $this->assertEquals(65, $result['issues'][0]['severity']);
    }

    /** @test */
    public function it_checks_digital_evidence_forensic_verification()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'digital',
            'description' => 'Email evidence',
            'obtained_by' => 'IT Forensics',
            'collection_method' => 'Legal extraction',
            'warrant' => true,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'IT Expert'],
                ['timestamp' => '2024-01-02', 'handler' => 'Evidence Room'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertStringContainsString('forensic verification', strtolower($result['issues'][0]['description']));
    }

    /** @test */
    public function it_accepts_digital_evidence_with_hash()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'digital',
            'description' => 'Email evidence',
            'obtained_by' => 'IT Forensics',
            'collection_method' => 'Legal extraction',
            'warrant' => true,
            'hash' => 'abc123def456',
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'IT Expert'],
                ['timestamp' => '2024-01-02', 'handler' => 'Evidence Room'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertTrue($result['checks']['authentication']['passed']);
    }

    /** @test */
    public function it_checks_expert_testimony_qualifications()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'expert',
            'description' => 'Expert testimony on forensic analysis',
            'obtained_by' => 'Expert witness',
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Court'],
                ['timestamp' => '2024-01-02', 'handler' => 'Evidence Room'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertStringContainsString('qualifications', strtolower($result['issues'][0]['description']));
    }

    /** @test */
    public function it_accepts_expert_testimony_with_qualifications()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'expert',
            'description' => 'Expert testimony on forensic analysis',
            'obtained_by' => 'Expert witness',
            'expert_qualifications' => 'PhD in Forensic Science, 20 years experience',
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Court'],
                ['timestamp' => '2024-01-02', 'handler' => 'Evidence Room'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertTrue($result['checks']['authentication']['passed']);
    }

    /** @test */
    public function it_checks_procedural_compliance_for_testimonial_evidence()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'testimonial',
            'description' => 'Witness statement',
            'obtained_by' => 'Police',
            'collection_method' => 'Interview',
            'rights_warned' => false,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer'],
                ['timestamp' => '2024-01-02', 'handler' => 'Evidence Room'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertStringContainsString('rights', strtolower($result['issues'][0]['description']));
        $this->assertEquals(70, $result['issues'][0]['severity']);
    }

    /** @test */
    public function it_checks_lawyer_presence_for_statements()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'testimonial',
            'description' => 'Accused statement',
            'obtained_by' => 'Police',
            'collection_method' => 'statement during interview',
            'rights_warned' => true,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer'],
                ['timestamp' => '2024-01-02', 'handler' => 'Evidence Room'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertFalse($result['admissible']);
        $this->assertStringContainsString('legal counsel', strtolower($result['issues'][0]['description']));
    }

    /** @test */
    public function it_calculates_confidence_score_correctly()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'physical',
            'description' => 'Partially valid evidence',
            'obtained_by' => 'Police',
            'collection_method' => 'Legal search',
            'warrant' => true,
            'chain_of_custody' => [], // This will fail
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        // 5 out of 6 checks should pass (chain_of_custody fails)
        $expectedConfidence = round((5 / 6) * 100);
        $this->assertEquals($expectedConfidence, $result['confidence']);
    }

    /** @test */
    public function it_provides_challenge_recommendation_when_inadmissible()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'physical',
            'description' => 'Invalid evidence',
            'obtained_by' => 'Police',
            'collection_method' => 'warrantless search',
            'warrant' => false,
            'chain_of_custody' => [],
        ];

        $this->mockOpenAIRelevanceCheck(false, 'Not relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertStringContainsString('Challenge admissibility', $result['recommendation']);
    }

    /** @test */
    public function it_provides_accept_recommendation_when_admissible()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'physical',
            'description' => 'Valid evidence',
            'obtained_by' => 'Police',
            'collection_method' => 'Legal search with warrant',
            'warrant' => true,
            'chain_of_custody' => [
                ['timestamp' => '2024-01-01', 'handler' => 'Officer 1'],
                ['timestamp' => '2024-01-02', 'handler' => 'Evidence Room'],
            ],
        ];

        $this->mockOpenAIRelevanceCheck(true, 'Relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        $this->assertStringContainsString('appears admissible', $result['recommendation']);
    }

    /** @test */
    public function it_includes_legal_basis_in_all_checks()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'type' => 'physical',
            'description' => 'Evidence',
            'obtained_by' => 'Police',
            'collection_method' => 'warrantless search',
            'warrant' => false,
            'chain_of_custody' => [],
        ];

        $this->mockOpenAIRelevanceCheck(false, 'Not relevant');

        // Act
        $result = $this->checker->check($evidence, $case);

        // Assert
        foreach ($result['checks'] as $checkName => $check) {
            $this->assertArrayHasKey('legal_basis', $check);
            $this->assertStringContainsString('ZKP', $check['legal_basis']);
        }
    }

    // Helper Methods

    protected function mockOpenAIRelevanceCheck(bool $relevant, string $reasoning): void
    {
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'relevant' => $relevant,
                                'reasoning' => $reasoning,
                            ]),
                        ],
                    ],
                ],
            ]);
    }
}
