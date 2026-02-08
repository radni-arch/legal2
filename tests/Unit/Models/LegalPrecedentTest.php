<?php

namespace Tests\Unit\Models;

use App\Models\LegalPrecedent;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LegalPrecedentTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_finds_precedents_by_court(): void
    {
        // Arrange
        LegalPrecedent::factory()->create(['court' => 'USRH']);
        LegalPrecedent::factory()->create(['court' => 'ECHR']);
        LegalPrecedent::factory()->create(['court' => 'VSRH']);

        // Act
        $usrhResults = LegalPrecedent::byCourt('USRH')->get();
        $echrResults = LegalPrecedent::byCourt('ECHR')->get();

        // Assert
        $this->assertCount(1, $usrhResults);
        $this->assertCount(1, $echrResults);
        $this->assertEquals('USRH', $usrhResults->first()->court);
    }

    /** @test */
    public function it_finds_precedents_for_profile(): void
    {
        // Arrange
        LegalPrecedent::factory()->create(['tags' => ['ustavni_sud', 'right_to_appeal']]);
        LegalPrecedent::factory()->create(['tags' => ['echr_application', 'article_6']]);
        LegalPrecedent::factory()->create(['tags' => ['ustavni_sud', 'due_process']]);

        // Act
        $results = LegalPrecedent::forProfile('ustavni_sud')->get();

        // Assert
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_finds_precedents_by_argument_type(): void
    {
        // Arrange
        LegalPrecedent::factory()->create(['argument_types' => ['equality_of_arms', 'file_access']]);
        LegalPrecedent::factory()->create(['argument_types' => ['effective_remedy']]);
        LegalPrecedent::factory()->create(['argument_types' => ['equality_of_arms', 'fair_trial']]);

        // Act
        $results = LegalPrecedent::forArgument('equality_of_arms')->get();

        // Assert
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_formats_citation_for_usrh(): void
    {
        // Arrange
        $precedent = LegalPrecedent::factory()->create([
            'case_number' => 'U-III-3071/2006',
            'court' => 'USRH',
            'decision_date' => '2009-03-18',
        ]);

        // Act
        $citation = $precedent->citation();

        // Assert
        $this->assertStringContainsString('U-III-3071/2006', $citation);
        $this->assertStringContainsString('USRH', $citation);
        $this->assertStringContainsString('2009', $citation);
    }

    /** @test */
    public function it_formats_citation_with_nn_reference(): void
    {
        // Arrange
        $precedent = LegalPrecedent::factory()->create([
            'case_number' => 'U-III-3071/2006',
            'court' => 'USRH',
            'decision_date' => '2009-03-18',
            'nn_reference' => 'NN 35/09',
        ]);

        // Act
        $citation = $precedent->citation();

        // Assert
        $this->assertStringContainsString('NN 35/09', $citation);
    }

    /** @test */
    public function it_formats_citation_for_echr(): void
    {
        // Arrange
        $precedent = LegalPrecedent::factory()->create([
            'case_number' => 'Dragojević v. Croatia',
            'court' => 'ECHR',
            'echr_app_number' => '68955/11',
            'decision_date' => '2015-01-15',
        ]);

        // Act
        $citation = $precedent->citation();

        // Assert
        $this->assertStringContainsString('Dragojević v. Croatia', $citation);
        $this->assertStringContainsString('68955/11', $citation);
    }

    /** @test */
    public function it_returns_quotable_text(): void
    {
        // Arrange
        $quote = 'Stranke zbog postupanja po pogrešnoj uputi ne smiju trpjeti štetne posljedice.';
        $precedent = LegalPrecedent::factory()->create([
            'key_quote' => $quote,
            'quote_language' => 'hr',
        ]);

        // Assert
        $this->assertNotEmpty($precedent->key_quote);
        $this->assertEquals($quote, $precedent->key_quote);
        $this->assertEquals('hr', $precedent->quote_language);
    }

    /** @test */
    public function it_casts_json_columns_to_arrays(): void
    {
        // Arrange
        $precedent = LegalPrecedent::factory()->create([
            'articles_interpreted' => ['ECHR Art.6', 'ECHR Art.13'],
            'argument_types' => ['fair_trial', 'effective_remedy'],
            'tags' => ['echr_application', 'human_rights'],
        ]);

        // Assert
        $this->assertIsArray($precedent->articles_interpreted);
        $this->assertIsArray($precedent->argument_types);
        $this->assertIsArray($precedent->tags);
        $this->assertCount(2, $precedent->articles_interpreted);
        $this->assertCount(2, $precedent->argument_types);
        $this->assertCount(2, $precedent->tags);
    }

    /** @test */
    public function it_casts_decision_date_to_carbon(): void
    {
        // Arrange
        $precedent = LegalPrecedent::factory()->create([
            'decision_date' => '2015-01-15',
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $precedent->decision_date);
        $this->assertEquals('2015-01-15', $precedent->decision_date->format('Y-m-d'));
    }

    /** @test */
    public function it_has_fillable_attributes(): void
    {
        // Arrange
        $data = [
            'case_number' => 'U-III-123/2020',
            'court' => 'USRH',
            'court_full' => 'Ustavni sud Republike Hrvatske',
            'decision_date' => '2020-05-15',
            'applicant' => 'Ivan Horvat',
            'respondent' => 'Republic of Croatia',
            'legal_issue' => 'Pravo na pristup sudu',
            'key_holding' => 'Ustavno pravo na pravično suđenje',
            'key_quote' => 'Pristup spisu mora biti osiguran strankama.',
            'quote_language' => 'hr',
            'relevance_to_case' => 'Direktno primjenjivo na predmet',
            'articles_interpreted' => ['Ustav RH čl.29'],
            'argument_types' => ['file_access', 'due_process'],
            'tags' => ['ustavni_sud'],
            'source_url' => 'https://usud.hr/example',
            'nn_reference' => 'NN 50/20',
        ];

        // Act
        $precedent = LegalPrecedent::create($data);

        // Assert
        $this->assertEquals('U-III-123/2020', $precedent->case_number);
        $this->assertEquals('USRH', $precedent->court);
        $this->assertEquals('Ustavni sud Republike Hrvatske', $precedent->court_full);
        $this->assertEquals('Ivan Horvat', $precedent->applicant);
        $this->assertEquals('https://usud.hr/example', $precedent->source_url);
    }
}
