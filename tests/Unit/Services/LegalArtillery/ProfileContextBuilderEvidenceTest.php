<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Contracts\LegalArtillery\AttachmentCollectorInterface;
use App\Contracts\LegalArtillery\DevastatingArgumentBuilderInterface;
use App\Contracts\LegalArtillery\SampleDocumentStoreInterface;
use App\DTOs\ArgumentChain;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\EnhancedContext;
use App\DTOs\SenderIdentity;
use App\Models\Evidence;
use App\Models\LegalCase;
use App\Services\LegalArtillery\ProfileContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ProfileContextBuilder Sprint 2 evidence and misconduct integration.
 *
 * Verifies:
 * - injectEvidenceContext() loads evidence from DB and adds to context
 * - injectMisconductFlags() adds misconduct flags to context
 * - buildEnhanced() includes evidence and misconduct data when set
 * - Fluent chaining works correctly
 * - Empty evidence/flags are handled gracefully
 */
class ProfileContextBuilderEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentProfile $profile;
    private CaseContext $caseContext;
    private ProfileContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = new DocumentProfile(
            key: 'test_profile',
            name: 'Test Profile',
            recipient: ['title' => 'Test Recipient', 'address' => 'Test Address'],
            legalBasis: ['Article 1'],
            tone: 'formal',
            structure: ['intro', 'body', 'conclusion'],
            docxTemplate: 'test-template',
        );

        $this->caseContext = new CaseContext(
            caseNumber: 'TEST-123/2025',
            searchDate: '2025-01-15',
            archiveDate: '2025-01-20',
            addressSearched: 'Test Address 123',
            warrantReference: 'WARRANT-REF',
            policeRequestKlasa: 'UP/I-123',
            policeRequestUrbroj: '511-01-01',
            legalBasisWarrant: 'Article 123 ZKP',
            suspectedOffense: 'Test Offense',
            judge: 'Test Judge',
            denialDate: '2025-02-01',
            countyCourtResponseDate: '2025-02-15',
            sender: new SenderIdentity(
                name: 'Test Sender',
                oib: '12345678901',
                address: 'Sender Address',
                email: 'test@example.com',
                phone: '+385991234567',
            ),
        );

        $argumentBuilder = Mockery::mock(DevastatingArgumentBuilderInterface::class);
        $argumentBuilder->shouldReceive('getChainsForProfile')->andReturn([]);

        $sampleStore = Mockery::mock(SampleDocumentStoreInterface::class);
        $sampleStore->shouldReceive('getSampleForProfile')->andReturn(null);

        $attachmentCollector = Mockery::mock(AttachmentCollectorInterface::class);
        $attachmentCollector->shouldReceive('getAttachmentsForProfile')->andReturn([]);

        $this->builder = new ProfileContextBuilder(
            $argumentBuilder,
            $sampleStore,
            $attachmentCollector,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // injectEvidenceContext() tests
    // =========================================================================

    public function test_inject_evidence_context_returns_self_for_fluent_chaining(): void
    {
        $case = LegalCase::factory()->create();

        $result = $this->builder->injectEvidenceContext($case->id);

        $this->assertSame($this->builder, $result);
    }

    public function test_inject_evidence_context_loads_evidence_from_database(): void
    {
        $case = LegalCase::factory()->create();
        $evidence1 = Evidence::factory()->create([
            'case_id' => $case->id,
            'title' => 'Search warrant document',
            'description' => 'Original search warrant issued by court',
            'type' => 'documentary',
        ]);
        $evidence2 = Evidence::factory()->create([
            'case_id' => $case->id,
            'title' => 'Video recording',
            'description' => 'Security camera footage from the search',
            'type' => 'digital',
        ]);

        $this->builder->injectEvidenceContext($case->id);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $this->assertInstanceOf(EnhancedContext::class, $context);
        $contextArray = $context->toArray();
        $this->assertArrayHasKey('evidence', $contextArray);
        $this->assertCount(2, $contextArray['evidence']);
    }

    public function test_inject_evidence_context_filters_by_specific_evidence_ids(): void
    {
        $case = LegalCase::factory()->create();
        $evidence1 = Evidence::factory()->create([
            'case_id' => $case->id,
            'title' => 'Wanted evidence',
        ]);
        $evidence2 = Evidence::factory()->create([
            'case_id' => $case->id,
            'title' => 'Unwanted evidence',
        ]);

        $this->builder->injectEvidenceContext($case->id, [$evidence1->id]);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        $this->assertCount(1, $contextArray['evidence']);
        $this->assertEquals('Wanted evidence', $contextArray['evidence'][0]['title']);
    }

    public function test_inject_evidence_context_handles_empty_case(): void
    {
        $case = LegalCase::factory()->create();
        // No evidence created for this case

        $this->builder->injectEvidenceContext($case->id);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        $this->assertArrayHasKey('evidence', $contextArray);
        $this->assertEmpty($contextArray['evidence']);
    }

    // =========================================================================
    // injectMisconductFlags() tests
    // =========================================================================

    public function test_inject_misconduct_flags_returns_self_for_fluent_chaining(): void
    {
        $result = $this->builder->injectMisconductFlags(['procedural_violation']);

        $this->assertSame($this->builder, $result);
    }

    public function test_inject_misconduct_flags_adds_flags_to_context(): void
    {
        $flags = [
            'procedural_violation' => 'Search conducted without valid warrant',
            'rights_denial' => 'Access to case files denied without formal decision',
        ];

        $this->builder->injectMisconductFlags($flags);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        $this->assertArrayHasKey('misconduct_flags', $contextArray);
        $this->assertCount(2, $contextArray['misconduct_flags']);
        $this->assertArrayHasKey('procedural_violation', $contextArray['misconduct_flags']);
    }

    public function test_inject_misconduct_flags_handles_empty_flags(): void
    {
        $this->builder->injectMisconductFlags([]);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        $this->assertArrayHasKey('misconduct_flags', $contextArray);
        $this->assertEmpty($contextArray['misconduct_flags']);
    }

    // =========================================================================
    // buildEnhanced() integration tests
    // =========================================================================

    public function test_build_enhanced_includes_evidence_in_llm_format(): void
    {
        $case = LegalCase::factory()->create();
        Evidence::factory()->create([
            'case_id' => $case->id,
            'title' => 'Court order document',
            'description' => 'The original court order for the search',
            'type' => 'documentary',
        ]);

        $this->builder->injectEvidenceContext($case->id);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $formatted = $context->formatForLLM();
        $this->assertStringContainsString('DOKAZI', $formatted);
        $this->assertStringContainsString('Court order document', $formatted);
    }

    public function test_build_enhanced_includes_misconduct_flags_in_llm_format(): void
    {
        $flags = [
            'procedural_violation' => 'Search without valid warrant',
        ];

        $this->builder->injectMisconductFlags($flags);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $formatted = $context->formatForLLM();
        $this->assertStringContainsString('ZLOUPOTREBA', $formatted);
        $this->assertStringContainsString('Search without valid warrant', $formatted);
    }

    public function test_build_enhanced_without_evidence_or_flags_still_works(): void
    {
        // No evidence or flags injected - should work as before
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $this->assertInstanceOf(EnhancedContext::class, $context);
        $contextArray = $context->toArray();
        $this->assertArrayHasKey('evidence', $contextArray);
        $this->assertArrayHasKey('misconduct_flags', $contextArray);
        $this->assertEmpty($contextArray['evidence']);
        $this->assertEmpty($contextArray['misconduct_flags']);
    }

    public function test_reset_clears_evidence_and_misconduct_state(): void
    {
        $case = LegalCase::factory()->create();
        Evidence::factory()->create(['case_id' => $case->id, 'title' => 'Test']);

        $this->builder
            ->injectEvidenceContext($case->id)
            ->injectMisconductFlags(['flag' => 'value'])
            ->reset();

        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);
        $contextArray = $context->toArray();

        $this->assertEmpty($contextArray['evidence']);
        $this->assertEmpty($contextArray['misconduct_flags']);
    }

    // =========================================================================
    // Fluent chaining tests
    // =========================================================================

    public function test_fluent_chaining_with_evidence_and_misconduct(): void
    {
        $case = LegalCase::factory()->create();
        Evidence::factory()->create([
            'case_id' => $case->id,
            'title' => 'Key evidence',
        ]);

        $context = $this->builder
            ->injectEvidenceContext($case->id)
            ->injectMisconductFlags(['abuse' => 'Denial of access'])
            ->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        $this->assertNotEmpty($contextArray['evidence']);
        $this->assertNotEmpty($contextArray['misconduct_flags']);
    }
}
