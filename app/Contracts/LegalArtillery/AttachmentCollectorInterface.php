<?php

namespace App\Contracts\LegalArtillery;

/**
 * Contract for collecting and generating attachment lists for documents.
 *
 * Implementations should provide access to document attachments
 * and generate formatted attachment sections for legal documents.
 */
interface AttachmentCollectorInterface
{
    /**
     * Get the list of attachments for a profile.
     *
     * @param string $profileKey The document profile key
     * @return array<string> Array of attachment descriptions
     */
    public function getAttachmentsForProfile(string $profileKey): array;

    /**
     * Generate a formatted attachment list section for document inclusion.
     *
     * @param string $profileKey The document profile key
     * @return string Formatted attachment section (PRILOZI)
     */
    public function generateAttachmentListSection(string $profileKey): string;

    /**
     * Check if a profile requires attachments.
     *
     * @param string $profileKey The document profile key
     * @return bool True if profile requires attachments
     */
    public function profileRequiresAttachments(string $profileKey): bool;
}
