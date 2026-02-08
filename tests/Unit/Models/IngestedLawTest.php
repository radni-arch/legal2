<?php

namespace Tests\Unit\Models;

use App\Models\IngestedLaw;
use App\Models\Law;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class IngestedLawTest extends TestCase
{
    use UsesTestDatabase;

    public function test_uses_string_primary_key(): void
    {
        // Arrange & Act
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'zakonhr-test-law-2024',
            'title' => 'Test Law Title',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertIsString($law->id);
        $this->assertNotEmpty($law->id);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        // Arrange & Act
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'zakonhr-statute-001',
            'title' => 'Zakon o obveznim odnosima',
            'law_number' => 'NN 35/2005',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'source_url' => 'https://www.zakon.hr/z/307/Zakon-o-obveznim-odnosima',
            'aliases' => ['ZOO', 'Law on Obligations'],
            'keywords' => ['contract', 'obligation', 'civil law'],
            'keywords_text' => 'contract obligation civil law',
            'metadata' => [
                'source' => 'zakon.hr',
                'date_published' => '2005-03-17',
                'date_effective' => '2005-01-01',
            ],
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertEquals('zakonhr-statute-001', $law->doc_id);
        $this->assertEquals('Zakon o obveznim odnosima', $law->title);
        $this->assertEquals('NN 35/2005', $law->law_number);
        $this->assertEquals('HR', $law->jurisdiction);
    }

    public function test_casts_aliases_as_array(): void
    {
        // Arrange & Act
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'test-aliases',
            'title' => 'Test Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'aliases' => ['Alias 1', 'Alias 2', 'Short Name'],
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertIsArray($law->aliases);
        $this->assertCount(3, $law->aliases);
        $this->assertContains('Alias 1', $law->aliases);
        $this->assertContains('Short Name', $law->aliases);
    }

    public function test_casts_keywords_as_array(): void
    {
        // Arrange & Act
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'test-keywords',
            'title' => 'Test Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'keywords' => ['employment', 'labor', 'rights', 'workplace'],
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertIsArray($law->keywords);
        $this->assertCount(4, $law->keywords);
        $this->assertContains('employment', $law->keywords);
        $this->assertContains('workplace', $law->keywords);
    }

    public function test_casts_metadata_as_array(): void
    {
        // Arrange & Act
        $metadata = [
            'source' => 'zakon.hr',
            'scraper_version' => '2.0',
            'date_scraped' => '2024-01-15',
            'article_count' => 1254,
            'chapter_count' => 28,
        ];

        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'test-metadata',
            'title' => 'Test Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'metadata' => $metadata,
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertIsArray($law->metadata);
        $this->assertEquals('zakon.hr', $law->metadata['source']);
        $this->assertEquals(1254, $law->metadata['article_count']);
        $this->assertEquals(28, $law->metadata['chapter_count']);
    }

    public function test_casts_ingested_at_as_datetime(): void
    {
        // Arrange
        $ingestedAt = now();

        // Act
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'test-datetime',
            'title' => 'Test Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'ingested_at' => $ingestedAt,
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $law->ingested_at);
        $this->assertEquals($ingestedAt->toDateTimeString(), $law->ingested_at->toDateTimeString());
    }

    public function test_has_many_laws_relationship(): void
    {
        // Arrange
        $ingestedLaw = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'parent-law',
            'title' => 'Parent Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'ingested_at' => now(),
        ]);

        Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'chunk-1',
            'ingested_law_id' => $ingestedLaw->id,
            'title' => 'Parent Law - Chunk 1',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Content of chunk 1',
        ]);

        Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'chunk-2',
            'ingested_law_id' => $ingestedLaw->id,
            'title' => 'Parent Law - Chunk 2',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Content of chunk 2',
        ]);

        // Act
        $laws = $ingestedLaw->laws;

        // Assert
        $this->assertCount(2, $laws);
        $this->assertInstanceOf(Law::class, $laws->first());
        $this->assertEquals($ingestedLaw->id, $laws->first()->ingested_law_id);
    }

    public function test_stores_croatian_characters_correctly(): void
    {
        // Arrange & Act
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'croatian-chars',
            'title' => 'Zakon o građevinarstvu i prostornom uređenju',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'keywords' => ['građevinarstvo', 'uređenje', 'prostorno'],
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertStringContainsString('građevinarstvu', $law->title);
        $this->assertStringContainsString('uređenju', $law->title);
        $this->assertContains('građevinarstvo', $law->keywords);
    }

    public function test_stores_source_url(): void
    {
        // Arrange & Act
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'url-test',
            'title' => 'Test Law with URL',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'source_url' => 'https://www.zakon.hr/z/123/Test-Law',
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertEquals('https://www.zakon.hr/z/123/Test-Law', $law->source_url);
        $this->assertStringStartsWith('https://', $law->source_url);
    }

    public function test_handles_null_optional_fields(): void
    {
        // Arrange & Act
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'minimal-law',
            'title' => 'Minimal Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'law_number' => null,
            'source_url' => null,
            'aliases' => null,
            'keywords' => null,
            'keywords_text' => null,
            'metadata' => null,
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertNull($law->law_number);
        $this->assertNull($law->source_url);
        $this->assertNull($law->aliases);
        $this->assertNull($law->keywords);
        $this->assertNull($law->keywords_text);
        $this->assertNull($law->metadata);
    }

    public function test_stores_law_number_in_official_gazette_format(): void
    {
        // Arrange & Act
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'nn-format',
            'title' => 'Narodne novine Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'law_number' => 'NN 110/2023',
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertEquals('NN 110/2023', $law->law_number);
        $this->assertMatchesRegularExpression('/NN \d+\/\d{4}/', $law->law_number);
    }

    public function test_stores_keywords_text_for_searchability(): void
    {
        // Arrange & Act
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'searchable-law',
            'title' => 'Searchable Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'keywords' => ['employment', 'contract', 'termination'],
            'keywords_text' => 'employment contract termination rights obligations',
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertStringContainsString('employment', $law->keywords_text);
        $this->assertStringContainsString('termination', $law->keywords_text);
        $this->assertStringContainsString('obligations', $law->keywords_text);
    }

    public function test_supports_different_jurisdictions(): void
    {
        // Arrange & Act
        $hrLaw = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'hr-law',
            'title' => 'Croatian National Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'ingested_at' => now(),
        ]);

        $euLaw = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'eu-law',
            'title' => 'EU Regulation',
            'jurisdiction' => 'EU',
            'country' => 'EU',
            'language' => 'en',
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertEquals('HR', $hrLaw->jurisdiction);
        $this->assertEquals('EU', $euLaw->jurisdiction);
    }

    public function test_stores_complex_metadata(): void
    {
        // Arrange & Act
        $metadata = [
            'source' => 'zakon.hr',
            'scraper_version' => '3.1.0',
            'date_scraped' => '2024-01-20T10:30:00Z',
            'date_published' => '2023-12-15',
            'date_effective' => '2024-01-01',
            'amendments' => [
                ['date' => '2024-06-01', 'nn' => 'NN 55/2024'],
            ],
            'related_laws' => ['NN 35/2005', 'NN 78/2015'],
            'article_count' => 425,
            'chapter_structure' => [
                ['chapter' => 1, 'title' => 'General Provisions', 'articles' => 15],
                ['chapter' => 2, 'title' => 'Obligations', 'articles' => 120],
            ],
        ];

        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'complex-metadata',
            'title' => 'Complex Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'metadata' => $metadata,
            'ingested_at' => now(),
        ]);

        // Assert
        $this->assertEquals('3.1.0', $law->metadata['scraper_version']);
        $this->assertEquals(425, $law->metadata['article_count']);
        $this->assertIsArray($law->metadata['amendments']);
        $this->assertIsArray($law->metadata['chapter_structure']);
        $this->assertCount(1, $law->metadata['amendments']);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $ingestedLaw = IngestedLaw::create([
            'id' => 'ingested-law-123',
            'doc_id' => 'doc-456',
            'title' => 'Zakon o parničnom postupku',
            'law_number' => 'NN 53/91',
            'jurisdiction' => 'HR',
            'country' => 'Croatia',
            'language' => 'hr',
            'source_url' => 'https://example.com/law',
            'ingested_at' => now(),
        ]);

        $this->assertEquals('ingested-law-123', $ingestedLaw->id);
        $this->assertEquals('Zakon o parničnom postupku', $ingestedLaw->title);
        $this->assertEquals('NN 53/91', $ingestedLaw->law_number);
    }

    /** @test */
    public function it_uses_string_primary_key()
    {
        $ingestedLaw = IngestedLaw::factory()->create(['id' => 'custom-id']);

        $this->assertEquals('custom-id', $ingestedLaw->id);
        $this->assertFalse($ingestedLaw->incrementing);
        $this->assertEquals('string', $ingestedLaw->getKeyType());
    }

    /** @test */
    public function it_has_many_laws()
    {
        $ingestedLaw = IngestedLaw::factory()->create();
        Law::factory()->count(3)->create(['ingested_law_id' => $ingestedLaw->id]);

        $this->assertCount(3, $ingestedLaw->laws);
        $this->assertInstanceOf(Law::class, $ingestedLaw->laws->first());
    }

    /** @test */
    public function it_casts_aliases_as_array()
    {
        $aliases = ['ZPP', 'Zakon o parničnom postupku', 'Civil Procedure Act'];

        $ingestedLaw = IngestedLaw::factory()->create(['aliases' => $aliases]);

        $this->assertIsArray($ingestedLaw->aliases);
        $this->assertCount(3, $ingestedLaw->aliases);
        $this->assertContains('ZPP', $ingestedLaw->aliases);
    }

    /** @test */
    public function it_casts_keywords_as_array()
    {
        $keywords = ['civil', 'procedure', 'litigation', 'court'];

        $ingestedLaw = IngestedLaw::factory()->create(['keywords' => $keywords]);

        $this->assertIsArray($ingestedLaw->keywords);
        $this->assertCount(4, $ingestedLaw->keywords);
        $this->assertContains('litigation', $ingestedLaw->keywords);
    }

    /** @test */
    public function it_casts_metadata_as_array()
    {
        $metadata = [
            'category' => 'civil',
            'subcategory' => 'procedure',
            'complexity' => 'high',
        ];

        $ingestedLaw = IngestedLaw::factory()->create(['metadata' => $metadata]);

        $this->assertIsArray($ingestedLaw->metadata);
        $this->assertEquals('civil', $ingestedLaw->metadata['category']);
    }

    /** @test */
    public function it_casts_ingested_at_as_datetime()
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'ingested_at' => now()->subDays(5),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $ingestedLaw->ingested_at);
    }

    /** @test */
    public function it_stores_keywords_text_for_search()
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'keywords' => ['criminal', 'law', 'procedure'],
            'keywords_text' => 'criminal law procedure',
        ]);

        $this->assertEquals('criminal law procedure', $ingestedLaw->keywords_text);
    }

    /** @test */
    public function it_handles_croatian_law_numbers()
    {
        $lawNumbers = [
            'NN 53/91',
            'NN 152/08',
            'NN 121/11',
        ];

        foreach ($lawNumbers as $number) {
            $law = IngestedLaw::factory()->create(['law_number' => $number]);
            $this->assertEquals($number, $law->law_number);
        }
    }

    /** @test */
    public function it_handles_croatian_unicode_characters()
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'title' => 'Zakon o izvršenju - članci o naplati',
            'aliases' => ['Ovršni zakon', 'Zakon o ovršenju'],
        ]);

        $this->assertStringContainsString('članci', $ingestedLaw->title);
        $this->assertContains('Ovršni zakon', $ingestedLaw->aliases);
    }

    /** @test */
    public function it_stores_source_url()
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'source_url' => 'https://narodne-novine.nn.hr/clanci/sluzbeni/1991_06_53_1658.html',
        ]);

        $this->assertStringContainsString('narodne-novine', $ingestedLaw->source_url);
    }

    /** @test */
    public function it_handles_doc_id_reference()
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'doc_id' => 'doc-law-zpp-53-91',
        ]);

        $this->assertEquals('doc-law-zpp-53-91', $ingestedLaw->doc_id);
    }

    /** @test */
    public function it_handles_different_jurisdictions()
    {
        $hrLaw = IngestedLaw::factory()->create([
            'jurisdiction' => 'HR',
            'country' => 'Croatia',
        ]);

        $euLaw = IngestedLaw::factory()->create([
            'jurisdiction' => 'EU',
            'country' => 'European Union',
        ]);

        $this->assertEquals('HR', $hrLaw->jurisdiction);
        $this->assertEquals('EU', $euLaw->jurisdiction);
    }

    /** @test */
    public function it_handles_multilingual_laws()
    {
        $hrLaw = IngestedLaw::factory()->create(['language' => 'hr']);
        $enLaw = IngestedLaw::factory()->create(['language' => 'en']);

        $this->assertEquals('hr', $hrLaw->language);
        $this->assertEquals('en', $enLaw->language);
    }

    /** @test */
    public function it_tracks_when_law_was_ingested()
    {
        $ingestedAt = now()->subWeek();
        $ingestedLaw = IngestedLaw::factory()->create([
            'ingested_at' => $ingestedAt,
        ]);

        $this->assertEquals($ingestedAt->timestamp, $ingestedLaw->ingested_at->timestamp);
    }

    /** @test */
    public function it_can_have_multiple_aliases()
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'title' => 'Zakon o kaznenom postupku',
            'aliases' => [
                'ZKP',
                'Kazneni postupak',
                'Criminal Procedure Act',
                'CPA',
            ],
        ]);

        $this->assertCount(4, $ingestedLaw->aliases);
    }

    /** @test */
    public function it_stores_comprehensive_metadata()
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'metadata' => [
                'category' => 'criminal',
                'subcategory' => 'procedure',
                'status' => 'active',
                'amendments' => ['NN 110/11', 'NN 121/11'],
                'related_laws' => ['NN 88/01', 'NN 152/08'],
            ],
        ]);

        $this->assertEquals('active', $ingestedLaw->metadata['status']);
        $this->assertCount(2, $ingestedLaw->metadata['amendments']);
    }

    /** @test */
    public function it_uses_configurable_table_name()
    {
        config(['vizra-adk.tables.ingested_laws' => 'custom_ingested_laws']);

        $ingestedLaw = new IngestedLaw;

        $this->assertEquals('custom_ingested_laws', $ingestedLaw->getTable());
    }

    /** @test */
    public function it_associates_with_chunked_law_documents()
    {
        $ingestedLaw = IngestedLaw::factory()->create();

        // Create 3 chunks of the same law
        Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'doc_id' => 'doc-law-1',
            'chunk_index' => 0,
        ]);

        Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'doc_id' => 'doc-law-1',
            'chunk_index' => 1,
        ]);

        Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'doc_id' => 'doc-law-1',
            'chunk_index' => 2,
        ]);

        $this->assertCount(3, $ingestedLaw->laws);
    }
}
