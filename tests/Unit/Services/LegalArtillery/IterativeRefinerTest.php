<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\RefinementResult;
use App\DTOs\SenderIdentity;
use App\Services\LegalArtillery\ArgumentValidator;
use App\Services\LegalArtillery\IterativeRefiner;
use App\Services\LegalArtillery\LlmClient;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class IterativeRefinerTest extends TestCase
{
    private MockInterface $llmClient;
    private MockInterface $validator;
    private IterativeRefiner $refiner;
    private DocumentProfile $profile;
    private CaseContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->llmClient = Mockery::mock(LlmClient::class);
        $this->validator = Mockery::mock(ArgumentValidator::class);

        $this->refiner = new IterativeRefiner(
            $this->llmClient,
            $this->validator,
        );

        $this->profile = new DocumentProfile(
            key: 'predsjednik_suda',
            name: 'Zahtjev predsjedniku suda',
            recipient: ['title' => 'Predsjednik Opcinski sud', 'address' => 'Test'],
            legalBasis: ['ZS cit. 10', 'ZKP cit. 239'],
            tone: 'formal_assertive',
            structure: ['Uvod', 'Cinjenice', 'Pravni temelj', 'Zahtjev'],
            docxTemplate: 'legal-formal',
        );

        $this->context = new CaseContext(
            caseNumber: 'Kir-123/2025',
            searchDate: '09.06.2025',
            archiveDate: '15.06.2025',
            addressSearched: 'Test Address 123',
            warrantReference: 'Kir-123/2025',
            policeRequestKlasa: '511-01-01/01-02/03',
            policeRequestUrbroj: '511-01-02-03-04-05',
            legalBasisWarrant: 'ZKP cit. 240',
            suspectedOffense: 'Test offense',
            judge: 'Test Judge',
            denialDate: '20.06.2025',
            countyCourtResponseDate: '25.06.2025',
            sender: new SenderIdentity(
                name: 'Test Sender',
                oib: '12345678901',
                address: 'Test Sender Address',
                email: 'test@example.com',
                phone: '+385911234567',
            ),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_refines_until_fire_ready(): void
    {
        // First validation: NEEDS_WORK with score 5
        $this->validator->shouldReceive('validate')
            ->once()
            ->with('Original content...', 'predsjednik_suda')
            ->andReturn([
                'overall_score' => 5,
                'verdict' => 'NEEDS_WORK',
                'improvements' => ['Add reference to Garcia Alva v. Germany'],
                'issues' => [],
            ]);

        // LLM refines the content
        $this->llmClient->shouldReceive('generate')
            ->once()
            ->andReturn('Improved content with Garcia Alva reference...');

        // Second validation: FIRE_READY with score 8
        $this->validator->shouldReceive('validate')
            ->once()
            ->with('Improved content with Garcia Alva reference...', 'predsjednik_suda')
            ->andReturn([
                'overall_score' => 8,
                'verdict' => 'FIRE_READY',
                'improvements' => [],
                'issues' => [],
            ]);

        $result = $this->refiner->refine(
            'Original content...',
            $this->profile,
            $this->context,
            maxIterations: 3
        );

        $this->assertInstanceOf(RefinementResult::class, $result);
        $this->assertEquals('FIRE_READY', $result->finalVerdict);
        $this->assertEquals(8, $result->finalScore);
        $this->assertEquals(2, $result->iterations);
        $this->assertEquals('Improved content with Garcia Alva reference...', $result->finalContent);
        $this->assertCount(2, $result->validationHistory);
        $this->assertContains('Add reference to Garcia Alva v. Germany', $result->improvements);
    }

    public function test_stops_at_max_iterations(): void
    {
        // All validations return NEEDS_WORK
        $this->validator->shouldReceive('validate')
            ->times(3)
            ->andReturn([
                'overall_score' => 5,
                'verdict' => 'NEEDS_WORK',
                'improvements' => ['Still needs work'],
                'issues' => [],
            ]);

        // LLM called twice for refinements (after iter 1 and 2, not after iter 3)
        $this->llmClient->shouldReceive('generate')
            ->times(2)
            ->andReturn('Still not good enough...');

        $result = $this->refiner->refine(
            'Original content...',
            $this->profile,
            $this->context,
            maxIterations: 3
        );

        $this->assertEquals('NEEDS_WORK', $result->finalVerdict);
        $this->assertEquals(5, $result->finalScore);
        $this->assertEquals(3, $result->iterations);
        $this->assertCount(3, $result->validationHistory);
    }

    public function test_returns_immediately_if_already_fire_ready(): void
    {
        // First validation already FIRE_READY
        $this->validator->shouldReceive('validate')
            ->once()
            ->andReturn([
                'overall_score' => 9,
                'verdict' => 'FIRE_READY',
                'improvements' => [],
                'issues' => [],
            ]);

        // LLM should never be called for refinement
        $this->llmClient->shouldNotReceive('generate');

        $result = $this->refiner->refine(
            'Already excellent content...',
            $this->profile,
            $this->context,
        );

        $this->assertEquals('FIRE_READY', $result->finalVerdict);
        $this->assertEquals(9, $result->finalScore);
        $this->assertEquals(1, $result->iterations);
        $this->assertEquals('Already excellent content...', $result->finalContent);
        $this->assertEmpty($result->improvements);
    }

    public function test_should_continue_returns_false_when_score_meets_threshold(): void
    {
        $validation = [
            'overall_score' => 8,
            'verdict' => 'FIRE_READY',
            'improvements' => [],
            'issues' => [],
        ];

        $this->assertFalse($this->refiner->shouldContinue($validation, 1));
    }

    public function test_should_continue_returns_true_when_score_below_threshold(): void
    {
        $validation = [
            'overall_score' => 7,
            'verdict' => 'NEEDS_WORK',
            'improvements' => ['Add more citations'],
            'issues' => [],
        ];

        $this->assertTrue($this->refiner->shouldContinue($validation, 1));
    }

    public function test_should_continue_returns_false_at_max_iterations(): void
    {
        $validation = [
            'overall_score' => 5,
            'verdict' => 'NEEDS_WORK',
            'improvements' => ['More work needed'],
            'issues' => [],
        ];

        // Even with low score, should stop at max iterations
        $this->assertFalse($this->refiner->shouldContinue($validation, 3, maxIterations: 3));
    }

    public function test_generates_refinement_prompt_with_improvements(): void
    {
        $validation = [
            'overall_score' => 5,
            'verdict' => 'NEEDS_WORK',
            'improvements' => [
                'Add reference to Garcia Alva v. Germany',
                'Strengthen proportionality argument',
            ],
            'issues' => [
                ['severity' => 'major', 'description' => 'Missing legal basis for claim'],
            ],
        ];

        $prompt = $this->refiner->generateRefinementPrompt(
            'Original content...',
            $validation
        );

        $this->assertStringContainsString('Garcia Alva v. Germany', $prompt);
        $this->assertStringContainsString('proportionality argument', $prompt);
        $this->assertStringContainsString('Original content...', $prompt);
        $this->assertStringContainsString('Missing legal basis', $prompt);
    }

    public function test_collects_all_improvements_across_iterations(): void
    {
        // First validation
        $this->validator->shouldReceive('validate')
            ->once()
            ->with('Original content...', 'predsjednik_suda')
            ->andReturn([
                'overall_score' => 4,
                'verdict' => 'NEEDS_WORK',
                'improvements' => ['Improvement A'],
                'issues' => [],
            ]);

        // First refinement
        $this->llmClient->shouldReceive('generate')
            ->once()
            ->andReturn('Better content...');

        // Second validation
        $this->validator->shouldReceive('validate')
            ->once()
            ->with('Better content...', 'predsjednik_suda')
            ->andReturn([
                'overall_score' => 6,
                'verdict' => 'NEEDS_WORK',
                'improvements' => ['Improvement B'],
                'issues' => [],
            ]);

        // Second refinement
        $this->llmClient->shouldReceive('generate')
            ->once()
            ->andReturn('Even better content...');

        // Third validation - passes
        $this->validator->shouldReceive('validate')
            ->once()
            ->with('Even better content...', 'predsjednik_suda')
            ->andReturn([
                'overall_score' => 8,
                'verdict' => 'FIRE_READY',
                'improvements' => [],
                'issues' => [],
            ]);

        $result = $this->refiner->refine(
            'Original content...',
            $this->profile,
            $this->context,
            maxIterations: 5
        );

        $this->assertEquals(3, $result->iterations);
        $this->assertContains('Improvement A', $result->improvements);
        $this->assertContains('Improvement B', $result->improvements);
    }

    public function test_threshold_is_configurable(): void
    {
        // Score of 7 should fail with default threshold of 8
        $this->validator->shouldReceive('validate')
            ->once()
            ->andReturn([
                'overall_score' => 7,
                'verdict' => 'STRONG',
                'improvements' => [],
                'issues' => [],
            ]);

        // Should not call LLM because we pass custom threshold=7
        $refinerWithLowerThreshold = new IterativeRefiner(
            $this->llmClient,
            $this->validator,
            qualityThreshold: 7
        );

        $result = $refinerWithLowerThreshold->refine(
            'Good enough content...',
            $this->profile,
            $this->context,
        );

        $this->assertEquals('STRONG', $result->finalVerdict);
        $this->assertEquals(7, $result->finalScore);
        $this->assertEquals(1, $result->iterations);
    }
}
