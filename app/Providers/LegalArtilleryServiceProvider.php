<?php

namespace App\Providers;

use App\Agents\LegalArtilleryAgent;
use App\Agents\LegalArtilleryOrchestrator;
use App\Services\LegalArtillery\CaseBridge;
use App\Services\LegalArtillery\DigitalSigner;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\EKomunikacijaDispatcher;
use App\Services\LegalArtillery\GmailDispatcher;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\PiiRedactor;
use App\Services\LegalArtillery\ProfileContextBuilder;
use App\Services\LegalArtillery\QualityGate;
use App\Services\LegalArtillery\ResponseHandler;
use Illuminate\Support\ServiceProvider;

class LegalArtilleryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmClient::class, fn () => LlmClient::fromConfig());

        $this->app->bind(\App\Services\LegalArtillery\PiiRedactor::class);
        $this->app->bind(CaseBridge::class);
        $this->app->bind(DocxRenderer::class);
        $this->app->bind(GmailDispatcher::class, function ($app) {
            return new GmailDispatcher($app->make(PiiRedactor::class));
        });
        $this->app->bind(DigitalSigner::class);
        $this->app->bind(QualityGate::class);

        $this->app->bind(ResponseHandler::class, function ($app) {
            return new ResponseHandler($app->make(LlmClient::class));
        });

        $this->app->bind(EKomunikacijaDispatcher::class, function ($app) {
            $ekomClientClass = \App\Services\EKomunikacija\Client::class;

            return new EKomunikacijaDispatcher(
                $app->bound($ekomClientClass) ? $app->make($ekomClientClass) : new $ekomClientClass(),
                $app->make(PiiRedactor::class),
            );
        });

        $this->app->singleton(LegalArtilleryOrchestrator::class, function ($app) {
            return new LegalArtilleryOrchestrator(
                $app->make(LlmClient::class),
                new ProfileContextBuilder(),
                $app->make(PiiRedactor::class),
            );
        });

        $this->app->singleton(LegalArtilleryAgent::class, function ($app) {
            $eKom = null;
            if ($app->bound(\App\Services\EKomunikacija\Client::class)) {
                $eKom = $app->make(EKomunikacijaDispatcher::class);
            }

            return new LegalArtilleryAgent(
                $app->make(LegalArtilleryOrchestrator::class),
                new DocxRenderer(),
                config('legal-artillery.gmail.enabled') ? $app->make(GmailDispatcher::class) : null,
                config('legal-artillery.ekomunikacija.enabled', false) ? $app->make(EKomunikacijaDispatcher::class) : null,
            );
        });

        $this->app->bind(
            \App\Agents\Contracts\LegalArtilleryAgentContract::class,
            LegalArtilleryAgent::class
        );

        // SOT-010: Canonical escalation suggester binding
        $this->app->bind(
            \App\Contracts\LegalArtillery\EscalationSuggesterInterface::class,
            \App\Services\LegalArtillery\EscalationLadderSuggester::class
        );
    }
}
