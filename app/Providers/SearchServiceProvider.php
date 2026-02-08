<?php

namespace App\Providers;

use App\Services\Search\CaseSearchService;
use App\Services\Search\CitationSearchService;
use App\Services\Search\DecisionSearchService;
use App\Services\Search\FullTextSearchService;
use App\Services\Search\LawSearchService;
use App\Services\Search\SearchEmbeddingService;
use App\Services\Search\SearchResultAggregator;
use App\Services\Search\SearchResultDeduplicator;
use Illuminate\Support\ServiceProvider;

/**
 * SearchServiceProvider
 *
 * Registers all search-related services extracted from UnifiedSearchService.
 * Phase 2: Modular corpus-specific vector search services.
 * Phase 3: Full-text search and citation search decomposition.
 */
class SearchServiceProvider extends ServiceProvider
{
    /**
     * Register search services
     *
     * All services are registered as singletons for performance.
     */
    public function register(): void
    {
        // Core embedding service
        $this->app->singleton(SearchEmbeddingService::class, function ($app) {
            return new SearchEmbeddingService(
                $app->make(\App\Services\OpenAIService::class)
            );
        });

        // Corpus-specific vector search services
        $this->app->singleton(LawSearchService::class, function ($app) {
            return new LawSearchService(
                $app->make(SearchEmbeddingService::class),
                $app->make(\App\Services\LawVectorStoreService::class)
            );
        });

        $this->app->singleton(DecisionSearchService::class, function ($app) {
            return new DecisionSearchService(
                $app->make(SearchEmbeddingService::class),
                $app->make(\App\Services\CourtDecisionVectorStoreService::class)
            );
        });

        $this->app->singleton(CaseSearchService::class, function ($app) {
            return new CaseSearchService(
                $app->make(SearchEmbeddingService::class),
                $app->make(\App\Services\CaseVectorStoreService::class)
            );
        });

        // Full-text and citation search services (Phase 3)
        $this->app->singleton(FullTextSearchService::class);
        $this->app->singleton(CitationSearchService::class);

        // Result processing services
        $this->app->singleton(SearchResultAggregator::class);
        $this->app->singleton(SearchResultDeduplicator::class);
    }

    /**
     * Bootstrap search services
     */
    public function boot(): void
    {
        // No bootstrap logic needed yet
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            SearchEmbeddingService::class,
            LawSearchService::class,
            DecisionSearchService::class,
            CaseSearchService::class,
            FullTextSearchService::class,
            CitationSearchService::class,
            SearchResultAggregator::class,
            SearchResultDeduplicator::class,
        ];
    }
}
