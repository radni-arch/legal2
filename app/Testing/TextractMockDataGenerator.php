<?php

namespace App\Testing;

use App\Models\TextractJob;
use Illuminate\Support\Str;

/**
 * Comprehensive mock data generator for Textract testing
 *
 * Provides factory methods for creating realistic test data including:
 * - TextractJob models with various states
 * - OCR documents with pages, lines, and blocks
 * - AWS Textract API responses
 * - Google Drive file listings
 * - Pipeline payloads
 *
 * Usage:
 *   $generator = new TextractMockDataGenerator();
 *   $job = $generator->createTextractJob();
 *   $ocrDoc = $generator->createOcrDocument(pages: 10);
 *   $apiResponse = $generator->createTextractApiResponse();
 */
class TextractMockDataGenerator
{
    /**
     * Default legal text samples for realistic content
     */
    protected const LEGAL_TEXT_SAMPLES = [
        'SUPREME COURT OF THE UNITED STATES',
        'IN THE MATTER OF THE APPLICATION OF',
        'MEMORANDUM OF LAW',
        'WHEREAS, the parties hereto agree that',
        'IT IS HEREBY ORDERED that the defendant shall',
        'The Court finds that the evidence presented',
        'Pursuant to Rule 56 of the Federal Rules of Civil Procedure',
        'The plaintiff respectfully requests that this Court',
        'Based on the foregoing facts and circumstances',
        'This Court has jurisdiction over this matter',
    ];

    /**
     * Create a TextractJob model with configurable attributes
     *
     * @param  array  $overrides  Attributes to override defaults
     */
    public function createTextractJob(array $overrides = []): TextractJob
    {
        $defaults = [
            'drive_file_id' => $this->generateDriveFileId(),
            'drive_file_name' => $this->generateFileName(),
            'case_id' => null,
            's3_key' => null,
            'job_id' => null,
            'status' => 'pending',
            'error' => null,
            'metadata' => [],
            'extracted_content' => null,
            'manual_content' => null,
            'manually_edited' => false,
            'content_edited_at' => null,
            'edited_by' => null,
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
            'embedding_synced_at' => null,
            'graph_synced_at' => null,
            'batch_id' => null,
            'queue_name' => null,
            'priority' => 0,
            'retry_count' => 0,
            'worker_id' => null,
            'queued_at' => null,
            'processing_started_at' => null,
            'performance_metrics' => null,
        ];

        return TextractJob::make(array_merge($defaults, $overrides));
    }

    /**
     * Create a succeeded TextractJob with extracted content
     *
     * @param  int  $pageCount  Number of pages of content
     * @param  array  $overrides  Additional overrides
     */
    public function createSucceededJob(int $pageCount = 5, array $overrides = []): TextractJob
    {
        return $this->createTextractJob(array_merge([
            'status' => 'succeeded',
            's3_key' => 'textract-input/'.Str::uuid().'.pdf',
            'job_id' => $this->generateAwsJobId(),
            'extracted_content' => $this->generateLegalContent($pageCount * 10),
            'metadata' => $this->generateMetadata(),
        ], $overrides));
    }

    /**
     * Create a failed TextractJob with error message
     *
     * @param  string  $errorMessage  Error message
     * @param  array  $overrides  Additional overrides
     */
    public function createFailedJob(string $errorMessage = 'Processing failed', array $overrides = []): TextractJob
    {
        return $this->createTextractJob(array_merge([
            'status' => 'failed',
            'error' => $errorMessage,
            's3_key' => 'textract-input/'.Str::uuid().'.pdf',
        ], $overrides));
    }

    /**
     * Create a processing TextractJob
     *
     * @param  array  $overrides  Additional overrides
     */
    public function createProcessingJob(array $overrides = []): TextractJob
    {
        return $this->createTextractJob(array_merge([
            'status' => 'processing',
            's3_key' => 'textract-input/'.Str::uuid().'.pdf',
            'job_id' => $this->generateAwsJobId(),
            'processing_started_at' => now(),
            'worker_id' => Str::uuid(),
        ], $overrides));
    }

    /**
     * Create a manually edited TextractJob
     *
     * @param  int  $userId  User ID who edited
     * @param  array  $overrides  Additional overrides
     */
    public function createEditedJob(int $userId, array $overrides = []): TextractJob
    {
        return $this->createTextractJob(array_merge([
            'status' => 'succeeded',
            'extracted_content' => $this->generateLegalContent(30),
            'manual_content' => $this->generateLegalContent(25),
            'manually_edited' => true,
            'content_edited_at' => now(),
            'edited_by' => $userId,
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
        ], $overrides));
    }

    /**
     * Create an OCR document structure with pages, lines, and blocks
     *
     * @param  int  $pageCount  Number of pages
     * @param  int  $linesPerPage  Lines per page
     * @param  bool  $includeBlocks  Include block-level data
     */
    public function createOcrDocument(
        int $pageCount = 5,
        int $linesPerPage = 30,
        bool $includeBlocks = true
    ): object {
        $pages = [];

        for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
            $pages[] = $this->createOcrPage($pageNum, $linesPerPage, $includeBlocks);
        }

        return (object) [
            'pages' => $pages,
            'documentMetadata' => [
                'pageCount' => $pageCount,
                'format' => 'PDF',
            ],
        ];
    }

    /**
     * Create a single OCR page
     *
     * @param  int  $pageNumber  Page number
     * @param  int  $lineCount  Number of lines
     * @param  bool  $includeBlocks  Include block-level data
     */
    public function createOcrPage(int $pageNumber, int $lineCount = 30, bool $includeBlocks = true): object
    {
        $lines = [];
        $blocks = [];

        for ($lineNum = 1; $lineNum <= $lineCount; $lineNum++) {
            $lineText = $this->generateLineText();
            $line = (object) [
                'text' => $lineText,
                'confidence' => $this->generateConfidence(),
                'geometry' => $this->generateGeometry(),
                'id' => "line-{$pageNumber}-{$lineNum}",
            ];
            $lines[] = $line;

            if ($includeBlocks) {
                $blocks[] = (object) [
                    'blockType' => 'LINE',
                    'text' => $lineText,
                    'confidence' => $line->confidence,
                    'geometry' => $line->geometry,
                    'id' => $line->id,
                    'relationships' => [
                        ['type' => 'CHILD', 'ids' => ["word-{$pageNumber}-{$lineNum}-1", "word-{$pageNumber}-{$lineNum}-2"]],
                    ],
                ];
            }
        }

        $page = (object) [
            'pageNumber' => $pageNumber,
            'lines' => $lines,
            'width' => 8.5,
            'height' => 11.0,
        ];

        if ($includeBlocks) {
            $page->blocks = $blocks;
        }

        return $page;
    }

    /**
     * Create AWS Textract API response structure
     *
     * @param  string  $status  Job status (IN_PROGRESS, SUCCEEDED, FAILED)
     * @param  int  $pageCount  Number of pages processed
     */
    public function createTextractApiResponse(
        string $status = 'SUCCEEDED',
        int $pageCount = 1
    ): array {
        $response = [
            'JobStatus' => $status,
            'DocumentMetadata' => [
                'Pages' => $pageCount,
            ],
        ];

        if ($status === 'SUCCEEDED') {
            $response['Blocks'] = $this->generateTextractBlocks($pageCount);
        } elseif ($status === 'FAILED') {
            $response['StatusMessage'] = 'Processing failed due to internal error';
        }

        return $response;
    }

    /**
     * Create Textract start job response
     */
    public function createStartJobResponse(): array
    {
        return [
            'JobId' => $this->generateAwsJobId(),
        ];
    }

    /**
     * Create Textract get result response
     *
     * @param  int  $pageCount  Number of pages
     */
    public function createGetResultResponse(int $pageCount = 5): array
    {
        return [
            'JobStatus' => 'SUCCEEDED',
            'DocumentMetadata' => [
                'Pages' => $pageCount,
            ],
            'Blocks' => $this->generateTextractBlocks($pageCount),
            'DetectDocumentTextModelVersion' => '1.0',
        ];
    }

    /**
     * Create Google Drive file listing
     *
     * @param  int  $fileCount  Number of files
     */
    public function createDriveFileList(int $fileCount = 10): array
    {
        $files = [];

        for ($i = 1; $i <= $fileCount; $i++) {
            $files[] = [
                'id' => $this->generateDriveFileId(),
                'name' => $this->generateFileName(),
                'mimeType' => 'application/pdf',
                'size' => rand(100000, 10000000),
                'createdTime' => now()->subDays(rand(1, 365))->toIso8601String(),
                'modifiedTime' => now()->subDays(rand(1, 30))->toIso8601String(),
            ];
        }

        return $files;
    }

    /**
     * Create a single Google Drive file
     *
     * @param  array  $overrides  Attribute overrides
     */
    public function createDriveFile(array $overrides = []): array
    {
        $defaults = [
            'id' => $this->generateDriveFileId(),
            'name' => $this->generateFileName(),
            'mimeType' => 'application/pdf',
            'size' => rand(100000, 10000000),
            'createdTime' => now()->subDays(30)->toIso8601String(),
            'modifiedTime' => now()->subDays(5)->toIso8601String(),
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create a pipeline payload structure
     *
     * @param  array  $overrides  Overrides for payload
     */
    public function createPipelinePayload(array $overrides = []): array
    {
        $defaults = [
            'driveFileId' => $this->generateDriveFileId(),
            'driveFileName' => $this->generateFileName(),
            'forceTextract' => false,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create a complete pipeline result with job and OCR document
     *
     * @param  int  $pageCount  Number of pages
     */
    public function createPipelineResult(int $pageCount = 5): array
    {
        $job = $this->createProcessingJob();

        return [
            'job' => $job,
            'ocrDocument' => $this->createOcrDocument($pageCount),
            's3Key' => 'textract-input/'.Str::uuid().'.pdf',
            'outKey' => 'textract-output/'.Str::uuid().'.pdf',
            'jobId' => $this->generateAwsJobId(),
        ];
    }

    /**
     * Generate Textract blocks for API response
     *
     * @param  int  $pageCount  Number of pages
     */
    protected function generateTextractBlocks(int $pageCount = 1): array
    {
        $blocks = [];

        for ($page = 1; $page <= $pageCount; $page++) {
            // Page block
            $blocks[] = [
                'BlockType' => 'PAGE',
                'Id' => "page-{$page}",
                'Page' => $page,
                'Geometry' => $this->generateGeometryArray(),
            ];

            // Line blocks
            for ($line = 1; $line <= 30; $line++) {
                $lineId = "line-{$page}-{$line}";
                $blocks[] = [
                    'BlockType' => 'LINE',
                    'Id' => $lineId,
                    'Text' => $this->generateLineText(),
                    'Confidence' => $this->generateConfidence(),
                    'Geometry' => $this->generateGeometryArray(),
                    'Page' => $page,
                    'Relationships' => [
                        [
                            'Type' => 'CHILD',
                            'Ids' => ["{$lineId}-word-1", "{$lineId}-word-2"],
                        ],
                    ],
                ];
            }
        }

        return $blocks;
    }

    /**
     * Generate a Google Drive file ID
     */
    protected function generateDriveFileId(): string
    {
        return Str::random(33);
    }

    /**
     * Generate an AWS Textract job ID
     */
    protected function generateAwsJobId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Generate a realistic PDF file name
     */
    protected function generateFileName(): string
    {
        $prefixes = ['Case', 'Document', 'Evidence', 'Exhibit', 'Motion', 'Brief', 'Complaint', 'Ruling'];
        $prefix = $prefixes[array_rand($prefixes)];
        $number = rand(100, 9999);

        return "{$prefix}_{$number}.pdf";
    }

    /**
     * Generate realistic legal content
     *
     * @param  int  $lineCount  Number of lines
     */
    protected function generateLegalContent(int $lineCount = 50): string
    {
        $content = [];

        for ($i = 0; $i < $lineCount; $i++) {
            $content[] = $this->generateLineText();
        }

        return implode("\n", $content);
    }

    /**
     * Generate a single line of legal text
     */
    protected function generateLineText(): string
    {
        $sample = self::LEGAL_TEXT_SAMPLES[array_rand(self::LEGAL_TEXT_SAMPLES)];

        // Add some variation
        $variations = [
            $sample,
            $sample.' dated '.now()->subDays(rand(1, 365))->format('F j, Y'),
            $sample.' Case No. '.rand(1000, 9999),
            $sample.' filed on '.now()->subDays(rand(1, 365))->format('m/d/Y'),
        ];

        return $variations[array_rand($variations)];
    }

    /**
     * Generate OCR confidence score
     */
    protected function generateConfidence(): float
    {
        // Most OCR is high confidence (85-99%)
        return round(rand(8500, 9900) / 100, 2);
    }

    /**
     * Generate geometry object
     */
    protected function generateGeometry(): object
    {
        return (object) [
            'boundingBox' => (object) [
                'width' => rand(10, 80) / 100,
                'height' => rand(1, 5) / 100,
                'left' => rand(5, 15) / 100,
                'top' => rand(10, 90) / 100,
            ],
            'polygon' => [
                (object) ['x' => rand(5, 15) / 100, 'y' => rand(10, 90) / 100],
                (object) ['x' => rand(85, 95) / 100, 'y' => rand(10, 90) / 100],
                (object) ['x' => rand(85, 95) / 100, 'y' => rand(11, 91) / 100],
                (object) ['x' => rand(5, 15) / 100, 'y' => rand(11, 91) / 100],
            ],
        ];
    }

    /**
     * Generate geometry array (for AWS format)
     */
    protected function generateGeometryArray(): array
    {
        return [
            'BoundingBox' => [
                'Width' => rand(10, 80) / 100,
                'Height' => rand(1, 5) / 100,
                'Left' => rand(5, 15) / 100,
                'Top' => rand(10, 90) / 100,
            ],
            'Polygon' => [
                ['X' => rand(5, 15) / 100, 'Y' => rand(10, 90) / 100],
                ['X' => rand(85, 95) / 100, 'Y' => rand(10, 90) / 100],
                ['X' => rand(85, 95) / 100, 'Y' => rand(11, 91) / 100],
                ['X' => rand(5, 15) / 100, 'Y' => rand(11, 91) / 100],
            ],
        ];
    }

    /**
     * Generate metadata for TextractJob
     */
    protected function generateMetadata(): array
    {
        return [
            'pageCount' => rand(1, 100),
            'fileSize' => rand(100000, 10000000),
            'processingTime' => rand(5, 300),
            'ocrQuality' => 'high',
            'averageConfidence' => $this->generateConfidence(),
            'detectedLanguage' => 'en',
        ];
    }

    /**
     * Create batch of TextractJobs
     *
     * @param  int  $count  Number of jobs to create
     * @param  string  $status  Status for all jobs
     * @return array<TextractJob>
     */
    public function createJobBatch(int $count = 10, string $status = 'pending'): array
    {
        $jobs = [];

        for ($i = 0; $i < $count; $i++) {
            $jobs[] = $this->createTextractJob(['status' => $status]);
        }

        return $jobs;
    }

    /**
     * Create a large OCR document for performance testing
     *
     * @param  int  $pageCount  Number of pages
     * @param  int  $linesPerPage  Lines per page
     */
    public function createLargeOcrDocument(int $pageCount = 100, int $linesPerPage = 50): object
    {
        return $this->createOcrDocument($pageCount, $linesPerPage, false);
    }

    /**
     * Create OCR document with low confidence scores
     *
     * @param  int  $pageCount  Number of pages
     */
    public function createLowConfidenceOcrDocument(int $pageCount = 5): object
    {
        $pages = [];

        for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
            $lines = [];
            for ($lineNum = 1; $lineNum <= 30; $lineNum++) {
                $lines[] = (object) [
                    'text' => $this->generateLineText(),
                    'confidence' => rand(4000, 7500) / 100, // Low confidence (40-75%)
                    'geometry' => $this->generateGeometry(),
                    'id' => "line-{$pageNum}-{$lineNum}",
                ];
            }

            $pages[] = (object) [
                'pageNumber' => $pageNum,
                'lines' => $lines,
                'width' => 8.5,
                'height' => 11.0,
            ];
        }

        return (object) ['pages' => $pages];
    }

    /**
     * Create OCR document with specific text content
     *
     * @param  array  $textByPage  Array of text arrays, indexed by page
     */
    public function createOcrDocumentWithContent(array $textByPage): object
    {
        $pages = [];
        $pageNum = 1;

        foreach ($textByPage as $pageLines) {
            $lines = [];
            foreach ($pageLines as $lineNum => $text) {
                $lines[] = (object) [
                    'text' => $text,
                    'confidence' => $this->generateConfidence(),
                    'geometry' => $this->generateGeometry(),
                    'id' => "line-{$pageNum}-{$lineNum}",
                ];
            }

            $pages[] = (object) [
                'pageNumber' => $pageNum,
                'lines' => $lines,
                'width' => 8.5,
                'height' => 11.0,
            ];

            $pageNum++;
        }

        return (object) ['pages' => $pages];
    }

    /**
     * Create an error response from AWS Textract
     *
     * @param  string  $errorCode  Error code
     * @param  string  $errorMessage  Error message
     */
    public function createTextractErrorResponse(
        string $errorCode = 'InternalServerError',
        string $errorMessage = 'An internal error occurred'
    ): array {
        return [
            'Error' => [
                'Code' => $errorCode,
                'Message' => $errorMessage,
            ],
            'JobStatus' => 'FAILED',
            'StatusMessage' => $errorMessage,
        ];
    }

    /**
     * Create performance metrics for a job
     */
    public function createPerformanceMetrics(): array
    {
        return [
            'download_time' => rand(1, 30),
            'upload_time' => rand(1, 20),
            'textract_time' => rand(30, 300),
            'total_time' => rand(50, 400),
            'file_size' => rand(100000, 10000000),
            'page_count' => rand(1, 100),
            'memory_peak' => rand(50, 200),
            'cpu_time' => rand(10, 100),
        ];
    }
}
