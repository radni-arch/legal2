<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Navigation\BreadcrumbService;
use Illuminate\Support\ServiceProvider;

class BreadcrumbServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BreadcrumbService::class, function () {
            $service = new BreadcrumbService();
            $this->registerBreadcrumbs($service);
            return $service;
        });
    }

    private function registerBreadcrumbs(BreadcrumbService $service): void
    {
        $service->registerMany([
            // Root
            'dashboard' => ['label' => 'Dashboard'],

            // AI & Research Tools
            'chatbot' => ['label' => 'AI Chatbot', 'parent' => 'dashboard'],
            'unified.search' => ['label' => 'Search', 'parent' => 'dashboard'],
            'topics.demo' => ['label' => 'Topic Analyzer', 'parent' => 'dashboard'],
            'legal.playground' => ['label' => 'Legal Playground', 'parent' => 'dashboard'],
            'federated.memory.search' => ['label' => 'Federated Memory', 'parent' => 'dashboard'],

            // Timeline Tools
            'timeline' => ['label' => 'Timeline', 'parent' => 'dashboard', 'url' => '/timeline'],
            'comparative-timeline' => ['label' => 'Comparative Timeline', 'parent' => 'dashboard', 'url' => '/comparative-timeline'],
            'parallel.timeline' => ['label' => 'Parallel Timeline', 'parent' => 'dashboard', 'url' => '/parallel-timeline'],

            // Knowledge & Graph
            'graph.dashboard' => ['label' => 'Graph Dashboard', 'parent' => 'dashboard'],
            'graph.viewer' => ['label' => 'Graph Viewer', 'parent' => 'graph.dashboard'],
            'graph.explore' => ['label' => 'Graph Explorer', 'parent' => 'dashboard', 'url' => '/graph/explore'],
            'decisions.discover' => ['label' => 'Decision Discovery', 'parent' => 'dashboard'],
            'citation.time-series' => ['label' => 'Citation Time Series', 'parent' => 'dashboard'],
            'feedback.dashboard' => ['label' => 'Feedback Dashboard', 'parent' => 'dashboard'],
            'learning.opportunities' => ['label' => 'Learning Opportunities', 'parent' => 'dashboard'],

            // Document Management
            'transcript' => ['label' => 'Transcript', 'parent' => 'dashboard'],
            'textract.manager' => ['label' => 'Textract Pipeline', 'parent' => 'dashboard'],
            'vectors.manage' => ['label' => 'Vector Store Manager', 'parent' => 'dashboard'],
            'ingested-laws.index' => ['label' => 'Ingested Laws', 'parent' => 'dashboard'],

            // Collaboration
            'collaborations.dashboard' => ['label' => 'Collaborations', 'parent' => 'dashboard', 'url' => '/collaborations'],

            // System Management
            'logs.viewer' => ['label' => 'Logs', 'parent' => 'dashboard'],
            'honeypot.dashboard' => ['label' => 'Honeypot Security', 'parent' => 'dashboard'],
            'honeypot.ip' => ['label' => 'IP Details', 'parent' => 'honeypot.dashboard'],
            'eoglasna.monitoring' => ['label' => 'e-Oglasna Monitoring', 'parent' => 'dashboard'],
            'agent.dashboard' => ['label' => 'Agent Dashboard', 'parent' => 'dashboard'],
            'agent.run' => ['label' => 'Agent Run', 'parent' => 'agent.dashboard'],
            'agent.performance' => ['label' => 'Agent Performance', 'parent' => 'dashboard'],
            'circuit-breaker.monitor' => ['label' => 'Circuit Breaker Monitor', 'parent' => 'dashboard'],
            'feedback.dashboard' => ['label' => 'Feedback Dashboard', 'parent' => 'dashboard'],
            'learning.opportunities' => ['label' => 'Learning Opportunities', 'parent' => 'dashboard'],

            // User
            'profile.show' => ['label' => 'Profile', 'parent' => 'dashboard'],

            // OpenAI
            'openai.responses' => ['label' => 'OpenAI Responses', 'parent' => 'dashboard'],
            'openai.vectors' => ['label' => 'OpenAI Vector Stores', 'parent' => 'dashboard'],

            // Uploader
            'uploader' => ['label' => 'File Uploader', 'parent' => 'dashboard'],

            // E-Komunikacije Module
            'ekom.dashboard' => ['label' => 'E-Komunikacije', 'parent' => 'dashboard'],
            'ekom.predmeti' => ['label' => 'Cases (Predmeti)', 'parent' => 'ekom.dashboard'],
            'ekom.predmeti.show' => ['label' => 'Case Details', 'parent' => 'ekom.predmeti'],
            'ekom.podnesci' => ['label' => 'Submissions (Podnesci)', 'parent' => 'ekom.dashboard'],
            'ekom.podnesci.create' => ['label' => 'New Submission', 'parent' => 'ekom.podnesci'],
            'ekom.otpravci' => ['label' => 'Dispatches (Otpravci)', 'parent' => 'ekom.dashboard'],
            'ekom.sync-status' => ['label' => 'Sync Status', 'parent' => 'ekom.dashboard'],
        ]);
    }
}
