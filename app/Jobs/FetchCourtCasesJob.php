<?php

namespace App\Jobs;

use App\Models\Court;
use App\Models\CourtCase;
use App\Models\SyncLog;
use App\Services\EPredmetService;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FetchCourtCasesJob - Batch fetch court cases from e-predmet API
 *
 * Fetches court cases for a specific court, year, and register from the
 * e-Predmet GraphQL API and stores them in the database.
 *
 * @see docs/plans/2026-01-20-epredmet-widget-conversion.md
 */
class FetchCourtCasesJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $courtExternalId;
    public int $year;
    public string $register;
    public int $maxCases;

    public int $timeout = 3600; // 1 hour max
    public int $tries = 1;

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $courtExternalId,
        int $year,
        string $register = 'Pp Prz',
        int $maxCases = 0,
        ?int $userId = null
    ) {
        $this->courtExternalId = $courtExternalId;
        $this->year = $year;
        $this->register = $register;
        $this->maxCases = $maxCases;
        $this->userId = $userId ?? auth()->id();
    }

    public function getJobDisplayName(): string
    {
        return "Fetching Court Cases: {$this->register} {$this->year}";
    }

    /**
     * Execute the job.
     */
    public function handle(EPredmetService $api): void
    {
        $court = Court::findByExternalId($this->courtExternalId);

        if (!$court) {
            Log::error('FetchCourtCasesJob: Court not found', [
                'external_id' => $this->courtExternalId,
            ]);
            return;
        }

        // Get or create sync log
        $syncLog = SyncLog::firstOrCreate(
            [
                'court_id' => $court->id,
                'register' => $this->register,
                'year' => $this->year,
            ],
            ['status' => 'pending']
        );

        $syncLog->markAsRunning();

        $broadcastJobId = 'fetch_cases_' . $this->courtExternalId . '_' . $this->year;
        $fetched = 0;
        $saved = 0;
        $errors = 0;

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'court_id' => $this->courtExternalId,
                    'year' => $this->year,
                    'register' => $this->register,
                ]);
            }
            $generator = $api->fetchAllCases(
                $court->external_id,
                $this->register,
                $this->year,
                null, // no progress callback for job
                1,    // start from 1
                $this->maxCases
            );

            foreach ($generator as $caseData) {
                $fetched++;

                try {
                    DB::beginTransaction();
                    CourtCase::createFromApiResponse($caseData, $court->id);
                    $saved++;

                    // Update progress periodically
                    $caseNumber = $this->extractCaseNumber($caseData['oznakaBroj'] ?? '');
                    $syncLog->updateLastCaseNumber($caseNumber);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors++;
                    Log::warning('FetchCourtCasesJob: Error saving case', [
                        'error' => $e->getMessage(),
                        'case' => $caseData['oznakaBroj'] ?? 'unknown',
                    ]);
                }

                // Update sync log every 50 cases
                if ($fetched % 50 === 0) {
                    $syncLog->update([
                        'total_fetched' => $fetched,
                        'total_saved' => $saved,
                        'total_errors' => $errors,
                    ]);
                }
            }

            // Final update
            $syncLog->update([
                'total_fetched' => $fetched,
                'total_saved' => $saved,
                'total_errors' => $errors,
            ]);
            $syncLog->markAsCompleted();

            Log::info('FetchCourtCasesJob: Completed', [
                'court' => $court->short_name,
                'year' => $this->year,
                'fetched' => $fetched,
                'saved' => $saved,
                'errors' => $errors,
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, [
                    'fetched' => $fetched,
                    'saved' => $saved,
                    'errors' => $errors,
                ]);
            }

        } catch (\Exception $e) {
            $syncLog->update([
                'total_fetched' => $fetched,
                'total_saved' => $saved,
                'total_errors' => $errors,
            ]);
            $syncLog->markAsFailed($e->getMessage());

            Log::error('FetchCourtCasesJob: Failed', [
                'court' => $court->short_name ?? $this->courtExternalId,
                'error' => $e->getMessage(),
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Fetching Cases', [
                    'fetched' => $fetched,
                    'saved' => $saved,
                    'errors' => $errors,
                ]);
            }

            throw $e; // Re-throw for job retry handling
        }
    }

    /**
     * Extract the case number from a case identifier string.
     *
     * @param string $caseNumber Case identifier like "Pp Prz-123/2025"
     * @return int The extracted case number (e.g., 123)
     */
    protected function extractCaseNumber(string $caseNumber): int
    {
        if (preg_match('/-(\d+)\//', $caseNumber, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }
}
