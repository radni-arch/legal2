<?php

namespace App\Services\LegalArtillery;

use App\Contracts\LegalArtillery\AttachmentCollectorInterface;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use Illuminate\Support\Facades\Storage;

/**
 * Service for collecting and managing document attachments.
 *
 * Scans the attachment directory for files matching required types,
 * and provides methods for finding specific attachments by type.
 */
class AttachmentCollector implements AttachmentCollectorInterface
{
    /**
     * Default attachment directory relative to storage/app/
     */
    private const DEFAULT_ATTACHMENT_DIR = 'legal-artillery/attachments';

    /**
     * Supported attachment types with their descriptions.
     */
    private const ATTACHMENT_TYPES = [
        'denial_letter' => 'Rjesenje o odbijanju uvida u spis',
        'warrant_copy' => 'Preslika naredbe za pretragu',
        'police_request' => 'Zahtjev policije',
        'court_response' => 'Odgovor suda',
        'access_request' => 'Zahtjev za uvid u spis',
        'complaint_filed' => 'Podnesena prituzba',
        'appeal' => 'Zalba',
        'evidence_exhibit' => 'Dokazni prilog',
        'witness_statement' => 'Izjava svjedoka',
        'expert_opinion' => 'Strucno misljenje',
    ];

    private string $attachmentDirectory;

    /**
     * Create a new AttachmentCollector instance.
     *
     * @param string|null $attachmentDirectory Custom attachment directory (relative to storage/app/)
     */
    public function __construct(?string $attachmentDirectory = null)
    {
        $this->attachmentDirectory = $attachmentDirectory ?? self::DEFAULT_ATTACHMENT_DIR;
    }

    /**
     * Collect all attachments for a document profile.
     *
     * Returns an array of attachment info for each required type,
     * with the path set to the found file or null if not found.
     *
     * @param DocumentProfile $profile The document profile to collect attachments for
     * @param CaseContext $context The case context for matching files
     * @return array<int, array{path: ?string, filename: ?string, type: string, description: string}>
     */
    public function collect(DocumentProfile $profile, CaseContext $context): array
    {
        // If profile doesn't require attachments, return empty array
        if (!$profile->requiresAttachments) {
            return [];
        }

        $requiredTypes = $this->getRequiredAttachments($profile);
        $attachments = [];

        foreach ($requiredTypes as $type) {
            $path = $this->findAttachment($type, $context);
            $attachments[] = [
                'path' => $path,
                'filename' => $path !== null ? basename($path) : null,
                'type' => $type,
                'description' => self::ATTACHMENT_TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type)),
            ];
        }

        return $attachments;
    }

    /**
     * Get the list of required attachment types for a profile.
     *
     * Looks for 'required_attachment_types' in the profile metadata,
     * or returns an empty array if not specified.
     *
     * @param DocumentProfile $profile The document profile
     * @return array<int, string> List of required attachment type names
     */
    public function getRequiredAttachments(DocumentProfile $profile): array
    {
        // If profile doesn't require attachments, return empty array
        if (!$profile->requiresAttachments) {
            return [];
        }

        // Look for explicit required types in metadata
        return $profile->metadata['required_attachment_types'] ?? [];
    }

    /**
     * Find a specific attachment by type.
     *
     * Searches the attachment directory for files matching the given type.
     * Files are matched by prefix (e.g., 'denial_letter' matches 'denial_letter_2025-09-03.pdf').
     * Also considers case reference in filename for more specific matching.
     *
     * @param string $type The attachment type to find
     * @param CaseContext $context The case context for matching
     * @return string|null The full path to the found file, or null if not found
     */
    public function findAttachment(string $type, CaseContext $context): ?string
    {
        $files = $this->getAttachmentFiles();

        // First try to find a file with both type and case reference
        $caseRef = $this->normalizeCaseReference($context->caseNumber);
        foreach ($files as $file) {
            $filename = basename($file);
            if (str_starts_with($filename, $type . '_') && str_contains($filename, $caseRef)) {
                return Storage::path($file);
            }
        }

        // Fall back to matching just by type prefix
        foreach ($files as $file) {
            $filename = basename($file);
            if (str_starts_with($filename, $type . '_') || str_starts_with($filename, $type . '.')) {
                return Storage::path($file);
            }
        }

        return null;
    }

    /**
     * Get all supported attachment types.
     *
     * @return array<int, string> List of supported type names
     */
    public function getSupportedAttachmentTypes(): array
    {
        return array_keys(self::ATTACHMENT_TYPES);
    }

    /**
     * Get the description for an attachment type.
     *
     * @param string $type The attachment type
     * @return string The description, or the type name if not found
     */
    public function getAttachmentDescription(string $type): string
    {
        return self::ATTACHMENT_TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Get all files in the attachment directory.
     *
     * @return array<int, string> List of file paths relative to storage/app/
     */
    private function getAttachmentFiles(): array
    {
        if (!Storage::exists($this->attachmentDirectory)) {
            return [];
        }

        return Storage::files($this->attachmentDirectory);
    }

    /**
     * Normalize a case reference for filename matching.
     *
     * Converts 'Pp Prz-74/2025' to 'Pp-Prz-74-2025' for filename matching.
     *
     * @param string $caseNumber The case number to normalize
     * @return string The normalized case reference
     */
    private function normalizeCaseReference(string $caseNumber): string
    {
        // Replace spaces and slashes with dashes
        return str_replace([' ', '/'], '-', $caseNumber);
    }

    /**
     * Generate a formatted attachment list section for a profile.
     *
     * Creates a numbered list of attachments appropriate for the given profile key,
     * suitable for including in document generation prompts.
     *
     * @param string $profileKey The profile key (e.g., 'predsjednik_suda', 'ombudsman')
     * @return string The formatted attachment list section, or empty string if no attachments
     */
    public function generateAttachmentListSection(string $profileKey): string
    {
        // Define standard attachments per profile type
        $profileAttachments = [
            'predsjednik_suda' => [
                'Preslika osobne iskaznice',
            ],
            'dorh_production' => [
                'Preslika zahtjeva za uvid u spis',
                'Preslika odgovora suda (ako postoji)',
            ],
            'kazneni_sud_motion' => [
                'Preslika poziva za pretragu',
            ],
            'ombudsman' => [
                'Preslika zahtjeva za uvid u spis',
                'Preslika odgovora suda',
                'Preslika naredbe za pretragu',
                'Preslika osobne iskaznice',
            ],
            'ministarstvo_pravosudja' => [
                'Preslika zahtjeva za uvid u spis',
                'Preslika odgovora suda',
                'Preslika prituzbe predsjedniku suda (ako podnesena)',
            ],
            'ustavni_sud' => [
                'Preslika pobijanih akata',
                'Preslika zahtjeva za uvid u spis',
                'Preslika odgovora suda',
                'Preslika osobne iskaznice',
            ],
            'izdvajanje_dokaza' => [
                'Preslika naredbe za pretragu',
                'Preslika zapisnika o pretrazi (ako dostupan)',
            ],
            'echr_application' => [
                'Copy of the final domestic decision',
                'Copy of all relevant court decisions',
                'Copy of the applicant\'s identity document',
                'Copy of the search warrant',
                'Copy of the file access denial (if formal)',
            ],
        ];

        $attachments = $profileAttachments[$profileKey] ?? [];

        if (empty($attachments)) {
            return '';
        }

        $lines = [];
        foreach ($attachments as $index => $attachment) {
            $lines[] = ($index + 1) . '. ' . $attachment;
        }

        return implode("\n", $lines);
    }

    /**
     * Get list of attachment descriptions for a profile.
     *
     * @param string $profileKey The document profile key
     * @return array<string> Array of attachment descriptions
     */
    public function getAttachmentsForProfile(string $profileKey): array
    {
        $profileAttachments = [
            'predsjednik_suda' => [
                'Preslika osobne iskaznice',
            ],
            'dorh_production' => [
                'Preslika zahtjeva za uvid u spis',
                'Preslika odgovora suda (ako postoji)',
            ],
            'kazneni_sud_motion' => [
                'Preslika poziva za pretragu',
            ],
            'ombudsman' => [
                'Preslika zahtjeva za uvid u spis',
                'Preslika odgovora suda',
                'Preslika naredbe za pretragu',
                'Preslika osobne iskaznice',
            ],
            'ministarstvo_pravosudja' => [
                'Preslika zahtjeva za uvid u spis',
                'Preslika odgovora suda',
                'Preslika prituzbe predsjedniku suda (ako podnesena)',
            ],
            'ustavni_sud' => [
                'Preslika pobijanih akata',
                'Preslika zahtjeva za uvid u spis',
                'Preslika odgovora suda',
                'Preslika osobne iskaznice',
            ],
            'izdvajanje_dokaza' => [
                'Preslika naredbe za pretragu',
                'Preslika zapisnika o pretrazi (ako dostupan)',
            ],
            'echr_application' => [
                'Copy of the final domestic decision',
                'Copy of all relevant court decisions',
                'Copy of the applicant\'s identity document',
                'Copy of the search warrant',
                'Copy of the file access denial (if formal)',
            ],
        ];

        return $profileAttachments[$profileKey] ?? [];
    }

    /**
     * Check if a profile requires attachments.
     *
     * @param string $profileKey The document profile key
     * @return bool True if profile requires attachments
     */
    public function profileRequiresAttachments(string $profileKey): bool
    {
        return (bool) data_get(
            config('legal-artillery.profiles', []),
            "{$profileKey}.requires_attachments",
            false
        );
    }
}
