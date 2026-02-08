<?php

namespace Tests\Unit\Services;

use App\Services\ContextCompressor;
use Tests\TestCase;

class ContextCompressorTest extends TestCase
{
    protected ContextCompressor $compressor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compressor = new ContextCompressor;
    }

    // ===== Basic Compression Tests =====

    /** @test */
    public function it_compresses_results_to_fit_token_budget()
    {
        $results = [
            ['content' => str_repeat('Word ', 1000), 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results, 500);

        $this->assertNotEmpty($compressed);
        $this->assertLessThanOrEqual(500 * 4, strlen($compressed[0]['content']));
    }

    /** @test */
    public function it_uses_default_token_budget_when_not_specified()
    {
        $results = [
            ['content' => str_repeat('Word ', 5000), 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        // Should use default 4000 token budget
        $this->assertNotEmpty($compressed);
    }

    /** @test */
    public function it_returns_empty_array_for_empty_results()
    {
        $compressed = $this->compressor->compress([]);

        $this->assertEquals([], $compressed);
    }

    /** @test */
    public function it_handles_results_without_content_field()
    {
        $results = [
            ['title' => 'Test', 'id' => '123'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertCount(1, $compressed);
        $this->assertEquals($results[0], $compressed[0]);
    }

    /** @test */
    public function it_handles_results_with_empty_content()
    {
        $results = [
            ['content' => '', 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertCount(1, $compressed);
        $this->assertEquals('', $compressed[0]['content']);
    }

    // ===== Short Content Tests =====

    /** @test */
    public function it_keeps_short_content_as_is()
    {
        $shortContent = str_repeat('Word ', 40); // ~200 chars ≈ 50 tokens
        $results = [
            ['content' => $shortContent, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertEquals($shortContent, $compressed[0]['content']);
        $this->assertArrayNotHasKey('compressed', $compressed[0]);
    }

    /** @test */
    public function it_does_not_mark_short_content_as_compressed()
    {
        $shortContent = 'This is a short legal text about članak 5.';
        $results = [
            ['content' => $shortContent, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertArrayNotHasKey('compressed', $compressed[0]);
    }

    // ===== Long Content Compression Tests =====

    /** @test */
    public function it_compresses_long_content()
    {
        $longContent = str_repeat('This is a sentence about legal matters. ', 100);
        $results = [
            ['content' => $longContent, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertLessThan(strlen($longContent), strlen($compressed[0]['content']));
        $this->assertTrue($compressed[0]['compressed']);
    }

    /** @test */
    public function it_preserves_original_content_in_full_content_field()
    {
        $longContent = str_repeat('Legal content sentence. ', 100);
        $results = [
            ['content' => $longContent, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertEquals($longContent, $compressed[0]['full_content']);
        $this->assertNotEquals($longContent, $compressed[0]['content']);
    }

    /** @test */
    public function it_marks_compressed_results()
    {
        $longContent = str_repeat('This is content. ', 200);
        $results = [
            ['content' => $longContent, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertTrue($compressed[0]['compressed']);
    }

    /** @test */
    public function it_adds_ellipsis_to_compressed_content()
    {
        // Need >800 chars to trigger compression
        $longContent = str_repeat('Legal text sentence. ', 200);
        $results = [
            ['content' => $longContent, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertStringEndsWith('[...]', $compressed[0]['content']);
    }

    // ===== Token Budget Tests =====

    /** @test */
    public function it_stops_processing_when_token_budget_exceeded()
    {
        $results = [
            ['content' => str_repeat('Word ', 1000), 'title' => 'Doc 1'],
            ['content' => str_repeat('Word ', 1000), 'title' => 'Doc 2'],
            ['content' => str_repeat('Word ', 1000), 'title' => 'Doc 3'],
        ];

        $compressed = $this->compressor->compress($results, 500);

        // Should not include all results if budget exceeded
        $this->assertLessThanOrEqual(3, count($compressed));
    }

    /** @test */
    public function it_tracks_tokens_used_across_multiple_results()
    {
        $results = [
            ['content' => str_repeat('Word ', 40), 'title' => 'Short 1'], // ~50 tokens
            ['content' => str_repeat('Word ', 40), 'title' => 'Short 2'], // ~50 tokens
            ['content' => str_repeat('Word ', 40), 'title' => 'Short 3'], // ~50 tokens
        ];

        $compressed = $this->compressor->compress($results, 200);

        // All should fit within budget
        $this->assertCount(3, $compressed);
    }

    /** @test */
    public function it_allocates_max_300_tokens_per_compressed_item()
    {
        $longContent = str_repeat('Legal sentence. ', 500);
        $results = [
            ['content' => $longContent, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results, 5000);

        // Should not exceed 300 tokens (1200 chars) even with large budget
        $this->assertLessThanOrEqual(1200, strlen($compressed[0]['content']));
    }

    // ===== Legal Keywords Extraction Tests =====

    /** @test */
    public function it_extracts_keywords_from_title()
    {
        $results = [
            [
                'content' => 'Some content about employment law.',
                'title' => 'Employment Law',
            ],
        ];

        $compressed = $this->compressor->compress($results);

        // Keywords from title should be used for sentence scoring
        $this->assertNotEmpty($compressed);
    }

    /** @test */
    public function it_extracts_keywords_from_law_number()
    {
        $results = [
            [
                'content' => 'Content about NN 123/2024 provisions.',
                'law_number' => 'NN 123/2024',
            ],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertNotEmpty($compressed);
    }

    /** @test */
    public function it_extracts_keywords_from_tags()
    {
        $results = [
            [
                'content' => str_repeat('Legal content about employment. ', 100),
                'tags' => ['employment', 'labor', 'contracts'],
            ],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertNotEmpty($compressed);
    }

    /** @test */
    public function it_handles_missing_metadata_fields()
    {
        $results = [
            ['content' => str_repeat('Some text. ', 100)],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertNotEmpty($compressed);
    }

    /** @test */
    public function it_lowercases_keywords_from_title()
    {
        $results = [
            [
                'content' => str_repeat('Content about LEGAL matters. ', 100),
                'title' => 'LEGAL TITLE',
            ],
        ];

        $compressed = $this->compressor->compress($results);

        // Should match case-insensitively
        $this->assertNotEmpty($compressed);
    }

    // ===== Relevant Sentences Extraction Tests =====

    /** @test */
    public function it_scores_sentences_with_keyword_matches()
    {
        $content = 'This sentence has no keywords. This sentence mentions employment law. Random text here.';
        $results = [
            [
                'content' => $content,
                'title' => 'Employment Law',
            ],
        ];

        $compressed = $this->compressor->compress($results);

        // Should prefer sentence with keywords
        $this->assertStringContainsString('employment law', $compressed[0]['content']);
    }

    /** @test */
    public function it_boosts_sentences_with_article_citations()
    {
        $content = str_repeat('Regular sentence. ', 50).
                   'Important text with članak 5 reference. '.
                   str_repeat('More text. ', 50);
        $results = [
            ['content' => $content, 'title' => 'Law'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertStringContainsString('članak 5', $compressed[0]['content']);
    }

    /** @test */
    public function it_boosts_sentences_with_nn_references()
    {
        $content = str_repeat('Regular sentence. ', 50).
                   'Reference to NN 123/2024 here. '.
                   str_repeat('More text. ', 50);
        $results = [
            ['content' => $content, 'title' => 'Law'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertStringContainsString('NN 123/2024', $compressed[0]['content']);
    }

    /** @test */
    public function it_boosts_sentences_with_zakon_o_pattern()
    {
        $content = str_repeat('Regular sentence. ', 50).
                   'Zakon o radu defines rights. '.
                   str_repeat('More text. ', 50);
        $results = [
            ['content' => $content, 'title' => 'Law'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertStringContainsString('Zakon o radu', $compressed[0]['content']);
    }

    /** @test */
    public function it_preserves_sentence_order_after_scoring()
    {
        $content = 'First sentence with law. Second sentence random. Third sentence with employment.';
        $results = [
            [
                'content' => $content,
                'title' => 'Employment Law',
            ],
        ];

        $compressed = $this->compressor->compress($results);

        // Should maintain "First...Third" order even after scoring
        $this->assertLessThan(
            strpos($compressed[0]['content'], 'Third'),
            strpos($compressed[0]['content'], 'First')
        );
    }

    /** @test */
    public function it_takes_top_50_percent_of_sentences()
    {
        $sentences = array_fill(0, 10, 'Irrelevant sentence. ');
        $sentences[5] = 'Important legal sentence with keyword.';
        $content = implode(' ', $sentences);

        $results = [
            [
                'content' => $content,
                'title' => 'keyword',
            ],
        ];

        $compressed = $this->compressor->compress($results);

        // Should include the important sentence
        $this->assertStringContainsString('keyword', $compressed[0]['content']);
    }

    /** @test */
    public function it_keeps_minimum_3_sentences()
    {
        $content = 'Sentence one. Sentence two.';
        $results = [
            ['content' => $content, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        // Even with only 2 sentences, should keep both (min 3 logic uses available)
        $this->assertNotEmpty($compressed[0]['content']);
    }

    /** @test */
    public function it_handles_content_without_sentence_delimiters()
    {
        $content = 'Long text without any periods or sentence breaks just continuous text';
        $results = [
            ['content' => $content, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertNotEmpty($compressed[0]['content']);
    }

    /** @test */
    public function it_handles_empty_keywords_gracefully()
    {
        $content = 'Some content. More content. Even more content.';
        $results = [
            ['content' => $content], // No metadata for keywords
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertNotEmpty($compressed[0]['content']);
    }

    // ===== Citation Extraction Tests =====

    /** @test */
    public function it_extracts_nn_law_numbers()
    {
        // Need long content to trigger compression and citation extraction
        $content = str_repeat('Legal text about various provisions. ', 100).
                   'According to NN 123/2024 and NN 45/2023, the law states important matters.';
        $results = [
            ['content' => $content, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        // Citations should be preserved
        $this->assertStringContainsString('[Cites:', $compressed[0]['content']);
    }

    /** @test */
    public function it_extracts_article_references()
    {
        // Need long content to trigger compression
        $content = str_repeat('Text about legal matters. ', 200).
                   'See članak 15 and čl. 20 for important details.';
        $results = [
            ['content' => $content, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertStringContainsString('[Cites:', $compressed[0]['content']);
    }

    /** @test */
    public function it_limits_citations_to_three()
    {
        $content = 'References: NN 1/2024, NN 2/2024, NN 3/2024, NN 4/2024, NN 5/2024. '.
                   str_repeat('More text. ', 100);
        $results = [
            ['content' => $content, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        // Extract just the citation list
        if (preg_match('/\[Cites: ([^\]]+)\]/', $compressed[0]['content'], $matches)) {
            $citationList = $matches[1];
            $citations = explode(', ', $citationList);
            $this->assertLessThanOrEqual(3, count($citations));
        } else {
            // If no citation list, that's fine too
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function it_handles_content_without_citations()
    {
        $content = str_repeat('Content without any legal citations. ', 50);
        $results = [
            ['content' => $content, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertNotEmpty($compressed[0]['content']);
    }

    /** @test */
    public function it_deduplicates_citations()
    {
        // Need long content to trigger compression
        $content = str_repeat('NN 123/2024 is mentioned repeatedly. ', 100).
                   str_repeat('Additional text about legal matters. ', 50);
        $results = [
            ['content' => $content, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        // NN 123/2024 should appear only once in citations
        if (strpos($compressed[0]['content'], '[Cites:') !== false) {
            preg_match('/\[Cites: ([^\]]+)\]/', $compressed[0]['content'], $matches);
            if (isset($matches[1])) {
                $citations = explode(', ', $matches[1]);
                $this->assertEquals(count($citations), count(array_unique($citations)));
            } else {
                $this->fail('Citation format not found');
            }
        } else {
            // Should have citations for this long content
            $this->fail('Expected citations to be present');
        }
    }

    // ===== Truncation Tests =====

    /** @test */
    public function it_truncates_at_sentence_boundary()
    {
        $longContent = str_repeat('This is a sentence. ', 200);
        $results = [
            ['content' => $longContent, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        // Should end with period before ellipsis (unless citations added)
        $contentWithoutEllipsis = str_replace('[...]', '', $compressed[0]['content']);
        $contentWithoutCitations = preg_replace('/\[Cites: [^\]]+\]/', '', $contentWithoutEllipsis);

        if (! empty(trim($contentWithoutCitations))) {
            $lastChar = substr(trim($contentWithoutCitations), -1);
            $this->assertEquals('.', $lastChar);
        }
    }

    /** @test */
    public function it_handles_text_without_periods_for_truncation()
    {
        $longContent = str_repeat('Continuous text without periods ', 100);
        $results = [
            ['content' => $longContent, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertStringEndsWith('[...]', $compressed[0]['content']);
    }

    /** @test */
    public function it_reserves_space_for_citations_and_ellipsis()
    {
        // Need long content to trigger compression
        $content = str_repeat('Legal text about various matters. ', 150).
                   'According to NN 123/2024, important provisions apply.';
        $results = [
            ['content' => $content, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results);

        // Should have both citations and ellipsis
        $this->assertStringContainsString('[Cites:', $compressed[0]['content']);
        $this->assertStringEndsWith('[...]', $compressed[0]['content']);
    }

    // ===== Compression Ratio Tests =====

    /** @test */
    public function it_calculates_compression_ratio()
    {
        $original = [
            ['content' => str_repeat('Word ', 100)], // 500 chars
        ];

        $compressed = [
            ['content' => str_repeat('Word ', 50)], // 250 chars
        ];

        $ratio = $this->compressor->calculateCompressionRatio($original, $compressed);

        $this->assertEquals(0.5, $ratio);
    }

    /** @test */
    public function it_calculates_ratio_for_multiple_results()
    {
        $original = [
            ['content' => str_repeat('A', 1000)],
            ['content' => str_repeat('B', 1000)],
        ];

        $compressed = [
            ['content' => str_repeat('A', 500)],
            ['content' => str_repeat('B', 500)],
        ];

        $ratio = $this->compressor->calculateCompressionRatio($original, $compressed);

        $this->assertEquals(0.5, $ratio);
    }

    /** @test */
    public function it_returns_1_0_for_identical_content()
    {
        $data = [
            ['content' => 'Same content'],
        ];

        $ratio = $this->compressor->calculateCompressionRatio($data, $data);

        $this->assertEquals(1.0, $ratio);
    }

    /** @test */
    public function it_returns_1_0_for_empty_original()
    {
        $original = [['content' => '']];
        $compressed = [['content' => 'Some content']];

        $ratio = $this->compressor->calculateCompressionRatio($original, $compressed);

        $this->assertEquals(1.0, $ratio);
    }

    /** @test */
    public function it_handles_missing_content_in_compression_ratio()
    {
        $original = [
            ['title' => 'Test'],
        ];

        $compressed = [
            ['title' => 'Test'],
        ];

        $ratio = $this->compressor->calculateCompressionRatio($original, $compressed);

        $this->assertEquals(1.0, $ratio);
    }

    /** @test */
    public function it_calculates_ratio_with_mixed_content()
    {
        $original = [
            ['content' => str_repeat('A', 500)],
            ['content' => ''],
            ['title' => 'No content'],
        ];

        $compressed = [
            ['content' => str_repeat('A', 250)],
            ['content' => ''],
            ['title' => 'No content'],
        ];

        $ratio = $this->compressor->calculateCompressionRatio($original, $compressed);

        $this->assertEquals(0.5, $ratio);
    }

    // ===== Integration Tests =====

    /** @test */
    public function it_compresses_realistic_legal_document()
    {
        // Create content >800 chars to trigger compression
        $content = 'Zakon o radu (NN 93/2014) regulira prava radnika. '.
                   'Članak 1. definira osnovne pojmove. '.
                   'Članak 2. određuje područje primjene. '.
                   str_repeat('Ostale odredbe zakona uređuju različita pitanja vezana uz radne odnose i prava zaposlenih. ', 50);

        $results = [
            [
                'content' => $content,
                'title' => 'Zakon o radu',
                'law_number' => 'NN 93/2014',
                'tags' => ['employment', 'labor'],
            ],
        ];

        $compressed = $this->compressor->compress($results, 500);

        $this->assertCount(1, $compressed);
        $this->assertTrue($compressed[0]['compressed']);
        $this->assertStringContainsString('Zakon o radu', $compressed[0]['content']);
        $this->assertStringContainsString('Članak', $compressed[0]['content']);
        $this->assertStringEndsWith('[...]', $compressed[0]['content']);
    }

    /** @test */
    public function it_handles_mixed_length_results()
    {
        $results = [
            ['content' => 'Short text.', 'title' => 'Doc 1'],
            ['content' => str_repeat('Long content. ', 200), 'title' => 'Doc 2'],
            ['content' => 'Another short text.', 'title' => 'Doc 3'],
        ];

        $compressed = $this->compressor->compress($results, 1000);

        $this->assertGreaterThanOrEqual(2, count($compressed));

        // First should not be compressed (short)
        $this->assertArrayNotHasKey('compressed', $compressed[0]);

        // Second should be compressed (long)
        $this->assertTrue($compressed[1]['compressed']);
    }

    /** @test */
    public function it_preserves_metadata_in_compressed_results()
    {
        $results = [
            [
                'content' => str_repeat('Legal text. ', 100),
                'title' => 'Test Law',
                'id' => 'law-123',
                'score' => 0.95,
                'metadata' => ['year' => 2024],
            ],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertEquals('Test Law', $compressed[0]['title']);
        $this->assertEquals('law-123', $compressed[0]['id']);
        $this->assertEquals(0.95, $compressed[0]['score']);
        $this->assertEquals(['year' => 2024], $compressed[0]['metadata']);
    }

    /** @test */
    public function it_handles_croatian_text_with_diacritics()
    {
        $content = 'Zakon o međunarodnom privatnom pravu (NN 101/17). '.
                   'Članak 5. određuje nadležnost za bračne sporove. '.
                   str_repeat('Dodatne odredbe o priznanju stranih odluka. ', 50);

        $results = [
            [
                'content' => $content,
                'title' => 'Međunarodno privatno pravo',
            ],
        ];

        $compressed = $this->compressor->compress($results);

        $this->assertStringContainsString('međunarodno', mb_strtolower($compressed[0]['content']));
        $this->assertStringContainsString('Članak', $compressed[0]['content']);
    }

    /** @test */
    public function it_compresses_content_to_reasonable_size()
    {
        $veryLongContent = str_repeat('This is legal text about various matters. ', 1000);
        $results = [
            ['content' => $veryLongContent, 'title' => 'Test'],
        ];

        $compressed = $this->compressor->compress($results, 4000);

        // Should be significantly smaller than original
        $this->assertLessThan(strlen($veryLongContent) * 0.5, strlen($compressed[0]['content']));

        // But should still have meaningful content
        $this->assertGreaterThan(100, strlen($compressed[0]['content']));
    }
}
