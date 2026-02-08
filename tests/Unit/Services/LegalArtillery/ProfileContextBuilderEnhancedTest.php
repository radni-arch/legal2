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
use App\Services\LegalArtillery\ProfileContextBuilder;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Tests for ProfileContextBuilder v2 enhanced functionality.
 *
 * These tests focus on the new enhanced mode with dependency injection,
 * separate from the legacy database-based tests.
 */
class ProfileContextBuilderEnhancedTest extends TestCase
{
    private DocumentProfile $profile;
    private CaseContext $caseContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = new DocumentProfile(
            key: 'supreme_court',
            name: 'Vrhovni sud',
            recipient: ['title' => 'Vrhovni sud RH', 'address' => 'Zagreb'],
            legalBasis: ['Article 1', 'Article 2'],
            tone: 'formal',
            structure: ['intro', 'body', 'conclusion'],
            docxTemplate: 'supreme-court-template',
        );

        $this->caseContext = new CaseContext(
            caseNumber: 'TEST-123/2024',
            searchDate: '2024-01-15',
            archiveDate: '2024-01-20',
            addressSearched: 'Test Address 123',
            warrantReference: 'WARRANT-REF',
            policeRequestKlasa: 'UP/I-123',
            policeRequestUrbroj: '511-01-01',
            legalBasisWarrant: 'Article 123 ZKP',
            suspectedOffense: 'Test Offense',
            judge: 'Test Judge',
            denialDate: '2024-02-01',
            countyCourtResponseDate: '2024-02-15',
            sender: new SenderIdentity(
                name: 'Test Sender',
                oib: '12345678901',
                address: 'Sender Address',
                email: 'test@example.com',
                phone: '+385991234567',
            ),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createMockArgumentBuilder(): DevastatingArgumentBuilderInterface
    {
        $mock = Mockery::mock(DevastatingArgumentBuilderInterface::class);
        $mock->shouldReceive('getChainsForProfile')
            ->andReturn([
                new ArgumentChain(
                    name: 'test_chain',
                    displayName: 'Test Chain',
                    steps: [
                        ['label' => 'Premise', 'argument' => 'Test premise'],
                    ],
                    killerSummary: 'Test summary',
                    profileKeys: ['supreme_court'],
                ),
            ]);
        $mock->shouldReceive('buildArgumentInjection')
            ->andReturn("## ARGUMENT CHAIN\nTest chain content");
        $mock->shouldReceive('getKillerSummaries')
            ->andReturn([['name' => 'test_chain', 'summary' => 'Test summary']]);

        return $mock;
    }

    private function createMockSampleStore(): SampleDocumentStoreInterface
    {
        $mock = Mockery::mock(SampleDocumentStoreInterface::class);
        $mock->shouldReceive('getSampleForProfile')
            ->andReturn('Sample document content for testing');
        $mock->shouldReceive('hasSampleForProfile')
            ->andReturn(true);
        $mock->shouldReceive('getCommonBlocks')
            ->andReturn('Common blocks content');

        return $mock;
    }

    private function createMockAttachmentCollector(): AttachmentCollectorInterface
    {
        $mock = Mockery::mock(AttachmentCollectorInterface::class);
        $mock->shouldReceive('getAttachmentsForProfile')
            ->andReturn(['Prilog 1: Document A', 'Prilog 2: Document B']);
        $mock->shouldReceive('generateAttachmentListSection')
            ->andReturn("PRILOZI:\n1. Document A\n2. Document B");
        $mock->shouldReceive('profileRequiresAttachments')
            ->andReturn(true);

        return $mock;
    }

    public function test_can_be_instantiated_with_dependencies(): void
    {
        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $this->assertInstanceOf(ProfileContextBuilder::class, $builder);
    }

    public function test_can_be_instantiated_without_dependencies(): void
    {
        $builder = new ProfileContextBuilder();

        $this->assertInstanceOf(ProfileContextBuilder::class, $builder);
    }

    public function test_buildEnhanced_returns_EnhancedContext(): void
    {
        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $result = $builder->buildEnhanced($this->profile, $this->caseContext);

        $this->assertInstanceOf(EnhancedContext::class, $result);
    }

    public function test_buildEnhanced_includes_profile_and_context(): void
    {
        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $result = $builder->buildEnhanced($this->profile, $this->caseContext);

        $this->assertSame($this->profile, $result->profile);
        $this->assertSame($this->caseContext, $result->caseContext);
    }

    public function test_buildEnhanced_auto_includes_argument_chains_from_builder(): void
    {
        $argumentBuilder = $this->createMockArgumentBuilder();

        $builder = new ProfileContextBuilder(
            $argumentBuilder,
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $result = $builder->buildEnhanced($this->profile, $this->caseContext);

        $this->assertTrue($result->hasArgumentChains());
        $this->assertCount(1, $result->argumentChains);
        $this->assertEquals('test_chain', $result->argumentChains[0]->name);
    }

    public function test_buildEnhanced_auto_includes_sample_from_store(): void
    {
        $sampleStore = $this->createMockSampleStore();

        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $sampleStore,
            $this->createMockAttachmentCollector(),
        );

        $result = $builder->buildEnhanced($this->profile, $this->caseContext);

        $this->assertTrue($result->hasSampleDocument());
        $this->assertStringContainsString('Sample document content', $result->sampleDocument);
    }

    public function test_buildEnhanced_auto_includes_attachments_from_collector(): void
    {
        $attachmentCollector = $this->createMockAttachmentCollector();

        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $attachmentCollector,
        );

        $result = $builder->buildEnhanced($this->profile, $this->caseContext);

        $this->assertTrue($result->hasAttachments());
        $this->assertCount(2, $result->attachmentsList);
    }

    public function test_includeArgumentChain_adds_chain_fluently(): void
    {
        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $customChain = new ArgumentChain(
            name: 'custom_chain',
            displayName: 'Custom Chain',
            steps: [['label' => 'Step', 'argument' => 'Custom arg']],
            killerSummary: 'Custom summary',
            profileKeys: ['supreme_court'],
        );

        $result = $builder
            ->includeArgumentChain($customChain)
            ->buildEnhanced($this->profile, $this->caseContext);

        // Should include both auto-loaded and manually added chains
        $chainNames = array_map(fn($c) => $c->name, $result->argumentChains);
        $this->assertContains('custom_chain', $chainNames);
    }

    public function test_includeSampleDocument_overrides_auto_sample(): void
    {
        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $customSample = 'This is a custom sample document override.';

        $result = $builder
            ->includeSampleDocument($customSample)
            ->buildEnhanced($this->profile, $this->caseContext);

        $this->assertEquals($customSample, $result->sampleDocument);
    }

    public function test_includeAttachments_adds_attachments_fluently(): void
    {
        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $customAttachments = ['Prilog X: Custom Doc'];

        $result = $builder
            ->includeAttachments($customAttachments)
            ->buildEnhanced($this->profile, $this->caseContext);

        // Should include both auto-loaded and manually added attachments
        $this->assertContains('Prilog X: Custom Doc', $result->attachmentsList);
    }

    public function test_fluent_api_allows_method_chaining(): void
    {
        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $chain = new ArgumentChain(
            name: 'chain1',
            displayName: 'Chain 1',
            steps: [],
            killerSummary: 'Summary',
            profileKeys: ['supreme_court'],
        );

        $result = $builder
            ->includeArgumentChain($chain)
            ->includeSampleDocument('Custom sample')
            ->includeAttachments(['Custom attachment'])
            ->buildEnhanced($this->profile, $this->caseContext);

        $this->assertInstanceOf(EnhancedContext::class, $result);
    }

    public function test_buildEnhanced_formatForLLM_includes_all_components(): void
    {
        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $result = $builder->buildEnhanced($this->profile, $this->caseContext);
        $formatted = $result->formatForLLM();

        // Should include profile info
        $this->assertStringContainsString('Vrhovni sud', $formatted);

        // Should include argument chain
        $this->assertStringContainsString('Test Chain', $formatted);

        // Should include sample document
        $this->assertStringContainsString('UZORAK', $formatted);

        // Should include attachments
        $this->assertStringContainsString('PRILOZI', $formatted);
    }

    public function test_reset_clears_manual_additions(): void
    {
        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $chain = new ArgumentChain(
            name: 'manual_chain',
            displayName: 'Manual',
            steps: [],
            killerSummary: 'Manual summary',
            profileKeys: ['supreme_court'],
        );

        // Add manual items then reset
        $builder
            ->includeArgumentChain($chain)
            ->includeSampleDocument('Manual sample')
            ->includeAttachments(['Manual attachment'])
            ->reset();

        $result = $builder->buildEnhanced($this->profile, $this->caseContext);

        // Manual chain should not be present (only auto-loaded)
        $chainNames = array_map(fn($c) => $c->name, $result->argumentChains);
        $this->assertNotContains('manual_chain', $chainNames);
    }

    // Note: test_build_returns_legacy_array_format is in the Feature tests
    // since it requires database access for loading provisions/precedents.

    public function test_handles_null_sample_document_gracefully(): void
    {
        $sampleStore = Mockery::mock(SampleDocumentStoreInterface::class);
        $sampleStore->shouldReceive('getSampleForProfile')->andReturn(null);
        $sampleStore->shouldReceive('hasSampleForProfile')->andReturn(false);
        $sampleStore->shouldReceive('getCommonBlocks')->andReturn(null);

        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $sampleStore,
            $this->createMockAttachmentCollector(),
        );

        $result = $builder->buildEnhanced($this->profile, $this->caseContext);

        $this->assertFalse($result->hasSampleDocument());
    }

    public function test_handles_empty_argument_chains_gracefully(): void
    {
        $argumentBuilder = Mockery::mock(DevastatingArgumentBuilderInterface::class);
        $argumentBuilder->shouldReceive('getChainsForProfile')->andReturn([]);
        $argumentBuilder->shouldReceive('buildArgumentInjection')->andReturn('');
        $argumentBuilder->shouldReceive('getKillerSummaries')->andReturn([]);

        $builder = new ProfileContextBuilder(
            $argumentBuilder,
            $this->createMockSampleStore(),
            $this->createMockAttachmentCollector(),
        );

        $result = $builder->buildEnhanced($this->profile, $this->caseContext);

        $this->assertFalse($result->hasArgumentChains());
    }

    public function test_handles_empty_attachments_gracefully(): void
    {
        $attachmentCollector = Mockery::mock(AttachmentCollectorInterface::class);
        $attachmentCollector->shouldReceive('getAttachmentsForProfile')->andReturn([]);
        $attachmentCollector->shouldReceive('generateAttachmentListSection')->andReturn('');
        $attachmentCollector->shouldReceive('profileRequiresAttachments')->andReturn(false);

        $builder = new ProfileContextBuilder(
            $this->createMockArgumentBuilder(),
            $this->createMockSampleStore(),
            $attachmentCollector,
        );

        $result = $builder->buildEnhanced($this->profile, $this->caseContext);

        $this->assertFalse($result->hasAttachments());
    }

    public function test_buildEnhanced_without_dependencies_returns_context_with_manual_only(): void
    {
        $builder = new ProfileContextBuilder();

        $chain = new ArgumentChain(
            name: 'manual_chain',
            displayName: 'Manual Chain',
            steps: [],
            killerSummary: 'Manual summary',
            profileKeys: ['supreme_court'],
        );

        $result = $builder
            ->includeArgumentChain($chain)
            ->includeSampleDocument('Manual sample')
            ->includeAttachments(['Manual attachment'])
            ->buildEnhanced($this->profile, $this->caseContext);

        $this->assertInstanceOf(EnhancedContext::class, $result);
        $this->assertCount(1, $result->argumentChains);
        $this->assertEquals('manual_chain', $result->argumentChains[0]->name);
        $this->assertEquals('Manual sample', $result->sampleDocument);
        $this->assertContains('Manual attachment', $result->attachmentsList);
    }
}
