# Discovery End-to-End Pipeline

## Overview

The `discovery:e2e` command executes the complete discovery pipeline from start to finish, validating the entire data flow:

```
Discovery → Ingestion → Graph Sync → Insight Fetch → Metrics
```

This command is designed for:
- **Staging validation**: Verify the full pipeline works before production deployment
- **Operational testing**: Regular health checks of the discovery system
- **Performance monitoring**: Track pipeline metrics over time
- **Debugging**: Identify bottlenecks or failure points in the pipeline

## Usage

### Basic Usage

Run the complete pipeline with default settings:

```bash
php artisan discovery:e2e
```

This will:
1. Generate 3 topics
2. Evaluate 20 decisions per topic
3. Ingest top 5 decisions per topic
4. Sync to Neo4j graph database
5. Fetch recent insights
6. Display metrics summary
7. Write detailed log to `storage/logs/discovery-e2e-TIMESTAMP.log`

### Custom Configuration

Adjust the pipeline parameters:

```bash
php artisan discovery:e2e \
  --topics=5 \
  --per-topic=50 \
  --ingest=10 \
  --threshold=80
```

**Options:**
- `--topics=N` - Number of topics to generate (default: 3)
- `--per-topic=N` - Decisions to evaluate per topic (default: 20)
- `--ingest=N` - Top decisions to ingest per topic (default: 5)
- `--threshold=N` - Relevance threshold 0-100 (default: 70)
- `--skip-discovery` - Skip discovery, use existing decisions
- `--skip-graph` - Skip Neo4j graph sync
- `--no-log` - Do not write to log file
- `-v` - Verbose output (show sample insights)

### Skip Discovery (Test Ingestion Only)

If you want to test just the ingestion and graph sync without discovery:

```bash
php artisan discovery:e2e --skip-discovery
```

This will process decisions added in the last 5 minutes.

### Skip Graph Sync (Test Discovery Only)

To test discovery without graph database:

```bash
php artisan discovery:e2e --skip-graph
```

### Verbose Output

Show detailed information including sample insights:

```bash
php artisan discovery:e2e -v
```

## Pipeline Stages

### Stage 1: Discovery

**What it does:**
- Generates relevant legal topics using LLM
- Searches for court decisions matching each topic
- Evaluates decisions for relevance
- Selects top N decisions for ingestion

**Metrics captured:**
- Topics generated
- Decisions evaluated
- Decisions ingested
- Duration
- Errors

**Sample output:**
```
🔍 Step 1: Discovery
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Running autonomous discovery...
✅ Discovery completed in 45.32s

┌──────────────────────┬───────┐
│ Metric               │ Count │
├──────────────────────┼───────┤
│ Topics generated     │ 3     │
│ Decisions evaluated  │ 60    │
│ Decisions ingested   │ 15    │
└──────────────────────┴───────┘
```

### Stage 2: Ingestion

**What it does:**
- Fetches decision metadata from odluke.sudovi.hr
- Downloads decision content (HTML or PDF)
- Generates embeddings for semantic search
- Optionally syncs to Neo4j graph database

**Metrics captured:**
- Decisions processed
- Succeeded
- Failed
- Graph sync enabled/disabled
- Duration

**Sample output:**
```
📥 Step 2: Ingestion (with graph sync)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Found 15 recent decisions to process
Processing 1/15: HR:VSRH:2024:TEST001
Processing 2/15: HR:VSRH:2024:TEST002
...
✅ Ingestion completed in 120.45s

┌────────────────────────┬──────────┐
│ Metric                 │ Count    │
├────────────────────────┼──────────┤
│ Decisions processed    │ 15       │
│ Succeeded              │ 14       │
│ Failed                 │ 1        │
│ Graph sync             │ Enabled  │
└────────────────────────┴──────────┘
```

### Stage 3: Graph Verification

**What it does:**
- Checks Neo4j availability
- Counts total nodes (Decisions, Laws)
- Counts total relationships
- Estimates recently added nodes

**Metrics captured:**
- Total Decision nodes
- Total Law nodes
- Total relationships
- Recent nodes added (estimate)
- Neo4j availability status
- Duration

**Sample output:**
```
🌐 Step 3: Graph Verification
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Graph verification completed in 2.34s

┌─────────────────────────┬────────┐
│ Metric                  │ Count  │
├─────────────────────────┼────────┤
│ Total Decision nodes    │ 1,234  │
│ Total Law nodes         │ 456    │
│ Total relationships     │ 3,567  │
│ Recent nodes (estimate) │ 15     │
└─────────────────────────┴────────┘
```

### Stage 4: Insight Fetch

**What it does:**
- Fetches recent insight events (last 10 minutes)
- Categorizes by severity (info, warning, critical)
- Counts unique agents and objectives
- Shows sample insights (with -v flag)

**Metrics captured:**
- Total recent insights
- By severity (info/warning/critical)
- Unique agents
- Unique objectives
- Duration

**Sample output:**
```
💡 Step 4: Insight Fetch
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Insight fetch completed in 0.45s

┌───────────────────────────┬───────┐
│ Metric                    │ Count │
├───────────────────────────┼───────┤
│ Recent insights (10min)   │ 12    │
│ Info severity             │ 6     │
│ Warning severity          │ 4     │
│ Critical severity         │ 2     │
│ Unique agents             │ 2     │
└───────────────────────────┴───────┘

Sample insights:
  [critical] autonomous_research_agent: Found precedent-setting case HR:VSRH:2024:...
  [warning] decision_discovery_agent: Identified high-relevance decision on labor law
  [info] autonomous_research_agent: Article 93 of Labor Law applies to termination
```

### Stage 5: Summary

**What it does:**
- Aggregates metrics from all stages
- Displays comprehensive summary table
- Lists any errors encountered
- Shows total pipeline duration
- Indicates log file location

**Sample output:**
```
📊 Pipeline Summary
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

┌───────────┬─────────────────────────┬────────┐
│ Stage     │ Metric                  │ Value  │
├───────────┼─────────────────────────┼────────┤
│ Discovery │ Topics                  │ 3      │
│ Discovery │ Decisions evaluated     │ 60     │
│ Discovery │ Decisions ingested      │ 15     │
│ Discovery │ Duration                │ 45.32s │
│ Ingestion │ Processed               │ 15     │
│ Ingestion │ Succeeded               │ 14     │
│ Ingestion │ Failed                  │ 1      │
│ Ingestion │ Duration                │ 120.4s │
│ Graph     │ Decision nodes          │ 1,234  │
│ Graph     │ Relationships           │ 3,567  │
│ Graph     │ Recent nodes            │ 15     │
│ Insights  │ Total recent            │ 12     │
│ Insights  │ Critical                │ 2      │
│ Insights  │ Unique agents           │ 2      │
│ Total     │ Duration                │ 168.5s │
│ Total     │ Errors                  │ 1      │
└───────────┴─────────────────────────┴────────┘

❌ Errors encountered:
  [ingestion] Failed to download PDF for decision HR:VSRH:2024:TEST007

✅ End-to-end pipeline completed
📄 Full metrics saved to: storage/logs/discovery-e2e-2025-11-01_143022.log
```

## Metrics Captured

The command captures comprehensive metrics at each stage:

### Discovery Metrics
```json
{
  "duration_seconds": 45.32,
  "topics_generated": 3,
  "decisions_evaluated": 60,
  "decisions_ingested": 15,
  "errors": []
}
```

### Ingestion Metrics
```json
{
  "duration_seconds": 120.45,
  "decisions_processed": 15,
  "succeeded": 14,
  "failed": 1,
  "graph_sync_enabled": true
}
```

### Graph Metrics
```json
{
  "duration_seconds": 2.34,
  "total_decision_nodes": 1234,
  "total_law_nodes": 456,
  "total_relationships": 3567,
  "recent_nodes_added_estimate": 15,
  "neo4j_available": true
}
```

### Insight Metrics
```json
{
  "duration_seconds": 0.45,
  "total_recent_insights": 12,
  "by_severity": {
    "info": 6,
    "warning": 4,
    "critical": 2
  },
  "unique_agents": 2,
  "unique_objectives": 3
}
```

### Complete Metrics JSON

Full metrics are saved to the log file in JSON format:

```json
{
  "start_time": "2025-11-01T14:30:22Z",
  "end_time": "2025-11-01T14:33:10Z",
  "total_duration_seconds": 168.51,
  "discovery": { ... },
  "ingestion": { ... },
  "graph": { ... },
  "insights": { ... },
  "errors": [
    {
      "stage": "ingestion",
      "error": "Failed to download PDF for decision HR:VSRH:2024:TEST007"
    }
  ]
}
```

## Log Files

Logs are written to `storage/logs/discovery-e2e-TIMESTAMP.log` with:
- Start/end timestamps
- Configuration details
- Complete metrics in JSON format
- Error traces

**Log file naming:**
```
discovery-e2e-2025-11-01_143022.log
                 ^         ^
                 date      time
```

**Log file format:**
```
Discovery E2E Pipeline Log
Started: 2025-11-01 14:30:22
================================================================================

Metrics:
================================================================================
{
  "start_time": "2025-11-01T14:30:22Z",
  "discovery": {
    "duration_seconds": 45.32,
    ...
  },
  ...
}

Completed: 2025-11-01 14:33:10
```

## Common Scenarios

### Daily Health Check

Run a quick health check every day:

```bash
php artisan discovery:e2e --topics=2 --per-topic=10 --ingest=3
```

This completes in ~1-2 minutes and validates all pipeline stages.

### Performance Benchmarking

Test with larger volumes for performance analysis:

```bash
php artisan discovery:e2e --topics=10 --per-topic=100 --ingest=20 -v
```

Monitor metrics to identify bottlenecks.

### Ingestion Testing

Test ingestion pipeline without discovery:

```bash
php artisan discovery:e2e --skip-discovery --ingest=10
```

Useful for testing after ingestion code changes.

### Graph-Free Testing

Test discovery without Neo4j dependency:

```bash
php artisan discovery:e2e --skip-graph
```

Useful in environments where Neo4j isn't available.

## Troubleshooting

### Neo4j Connection Failed

**Error:**
```
⚠️  Graph verification failed: Neo4j is not available
```

**Solution:**
- Check Neo4j is running: `systemctl status neo4j`
- Verify connection settings in `.env`: `NEO4J_HOST`, `NEO4J_PORT`, `NEO4J_PASSWORD`
- Run with `--skip-graph` to bypass graph verification

### Discovery Agent Timeout

**Error:**
```
❌ Discovery failed: Maximum execution time exceeded
```

**Solution:**
- Reduce volume: `--topics=2 --per-topic=10`
- Increase PHP timeout: `php -d max_execution_time=600 artisan discovery:e2e`
- Check API rate limits on odluke.sudovi.hr

### Ingestion Failed

**Error:**
```
⚠️  Failed: Failed to download PDF for decision HR:VSRH:2024:...
```

**Solution:**
- Individual failures are expected (decisions may be unavailable)
- Check `failed` count in summary - if > 50%, investigate
- Review full error details in log file
- Try with `--prefer=html` if PDF downloads are failing

### No Recent Decisions

**Error:**
```
Found 0 recent decisions to process
```

**Solution:**
- Run with discovery enabled (remove `--skip-discovery`)
- Or manually ingest some decisions first:
  ```bash
  php artisan decisions:ingest --query="labor law" --limit=5
  php artisan discovery:e2e --skip-discovery
  ```

## Monitoring

### Track Pipeline Performance

Run regularly and compare metrics:

```bash
# Run daily and save metrics
php artisan discovery:e2e --topics=3 --per-topic=20 --ingest=5

# Extract duration from log
grep "total_duration_seconds" storage/logs/discovery-e2e-*.log | tail -5
```

### Alert on Failures

Monitor error count in CI/CD:

```bash
# Exit with failure if errors > threshold
php artisan discovery:e2e || echo "Pipeline failed"
```

### Track Graph Growth

Monitor node/relationship counts over time:

```bash
# Extract graph stats from logs
grep -A5 '"graph":' storage/logs/discovery-e2e-*.log | \
  grep total_decision_nodes | \
  tail -10
```

## Integration

### CI/CD Pipeline

Add to staging deployment pipeline:

```yaml
# .github/workflows/staging.yml
- name: Run E2E Pipeline Test
  run: |
    php artisan discovery:e2e \
      --topics=2 \
      --per-topic=10 \
      --ingest=3 \
      --no-log
```

### Cron Job

Schedule regular health checks:

```bash
# crontab -e
0 3 * * * cd /path/to/app && php artisan discovery:e2e --topics=2 --per-topic=10 >> /var/log/discovery-e2e.log 2>&1
```

### Monitoring Dashboard

Parse log files for dashboard metrics:

```python
import json
import glob

# Load latest metrics
log_files = sorted(glob.glob('storage/logs/discovery-e2e-*.log'))
with open(log_files[-1]) as f:
    content = f.read()
    # Extract JSON between "Metrics:" headers
    metrics_json = content.split('Metrics:\n')[1].split('\n\nCompleted:')[0]
    metrics = json.loads(metrics_json)

    print(f"Duration: {metrics['total_duration_seconds']}s")
    print(f"Ingested: {metrics['ingestion']['succeeded']}")
    print(f"Insights: {metrics['insights']['total_recent_insights']}")
```

## Best Practices

1. **Run regularly** - Schedule weekly or daily runs to catch issues early
2. **Monitor trends** - Track metrics over time to identify degradation
3. **Start small** - Use conservative settings initially (`--topics=2`)
4. **Check logs** - Review log files for detailed error information
5. **Adjust thresholds** - Tune relevance threshold based on quality
6. **Verify graphs** - Always include graph verification in production tests
7. **Test incrementally** - Use skip flags to test individual stages

## Related Commands

- `decisions:discover` - Run only the discovery stage
- `decisions:ingest` - Run only the ingestion stage
- `graph:smoke-test` - Test graph database integration
- `graph:stats` - View graph statistics
- `insights:query` - Query insight events (if available)

## Support

For issues or questions:
- Review logs in `storage/logs/discovery-e2e-*.log`
- Check Neo4j logs: `/var/log/neo4j/`
- Verify environment settings in `.env`
- Run with `-v` for detailed output
