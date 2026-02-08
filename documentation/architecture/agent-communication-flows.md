# Agent Communication Bus - Sequence Diagrams

This document illustrates common communication patterns in the Agent Communication Bus architecture.

## Scenario 1: Collaborative Research (Request/Response)

**Use Case**: A Research Specialist agent encounters a complex precedent case and requests help from a Precedent Analyst specialist.

```mermaid
sequenceDiagram
    participant RS as ResearchSpecialistAgent
    participant Bus as AgentMessageBus
    participant Router as MessageRouter
    participant Queue as Priority Queue
    participant PA as PrecedentAnalystAgent

    Note over RS: Executing research plan<br/>Encounters complex precedent

    RS->>Bus: send(REQUEST)
    Note right of RS: "Analyze precedent<br/>for proportionality"

    Bus->>Bus: Generate message ID & correlation ID
    Bus->>Router: route(message)

    Router->>Router: Check routing.target_type = AGENT_TYPE
    Router->>Router: Lookup PrecedentAnalystAgent

    Router->>Queue: enqueue(message, priority=HIGH)
    Note right of Queue: priority=500<br/>Queue ordered by priority DESC

    Bus-->>RS: Message ID returned
    Note over RS: Continues other work<br/>(async pattern)

    Queue->>PA: ProcessAgentMessage job
    Note right of PA: Queue worker dispatches<br/>highest priority message

    PA->>PA: Plan: Analyze precedent
    PA->>PA: Act: Search graph + vector store
    PA->>PA: Evaluate: Extract insights

    PA->>Bus: send(RESPONSE)
    Note right of PA: correlation_id links<br/>to original REQUEST

    Bus->>Router: route(response)
    Router->>Queue: enqueue(response, priority=HIGH)

    Queue->>RS: ProcessAgentMessage job

    RS->>RS: Receive RESPONSE
    Note over RS: correlation_id matched<br/>to original request

    RS->>RS: Integrate analysis into research
    RS->>RS: Continue Plan → Act → Evaluate
```

**Key Points**:
- Asynchronous request/response pattern
- Correlation ID links response back to request
- Both agents continue work while waiting
- Priority ensures timely processing

---

## Scenario 2: Pipeline Processing (Sequential Work Passing)

**Use Case**: A case is processed through multiple agent stages: Evidence Analysis → Risk Assessment → Strategy Recommendation

```mermaid
sequenceDiagram
    participant User as Defense Attorney
    participant EA as EvidenceAnalysisAgent
    participant Bus as AgentMessageBus
    participant Queue as Priority Queue
    participant RA as RiskAnalystAgent
    participant SA as StrategySpecialistAgent

    User->>EA: Analyze new case evidence

    EA->>EA: Plan: Identify evidence issues
    EA->>EA: Act: Search admissibility precedents
    EA->>EA: Evaluate: Score evidence strength

    Note over EA: Evidence analysis complete<br/>Score: 0.42 (weak evidence)

    EA->>Bus: send(REQUEST to RiskAnalystAgent)
    Note right of EA: "Assess risk given<br/>weak evidence"

    Bus->>Queue: enqueue(message, priority=NORMAL)

    Queue->>RA: ProcessAgentMessage job

    RA->>RA: Plan: Evaluate case risk
    RA->>RA: Act: Analyze prosecution strength
    RA->>RA: Evaluate: Calculate risk score

    Note over RA: Risk assessment complete<br/>Risk: MEDIUM (45%)

    RA->>Bus: send(REQUEST to StrategySpecialistAgent)
    Note right of RA: "Recommend strategy<br/>for medium-risk case"

    Bus->>Queue: enqueue(message, priority=NORMAL)

    Queue->>SA: ProcessAgentMessage job

    SA->>SA: Plan: Generate defense strategies
    SA->>SA: Act: Search precedents for similar cases
    SA->>SA: Evaluate: Rank strategies by success probability

    Note over SA: Strategy recommendations:<br/>1. Suppression motion (0.68)<br/>2. Plea negotiation (0.55)

    SA->>Bus: send(RESPONSE to RA)

    Bus->>Queue: enqueue(response)
    Queue->>RA: ProcessAgentMessage job

    RA->>Bus: send(RESPONSE to EA)

    Bus->>Queue: enqueue(response)
    Queue->>EA: ProcessAgentMessage job

    EA->>EA: Compile final report
    EA->>User: Return comprehensive analysis

    Note over User: Complete case analysis<br/>with evidence, risk, and strategy
```

**Key Points**:
- Sequential pipeline: EA → RA → SA → (back through chain)
- Each agent completes its work before triggering next stage
- Responses flow back through the chain
- Eventual consistency: Total time 10-30 seconds

---

## Scenario 3: Broadcast Coordination (Event Notification)

**Use Case**: New evidence is added to a case. All agents working on that case need to be notified to update their analysis.

```mermaid
sequenceDiagram
    participant User as Defense Attorney
    participant System as Case Management System
    participant Bus as AgentMessageBus
    participant Registry as Subscriber Registry
    participant Queue as Priority Queue
    participant EA as EvidenceAnalysisAgent
    participant RA as RiskAnalystAgent
    participant SA as StrategySpecialistAgent

    Note over EA,SA: All agents subscribed to<br/>"case.updated" channel

    User->>System: Upload new witness statement

    System->>System: Store document in S3
    System->>System: Run Textract OCR
    System->>System: Add to case.documents

    System->>Bus: broadcast(EVENT)
    Note right of System: type: CASE_EVIDENCE_ADDED<br/>case_id: 01JKWXYZ...<br/>channel: "case.updated"

    Bus->>Registry: getSubscribers("case.updated")

    Registry-->>Bus: [EA, RA, SA]

    Bus->>Queue: enqueue(message for EA, priority=NORMAL)
    Bus->>Queue: enqueue(message for RA, priority=NORMAL)
    Bus->>Queue: enqueue(message for SA, priority=NORMAL)

    Note over Queue: 3 messages queued<br/>(one per subscriber)

    par Parallel Message Delivery
        Queue->>EA: ProcessAgentMessage job
        and
        Queue->>RA: ProcessAgentMessage job
        and
        Queue->>SA: ProcessAgentMessage job
    end

    par Parallel Agent Updates
        EA->>EA: Re-analyze evidence<br/>with new witness statement
        and
        RA->>RA: Recalculate risk<br/>based on new evidence
        and
        SA->>SA: Update strategy<br/>recommendations
    end

    Note over EA,SA: All agents updated<br/>independently

    par Optional: Agents Publish Results
        EA->>Bus: broadcast(EVENT)
        Note right of EA: "Evidence analysis updated"
        and
        RA->>Bus: broadcast(EVENT)
        Note right of RA: "Risk score changed"
        and
        SA->>Bus: broadcast(EVENT)
        Note right of SA: "Strategy recommendations updated"
    end
```

**Key Points**:
- Single broadcast reaches multiple subscribers
- Subscriber Registry maintains channel subscriptions
- Agents process event independently (parallel execution)
- No response expected (fire-and-forget pattern)
- Agents can publish their own events after updating

---

## Scenario 4: Resource Negotiation (Coordination)

**Use Case**: Multiple agents want to call the Odluke.sudovi.hr API, but must respect rate limits (30 requests/minute). Agents coordinate to avoid exceeding the limit.

```mermaid
sequenceDiagram
    participant RA1 as ResearchAgent #1
    participant RA2 as ResearchAgent #2
    participant Bus as AgentMessageBus
    participant Coord as ResourceCoordinator
    participant Queue as Priority Queue
    participant Odluke as Odluke API

    Note over RA1,RA2: Both agents need to search<br/>Odluke for precedents

    RA1->>Bus: send(REQUEST to ResourceCoordinator)
    Note right of RA1: "Request Odluke API slot"

    RA2->>Bus: send(REQUEST to ResourceCoordinator)
    Note right of RA2: "Request Odluke API slot"

    Bus->>Queue: enqueue(RA1 request, priority=HIGH)
    Bus->>Queue: enqueue(RA2 request, priority=NORMAL)

    Queue->>Coord: ProcessAgentMessage (RA1 request)

    Coord->>Coord: Check rate limiter<br/>Current: 28/30 RPM<br/>Available slots: 2

    Coord->>Bus: send(RESPONSE to RA1)
    Note right of Coord: "GRANTED: slot reserved"

    Bus->>Queue: enqueue(response to RA1)

    Queue->>Coord: ProcessAgentMessage (RA2 request)

    Coord->>Coord: Check rate limiter<br/>Current: 29/30 RPM<br/>Available slots: 1

    Coord->>Bus: send(RESPONSE to RA2)
    Note right of Coord: "GRANTED: slot reserved"

    Bus->>Queue: enqueue(response to RA2)

    par Parallel API Calls
        Queue->>RA1: ProcessAgentMessage (response)
        RA1->>Odluke: Search for precedents
        Odluke-->>RA1: Results
        RA1->>Coord: broadcast(EVENT: "API call complete")
        and
        Queue->>RA2: ProcessAgentMessage (response)
        RA2->>Odluke: Search for precedents
        Odluke-->>RA2: Results
        RA2->>Coord: broadcast(EVENT: "API call complete")
    end

    Note over Coord: Rate limiter updated<br/>Slots released

    Note over RA1,RA2: Both agents completed<br/>API calls without exceeding limit
```

**Key Points**:
- ResourceCoordinator acts as gatekeeper for rate-limited resources
- Priority queue ensures urgent requests processed first
- Agents wait for GRANTED response before calling API
- Broadcast events signal slot release (for next waiting agent)
- Prevents circuit breaker trips from rate limit violations

---

## Message Flow Performance Characteristics

### Latency Targets

| Priority   | Target Delivery Time | Max Queue Depth | Use Cases                          |
|------------|----------------------|------------------|------------------------------------|
| CRITICAL   | < 1 second           | 10 messages      | Court deadlines, emergencies       |
| HIGH       | < 3 seconds          | 50 messages      | Active case work, user requests    |
| NORMAL     | < 5 seconds          | 200 messages     | Background research, analysis      |
| LOW        | < 30 seconds         | 1000 messages    | Corpus updates, batch processing   |

### Throughput Targets

- **Current Scale**: 100+ messages/minute
- **Target Scale**: 500 messages/minute (5x headroom)
- **Peak Scale**: 1000 messages/minute (circuit breaker threshold)

### Reliability Guarantees

- **At-Least-Once Delivery**: Messages remain in queue until acknowledged
- **Persistence**: All messages logged to `agent_messages` table
- **TTL Handling**: Messages expire after `ttl_seconds` (default: 3600)
- **Dead Letter Queue**: Failed messages after 3 retries moved to DLQ

---

## Queue Worker Configuration

### Recommended Worker Setup

```bash
# Production: Multiple workers for priority tiers
php artisan queue:work --queue=agent_messages_critical --tries=3 --timeout=60
php artisan queue:work --queue=agent_messages_high --tries=3 --timeout=120
php artisan queue:work --queue=agent_messages_normal --tries=3 --timeout=300
php artisan queue:work --queue=agent_messages_low --tries=1 --timeout=600

# Development: Single worker for all priorities
php artisan queue:work --queue=agent_messages --tries=3 --timeout=300
```

### Monitoring Commands

```bash
# Check queue depth by priority
php artisan queue:monitor agent_messages_critical --max=10
php artisan queue:monitor agent_messages_high --max=50

# Retry failed messages
php artisan queue:retry all

# Clear stuck messages
php artisan queue:flush
```

---

## Testing Examples

### Unit Test: Request/Response Flow

```php
use App\Services\AgentBus\AgentMessageBus;
use App\Services\AgentBus\AgentMessage;
use Tests\TestCase;

class AgentCommunicationTest extends TestCase
{
    public function test_request_response_correlation()
    {
        $bus = app(AgentMessageBus::class);

        // Agent 1 sends request
        $request = new AgentMessage([
            'type' => 'REQUEST',
            'routing' => ['target_type' => 'AGENT', 'target_id' => 'agent-2'],
            'payload' => ['action' => 'ANALYZE_PRECEDENT'],
        ]);

        $messageId = $bus->send($request);

        // Assert message queued
        $this->assertDatabaseHas('jobs', [
            'queue' => 'agent_messages_high',
        ]);

        // Agent 2 sends response
        $response = new AgentMessage([
            'type' => 'RESPONSE',
            'correlation_id' => $messageId,
            'routing' => ['target_type' => 'AGENT', 'target_id' => 'agent-1'],
            'payload' => ['status' => 'SUCCESS', 'result' => ['confidence' => 0.87]],
        ]);

        $bus->send($response);

        // Assert correlation preserved
        $this->assertEquals($messageId, $response->correlation_id);
    }
}
```

### Integration Test: Broadcast to Multiple Agents

```php
public function test_broadcast_delivers_to_all_subscribers()
{
    $bus = app(AgentMessageBus::class);
    $registry = app(SubscriberRegistry::class);

    // Subscribe 3 agents to channel
    $registry->subscribe('case.updated', 'agent-1');
    $registry->subscribe('case.updated', 'agent-2');
    $registry->subscribe('case.updated', 'agent-3');

    // Broadcast event
    $broadcast = new AgentMessage([
        'type' => 'BROADCAST',
        'routing' => ['target_type' => 'BROADCAST', 'channel' => 'case.updated'],
        'payload' => ['event' => 'EVIDENCE_ADDED'],
    ]);

    $bus->broadcast($broadcast);

    // Assert 3 messages queued (one per subscriber)
    $this->assertEquals(3, DB::table('jobs')->where('queue', 'agent_messages_normal')->count());
}
```

---

## Next Steps

1. **Team Review**: Review sequence diagrams with development team
2. **Performance Testing**: Validate latency targets with load tests
3. **Implementation**: Begin Phase 2 (Core Infrastructure) - see ADR-001
4. **Documentation**: Update AGENTS.md with communication bus usage examples

---

**Related Documents**:
- [ADR-001: Agent Communication Bus](./adr-001-agent-communication-bus.md)
- [AGENTS.md](../AGENTS.md)
- Sprint 17.6 Requirements
