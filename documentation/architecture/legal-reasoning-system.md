# Legal Reasoning System - Architecture & Flow Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [Database Schema](#database-schema)
3. [Service Architecture](#service-architecture)
4. [Data Flow](#data-flow)
5. [API Flow](#api-flow)
6. [Component Interactions](#component-interactions)
7. [Implementation Status](#implementation-status)

---

## System Overview

The Legal Reasoning System provides AI-powered legal analytics, predictive capabilities, and strategic planning for legal cases. It consists of three main subsystems:

1. **Legal Reasoning Engine** - Conflict resolution, citation analysis, legal logic
2. **Predictive Analytics** - Outcome prediction, duration estimation, impact analysis
3. **Legal Strategy Builder** - Argument generation, risk assessment, action planning

### Technology Stack
- **Framework**: Laravel 12.0
- **Database**: PostgreSQL with pgvector extension
- **Graph DB**: Neo4j (for citation networks)
- **AI/ML**: OpenAI GPT-4o-mini, text-embedding-3-small
- **Vector Search**: pgvector (1536 dimensions)

---

## Database Schema

### Core Tables

```mermaid
erDiagram
    CASES ||--o{ CASE_FEATURES : has
    CASES ||--o{ CASE_PREDICTIONS : has
    CASES ||--o{ CASE_STRATEGIES : has
    CASES ||--o{ CASE_DOCUMENTS : has
    COURT_DECISIONS ||--o| DECISION_IMPACT_METRICS : has
    COURT_DECISIONS ||--o{ COURT_DECISION_DOCUMENTS : has

    CASES {
        ulid id PK
        string case_number UK
        string title
        string client_name
        string opponent_name
        string court
        string jurisdiction
        date filing_date
        string status
        json tags
        text description
    }

    CASE_FEATURES {
        ulid id PK
        ulid case_id FK
        string case_type
        string case_category
        string complexity_level
        float complexity_score
        int document_count
        int precedent_count
        string client_type
        string opponent_type
        json legal_issues
        vector embedding
        json embedding_vector
        timestamp features_extracted_at
    }

    CASE_PREDICTIONS {
        ulid id PK
        ulid case_id FK
        string prediction_type
        json features
        json prediction
        float confidence
        string model_version
        json similar_cases
        text reasoning
        timestamp predicted_at
        timestamp actual_outcome_at
        json actual_outcome
        float accuracy_score
    }

    CASE_STRATEGIES {
        ulid id PK
        ulid case_id FK
        string version
        string status
        json objectives
        json analysis
        json arguments
        json risks
        json precedents
        json action_plan
        json timeline
        float confidence_score
        bigint created_by FK
        bigint approved_by FK
        timestamp approved_at
    }

    DECISION_IMPACT_METRICS {
        ulid id PK
        ulid decision_id FK
        int citation_count
        int direct_citations
        int indirect_citations
        float authority_score
        float precedent_strength
        float influence_score
        json citations_over_time
        json jurisdictional_spread
        json citing_courts
        timestamp last_calculated_at
    }
```

### Indexes & Performance

**case_features:**
- `(case_type, case_category)` - Fast filtering by type
- `complexity_score` - Range queries
- `case_id` UNIQUE - One feature set per case
- Vector index on `embedding` (IVFFlat for pgvector)

**case_predictions:**
- `(case_id, prediction_type)` - Multi-column lookup
- `predicted_at` - Temporal queries

**decision_impact_metrics:**
- `authority_score`, `precedent_strength`, `citation_count` - Ranking queries

---

## Service Architecture

### Dependency Graph

```mermaid
graph TB
    subgraph "API Layer"
        RC[ReasoningController]
        AC[AnalyticsController]
        SC[StrategyController]
    end

    subgraph "Legal Reasoning Services"
        CR[ConflictResolver]
        CA[CitationAnalyzer]
        LE[LogicEngine]
    end

    subgraph "Predictive Analytics Services"
        PA[PredictiveAnalytics]
        OP[OutcomePredictor]
        DE[DurationEstimator]
        IA[ImpactAnalyzer]
        FE[FeatureExtractor]
    end

    subgraph "Strategy Services"
        SB[StrategyBuilder]
        AG[ArgumentGenerator]
        RA[RiskAssessor]
        SP[StrategicPlanner]
    end

    subgraph "Infrastructure"
        OAI[OpenAIService]
        GDB[GraphDatabaseService]
        VEC[CaseVectorStoreService]
    end

    subgraph "Models"
        CF[CaseFeature]
        CP[CasePrediction]
        CS[CaseStrategy]
        DIM[DecisionImpactMetric]
    end

    RC --> CR
    RC --> CA
    RC --> LE

    AC --> PA
    PA --> OP
    PA --> DE
    PA --> IA
    OP --> FE
    OP --> VEC

    SC --> SB
    SB --> OP
    SB --> AG
    SB --> RA
    SB --> SP

    CR --> GDB
    CR --> OAI
    CA --> GDB
    LE --> OAI
    IA --> GDB
    AG --> OAI
    FE --> OAI

    FE --> CF
    PA --> CP
    SB --> CS
    IA --> DIM
```

### Service Registrations

All services registered as singletons in `LegalReasoningServiceProvider`:

```php
// Foundation Services
FeatureExtractor(OpenAIService)
OutcomePredictor(OpenAIService, CaseVectorStoreService, FeatureExtractor)

// Reasoning Services
ConflictResolver(GraphDatabaseService, OpenAIService)
CitationAnalyzer(GraphDatabaseService)
LogicEngine(OpenAIService)

// Analytics Services
DurationEstimator()
ImpactAnalyzer(GraphDatabaseService)
PredictiveAnalytics(OutcomePredictor, DurationEstimator, ImpactAnalyzer)

// Strategy Services
ArgumentGenerator(OpenAIService)
RiskAssessor(ConflictResolver)
StrategicPlanner(DurationEstimator)
StrategyBuilder(OutcomePredictor, ArgumentGenerator, RiskAssessor, OpenAIService)
```

---

## Data Flow

### 1. Feature Extraction & Persistence Flow

```mermaid
sequenceDiagram
    participant API as API Request
    participant PA as PredictiveAnalytics
    participant OP as OutcomePredictor
    participant FE as FeatureExtractor
    participant DB as PostgreSQL
    participant OAI as OpenAI

    API->>PA: predictOutcome(caseId)
    PA->>OP: predictOutcome(caseId)
    OP->>FE: extractCaseFeatures(case)

    FE->>FE: classifyCaseType()
    FE->>FE: calculateComplexity()
    FE->>FE: extractLegalIssues()
    FE->>OAI: embeddings(case_text)
    OAI-->>FE: embedding[1536]

    FE-->>OP: features{type, complexity, embedding}

    OP->>DB: SELECT from case_features<br/>WHERE 1-(embedding <=> ?) > 0.7
    DB-->>OP: similar_cases[]

    OP->>OP: analyzeSimilarOutcomes()
    OP->>OAI: complete(analysis_prompt)
    OAI-->>OP: reasoning_text

    OP-->>PA: prediction{outcome, confidence, reasoning}

    PA->>DB: INSERT case_predictions
    DB-->>PA: prediction_id

    PA-->>API: prediction_result
```

### 2. Feature Persistence Flow

```mermaid
sequenceDiagram
    participant FE as FeatureExtractor
    participant DB as PostgreSQL

    Note over FE: extractAndPersistCaseFeatures()

    FE->>FE: extractCaseFeatures()
    FE->>DB: INSERT/UPDATE case_features

    alt PostgreSQL with pgvector
        FE->>DB: UPDATE embedding = ?::vector
        Note right of DB: Native vector type
    else MySQL/SQLite
        Note right of DB: JSON fallback
    end

    DB-->>FE: CaseFeature model
```

### 3. Outcome Prediction Flow

```mermaid
flowchart TD
    Start[API: POST /analytics/predict-outcome/caseId] --> LoadCase[Load LegalCase with documents]
    LoadCase --> CheckCache{Features<br/>cached?}

    CheckCache -->|Yes, <7 days| UseCache[Use cached features]
    CheckCache -->|No| Extract[Extract fresh features]

    Extract --> Embed[Generate embedding<br/>OpenAI API]
    Embed --> Persist[Persist to case_features]

    UseCache --> Search[Vector similarity search<br/>pgvector query]
    Persist --> Search

    Search --> Filter{Found similar<br/>cases?}

    Filter -->|Yes| Analyze[Analyze outcomes<br/>Calculate probabilities]
    Filter -->|No| NoData[Return low confidence]

    Analyze --> LLM[LLM analysis<br/>Nuanced reasoning]
    LLM --> Factors[Identify key factors]

    Factors --> SavePred[Save to case_predictions]
    NoData --> SavePred

    SavePred --> Return[Return prediction JSON]
    Return --> End[Response]
```

---

## API Flow

### API Endpoints Structure

```mermaid
graph LR
    subgraph "Reasoning API"
        R1[POST /reasoning/analyze-conflict]
        R2[POST /reasoning/resolve-conflict]
        R3[POST /reasoning/authority-score]
        R4[POST /reasoning/parse-logic]
        R5[POST /reasoning/apply-deductive]
    end

    subgraph "Analytics API"
        A1[POST /analytics/predict-outcome/:id]
        A2[POST /analytics/estimate-duration/:id]
        A3[POST /analytics/analyze-impact/:id]
        A4[POST /analytics/comprehensive/:id]
        A5[POST /analytics/batch-predict]
    end

    subgraph "Strategy API"
        S1[POST /strategy/build/:id]
        S2[POST /strategy/generate-arguments/:id]
        S3[POST /strategy/assess-risks/:id]
        S4[POST /strategy/action-plan/:id]
        S5[POST /strategy/comprehensive/:id]
    end
```

### Request/Response Flow

```mermaid
sequenceDiagram
    participant Client
    participant Route as routes/api.php
    participant MW as Middleware<br/>(throttle:60,1)
    participant Ctrl as AnalyticsController
    participant Svc as PredictiveAnalytics
    participant DB as Database

    Client->>Route: POST /api/analytics/predict-outcome/01J...
    Route->>MW: Apply rate limiting
    MW->>Ctrl: predictOutcome(caseId)

    Ctrl->>Ctrl: generateRequestId()
    Ctrl->>Ctrl: Log request

    Ctrl->>Svc: predictOutcome(caseId)

    alt Success
        Svc->>DB: Query & Predict
        DB-->>Svc: Result
        Svc-->>Ctrl: prediction[]
        Ctrl-->>Client: 200 OK<br/>{success, prediction}
    else Error
        Svc->>Ctrl: throw Exception
        Ctrl->>Ctrl: Log error
        Ctrl-->>Client: 500 Error<br/>{success:false, error}
    end
```

---

## Component Interactions

### 1. OutcomePredictor - Complete Flow

```mermaid
flowchart TD
    A[OutcomePredictor::predictOutcome] --> B[Load LegalCase + documents]
    B --> C[FeatureExtractor::extractCaseFeatures]

    C --> D1[classifyCaseType<br/>keyword matching]
    C --> D2[calculateComplexity<br/>multi-factor score]
    C --> D3[extractLegalIssues<br/>from tags/description]
    C --> D4[generateCaseEmbedding<br/>OpenAI API]

    D1 & D2 & D3 & D4 --> E[features array]

    E --> F[findSimilarCases<br/>pgvector query]
    F --> G{Similar<br/>cases found?}

    G -->|Yes| H[analyzeSimilarOutcomes<br/>calculate probabilities]
    G -->|No| I[Return unknown<br/>confidence=0]

    H --> J[llmOutcomeAnalysis<br/>GPT-4o-mini reasoning]
    J --> K[identifyKeyFactors<br/>extract influences]

    K --> L[Return prediction<br/>with metadata]
    I --> L

    L --> M[PredictiveAnalytics<br/>persist to DB]
```

### 2. FeatureExtractor - Persistence Strategy

```mermaid
flowchart LR
    A[getCaseFeatures] --> B{Features<br/>exist?}

    B -->|No| C[Extract fresh]
    B -->|Yes| D{Age < 7 days?}

    D -->|Yes| E[Return cached]
    D -->|No| C

    C --> F[extractCaseFeatures]
    F --> G[Generate embedding]
    G --> H[Calculate metrics]

    H --> I[extractAndPersistCaseFeatures]
    I --> J[CaseFeature::updateOrCreate]
    J --> K{PostgreSQL?}

    K -->|Yes| L[Store as vector type]
    K -->|No| M[Store as JSON]

    L & M --> N[Return features]
    E --> N
```

### 3. PredictiveAnalytics - Orchestration

```mermaid
graph TB
    PA[PredictiveAnalytics]

    PA -->|delegates| OP[OutcomePredictor]
    PA -->|delegates| DE[DurationEstimator]
    PA -->|delegates| IA[ImpactAnalyzer]

    OP --> DB1[(case_predictions)]
    DE --> DB1
    IA --> DB2[(decision_impact_metrics)]

    PA -->|reads| DB1
    PA -->|updates| ACC[Accuracy Tracking]

    ACC -->|calculateAccuracyScore| COMP[Compare predicted<br/>vs actual]
    COMP --> SCORE[accuracy_score: 0-1]
```

### 4. Strategy Builder Flow

```mermaid
sequenceDiagram
    participant API
    participant SB as StrategyBuilder
    participant OP as OutcomePredictor
    participant AG as ArgumentGenerator
    participant RA as RiskAssessor
    participant SP as StrategicPlanner

    API->>SB: buildCaseStrategy(caseId, objectives)

    par Parallel Analysis
        SB->>OP: findSimilarCases()
        SB->>AG: generateArguments()
        SB->>RA: assessRisks()
    end

    OP-->>SB: similar_cases
    AG-->>SB: arguments[]
    RA-->>SB: risks{}

    SB->>SB: analyzeCasePosition()
    SB->>SB: selectPrecedents()
    SB->>SP: createTimeline()
    SP-->>SB: timeline{}

    SB->>SB: synthesizeStrategy()<br/>LLM holistic view

    SB->>DB: CaseStrategy::create()
    SB-->>API: comprehensive_strategy
```

---

## Implementation Status

### ✅ Completed (Phase C + Fixes)

**Database Layer:**
- ✅ 4 migrations created
- ✅ 4 Eloquent models created
- ✅ Relationships added to existing models
- ✅ Vector storage (pgvector) support

**API Layer:**
- ✅ 3 controllers (15 endpoints total)
- ✅ API routes with throttling
- ✅ Request validation
- ✅ Error handling & logging

**Service Layer:**
- ✅ OutcomePredictor (330 lines)
- ✅ FeatureExtractor (394 lines)
- ✅ PredictiveAnalytics (220 lines)
- ✅ Service provider registrations
- ✅ Dependency injection configured

**Features:**
- ✅ Feature extraction with caching
- ✅ Feature persistence to database
- ✅ Vector similarity search
- ✅ Outcome prediction with LLM reasoning
- ✅ Prediction persistence & tracking
- ✅ Accuracy measurement capability

### ⏳ Pending (Phase B - Remaining Services)

**Reasoning Services (3):**
- ⏳ ConflictResolver (skeleton)
- ⏳ CitationAnalyzer (skeleton)
- ⏳ LogicEngine (skeleton)

**Analytics Services (2):**
- ⏳ DurationEstimator (skeleton)
- ⏳ ImpactAnalyzer (skeleton)

**Strategy Services (4):**
- ⏳ ArgumentGenerator (skeleton)
- ⏳ RiskAssessor (skeleton)
- ⏳ StrategicPlanner (skeleton)
- ⏳ StrategyBuilder (skeleton)

---

## Key Technical Decisions

### 1. Vector Storage Strategy
- **Primary**: PostgreSQL pgvector extension (native vector type)
- **Fallback**: JSON column for MySQL/SQLite
- **Dimension**: 1536 (OpenAI text-embedding-3-small)
- **Index**: IVFFlat for similarity search

### 2. Feature Caching
- **TTL**: 7 days
- **Rationale**: Cases don't change rapidly
- **Refresh**: Automatic on stale data or manual `refresh=true`

### 3. Prediction Persistence
- **Why**: Track accuracy over time
- **Accuracy Calculation**: Compare predicted vs actual outcomes
- **Use Cases**: Model improvement, confidence calibration

### 4. Service Orchestration
- **Pattern**: Facade pattern (PredictiveAnalytics orchestrates)
- **Benefits**: Single entry point, transaction management, consistent logging

### 5. Error Handling
- **Strategy**: Try-catch with detailed logging
- **Response**: Always return JSON with success flag
- **Logging**: Include request_id for tracing

---

## Performance Considerations

### Database Queries
- Vector similarity: `O(n*d)` where n=cases, d=1536
- Optimization: IVFFlat index reduces to `O(log n)`
- Threshold: 0.7 similarity minimum

### API Response Times
**Expected:**
- Feature extraction: 1-3s (with OpenAI embedding)
- Similarity search: 100-500ms (indexed)
- LLM analysis: 2-5s
- **Total**: ~5-10s for full prediction

**Caching Strategy:**
- Features: 7-day TTL
- API responses: Not cached (real-time predictions)
- Similar cases: Cached at DB level (pgvector index)

---

## Future Enhancements

1. **Batch Processing**: Process multiple cases in parallel
2. **Model Versioning**: Track which model version made predictions
3. **Feature Engineering**: Add more sophisticated features
4. **Custom Models**: Train domain-specific models
5. **Real-time Updates**: WebSocket for long-running analyses
6. **Explanation Engine**: SHAP values for feature importance
7. **A/B Testing**: Compare different prediction strategies

---

## Conclusion

The Legal Reasoning System is now architecturally complete with:
- ✅ Solid database foundation (4 tables, relationships, indexes)
- ✅ Clean API layer (15 endpoints across 3 controllers)
- ✅ Core analytics working (outcome prediction with persistence)
- ✅ Proper service orchestration and dependency injection
- ✅ Vector similarity search operational
- ⏳ 9 services remaining to implement (all have skeletons)

**Next Steps**: Implement remaining services one-by-one following the same pattern established with OutcomePredictor and PredictiveAnalytics.

---

Generated: 2025-10-28
Version: 1.0.0
System: AI Legal War Machine - Legal Reasoning Subsystem
