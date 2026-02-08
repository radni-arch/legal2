<?php

namespace App\Services\Ekom;

use App\Clients\EkomApiClientInterface;
use App\DTOs\Ekom\PaginatedResponse;
use App\DTOs\Ekom\Predmeti\PagedPredmetDTO;
use App\DTOs\Ekom\Predmeti\PredmetDTO;
use Illuminate\Support\Facades\Storage;

class PredmetService
{
    public function __construct(
        private readonly EkomApiClientInterface $client
    ) {}

    /**
     * Paginated search with filters.
     *
     * @param array $filters Available: status[], sudId[], vrstaUpisnikaOznaka[], oznaka, strankaProtustrankaOib
     * @param int $page Page number (0-indexed)
     * @param int $size Page size (max 100)
     * @param string|null $sort Sort field,direction (e.g. 'oznaka,asc')
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

        $response = $this->client->listPredmeti($query);

        return PaginatedResponse::fromApiResponse(
            $response,
            fn (array $item) => PagedPredmetDTO::fromApiResponse($item)
        );
    }

    /**
     * Get full predmet detail by ID.
     */
    public function getById(int $id): PredmetDTO
    {
        $response = $this->client->getPredmetById($id);

        return PredmetDTO::fromApiResponse($response);
    }

    /**
     * Direct lookup by court+oznaka.
     *
     * Requires exactly one of: sudId, sud(naziv), sudOznaka
     * And exactly one of: oznaka, predmetOznaka
     *
     * @param array $params ['sudId' => int] or ['sud' => string] or ['sudOznaka' => string]
     *                      AND ['oznaka' => string] or ['predmetOznaka' => string]
     */
    public function getByCourtAndOznaka(array $params): PredmetDTO
    {
        $response = $this->client->getPredmetByParams($params);

        return PredmetDTO::fromApiResponse($response);
    }

    /**
     * Download case documents as ZIP.
     *
     * @param int $id Predmet ID
     * @param array|null $dokumentIds Optional filter by document IDs
     * @param string|null $saveToPath Path to save file (generated if null)
     * @return string Path to saved file
     */
    public function downloadDocuments(int $id, ?array $dokumentIds = null, ?string $saveToPath = null): string
    {
        $path = $saveToPath ?? $this->generateDownloadPath('predmet', $id, 'zip');

        $this->ensureDirectoryExists($path);

        $this->client->downloadDokumentiPredmeta($id, $dokumentIds ?? [], $path);

        return $path;
    }

    /**
     * Get dispatches within a case.
     *
     * @return array Array of dispatch data
     */
    public function getOtpravci(int $predmetId): array
    {
        return $this->client->getOtpravciPredmeta($predmetId);
    }

    /**
     * Download delivery receipt document.
     *
     * @param int $predmetId Predmet ID
     * @param int $otpravakId Otpravak ID within the predmet
     * @param string|null $saveToPath Path to save file (generated if null)
     * @return string Path to saved file
     */
    public function downloadDostavnica(int $predmetId, int $otpravakId, ?string $saveToPath = null): string
    {
        $path = $saveToPath ?? $this->generateDownloadPath('dostavnica', $otpravakId, 'pdf');

        $this->ensureDirectoryExists($path);

        $this->client->downloadDostavnicaOtpravkaPredmeta($predmetId, $otpravakId, $path);

        return $path;
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
