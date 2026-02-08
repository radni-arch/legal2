<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\DocumentInventory;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentInventoryTest extends TestCase
{
    private string $caseDocDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caseDocDir = storage_path('app/legal-artillery/case-documents');
        File::ensureDirectoryExists($this->caseDocDir);
    }

    protected function tearDown(): void
    {
        File::delete($this->caseDocDir . '/found.pdf');
        File::delete($this->caseDocDir . '/alpha.pdf');
        File::delete($this->caseDocDir . '/generated-found.pdf');

        parent::tearDown();
    }

    #[Test]
    public function audit_reports_found_and_missing_documents(): void
    {
        config()->set('legal-artillery-documents.documents', [
            [
                'id' => 'found_doc',
                'filename' => 'found.pdf',
                'description_hr' => 'Pronadeni dokument',
                'description_en' => 'Found document',
                'date' => '2025-01-01',
                'type' => 'request',
                'profiles' => ['all'],
                'critical' => false,
                'ocr_needed' => false,
            ],
            [
                'id' => 'missing_doc',
                'filename' => 'missing.pdf',
                'description_hr' => 'Nedostajuci dokument',
                'description_en' => 'Missing document',
                'date' => '2025-01-02',
                'type' => 'response',
                'profiles' => ['all'],
                'critical' => true,
                'ocr_needed' => false,
            ],
        ]);
        config()->set('legal-artillery-documents.generated_documents', []);

        File::put($this->caseDocDir . '/found.pdf', 'content');

        $inventory = new DocumentInventory();
        $audit = $inventory->audit();

        $this->assertCount(1, $audit['found']);
        $this->assertCount(1, $audit['missing']);
        $this->assertCount(1, $audit['critical_missing']);
    }

    #[Test]
    public function for_profile_filters_and_sorts_documents(): void
    {
        config()->set('legal-artillery-documents.documents', [
            [
                'id' => 'alpha_doc',
                'filename' => 'alpha.pdf',
                'description_hr' => 'Alpha dokument',
                'description_en' => 'Alpha document',
                'date' => '2025-01-02',
                'type' => 'request',
                'profiles' => ['alpha'],
                'critical' => false,
                'ocr_needed' => false,
            ],
            [
                'id' => 'all_doc',
                'filename' => 'all.pdf',
                'description_hr' => 'All dokument',
                'description_en' => 'All document',
                'date' => '2025-01-01',
                'type' => 'request',
                'profiles' => ['all'],
                'critical' => false,
                'ocr_needed' => false,
            ],
            [
                'id' => 'beta_doc',
                'filename' => 'beta.pdf',
                'description_hr' => 'Beta dokument',
                'description_en' => 'Beta document',
                'date' => '2025-01-03',
                'type' => 'request',
                'profiles' => ['beta'],
                'critical' => false,
                'ocr_needed' => false,
            ],
        ]);
        config()->set('legal-artillery-documents.generated_documents', []);

        File::put($this->caseDocDir . '/alpha.pdf', 'content');

        $inventory = new DocumentInventory();
        $documents = $inventory->forProfile('alpha');

        $this->assertCount(2, $documents);
        $this->assertSame('all_doc', $documents[0]['id']);
        $this->assertSame('alpha_doc', $documents[1]['id']);
        $this->assertFalse($documents[0]['exists']);
        $this->assertNull($documents[0]['path']);
        $this->assertTrue($documents[1]['exists']);
        $this->assertNotNull($documents[1]['path']);
    }

    #[Test]
    public function audit_includes_generated_documents(): void
    {
        config()->set('legal-artillery-documents.documents', []);
        config()->set('legal-artillery-documents.generated_documents', [
            [
                'id' => 'gen_found',
                'filename' => 'generated-found.pdf',
                'description_hr' => 'Generirani pronadjeni',
                'description_en' => 'Generated found',
                'generator' => 'TestGenerator',
                'profiles' => ['ustavni_sud'],
                'auto_generate' => true,
            ],
            [
                'id' => 'gen_missing',
                'filename' => 'generated-missing.pdf',
                'description_hr' => 'Generirani nedostajuci',
                'description_en' => 'Generated missing',
                'generator' => 'TestGenerator',
                'profiles' => ['ustavni_sud'],
                'auto_generate' => true,
            ],
        ]);

        File::put($this->caseDocDir . '/generated-found.pdf', 'content');

        $inventory = new DocumentInventory();
        $audit = $inventory->audit();

        $this->assertCount(1, $audit['found']);
        $this->assertSame('gen_found', $audit['found'][0]['id']);
        $this->assertCount(1, $audit['missing']);
        $this->assertSame('gen_missing', $audit['missing'][0]['id']);
    }

    #[Test]
    public function for_profile_includes_generated_documents(): void
    {
        config()->set('legal-artillery-documents.documents', [
            [
                'id' => 'case_doc',
                'filename' => 'alpha.pdf',
                'description_hr' => 'Alpha dokument',
                'description_en' => 'Alpha document',
                'date' => '2025-01-01',
                'type' => 'request',
                'profiles' => ['ustavni_sud'],
                'critical' => false,
                'ocr_needed' => false,
            ],
        ]);
        config()->set('legal-artillery-documents.generated_documents', [
            [
                'id' => 'gen_doc',
                'filename' => 'generated-found.pdf',
                'description_hr' => 'Kronologija korespondencije',
                'description_en' => 'Correspondence chronology',
                'generator' => 'ChronologyGenerator',
                'profiles' => ['ustavni_sud'],
                'auto_generate' => true,
            ],
            [
                'id' => 'gen_other',
                'filename' => 'other-generated.pdf',
                'description_hr' => 'Drugi generirani',
                'description_en' => 'Other generated',
                'generator' => 'OtherGenerator',
                'profiles' => ['echr_application'],
                'auto_generate' => true,
            ],
        ]);

        File::put($this->caseDocDir . '/alpha.pdf', 'content');
        File::put($this->caseDocDir . '/generated-found.pdf', 'content');

        $inventory = new DocumentInventory();
        $docs = $inventory->forProfile('ustavni_sud');

        // Should include the case doc AND the matching generated doc
        $ids = array_column($docs, 'id');
        $this->assertContains('case_doc', $ids);
        $this->assertContains('gen_doc', $ids);
        // Should NOT include the echr_application-only generated doc
        $this->assertNotContains('gen_other', $ids);
    }

    #[Test]
    public function attachment_list_includes_generated_documents(): void
    {
        config()->set('legal-artillery-documents.documents', []);
        config()->set('legal-artillery-documents.generated_documents', [
            [
                'id' => 'gen_doc',
                'filename' => 'generated-found.pdf',
                'description_hr' => 'Kronologija korespondencije',
                'description_en' => 'Correspondence chronology',
                'generator' => 'ChronologyGenerator',
                'profiles' => ['ustavni_sud'],
                'auto_generate' => true,
            ],
        ]);

        File::put($this->caseDocDir . '/generated-found.pdf', 'content');

        $inventory = new DocumentInventory();
        $list = $inventory->generateAttachmentList('ustavni_sud');

        $this->assertStringContainsString('Kronologija korespondencije', $list);
        $this->assertStringContainsString('Prilog 1:', $list);
    }
}
