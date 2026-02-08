<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sentry\State\Scope;

class SentryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Skip in testing environment
        if (app()->environment('testing')) {
            return;
        }

        // Configure Sentry scope with user context
        \Sentry\configureScope(function (Scope $scope): void {
            // Add user context if authenticated
            if (auth()->check()) {
                $scope->setUser([
                    'id' => auth()->id(),
                    'email' => auth()->user()->email ?? null,
                ]);
            }

            // Add correlation ID from request headers
            if (request()->hasHeader('X-Request-ID')) {
                $scope->setExtra('correlation_id', request()->header('X-Request-ID'));
            }
        });
    }
}
