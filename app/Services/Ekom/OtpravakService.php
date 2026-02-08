<?php

namespace App\Services\Ekom;

use App\Clients\EkomApiClientInterface;
use App\DTOs\Ekom\Otpravci\OtpravakDTO;
use App\DTOs\Ekom\Otpravci\PagedOtpravakDTO;
use App\DTOs\Ekom\PaginatedResponse;
use App\DTOs\Ekom\Predmeti\PredmetDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OtpravakService
{
    public function __construct(
        private readonly EkomApiClientInterface $client
    ) {}

    /**
     * Paginated search for dispatches.
     *
     * @param array $filters Available: status, sudId[], vrstaUpisnikaOznaka[], datumSlanjaSaSudaOd, datumSlanjaSaSudaDo,
     *                       datumPotvrdePrimitkaOd, datumPotvrdePrimitkaDo, samoPrimljeniZbogIstekaRoka
     * @param int $page Page number (0-indexed)
     * @param int $size Page size (max 100)
     * @param string|null $sort Sort field,direction (e.g. 'datumSlanjaSaSuda,desc')
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

        $response = $this->client->listOtpravci($query);

        return PaginatedResponse::fromApiResponse(
            $response,
            fn (array $item) => PagedOtpravakDTO::fromApiResponse($item)
        );
    }

    /**
     * Get dispatch detail by ID.
     *
     * Note: API returns full Predmet schema, not just Otpravak.
     */
    public function getById(int $id): PredmetDTO
    {
        $response = $this->client->getOtpravakById($id);

        return PredmetDTO::fromApiResponse($response);
    }

    /**
     * Download dispatch documents as ZIP.
     *
     * @param int $id Otpravak ID
     * @param array|null $dokumentIds Optional filter by document IDs
     * @param string|null $saveToPath Path to save file (generated if null)
     * @return string Path to saved file
     */
    public function downloadDocuments(int $id, ?array $dokumentIds = null, ?string $saveToPath = null): string
    {
        $path = $saveToPath ?? $this->generateDownloadPath('otpravak', $id, 'zip');

        $this->ensureDirectoryExists($path);

        $this->client->downloadDokumentiOtpravka($id, $dokumentIds ?? [], $path);

        return $path;
    }

    /**
     * Download receipt confirmation PDF.
     *
     * @param int $id Otpravak ID
     * @param string|null $saveToPath Path to save file (generated if null)
     * @return string Path to saved file
     */
    public function downloadPotvrdaPrimitka(int $id, ?string $saveToPath = null): string
    {
        $path = $saveToPath ?? $this->generateDownloadPath('potvrda-primitka', $id, 'pdf');

        $this->ensureDirectoryExists($path);

        $this->client->downloadPotvrdaPrimitkaOtpravka($id, $path);

        return $path;
    }

    /**
     * Confirm receipt of dispatch (legally significant action!).
     *
     * This confirms receipt per ZPP čl. 143.c, ZUS čl. 110, ZKP čl. 172.a.
     * After zadnjiTrenutakZaPotvrduPrimitka, dispatch is considered received automatically.
     *
     * @param int $id Otpravak ID
     * @throws \Exception if confirmation fails
     */
    public function potvrdiPrimitak(int $id): void
    {
        Log::info('Confirming receipt of otpravak', [
            'otpravak_id' => $id,
            'user_id' => auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->client->potvrdiPrimitakOtpravka($id);

        Log::info('Otpravak receipt confirmed', [
            'otpravak_id' => $id,
        ]);
    }

    /**
     * Get dispatches approaching deadline.
     *
     * @param int $hoursBeforeDeadline Hours before deadline to consider "approaching"
     * @return array<PagedOtpravakDTO> Dispatches with approaching deadlines
     */
    public function getApproachingDeadlines(int $hoursBeforeDeadline = 24): array
    {
        $now = now();
        $cutoff = $now->copy()->addHours($hoursBeforeDeadline);

        // Fetch unconfirmed dispatches
        $response = $this->list([
            'status' => 'U_DOSTAVI',
        ], page: 0, size: 100);

        $approaching = [];

        foreach ($response->content as $dto) {
            if ($dto instanceof PagedOtpravakDTO && $dto->zadnjiTrenutakZaPotvrduPrimitka) {
                $deadline = Carbon::parse($dto->zadnjiTrenutakZaPotvrduPrimitka);
                if ($deadline->lessThanOrEqualTo($cutoff) && $deadline->greaterThan($now)) {
                    $approaching[] = $dto;
                }
            }
        }

        return $approaching;
    }

    /**
     * Check if a dispatch deadline has passed.
     */
    public function isDeadlinePassed(PagedOtpravakDTO $otpravak): bool
    {
        if (! $otpravak->zadnjiTrenutakZaPotvrduPrimitka) {
            return false;
        }

        return Carbon::parse($otpravak->zadnjiTrenutakZaPotvrduPrimitka)->isPast();
    }

    /**
     * Calculate time remaining until deadline.
     *
     * @return \DateInterval|null Null if no deadline or already passed
     */
    public function getTimeUntilDeadline(PagedOtpravakDTO $otpravak): ?\DateInterval
    {
        if (! $otpravak->zadnjiTrenutakZaPotvrduPrimitka) {
            return null;
        }

        $deadline = Carbon::parse($otpravak->zadnjiTrenutakZaPotvrduPrimitka);
        if ($deadline->isPast()) {
            return null;
        }

        return now()->diff($deadline);
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
