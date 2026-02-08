<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EvidenceAssetController;
use App\Http\Controllers\McpHttpController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

// Authentication Routes (Public)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Redirect root to dashboard
Route::get('/', function () {
    return redirect('dashboard');
});

// Protected Routes - Require Authentication
Route::middleware('auth')->group(function () {
    Route::view('/uploader', 'uploader')->name('uploader');

    // File uploads - session-authenticated web routes (mirrors API upload routes)
    Route::prefix('uploads')->group(function () {
        Route::post('/', [UploadController::class, 'direct']);
        Route::post('start', [UploadController::class, 'start']);
        Route::post('{uploadId}/chunk/{index}', [UploadController::class, 'chunk'])
            ->where(['index' => '[0-9]+']);
        Route::post('{uploadId}/complete', [UploadController::class, 'complete']);
        Route::delete('{uploadId}', [UploadController::class, 'cancel']);
    });

    Route::get('/timeline', \App\Http\Livewire\TimelinePage::class);  // dobar
    Route::get('/comparative-timeline3', \App\Http\Livewire\ComparativeTimelinePage::class); // stari dobar
    Route::get('/comparative-timeline', \App\Http\Livewire\GupTimeline::class);
    Route::view('/openai/logs', 'openai-logs');
    Route::view('/openai/responses', 'openai-responses')->name('openai.responses');

    Route::get('/chatbot', \App\Http\Livewire\ChatbotComponent::class)
        ->middleware('throttle:60,1')
        ->name('chatbot');

    Route::view('/ingested-laws', 'ingested-laws')->name('ingested-laws.index');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/evidence/asset', EvidenceAssetController::class)
        ->middleware('signed')
        ->name('evidence.asset');

    // Unified Search Interface
    Route::view('/search', 'search')->name('unified.search');

    // New transcript preview page
    Route::view('/transcript', 'transcript')->name('transcript');

    // Textract Pipeline Manager
    Route::view('/textract', 'textract')->name('textract.manager');

    // Textract PDF file serving (requires signed URL)
    Route::get('/textract/file/{document}', [App\Http\Controllers\Textract\TextractFileController::class, 'show'])
        ->middleware(['auth', 'signed'])
        ->name('textract.file');

    // e-Oglasna monitoring dashboard
    Route::get('/eoglasna', \App\Http\Livewire\EoglasnaMonitoring::class)->name('eoglasna.monitoring');

    // Topic Framework Demo - Interactive testing interface
    Route::get('/topics-demo', \App\Http\Livewire\TopicAnalyzer::class)->name('topics.demo');

    // Legal Defense Playground - Comprehensive testing interface for ALL modules
    Route::view('/playground', 'legal-playground')->name('legal.playground');

    // Autonomous Agent Dashboard
    Route::get('/agent/dashboard', [\App\Http\Controllers\AgentController::class, 'dashboard'])->name('agent.dashboard');
    Route::get('/agent/run/{id}', [\App\Http\Controllers\AgentController::class, 'viewRun'])->name('agent.run');

    // User Profile & API Token Management
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile/generate-token', [\App\Http\Controllers\ProfileController::class, 'generateToken'])->name('profile.generate-token');
    Route::post('/profile/revoke-token', [\App\Http\Controllers\ProfileController::class, 'revokeToken'])->name('profile.revoke-token');

    // Honeypot Security Dashboard
    Route::get('/honeypot', [\App\Http\Controllers\HoneypotDashboardController::class, 'index'])->name('honeypot.dashboard');
    Route::get('/honeypot/ip/{ip}', [\App\Http\Controllers\HoneypotDashboardController::class, 'showIP'])->name('honeypot.ip');
    Route::post('/honeypot/block/{ip}', [\App\Http\Controllers\HoneypotDashboardController::class, 'blockIP'])->name('honeypot.block');
    Route::get('/honeypot/export', [\App\Http\Controllers\HoneypotDashboardController::class, 'export'])->name('honeypot.export');
    Route::get('/honeypot/stats', [\App\Http\Controllers\HoneypotDashboardController::class, 'apiStats'])->name('honeypot.stats');

    // Inside the auth middleware group
    Route::get('/graph-dashboard', \App\Http\Livewire\GraphDashboard::class)->name('graph.dashboard');

    // Neo4j Graph Viewer
    Route::get('/graph', \App\Http\Livewire\GraphViewer::class)->name('graph.viewer');

    // Force Graph Explorer - Interactive force-directed graph visualization
    Route::get('/graph/explore/{nodeId?}', function (?string $nodeId = null) {
        return view('graph.explore', [
            'nodeId' => $nodeId,
            'panel' => request()->query('panel', 'arguments'),
        ]);
    })->name('graph.explore');

    // Decision Discovery Dashboard - Manual search and ingestion from odluke.sudovi.hr
    Route::get('/decisions/discover', \App\Http\Livewire\DecisionDiscoveryDashboard::class)->name('decisions.discover');

    // Vector Store Manager - Browse and manage all vector stores
    Route::get('/vectors/manage', \App\Http\Livewire\VectorStoreManager::class)->name('vectors.manage');

    // Laravel Log Viewer - Monitor and analyze application logs
    Route::get('/logs', \App\Http\Livewire\LaravelLogViewer::class)->name('logs.viewer');

    // Agent Collaboration Viewer - View multi-agent collaboration status
    Route::get('/collaboration/{orchestrationId}', \App\Http\Livewire\AgentCollaborationViewer::class)->name('collaboration.viewer');

    // Federated Memory Search - Semantic search across all agent memories with pgvector
    Route::get('/federated-memory', \App\Http\Livewire\FederatedMemorySearch::class)->name('federated.memory.search');

    // Citation Time Series Viewer - Interactive visualization of citation trends
    Route::get('/citation-time-series', \App\Http\Livewire\CitationTimeSeriesViewer::class)->name('citation.time-series');

    // Feedback Dashboard - Learning opportunities and human feedback metrics
    Route::get('/feedback', \App\Http\Livewire\FeedbackDashboard::class)->name('feedback.dashboard');

    // Circuit Breaker Monitor - Service health and circuit breaker status
    Route::get('/circuit-breaker', \App\Http\Livewire\CircuitBreakerMonitor::class)->name('circuit-breaker.monitor');

    // Learning Opportunity Manager - Review low-confidence AI outputs
    Route::get('/learning-opportunities', \App\Http\Livewire\LearningOpportunityManager::class)->name('learning.opportunities');

    // OpenAI Vector Manager - Manage OpenAI vector stores and embeddings
    Route::get('/openai/vectors', \App\Http\Livewire\OpenAIVectorManager::class)->name('openai.vectors');

    // Parallel Timeline - Dual-lane comparative timeline visualization
    Route::get('/parallel-timeline', \App\Http\Livewire\ParallelTimeline::class)->name('parallel.timeline');

    // Collaboration Dashboard - Multi-agent collaboration overview
    Route::get('/collaborations', \App\Http\Livewire\CollaborationDashboard::class)->name('collaborations.dashboard');

    // Agent Performance Dashboard - Agent metrics and performance tracking
    Route::get('/agent/performance', \App\Livewire\AgentPerformanceDashboard::class)->name('agent.performance');

    // Case Views - LegalCase listing, overview, document completeness, and analysis
    Route::get('/cases', \App\Livewire\CaseIndex::class)->name('cases.index');
    Route::get('/case/{case}', \App\Livewire\CaseOverview::class)->name('case.overview');
    Route::get('/case/{case}/completeness', \App\Livewire\CaseFileCompleteness::class)->name('case.completeness');
    Route::get('/case/{case}/analysis', \App\Livewire\CaseAnalysisDashboard::class)->name('case.analysis');
});

// E-Komunikacije Dashboard Routes
Route::middleware(['auth'])->prefix('ekom')->name('ekom.')->group(function () {
    Route::get('/', \App\Http\Livewire\EkomDashboard::class)->name('dashboard');
    Route::get('/predmeti', \App\Http\Livewire\EkomPredmetiList::class)->name('predmeti');
    Route::get('/predmeti/{remoteId}', \App\Http\Livewire\EkomPredmetDetail::class)->name('predmeti.show');
    Route::get('/podnesci', \App\Http\Livewire\EkomPodnesciList::class)->name('podnesci');
    Route::get('/podnesci/create', \App\Http\Livewire\EkomPodnesakCreate::class)->name('podnesci.create');
    Route::get('/otpravci', \App\Http\Livewire\EkomOtpravciList::class)->name('otpravci');
    Route::get('/sync-status', \App\Http\Livewire\EkomSyncStatus::class)->name('sync-status');
});

// Legal Artillery Dashboard Routes
Route::middleware(['auth'])->prefix('legal-artillery')->name('legal-artillery.')->group(function () {
    Route::get('/', \App\Livewire\LegalArtillery\Dashboard::class)->name('dashboard');
    Route::get('/new', \App\Livewire\LegalArtillery\NewGeneration::class)->name('new');
    Route::get('/run/{runId}', \App\Livewire\LegalArtillery\RunDetails::class)->name('run');

    // Download generated document (DOCX or PDF)
    Route::get('/run/{runId}/download/{format?}', function (string $runId, string $format = 'docx') {
        $run = \App\Models\DocumentGenerationRun::findOrFail($runId);

        if ((int) $run->user_id !== \Illuminate\Support\Facades\Auth::id()) {
            abort(403);
        }

        $docxPath = $run->model_config['docx_path'] ?? null;
        if (!$docxPath || !file_exists($docxPath)) {
            abort(404, 'Document file not found.');
        }

        if ($format === 'pdf') {
            $pdfPath = preg_replace('/\.docx$/i', '.pdf', $docxPath);

            // Generate PDF on-demand if it doesn't exist yet
            if (!file_exists($pdfPath)) {
                $renderer = app(\App\Services\LegalArtillery\DocxRenderer::class);
                $pdfPath = $renderer->convertToPdf($docxPath);
            }

            return response()->download($pdfPath);
        }

        return response()->download($docxPath);
    })->name('download')->where('format', 'docx|pdf');
});

// MCP HTTP Endpoint - for Vizra ADK agents (OdlukeAgent) and external MCP clients
// These routes use their own MCP-specific authentication (mcp.auth middleware)
Route::prefix('mcp')->group(function () {
    // Info endpoint is public with rate limiting
    Route::get('/info', [McpHttpController::class, 'info'])
        ->middleware('throttle:60,1')
        ->name('mcp.info');

    // Message endpoint requires MCP authentication and rate limiting
    Route::post('/message', [McpHttpController::class, 'message'])
        ->middleware(['mcp.auth', 'throttle:60,1'])
        ->name('mcp.message');
});

// Health Check Endpoints - Public for monitoring
Route::get('/health/database', function () {
    $healthCheck = app(\App\HealthChecks\DatabaseConnectionPoolHealthCheck::class);
    $result = $healthCheck();

    $statusCode = match ($result->status) {
        'healthy' => 200,
        'degraded' => 200,
        'unhealthy' => 503,
        default => 500,
    };

    return response()->json($result->toArray(), $statusCode);
})->name('health.database');

Route::get('/health/database/detailed', function () {
    $healthCheck = app(\App\HealthChecks\DatabaseConnectionPoolHealthCheck::class);
    $detailed = $healthCheck->getDetailedHealth();

    $statusCode = match ($detailed['status']) {
        'healthy' => 200,
        'degraded' => 200,
        'unhealthy' => 503,
        default => 500,
    };

    return response()->json($detailed, $statusCode);
})->name('health.database.detailed');

require __DIR__.'/../routes/dusk-test.php';
