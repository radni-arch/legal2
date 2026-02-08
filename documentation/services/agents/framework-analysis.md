# AI Legal War Machine - Agent Framework Analysis

**Date**: October 30, 2025
**Scope**: Agent framework usage and queue job support analysis

---

## Total Agents: 7

| # | Agent Name | Location | Framework | Queue Support | Status |
|---|------------|----------|-----------|---------------|--------|
| 1 | AutonomousResearchAgent | app/Agents/ | ✅ Vizra BaseLlmAgent | ✅ YES (2 jobs) | Production |
| 2 | OdlukeAgent | app/Agents/ | ✅ Vizra BaseLlmAgent | ❌ NO | Production |
| 3 | DecisionDiscoveryAgent | app/Agents/ | ❌ Standalone | ❌ NO | Production |
| 4 | ResearchSpecialistAgent | app/Agents/Specialists/ | ❌ Standalone | ❌ NO | Production |
| 5 | PrecedentAnalystAgent | app/Agents/Specialists/ | ❌ Standalone | ❌ NO | Production |
| 6 | StrategySpecialistAgent | app/Agents/Specialists/ | ❌ Standalone | ❌ NO | Production |
| 7 | RiskAnalystAgent | app/Agents/Specialists/ | ❌ Standalone | ❌ NO | Production |

---

## Framework Usage Summary

**Using Vizra ADK**: 2/7 (28.6%)
- AutonomousResearchAgent ✅
- OdlukeAgent ✅

**Standalone (No Framework)**: 5/7 (71.4%)
- DecisionDiscoveryAgent
- ResearchSpecialistAgent
- PrecedentAnalystAgent
- StrategySpecialistAgent
- RiskAnalystAgent

---

## Queue Job Support

**Has Queue Jobs**: 1/7 (14.3%)
- AutonomousResearchAgent:
  - `ExecuteAgentResearch.php`
  - `RunAutonomousResearchJob.php`

**No Queue Jobs**: 6/7 (85.7%)
- All other agents run synchronously only

---

## Detailed Analysis

### 1. AutonomousResearchAgent ✅ FULL SUPPORT
- **Framework**: Extends `Vizra\VizraADK\Agents\BaseLlmAgent`
- **Queue Jobs**:
  - `ExecuteAgentResearch` - Main background execution job
  - `RunAutonomousResearchJob` - Alternative execution with resume support
- **Features**:
  - Async/sync execution modes via `async` parameter
  - Checkpoint/resume capability
  - Timeout: 600 seconds (10 min)
  - Events: `ResearchCompleted`, `ResearchFailed`
  - 18 tools available (law search, decision search, case search, graph query, etc.)
- **API**: `POST /api/agent/research/start {"async": true}`
- **Usage**: Production ready with full queue support

**Example**:
```bash
# Async execution (returns immediately, runs in background)
curl -X POST http://localhost/api/agent/research/start \
  -H "X-API-Token: $TOKEN" \
  -d '{
    "objective": "Research Croatian employment termination laws",
    "async": true,
    "max_iterations": 5
  }'

# Response:
{
  "success": true,
  "async": true,
  "run": {
    "id": "uuid",
    "status": "running",
    "message": "Research run started in background. Check status using GET /api/agent/research/{id}"
  }
}
```

---

### 2. OdlukeAgent ⚠️ PARTIAL SUPPORT
- **Framework**: Extends `Vizra\VizraADK\Agents\BaseLlmAgent`
- **Queue Jobs**: NONE
- **Issue**: Uses Vizra framework but NO queue job implementation
- **Usage**: Synchronous only (ChatGPT + MCP integration)
- **Recommendation**: Should have queue job for long-running tasks

**Gap**: Can handle MCP tool chains, but long-running chains block HTTP request.

---

### 3. DecisionDiscoveryAgent ⚠️ NO FRAMEWORK, NO QUEUE
- **Framework**: Standalone class (not using Vizra)
- **Queue Jobs**: NONE
- **Issue**: Runs long autonomous discovery (250+ decisions), but NO queue support
- **Current**: Runs via scheduled command (`php artisan decisions:discover`)
- **Problem**: Blocks cron execution for 3-5 minutes
- **Recommendation**: Should be converted to:
  1. Extend Vizra BaseLlmAgent (preferred), OR
  2. Create dedicated queue job for background execution

**Example Current Usage** (blocking):
```bash
# Scheduled in app/Console/Kernel.php (runs daily at 2 AM)
$schedule->command('decisions:discover')->dailyAt('02:00');

# Blocks for 3-5 minutes while:
# 1. LLM generates topics
# 2. Searches odluke.sudovi.hr (250 results)
# 3. LLM scores each decision
# 4. Ingests top 50 decisions
```

**Recommended**:
```bash
# Should dispatch queue job instead
$schedule->job(new ExecuteDecisionDiscoveryJob())->dailyAt('02:00');
```

---

### 4-7. Specialist Agents ℹ️ LIGHTWEIGHT
- **Framework**: Standalone classes
- **Queue Jobs**: NONE
- **Reason**: These are lightweight, fast-executing agents
- **Usage**: Called synchronously by `LegalTeamOrchestrator`
- **Performance**: < 5 seconds per task
- **Recommendation**: Queue support NOT needed (lightweight tasks)

These agents are designed to be fast, focused workers in a multi-agent collaboration system. They execute quickly and return results, making queue jobs unnecessary overhead.

---

## Queue Job Implementations

### ExecuteAgentResearch.php
```php
<?php

namespace App\Jobs;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteAgentResearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout;

    public function __construct(protected AgentRun $run)
    {
        // Dynamic timeout based on run's time limit
        $this->timeout = $run->time_limit_seconds
            ? $run->time_limit_seconds + 60
            : config('agent.safety.max_time_per_run', 3600) + 60;
    }

    public function handle(AutonomousResearchAgent $agent): void
    {
        // Execute the research run
        $agent->executeRun($this->run);
    }

    public function failed(\Throwable $exception): void
    {
        $this->run->update([
            'status' => 'failed',
            'error' => 'Job failed: ' . $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }

    public function tags(): array
    {
        return [
            'agent:research',
            'run:' . $this->run->id,
        ];
    }
}
```

### RunAutonomousResearchJob.php
```php
<?php

namespace App\Jobs;

use App\Agents\AutonomousResearchAgent;
use App\Events\ResearchCompleted;
use App\Events\ResearchFailed;
use App\Models\AgentRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class RunAutonomousResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes max
    public int $tries = 1; // Don't retry failed research runs

    public function __construct(
        public string $runId,
        public bool $resuming = false
    ) {}

    public function handle(): void
    {
        $run = AgentRun::findOrFail($this->runId);
        $agent = new AutonomousResearchAgent();

        if ($this->resuming && $run->canBeResumed()) {
            // Resume from checkpoint
            $completed = $agent->resumeRun($run);
        } else {
            // Normal execution
            $completed = $agent->executeRun($run);
        }

        // Broadcast completion event
        event(new ResearchCompleted($completed));
    }

    public function failed(\Throwable $exception): void
    {
        $run = AgentRun::find($this->runId);

        if ($run) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            event(new ResearchFailed($run, $exception->getMessage()));
        }
    }
}
```

---

## Gap Analysis

### Missing Queue Support

#### 1. OdlukeAgent - Has framework, missing queue job
- **Priority**: MEDIUM
- **Effort**: LOW (1 hour - copy ExecuteAgentResearch pattern)
- **Use case**: Long MCP tool chains
- **Impact**: Prevents HTTP timeout on complex queries

#### 2. DecisionDiscoveryAgent - No framework, no queue job
- **Priority**: HIGH
- **Effort**: MEDIUM (2 hours)
- **Use case**: Autonomous discovery runs (currently blocks cron)
- **Current workaround**: Scheduled command (not ideal)
- **Impact**: High - unblocks daily cron, enables parallel discovery

---

## Recommendations

### Short Term (Quick Wins)

#### 1. Create ExecuteDecisionDiscoveryJob ⚡ HIGH PRIORITY
```php
<?php

namespace App\Jobs;

use App\Agents\DecisionDiscoveryAgent;
use App\Models\DecisionDiscoveryRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExecuteDecisionDiscoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 1;

    public function handle(DecisionDiscoveryAgent $agent): void
    {
        $result = $agent->discover();

        // Store run record
        DecisionDiscoveryRun::create([
            'status' => 'completed',
            'statistics' => $result,
            'completed_at' => now(),
        ]);
    }
}

// In app/Console/Kernel.php:
$schedule->job(new ExecuteDecisionDiscoveryJob())->dailyAt('02:00');
```

#### 2. Create ExecuteOdlukeAgentJob 📋 MEDIUM PRIORITY
```php
<?php

namespace App\Jobs;

use App\Agents\OdlukeAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExecuteOdlukeAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 1;

    public function __construct(
        protected string $query,
        protected array $context = []
    ) {}

    public function handle(OdlukeAgent $agent): void
    {
        // Execute MCP tool chain
        $result = $agent->execute($this->query, $this->context);

        // Store or return result
        cache()->put("odluke_agent:{$this->query}", $result, 3600);
    }
}
```

### Long Term (Framework Consistency)

#### 1. Migrate DecisionDiscoveryAgent to Vizra Framework
- **Impact**: Consistency, code reuse, better error handling
- **Effort**: 4 hours
- **Benefits**:
  - Automatic checkpoint/resume
  - Built-in evaluation
  - Standardized tool interface
  - Event broadcasting

#### 2. Standardize all agents on Vizra ADK
- **Impact**: Code consistency across project
- **Effort**: 8 hours total
- **Benefit**: Lower maintenance, easier debugging
- **Note**: Specialist agents may not need this (they're intentionally simple)

---

## Current State vs Ideal State

### Current State ❌ Inconsistent
```
AutonomousResearchAgent → ✅ Vizra + ✅ Queue Jobs      ✅ EXCELLENT
OdlukeAgent             → ✅ Vizra + ❌ NO Queue        ⚠️ PARTIAL
DecisionDiscoveryAgent  → ❌ Standalone + ❌ NO Queue  ❌ BLOCKING ISSUE
Specialist Agents (4)   → ❌ Standalone + ❌ NO Queue  ✅ OK (fast tasks)
```

### Ideal State ✅ Consistent
```
AutonomousResearchAgent → ✅ Vizra + ✅ Queue Jobs      ✅ EXCELLENT
OdlukeAgent             → ✅ Vizra + ✅ Queue Jobs      ✅ EXCELLENT
DecisionDiscoveryAgent  → ✅ Vizra + ✅ Queue Jobs      ✅ EXCELLENT
Specialist Agents (4)   → ✅ Standalone + ❌ NO Queue  ✅ OK (intentional)
```

---

## Implementation Priority

### ⚡ HIGH Priority (Do First)
- [ ] **Add queue job for DecisionDiscoveryAgent**
  - Impact: HIGH (unblocks daily cron, enables parallel discovery)
  - Effort: 2 hours
  - Files to create:
    - `app/Jobs/ExecuteDecisionDiscoveryJob.php`
  - Files to modify:
    - `app/Console/Kernel.php` (change command to job dispatch)

### 📋 MEDIUM Priority (Do Soon)
- [ ] **Add queue job for OdlukeAgent**
  - Impact: MEDIUM (enables async MCP tool chains)
  - Effort: 1 hour
  - Files to create:
    - `app/Jobs/ExecuteOdlukeAgentJob.php`

### 📌 LOW Priority (Future Enhancement)
- [ ] **Migrate DecisionDiscoveryAgent to Vizra framework**
  - Impact: LOW (code consistency, nice-to-have)
  - Effort: 4 hours
  - Benefit: Checkpoint/resume, evaluation, events

- [ ] **Consider migrating specialist agents to Vizra**
  - Impact: LOW (they work fine as standalone)
  - Effort: 8 hours
  - Note: May be unnecessary complexity for simple agents

---

## Testing Queue Jobs

### Test Execution
```bash
# Dispatch DecisionDiscoveryJob to queue
php artisan tinker
>>> App\Jobs\ExecuteDecisionDiscoveryJob::dispatch();

# Start queue worker
php artisan queue:work --queue=default --tries=1

# Monitor job progress
php artisan queue:listen

# Check failed jobs
php artisan queue:failed
```

### Test Async AutonomousResearchAgent
```bash
# Start queue worker
php artisan queue:work --queue=default --tries=1 &

# Dispatch async research
curl -X POST http://localhost/api/agent/research/start \
  -H "X-API-Token: $TOKEN" \
  -d '{
    "objective": "Find laws on employment termination",
    "async": true,
    "max_iterations": 3
  }'

# Check status (returns immediately)
curl http://localhost/api/agent/research/{run_id} \
  -H "X-API-Token: $TOKEN"
```

---

## Conclusion

**Summary**:
- **7 agents total** in the project
- **Only 2 agents (28.6%)** use Vizra ADK framework
- **Only 1 agent (14.3%)** has queue job support
- **Major gap**: DecisionDiscoveryAgent runs daily but blocks cron (no queue support)

**Immediate Action Required**:
1. Create `ExecuteDecisionDiscoveryJob` (HIGH priority - 2 hours)
2. Update scheduled task to use queue job

**Optional Improvements**:
1. Create `ExecuteOdlukeAgentJob` (MEDIUM priority - 1 hour)
2. Migrate agents to Vizra framework for consistency (LOW priority - 12 hours total)

**Current Status**: ⚠️ Functional but inconsistent. Queue support is ad-hoc rather than standardized.

**Ideal Status**: ✅ All long-running agents (>30 sec) should support queue jobs for scalability and reliability.

---

**Document End** | Agent Framework Analysis v1.0
