# AI Legal War Machine - System Architecture

**Version**: 1.0
**Date**: 2025-11-11
**Status**: Production

## Table of Contents

1. [System Overview](#system-overview)
2. [High-Level Architecture](#high-level-architecture)
3. [Agent Architecture](#agent-architecture)
4. [Multi-Agent Orchestration](#multi-agent-orchestration)
5. [Data Flow](#data-flow)
6. [Module Architecture](#module-architecture)
7. [Integration Architecture](#integration-architecture)
8. [Deployment Architecture](#deployment-architecture)

---

## System Overview

AI Legal War Machine is a comprehensive suite of AI-powered legal defense tools for Croatian criminal defense attorneys, combining:

- **Vector Search**: Semantic search across laws and court decisions
- **Graph Database (Neo4j)**: Citation networks and legal relationships
- **AI Agents**: Autonomous specialist agents for research, analysis, and strategy
- **AWS Textract**: OCR for PDF document processing
- **Multi-Agent Orchestration**: Coordinated AI agents working together

**Tech Stack**:
- Laravel 11 + PHP 8.2+
- PostgreSQL (primary data + pgvector for embeddings)
- Neo4j (graph relationships)
- OpenAI GPT-4o / GPT-4o-mini
- AWS S3 + Textract
- Redis (caching + queues)

---

## High-Level Architecture

```mermaid
graph TB
    subgraph "Frontend Layer"
        UI[Web UI - Livewire]
        API[REST API]
        Playground[Legal Playground]
    end

    subgraph "Application Layer"
        Controllers[Controllers]
        Services[Services]
        Agents[AI Agents]
        Modules[Domain Modules]
    end

    subgraph "AI/ML Layer"
        OpenAI[OpenAI API<br/>GPT-4o/mini]
        VectorSearch[Vector Search<br/>pgvector]
        GraphRAG[Graph RAG<br/>Neo4j]
    end

    subgraph "Data Layer"
        PostgreSQL[(PostgreSQL<br/>Primary Data)]
        Neo4j[(Neo4j<br/>Graph DB)]
        Redis[(Redis<br/>Cache/Queue)]
        S3[(AWS S3<br/>Storage)]
    end

    subgraph "External Integrations"
        EKOM[EKOM<br/>e-Courts]
        Odluke[Odluke.sudovi.hr<br/>Court Decisions]
        Eoglasna[Eoglasna<br/>Public Notices]
        Textract[AWS Textract<br/>OCR]
    end

    UI --> Controllers
    API --> Controllers
    Playground --> Controllers

    Controllers --> Services
    Controllers --> Agents
    Controllers --> Modules

    Services --> OpenAI
    Services --> VectorSearch
    Services --> GraphRAG

    Agents --> OpenAI
    Agents --> VectorSearch
    Agents --> GraphRAG

    Modules --> Services

    VectorSearch --> PostgreSQL
    GraphRAG --> Neo4j
    Services --> Redis

    Services --> EKOM
    Services --> Odluke
    Services --> Eoglasna
    Services --> Textract
    Services --> S3

    Textract --> S3
```

**Key Components**:
- **Frontend**: Livewire components + REST API
- **Application**: Controllers, Services, AI Agents, Domain Modules
- **AI/ML**: OpenAI integration, Vector search, Graph RAG
- **Data**: PostgreSQL (primary + vectors), Neo4j (graph), Redis (cache/queue), S3 (files)
- **External**: EKOM (e-courts), Odluke (decisions), Eoglasna (notices), Textract (OCR)

---

## Agent Architecture

### Agent System Overview

```mermaid
graph TB
    subgraph "Agent Types"
        Research[ResearchSpecialistAgent<br/>Legal Research]
        Precedent[PrecedentAnalystAgent<br/>Case Analysis]
        Risk[RiskAnalystAgent<br/>Risk Assessment]
        Strategy[StrategySpecialistAgent<br/>Strategy Development]
        Decision[DecisionDiscoveryAgent<br/>Decision Discovery]
        Odluke[OdlukeAgent<br/>MCP Integration]
    end

    subgraph "Agent Infrastructure"
        Context[SharedAgentContext<br/>Shared Memory]
        CommBus[AgentCommunicationBus<br/>Messaging]
        Trace[ReasoningTraceService<br/>Explainability]
        Cache[AgentResultCache<br/>Performance]
        Parallel[ParallelExecution<br/>Concurrency]
    end

    subgraph "Agent Tools"
        LawSearch[LawSearchService]
        DecisionSearch[DecisionSearchService]
        GraphEnhancer[GraphResearchEnhancer]
        OpenAI[OpenAIService]
    end

    subgraph "Data Storage"
        AgentRuns[(agent_runs)]
        AgentComms[(agent_communications)]
        Traces[(reasoning_traces)]
        Learning[(learning_opportunities)]
    end

    Research --> Context
    Precedent --> Context
    Risk --> Context
    Strategy --> Context
    Decision --> Context
    Odluke --> Context

    Research --> CommBus
    Precedent --> CommBus
    Risk --> CommBus
    Strategy --> CommBus

    Research --> Trace
    Precedent --> Trace
    Risk --> Trace
    Strategy --> Trace

    Research --> Cache
    Precedent --> Cache

    Research --> Parallel

    Research --> LawSearch
    Research --> DecisionSearch
    Research --> GraphEnhancer

    Precedent --> OpenAI
    Risk --> OpenAI
    Strategy --> OpenAI

    Context --> AgentRuns
    CommBus --> AgentComms
    Trace --> Traces
    Research --> Learning
    Precedent --> Learning
```

### Agent Capabilities

| Agent | Role | Primary Function | Key Tools |
|-------|------|------------------|-----------|
| **ResearchSpecialist** | Legal Researcher | Find relevant laws and court decisions | LawSearch, DecisionSearch, GraphRAG |
| **PrecedentAnalyst** | Case Analyst | Analyze applicability and authority of precedents | OpenAI, ActiveLearning |
| **RiskAnalyst** | Risk Assessor | Identify risks and vulnerabilities | OpenAI, Pattern Analysis |
| **StrategySpecialist** | Strategist | Develop legal strategies and action plans | OpenAI, Success Prediction |
| **DecisionDiscoveryAgent** | Discovery | Discover and analyze court decisions | Odluke API, OpenAI |
| **OdlukeAgent** | MCP Integration | MCP-powered search and analysis | MCP Tools, Odluke API |

### Agent Lifecycle

```mermaid
sequenceDiagram
    participant User
    participant Controller
    participant Agent
    participant Context
    participant Tools
    participant Storage

    User->>Controller: Request
    Controller->>Agent: execute(context, task)
    activate Agent

    Agent->>Context: Initialize session
    Agent->>Context: logEvent('started')

    Agent->>Tools: Search/Analyze
    activate Tools
    Tools-->>Agent: Results
    deactivate Tools

    Agent->>Context: write('results', data)
    Agent->>Context: sendMessage(other_agent)

    Agent->>Storage: Store trace
    Agent->>Storage: Store run metadata

    Agent->>Context: logEvent('completed')
    Agent-->>Controller: Result
    deactivate Agent

    Controller-->>User: Response
```

---

## Multi-Agent Orchestration

### Orchestration Flow

```mermaid
graph LR
    subgraph "Entry Point"
        User[User Request]
        Orchestrator[OrchestratorService]
    end

    subgraph "Phase 1: Research"
        Research[ResearchSpecialist<br/>Search laws & decisions]
    end

    subgraph "Phase 2: Analysis"
        Precedent[PrecedentAnalyst<br/>Analyze precedents]
        Strategy[StrategySpecialist<br/>Develop strategy]
    end

    subgraph "Phase 3: Assessment"
        Risk[RiskAnalyst<br/>Assess risks]
    end

    subgraph "Shared Context"
        Context[SharedAgentContext<br/>- researched_laws<br/>- researched_decisions<br/>- analyzed_precedents<br/>- legal_arguments<br/>- risk_assessment]
    end

    User -->|Problem Statement| Orchestrator

    Orchestrator -->|Task 1| Research
    Research -->|Laws + Decisions| Context

    Context -->|Research Data| Precedent
    Context -->|Research Data| Strategy

    Precedent -->|Analysis| Context
    Strategy -->|Strategy| Context

    Context -->|Strategy| Risk
    Risk -->|Risk Report| Context

    Context -->|Final Result| Orchestrator
    Orchestrator -->|Response| User
```

### Communication Patterns

```mermaid
sequenceDiagram
    participant R as ResearchSpecialist
    participant P as PrecedentAnalyst
    participant S as StrategySpecialist
    participant K as RiskAnalyst
    participant C as SharedContext
    participant B as CommunicationBus

    Note over R,B: Phase 1: Research
    R->>C: write('researched_laws', laws)
    R->>C: write('researched_decisions', decisions)
    R->>B: sendMessage(precedent_analyst, research_complete)

    Note over P,S: Phase 2: Parallel Analysis
    par Precedent Analysis
        P->>C: read('researched_decisions')
        P->>P: Analyze precedents
        P->>C: write('analyzed_precedents', analysis)
        P->>B: sendMessage(strategy_specialist, analysis_complete)
    and Strategy Development
        S->>C: read('researched_laws')
        S->>C: read('researched_decisions')
        S->>S: Develop strategy
        S->>C: write('legal_arguments', arguments)
        S->>C: write('strategic_recommendations', recommendations)
        S->>B: sendMessage(risk_analyst, strategy_complete)
    end

    Note over K: Phase 3: Risk Assessment
    K->>C: read('legal_arguments')
    K->>C: read('analyzed_precedents')
    K->>K: Assess risks
    K->>C: write('risk_assessment', risks)
    K->>C: write('risk_mitigations', mitigations)
```

### Orchestration Configuration

```php
// config/agent.php
'orchestration' => [
    'enabled' => env('AGENT_ORCHESTRATION_ENABLED', true),
    'max_parallel_agents' => 3,
    'timeout_seconds' => 300,
    'retry_failed_agents' => true,
    'max_retries' => 2,
],
```

---

## Data Flow

### Research to Strategy Flow

```mermaid
graph TD
    subgraph "Input"
        Problem[Problem Statement<br/>"Client charged with..."]
    end

    subgraph "Research Phase"
        LawSearch[Law Vector Search<br/>pgvector]
        DecisionSearch[Decision Vector Search<br/>pgvector]
        GraphSearch[Graph Traversal<br/>Neo4j]
    end

    subgraph "Enrichment"
        Dedupe[Deduplicate Results]
        Prioritize[Prioritize by Authority]
        GraphMerge[Merge Graph Results]
    end

    subgraph "Analysis Phase"
        ScorePrecedents[Score Applicability<br/>0-100]
        ClassifyAuthority[Classify Authority<br/>binding/persuasive]
        IdentifyFactors[Identify Key Factors]
    end

    subgraph "Strategy Phase"
        DevelopArgs[Develop Arguments]
        EstimateSuccess[Estimate Success Prob]
        CreatePlan[Create Action Plan]
    end

    subgraph "Risk Phase"
        AnalyzeWeaknesses[Analyze Weaknesses]
        IdentifyAdverse[Identify Adverse Precedents]
        GenerateMitigations[Generate Mitigations]
    end

    subgraph "Output"
        Report[Comprehensive Legal Analysis]
    end

    Problem --> LawSearch
    Problem --> DecisionSearch
    Problem --> GraphSearch

    LawSearch --> Dedupe
    DecisionSearch --> Dedupe
    GraphSearch --> GraphMerge

    GraphMerge --> Dedupe
    Dedupe --> Prioritize

    Prioritize --> ScorePrecedents
    ScorePrecedents --> ClassifyAuthority
    ClassifyAuthority --> IdentifyFactors

    IdentifyFactors --> DevelopArgs
    DevelopArgs --> EstimateSuccess
    EstimateSuccess --> CreatePlan

    CreatePlan --> AnalyzeWeaknesses
    AnalyzeWeaknesses --> IdentifyAdverse
    IdentifyAdverse --> GenerateMitigations

    GenerateMitigations --> Report
```

### Vector + Graph Hybrid Search

```mermaid
graph TB
    subgraph "Vector Search (pgvector)"
        Query[Search Query]
        Embed[Generate Embedding<br/>OpenAI ada-002]
        VectorDB[(court_decision_embeddings<br/>law_embeddings)]
        VectorResults[Top K Results<br/>by Cosine Similarity]
    end

    subgraph "Graph Enhancement (Neo4j)"
        GraphDB[(Neo4j Graph)]
        CitedLaws[Find Decisions<br/>Citing Discovered Laws]
        RelatedDecisions[Find Related Decisions<br/>via Citations]
        GraphResults[Graph-Discovered Results]
    end

    subgraph "Merge & Rank"
        Combine[Combine Results]
        Dedupe[Deduplicate by ID]
        Rank[Rank by:<br/>1. Similarity Score<br/>2. Court Authority<br/>3. Recency]
        Final[Final Top K Results]
    end

    Query --> Embed
    Embed --> VectorDB
    VectorDB --> VectorResults

    VectorResults --> GraphDB
    GraphDB --> CitedLaws
    GraphDB --> RelatedDecisions

    CitedLaws --> GraphResults
    RelatedDecisions --> GraphResults

    VectorResults --> Combine
    GraphResults --> Combine

    Combine --> Dedupe
    Dedupe --> Rank
    Rank --> Final
```

---

## Module Architecture

### Module Structure

```mermaid
graph TB
    subgraph "app/Modules/"
        Evidence[Evidence/<br/>Evidence Analysis]
        Misconduct[Misconduct/<br/>Prosecutor Misconduct]
        Topics[Topics/<br/>Abuse Detection]
        HomeSearch[HomeSearch/<br/>Home Search Analysis]
        Defence[Defence/<br/>Defense Strategy]
    end

    subgraph "Module Components"
        Controller[Controllers/<br/>HTTP Handlers]
        Service[Services/<br/>Business Logic]
        Model[Models/<br/>Data Objects]
        Route[routes.php<br/>API Endpoints]
    end

    subgraph "Shared Services"
        OpenAI[OpenAIService]
        VectorStore[VectorStoreServices]
        Graph[GraphServices]
    end

    Evidence --> Controller
    Misconduct --> Controller
    Topics --> Controller
    HomeSearch --> Controller
    Defence --> Controller

    Controller --> Service
    Service --> Model
    Controller --> Route

    Service --> OpenAI
    Service --> VectorStore
    Service --> Graph
```

### Evidence Module Example

```
app/Modules/Evidence/
├── Controllers/
│   └── EvidenceAnalysisController.php
├── Services/
│   ├── EvidenceAnalysisService.php
│   └── RecontextualizationService.php
├── Models/
│   └── EvidenceAnalysis.php
└── routes.php  (API endpoints)

API Endpoints:
POST /api/evidence/analyze
POST /api/evidence/recontextualize
POST /api/evidence/suppression-motion
```

---

## Integration Architecture

### External Integrations

```mermaid
graph TB
    subgraph "Application"
        App[AI Legal War Machine]
    end

    subgraph "Croatian Legal Systems"
        EKOM[EKOM<br/>e-Courts System]
        Odluke[Odluke.sudovi.hr<br/>Court Decisions]
        Eoglasna[Eoglasna<br/>Public Notices]
    end

    subgraph "AI/ML Services"
        OpenAI[OpenAI API<br/>GPT-4o/mini]
        Embeddings[OpenAI Embeddings<br/>text-embedding-ada-002]
    end

    subgraph "AWS Services"
        S3[S3<br/>Document Storage]
        Textract[Textract<br/>OCR Processing]
    end

    subgraph "Database Services"
        PostgreSQL[(PostgreSQL<br/>+ pgvector)]
        Neo4j[(Neo4j<br/>Graph DB)]
        Redis[(Redis<br/>Cache/Queue)]
    end

    App -->|REST API| EKOM
    App -->|HTTP Client| Odluke
    App -->|HTTP Client| Eoglasna

    App -->|API Key| OpenAI
    App -->|API Key| Embeddings

    App -->|SDK| S3
    App -->|SDK| Textract
    S3 -.->|Trigger| Textract

    App -->|PDO| PostgreSQL
    App -->|Bolt Protocol| Neo4j
    App -->|TCP| Redis
```

### MCP (Model Context Protocol) Integration

```mermaid
graph LR
    subgraph "MCP Architecture"
        MCPServer[MCP Server<br/>Laravel App]
        MCPTools[MCP Tools<br/>app/Mcp/Tools/]
    end

    subgraph "Available Tools"
        SearchDecision[search_decisions<br/>Find court decisions]
        GetDecision[get_decision<br/>Get decision details]
        SearchLaw[search_law_articles<br/>Search law articles]
        ExtractCitations[extract_citations<br/>Parse citations]
        CompareFacts[compare_facts<br/>Fact similarity]
    end

    subgraph "MCP Client"
        OdlukeAgent[OdlukeAgent<br/>Uses MCP Tools]
    end

    subgraph "Data Sources"
        OdlukeDB[Odluke.sudovi.hr]
        LawDB[Law Database]
    end

    MCPServer --> MCPTools

    MCPTools --> SearchDecision
    MCPTools --> GetDecision
    MCPTools --> SearchLaw
    MCPTools --> ExtractCitations
    MCPTools --> CompareFacts

    OdlukeAgent --> MCPServer

    SearchDecision --> OdlukeDB
    GetDecision --> OdlukeDB
    SearchLaw --> LawDB
```

---

## Deployment Architecture

### Production Deployment

```mermaid
graph TB
    subgraph "Load Balancer"
        LB[Nginx Load Balancer]
    end

    subgraph "Web Tier"
        Web1[Laravel App Server 1<br/>PHP-FPM + Nginx]
        Web2[Laravel App Server 2<br/>PHP-FPM + Nginx]
    end

    subgraph "Queue Workers"
        Worker1[Queue Worker 1<br/>textract,agents,default]
        Worker2[Queue Worker 2<br/>textract,agents,default]
    end

    subgraph "Database Tier"
        PostgreSQL[(PostgreSQL 15+<br/>pgvector extension)]
        Neo4j[(Neo4j 5.x)]
        Redis[(Redis 7.x)]
    end

    subgraph "Storage"
        S3[(AWS S3<br/>Documents & Results)]
    end

    subgraph "External Services"
        OpenAI[OpenAI API]
        Textract[AWS Textract]
    end

    LB --> Web1
    LB --> Web2

    Web1 --> PostgreSQL
    Web1 --> Neo4j
    Web1 --> Redis
    Web1 --> S3

    Web2 --> PostgreSQL
    Web2 --> Neo4j
    Web2 --> Redis
    Web2 --> S3

    Worker1 --> Redis
    Worker1 --> PostgreSQL
    Worker1 --> S3
    Worker1 --> OpenAI
    Worker1 --> Textract

    Worker2 --> Redis
    Worker2 --> PostgreSQL
    Worker2 --> S3
    Worker2 --> OpenAI
    Worker2 --> Textract
```

### Scaling Strategy

| Component | Scaling Strategy | Notes |
|-----------|-----------------|-------|
| **Web Tier** | Horizontal (Add servers) | Stateless, sessions in Redis |
| **Queue Workers** | Horizontal (Add workers) | Independent queue processing |
| **PostgreSQL** | Vertical + Read Replicas | Primary for writes, replicas for reads |
| **Neo4j** | Cluster (Enterprise) | Causal clustering for HA |
| **Redis** | Cluster + Sentinel | High availability + partitioning |
| **S3** | Auto-scaling | Managed by AWS |

---

## Security Architecture

```mermaid
graph TB
    subgraph "Authentication"
        Auth[Laravel Sanctum<br/>API Tokens]
        Session[Session Auth<br/>Web UI]
    end

    subgraph "Authorization"
        Policies[Authorization Policies]
        Gates[Gates & Abilities]
        RBAC[Role-Based Access Control]
    end

    subgraph "Data Protection"
        Encryption[Encryption at Rest]
        TLS[TLS in Transit]
        Secrets[Secret Management]
    end

    subgraph "API Security"
        RateLimit[Rate Limiting]
        CORS[CORS Configuration]
        CSRF[CSRF Protection]
    end

    Auth --> Policies
    Session --> Policies

    Policies --> Gates
    Gates --> RBAC

    RBAC --> Encryption
    RBAC --> TLS

    Encryption --> Secrets

    Auth --> RateLimit
    Auth --> CORS
    Session --> CSRF
```

---

## Performance Architecture

```mermaid
graph TB
    subgraph "Caching Layers"
        L1[Level 1: Result Cache<br/>Agent execution results]
        L2[Level 2: Query Cache<br/>Database queries]
        L3[Level 3: HTTP Cache<br/>API responses]
    end

    subgraph "Database Optimization"
        Indexes[Strategic Indexes<br/>court, date, ECLI, etc.]
        VectorIndex[IVFFlat Vector Indexes<br/>10-50x faster search]
        Partitioning[Table Partitioning<br/>by date ranges]
    end

    subgraph "Parallel Execution"
        ParallelSearch[Parallel Searches<br/>Law + Decision concurrent]
        BatchLLM[Batched LLM Calls<br/>5 items in 1 call]
        AsyncQueue[Async Queue Processing<br/>Background jobs]
    end

    subgraph "Performance Targets"
        Research[ResearchSpecialist: <5s]
        Precedent[PrecedentAnalyst: <8s]
        MultiAgent[Multi-agent: <30s]
    end

    L1 --> Research
    L2 --> Research
    L3 --> Research

    Indexes --> Research
    VectorIndex --> Research

    ParallelSearch --> Research
    BatchLLM --> Precedent
    AsyncQueue --> MultiAgent
```

**Performance Optimizations**:
1. **Database Indexes**: 50-70% query improvement
2. **Vector Indexes (IVFFlat)**: 10-50x similarity search improvement
3. **Result Caching**: Eliminates redundant agent executions
4. **Parallel Execution**: 30-50% time reduction for independent operations
5. **LLM Batching**: 70% faster, 80% cost reduction

---

## Monitoring & Observability

```mermaid
graph TB
    subgraph "Application Metrics"
        AgentMetrics[Agent Performance<br/>Execution time, success rate]
        APIMetrics[API Metrics<br/>Request rate, latency]
        QueueMetrics[Queue Metrics<br/>Job rate, failures]
    end

    subgraph "Infrastructure Metrics"
        DBMetrics[Database Performance<br/>Query time, connections]
        CacheMetrics[Cache Performance<br/>Hit rate, evictions]
        StorageMetrics[Storage Metrics<br/>S3 usage, costs]
    end

    subgraph "Business Metrics"
        UsageMetrics[Usage Analytics<br/>Cases analyzed, decisions found]
        CostMetrics[Cost Tracking<br/>LLM tokens, API costs]
        AccuracyMetrics[Accuracy Metrics<br/>Benchmark scores]
    end

    subgraph "Logging & Tracing"
        AppLogs[Application Logs<br/>Laravel Log]
        ReasoningTraces[Reasoning Traces<br/>Explainability]
        AuditLog[Audit Log<br/>User actions]
    end

    AgentMetrics --> AppLogs
    APIMetrics --> AppLogs
    QueueMetrics --> AppLogs

    DBMetrics --> AppLogs
    CacheMetrics --> AppLogs

    AgentMetrics --> ReasoningTraces
    UsageMetrics --> AuditLog
```

---

## Technology Stack

### Backend

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| **Framework** | Laravel | 11.x | Web application framework |
| **Language** | PHP | 8.2+ | Server-side programming |
| **Database** | PostgreSQL | 15+ | Primary data store |
| **Vector DB** | pgvector | 0.5+ | Vector embeddings |
| **Graph DB** | Neo4j | 5.x | Citation relationships |
| **Cache/Queue** | Redis | 7.x | Caching & job queues |
| **Storage** | AWS S3 | - | Document storage |
| **OCR** | AWS Textract | - | PDF text extraction |

### AI/ML

| Component | Technology | Purpose |
|-----------|-----------|---------|
| **LLM** | OpenAI GPT-4o / GPT-4o-mini | Text generation & analysis |
| **Embeddings** | text-embedding-ada-002 | Vector embeddings |
| **Vector Search** | pgvector (IVFFlat) | Similarity search |
| **Graph RAG** | Neo4j Cypher | Graph traversal |

### Frontend

| Component | Technology | Purpose |
|-----------|-----------|---------|
| **UI Framework** | Livewire | Reactive components |
| **CSS Framework** | Tailwind CSS | Styling |
| **Build Tool** | Vite | Asset bundling |

---

## File Structure

```
ai-legal-war-machine/
├── app/
│   ├── Agents/              # AI Agents
│   │   ├── Specialists/     # Specialist agents
│   │   └── ...
│   ├── Modules/             # Domain modules
│   │   ├── Evidence/
│   │   ├── Misconduct/
│   │   └── Topics/
│   ├── Services/            # Business logic
│   │   ├── Collaboration/   # Agent collaboration
│   │   ├── Graph/           # Neo4j services
│   │   └── ...
│   ├── Mcp/                 # MCP server & tools
│   ├── Benchmarks/          # Performance benchmarks
│   └── Console/Commands/    # Artisan commands
├── database/
│   ├── migrations/          # Database migrations
│   └── factories/           # Test data factories
├── tests/
│   ├── Feature/             # Feature tests
│   ├── Unit/                # Unit tests
│   └── Fixtures/            # Test fixtures
├── docs/                    # Documentation
│   ├── ARCHITECTURE.md      # This file
│   ├── BENCHMARKS.md
│   ├── PERFORMANCE_OPTIMIZATION.md
│   └── VALIDATION_RESULTS.md
├── config/                  # Configuration files
└── routes/                  # Route definitions
```

---

## Design Patterns

### Agent Pattern

**Pattern**: Autonomous AI agents with specialized roles

**Implementation**:
- Each agent is a self-contained class
- Agents communicate via `SharedAgentContext`
- Agents use `ReasoningTraceService` for explainability
- Agents can send messages via `AgentCommunicationBus`

### Repository Pattern

**Pattern**: Abstraction layer for data access

**Implementation**:
- Services encapsulate business logic
- Models represent data objects
- No direct DB queries in controllers

### Circuit Breaker Pattern

**Pattern**: Prevent cascading failures from external services

**Implementation**:
- `OdlukeClient` implements circuit breaker
- After 3 failures, circuit opens (stops calls)
- Automatic retry after timeout

### Cache-Aside Pattern

**Pattern**: Application manages cache explicitly

**Implementation**:
- `AgentResultCacheService` implements cache-aside
- Try cache first, execute on miss, store result
- TTL-based expiration

---

## References

- [API Documentation](API_DOCUMENTATION.md)
- [Performance Optimization Guide](PERFORMANCE_OPTIMIZATION.md)
- [Benchmark Documentation](BENCHMARKS.md)
- [Testing Guide](TESTING.md)
- [CLAUDE.md](../CLAUDE.md) - Quick reference

---

**Last Updated**: 2025-11-11
**Maintained By**: Development Team
**Version**: 1.0
