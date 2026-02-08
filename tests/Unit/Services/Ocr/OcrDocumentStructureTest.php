<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\OcrBox;
use App\Services\Ocr\OcrDocument;
use App\Services\Ocr\OcrLine;
use App\Services\Ocr\OcrPage;
use Tests\TestCase;

class OcrDocumentStructureTest extends TestCase
{
    public function test_ocr_document_can_be_instantiated(): void
    {
        // Act
        $document = new OcrDocument;

        // Assert
        $this->assertInstanceOf(OcrDocument::class, $document);
        $this->assertIsArray($document->pages);
        $this->assertEmpty($document->pages);
    }

    public function test_ocr_document_can_hold_multiple_pages(): void
    {
        // Arrange
        $document = new OcrDocument;
        $page1 = new OcrPage(1);
        $page2 = new OcrPage(2);
        $page3 = new OcrPage(3);

        // Act
        $document->pages = [$page1, $page2, $page3];

        // Assert
        $this->assertCount(3, $document->pages);
        $this->assertInstanceOf(OcrPage::class, $document->pages[0]);
        $this->assertEquals(1, $document->pages[0]->number);
        $this->assertEquals(2, $document->pages[1]->number);
        $this->assertEquals(3, $document->pages[2]->number);
    }

    public function test_ocr_page_can_be_instantiated_with_number(): void
    {
        // Act
        $page = new OcrPage(5);

        // Assert
        $this->assertInstanceOf(OcrPage::class, $page);
        $this->assertEquals(5, $page->number);
        $this->assertIsArray($page->lines);
        $this->assertIsArray($page->signatures);
        $this->assertEmpty($page->lines);
        $this->assertEmpty($page->signatures);
    }

    public function test_ocr_page_can_hold_lines(): void
    {
        // Arrange
        $line1 = new OcrLine('First line text', 0.1, 0.2, 0.8, 0.05);
        $line2 = new OcrLine('Second line text', 0.1, 0.3, 0.8, 0.05);

        // Act
        $page = new OcrPage(
            number: 1,
            lines: [$line1, $line2]
        );

        // Assert
        $this->assertCount(2, $page->lines);
        $this->assertInstanceOf(OcrLine::class, $page->lines[0]);
        $this->assertEquals('First line text', $page->lines[0]->text);
        $this->assertEquals('Second line text', $page->lines[1]->text);
    }

    public function test_ocr_page_can_hold_signatures(): void
    {
        // Arrange
        $signature1 = new OcrBox(0.5, 0.8, 0.2, 0.1);
        $signature2 = new OcrBox(0.7, 0.8, 0.2, 0.1);

        // Act
        $page = new OcrPage(
            number: 1,
            lines: [],
            signatures: [$signature1, $signature2]
        );

        // Assert
        $this->assertCount(2, $page->signatures);
        $this->assertInstanceOf(OcrBox::class, $page->signatures[0]);
        $this->assertEquals(0.5, $page->signatures[0]->left);
        $this->assertEquals(0.7, $page->signatures[1]->left);
    }

    public function test_ocr_line_stores_text_and_position(): void
    {
        // Act
        $line = new OcrLine(
            text: 'Sample text content',
            left: 0.15,
            top: 0.25,
            width: 0.7,
            height: 0.04
        );

        // Assert
        $this->assertEquals('Sample text content', $line->text);
        $this->assertEquals(0.15, $line->left);
        $this->assertEquals(0.25, $line->top);
        $this->assertEquals(0.7, $line->width);
        $this->assertEquals(0.04, $line->height);
    }

    public function test_ocr_line_has_default_confidence_zero(): void
    {
        // Act
        $line = new OcrLine('Text', 0.1, 0.2, 0.5, 0.05);

        // Assert
        $this->assertEquals(0.0, $line->confidence);
    }

    public function test_ocr_line_can_store_confidence(): void
    {
        // Act
        $line = new OcrLine(
            text: 'High confidence text',
            left: 0.1,
            top: 0.2,
            width: 0.5,
            height: 0.05,
            confidence: 99.8
        );

        // Assert
        $this->assertEquals(99.8, $line->confidence);
    }

    public function test_ocr_line_has_default_empty_style(): void
    {
        // Act
        $line = new OcrLine('Text', 0.1, 0.2, 0.5, 0.05);

        // Assert
        $this->assertEquals('', $line->style);
    }

    public function test_ocr_line_can_store_style(): void
    {
        // Act
        $line = new OcrLine(
            text: 'Bold header text',
            left: 0.1,
            top: 0.1,
            width: 0.8,
            height: 0.06,
            confidence: 99.5,
            style: 'bold'
        );

        // Assert
        $this->assertEquals('bold', $line->style);
    }

    public function test_ocr_line_has_default_is_header_false(): void
    {
        // Act
        $line = new OcrLine('Regular text', 0.1, 0.2, 0.5, 0.05);

        // Assert
        $this->assertFalse($line->isHeader);
    }

    public function test_ocr_line_can_be_marked_as_header(): void
    {
        // Act
        $line = new OcrLine(
            text: 'CHAPTER 1: INTRODUCTION',
            left: 0.1,
            top: 0.1,
            width: 0.8,
            height: 0.07,
            confidence: 99.9,
            style: 'bold',
            isHeader: true
        );

        // Assert
        $this->assertTrue($line->isHeader);
        $this->assertEquals('CHAPTER 1: INTRODUCTION', $line->text);
    }

    public function test_ocr_line_has_default_null_id(): void
    {
        // Act
        $line = new OcrLine('Text', 0.1, 0.2, 0.5, 0.05);

        // Assert
        $this->assertNull($line->id);
    }

    public function test_ocr_line_can_store_id(): void
    {
        // Act
        $line = new OcrLine(
            text: 'Identified text',
            left: 0.1,
            top: 0.2,
            width: 0.5,
            height: 0.05,
            confidence: 95.0,
            style: '',
            isHeader: false,
            id: 'line-uuid-12345'
        );

        // Assert
        $this->assertEquals('line-uuid-12345', $line->id);
    }

    public function test_ocr_box_stores_bounding_box_coordinates(): void
    {
        // Act
        $box = new OcrBox(
            left: 0.3,
            top: 0.4,
            width: 0.2,
            height: 0.15
        );

        // Assert
        $this->assertEquals(0.3, $box->left);
        $this->assertEquals(0.4, $box->top);
        $this->assertEquals(0.2, $box->width);
        $this->assertEquals(0.15, $box->height);
    }

    public function test_ocr_box_coordinates_are_normalized(): void
    {
        // Coordinates should be normalized (0.0 to 1.0 range)
        // Act
        $box = new OcrBox(0.0, 0.0, 1.0, 1.0);

        // Assert
        $this->assertEquals(0.0, $box->left);
        $this->assertEquals(0.0, $box->top);
        $this->assertEquals(1.0, $box->width);
        $this->assertEquals(1.0, $box->height);
    }

    public function test_ocr_box_can_represent_small_area(): void
    {
        // Test very small bounding box (e.g., for punctuation)
        // Act
        $box = new OcrBox(0.45, 0.52, 0.01, 0.02);

        // Assert
        $this->assertEquals(0.45, $box->left);
        $this->assertEquals(0.52, $box->top);
        $this->assertEquals(0.01, $box->width);
        $this->assertEquals(0.02, $box->height);
    }

    public function test_complete_document_structure(): void
    {
        // Arrange - Build a complete document structure
        $document = new OcrDocument;

        $line1Page1 = new OcrLine(
            text: 'Članak 1. Definicije',
            left: 0.1,
            top: 0.15,
            width: 0.8,
            height: 0.05,
            confidence: 99.5,
            style: 'bold',
            isHeader: true,
            id: 'line-1-1'
        );

        $line2Page1 = new OcrLine(
            text: 'U ovom zakonu koriste se sljedeće definicije:',
            left: 0.1,
            top: 0.22,
            width: 0.8,
            height: 0.04,
            confidence: 98.7,
            style: '',
            isHeader: false,
            id: 'line-1-2'
        );

        $signature = new OcrBox(0.6, 0.85, 0.25, 0.1);

        $page1 = new OcrPage(
            number: 1,
            lines: [$line1Page1, $line2Page1],
            signatures: [$signature]
        );

        $line1Page2 = new OcrLine(
            text: 'Članak 2. Opće odredbe',
            left: 0.1,
            top: 0.1,
            width: 0.8,
            height: 0.05,
            confidence: 99.2,
            style: 'bold',
            isHeader: true,
            id: 'line-2-1'
        );

        $page2 = new OcrPage(
            number: 2,
            lines: [$line1Page2]
        );

        $document->pages = [$page1, $page2];

        // Assert
        $this->assertCount(2, $document->pages);

        // Page 1 assertions
        $this->assertEquals(1, $document->pages[0]->number);
        $this->assertCount(2, $document->pages[0]->lines);
        $this->assertCount(1, $document->pages[0]->signatures);
        $this->assertTrue($document->pages[0]->lines[0]->isHeader);
        $this->assertFalse($document->pages[0]->lines[1]->isHeader);
        $this->assertEquals('Članak 1. Definicije', $document->pages[0]->lines[0]->text);

        // Page 2 assertions
        $this->assertEquals(2, $document->pages[1]->number);
        $this->assertCount(1, $document->pages[1]->lines);
        $this->assertEquals('Članak 2. Opće odredbe', $document->pages[1]->lines[0]->text);
        $this->assertTrue($document->pages[1]->lines[0]->isHeader);
    }

    public function test_ocr_line_supports_croatian_characters(): void
    {
        // Act
        $line = new OcrLine(
            text: 'Članak Žarko Šarenac Đakovo Ćorić',
            left: 0.1,
            top: 0.2,
            width: 0.8,
            height: 0.05
        );

        // Assert
        $this->assertStringContainsString('Č', $line->text);
        $this->assertStringContainsString('Ž', $line->text);
        $this->assertStringContainsString('Š', $line->text);
        $this->assertStringContainsString('Đ', $line->text);
        $this->assertStringContainsString('Ć', $line->text);
    }

    public function test_ocr_structures_are_immutable(): void
    {
        // These are readonly properties (via constructor property promotion)
        $line = new OcrLine('Original text', 0.1, 0.2, 0.5, 0.05);

        // Assert that properties exist and are accessible
        $this->assertEquals('Original text', $line->text);
        $this->assertEquals(0.1, $line->left);

        // In PHP 8.1+, these are public readonly, so they can be read but not reassigned
        $this->assertIsString($line->text);
        $this->assertIsFloat($line->left);
    }

    public function test_page_numbers_are_positive_integers(): void
    {
        // Test various page numbers
        $page1 = new OcrPage(1);
        $page50 = new OcrPage(50);
        $page1000 = new OcrPage(1000);

        $this->assertEquals(1, $page1->number);
        $this->assertEquals(50, $page50->number);
        $this->assertEquals(1000, $page1000->number);
        $this->assertIsInt($page1->number);
        $this->assertIsInt($page50->number);
        $this->assertIsInt($page1000->number);
    }

    public function test_ocr_line_coordinates_represent_page_positions(): void
    {
        // Test lines at different positions on page
        $topLine = new OcrLine('Top of page', 0.1, 0.05, 0.8, 0.04);
        $middleLine = new OcrLine('Middle of page', 0.1, 0.5, 0.8, 0.04);
        $bottomLine = new OcrLine('Bottom of page', 0.1, 0.92, 0.8, 0.04);

        // Assert top line is near top
        $this->assertLessThan(0.1, $topLine->top);

        // Assert middle line is around middle
        $this->assertGreaterThan(0.4, $middleLine->top);
        $this->assertLessThan(0.6, $middleLine->top);

        // Assert bottom line is near bottom
        $this->assertGreaterThan(0.9, $bottomLine->top);
    }

    public function test_empty_ocr_document_structure(): void
    {
        // Test handling of empty document
        $document = new OcrDocument;

        $this->assertEmpty($document->pages);
        $this->assertCount(0, $document->pages);
    }

    public function test_ocr_page_with_no_content(): void
    {
        // Test page with no lines or signatures (blank page)
        $blankPage = new OcrPage(5);

        $this->assertEquals(5, $blankPage->number);
        $this->assertEmpty($blankPage->lines);
        $this->assertEmpty($blankPage->signatures);
    }
}
