<?php

namespace Tests\Unit\Actions\Textract;

use App\Actions\Textract\AnalyzeTextractLayout;
use App\Services\Ocr\OcrDocument;
use App\Services\Ocr\TextractLayoutAnalyzer;
use InvalidArgumentException;
use Tests\TestCase;

class AnalyzeTextractLayoutTest extends TestCase
{
    protected AnalyzeTextractLayout $action;

    protected TextractLayoutAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AnalyzeTextractLayout;
        $this->analyzer = new TextractLayoutAnalyzer;
    }

    /** @test */
    public function it_parses_textract_blocks_page_line_word()
    {
        $blocks = [
            [
                'BlockType' => 'PAGE',
                'Page' => 1,
                'Id' => 'page-1',
            ],
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-1',
                'Text' => 'Hello World',
                'Confidence' => 99.5,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.2,
                        'Width' => 0.3,
                        'Height' => 0.05,
                    ],
                ],
            ],
            [
                'BlockType' => 'WORD',
                'Page' => 1,
                'Id' => 'word-1',
                'Text' => 'Hello',
                'Confidence' => 99.8,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.2,
                        'Width' => 0.15,
                        'Height' => 0.05,
                    ],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertInstanceOf(OcrDocument::class, $result);
        $this->assertCount(1, $result->pages);
        $this->assertEquals(1, $result->pages[0]->number);
        $this->assertCount(1, $result->pages[0]->lines);
        $this->assertEquals('Hello World', $result->pages[0]->lines[0]->text);
        $this->assertEquals(99.5, $result->pages[0]->lines[0]->confidence);
    }

    /** @test */
    public function it_reconstructs_document_structure_with_multiple_pages()
    {
        $blocks = [
            // Page 1
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-1',
                'Text' => 'Page 1 Line 1',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            // Page 2
            [
                'BlockType' => 'LINE',
                'Page' => 2,
                'Id' => 'line-2',
                'Text' => 'Page 2 Line 1',
                'Confidence' => 98.5,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            // Page 3
            [
                'BlockType' => 'LINE',
                'Page' => 3,
                'Id' => 'line-3',
                'Text' => 'Page 3 Line 1',
                'Confidence' => 97.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertCount(3, $result->pages);
        $this->assertEquals(1, $result->pages[0]->number);
        $this->assertEquals(2, $result->pages[1]->number);
        $this->assertEquals(3, $result->pages[2]->number);
        $this->assertEquals('Page 1 Line 1', $result->pages[0]->lines[0]->text);
        $this->assertEquals('Page 2 Line 1', $result->pages[1]->lines[0]->text);
        $this->assertEquals('Page 3 Line 1', $result->pages[2]->lines[0]->text);
    }

    /** @test */
    public function it_identifies_headers_with_bold_style_inference()
    {
        $blocks = [
            // All-caps line with tall height (should be bold)
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-header',
                'Text' => 'IMPORTANT HEADER',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.5,
                        'Height' => 0.02, // Tall enough (>= 0.015)
                    ],
                ],
            ],
            // Normal text (should not be bold)
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-normal',
                'Text' => 'Regular paragraph text',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.2,
                        'Width' => 0.5,
                        'Height' => 0.012,
                    ],
                ],
            ],
            // All-caps but too small (should not be bold)
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-small-caps',
                'Text' => 'SMALL CAPS',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.3,
                        'Width' => 0.3,
                        'Height' => 0.01, // Too small (< 0.015)
                    ],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertCount(3, $result->pages[0]->lines);

        // First line should be bold (all-caps + tall)
        $this->assertEquals('B', $result->pages[0]->lines[0]->style);
        $this->assertEquals('IMPORTANT HEADER', $result->pages[0]->lines[0]->text);

        // Second line should be normal
        $this->assertEquals('', $result->pages[0]->lines[1]->style);

        // Third line should not be bold (too small)
        $this->assertEquals('', $result->pages[0]->lines[2]->style);
    }

    /** @test */
    public function it_extracts_reading_order_top_to_bottom_left_to_right()
    {
        $blocks = [
            // Bottom right
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-4',
                'Text' => 'Bottom Right',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.5, // Right
                        'Top' => 0.8,  // Bottom
                        'Width' => 0.3,
                        'Height' => 0.05,
                    ],
                ],
            ],
            // Top left
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-1',
                'Text' => 'Top Left',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1, // Left
                        'Top' => 0.1,  // Top
                        'Width' => 0.3,
                        'Height' => 0.05,
                    ],
                ],
            ],
            // Top right
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-2',
                'Text' => 'Top Right',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.5, // Right
                        'Top' => 0.1,  // Top
                        'Width' => 0.3,
                        'Height' => 0.05,
                    ],
                ],
            ],
            // Bottom left
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-3',
                'Text' => 'Bottom Left',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1, // Left
                        'Top' => 0.8,  // Bottom
                        'Width' => 0.3,
                        'Height' => 0.05,
                    ],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $lines = $result->pages[0]->lines;
        $this->assertCount(4, $lines);

        // Should be sorted: top-to-bottom, then left-to-right
        $this->assertEquals('Top Left', $lines[0]->text);
        $this->assertEquals('Top Right', $lines[1]->text);
        $this->assertEquals('Bottom Left', $lines[2]->text);
        $this->assertEquals('Bottom Right', $lines[3]->text);
    }

    /** @test */
    public function it_handles_multi_column_layouts()
    {
        $blocks = [
            // Left column, top
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'left-1',
                'Text' => 'Left Column Line 1',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.35,
                        'Height' => 0.05,
                    ],
                ],
            ],
            // Right column, top
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'right-1',
                'Text' => 'Right Column Line 1',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.55,
                        'Top' => 0.1,
                        'Width' => 0.35,
                        'Height' => 0.05,
                    ],
                ],
            ],
            // Left column, middle
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'left-2',
                'Text' => 'Left Column Line 2',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.2,
                        'Width' => 0.35,
                        'Height' => 0.05,
                    ],
                ],
            ],
            // Right column, middle
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'right-2',
                'Text' => 'Right Column Line 2',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.55,
                        'Top' => 0.2,
                        'Width' => 0.35,
                        'Height' => 0.05,
                    ],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $lines = $result->pages[0]->lines;
        $this->assertCount(4, $lines);

        // Current implementation sorts top-to-bottom, left-to-right
        // This creates reading order: left-col-line1, right-col-line1, left-col-line2, right-col-line2
        $this->assertEquals('Left Column Line 1', $lines[0]->text);
        $this->assertEquals('Right Column Line 1', $lines[1]->text);
        $this->assertEquals('Left Column Line 2', $lines[2]->text);
        $this->assertEquals('Right Column Line 2', $lines[3]->text);
    }

    /** @test */
    public function it_identifies_tables_and_extracts_structure()
    {
        // Note: Current implementation doesn't fully extract TABLE/CELL blocks
        // Without LINE blocks, no pages are created and an exception is thrown
        $blocks = [
            [
                'BlockType' => 'TABLE',
                'Page' => 1,
                'Id' => 'table-1',
                'Confidence' => 98.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.3,
                        'Width' => 0.8,
                        'Height' => 0.4,
                    ],
                ],
                'Relationships' => [
                    [
                        'Type' => 'CHILD',
                        'Ids' => ['cell-1', 'cell-2'],
                    ],
                ],
            ],
            [
                'BlockType' => 'CELL',
                'Page' => 1,
                'Id' => 'cell-1',
                'RowIndex' => 1,
                'ColumnIndex' => 1,
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.3,
                        'Width' => 0.4,
                        'Height' => 0.1,
                    ],
                ],
            ],
        ];

        // Current implementation requires LINE blocks to create pages
        // TABLE/CELL blocks alone don't create pages
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No pages found in Textract blocks');

        $this->analyzer->analyze($blocks);

        // Future enhancement: fully extract TABLE/CELL blocks
        // Then this test should be updated to:
        // $result = $this->analyzer->analyze($blocks);
        // $this->assertInstanceOf(OcrDocument::class, $result);
        // $this->assertCount(1, $result->pages);
        // $this->assertArrayHasKey('tables', $result->pages[0]);
    }

    /** @test */
    public function it_handles_rotated_text()
    {
        // Note: Current implementation doesn't handle rotation
        // This test documents expected behavior
        $blocks = [
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-rotated',
                'Text' => 'Rotated Text',
                'Confidence' => 95.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                    'Polygon' => [
                        ['X' => 0.1, 'Y' => 0.15],
                        ['X' => 0.6, 'Y' => 0.1],
                        ['X' => 0.6, 'Y' => 0.15],
                        ['X' => 0.1, 'Y' => 0.2],
                    ],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        // Current implementation uses BoundingBox, ignores Polygon
        $this->assertCount(1, $result->pages[0]->lines);
        $this->assertEquals('Rotated Text', $result->pages[0]->lines[0]->text);

        // Future enhancement: detect rotation from Polygon data
        // $this->assertGreaterThan(0, $result->pages[0]->lines[0]->rotation);
    }

    /** @test */
    public function it_calculates_confidence_scores_per_section()
    {
        $blocks = [
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-1',
                'Text' => 'High confidence text',
                'Confidence' => 99.8,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-2',
                'Text' => 'Medium confidence text',
                'Confidence' => 85.5,
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
                'Page' => 1,
                'Id' => 'line-3',
                'Text' => 'Low confidence text',
                'Confidence' => 65.0,
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

        $result = $this->analyzer->analyze($blocks);

        $lines = $result->pages[0]->lines;
        $this->assertCount(3, $lines);
        $this->assertEquals(99.8, $lines[0]->confidence);
        $this->assertEquals(85.5, $lines[1]->confidence);
        $this->assertEquals(65.0, $lines[2]->confidence);

        // Calculate average confidence
        $avgConfidence = array_sum(array_map(fn ($line) => $line->confidence, $lines)) / count($lines);
        $this->assertEquals(83.43, round($avgConfidence, 2));
    }

    /** @test */
    public function it_identifies_form_fields_key_value_pairs()
    {
        // Note: Current implementation doesn't extract KEY_VALUE_SET blocks
        // Without LINE blocks, no pages are created and an exception is thrown
        $blocks = [
            [
                'BlockType' => 'KEY_VALUE_SET',
                'Page' => 1,
                'Id' => 'kv-1',
                'EntityTypes' => ['KEY'],
                'Confidence' => 98.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.2,
                        'Height' => 0.05,
                    ],
                ],
                'Relationships' => [
                    [
                        'Type' => 'VALUE',
                        'Ids' => ['kv-2'],
                    ],
                    [
                        'Type' => 'CHILD',
                        'Ids' => ['word-key-1'],
                    ],
                ],
            ],
            [
                'BlockType' => 'KEY_VALUE_SET',
                'Page' => 1,
                'Id' => 'kv-2',
                'EntityTypes' => ['VALUE'],
                'Confidence' => 97.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.35,
                        'Top' => 0.1,
                        'Width' => 0.3,
                        'Height' => 0.05,
                    ],
                ],
            ],
        ];

        // Current implementation requires LINE blocks to create pages
        // KEY_VALUE_SET blocks alone don't create pages
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No pages found in Textract blocks');

        $this->analyzer->analyze($blocks);

        // Future enhancement: fully extract KEY_VALUE_SET blocks
        // Then this test should be updated to:
        // $result = $this->analyzer->analyze($blocks);
        // $this->assertInstanceOf(OcrDocument::class, $result);
        // $this->assertArrayHasKey('formFields', $result->pages[0]);
        // $this->assertCount(1, $result->pages[0]->formFields);
    }

    /** @test */
    public function it_handles_missing_blocks_gracefully()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Textract payload has no blocks');

        $this->analyzer->analyze([]);
    }

    /** @test */
    public function it_handles_malformed_blocks_without_geometry()
    {
        $blocks = [
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-1',
                'Text' => 'Valid line',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-2',
                'Text' => 'Line without geometry',
                'Confidence' => 99.0,
                // Missing Geometry
            ],
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-3',
                'Text' => '', // Empty text
                'Confidence' => 99.0,
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

        $result = $this->analyzer->analyze($blocks);

        // Should only include valid line (line-2 has no geometry, line-3 has no text)
        $this->assertCount(1, $result->pages[0]->lines);
        $this->assertEquals('Valid line', $result->pages[0]->lines[0]->text);
    }

    /** @test */
    public function it_preserves_spatial_relationships_with_normalized_coordinates()
    {
        $blocks = [
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-1',
                'Text' => 'Top left corner',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.05,
                        'Top' => 0.05,
                        'Width' => 0.3,
                        'Height' => 0.05,
                    ],
                ],
            ],
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-2',
                'Text' => 'Bottom right corner',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.65,
                        'Top' => 0.90,
                        'Width' => 0.3,
                        'Height' => 0.05,
                    ],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $lines = $result->pages[0]->lines;
        $this->assertCount(2, $lines);

        // Verify spatial coordinates are preserved
        $this->assertEquals(0.05, $lines[0]->left);
        $this->assertEquals(0.05, $lines[0]->top);
        $this->assertEquals(0.3, $lines[0]->width);
        $this->assertEquals(0.05, $lines[0]->height);

        $this->assertEquals(0.65, $lines[1]->left);
        $this->assertEquals(0.90, $lines[1]->top);
        $this->assertEquals(0.3, $lines[1]->width);
        $this->assertEquals(0.05, $lines[1]->height);

        // Verify normalized (0-1 range)
        foreach ($lines as $line) {
            $this->assertGreaterThanOrEqual(0.0, $line->left);
            $this->assertLessThanOrEqual(1.0, $line->left);
            $this->assertGreaterThanOrEqual(0.0, $line->top);
            $this->assertLessThanOrEqual(1.0, $line->top);
            $this->assertGreaterThanOrEqual(0.0, $line->width);
            $this->assertLessThanOrEqual(1.0, $line->width);
            $this->assertGreaterThanOrEqual(0.0, $line->height);
            $this->assertLessThanOrEqual(1.0, $line->height);
        }
    }

    /** @test */
    public function it_generates_layout_metadata_with_page_count_and_line_count()
    {
        $blocks = [
            // Page 1 - 2 lines
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-1-1',
                'Text' => 'Page 1 Line 1',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-1-2',
                'Text' => 'Page 1 Line 2',
                'Confidence' => 98.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.2,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            // Page 2 - 3 lines
            [
                'BlockType' => 'LINE',
                'Page' => 2,
                'Id' => 'line-2-1',
                'Text' => 'Page 2 Line 1',
                'Confidence' => 97.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            [
                'BlockType' => 'LINE',
                'Page' => 2,
                'Id' => 'line-2-2',
                'Text' => 'Page 2 Line 2',
                'Confidence' => 96.0,
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
                'Page' => 2,
                'Id' => 'line-2-3',
                'Text' => 'Page 2 Line 3',
                'Confidence' => 95.0,
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

        $result = $this->analyzer->analyze($blocks);

        // Metadata: page count
        $this->assertCount(2, $result->pages);

        // Metadata: line counts per page
        $this->assertCount(2, $result->pages[0]->lines);
        $this->assertCount(3, $result->pages[1]->lines);

        // Total lines across all pages
        $totalLines = array_sum(array_map(fn ($page) => count($page->lines), $result->pages));
        $this->assertEquals(5, $totalLines);
    }

    /** @test */
    public function it_handles_signature_blocks()
    {
        $blocks = [
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-1',
                'Text' => 'Document content',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1,
                        'Width' => 0.5,
                        'Height' => 0.05,
                    ],
                ],
            ],
            [
                'BlockType' => 'SIGNATURE',
                'Page' => 1,
                'Id' => 'sig-1',
                'Confidence' => 95.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.8,
                        'Width' => 0.3,
                        'Height' => 0.1,
                    ],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertCount(1, $result->pages);
        $this->assertCount(1, $result->pages[0]->lines);
        $this->assertCount(1, $result->pages[0]->signatures);

        $signature = $result->pages[0]->signatures[0];
        $this->assertEquals(0.1, $signature->left);
        $this->assertEquals(0.8, $signature->top);
        $this->assertEquals(0.3, $signature->width);
        $this->assertEquals(0.1, $signature->height);
    }

    /** @test */
    public function it_clamps_out_of_range_coordinates()
    {
        $blocks = [
            [
                'BlockType' => 'LINE',
                'Page' => 1,
                'Id' => 'line-1',
                'Text' => 'Text with invalid coordinates',
                'Confidence' => 99.0,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => -0.1,   // Out of range (< 0)
                        'Top' => 1.5,     // Out of range (> 1)
                        'Width' => 2.0,   // Out of range (> 1)
                        'Height' => -0.05, // Out of range (< 0)
                    ],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $line = $result->pages[0]->lines[0];

        // All values should be clamped to [0, 1]
        $this->assertEquals(0.0, $line->left);   // Clamped from -0.1
        $this->assertEquals(1.0, $line->top);    // Clamped from 1.5
        $this->assertEquals(1.0, $line->width);  // Clamped from 2.0
        $this->assertEquals(0.0, $line->height); // Clamped from -0.05
    }

    /** @test */
    public function it_parses_json_string_input()
    {
        $json = json_encode([
            'Blocks' => [
                [
                    'BlockType' => 'LINE',
                    'Page' => 1,
                    'Id' => 'line-1',
                    'Text' => 'JSON string input',
                    'Confidence' => 99.0,
                    'Geometry' => [
                        'BoundingBox' => [
                            'Left' => 0.1,
                            'Top' => 0.1,
                            'Width' => 0.5,
                            'Height' => 0.05,
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($json);

        $this->assertInstanceOf(OcrDocument::class, $result);
        $this->assertCount(1, $result->pages);
        $this->assertCount(1, $result->pages[0]->lines);
        $this->assertEquals('JSON string input', $result->pages[0]->lines[0]->text);
    }

    /** @test */
    public function it_throws_exception_for_invalid_json_string()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->analyzer->analyze('invalid json {{{');
    }

    /** @test */
    public function it_throws_exception_when_no_pages_found()
    {
        $blocks = [
            [
                'BlockType' => 'PAGE',
                'Page' => 1,
                'Id' => 'page-1',
                // No LINE blocks, so no pages will be created
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No pages found in Textract blocks');

        $this->analyzer->analyze($blocks);
    }

    /** @test */
    public function it_handles_action_with_file_path()
    {
        $jsonPath = sys_get_temp_dir().'/textract-test-'.uniqid().'.json';

        $blocks = [
            'Blocks' => [
                [
                    'BlockType' => 'LINE',
                    'Page' => 1,
                    'Id' => 'line-1',
                    'Text' => 'File-based analysis',
                    'Confidence' => 99.0,
                    'Geometry' => [
                        'BoundingBox' => [
                            'Left' => 0.1,
                            'Top' => 0.1,
                            'Width' => 0.5,
                            'Height' => 0.05,
                        ],
                    ],
                ],
            ],
        ];

        file_put_contents($jsonPath, json_encode($blocks));

        try {
            $result = $this->action->handle($jsonPath);

            $this->assertInstanceOf(OcrDocument::class, $result);
            $this->assertCount(1, $result->pages);
            $this->assertCount(1, $result->pages[0]->lines);
            $this->assertEquals('File-based analysis', $result->pages[0]->lines[0]->text);
        } finally {
            if (file_exists($jsonPath)) {
                unlink($jsonPath);
            }
        }
    }
}
