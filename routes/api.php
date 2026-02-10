<?php

use App\Http\Controllers\AgentMonitoringController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\AgentCollaborationController;
use App\Http\Controllers\Api\DocumentGenerationController;
use App\Http\Controllers\Api\StreamingChatController;
use App\Http\Controllers\Api\UnifiedSearchController;
use App\Http\Controllers\FactPatternController;
use App\Http\Controllers\GraphVisualizationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HoneypotController;
use App\Http\Controllers\IngestController;
use App\Http\Controllers\McpOpenAIController;
use App\Http\Controllers\McpToolsController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\ReasoningController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StrategyController;
use App\Http\Controllers\TestErrorRecoveryController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

// Health check endpoint - no authentication required (for monitoring tools)
Route::get('health', [HealthController::class, 'index']);
Route::get('health/graph', \App\Http\Controllers\Api\GraphHealthController::class);

// Test endpoint for error recovery testing (rate limiting only - for testing)
Route::post('evidence/analyze', [TestErrorRecoveryController::class, 'analyze'])
    ->middleware(['throttle:60,1']);

// OpenAI API proxy - requires API token authentication + rate limiting
Route::prefix('openai')->middleware(['api.token', 'throttle:openai', 'track.tokens'])->group(function () {
    Route::post('responses', [OpenAIController::class, 'responses']);
    Route::post('chat', [OpenAIController::class, 'chat']);
    Route::post('chat/stream', [StreamingChatController::class, 'stream']);
    Route::post('embeddings', [OpenAIController::class, 'embeddings']);
    Route::post('image', [OpenAIController::class, 'image']);
    Route::post('tts', [OpenAIController::class, 'tts']);
    Route::post('transcribe', [OpenAIController::class, 'transcribe']);

    Route::post('files', [OpenAIController::class, 'filesUpload']);
    Route::get('files', [OpenAIController::class, 'filesList']);
    Route::delete('files/{fileId}', [OpenAIController::class, 'filesDelete']);

    // Assistants
    Route::post('assistants', [OpenAIController::class, 'assistantsCreate']);
    Route::get('assistants', [OpenAIController::class, 'assistantsList']);
    Route::get('assistants/{assistantId}', [OpenAIController::class, 'assistantsRetrieve']);
    Route::delete('assistants/{assistantId}', [OpenAIController::class, 'assistantsDelete']);

    // Vector Stores
    Route::post('vector-stores', [OpenAIController::class, 'vectorStoreCreate']);
    Route::get('vector-stores', [OpenAIController::class, 'vectorStoreList']);
    Route::get('vector-stores/{storeId}', [OpenAIController::class, 'vectorStoreRetrieve']);
    Route::delete('vector-stores/{storeId}', [OpenAIController::class, 'vectorStoreDelete']);
    Route::post('vector-stores/{storeId}/files', [OpenAIController::class, 'vectorStoreAddFile']);
    Route::get('vector-stores/{storeId}/files', [OpenAIController::class, 'vectorStoreListFiles']);
    Route::delete('vector-stores/{storeId}/files/{fileId}', [OpenAIController::class, 'vectorStoreDeleteFile']);
});

// Content ingestion - requires API token authentication
Route::prefix('ingest')->middleware('api.token')->group(function () {
    Route::post('text', [IngestController::class, 'ingestText']);
    Route::post('file', [IngestController::class, 'ingestFile']);
    Route::post('search', [IngestController::class, 'search']);
    Route::post('laws', [IngestController::class, 'ingestLaws']);
});

// File uploads - requires API token authentication
Route::prefix('uploads')->middleware('api.token')->group(function () {
    // direct upload
    Route::post('/', [UploadController::class, 'direct']);

    // chunked upload
    Route::post('start', [UploadController::class, 'start']);
    Route::post('{uploadId}/chunk/{index}', [UploadController::class, 'chunk'])
        ->where(['index' => '[0-9]+']);
    Route::post('{uploadId}/complete', [UploadController::class, 'complete']);
    Route::delete('{uploadId}', [UploadController::class, 'cancel']);
});

/*
|--------------------------------------------------------------------------
| MCP Tools API Routes
|--------------------------------------------------------------------------
|
| HTTP REST API endpoints for MCP tools. These provide HTTP access to the
| same functionality available through the MCP protocol.
|
| Authentication: Use X-MCP-Token header (configured in .env)
| Rate Limiting: Applied per-tool and globally
|
*/

Route::prefix('mcp')->middleware('mcp.auth')->group(function () {
    // Law Tools
    Route::post('law.search', [McpToolsController::class, 'lawSearch'])
        ->middleware('mcp.auth:law.search');
    Route::post('law.get_article', [McpToolsController::class, 'lawGetArticle'])
        ->middleware('mcp.auth:law.get_article');

    // Court Decision Tools
    Route::post('decision.search', [McpToolsController::class, 'decisionSearch'])
        ->middleware('mcp.auth:decision.search');
    Route::post('decision.get', [McpToolsController::class, 'decisionGet'])
        ->middleware('mcp.auth:decision.get');

    // Case Tools (Private - requires authentication)
    Route::post('case.search', [McpToolsController::class, 'caseSearch'])
        ->middleware('mcp.auth:case.search');

});

// MCP-OpenAI Bridge: Exposes MCP tools as OpenAI-compatible function calling endpoints
Route::prefix('mcp-openai')->group(function () {
    // Public info endpoint (no auth required, with rate limiting)
    Route::get('info', [McpOpenAIController::class, 'info'])
        ->middleware('throttle:60,1');

    // Protected endpoints - require API token authentication and rate limiting
    Route::middleware(['mcp.auth', 'throttle:60,1'])->group(function () {
        // Tool discovery and execution
        Route::get('tools', [McpOpenAIController::class, 'listTools']);
        Route::post('tools/execute', [McpOpenAIController::class, 'executeTool']);

        // OpenAI-compatible chat completions with automatic MCP tools injection
        Route::post('chat/completions', [McpOpenAIController::class, 'chatCompletions']);

        // Webhook endpoint for OpenAI function calling callbacks
        Route::post('webhook', [McpOpenAIController::class, 'webhook']);
    });
});

// Autonomous agent endpoints - requires API token authentication + rate limiting
// Sprint 12.5 - Worker C: Controller Migration - Consolidated to Api\AgentController
Route::prefix('agent')->middleware(['api.token', 'throttle:agents'])->group(function () {
    // Research runs
    Route::post('research/start', [\App\Http\Controllers\Api\AgentController::class, 'startResearch']);
    Route::get('research', [\App\Http\Controllers\Api\AgentController::class, 'listResearch']);
    Route::get('research/{id}', [\App\Http\Controllers\Api\AgentController::class, 'getResearch']);
    Route::get('research/{id}/evaluation', [\App\Http\Controllers\Api\AgentController::class, 'getEvaluation']);
    Route::delete('research/{id}', [\App\Http\Controllers\Api\AgentController::class, 'deleteResearch']);
});

// Agent insights endpoints - requires API token authentication
Route::prefix('insights')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Retrieve insights for an agent
    Route::get('{agentName}', [\App\Http\Controllers\InsightsController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Agent Collaboration API Routes
|--------------------------------------------------------------------------
|
| Sprint 3.7: Orchestrator API Endpoints
|
| Endpoints for starting and monitoring multi-agent collaborations.
|
| Features:
| - Request validation via StartCollaborationRequest
| - API token authentication
| - Rate limiting: 10 requests per minute per user
| - Real-time status monitoring
| - Result retrieval upon completion
|
*/

Route::prefix('agents/collaborate')->middleware(['api.token', 'throttle:10,1'])->group(function () {
    // Start a new collaboration
    Route::post('/', [AgentCollaborationController::class, 'start']);

    // Get collaboration status
    Route::get('{id}/status', [AgentCollaborationController::class, 'status']);

    // Get collaboration result
    Route::get('{id}/result', [AgentCollaborationController::class, 'result']);
});

// OdlukeAgent API - requires API token authentication and rate limiting
Route::prefix('odluke-agent')->middleware(['api.token', 'throttle:30,1'])->group(function () {
    Route::post('/execute', [\App\Http\Controllers\OdlukeController::class, 'execute']);
    Route::post('/status', [\App\Http\Controllers\OdlukeController::class, 'status']);
});

/*
|--------------------------------------------------------------------------
| Search API Routes
|--------------------------------------------------------------------------
|
| Unified search API providing access to all legal corpora (laws, decisions, cases).
|
| Features:
| - Request validation via FormRequest classes
| - API token authentication
| - Rate limiting: 60 requests per minute per user
| - Response caching: 5 minutes for frequent queries
| - Supports vector search, hybrid search, and citation-aware search
|
*/

Route::prefix('search')->middleware(['api.token', 'throttle:search'])->group(function () {
    // Unified search across all corpora
    Route::post('/', [SearchController::class, 'search']);

    // Corpus-specific search
    Route::post('/laws', [SearchController::class, 'searchLaws']);
    Route::post('/decisions', [SearchController::class, 'searchDecisions']);
    Route::post('/cases', [SearchController::class, 'searchCases']);

    // Advanced search
    Route::post('/hybrid', [SearchController::class, 'hybridSearch']);
    Route::post('/with-citations', [SearchController::class, 'searchWithCitations']);
});

// Unified Search API (Sprint 13: Worker C)
Route::post('unified-search', [UnifiedSearchController::class, 'search'])
    ->middleware(['api.token', 'throttle:search']);

/*
|--------------------------------------------------------------------------
| Legal Reasoning API Routes
|--------------------------------------------------------------------------
|
| Law reasoning engine endpoints for conflict resolution, citation analysis,
| and logical reasoning.
|
| Features:
| - Conflict detection between laws
| - Authority scoring using graph algorithms
| - Legal logic parsing and deductive reasoning
|
*/

Route::prefix('reasoning')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Conflict resolution
    Route::post('/analyze-conflict', [ReasoningController::class, 'analyzeConflict']);
    Route::post('/resolve-conflict', [ReasoningController::class, 'resolveConflict']);

    // Citation and authority analysis
    Route::post('/authority-score', [ReasoningController::class, 'calculateAuthorityScore']);

    // Legal logic
    Route::post('/parse-logic', [ReasoningController::class, 'parseLogic']);
    Route::post('/apply-deductive', [ReasoningController::class, 'applyDeductive']);
});

/*
|--------------------------------------------------------------------------
| Predictive Analytics API Routes
|--------------------------------------------------------------------------
|
| Predictive analytics endpoints for case outcome prediction, duration
| estimation, and decision impact analysis.
|
| Features:
| - ML-based case outcome prediction
| - Historical analysis for timeline estimation
| - Citation graph analysis for impact metrics
|
*/

Route::prefix('analytics')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Case predictions
    Route::post('/predict-outcome/{caseId}', [AnalyticsController::class, 'predictOutcome']);
    Route::post('/estimate-duration/{caseId}', [AnalyticsController::class, 'estimateDuration']);
    Route::post('/comprehensive/{caseId}', [AnalyticsController::class, 'comprehensiveAnalytics']);

    // Decision impact
    Route::post('/analyze-impact/{decisionId}', [AnalyticsController::class, 'analyzeImpact']);

    // Batch operations
    Route::post('/batch-predict', [AnalyticsController::class, 'batchPredict']);
});

/*
|--------------------------------------------------------------------------
| Legal Strategy API Routes
|--------------------------------------------------------------------------
|
| Strategy building endpoints for legal argument generation, risk assessment,
| and strategic planning.
|
| Features:
| - Comprehensive strategy generation
| - IRAC-based argument building
| - Multi-dimensional risk assessment
| - Phased action planning
|
*/

Route::prefix('strategy')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Strategy building
    Route::post('/build/{caseId}', [StrategyController::class, 'buildStrategy']);
    Route::post('/comprehensive/{caseId}', [StrategyController::class, 'comprehensiveStrategy']);

    // Arguments
    Route::post('/generate-arguments/{caseId}', [StrategyController::class, 'generateArguments']);

    // Risk assessment
    Route::post('/assess-risks/{caseId}', [StrategyController::class, 'assessRisks']);

    // Action planning
    Route::post('/action-plan/{caseId}', [StrategyController::class, 'createActionPlan']);
});

/*
|--------------------------------------------------------------------------
| Fact Pattern Extraction API Routes
|--------------------------------------------------------------------------
|
| Fact pattern extraction endpoints for converting raw legal narratives
| into structured, queryable fact patterns.
|
| Features:
| - LLM-powered fact extraction from narratives
| - Batch processing for multiple narratives
| - Fact pattern comparison and similarity matching
| - Integration with case management system
| - Historical pattern analysis
|
*/

Route::prefix('fact-patterns')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Extract facts from narrative
    Route::post('/extract', [FactPatternController::class, 'extract']);

    // Batch extract multiple narratives
    Route::post('/batch-extract', [FactPatternController::class, 'batchExtract']);

    // List user's fact patterns
    Route::get('/', [FactPatternController::class, 'index']);

    // Get specific fact pattern
    Route::get('/{id}', [FactPatternController::class, 'show']);

    // Delete fact pattern
    Route::delete('/{id}', [FactPatternController::class, 'destroy']);

    // Compare two fact patterns
    Route::post('/compare', [FactPatternController::class, 'compare']);

    // Find similar fact patterns
    Route::post('/{id}/find-similar', [FactPatternController::class, 'findSimilar']);
});

Route::prefix('evidence')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    require __DIR__.'/evidence.php';
});

/*
|--------------------------------------------------------------------------
| Prosecutorial Misconduct Module API Routes
|--------------------------------------------------------------------------
|
| Prosecutorial misconduct detection and legal action generation.
|
| Features:
| - Detect 6 types of prosecutorial misconduct
| - Pattern analysis across case timeline
| - Generate Croatian dismissal motions
| - Generate complaints to State Attorney/Judicial Council/Police
| - Build appeals (žalba, zaštita zakonitosti, ustavna tužba)
|
*/

// Prosecutorial Misconduct Module
require __DIR__.'/misconduct.php';

/*
|--------------------------------------------------------------------------
| Document Generation API Routes
|--------------------------------------------------------------------------
|
| Recursive document writing agent endpoints for generating Croatian legal
| documents using iterative improvement.
|
| Features:
| - Generate 9 types of Croatian legal documents
| - Recursive quality improvement (critic + worker loop)
| - Support for standalone, case-based, and mixed context modes
| - Multi-dimensional quality scoring
|
*/

Route::prefix('documents')->middleware(['api.token', 'throttle:documents'])->group(function () {
    // Generate a new document
    Route::post('generate', [DocumentGenerationController::class, 'generate']);

    // List user's document generation runs
    Route::get('runs', [DocumentGenerationController::class, 'index']);

    // Get specific document generation run
    Route::get('runs/{id}', [DocumentGenerationController::class, 'show']);

    // Approve a document generation run
    Route::post('runs/{id}/approve', [DocumentGenerationController::class, 'approve']);

    // Dispatch an approved document generation run
    Route::post('runs/{id}/dispatch', [DocumentGenerationController::class, 'dispatch']);

    // Preview dispatch payloads for an approved run
    Route::post('runs/{id}/preview-dispatch', [DocumentGenerationController::class, 'previewDispatch']);

    // Export audit report for a document generation run
    Route::get('runs/{id}/audit', [DocumentGenerationController::class, 'audit']);
});

/*
|--------------------------------------------------------------------------
| Topic-Based Abuse Analysis API Routes
|--------------------------------------------------------------------------
|
| Modular topic-based prosecutorial abuse detection system.
| Each topic represents a specific type of abuse pattern.
|
| TOPICS SUPPORTED:
| - drug_charge_severity: Overcharging in drug cases (dealing for personal use amounts)
| - home_search_abuse: Disproportionate home search warrants
| - bail_denial: Excessive bail denial (planned)
| - pretrial_detention: Excessive pre-trial detention (planned)
| - witness_intimidation: Witness intimidation (planned)
|
| EXAMPLE QUESTIONS ANSWERED:
| - "How many dealing charges for <30g cannabis in Osijek?"
| - "Is Osijek worse than Zadar for drug overcharging?"
| - "How many home search warrants for misdemeanors in 2025?"
|
*/

Route::prefix('topics')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // List all available topics
    Route::get('/', [\App\Http\Controllers\TopicController::class, 'listTopics']);

    // Generic topic routes (fallback for dynamic topics)
    Route::post('{topic}/analyze/{caseId}', [\App\Http\Controllers\TopicController::class, 'analyzeTopic']);
    Route::get('{topic}/statistics', [\App\Http\Controllers\TopicController::class, 'getStatistics']);
    Route::get('{topic}/compare-regions', [\App\Http\Controllers\TopicController::class, 'compareRegions']);

    // Illegal Search Analysis Routes
    Route::prefix('illegal_search')->group(function () {
        Route::post('/analyze', [\App\Http\Controllers\Api\Topics\IllegalSearchController::class, 'analyze']);
        Route::get('/statistics', [\App\Http\Controllers\Api\Topics\IllegalSearchController::class, 'statistics']);
        Route::get('/compare-regions', [\App\Http\Controllers\Api\Topics\IllegalSearchController::class, 'compareRegions']);
    });

    // Excessive Pretrial Detention Routes
    Route::prefix('excessive_pretension')->group(function () {
        Route::post('/analyze', [\App\Http\Controllers\Api\Topics\ExcessivePretensionController::class, 'analyze']);
        Route::get('/statistics', [\App\Http\Controllers\Api\Topics\ExcessivePretensionController::class, 'statistics']);
        Route::get('/compare-regions', [\App\Http\Controllers\Api\Topics\ExcessivePretensionController::class, 'compareRegions']);
    });

    // Disproportionate Sentencing Routes
    Route::prefix('disproportionate_sentencing')->group(function () {
        Route::post('/analyze', [\App\Http\Controllers\Api\Topics\DisproportionateSentencingController::class, 'analyze']);
        Route::post('/statistics', [\App\Http\Controllers\Api\Topics\DisproportionateSentencingController::class, 'statistics']);
        Route::post('/compare-regions', [\App\Http\Controllers\Api\Topics\DisproportionateSentencingController::class, 'compareRegions']);
    });
});

/*
|--------------------------------------------------------------------------
| Multi-Agent Collaboration API Routes
|--------------------------------------------------------------------------
|
| Multi-agent collaboration system where specialist agents work together
| to solve complex legal problems.
|
| Features:
| - Research Specialist: Finds relevant laws and court decisions
| - Precedent Analyst: Analyzes applicability of precedents
| - Strategy Specialist: Develops legal strategy and arguments
| - Risk Analyst: Identifies risks and weaknesses
| - Orchestrated workflow with shared memory and inter-agent communication
|
*/

Route::prefix('collaboration')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Solve legal problem with multi-agent team
    Route::post('/solve', [\App\Http\Controllers\CollaborationController::class, 'solve']);

    // Get collaboration details
    Route::get('/{id}', [\App\Http\Controllers\CollaborationController::class, 'show']);

    // Get recent collaborations
    Route::get('/recent', [\App\Http\Controllers\CollaborationController::class, 'recent']);

    // Get collaboration statistics
    Route::get('/stats', [\App\Http\Controllers\CollaborationController::class, 'stats']);
});

/*
|--------------------------------------------------------------------------
| Agent Monitoring API Routes
|--------------------------------------------------------------------------
|
| Monitor health and performance of agent jobs.
|
| Features:
| - System health checks with failure rate analysis
| - Agent statistics (research agent, decision discovery)
| - Recent run history
| - Failed job tracking for debugging
|
*/

Route::prefix('monitoring')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Agent-specific health and statistics (legacy endpoints)
    Route::get('/health', [AgentMonitoringController::class, 'health']);
    Route::get('/statistics', [AgentMonitoringController::class, 'statistics']);
    Route::get('/recent-runs', [AgentMonitoringController::class, 'recentRuns']);
    Route::get('/failed-jobs', [AgentMonitoringController::class, 'failedJobs']);

    // Comprehensive system health checks
    Route::get('/health/system', [MonitoringController::class, 'systemHealth']);
    Route::get('/health/database', [MonitoringController::class, 'databaseHealth']);
    Route::get('/health/neo4j', [MonitoringController::class, 'neo4jHealth']);
    Route::get('/health/openai', [MonitoringController::class, 'openaiHealth']);
    Route::get('/health/cache', [MonitoringController::class, 'cacheHealth']);
    Route::get('/health/queue', [MonitoringController::class, 'queueHealth']);

    // Metrics endpoints
    Route::get('/metrics/rate-limits', [MonitoringController::class, 'rateLimitMetrics']);
    Route::get('/metrics/tokens', [MonitoringController::class, 'tokenMetrics']);

    // Performance and error monitoring (existing MonitoringController endpoints)
    Route::get('/performance', [MonitoringController::class, 'performance']);
    Route::get('/system', [MonitoringController::class, 'system']);
    Route::get('/error-rate', [MonitoringController::class, 'errorRate']);
    Route::get('/response-times', [MonitoringController::class, 'responseTimes']);
    Route::get('/alerts', [MonitoringController::class, 'alerts']);
    Route::delete('/alerts/{type}', [MonitoringController::class, 'clearAlert']);
    Route::delete('/alerts', [MonitoringController::class, 'clearAllAlerts']);
    Route::post('/cleanup', [MonitoringController::class, 'cleanup']);
});

/*
|--------------------------------------------------------------------------
| Graph Visualization API Routes
|--------------------------------------------------------------------------
|
| Neo4j graph visualization endpoints for exploring citation networks,
| relationships, and graph topology.
|
| Features:
| - Interactive node visualization with depth control
| - Filtered subgraph extraction by node type and properties
| - Real-time graph statistics (node/relationship counts)
|
*/

Route::prefix('graph')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Visualize node with neighbors
    Route::get('/visualize/{nodeId}', [GraphVisualizationController::class, 'visualize']);

    // Get filtered subgraph
    Route::post('/subgraph', [GraphVisualizationController::class, 'subgraph']);

    // Get graph statistics
    Route::get('/stats', [GraphVisualizationController::class, 'stats']);
});

/*
|--------------------------------------------------------------------------
| Decision Discovery API Routes
|--------------------------------------------------------------------------
|
| Discovery and ingestion of court decisions from odluke.sudovi.hr
|
| Features:
| - Search decisions by keywords, court, date range, and decision type
| - Preview decisions before ingesting
| - Batch ingest selected decisions
| - Track discovery progress and statistics
|
| Sprint 7: UX Features Completion - Worker G
|
*/

Route::prefix('decisions')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Start new discovery search
    Route::post('/discover', [\App\Http\Controllers\DecisionDiscoveryController::class, 'discover']);

    // List all discoveries
    Route::get('/discoveries', [\App\Http\Controllers\DecisionDiscoveryController::class, 'listDiscoveries']);

    // Get discovery details
    Route::get('/discoveries/{id}', [\App\Http\Controllers\DecisionDiscoveryController::class, 'getDiscovery']);

    // Ingest selected decisions from discovery
    Route::post('/discoveries/{id}/ingest', [\App\Http\Controllers\DecisionDiscoveryController::class, 'ingestDecisions']);

    // Get discovery statistics
    Route::get('/discoveries/{id}/stats', [\App\Http\Controllers\DecisionDiscoveryController::class, 'getDiscoveryStatistics']);
});

/*
|--------------------------------------------------------------------------
| Explainability / Reasoning Trace API Routes
|--------------------------------------------------------------------------
|
| Sprint 2.4: Basic Reasoning Trace Integration
|
| API endpoints for retrieving AI reasoning traces for transparency,
| debugging, and understanding agent decision-making processes.
|
| Features:
| - Retrieve complete trace trees with nested reasoning steps
| - View hierarchical agent execution traces
| - Inspect confidence scores, token usage, and performance metrics
|
*/

Route::prefix('explainability')->group(function () {
    // Get full trace tree for a given trace ID
    Route::get('/trace/{traceId}', [\App\Http\Controllers\Api\TraceViewerController::class, 'show']);
});

// Sprint 5.2: Learning Feedback API - requires authentication
Route::prefix('learning')->middleware('auth:api')->group(function () {
    Route::post('/feedback/{opportunityId}', [\App\Http\Controllers\Api\LearningFeedbackController::class, 'submitFeedback']);
});

/*
|--------------------------------------------------------------------------
| Log File API Routes
|--------------------------------------------------------------------------
|
| Log file viewing endpoints for agent-driven error debugging.
| Allows AI agents to consume recent errors from laravel.log.
|
*/

Route::prefix('logs')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    Route::get('/files', [\App\Http\Controllers\Api\LogFileController::class, 'files']);
    Route::get('/entries', [\App\Http\Controllers\Api\LogFileController::class, 'entries']);
});

Route::middleware('honeypot')->group(function () {
    // Fake admin endpoints (most commonly targeted)
    Route::post('admin/login', [HoneypotController::class, 'adminLogin']);
    Route::get('admin/users', [HoneypotController::class, 'adminUsers']);
    Route::get('admin/config', [HoneypotController::class, 'config']);
    Route::get('admin/backup', [HoneypotController::class, 'databaseBackup']);
    Route::post('admin/backup', [HoneypotController::class, 'databaseBackup']);
    Route::get('admin/keys', [HoneypotController::class, 'apiKeys']);

    // Fake configuration and environment files
    Route::get('.env', [HoneypotController::class, 'envFile']);
    Route::get('env', [HoneypotController::class, 'envFile']);
    Route::get('config/database', [HoneypotController::class, 'config']);
    Route::get('config/app', [HoneypotController::class, 'config']);
    Route::get('.git/config', [HoneypotController::class, 'gitConfig']);

    // Fake debug endpoints
    Route::get('debug', [HoneypotController::class, 'debugInfo']);
    Route::get('debug/info', [HoneypotController::class, 'debugInfo']);
    Route::get('phpinfo', [HoneypotController::class, 'phpInfo']);
    Route::get('phpinfo.php', [HoneypotController::class, 'phpInfo']);
    Route::get('info.php', [HoneypotController::class, 'phpInfo']);

    // Fake database endpoints
    Route::get('database/dump', [HoneypotController::class, 'databaseDump']);
    Route::get('db/backup', [HoneypotController::class, 'databaseBackup']);
    Route::get('backup.sql', [HoneypotController::class, 'databaseDump']);

    // Fake sensitive data endpoints
    Route::get('users/passwords', [HoneypotController::class, 'userPasswords']);
    Route::get('api/keys', [HoneypotController::class, 'apiKeys']);
    Route::get('api/tokens', [HoneypotController::class, 'apiKeys']);
    Route::get('credentials', [HoneypotController::class, 'config']);

    // Fake vulnerable endpoints (SQL injection, command execution)
    Route::get('user', [HoneypotController::class, 'sqlVulnerable']);
    Route::post('user', [HoneypotController::class, 'sqlVulnerable']);
    Route::get('exec', [HoneypotController::class, 'execCommand']);
    Route::post('exec', [HoneypotController::class, 'execCommand']);
    Route::post('cmd', [HoneypotController::class, 'execCommand']);

    // Fake AWS/Cloud endpoints
    Route::get('aws/credentials', [HoneypotController::class, 's3Upload']);
    Route::get('s3/config', [HoneypotController::class, 's3Upload']);
    Route::post('upload/s3', [HoneypotController::class, 's3Upload']);

    // Fake internal documentation
    Route::get('docs/internal', [HoneypotController::class, 'internalDocs']);
    Route::get('api/docs/private', [HoneypotController::class, 'internalDocs']);

    // Common WordPress admin paths (commonly scanned)
    Route::any('wp-admin', [HoneypotController::class, 'adminLogin']);
    Route::any('wp-login.php', [HoneypotController::class, 'adminLogin']);
    Route::any('wp-admin/admin-ajax.php', [HoneypotController::class, 'adminLogin']);

    // Common paths attackers look for
    Route::any('phpmyadmin', [HoneypotController::class, 'adminLogin']);
    Route::any('pma', [HoneypotController::class, 'adminLogin']);
    Route::any('mysql', [HoneypotController::class, 'databaseDump']);
    Route::any('adminer.php', [HoneypotController::class, 'adminLogin']);
});

/*
|--------------------------------------------------------------------------
| EKOM Internal API Routes
|--------------------------------------------------------------------------
|
| Internal REST API for programmatic access to EKOM data (Croatian e-Komunikacija
| court system integration).
|
| Features:
| - List/search/filter predmeti (cases)
| - Get single predmet by remote_id
| - List/filter podnesci (submissions)
| - List/filter otpravci (dispatches) including pending filter
| - Trigger manual sync of all EKOM entities
|
*/

Route::prefix('ekom')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // Predmeti (cases)
    Route::get('/predmeti', [\App\Http\Controllers\Api\EkomController::class, 'listPredmeti'])->name('api.ekom.predmeti.index');
    Route::get('/predmeti/{remoteId}', [\App\Http\Controllers\Api\EkomController::class, 'showPredmet'])->name('api.ekom.predmeti.show');

    // Podnesci (submissions)
    Route::get('/podnesci', [\App\Http\Controllers\Api\EkomController::class, 'listPodnesci'])->name('api.ekom.podnesci.index');

    // Otpravci (dispatches)
    Route::get('/otpravci', [\App\Http\Controllers\Api\EkomController::class, 'listOtpravci'])->name('api.ekom.otpravci.index');

    // Sync trigger
    Route::post('/sync', [\App\Http\Controllers\Api\EkomController::class, 'triggerSync'])->name('api.ekom.sync');
});
