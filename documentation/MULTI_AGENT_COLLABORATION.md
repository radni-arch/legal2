# Multi-Agent Collaboration System

## Overview

The Multi-Agent Collaboration System is a revolutionary feature that coordinates multiple specialized AI agents to work together on complex legal problems. Instead of a single AI handling everything, this system mimics how real legal teams collaborate - with specialists in research, precedent analysis, strategy, and risk assessment working together with shared knowledge.

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                  LegalTeamOrchestrator                      │
│                  (Coordinates Everything)                    │
└────────────────────┬────────────────────────────────────────┘
                     │
         ┌───────────┴───────────┐
         │                       │
         ▼                       ▼
┌─────────────────┐    ┌─────────────────────┐
│ SharedAgent     │◄───┤  Agent Executions   │
│ Context         │    │  (Tracking)         │
│ (Shared Memory) │    └─────────────────────┘
└────────┬────────┘
         │
         │ Accessible by all agents
         │
    ┌────┴────┬────────┬────────┬────────┐
    │         │        │        │        │
    ▼         ▼        ▼        ▼        ▼
┌────────┐ ┌───────┐ ┌───────┐ ┌──────┐
│Research│ │Precedent│Strategy││ Risk  │
│Special-│ │Analyst│ │Special-││Analyst│
│ist     │ │       │ │ist    ││      │
└────────┘ └───────┘ └───────┘ └──────┘
```

### Specialist Agents

#### 1. **Research Specialist Agent**
- **Role**: Legal Researcher
- **Expertise**: Legal research, information retrieval, source validation
- **Tasks**:
  - Searches for relevant Croatian laws
  - Finds applicable court decisions
  - Prioritizes results by relevance and authority
  - Uses LLM to plan research strategy

**Example Output**:
```json
{
  "laws_found": 15,
  "decisions_found": 20,
  "prioritized_results": {
    "laws": [...],
    "decisions": [...]
  },
  "summary": "Research complete: Found 15 relevant laws and 20 court decisions..."
}
```

#### 2. **Precedent Analyst Agent**
- **Role**: Precedent Analyst
- **Expertise**: Case law analysis, precedent comparison, binding authority assessment
- **Tasks**:
  - Analyzes each precedent for applicability (0-100 score)
  - Assesses binding authority (binding/persuasive/informative)
  - Identifies favorable vs unfavorable precedents
  - Extracts distinguishing factors

**Example Output**:
```json
{
  "strongest_precedents": [
    {
      "court": "Vrhovni sud Republike Hrvatske",
      "applicability_score": 92,
      "binding_authority": "binding",
      "favorable": true
    }
  ],
  "applicable_laws": [...],
  "distinguishing_factors": [...]
}
```

#### 3. **Strategy Specialist Agent**
- **Role**: Strategy Specialist
- **Expertise**: Strategic planning, argument development, tactical recommendations
- **Tasks**:
  - Develops 3-5 strong legal arguments
  - Creates strategic recommendations
  - Develops action plan with timeline
  - Estimates success probability

**Example Output**:
```json
{
  "legal_arguments": [
    {
      "title": "Unlawful termination during pregnancy",
      "argument": "According to Labor Law Art. 93...",
      "legal_basis": ["Labor Law NN 93/14 Art. 93"],
      "strength_score": 85,
      "potential_counterarguments": [...]
    }
  ],
  "strategic_recommendations": {
    "primary_strategy": "Focus on discrimination angle",
    "success_probability": 0.78
  }
}
```

#### 4. **Risk Analyst Agent**
- **Role**: Risk Analyst
- **Expertise**: Risk assessment, vulnerability analysis, defensive strategy
- **Tasks**:
  - Identifies weaknesses in arguments
  - Finds adverse precedents opposing counsel might use
  - Assesses procedural risks
  - Generates mitigation strategies

**Example Output**:
```json
{
  "overall_risk_score": {
    "score": 45,
    "level": "medium"
  },
  "argument_risks": [...],
  "adverse_precedents": [...],
  "risk_mitigations": [...]
}
```

## How It Works

### Execution Flow

```
1. User submits problem
         ↓
2. Orchestrator creates collaboration session
         ↓
3. Plans execution (which agents, what order)
         ↓
4. Executes agents sequentially:

   Research Specialist
         ↓ (writes to shared memory)
   Precedent Analyst
         ↓ (reads research, writes analysis)
   Strategy Specialist
         ↓ (reads analysis, writes strategy)
   Risk Analyst
         ↓ (reads strategy, writes risks)

5. Orchestrator synthesizes final result using LLM
         ↓
6. Returns comprehensive legal analysis
```

### Shared Memory System

Agents communicate through **SharedAgentContext**:

```php
// Agent writes to shared memory
$context->write('researched_laws', $laws);

// Another agent reads from shared memory
$laws = $context->read('researched_laws');

// Agents send messages to each other
$context->sendMessage('precedent_analyst', [
    'type' => 'research_complete',
    'laws' => $laws,
    'decisions' => $decisions
]);

// Get another agent's output
$researchOutput = $context->getAgentOutput('research_specialist');
```

## API Usage

### Solve a Legal Problem

**Endpoint**: `POST /api/collaboration/solve`

**Request**:
```json
{
  "problem": "My client was fired while pregnant without prior notice. What are our legal options?",
  "problem_type": "employment",  // optional
  "context": {                    // optional
    "jurisdiction": "Croatia",
    "additional_facts": "..."
  },
  "agents": [                     // optional, defaults to all
    "research_specialist",
    "precedent_analyst",
    "strategy_specialist",
    "risk_analyst"
  ],
  "execution_mode": "sequential"  // optional
}
```

**Response**:
```json
{
  "success": true,
  "collaboration_id": "9c8f3b2a-...",
  "session_id": "collab_abc123",
  "problem_statement": "My client was fired...",
  "execution_plan": {
    "mode": "sequential",
    "steps": [...]
  },
  "agent_outputs": {
    "research_specialist": {...},
    "precedent_analyst": {...},
    "strategy_specialist": {...},
    "risk_analyst": {...}
  },
  "final_result": {
    "synthesis": "Executive summary of the legal situation...",
    "research_findings": {...},
    "precedent_analysis": {...},
    "legal_strategy": {...},
    "risk_assessment": {...}
  },
  "metadata": {
    "tokens_used": 12345,
    "cost_spent": 1.85,
    "duration_seconds": 45
  }
}
```

### Get Collaboration Details

**Endpoint**: `GET /api/collaboration/{id}`

Returns full collaboration details including all agent executions.

### Get Recent Collaborations

**Endpoint**: `GET /api/collaboration/recent?limit=10`

Returns list of recent collaborations with basic info.

### Get Statistics

**Endpoint**: `GET /api/collaboration/stats`

Returns aggregated statistics:
- Total collaborations
- Success/failure rates
- Average duration
- Total tokens used
- Total cost

## Database Schema

### agent_collaborations Table

```sql
- id (uuid, primary key)
- session_id (string, indexed)
- orchestrator (string)
- problem_type (string)
- problem_statement (text)
- context (json)
- status (string: in_progress|completed|failed)
- agents_involved (json array)
- execution_plan (json)
- shared_memory (json)  -- Shared data between agents
- agent_outputs (json)
- final_result (json)
- synthesis (text)      -- Final summary
- total_steps (int)
- completed_steps (int)
- tokens_used (int)
- cost_spent (decimal)
- duration_seconds (int)
- started_at (timestamp)
- completed_at (timestamp)
```

### agent_executions Table

```sql
- id (uuid, primary key)
- collaboration_id (uuid, foreign key)
- agent_name (string)
- agent_role (string)
- execution_order (int)
- status (string: pending|running|completed|failed)
- task_description (text)
- input_context (json)
- output (json)
- messages_to_others (json)    -- Inter-agent messages sent
- messages_from_others (json)  -- Inter-agent messages received
- tokens_used (int)
- cost_spent (decimal)
- duration_ms (int)
- error_message (text)
- started_at (timestamp)
- completed_at (timestamp)
```

## Configuration

### Agent Selection

You can choose which agents to use:

```json
{
  "agents": ["research_specialist", "precedent_analyst"]
}
```

This skips Strategy and Risk agents if you only need research and analysis.

### Execution Mode

- `sequential` (default): Agents run one after another
- `parallel` (planned): Agents run simultaneously where dependencies allow

### Problem Type

Auto-detected but can be specified:
- `employment`
- `contract`
- `property`
- `family`
- `criminal`
- `general`

## Monitoring

### Livewire Dashboard

View at `/collaboration-dashboard`:
- Real-time collaboration monitoring
- Agent execution tracking
- Cost and token usage
- Success/failure rates
- Detailed execution logs

### Logging

All operations are logged:
```php
Log::info('LegalTeamOrchestrator - Starting collaboration', [...]);
Log::info('ResearchSpecialistAgent - Research completed', [...]);
```

## Cost Management

### Token Tracking

Every LLM call is tracked:
```php
$context->addTokensUsed($tokens);
```

Automatically propagates from:
- Agent Execution → Collaboration total

### Cost Calculation

Based on GPT-4o-mini pricing:
- $0.15 per 1M tokens (input + output)

Average collaboration costs: **$0.50 - $2.00**

## Performance

### Typical Execution Times

- Research Specialist: 10-15s
- Precedent Analyst: 15-20s
- Strategy Specialist: 12-18s
- Risk Analyst: 10-15s
- Final Synthesis: 5-8s

**Total**: 50-75 seconds for complete analysis

### Optimization Tips

1. **Limit agent selection**: Only use agents you need
2. **Reduce result counts**: Lower `limit` in searches
3. **Cache research results**: Reuse for similar problems
4. **Batch processing**: Queue multiple problems

## Advanced Usage

### Custom Agent Workflows

Create custom orchestration:

```php
use App\Services\Collaboration\LegalTeamOrchestrator;

$orchestrator = app(LegalTeamOrchestrator::class);

$result = $orchestrator->solveProblem(
    "Complex legal problem...",
    [
        'agents' => ['research_specialist', 'strategy_specialist'],
        'context' => ['special_instructions' => '...'],
    ]
);
```

### Extending with New Agents

1. Create new specialist agent class extending base pattern
2. Register in `LegalTeamOrchestrator` specialists array
3. Update execution plan generation
4. Agent automatically gets access to SharedAgentContext

### Integration with Existing Systems

```php
// From case management system
$case = LegalCase::find($id);

$result = $orchestrator->solveProblem(
    $case->description,
    [
        'problem_type' => 'employment',
        'context' => [
            'case_id' => $case->id,
            'existing_evidence' => $case->evidence,
        ],
    ]
);

// Store result
$case->ai_analysis = $result['final_result'];
$case->save();
```

## Troubleshooting

### Common Issues

**Problem**: Agent execution fails
- Check logs: `Log::error('Agent execution failed')`
- Verify API keys in `.env`
- Check token limits

**Problem**: Slow execution
- Reduce search result limits
- Use fewer agents
- Check network latency

**Problem**: High costs
- Monitor token usage in dashboard
- Set budget limits
- Use caching for repeated queries

### Debug Mode

Enable verbose logging:
```php
// In config/logging.php
'level' => 'debug',
```

View execution details in dashboard.

## Best Practices

1. **Clear Problem Statements**: More specific = better results
2. **Provide Context**: Include relevant facts upfront
3. **Monitor Costs**: Check dashboard regularly
4. **Validate AI Output**: Always have lawyer review
5. **Iterative Refinement**: Use results to ask follow-up questions

## Future Enhancements

Planned features:
- ✅ Sequential execution
- ⏳ Parallel execution (where dependencies allow)
- ⏳ Agent memory persistence across sessions
- ⏳ Human-in-the-loop approval at each step
- ⏳ Fine-tuned Croatian legal LLM
- ⏳ Integration with document generation
- ⏳ Multi-language support

## Examples

### Example 1: Employment Law Problem

**Input**:
```json
{
  "problem": "Employee was terminated during pregnancy without notice. Employer claims restructuring. Employee had 3 years tenure."
}
```

**Agents Execute**:
1. Research → Finds Labor Law Art. 93, Anti-Discrimination Act, 15 precedents
2. Precedent → Identifies 5 highly applicable cases, 3 favorable
3. Strategy → Develops discrimination + wrongful termination arguments
4. Risk → Identifies employer's restructuring defense, suggests mitigation

**Output**: Comprehensive analysis with 85% success probability estimate.

### Example 2: Contract Dispute

**Input**:
```json
{
  "problem": "Contractor failed to deliver project on time. Contract has liquidated damages clause but contractor claims force majeure due to supply chain."
}
```

**Result**: Full analysis of applicable contract law, precedents on force majeure, strategy for liquidated damages claim, risks of contractor defenses.

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- View dashboard: `/collaboration-dashboard`
- API documentation: `/api/documentation`

## License

Part of AI Legal War Machine - MIT License
