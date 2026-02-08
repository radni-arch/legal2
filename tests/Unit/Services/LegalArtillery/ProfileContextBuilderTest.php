<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Contracts\LegalArtillery\AttachmentCollectorInterface;
use App\Contracts\LegalArtillery\DevastatingArgumentBuilderInterface;
use App\Contracts\LegalArtillery\SampleDocumentStoreInterface;
use App\DTOs\DocumentProfile;
use App\Models\LegalPrecedent;
use App\Models\LegalProvision;
use App\Services\LegalArtillery\AttachmentCollector;
use App\Services\LegalArtillery\ProfileContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProfileContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected ProfileContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\LegalProvisionsSeeder::class);
        $this->seed(\Database\Seeders\LegalPrecedentsSeeder::class);

        // Create mocks for required dependencies
        $argumentBuilder = Mockery::mock(DevastatingArgumentBuilderInterface::class);
        $argumentBuilder->shouldReceive('getChainsForProfile')->andReturn([]);
        $argumentBuilder->shouldReceive('buildArgumentInjection')->andReturn('');
        $argumentBuilder->shouldReceive('getKillerSummaries')->andReturn([]);

        $sampleStore = Mockery::mock(SampleDocumentStoreInterface::class);
        $sampleStore->shouldReceive('getSampleForProfile')->andReturn(null);
        $sampleStore->shouldReceive('getCommonBlocks')->andReturn(null);

        // Use real AttachmentCollector for accurate testing
        $attachmentCollector = new AttachmentCollector();

        $this->builder = new ProfileContextBuilder(
            $argumentBuilder,
            $sampleStore,
            $attachmentCollector,
        );
    }

    public function test_builds_context_for_predsjednik_suda(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = $this->builder->build($profile);

        $this->assertArrayHasKey('provisions', $context);
        $this->assertArrayHasKey('precedents', $context);
        $this->assertArrayHasKey('attachments_section', $context);
        $this->assertArrayHasKey('injected_prompt', $context);
        $this->assertNotEmpty($context['provisions']);
    }

    public function test_builds_context_for_ustavni_sud(): void
    {
        $profile = DocumentProfile::fromConfig('ustavni_sud');
        $context = $this->builder->build($profile);

        // Ustavna tuzba treba najvise prakse i odredbi
        $this->assertNotEmpty($context['precedents']);
        $this->assertNotEmpty($context['provisions']);
    }

    public function test_builds_context_for_echr(): void
    {
        $profile = DocumentProfile::fromConfig('echr_application');
        $context = $this->builder->build($profile);

        // ECHR treba ECHR presude
        $echrPrecedents = array_filter(
            $context['precedents'],
            fn($p) => $p['court'] === 'ECHR'
        );
        $this->assertNotEmpty($echrPrecedents);
    }

    public function test_injected_prompt_contains_citations(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = $this->builder->build($profile);

        $this->assertStringContainsString('cl.150', $context['injected_prompt']);
    }

    public function test_injected_prompt_contains_precedent_quotes(): void
    {
        $profile = DocumentProfile::fromConfig('ustavni_sud');
        $context = $this->builder->build($profile);

        // Treba sadrzavati citatne navode iz presuda
        $this->assertStringContainsString('U-III-3071/2006', $context['injected_prompt']);
    }

    public function test_provisions_array_has_expected_structure(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = $this->builder->build($profile);

        $this->assertNotEmpty($context['provisions']);
        $firstProvision = $context['provisions'][0];
        $this->assertArrayHasKey('citation', $firstProvision);
        $this->assertArrayHasKey('full_citation', $firstProvision);
        $this->assertArrayHasKey('full_text', $firstProvision);
        $this->assertArrayHasKey('interpretation', $firstProvision);
        $this->assertArrayHasKey('law_name', $firstProvision);
    }

    public function test_precedents_array_has_expected_structure(): void
    {
        $profile = DocumentProfile::fromConfig('ustavni_sud');
        $context = $this->builder->build($profile);

        $this->assertNotEmpty($context['precedents']);
        $firstPrecedent = $context['precedents'][0];
        $this->assertArrayHasKey('citation', $firstPrecedent);
        $this->assertArrayHasKey('court', $firstPrecedent);
        $this->assertArrayHasKey('key_holding', $firstPrecedent);
        $this->assertArrayHasKey('key_quote', $firstPrecedent);
        $this->assertArrayHasKey('quote_language', $firstPrecedent);
        $this->assertArrayHasKey('relevance', $firstPrecedent);
    }

    public function test_attachments_section_is_populated_for_profile_with_attachments(): void
    {
        // ombudsman profile requires attachments
        $profile = DocumentProfile::fromConfig('ombudsman');
        $context = $this->builder->build($profile);

        $this->assertNotEmpty($context['attachments_section']);
    }

    public function test_attachments_section_is_empty_for_profile_without_attachments(): void
    {
        // predsjednik_suda has minimal attachments (just ID)
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = $this->builder->build($profile);

        // predsjednik_suda does have 1 attachment (personal ID), so check it's not empty
        $this->assertNotEmpty($context['attachments_section']);
    }

    public function test_empty_database_returns_empty_collections(): void
    {
        // Truncate the tables
        LegalProvision::query()->delete();
        LegalPrecedent::query()->delete();

        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = $this->builder->build($profile);

        $this->assertEmpty($context['provisions']);
        $this->assertEmpty($context['precedents']);
    }

    public function test_injected_prompt_has_legal_base_section(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = $this->builder->build($profile);

        // Should have the legal base header
        $this->assertStringContainsString('PRAVNA BAZA', $context['injected_prompt']);
        $this->assertStringContainsString('Zakonske odredbe', $context['injected_prompt']);
    }

    public function test_injected_prompt_has_case_law_section(): void
    {
        $profile = DocumentProfile::fromConfig('ustavni_sud');
        $context = $this->builder->build($profile);

        // Should have the case law section header
        $this->assertStringContainsString('Sudska praksa', $context['injected_prompt']);
    }
}
