<?php

namespace App\Providers;

use App\Contracts\Agents\IterationControllerInterface;
use App\Contracts\Research\AnswerEvaluatorInterface;
use App\Contracts\Research\QualityAssessorInterface;
use App\Contracts\Research\QuestionGeneratorInterface;
use App\Contracts\Research\SearchExecutorInterface;
use App\Services\Agents\IterationControllerService;
use App\Services\Research\AnswerEvaluatorService;
use App\Services\Research\QualityAssessorService;
use App\Services\Research\QuestionGeneratorService;
use App\Services\Research\SearchExecutorService;
use App\Services\ResearchOrchestrator;
use Illuminate\Support\ServiceProvider;

/**
 * Research Services Provider
 *
 * Registers all Research-related services with the Laravel service container.
 * These services work together to implement the autonomous research pipeline.
 *
 * Service Registration Order:
 * 1. QuestionGeneratorService - Generates and refines research questions
 * 2. SearchExecutorService - Executes searches using questions
 * 3. AnswerEvaluatorService - Evaluates answers from search results
 * 4. QualityAssessorService - Assesses overall research quality
 * 5. IterationControllerService - Controls iteration limits and budgets
 * 6. ResearchOrchestrator - Coordinates the entire research pipeline
 *
 * Pipeline Flow:
 * QuestionGenerator → SearchExecutor → AnswerEvaluator → QualityAssessor
 *                                            ↓
 *                                   IterationController
 *                                            ↓
 *                             (repeat if quality < threshold)
 *
 * All services are bound to their interfaces for easy mocking in tests.
 *
 * @see \App\Services\ResearchOrchestrator
 */
class ResearchServiceProvider extends ServiceProvider
{
    /**
     * Register Research services
     */
    public function register(): void
    {
        // Register QuestionGeneratorService
        $this->app->singleton(QuestionGeneratorInterface::class, function ($app) {
            return new QuestionGeneratorService(
                $app->make(\App\Contracts\AI\ChatServiceInterface::class)
            );
        });

        // Register SearchExecutorService
        $this->app->singleton(SearchExecutorInterface::class, function ($app) {
            return new SearchExecutorService(
                $app->make(\App\Services\LawSearchService::class),
                $app->make(\App\Services\DecisionSearchService::class),
                $app->make(\App\Services\CaseSearchService::class)
            );
        });

        // Register AnswerEvaluatorService
        $this->app->singleton(AnswerEvaluatorInterface::class, function ($app) {
            return new AnswerEvaluatorService(
                $app->make(\App\Contracts\AI\ChatServiceInterface::class)
            );
        });

        // Register QualityAssessorService
        $this->app->singleton(QualityAssessorInterface::class, function ($app) {
            return new QualityAssessorService(
                $app->make(\App\Contracts\AI\ChatServiceInterface::class)
            );
        });

        // Register IterationControllerService
        $this->app->singleton(IterationControllerInterface::class, function ($app) {
            return new IterationControllerService;
        });

        // Register ResearchOrchestrator (coordinates all services)
        $this->app->singleton(ResearchOrchestrator::class, function ($app) {
            return new ResearchOrchestrator(
                $app->make(QuestionGeneratorInterface::class),
                $app->make(SearchExecutorInterface::class),
                $app->make(AnswerEvaluatorInterface::class),
                $app->make(QualityAssessorInterface::class),
                $app->make(IterationControllerInterface::class),
                $app->make(\App\Contracts\AI\ChatServiceInterface::class)
            );
        });

        // Alias for backward compatibility
        $this->app->alias(ResearchOrchestrator::class, 'research.orchestrator');
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        //
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            QuestionGeneratorInterface::class,
            SearchExecutorInterface::class,
            AnswerEvaluatorInterface::class,
            QualityAssessorInterface::class,
            IterationControllerInterface::class,
            ResearchOrchestrator::class,
            'research.orchestrator',
        ];
    }
}
