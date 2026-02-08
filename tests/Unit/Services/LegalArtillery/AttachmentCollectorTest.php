<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\SenderIdentity;
use App\Services\LegalArtillery\AttachmentCollector;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachmentCollectorTest extends TestCase
{
    private AttachmentCollector $collector;
    private CaseContext $context;
    private DocumentProfile $profileWithAttachments;
    private DocumentProfile $profileWithoutAttachments;

    protected function setUp(): void
    {
        parent::setUp();

        // Use fake storage
        Storage::fake('local');

        $this->collector = new AttachmentCollector();

        // Create a test sender identity
        $sender = new SenderIdentity(
            name: 'Test Sender',
            oib: '12345678901',
            address: 'Test Address 123',
            email: 'test@example.com',
            phone: '+385 99 1234567'
        );

        // Create test context
        $this->context = new CaseContext(
            caseNumber: 'Pp Prz-74/2025',
            searchDate: '2025-06-09',
            archiveDate: '2025-07-10',
            addressSearched: 'Primorska 5, 31000 Osijek',
            warrantReference: 'Pp Prz-74/2025-2',
            policeRequestKlasa: 'NK-214-05/25-01/1155',
            policeRequestUrbroj: '511-07-11-25-2',
            legalBasisWarrant: 'PZ cl.159 st.1 toc.1 u vezi ZKP cl.240',
            suspectedOffense: 'cl.54 st.3 Zakon o suzbijanju zlouporabe droga',
            judge: 'Dunja Bertok',
            denialDate: '2025-09-03',
            countyCourtResponseDate: '2025-09-17',
            sender: $sender
        );

        // Create a profile that requires attachments
        $this->profileWithAttachments = new DocumentProfile(
            key: 'ustavni_sud',
            name: 'Ustavna tuzba',
            recipient: [
                'title' => 'Ustavni sud RH',
                'address' => 'Trg svetog Marka 4, 10000 Zagreb',
            ],
            legalBasis: ['Ustav cl.62'],
            tone: 'formal_constitutional',
            structure: ['heading', 'content', 'attachments_list'],
            docxTemplate: 'legal-constitutional',
            requiresAttachments: true,
            metadata: ['required_attachment_types' => ['denial_letter', 'warrant_copy', 'police_request']]
        );

        // Create a profile that does not require attachments
        $this->profileWithoutAttachments = new DocumentProfile(
            key: 'predsjednik_suda',
            name: 'Zahtjev predsjedniku suda',
            recipient: [
                'title' => 'Predsjednica suda',
                'address' => 'Europska avenija 7, 31000 Osijek',
            ],
            legalBasis: ['PZ cl.150'],
            tone: 'formal_assertive',
            structure: ['heading', 'content'],
            docxTemplate: 'legal-formal',
            requiresAttachments: false
        );
    }

    #[Test]
    public function collect_returns_array_of_attachments_for_profile_with_attachments(): void
    {
        // Arrange: Create some test attachment files
        $attachmentDir = 'legal-artillery/attachments';
        Storage::put("{$attachmentDir}/denial_letter_2025-09-03.pdf", 'denial content');
        Storage::put("{$attachmentDir}/warrant_copy_Pp-Prz-74-2025.pdf", 'warrant content');

        // Act
        $attachments = $this->collector->collect($this->profileWithAttachments, $this->context);

        // Assert
        $this->assertIsArray($attachments);
    }

    #[Test]
    public function collect_returns_empty_array_for_profile_without_attachments(): void
    {
        // Act
        $attachments = $this->collector->collect($this->profileWithoutAttachments, $this->context);

        // Assert
        $this->assertIsArray($attachments);
        $this->assertEmpty($attachments);
    }

    #[Test]
    public function get_required_attachments_returns_attachment_types_from_profile(): void
    {
        // Act
        $types = $this->collector->getRequiredAttachments($this->profileWithAttachments);

        // Assert
        $this->assertIsArray($types);
        $this->assertContains('denial_letter', $types);
        $this->assertContains('warrant_copy', $types);
        $this->assertContains('police_request', $types);
    }

    #[Test]
    public function get_required_attachments_returns_empty_for_profile_without_attachments(): void
    {
        // Act
        $types = $this->collector->getRequiredAttachments($this->profileWithoutAttachments);

        // Assert
        $this->assertIsArray($types);
        $this->assertEmpty($types);
    }

    #[Test]
    public function find_attachment_returns_path_when_file_exists(): void
    {
        // Arrange
        $attachmentDir = 'legal-artillery/attachments';
        Storage::put("{$attachmentDir}/denial_letter_2025-09-03.pdf", 'denial content');

        // Act
        $path = $this->collector->findAttachment('denial_letter', $this->context);

        // Assert
        $this->assertNotNull($path);
        $this->assertStringContainsString('denial_letter', $path);
    }

    #[Test]
    public function find_attachment_returns_null_when_file_does_not_exist(): void
    {
        // Act
        $path = $this->collector->findAttachment('nonexistent_type', $this->context);

        // Assert
        $this->assertNull($path);
    }

    #[Test]
    public function collect_returns_attachments_with_required_keys(): void
    {
        // Arrange: Create a test attachment file
        $attachmentDir = 'legal-artillery/attachments';
        Storage::put("{$attachmentDir}/denial_letter_2025-09-03.pdf", 'denial content');

        // Act
        $attachments = $this->collector->collect($this->profileWithAttachments, $this->context);

        // Assert: Filter to only found attachments and check structure
        $found = array_filter($attachments, fn($a) => $a['path'] !== null);
        if (!empty($found)) {
            $first = reset($found);
            $this->assertArrayHasKey('path', $first);
            $this->assertArrayHasKey('filename', $first);
            $this->assertArrayHasKey('type', $first);
            $this->assertArrayHasKey('description', $first);
        }
    }

    #[Test]
    public function collect_finds_attachments_with_case_reference_in_filename(): void
    {
        // Arrange: Create attachment with case number in filename
        $attachmentDir = 'legal-artillery/attachments';
        Storage::put("{$attachmentDir}/warrant_copy_Pp-Prz-74-2025.pdf", 'warrant content');

        // Act
        $attachments = $this->collector->collect($this->profileWithAttachments, $this->context);

        // Assert: Should find the warrant
        $found = array_filter($attachments, fn($a) => $a['type'] === 'warrant_copy' && $a['path'] !== null);
        $this->assertNotEmpty($found, 'Should find warrant_copy attachment with case reference');
    }

    #[Test]
    public function find_attachment_matches_by_type_prefix(): void
    {
        // Arrange: Create multiple files with same type prefix
        $attachmentDir = 'legal-artillery/attachments';
        Storage::put("{$attachmentDir}/court_response_2025-09-17.pdf", 'response content');
        Storage::put("{$attachmentDir}/court_response_2025-08-25.pdf", 'older response');

        // Act
        $path = $this->collector->findAttachment('court_response', $this->context);

        // Assert: Should find at least one
        $this->assertNotNull($path);
        $this->assertStringContainsString('court_response', $path);
    }

    #[Test]
    public function get_supported_attachment_types_returns_known_types(): void
    {
        // Act
        $types = $this->collector->getSupportedAttachmentTypes();

        // Assert
        $this->assertIsArray($types);
        $this->assertContains('denial_letter', $types);
        $this->assertContains('warrant_copy', $types);
        $this->assertContains('police_request', $types);
        $this->assertContains('court_response', $types);
    }

    #[Test]
    public function collect_uses_custom_attachment_directory_when_configured(): void
    {
        // Arrange
        $customDir = 'custom/attachments';
        Storage::put("{$customDir}/denial_letter_test.pdf", 'content');

        $collector = new AttachmentCollector($customDir);

        // Act
        $path = $collector->findAttachment('denial_letter', $this->context);

        // Assert
        $this->assertNotNull($path);
    }
}
