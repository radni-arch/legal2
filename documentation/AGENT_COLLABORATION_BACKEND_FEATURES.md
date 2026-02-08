# Agent Collaboration Backend Features

This document describes backend features of the Agent Collaboration system that currently lack UI exposure but are fully functional through the API and database layer.

## Overview

The Agent Collaboration system consists of:
- **OrchestrationLog** model - Tracks multi-agent collaboration sessions
- **AgentCommunication** model - Records inter-agent message passing
- **OrchestratorService** - Orchestrates agent pipeline execution
- **AgentCollaborationViewer** Livewire component - Real-time UI for monitoring

## Backend Features WITHOUT UI

### 1. Budget Management

**Location**: `OrchestrationLog` model, `OrchestratorService`

**Available but Not Exposed in UI**:
- `token_budget` - Maximum tokens allowed for the orchestration
- `cost_budget` - Maximum cost allowed (in USD)
- `time_budget_ms` - Maximum execution time (in milliseconds)

**How It Works**:
```php
$orchestrator = app(OrchestratorService::class);
$id = $orchestrator->orchestrate(
    pipeline: ['ResearchAgent', 'AnalysisAgent'],
    taskDescription: 'Legal case analysis',
    initialContext: ['case_id' => 'case-123'],
    budgets: [
        'token_budget' => 10000,
        'cost_budget' => 1.00,
        'time_budget_ms' => 60000,
    ]
);
```

**Current Limitation**: The UI displays token usage and cost but doesn't show:
- Budget limits
- Percentage of budget consumed
- Budget warnings/alerts

### 2. Feedback Iteration System

**Location**: `OrchestrationLog` model

**Available but Not Exposed in UI**:
- `feedback_requests` - Array of feedback requests during execution
- `feedback_iteration_count` - Number of feedback iterations performed

**How It Works**:
Agents can request human feedback during execution, creating a feedback loop. The orchestrator tracks:
- What feedback was requested
- How many iterations occurred
- Whether feedback was provided

**Current Limitation**: The UI shows execution history but doesn't display:
- Feedback requests made by agents
- Human responses provided
- Iteration count for quality improvement

### 3. Advanced AgentCommunication Features

**Location**: `AgentCommunication` model

**Available but Not Exposed in UI**:
- `response_data` - Agent's response to the message
- `duration_ms` - Time taken to process the message
- `retry_count` - Number of retry attempts
- `processed_at` - Timestamp when message was processed

**Query Scopes Available**:
```php
// Find messages from specific agent
AgentCommunication::fromAgent('ResearchAgent')->get();

// Find messages to specific agent
AgentCommunication::toAgent('AnalysisAgent')->get();

// Filter by status
AgentCommunication::withStatus('completed')->get();
```

**Current Limitation**: The UI shows basic message info (sender, receiver, payload, status) but doesn't display:
- Response data from receiver
- Processing duration
- Retry attempts
- Detailed timestamps

### 4. OrchestrationLog Timestamps

**Location**: `OrchestrationLog` model

**Available but Not Exposed in UI**:
- `started_at` - When orchestration actually began execution
- `completed_at` - When orchestration finished
- `duration_ms` - Total execution time

**Current Limitation**: The UI shows duration in milliseconds, but doesn't show:
- Start timestamp
- End timestamp
- Execution timeline graph
- Time spent per agent (as percentage of total)

### 5. Agent Pipeline Pre-execution State

**Location**: `OrchestrationLog` model

**Available but Not Tracked in UI**:
- `agent_pipeline` - Array of agents to be executed
- `total_agents` - Total number of agents in pipeline

**Current Limitation**: The UI shows execution history (agents that ran) but doesn't show:
- Full pipeline before execution starts
- Which agents are queued/pending
- Visual pipeline diagram with current position

### 6. Orchestration API Endpoints

**Location**: `app/Services/Agents/OrchestratorService.php`

**Available Features**:
```php
// Create orchestration
$orchestrationId = $orchestrator->orchestrate(
    pipeline: ['Agent1', 'Agent2', 'Agent3'],
    taskDescription: 'Task description',
    initialContext: ['key' => 'value'],
    budgets: ['token_budget' => 5000]
);

// Execute orchestration
$result = $orchestrator->execute($orchestrationId);
```

**Current Limitation**: No web UI for:
- Creating new orchestrations
- Triggering orchestration execution
- Canceling running orchestrations
- Re-running failed orchestrations

## Recommendations for UI Enhancement

### Priority 1: Budget Monitoring
Add budget display to the metrics section:
- Show budget limits alongside current usage
- Add progress bars for token/cost/time budgets
- Alert when approaching limits (90% threshold)

### Priority 2: Feedback System UI
Create feedback section:
- Display feedback requests made by agents
- Show iteration count
- Allow human feedback input if orchestration is waiting

### Priority 3: Enhanced Message Details
Expand message cards to show:
- Response data (collapsible)
- Processing duration
- Retry count
- Full timestamp history

### Priority 4: Pipeline Visualization
Add visual pipeline diagram:
- Show all agents in pipeline
- Highlight current/completed/failed agents
- Display progress through pipeline

### Priority 5: Orchestration Management
Add orchestration control panel:
- Form to create new orchestrations
- Button to execute pending orchestrations
- Cancel button for running orchestrations
- Re-run button for failed orchestrations

## Testing Coverage

All backend features are covered by:
- **E2E Tests**: `/tests/Browser/AgentCollaborationViewerTest.php`
- **Feature Tests**: `/tests/Feature/Livewire/AgentCollaborationViewerTest.php`

The E2E tests comprehensively cover:
- Component rendering
- Metrics display
- Timeline visualization
- Shared context display
- Inter-agent messages
- Error handling
- Empty states
- Authentication
- Complex multi-agent workflows

## Database Schema

### orchestration_logs table
```sql
- orchestration_id (UUID, unique)
- task_description (text)
- agent_pipeline (json)
- shared_context (json)
- execution_history (json)
- status (string)
- total_agents (integer)
- completed_agents (integer)
- failed_agents (integer)
- tokens_used (integer)
- cost_spent (decimal)
- duration_ms (integer)
- token_budget (integer, nullable)
- cost_budget (decimal, nullable)
- time_budget_ms (integer, nullable)
- error_message (text, nullable)
- started_at (timestamp, nullable)
- completed_at (timestamp, nullable)
- feedback_requests (json, nullable)
- feedback_iteration_count (integer, nullable)
```

### agent_communications table
```sql
- communication_id (UUID, unique)
- trace_id (string, nullable)
- collaboration_id (string, nullable)
- sender_agent_type (string)
- receiver_agent_type (string)
- message_type (string)
- message_data (json)
- response_data (json, nullable)
- status (string)
- duration_ms (integer, nullable)
- priority (integer, default 5)
- retry_count (integer, default 0)
- processed_at (timestamp, nullable)
```

## API Usage Examples

### Example 1: Create and Execute Orchestration
```php
use App\Services\Agents\OrchestratorService;

$orchestrator = app(OrchestratorService::class);

// Create orchestration
$orchestrationId = $orchestrator->orchestrate(
    pipeline: [
        'ResearchAgent',
        'PrecedentAnalyst',
        'EvidenceAnalyzer',
        'StrategyAdvisor'
    ],
    taskDescription: 'Analyze drug possession case for defense strategy',
    initialContext: [
        'case_id' => 'case-456',
        'jurisdiction' => 'Županijski sud u Osijeku',
        'charge' => 'Drug possession'
    ],
    budgets: [
        'token_budget' => 15000,
        'cost_budget' => 0.50,
        'time_budget_ms' => 120000
    ]
);

// Execute
$result = $orchestrator->execute($orchestrationId);

// View results at: /collaboration/{$orchestrationId}
```

### Example 2: Query Agent Communications
```php
use App\Models\AgentCommunication;

// Get all communications for an orchestration
$messages = AgentCommunication::query()
    ->where('collaboration_id', $orchestrationId)
    ->orderBy('created_at', 'asc')
    ->get();

// Get messages from specific agent
$researchMessages = AgentCommunication::fromAgent('ResearchAgent')
    ->where('collaboration_id', $orchestrationId)
    ->get();

// Get pending messages
$pending = AgentCommunication::withStatus('pending')->get();
```

### Example 3: Query Orchestration Logs
```php
use App\Models\OrchestrationLog;

// Get running orchestrations
$running = OrchestrationLog::where('status', 'running')->get();

// Get failed orchestrations
$failed = OrchestrationLog::where('status', 'failed')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Get orchestrations that exceeded budget
$overBudget = OrchestrationLog::query()
    ->whereNotNull('token_budget')
    ->whereRaw('tokens_used > token_budget')
    ->get();
```

## Future Enhancements

1. **Real-time WebSocket Updates**: Replace polling with WebSockets for true real-time updates
2. **Agent Performance Analytics**: Track agent success rates, average costs, typical durations
3. **Orchestration Templates**: Pre-defined agent pipelines for common tasks
4. **Budget Alerts**: Email/Slack notifications when budgets are exceeded
5. **Feedback UI**: Interactive UI for providing feedback to waiting agents
6. **Pipeline Editor**: Visual drag-and-drop pipeline builder
7. **Orchestration History**: Dashboard showing all orchestrations over time with analytics

## Related Documentation

- [README.md](../README.md) - Main project documentation
- [API_DOCUMENTATION.md](../API_DOCUMENTATION.md) - API endpoints
- [Livewire Component](../app/Http/Livewire/AgentCollaborationViewer.php) - Component source
- [E2E Tests](../tests/Browser/AgentCollaborationViewerTest.php) - Comprehensive test suite
