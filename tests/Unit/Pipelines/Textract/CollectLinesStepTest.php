<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Models\TextractJob;
use App\Pipelines\Textract\CollectLinesStep;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CollectLinesStepTest extends TestCase
{
    use UsesTestDatabase;

    protected CollectLinesStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->step = new CollectLinesStep;
    }

    protected function createTextractJson(array $blocks = []): string
    {
        $jsonPath = sys_get_temp_dir().'/textract-test-'.uniqid().'.json';

        if (empty($blocks)) {
            // Create a simple valid Textract JSON with PAGE, LINE, and WORD blocks
            $blocks = [
                [
                    'BlockType' => 'PAGE',
                    'Id' => 'page-1',
                    'Page' => 1,
                    'Geometry' => [
                        'BoundingBox' => [
                            'Width' => 1.0,
                            'Height' => 1.0,
                            'Left' => 0.0,
                            'Top' => 0.0,
                        ],
                    ],
                ],
                [
                    'BlockType' => 'LINE',
                    'Id' => 'line-1',
                    'Page' => 1,
                    'Text' => 'Sample line 1',
                    'Geometry' => [
                        'BoundingBox' => [
                            'Width' => 0.5,
                            'Height' => 0.05,
                            'Left' => 0.1,
                            'Top' => 0.1,
                        ],
                    ],
                    'Relationships' => [
                        [
                            'Type' => 'CHILD',
                            'Ids' => ['word-1', 'word-2'],
                        ],
                    ],
                ],
                [
                    'BlockType' => 'WORD',
                    'Id' => 'word-1',
                    'Page' => 1,
                    'Text' => 'Sample',
                    'Geometry' => [
                        'BoundingBox' => [
                            'Width' => 0.2,
                            'Height' => 0.05,
                            'Left' => 0.1,
                            'Top' => 0.1,
                        ],
                    ],
                ],
                [
                    'BlockType' => 'WORD',
                    'Id' => 'word-2',
                    'Page' => 1,
                    'Text' => 'line',
                    'Geometry' => [
                        'BoundingBox' => [
                            'Width' => 0.15,
                            'Height' => 0.05,
                            'Left' => 0.32,
                            'Top' => 0.1,
                        ],
                    ],
                ],
            ];
        }

        file_put_contents($jsonPath, json_encode(['Blocks' => $blocks]));

        return $jsonPath;
    }

    /** @test */
    public function it_analyzes_textract_layout_from_json_file()
    {
        $job = TextractJob::factory()->analyzing()->create();

        // Create a real Textract JSON file
        $jsonPath = $this->createTextractJson();

        $payload = [
            'job' => $job,
            'resultsMeta' => [
                'localJsonAbs' => $jsonPath,
            ],
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('ocrDocument', $result);
        $this->assertIsObject($result['ocrDocument']);
        $this->assertObjectHasProperty('pages', $result['ocrDocument']);
        $this->assertIsArray($result['ocrDocument']->pages);

        // Cleanup
        @unlink($jsonPath);
    }

    /** @test */
    public function it_updates_job_status_to_reconstructing()
    {
        $job = TextractJob::factory()->analyzing()->create();

        // Create a real Textract JSON file
        $jsonPath = $this->createTextractJson();

        $payload = [
            'job' => $job,
            'resultsMeta' => [
                'localJsonAbs' => $jsonPath,
            ],
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertEquals('reconstructing', $job->status);

        // Cleanup
        @unlink($jsonPath);
    }

    /** @test */
    public function it_throws_exception_if_json_path_not_in_payload()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $payload = [
            'job' => $job,
            'resultsMeta' => [
                // No localJsonAbs
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Textract JSON file not found for analysis');

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_throws_exception_if_json_file_does_not_exist()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $payload = [
            'job' => $job,
            'resultsMeta' => [
                'localJsonAbs' => '/non/existent/file.json',
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Textract JSON file not found for analysis');

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_adds_ocr_document_to_payload()
    {
        $job = TextractJob::factory()->analyzing()->create();

        // Create Textract JSON with 2 pages and some lines
        $blocks = [
            // Page 1
            [
                'BlockType' => 'PAGE',
                'Id' => 'page-1',
                'Page' => 1,
                'Geometry' => [
                    'BoundingBox' => [
                        'Width' => 1.0,
                        'Height' => 1.0,
                        'Left' => 0.0,
                        'Top' => 0.0,
                    ],
                ],
            ],
            [
                'BlockType' => 'LINE',
                'Id' => 'line-1',
                'Page' => 1,
                'Text' => 'Page 1 content',
                'Geometry' => [
                    'BoundingBox' => [
                        'Width' => 0.5,
                        'Height' => 0.05,
                        'Left' => 0.1,
                        'Top' => 0.1,
                    ],
                ],
            ],
            // Page 2
            [
                'BlockType' => 'PAGE',
                'Id' => 'page-2',
                'Page' => 2,
                'Geometry' => [
                    'BoundingBox' => [
                        'Width' => 1.0,
                        'Height' => 1.0,
                        'Left' => 0.0,
                        'Top' => 0.0,
                    ],
                ],
            ],
            [
                'BlockType' => 'LINE',
                'Id' => 'line-2',
                'Page' => 2,
                'Text' => 'Page 2 content',
                'Geometry' => [
                    'BoundingBox' => [
                        'Width' => 0.5,
                        'Height' => 0.05,
                        'Left' => 0.1,
                        'Top' => 0.1,
                    ],
                ],
            ],
        ];

        $jsonPath = $this->createTextractJson($blocks);

        $payload = [
            'job' => $job,
            'resultsMeta' => [
                'localJsonAbs' => $jsonPath,
            ],
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('ocrDocument', $result);
        $this->assertIsObject($result['ocrDocument']);
        $this->assertCount(2, $result['ocrDocument']->pages);

        // Cleanup
        @unlink($jsonPath);
    }

    /** @test */
    public function it_passes_all_payload_to_next_step()
    {
        $job = TextractJob::factory()->analyzing()->create();

        // Create a real Textract JSON file
        $jsonPath = $this->createTextractJson();

        $payload = [
            'job' => $job,
            'resultsMeta' => [
                'localJsonAbs' => $jsonPath,
                's3JsonKey' => 'textract/output/test.json',
            ],
            'blocks' => [],
            'driveFileId' => 'file-123',
            'customData' => 'preserved',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('customData', $result);
        $this->assertEquals('preserved', $result['customData']);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertArrayHasKey('resultsMeta', $result);
        $this->assertArrayHasKey('ocrDocument', $result);

        // Cleanup
        @unlink($jsonPath);
    }
}
