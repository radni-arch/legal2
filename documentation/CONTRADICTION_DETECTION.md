# Contradiction Detection System (Sprint 4.6)

## Overview

The Contradiction Detection System automatically identifies conflicting legal precedents during court decision ingestion using LLM-based analysis. When contradictions are detected with high confidence (>0.75), the system creates `CONTRADICTS` relationships in the Neo4j graph database and logs warnings.

**Key Features:**
- ✅ Automated LLM-based contradiction analysis
- ✅ Confidence-based thresholding (>75% for relationship creation)
- ✅ Temporal context awareness (distinguishes evolution from contradiction)
- ✅ Graph relationship creation for visualization
- ✅ Comprehensive logging and error handling

## Architecture

### Components

1. **ContradictionDetectionService** (`app/Services/Graph/ContradictionDetectionService.php`)
   - Core LLM-based contradiction detection
   - Uses GPT-4o for analysis
   - Returns structured results with confidence scores

2. **ContradictionPipelineService** (`app/Services/Graph/ContradictionPipelineService.php`)
   - Orchestrates contradiction detection workflow
   - Finds similar decisions via vector search (with fallback)
   - Creates CONTRADICTS relationships in Neo4j
   - Provides statistics

3. **OdlukeIngestService Integration** (`app/Services/Odluke/OdlukeIngestService.php`)
   - Automatically runs contradiction check after successful ingestion
   - Non-blocking: ingestion succeeds even if contradiction check fails
   - Tracks contradiction statistics in ingestion results

## How It Works

### Detection Workflow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. New Decision Ingested                                   │
│    → Decision stored in PostgreSQL                          │
│    → Chunks embedded in vector store                        │
│    → Synced to Neo4j graph                                  │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. Find Similar Decisions                                   │
│    → Try vector search: findSimilar(decisionId)             │
│    → Fallback: Recent decisions from same court/jurisdiction│
│    → Limit: 10 candidates (configurable)                    │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. LLM Analysis for Each Candidate                          │
│    → Build context: decision texts + temporal metadata      │
│    → GPT-4o analyzes for contradictions                     │
│    → Returns: is_contradiction, confidence, type, severity  │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. Create Relationships (if confidence > 0.75)              │
│    → MERGE (decision1)-[r:CONTRADICTS]->(decision2)         │
│    → Properties: confidence, type, severity, explanation    │
│    → Log warning for manual review                          │
└─────────────────────────────────────────────────────────────┘
```

### Contradiction Types

The LLM classifies contradictions into four types:

1. **legal_conclusion**: Decisions reach opposite legal conclusions
   - Example: "Home search requires warrant" vs. "Warrantless home search allowed"

2. **factual_finding**: Contradictory factual findings about same events
   - Example: "Defendant was at home" vs. "Defendant was at crime scene"

3. **legal_reasoning**: Contradictory legal reasoning or interpretation
   - Example: Different interpretations of same law article

4. **procedural_ruling**: Opposite procedural rulings
   - Example: "Evidence admissible" vs. "Evidence inadmissible"

### Severity Levels

- **high**: Direct contradiction on core legal holding (creates precedent conflict)
- **medium**: Contradictory reasoning that may confuse lower courts
- **low**: Minor inconsistencies, easily distinguishable cases

### Temporal Context Awareness

The system distinguishes between contradictions and temporal evolution:

```php
$metadata = [
    'older_decision_date' => '2015-05-10',
    'newer_decision_date' => '2023-08-15',
];

// LLM receives temporal context in prompt:
// "Decision 1 Date: 2015-05-10"
// "Decision 2 Date: 2023-08-15"
// "(Consider whether this might be temporal evolution rather than contradiction)"
```

**Not contradictions:**
- Newer decision updates older standard (temporal evolution)
- Exception to general rule (legal hierarchy)
- Higher court overrules lower court (jurisdictional hierarchy)

## Usage

### Automatic Detection (During Ingestion)

Contradiction detection runs automatically when new decisions are ingested:

```bash
# Ingest decisions from odluke.sudovi.hr
php artisan odluke:ingest dec-id-123 dec-id-456

# View ingestion results (includes contradiction stats)
# Output:
# ids_processed: 2
# inserted: 150 (chunks)
# contradictions_checked: 2
# contradictions_found: 1
```

### Programmatic Usage

```php
use App\Services\Graph\ContradictionDetectionService;
use App\Services\Graph\ContradictionPipelineService;

// 1. Direct contradiction detection
$contradictionService = app(ContradictionDetectionService::class);

$result = $contradictionService->detectContradiction(
    $decision1Text,
    $decision2Text,
    ['older_decision_date' => '2020-01-15', 'newer_decision_date' => '2023-06-20']
);

if ($result['is_contradiction'] && $result['confidence'] > 0.75) {
    echo "⚠️ Contradiction detected ({$result['confidence']} confidence)\n";
    echo "Type: {$result['contradiction_type']}\n";
    echo "Severity: {$result['severity']}\n";
    echo "Explanation: {$result['explanation']}\n";
}

// 2. Pipeline (finds similar + detects + creates relationships)
$pipeline = app(ContradictionPipelineService::class);

$result = $pipeline->checkNewDecision('decision-id-123', [
    'similarity_limit' => 10,  // Number of similar decisions to check
    'enabled' => true,         // Enable/disable pipeline
]);

// Result structure:
// [
//     'enabled' => true,
//     'decision_id' => 'decision-id-123',
//     'similar_decisions_checked' => 5,
//     'contradictions_found' => 2,
//     'relationships_created' => 2,
//     'details' => [
//         [
//             'contradicts_with' => 'decision-id-456',
//             'confidence' => 0.88,
//             'type' => 'legal_conclusion',
//             'severity' => 'high',
//             'explanation' => '...',
//         ],
//     ],
// ]
```

### Batch Detection

```php
$source = 'Warrantless arrest requires probable cause AND exigent circumstances.';

$candidates = [
    'dec-1' => 'Probable cause alone is sufficient.',
    'dec-2' => 'Both requirements must be met.',
    'dec-3' => 'Arrest warrant always required.',
];

$results = $contradictionService->detectContradictionsInBatch($source, $candidates);

// Results: ['dec-1' => [...], 'dec-2' => [...], 'dec-3' => [...]]
```

### Statistics

```php
$stats = $pipeline->getStatistics();

// [
//     'graph_available' => true,
//     'total_contradictions' => 42,
// ]
```

## Neo4j Graph Queries

### Find All Contradictions

```cypher
MATCH (d1:Decision)-[r:CONTRADICTS]->(d2:Decision)
RETURN d1.case_number, d2.case_number, r.confidence, r.severity, r.explanation
ORDER BY r.confidence DESC
LIMIT 50
```

### Find High-Severity Contradictions

```cypher
MATCH (d1:Decision)-[r:CONTRADICTS {severity: 'high'}]->(d2:Decision)
WHERE r.confidence > 0.85
RETURN d1, r, d2
```

### Contradictions Involving Specific Decision

```cypher
MATCH (d:Decision {case_number: 'K-123/2024'})-[r:CONTRADICTS]-(other:Decision)
RETURN other.case_number, r.confidence, r.type, r.explanation
```

### Contradiction Networks (Decisions contradicting multiple others)

```cypher
MATCH (d:Decision)-[r:CONTRADICTS]->(other:Decision)
WITH d, count(r) as contradiction_count
WHERE contradiction_count > 2
RETURN d.case_number, contradiction_count
ORDER BY contradiction_count DESC
```

## Configuration

### Environment Variables

```env
# Graph database (required for relationship creation)
NEO4J_ENABLED=true
NEO4J_URI=bolt://localhost:7687
NEO4J_PASSWORD=your_password

# OpenAI (required for LLM analysis)
OPENAI_API_KEY=sk-...
```

### Config Files

```php
// config/graph.php
return [
    'contradiction_detection' => [
        'enabled' => env('CONTRADICTION_DETECTION_ENABLED', true),
        'confidence_threshold' => 0.75,
        'similarity_limit' => 10,
    ],
];
```

## Performance Considerations

### LLM Costs

- Model: GPT-4o (temperature: 0.1 for consistency)
- Average tokens per comparison: ~500-800 tokens
- Cost per 1,000 comparisons: ~$3-5 (depending on decision length)

**Optimization:**
- Only checks 10 most similar decisions (configurable)
- Fallback to court/jurisdiction filtering reduces irrelevant comparisons
- Non-blocking: doesn't slow down ingestion

### Throughput

- **Single decision**: ~10 candidates × 2s each = ~20s total
- **Batch ingestion** (100 decisions): ~30-40 minutes (sequential)

**Future optimization:**
- Parallel LLM calls (async processing)
- Caching of frequently compared decision pairs
- Scheduled batch processing (off-peak hours)

## Accuracy & Benchmarking

### Target Metrics

- **Precision**: >80% of detected contradictions are true contradictions
- **Recall**: >75% of actual contradictions are detected
- **F1 Score**: >77%

### Validation Process

1. Manual review of 100 detected contradictions
2. Classification: true positive, false positive, uncertain
3. Adjust confidence threshold if precision < 80%
4. Improve system prompt if recurring false positives

### Current Performance

(To be measured after production deployment)

## Troubleshooting

### "No similar decisions found"

**Cause**: Vector store empty or decision not embedded

**Solution**:
```bash
# Verify decision is in vector store
php artisan tinker
>>> DB::table('court_decision_documents')->where('decision_id', 'dec-123')->count();

# Re-ingest if needed
php artisan odluke:ingest dec-123
```

### "Graph relationship creation failed"

**Cause**: Neo4j unavailable or decision not synced

**Solution**:
```bash
# Check Neo4j connectivity
php artisan neo4j:health

# Sync decision to graph
php artisan neo4j:sync-decisions --id=dec-123
```

### "LLM analysis timed out"

**Cause**: OpenAI API slow or rate limited

**Solution**:
- Check OpenAI API status
- Reduce batch size
- Enable retry logic in future version

### "Low contradiction detection rate"

**Cause**: Insufficient similar decisions or bad similarity matching

**Solutions**:
1. Increase `similarity_limit` from 10 to 20
2. Improve vector embeddings (use better model)
3. Tune fallback query (e.g., include decisions from higher courts)

## Testing

### Unit Tests

```bash
./vendor/bin/phpunit --filter=ContradictionDetectionServiceTest
```

**Coverage:**
- Basic contradiction detection (9 tests)
- Confidence thresholding
- Temporal context
- Batch processing
- Error handling

### Integration Tests

```bash
./vendor/bin/phpunit --filter=ContradictionDetectionIntegrationTest
```

**Coverage:**
- End-to-end workflow (6 tests)
- LLM integration
- Response validation
- Error resilience

### Test Results

```
Tests: 15, Assertions: 43
✅ All tests passing
```

## Future Enhancements

1. **Contradiction Alerts**
   - Email notifications for high-severity contradictions
   - Dashboard widget for contradiction summary
   - Integration with PrecedentAnalystAgent

2. **Machine Learning Improvements**
   - Fine-tuned model for legal contradictions
   - Ensemble approach (multiple models voting)
   - Active learning from manual reviews

3. **Advanced Graph Analysis**
   - Contradiction propagation (transitive contradictions)
   - Temporal contradiction tracking
   - Jurisdiction-specific contradiction networks

4. **Performance Optimization**
   - Async LLM calls (parallel processing)
   - Smart caching of comparison results
   - Incremental updates (only check new decisions)

5. **User Interface**
   - Contradiction visualization in graph viewer
   - Manual override/confirmation workflow
   - Export contradiction reports

## Related Documentation

- [Graph Embeddings (Sprint 4.5)](./GRAPH_EMBEDDINGS.md)
- [Temporal Reasoning (Sprint 4.2)](./TEMPORAL_REASONING.md)
- [Graph Schema (Sprint 4.1)](./GRAPH_SCHEMA.md)
- [Neo4j Integration](./NEO4J.md)

## References

- Sprint 4.6 Acceptance Criteria
- OpenAI GPT-4o Documentation
- Neo4j Cypher Query Language
- PostgreSQL pgvector Extension
