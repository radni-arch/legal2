<?php

namespace App\Observers;

use App\Models\CourtDecision;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CourtDecisionObserver
{
    /**
     * Handle the CourtDecision "created" event.
     */
    public function created(CourtDecision $decision): void
    {
        $this->invalidateDecisionCache($decision);
        $this->invalidateDecisionIndexCache();

        Log::info('Cache invalidated after court decision created', [
            'decision_id' => $decision->id,
            'ecli' => $decision->ecli,
            'court' => $decision->court,
        ]);
    }

    /**
     * Handle the CourtDecision "updated" event.
     */
    public function updated(CourtDecision $decision): void
    {
        $this->invalidateDecisionCache($decision);
        $this->invalidateDecisionIndexCache();

        Log::info('Cache invalidated after court decision updated', [
            'decision_id' => $decision->id,
            'ecli' => $decision->ecli,
            'court' => $decision->court,
        ]);
    }

    /**
     * Handle the CourtDecision "deleted" event.
     */
    public function deleted(CourtDecision $decision): void
    {
        $this->invalidateDecisionCache($decision);
        $this->invalidateDecisionIndexCache();

        Log::info('Cache invalidated after court decision deleted', [
            'decision_id' => $decision->id,
            'ecli' => $decision->ecli,
            'court' => $decision->court,
        ]);
    }

    /**
     * Handle the CourtDecision "restored" event.
     */
    public function restored(CourtDecision $decision): void
    {
        $this->invalidateDecisionCache($decision);
        $this->invalidateDecisionIndexCache();

        Log::info('Cache invalidated after court decision restored', [
            'decision_id' => $decision->id,
            'ecli' => $decision->ecli,
            'court' => $decision->court,
        ]);
    }

    /**
     * Handle the CourtDecision "force deleted" event.
     */
    public function forceDeleted(CourtDecision $decision): void
    {
        $this->invalidateDecisionCache($decision);
        $this->invalidateDecisionIndexCache();

        Log::info('Cache invalidated after court decision force deleted', [
            'decision_id' => $decision->id,
            'ecli' => $decision->ecli,
            'court' => $decision->court,
        ]);
    }

    /**
     * Invalidate specific decision caches.
     */
    protected function invalidateDecisionCache(CourtDecision $decision): void
    {
        // Clear cache by ECLI
        if ($decision->ecli) {
            Cache::forget("court_decision:ecli:{$decision->ecli}");
        }

        // Clear cache by ID
        Cache::forget("court_decision:{$decision->id}");

        // Clear court-specific recent decisions cache
        if ($decision->court) {
            Cache::forget("court_decisions:court:{$decision->court}:recent");
        }
    }

    /**
     * Invalidate decision index caches.
     */
    protected function invalidateDecisionIndexCache(): void
    {
        // Clear recent decisions cache
        Cache::forget('court_decisions:recent:100');

        // Clear system stats that include decision count
        Cache::forget('system:stats:database');

        // Clear all court-specific caches (may have many courts, so use pattern)
        // Note: This requires manual iteration since Cache::forget doesn't support wildcards
        // For production, consider using cache tags if supported
        $courts = [
            'Vrhovni sud Republike Hrvatske',
            'Visoki upravni sud Republike Hrvatske',
            'Ustavni sud Republike Hrvatske',
        ];

        foreach ($courts as $court) {
            Cache::forget("court_decisions:court:{$court}:recent");
        }
    }
}
