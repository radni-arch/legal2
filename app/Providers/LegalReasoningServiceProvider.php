<?php

namespace App\Providers;

use App\Services\CaseVectorStoreService;
use App\Services\GraphDatabaseService;
use App\Services\LegalReasoning\ArgumentGenerator;
use App\Services\LegalReasoning\CitationAnalyzer;
use App\Services\LegalReasoning\ConflictResolver;
use App\Services\LegalReasoning\DurationEstimator;
use App\Services\LegalReasoning\FeatureExtractor;
use App\Services\LegalReasoning\ImpactAnalyzer;
use App\Services\LegalReasoning\LogicEngine;
use App\Services\LegalReasoning\OutcomePredictor;
use App\Services\LegalReasoning\PredictiveAnalytics;
use App\Services\LegalReasoning\RiskAssessor;
use App\Services\LegalReasoning\StrategicPlanner;
use App\Services\LegalReasoning\StrategyBuilder;
use App\Services\OpenAIService;
use Illuminate\Support\ServiceProvider;

class LegalReasoningServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register FeatureExtractor as singleton (foundational service)
        $this->app->singleton(FeatureExtractor::class, function ($app) {
            return new FeatureExtractor(
                $app->make(OpenAIService::class)
            );
        });

        // Register OutcomePredictor as singleton
        $this->app->singleton(OutcomePredictor::class, function ($app) {
            return new OutcomePredictor(
                $app->make(OpenAIService::class),
                $app->make(CaseVectorStoreService::class),
                $app->make(FeatureExtractor::class)
            );
        });

        // Register ConflictResolver as singleton
        $this->app->singleton(ConflictResolver::class, function ($app) {
            return new ConflictResolver(
                $app->make(GraphDatabaseService::class),
                $app->make(OpenAIService::class)
            );
        });

        // Register CitationAnalyzer as singleton
        $this->app->singleton(CitationAnalyzer::class, function ($app) {
            return new CitationAnalyzer(
                $app->make(GraphDatabaseService::class)
            );
        });

        // Register LogicEngine as singleton
        $this->app->singleton(LogicEngine::class, function ($app) {
            return new LogicEngine(
                $app->make(OpenAIService::class)
            );
        });

        // Register DurationEstimator as singleton
        $this->app->singleton(DurationEstimator::class, function ($app) {
            return new DurationEstimator(
                $app->make(FeatureExtractor::class)
            );
        });

        // Register ImpactAnalyzer as singleton
        $this->app->singleton(ImpactAnalyzer::class, function ($app) {
            return new ImpactAnalyzer(
                $app->make(CitationAnalyzer::class)
            );
        });

        // Register PredictiveAnalytics as singleton
        $this->app->singleton(PredictiveAnalytics::class, function ($app) {
            return new PredictiveAnalytics(
                $app->make(OutcomePredictor::class),
                $app->make(DurationEstimator::class),
                $app->make(ImpactAnalyzer::class)
            );
        });

        // Register ArgumentGenerator as singleton
        $this->app->singleton(ArgumentGenerator::class, function ($app) {
            return new ArgumentGenerator(
                $app->make(OpenAIService::class),
                $app->make(CitationAnalyzer::class)
            );
        });

        // Register RiskAssessor as singleton
        $this->app->singleton(RiskAssessor::class, function ($app) {
            return new RiskAssessor(
                $app->make(OpenAIService::class),
                $app->make(OutcomePredictor::class),
                $app->make(CitationAnalyzer::class)
            );
        });

        // Register StrategicPlanner as singleton
        $this->app->singleton(StrategicPlanner::class, function ($app) {
            return new StrategicPlanner(
                $app->make(OpenAIService::class),
                $app->make(DurationEstimator::class)
            );
        });

        // Register StrategyBuilder as singleton
        $this->app->singleton(StrategyBuilder::class, function ($app) {
            return new StrategyBuilder(
                $app->make(OpenAIService::class),
                $app->make(OutcomePredictor::class),
                $app->make(ArgumentGenerator::class),
                $app->make(RiskAssessor::class),
                $app->make(StrategicPlanner::class),
                $app->make(CitationAnalyzer::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
