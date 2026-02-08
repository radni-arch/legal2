# AI Legal War Machine

**AI-powered defensive legal tools for Croatian criminal defense attorneys**

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

## Overview

The AI Legal War Machine is a comprehensive suite of AI-powered tools designed to assist Croatian defense attorneys in criminal proceedings. Built on Laravel 11, it leverages OpenAI's GPT models to detect prosecutorial misconduct, analyze evidence, and generate legal documents in compliance with Croatian law (Zakon o kaznenom postupku, Ustav Republike Hrvatske).

**Legal System**: Croatian (Republika Hrvatska)
**Framework**: Laravel 11.x
**AI**: OpenAI GPT-4o, GPT-4o-mini
**Language**: PHP 8.2+, Croatian legal documents

---

## 🎮 Try It Out - Legal Playground

**NEW** ✨ Comprehensive interactive testing interface for ALL modules!

Visit **`http://localhost/playground`** (after authentication) to access the Legal Defense Playground - a unified interface for testing all legal defense modules with TextractManager-style dark theme.

### What You Can Test:

**📋 Evidence Analysis**
- Analyze evidence for constitutional violations
- Test admissibility challenges
- Generate suppression grounds

**🔄 Evidence Recontextualization**
- Counter selective presentation
- Test with prosecution vs full context
- Generate defense narratives with credibility scoring

**⚠️ Prosecutorial Misconduct**
- Detect 6 types of misconduct
- Generate dismissal motions (Croatian)
- Generate ethics complaints to State Attorney

**📊 Topic Framework**
- Drug charge overcharging (30g cannabis threshold)
- Home search abuse detection
- Regional comparison (Osijek vs Zadar)
- Statistical analysis

All in one beautiful dark-themed interface with real-time results!

[See Playground Documentation](#legal-playground-documentation)

---

## Features

### 1. Evidence Analysis Module

Comprehensive evidence analysis for admissibility challenges under Croatian law:
- Constitutional violation detection (Ustav RH)
- Procedural error identification (ZKP)
- Evidence exclusion grounds
- Alternative interpretations based on actual facts
- Suppression motion generation

**API Endpoint**: `POST /api/evidence/analyze/{caseId}`

[Documentation](docs_recent/modules/evidence/sprint-3-implementation.md)

---

### 2. Evidence Recontextualization Module

Detects and counters prosecutorial selective presentation of evidence:

**5 Types of Selective Presentation Detected**:
1. **Partial Messages** (SMS/email/chat) - showing only incriminating excerpts
2. **Cherry-Picked Timestamps** - ignoring exculpatory timeline evidence
3. **Out-of-Context Media** (photos/videos) - misleading framing
4. **Partial Witness Statements** - omitting clarifying context
5. **Selective Financial Records** - hiding legitimate transactions

**Capabilities**:
- Full context restoration based on actual evidence
- Defense narrative generation showing complete picture
- Credibility scoring (0-100) for defense arguments
- Supporting evidence identification
- Never fabricates context (evidence-based only)

**API Endpoint**: `POST /api/evidence/recontextualize/{caseId}`

**Legal Basis**: ZKP Članak 9 (Objektivnost), ZKP Članak 331 (Slobodna ocjena dokaza)

[Full Documentation](docs_recent/modules/evidence/recontextualization.md) | [Technical Docs](docs_recent/modules/evidence/sprint-3-implementation.md)

---

### 3. Prosecutorial Misconduct Module

Detects and documents prosecutorial misconduct in Croatian criminal proceedings:

**6 Types of Misconduct Detected**:
1. **Fabricated Probable Cause** (severity 85-95) - invented justifications for searches/arrests
2. **Hidden Evidence** - Brady violations (severity 90-100)
3. **Backdated Documents** (severity 85-95) - false timestamps on warrants/reports
4. **Rights Violations** (severity 70-90) - constitutional right violations
5. **Prosecutor Threats/Lying** (severity 80-95) - intimidation, false statements
6. **Misdemeanor Pretexting** (severity 60-80) - minor charges as pretext for investigation

**Capabilities**:
- Pattern analysis for systemic violations
- Dismissal motion generation (severity >= 80)
- Ethics complaints to State Attorney
- Judicial Council complaints
- Appeal building with misconduct grounds

**API Endpoints**:
- `POST /api/misconduct/analyze/{caseId}` - Comprehensive analysis
- `POST /api/misconduct/dismissal-motion/{caseId}` - Generate dismissal motion
- `POST /api/misconduct/complaint/{caseId}` - Generate ethics complaint
- `POST /api/misconduct/appeal/{caseId}` - Build appeal

**Legal Basis**: ZKP Članak 9, 175, 177, 292; Ustav RH Članak 29, 32; Zakon o Državnom odvjetništvu

[Full Documentation](docs_recent/modules/misconduct/README.md)

---

### 4. Topic Framework - Modular Abuse Detection System

**NEW** ✨ - Modular system for detecting specific types of prosecutorial abuse patterns

The Topic Framework provides a scalable, modular approach to detecting different types of prosecutorial abuse. Each "topic" represents a specific abuse pattern with its own detection logic, thresholds, and defense strategies.

#### Currently Supported Topics

##### Drug Charge Severity (`drug_charge_severity`)
Detects overcharging in drug cases - charging "dealing/trafficking" for amounts that indicate personal use.

**Personal Use Thresholds**:
- Cannabis/Marihuana: ≤30g (dealing: >50g)
- Cocaine/Kokain: ≤1g (dealing: >2g)
- Heroin: ≤1g (dealing: >2g)
- Ecstasy/MDMA: ≤5 pills (dealing: >10 pills)

**Answers Questions Like**:
- "How many dealing charges for <30g cannabis in Osijek in 2025?"
- "Is Osijek worse than Zadar for drug overcharging?"
- "What percentage of drug cases are overcharged?"

**Legal Framework**: KZ Čl. 190 (dealing), KZ Čl. 173 (personal use), ZKP Čl. 179 (proportionality)

##### Home Search Abuse (`home_search_abuse`)
Detects disproportionate home search warrants for minor offenses.

**Questions Answered**:
- "How many home search warrants for misdemeanors in 2025?"
- "Which regions have highest rates of disproportionate searches?"

**Legal Framework**: ZKP Čl. 215-220 (home search), Ustav RH Čl. 34 (inviolability of home)

[Full Documentation](docs_recent/modules/home-search/README.md)

##### Future Topics (Planned)
- **Bail Denial** - Excessive bail or denial patterns
- **Pre-trial Detention** - Excessive detention for minor offenses
- **Witness Intimidation** - Prosecutorial intimidation patterns

#### API Endpoints

```bash
# List all available topics
GET /api/topics

# Analyze specific case for drug overcharging
POST /api/topics/drug_charge_severity/analyze/{caseId}
{
  "drug_type": "cannabis",
  "amount": 30,
  "charged_as": "dealing",
  "evidence_of_dealing": []
}

# Get statistics (e.g., "How many dealing charges for <30g cannabis?")
GET /api/topics/drug_charge_severity/statistics?year=2025&region=Osijek

# Compare regions (e.g., "Is Osijek worse than Zadar?")
GET /api/topics/drug_charge_severity/compare-regions?region1=Osijek&region2=Zadar&year=2025
```

#### Key Features

✅ **Modular Design** - Easy to add new abuse topics
✅ **AI-Powered Extraction** - Extracts data from court decisions using GPT-4o-mini
✅ **Regional Comparisons** - Compare any two regions (Osijek vs Zadar, etc.)
✅ **Statistical Analysis** - Answer "How common is X?" questions
✅ **Real Data Integration** - Fetches actual court decisions from odluke.sudovi.hr
✅ **Automatic Defense Strategies** - Generates motions and arguments

#### Usage Example

```php
use App\Modules\Topics\Analyzers\DrugChargeAbuseDetector;

$detector = app(DrugChargeAbuseDetector::class);

// Analyze case for drug overcharging
$result = $detector->analyzeCase($case, [
    'drug_type' => 'cannabis',
    'amount' => 30,
    'charged_as' => 'dealing',
    'evidence_of_dealing' => [],
]);

if ($result['overcharge_detected']) {
    echo "Overcharge severity: {$result['overcharge_severity']}/100\n";
    echo "Recommended charge: {$result['recommended_charge']}\n";

    // Defense strategies automatically generated
    foreach ($result['defense_strategy'] as $strategy) {
        echo "Strategy: {$strategy['title']}\n";
    }
}

// Compare regions
$comparison = $detector->compareRegions('Osijek', 'Zadar', 2025);
echo "Worse region: {$comparison['worse_region']['worse_region']}\n";
```

[Full Documentation](docs_recent/modules/topic-framework/README.md) | [Tests](tests/Unit/Topics/) | [Try It Out](#livewire-interface)

---

### 5. Integration Between Modules

The modules work seamlessly together to provide comprehensive defense capabilities:

**Combined Defense Strategy**:
- Detect Brady violations (hidden evidence) + recontextualize with full messages
- Identify backdated documents + timeline analysis with metadata
- Pattern of violations + comprehensive evidence recontextualization
- Topic-based analysis (drug charges, home searches) + misconduct detection
- Dismissal motions citing both misconduct and recontextualized evidence

[Integration Tests](tests/Feature/MisconductEvidenceIntegrationTest.php)

---

### 6. Citation Network Analysis

Analyze citation relationships between court decisions to understand influence, authority, and citation patterns.

**Features**:
- **Authority Metrics**: H-index, influence rank, citation counts, authority score
- **Interactive Citation Graph**: D3.js visualization with force-directed layout
- **Citation Patterns**: Temporal distribution, citation types (direct vs indirect)
- **Influence Spread**: Track influence propagation through citation network

**Capabilities**:
- Visual network showing citation relationships
- Color-coded nodes (purple=selected, blue=citing, green=cited)
- Drag-to-rearrange graph functionality
- Pattern detection (e.g., "primarily_direct_citations")
- Direct influences (1-hop citations)
- Indirect influences (2-hop citations)
- Total reach metrics

**UI Integration**: Graph Viewer (`/graph`) → Citation Analysis button → Select operation (graph, authority, patterns, influence)

**API**: `DecisionCitationService::analyzeCitations($decisionId, $options)`

[Full Documentation](docs_recent/features/CITATION_NETWORK_ANALYSIS.md)

---

## Architecture

The AI Legal War Machine uses a modular, service-oriented architecture with clear separation of concerns.

### Core Services

#### Graph Services (Neo4j Integration)

**Primary Orchestrator**:
- `App\Services\Graph\GraphRagOrchestrator` - Coordinates all graph operations

**Specialized Sync Services**:
- `App\Services\Graph\CaseGraphSyncService` - Syncs case documents to Neo4j
- `App\Services\Graph\TextractGraphSyncService` - Syncs OCR'd documents to Neo4j
- `App\Services\Graph\LawGraphSyncService` - Syncs laws to Neo4j
- `App\Services\Graph\DecisionGraphSyncService` - Syncs court decisions to Neo4j

**Linking Services**:
- `App\Services\Graph\GraphKeywordLinker` - Extracts and links keywords
- `App\Services\Graph\GraphCitationLinker` - Extracts and links legal citations
- `App\Services\Graph\GraphSimilarityLinker` - Calculates and links similar documents

**Core Infrastructure**:
- `App\Services\GraphDatabaseService` - Direct Neo4j interface
- `App\Services\TaggingService` - Automatic document tagging

**Backward Compatibility** (deprecated):
- ⚠️ `App\Services\GraphRagService` - Wrapper for old code (use GraphRagOrchestrator instead)

#### Search Services

**Unified Search**:
- `App\Services\UnifiedSearchService` - Main search orchestrator (active)
- `App\Services\Search\SearchOrchestrator` - Newer architecture (optional)

**Specialized Search Services**:
- `App\Services\Search\LawSearchService` - Search laws corpus
- `App\Services\Search\CaseSearchService` - Search case documents
- `App\Services\Search\DecisionSearchService` - Search court decisions

**Search Infrastructure**:
- `App\Services\Search\SearchEmbeddingService` - Generate embeddings
- `App\Services\Search\SearchResultAggregator` - Aggregate multi-corpus results
- `App\Services\Search\SearchResultDeduplicator` - Remove duplicate results

#### Vector Stores

- `App\Services\LawVectorStoreService` - Croatian laws (ZKP, KZ, Ustav RH)
- `App\Services\CourtDecisionVectorStoreService` - Court decisions from odluke.sudovi.hr
- `App\Services\CaseVectorStoreService` - Internal case documents
- `App\Services\TextractVectorStoreService` - OCR'd PDF documents

#### AI Services (OpenAI Integration)

**Unified Orchestrator** (✨ NEW - Sprint 3):
- `App\Services\AI\OpenAIOrchestrator` - Facade pattern orchestrator for all OpenAI operations

**Specialized AI Services**:
- `App\Services\AI\OpenAIChatService` - Chat completions (GPT-4o, GPT-4o-mini)
- `App\Services\AI\OpenAIEmbeddingService` - Text embeddings (text-embedding-3-small)
- `App\Services\AI\OpenAIAnalysisService` - Legal text analysis and summarization
- `App\Services\AI\OpenAICacheService` - Response caching (60% cost reduction)

**Service Interfaces** (contracts for DI):
- `App\Contracts\AI\ChatServiceInterface` - Chat service contract
- `App\Contracts\AI\EmbeddingServiceInterface` - Embedding service contract
- `App\Contracts\AI\AnalysisServiceInterface` - Analysis service contract
- `App\Contracts\AI\CacheServiceInterface` - Cache service contract

**Architecture Benefits**:
- ✅ **Single Responsibility**: Each service has one clear purpose
- ✅ **Testable**: Full dependency injection, mockable interfaces
- ✅ **Maintainable**: Average 250 lines per service vs 1,010-line monolith
- ✅ **Type-safe**: Interface contracts enforce correct usage
- ✅ **Performance**: Intelligent caching, lazy loading

**Backward Compatibility**:
- ⚠️ `App\Services\OpenAIService` - Legacy monolithic service (preserved, 100% compatible)

**Migration**: See [docs/migration-guides/openai-services.md](docs_recent/migration-guides/openai-services.md)

#### Research Services (✨ NEW - Sprint 4)

**Autonomous Research Pipeline** - Modular, service-oriented architecture for legal research

**Research Orchestrator**:
- `App\Services\ResearchOrchestrator` - Coordinates entire research pipeline

**Core Research Services**:
1. **QuestionGeneratorService** - Generates and refines research questions using LLM
2. **SearchExecutorService** - Executes searches across law, decision, and case databases
3. **AnswerEvaluatorService** - Evaluates answer completeness and identifies gaps
4. **QualityAssessorService** - Assesses overall research quality (0-100 score)
5. **IterationControllerService** - Controls iteration limits and resource usage

**Service Interfaces** (contracts for DI):
- `App\Contracts\Research\QuestionGeneratorInterface`
- `App\Contracts\Research\SearchExecutorInterface`
- `App\Contracts\Research\AnswerEvaluatorInterface`
- `App\Contracts\Research\QualityAssessorInterface`
- `App\Contracts\Research\IterationControllerInterface`

**Research Pipeline Flow**:
```
1. Generate Questions (QuestionGenerator)
   ↓
2. Execute Searches (SearchExecutor)
   ↓
3. Evaluate Answer (AnswerEvaluator)
   ↓
4. Assess Quality (QualityAssessor)
   ↓
5. Check Limits (IterationController)
   ↓
   If quality < threshold AND budget remaining:
   → Refine Questions (back to step 1)

   Else:
   → Return Final Answer
```

**Usage Example**:
```php
use App\Services\ResearchOrchestrator;

$orchestrator = app(ResearchOrchestrator::class);

$result = $orchestrator->research(
    query: 'Research Croatian labor law termination notice periods',
    options: [
        'max_iterations' => 5,
        'quality_threshold' => 85,
        'token_budget' => 50000,
        'time_budget' => 300, // 5 minutes
    ]
);

// Access results
$answer = $result['answer'];              // Synthesized answer
$quality = $result['quality_score'];      // Quality score (0-100)
$sources = $result['sources'];            // Search results used
$iterations = $result['iterations'];      // Number of iterations
$assessment = $result['assessment'];      // Detailed quality breakdown
```

**Architecture Benefits**:
- ✅ **Service-Oriented**: 5 focused services vs monolithic agent
- ✅ **Highly Testable**: 232 tests with 100% success rate
- ✅ **Maintainable**: Average 400 lines per service vs 1,146-line monolith
- ✅ **Iterative Quality**: Automatically refines questions until quality threshold met
- ✅ **Resource Control**: Token, time, and iteration budgets enforced

**Test Coverage**: 232 tests (1.79:1 test-to-code ratio)
- QuestionGeneratorService: 24 tests, 93 assertions
- SearchExecutorService: Comprehensive test suite
- AnswerEvaluatorService: Comprehensive test suite
- QualityAssessorService: Comprehensive test suite
- IterationControllerService: Comprehensive test suite
- ResearchOrchestrator: 13 tests, 44 assertions
- Characterization Tests: 50 tests (backward compatibility)

**Backward Compatibility**:
- ⚠️ `App\Agents\AutonomousResearchAgent` - Legacy agent (deprecated, fully functional)

**Migration**: See [docs/migration-guides/research-services.md](docs_recent/migration-guides/research-services.md)

### Service Providers

- `App\Providers\GraphServiceProvider` - Registers all Graph services
- `App\Providers\SearchServiceProvider` - Registers all Search services
- `App\Providers\OpenAIServiceProvider` - Registers all AI services (✨ NEW)
- `App\Providers\ResearchServiceProvider` - Registers all Research services (✨ NEW - Sprint 4)
- `App\Providers\AppServiceProvider` - Main application services

### Migration Guide

See [docs/migration-guides/phase-2.md](docs_recent/migration-guides/phase-2.md) for detailed migration instructions from old to new services.

---

## Quick Start

### Requirements

- PHP 8.2 or higher
- Composer
- MySQL/PostgreSQL
- OpenAI API key

### Installation

```bash
# Clone repository
git clone <repository-url>
cd ai-legal-war-machine

# Install dependencies
composer install

# Configure environment
cp .env.example .env
# Edit .env and add:
# - OPENAI_API_KEY=your-api-key
# - Database credentials

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Start server
php artisan serve
```

### Usage Example

```php
use App\Modules\Misconduct\ProsecutorialMisconductModule;
use App\Modules\Evidence\EvidenceAnalysisModule;

// Detect prosecutorial misconduct
$misconductModule = app(ProsecutorialMisconductModule::class);
$misconduct = $misconductModule->analyzeMisconduct($caseId);

if ($misconduct['severity_score'] >= 85) {
    // Generate dismissal motion
    $motion = $misconductModule->generateDismissalMotion($caseId);
}

// Recontextualize evidence
$evidenceModule = app(EvidenceAnalysisModule::class);
$evidence = [
    'id' => 'ev1',
    'type' => 'communication',
    'prosecution_description' => "I'll get the stuff tonight",
    'full_content' => "Full SMS: conversation about grocery shopping",
];

$result = $evidenceModule->recontextualizeEvidence($caseId, $evidence);

if ($result['recontextualization']['credibility_score'] >= 70) {
    // Use in defense strategy
    echo "Strong defense argument available\n";
}
```

---

## Documentation

### 📚 Core Documentation

- **[Architecture Guide](docs_recent/ARCHITECTURE.md)** - System architecture with comprehensive Mermaid diagrams
  - High-level architecture
  - Agent architecture and lifecycle
  - Multi-agent orchestration flows
  - Data flow pipelines
  - Integration architecture
  - Deployment topology
  - Security and performance architecture

- **[Module Documentation Index](docs_recent/modules/MODULE_INDEX.md)** - Complete guide to all 5 modules
  - Evidence Module - ZKP compliance and suppression motions
  - Misconduct Module - Prosecutorial misconduct detection
  - Topic Framework - Abuse pattern detection
  - Home Search Module - Warrant proportionality analysis
  - Defense Module - Strategy and recommendations

- **[Performance Optimization Guide](docs_recent/PERFORMANCE_OPTIMIZATION.md)** - Sprint 6.4
  - Profiling tools and bottleneck identification
  - Database indexes and query optimization
  - Result caching strategies
  - Parallel execution patterns
  - LLM call optimization (70% faster, 80% cheaper)
  - Performance targets and benchmarks

- **[Troubleshooting Guide](docs_recent/TROUBLESHOOTING.md)** - Common issues and solutions
  - Agent execution issues
  - Database problems (PostgreSQL, Neo4j)
  - Vector search troubleshooting
  - LLM API failures
  - Performance problems
  - Deployment issues

- **[Deployment Guide](docs_recent/DEPLOYMENT.md)** - Production deployment walkthrough
  - Server setup (Ubuntu/Debian)
  - PostgreSQL + pgvector installation
  - Neo4j and Redis setup
  - Nginx configuration with SSL
  - Queue workers and Supervisor
  - Security hardening
  - Monitoring and backups

- **[API Documentation](docs_recent/openapi.yaml)** - OpenAPI 3.0 specification
  - All API endpoints documented
  - Request/response schemas
  - Authentication and rate limiting
  - Error handling
  - Interactive documentation

- **[Demo Videos Guide](docs_recent/DEMO_VIDEOS.md)** - Video production documentation
  - Video scripts for all key features
  - Recording and editing guidelines
  - Technical setup requirements
  - Publishing recommendations

### 📖 Module Guides

- [Topic Framework](docs_recent/modules/topic-framework/README.md) - Modular abuse detection system with drug charge, home search, and more topics
- [Prosecutorial Misconduct Module](docs_recent/modules/misconduct/README.md) - Detecting and responding to misconduct
- [Evidence Module](docs_recent/modules/evidence/README.md) - Evidence analysis and admissibility
- [Evidence Recontextualization](docs_recent/modules/evidence/recontextualization.md) - Countering selective evidence presentation
- [Home Search Abuse Module](docs_recent/modules/home-search/README.md) - Disproportionate warrant detection
- [Defense Module](docs_recent/modules/defense/README.md) - Strategy analysis and recommendations
- [Odluke Search Agent](docs_recent/ODLUKE_SEARCH_AGENT.md) - Autonomous agent for real data collection

### 🔧 Technical Documentation

- [Sprint 3 Technical Docs](docs_recent/modules/evidence/sprint-3-implementation.md) - Architecture and data flow
- [Code Review Iteration Fixes](docs_recent/archive/2025-11/reports/CODE_REVIEW_ITERATION_FIXES.md) - 9 bugs fixed
- [Testing Guide](TESTING.md) - Comprehensive testing documentation

### 🧪 Testing

- [Topic Framework Tests](tests/Unit/Topics/) - Drug charge abuse detection unit tests
- [Topic Controller Tests](tests/Feature/Api/TopicControllerTest.php) - API endpoint tests
- [Topic Integration Tests](tests/Feature/TopicFrameworkIntegrationTest.php) - Full flow tests
- [Evidence Module Tests](tests/Feature/EvidenceModuleTest.php)
- [Misconduct Module Tests](tests/Feature/MisconductModuleTest.php)
- [Integration Tests](tests/Feature/MisconductEvidenceIntegrationTest.php)

---

## Croatian Legal Framework

This project operates under Croatian law:

- **ZKP** (Zakon o kaznenom postupku) - Criminal Procedure Act
- **Ustav RH** (Ustav Republike Hrvatske) - Croatian Constitution
- **Zakon o Državnom odvjetništvu** - State Attorney Act
- **Kazneni zakon** (KZ) - Criminal Code
- **Kodeks profesionalne etike** - Professional Ethics Code

All modules cite proper legal authorities and generate documents in Croatian when appropriate.

---

## Ethical Use

### ✅ Legitimate Uses
- Detecting actual prosecutorial misconduct based on evidence
- Identifying constitutional violations
- Revealing full context of selectively presented evidence
- Protecting defendant rights
- Generating legal motions based on documented violations

### ❌ Prohibited Uses
- Fabricating evidence or accusations
- Creating false context
- Harassing prosecutors without basis
- Obstructing justice
- Filing frivolous complaints

**This is a defensive tool for legitimate legal defense, not for creating false accusations or fabricating evidence.**

---

## Testing

This project uses an integrated testing approach with a production database copy. Tests run fast using database transactions, eliminating the need to refresh the database after each test.

### Quick Start

```bash
# Setup test database (one-time)
composer test:setup

# Run all tests
composer test:integrated

# Or setup + run in one command
composer test:all
```

### Available Test Commands

```bash
# Basic testing
composer test              # Quick test (in-memory SQLite)
composer test:integrated   # Full integrated test suite
composer test:all          # Setup database + run all tests

# Test suites
composer test:unit         # Unit tests only
composer test:feature      # Feature tests only

# Advanced options
composer test:coverage     # With coverage report (min 80%)
composer test:parallel     # Run tests in parallel (faster)
composer test:quick        # Fast fail (parallel + stop on failure)

# Specific tests
./scripts/run-tests.sh --filter=DrugChargeAbuseDetectorTest
./scripts/run-tests.sh --filter=TopicControllerTest
```

### Test Database Management

```bash
# Setup/refresh test database
composer test:setup
# or
php artisan test:setup-db

# Setup with seeding
php artisan test:setup-db --seed

# Force recreation
./scripts/setup-test-db.sh --force
```

### How It Works

1. **One-time Setup**: Copy production database to create test database
2. **Transaction Wrapping**: Each test runs in a database transaction
3. **Automatic Rollback**: Changes are rolled back after each test
4. **Fast Execution**: No need to rebuild database between tests

Benefits:
- **Fast**: No schema rebuilding
- **Realistic**: Production-like data
- **Clean**: No side effects between tests
- **Easy**: Single command to run everything

For complete testing documentation, see [TESTING.md](TESTING.md)

### End-to-End Browser Testing

Comprehensive browser automation tests covering all user workflows:

```bash
# Run all E2E tests
composer test:e2e

# Run specific suite
composer test:e2e:auth         # Authentication
composer test:e2e:playground   # Legal Playground
composer test:e2e:textract     # Textract Manager
composer test:e2e:graph        # Graph Viewer
composer test:e2e:laws         # Law Downloads
composer test:e2e:timeline     # Timeline Visualization
```

**Test Coverage:** 42 scenarios across 6 suites (auth, playground, textract, graph, laws, timeline)

See [E2E Testing Guide](docs_recent/E2E_TESTING.md) for detailed documentation.

---

## API Reference

### Topic Framework
- `GET /api/topics` - List all available topics
- `POST /api/topics/{topic}/analyze/{caseId}` - Analyze case for topic
- `GET /api/topics/{topic}/statistics` - Get topic statistics (with filters)
- `GET /api/topics/{topic}/compare-regions` - Compare two regions

### Evidence Analysis
- `POST /api/evidence/analyze/{caseId}` - Comprehensive evidence analysis
- `POST /api/evidence/recontextualize/{caseId}` - Recontextualize evidence
- `POST /api/evidence/suppress-motion/{caseId}` - Generate suppression motion

### Prosecutorial Misconduct
- `POST /api/misconduct/analyze/{caseId}` - Analyze misconduct
- `POST /api/misconduct/dismissal-motion/{caseId}` - Generate dismissal motion
- `POST /api/misconduct/complaint/{caseId}` - Generate complaint
- `POST /api/misconduct/appeal/{caseId}` - Build appeal

---

## Contributing

Contributions are welcome! Please ensure:
- All code follows ethical guidelines
- Tests are included for new features
- Croatian legal citations are accurate
- Documentation is updated

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

**Legal Disclaimer**: This software is provided for legal defense purposes only. Users are responsible for ensuring ethical use in compliance with Croatian legal ethics and professional responsibility standards. Always consult with a licensed Croatian attorney before filing motions or complaints.

---

# MCP Tools

## Textract searchable-PDF pipeline
This project includes a pipeline that:
- fetches PDFs from a Google Drive folder (via Service Account),
- uploads them to S3,
- runs AWS Textract OCR asynchronously,
- reconstructs a “searchable” PDF by overlaying an invisible text layer (FPDI + TCPDF),
- stores the raw Textract JSON and the final searchable PDF back to S3.

### 1) Install PHP packages
Already included in composer.json; if needed, ensure dependencies are installed:

```
composer install
```

### 2) Configure .env
Add the following keys (see `.env.example`):

```
# Google Drive (Service Account)
GOOGLE_APPLICATION_CREDENTIALS=/absolute/path/to/service-account.json
GOOGLE_DRIVE_FOLDER_ID=YOUR_FOLDER_ID
GOOGLE_IMPERSONATE_USER=

# AWS / S3 / Textract
AWS_DEFAULT_REGION=eu-central-1
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_BUCKET=your-s3-bucket
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

# S3 prefixes for pipeline artifacts
S3_INPUT_PREFIX=textract/input
S3_OUTPUT_PREFIX=textract/output
S3_JSON_PREFIX=textract/json

# Queue
QUEUE_CONNECTION=database
```

Share the Drive folder with the Service Account email and enable the Drive API in Google Cloud.
Ensure the S3 bucket exists and the IAM user/role has permissions:
- s3:GetObject, s3:PutObject, s3:ListBucket
- textract:StartDocumentTextDetection, textract:GetDocumentTextDetection

### 3) Migrate database

```
php artisan migrate --force
```

This creates the `textract_jobs` table for job tracking.

### 4) Run the pipeline
Start a queue worker and enqueue jobs for a Drive folder:

```
php artisan queue:work --queue=textract
php artisan textract:process-drive-folder YOUR_FOLDER_ID --limit=3
```

Artifacts will appear on S3:
- `${AWS_BUCKET}/${S3_JSON_PREFIX}/{drive_file_id}.json`
- `${AWS_BUCKET}/${S3_OUTPUT_PREFIX}/{drive_file_id}-searchable.pdf`

### Notes
- FPDI/TCPDF overlays OCR LINE blocks as invisible text; adjust opacity in `PdfReconstructor` if you want to debug placement.
- For large PDFs, consider SNS/SQS instead of polling Textract.
- The pipeline defaults to LINE blocks; WORD-level placement is possible with minor changes.

### PDF Preview ⚠️ PARTIALLY IMPLEMENTED

**Status:** Frontend complete, backend integration incomplete (60% done)

View uploaded documents directly in browser using PDF.js:
- ✅ Inline PDF viewer with canvas rendering
- ✅ Page navigation and zoom controls
- ✅ Hardware-accelerated rendering via PDF.js 4.8.69
- ⚠️ Secure access via signed URLs (service implemented, route missing)
- ⚠️ 1-hour URL expiration (configurable)
- ❌ Livewire integration pending (modal UI not yet added to Textract Manager)

**What Works:**
- PDF.js installed and configured
- PdfViewer JavaScript component fully functional
- Browser tests written (4 comprehensive Dusk tests)
- Build system configured for worker files

**What's Missing:**
- TextractFileController (serves PDFs from S3)
- Route `textract.file` with auth/signed middleware
- TextractManager Livewire integration (modal UI)
- Preview button in Textract Manager document list

**To Complete:**
```bash
# See implementation plan and detailed status
cat docs/plans/2025-11-15-textract-pdf-preview.md
cat docs/features/TEXTRACT_PDF_PREVIEW.md
```

See [TEXTRACT_PDF_PREVIEW.md](docs_recent/features/TEXTRACT_PDF_PREVIEW.md) for comprehensive documentation, architecture details, and remaining work.

## Odluke Agent (ChatGPT + MCP)

This app includes an autonomous agent `odluke_agent` powered by OpenAI (ChatGPT) via Vizra ADK. It uses MCP tools to search and download decisions from odluke.sudovi.hr.

### Configure

Set the following in your `.env`:

- `APP_URL` (e.g., `http://localhost:8000`)
- `OPENAI_API_KEY` (required)
- optional: `MCP_ODLUKE_URL` (defaults to `${APP_URL}/mcp/message`)

The MCP HTTP server is exposed at `/mcp/message` and is auto-registered by `php-mcp/laravel`.

### Quick start

1) Start the Laravel server and queues as usual.

2) Call the agent via Vizra ADK API:

```
curl -sS -X POST "$APP_URL/api/vizra-adk/interact" \
  -H 'Content-Type: application/json' \
  -d '{
    "agent_name": "odluke_agent",
    "input": "Pronađi najnovije presude o ugovoru o radu i preuzmi PDF jedne relevantne presude"
  }'
```

3) Optional: test MCP directly

- Initialize and list tools
```
curl -sS -X POST "$APP_URL/mcp/message" -H 'Content-Type: application/json' -d '{
  "jsonrpc":"2.0","id":1,
  "method":"initialize",
  "params":{
    "protocolVersion":"2024-11-05",
    "capabilities":{"tools":{},"resources":{},"prompts":{}},
    "clientInfo":{"name":"local","version":"dev"}
  }
}'

curl -sS -X POST "$APP_URL/mcp/message" -H 'Content-Type: application/json' -d '{
  "jsonrpc":"2.0","id":2,
  "method":"tools/list"
}'
```

- Call `odluke-search`
```
curl -sS -X POST "$APP_URL/mcp/message" -H 'Content-Type: application/json' -d '{
  "jsonrpc":"2.0","id":3,
  "method":"tools/call",
  "params":{ "name":"odluke-search", "arguments":{ "q":"ugovor o radu", "limit":25, "page":1 }}
}'
```

### What the agent does

- Uses `odluke-search` to find IDs by topic/filters.
- Uses `odluke-meta` to score and shortlist results.
- Uses `odluke-download` to fetch PDF/HTML (set `save=true` when appropriate).
- Produces a concise Croatian or user-language summary and listed downloads.

### Court Decision Ingestion with Graph Sync

When ingesting court decisions via `OdlukeIngestService`, the system can automatically sync decision metadata to the Neo4j graph database. This enables relationship discovery and pattern analysis across decisions.

**Configuration**:

Set `ODLUKE_SYNC_GRAPH=true` in your `.env` (enabled by default):

```env
# Enable automatic graph sync during court decision ingestion
ODLUKE_SYNC_GRAPH=true

# Also ensure Neo4j is enabled
NEO4J_ENABLED=true
NEO4J_URI=bolt://localhost:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your-password
```

**How it works**:

1. When ingesting decisions via `ingestByIds()`, the service reads the `ODLUKE_SYNC_GRAPH` environment variable
2. After successful vector ingestion, decision metadata is stored in Neo4j graph database
3. Graph sync happens automatically unless explicitly disabled via options: `['sync_graph' => false]`
4. If sync fails, it logs a warning but does not block vector ingestion

**Example**:

```php
use App\Services\Odluke\OdlukeIngestService;

$service = app(OdlukeIngestService::class);

// Sync is enabled by default (reads ODLUKE_SYNC_GRAPH env)
$result = $service->ingestByIds(['decision-id-1', 'decision-id-2']);

// Explicitly disable sync for this call
$result = $service->ingestByIds(['decision-id-3'], ['sync_graph' => false]);

// Check results
echo "Graph synced: {$result['graph_synced']}";
echo "Graph errors: {$result['graph_errors']}";
```

### Notes

- Saved files default to `storage/app/odluke`. Configure via `config/odluke.php` if present.
- Ensure `APP_URL` is correct for MCP HTTP calls.
- The agent uses model `gpt-4.1-mini` by default; change in `App/Agents/OdlukeAgent.php` if needed.
