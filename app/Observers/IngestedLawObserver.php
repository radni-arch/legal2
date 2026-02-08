<?php

namespace App\Observers;

use App\Models\IngestedLaw;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IngestedLawObserver
{
    /**
     * Handle the IngestedLaw "created" event.
     */
    public function created(IngestedLaw $law): void
    {
        $this->invalidateLawCache($law);
        $this->invalidateLawIndexCache();

        Log::info('Cache invalidated after law created', [
            'law_id' => $law->id,
            'law_code' => $law->law_code,
        ]);
    }

    /**
     * Handle the IngestedLaw "updated" event.
     */
    public function updated(IngestedLaw $law): void
    {
        $this->invalidateLawCache($law);
        $this->invalidateLawIndexCache();

        Log::info('Cache invalidated after law updated', [
            'law_id' => $law->id,
            'law_code' => $law->law_code,
        ]);
    }

    /**
     * Handle the IngestedLaw "deleted" event.
     */
    public function deleted(IngestedLaw $law): void
    {
        $this->invalidateLawCache($law);
        $this->invalidateLawIndexCache();

        Log::info('Cache invalidated after law deleted', [
            'law_id' => $law->id,
            'law_code' => $law->law_code,
        ]);
    }

    /**
     * Handle the IngestedLaw "restored" event.
     */
    public function restored(IngestedLaw $law): void
    {
        $this->invalidateLawCache($law);
        $this->invalidateLawIndexCache();

        Log::info('Cache invalidated after law restored', [
            'law_id' => $law->id,
            'law_code' => $law->law_code,
        ]);
    }

    /**
     * Handle the IngestedLaw "force deleted" event.
     */
    public function forceDeleted(IngestedLaw $law): void
    {
        $this->invalidateLawCache($law);
        $this->invalidateLawIndexCache();

        Log::info('Cache invalidated after law force deleted', [
            'law_id' => $law->id,
            'law_code' => $law->law_code,
        ]);
    }

    /**
     * Invalidate specific law caches.
     */
    protected function invalidateLawCache(IngestedLaw $law): void
    {
        // Clear cache by law code
        if ($law->law_code) {
            Cache::forget("law:code:{$law->law_code}");
        }

        // Clear cache by ID
        Cache::forget("law:{$law->id}");

        // Clear law articles cache
        Cache::forget("law:{$law->id}:articles");
    }

    /**
     * Invalidate law index cache.
     */
    protected function invalidateLawIndexCache(): void
    {
        // Clear law index cache
        Cache::forget('laws:index');

        // Clear system stats that include law count
        Cache::forget('system:stats:database');
    }
}
