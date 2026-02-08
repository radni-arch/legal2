# ADR-001: Agent Communication Bus

**Status**: Proposed
**Date**: 2025-11-10
**Decision Makers**: Development Team
**Sprint**: 17.6 (Priority: MEDIUM, Points: 5)

## Context

The AI Legal War Machine uses autonomous agents for legal research, analysis, and decision-making. Currently, agents operate independently without a standardized way to communicate, collaborate, or coordinate work. As we expand to multi-agent workflows (collaborative research, pipeline processing, resource negotiation), we need a robust message bus architecture.

### Current Agent Architecture

Agents follow the **Plan → Act → Evaluate** loop:
- **Plan**: Analyze current state, generate research questions, validate plan
- **Act**: Execute planned actions (search laws, query graph, fetch resources)
- **Evaluate**: Extract insights, score quality, decide to continue or stop

**Existing Agents**:
- `AutonomousResearchAgent` - Self-evaluating research with iterative improvement
- `DecisionDiscoveryAgent` - Discovers and analyzes court decisions
- `OdlukeAgent` - MCP-powered agent for odluke.sudovi.hr
- `Specialists/`:
  - `RiskAnalystAgent` - Evaluates case risk factors
  - `ResearchSpecialistAgent` - Deep legal research
  - `PrecedentAnalystAgent` - Finds and analyzes precedent cases
  - `StrategySpecialistAgent` - Defense strategy recommendations

**Current Queue System**:
- Database-backed Laravel queues
- Single `agents` queue for all agent jobs
- No inter-agent communication mechanism
- No priority handling beyond queue order

### Communication Scenarios

1. **Collaborative Research**: Research agent requests precedent analysis from specialist
2. **Pipeline Processing**: Sequential work passing (evidence analysis → risk assessment → strategy)
3. **Broadcast Coordination**: Notify all agents of case updates (new evidence, status changes)
4. **Resource Negotiation**: Agents coordinate to avoid duplicate work or API rate limits

## Decision

We will implement an **Agent Communication Bus** as a message-oriented middleware layer on top of Laravel's existing database queue system.

### Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│              Agent Communication Bus                     │
├─────────────────────────────────────────────────────────┤
│                                                           │
│  ┌──────────────┐      ┌──────────────┐                │
│  │   Message    │◄────►│   Message    │                │
│  │   Publisher  │      │   Router     │                │
│  └──────────────┘      └──────────────┘                │
│         ▲                      │                         │
│         │                      ▼                         │
│  ┌──────────────┐      ┌──────────────┐                │
│  │   Agents     │      │   Priority   │                │
│  │              │      │   Queue      │                │
│  └──────────────┘      └──────────────┘                │
│         ▲                      │                         │
│         │                      ▼                         │
│  ┌──────────────┐      ┌──────────────┐                │
│  │   Message    │◄────►│  Subscriber  │                │
│  │   Consumer   │      │   Registry   │                │
│  └──────────────┘      └──────────────┘                │
│                                                           │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
              Laravel Queue (Database)
```

**Core Components**:
1. **Message Publisher** - API for agents to send messages
2. **Message Router** - Routes messages to appropriate agents/channels
3. **Priority Queue** - Orders messages by priority level
4. **Subscriber Registry** - Tracks which agents handle which message types
5. **Message Consumer** - Delivers messages to target agents

### Message Format

All messages follow a standard envelope:

```json
{
  "id": "ULID",
  "type": "REQUEST|RESPONSE|BROADCAST|EVENT",
  "priority": "CRITICAL|HIGH|NORMAL|LOW",
  "sender": {
    "agent_id": "ULID",
    "agent_class": "Full\\Class\\Name",
    "agent_run_id": "ULID"
  },
  "routing": {
    "target_type": "AGENT|AGENT_TYPE|BROADCAST",
    "target_id": "ULID or null",
    "target_class": "Full\\Class\\Name or null"
  },
  "correlation_id": "ULID or null",
  "timestamp": "ISO 8601",
  "ttl_seconds": 3600,
  "payload": {},
  "metadata": {
    "case_id": "ULID",
    "context": {},
    "tags": []
  }
}
```

**Message Types**:
- **REQUEST**: Agent asks another agent for help
- **RESPONSE**: Agent replies to a REQUEST (linked via correlation_id)
- **BROADCAST**: Agent announces information to all subscribers
- **EVENT**: System or external event notification

**Priority Levels**:
- **CRITICAL** (1000): Immediate action required (deadlines, emergencies)
- **HIGH** (500): Important but not urgent (active case research)
- **NORMAL** (100): Standard operations (background analysis)
- **LOW** (10): Batch processing (corpus updates, maintenance)

### Routing Strategies

1. **Direct to Agent**: Route to specific agent instance by ULID
   ```php
   $bus->send(new AgentMessage([
       'routing' => ['target_type' => 'AGENT', 'target_id' => '01JKWXYZ...']
   ]));
   ```

2. **Route to Agent Type**: Route to any available instance of agent class
   ```php
   $bus->send(new AgentMessage([
       'routing' => ['target_type' => 'AGENT_TYPE', 'target_class' => PrecedentAnalystAgent::class]
   ]));
   ```

3. **Broadcast**: Send to all agents subscribed to a channel
   ```php
   $bus->broadcast(new AgentMessage([
       'routing' => ['target_type' => 'BROADCAST', 'channel' => 'case.updated']
   ]));
   ```

### Implementation Components

**New Classes**:
- `App\Services\AgentBus\AgentMessageBus` - Main bus service
- `App\Services\AgentBus\AgentMessage` - Message envelope
- `App\Services\AgentBus\MessageRouter` - Routing logic
- `App\Services\AgentBus\SubscriberRegistry` - Subscription management
- `App\Jobs\ProcessAgentMessage` - Queue job for message delivery
- `App\Events\AgentMessageReceived` - Laravel event for message delivery

**Database Tables**:
- `agent_messages` - Message persistence and audit trail
- `agent_subscriptions` - Agent subscription registry
- Migration to add `priority` column to `jobs` table

**Configuration**:
- `config/agent-bus.php` - Bus configuration (timeouts, retries, limits)

## Rationale

### Why Database Queue Over Redis/RabbitMQ?

**Decision**: Use existing Laravel database queues

**Reasons**:
1. **Simplicity**: No additional infrastructure dependencies
2. **Persistence**: Messages automatically persisted to database
3. **Consistency**: Already using database queues for agent jobs
4. **Performance**: Acceptable for eventual consistency (2-5 second delivery)
5. **Operations**: Team already familiar with Laravel queue workers

**Tradeoffs**:
- Lower throughput than Redis/RabbitMQ (acceptable for current scale)
- No native pub/sub (implement via subscription registry)
- Polling overhead (mitigated by efficient SQL queries with indexes)

### Why Eventual Consistency?

**Decision**: Messages can take 2-5 seconds to deliver

**Reasons**:
1. Legal research is not real-time (humans involved in review)
2. Agent Plan → Act → Evaluate loops already take seconds/minutes
3. Simplifies implementation (no complex distributed transactions)
4. Matches current system behavior (agents are already async)

**Performance Requirements**:
- **Delivery Latency**: 2-5 seconds for NORMAL priority, <1 second for CRITICAL
- **Throughput**: Support 100+ messages/minute (current scale)
- **Reliability**: At-least-once delivery (messages persist until acknowledged)

### Why Priority Queue?

**Decision**: Extend Laravel queue with priority ordering

**Reasons**:
1. **Urgency Handling**: Some agent work is time-sensitive (court deadlines)
2. **Resource Management**: Critical work should bypass background tasks
3. **Fair Scheduling**: Prevent low-priority work from blocking important tasks

**Implementation**:
- Add `priority` integer column to `jobs` table (indexed)
- Modify queue worker to `ORDER BY priority DESC, available_at ASC`
- Agents specify priority when publishing messages

### Why Standard Message Envelope?

**Decision**: All messages follow same structure (headers + payload)

**Reasons**:
1. **Consistency**: Easy to add routing, monitoring, debugging
2. **Versioning**: Can evolve payload format without breaking routing
3. **Correlation**: Standard correlation_id for request/response pairing
4. **Observability**: Uniform structure simplifies logging and tracing

## Consequences

### Positive

1. **Enables Multi-Agent Workflows**: Agents can collaborate and coordinate
2. **Decouples Agents**: Agents don't need to know about each other's implementation
3. **Scalable**: Can add new agent types without changing bus infrastructure
4. **Observable**: All messages logged and traceable
5. **Testable**: Can mock message bus for agent unit tests
6. **Leverages Existing Stack**: No new infrastructure dependencies

### Negative

1. **Added Complexity**: New abstraction layer to maintain
2. **Performance Overhead**: Message serialization/deserialization, database I/O
3. **Eventual Consistency**: Agents must handle delayed/out-of-order messages
4. **Migration Effort**: Existing agents need updates to use bus

### Risks & Mitigations

**Risk**: Message queue becomes bottleneck at high load
**Mitigation**:
- Monitor queue depth and processing times
- Add database indexes on `priority` and `available_at`
- Can migrate to Redis/RabbitMQ later if needed (same message format)

**Risk**: Messages lost or duplicated
**Mitigation**:
- Persist all messages to `agent_messages` table (audit trail)
- Implement idempotency keys for critical operations
- At-least-once delivery guarantees (messages remain in queue until acknowledged)

**Risk**: Agents become coupled through message contracts
**Mitigation**:
- Use semantic versioning for message payload schemas
- Support multiple payload versions during migration periods
- Document all message types in `docs/agent-messages.md`

## Alternatives Considered

### Alternative 1: Direct Method Calls

**Description**: Agents call each other's methods directly

**Rejected Because**:
- Tight coupling between agents
- Can't run agents on different servers/containers
- No async coordination (blocking calls)
- Hard to add monitoring/logging

### Alternative 2: Laravel Events

**Description**: Use Laravel's event/listener system for agent communication

**Rejected Because**:
- Events are synchronous by default (or single queue for async)
- No priority handling
- No request/response correlation
- Events are designed for notifications, not work distribution

### Alternative 3: Redis Pub/Sub

**Description**: Use Redis pub/sub channels for agent messaging

**Rejected Because**:
- Adds infrastructure dependency
- Messages not persisted (lost if no subscriber online)
- No at-least-once delivery guarantees
- Team preference to use existing database queues

### Alternative 4: RabbitMQ/AMQP

**Description**: Use dedicated message broker

**Rejected Because**:
- Overkill for current scale (100+ msg/min)
- Adds operational complexity (another service to maintain)
- Team not familiar with RabbitMQ
- Can migrate to this later if needed (message format portable)

## Implementation Plan

### Phase 1: Core Infrastructure (Sprint 17.6)
- [ ] Design complete (ADR, sequence diagrams, performance requirements)
- [ ] Team approval of design

### Phase 2: Implementation (Sprint 17.7)
- [ ] Create `agent_messages` and `agent_subscriptions` tables
- [ ] Implement `AgentMessageBus`, `AgentMessage`, `MessageRouter`
- [ ] Implement `ProcessAgentMessage` queue job
- [ ] Add priority column to `jobs` table
- [ ] Unit tests for bus components

### Phase 3: Integration (Sprint 17.8)
- [ ] Update `AutonomousResearchAgent` to use bus
- [ ] Update specialist agents to subscribe to message types
- [ ] Integration tests for multi-agent scenarios
- [ ] Documentation and examples

### Phase 4: Monitoring (Sprint 17.9)
- [ ] Add message metrics (delivery time, queue depth, errors)
- [ ] Dashboard for message flow visualization
- [ ] Alerting for message failures

## Success Metrics

1. **Delivery Latency**: 95th percentile < 5 seconds for NORMAL priority
2. **Reliability**: 99.9% message delivery success rate
3. **Throughput**: Handle 100+ messages/minute without queue backlog
4. **Adoption**: 3+ agent types using bus for communication within 2 sprints

## References

- [AGENTS.md](../AGENTS.md) - Agent architecture documentation
- Laravel Queue Documentation: https://laravel.com/docs/11.x/queues
- Message-Oriented Middleware Patterns: https://www.enterpriseintegrationpatterns.com/
- Sprint 17.6 Requirements: See project management board

## Approval

- [ ] Technical Lead
- [ ] Product Owner
- [ ] Development Team

---

**Next Steps**: Create sequence diagrams for common flows (collaborative research, pipeline processing, broadcast coordination)
