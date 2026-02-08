<?php

namespace Tests\Unit\Models;

use App\Models\LegalProvision;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LegalProvisionTest extends TestCase
{
    use UsesTestDatabase;

    public function test_finds_provisions_by_law_and_article(): void
    {
        // Arrange
        LegalProvision::factory()->create([
            'law_name' => 'Prekrsajni zakon',
            'law_short' => 'PZ',
            'article' => '150',
            'paragraph' => '4',
        ]);

        LegalProvision::factory()->create([
            'law_name' => 'Zakon o kaznenom postupku',
            'law_short' => 'ZKP',
            'article' => '184',
        ]);

        // Act
        $results = LegalProvision::forLaw('PZ')->forArticle('150')->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('PZ', $results->first()->law_short);
        $this->assertEquals('150', $results->first()->article);
    }

    public function test_finds_provisions_by_tag(): void
    {
        // Arrange
        LegalProvision::factory()->create([
            'tags' => ['file_access', 'predsjednik_suda'],
        ]);

        LegalProvision::factory()->create([
            'tags' => ['constitutional', 'ustavni_sud'],
        ]);

        // Act
        $results = LegalProvision::withTag('file_access')->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertContains('file_access', $results->first()->tags);
    }

    public function test_finds_provisions_for_profile(): void
    {
        // Arrange
        LegalProvision::factory()->create([
            'tags' => ['predsjednik_suda'],
            'law_short' => 'PZ',
            'article' => '150',
        ]);

        LegalProvision::factory()->create([
            'tags' => ['ustavni_sud'],
            'law_short' => 'Ustav',
            'article' => '18',
        ]);

        // Act
        $results = LegalProvision::forProfile('predsjednik_suda')->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('PZ', $results->first()->law_short);
    }

    public function test_formats_short_citation_string(): void
    {
        // Arrange
        $provision = LegalProvision::factory()->create([
            'law_name' => 'Prekrsajni zakon',
            'law_short' => 'PZ',
            'article' => '150',
            'paragraph' => '1',
            'point' => null,
        ]);

        // Act & Assert
        $this->assertEquals('PZ cl.150 st.1', $provision->shortCitation());
    }

    public function test_formats_short_citation_with_point(): void
    {
        // Arrange
        $provision = LegalProvision::factory()->create([
            'law_name' => 'Zakon o kaznenom postupku',
            'law_short' => 'ZKP',
            'article' => '10',
            'paragraph' => '2',
            'point' => '2',
        ]);

        // Act & Assert
        $this->assertEquals('ZKP cl.10 st.2 toc.2', $provision->shortCitation());
    }

    public function test_formats_full_citation_string(): void
    {
        // Arrange
        $provision = LegalProvision::factory()->create([
            'law_name' => 'Prekrsajni zakon',
            'law_short' => 'PZ',
            'article' => '150',
            'paragraph' => '1',
        ]);

        // Act
        $citation = $provision->fullCitation();

        // Assert
        $this->assertStringContainsString('Prekrsajni zakon', $citation);
        $this->assertStringContainsString('clanak 150', $citation);
        $this->assertStringContainsString('stavak 1', $citation);
    }

    public function test_casts_tags_as_array(): void
    {
        // Arrange & Act
        $provision = LegalProvision::factory()->create([
            'tags' => ['file_access', 'defence_rights', 'constitutional'],
        ]);

        // Assert
        $this->assertIsArray($provision->tags);
        $this->assertCount(3, $provision->tags);
        $this->assertContains('file_access', $provision->tags);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        // Arrange & Act
        $provision = LegalProvision::create([
            'law_name' => 'Prekrsajni zakon',
            'law_short' => 'PZ',
            'article' => '150',
            'paragraph' => '1',
            'point' => null,
            'title' => 'Razgledavanje i prepisivanje spisa',
            'full_text' => 'Stranke i sudionici u postupku imaju pravo razgledavati i prepisivati spise.',
            'interpretation' => 'Ovo je tumacenje odredbe',
            'tags' => ['file_access'],
            'source_url' => 'https://www.zakon.hr/z/52/Prekrsajni-zakon',
        ]);

        // Assert
        $this->assertEquals('Prekrsajni zakon', $provision->law_name);
        $this->assertEquals('PZ', $provision->law_short);
        $this->assertEquals('150', $provision->article);
        $this->assertEquals('1', $provision->paragraph);
        $this->assertNull($provision->point);
        $this->assertStringContainsString('Razgledavanje', $provision->title);
        $this->assertStringContainsString('Stranke', $provision->full_text);
        $this->assertStringContainsString('tumacenje', $provision->interpretation);
        $this->assertContains('file_access', $provision->tags);
        $this->assertStringContainsString('zakon.hr', $provision->source_url);
    }

    public function test_handles_null_optional_fields(): void
    {
        // Arrange & Act
        $provision = LegalProvision::factory()->create([
            'paragraph' => null,
            'point' => null,
            'title' => null,
            'interpretation' => null,
            'source_url' => null,
        ]);

        // Assert
        $this->assertNull($provision->paragraph);
        $this->assertNull($provision->point);
        $this->assertNull($provision->title);
        $this->assertNull($provision->interpretation);
        $this->assertNull($provision->source_url);
    }

    public function test_supports_croatian_characters(): void
    {
        // Arrange & Act
        $provision = LegalProvision::factory()->create([
            'law_name' => 'Prekrsajni zakon',
            'title' => 'Razgledavanje i prepisivanje spisa',
            'full_text' => 'Stranke i sudionici u postupku imaju pravo razgledavati i prepisivati spise. To sud moze dopustiti i svakomu drugom tko za to ima opravdani interes.',
        ]);

        // Assert
        $this->assertStringContainsString('Prekrsajni', $provision->law_name);
        $this->assertStringContainsString('Razgledavanje', $provision->title);
        $this->assertStringContainsString('sudionici', $provision->full_text);
    }

    public function test_short_citation_without_paragraph(): void
    {
        // Arrange
        $provision = LegalProvision::factory()->create([
            'law_short' => 'ZKP',
            'article' => '183',
            'paragraph' => null,
            'point' => null,
        ]);

        // Act & Assert
        $this->assertEquals('ZKP cl.183', $provision->shortCitation());
    }

    public function test_can_query_by_law_short(): void
    {
        // Arrange
        LegalProvision::factory()->create(['law_short' => 'PZ', 'article' => '150']);
        LegalProvision::factory()->create(['law_short' => 'PZ', 'article' => '108']);
        LegalProvision::factory()->create(['law_short' => 'ZKP', 'article' => '184']);

        // Act
        $pzProvisions = LegalProvision::forLaw('PZ')->get();
        $zkpProvisions = LegalProvision::forLaw('ZKP')->get();

        // Assert
        $this->assertCount(2, $pzProvisions);
        $this->assertCount(1, $zkpProvisions);
    }

    public function test_get_complements_resolves_ascii_cl_references(): void
    {
        // Arrange - create the target provision that should be found
        LegalProvision::factory()->create([
            'law_short' => 'ZKP',
            'article' => '240',
            'paragraph' => null,
        ]);

        // Create the provision with complements using cl. format (as produced by shortCitation/seeder)
        $provision = LegalProvision::factory()->create([
            'complements' => ['ZKP cl.240'],
        ]);

        // Act
        $complements = $provision->getComplements();

        // Assert
        $this->assertCount(1, $complements);
        $this->assertEquals('ZKP', $complements->first()->law_short);
        $this->assertEquals('240', $complements->first()->article);
    }

    public function test_get_complements_resolves_croatian_cl_references(): void
    {
        // Arrange
        LegalProvision::factory()->create([
            'law_short' => 'Ustav',
            'article' => '34',
            'paragraph' => null,
        ]);

        $provision = LegalProvision::factory()->create([
            'complements' => ['Ustav čl.34'],
        ]);

        // Act
        $complements = $provision->getComplements();

        // Assert
        $this->assertCount(1, $complements);
        $this->assertEquals('Ustav', $complements->first()->law_short);
        $this->assertEquals('34', $complements->first()->article);
    }

    public function test_get_complements_resolves_reference_with_paragraph(): void
    {
        // Arrange
        LegalProvision::factory()->create([
            'law_short' => 'PZ',
            'article' => '150',
            'paragraph' => '4',
        ]);

        $provision = LegalProvision::factory()->create([
            'complements' => ['PZ cl.150 st.4'],
        ]);

        // Act
        $complements = $provision->getComplements();

        // Assert
        $this->assertCount(1, $complements);
        $this->assertEquals('PZ', $complements->first()->law_short);
        $this->assertEquals('150', $complements->first()->article);
        $this->assertEquals('4', $complements->first()->paragraph);
    }

    public function test_get_complements_returns_empty_when_no_complements(): void
    {
        // Arrange
        $provision = LegalProvision::factory()->create([
            'complements' => [],
        ]);

        // Act
        $complements = $provision->getComplements();

        // Assert
        $this->assertCount(0, $complements);
    }

    public function test_get_complements_resolves_multiple_references(): void
    {
        // Arrange
        LegalProvision::factory()->create([
            'law_short' => 'ZKP',
            'article' => '240',
            'paragraph' => null,
        ]);

        LegalProvision::factory()->create([
            'law_short' => 'Ustav',
            'article' => '34',
            'paragraph' => null,
        ]);

        $provision = LegalProvision::factory()->create([
            'complements' => ['ZKP cl.240', 'Ustav cl.34'],
        ]);

        // Act
        $complements = $provision->getComplements();

        // Assert
        $this->assertCount(2, $complements);
        $lawShorts = $complements->pluck('law_short')->sort()->values()->all();
        $this->assertEquals(['Ustav', 'ZKP'], $lawShorts);
    }

    public function test_factory_creates_valid_provision(): void
    {
        // Act
        $provision = LegalProvision::factory()->create();

        // Assert
        $this->assertNotNull($provision->id);
        $this->assertNotNull($provision->law_name);
        $this->assertNotNull($provision->law_short);
        $this->assertNotNull($provision->article);
        $this->assertNotNull($provision->full_text);
        $this->assertIsArray($provision->tags);
    }
}
