<?php

namespace App\Providers;

use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Defense\DefenseReportBuilder;
use App\Services\Defense\Detectors\ChainOfCustodyAnalyzer;
use App\Services\Defense\Detectors\ConstitutionalViolationScanner;
use App\Services\Defense\Detectors\DefenseTimeAdequacyChecker;
use App\Services\Defense\Detectors\ExpertWitnessValidator;
use App\Services\Defense\Detectors\FruitOfPoisonousTreeMapper;
use App\Services\Defense\Detectors\JudicialBiasDetector;
use App\Services\Defense\Detectors\NeBisInIdemDetector;
use App\Services\Defense\Detectors\ProportionalityChecker;
use App\Services\Defense\Detectors\ProsecutorialDisclosureChecker;
use App\Services\Defense\Detectors\ZastaraCalculator;
use Illuminate\Support\ServiceProvider;

class DefenseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DefenseReportBuilder::class, function ($app) {
            $builder = new DefenseReportBuilder();

            // Tier A: Pure computation (no AI)
            $builder->register(new ZastaraCalculator());
            $builder->register(new DefenseTimeAdequacyChecker());
            $builder->register(new NeBisInIdemDetector());

            // Tier B: Cross-document pattern matching (no AI)
            $builder->register(new ChainOfCustodyAnalyzer());
            $builder->register(new FruitOfPoisonousTreeMapper());
            $builder->register(new ProsecutorialDisclosureChecker());

            // Tier C: AI-assisted (require ClaudeAnalysisService)
            $claudeService = $app->make(ClaudeAnalysisService::class);
            $builder->register(new JudicialBiasDetector($claudeService));
            $builder->register(new ProportionalityChecker());
            $builder->register(new ExpertWitnessValidator());
            $builder->register(new ConstitutionalViolationScanner($claudeService));

            return $builder;
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
