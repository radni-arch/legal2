<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\LegalCase;
use App\Services\CaseVectorStoreService;
use App\Services\LegalReasoning\FeatureExtractor;
use App\Services\LegalReasoning\OutcomePredictor;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class OutcomePredictorTest extends TestCase
{
    use UsesTestDatabase;

    protected OutcomePredictor $outcomePredictor;

    protected $openAIMock;

    protected $vectorStoreMock;

    protected $featureExtractorMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->vectorStoreMock = Mockery::mock(CaseVectorStoreService::class);
        $this->featureExtractorMock = Mockery::mock(FeatureExtractor::class);

        $this->outcomePredictor = new OutcomePredictor(
            $this->openAIMock,
            $this->vectorStoreMock,
            $this->featureExtractorMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_predicts_outcome_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'title' => 'Test Case',
            'court' => 'Supreme Court',
            'jurisdiction' => 'HR',
        ]);

        $mockFeatures = [
            'case_type' => 'civil',
            'complexity_score' => 0.7,
            'court' => 'Supreme Court',
            'jurisdiction' => 'HR',
            'legal_issues' => ['contract', 'liability'],
            'embedding' => array_fill(0, 1536, 0.1),
        ];

        $this->featureExtractorMock
            ->shouldReceive('extractCaseFeatures')
            ->once()
            ->with(Mockery::type(LegalCase::class))
            ->andReturn($mockFeatures);

        // Mock vector search results
        DB::shouldReceive('table')
            ->andReturnSelf();
        DB::shouldReceive('join')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->andReturnSelf();
        DB::shouldReceive('selectRaw')
            ->andReturnSelf();
        DB::shouldReceive('where')
            ->andReturnSelf();
        DB::shouldReceive('whereNotNull')
            ->andReturnSelf();
        DB::shouldReceive('raw')
            ->andReturn(Mockery::mock('Illuminate\Database\Query\Expression'));
        DB::shouldReceive('orderByDesc')
            ->andReturnSelf();
        DB::shouldReceive('limit')
            ->andReturnSelf();
        DB::shouldReceive('get')
            ->andReturn(collect([
                (object) [
                    'id' => 'test-case-1',
                    'case_title' => 'Similar Case',
                    'outcome' => 'win',
                    'similarity' => 0.85,
                ],
            ]));

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Analysis: Case shows strong precedent for favorable outcome.']],
                ],
            ]);

        // Act
        $result = $this->outcomePredictor->predictOutcome($case->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('predicted_outcome', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('probability_distribution', $result);
        $this->assertArrayHasKey('similar_cases', $result);
        $this->assertArrayHasKey('reasoning', $result);
        $this->assertArrayHasKey('key_factors', $result);
    }

    /** @test */
    public function it_handles_case_not_found()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->outcomePredictor->predictOutcome('non-existent-id');
    }

    /** @test */
    public function it_calculates_probability_distribution_correctly()
    {
        // Arrange
        $outcomes = collect([
            (object) ['status' => 'won'],
            (object) ['status' => 'won'],
            (object) ['status' => 'lost'],
            (object) ['status' => 'settled'],
        ]);

        // Act
        $distribution = $this->invokePrivateMethod(
            $this->outcomePredictor,
            'analyzeSimilarOutcomes',
            [$outcomes]
        );

        // Assert
        $this->assertIsArray($distribution);
        $this->assertArrayHasKey('most_likely', $distribution);
        $this->assertArrayHasKey('favorable_pct', $distribution);
        $this->assertArrayHasKey('unfavorable_pct', $distribution);
        $this->assertEquals(75.0, $distribution['favorable_pct']); // (2 won + 1 settled) / 4 = 75%
        $this->assertEquals(25.0, $distribution['unfavorable_pct']); // 1 lost / 4 = 25%
    }

    /** @test */
    public function it_identifies_key_factors_from_features()
    {
        // Arrange
        $features = [
            'case_type' => 'criminal',
            'complexity_score' => 0.8,
            'document_count' => 50,
        ];

        $similarCases = collect([
            (object) ['case_title' => 'Case 1', 'similarity' => 0.9],
        ]);

        // Act
        $keyFactors = $this->invokePrivateMethod(
            $this->outcomePredictor,
            'identifyKeyFactors',
            [$features, $similarCases]
        );

        // Assert
        $this->assertIsArray($keyFactors);
        $this->assertNotEmpty($keyFactors);
    }

    /**
     * Helper method to invoke private methods for testing
     */
    protected function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
