# Comprehensive Sprint Plan - Documentation & Infrastructure

**Generated**: 2025-12-21
**Based On**: Documentation Verification Report from 8 parallel agents
**Total Tasks**: 60+ across 7 sprints

---

## Overview

| Sprint | Focus | Tasks | Duration | Priority | Dependencies |
|--------|-------|-------|----------|----------|--------------|
| Sprint 1 | Critical Doc Fixes | 5 | 1 day | CRITICAL | None |
| Sprint 2 | Production Scripts | 12 | 2 days | HIGH | None |
| Sprint 3 | Medium Priority Docs | 8 | 1-2 days | MEDIUM | Sprint 1 |
| Sprint 4 | Low Priority Docs | 9 | 1-2 days | LOW | Sprint 1 |
| Sprint 5 | API Implementation | 15+ | 5-7 days | HIGH | Sprint 2 |
| Sprint 6 | Artisan Commands | 8+ | 3-4 days | MEDIUM | Sprint 5 |
| Sprint 7 | Specialist Agents | 5+ | 5-7 days | LOW | Sprint 5,6 |

**Total Estimated Duration**: 18-25 days (3.5-5 weeks)

---

## Sprint 1: Critical Documentation Fixes
**Duration**: 1 day
**Goal**: Fix documentation claiming non-existent features exist
**Team**: 1 developer
**Priority**: CRITICAL - Prevents confusion and false expectations

### Tasks (in order):

#### 1. [ ] MILESTONE_F_SUMMARY.md - Remove False Claims
- **File**: `documentation/MILESTONE_F_SUMMARY.md`
- **Issue**: Claims docs exist that don't: `docs/MCP_TOOLS.md`, `docs/RAG_GUIDE.md`, `docs/MCP_ACCESS_GUIDE.md`
- **Action**: Remove references OR create placeholder files with "PLANNED" status
- **Time**: 30 minutes

#### 2. [ ] AGENT_API.md - Add Status Warning
- **File**: `documentation/AGENT_API.md`
- **Issue**: Documents comprehensive API at `/api/agent/research/*` that doesn't exist
- **Action**: Add prominent "STATUS: PLANNED - NOT YET IMPLEMENTED" header
- **Time**: 15 minutes

#### 3. [ ] INFRASTRUCTURE_INVENTORY.md - Rewrite MCP Tools Section
- **File**: `documentation/INFRASTRUCTURE_INVENTORY.md`
- **Issue**: Lists 6 tools, reality has 15+ with different names
- **Action**: Complete rewrite of MCP Tools section based on actual `app/Mcp/Tools/*.php` files
- **Commands**:
  ```bash
  ls app/Mcp/Tools/*.php
  # Enumerate all tools and their purposes
  ```
- **Time**: 1 hour

#### 4. [ ] GAME_CHANGER_ROADMAP.md - Archive or Update
- **File**: `documentation/GAME_CHANGER_ROADMAP.md`
- **Issue**: Proposes creating features that already exist (OrchestratorService, DynamicAgentSpawner, agent_communications table)
- **Action**: Move to archive as historical OR update to show completed items
- **Time**: 30 minutes

#### 5. [ ] AGENT_ANALYSIS.md - Clarify Implementation Status
- **File**: `documentation/AGENT_ANALYSIS.md`
- **Issue**: Describes specialist agents that don't exist (ResearchSpecialistAgent, PrecedentAnalystAgent, etc.)
- **Action**: Add clear sections: "Implemented Agents" vs "Planned Agents"
- **Time**: 45 minutes

### Completion Criteria:
- All 5 files updated
- No documentation claims non-existent features as existing
- Clear distinction between implemented vs planned
- Changes committed and pushed

### Verification:
```bash
# Verify no false claims remain
grep -r "docs/MCP_TOOLS.md" documentation/
grep -r "/api/agent/research" documentation/ | grep -v "PLANNED"
```

---

## Sprint 2: Production Infrastructure Scripts
**Duration**: 2 days
**Goal**: Create missing scripts required for production deployment
**Team**: 1-2 developers
**Priority**: HIGH - Required before go-live
**Dependencies**: None

### Background:
Multiple documentation files reference scripts that don't exist. These are required for:
- Production deployment
- Monitoring and backups
- Neo4j setup
- Database management

### Tasks:

#### Backup Scripts (go-live-checklist.md dependencies)

##### 1. [ ] Create backup-database.sh
- **Path**: `scripts/backup-database.sh`
- **Purpose**: Backup PostgreSQL database
- **Features**:
  - Timestamp-based backup files
  - Compression
  - Retention policy (30 days)
  - Backup to `/backups/database/`
- **Time**: 1 hour

##### 2. [ ] Create backup-neo4j.sh
- **Path**: `scripts/backup-neo4j.sh`
- **Purpose**: Backup Neo4j graph database
- **Features**:
  - Graph dump export
  - Timestamp naming
  - Compression
  - AuraDB support
- **Time**: 1.5 hours

##### 3. [ ] Create backup-app.sh
- **Path**: `scripts/backup-app.sh`
- **Purpose**: Full application backup (code, uploads, configs)
- **Features**:
  - Exclude vendor, node_modules
  - Include storage/, .env
  - Tar+gzip compression
- **Time**: 45 minutes

#### Monitoring Scripts (monitoring-setup.md dependencies)

##### 4. [ ] Create check-error-rate.sh
- **Path**: `scripts/check-error-rate.sh`
- **Purpose**: Monitor Laravel error logs and alert on spikes
- **Features**:
  - Parse laravel.log
  - Count errors in last hour
  - Alert if threshold exceeded
  - Output to stdout for monitoring systems
- **Time**: 1 hour

##### 5. [ ] Create logrotate configuration
- **Path**: `docs/server-config/logrotate/ai-legal-war-machine`
- **Purpose**: Automatic log rotation config
- **Features**:
  - Daily rotation
  - 30 day retention
  - Compression
  - Laravel log paths
- **Time**: 30 minutes

#### Neo4j Setup Scripts (NEO4J_SETUP_SUMMARY.md dependencies)

##### 6. [ ] Create configure-auradb.sh
- **Path**: `scripts/configure-auradb.sh`
- **Purpose**: Configure AuraDB connection
- **Features**:
  - Set environment variables
  - Test connection
  - Verify credentials
- **Time**: 1 hour

##### 7. [ ] Create install-neo4j-docker.sh
- **Path**: `scripts/install-neo4j-docker.sh`
- **Purpose**: Install Neo4j via Docker
- **Features**:
  - Docker-based Neo4j 5.x
  - Plugin installation (APOC, GDS)
  - Port configuration
- **Time**: 1.5 hours

##### 8. [ ] Create check-neo4j-requirements.sh
- **Path**: `scripts/check-neo4j-requirements.sh`
- **Purpose**: Verify Neo4j requirements before install
- **Features**:
  - Check Java version
  - Check disk space
  - Check memory
  - Check ports availability
- **Time**: 45 minutes

##### 9. [ ] Create install-aura-cli.sh
- **Path**: `scripts/install-aura-cli.sh`
- **Purpose**: Install Neo4j Aura CLI tools
- **Features**:
  - Download Aura CLI
  - Setup authentication
  - Verify installation
- **Time**: 45 minutes

#### Database Scripts (postgresql-setup-guide.md dependencies)

##### 10. [ ] Create scripts/setup-postgresql.sh
- **Path**: `scripts/setup-postgresql.sh`
- **Purpose**: Initialize PostgreSQL with required extensions
- **Features**:
  - Install pgvector extension
  - Create databases
  - Set permissions
  - Run migrations
- **Time**: 1 hour
- **Note**: Similar to `ensure-postgres-pgvector.sh` but more comprehensive

##### 11. [ ] Move setup-test-db.sh from archive
- **Current**: `scripts/archive/setup-postgresql.sh`
- **Action**: Restore to `scripts/setup-test-db.sh` or update references
- **Time**: 15 minutes

#### Neo4j Additional Scripts

##### 12. [ ] Create run-neo4j-tests.sh
- **Path**: `scripts/run-neo4j-tests.sh`
- **Purpose**: Run all Neo4j-related tests
- **Features**:
  - Run unit tests for graph services
  - Run integration tests
  - Generate coverage report
- **Time**: 1 hour

### Completion Criteria:
- All 12 scripts created and executable
- All scripts tested locally
- Documentation updated to match reality
- Scripts committed to repository
- go-live-checklist.md can be followed without errors

### Verification:
```bash
# Verify all scripts exist
ls -la scripts/backup-*.sh
ls -la scripts/check-*.sh
ls -la scripts/install-*.sh
ls -la scripts/configure-*.sh
ls -la scripts/setup-*.sh
ls -la scripts/run-neo4j-tests.sh

# Verify all are executable
find scripts/ -name "*.sh" -not -perm -u+x

# Run shellcheck on all
shellcheck scripts/*.sh
```

---

## Sprint 3: Medium Priority Documentation Updates
**Duration**: 1-2 days
**Goal**: Update documentation with moderate accuracy issues
**Team**: 1 developer
**Priority**: MEDIUM
**Dependencies**: Sprint 1 (consistent style)

### Tasks:

#### 1. [ ] NEO4J_SETUP_SUMMARY.md - Update Scripts Section
- **File**: `documentation/NEO4J_SETUP_SUMMARY.md`
- **Issue**: Claims 6 scripts exist, only `setup-neo4j.sh` actually exists
- **Action**: Update scripts section to list only existing scripts until Sprint 2 completes
- **Time**: 20 minutes

#### 2. [ ] AURADB_QUICKSTART.md - Fix Automated Setup
- **File**: `documentation/AURADB_QUICKSTART.md`
- **Issue**: References `install-aura-cli.sh` and `setup-auradb.sh` that don't exist
- **Action**: Add note "Scripts pending - see Sprint 2" or remove automated section
- **Time**: 15 minutes

#### 3. [ ] citation-network-ui-requirements.md - Update Status
- **File**: `documentation/citation-network-ui-requirements.md`
- **Issue**: States "UI Components: NOT YET IMPLEMENTED" but GraphViewer.php exists
- **Action**: Change status to "IMPLEMENTED" and reference GraphViewer.php
- **Time**: 10 minutes

#### 4. [ ] FORMREQUEST_VALIDATION_GUIDE.md - Update Count
- **File**: `documentation/FORMREQUEST_VALIDATION_GUIDE.md`
- **Issue**: Claims 34 FormRequest classes, reality is 75+
- **Action**: Update count to 75+, regenerate list if documented
- **Commands**:
  ```bash
  find app/Http/Requests -name "*Request.php" | wc -l
  ```
- **Time**: 30 minutes

#### 5. [ ] FACT_PATTERN_USAGE_EXAMPLES.md - Mark Proposed Endpoints
- **File**: `documentation/FACT_PATTERN_USAGE_EXAMPLES.md`
- **Issue**: Documents API endpoints that don't exist
- **Action**: Add "PROPOSED" or "PLANNED" labels to endpoints
- **Time**: 20 minutes

#### 6. [ ] CURRENT_STATUS.md - Update Date and References
- **File**: `documentation/CURRENT_STATUS.md`
- **Issue**: Last updated 17 days ago, references missing `sprints/README.md`
- **Action**: Update date to current, fix file references
- **Time**: 30 minutes

#### 7. [ ] TROUBLESHOOTING.md - Mark Optional Commands
- **File**: `documentation/TROUBLESHOOTING.md`
- **Issue**: References commands that don't exist (horizon:status, pail, odluke:ingest, queue:monitor)
- **Action**: Add "(Optional - if installed)" notes or remove invalid commands
- **Time**: 30 minutes

#### 8. [ ] NEO4J_INSTALLATION.md - Update References
- **File**: `documentation/NEO4J_INSTALLATION.md`
- **Issue**: Missing scripts and commands referenced
- **Action**: Update to reference only existing files or mark as planned
- **Time**: 30 minutes

### Completion Criteria:
- All 8 files updated
- No broken references (or marked as planned)
- Counts and status accurate
- Committed and pushed

### Verification:
```bash
# Verify no broken references
grep -r "scripts/" documentation/*.md | while read line; do
  script=$(echo "$line" | grep -oP 'scripts/[^"]*\.sh')
  if [ ! -f "$script" ]; then echo "Missing: $script"; fi
done
```

---

## Sprint 4: Low Priority Documentation Updates
**Duration**: 1-2 days
**Goal**: Fix minor documentation issues and outdated counts
**Team**: 1 developer
**Priority**: LOW - Cleanup and polish
**Dependencies**: Sprint 1 (consistent approach)

### Tasks:

#### 1. [ ] monitoring-setup.md - Note Missing Files
- **File**: `documentation/monitoring-setup.md`
- **Issue**: References missing logrotate config and check-error-rate.sh
- **Action**: Add note "See Sprint 2 for script creation"
- **Time**: 10 minutes

#### 2. [ ] postgresql-setup-guide.md - Fix Paths
- **File**: `documentation/postgresql-setup-guide.md`
- **Issue**: Wrong path for setup-test-db.sh (in archive)
- **Action**: Update path to archive location or note restoration plan
- **Time**: 10 minutes

#### 3. [ ] deployment-runbook.md - Fix Documentation References
- **File**: `documentation/deployment-runbook.md`
- **Issue**: References missing docs in `docs/` folder
- **Action**: Update paths to `documentation/` or remove broken links
- **Time**: 20 minutes

#### 4. [ ] CHANGELOG.md - Fix File References
- **File**: `documentation/CHANGELOG.md`
- **Issue**: References missing MIGRATION_AGENT_TOOLBOX.md, MCP_TOOLS.md
- **Action**: Remove references or mark as archived
- **Time**: 15 minutes

#### 5. [ ] GRAPH_EMBEDDINGS.md - Note Missing Commands
- **File**: `documentation/GRAPH_EMBEDDINGS.md`
- **Issue**: References graph:benchmark, neo4j:sync-decisions commands
- **Action**: Mark as "Planned commands - not yet implemented"
- **Time**: 10 minutes

#### 6. [ ] neo4j-test-coverage-audit.md - Update Count
- **File**: `documentation/neo4j-test-coverage-audit.md`
- **Issue**: Claims 47 test files, reality is 51+
- **Action**: Update count to reflect current state
- **Time**: 20 minutes

#### 7. [ ] TESTING_GUIDE.md - Fix Missing References
- **File**: `documentation/TESTING_GUIDE.md`
- **Issue**: References missing testing/README.md, testing/summaries/
- **Action**: Remove references or create placeholder files
- **Time**: 20 minutes

#### 8. [ ] NEO4J_PORTABLE_ANALYSIS.md - Fix Script References
- **File**: `documentation/NEO4J_PORTABLE_ANALYSIS.md`
- **Issue**: References missing scripts (README-AURADB.md, setup-auradb.sh, install-aura-cli.sh)
- **Action**: Update to reference existing files or mark as Sprint 2 deliverables
- **Time**: 15 minutes

#### 9. [ ] Update Outdated Progress Counts
- **Files**:
  - ODLUKE_SEARCH_AGENT.md (580 lines → 1168 lines)
  - NEO4J_SPRINT_PLAN.md (unchecked tasks → mark completed services)
  - BENCHMARKS.md (15 → 16 benchmarks)
- **Action**: Update all counts to reflect current reality
- **Time**: 30 minutes

### Completion Criteria:
- All 9 tasks completed
- No broken references without "planned" notation
- All counts current
- Committed and pushed

---

## Sprint 5: API Implementation (Development)
**Duration**: 5-7 days
**Goal**: Implement planned but missing API endpoints
**Team**: 2-3 developers
**Priority**: HIGH - Core functionality
**Dependencies**: Sprint 2 (infrastructure must be stable)

### Background:
Multiple API endpoint sets are documented but not implemented:
- Agent Research API (`/api/agent/research/*`)
- MCP Tools API (`/api/mcp/*`)
- Fact Pattern API (`/api/fact-patterns/*`)

### Tasks:

#### Agent Research API (AGENT_API.md)

##### 1. [ ] POST /api/agent/research/execute
- **Controller**: `app/Http/Controllers/Api/AgentResearchController.php`
- **Purpose**: Execute research request with specific agent
- **Request**: `ExecuteResearchRequest`
- **Response**: Research task ID and initial status
- **Time**: 4 hours (TDD)

##### 2. [ ] GET /api/agent/research/{id}
- **Purpose**: Get research task status and results
- **Response**: Task status, progress, results
- **Time**: 2 hours (TDD)

##### 3. [ ] POST /api/agent/research/{id}/cancel
- **Purpose**: Cancel running research task
- **Time**: 2 hours (TDD)

##### 4. [ ] GET /api/agent/research/{id}/stream
- **Purpose**: Stream real-time research progress
- **Features**: SSE (Server-Sent Events)
- **Time**: 4 hours (TDD)

##### 5. [ ] POST /api/agent/research/batch
- **Purpose**: Execute multiple research tasks
- **Time**: 3 hours (TDD)

##### 6. [ ] GET /api/agent/research
- **Purpose**: List all research tasks for user
- **Features**: Pagination, filtering
- **Time**: 3 hours (TDD)

#### MCP Tools API (MILESTONE_F_SUMMARY.md)

##### 7. [ ] GET /api/mcp/tools
- **Purpose**: List all available MCP tools
- **Response**: Tool names, descriptions, schemas
- **Time**: 2 hours (TDD)

##### 8. [ ] POST /api/mcp/tools/{tool}/execute
- **Purpose**: Execute specific MCP tool
- **Request**: Tool-specific parameters
- **Time**: 4 hours (TDD)

##### 9. [ ] GET /api/mcp/tools/{tool}/schema
- **Purpose**: Get tool's JSON schema
- **Time**: 1 hour (TDD)

#### Fact Pattern API (FACT_PATTERN_USAGE_EXAMPLES.md)

##### 10. [ ] POST /api/fact-patterns/extract
- **Purpose**: Extract fact patterns from document
- **Request**: Document content or ID
- **Response**: Extracted fact patterns
- **Time**: 4 hours (TDD)

##### 11. [ ] POST /api/fact-patterns/batch-extract
- **Purpose**: Batch extract from multiple documents
- **Time**: 3 hours (TDD)

##### 12. [ ] POST /api/fact-patterns/{id}/find-similar
- **Purpose**: Find similar fact patterns using embeddings
- **Features**: Vector similarity search
- **Time**: 4 hours (TDD)

##### 13. [ ] GET /api/fact-patterns
- **Purpose**: List fact patterns with filtering
- **Time**: 2 hours (TDD)

##### 14. [ ] GET /api/fact-patterns/{id}
- **Purpose**: Get specific fact pattern details
- **Time**: 1 hour (TDD)

##### 15. [ ] DELETE /api/fact-patterns/{id}
- **Purpose**: Delete fact pattern
- **Time**: 1 hour (TDD)

### Additional Requirements:

##### 16. [ ] API Authentication & Rate Limiting
- **Features**:
  - Laravel Sanctum tokens
  - Rate limiting per endpoint
  - API key management
- **Time**: 4 hours

##### 17. [ ] API Documentation (OpenAPI/Swagger)
- **Tools**: L5-Swagger or Scramble
- **Time**: 6 hours

### Completion Criteria:
- All endpoints implemented
- Full test coverage (Unit + Feature)
- API documentation generated
- Postman/OpenAPI collection
- All tests passing
- Documentation updated to remove "PLANNED" status

### Verification:
```bash
# Run API tests
php artisan test --group=api

# Check route list
php artisan route:list --path=api

# Generate API docs
php artisan l5-swagger:generate
```

---

## Sprint 6: Artisan Commands Implementation (Development)
**Duration**: 3-4 days
**Goal**: Implement missing artisan commands referenced in docs
**Team**: 1-2 developers
**Priority**: MEDIUM - Quality of life improvements
**Dependencies**: Sprint 5 (may share services)

### Background:
Documentation references multiple artisan commands that don't exist:
- Graph/Neo4j commands
- Queue monitoring
- Odluke ingestion
- Specialized operations

### Tasks:

#### Neo4j Commands

##### 1. [ ] php artisan neo4j:health-check
- **Path**: `app/Console/Commands/Neo4jHealthCheck.php`
- **Purpose**: Check Neo4j connection and database health
- **Features**:
  - Test connection
  - Check index status
  - Report constraint violations
  - Check disk space
- **Time**: 2 hours (TDD)

##### 2. [ ] php artisan neo4j:sync-decisions
- **Path**: `app/Console/Commands/SyncDecisionsToNeo4j.php`
- **Purpose**: Sync court decisions from PostgreSQL to Neo4j
- **Features**:
  - Batch processing
  - Progress bar
  - Error handling
  - Dry-run mode
- **Time**: 4 hours (TDD)

##### 3. [ ] php artisan graph:benchmark
- **Path**: `app/Console/Commands/GraphBenchmark.php`
- **Purpose**: Run performance benchmarks on graph queries
- **Features**:
  - Multiple query types
  - Timing statistics
  - Export results
- **Time**: 3 hours (TDD)

#### Queue Commands

##### 4. [ ] php artisan queue:monitor
- **Path**: `app/Console/Commands/QueueMonitor.php`
- **Purpose**: Real-time queue monitoring
- **Features**:
  - Active jobs display
  - Failed jobs count
  - Queue depth per queue
  - Refresh every N seconds
- **Time**: 3 hours (TDD)

#### Odluke Commands

##### 5. [ ] php artisan odluke:ingest
- **Path**: `app/Console/Commands/OdlukeIngest.php`
- **Purpose**: Ingest decisions from Odluke API
- **Features**:
  - Date range filtering
  - Batch processing
  - Error logging
  - Rate limiting
- **Time**: 4 hours (TDD)

#### Optional Commands (If Installed)

##### 6. [ ] Document Horizon requirement
- **Action**: Add to TROUBLESHOOTING.md: "Install Laravel Horizon for horizon:status"
- **Time**: 30 minutes

##### 7. [ ] Document Pail requirement
- **Action**: Add to TROUBLESHOOTING.md: "Install Laravel Pail for pail command"
- **Time**: 30 minutes

### Additional Tasks:

##### 8. [ ] Update command listings in documentation
- **Files**: TROUBLESHOOTING.md, deployment-runbook.md, operations-manual.md
- **Action**: Add new commands to all relevant docs
- **Time**: 1 hour

### Completion Criteria:
- All 5 core commands implemented
- Full test coverage
- Commands listed in `php artisan list`
- Documentation updated
- All tests passing

### Verification:
```bash
# Verify commands exist
php artisan list | grep -E "(neo4j|graph|queue|odluke)"

# Run command tests
php artisan test --group=commands

# Test each command
php artisan neo4j:health-check --help
php artisan queue:monitor --help
```

---

## Sprint 7: Specialist Agent Implementation (Development)
**Duration**: 5-7 days
**Goal**: Implement specialist agents described in AGENT_ANALYSIS.md
**Team**: 2-3 developers
**Priority**: LOW - Advanced features
**Dependencies**: Sprint 5, Sprint 6 (APIs and commands needed)

### Background:
AGENT_ANALYSIS.md describes specialist agents that don't exist:
- ResearchSpecialistAgent
- PrecedentAnalystAgent
- StrategySpecialistAgent
- RiskAnalystAgent
- OdlukeSearchAgent (partially exists)

### Tasks:

#### 1. [ ] ResearchSpecialistAgent
- **Path**: `app/Services/Agents/ResearchSpecialistAgent.php`
- **Purpose**: Specialized legal research agent
- **Features**:
  - Deep case law research
  - Citation network traversal
  - Relevance scoring
- **Time**: 8 hours (TDD)
- **Tests**: `tests/Unit/Services/Agents/ResearchSpecialistAgentTest.php`

#### 2. [ ] PrecedentAnalystAgent
- **Path**: `app/Services/Agents/PrecedentAnalystAgent.php`
- **Purpose**: Analyze precedent strength and applicability
- **Features**:
  - Precedent hierarchy analysis
  - Binding vs persuasive classification
  - Overruling detection
- **Time**: 10 hours (TDD)

#### 3. [ ] StrategySpecialistAgent
- **Path**: `app/Services/Agents/StrategySpecialistAgent.php`
- **Purpose**: Legal strategy recommendation
- **Features**:
  - Argument strength analysis
  - Counter-argument generation
  - Strategy recommendations
- **Time**: 10 hours (TDD)

#### 4. [ ] RiskAnalystAgent
- **Path**: `app/Services/Agents/RiskAnalystAgent.php`
- **Purpose**: Case risk assessment
- **Features**:
  - Success probability estimation
  - Risk factor identification
  - Mitigation suggestions
- **Time**: 8 hours (TDD)

#### 5. [ ] Complete OdlukeSearchAgent
- **Current**: Partial implementation exists (1168 lines)
- **Action**: Fill in missing features, full test coverage
- **Time**: 6 hours (TDD)

### Integration Tasks:

#### 6. [ ] Agent Registry/Factory
- **Path**: `app/Services/Agents/AgentRegistry.php`
- **Purpose**: Centralized agent registration and instantiation
- **Features**:
  - Agent discovery
  - Dependency injection
  - Agent versioning
- **Time**: 4 hours (TDD)

#### 7. [ ] Agent API Integration
- **Action**: Connect agents to `/api/agent/research/*` endpoints
- **Time**: 4 hours

#### 8. [ ] Agent Monitoring Dashboard
- **Path**: `app/Livewire/AgentMonitoringDashboard.php`
- **Purpose**: Real-time agent execution monitoring
- **Features**:
  - Active agents display
  - Performance metrics
  - Error tracking
- **Time**: 6 hours (TDD)

### Documentation Tasks:

#### 9. [ ] Update AGENT_ANALYSIS.md
- **Action**: Move all agents from "Planned" to "Implemented"
- **Time**: 1 hour

#### 10. [ ] Create Agent Usage Guide
- **Path**: `documentation/AGENT_USAGE_GUIDE.md`
- **Content**:
  - Each agent's purpose
  - API usage examples
  - Best practices
- **Time**: 3 hours

### Completion Criteria:
- All 5 specialist agents implemented
- Full test coverage
- API integration complete
- Monitoring dashboard functional
- Documentation updated
- All tests passing

### Verification:
```bash
# Run agent tests
php artisan test --group=agents

# Verify agent registration
php artisan tinker
>>> app(App\Services\Agents\AgentRegistry::class)->listAgents()

# Test API endpoints
curl -X POST http://localhost:8000/api/agent/research/execute \
  -H "Content-Type: application/json" \
  -d '{"agent":"ResearchSpecialistAgent","query":"..."}'
```

---

## Backlog (Unprioritized)

### Documentation Files to Create
These are referenced but don't exist. Create when needed:

1. `docs/MCP_TOOLS.md` - MCP tools comprehensive guide
2. `docs/RAG_GUIDE.md` - RAG implementation guide
3. `docs/MCP_ACCESS_GUIDE.md` - MCP access patterns
4. `docs/GRAPH_SCHEMA.md` - Neo4j schema documentation
5. `testing/README.md` - Testing documentation index
6. `testing/summaries/` - Test result summaries
7. `README-AURADB.md` - AuraDB-specific setup

### Additional Scripts
Nice to have but not critical:

1. `scripts/install-neo4j.sh` - Native Neo4j installation (vs Docker)
2. `scripts/setup-auradb.sh` - Full AuraDB setup automation
3. `scripts/performance-test.sh` - Run performance test suite
4. `scripts/lint-all.sh` - Run all linters (PHP, JS, etc.)

### Additional Commands
Quality of life improvements:

1. `php artisan app:reset-demo` - Reset to demo state
2. `php artisan graph:export` - Export graph to GraphML
3. `php artisan graph:import` - Import graph from file
4. `php artisan metrics:report` - Generate metrics report

---

## Implementation Recommendations

### Week 1: Foundation
- **Sprint 1**: Day 1 - Critical doc fixes (morning)
- **Sprint 2**: Day 1-3 - Production scripts (afternoon Day 1, full Day 2-3)
- **Goal**: Documentation accurate, infrastructure scripts ready

### Week 2: Documentation Cleanup
- **Sprint 3**: Day 4-5 - Medium priority docs
- **Sprint 4**: Day 6-7 - Low priority docs
- **Goal**: All documentation aligned with reality

### Week 3-4: API Development
- **Sprint 5**: Day 8-14 - API implementation
- **Goal**: All documented APIs functional

### Week 4-5: Commands & Agents
- **Sprint 6**: Day 15-18 - Artisan commands
- **Sprint 7**: Day 19-25 - Specialist agents (if time permits)
- **Goal**: Full feature parity with documentation

### Critical Path:
```
Sprint 1 → Sprint 2 → Sprint 3/4 (parallel) → Sprint 5 → Sprint 6 → Sprint 7
```

### Parallelization Opportunities:
- Sprint 3 and Sprint 4 can run in parallel (different files)
- Sprint 6 can start once Sprint 5 APIs are partially complete
- Sprint 7 requires Sprint 5 and 6 completion

---

## Success Metrics

### Sprint 1 Success:
- Zero documentation files claiming features don't exist
- Clear "PLANNED" vs "IMPLEMENTED" distinction

### Sprint 2 Success:
- `go-live-checklist.md` can be followed without missing scripts
- All backup scripts functional and tested

### Sprint 3 & 4 Success:
- Zero broken file/script references in documentation
- All counts current and accurate

### Sprint 5 Success:
- All documented API endpoints functional
- 100% test coverage on new APIs
- Postman collection available

### Sprint 6 Success:
- All referenced artisan commands exist
- Commands appear in `php artisan list`

### Sprint 7 Success:
- All described agents implemented
- Agent API fully functional

### Overall Success:
- Documentation verification report shows 100% accuracy
- No "MISSING" or "NOT FOUND" in any docs
- Production-ready state achieved

---

## Risk Management

### High Risk:
1. **Sprint 5 duration**: API implementation could take longer
   - **Mitigation**: Start with MVP, iterate

2. **Sprint 7 complexity**: AI agents are complex
   - **Mitigation**: Start with simplest agent, reuse patterns

### Medium Risk:
1. **Script testing**: Scripts need real environments
   - **Mitigation**: Use staging environment

2. **API breaking changes**: Might affect existing code
   - **Mitigation**: Version APIs, deprecation notices

### Low Risk:
1. **Documentation updates**: Low technical risk
   - **Mitigation**: Peer review

---

## Dependencies & Prerequisites

### Before Starting:
- [x] Documentation verification report complete
- [ ] Development environment ready
- [ ] Staging environment available
- [ ] Git branch strategy agreed

### Sprint-Specific:
- **Sprint 2**: Access to server for script testing
- **Sprint 5**: API design approved
- **Sprint 6**: Service layer refactored if needed
- **Sprint 7**: LLM API keys and quotas sufficient

---

## Notes

### Quick Wins (Can Start Immediately):
1. Sprint 1, Task 2: Add "PLANNED" header (15 min)
2. Sprint 3, Task 3: Update UI status (10 min)
3. Sprint 3, Task 4: Update FormRequest count (30 min)
4. Sprint 4, Task 5: Note missing commands (10 min)

### High-Impact Tasks:
1. Sprint 2: Backup scripts - Required for production
2. Sprint 5: Agent Research API - Core functionality
3. Sprint 6: neo4j:health-check - Operational necessity

### Can Be Deferred:
1. Sprint 7: Specialist agents - Advanced features
2. Backlog items: All nice-to-have

---

## Conclusion

This sprint plan provides a clear, actionable roadmap to:
1. **Fix critical documentation issues** (Sprint 1)
2. **Create production-required infrastructure** (Sprint 2)
3. **Clean up all documentation** (Sprint 3-4)
4. **Implement missing APIs** (Sprint 5)
5. **Add operational commands** (Sprint 6)
6. **Complete specialist agents** (Sprint 7)

**Total effort**: 18-25 developer-days across 7 sprints.

**Recommended approach**: Execute Sprints 1-2 immediately (high priority), then evaluate Sprint 5 vs Sprint 3-4 based on production timeline.
