# Autonomous Court Decision Discovery

## Overview

The Decision Discovery Agent autonomously identifies, evaluates, and ingests interesting court decisions from odluke.sudovi.hr without human intervention.

**Key Features:**
- LLM-powered topic generation
- Intelligent decision scoring and filtering
- Autonomous ingestion of high-quality decisions
- Comprehensive monitoring and tracking
- Scheduled daily and weekly discovery runs

## How It Works

### 1. Topic Generation (LLM-Driven)

The agent uses GPT-4o-mini to generate 5 important legal research topics per run:

```php
php artisan decisions:discover
```

**Example topics:**
- Nezakonit otkaz (Unlawful termination)
- Ugovorna odgovornost (Contractual liability)
- Potrošačka zaštita (Consumer protection)
- Vlasničkopravni sporovi (Property disputes)
- Kaznena djela prijevare (Fraud offenses)

Topics are cached for 1 week to ensure consistency.

**LLM Prompt Strategy:**
```
You are a Croatian legal researcher. Identify the 5 most important areas
of Croatian law where new court decisions should be monitored.

CRITERIA:
- Areas with frequent litigation
- Emerging legal issues
- Topics with recent legislative changes
- Areas important for employment, contract, or property law
- Mix of civil and criminal law topics
```

**Temperature:** 0.8 (high diversity for varied topics)

**Fallback:** If LLM fails, uses predefined topics:
- Radno pravo
- Ugovorno pravo
- Potrošačka zaštita
- Vlasničkopravni sporovi
- Obvezno pravo

### 2. Decision Search

For each topic, the agent:
1. Translates the topic to a search query
2. Searches odluke.sudovi.hr using `OdlukeClient`
3. Retrieves up to 50 decision IDs per topic
4. Fetches metadata for each decision (title, court, date, type, description)

**Metadata Fields:**
- `title`: Decision title
- `court`: Court name (Vrhovni sud, Županijski sud, Općinski sud)
- `date`: Decision date
- `type`: Decision type (Presuda, Rješenje, etc.)
- `description`: Summary text

### 3. Decision Scoring (LLM-Driven)

Decisions are scored in batches of 10 using GPT-4o-mini:

**Scoring Scale:** 0-100
- **90-100:** Highly relevant, directly addresses topic, from authoritative court
- **70-89:** Relevant, related to topic, useful precedent
- **50-69:** Somewhat relevant, tangentially related
- **0-49:** Not relevant or low quality

**Scoring Criteria:**
- Relevance to topic
- Court authority (Vrhovni sud > Županijski > Općinski)
- Decision type (Presuda > Rješenje > other)
- Recency (newer decisions preferred)

**Temperature:** 0.3 (low for consistent scoring)

**Output Format:**
```json
{
  "scores": [
    {
      "id": "decision_id",
      "score": 85,
      "reasoning": "Highly relevant Supreme Court ruling on unlawful termination"
    }
  ]
}
```

**Fallback:** If scoring fails, assigns neutral score of 50 to all decisions.

### 4. Filtering & Ingestion

**Threshold:** Only decisions with score ≥ 70 are considered for ingestion (configurable)

**Process:**
1. Filter decisions by relevance threshold
2. Sort by score (highest first)
3. Take top N decisions per topic (default: 10)
4. Ingest via `OdlukeIngestService` with:
   - Graph synchronization enabled
   - Chunk size: 1500 characters
   - Overlap: 200 characters

**Result:** High-quality decisions automatically added to knowledge base

## Configuration

### Agent Configuration

```php
use App\Agents\DecisionDiscoveryAgent;

$agent = app(DecisionDiscoveryAgent::class);

// Configure discovery parameters
$agent->setTopicsPerRun(10)              // Default: 5
      ->setDecisionsPerTopic(100)        // Default: 50
      ->setIngestPerTopic(20)            // Default: 10
      ->setRelevanceThreshold(60.0);     // Default: 70.0
```

### Configuration Options

| Option | Default | Description |
|--------|---------|-------------|
| `topicsPerRun` | 5 | Number of topics to generate per run |
| `decisionsPerTopic` | 50 | Decisions to evaluate per topic |
| `ingestPerTopic` | 10 | Top decisions to ingest per topic |
| `relevanceThreshold` | 70.0 | Minimum score for ingestion (0-100) |

## CLI Command

### Basic Usage

```bash
php artisan decisions:discover
```

### Command Options

```bash
php artisan decisions:discover \
  --topics=10 \          # Generate 10 topics
  --per-topic=100 \      # Evaluate 100 decisions per topic
  --ingest=20 \          # Ingest top 20 per topic
  --threshold=60 \       # Lower threshold to 60%
  --dry-run              # Evaluate but don't ingest
```

### Dry Run Mode

Test the discovery process without actually ingesting:

```bash
php artisan decisions:discover --dry-run
```

**Output:**
- Configuration table
- Progress messages for each topic
- Statistics (topics generated, decisions evaluated, would-be ingested)
- No changes to database

## Scheduled Discovery

### Daily Discovery

Runs every day at 2:00 AM with default configuration:

```php
// app/Console/Kernel.php
$schedule->command('decisions:discover')
         ->daily()
         ->at('02:00')
         ->withoutOverlapping()
         ->onOneServer()
         ->emailOutputOnFailure(config('mail.admin_email'));
```

**Settings:**
- 5 topics
- 50 decisions per topic
- Ingest top 10
- Threshold: 70%
- Email notification on failure

### Weekly Comprehensive Discovery

Runs every Sunday at 3:00 AM with expanded configuration:

```php
$schedule->command('decisions:discover --topics=10 --threshold=60')
         ->weekly()
         ->sundays()
         ->at('03:00')
         ->withoutOverlapping()
         ->onOneServer();
```

**Settings:**
- 10 topics (more diverse)
- 50 decisions per topic
- Ingest top 10
- Threshold: 60% (lower for broader ingestion)

### Safety Features

- `withoutOverlapping()`: Prevents concurrent runs
- `onOneServer()`: Ensures only one server runs the task (multi-server deployments)
- Email alerts on failure (daily run only)

## Monitoring Dashboard

### Accessing the Dashboard

Add route to `routes/web.php`:

```php
use App\Http\Livewire\DecisionDiscoveryDashboard;

Route::get('/admin/discovery', DecisionDiscoveryDashboard::class)
    ->middleware(['auth', 'admin']);
```

### Dashboard Features

**Stats Cards:**
- Total Runs
- Total Decisions Ingested
- Total Decisions Evaluated
- Success Rate (%)

**Latest Run Panel:**
- Start time and status (Running/Completed/Failed)
- Topics generated
- Decisions ingested vs evaluated
- Research topics list

**Run History Table:**
- Start time
- Status (with color coding)
- Topics generated
- Decisions evaluated
- Decisions ingested
- Duration (HH:MM:SS)
- Pagination (20 per page)

**Status Indicators:**
- ✓ Completed (green)
- ⟳ Running (blue)
- ✗ Failed (red)

### Database Tracking

All discovery runs are tracked in `decision_discovery_runs` table:

```sql
CREATE TABLE decision_discovery_runs (
  id BIGINT PRIMARY KEY,
  started_at TIMESTAMP NOT NULL,
  completed_at TIMESTAMP NULL,
  topics_generated INT DEFAULT 0,
  decisions_evaluated INT DEFAULT 0,
  decisions_ingested INT DEFAULT 0,
  topics JSON NULL,
  errors JSON NULL,
  status VARCHAR(255) DEFAULT 'running',
  error_message TEXT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

**Run Lifecycle:**
1. **Running:** Run starts, record created with status 'running'
2. **Completed:** Run succeeds, status updated to 'completed' with stats
3. **Failed:** Exception occurs, status updated to 'failed' with error message

## Architecture

### Class Structure

```
DecisionDiscoveryAgent
├── discover()                      # Main entry point
├── generateResearchTopics()        # LLM topic generation
├── discoverForTopic()             # Per-topic discovery
├── scoreDecisions()               # Batch scoring coordinator
├── scoreBatch()                   # LLM scoring for 10 decisions
└── translateTopicToQuery()        # Topic to search query

Dependencies:
├── OdlukeClient                   # Search and fetch decisions
├── OdlukeIngestService           # Ingest decisions
└── OpenAIService                  # LLM integration
```

### Discovery Flow

```
1. START
   ├─> Create DecisionDiscoveryRun (status: running)
   |
2. GENERATE TOPICS (LLM)
   ├─> GPT-4o-mini generates 5 topics
   ├─> Cache for 1 week
   └─> Fallback to predefined if fails
   |
3. FOR EACH TOPIC:
   ├─> Search odluke.sudovi.hr
   ├─> Fetch metadata for all IDs
   ├─> Score in batches of 10 (LLM)
   ├─> Filter by threshold (≥70)
   ├─> Sort by score
   ├─> Take top 10
   └─> Ingest via OdlukeIngestService
   |
4. END
   └─> Update DecisionDiscoveryRun (status: completed/failed)
```

### Batch Processing

Decisions are scored in batches of 10 to optimize LLM calls:

```
50 decisions per topic
├─> Batch 1: decisions 1-10  → LLM call 1
├─> Batch 2: decisions 11-20 → LLM call 2
├─> Batch 3: decisions 21-30 → LLM call 3
├─> Batch 4: decisions 31-40 → LLM call 4
└─> Batch 5: decisions 41-50 → LLM call 5

Total: 5 LLM calls per topic
```

**Benefit:** Reduces LLM costs and latency compared to individual scoring.

## Cost Analysis

### LLM Usage

**Model:** GPT-4o-mini ($0.15 per 1M tokens)

**Per Run Estimates:**

**Topic Generation:**
- 1 LLM call per run
- ~500 tokens per call
- Cost: ~$0.0001 per run

**Decision Scoring:**
- 5 batches per topic × 5 topics = 25 LLM calls
- ~1000 tokens per call
- Cost: ~$0.0038 per run

**Total per run:** ~$0.004 (less than half a cent)

**Daily cost:** ~$0.004
**Monthly cost:** ~$0.12
**Yearly cost:** ~$1.44

**Conclusion:** Extremely cost-effective for autonomous operation.

## Error Handling

### Graceful Degradation

1. **Topic Generation Fails:**
   - Falls back to predefined topics
   - Logs warning
   - Continues with fallback topics

2. **Decision Scoring Fails:**
   - Assigns neutral score (50) to all decisions in failed batch
   - Logs error
   - Continues with neutral scores

3. **Metadata Fetch Fails:**
   - Logs warning for specific decision
   - Skips that decision
   - Continues with remaining decisions

4. **Ingestion Fails:**
   - Error logged for topic
   - Added to run's errors array
   - Continues with next topic

5. **Complete Failure:**
   - Run marked as 'failed'
   - Error message saved
   - Exception propagated
   - Email alert sent (if configured)

### Logging

All operations are logged with structured data:

```php
Log::info('Generated research topics', [
    'count' => 5,
    'topics' => [...],
]);

Log::info('Ingesting top decisions', [
    'topic' => 'nezakonit otkaz',
    'count' => 10,
    'threshold' => 70,
    'top_scores' => [85, 80, 78],
]);
```

## Testing

### Running Tests

```bash
php artisan test --filter=DecisionDiscoveryAgentTest
```

### Test Coverage

**Unit Tests:** `tests/Unit/Agents/DecisionDiscoveryAgentTest.php`

1. **`it_generates_research_topics_via_llm()`**
   - Mocks OpenAI topic generation
   - Verifies 3 topics returned
   - Tests reflection access to protected method

2. **`it_searches_for_decisions_on_topic()`**
   - Mocks full workflow
   - Tests threshold filtering (70%)
   - Verifies only 2 of 3 decisions ingested

3. **`it_scores_decisions_via_llm()`**
   - Mocks LLM scoring
   - Verifies score format (id, score, reasoning)

4. **`it_filters_by_relevance_threshold()`**
   - Tests configuration setters/getters
   - Verifies threshold persistence

**All tests use Mockery to avoid real API calls.**

## Best Practices

### Topic Generation

- Review generated topics periodically
- Adjust topic count based on caseload
- Consider domain-specific topics for specialized practices

### Threshold Tuning

- **High threshold (80-100):** Only highest quality, Supreme Court cases
- **Medium threshold (60-79):** Balanced quality and quantity
- **Low threshold (40-59):** Broader ingestion, more coverage

**Recommendation:** Start with 70, adjust based on quality feedback.

### Monitoring

- Check dashboard weekly
- Review success rate (should be >90%)
- Investigate failed runs promptly
- Monitor ingestion rate (decisions per run)

### Performance

- Keep `decisionsPerTopic` at 50-100 (optimal batch size)
- Limit `topicsPerRun` to 5-10 (avoid overwhelming system)
- Use dry-run mode to test new configurations

## Troubleshooting

### No Decisions Ingested

**Possible causes:**
- Threshold too high
- Topics too narrow/specific
- Poor network connectivity to odluke.sudovi.hr

**Solutions:**
- Lower threshold (try 60%)
- Run with --dry-run to see scores
- Check odluke.sudovi.hr availability

### LLM Calls Failing

**Symptoms:**
- Using fallback topics
- Neutral scores (50) for all decisions

**Solutions:**
- Check OpenAI API key configuration
- Verify OpenAI account status
- Check API rate limits

### Runs Taking Too Long

**Causes:**
- Too many topics or decisions per topic
- Slow network to odluke.sudovi.hr
- Large number of metadata fetches

**Solutions:**
- Reduce `decisionsPerTopic`
- Reduce `topicsPerRun`
- Check network latency

### Duplicate Ingestions

**Note:** The system does NOT automatically deduplicate. If the same decision is scored highly in multiple runs, it may be ingested multiple times.

**Mitigation:**
- Topic caching (1 week) reduces duplicates
- Review ingestion logs
- Consider adding deduplication logic to `OdlukeIngestService`

## Future Enhancements

### Potential Improvements

1. **Deduplication**
   - Check if decision already ingested before scoring
   - Skip already-ingested decisions

2. **Topic Diversity**
   - Track ingested topics over time
   - Bias LLM toward less-covered topics

3. **Quality Feedback Loop**
   - Allow users to rate ingested decisions
   - Use ratings to fine-tune scoring prompts

4. **Multi-Source Discovery**
   - Extend beyond odluke.sudovi.hr
   - Include EU court decisions
   - Monitor legislative changes

5. **Advanced Scoring**
   - Use embeddings for semantic similarity
   - Train custom model for Croatian legal relevance
   - Incorporate citation analysis

6. **Real-Time Discovery**
   - Monitor RSS feeds or webhooks
   - Immediate ingestion of high-priority decisions
   - Push notifications for critical cases

## Related Documentation

- [Autonomous Research Agent](AUTONOMOUS_AGENT_README.md) - Main research agent docs
- [OdlukeClient Documentation](../app/Services/Odluke/README.md) - Court decision API
- [OpenAI Integration](../app/Services/OpenAIService.php) - LLM service

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review dashboard: `/admin/discovery`
- Run diagnostics: `php artisan decisions:discover --dry-run`
- Open GitHub issue with full error logs and run statistics
