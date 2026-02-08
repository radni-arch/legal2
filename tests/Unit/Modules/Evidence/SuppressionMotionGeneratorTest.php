<?php

namespace Tests\Unit\Modules\Evidence;

use App\Models\Evidence;
use App\Models\LegalCase;
use App\Modules\Evidence\Services\ConstitutionalViolationDetector;
use App\Modules\Evidence\Services\EvidenceAdmissibilityChecker;
use App\Modules\Evidence\Services\SuppressionMotionGenerator;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class SuppressionMotionGeneratorTest extends TestCase
{
    use UsesTestDatabase;

    protected SuppressionMotionGenerator $generator;
    protected OpenAIService $mockOpenAI;
    protected EvidenceAdmissibilityChecker $mockAdmissibility;
    protected ConstitutionalViolationDetector $mockConstitutional;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->mockOpenAI = Mockery::mock(OpenAIService::class);
        $this->mockAdmissibility = Mockery::mock(EvidenceAdmissibilityChecker::class);
        $this->mockConstitutional = Mockery::mock(ConstitutionalViolationDetector::class);

        $this->generator = new SuppressionMotionGenerator(
            $this->mockOpenAI,
            $this->mockAdmissibility,
            $this->mockConstitutional
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_fetches_evidence_from_database()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = Evidence::factory()->create([
            'case_id' => $case->id,
            'type' => 'physical',
            'description' => 'Test evidence description',
        ]);

        // Mock the checkers to return safe values
        $this->mockAdmissibility
            ->shouldReceive('check')
            ->andReturn(['admissible' => true, 'issues' => []]);

        $this->mockConstitutional
            ->shouldReceive('detect')
            ->andReturn([]);

        $this->mockOpenAI
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Test motion text']],
                ],
            ]);

        // Act
        $result = $this->generator->generate($case, [$evidence->id]);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($case->id, $result['case_id']);
        $this->assertContains($evidence->id, $result['evidence_ids']);
    }

    /** @test */
    public function it_handles_nonexistent_evidence_gracefully()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $nonExistentId = '01HX0000000000000000000000';

        // Mock the checkers - they should NOT be called for null evidence
        $this->mockAdmissibility
            ->shouldReceive('check')
            ->never();

        $this->mockConstitutional
            ->shouldReceive('detect')
            ->never();

        $this->mockOpenAI
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Test motion text']],
                ],
            ]);

        // Act
        $result = $this->generator->generate($case, [$nonExistentId]);

        // Assert
        $this->assertIsArray($result);
        // Should still return a result but with no evidence analyses
        $this->assertEmpty($result['primary_grounds']);
    }

    /** @test */
    public function it_uses_evidence_description_in_analysis()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = Evidence::factory()->create([
            'case_id' => $case->id,
            'type' => 'document',
            'description' => 'Illegally obtained confession',
        ]);

        // Track what evidence is passed to the checkers
        $capturedEvidence = null;
        $this->mockAdmissibility
            ->shouldReceive('check')
            ->andReturnUsing(function ($evidenceArray) use (&$capturedEvidence) {
                $capturedEvidence = $evidenceArray;
                return ['admissible' => false, 'issues' => []];
            });

        $this->mockConstitutional
            ->shouldReceive('detect')
            ->andReturn([]);

        $this->mockOpenAI
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Test motion text']],
                ],
            ]);

        // Act
        $this->generator->generate($case, [$evidence->id]);

        // Assert - verify evidence array with description was passed to checkers
        $this->assertNotNull($capturedEvidence);
        $this->assertIsArray($capturedEvidence);
        $this->assertEquals('Illegally obtained confession', $capturedEvidence['description']);
        $this->assertEquals('document', $capturedEvidence['type']);
    }

    /** @test */
    public function it_processes_multiple_evidence_items()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence1 = Evidence::factory()->create(['case_id' => $case->id]);
        $evidence2 = Evidence::factory()->create(['case_id' => $case->id]);
        $evidence3 = Evidence::factory()->create(['case_id' => $case->id]);

        // Mock the checkers
        $this->mockAdmissibility
            ->shouldReceive('check')
            ->andReturn(['admissible' => true, 'issues' => []]);

        $this->mockConstitutional
            ->shouldReceive('detect')
            ->andReturn([]);

        $this->mockOpenAI
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Test motion text']],
                ],
            ]);

        // Act
        $result = $this->generator->generate($case, [
            $evidence1->id,
            $evidence2->id,
            $evidence3->id,
        ]);

        // Assert
        $this->assertCount(3, $result['evidence_ids']);
        $this->assertContains($evidence1->id, $result['evidence_ids']);
        $this->assertContains($evidence2->id, $result['evidence_ids']);
        $this->assertContains($evidence3->id, $result['evidence_ids']);
    }
}
