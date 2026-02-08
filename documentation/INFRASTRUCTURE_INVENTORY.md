# AI Legal War Machine - Reusable Infrastructure Inventory

## Executive Summary

This Laravel-based legal AI system has **extensive reusable infrastructure** for building legal reasoning, document generation, and workflow automation. The codebase includes 3 production agents, 50+ specialized legal services, comprehensive document processing pipelines, graph database integration, and evaluation frameworks.

---

## 1. EXISTING AGENTS (Beyond OdlukeAgent)

### 1.1 AutonomousResearchAgent
**Location:** `/app/Agents/AutonomousResearchAgent.php`
**Purpose:** Self-directed legal research with plan→act→evaluate loops

**Key Features:**
- Budget-constrained execution (tokens, cost, time, iterations)
- Multi-source legal research (laws, decisions, cases)
- LLM-driven planning with fallback strategies
- Insight extraction with proper citations
- Agent run tracking and evaluation
- Vector memory integration

**Database Models:**
- `AgentRun`: Tracks research execution with metrics
- `AgentVectorMemory`: Persistent memory for insights

**Reusable Patterns:**
```php
// Constraint-based execution model
$run = $agent->startRun($objective, $context, [
    'token_budget' => 50000,
    'cost_budget' => 10.00,
    'time_limit_seconds' => 600,
    'max_iterations' => 10
]);

// Plan-Act-Evaluate loop
// Evaluation uses weighted scoring: completeness (25%), citations (25%), relevance (20%), quality (15%), evidence (15%)
```

### 1.2 DecisionDiscoveryAgent
**Location:** `/app/Agents/DecisionDiscoveryAgent.php`
**Purpose:** Autonomous discovery and ingestion of court decisions

**Key Features:**
- LLM-generated research topics (cached weekly)
- Batch decision scoring (0-100)
- Relevance filtering with configurable thresholds
- Automatic graph synchronization
- Discovery run tracking with statistics

**Reusable Patterns:**
```php
// Topic generation → Search → Score → Filter → Ingest
// Configurable scoring: court authority, decision type, recency
// Batch processing for efficiency
```

### 1.3 OdlukeAgent
**Location:** `/app/Agents/OdlukeAgent.php`
**Purpose:** Interactive court decision search and retrieval

**Key Features:**
- MCP tool integration for stateless operation
- Direct API tools (no server dependency)
- Search, metadata fetching, downloading
- Configurable base URLs for court systems

---

## 2. AI/LLM INTEGRATION PATTERNS

### 2.1 OpenAI Service
**Location:** `/app/Services/OpenAIService.php`
**Features:**
- Full OpenAI API integration
- Request/response logging
- Configurable organization/project IDs
- Timeout and retry handling
- Built-in circuit breaker
- Cost tracking

### 2.2 Chat Message Framework
**Location:** `/app/Services/AI/LLM/ChatMessage.php`
**Features:**
- Grounded responses with citations
- Confidence scoring (0-1)
- Custom metadata support
- JSON schema support for structured responses
- Citation formatting (Croatian-ready)
- Built-in response schema builder

**Example Usage:**
```php
$msg = ChatMessage::grounded(
    content: "...",
    citations: [...],
    confidence: 0.85,
    metadata: ['source' => 'law']
);

// Format citations as markdown
echo $msg->formatCitations();
```

### 2.3 Models Used
- **Production:** `gpt-4o-mini` (all agents)
- **Embeddings:** `text-embedding-3-small`
- **Inference:** Supports JSON output, function calling, streaming

### 2.4 LLM-Driven Features
- **Query Rewriting:** `QueryRewriter` service
- **Query Normalization:** `QueryNormalizer` service
- **Insight Extraction:** With proper Croatian legal citations
- **Decision Scoring:** Batch evaluation with thresholds
- **Topic Generation:** Intelligent research topic suggestions

---

## 3. DOCUMENT GENERATION & TEMPLATING SYSTEMS

### 3.1 PDF Rendering Pipeline
**Location:** `/app/Services/PdfRenderer.php`
**Template:** `/resources/views/pdf/article.blade.php`
**Features:**
- Laravel Blade templating (DOMPDF backend)
- Article extraction and rendering
- Per-article PDF generation
- Context: law title, ELI, publication date, article HTML, citations
- Memory-efficient rendering (cleanup after each article)

**Reusable Pattern:**
```php
$pdf = Pdf::loadHTML($html)
    ->setPaper('a4', 'portrait');
$pdf->save($destPath);
```

### 3.2 OCR Document Reconstruction
**Location:** `/app/Services/Ocr/TextractPdfReconstructor.php`
**Features:**
- AWS Textract integration
- Layout analysis and reconstruction
- Spatial positioning preservation
- Page break handling
- Quality-aware reconstruction

### 3.3 Document Metadata Extraction
**Location:** `/app/Services/Ocr/LegalMetadataExtractor.php`
**Features:**
- Automatic legal document classification
- Citation detection (all types)
- Party/court extraction
- Language detection
- Comprehensive legal metadata model

### 3.4 Template Support Infrastructure
- Blade templating system for court documents
- Configurable document templates
- Metadata-driven content rendering
- Multi-language support (Croatian focus)

---

## 4. WORKFLOW & PIPELINE INFRASTRUCTURE

### 4.1 Textract Processing Pipeline
**Location:** `/app/Pipelines/Textract/`
**Stages:** 13 orchestrated pipeline steps
```
1. DownloadDriveFile      - Get file from Google Drive
2. UploadInputToS3        - Stage file for AWS Textract
3. EnsureJob              - Create or retrieve Textract job
4. StartAnalysis          - Trigger async text detection
5. WaitAndFetch           - Poll and retrieve results
6. AnalyzeLayout          - Spatial analysis
7. CollectLines           - Text extraction
8. CheckOcrQuality        - Quality gate validation
9. CreateMetadata         - Legal metadata extraction
10. ReconstructPdf        - Rebuild PDF with text
11. UploadOutput          - Store reconstructed file
12. PersistReconstucted   - Save to case documents
13. SaveResults           - Finalize document records
```

**Reusable Pattern:** Pipeline step middleware architecture with payload passing

### 4.2 Case Document Ingestion Pipeline
**Location:** `/app/Services/CaseIngestPipeline.php`
**Features:**
- OCR quality validation
- Language normalization (Croatian)
- Chunking with overlap
- Embedding generation
- Quality gates (confidence, coverage thresholds)
- Graceful degradation on embedding failure

**Configuration:**
```php
'chunk_size' => 1200,
'overlap' => 150,
'min_confidence' => 0.82,
'min_coverage' => 0.75,
'skip_embedding_on_low_quality' => false
```

### 4.3 Law Ingest Pipeline
**Location:** `/app/Services/LawIngestService.php`
**Features:**
- Law document parsing
- Article extraction and chunking
- Metadata generation
- Embedding synchronization
- Law registry management

### 4.4 Job Queue System
**Location:** `/app/Jobs/`
**Available Jobs:**
- `ExecuteAgentResearch` - Run agents asynchronously
- `GenerateLawMetadata` - Extract legal metadata
- `ProcessDrivePdfJob` - Drive file processing
- `ReprocessTextractJob` - Retry failed OCR jobs

---

## 5. EVALUATION & SCORING SYSTEMS

### 5.1 Agent Evaluation Service
**Location:** `/app/Services/AgentEvaluationService.php`
**Evaluation Criteria (Weighted):**
- **Completeness** (25%): Does output address objective?
- **Citations** (25%): Proper legal citations (NN format, article numbers)
- **Relevance** (20%): Alignment with objective
- **Quality** (15%): Writing quality, accuracy
- **Evidence** (15%): Supporting action evidence

**Citation Requirements:**
- Min: 1 for acceptable (60%)
- Good: 3+ for 80% score
- Excellent: 5+ for full credit

**Quality Requirements:**
- Min: 50 words acceptable
- Good: 100+ words (80%)
- Excellent: 200+ words (100%)

### 5.2 OCR Quality Analyzer
**Location:** `/app/Services/Ocr/OcrQualityAnalyzer.php`
**Metrics:**
- Confidence scores per page
- Coverage analysis
- Low-confidence page tracking
- Quality gates with thresholds

### 5.3 Confidence Scoring
- Embedded in `ChatMessage` (0-1 range)
- Citation-based confidence
- Source reliability weighting
- Integrated into RAG results

---

## 6. LEGAL ANALYSIS FEATURES

### 6.1 Citation Detection System
**Location:** `/app/Services/LegalCitations/`
**Detectors Available:**
- `StatuteCitationDetector`: Law citations (NN format)
- `CaseNumberDetector`: Court case numbers
- `DateDetector`: Decision dates (YYYY-MM-DD parsing)
- `EcliDetector`: ECLI standard citations
- `NarodneNovineDetector`: Official gazette citations
- `HrLegalCitationsDetector`: Comprehensive Croatian citation detector

**Features:**
- Regex-based pattern matching
- Context extraction
- Citation validation
- Support for Croatian legal terminology

### 6.2 Legal Metadata Extraction
**Detectors:**
- `DocumentTypeClassifier`: Presuda, Rješenje, Optužnica, etc.
- `CourtDetector`: Court identification (Vrhovni sud, Županijski, Općinski)
- `PartyDetector`: Plaintiff/defendant/representative extraction
- `KeyPhraseExtractor`: Domain-specific phrase recognition

**Legal Phrase Dictionary (180+ terms):**
- Procedure types: kazneni/parnicni postupak, pravilna/nepravilna
- Remedies: žalba, revizija, kasacija
- Procedural elements: članak, stavak, točka, alineja
- Parties: tužitelj, tuženik, okrivljenik, odvjetnik

### 6.3 Graph RAG Service
**Location:** `/app/Services/GraphRagService.php`
**Features:**
- Law document → Neo4j nodes
- Case → Node creation
- Citation relationship extraction
- Keyword linking
- Similarity relationships
- Jurisdiction hierarchy

### 6.4 RAG Orchestrator
**Location:** `/app/Services/RagOrchestrator.php`
**Features:**
- Query normalization
- Hybrid retrieval (vector + keyword + graph)
- Reciprocal Rank Fusion (RRF)
- Maximal Marginal Relevance (MMR) for diversity
- Per-corpus result capping
- Confidence scoring
- Metadata enrichment

**Configuration:**
```php
DEFAULT_TOP_K = 20
DEFAULT_MMR_LAMBDA = 0.5  // Balance relevance/diversity
DEFAULT_RRF_K = 60
MIN_CONFIDENCE_THRESHOLD = 0.3
```

---

## 7. DATABASE SCHEMAS FOR CASE MANAGEMENT

### 7.1 Case Management Tables
**Primary Tables:**
- `cases` - Legal cases with status, court, jurisdiction
- `cases_documents` - Case documents with embeddings
- `cases_documents_uploads` - Upload tracking with integrity checks

**Attributes:**
- Case number, title, client, opponent, court, judge
- Filing date, status, tags, description
- Document content, chunks, embeddings, metadata

### 7.2 Court Decision Tables
**Primary Tables:**
- `court_decisions` - Decision metadata
- `court_decision_documents` - Decision document content
- `court_decision_document_uploads` - Upload tracking

**Attributes:**
- Case number, title, court, judge
- Decision date, publication date, type, ECLI
- Register, finality status, jurisdiction

### 7.3 Law Tables
**Primary Tables:**
- `laws` - Law documents with embeddings
- `ingested_laws` - Ingestion tracking
- `law_uploads` - Upload source tracking

**Attributes:**
- Law number, title, jurisdiction
- Content chunks with embeddings
- Effective date, promulgation date
- Language, metadata

### 7.4 Agent Tracking Tables
- `agent_runs` - Research execution records
- `agent_vector_memory` - Persistent agent memories
- `decision_discovery_runs` - Decision discovery runs

### 7.5 External System Tables
**Eoglasna Monitoring:**
- `eoglasna_notices` - Court notices
- `eoglasna_keywords` - Watched keywords
- `eoglasna_keyword_matches` - Match tracking
- `eoglasna_osijek_monitoring` - Court-specific monitoring

**Ekom Integration:**
- `ekom_predmeti` - Case objects
- `ekom_podnesci` - Legal documents filed
- `ekom_otpravci` - Document dispatches

### 7.6 Vector Storage
**Embeddings:**
- pgvector (PostgreSQL) support
- Multiple embedding models supported
- Similarity search via vector operations
- Configurable embedding dimensions

### 7.7 Neo4j Graph Schema
**Node Types:**
- `LawDocument` - Laws with chunks
- `CourtDecision` - Decisions
- `LegalCase` - Cases
- `Jurisdiction` - Jurisdictions
- `Court` - Court entities
- `Party` - Legal parties
- `Citation` - Citation nodes

**Relationships:**
- `CITES` - Document citations
- `APPLIES_TO` - Law applications
- `DECIDED_IN` - Court decisions
- `BELONGS_TO_JURISDICTION`
- `REFERENCES` - General references
- `SIMILAR` - Similar documents

---

## 8. API INTEGRATIONS & EXTERNAL SYSTEMS

### 8.1 Court Decision API (Odluke.sudovi.hr)
**Service:** `OdlukeClient` / `OdlukeIngestService`
**Features:**
- Search by keyword, filters (court, type, date range)
- Metadata fetching for decisions
- PDF/HTML download with caching
- Circuit breaker pattern (fail after 3 failures)
- Rate limiting (configurable RPM)
- Exponential backoff with monotonic spacing
- Robust error handling with retry

**Configuration:**
```php
'rpm' => 30,                    // Requests per minute
'timeout' => 30,                // Socket timeout
'retry' => 2,                   // Retry attempts
'backoff_ms' => 800             // Backoff time
```

### 8.2 Croatian Law Registry (Zakon.hr)
**Services:** `ZakonHrScraper` / `ZakonHrIngestService`
**Features:**
- Web scraping for law documents
- Article extraction
- Law registry lookup
- Metadata extraction

### 8.3 Ekom Integration (Croatian eFilings)
**Services:** `EkomService` / `EkomApiClient`
**Features:**
- Predmeti (case objects) sync
- Podnesci (documents) retrieval
- Otpravci (dispatches) tracking
- Bidirectional document creation (templates exist)
- Repository pattern for data management

**Repositories:**
- `EkomPredmetRepository`
- `EkomPodnesakRepository`
- `EkomOtpravakRepository`

### 8.4 Eoglasna Monitoring (Croatian Court Notices)
**Services:** `EoglasnaService`
**Features:**
- Court notice monitoring
- Keyword watching with regex support
- Notice matching and tracking
- Court registry sync
- Osijek-specific monitoring

### 8.5 Google Drive Integration
**Service:** `GoogleDriveService`
**Features:**
- File listing and download
- PDF pipeline integration
- Document staging

### 8.6 AWS Services
**Integration Points:**
- **S3:** File storage, input/output staging
- **Textract:** Document OCR and layout analysis

---

## 9. TOOL & MCP INFRASTRUCTURE

### 9.1 MCP Tools
**Location:** `/app/Mcp/Tools/`

The system provides 15 MCP (Model Context Protocol) tools organized by domain:

#### Law Tools (3)
- **`LawSearchTool`** (`law.search`) - Search laws with keyword/vector/hybrid search, supports filters (jurisdiction, country, language, law_number, tags), returns paginated results
- **`LawGetArticleTool`** (`law.get_article`) - Retrieve specific law articles by doc_id and chunk number, supports filtering by chapter/section
- **`StatutoryInterpretationTool`** (`statute.interpret`) - Statutory interpretation with operations: legislative history analysis, statutory construction methods (textualist, purposivist, originalist, pragmatic), conflict resolution, regulatory framework mapping

#### Court Decision Tools (9)
- **`DecisionSearchTool`** (`decision.search`) - Search court decisions with keyword/vector/hybrid search, extensive filters (case_number, court, jurisdiction, judge, decision_type, ecli, date ranges)
- **`DecisionGetTool`** (`decision.get`) - Retrieve specific court decision by ID with optional document content
- **`DecisionExtractCitationsTool`** (`decision.extract_citations`) - Extract all legal citations (statutes, case numbers, ECLI) with categorization and statistics
- **`DecisionAnalyzeCitationsTool`** (`decision.analyze_citations`) - Comprehensive citation analysis including graph-based context and similar citation patterns
- **`DecisionExtractFactsTool`** (`decision.extract_facts`) - Extract structured legal facts (parties, issues, holdings, arguments, evidence, procedural history) using LLM with caching
- **`DecisionCompareFactsTool`** (`decision.compare_facts`) - Compare legal facts between two decisions to identify similarities
- **`DecisionFindSimilarTool`** (`decision.find_similar`) - Find similar decisions using content embeddings, citation patterns, and graph relationships
- **`DecisionSearchByCitedLawTool`** (`decision.search_by_cited_law`) - Find decisions citing specific law and article with optional court/jurisdiction/date filters
- **`CitationNetworkTool`** (`citation.network`) - Citation network analysis: graph traversal, authority scoring, pattern analysis, influence mapping

#### Case Management Tools (2) [PRIVATE]
- **`CaseSearchTool`** (`case.search`) - Search legal cases and documents (keyword/vector), filter by case_id, case_number, client, opponent, court, jurisdiction, status, tags. *Requires authentication*
- **`CaseAnalysisTool`** (`case.analyze`) - Comprehensive case analysis: strength assessment, risk analysis, timeline reconstruction, evidence evaluation with recommendations. *Requires authentication*

#### Legal Analysis Tools (1)
- **`LegalConceptTool`** (`legal.concept`) - Legal concept analysis: definition lookup, related concepts discovery, precedent identification, doctrinal analysis across jurisdictions

### 9.2 OpenAI Integration
**Location:** `/app/Services/McpToOpenAIBridge.php`
**Purpose:** Bridge between MCP tools and OpenAI function calling

### 9.3 Agent Toolbox
**Location:** `/app/Services/AgentToolbox.php`
**Methods:**
- `vectorSearch()` - Multi-corpus semantic search
- `graphQuery()` - Neo4j Cypher execution
- `webFetch()` - HTTP retrieval
- `noteSave()` - Vector memory persistence

---

## 10. SEARCH & RETRIEVAL INFRASTRUCTURE

### 10.1 Unified Search Service
**Location:** `/app/Services/UnifiedSearchService.php`
**Features:**
- Multi-corpus search coordination
- Hybrid search (vector + keyword)
- Result merging and ranking
- Configurable search options

### 10.2 Law Search Service
**Location:** `/app/Services/LawSearchService.php`
**Methods:**
- `vectorSearch()` - Semantic search
- `keywordSearch()` - Exact matching
- `hybridSearch()` - Combined approach
- `lookupByNumber()` - Direct law lookup
- `lookupByDocId()` - Document lookup

### 10.3 Decision Search Service
**Location:** `/app/Services/DecisionSearchService.php`
**Methods:**
- Vector/keyword/hybrid search
- Lookup by court, case number, dates
- Direct ID retrieval

### 10.4 Case Search Service
**Location:** `/app/Services/CaseSearchService.php`
**Methods:**
- Case search and filtering
- Document search within cases
- Vector-based case matching

### 10.5 Vector Store Services
- `LawVectorStoreService` - Law embeddings management
- `CaseVectorStoreService` - Case document embeddings
- `CourtDecisionVectorStoreService` - Decision embeddings
- Support for pgvector (PostgreSQL)

---

## 11. LEGAL DOCUMENT PROCESSING

### 11.1 OCR & Layout Services
**Services:**
- `OcrService` - Main OCR orchestration
- `OcrQualityAnalyzer` - Quality metrics
- `TextractLayoutAnalyzer` - Spatial analysis
- `OcrDocument/OcrPage/OcrLine/OcrBox` - Data models

### 11.2 Language Normalization
**Service:** `HrLanguageNormalizer`
**Features:**
- Croatian diacritics normalization
- Special character handling
- Case normalization
- Context-aware corrections

### 11.3 PDF Processing
**Services:**
- `PdfMerger` - Document merging
- `PdfRenderer` - PDF generation from Blade templates
- `PdfArticleSplitter` - Law article extraction
- `TextractPdfReconstructor` - PDF text integration

---

## 12. QUERY PROCESSING INFRASTRUCTURE

### 12.1 Query Normalization
**Service:** `QueryNormalizer`
**Features:**
- Text normalization
- Diacritics handling
- Stop word removal (optional)
- Query expansion (optional)

### 12.2 Query Rewriting
**Service:** `QueryRewriter`
**Features:**
- LLM-based query optimization
- Multi-language support
- Query expansion for coverage
- Semantic query refinement

---

## 13. CONFIGURATION FRAMEWORK

### 13.1 Agent Configuration
**Location:** `config/agent.php`
**Areas:**
- Execution defaults (iterations, time, tokens)
- Evaluation weights and thresholds
- Toolbox settings
- Scheduling configuration
- Performance tuning
- Safety limits
- Logging levels

### 13.2 Service Configurations
- `config/openai.php` - OpenAI settings
- `config/neo4j.php` - Graph database
- `config/ekom.php` - Ekom integration
- `config/eoglasna.php` - Court monitoring
- `config/odluke.php` - Court decision API
- `config/mcp.php` - MCP server config

---

## 14. LIVEWIRE COMPONENTS (UI PATTERNS)

**Interactive Components:**
- `TextractManager` - Document processing UI
- `UnifiedSearch` - Search interface
- `OpenAILogViewer` - API log inspection
- `OpenAIResponsesViewer` - Response analysis
- `DecisionDiscoveryDashboard` - Discovery monitoring
- `TimelinePage` / `ComparativeTimeline` - Event visualization
- `EoglasnaMonitoring` - Notice tracking
- `IngestedLawsManager` - Law library management

**Patterns Available:**
- Real-time search with debouncing
- Async file processing with progress
- Interactive data tables
- Graph visualization
- Timeline views

---

## 15. TESTING & VALIDATION INFRASTRUCTURE

**Test Suites:** 90+ tests covering:
- Agent planning and execution
- Service integration (Odluke, Ekom, Eoglasna)
- OCR quality analysis
- Citation detection
- RAG accuracy
- Search functionality
- MCP tool schemas

---

## SUMMARY: KEY REUSABLE COMPONENTS FOR YOUR USE CASE

### For Legal Reasoning:
1. **AutonomousResearchAgent** - Self-directed research template
2. **AgentEvaluationService** - Quality evaluation framework
3. **Citation detectors** - Legal citation identification
4. **RAG orchestrator** - Hybrid retrieval architecture
5. **Legal metadata extractors** - Document classification

### For Document Generation:
1. **PdfRenderer** - Blade template rendering
2. **Textract pipeline** - OCR and reconstruction
3. **CaseIngestPipeline** - Quality-gated processing
4. **Document templates** - Blade views for legal docs
5. **Metadata extraction** - Automatic enrichment

### For Workflow Automation:
1. **Pipeline framework** - 13-step orchestration pattern
2. **Job queue system** - Async execution
3. **Agent run tracking** - Execution monitoring
4. **Configuration framework** - Constraint management
5. **External API integrations** - 6 system integrations
6. **Repository pattern** - Data access layers
7. **Livewire components** - Interactive UI patterns

### Architectural Patterns:
- Circuit breaker (API resilience)
- Graceful degradation (embedding failures)
- Quality gates (OCR validation)
- Constraint-based execution (token/cost/time)
- Plan-Act-Evaluate loops
- Vector memory for persistence
- Graph relationships for legal logic
- RRF + MMR for result ranking

