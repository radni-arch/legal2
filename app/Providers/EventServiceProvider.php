<?php

namespace App\Providers;

use App\Events\CaseReadyForReview;
use App\Events\CircuitBreaker\CircuitBreakerOpened;
use App\Events\JobCompleted;
use App\Events\JobFailed;
use App\Events\NewInsightDiscovered;
use App\Events\TextractJobGraphSynced;
use App\Listeners\CheckCaseCompleteness;
use App\Listeners\CircuitBreaker\LogCircuitBreakerState;
use App\Listeners\CircuitBreaker\NotifySlackOnCircuitBreaker;
use App\Listeners\CircuitBreaker\RecordCircuitBreakerMetrics;
use App\Listeners\LogNewInsight;
use App\Listeners\NotifyCaseReadyForReview;
use App\Listeners\PersistJobNotification;
use App\Listeners\TriggerDocumentAnalysis;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event Service Provider
 *
 * Configures event listeners and their queue settings for the application.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        CaseDocumentIngested::class => [
            TriggerDocumentAnalysis::class,
        ],
        NewInsightDiscovered::class => [
            LogNewInsight::class,
        ],
        CircuitBreakerOpened::class => [
            NotifySlackOnCircuitBreaker::class,
        ],
        JobCompleted::class => [
            PersistJobNotification::class,
        ],
        JobFailed::class => [
            PersistJobNotification::class,
        ],
        TextractJobGraphSynced::class => [
            CheckCaseCompleteness::class,
        ],
        CaseReadyForReview::class => [
            NotifyCaseReadyForReview::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        LogCircuitBreakerState::class,
        RecordCircuitBreakerMetrics::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}
