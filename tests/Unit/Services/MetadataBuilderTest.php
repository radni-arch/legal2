<?php

namespace Tests\Unit\Services;

use App\Services\MetadataBuilder;
use Tests\TestCase;

class MetadataBuilderTest extends TestCase
{
    protected MetadataBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new MetadataBuilder;
    }

    // ===== Basic Metadata Structure Tests =====

    /** @test */
    public function it_builds_article_metadata_with_required_fields()
    {
        $context = $this->createBasicContext();
        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertArrayHasKey('@context', $metadata);
        $this->assertArrayHasKey('@type', $metadata);
        $this->assertArrayHasKey('id', $metadata);
        $this->assertArrayHasKey('identifier', $metadata);
        $this->assertArrayHasKey('inLanguage', $metadata);
        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('datePublished', $metadata);
        $this->assertArrayHasKey('isBasedOn', $metadata);
        $this->assertArrayHasKey('about', $metadata);
        $this->assertArrayHasKey('article', $metadata);
        $this->assertArrayHasKey('file', $metadata);
        $this->assertArrayHasKey('generator', $metadata);
    }

    /** @test */
    public function it_sets_schema_org_context()
    {
        $context = $this->createBasicContext();
        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('https://schema.org', $metadata['@context']);
    }

    /** @test */
    public function it_sets_creative_work_type()
    {
        $context = $this->createBasicContext();
        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('CreativeWork', $metadata['@type']);
    }

    /** @test */
    public function it_sets_croatian_language()
    {
        $context = $this->createBasicContext();
        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('hr', $metadata['inLanguage']);
    }

    // ===== ID Generation Tests =====

    /** @test */
    public function it_generates_id_from_eli_resource_and_article_number()
    {
        $context = $this->createBasicContext([
            'eli_resource' => 'http://lex.hr/hr/NN/2024/123',
            'article_number' => '5',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $expectedId = 'urn:hr-law:hr/NN/2024/123#clanak-5';
        $this->assertEquals($expectedId, $metadata['id']);
    }

    /** @test */
    public function it_generates_id_with_different_article_numbers()
    {
        $context = $this->createBasicContext([
            'eli_resource' => 'http://lex.hr/test/path',
            'article_number' => '42',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertStringContainsString('#clanak-42', $metadata['id']);
    }

    /** @test */
    public function it_trims_leading_slash_from_eli_resource_path()
    {
        $context = $this->createBasicContext([
            'eli_resource' => 'http://example.com/path/to/resource',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertStringStartsWith('urn:hr-law:', $metadata['id']);
        $this->assertStringNotContainsString('urn:hr-law://', $metadata['id']);
    }

    /** @test */
    public function it_sets_id_and_identifier_to_same_value()
    {
        $context = $this->createBasicContext();
        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals($metadata['id'], $metadata['identifier']);
    }

    /** @test */
    public function it_handles_complex_eli_resource_paths()
    {
        $context = $this->createBasicContext([
            'eli_resource' => 'https://lex.hr/hr/ZAKI/2024/SL/123/1',
            'article_number' => '10',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('urn:hr-law:hr/ZAKI/2024/SL/123/1#clanak-10', $metadata['id']);
    }

    // ===== Name/Title Tests =====

    /** @test */
    public function it_formats_article_name_with_title_and_number()
    {
        $context = $this->createBasicContext([
            'title' => 'Zakon o radu',
            'article_number' => '15',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('Zakon o radu – Članak 15', $metadata['name']);
    }

    /** @test */
    public function it_formats_name_with_different_titles()
    {
        $context = $this->createBasicContext([
            'title' => 'Kazneni zakon',
            'article_number' => '100',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('Kazneni zakon – Članak 100', $metadata['name']);
    }

    /** @test */
    public function it_handles_titles_with_special_characters()
    {
        $context = $this->createBasicContext([
            'title' => 'Zakon o međunarodnom i privremenom održavanju djece',
            'article_number' => '1',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertStringContainsString('međunarodnom', $metadata['name']);
    }

    // ===== Date Published Tests =====

    /** @test */
    public function it_sets_date_published_from_context()
    {
        $context = $this->createBasicContext([
            'date_publication' => '2024-05-15',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('2024-05-15', $metadata['datePublished']);
    }

    /** @test */
    public function it_handles_different_date_formats()
    {
        $context = $this->createBasicContext([
            'date_publication' => '2023-12-31T23:59:59Z',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('2023-12-31T23:59:59Z', $metadata['datePublished']);
    }

    // ===== Legislation (isBasedOn) Tests =====

    /** @test */
    public function it_sets_legislation_type_in_is_based_on()
    {
        $context = $this->createBasicContext();
        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('Legislation', $metadata['isBasedOn']['@type']);
    }

    /** @test */
    public function it_sets_eli_resource_as_legislation_identifier()
    {
        $context = $this->createBasicContext([
            'eli_resource' => 'http://lex.hr/hr/NN/2024/50',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('http://lex.hr/hr/NN/2024/50', $metadata['isBasedOn']['identifier']);
    }

    /** @test */
    public function it_includes_eli_expression_in_same_as()
    {
        $context = $this->createBasicContext([
            'eli_expression' => 'http://lex.hr/hr/NN/2024/50/hrv',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertContains('http://lex.hr/hr/NN/2024/50/hrv', $metadata['isBasedOn']['sameAs']);
    }

    /** @test */
    public function it_includes_html_url_in_same_as()
    {
        $context = $this->createBasicContext([
            'html_url' => 'https://narodne-novine.nn.hr/clanci/sluzbeni/2024_05_50.html',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertContains('https://narodne-novine.nn.hr/clanci/sluzbeni/2024_05_50.html', $metadata['isBasedOn']['sameAs']);
    }

    /** @test */
    public function it_includes_pdf_url_in_same_as()
    {
        $context = $this->createBasicContext([
            'pdf_url' => 'https://narodne-novine.nn.hr/clanci/sluzbeni/2024_05_50.pdf',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertContains('https://narodne-novine.nn.hr/clanci/sluzbeni/2024_05_50.pdf', $metadata['isBasedOn']['sameAs']);
    }

    /** @test */
    public function it_includes_all_three_urls_in_same_as_array()
    {
        $context = $this->createBasicContext([
            'eli_expression' => 'http://lex.hr/eli/expression',
            'html_url' => 'http://example.com/html',
            'pdf_url' => 'http://example.com/pdf',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertCount(3, $metadata['isBasedOn']['sameAs']);
        $this->assertContains('http://lex.hr/eli/expression', $metadata['isBasedOn']['sameAs']);
        $this->assertContains('http://example.com/html', $metadata['isBasedOn']['sameAs']);
        $this->assertContains('http://example.com/pdf', $metadata['isBasedOn']['sameAs']);
    }

    // ===== About Section Tests =====

    /** @test */
    public function it_sets_type_document_in_about_section()
    {
        $context = $this->createBasicContext([
            'type_document' => 'ZAKON',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('ZAKON', $metadata['about']['type_document']);
    }

    /** @test */
    public function it_sets_nn_part_to_sl()
    {
        $context = $this->createBasicContext();
        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('SL', $metadata['about']['nn_part']);
    }

    /** @test */
    public function it_sets_nn_year_from_context()
    {
        $context = $this->createBasicContext([
            'year' => 2024,
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals(2024, $metadata['about']['nn_year']);
    }

    /** @test */
    public function it_sets_nn_edition_from_context()
    {
        $context = $this->createBasicContext([
            'edition' => 123,
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals(123, $metadata['about']['nn_edition']);
    }

    /** @test */
    public function it_sets_nn_act_from_context()
    {
        $context = $this->createBasicContext([
            'act' => 456,
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals(456, $metadata['about']['nn_act']);
    }

    /** @test */
    public function it_sets_is_consolidated_text_to_false_by_default()
    {
        $context = $this->createBasicContext();
        unset($context['is_consolidated']);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertFalse($metadata['about']['is_consolidated_text']);
    }

    /** @test */
    public function it_sets_is_consolidated_text_when_provided()
    {
        $context = $this->createBasicContext([
            'is_consolidated' => true,
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertTrue($metadata['about']['is_consolidated_text']);
    }

    /** @test */
    public function it_handles_is_consolidated_as_false()
    {
        $context = $this->createBasicContext([
            'is_consolidated' => false,
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertFalse($metadata['about']['is_consolidated_text']);
    }

    // ===== Article Section Tests =====

    /** @test */
    public function it_converts_article_number_to_string()
    {
        $context = $this->createBasicContext([
            'article_number' => 42,
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertIsString($metadata['article']['number']);
        $this->assertEquals('42', $metadata['article']['number']);
    }

    /** @test */
    public function it_handles_string_article_numbers()
    {
        $context = $this->createBasicContext([
            'article_number' => '15a',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('15a', $metadata['article']['number']);
    }

    /** @test */
    public function it_includes_heading_chain_when_provided()
    {
        $context = $this->createBasicContext([
            'heading_chain' => ['Opće odredbe', 'Načela', 'Primjena'],
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals(['Opće odredbe', 'Načela', 'Primjena'], $metadata['article']['heading_chain']);
    }

    /** @test */
    public function it_sets_heading_chain_to_empty_array_by_default()
    {
        $context = $this->createBasicContext();
        unset($context['heading_chain']);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals([], $metadata['article']['heading_chain']);
    }

    /** @test */
    public function it_includes_text_checksum_when_provided()
    {
        $context = $this->createBasicContext([
            'text_checksum' => 'abc123def456',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('abc123def456', $metadata['article']['text_checksum']);
    }

    /** @test */
    public function it_sets_text_checksum_to_null_by_default()
    {
        $context = $this->createBasicContext();
        unset($context['text_checksum']);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertNull($metadata['article']['text_checksum']);
    }

    // ===== File Section Tests =====

    /** @test */
    public function it_includes_file_path_when_provided()
    {
        $context = $this->createBasicContext([
            'file_path' => '/storage/laws/2024/zakon-o-radu.json',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('/storage/laws/2024/zakon-o-radu.json', $metadata['file']['path']);
    }

    /** @test */
    public function it_sets_file_path_to_null_by_default()
    {
        $context = $this->createBasicContext();
        unset($context['file_path']);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertNull($metadata['file']['path']);
    }

    /** @test */
    public function it_includes_file_bytes_when_provided()
    {
        $context = $this->createBasicContext([
            'file_bytes' => 524288,
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals(524288, $metadata['file']['bytes']);
    }

    /** @test */
    public function it_sets_file_bytes_to_null_by_default()
    {
        $context = $this->createBasicContext();
        unset($context['file_bytes']);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertNull($metadata['file']['bytes']);
    }

    /** @test */
    public function it_includes_file_sha256_when_provided()
    {
        $context = $this->createBasicContext([
            'file_sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $metadata['file']['sha256']);
    }

    /** @test */
    public function it_sets_file_sha256_to_null_by_default()
    {
        $context = $this->createBasicContext();
        unset($context['file_sha256']);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertNull($metadata['file']['sha256']);
    }

    // ===== Generator Section Tests =====

    /** @test */
    public function it_sets_generator_name_to_hr_law_ingestor()
    {
        $context = $this->createBasicContext();
        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('hr-law-ingestor', $metadata['generator']['name']);
    }

    /** @test */
    public function it_includes_generator_version_when_provided()
    {
        $context = $this->createBasicContext([
            'generator_version' => '2.5.3',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('2.5.3', $metadata['generator']['version']);
    }

    /** @test */
    public function it_sets_generator_version_to_1_0_0_by_default()
    {
        $context = $this->createBasicContext();
        unset($context['generator_version']);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('1.0.0', $metadata['generator']['version']);
    }

    /** @test */
    public function it_includes_generated_at_when_provided()
    {
        $context = $this->createBasicContext([
            'generated_at' => '2024-05-15T10:30:45Z',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals('2024-05-15T10:30:45Z', $metadata['generator']['generated_at']);
    }

    /** @test */
    public function it_generates_timestamp_when_not_provided()
    {
        $context = $this->createBasicContext();
        unset($context['generated_at']);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertNotEmpty($metadata['generator']['generated_at']);
        // Should be a valid ISO 8601 timestamp
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/', $metadata['generator']['generated_at']);
    }

    /** @test */
    public function it_generates_current_utc_timestamp_in_iso8601_format()
    {
        $context = $this->createBasicContext();
        unset($context['generated_at']);

        $beforeTime = gmdate('c');
        $metadata = $this->builder->buildArticleMetadata($context);
        $afterTime = gmdate('c');

        $generatedAt = $metadata['generator']['generated_at'];

        // The generated timestamp should be between before and after
        $this->assertGreaterThanOrEqual($beforeTime, $generatedAt);
        $this->assertLessThanOrEqual($afterTime, $generatedAt);
    }

    // ===== Integration Tests =====

    /** @test */
    public function it_builds_complete_metadata_with_all_optional_fields()
    {
        $context = [
            'eli_resource' => 'http://lex.hr/hr/NN/2024/50',
            'eli_expression' => 'http://lex.hr/hr/NN/2024/50/hrv',
            'html_url' => 'https://nn.hr/2024/50.html',
            'pdf_url' => 'https://nn.hr/2024/50.pdf',
            'article_number' => '25',
            'title' => 'Zakon o radu',
            'date_publication' => '2024-05-15',
            'type_document' => 'ZAKON',
            'year' => 2024,
            'edition' => 50,
            'act' => 1234,
            'is_consolidated' => true,
            'heading_chain' => ['Dio I', 'Glava II', 'Odjeljak 3'],
            'text_checksum' => 'abc123',
            'file_path' => '/storage/laws/zakon-o-radu.json',
            'file_bytes' => 102400,
            'file_sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'generator_version' => '2.1.0',
            'generated_at' => '2024-05-15T12:00:00Z',
        ];

        $metadata = $this->builder->buildArticleMetadata($context);

        // Verify all sections are present and populated
        $this->assertEquals('urn:hr-law:hr/NN/2024/50#clanak-25', $metadata['id']);
        $this->assertEquals('Zakon o radu – Članak 25', $metadata['name']);
        $this->assertEquals('2024-05-15', $metadata['datePublished']);
        $this->assertEquals('http://lex.hr/hr/NN/2024/50', $metadata['isBasedOn']['identifier']);
        $this->assertEquals('ZAKON', $metadata['about']['type_document']);
        $this->assertEquals(2024, $metadata['about']['nn_year']);
        $this->assertTrue($metadata['about']['is_consolidated_text']);
        $this->assertEquals('25', $metadata['article']['number']);
        $this->assertEquals(['Dio I', 'Glava II', 'Odjeljak 3'], $metadata['article']['heading_chain']);
        $this->assertEquals('/storage/laws/zakon-o-radu.json', $metadata['file']['path']);
        $this->assertEquals('2.1.0', $metadata['generator']['version']);
    }

    /** @test */
    public function it_builds_minimal_metadata_with_only_required_fields()
    {
        $context = [
            'eli_resource' => 'http://lex.hr/hr/test',
            'eli_expression' => 'http://lex.hr/hr/test/hrv',
            'html_url' => 'http://example.com/html',
            'pdf_url' => 'http://example.com/pdf',
            'article_number' => '1',
            'title' => 'Test Law',
            'date_publication' => '2024-01-01',
            'type_document' => 'TEST',
            'year' => 2024,
            'edition' => 1,
            'act' => 1,
        ];

        $metadata = $this->builder->buildArticleMetadata($context);

        // Verify structure is complete with defaults
        $this->assertArrayHasKey('id', $metadata);
        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('isBasedOn', $metadata);
        $this->assertArrayHasKey('about', $metadata);
        $this->assertArrayHasKey('article', $metadata);
        $this->assertArrayHasKey('file', $metadata);
        $this->assertArrayHasKey('generator', $metadata);

        // Verify defaults
        $this->assertFalse($metadata['about']['is_consolidated_text']);
        $this->assertEquals([], $metadata['article']['heading_chain']);
        $this->assertNull($metadata['article']['text_checksum']);
        $this->assertNull($metadata['file']['path']);
        $this->assertEquals('1.0.0', $metadata['generator']['version']);
    }

    /** @test */
    public function it_handles_numeric_values_correctly()
    {
        $context = $this->createBasicContext([
            'year' => '2024',  // String instead of int
            'edition' => '123',  // String instead of int
            'act' => '456',  // String instead of int
            'article_number' => 789,  // Int instead of string
            'file_bytes' => '1024',  // String instead of int
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        // Article number should be converted to string
        $this->assertIsString($metadata['article']['number']);
        $this->assertEquals('789', $metadata['article']['number']);

        // Year, edition, act should keep their types
        $this->assertEquals('2024', $metadata['about']['nn_year']);
        $this->assertEquals('123', $metadata['about']['nn_edition']);
        $this->assertEquals('456', $metadata['about']['nn_act']);
        $this->assertEquals('1024', $metadata['file']['bytes']);
    }

    /** @test */
    public function it_handles_empty_heading_chain()
    {
        $context = $this->createBasicContext([
            'heading_chain' => [],
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertEquals([], $metadata['article']['heading_chain']);
    }

    /** @test */
    public function it_handles_long_heading_chains()
    {
        $context = $this->createBasicContext([
            'heading_chain' => [
                'Dio I - Temeljne odredbe',
                'Glava I - Opće odredbe',
                'Odjeljak 1 - Područje primjene',
                'Pododjeljak 1 - Načela',
            ],
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertCount(4, $metadata['article']['heading_chain']);
        $this->assertEquals('Dio I - Temeljne odredbe', $metadata['article']['heading_chain'][0]);
    }

    /** @test */
    public function it_preserves_special_characters_in_urls()
    {
        $context = $this->createBasicContext([
            'eli_resource' => 'http://lex.hr/hr/NN/2024/50?version=latest&lang=hr',
            'html_url' => 'https://nn.hr/clanci/2024_05_50.html#article-1',
        ]);

        $metadata = $this->builder->buildArticleMetadata($context);

        $this->assertStringContainsString('?version=latest&lang=hr', $metadata['isBasedOn']['identifier']);
        $this->assertContains('https://nn.hr/clanci/2024_05_50.html#article-1', $metadata['isBasedOn']['sameAs']);
    }

    // ===== Helper Methods =====

    protected function createBasicContext(array $overrides = []): array
    {
        $defaults = [
            'eli_resource' => 'http://lex.hr/hr/NN/2024/50',
            'eli_expression' => 'http://lex.hr/hr/NN/2024/50/hrv',
            'html_url' => 'https://nn.hr/2024/50.html',
            'pdf_url' => 'https://nn.hr/2024/50.pdf',
            'article_number' => '10',
            'title' => 'Test Zakon',
            'date_publication' => '2024-05-01',
            'type_document' => 'ZAKON',
            'year' => 2024,
            'edition' => 50,
            'act' => 100,
            'is_consolidated' => false,
            'heading_chain' => ['Dio I', 'Glava I'],
            'text_checksum' => 'checksum123',
            'file_path' => '/storage/test.json',
            'file_bytes' => 1024,
            'file_sha256' => 'sha256hash',
            'generator_version' => '1.0.0',
            'generated_at' => '2024-05-01T10:00:00Z',
        ];

        return array_merge($defaults, $overrides);
    }
}
