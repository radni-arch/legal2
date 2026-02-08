<?php

namespace App\Providers;

use App\Models\AgentRun;
use App\Models\CaseDocument;
use App\Models\CourtDecision;
use App\Models\DocumentGenerationRun;
use App\Models\Law;
use App\Models\LegalCase;
use App\Models\TextractJob;
use App\Policies\AgentRunPolicy;
use App\Policies\CaseDocumentPolicy;
use App\Policies\CourtDecisionPolicy;
use App\Policies\DocumentGenerationRunPolicy;
use App\Policies\LawPolicy;
use App\Policies\LegalCasePolicy;
use App\Policies\TextractJobPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        LegalCase::class => LegalCasePolicy::class,
        CaseDocument::class => CaseDocumentPolicy::class,
        CourtDecision::class => CourtDecisionPolicy::class,
        Law::class => LawPolicy::class,
        AgentRun::class => AgentRunPolicy::class,
        DocumentGenerationRun::class => DocumentGenerationRunPolicy::class,
        TextractJob::class => TextractJobPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
