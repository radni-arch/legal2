<?php

namespace Tests\Unit\DTOs;

use App\DTOs\ArgumentChain;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\EnhancedContext;
use App\DTOs\SenderIdentity;
use PHPUnit\Framework\TestCase;

class EnhancedContextTest extends TestCase
{
    private function createCaseContext(): CaseContext
    {
        return new CaseContext(
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

    private function createDocumentProfile(): DocumentProfile
    {
        return new DocumentProfile(
            key: 'test_profile',
            name: 'Test Profile',
            recipient: ['title' => 'Test Court', 'address' => '123 Court St'],
            legalBasis: ['Article 1', 'Article 2'],
            tone: 'formal',
            structure: ['intro', 'body', 'conclusion'],
            docxTemplate: 'test-template',
        );
    }

    private function createArgumentChain(): ArgumentChain
    {
        return new ArgumentChain(
            name: 'test_chain',
            displayName: 'Test Argument Chain',
            steps: [
                ['label' => 'Premise', 'argument' => 'Test premise'],
                ['label' => 'Conclusion', 'argument' => 'Test conclusion'],
            ],
            killerSummary: 'Test killer summary',
            profileKeys: ['test_profile'],
        );
    }

    public function test_can_be_constructed_with_all_properties(): void
    {
        $profile = $this->createDocumentProfile();
        $caseContext = $this->createCaseContext();
        $argumentChains = [$this->createArgumentChain()];
        $sampleDocument = 'Sample document content';
        $attachmentsList = ['Attachment 1', 'Attachment 2'];

        $enhanced = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
            argumentChains: $argumentChains,
            sampleDocument: $sampleDocument,
            attachmentsList: $attachmentsList,
        );

        $this->assertSame($profile, $enhanced->profile);
        $this->assertSame($caseContext, $enhanced->caseContext);
        $this->assertCount(1, $enhanced->argumentChains);
        $this->assertEquals('Sample document content', $enhanced->sampleDocument);
        $this->assertEquals(['Attachment 1', 'Attachment 2'], $enhanced->attachmentsList);
    }

    public function test_can_be_constructed_with_minimal_properties(): void
    {
        $profile = $this->createDocumentProfile();
        $caseContext = $this->createCaseContext();

        $enhanced = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
        );

        $this->assertSame($profile, $enhanced->profile);
        $this->assertSame($caseContext, $enhanced->caseContext);
        $this->assertEmpty($enhanced->argumentChains);
        $this->assertNull($enhanced->sampleDocument);
        $this->assertEmpty($enhanced->attachmentsList);
    }

    public function test_hasArgumentChains_returns_correct_value(): void
    {
        $profile = $this->createDocumentProfile();
        $caseContext = $this->createCaseContext();

        $withChains = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
            argumentChains: [$this->createArgumentChain()],
        );

        $withoutChains = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
        );

        $this->assertTrue($withChains->hasArgumentChains());
        $this->assertFalse($withoutChains->hasArgumentChains());
    }

    public function test_hasSampleDocument_returns_correct_value(): void
    {
        $profile = $this->createDocumentProfile();
        $caseContext = $this->createCaseContext();

        $withSample = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
            sampleDocument: 'Some sample content',
        );

        $withoutSample = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
        );

        $this->assertTrue($withSample->hasSampleDocument());
        $this->assertFalse($withoutSample->hasSampleDocument());
    }

    public function test_hasAttachments_returns_correct_value(): void
    {
        $profile = $this->createDocumentProfile();
        $caseContext = $this->createCaseContext();

        $withAttachments = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
            attachmentsList: ['Attachment 1'],
        );

        $withoutAttachments = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
        );

        $this->assertTrue($withAttachments->hasAttachments());
        $this->assertFalse($withoutAttachments->hasAttachments());
    }

    public function test_formatForLLM_includes_argument_chains(): void
    {
        $profile = $this->createDocumentProfile();
        $caseContext = $this->createCaseContext();
        $chain = $this->createArgumentChain();

        $enhanced = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
            argumentChains: [$chain],
        );

        $formatted = $enhanced->formatForLLM();

        $this->assertStringContainsString('Test Argument Chain', $formatted);
        $this->assertStringContainsString('Test premise', $formatted);
        $this->assertStringContainsString('Test conclusion', $formatted);
        $this->assertStringContainsString('Test killer summary', $formatted);
    }

    public function test_formatForLLM_includes_sample_document(): void
    {
        $profile = $this->createDocumentProfile();
        $caseContext = $this->createCaseContext();

        $enhanced = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
            sampleDocument: 'This is a sample document for reference.',
        );

        $formatted = $enhanced->formatForLLM();

        $this->assertStringContainsString('This is a sample document for reference.', $formatted);
        $this->assertStringContainsString('UZORAK', $formatted); // Croatian for "sample"
    }

    public function test_formatForLLM_includes_attachments_list(): void
    {
        $profile = $this->createDocumentProfile();
        $caseContext = $this->createCaseContext();

        $enhanced = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
            attachmentsList: [
                'Prilog 1: Warrant Copy',
                'Prilog 2: Police Request',
            ],
        );

        $formatted = $enhanced->formatForLLM();

        $this->assertStringContainsString('PRILOZI', $formatted); // Croatian for "attachments"
        $this->assertStringContainsString('Prilog 1: Warrant Copy', $formatted);
        $this->assertStringContainsString('Prilog 2: Police Request', $formatted);
    }

    public function test_formatForLLM_handles_empty_optional_properties(): void
    {
        $profile = $this->createDocumentProfile();
        $caseContext = $this->createCaseContext();

        $enhanced = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
        );

        $formatted = $enhanced->formatForLLM();

        // Should still produce valid output without optional content
        $this->assertIsString($formatted);
        $this->assertStringContainsString('Test Profile', $formatted);
    }

    public function test_toArray_returns_complete_structure(): void
    {
        $profile = $this->createDocumentProfile();
        $caseContext = $this->createCaseContext();
        $chain = $this->createArgumentChain();

        $enhanced = new EnhancedContext(
            profile: $profile,
            caseContext: $caseContext,
            argumentChains: [$chain],
            sampleDocument: 'Sample content',
            attachmentsList: ['Attachment 1'],
        );

        $array = $enhanced->toArray();

        $this->assertArrayHasKey('profile_key', $array);
        $this->assertArrayHasKey('case_context', $array);
        $this->assertArrayHasKey('argument_chains', $array);
        $this->assertArrayHasKey('sample_document', $array);
        $this->assertArrayHasKey('attachments_list', $array);

        $this->assertEquals('test_profile', $array['profile_key']);
        $this->assertCount(1, $array['argument_chains']);
        $this->assertEquals('Sample content', $array['sample_document']);
    }
}
