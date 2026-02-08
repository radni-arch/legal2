<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Models\TextractJob;
use App\Pipelines\Textract\CollectLinesStep;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CollectLinesStepFormattingTest extends TestCase
{
    use UsesTestDatabase;

    protected CollectLinesStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->step = new CollectLinesStep;
    }

    protected function createTextractJson(array $blocks): string
    {
        $jsonPath = sys_get_temp_dir().'/textract-formatting-test-'.uniqid().'.json';
        file_put_contents($jsonPath, json_encode(['Blocks' => $blocks]));

        return $jsonPath;
    }

    /** @test */
    public function it_preserves_page_boundaries_in_collected_text(): void
    {
        $job = TextractJob::factory()->analyzing()->create();

        $blocks = [
            // Page 1
            [
                'BlockType' => 'PAGE',
                'Id' => 'page-1',
                'Page' => 1,
                'Geometry' => ['BoundingBox' => ['Width' => 1.0, 'Height' => 1.0, 'Left' => 0.0, 'Top' => 0.0]],
            ],
            [
                'BlockType' => 'LINE',
                'Id' => 'line-1',
                'Page' => 1,
                'Text' => 'Line 1 of page 1',
                'Geometry' => ['BoundingBox' => ['Width' => 0.5, 'Height' => 0.05, 'Left' => 0.1, 'Top' => 0.1]],
            ],
            [
                'BlockType' => 'LINE',
                'Id' => 'line-2',
                'Page' => 1,
                'Text' => 'Line 2 of page 1',
                'Geometry' => ['BoundingBox' => ['Width' => 0.5, 'Height' => 0.05, 'Left' => 0.1, 'Top' => 0.2]],
            ],
            // Page 2
            [
                'BlockType' => 'PAGE',
                'Id' => 'page-2',
                'Page' => 2,
                'Geometry' => ['BoundingBox' => ['Width' => 1.0, 'Height' => 1.0, 'Left' => 0.0, 'Top' => 0.0]],
            ],
            [
                'BlockType' => 'LINE',
                'Id' => 'line-3',
                'Page' => 2,
                'Text' => 'Line 1 of page 2',
                'Geometry' => ['BoundingBox' => ['Width' => 0.5, 'Height' => 0.05, 'Left' => 0.1, 'Top' => 0.1]],
            ],
            [
                'BlockType' => 'LINE',
                'Id' => 'line-4',
                'Page' => 2,
                'Text' => 'Line 2 of page 2',
                'Geometry' => ['BoundingBox' => ['Width' => 0.5, 'Height' => 0.05, 'Left' => 0.1, 'Top' => 0.2]],
            ],
        ];

        $jsonPath = $this->createTextractJson($blocks);

        $payload = [
            'job' => $job,
            'resultsMeta' => ['localJsonAbs' => $jsonPath],
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('textractText', $result);
        // Should contain text from both pages
        $this->assertStringContainsString('Line 1 of page 1', $result['textractText']);
        $this->assertStringContainsString('Line 2 of page 1', $result['textractText']);
        $this->assertStringContainsString('Line 1 of page 2', $result['textractText']);
        $this->assertStringContainsString('Line 2 of page 2', $result['textractText']);
        // Pages should be separated by double newline (page boundary)
        $this->assertMatchesRegularExpression('/page 1\n\n.*page 2/s', $result['textractText']);

        @unlink($jsonPath);
    }

    /** @test */
    public function it_stores_textract_text_in_payload(): void
    {
        $job = TextractJob::factory()->analyzing()->create();

        $blocks = [
            [
                'BlockType' => 'PAGE',
                'Id' => 'page-1',
                'Page' => 1,
                'Geometry' => ['BoundingBox' => ['Width' => 1.0, 'Height' => 1.0, 'Left' => 0.0, 'Top' => 0.0]],
            ],
            [
                'BlockType' => 'LINE',
                'Id' => 'line-1',
                'Page' => 1,
                'Text' => 'Presuda suda',
                'Geometry' => ['BoundingBox' => ['Width' => 0.5, 'Height' => 0.05, 'Left' => 0.1, 'Top' => 0.1]],
            ],
            [
                'BlockType' => 'LINE',
                'Id' => 'line-2',
                'Page' => 1,
                'Text' => 'Clanak 123.',
                'Geometry' => ['BoundingBox' => ['Width' => 0.5, 'Height' => 0.05, 'Left' => 0.1, 'Top' => 0.2]],
            ],
        ];

        $jsonPath = $this->createTextractJson($blocks);

        $payload = [
            'job' => $job,
            'resultsMeta' => ['localJsonAbs' => $jsonPath],
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('textractText', $result);
        $this->assertStringContainsString('Presuda suda', $result['textractText']);
        $this->assertStringContainsString('Clanak 123.', $result['textractText']);

        @unlink($jsonPath);
    }
}
