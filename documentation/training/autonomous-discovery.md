# Autonomous Decision Discovery - Legal Analyst Training Guide

**Version:** 1.0
**Last Updated:** 2025-01-29
**Target Audience:** Legal Analysts, Researchers, Knowledge Managers
**Duration:** 90 minutes

## Table of Contents

1. [Introduction](#introduction)
2. [System Overview](#system-overview)
3. [Getting Started](#getting-started)
4. [Workflow Walkthrough](#workflow-walkthrough)
5. [Dashboard Guide](#dashboard-guide)
6. [API Integration](#api-integration)
7. [Inspection & Quality Control](#inspection--quality-control)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)
10. [Appendix](#appendix)

---

## Introduction

### What is Autonomous Decision Discovery?

The Autonomous Decision Discovery system is an AI-powered workflow that automatically:
- **Identifies** legally relevant topics for research
- **Discovers** court decisions from Croatian legal databases
- **Evaluates** decision quality and relevance using AI
- **Ingests** high-quality decisions into the knowledge base
- **Synchronizes** decisions with the graph database for advanced analytics

### Why Autonomous Discovery?

**Traditional Workflow:**
```
Legal Analyst → Manual Search → Manual Review → Manual Selection → Manual Ingestion
Time: 2-4 hours per session
```

**Autonomous Workflow:**
```
AI Agent → Automated Search → AI Scoring → Auto-Ingestion → Graph Sync
Time: 15-30 minutes per session (runs while you sleep!)
```

**Benefits:**
- 🤖 **Hands-free operation** - Runs daily at 2 AM
- 🎯 **Intelligent targeting** - AI generates relevant topics
- ⚖️ **Quality assurance** - LLM scoring filters low-quality decisions
- 📊 **Complete audit trail** - Every decision tracked and scored
- 🔄 **Graph integration** - Automatic citation analysis and clustering

### Learning Objectives

By the end of this training, you will be able to:
1. ✅ Understand how the autonomous discovery workflow operates
2. ✅ Monitor discovery runs using the dashboard
3. ✅ Trigger manual discovery runs when needed
4. ✅ Inspect discovered decisions for quality control
5. ✅ Use the API to integrate with custom workflows
6. ✅ Troubleshoot common issues

---

## System Overview

### Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    AUTONOMOUS DISCOVERY PIPELINE                 │
└─────────────────────────────────────────────────────────────────┘

1. TOPIC GENERATION (AI-Driven)
   ┌──────────────┐
   │  GPT-4o-mini │ → Generates 5-10 legal research topics
   └──────────────┘    (Employment law, Consumer protection, etc.)
         │
         ↓
2. DECISION SEARCH
   ┌──────────────┐
   │ Odluke.hr API│ → Searches 50 decisions per topic
   └──────────────┘    (Fetches metadata: title, court, date)
         │
         ↓
3. INTELLIGENT SCORING (AI-Driven)
   ┌──────────────┐
   │  GPT-4o-mini │ → Scores each decision 0-100
   └──────────────┘    (Relevance, authority, recency)
         │
         ↓
4. FILTERING & RANKING
   ┌──────────────┐
   │Filter ≥ 70   │ → Keeps only high-quality decisions
   └──────────────┘    (Top 10 per topic)
         │
         ↓
5. AUTONOMOUS INGESTION
   ┌──────────────┐
   │ Ingest Queue │ → Processes full text, creates embeddings
   └──────────────┘    (Graph sync, citation extraction)
         │
         ↓
6. KNOWLEDGE BASE UPDATE
   ┌──────────────┐
   │PostgreSQL DB │ → Stores decisions with full-text search
   │   Neo4j      │ → Creates citation network
   └──────────────┘
```

### Components

| Component | Technology | Purpose |
|-----------|-----------|---------|
| **Topic Generator** | GPT-4o-mini (temp=0.8) | Generates diverse legal research topics |
| **Decision Scorer** | GPT-4o-mini (temp=0.3) | Evaluates decision relevance 0-100 |
| **Odluke Client** | HTTP/REST | Fetches decisions from odluke.sudovi.hr |
| **Ingest Service** | Laravel Queue | Processes full decision text |
| **Graph Sync** | Neo4j Bolt | Creates citation relationships |
| **Dashboard** | Livewire | Real-time monitoring interface |

### Key Metrics

The system tracks these performance indicators:

- **Total Runs**: Number of discovery sessions executed
- **Decisions Evaluated**: Total decisions scored by AI
- **Decisions Ingested**: High-quality decisions added to knowledge base
- **Success Rate**: % of runs completed without errors
- **Average Score**: Mean relevance score across all decisions
- **Ingestion Rate**: % of evaluated decisions that pass threshold

---

## Getting Started

### Access Requirements

#### 1. Dashboard Access

**URL:** `https://your-domain.com/dashboard`

**Login Credentials:**
- Email: [Your analyst email]
- Password: [Provided by admin]

![Dashboard Login](../images/autonomous-discovery/01-login.png)
*Screenshot 1: Login screen*

#### 2. Permissions Required

Ensure you have these permissions:
- ✅ View Decision Discovery Dashboard
- ✅ Trigger Manual Discovery Runs
- ✅ View Decision Details
- ✅ Access Graph Viewer

Contact your system administrator if you're missing permissions.

#### 3. API Access (Optional)

For API integration, you'll need:
- **API Token**: Request from admin
- **Base URL**: `https://your-domain.com/api`
- **Postman Collection**: Provided in this training

### Dashboard Navigation

#### Main Menu

```
┌────────────────────────────────────────────┐
│  AI Legal War Machine                      │
├────────────────────────────────────────────┤
│  📊 Dashboard            ← Home             │
│  🔍 Unified Search       ← Search KB        │
│  🤖 Decision Discovery   ← THIS MODULE      │
│  📈 Graph Viewer         ← Citation network │
│  📂 Ingested Laws        ← Legal codes      │
│  🔔 Monitoring           ← System health    │
└────────────────────────────────────────────┘
```

#### Decision Discovery Page

Navigate to: **Dashboard → Decision Discovery**

![Discovery Dashboard](../images/autonomous-discovery/02-dashboard-overview.png)
*Screenshot 2: Decision Discovery dashboard overview*

---

## Workflow Walkthrough

### Scenario 1: Automated Daily Discovery

**When:** Every day at 2:00 AM (scheduled)
**Who:** System (no human intervention)
**Duration:** 15-30 minutes

#### Step-by-Step Process

**Step 1: Topic Generation (2:00 AM)**

The AI generates 5 legal research topics based on:
- Current legal trends in Croatia
- Areas with frequent litigation
- Recent legislative changes
- Mix of civil and criminal law

Example topics:
```
1. Nezakonit otkaz (Unlawful termination)
2. Ugovorna odgovornost (Contractual liability)
3. Potrošačka zaštita (Consumer protection)
4. Vlasničkopravni sporovi (Property disputes)
5. Kaznena djela prijevare (Fraud offenses)
```

![Topic Generation](../images/autonomous-discovery/03-topic-generation.png)
*Screenshot 3: AI-generated topics displayed in dashboard*

**Step 2: Decision Search (2:02 AM)**

For each topic, the system:
- Searches odluke.sudovi.hr
- Retrieves up to 50 decision IDs
- Fetches metadata for each decision

Example search results:
```
Topic: "Nezakonit otkaz"
Found: 47 decisions
Courts: Vrhovni sud (12), Županijski sud (28), Općinski sud (7)
Date range: 2023-01-15 to 2025-01-28
```

![Search Results](../images/autonomous-discovery/04-search-results.png)
*Screenshot 4: Search results per topic*

**Step 3: AI Scoring (2:05 AM)**

Each decision is scored 0-100 based on:

| Criteria | Weight | Example |
|----------|--------|---------|
| Relevance to topic | 40% | Directly addresses unlawful termination |
| Court authority | 30% | Vrhovni sud = highest weight |
| Decision type | 20% | Presuda > Rješenje |
| Recency | 10% | 2024-2025 preferred |

Example scores:
```
Decision ID: VRH-2024-123
Title: "Utvrđenje nezakonitosti otkaza ugovora o radu"
Court: Vrhovni sud
Score: 92/100
Reasoning: "Highly relevant Supreme Court ruling directly on point"

Decision ID: ZS-2023-456
Title: "Naknada štete zbog otkaza"
Court: Županijski sud Zagreb
Score: 78/100
Reasoning: "Relevant regional court case on damages"
```

![AI Scoring](../images/autonomous-discovery/05-scoring-results.png)
*Screenshot 5: AI scoring results with reasoning*

**Step 4: Filtering (2:10 AM)**

Only decisions scoring ≥ 70 proceed to ingestion:

```
Total evaluated: 235 decisions
Threshold: ≥ 70
Passed filter: 48 decisions (20.4%)
Selected for ingestion: Top 10 per topic = 50 decisions
```

![Filtering](../images/autonomous-discovery/06-filtering.png)
*Screenshot 6: Filtering and ranking visualization*

**Step 5: Autonomous Ingestion (2:12 AM)**

For each selected decision:
1. Download full text from odluke.sudovi.hr
2. Extract metadata (parties, dates, case numbers)
3. Chunk text (1500 chars, 200 overlap)
4. Generate embeddings (text-embedding-3-small)
5. Store in PostgreSQL with vector search
6. Sync to Neo4j graph database
7. Extract citations and create relationships

![Ingestion Progress](../images/autonomous-discovery/07-ingestion-progress.png)
*Screenshot 7: Live ingestion progress*

**Step 6: Completion (2:25 AM)**

Run summary:
```
✅ Status: Completed
⏱️ Duration: 23 minutes
📊 Topics: 5
🔍 Evaluated: 235 decisions
✨ Ingested: 48 decisions
📈 Success rate: 100%
```

![Completion Summary](../images/autonomous-discovery/08-completion-summary.png)
*Screenshot 8: Run completion summary*

---

### Scenario 2: Manual Discovery Run

**When:** On-demand (e.g., after major court ruling)
**Who:** Legal Analyst
**Duration:** 15-30 minutes

#### Triggering a Manual Run

**Option A: Via Dashboard**

1. Navigate to Decision Discovery dashboard
2. Click "Start New Discovery Run" button
3. Configure options (optional):
   - Topics: 5-10 (default: 5)
   - Decisions per topic: 50-100 (default: 50)
   - Threshold: 60-80 (default: 70)
4. Click "Run Discovery"
5. Monitor progress in real-time

![Manual Run Interface](../images/autonomous-discovery/09-manual-run-config.png)
*Screenshot 9: Manual run configuration*

**Option B: Via Command Line**

```bash
# Basic run (uses defaults)
php artisan decisions:discover

# Custom configuration
php artisan decisions:discover \
  --topics=10 \
  --threshold=60 \
  --decisions-per-topic=100

# Queue-based (async, doesn't block)
php artisan decisions:discovery --queue
```

**Option C: Via API**

```bash
curl -X POST https://your-domain.com/api/discovery/start \
  -H "X-MCP-Token: your-api-token" \
  -H "Content-Type: application/json" \
  -d '{
    "topics": 8,
    "threshold": 70,
    "decisionsPerTopic": 75
  }'
```

![API Response](../images/autonomous-discovery/10-api-trigger.png)
*Screenshot 10: API response for manual trigger*

#### Monitoring the Run

**Real-time Updates**

The dashboard shows live progress:

```
Current Status: Running
Stage: Scoring decisions (3/5 topics complete)
Elapsed: 8 minutes
ETA: 7 minutes remaining

Progress:
├─ Topic 1: "Nezakonit otkaz" ✅ (52 evaluated, 12 selected)
├─ Topic 2: "Ugovorna odgovornost" ✅ (48 evaluated, 10 selected)
├─ Topic 3: "Potrošačka zaštita" ⏳ (Scoring in progress...)
├─ Topic 4: "Vlasničkopravni sporovi" ⏸️ (Pending)
└─ Topic 5: "Kaznena djela" ⏸️ (Pending)
```

![Live Progress](../images/autonomous-discovery/11-live-progress.png)
*Screenshot 11: Real-time progress monitoring*

---

## Dashboard Guide

### Overview Tab

**Key Metrics Panel**

```
┌────────────────┬────────────────┬────────────────┬────────────────┐
│   Total Runs   │   Evaluated    │   Ingested     │  Success Rate  │
│      127       │     29,853     │     5,624      │     94.5%      │
└────────────────┴────────────────┴────────────────┴────────────────┘
```

**Latest Run Status**

```
Last Run: 2025-01-29 02:00:15
Status: ✅ Completed
Duration: 23 min 47 sec
Topics: 5
Evaluated: 235 decisions
Ingested: 48 decisions
Quality Score: 82.3/100 (average)
```

![Overview Tab](../images/autonomous-discovery/12-overview-tab.png)
*Screenshot 12: Dashboard overview tab*

### Run History

**Runs Table**

| Run ID | Date | Status | Topics | Evaluated | Ingested | Duration | Actions |
|--------|------|--------|--------|-----------|----------|----------|---------|
| 127 | 2025-01-29 02:00 | ✅ Completed | 5 | 235 | 48 | 23m 47s | [View Details] |
| 126 | 2025-01-28 02:00 | ✅ Completed | 5 | 241 | 52 | 25m 12s | [View Details] |
| 125 | 2025-01-27 02:00 | ⚠️ Partial | 5 | 198 | 38 | 28m 03s | [View Details] |
| 124 | 2025-01-26 02:00 | ✅ Completed | 5 | 228 | 45 | 22m 35s | [View Details] |

**Filters:**
- Status: All / Completed / Failed / Partial
- Date Range: Last 7 days / Last 30 days / Custom
- Topics: All / Specific topic

![Run History](../images/autonomous-discovery/13-run-history.png)
*Screenshot 13: Run history with filters*

### Run Details

Click "View Details" on any run to see:

**1. Topics Generated**

```
📚 Topics for Run #127

1. Nezakonit otkaz (Unlawful termination)
   Evaluated: 52 | Ingested: 12 | Avg Score: 81.5

2. Ugovorna odgovornost (Contractual liability)
   Evaluated: 48 | Ingested: 10 | Avg Score: 78.2

3. Potrošačka zaštita (Consumer protection)
   Evaluated: 45 | Ingested: 9 | Avg Score: 75.8

4. Vlasničkopravni sporovi (Property disputes)
   Evaluated: 43 | Ingested: 8 | Avg Score: 73.1

5. Kaznena djela prijevare (Fraud offenses)
   Evaluated: 47 | Ingested: 9 | Avg Score: 76.4
```

**2. Decision Breakdown**

```
Score Distribution:
90-100: ████████ 18 decisions (7.7%)
80-89:  ████████████ 28 decisions (11.9%)
70-79:  ████████████████ 38 decisions (16.2%)  ← Ingested
60-69:  ██████████████ 32 decisions (13.6%)
50-59:  ████████ 19 decisions (8.1%)
0-49:   ████████████████████████████ 100 decisions (42.6%)
```

**3. Top Decisions**

| Rank | Decision | Court | Score | Status |
|------|----------|-------|-------|--------|
| 1 | VRH-2024-543 | Vrhovni sud | 95 | ✅ Ingested |
| 2 | VRH-2024-312 | Vrhovni sud | 93 | ✅ Ingested |
| 3 | ZS-2024-789 | Županijski sud Zagreb | 88 | ✅ Ingested |
| 4 | ZS-2024-654 | Županijski sud Split | 86 | ✅ Ingested |
| 5 | VRH-2023-987 | Vrhovni sud | 84 | ✅ Ingested |

![Run Details](../images/autonomous-discovery/14-run-details.png)
*Screenshot 14: Detailed run information*

### Decision Inspector

Click on any decision to inspect:

```
┌─────────────────────────────────────────────────────────────┐
│ Decision ID: VRH-2024-543                                   │
├─────────────────────────────────────────────────────────────┤
│ Title: Utvrđenje nezakonitosti otkaza ugovora o radu       │
│ Court: Vrhovni sud Republike Hrvatske                       │
│ Date: 2024-11-15                                            │
│ Type: Presuda (Judgment)                                    │
│ Case Number: Rev-1234/2024                                  │
├─────────────────────────────────────────────────────────────┤
│ AI EVALUATION                                               │
│ Score: 95/100 🌟                                            │
│ Topic: Nezakonit otkaz                                      │
│                                                             │
│ Reasoning:                                                  │
│ "Highly authoritative Supreme Court ruling that directly   │
│  addresses the criteria for determining unlawful            │
│  termination. Establishes clear precedent on employer's     │
│  burden of proof. Recent and highly relevant."              │
├─────────────────────────────────────────────────────────────┤
│ INGESTION STATUS                                            │
│ ✅ Ingested on: 2025-01-29 02:18:42                        │
│ ✅ Full text: 12,543 characters                            │
│ ✅ Chunks: 9 segments                                       │
│ ✅ Embeddings: Generated                                    │
│ ✅ Graph sync: Complete                                     │
│ ✅ Citations: 15 found                                      │
├─────────────────────────────────────────────────────────────┤
│ ACTIONS                                                     │
│ [View Full Text] [View in Graph] [View Citations]          │
└─────────────────────────────────────────────────────────────┘
```

![Decision Inspector](../images/autonomous-discovery/15-decision-inspector.png)
*Screenshot 15: Decision detail inspector*

---

## API Integration

### Authentication

All API requests require an authentication token:

```bash
# Set your API token
export MCP_TOKEN="your-api-token-here"

# Test authentication
curl -H "X-MCP-Token: $MCP_TOKEN" \
     https://your-domain.com/api/mcp/decision.search
```

### Available Endpoints

#### 1. Search Decisions

**Endpoint:** `POST /api/mcp/decision.search`

**Request:**
```json
{
  "query": "nezakonit otkaz",
  "limit": 20,
  "offset": 0,
  "filters": {
    "court": "Vrhovni sud",
    "dateFrom": "2024-01-01",
    "dateTo": "2025-01-29"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 147,
    "decisions": [
      {
        "id": "VRH-2024-543",
        "title": "Utvrđenje nezakonitosti otkaza ugovora o radu",
        "court": "Vrhovni sud",
        "date": "2024-11-15",
        "type": "Presuda",
        "summary": "Ruling on criteria for unlawful termination...",
        "score": 95,
        "url": "https://odluke.sudovi.hr/..."
      }
    ]
  }
}
```

#### 2. Get Decision Details

**Endpoint:** `POST /api/mcp/decision.get`

**Request:**
```json
{
  "id": "VRH-2024-543"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "VRH-2024-543",
    "title": "Utvrđenje nezakonitosti otkaza ugovora o radu",
    "court": "Vrhovni sud",
    "fullText": "...",
    "citations": [
      "VRH-2023-123",
      "VRH-2022-456"
    ],
    "chunks": [...]
  }
}
```

#### 3. Trigger Discovery Run (Coming Soon)

**Endpoint:** `POST /api/discovery/start`

**Request:**
```json
{
  "topics": 8,
  "threshold": 70,
  "decisionsPerTopic": 75,
  "async": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "runId": 128,
    "status": "running",
    "estimatedDuration": "20-30 minutes",
    "statusUrl": "/api/discovery/runs/128"
  }
}
```

### Postman Collection

A complete Postman collection is provided: `docs/postman/autonomous-discovery.json`

**Import Steps:**
1. Open Postman
2. Click "Import"
3. Select `autonomous-discovery.json`
4. Update `{{base_url}}` variable
5. Update `{{mcp_token}}` variable
6. Run collection tests

![Postman Collection](../images/autonomous-discovery/16-postman-collection.png)
*Screenshot 16: Postman collection overview*

---

## Inspection & Quality Control

### Quality Assurance Workflow

**Daily QA Checklist (10 minutes):**

1. ✅ **Check Latest Run Status**
   - Navigate to Decision Discovery dashboard
   - Verify latest run completed successfully
   - Check success rate is ≥ 90%

2. ✅ **Review High-Score Decisions**
   - View decisions with score ≥ 90
   - Spot-check 3-5 decisions
   - Verify relevance and quality

3. ✅ **Inspect Failed/Partial Runs**
   - If any runs failed, check error logs
   - Note recurring issues
   - Report to system admin if needed

4. ✅ **Monitor Graph Sync**
   - Open Graph Viewer
   - Check recent citations extracted
   - Verify cluster formation

![QA Checklist](../images/autonomous-discovery/17-qa-checklist.png)
*Screenshot 17: QA checklist interface*

### Quality Metrics

**What to Monitor:**

| Metric | Healthy Range | Action if Outside Range |
|--------|---------------|------------------------|
| Success Rate | ≥ 90% | Check system logs |
| Average Score | 75-85 | Review threshold settings |
| Ingestion Rate | 15-25% | Adjust threshold if needed |
| Citations Extracted | ≥ 80% | Check graph sync |
| Processing Time | 15-35 min | Check system resources |

### Manual Override

**When to Override:**

Sometimes you may want to:
- Ingest a decision below threshold (high importance)
- Skip a decision above threshold (not actually relevant)
- Adjust scoring for specific topics

**How to Override:**

1. Navigate to Run Details
2. Find the decision
3. Click "Override Decision"
4. Select action:
   - Force Ingest
   - Force Skip
   - Adjust Score
5. Provide justification
6. Submit

![Manual Override](../images/autonomous-discovery/18-manual-override.png)
*Screenshot 18: Manual override interface*

---

## Best Practices

### For Legal Analysts

**✅ DO:**
- Monitor daily runs each morning (10 min)
- Review high-score decisions weekly
- Report recurring quality issues
- Use manual runs for urgent topics
- Provide feedback on scoring accuracy

**❌ DON'T:**
- Lower threshold below 60 (quality suffers)
- Run multiple manual discoveries simultaneously
- Skip QA checks for convenience
- Ignore failed runs without investigating

### Configuration Recommendations

**Standard Configuration (Daily Runs):**
```
Topics: 5
Decisions per topic: 50
Threshold: 70
Ingest per topic: 10
```

**Aggressive Configuration (Weekly Deep Dive):**
```
Topics: 10
Decisions per topic: 100
Threshold: 65
Ingest per topic: 15
```

**Conservative Configuration (Quality Focus):**
```
Topics: 3
Decisions per topic: 30
Threshold: 80
Ingest per topic: 5
```

### Topic Customization

**Adding Custom Topics:**

You can influence topic generation by adding suggestions:

1. Create file: `storage/app/discovery/custom-topics.txt`
2. Add topics (one per line):
   ```
   Diskriminacija na radnom mjestu
   Autorska prava u digitalnom dobu
   Zaštita podataka - GDPR
   ```
3. AI will consider these alongside generated topics

---

## Troubleshooting

### Common Issues

#### Issue 1: Run Failed - Connection Error

**Symptom:**
```
❌ Status: Failed
Error: Connection timeout to odluke.sudovi.hr
```

**Cause:** Odluke.hr website is down or slow

**Solution:**
1. Wait 30 minutes, try again
2. If persists, check odluke.hr manually
3. Report to system admin

---

#### Issue 2: Low Ingestion Rate (< 10%)

**Symptom:**
```
⚠️ Evaluated: 250
✅ Ingested: 18 (7.2%)
```

**Cause:** Threshold too high or topics too specific

**Solution:**
1. Review average scores in run details
2. If most scores 60-69, lower threshold to 65
3. If scores consistently low, topics may be too niche

---

#### Issue 3: Duplicate Decisions

**Symptom:** Same decision ingested multiple times

**Cause:** Database constraint issue

**Solution:**
1. Report to admin (automatic deduplication should prevent this)
2. Manually remove duplicates via database

---

#### Issue 4: Graph Sync Failed

**Symptom:**
```
⚠️ Status: Partial
Graph sync: ❌ Failed for 12 decisions
```

**Cause:** Neo4j connection issue

**Solution:**
1. Check Neo4j health: `php artisan neo4j:health`
2. Manually sync: `php artisan graph:sync decision`
3. Report if persists

---

### Getting Help

**Support Channels:**

1. **Documentation:** `docs/AUTONOMOUS_DECISION_DISCOVERY.md`
2. **System Admin:** [admin-email]
3. **Slack:** #legal-tech-support
4. **Ticket System:** [support-url]

**When Reporting Issues:**

Include:
- Run ID
- Error message
- Screenshots
- Steps to reproduce

---

## Appendix

### A. Glossary

| Term | Definition |
|------|------------|
| **Autonomous Discovery** | AI-driven process to find and ingest court decisions |
| **Scoring** | AI evaluation of decision relevance (0-100 scale) |
| **Threshold** | Minimum score for ingestion (default: 70) |
| **Ingestion** | Process of adding decision to knowledge base |
| **Graph Sync** | Synchronizing decisions to Neo4j for citation analysis |
| **Topic** | Legal research area (e.g., "Nezakonit otkaz") |
| **Chunk** | Text segment for embedding generation |
| **Embedding** | Vector representation for semantic search |

### B. Keyboard Shortcuts

| Action | Shortcut |
|--------|----------|
| Refresh Dashboard | `F5` |
| Search Decisions | `Ctrl+K` |
| View Latest Run | `Ctrl+L` |
| Trigger Manual Run | `Ctrl+M` |
| Open Inspector | `Ctrl+I` |

### C. API Rate Limits

| Endpoint | Rate Limit | Window |
|----------|-----------|--------|
| `decision.search` | 30 requests | per minute |
| `decision.get` | 60 requests | per minute |
| `discovery.start` | 5 requests | per hour |

### D. File Locations

```
docs/
├── training/
│   └── autonomous-discovery.md        ← This guide
├── postman/
│   └── autonomous-discovery.json      ← API collection
├── images/autonomous-discovery/
│   ├── 01-login.png
│   ├── 02-dashboard-overview.png
│   ├── ...
│   └── 18-manual-override.png
└── AUTONOMOUS_DECISION_DISCOVERY.md   ← Technical docs
```

### E. Sample Output

**Successful Run Output:**

```bash
$ php artisan decisions:discover

🤖 Autonomous Decision Discovery
════════════════════════════════

[Step 1/5] Generating topics...
✅ Generated 5 topics in 3.2s

Topics:
  1. Nezakonit otkaz
  2. Ugovorna odgovornost
  3. Potrošačka zaštita
  4. Vlasničkopravni sporovi
  5. Kaznena djela prijevare

[Step 2/5] Searching decisions...
⏳ Topic 1/5: Nezakonit otkaz
   Found 52 decisions
⏳ Topic 2/5: Ugovorna odgovornost
   Found 48 decisions
⏳ Topic 3/5: Potrošačka zaštita
   Found 45 decisions
⏳ Topic 4/5: Vlasničkopravni sporovi
   Found 43 decisions
⏳ Topic 5/5: Kaznena djela prijevare
   Found 47 decisions

✅ Total: 235 decisions found

[Step 3/5] Scoring decisions...
⏳ Batch 1/24 (10 decisions)... ✅
⏳ Batch 2/24 (10 decisions)... ✅
⏳ Batch 3/24 (10 decisions)... ✅
...
✅ Scored 235 decisions in 8m 15s

[Step 4/5] Filtering (threshold: 70)...
✅ Selected 48 decisions

[Step 5/5] Ingesting decisions...
⏳ 1/48: VRH-2024-543 (score: 95)... ✅
⏳ 2/48: VRH-2024-312 (score: 93)... ✅
...
✅ Ingested 48 decisions in 12m 22s

════════════════════════════════
✨ Discovery Complete!

📊 Summary:
   Topics: 5
   Evaluated: 235
   Ingested: 48 (20.4%)
   Duration: 23m 47s
   Average Score: 82.3

🔗 View results:
   Dashboard: /decision-discovery/runs/127
   Graph: /graph-viewer
```

---

## Conclusion

You now have all the knowledge needed to:
- ✅ Understand autonomous discovery workflows
- ✅ Monitor daily discovery runs
- ✅ Trigger manual discovery when needed
- ✅ Inspect decision quality
- ✅ Integrate via API
- ✅ Troubleshoot common issues

**Next Steps:**
1. Practice navigating the dashboard
2. Review yesterday's discovery run
3. Try triggering a manual run (test environment)
4. Import Postman collection and test API
5. Set up daily QA routine

**Questions?**
Contact the legal tech team or refer to the full technical documentation.

---

**Document Revision History:**

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-01-29 | Training Team | Initial training guide |

**End of Training Guide**
