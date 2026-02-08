# LLM Brain Architecture

The LLM Brain is the core AI intelligence layer of the AI Legal War Machine, combining Neo4j graph database with OpenAI LLMs to enable sophisticated legal research, document analysis, and reasoning capabilities.

## Overview

The LLM Brain uses Neo4j as a "knowledge graph" that stores interconnected legal entities, documents, and relationships. Natural language queries are converted to Cypher queries via LLMs, enabling attorneys to explore complex legal relationships without needing graph query expertise.

### Key Capabilities

1. **Hybrid Retrieval** - Combines vector similarity, keyword search, and graph traversal
2. **Natural Language to Cypher** - Converts questions to graph queries
3. **Time-Travel Queries** - Query legal state at any historical date
4. **Reasoning Chains** - Multi-hop graph traversal for complex legal reasoning
5. **Contradiction Detection** - LLM-powered identification of conflicting decisions
6. **Influence Analysis** - PageRank-based discovery of influential documents
7. **Structural Similarity** - Node2Vec embeddings for graph-based similarity

---

## Architecture Components

### Entry Points

| Component | File | Purpose |
|-----------|------|---------|
| LlmBrainPanel | `app/Http/Livewire/LlmBrainPanel.php` | Web UI for natural language queries |
| TestLlmBrainCommand | `app/Console/Commands/TestLlmBrainCommand.php` | CLI testing interface |
| LlmBrainExamples | `app/Examples/LlmBrainExamples.php` | 10 practical usage examples |

### Core Services

| Service | File | Purpose |
|---------|------|---------|
| GraphDatabaseService | `app/Services/GraphDatabaseService.php` | Core Neo4j operations |
| ReasoningChainService | `app/Services/Graph/ReasoningChainService.php` | NL→Cypher conversion |
| GraphRagOrchestrator | `app/Services/Graph/GraphRagOrchestrator.php` | Coordinates sync & queries |
| RagOrchestrator | `app/Services/RagOrchestrator.php` | Hybrid retrieval engine |

---

## Frontend Interface

The LLM Brain has a web-based frontend built with Livewire.

### LlmBrainPanel Component

**Livewire Component:** `app/Http/Livewire/LlmBrainPanel.php`
**Blade View:** `resources/views/livewire/llm-brain-panel.blade.php`
**Tests:** `tests/Feature/Livewire/LlmBrainPanelTest.php`

### Features

1. **Natural Language Query Input** - Textarea for entering questions in Croatian or English
2. **Three Operation Modes:**
   - **Query Mode** (active) - Execute natural language queries against the graph
   - **Chat Mode** (coming soon) - Interactive chat with graph context
   - **Reasoning Chains Mode** (coming soon) - Multi-hop reasoning with explainability

3. **Example Queries** - Pre-configured examples with one-click usage:
   - Find Supreme Court decisions citing ZKP Article 9
   - Find contradicting decisions about proportionality
   - Find binding precedents through citation chains
   - Find decisions affected by law amendment in 2023

4. **Results Display:**
   - Generated Cypher query with syntax highlighting
   - Query explanation from LLM
   - Results in formatted JSON
   - Loading states and error handling

### UI Components

```
┌─────────────────────────────────────────────────────────────┐
│  Mode Selector: [🔍 Query] [💬 Chat] [🧠 Reasoning]         │
├─────────────────────────────────────────────────────────────┤
│  Natural Language Query                                     │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ [Textarea for entering questions]                    │   │
│  └─────────────────────────────────────────────────────┘   │
│  [Execute Query] [Clear Results]                           │
├─────────────────────────────────────────────────────────────┤
│  Example Queries (clickable grid)                          │
│  ┌─────────────────┐  ┌─────────────────┐                  │
│  │ Citation search │  │ Contradictions  │                  │
│  └─────────────────┘  └─────────────────┘                  │
│  ┌─────────────────┐  ┌─────────────────┐                  │
│  │ Multi-hop       │  │ Temporal impact │                  │
│  └─────────────────┘  └─────────────────┘                  │
├─────────────────────────────────────────────────────────────┤
│  Generated Cypher Query                                     │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ MATCH (d:Decision)-[:CITES]->(l:LawDocument)...     │   │
│  └─────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────┤
│  Results (JSON)                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ [{ "case_number": "...", "date": "..." }, ...]      │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Integration

The LlmBrainPanel is embedded in the Graph Dashboard:

```blade
{{-- resources/views/livewire/graph-dashboard.blade.php --}}
@livewire('llm-brain-panel')
```

### How It Works

1. User enters natural language query
2. `executeQuery()` calls `ReasoningChainService::executeReasoningChain()`
3. Service converts NL to Cypher using OpenAI
4. Cypher executes against Neo4j
5. Results returned with:
   - `cypher_query` - Generated Cypher
   - `explanation` - Human-readable explanation
   - `results` - Query results array

```php
// LlmBrainPanel.php
public function executeQuery(): void
{
    $reasoningChain = app(ReasoningChainService::class);
    $result = $reasoningChain->executeReasoningChain($this->naturalQuery);

    $this->queryResult = $result['results'];
    $this->generatedCypher = $result['cypher_query'];
    $this->explanation = $result['explanation'];
}
```

---

## What the LLM Brain Extracts

### From Court Decisions

| Data | Extraction Method |
|------|-------------------|
| Case number, court, date | Pattern matching + metadata |
| Parties (plaintiffs, defendants) | `PartyDetector` service |
| Judges, prosecutors, attorneys | Role keyword patterns |
| Legal issues and holdings | `FactExtractionService` (LLM) |
| Key arguments | `FactExtractionService` (LLM) |
| Evidence mentioned | `FactExtractionService` (LLM) |
| Procedural history | `FactExtractionService` (LLM) |
| Legal citations | `GraphCitationLinker` |
| Keywords/concepts | `AdvancedKeywordExtractor` |

### From Laws (NN Documents)

| Data | Extraction Method |
|------|-------------------|
| Law number (NN format) | Regex patterns |
| Title, jurisdiction | Metadata parsing |
| Effective/promulgation dates | Date extraction |
| Content chunks | Configurable chunking |
| Embeddings | OpenAI text-embedding-3-large |
| Citations to other laws | `GraphCitationLinker` |
| Keywords | `AdvancedKeywordExtractor` |

### From OCR'd Documents (Textract)

| Data | Extraction Method |
|------|-------------------|
| Full text content | AWS Textract / pdftotext |
| Layout information | Textract blocks |
| Tables | `TableExtractorService` |
| Document metadata | `LegalMetadataExtractor` |
| Key phrases | `KeyPhraseExtractor` |

---

## Extraction Services

### AdvancedKeywordExtractor

**File:** `app/Services/AdvancedKeywordExtractor.php`

Hybrid keyword extraction combining:
1. **OpenAI embeddings** - Semantic analysis
2. **TF-IDF** - Statistical term importance
3. **Croatian legal dictionary** - Domain-specific boosting

Built-in Croatian legal concepts (78+ terms):
- `izvršenje presude` (judgment execution)
- `parničko pravo` (civil procedure law)
- `kazneno djelo` (criminal offense)
- `upravni spor` (administrative dispute)

### FactExtractionService

**File:** `app/Services/FactExtractionService.php`

LLM-powered extraction of structured legal facts:
- Parties with roles
- Legal issues addressed
- Holdings and conclusions
- Key arguments from each side
- Evidence and procedural history
- Important dates

### GraphCitationLinker

**File:** `app/Services/Graph/GraphCitationLinker.php`

Detects Croatian legal citations:
- **NN citations:** `NN 123/20`, `Narodne novine 45/2021`
- **Article references:** `članak 5. (NN 123/20)`
- **Named laws:** `Zakon o ... (NN 123/20)`
- **Case references:** `odluka broj: XYZ-123/2020`

### PartyDetector

**File:** `app/Services/LegalMetadata/PartyDetector.php`

Detects legal parties via:
- Role keywords (`tužitelj`, `tuženi`, `optuženik`)
- `između ... i ...` patterns
- `protiv` (against) patterns
- Company suffix detection (d.o.o., d.d., obrt)

---

## Graph Node Types

```
┌─────────────────────────────────────────────────────────────┐
│                     DOCUMENT NODES                          │
├─────────────────────────────────────────────────────────────┤
│  Decision         - Court decisions with metadata           │
│  LawDocument      - Laws with version tracking              │
│  CaseDocument     - Case files and evidence                 │
│  TextractDocument - OCR'd documents from Textract           │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                      ENTITY NODES                           │
├─────────────────────────────────────────────────────────────┤
│  Keyword          - Extracted legal terms/concepts          │
│  Jurisdiction     - Geographic/legal jurisdictions          │
│  Court            - Court entities                          │
│  Prosecutor       - Prosecutor entities                     │
│  Judge            - Judge entities                          │
│  Party            - Legal parties (plaintiffs, defendants)  │
└─────────────────────────────────────────────────────────────┘
```

---

## Graph Relationship Types

### Citation Relationships

```
(Decision)-[:CITES]->(LawDocument)
(Decision)-[:CITES]->(Decision)
(LawDocument)-[:CITES]->(LawDocument)
```
Created by `GraphCitationLinker` when documents reference other documents.

### Contradiction Relationships

```
(Decision)-[:CONTRADICTS {confidence: 0.85, severity: "high"}]->(Decision)
```
Created by `ContradictionDetectionService` using LLM analysis.

### Keyword Relationships

```
(Decision)-[:HAS_KEYWORD {weight: 0.8}]->(Keyword)
(LawDocument)-[:HAS_KEYWORD {weight: 0.7}]->(Keyword)
```
Created by `GraphKeywordLinker` with relevance weights.

### Entity Relationships

```
(Decision)-[:HAS_PROSECUTOR]->(Prosecutor)
(Decision)-[:HAS_JUDGE]->(Judge)
(Decision)-[:HAS_PARTY {role: "plaintiff"}]->(Party)
(LawDocument)-[:BELONGS_TO_JURISDICTION]->(Jurisdiction)
```

### Version Relationships

```
(LawDocument:v2)-[:SUPERSEDES]->(LawDocument:v1)
```
Created for law amendments with temporal tracking.

### Similarity Relationships

```
(Decision)-[:SIMILAR_TO {score: 0.92}]->(Decision)
(LawDocument)-[:RELATED_TO]->(LawDocument)
```
Created by `GraphSimilarityLinker` using embedding similarity.

---

## How Nodes and Edges Connect

### Document Ingestion Flow

```
┌──────────────┐     ┌───────────────┐     ┌─────────────────┐
│   Document   │────>│ TextractService│────>│ CaseIngestPipe  │
│   Upload     │     │   (OCR)        │     │    line         │
└──────────────┘     └───────────────┘     └────────┬────────┘
                                                    │
                     ┌──────────────────────────────┘
                     ▼
┌────────────────────────────────────────────────────────────┐
│                   GraphRagOrchestrator                      │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  1. Create document node (Decision/Law/Case/etc)    │   │
│  │  2. Extract keywords → create Keyword nodes         │   │
│  │  3. Detect citations → create CITES relationships   │   │
│  │  4. Calculate similarity → create SIMILAR_TO edges  │   │
│  │  5. Extract entities → create entity nodes & edges  │   │
│  └─────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────┘
```

### Graph Sync Services

| Service | Creates Nodes | Creates Relationships |
|---------|---------------|----------------------|
| `CaseGraphSyncService` | CaseDocument | HAS_KEYWORD, CITES, SIMILAR_TO |
| `LawGraphSyncService` | LawDocument, Jurisdiction | BELONGS_TO_JURISDICTION, HAS_KEYWORD, CITES |
| `DecisionGraphSyncService` | Decision | HAS_KEYWORD, CITES, SIMILAR_TO |
| `TextractGraphSyncService` | TextractDocument | HAS_KEYWORD, CITES, SIMILAR_TO |

### Linking Process

1. **Keyword Linking** (`GraphKeywordLinker`)
   ```php
   // Extract keywords using hybrid approach
   $keywords = $this->keywordExtractor->extract($text);

   // Create Keyword nodes and HAS_KEYWORD relationships
   foreach ($keywords as $keyword => $weight) {
       $this->graph->upsertNode('Keyword', $keyword, [...]);
       $this->graph->createRelationship(
           $docLabel, $docId,
           'HAS_KEYWORD',
           'Keyword', $keyword,
           ['weight' => $weight]
       );
   }
   ```

2. **Citation Linking** (`GraphCitationLinker`)
   ```php
   // Detect citations in text
   $citations = $this->detectCitations($text);

   // Create CITES relationships to referenced documents
   foreach ($citations as $citation) {
       if ($targetDoc = $this->findDocument($citation)) {
           $this->graph->createRelationship(
               $docLabel, $docId,
               'CITES',
               $targetDoc['label'], $targetDoc['id']
           );
       }
   }
   ```

3. **Similarity Linking** (`GraphSimilarityLinker`)
   ```php
   // Find similar documents via embeddings
   $similar = $this->findSimilarDocuments($embedding, $threshold);

   // Create SIMILAR_TO relationships
   foreach ($similar as $doc => $score) {
       $this->graph->createRelationship(
           $docLabel, $docId,
           'SIMILAR_TO',
           $doc['label'], $doc['id'],
           ['score' => $score]
       );
   }
   ```

---

## Querying the LLM Brain

### Natural Language to Cypher

The `ReasoningChainService` converts natural language queries to Cypher:

**Example Input:**
> "Find Supreme Court decisions that contradict decisions citing Zakon o obveznim odnosima"

**Generated Cypher:**
```cypher
MATCH (law:LawDocument {title: 'Zakon o obveznim odnosima'})
<-[:CITES]-(d1:Decision)
<-[:CONTRADICTS]-(d2:Decision {court: 'Vrhovni sud'})
RETURN d2.case_number, d2.date, d2.summary
```

### Reasoning Patterns Supported

1. **Citation Chain Traversal**
   ```cypher
   MATCH path = (d:Decision)-[:CITES*1..3]->(target:LawDocument)
   WHERE target.title CONTAINS 'Zakon o...'
   RETURN path
   ```

2. **Contradiction Detection**
   ```cypher
   MATCH (d1:Decision)-[:CONTRADICTS]->(d2:Decision)
   WHERE d1.court = 'Vrhovni sud'
   RETURN d1, d2
   ```

3. **Time-Travel Queries**
   ```cypher
   MATCH (law:LawDocument)
   WHERE law.valid_from <= date('2020-01-01')
     AND (law.valid_until IS NULL OR law.valid_until > date('2020-01-01'))
   RETURN law
   ```

4. **Influence Analysis**
   ```cypher
   CALL gds.pageRank.stream('citation-graph')
   YIELD nodeId, score
   RETURN gds.util.asNode(nodeId).title, score
   ORDER BY score DESC
   LIMIT 10
   ```

---

## Advanced Features

### Contradiction Detection

**Service:** `app/Services/Graph/ContradictionDetectionService.php`

Uses LLM to analyze decision pairs and detect contradictions:
- Compares holdings and reasoning
- Returns confidence scores
- Categorizes contradiction types
- Assigns severity levels (low/medium/high)
- Creates CONTRADICTS relationships

### Temporal Reasoning

**Service:** `app/Services/Graph/TemporalReasoningService.php`

- Tracks law versions with `valid_from`/`valid_until` dates
- Enables "what was the law on date X?" queries
- Detects contradictions with temporal awareness
- Supports SUPERSEDES relationship chains

### Entity Tracking

**Service:** `app/Services/Graph/EntityTrackingService.php`

- Detects new entities (prosecutors, judges, keywords, courts)
- Time-window-based detection (e.g., last 7 days)
- Relevance scoring based on decision impact
- Entity type weighting (prosecutor > judge > court > keyword)

### Graph Embeddings

**Service:** `app/Services/Graph/GraphEmbeddingService.php`

- Generates Node2Vec embeddings for structural similarity
- 128-dimensional embeddings stored in PostgreSQL
- Enables similarity search beyond content matching
- Trained using Python graph ML libraries

---

## Example Usage

### Web Interface (LlmBrainPanel)

```php
// Natural language query
$query = "Which decisions have been contradicted by the Supreme Court in 2023?";

// ReasoningChainService converts to Cypher
$cypher = $reasoningService->translateToCypher($query);

// Execute against Neo4j
$results = $graphService->run($cypher);

// Display with explanation
return view('results', [
    'results' => $results,
    'cypher' => $cypher,
    'explanation' => $reasoningService->getExplanation()
]);
```

### CLI Testing

```bash
php artisan test:llm-brain

# Interactive menu:
# 1. Hybrid retrieval
# 2. Natural language query
# 3. Time-travel query
# 4. Reasoning chains
# 5. Contradiction detection
# ...
```

### Programmatic Access

```php
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\Graph\ReasoningChainService;

// Sync document to graph
$orchestrator->syncDocument($document);

// Query with natural language
$reasoning = app(ReasoningChainService::class);
$results = $reasoning->query("Find all decisions applying članak 5");

// Get influence scores
$helper = app(GraphQueryHelper::class);
$influential = $helper->findInfluentialDocuments('Decision', 10);
```

---

## Configuration

### Neo4j Connection

```env
NEO4J_HOST=localhost
NEO4J_PORT=7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=secret
NEO4J_DATABASE=neo4j
```

### OpenAI Settings

```env
OPENAI_API_KEY=sk-...
OPENAI_EMBEDDING_MODEL=text-embedding-3-large
OPENAI_CHAT_MODEL=gpt-4
```

### Feature Flags

```env
GRAPH_SYNC_ENABLED=true
CONTRADICTION_DETECTION_ENABLED=true
TEMPORAL_REASONING_ENABLED=true
```

---

## File Reference

### Core Services
- `app/Services/GraphDatabaseService.php` - Neo4j operations
- `app/Services/Graph/GraphRagOrchestrator.php` - Orchestration
- `app/Services/Graph/ReasoningChainService.php` - NL→Cypher
- `app/Services/RagOrchestrator.php` - Hybrid retrieval

### Extraction Services
- `app/Services/AdvancedKeywordExtractor.php` - Keyword extraction
- `app/Services/FactExtractionService.php` - Fact extraction
- `app/Services/LegalReasoning/FactPatternExtractor.php` - Pattern extraction
- `app/Services/LegalMetadata/PartyDetector.php` - Party detection
- `app/Services/LegalMetadata/KeyPhraseExtractor.php` - Key phrase extraction

### Graph Sync Services
- `app/Services/Graph/CaseGraphSyncService.php`
- `app/Services/Graph/LawGraphSyncService.php`
- `app/Services/Graph/DecisionGraphSyncService.php`
- `app/Services/Graph/TextractGraphSyncService.php`

### Graph Linking Services
- `app/Services/Graph/GraphKeywordLinker.php`
- `app/Services/Graph/GraphCitationLinker.php`
- `app/Services/Graph/GraphSimilarityLinker.php`

### Advanced Features
- `app/Services/Graph/ContradictionDetectionService.php`
- `app/Services/Graph/TemporalReasoningService.php`
- `app/Services/Graph/EntityTrackingService.php`
- `app/Services/Graph/GraphEmbeddingService.php`

### Entry Points
- `app/Http/Livewire/LlmBrainPanel.php` - Web UI
- `app/Console/Commands/TestLlmBrainCommand.php` - CLI
- `app/Examples/LlmBrainExamples.php` - Usage examples
