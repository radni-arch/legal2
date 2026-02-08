<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Contracts\LegalArtillery\AttachmentCollectorInterface;
use App\Contracts\LegalArtillery\DevastatingArgumentBuilderInterface;
use App\Contracts\LegalArtillery\SampleDocumentStoreInterface;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\SenderIdentity;
use App\Models\Evidence;
use App\Models\LegalCase;
use App\Services\LegalArtillery\CaseBridge;
use App\Services\LegalArtillery\ProfileContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ProfileContextBuilder injectCaseContext() method (Sprint 6).
 *
 * Verifies:
 * - injectCaseContext() returns self for fluent chaining
 * - injectCaseContext() populates case facts into context
 * - injectCaseContext() populates case timeline into context
 * - injectCaseContext() populates case warnings into context
 * - injectCaseContext() triggers evidence loading when evidence IDs found
 * - reset() clears case context data
 */
class ProfileContextBuilderCaseContextTest extends TestCase
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
    // injectCaseContext() basic tests
    // =========================================================================

    public function test_inject_case_context_returns_self_for_fluent_chaining(): void
    {
        $case = LegalCase::factory()->create();

        $result = $this->builder->injectCaseContext($case->id);

        $this->assertSame($this->builder, $result);
    }

    public function test_inject_case_context_populates_case_facts(): void
    {
        $case = LegalCase::factory()->create([
            'description' => 'Important case description for document generation',
        ]);

        $this->builder->injectCaseContext($case->id);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        $this->assertArrayHasKey('case_facts', $contextArray);
        $this->assertStringContainsString('Important case description', $contextArray['case_facts']);
    }

    public function test_inject_case_context_populates_case_timeline(): void
    {
        $case = LegalCase::factory()->create();

        $this->builder->injectCaseContext($case->id);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        $this->assertArrayHasKey('case_timeline', $contextArray);
        $this->assertNotEmpty($contextArray['case_timeline']);
    }

    public function test_inject_case_context_populates_warnings_for_missing_case(): void
    {
        $this->builder->injectCaseContext('nonexistent-case-id');
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        $this->assertArrayHasKey('case_warnings', $contextArray);
        $this->assertStringContainsString('not found', $contextArray['case_warnings']);
    }

    public function test_inject_case_context_loads_evidence_when_available(): void
    {
        $case = LegalCase::factory()->create();
        Evidence::factory()->create([
            'case_id' => $case->id,
            'title' => 'Auto-loaded evidence',
        ]);

        $this->builder->injectCaseContext($case->id);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        $this->assertNotEmpty($contextArray['evidence']);
        $this->assertSame('Auto-loaded evidence', $contextArray['evidence'][0]['title']);
    }

    public function test_inject_case_context_with_specific_evidence_ids(): void
    {
        $case = LegalCase::factory()->create();
        $wanted = Evidence::factory()->create([
            'case_id' => $case->id,
            'title' => 'Wanted evidence',
        ]);
        Evidence::factory()->create([
            'case_id' => $case->id,
            'title' => 'Unwanted evidence',
        ]);

        $this->builder->injectCaseContext($case->id, [$wanted->id]);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        $this->assertCount(1, $contextArray['evidence']);
        $this->assertSame('Wanted evidence', $contextArray['evidence'][0]['title']);
    }

    public function test_inject_case_context_handles_case_with_no_evidence_gracefully(): void
    {
        $case = LegalCase::factory()->create();

        $this->builder->injectCaseContext($case->id);
        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);

        $contextArray = $context->toArray();
        // No evidence loaded, but no error either
        $this->assertArrayHasKey('evidence', $contextArray);
    }

    public function test_reset_clears_case_context_data(): void
    {
        $case = LegalCase::factory()->create([
            'description' => 'Should be cleared after reset',
        ]);

        $this->builder
            ->injectCaseContext($case->id)
            ->reset();

        $context = $this->builder->buildEnhanced($this->profile, $this->caseContext);
        $contextArray = $context->toArray();

        // After reset, case-specific data should not be present
        $this->assertArrayNotHasKey('case_facts', $contextArray);
        $this->assertArrayNotHasKey('case_timeline', $contextArray);
        $this->assertArrayNotHasKey('case_warnings', $contextArray);
    }
}
