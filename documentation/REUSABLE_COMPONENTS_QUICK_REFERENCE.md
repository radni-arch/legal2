# Reusable Components - Quick Reference Guide

## Build Legal Reasoning with These Components

### Research Agent Template
```php
use App\Agents\AutonomousResearchAgent;
use App\Services\AgentEvaluationService;

// Create constrained research agent
$agent = app(AutonomousResearchAgent::class);
$run = $agent->startRun(
    objective: "Research employment law termination procedures",
    context: ['jurisdiction' => 'Croatia'],
    constraints: [
        'max_iterations' => 10,
        'token_budget' => 50000,
        'cost_budget' => 10.00,
        'time_limit_seconds' => 600
    ]
);

// Execute with automatic planning and evaluation
$result = $agent->executeRun($run);
// Output: Research report with cited findings
```

### Citation Detection
```php
use App\Services\HrLegalCitationsDetector;

$detector = app(HrLegalCitationsDetector::class);
$citations = $detector->detectAll($text);
// Returns: Law numbers, case numbers, dates, ECLI, gazette refs
```

### Legal Metadata Extraction
```php
use App\Services\LegalMetadata\KeyPhraseExtractor;
use App\Services\LegalMetadata\DocumentTypeClassifier;
use App\Services\LegalMetadata\CourtDetector;
use App\Services\LegalMetadata\PartyDetector;

$classifier = app(DocumentTypeClassifier::class);
$type = $classifier->classify($text); // "Presuda", "Rješenje", etc.

$courtDetector = app(CourtDetector::class);
$court = $courtDetector->extract($text); // "Vrhovni sud", etc.

$partyDetector = app(PartyDetector::class);
$parties = $partyDetector->extract($text); // Plaintiff, defendant, etc.

$extractor = app(KeyPhraseExtractor::class);
$phrases = $extractor->extract($text); // Legal terminology with context
```

### Quality-Gated Legal Reasoning
```php
use App\Services\AgentEvaluationService;

$evaluator = app(AgentEvaluationService::class);
$evaluation = $evaluator->evaluateRun($runId, $output);

// Weighted scoring:
// - Completeness (25%)
// - Citations (25%)
// - Relevance (20%)
// - Quality (15%)
// - Evidence (15%)

if ($evaluation['score'] >= 0.75) {
    // Pass threshold
    publishInsight($output);
}
```

### Hybrid Legal Search
```php
use App\Services\RagOrchestrator;

$rag = app(RagOrchestrator::class);
$results = $rag->retrieve(
    query: "What are termination notice requirements?",
    options: [
        'top_k' => 20,
        'mmr_lambda' => 0.5,  // Balance relevance/diversity
        'rrf_k' => 60,        // Rank fusion parameter
        'corpus_caps' => [
            'laws' => 5,
            'decisions' => 10,
            'cases' => 5
        ]
    ]
);
// Returns chunks with confidence scores and metadata
```

---

## Build Document Generation with These Components

### PDF Template Rendering
```php
use App\Services\PdfRenderer;

$renderer = app(PdfRenderer::class);
$renderer->renderArticle([
    'law_title' => 'Labor Law',
    'law_eli' => 'urn:celex:...',
    'law_pub_date' => '2024-01-01',
    'article_number' => '52',
    'article_html' => '<h2>Article 52...</h2>',
    'source_citation' => 'NN 93/14'
], '/path/to/output.pdf');
```

### OCR Processing Pipeline
```php
use App\Pipelines\Textract\DownloadDriveFileStep;
use App\Pipelines\Textract\UploadInputToS3Step;
use App\Pipelines\Textract\StartAnalysisStep;
use App\Pipelines\Textract\WaitAndFetchStep;
use App\Pipelines\Textract\ReconstructPdfStep;
use App\Pipelines\Textract\PersistReconstructedStep;

// Pipeline orchestrates 13 steps automatically:
// Download → Upload → Analyze → Reconstruct → Persist

// Use case: Convert scanned PDFs to searchable documents
```

### Case Document Ingestion
```php
use App\Services\CaseIngestPipeline;

$ingest = app(CaseIngestPipeline::class);
$result = $ingest->ingest(
    caseId: $case->id,
    docId: 'doc-12345',
    rawText: $extractedText,
    ocrBlocks: $textractBlocks,  // For quality analysis
    options: [
        'chunk_size' => 1200,
        'overlap' => 150,
        'min_confidence' => 0.82,  // Skip embedding if below
        'min_coverage' => 0.75,
        'language' => 'hr'
    ]
);

// Quality gates prevent low-quality embeddings
// Automatic fallback to non-embedded storage
```

### Automatic Legal Metadata
```php
use App\Services\Ocr\LegalMetadataExtractor;

$extractor = app(LegalMetadataExtractor::class);
$metadata = $extractor->extract(
    document: $ocrDocument,
    driveFileId: 'file-id',
    driveFileName: 'decision.pdf'
);

// Returns:
// - documentType: Presuda, Rješenje, etc.
// - citations: All detected citations
// - courts: Identified courts
// - parties: Extracted parties
// - language: Detected language
```

---

## Build Workflow Automation with These Components

### 13-Step OCR Pipeline
```php
// Entire workflow: Drive → S3 → Textract → Layout → Text → Quality → Metadata → Reconstruct → Store

// Use as template for other document workflows
// Middleware pattern with payload passing between steps
// Each step can fail gracefully with optional rollback

Pipeline::send($payload)
    ->through([
        DownloadDriveFileStep::class,
        UploadInputToS3Step::class,
        EnsureJobStep::class,
        StartAnalysisStep::class,
        WaitAndFetchStep::class,
        AnalyzeLayoutStep::class,
        CollectLinesStep::class,
        CheckOcrQualityStep::class,
        CreateMetadataStep::class,
        ReconstructPdfStep::class,
        UploadOutputStep::class,
        PersistReconstructedStep::class,
        SaveResultsStep::class,
    ])
    ->then(fn($result) => $this->handleSuccess($result));
```

### Async Execution with Jobs
```php
use App\Jobs\ExecuteAgentResearch;

// Dispatch async research execution
ExecuteAgentResearch::dispatch($agent, $objective, $constraints);

// Other available jobs:
// - GenerateLawMetadata
// - ProcessDrivePdfJob
// - ReprocessTextractJob
```

### External System Integration
```php
// Court Decisions API
use App\Services\Odluke\OdlukeClient;

$client = OdlukeClient::fromConfig();
$ids = $client->collectIdsFromList(
    query: "employment termination",
    filters: "sort=date&od=2024-01-01",
    limit: 50
);
$metadata = $client->fetchDecisionMeta($ids);

// Ekom Case Filing
use App\Services\EkomService;

$ekom = app(EkomService::class);
$predmeti = $ekom->syncPredmeti(['filter' => 'value']);
$podnesci = $ekom->syncPodnesci(['predmet_id' => '...']);

// Eoglasna Monitoring
use App\Services\EoglasnaService;

$eoglasna = app(EoglasnaService::class);
$notices = $eoglasna->searchNotices($keywords);

// These integrate with repositories for data persistence
```

### Agent Run Tracking
```php
use App\Models\AgentRun;

// Track execution metrics
$run = AgentRun::where('status', 'running')
    ->where('agent_name', 'autonomous_research_agent')
    ->first();

echo "Progress: {$run->current_iteration}/{$run->max_iterations}";
echo "Tokens: {$run->tokens_used} / {$run->token_budget}";
echo "Cost: \${$run->cost_spent} / \${$run->cost_budget}";
echo "Time: {$run->elapsed_seconds}s / {$run->time_limit_seconds}s";

// Query run results
$findings = $run->iterations;
$final = $run->final_output;
$score = $run->score;
```

### Configuration-Driven Behavior
```php
// config/agent.php controls:
config('agent.defaults.max_iterations')        // 10
config('agent.defaults.time_limit_seconds')    // 600
config('agent.defaults.threshold')             // 0.75
config('agent.evaluation.weights')             // Scoring weights
config('agent.safety.max_cost_per_run')        // $5.00
config('agent.safety.max_concurrent_runs')     // 5

// Modify behavior without code changes
// Environment variable overrides available
```

### Knowledge Graph Integration
```php
use App\Services\GraphDatabaseService;
use App\Services\GraphRagService;

// Query legal relationships
$neo = app(GraphDatabaseService::class);
$results = $neo->run('
    MATCH (law:LawDocument {law_number: $num})
    -[:CITES]->(cited:LawDocument)
    RETURN cited.title, cited.law_number
', ['num' => '93/14']);

// Sync decisions to graph
$graphRag = app(GraphRagService::class);
$graphRag->syncDecision($decisionId);

// Graph enables:
// - Citation tracking
// - Relationship discovery
// - Similar document finding
// - Legal logic inference
```

---

## Data Models for Case Management

```php
// Case management
use App\Models\LegalCase;
use App\Models\CaseDocument;
use App\Models\CaseDocumentUpload;

$case = LegalCase::create([
    'case_number' => 'Pr-123/2024',
    'title' => 'Employment Dispute',
    'client_name' => 'Jane Doe',
    'opponent_name' => 'ABC Corp',
    'court' => 'Municipal Court Zagreb',
    'jurisdiction' => 'Croatia',
    'filing_date' => '2024-01-15',
    'status' => 'pending'
]);

// Attach documents with embeddings
$document = $case->documents()->create([
    'doc_id' => 'doc-claim',
    'title' => 'Original Claim',
    'content' => $fullText,
    'embedding_vector' => $embedding,
    'metadata' => ['type' => 'claim', 'language' => 'hr']
]);

// Court decisions with ECLI support
use App\Models\CourtDecision;

$decision = CourtDecision::create([
    'case_number' => 'Gž-123/2024',
    'title' => 'Supreme Court Decision',
    'court' => 'Vrhovni sud RH',
    'decision_date' => '2024-06-15',
    'publication_date' => '2024-06-20',
    'decision_type' => 'Presuda',
    'ecli' => 'ECLI:HR:VSRH:2024:...',
    'finality' => 'final'
]);
```

---

## Search Patterns

```php
use App\Services\LawSearchService;
use App\Services\DecisionSearchService;
use App\Services\CaseSearchService;

// Law search - multiple strategies
$lawService = app(LawSearchService::class);

// Semantic search
$results = $lawService->vectorSearch(
    query: "termination notice period",
    options: ['limit' => 10, 'min_similarity' => 0.7]
);

// Keyword search
$results = $lawService->keywordSearch(
    query: "notice",
    options: ['law_number' => '93/14', 'limit' => 5]
);

// Hybrid (vector + keyword)
$results = $lawService->hybridSearch(
    query: "employment termination",
    options: ['limit' => 20]
);

// Decision search - same patterns
$decisionService = app(DecisionSearchService::class);
$results = $decisionService->vectorSearch("wrongful dismissal");
$results = $decisionService->keywordSearch(
    query: "termination",
    options: ['court' => 'Vrhovni sud', 'date_from' => '2023-01-01']
);

// Case search
$caseService = app(CaseSearchService::class);
$results = $caseService->searchCases("employment dispute");
$docResults = $caseService->searchDocuments("termination clause");
```

---

## Testing Utilities

```php
// 90+ test cases available covering:
// - Agent planning and execution
// - Citation detection accuracy
// - OCR quality gates
// - Search relevance
// - API integration robustness
// - Graph synchronization

// Run test suite:
// php artisan test
// php artisan test --filter AgentTest
// php artisan test --filter Citation
```

---

## Configuration Checklist

```
Required Configuration:
- OPENAI_API_KEY
- OPENAI_ORGANIZATION (optional)
- OPENAI_PROJECT (optional)
- DATABASE_URL (PostgreSQL with pgvector)
- NEO4J_CONNECTION (Graph database)
- AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY (for Textract)
- AWS_BUCKET, AWS_REGION (S3 storage)
- GOOGLE_APPLICATION_CREDENTIALS (Drive API)

Optional Integrations:
- ODLUKE_BASE_URL (Court decision API)
- EKOM_API_BASE_URL (eFilings)
- EOGLASNA_BASE_URL (Court notices)
```

---

## Next Steps

1. **Start with AutonomousResearchAgent** - Most complete example of legal reasoning
2. **Use CaseIngestPipeline** - Quality-gated document processing template
3. **Leverage AgentEvaluationService** - Quality assurance framework
4. **Integrate Citation Detectors** - Improve legal analysis accuracy
5. **Build on RAG Orchestrator** - Advanced search for legal queries
6. **Extend API Integrations** - Connect to more legal data sources

All components follow Laravel patterns and are tested in production.
