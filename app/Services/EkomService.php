<?php

namespace App\Services;

use App\Clients\EkomApiClientInterface;
use App\Contracts\External\EkomServiceInterface;
use App\Exceptions\EkomApiException;
use App\Repositories\EkomOtpravakRepository;
use App\Repositories\EkomPodnesakRepository;
use App\Repositories\EkomPredmetRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EkomService implements EkomServiceInterface
{
    public function __construct(
        private readonly EkomApiClientInterface $client,
        private readonly EkomPredmetRepository $predmetRepo,
        private readonly EkomPodnesakRepository $podnesakRepo,
        private readonly EkomOtpravakRepository $otpravakRepo,
    ) {}

    // ---------- Sync operations ----------

    /**
     * Sync predmeti (cases) from EKOM API
     *
     * @param  array  $filters  Query filters
     * @param  int  $maxPages  Maximum pages to fetch
     * @param  int|null  $pageSize  Page size (defaults to config)
     * @return int Number of records saved
     *
     * @throws EkomApiException if sync fails
     */
    public function syncPredmeti(array $filters = [], int $maxPages = 1, ?int $pageSize = null): int
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        try {
            if ($maxPages < 1) {
                throw new \InvalidArgumentException('Max pages must be at least 1');
            }

            if ($pageSize !== null && $pageSize < 1) {
                throw new \InvalidArgumentException('Page size must be at least 1');
            }

            Log::info('EKOM predmeti sync initiated', [
                'filters' => $filters,
                'max_pages' => $maxPages,
                'user_id' => auth()->id(),
            ]);

            $page = 0;
            $saved = 0;
            $failed = 0;
            $size = $pageSize ?? (int) config('ekom.default_page_size', 50);

            do {
                $query = array_merge($filters, ['page' => $page, 'size' => $size]);
                $res = $this->fetchPredmetiPage($query);

                $content = $res['content'] ?? [];
                if (! is_array($content)) {
                    Log::warning('Invalid content format in predmeti response', ['type' => gettype($content)]);
                    break;
                }

                foreach ($content as $item) {
                    if (! is_array($item)) {
                        Log::debug('Skipping non-array predmet item');

                        continue;
                    }

                    try {
                        $this->predmetRepo->upsertFromApi($item);
                        $saved++;
                    } catch (\Exception $e) {
                        $failed++;
                        Log::warning('Failed to upsert predmet', [
                            'item_id' => $item['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $last = (bool) ($res['last'] ?? true);
                $page++;
            } while (! $last && $page <= $maxPages);

            Log::info('EKOM predmeti sync completed', [
                'saved' => $saved,
                'failed' => $failed,
                'pages' => $page,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $saved;

        } catch (EkomApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('EKOM predmeti sync failed', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Predmeti sync failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Sync podnesci (submissions) from EKOM API
     *
     * @param  array  $filters  Query filters
     * @param  int  $maxPages  Maximum pages to fetch
     * @param  int|null  $pageSize  Page size (defaults to config)
     * @return int Number of records saved
     *
     * @throws EkomApiException if sync fails
     */
    public function syncPodnesci(array $filters = [], int $maxPages = 1, ?int $pageSize = null): int
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        try {
            if ($maxPages < 1) {
                throw new \InvalidArgumentException('Max pages must be at least 1');
            }

            if ($pageSize !== null && $pageSize < 1) {
                throw new \InvalidArgumentException('Page size must be at least 1');
            }

            Log::info('EKOM podnesci sync initiated', [
                'filters' => $filters,
                'max_pages' => $maxPages,
                'user_id' => auth()->id(),
            ]);

            $page = 0;
            $saved = 0;
            $failed = 0;
            $size = $pageSize ?? (int) config('ekom.default_page_size', 50);

            do {
                $query = array_merge($filters, ['page' => $page, 'size' => $size]);
                $res = $this->fetchPodnesciPage($query);

                $content = $res['content'] ?? [];
                if (! is_array($content)) {
                    Log::warning('Invalid content format in podnesci response', ['type' => gettype($content)]);
                    break;
                }

                foreach ($content as $item) {
                    if (! is_array($item)) {
                        Log::debug('Skipping non-array podnesak item');

                        continue;
                    }

                    try {
                        $this->podnesakRepo->upsertFromPagedApi($item);
                        $saved++;
                    } catch (\Exception $e) {
                        $failed++;
                        Log::warning('Failed to upsert podnesak', [
                            'item_id' => $item['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $last = (bool) ($res['last'] ?? true);
                $page++;
            } while (! $last && $page <= $maxPages);

            Log::info('EKOM podnesci sync completed', [
                'saved' => $saved,
                'failed' => $failed,
                'pages' => $page,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $saved;

        } catch (EkomApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('EKOM podnesci sync failed', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Podnesci sync failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Sync otpravci (dispatches) from EKOM API
     *
     * @param  array  $filters  Query filters
     * @param  int  $maxPages  Maximum pages to fetch
     * @param  int|null  $pageSize  Page size (defaults to config)
     * @return int Number of records saved
     *
     * @throws EkomApiException if sync fails
     */
    public function syncOtpravci(array $filters = [], int $maxPages = 1, ?int $pageSize = null): int
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        try {
            if ($maxPages < 1) {
                throw new \InvalidArgumentException('Max pages must be at least 1');
            }

            if ($pageSize !== null && $pageSize < 1) {
                throw new \InvalidArgumentException('Page size must be at least 1');
            }

            Log::info('EKOM otpravci sync initiated', [
                'filters' => $filters,
                'max_pages' => $maxPages,
                'user_id' => auth()->id(),
            ]);

            $page = 0;
            $saved = 0;
            $failed = 0;
            $size = $pageSize ?? (int) config('ekom.default_page_size', 50);

            do {
                $query = array_merge($filters, ['page' => $page, 'size' => $size]);
                $res = $this->fetchOtpravciPage($query);

                $content = $res['content'] ?? [];
                if (! is_array($content)) {
                    Log::warning('Invalid content format in otpravci response', ['type' => gettype($content)]);
                    break;
                }

                foreach ($content as $item) {
                    if (! is_array($item)) {
                        Log::debug('Skipping non-array otpravak item');

                        continue;
                    }

                    try {
                        $this->otpravakRepo->upsertFromPagedApi($item);
                        $saved++;
                    } catch (\Exception $e) {
                        $failed++;
                        Log::warning('Failed to upsert otpravak', [
                            'item_id' => $item['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $last = (bool) ($res['last'] ?? true);
                $page++;
            } while (! $last && $page <= $maxPages);

            Log::info('EKOM otpravci sync completed', [
                'saved' => $saved,
                'failed' => $failed,
                'pages' => $page,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $saved;

        } catch (EkomApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('EKOM otpravci sync failed', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Otpravci sync failed: '.$e->getMessage()
            );
        }
    }

    // ---------- Shortcuts to client methods for commands/controllers ----------

    /**
     * Turn on Do Not Disturb for a specific predmet
     *
     * @throws EkomApiException if operation fails
     */
    public function turnOnDndPredmet(int $predmetId): bool
    {
        try {
            Log::info('Turning on DND for predmet', ['predmet_id' => $predmetId]);

            return $this->client->turnOnDoNotDisturbPredmet($predmetId);

        } catch (\Exception $e) {
            Log::error('Failed to turn on DND for predmet', [
                'predmet_id' => $predmetId,
                'error' => $e->getMessage(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Failed to turn on DND: '.$e->getMessage()
            );
        }
    }

    /**
     * Turn off Do Not Disturb for a specific predmet
     *
     * @throws EkomApiException if operation fails
     */
    public function turnOffDndPredmet(int $predmetId): bool
    {
        try {
            Log::info('Turning off DND for predmet', ['predmet_id' => $predmetId]);

            return $this->client->turnOffDoNotDisturbPredmet($predmetId);

        } catch (\Exception $e) {
            Log::error('Failed to turn off DND for predmet', [
                'predmet_id' => $predmetId,
                'error' => $e->getMessage(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Failed to turn off DND: '.$e->getMessage()
            );
        }
    }

    /**
     * Turn on general Do Not Disturb
     *
     * @throws EkomApiException if operation fails
     */
    public function turnOnGeneralDnd(): array
    {
        try {
            Log::info('Turning on general DND');

            return $this->client->turnOnGeneralDoNotDisturb();

        } catch (\Exception $e) {
            Log::error('Failed to turn on general DND', ['error' => $e->getMessage()]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Failed to turn on general DND: '.$e->getMessage()
            );
        }
    }

    /**
     * Turn off general Do Not Disturb
     *
     * @throws EkomApiException if operation fails
     */
    public function turnOffGeneralDnd(): array
    {
        try {
            Log::info('Turning off general DND');

            return $this->client->turnOffGeneralDoNotDisturb();

        } catch (\Exception $e) {
            Log::error('Failed to turn off general DND', ['error' => $e->getMessage()]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Failed to turn off general DND: '.$e->getMessage()
            );
        }
    }

    /**
     * Turn off Do Not Disturb for all predmeti
     *
     * @throws EkomApiException if operation fails
     */
    public function dndAllOff(): void
    {
        try {
            Log::info('Turning off DND for all predmeti');
            $this->client->turnOffDoNotDisturbForAllPredmet();

        } catch (\Exception $e) {
            Log::error('Failed to turn off DND for all predmeti', ['error' => $e->getMessage()]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Failed to turn off DND for all: '.$e->getMessage()
            );
        }
    }

    /**
     * Confirm receipt of otpravak
     *
     * @throws EkomApiException if operation fails
     */
    public function potvrdiPrimitakOtpravka(int $id): void
    {
        try {
            Log::info('Confirming receipt of otpravak', ['otpravak_id' => $id]);
            $this->client->potvrdiPrimitakOtpravka($id);

        } catch (\Exception $e) {
            Log::error('Failed to confirm otpravak receipt', [
                'otpravak_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Failed to confirm receipt: '.$e->getMessage()
            );
        }
    }

    /**
     * Download various document types from EKOM
     *
     * @param  string  $type  Document type
     * @param  array  $params  Document parameters
     * @param  string  $saveToPath  Path to save downloaded file
     * @return string Path to saved file
     *
     * @throws \InvalidArgumentException if type is unsupported
     * @throws EkomApiException if download fails
     */
    public function download(string $type, array $params, string $saveToPath): string
    {
        try {
            Log::info('EKOM document download initiated', [
                'type' => $type,
                'params' => $params,
                'save_to' => $saveToPath,
            ]);

            $startTime = microtime(true);

            $result = match ($type) {
                'predmet-dokumenti' => $this->client->downloadDokumentiPredmeta(
                    $params['predmetId'],
                    $params['dokumentIds'] ?? [],
                    $saveToPath
                ),
                'predmet-dostavnica' => $this->client->downloadDostavnicaOtpravkaPredmeta(
                    $params['predmetId'],
                    $params['otpravakId'],
                    $saveToPath
                ),
                'otpravak-potvrda' => $this->client->downloadPotvrdaPrimitkaOtpravka(
                    $params['otpravakId'],
                    $saveToPath
                ),
                'otpravak-dokumenti' => $this->client->downloadDokumentiOtpravka(
                    $params['otpravakId'],
                    $params['dokumentIds'] ?? [],
                    $saveToPath
                ),
                'podnesak-obavijest' => $this->client->downloadObavijestOPrimitkuPodneska(
                    $params['podnesakId'],
                    $saveToPath
                ),
                'podnesak-nalog' => $this->client->downloadNalogZaPlacanjePristojbePodneska(
                    $params['podnesakId'],
                    $saveToPath
                ),
                'podnesak-dokaz' => $this->client->downloadDokazUplateOslobodjenjaPristojbePodneska(
                    $params['podnesakId'],
                    $saveToPath
                ),
                default => throw new \InvalidArgumentException("Unsupported download type: {$type}"),
            };

            Log::info('EKOM document download completed', [
                'type' => $type,
                'saved_to' => $result,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $result;

        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('EKOM document download failed', [
                'type' => $type,
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Download failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Create a new podnesak (submission)
     *
     * @param  array  $payload  Submission payload
     * @param  array  $filePaths  Attached file paths
     * @return array Created submission data
     *
     * @throws EkomApiException if creation fails
     */
    public function createPodnesak(array $payload, array $filePaths): array
    {
        try {
            Log::info('EKOM podnesak creation initiated', [
                'payload' => $payload,
                'file_count' => count($filePaths),
            ]);

            $startTime = microtime(true);

            $result = $this->client->createPodnesak($payload, $filePaths);

            Log::info('EKOM podnesak creation completed', [
                'result' => $result,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('EKOM podnesak creation failed', [
                'payload' => $payload,
                'file_count' => count($filePaths),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Podnesak creation failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Fetch predmeti page with error handling
     *
     * @throws EkomApiException
     */
    protected function fetchPredmetiPage(array $query): array
    {
        try {
            return $this->client->listPredmeti($query);

        } catch (\Exception $e) {
            Log::error('Failed to fetch predmeti page', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Failed to fetch predmeti: '.$e->getMessage()
            );
        }
    }

    /**
     * Fetch podnesci page with error handling
     *
     * @throws EkomApiException
     */
    protected function fetchPodnesciPage(array $query): array
    {
        try {
            return $this->client->listPodnesci($query);

        } catch (\Exception $e) {
            Log::error('Failed to fetch podnesci page', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Failed to fetch podnesci: '.$e->getMessage()
            );
        }
    }

    /**
     * Fetch otpravci page with error handling
     *
     * @throws EkomApiException
     */
    protected function fetchOtpravciPage(array $query): array
    {
        try {
            return $this->client->listOtpravci($query);

        } catch (\Exception $e) {
            Log::error('Failed to fetch otpravci page', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            throw new EkomApiException(
                statusCode: 500,
                errorMessage: 'Failed to fetch otpravci: '.$e->getMessage()
            );
        }
    }
}
