# Agent Monitoring System - Complete Flow Documentation

## Overview

This document provides comprehensive flow diagrams and schemas for the Agent Monitoring API system, showing how health checks, statistics, and alerting work together to provide real-time visibility into agent job execution.

**Created:** Task 3.2 (Agent Monitoring API)
**Components:** AgentMonitoringController, Model Scopes, API Routes
**Purpose:** Real-time monitoring, alerting, and performance tracking

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    Agent Monitoring System Architecture                  │
│                                                                          │
│  ┌────────────────────────────────────────────────────────────────┐    │
│  │                    Client Layer                                 │    │
│  │                                                                 │    │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │    │
│  │  │  Dashboard   │  │  CLI Tool    │  │  Alert Bot   │         │    │
│  │  │  (Livewire)  │  │  (Artisan)   │  │  (Cron)      │         │    │
│  │  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘         │    │
│  │         │                  │                  │                 │    │
│  │         └──────────────────┼──────────────────┘                 │    │
│  │                            │                                    │    │
│  └────────────────────────────┼────────────────────────────────────┘    │
│                               │                                         │
│                               ▼                                         │
│  ┌────────────────────────────────────────────────────────────────┐    │
│  │                  API Routes (api.php)                          │    │
│  │                                                                 │    │
│  │  Middleware: api.token + throttle:60,1                         │    │
│  │                                                                 │    │
│  │  GET  /api/monitoring/health        → health()                 │    │
│  │  GET  /api/monitoring/statistics    → statistics()             │    │
│  │  GET  /api/monitoring/recent-runs   → recentRuns()             │    │
│  │  GET  /api/monitoring/failed-jobs   → failedJobs()             │    │
│  └────────────────────────────┬────────────────────────────────────┘    │
│                               │                                         │
│                               ▼                                         │
│  ┌────────────────────────────────────────────────────────────────┐    │
│  │           AgentMonitoringController                            │    │
│  │                                                                 │    │
│  │  ┌──────────────────┐  ┌──────────────────┐                   │    │
│  │  │ health()         │  │ statistics()     │                   │    │
│  │  │ • Calculate      │  │ • Aggregate runs │                   │    │
│  │  │   failure rates  │  │ • Duration stats │                   │    │
│  │  │ • Determine      │  │ • Decision counts│                   │    │
│  │  │   status         │  │ • Success rates  │                   │    │
│  │  └──────────────────┘  └──────────────────┘                   │    │
│  │                                                                 │    │
│  │  ┌──────────────────┐  ┌──────────────────┐                   │    │
│  │  │ recentRuns()     │  │ failedJobs()     │                   │    │
│  │  │ • Latest jobs    │  │ • Error details  │                   │    │
│  │  │ • Both agents    │  │ • Stack traces   │                   │    │
│  │  └──────────────────┘  └──────────────────┘                   │    │
│  └────────────────────────────┬────────────────────────────────────┘    │
│                               │                                         │
│                               ▼                                         │
│  ┌────────────────────────────────────────────────────────────────┐    │
│  │                    Data Layer                                  │    │
│  │                                                                 │    │
│  │  ┌─────────────────┐    ┌──────────────────────────┐          │    │
│  │  │  AgentRun       │    │ DecisionDiscoveryRun     │          │    │
│  │  │  (Model)        │    │ (Model)                  │          │    │
│  │  │                 │    │                          │          │    │
│  │  │  Scopes:        │    │  Scopes:                 │          │    │
│  │  │  • recent()     │    │  • recent()              │          │    │
│  │  │                 │    │  • completed()           │          │    │
│  │  │                 │    │  • failed()              │          │    │
│  │  │                 │    │                          │          │    │
│  │  │                 │    │  Methods:                │          │    │
│  │  │                 │    │  • getAverageDuration()  │          │    │
│  │  │                 │    │  • getTotalDiscovered()  │          │    │
│  │  │                 │    │  • getTotalIngested()    │          │    │
│  │  └─────────────────┘    └──────────────────────────┘          │    │
│  │         │                           │                          │    │
│  │         ▼                           ▼                          │    │
│  │  ┌──────────────┐           ┌──────────────────┐              │    │
│  │  │ agent_runs   │           │ decision_        │              │    │
│  │  │ (Table)      │           │ discovery_runs   │              │    │
│  │  └──────────────┘           │ (Table)          │              │    │
│  │                             └──────────────────┘              │    │
│  │                                                                 │    │
│  │  ┌──────────────┐           ┌──────────────────┐              │    │
│  │  │ jobs         │           │ failed_jobs      │              │    │
│  │  │ (Queue)      │           │ (Table)          │              │    │
│  │  └──────────────┘           └──────────────────┘              │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Complete Flow Schemas

### 1. Health Endpoint Flow

```
┌───────────────────────────────────────────────────────────────────────┐
│                    Health Check Request Flow                          │
│                                                                       │
│  CLIENT REQUEST                                                       │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ GET /api/monitoring/health?days=7                       │         │
│  │ Headers:                                                │         │
│  │   X-API-Token: abc123...                                │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ MIDDLEWARE LAYER                                        │         │
│  │                                                         │         │
│  │ 1. api.token → Validate X-API-Token header             │         │
│  │    └─ Invalid → 401 Unauthorized                       │         │
│  │                                                         │         │
│  │ 2. throttle:60,1 → Check rate limit                    │         │
│  │    └─ Exceeded → 429 Too Many Requests                 │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ CONTROLLER: health()                                    │         │
│  │                                                         │         │
│  │ 1. Extract parameters                                  │         │
│  │    $days = $request->integer('days', 7)                │         │
│  │                                                         │         │
│  │ 2. Get Research Agent Health                           │         │
│  │    ├─ Query: AgentRun::recent(7)->count()              │         │
│  │    ├─ Query: AgentRun::recent(7)                       │         │
│  │    │         ->where('status', 'failed')->count()      │         │
│  │    ├─ Calculate: failure_rate = (failed/total) * 100   │         │
│  │    └─ Determine: status = rate < 10% ? healthy : deg   │         │
│  │                                                         │         │
│  │ 3. Get Decision Discovery Health                       │         │
│  │    ├─ Query: DecisionDiscoveryRun::recent(7)->count()  │         │
│  │    ├─ Query: DecisionDiscoveryRun::recent(7)           │         │
│  │    │         ->failed()->count()                       │         │
│  │    ├─ Calculate: failure_rate                          │         │
│  │    ├─ Calculate: avg_decisions_per_run                 │         │
│  │    └─ Determine: status                                │         │
│  │                                                         │         │
│  │ 4. Get Queue Health                                    │         │
│  │    ├─ Query: Queue::size('default')                    │         │
│  │    ├─ Query: DB::table('failed_jobs')->count()         │         │
│  │    └─ Determine: status = size<100 && failed<10        │         │
│  │                                                         │         │
│  │ 5. Determine Overall Status                            │         │
│  │    ├─ Get max failure rate from both agents            │         │
│  │    ├─ If max > 50% → 'critical'                        │         │
│  │    ├─ Else if max > 25% → 'degraded'                   │         │
│  │    └─ Else → 'healthy'                                 │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ RESPONSE BUILDING                                       │         │
│  │                                                         │         │
│  │ Build JSON response:                                   │         │
│  │ {                                                      │         │
│  │   "status": "healthy",                                 │         │
│  │   "timestamp": "2025-10-31T10:30:00Z",                │         │
│  │   "period_days": 7,                                    │         │
│  │   "agents": {                                          │         │
│  │     "research": {                                      │         │
│  │       "total_runs": 45,                                │         │
│  │       "failed_runs": 2,                                │         │
│  │       "failure_rate": 4.44,                            │         │
│  │       "status": "healthy"                              │         │
│  │     },                                                 │         │
│  │     "decision_discovery": {                            │         │
│  │       "total_runs": 7,                                 │         │
│  │       "failed_runs": 0,                                │         │
│  │       "failure_rate": 0,                               │         │
│  │       "avg_decisions_per_run": 42.5,                   │         │
│  │       "status": "healthy"                              │         │
│  │     }                                                  │         │
│  │   },                                                   │         │
│  │   "queue": {                                           │         │
│  │     "queue_size": 3,                                   │         │
│  │     "failed_jobs_total": 5,                            │         │
│  │     "status": "healthy"                                │         │
│  │   }                                                    │         │
│  │ }                                                      │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ RESPONSE: 200 OK                                        │         │
│  │ Content-Type: application/json                          │         │
│  └─────────────────────────────────────────────────────────┘         │
└───────────────────────────────────────────────────────────────────────┘
```

### 2. Statistics Endpoint Flow

```
┌───────────────────────────────────────────────────────────────────────┐
│                    Statistics Request Flow                            │
│                                                                       │
│  CLIENT REQUEST                                                       │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ GET /api/monitoring/statistics?days=30                  │         │
│  │ Headers: X-API-Token: abc123...                         │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ MIDDLEWARE: api.token + throttle                        │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ CONTROLLER: statistics()                                │         │
│  │                                                         │         │
│  │ 1. Extract parameters                                  │         │
│  │    $days = $request->integer('days', 30)               │         │
│  │                                                         │         │
│  │ ┌─────────────────────────────────────────────────┐   │         │
│  │ │ Research Agent Statistics                       │   │         │
│  │ │                                                 │   │         │
│  │ │ Total Runs:                                     │   │         │
│  │ │   AgentRun::recent(30)->count()                 │   │         │
│  │ │                                                 │   │         │
│  │ │ Completed:                                      │   │         │
│  │ │   AgentRun::recent(30)                          │   │         │
│  │ │     ->where('status', 'completed')->count()     │   │         │
│  │ │                                                 │   │         │
│  │ │ Failed:                                         │   │         │
│  │ │   AgentRun::recent(30)                          │   │         │
│  │ │     ->where('status', 'failed')->count()        │   │         │
│  │ │                                                 │   │         │
│  │ │ Average Duration:                               │   │         │
│  │ │   1. Get completed runs                         │   │         │
│  │ │   2. For each run:                              │   │         │
│  │ │      - If started_at && completed_at:           │   │         │
│  │ │        duration = diffInSeconds                 │   │         │
│  │ │      - Else: use elapsed_seconds                │   │         │
│  │ │   3. Average all durations                      │   │         │
│  │ │   4. Round to 2 decimals                        │   │         │
│  │ └─────────────────────────────────────────────────┘   │         │
│  │                                                         │         │
│  │ ┌─────────────────────────────────────────────────┐   │         │
│  │ │ Decision Discovery Statistics                   │   │         │
│  │ │                                                 │   │         │
│  │ │ Total Runs:                                     │   │         │
│  │ │   DecisionDiscoveryRun::recent(30)->count()     │   │         │
│  │ │                                                 │   │         │
│  │ │ Completed:                                      │   │         │
│  │ │   DecisionDiscoveryRun::recent(30)              │   │         │
│  │ │     ->completed()->count()                      │   │         │
│  │ │                                                 │   │         │
│  │ │ Failed:                                         │   │         │
│  │ │   DecisionDiscoveryRun::recent(30)              │   │         │
│  │ │     ->failed()->count()                         │   │         │
│  │ │                                                 │   │         │
│  │ │ Average Duration:                               │   │         │
│  │ │   DecisionDiscoveryRun::getAverageDuration(30)  │   │         │
│  │ │   (Uses duration() method on each run)          │   │         │
│  │ │                                                 │   │         │
│  │ │ Total Decisions Found:                          │   │         │
│  │ │   DecisionDiscoveryRun::getTotalDiscovered(30)  │   │         │
│  │ │   (Sum of decisions_evaluated column)           │   │         │
│  │ │                                                 │   │         │
│  │ │ Total Decisions Ingested:                       │   │         │
│  │ │   DecisionDiscoveryRun::getTotalIngested(30)    │   │         │
│  │ │   (Sum of decisions_ingested column)            │   │         │
│  │ └─────────────────────────────────────────────────┘   │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ RESPONSE: 200 OK                                        │         │
│  │                                                         │         │
│  │ {                                                      │         │
│  │   "period_days": 30,                                   │         │
│  │   "research_agent": {                                  │         │
│  │     "total_runs": 120,                                 │         │
│  │     "completed": 115,                                  │         │
│  │     "failed": 5,                                       │         │
│  │     "avg_duration_seconds": 45.32                      │         │
│  │   },                                                   │         │
│  │   "decision_discovery": {                              │         │
│  │     "total_runs": 30,                                  │         │
│  │     "completed": 28,                                   │         │
│  │     "failed": 2,                                       │         │
│  │     "avg_duration_seconds": 142.35,                    │         │
│  │     "total_decisions_found": 3200,                     │         │
│  │     "total_decisions_ingested": 650                    │         │
│  │   }                                                    │         │
│  │ }                                                      │         │
│  └─────────────────────────────────────────────────────────┘         │
└───────────────────────────────────────────────────────────────────────┘
```

### 3. Real-time Dashboard Integration Flow

```
┌───────────────────────────────────────────────────────────────────────┐
│              Real-time Dashboard Polling Flow                         │
│                                                                       │
│  INITIAL PAGE LOAD                                                    │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ User visits /dashboard/monitoring                       │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Livewire Component: AgentHealthWidget                  │         │
│  │                                                         │         │
│  │ mount() {                                               │         │
│  │   $this->loadData();  // Initial load                   │         │
│  │ }                                                       │         │
│  │                                                         │         │
│  │ loadData() {                                            │         │
│  │   // Call health endpoint                               │         │
│  │   $this->health = Http::withHeaders([                   │         │
│  │     'X-API-Token' => config('app.api_token')            │         │
│  │   ])->get('/api/monitoring/health?days=7')              │         │
│  │     ->json();                                           │         │
│  │                                                         │         │
│  │   // Call statistics endpoint                           │         │
│  │   $this->statistics = Http::withHeaders([               │         │
│  │     'X-API-Token' => config('app.api_token')            │         │
│  │   ])->get('/api/monitoring/statistics?days=30')         │         │
│  │     ->json();                                           │         │
│  │ }                                                       │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ RENDER INITIAL STATE                                    │         │
│  │                                                         │         │
│  │ Display:                                                │         │
│  │ • System status badge (healthy/degraded/critical)       │         │
│  │ • Research agent metrics                                │         │
│  │ • Discovery agent metrics                               │         │
│  │ • Queue health                                          │         │
│  │ • Charts/graphs                                         │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ START POLLING (wire:poll.30s="loadData")               │         │
│  │                                                         │         │
│  │ Every 30 seconds:                                       │         │
│  │   1. Call loadData()                                    │         │
│  │   2. Fetch fresh health data                            │         │
│  │   3. Fetch fresh statistics                             │         │
│  │   4. Update component state                             │         │
│  │   5. Livewire re-renders changed elements               │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ STATUS CHANGE DETECTED                                  │         │
│  │                                                         │         │
│  │ IF health['status'] changed:                            │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ healthy → degraded                   │             │         │
│  │   │   ├─ Update badge color (yellow)     │             │         │
│  │   │   ├─ Show warning notification       │             │         │
│  │   │   └─ Log event                       │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ degraded → critical                  │             │         │
│  │   │   ├─ Update badge color (red)        │             │         │
│  │   │   ├─ Show alert notification         │             │         │
│  │   │   ├─ Send alert email/Slack          │             │         │
│  │   │   └─ Log critical event              │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ critical/degraded → healthy          │             │         │
│  │   │   ├─ Update badge color (green)      │             │         │
│  │   │   ├─ Show recovery notification      │             │         │
│  │   │   └─ Log recovery event              │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ CONTINUOUS MONITORING                                   │         │
│  │                                                         │         │
│  │ Loop continues every 30 seconds...                      │         │
│  │   • Poll health endpoint                                │         │
│  │   • Poll statistics endpoint                            │         │
│  │   • Update UI in real-time                              │         │
│  │   • Trigger alerts if needed                            │         │
│  └─────────────────────────────────────────────────────────┘         │
└───────────────────────────────────────────────────────────────────────┘
```

### 4. Automated Alert Flow

```
┌───────────────────────────────────────────────────────────────────────┐
│                    Automated Alert System Flow                        │
│                                                                       │
│  CRON TRIGGER (Every 15 minutes)                                      │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ php artisan schedule:run                                │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Kernel.php: schedule()                                  │         │
│  │                                                         │         │
│  │ $schedule->command('agents:check-health --alert')       │         │
│  │          ->everyFifteenMinutes();                       │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Artisan Command: CheckAgentHealth                      │         │
│  │                                                         │         │
│  │ 1. Call API endpoint                                    │         │
│  │    $response = Http::withHeaders([                      │         │
│  │      'X-API-Token' => config('app.api_token')           │         │
│  │    ])->get('/api/monitoring/health?days=7');            │         │
│  │                                                         │         │
│  │ 2. Parse response                                       │         │
│  │    $health = $response->json();                         │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ STATUS EVALUATION                                       │         │
│  │                                                         │         │
│  │ if ($health['status'] === 'healthy') {                 │         │
│  │   ├─ Log: "System healthy"                              │         │
│  │   ├─ Exit code: 0                                       │         │
│  │   └─ No alert                                           │         │
│  │ }                                                       │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ DEGRADED STATUS DETECTED                                │         │
│  │                                                         │         │
│  │ if ($health['status'] === 'degraded') {                │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ 1. Log Warning                       │             │         │
│  │   │    Log::warning('Agent system        │             │         │
│  │   │                  degraded', $health) │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ 2. Send Email Alert                  │             │         │
│  │   │    Mail::to('devops@example.com')    │             │         │
│  │   │        ->send(new SystemDegraded(    │             │         │
│  │   │          subject: "Warning: Agent    │             │         │
│  │   │                   System Degraded",  │             │         │
│  │   │          data: $health                │             │         │
│  │   │        ));                            │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ 3. Send Slack Notification           │             │         │
│  │   │    Slack::to('#alerts')               │             │         │
│  │   │         ->send("⚠️ Agent system      │             │         │
│  │   │                degraded\n"            │             │         │
│  │   │                + "Research: {rate}%  │             │         │
│  │   │                  failure\n"           │             │         │
│  │   │                + "Discovery: {rate}% │             │         │
│  │   │                  failure");           │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ 4. Exit                              │             │         │
│  │   │    return 1; // Non-zero exit code   │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │ }                                                       │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ CRITICAL STATUS DETECTED                                │         │
│  │                                                         │         │
│  │ if ($health['status'] === 'critical') {                │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ 1. Log Critical Error                │             │         │
│  │   │    Log::critical('Agent system       │             │         │
│  │   │                   critical', $health)│             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ 2. Send URGENT Email                 │             │         │
│  │   │    Mail::to([                        │             │         │
│  │   │      'devops@example.com',           │             │         │
│  │   │      'oncall@example.com'            │             │         │
│  │   │    ])->send(new SystemCritical(      │             │         │
│  │   │      subject: "🚨 CRITICAL: Agent   │             │         │
│  │   │                System Failure",      │             │         │
│  │   │      data: $health,                  │             │         │
│  │   │      priority: 'high'                │             │         │
│  │   │    ));                                │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ 3. Send Slack @channel Alert         │             │         │
│  │   │    Slack::to('#alerts')               │             │         │
│  │   │         ->send("🚨 @channel CRITICAL:│             │         │
│  │   │                Agent system failure\n"│             │         │
│  │   │                + "Failure rate >50%\n"│             │         │
│  │   │                + "Immediate action   │             │         │
│  │   │                  required!");         │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ 4. Send SMS (PagerDuty/Twilio)       │             │         │
│  │   │    PagerDuty::trigger([              │             │         │
│  │   │      'incident_key' => 'agent-crit', │             │         │
│  │   │      'description' => 'Agent failure',│             │         │
│  │   │      'severity' => 'critical'        │             │         │
│  │   │    ]);                                │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ 5. Create Incident Record            │             │         │
│  │   │    Incident::create([                │             │         │
│  │   │      'type' => 'agent_failure',      │             │         │
│  │   │      'severity' => 'critical',       │             │         │
│  │   │      'details' => $health,           │             │         │
│  │   │      'status' => 'open'              │             │         │
│  │   │    ]);                                │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ 6. Exit                              │             │         │
│  │   │    return 1; // Non-zero exit code   │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │ }                                                       │         │
│  └─────────────────────────────────────────────────────────┘         │
│                                                                      │
│  NEXT CHECK IN 15 MINUTES                                            │
│  (Loop continues...)                                                 │
└───────────────────────────────────────────────────────────────────────┘
```

### 5. Failed Job Investigation Flow

```
┌───────────────────────────────────────────────────────────────────────┐
│                 Failed Job Investigation Flow                         │
│                                                                       │
│  DEVELOPER NOTIFICATION                                               │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ "Alert: 15 failed jobs in last hour"                   │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 1: Get Failed Jobs                                │         │
│  │                                                         │         │
│  │ GET /api/monitoring/failed-jobs?limit=50                │         │
│  │                                                         │         │
│  │ Response:                                               │         │
│  │ {                                                      │         │
│  │   "total": 15,                                         │         │
│  │   "recent": [                                          │         │
│  │     {                                                  │         │
│  │       "id": 42,                                        │         │
│  │       "queue": "default",                              │         │
│  │       "payload": "{...job data...}",                   │         │
│  │       "exception": "Exception: MCP server timeout...", │         │
│  │       "failed_at": "2025-10-31T10:15:00Z"             │         │
│  │     },                                                 │         │
│  │     ...                                                │         │
│  │   ]                                                    │         │
│  │ }                                                      │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 2: Analyze Patterns                               │         │
│  │                                                         │         │
│  │ Group by exception type:                                │         │
│  │ ┌────────────────────────────────────────┐             │         │
│  │ │ MCP server timeout: 12 occurrences     │             │         │
│  │ │ Database deadlock: 2 occurrences       │             │         │
│  │ │ Memory exhausted: 1 occurrence         │             │         │
│  │ └────────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │ Pattern identified: MCP server issues                   │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 3: Get Recent Runs for Context                    │         │
│  │                                                         │         │
│  │ GET /api/monitoring/recent-runs?limit=20                │         │
│  │                                                         │         │
│  │ Cross-reference failed jobs with recent runs            │         │
│  │ ┌────────────────────────────────────────┐             │         │
│  │ │ Run #156: failed (MCP timeout)         │             │         │
│  │ │ Run #157: failed (MCP timeout)         │             │         │
│  │ │ Run #158: completed (took 3 min)       │             │         │
│  │ │ Run #159: failed (MCP timeout)         │             │         │
│  │ └────────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │ Observation: Intermittent MCP failures                  │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 4: Check Health for Trends                        │         │
│  │                                                         │         │
│  │ GET /api/monitoring/health?days=1                       │         │
│  │                                                         │         │
│  │ {                                                      │         │
│  │   "status": "degraded",                                │         │
│  │   "agents": {                                          │         │
│  │     "research": {                                      │         │
│  │       "failure_rate": 35.5,  ← High!                   │         │
│  │       "status": "degraded"                             │         │
│  │     }                                                  │         │
│  │   }                                                    │         │
│  │ }                                                      │         │
│  │                                                         │         │
│  │ Conclusion: Research agent having MCP connectivity      │         │
│  │             issues in last 24 hours                     │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 5: Root Cause Investigation                       │         │
│  │                                                         │         │
│  │ 1. Check MCP server status                              │         │
│  │    curl http://app-url/mcp/info                         │         │
│  │    → Slow response (8 seconds) ✗                        │         │
│  │                                                         │         │
│  │ 2. Check MCP server logs                                │         │
│  │    tail -f storage/logs/mcp.log                         │         │
│  │    → Many timeout warnings                              │         │
│  │                                                         │         │
│  │ 3. Check network latency                                │         │
│  │    ping mcp-server                                      │         │
│  │    → High latency (200ms avg)                           │         │
│  │                                                         │         │
│  │ ROOT CAUSE: MCP server network issues                   │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 6: Remediation                                    │         │
│  │                                                         │         │
│  │ 1. Restart MCP server                                   │         │
│  │ 2. Increase job timeout from 300s to 600s              │         │
│  │ 3. Retry failed jobs                                    │         │
│  │    php artisan queue:retry all                          │         │
│  │                                                         │         │
│  │ 4. Monitor for 30 minutes                               │         │
│  │    Watch /api/monitoring/health                         │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 7: Verify Resolution                              │         │
│  │                                                         │         │
│  │ GET /api/monitoring/health?days=1                       │         │
│  │                                                         │         │
│  │ {                                                      │         │
│  │   "status": "healthy",  ← Recovered!                   │         │
│  │   "agents": {                                          │         │
│  │     "research": {                                      │         │
│  │       "failure_rate": 4.2,                             │         │
│  │       "status": "healthy"                              │         │
│  │     }                                                  │         │
│  │   }                                                    │         │
│  │ }                                                      │         │
│  │                                                         │         │
│  │ ✓ System recovered                                      │         │
│  │ ✓ Failed jobs reprocessed                               │         │
│  │ ✓ Failure rate back to normal                           │         │
│  └─────────────────────────────────────────────────────────┘         │
└───────────────────────────────────────────────────────────────────────┘
```

---

## Data Flow Schemas

### 1. Model Scope Resolution

```
┌───────────────────────────────────────────────────────────────────────┐
│                    Scope Query Execution Flow                         │
│                                                                       │
│  EXAMPLE QUERY:                                                       │
│  DecisionDiscoveryRun::recent(7)->completed()->get()                  │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 1: recent(7) scope                                │         │
│  │                                                         │         │
│  │ public function scopeRecent($query, int $days = 7)     │         │
│  │ {                                                       │         │
│  │   return $query->where('created_at', '>=',             │         │
│  │                        now()->subDays($days));         │         │
│  │ }                                                       │         │
│  │                                                         │         │
│  │ Generated SQL fragment:                                 │         │
│  │   WHERE created_at >= '2025-10-24 00:00:00'            │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 2: completed() scope                              │         │
│  │                                                         │         │
│  │ public function scopeCompleted($query)                 │         │
│  │ {                                                       │         │
│  │   return $query->where('status', 'completed');         │         │
│  │ }                                                       │         │
│  │                                                         │         │
│  │ Generated SQL fragment:                                 │         │
│  │   AND status = 'completed'                             │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 3: get() - Execute query                          │         │
│  │                                                         │         │
│  │ Final SQL:                                              │         │
│  │                                                         │         │
│  │ SELECT *                                                │         │
│  │ FROM decision_discovery_runs                            │         │
│  │ WHERE created_at >= '2025-10-24 00:00:00'              │         │
│  │   AND status = 'completed'                             │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Database Query Execution                                │         │
│  │                                                         │         │
│  │ Returns collection of DecisionDiscoveryRun models       │         │
│  └─────────────────────────────────────────────────────────┘         │
└───────────────────────────────────────────────────────────────────────┘
```

### 2. Average Duration Calculation

```
┌───────────────────────────────────────────────────────────────────────┐
│              Average Duration Calculation Flow                        │
│                                                                       │
│  METHOD CALL:                                                         │
│  DecisionDiscoveryRun::getAverageDuration(30)                         │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 1: Fetch completed runs from last 30 days         │         │
│  │                                                         │         │
│  │ $runs = static::recent(30)                              │         │
│  │              ->completed()                              │         │
│  │              ->whereNotNull('completed_at')             │         │
│  │              ->get();                                   │         │
│  │                                                         │         │
│  │ SQL:                                                    │         │
│  │   SELECT *                                              │         │
│  │   FROM decision_discovery_runs                          │         │
│  │   WHERE created_at >= NOW() - INTERVAL 30 DAY          │         │
│  │     AND status = 'completed'                           │         │
│  │     AND completed_at IS NOT NULL                       │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 2: Check if empty                                 │         │
│  │                                                         │         │
│  │ if ($runs->isEmpty()) {                                │         │
│  │   return 0;                                             │         │
│  │ }                                                       │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 3: Calculate duration for each run                │         │
│  │                                                         │         │
│  │ $totalSeconds = $runs->sum(function ($run) {            │         │
│  │   return $run->duration() ?? 0;                         │         │
│  │ });                                                     │         │
│  │                                                         │         │
│  │ For each run:                                           │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ Run #1:                              │             │         │
│  │   │   started_at:  2025-10-30 02:00:00   │             │         │
│  │   │   completed_at: 2025-10-30 02:02:22  │             │         │
│  │   │   duration = 142 seconds             │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ┌──────────────────────────────────────┐             │         │
│  │   │ Run #2:                              │             │         │
│  │   │   started_at:  2025-10-29 02:00:00   │             │         │
│  │   │   completed_at: 2025-10-29 02:03:15  │             │         │
│  │   │   duration = 195 seconds             │             │         │
│  │   └──────────────────────────────────────┘             │         │
│  │                                                         │         │
│  │   ... (for all runs)                                    │         │
│  │                                                         │         │
│  │ Total: 4,250 seconds (sum of all durations)            │         │
│  └──────────────────────┬──────────────────────────────────┘         │
│                         │                                            │
│                         ▼                                            │
│  ┌─────────────────────────────────────────────────────────┐         │
│  │ Step 4: Calculate average                              │         │
│  │                                                         │         │
│  │ $average = $totalSeconds / $runs->count();              │         │
│  │          = 4,250 / 30                                   │         │
│  │          = 141.67                                       │         │
│  │                                                         │         │
│  │ return round($average, 2);                              │         │
│  │ → 141.67 seconds                                        │         │
│  └─────────────────────────────────────────────────────────┘         │
└───────────────────────────────────────────────────────────────────────┘
```

---

## Alert Threshold Matrix

```
┌───────────────────────────────────────────────────────────────────────┐
│                    Alert Threshold Decision Matrix                    │
│                                                                       │
│  METRIC: Failure Rate                                                 │
│                                                                       │
│  ┌─────────────┬──────────────┬──────────────┬──────────────┐        │
│  │ Failure     │ Per-Agent    │ Overall      │ Alert        │        │
│  │ Rate        │ Status       │ Status       │ Action       │        │
│  ├─────────────┼──────────────┼──────────────┼──────────────┤        │
│  │ 0% - 9%     │ healthy      │ healthy      │ None         │        │
│  │             │ ✓            │ ✓            │              │        │
│  ├─────────────┼──────────────┼──────────────┼──────────────┤        │
│  │ 10% - 24%   │ degraded     │ healthy      │ Log warning  │        │
│  │             │ ⚠            │ ✓            │              │        │
│  ├─────────────┼──────────────┼──────────────┼──────────────┤        │
│  │ 25% - 49%   │ degraded     │ degraded     │ Email +      │        │
│  │             │ ⚠            │ ⚠            │ Slack        │        │
│  ├─────────────┼──────────────┼──────────────┼──────────────┤        │
│  │ 50% - 100%  │ degraded     │ critical     │ Email +      │        │
│  │             │ ⚠            │ 🚨           │ Slack +      │        │
│  │             │              │              │ SMS/Page     │        │
│  └─────────────┴──────────────┴──────────────┴──────────────┘        │
│                                                                       │
│  METRIC: Queue Size                                                   │
│                                                                       │
│  ┌─────────────┬──────────────┬──────────────────────────────┐       │
│  │ Queue       │ Queue        │ Alert                         │       │
│  │ Size        │ Status       │ Action                        │       │
│  ├─────────────┼──────────────┼──────────────────────────────┤       │
│  │ 0 - 99      │ healthy      │ None                          │       │
│  │             │ ✓            │                               │       │
│  ├─────────────┼──────────────┼──────────────────────────────┤       │
│  │ 100 - 499   │ degraded     │ Log warning                   │       │
│  │             │ ⚠            │ Monitor trend                 │       │
│  ├─────────────┼──────────────┼──────────────────────────────┤       │
│  │ 500+        │ degraded     │ Email alert                   │       │
│  │             │ ⚠            │ Scale workers                 │       │
│  └─────────────┴──────────────┴──────────────────────────────┘       │
│                                                                       │
│  METRIC: Failed Jobs                                                  │
│                                                                       │
│  ┌─────────────┬──────────────┬──────────────────────────────┐       │
│  │ Failed      │ Queue        │ Alert                         │       │
│  │ Jobs        │ Status       │ Action                        │       │
│  ├─────────────┼──────────────┼──────────────────────────────┤       │
│  │ 0 - 9       │ healthy      │ None                          │       │
│  │             │ ✓            │                               │       │
│  ├─────────────┼──────────────┼──────────────────────────────┤       │
│  │ 10 - 49     │ degraded     │ Log warning                   │       │
│  │             │ ⚠            │ Review errors                 │       │
│  ├─────────────┼──────────────┼──────────────────────────────┤       │
│  │ 50+         │ degraded     │ Email alert                   │       │
│  │             │ ⚠            │ Immediate investigation       │       │
│  └─────────────┴──────────────┴──────────────────────────────┘       │
└───────────────────────────────────────────────────────────────────────┘
```

---

## Monitoring Dashboard Layout

```
┌───────────────────────────────────────────────────────────────────────┐
│                     Agent Monitoring Dashboard                        │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────────┐ │
│  │ SYSTEM STATUS                                    [Refresh: 30s] │ │
│  │                                                                  │ │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │ │
│  │  │   Overall    │  │   Research   │  │  Discovery   │          │ │
│  │  │              │  │    Agent     │  │    Agent     │          │ │
│  │  │  ✓ HEALTHY   │  │  ✓ healthy   │  │  ✓ healthy   │          │ │
│  │  │              │  │  4.4% failed │  │  0% failed   │          │ │
│  │  └──────────────┘  └──────────────┘  └──────────────┘          │ │
│  │                                                                  │ │
│  │  ┌──────────────┐  ┌──────────────┐                            │ │
│  │  │    Queue     │  │ Failed Jobs  │                            │ │
│  │  │              │  │              │                            │ │
│  │  │  ✓ healthy   │  │      5       │                            │ │
│  │  │  Size: 3     │  │              │                            │ │
│  │  └──────────────┘  └──────────────┘                            │ │
│  └─────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────────┐ │
│  │ STATISTICS (Last 30 Days)                                       │ │
│  │                                                                  │ │
│  │  ┌───────────────────────────────┐ ┌─────────────────────────┐ │ │
│  │  │ Research Agent                │ │ Decision Discovery      │ │ │
│  │  │                               │ │                         │ │ │
│  │  │ Total Runs:          120      │ │ Total Runs:        30   │ │ │
│  │  │ Completed:           115      │ │ Completed:         28   │ │ │
│  │  │ Failed:                5      │ │ Failed:             2   │ │ │
│  │  │ Success Rate:      95.8%      │ │ Success Rate:    93.3%  │ │ │
│  │  │ Avg Duration:      45.3s      │ │ Avg Duration:   142.4s  │ │ │
│  │  │                               │ │                         │ │ │
│  │  │                               │ │ Decisions Found: 3,200  │ │ │
│  │  │                               │ │ Ingested:          650  │ │ │
│  │  │                               │ │ Ingest Rate:     20.3%  │ │ │
│  │  └───────────────────────────────┘ └─────────────────────────┘ │ │
│  └─────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────────┐ │
│  │ RECENT RUNS (Last 10)                                           │ │
│  │                                                                  │ │
│  │  ┌────┬─────────┬──────────────────┬──────────┬─────────────┐  │ │
│  │  │ ID │ Agent   │ Objective/Topic  │ Status   │ Duration    │  │ │
│  │  ├────┼─────────┼──────────────────┼──────────┼─────────────┤  │ │
│  │  │ 42 │Research │ Labor law        │✓Complete │ 45s         │  │ │
│  │  │ 41 │Discovery│ Auto discovery   │✓Complete │ 142s        │  │ │
│  │  │ 40 │Research │ Property law     │✓Complete │ 38s         │  │ │
│  │  │ 39 │Research │ Consumer rights  │✗ Failed  │ 120s        │  │ │
│  │  │ 38 │Discovery│ Auto discovery   │✓Complete │ 155s        │  │ │
│  │  └────┴─────────┴──────────────────┴──────────┴─────────────┘  │ │
│  │                                                                  │ │
│  │  [View All] [Export CSV]                                        │ │
│  └─────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────────┐ │
│  │ PERFORMANCE TRENDS                                              │ │
│  │                                                                  │ │
│  │  Success Rate (7 days)                                          │ │
│  │  100% ┼─────────────────────────────────────────────           │ │
│  │   90% ┼─────────────●─────●─────●─────●─────●─────            │ │
│  │   80% ┼                                                         │ │
│  │       └─────────────────────────────────────────                │ │
│  │        Mon  Tue  Wed  Thu  Fri  Sat  Sun                        │ │
│  │                                                                  │ │
│  │  Average Duration (7 days)                                      │ │
│  │  150s ┼─────────────────────────────────────────────           │ │
│  │  100s ┼─────●─────●─────●─────●─────●─────●─────              │ │
│  │   50s ┼                                                         │ │
│  │       └─────────────────────────────────────────                │ │
│  │        Mon  Tue  Wed  Thu  Fri  Sat  Sun                        │ │
│  └─────────────────────────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────────────────────────┘
```

---

## Integration Architecture

```
┌───────────────────────────────────────────────────────────────────────┐
│           Monitoring API Integration Architecture                     │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────────┐ │
│  │                    External Systems                              │ │
│  │                                                                  │ │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │ │
│  │  │  Prometheus  │  │   Grafana    │  │  DataDog     │          │ │
│  │  │              │  │              │  │              │          │ │
│  │  │ • Scrapes    │  │ • Visualizes │  │ • APM        │          │ │
│  │  │   /metrics   │  │   metrics    │  │ • Alerts     │          │ │
│  │  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │ │
│  │         │                  │                  │                 │ │
│  │         └──────────────────┼──────────────────┘                 │ │
│  │                            │                                    │ │
│  └────────────────────────────┼────────────────────────────────────┘ │
│                               │                                      │
│                               ▼                                      │
│  ┌─────────────────────────────────────────────────────────────────┐ │
│  │              Monitoring API (Laravel)                            │ │
│  │                                                                  │ │
│  │  GET /api/monitoring/health                                     │ │
│  │  GET /api/monitoring/statistics                                 │ │
│  │  GET /api/monitoring/recent-runs                                │ │
│  │  GET /api/monitoring/failed-jobs                                │ │
│  │  GET /metrics (Prometheus format)                               │ │
│  └────────────────────────────┬────────────────────────────────────┘ │
│                               │                                      │
│                               ▼                                      │
│  ┌─────────────────────────────────────────────────────────────────┐ │
│  │                   Alert Routing Layer                            │ │
│  │                                                                  │ │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐                │ │
│  │  │   Email    │  │   Slack    │  │  PagerDuty │                │ │
│  │  │            │  │            │  │            │                │ │
│  │  │ • Degraded │  │ • @channel │  │ • Critical │                │ │
│  │  │ • Critical │  │   alerts   │  │   incidents│                │ │
│  │  └────────────┘  └────────────┘  └────────────┘                │ │
│  └─────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────────┐ │
│  │                   Internal Consumers                             │ │
│  │                                                                  │ │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │ │
│  │  │  Dashboard   │  │   CLI Tool   │  │  Cron Jobs   │          │ │
│  │  │  (Livewire)  │  │  (Artisan)   │  │  (Schedule)  │          │ │
│  │  │              │  │              │  │              │          │ │
│  │  │ • Real-time  │  │ • On-demand  │  │ • Periodic   │          │ │
│  │  │   polling    │  │   checks     │  │   checks     │          │ │
│  │  └──────────────┘  └──────────────┘  └──────────────┘          │ │
│  └─────────────────────────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────────────────────────┘
```

---

## File Structure

```
app/
├── Http/
│   └── Controllers/
│       └── AgentMonitoringController.php
│           ├── health()              [GET  /api/monitoring/health]
│           ├── statistics()          [GET  /api/monitoring/statistics]
│           ├── recentRuns()          [GET  /api/monitoring/recent-runs]
│           ├── failedJobs()          [GET  /api/monitoring/failed-jobs]
│           ├── getResearchAgentHealth()
│           ├── getDecisionDiscoveryHealth()
│           ├── getQueueHealth()
│           └── getResearchAgentAvgDuration()
│
├── Models/
│   ├── AgentRun.php
│   │   └── scopeRecent($query, int $days)
│   │
│   └── DecisionDiscoveryRun.php
│       ├── scopeRecent($query, int $days)
│       ├── scopeCompleted($query)
│       ├── scopeFailed($query)
│       ├── getAverageDuration(int $days): float
│       ├── getTotalDiscovered(int $days): int
│       └── getTotalIngested(int $days): int
│
routes/
└── api.php
    └── Route::prefix('monitoring')
        ├── GET  /health
        ├── GET  /statistics
        ├── GET  /recent-runs
        └── GET  /failed-jobs

docs/
├── AGENT_MONITORING_API.md           (API documentation)
└── AGENT_MONITORING_FLOW.md          (This file - Flow schemas)
```

---

## Quick Reference

### Endpoints

| Endpoint | Purpose | Key Metrics |
|----------|---------|-------------|
| `/health` | System health | Status, failure rates, queue health |
| `/statistics` | Performance metrics | Runs, durations, success rates |
| `/recent-runs` | Activity log | Latest executions with status |
| `/failed-jobs` | Error tracking | Failed jobs with exceptions |

### Status Values

| Status | Meaning | Action Required |
|--------|---------|-----------------|
| `healthy` | All systems normal | None |
| `degraded` | Some failures detected | Monitor, investigate |
| `critical` | High failure rate | Immediate action |

### Alert Channels

| Channel | Trigger | Response Time |
|---------|---------|---------------|
| Logs | All events | Review daily |
| Email | Degraded/Critical | Check hourly |
| Slack | Critical | Check immediately |
| SMS/Page | Critical | Respond within 15 min |

---

## Related Documentation

- [Agent Monitoring API](AGENT_MONITORING_API.md) - Complete API reference
- [Agent Queue Jobs Overview](AGENT_QUEUE_JOBS_OVERVIEW.md) - Jobs architecture
- [ExecuteDecisionDiscoveryJob Flow](EXECUTE_DECISION_DISCOVERY_JOB_FLOW.md)
- [ExecuteOdlukeAgentJob Flow](EXECUTE_ODLUKE_AGENT_JOB_FLOW.md)

---

## Changelog

**v1.0.0 (2025-10-31) - Initial Release**
- Created comprehensive monitoring flow documentation
- Added 5 complete flow schemas (health, statistics, dashboard, alerts, investigation)
- Documented data flow for model scopes and calculations
- Included alert threshold matrix
- Added dashboard layout mockup
- Documented integration architecture
- Created quick reference guide

---

## Support

**For monitoring questions:**
1. Review flow diagrams in this document
2. Check API documentation: AGENT_MONITORING_API.md
3. Test endpoints with cURL examples
4. Review logs: `storage/logs/laravel.log`
5. Open GitHub issue with flow-specific questions
