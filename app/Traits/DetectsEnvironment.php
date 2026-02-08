<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait DetectsEnvironment
{
    /**
     * Check if running in production environment (not localhost)
     */
    protected function isProduction(): bool
    {
        $isProduction = app()->environment('production');
        $isLocalhost = in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost']);

        return $isProduction && ! $isLocalhost;
    }

    /**
     * Check if running on localhost/dev
     */
    protected function isLocalhost(): bool
    {
        return ! $this->isProduction();
    }

    /**
     * Log environment detection
     */
    protected function logEnvironment(string $context): void
    {
        Log::info("Environment detection: {$context}", [
            'is_production' => $this->isProduction(),
            'is_localhost' => $this->isLocalhost(),
            'app_env' => app()->environment(),
            'request_ip' => request()->ip(),
        ]);
    }
}
