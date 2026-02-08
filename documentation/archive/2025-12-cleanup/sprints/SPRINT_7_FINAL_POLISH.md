# Sprint 7: Final Polish - Graph Embeddings & Observability

**Goal**: Complete the remaining 13 story points to achieve 100% sprint completion and full production readiness.

**Duration**: 1 week
**Total Points**: 13
**Priority**: HIGH - Blocks full production deployment

---

## Sprint Overview

This sprint completes the two remaining high-priority items from the original roadmap:
1. **Graph Embeddings Training** (Sprint 4.5) - 8 points
2. **Observability Polish** (Sprint 1.7) - 5 points

Upon completion, the system will achieve:
- 100% sprint completion (470/470 story points)
- Full graph intelligence capabilities
- Production-grade monitoring and alerting
- Complete game-changer status

---

## User Stories

### 7.1 Node2Vec Graph Embeddings Training
**Priority**: HIGH
**Points**: 8
**Owner**: AI/ML + Backend
**Sprint**: 4.5 (carried over)

**Story**: As the graph intelligence system, I need to train Node2Vec embeddings on the court decision citation graph so that I can find similar decisions based on structural relationships, not just content similarity.

**Context**:
- Migration exists: `database/migrations/2025_11_11_000000_create_decision_graph_embeddings_table.php`
- Documentation exists: `docs/GRAPH_EMBEDDINGS.md`
- Schema is ready, but training pipeline is incomplete

**Tasks**:
- [ ] Install/verify Node2Vec library (Python: `node2vec` or PHP bridge)
- [ ] Create `app/Services/Graph/Node2VecTrainer.php` service
  - [ ] `exportGraphToEdgeList()` - Export Neo4j citation graph to edge list format
  - [ ] `trainEmbeddings()` - Train Node2Vec model (128 dimensions, walk_length=80, num_walks=10, p=1, q=1)
  - [ ] `saveEmbeddings()` - Save to `decision_graph_embeddings` table
  - [ ] `updateEmbeddings()` - Incremental training for new decisions
- [ ] Create `app/Console/Commands/Graph/TrainEmbeddingsCommand.php`
  - [ ] `php artisan graph:train-embeddings` - Train on full graph
  - [ ] `php artisan graph:train-embeddings --incremental` - Update for new nodes
  - [ ] Progress bar for training
  - [ ] Validation of embedding quality (average cosine similarity)
- [ ] Integrate embeddings into `GraphResearchEnhancer.php`
  - [ ] Add `findSimilarByEmbedding()` method
  - [ ] Hybrid search: vector (0.6) + graph embedding (0.4) weights
  - [ ] Compare precision/recall vs. vector-only
- [ ] Create benchmark: `app/Benchmarks/GraphEmbeddingSimilarityBenchmark.php`
  - [ ] Test: Do structurally similar decisions (same citation network) have similar embeddings?
  - [ ] Acceptance: >0.7 average cosine similarity for decisions citing same laws
- [ ] Write tests:
  - [ ] `tests/Unit/Services/Graph/Node2VecTrainerTest.php` - Mock edge list export
  - [ ] `tests/Integration/GraphEmbeddingIntegrationTest.php` - Full training pipeline
- [ ] Schedule automatic retraining
  - [ ] Daily incremental updates (new decisions only)
  - [ ] Weekly full retraining
  - [ ] Add to `app/Console/Kernel.php` schedule

**Acceptance Criteria**:
- ✅ Node2Vec model trains successfully on decision graph (>1000 nodes)
- ✅ 128-dimensional embeddings stored in database
- ✅ `graph:train-embeddings` command completes in <10 minutes for full graph
- ✅ Hybrid vector+embedding search improves precision by >10% (benchmark)
- ✅ Embeddings update incrementally when new decisions ingested
- ✅ Benchmark shows >0.7 similarity for decisions with shared citations
- ✅ Documentation updated with embedding training guide

**Database Schema** (already exists):
```sql
CREATE TABLE decision_graph_embeddings (
    id BIGSERIAL PRIMARY KEY,
    decision_id INTEGER REFERENCES decisions(id),
    embedding VECTOR(128), -- pgvector extension
    trained_at TIMESTAMP,
    model_version VARCHAR(50),
    UNIQUE(decision_id)
);

CREATE INDEX ON decision_graph_embeddings USING ivfflat (embedding vector_cosine_ops);
```

**Dependencies**:
- Neo4j graph populated with decisions (✅ Complete)
- PostgreSQL pgvector extension installed
- Python environment (if using Python Node2Vec library)

---

### 7.2 Observability Polish - Grafana Dashboards
**Priority**: MEDIUM
**Points**: 5
**Owner**: DevOps + Backend
**Sprint**: 1.7 (carried over)

**Story**: As a system administrator, I need production-grade Grafana dashboards and alerting so that I can monitor system health, agent performance, and detect issues before they impact users.

**Context**:
- Monitoring documentation exists: `docs/MONITORING.md`
- Structured logging implemented
- Missing: Actual Grafana dashboards and alert delivery

**Tasks**:
- [ ] **Grafana Dashboard Setup**
  - [ ] Create `grafana/dashboards/ai-legal-overview.json`
    - [ ] System health panel (CPU, memory, queue depth)
    - [ ] Agent performance panel (avg duration, success rate per agent)
    - [ ] API metrics panel (request rate, error rate, p95 latency)
    - [ ] Cost tracking panel (OpenAI tokens, cost per day/week/month)
  - [ ] Create `grafana/dashboards/agent-deep-dive.json`
    - [ ] Per-agent success rate over time
    - [ ] Reasoning trace depth histogram
    - [ ] Confidence score distribution
    - [ ] Active learning opportunities flagged/resolved
  - [ ] Create `grafana/dashboards/data-pipeline.json`
    - [ ] Textract job status (queued, processing, failed)
    - [ ] OdlukeSearchAgent extraction accuracy
    - [ ] Vector store ingestion rate
    - [ ] Neo4j query performance
  - [ ] Create `grafana/dashboards/benchmarks.json`
    - [ ] Benchmark scores over time (15 benchmarks)
    - [ ] Regression detection (alert if score drops >5%)
    - [ ] Test suite pass rate

- [ ] **Prometheus Metrics Export**
  - [ ] Add `app/Services/Metrics/PrometheusExporter.php`
  - [ ] Expose `/metrics` endpoint (Laravel Prometheus package)
  - [ ] Metrics to track:
    - `agent_duration_seconds{agent_type}` - Histogram
    - `agent_success_total{agent_type}` - Counter
    - `agent_failure_total{agent_type,error_type}` - Counter
    - `openai_tokens_total{model}` - Counter
    - `openai_cost_dollars{model}` - Counter
    - `queue_depth{queue_name}` - Gauge
    - `benchmark_score{benchmark_name}` - Gauge
    - `learning_opportunities_flagged_total` - Counter
    - `reasoning_trace_depth{agent_type}` - Histogram

- [ ] **Alerting Rules**
  - [ ] Create `grafana/alerts/critical.yml`
    - [ ] Queue depth >100 for >5 minutes → Critical
    - [ ] Agent failure rate >20% for >10 minutes → Critical
    - [ ] OpenAI API errors >10 in 5 minutes → Critical
    - [ ] Database connection pool exhausted → Critical
  - [ ] Create `grafana/alerts/warning.yml`
    - [ ] Benchmark score drops >5% from baseline → Warning
    - [ ] Agent p95 latency >30s → Warning
    - [ ] Learning opportunities not reviewed in 7 days → Warning
    - [ ] Neo4j query time >5s → Warning
  - [ ] Configure alert delivery:
    - [ ] Email alerts (configurable SMTP)
    - [ ] Slack webhook (optional)
    - [ ] PagerDuty integration (optional for production)

- [ ] **Documentation & Testing**
  - [ ] Update `docs/MONITORING.md` with Grafana setup instructions
  - [ ] Create `docs/RUNBOOK.md` - Incident response guide
    - [ ] What to do when queue depth alert fires
    - [ ] How to investigate agent failures
    - [ ] OpenAI rate limit handling
    - [ ] Database performance troubleshooting
  - [ ] Test alert delivery manually
  - [ ] Screenshot dashboards for documentation

**Acceptance Criteria**:
- ✅ Grafana dashboards visualize all key metrics (4 dashboards created)
- ✅ Prometheus `/metrics` endpoint exports 10+ custom metrics
- ✅ Critical alerts fire within 5 minutes of issue
- ✅ Warning alerts fire within 10 minutes of degradation
- ✅ Alert delivery tested (email or Slack confirmed received)
- ✅ Runbook provides clear incident response steps
- ✅ Dashboard screenshots included in `docs/MONITORING.md`

**Dependencies**:
- Prometheus server running (or cloud metrics service)
- Grafana instance accessible
- SMTP/Slack configured for alerts

**Optional Enhancements** (not required for completion):
- [ ] Log aggregation (Loki + Grafana)
- [ ] Distributed tracing (Jaeger integration)
- [ ] Custom alerting UI in Laravel app

---

## Sprint Goals

### Definition of Done
- ✅ All 13 story points completed
- ✅ 100% sprint completion achieved (470/470 points)
- ✅ Graph embeddings improve search precision by >10%
- ✅ Grafana dashboards operational and monitoring production
- ✅ All acceptance criteria met
- ✅ Tests passing (unit + integration)
- ✅ Documentation updated

### Success Metrics
- **Graph Embeddings**:
  - Model training time: <10 minutes for full graph
  - Embedding similarity correlation: >0.7 for related decisions
  - Hybrid search precision improvement: >10%

- **Observability**:
  - Dashboard load time: <3 seconds
  - Alert delivery time: <5 minutes (critical), <10 minutes (warning)
  - Metrics export overhead: <5% system resources

### Risk Assessment

**Medium Risk - Graph Embeddings**:
- **Risk**: Node2Vec training may be slow on large graphs (>10k nodes)
- **Mitigation**: Start with incremental training, optimize walk parameters
- **Fallback**: Use simpler graph features (citation count, PageRank) if Node2Vec too slow

**Low Risk - Observability**:
- **Risk**: Prometheus/Grafana infrastructure may not be available
- **Mitigation**: Use cloud alternatives (AWS CloudWatch, Grafana Cloud)
- **Fallback**: Build basic Laravel-based monitoring dashboard

---

## Timeline

### Day 1-2: Graph Embeddings Foundation
- Install dependencies (Node2Vec library, pgvector)
- Create `Node2VecTrainer` service
- Implement `exportGraphToEdgeList()` and `trainEmbeddings()`

### Day 3-4: Graph Embeddings Integration
- Train initial embeddings on current graph
- Integrate into `GraphResearchEnhancer`
- Create benchmark and validate improvement

### Day 5: Observability - Dashboards
- Create 4 Grafana dashboards
- Set up Prometheus metrics export
- Test dashboard rendering

### Day 6: Observability - Alerting
- Configure alerting rules (critical + warning)
- Test alert delivery
- Write runbook

### Day 7: Testing & Documentation
- Run full test suite
- Update documentation
- Final validation

---

## Post-Sprint Status

**Upon completion, the system will achieve**:
- ✅ 100% sprint completion (470/470 story points)
- ✅ All 10 agents graded 8.0+ / 10
- ✅ Complete graph intelligence (vector + graph + embeddings)
- ✅ Production-grade monitoring and alerting
- ✅ Full game-changer status with no remaining blockers

**Game-Changer Score**:
- Current: 47/50 (94%)
- After Sprint 7: **50/50 (100%)** ⭐⭐⭐

**Next Steps**:
1. Staging deployment (immediate)
2. User acceptance testing (1 week)
3. Production deployment (after UAT)
4. Active learning feedback collection
5. Research publication preparation

---

## Appendix: Technical References

### Node2Vec Parameters
```python
# Recommended parameters for legal citation graph
dimensions = 128  # Embedding size
walk_length = 80  # Length of random walk
num_walks = 10    # Number of walks per node
p = 1             # Return parameter (BFS vs DFS)
q = 1             # In-out parameter
window = 10       # Context size for skip-gram
min_count = 1     # Minimum word frequency
workers = 4       # Parallel workers
```

### Prometheus Metric Examples
```php
// Agent duration histogram
Histogram::observe('agent_duration_seconds', $duration, [
    'agent_type' => 'research_specialist'
]);

// Agent success counter
Counter::increment('agent_success_total', 1, [
    'agent_type' => 'decision_discovery'
]);

// Queue depth gauge
Gauge::set('queue_depth', $depth, [
    'queue_name' => 'agents'
]);
```

### Grafana Alert Example
```yaml
groups:
  - name: ai_legal_alerts
    interval: 1m
    rules:
      - alert: HighQueueDepth
        expr: queue_depth{queue_name="agents"} > 100
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "Agent queue depth critical"
          description: "Queue {{ $labels.queue_name }} has {{ $value }} jobs"
```

---

**End of Sprint Plan**
**Created**: 2025-11-11
**Sprint Duration**: 1 week
**Total Points**: 13
**Goal**: Achieve 100% completion and full production readiness
