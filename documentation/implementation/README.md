# Implementation Quick Start Guide
**AI Legal War Machine - From 7.3 to 9.5 in 5 Weeks**

---

## 📚 Documentation Overview

You now have 3 comprehensive documents:

### 1. **PROJECT_ASSESSMENT_REPORT.md** (600+ lines)
**Purpose:** Understanding what's broken and why

**Contents:**
- Overall 7.3/10 score breakdown by milestone
- Detailed assessment of each component
- "Library Clerk vs War Machine" comparison
- Concrete code examples showing exact problems
- Cost estimates and ROI analysis

**Read this first** to understand the current state.

---

### 2. **IMPLEMENTATION_TASKS.md** (3,400+ lines)
**Purpose:** Step-by-step execution guide for coding agents

**Contents:**
- **22 concrete tasks** organized into 3 phases
- **Exact file paths** with line numbers
- **Complete ready-to-use code** for every task
- **Acceptance criteria** checklists
- **Time estimates** (90 total hours)

**Use this** as your execution roadmap.

---

### 3. **IMPLEMENTATION_QUICKSTART.md** (this file)
**Purpose:** Executive summary and quick navigation

---

## 🎯 The Problem (TL;DR)

Your autonomous agent is **scripted**, not **intelligent**.

**Evidence:**
```php
// app/Agents/AutonomousResearchAgent.php:239-253
protected function planNextStep(AgentRun $run): array
{
    // Comment says: "Use the LLM to plan"
    // Reality: Returns hardcoded actions!
    return ['actions' => $this->generateActions($run)];
}
```

**Translation:** Agent follows a fixed recipe instead of thinking.

---

## 🚀 The Solution (90 Hours)

### Phase 1: Fix the Brain (2 weeks, 30 hours)
**Replace hardcoded logic with LLM-driven intelligence**

| Task | File | Lines | Time |
|------|------|-------|------|
| 1.1 | AutonomousResearchAgent.php | Add after 298 | 2h |
| 1.2 | AutonomousResearchAgent.php | Add method | 1h |
| 1.3 | AutonomousResearchAgent.php | **Replace 239-253** | 4h |
| 1.4 | AutonomousResearchAgent.php | Add before 413 | 1h |
| 1.5 | AutonomousResearchAgent.php | **Replace 413-431** | 4h |
| 1.6 | AutonomousResearchAgent.php | Update method | 1h |
| 1.7 | Create test file | New file | 4h |
| 1.8 | Create test file | New file | 3h |
| 1.9 | Update docs | AUTONOMOUS_AGENT_README.md | 2h |

**Outcome:** Agent thinks and adapts instead of following script

---

### Phase 2: Autonomous Discovery (2 weeks, 40 hours)
**Build agent that discovers what to research**

| Task | File | Description | Time |
|------|------|-------------|------|
| 2.1 | Create DecisionDiscoveryAgent.php | Full agent class | 6h |
| 2.2 | Create DiscoverCourtDecisions.php | Artisan command | 2h |
| 2.3 | app/Console/Kernel.php | Schedule daily runs | 1h |
| 2.4 | Migration + Livewire dashboard | Monitoring UI | 8h |
| 2.5 | Create test file | Unit tests | 6h |
| 2.6 | Create docs | Full documentation | 3h |

**Outcome:** Agent autonomously finds and ingests court decisions

---

### Phase 3: Query Optimization (1 week, 20 hours)
**Make searches smarter**

| Task | File | Description | Time |
|------|------|-------------|------|
| 3.1 | Create QueryRewriter.php | LLM query rewriting | 6h |
| 3.2 | Update 3 search services | Integration | 3h |
| 3.3 | Create test file | Rewriter tests | 3h |
| 3.4 | Create ContextCompressor.php | Smart compression | 4h |
| 3.5 | Create/update docs | Documentation | 2h |
| 3.6 | Create integration test | End-to-end tests | 2h |

**Outcome:** Better search results, lower costs

---

## 📋 Execution Checklist

### Week 1-2: Agent Intelligence
- [ ] Complete Task 1.1-1.3 (LLM planning)
- [ ] Complete Task 1.4-1.5 (LLM insights)
- [ ] Complete Task 1.6-1.9 (tests & docs)
- [ ] **Validate:** Run `php artisan test --filter=AutonomousResearchAgent`
- [ ] **Test manually:** Agent now shows LLM reasoning in logs

### Week 3-4: Autonomous Discovery
- [ ] Complete Task 2.1 (DecisionDiscoveryAgent)
- [ ] Complete Task 2.2-2.3 (command & scheduling)
- [ ] Complete Task 2.4 (dashboard)
- [ ] Complete Task 2.5-2.6 (tests & docs)
- [ ] **Validate:** Run `php artisan decisions:discover --dry`
- [ ] **Test manually:** Dashboard shows discovery statistics

### Week 5: Optimization
- [ ] Complete Task 3.1-3.3 (QueryRewriter)
- [ ] Complete Task 3.4-3.6 (ContextCompressor)
- [ ] **Validate:** Run integration tests
- [ ] **Test manually:** Search quality improved

### Week 6: Polish & Deploy
- [ ] Run full test suite
- [ ] Update environment variables (if needed)
- [ ] Deploy to production
- [ ] Enable scheduled discovery
- [ ] Monitor first autonomous run

---

## 🎬 Quick Start: First Task

### TASK 1.1: Add LLM Planning Context Builder

**What:** Add a method that builds context for LLM planning

**Where:** `app/Agents/AutonomousResearchAgent.php` (add after line 298)

**Time:** 2 hours

**Code:** See IMPLEMENTATION_TASKS.md lines 8-74

**Steps:**
1. Open `app/Agents/AutonomousResearchAgent.php`
2. Find line 298 (end of `generateActions` method)
3. Add new method `buildPlanningContext()`
4. Copy code from IMPLEMENTATION_TASKS.md
5. Save file
6. ✅ Check acceptance criteria

---

## 💰 Cost Analysis

### Development
- **Phase 1:** 30 hours × $100/hr = $3,000
- **Phase 2:** 40 hours × $100/hr = $4,000
- **Phase 3:** 20 hours × $100/hr = $2,000
- **Total:** 90 hours / **$9,000**

### OpenAI API (Ongoing)
- **Planning:** 10 agents/day × $0.0003 = $0.003/day
- **Insights:** 30 extractions/day × $0.00005 = $0.0015/day
- **Discovery:** 1 run/day × $0.002 = $0.002/day
- **Query rewriting:** 50 queries/day × $0.00005 = $0.0025/day
- **Total:** ~**$0.25/day** = **$7.50/month**

Extremely affordable for an autonomous AI legal assistant!

---

## 📊 Expected Outcomes

### Before (Current State)
| Metric | Score/Value |
|--------|-------------|
| Overall quality | 7.3/10 |
| Agent intelligence | 6.0/10 (scripted) |
| Autonomous discovery | 0/10 (doesn't exist) |
| Search optimization | 7.0/10 (basic) |
| Monthly API cost | $0 (no autonomous ops) |

### After (All Phases Complete)
| Metric | Score/Value |
|--------|-------------|
| Overall quality | **9.5/10** |
| Agent intelligence | **9.0/10** (LLM-driven) |
| Autonomous discovery | **9.0/10** (fully autonomous) |
| Search optimization | **9.5/10** (query rewriting + compression) |
| Monthly API cost | **$7.50** (still very cheap!) |

---

## 🔧 Prerequisites

Before starting, ensure:
- [ ] PHP 8.1+ installed
- [ ] Composer dependencies up to date
- [ ] PostgreSQL with pgvector extension
- [ ] OpenAI API key in `.env` (OPENAI_API_KEY)
- [ ] Laravel queue worker running
- [ ] Neo4j running (for graph features)
- [ ] AWS credentials (for Textract, optional)

---

## 🧪 Testing Strategy

### After Each Phase

**Phase 1:**
```bash
# Unit tests
php artisan test --filter=AutonomousResearchAgentPlanningTest
php artisan test --filter=AutonomousResearchAgentInsightTest

# Manual test
php artisan tinker
>>> $agent = new \App\Agents\AutonomousResearchAgent();
>>> $run = $agent->startRun("Research Croatian labor law on overtime");
>>> $completed = $agent->executeRun($run);
>>> dd($completed->iterations); // Should show LLM reasoning!
```

**Phase 2:**
```bash
# Dry run (doesn't ingest)
php artisan decisions:discover --dry

# Real run with limited scope
php artisan decisions:discover --topics=2 --per-topic=10 --ingest=3

# Check dashboard
# Visit: http://localhost/admin/discovery
```

**Phase 3:**
```bash
# Test query rewriting
php artisan tinker
>>> $rewriter = app(\App\Services\QueryRewriter::class);
>>> $variants = $rewriter->rewrite("Can employer fire me?");
>>> dd($variants); // Should show 3 variants

# Integration test
php artisan test --filter=QueryOptimizationIntegrationTest
```

---

## 📞 Support

### Issues During Implementation

**Task taking too long?**
- Reference the "Time" estimate - if 2x over, ask for help
- Check "Acceptance Criteria" - maybe you're over-engineering

**Code doesn't work?**
- Copy code EXACTLY from IMPLEMENTATION_TASKS.md
- Check file paths and line numbers
- Verify dependencies are installed

**Tests failing?**
- Run migrations: `php artisan migrate`
- Clear cache: `php artisan cache:clear`
- Check OpenAI API key is valid

---

## 🎉 Success Indicators

You'll know you're done when:

### Phase 1 Victory
```bash
# Run agent and see LLM reasoning in logs
tail -f storage/logs/laravel.log | grep "LLM planning"
# Output: "LLM planning completed: reasoning: 'Found law X, now search for decisions...'"
```

### Phase 2 Victory
```bash
# Agent autonomously discovers and ingests
php artisan decisions:discover
# Output: "✅ Discovery Completed: 5 topics, 50 ingested, 0 errors"
```

### Phase 3 Victory
```bash
# Queries optimized automatically
# Search "fire me" → finds "nezakonit otkaz" + "Zakon o radu" + decisions
```

---

## 🗺️ Navigation Guide

### I Want To...

**Understand what's broken**
→ Read PROJECT_ASSESSMENT_REPORT.md

**Start coding immediately**
→ Go to IMPLEMENTATION_TASKS.md, Task 1.1

**See the big picture**
→ Read this file (IMPLEMENTATION_QUICKSTART.md)

**Check specific milestone status**
→ See docs/IMPLEMENTATION_STATUS.md (497 lines, technical details)

**Understand architecture**
→ See docs/ARCHITECTURE.md

**Learn about MCP tools**
→ See docs/MCP_TOOLS.md (962 lines)

**Get help with decisions scraping**
→ See docs/DECISIONS_INGESTION_FLOW.md

---

## 📈 Progress Tracking

Use this to track your progress:

```
PHASE 1: Fix Agent Brain
[ ] Task 1.1: Planning context builder (2h)
[ ] Task 1.2: Planning system prompt (1h)
[ ] Task 1.3: LLM-based planNextStep (4h) ⭐ CRITICAL
[ ] Task 1.4: Insight extraction prompt (1h)
[ ] Task 1.5: LLM-based extractInsight (4h) ⭐ CRITICAL
[ ] Task 1.6: Update agent instructions (1h)
[ ] Task 1.7: Planning tests (4h)
[ ] Task 1.8: Insight tests (3h)
[ ] Task 1.9: Documentation (2h)

PHASE 2: Autonomous Discovery
[ ] Task 2.1: DecisionDiscoveryAgent class (6h) ⭐ CRITICAL
[ ] Task 2.2: Artisan command (2h)
[ ] Task 2.3: Scheduled discovery (1h)
[ ] Task 2.4: Monitoring dashboard (8h)
[ ] Task 2.5: Discovery tests (6h)
[ ] Task 2.6: Documentation (3h)

PHASE 3: Query Optimization
[ ] Task 3.1: QueryRewriter service (6h)
[ ] Task 3.2: Search service integration (3h)
[ ] Task 3.3: Rewriter tests (3h)
[ ] Task 3.4: ContextCompressor service (4h)
[ ] Task 3.5: Documentation (2h)
[ ] Task 3.6: Integration tests (2h)

DEPLOYMENT
[ ] All tests passing
[ ] Documentation complete
[ ] Environment configured
[ ] Production deployment
[ ] Monitoring enabled
```

---

## 🚀 Let's Build This!

You have everything you need:
- ✅ Detailed assessment (PROJECT_ASSESSMENT_REPORT.md)
- ✅ Technical status (docs/IMPLEMENTATION_STATUS.md)
- ✅ Complete task breakdown (IMPLEMENTATION_TASKS.md)
- ✅ This quick start guide

**Next step:** Open IMPLEMENTATION_TASKS.md and start with Task 1.1

**Questions?** Every task has:
- Exact file and line numbers
- Complete code ready to use
- Acceptance criteria
- Time estimates

**You've got this!** 🚀

---

**Created:** October 26, 2025
**Last Updated:** October 26, 2025
**Version:** 1.0
**Status:** Ready for implementation
