<?php

$providers = [
    App\Providers\AgentServiceProvider::class,
    App\Providers\ApiRotatorServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\DefenseServiceProvider::class,
    App\Providers\EkomServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\GraphQLDiscoveryServiceProvider::class,
    App\Providers\GraphServiceProvider::class,
    App\Providers\InternalMcpServiceProvider::class,
    App\Providers\LegalArtilleryServiceProvider::class,
    App\Providers\LegalReasoningServiceProvider::class,
    App\Providers\Neo4jServiceProvider::class,
    App\Providers\OpenAIServiceProvider::class,
    App\Providers\ResearchServiceProvider::class,
    App\Providers\SearchServiceProvider::class,
    App\Providers\SentryServiceProvider::class,
    // Register Breadcrumb navigation service
    App\Providers\BreadcrumbServiceProvider::class,

];

// Register Horizon only when the package is installed
if (class_exists(\Laravel\Horizon\HorizonApplicationServiceProvider::class)) {
    $providers[] = App\Providers\HorizonServiceProvider::class;
}

return $providers;
