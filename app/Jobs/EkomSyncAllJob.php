<?php

namespace App\Jobs;

use App\Contracts\External\EkomServiceInterface;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * EkomSyncAllJob - Background job to sync all EKOM entities
 *
 * Synchronizes cases (predmeti), submissions (podnesci), and dispatches (otpravci)
 * from the Croatian e-Komunikacija court system. This job is dispatched from the
 * dashboard "Sync All" button.
 */
class EkomSyncAllJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of pages to fetch per entity type.
     */
    public int $maxPages;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(int $maxPages = 10, ?int $userId = null)
    {
        $this->maxPages = $maxPages;
        $this->userId = $userId ?? auth()->id();
    }

    /**
     * Ensure userId is initialized after deserialization.
     *
     * PHP typed properties remain uninitialized (not null) when missing
     * from serialized data, causing access errors on deserialization.
     */
    public function __wakeup(): void
    {
        if (! isset($this->userId)) {
            $this->userId = null;
        }
    }

    public function getJobDisplayName(): string
    {
        return 'EKOM Full Sync';
    }

    /**
     * Execute the job.
     */
    public function handle(EkomServiceInterface $service): void
    {
        $startTime = microtime(true);

        Log::info('EKOM full sync started', ['max_pages' => $this->maxPages]);

        $broadcastJobId = 'ekom_sync_' . now()->timestamp;

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'max_pages' => $this->maxPages,
                ]);
            }

            $predmetiCount = $service->syncPredmeti([], $this->maxPages, null);
            Log::info('EKOM predmeti synced', ['count' => $predmetiCount]);

            if ($this->userId) {
                $this->broadcastProgress($this->userId, $broadcastJobId, 33, 'Syncing Podnesci', 'Predmeti complete');
            }

            $podnesciCount = $service->syncPodnesci([], $this->maxPages, null);
            Log::info('EKOM podnesci synced', ['count' => $podnesciCount]);

            if ($this->userId) {
                $this->broadcastProgress($this->userId, $broadcastJobId, 66, 'Syncing Otpravci', 'Podnesci complete');
            }

            $otpravciCount = $service->syncOtpravci([], $this->maxPages, null);
            Log::info('EKOM otpravci synced', ['count' => $otpravciCount]);

            Log::info('EKOM full sync completed', [
                'predmeti' => $predmetiCount,
                'podnesci' => $podnesciCount,
                'otpravci' => $otpravciCount,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, [
                    'predmeti' => $predmetiCount,
                    'podnesci' => $podnesciCount,
                    'otpravci' => $otpravciCount,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('EKOM full sync failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'EKOM Sync');
            }

            throw $e;
        }
    }
}
