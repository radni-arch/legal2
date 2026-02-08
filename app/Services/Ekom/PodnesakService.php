<?php

namespace App\Services\Ekom;

use App\Clients\EkomApiClientInterface;
use App\DTOs\Ekom\PaginatedResponse;
use App\DTOs\Ekom\Podnesci\EkomPodnesakDTO;
use App\DTOs\Ekom\Podnesci\PagedPodnesakDTO;
use App\DTOs\Ekom\Podnesci\PristojbaDTO;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PodnesakService
{
    public function __construct(
        private readonly EkomApiClientInterface $client
    ) {}

    /**
     * Paginated list of submissions.
     *
     * @param array $filters Available filters from API
     * @param int $page Page number (0-indexed)
     * @param int $size Page size (max 100)
     * @param string|null $sort Sort field,direction
     */
    public function list(array $filters = [], int $page = 0, int $size = 50, ?string $sort = null): PaginatedResponse
    {
        $query = array_merge($filters, [
            'page' => $page,
            'size' => min($size, 100),
        ]);

        if ($sort !== null) {
            $query['sort'] = $sort;
        }

        $response = $this->client->listPodnesci($query);

        return PaginatedResponse::fromApiResponse(
            $response,
            fn (array $item) => PagedPodnesakDTO::fromApiResponse($item)
        );
    }

    /**
     * Get submission detail by ID.
     */
    public function getById(int $id): EkomPodnesakDTO
    {
        $response = $this->client->getPodnesak($id);

        return EkomPodnesakDTO::fromApiResponse($response);
    }

    /**
     * Create a new submission draft.
     *
     * Use CreateEkomPodnesakRequestBuilder for easier request construction.
     *
     * @param array $payload The full creation request payload
     * @return EkomPodnesakDTO The created draft
     */
    public function createDraft(array $payload): EkomPodnesakDTO
    {
        Log::info('Creating e-Komunikacija submission draft', [
            'court_id' => $payload['sudId'] ?? $payload['sudOznaka'] ?? null,
            'type_id' => $payload['vrstaPodneskaId'] ?? null,
            'user_id' => auth()->id(),
        ]);

        $response = $this->client->createPodnesak($payload);

        Log::info('Submission draft created', [
            'podnesak_id' => $response['id'] ?? null,
        ]);

        return EkomPodnesakDTO::fromApiResponse($response);
    }

    /**
     * Add an attachment to a draft submission.
     *
     * @param int $id Podnesak ID
     * @param array $prilogPayload The attachment request (opis, primjedba, sadrzaj)
     * @return array The attachment response
     */
    public function addAttachment(int $id, array $prilogPayload): array
    {
        Log::info('Adding attachment to submission', [
            'podnesak_id' => $id,
            'filename' => $prilogPayload['sadrzaj']['naziv'] ?? null,
        ]);

        return $this->client->createPrilog($id, $prilogPayload);
    }

    /**
     * Delete a draft submission.
     *
     * Only drafts (NACRT status) can be deleted.
     *
     * @param int $id Podnesak ID
     * @throws \Exception if deletion fails or submission is not a draft
     */
    public function delete(int $id): void
    {
        Log::info('Deleting submission draft', [
            'podnesak_id' => $id,
            'user_id' => auth()->id(),
        ]);

        $this->client->deletePodnesak($id);

        Log::info('Submission draft deleted', [
            'podnesak_id' => $id,
        ]);
    }

    /**
     * Get fee calculation for a submission.
     */
    public function getPristojba(int $id): PristojbaDTO
    {
        $response = $this->client->getPristojbaPodneska($id);

        return PristojbaDTO::fromApiResponse($response);
    }

    /**
     * Download payment order PDF.
     *
     * @param int $id Podnesak ID
     * @param string|null $saveToPath Path to save file (generated if null)
     * @return string Path to saved file
     */
    public function downloadNalogZaPlacanje(int $id, ?string $saveToPath = null): string
    {
        $path = $saveToPath ?? $this->generateDownloadPath('nalog-za-placanje', $id, 'pdf');

        $this->ensureDirectoryExists($path);

        $this->client->downloadNalogZaPlacanjePodneska($id, $path);

        return $path;
    }

    /**
     * Download proof of payment/exemption.
     *
     * @param int $id Podnesak ID
     * @param string|null $saveToPath Path to save file (generated if null)
     * @return string Path to saved file
     */
    public function downloadDokazUplate(int $id, ?string $saveToPath = null): string
    {
        $path = $saveToPath ?? $this->generateDownloadPath('dokaz-uplate', $id, 'pdf');

        $this->ensureDirectoryExists($path);

        $this->client->downloadDokazUplatePodneska($id, $path);

        return $path;
    }

    /**
     * Send submission to court (LEGALLY IRREVERSIBLE ACTION!).
     *
     * Pre-flight validation should be performed before calling this.
     * If fee unpaid (ostatak > 0) and no 100% exemption, razlogNeplacanjaId is required.
     *
     * @param int $id Podnesak ID
     * @param int|null $razlogNeplacanjaId Reason for non-payment (if applicable)
     * @throws \InvalidArgumentException if razlogNeplacanjaId required but not provided
     * @throws \Exception if send fails
     */
    public function sendToCourt(int $id, ?int $razlogNeplacanjaId = null): void
    {
        // Fetch current state to determine if razlog is needed
        $podnesak = $this->getById($id);
        $needsRazlog = $this->requiresNonPaymentReason($podnesak);

        if ($needsRazlog && $razlogNeplacanjaId === null) {
            throw new InvalidArgumentException('razlogNeplacanjaId required for unpaid fee');
        }

        Log::warning('Sending submission to court - IRREVERSIBLE ACTION', [
            'podnesak_id' => $id,
            'razlog_neplacanja_id' => $razlogNeplacanjaId,
            'user_id' => $this->getCurrentUserId(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Build payload: only include razlog if needed and provided
        $payload = $needsRazlog && $razlogNeplacanjaId !== null
            ? ['razlogNeplacanjaId' => $razlogNeplacanjaId]
            : [];

        $this->client->posaljiPodnesakNaSud($id, $payload);

        Log::info('Submission sent to court successfully', [
            'podnesak_id' => $id,
        ]);
    }

    /**
     * Determine if a non-payment reason is required for this submission.
     *
     * Required when:
     * - Fee exists (pristojba is not null)
     * - Outstanding balance > 0 (ostatak > 0)
     * - Not fully exempt (postotakOslobodjenja < 100)
     */
    private function requiresNonPaymentReason(EkomPodnesakDTO $podnesak): bool
    {
        if (! $podnesak->pristojba) {
            return false;
        }

        if ($podnesak->pristojba->ostatak === null || $podnesak->pristojba->ostatak <= 0) {
            return false;
        }

        if ($podnesak->pristojba->postotakOslobodjenja !== null && $podnesak->pristojba->postotakOslobodjenja >= 100) {
            return false;
        }

        return true;
    }

    /**
     * Get the current authenticated user ID (null-safe for unit tests).
     */
    private function getCurrentUserId(): ?int
    {
        if (! function_exists('auth')) {
            return null;
        }

        try {
            return auth()->id();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Download receipt notification from court (after acceptance).
     *
     * Only available for POSLAN status submissions.
     *
     * @param int $id Podnesak ID
     * @param string|null $saveToPath Path to save file (generated if null)
     * @return string Path to saved file
     */
    public function downloadObavijestOPrimitku(int $id, ?string $saveToPath = null): string
    {
        $path = $saveToPath ?? $this->generateDownloadPath('obavijest-o-primitku', $id, 'pdf');

        $this->ensureDirectoryExists($path);

        $this->client->downloadObavijestOPrimitkuPodneska($id, $path);

        return $path;
    }

    /**
     * Validate submission is ready for sending.
     *
     * @return array<string> List of validation errors, empty if valid
     */
    public function validateForSending(EkomPodnesakDTO $podnesak): array
    {
        $errors = [];

        if ($podnesak->status !== 'NACRT') {
            $errors[] = 'Submission is not in draft status';
        }

        if (empty($podnesak->sadrzaj)) {
            $errors[] = 'Submission has no content';
        }

        // Digital signature validation (S5-7)
        if ($podnesak->statusValidacijePotpisa !== 'PRIHVATLJIV') {
            $errors[] = 'Document must have valid digital signature';
        }

        // Fee validation
        if ($podnesak->pristojba) {
            $pristojba = $podnesak->pristojba;
            if ($pristojba->ostatak > 0 && $pristojba->postotakOslobodjenja < 100) {
                $errors[] = 'Fee payment required or exemption reason needed';
            }
        }

        return $errors;
    }

    /**
     * Generate a download path under storage.
     */
    private function generateDownloadPath(string $type, int $id, string $extension): string
    {
        $date = now()->format('Y-m-d');

        return storage_path("app/ekom/downloads/{$date}/{$type}-{$id}.{$extension}");
    }

    /**
     * Ensure the directory for a file path exists.
     */
    private function ensureDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
