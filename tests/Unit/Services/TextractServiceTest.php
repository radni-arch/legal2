<?php

namespace Tests\Unit\Services;

use App\Services\TextractService;
use Aws\Result;
use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class TextractServiceTest extends TestCase
{
    protected $textractClientMock;

    protected TextractService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the TextractClient
        $this->textractClientMock = Mockery::mock(TextractClient::class);

        // Create service with mocked client
        $this->service = Mockery::mock(TextractService::class)->makePartial();
        $this->service->shouldAllowMockingProtectedMethods();

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->service, $this->textractClientMock);

        $bucketProperty = $reflection->getProperty('bucket');
        $bucketProperty->setAccessible(true);
        $bucketProperty->setValue($this->service, 'test-bucket');

        $inputPrefixProperty = $reflection->getProperty('inputPrefix');
        $inputPrefixProperty->setAccessible(true);
        $inputPrefixProperty->setValue($this->service, 'textract/input');

        $jsonPrefixProperty = $reflection->getProperty('jsonPrefix');
        $jsonPrefixProperty->setAccessible(true);
        $jsonPrefixProperty->setValue($this->service, 'textract/json');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_starts_document_text_detection_successfully(): void
    {
        // Arrange
        $s3Key = 'textract/input/test-file.pdf';
        $jobId = 'test-job-123';

        $awsResult = new Result([
            'JobId' => $jobId,
        ]);

        $this->textractClientMock->shouldReceive('startDocumentTextDetection')
            ->once()
            ->withArgs(function ($params) use ($s3Key) {
                return $params['DocumentLocation']['S3Object']['Bucket'] === 'test-bucket'
                    && $params['DocumentLocation']['S3Object']['Name'] === $s3Key
                    && isset($params['JobTag']);
            })
            ->andReturn($awsResult);

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Act
        $result = $this->service->startDocumentTextDetection($s3Key);

        // Assert
        $this->assertEquals($jobId, $result);
    }

    public function test_starts_document_text_detection_with_custom_job_tag(): void
    {
        // Arrange
        $s3Key = 'textract/input/test-file.pdf';
        $customTag = 'custom-job-tag-123';
        $jobId = 'test-job-456';

        $awsResult = new Result(['JobId' => $jobId]);

        $this->textractClientMock->shouldReceive('startDocumentTextDetection')
            ->once()
            ->withArgs(function ($params) use ($customTag) {
                return $params['JobTag'] === $customTag;
            })
            ->andReturn($awsResult);

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Act
        $result = $this->service->startDocumentTextDetection($s3Key, $customTag);

        // Assert
        $this->assertEquals($jobId, $result);
    }

    public function test_waits_and_fetches_text_detection_when_succeeded_immediately(): void
    {
        // Arrange
        $jobId = 'test-job-789';
        $blocks = [
            ['BlockType' => 'LINE', 'Text' => 'Line 1'],
            ['BlockType' => 'LINE', 'Text' => 'Line 2'],
        ];

        $awsResult = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => $blocks,
            'NextToken' => null,
        ]);

        $this->textractClientMock->shouldReceive('getDocumentTextDetection')
            ->once()
            ->andReturn($awsResult);

        // Act
        $result = $this->service->waitAndFetchTextDetection($jobId, 1, 10);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('Line 1', $result[0]['Text']);
        $this->assertEquals('Line 2', $result[1]['Text']);
    }

    public function test_waits_and_fetches_text_detection_with_pagination(): void
    {
        // Arrange
        $jobId = 'test-job-paginated';

        // First call - still in progress
        $statusResult1 = new Result([
            'JobStatus' => 'IN_PROGRESS',
        ]);

        // Second call - succeeded with pagination
        $statusResult2 = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [
                ['BlockType' => 'LINE', 'Text' => 'Page 1 Line 1'],
            ],
            'NextToken' => 'token-123',
        ]);

        // Third call - next page
        $statusResult3 = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [
                ['BlockType' => 'LINE', 'Text' => 'Page 2 Line 1'],
            ],
            'NextToken' => null,
        ]);

        $this->textractClientMock->shouldReceive('getDocumentTextDetection')
            ->times(3)
            ->andReturn($statusResult1, $statusResult2, $statusResult3);

        // Act
        $result = $this->service->waitAndFetchTextDetection($jobId, 1, 10);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('Page 1 Line 1', $result[0]['Text']);
        $this->assertEquals('Page 2 Line 1', $result[1]['Text']);
    }

    public function test_waits_and_fetches_throws_exception_on_failed_status(): void
    {
        // Arrange
        $jobId = 'test-job-failed';

        $awsResult = new Result([
            'JobStatus' => 'FAILED',
        ]);

        $this->textractClientMock->shouldReceive('getDocumentTextDetection')
            ->once()
            ->andReturn($awsResult);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('status: FAILED');

        $this->service->waitAndFetchTextDetection($jobId, 1, 10);
    }

    public function test_waits_and_fetches_throws_exception_on_timeout(): void
    {
        // Arrange
        $jobId = 'test-job-timeout';

        $awsResult = new Result([
            'JobStatus' => 'IN_PROGRESS',
        ]);

        // Always return IN_PROGRESS to trigger timeout
        $this->textractClientMock->shouldReceive('getDocumentTextDetection')
            ->andReturn($awsResult);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('timed out');

        $this->service->waitAndFetchTextDetection($jobId, 1, 3);
    }

    public function test_starts_document_analysis_with_default_features(): void
    {
        // Arrange
        $s3Key = 'textract/input/analysis-test.pdf';
        $jobId = 'analysis-job-123';

        $awsResult = new Result(['JobId' => $jobId]);

        $this->textractClientMock->shouldReceive('startDocumentAnalysis')
            ->once()
            ->withArgs(function ($params) use ($s3Key) {
                return $params['DocumentLocation']['S3Object']['Bucket'] === 'test-bucket'
                    && $params['DocumentLocation']['S3Object']['Name'] === $s3Key
                    && in_array('LAYOUT', $params['FeatureTypes'])
                    && in_array('FORMS', $params['FeatureTypes'])
                    && in_array('TABLES', $params['FeatureTypes'])
                    && in_array('SIGNATURES', $params['FeatureTypes']);
            })
            ->andReturn($awsResult);

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Act
        $result = $this->service->startDocumentAnalysis($s3Key);

        // Assert
        $this->assertEquals($jobId, $result);
    }

    public function test_starts_document_analysis_with_custom_features(): void
    {
        // Arrange
        $s3Key = 'textract/input/custom-features.pdf';
        $customFeatures = ['TABLES', 'FORMS'];
        $jobId = 'analysis-job-custom';

        $awsResult = new Result(['JobId' => $jobId]);

        $this->textractClientMock->shouldReceive('startDocumentAnalysis')
            ->once()
            ->withArgs(function ($params) use ($customFeatures) {
                return $params['FeatureTypes'] === $customFeatures;
            })
            ->andReturn($awsResult);

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Act
        $result = $this->service->startDocumentAnalysis($s3Key, null, $customFeatures);

        // Assert
        $this->assertEquals($jobId, $result);
    }

    public function test_starts_document_analysis_filters_invalid_features(): void
    {
        // Arrange
        $s3Key = 'textract/input/invalid-features.pdf';
        $invalidFeatures = ['INVALID_FEATURE', 'TABLES', 'ANOTHER_INVALID'];
        $jobId = 'analysis-job-filtered';

        $awsResult = new Result(['JobId' => $jobId]);

        $this->textractClientMock->shouldReceive('startDocumentAnalysis')
            ->once()
            ->withArgs(function ($params) {
                // Should only have TABLES (the valid feature)
                return $params['FeatureTypes'] === ['TABLES'];
            })
            ->andReturn($awsResult);

        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        // Act
        $result = $this->service->startDocumentAnalysis($s3Key, null, $invalidFeatures);

        // Assert
        $this->assertEquals($jobId, $result);
    }

    public function test_waits_and_fetches_document_analysis(): void
    {
        // Arrange
        $jobId = 'analysis-job-wait';
        $blocks = [
            ['BlockType' => 'LINE', 'Text' => 'Analysis Line 1'],
            ['BlockType' => 'TABLE', 'Confidence' => 99.5],
        ];

        $awsResult = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => $blocks,
            'NextToken' => null,
        ]);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andReturn($awsResult);

        Log::shouldReceive('debug')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);

        // Act
        $result = $this->service->waitAndFetchDocumentAnalysis($jobId, 1, 10);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('LINE', $result[0]['BlockType']);
        $this->assertEquals('TABLE', $result[1]['BlockType']);
    }

    public function test_collects_lines_by_page(): void
    {
        // Arrange
        $blocks = [
            [
                'BlockType' => 'LINE',
                'Text' => 'Page 1 Line 1',
                'Page' => 1,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.2,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            [
                'BlockType' => 'LINE',
                'Text' => 'Page 1 Line 2',
                'Page' => 1,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.3,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            [
                'BlockType' => 'LINE',
                'Text' => 'Page 2 Line 1',
                'Page' => 2,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.6,
                        'Height' => 0.04,
                    ],
                ],
            ],
            [
                'BlockType' => 'WORD',
                'Text' => 'Should be ignored',
                'Page' => 1,
            ],
        ];

        // Act
        $result = $this->service->collectLinesByPage($blocks);

        // Assert
        $this->assertCount(2, $result); // 2 pages
        $this->assertCount(2, $result[1]); // Page 1 has 2 lines
        $this->assertCount(1, $result[2]); // Page 2 has 1 line

        $this->assertEquals('Page 1 Line 1', $result[1][0]['text']);
        $this->assertEquals(0.1, $result[1][0]['left']);
        $this->assertEquals('Page 2 Line 1', $result[2][0]['text']);
    }

    public function test_saves_results_to_s3_and_local(): void
    {
        // Arrange
        Storage::fake('s3');
        Storage::fake('local');

        $driveFileId = 'test-drive-file-id';
        $blocks = [
            ['BlockType' => 'LINE', 'Text' => 'Test content'],
        ];

        Log::shouldReceive('info')->andReturn(null);

        // Act
        $result = $this->service->saveResultsToS3AndLocal($driveFileId, $blocks);

        // Assert
        $this->assertArrayHasKey('s3JsonKey', $result);
        $this->assertArrayHasKey('localJsonRel', $result);
        $this->assertArrayHasKey('localJsonAbs', $result);

        $this->assertEquals('textract/json/test-drive-file-id.json', $result['s3JsonKey']);
        $this->assertEquals('textract/json/test-drive-file-id.json', $result['localJsonRel']);

        // Verify files were created
        Storage::disk('s3')->assertExists($result['s3JsonKey']);
        Storage::disk('local')->assertExists($result['localJsonRel']);

        // Verify content
        $s3Content = Storage::disk('s3')->get($result['s3JsonKey']);
        $decodedContent = json_decode($s3Content, true);
        $this->assertCount(1, $decodedContent);
        $this->assertEquals('Test content', $decodedContent[0]['Text']);
    }

    public function test_uploads_to_s3(): void
    {
        // Arrange
        Storage::fake('s3');

        $localPath = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($localPath, 'Test PDF content');
        $s3Key = 'textract/input/uploaded-file.pdf';

        // Act
        $result = $this->service->uploadToS3($localPath, $s3Key);

        // Assert
        $this->assertEquals($s3Key, $result);
        Storage::disk('s3')->assertExists($s3Key);

        // Cleanup
        unlink($localPath);
    }

    public function test_sanitizes_job_tag_removes_invalid_characters(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeJobTag');
        $method->setAccessible(true);

        // Test various invalid characters
        $this->assertEquals('test-file-name-pdf', $method->invoke($this->service, 'test file@name!.pdf'));
        $this->assertEquals('legal-doc-2024', $method->invoke($this->service, 'legal#doc$2024'));
    }

    public function test_sanitizes_job_tag_enforces_max_length(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeJobTag');
        $method->setAccessible(true);

        $longTag = str_repeat('a', 100);
        $result = $method->invoke($this->service, $longTag);

        $this->assertLessThanOrEqual(64, strlen($result));
    }

    public function test_sanitizes_job_tag_handles_empty_string(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeJobTag');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, '');

        $this->assertNotEmpty($result);
        $this->assertStringStartsWith('job-', $result);
    }

    public function test_collects_lines_by_page_ignores_missing_geometry(): void
    {
        // Arrange
        $blocks = [
            [
                'BlockType' => 'LINE',
                'Text' => 'Valid line',
                'Page' => 1,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.2,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            [
                'BlockType' => 'LINE',
                'Text' => 'No geometry',
                'Page' => 1,
                // Missing Geometry
            ],
            [
                'BlockType' => 'LINE',
                'Text' => '', // Empty text
                'Page' => 1,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.3,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
        ];

        // Act
        $result = $this->service->collectLinesByPage($blocks);

        // Assert
        $this->assertCount(1, $result); // Only page 1
        $this->assertCount(1, $result[1]); // Only 1 valid line
        $this->assertEquals('Valid line', $result[1][0]['text']);
    }

    public function test_waits_and_fetches_document_analysis_handles_partial_success(): void
    {
        // Arrange
        $jobId = 'analysis-job-partial';

        $awsResult = new Result([
            'JobStatus' => 'PARTIAL_SUCCESS',
        ]);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andReturn($awsResult);

        Log::shouldReceive('debug')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PARTIAL_SUCCESS');

        $this->service->waitAndFetchDocumentAnalysis($jobId, 1, 10);
    }
}
