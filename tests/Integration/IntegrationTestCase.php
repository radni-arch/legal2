<?php

namespace Tests\Integration;

use App\Services\DecisionSearchService;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Doubles\FakeOpenAIService;
use Tests\TestCase;

/**
 * Base class for Integration tests
 *
 * Provides common infrastructure:
 * - Mocks external services (OpenAI, S3, etc.)
 * - Database refresh before each test for proper isolation
 * - Automatic cache clearing between tests
 * - Shared test utilities
 *
 * Usage:
 *   class MyIntegrationTest extends IntegrationTestCase
 *   {
 *       public function test_something()
 *       {
 *           // External services are automatically mocked
 *           // Database is refreshed before each test
 *       }
 *   }
 */
abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeOpenAIService $fakeOpenAI;

    protected bool $mockExternalServices = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache to ensure test isolation
        Cache::flush();

        if ($this->mockExternalServices) {
            $this->setupExternalServiceMocks();
        }
    }

    /**
     * Setup external service mocks to prevent real API calls
     */
    protected function setupExternalServiceMocks(): void
    {
        $this->setupOpenAIMock();
        $this->setupDecisionSearchMock();
        $this->setupStorageMock();
    }

    /**
     * Mock OpenAI Service to prevent real API calls
     */
    protected function setupOpenAIMock(): void
    {
        $this->fakeOpenAI = new FakeOpenAIService;
        $this->app->instance(OpenAIService::class, $this->fakeOpenAI);
    }

    /**
     * Mock DecisionSearchService to prevent vector search and embeddings
     */
    protected function setupDecisionSearchMock(): void
    {
        $this->mock(DecisionSearchService::class, function ($mock) {
            // Mock the search() method that's actually called
            $mock->shouldReceive('search')
                ->andReturn([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                    'metadata' => [
                        'query' => 'test',
                        'filters' => [],
                    ],
                ]);

            // Mock findSimilarCases() for legacy code
            $mock->shouldReceive('findSimilarCases')
                ->andReturn([]);

            // Mock vectorSearch() if called directly
            $mock->shouldReceive('vectorSearch')
                ->andReturn([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                ]);
        });
    }

    /**
     * Mock S3 storage to prevent real file uploads
     */
    protected function setupStorageMock(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
    }

    /**
     * Helper: Queue a chat response for next OpenAI chat() call
     */
    protected function queueChatResponse(string|array $content): void
    {
        if (is_string($content)) {
            $content = [
                'choices' => [
                    [
                        'message' => [
                            'content' => $content,
                        ],
                    ],
                ],
            ];
        }

        $this->fakeOpenAI->queueChatResponse($content);
    }

    /**
     * Helper: Queue an embedding response for next OpenAI embedding call
     */
    protected function queueEmbeddingResponse(?array $embedding = null): void
    {
        $embedding = $embedding ?? array_fill(0, 1536, 0.1);
        $this->fakeOpenAI->queueEmbeddingResponse($embedding);
    }

    /**
     * Helper: Assert OpenAI chat was called N times
     */
    protected function assertChatCalled(int $times): void
    {
        $this->assertEquals(
            $times,
            $this->fakeOpenAI->getChatCallCount(),
            "Expected OpenAI chat() to be called {$times} times, but was called {$this->fakeOpenAI->getChatCallCount()} times"
        );
    }

    /**
     * Helper: Assert OpenAI embedding was called N times
     */
    protected function assertEmbeddingCalled(int $times): void
    {
        $this->assertEquals(
            $times,
            $this->fakeOpenAI->getEmbeddingCallCount(),
            "Expected OpenAI createEmbedding() to be called {$times} times, but was called {$this->fakeOpenAI->getEmbeddingCallCount()} times"
        );
    }
}
