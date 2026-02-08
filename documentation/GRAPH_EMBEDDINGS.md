# Graph Embeddings for Structural Similarity (Sprint 4.5)

## Overview

This document describes the Node2Vec graph embedding system for finding structurally similar court decisions based on citation patterns.

**Key Concept**: Traditional vector embeddings capture *content* similarity. Graph embeddings capture *structural* similarity - decisions that are cited together, cite similar sources, or occupy similar positions in the legal citation network.

## Architecture

### Components

1. **Database Layer** (`decision_graph_embeddings` table)
   - Stores 128-dimensional Node2Vec embeddings
   - Uses PostgreSQL pgvector extension
   - Includes model versioning and training timestamps

2. **Python Training Script** (`scripts/train_graph_embeddings.py`)
   - Exports citation graph from Neo4j
   - Trains Node2Vec model
   - Stores embeddings in PostgreSQL

3. **GraphEmbeddingService** (`app/Services/Graph/GraphEmbeddingService.php`)
   - Laravel interface to Python script
   - Provides embedding CRUD operations
   - Implements graph-based similarity search

4. **Artisan Command** (`php artisan graph:generate-embeddings`)
   - User-friendly command-line interface
   - Statistics and status reporting
   - Training progress tracking

## Installation

### 1. Database Migration

Run the migration to create the embeddings table:

```bash
php artisan migrate
```

This creates:
```sql
CREATE TABLE decision_graph_embeddings (
    decision_id VARCHAR(100) PRIMARY KEY,
    graph_embedding vector(128),
    trained_at TIMESTAMP,
    model_version VARCHAR(50),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### 2. Python Dependencies

Install required Python packages:

```bash
pip install neo4j networkx node2vec psycopg2-binary numpy
```

### 3. Environment Configuration

Ensure your `.env` file has Neo4j and PostgreSQL configuration:

```env
# Neo4j (source for citation graph)
NEO4J_URI=bolt://localhost:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your_password

# PostgreSQL (storage for embeddings)
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_DATABASE=ai_legal_war_machine
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

## Usage

### Generate Embeddings

```bash
# Show current statistics
php artisan graph:generate-embeddings --stats

# Generate embeddings (interactive)
php artisan graph:generate-embeddings

# Clear existing embeddings and regenerate
php artisan graph:generate-embeddings --clear
```

### Programmatic Access

```php
use App\Services\Graph\GraphEmbeddingService;

$service = app(GraphEmbeddingService::class);

// Check if decision has embedding
if ($service->hasEmbedding($decisionId)) {
    // Get embedding data
    $embedding = $service->getEmbedding($decisionId);

    // Find structurally similar decisions
    $similar = $service->findSimilarByGraph($decisionId, $limit = 10);

    foreach ($similar as $result) {
        echo "{$result['decision_id']}: {$result['similarity']}\n";
    }
}

// Get system statistics
$stats = $service->getStatistics();
echo "Coverage: {$stats['coverage_percentage']}%\n";
echo "Total embeddings: {$stats['total_embeddings']}\n";
```

## How Node2Vec Works

### Citation Graph Structure

```
Decision A ──cites──> Decision B ──cites──> Decision C
     │                     │                     │
     └─────cites──────> Decision D <──cites────┘
```

### Training Process

1. **Random Walks**: Generate random walks through the citation graph
   - Walk length: 80 steps
   - Walks per node: 10
   - Parameters: p=1 (return), q=1 (in-out)

2. **Skip-Gram Model**: Learn embeddings using Word2Vec-style training
   - Treats walks as "sentences"
   - Learns which decisions "co-occur" in walks
   - Produces 128-dimensional vectors

3. **Result**: Decisions with similar citation patterns get similar embeddings

### Example Use Cases

**1. Find Precedents via Citation Chains**
- Decision X cites A, B, C
- Decision Y cites A, B, D
- Node2Vec: X and Y are structurally similar (even if content differs)

**2. Identify Hub Decisions**
- Decision A is cited by 50+ other decisions
- Decision B is also cited by 50+ decisions
- Both have high "centrality" → similar graph embeddings

**3. Discover Topic Clusters**
- Decisions citing similar laws cluster together
- Even without explicit topic labels

## Hybrid Similarity Search

### Concept

Combine content and structure for better search:

```
hybrid_score = α * content_similarity + (1-α) * graph_similarity
```

Where:
- `α = 0.5` (configurable weight)
- `content_similarity` = cosine similarity of text embeddings
- `graph_similarity` = cosine similarity of graph embeddings

### Benefits

| Search Type | Finds | Example |
|------------|-------|---------|
| **Content Only** | Decisions with similar text | Both mention "home search" |
| **Graph Only** | Decisions with similar citations | Both cite same precedents |
| **Hybrid** | Both text + structure | Similar topic AND legal reasoning |

### Future Integration

To integrate hybrid search into `DecisionSearchService`:

```php
public function hybridSearch(string $query, array $options = []): array
{
    $alpha = $options['content_weight'] ?? 0.5;

    // 1. Get content-based results
    $contentResults = $this->vectorSearch($query, $options);

    // 2. For each result, get graph similarity
    $graphService = app(GraphEmbeddingService::class);

    foreach ($contentResults['data'] as &$result) {
        $graphSimilar = $graphService->findSimilarByGraph($result['id'], 1);

        // Combine scores
        $contentScore = $result['score'];
        $graphScore = $graphSimilar[0]['similarity'] ?? 0;

        $result['hybrid_score'] = ($alpha * $contentScore) + ((1 - $alpha) * $graphScore);
        $result['content_score'] = $contentScore;
        $result['graph_score'] = $graphScore;
    }

    // Re-sort by hybrid score
    usort($contentResults['data'], fn($a, $b) => $b['hybrid_score'] <=> $a['hybrid_score']);

    return $contentResults;
}
```

## Performance Considerations

### Training Time

- **Small graph** (< 1,000 nodes): ~1 minute
- **Medium graph** (1,000-10,000 nodes): ~5-10 minutes
- **Large graph** (> 10,000 nodes): ~30+ minutes

### Storage

- Each embedding: ~512 bytes (128 floats × 4 bytes)
- 10,000 decisions: ~5 MB
- 100,000 decisions: ~50 MB

### Query Performance

- Similarity search: O(n) with ivfflat index
- Typical query time: < 100ms for 10,000 embeddings

## Benchmarking

### Comparison Metrics

1. **Precision@K**: How many of top-K results are relevant?
2. **Recall@K**: What % of relevant results are in top-K?
3. **Diversity**: Do graph embeddings find different (but valuable) results?

### Expected Results

- **Content similarity**: High precision for keyword matches
- **Graph similarity**: Finds precedents that don't share keywords
- **Hybrid**: Best of both worlds

### Running Benchmarks

> **Note:** The `graph:benchmark` command is planned but not yet implemented. The following shows the intended interface:

```bash
# Create benchmark dataset (PLANNED - not yet implemented)
php artisan graph:benchmark --dataset=precedent_chains --size=100

# Compare search methods (PLANNED - not yet implemented)
php artisan graph:benchmark --compare-methods

# Expected output when implemented:
# Method           | Precision@10 | Recall@10 | Diversity
# -----------------|--------------|-----------|----------
# Content Only     | 0.82         | 0.65      | 0.45
# Graph Only       | 0.71         | 0.78      | 0.89
# Hybrid (α=0.5)   | 0.85         | 0.82      | 0.72
```

**Implementation Plan:**
1. Create `GraphBenchmarkCommand.php` with dataset generation
2. Add benchmark metrics collection (precision, recall, diversity)
3. Implement comparison logic between search methods

## Troubleshooting

### "No decision nodes found in Neo4j"

**Cause**: Citation graph hasn't been synced to Neo4j

**Solution**:
```bash
# Sync decisions to Neo4j first
php artisan graph:sync --all

# Then generate embeddings
php artisan graph:generate-embeddings
```

### "Python dependencies missing"

**Cause**: Required packages not installed

**Solution**:
```bash
pip install neo4j networkx node2vec psycopg2-binary numpy
```

### "Training taking too long"

**Cause**: Large graph or slow machine

**Solution**: Reduce random walk parameters in `train_graph_embeddings.py`:
```python
node2vec = Node2Vec(
    G,
    dimensions=128,
    walk_length=40,    # Reduced from 80
    num_walks=5,       # Reduced from 10
    workers=8,         # Increase if you have more CPUs
)
```

### "Low coverage percentage"

**Cause**: Not all decisions synced to Neo4j or isolated nodes

**Solution**:
- Ensure all decisions have been synced to Neo4j
- Isolated decisions (no citations) won't get embeddings
- This is expected - focus on connected component

## References

- [Node2Vec Paper (Grover & Leskovec, 2016)](https://arxiv.org/abs/1607.00653)
- [PostgreSQL pgvector Documentation](https://github.com/pgvector/pgvector)
- [NetworkX Documentation](https://networkx.org/documentation/stable/)

## Related Sprints

- **Sprint 4.1**: Temporal Legal Reasoning - Graph Schema
- **Sprint 4.2**: TemporalReasoningService Implementation
- **Sprint 4.3**: Graph-Enhanced Research for ResearchSpecialistAgent
- **Sprint 4.4**: Graph-Based Reasoning Chains

## Future Enhancements

1. **Dynamic Re-training**: Auto-retrain when new decisions added
2. **Custom Walk Parameters**: Allow tuning p/q for different legal domains
3. **Multi-Model Ensembles**: Combine multiple embedding methods
4. **Temporal Awareness**: Weight recent citations higher
5. **Cross-Jurisdiction**: Separate models per jurisdiction
