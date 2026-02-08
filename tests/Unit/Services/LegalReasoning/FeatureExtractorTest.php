<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\CaseFeature;
use App\Models\LegalCase;
use App\Services\LegalReasoning\FeatureExtractor;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class FeatureExtractorTest extends TestCase
{
    use UsesTestDatabase;

    protected FeatureExtractor $featureExtractor;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->featureExtractor = new FeatureExtractor($this->openAIMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_extracts_case_features_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'title' => 'Complex Contract Dispute',
            'description' => 'A complex commercial contract breach involving multiple parties.',
            'court' => 'Commercial Court',
        ]);

        $case->documents()->createMany([
            ['title' => 'Doc 1', 'content' => 'Document 1 content', 'file_path' => 'path1.pdf'],
            ['title' => 'Doc 2', 'content' => 'Document 2 content', 'file_path' => 'path2.pdf'],
        ]);

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ]);

        // Act
        $features = $this->featureExtractor->extractCaseFeatures($case);

        // Assert
        $this->assertIsArray($features);
        $this->assertArrayHasKey('case_type', $features);
        $this->assertArrayHasKey('complexity_score', $features);
        $this->assertArrayHasKey('embedding', $features);
        $this->assertArrayHasKey('legal_issues', $features);
        $this->assertCount(1536, $features['embedding']);
    }

    /** @test */
    public function it_calculates_complexity_score_correctly()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        // Create 25 documents with unique content to avoid duplicate content_hash
        for ($i = 1; $i <= 25; $i++) {
            $case->documents()->create([
                'title' => "Document $i",
                'content' => "Document content $i with some unique text",
                'file_path' => "path$i.pdf",
            ]);
        }

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ]);

        // Act
        $features = $this->featureExtractor->extractCaseFeatures($case);

        // Assert
        $this->assertGreaterThan(0, $features['complexity_score']);
        $this->assertLessThanOrEqual(1, $features['complexity_score']);
        $this->assertGreaterThanOrEqual(0.25, $features['complexity_score']); // 25 docs should be moderately complex
    }

    /** @test */
    public function it_persists_features_to_database()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ]);

        // Act
        $caseFeature = $this->featureExtractor->extractAndPersistCaseFeatures($case);

        // Assert
        $this->assertInstanceOf(CaseFeature::class, $caseFeature);
        $this->assertEquals($case->id, $caseFeature->case_id);
        $this->assertDatabaseHas('case_features', [
            'case_id' => $case->id,
        ]);
    }

    /** @test */
    public function it_uses_cached_features_when_recent()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $caseFeature = CaseFeature::create([
            'case_id' => $case->id,
            'case_type' => 'civil',
            'complexity_score' => 0.7,
            'embedding_vector' => array_fill(0, 1536, 0.1),
            'features_extracted_at' => now()->subDays(3), // Recent (< 7 days)
        ]);

        // OpenAI should NOT be called since we're using cache
        $this->openAIMock
            ->shouldNotReceive('complete');
        $this->openAIMock
            ->shouldNotReceive('embeddings');

        // Act
        $features = $this->featureExtractor->getCaseFeatures($case, false);

        // Assert
        $this->assertEquals('civil', $features['case_type']);
        $this->assertEquals(0.7, $features['complexity_score']);
    }

    /** @test */
    public function it_refreshes_features_when_requested()
    {
        // Arrange - Create case with criminal keywords in title/description
        $case = LegalCase::factory()->create([
            'title' => 'Criminal Case - Theft and Fraud',
            'description' => 'A case involving theft of property and fraud charges',
        ]);

        CaseFeature::create([
            'case_id' => $case->id,
            'case_type' => 'civil',
            'features_extracted_at' => now()->subDays(3),
        ]);

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ]);

        // Act
        $features = $this->featureExtractor->getCaseFeatures($case, true); // Force refresh

        // Assert
        $this->assertEquals('criminal', $features['case_type']); // Should be new value
    }

    /** @test */
    public function it_handles_empty_legal_issues_gracefully()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'description' => '',
        ]);

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ]);

        // Act
        $features = $this->featureExtractor->extractCaseFeatures($case);

        // Assert
        $this->assertIsArray($features['legal_issues']);
    }
}
