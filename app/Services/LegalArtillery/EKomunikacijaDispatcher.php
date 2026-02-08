<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\EKomunikacija\Client as EKomClient;
use App\Services\LegalArtillery\PiiRedactor;
use Illuminate\Support\Facades\Log;

/**
 * Dispatcher for submitting legal documents via e-komunikacija API.
 *
 * Handles payload preparation, court ID resolution, document type mapping,
 * and delegation to the EKomunikacija Client for actual submission.
 */
class EKomunikacijaDispatcher
{
    /**
     * Court ID mapping for e-komunikacija.
     * Maps institution names (as stored in document profiles) to court identifiers.
     */
    private array $courtMapping = [
        'Opcinski sud u Osijeku' => 'OS_OSIJEK',
        'Opcinski sud u Osijeku — kazneni odjel' => 'OS_OSIJEK',
        'Zupanijski sud u Osijeku' => 'ZS_OSIJEK',
        'Ustavni sud RH' => 'USRH',
        'Ustavni sud Republike Hrvatske' => 'USRH',
        'Vrhovni sud Republike Hrvatske' => 'VSRH',
        'Ministarstvo pravosudja i uprave' => 'MPU',
        'ECHR / ESLJP' => 'ECHR',
        'Drzavno odvjetnistvo' => 'DORH',
    ];

    public function __construct(
        protected EKomClient $client,
        protected PiiRedactor $piiRedactor,
    ) {}

    /**
     * Preview the submission payload without sending.
     */
    public function preview(DocumentProfile $profile, CaseContext $context, string $docxPath): array
    {
        $payload = $this->preparePayload($profile, $context, $docxPath);
        $courtId = $this->resolveCourtId($profile);

        return [
            'court_id' => $courtId,
            'document_type' => $this->mapDocumentType($profile->key),
            'payload' => $this->piiRedactor->redactArray($payload),
            'attachment' => basename($docxPath),
            'attachment_path' => $docxPath,
            'attachment_exists' => file_exists($docxPath),
        ];
    }

    /**
     * Submit document to e-komunikacija.
     *
     * Prepares the payload from profile/context/docx, delegates to the client,
     * and returns the result. Catches exceptions to return a structured error response.
     */
    public function submit(
        DocumentProfile $profile,
        CaseContext $context,
        string $docxPath,
        array $additionalAttachments = []
    ): array {
        Log::info('EKomunikacijaDispatcher: Submitting', [
            'profile' => $profile->key,
            'case' => $this->piiRedactor->redact((string) $context->caseNumber),
        ]);

        $payload = $this->preparePayload($profile, $context, $docxPath, $additionalAttachments);

        try {
            $result = $this->client->submitDocument($payload);

            Log::info('EKomunikacijaDispatcher: Submitted', [
                'submission_id' => $result['submission_id'] ?? null,
                'success' => $result['success'] ?? false,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('EKomunikacijaDispatcher: Failed', [
                'error' => $this->piiRedactor->redact($e->getMessage()),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Prepare submission payload from profile, context, and document path.
     *
     * @param DocumentProfile $profile The document profile defining recipient, type, etc.
     * @param CaseContext $context The case context with case number, sender info, etc.
     * @param string $docxPath Path to the main DOCX document to submit.
     * @param array $additionalAttachments Extra attachments, each with 'path' and optional 'filename', 'mime_type'.
     * @return array Structured payload ready for e-komunikacija submission.
     */
    public function preparePayload(
        DocumentProfile $profile,
        CaseContext $context,
        string $docxPath,
        array $additionalAttachments = []
    ): array {
        $courtId = $this->resolveCourtId($profile);
        $documentType = $this->mapDocumentType($profile->key);

        $attachments = [];

        // Main document
        if (file_exists($docxPath)) {
            $attachments[] = [
                'type' => 'main_document',
                'filename' => basename($docxPath),
                'content' => base64_encode(file_get_contents($docxPath)),
                'mime_type' => $this->resolveMimeType($docxPath),
            ];
        }

        // Additional attachments
        foreach ($additionalAttachments as $att) {
            if (isset($att['path']) && file_exists($att['path'])) {
                $attachments[] = [
                    'type' => 'attachment',
                    'filename' => $att['filename'] ?? basename($att['path']),
                    'content' => base64_encode(file_get_contents($att['path'])),
                    'mime_type' => $att['mime_type'] ?? $this->resolveMimeType($att['path']),
                ];
            }
        }

        return [
            'case_number' => $context->caseNumber,
            'court_id' => $courtId,
            'document_type' => $documentType,
            'sender' => [
                'name' => $context->sender->name,
                'oib' => $context->sender->oib,
                'address' => $context->sender->address,
                'email' => $context->sender->email,
            ],
            'subject' => $profile->name,
            'description' => "Podnesak u predmetu {$context->caseNumber}",
            'attachments' => $attachments,
            'metadata' => [
                'generated_by' => 'legal_artillery',
                'profile_key' => $profile->key,
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Resolve court identifier from profile recipient institution.
     */
    protected function resolveCourtId(DocumentProfile $profile): string
    {
        $institution = $profile->recipient['institution'] ?? '';
        return $this->courtMapping[$institution] ?? 'UNKNOWN';
    }

    /**
     * Map document profile key to e-komunikacija document type code.
     */
    protected function mapDocumentType(string $profileKey): string
    {
        $documentTypes = config('legal-artillery.document_type_map', []);

        return $documentTypes[$profileKey] ?? 'PODNESAK';
    }

    /**
     * Check submission status by submission ID.
     */
    public function checkStatus(string $submissionId): array
    {
        return $this->client->getSubmissionStatus($submissionId);
    }

    /**
     * Get submission history for a case number.
     */
    public function getHistory(string $caseNumber): array
    {
        return $this->client->getSubmissionHistory($caseNumber);
    }

    private function resolveMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }
}
