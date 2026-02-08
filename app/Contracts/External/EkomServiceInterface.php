<?php

namespace App\Contracts\External;

/**
 * EKOM Service Interface
 *
 * Defines the contract for Croatian e-Komunikacija court system integration.
 * Handles synchronization of cases (predmeti), submissions (podnesci),
 * and dispatches (otpravci).
 */
interface EkomServiceInterface
{
    /**
     * Synchronize cases (predmeti) from EKOM
     *
     * @param  array  $filters  Filter criteria
     * @param  int  $maxPages  Maximum pages to fetch
     * @param  int|null  $pageSize  Page size
     * @return int Number of cases synchronized
     */
    public function syncPredmeti(array $filters = [], int $maxPages = 1, ?int $pageSize = null): int;

    /**
     * Synchronize submissions (podnesci) from EKOM
     *
     * @param  array  $filters  Filter criteria
     * @param  int  $maxPages  Maximum pages to fetch
     * @param  int|null  $pageSize  Page size
     * @return int Number of submissions synchronized
     */
    public function syncPodnesci(array $filters = [], int $maxPages = 1, ?int $pageSize = null): int;

    /**
     * Synchronize dispatches (otpravci) from EKOM
     *
     * @param  array  $filters  Filter criteria
     * @param  int  $maxPages  Maximum pages to fetch
     * @param  int|null  $pageSize  Page size
     * @return int Number of dispatches synchronized
     */
    public function syncOtpravci(array $filters = [], int $maxPages = 1, ?int $pageSize = null): int;

    /**
     * Turn on Do Not Disturb for a specific case
     *
     * @param  int  $predmetId  Case ID
     * @return bool Success status
     */
    public function turnOnDndPredmet(int $predmetId): bool;

    /**
     * Turn off Do Not Disturb for a specific case
     *
     * @param  int  $predmetId  Case ID
     * @return bool Success status
     */
    public function turnOffDndPredmet(int $predmetId): bool;

    /**
     * Turn on general Do Not Disturb
     *
     * @return array DND status
     */
    public function turnOnGeneralDnd(): array;

    /**
     * Turn off general Do Not Disturb
     *
     * @return array DND status
     */
    public function turnOffGeneralDnd(): array;

    /**
     * Turn off all Do Not Disturb settings
     */
    public function dndAllOff(): void;

    /**
     * Confirm receipt of a dispatch
     *
     * @param  int  $id  Dispatch ID
     */
    public function potvrdiPrimitakOtpravka(int $id): void;

    /**
     * Download a file from EKOM
     *
     * @param  string  $type  File type
     * @param  array  $params  Download parameters
     * @param  string  $saveToPath  Path to save file
     * @return string Downloaded file path
     */
    public function download(string $type, array $params, string $saveToPath): string;

    /**
     * Create a submission (podnesak) in EKOM
     *
     * @param  array  $payload  Submission data
     * @param  array  $filePaths  Attached file paths
     * @return array Creation result
     */
    public function createPodnesak(array $payload, array $filePaths): array;
}
