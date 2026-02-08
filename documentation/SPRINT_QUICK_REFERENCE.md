# Sprint Quick Reference Guide

**Generated**: 2025-12-21
**Full Plan**: See `COMPREHENSIVE_SPRINT_PLAN.md`

---

## TL;DR - Start Here

### Immediate Actions (Day 1):
1. **Morning**: Sprint 1 - Fix 5 critical docs (3 hours)
2. **Afternoon**: Start Sprint 2 - Create backup scripts (5 hours)

### This Week:
- Complete Sprint 1 (Critical docs)
- Complete Sprint 2 (Production scripts)
- Start Sprint 3 (Medium priority docs)

### Next 2-3 Weeks:
- Finish Sprint 3-4 (Documentation cleanup)
- Begin Sprint 5 (API implementation)

---

## Sprint Overview Table

| Sprint | What | When | Who | Why |
|--------|------|------|-----|-----|
| 1 | Fix 5 critical docs | Day 1 AM | 1 dev | Prevents confusion |
| 2 | Create 12 prod scripts | Day 1-3 | 1-2 devs | Required for go-live |
| 3 | Fix 8 medium docs | Day 4-5 | 1 dev | Accuracy |
| 4 | Fix 9 low priority docs | Day 6-7 | 1 dev | Polish |
| 5 | Build 15+ API endpoints | Day 8-14 | 2-3 devs | Core features |
| 6 | Create 5+ commands | Day 15-18 | 1-2 devs | Operations |
| 7 | Build 5 specialist agents | Day 19-25 | 2-3 devs | Advanced features |

---

## Sprint 1: Critical Docs (3 hours)

```bash
# Files to edit (in order):
1. documentation/MILESTONE_F_SUMMARY.md          # 30 min
2. documentation/AGENT_API.md                    # 15 min
3. documentation/INFRASTRUCTURE_INVENTORY.md     # 60 min
4. documentation/GAME_CHANGER_ROADMAP.md         # 30 min
5. documentation/AGENT_ANALYSIS.md               # 45 min
```

**Key Action**: Add "PLANNED" headers, remove false claims

---

## Sprint 2: Production Scripts (2 days)

```bash
# Scripts to create:
scripts/backup-database.sh              # PostgreSQL backups
scripts/backup-neo4j.sh                 # Neo4j backups
scripts/backup-app.sh                   # Full app backup
scripts/check-error-rate.sh             # Error monitoring
scripts/setup-neo4j.sh                  # Neo4j installation and setup
scripts/setup-postgresql.sh             # PostgreSQL init
scripts/run-neo4j-tests.sh              # Test runner

# Config files:
docs/server-config/logrotate/ai-legal-war-machine  # Log rotation
```

**Key Action**: Make production-ready infrastructure

---

## Sprint 3-4: Doc Cleanup (2-3 days)

**Medium Priority** (8 files):
- NEO4J_SETUP_SUMMARY.md
- AURADB_QUICKSTART.md
- citation-network-ui-requirements.md
- FORMREQUEST_VALIDATION_GUIDE.md
- FACT_PATTERN_USAGE_EXAMPLES.md
- CURRENT_STATUS.md
- TROUBLESHOOTING.md
- NEO4J_INSTALLATION.md

**Low Priority** (9 files):
- monitoring-setup.md
- postgresql-setup-guide.md
- deployment-runbook.md
- CHANGELOG.md
- GRAPH_EMBEDDINGS.md
- neo4j-test-coverage-audit.md
- TESTING_GUIDE.md
- NEO4J_PORTABLE_ANALYSIS.md
- Plus 3 outdated count updates

**Key Action**: Fix all broken references, update counts

---

## Sprint 5: API Endpoints (5-7 days)

### Agent Research API (6 endpoints):
```
POST   /api/agent/research/execute
GET    /api/agent/research/{id}
POST   /api/agent/research/{id}/cancel
GET    /api/agent/research/{id}/stream
POST   /api/agent/research/batch
GET    /api/agent/research
```

### MCP Tools API (3 endpoints):
```
GET    /api/mcp/tools
POST   /api/mcp/tools/{tool}/execute
GET    /api/mcp/tools/{tool}/schema
```

### Fact Pattern API (6 endpoints):
```
POST   /api/fact-patterns/extract
POST   /api/fact-patterns/batch-extract
POST   /api/fact-patterns/{id}/find-similar
GET    /api/fact-patterns
GET    /api/fact-patterns/{id}
DELETE /api/fact-patterns/{id}
```

**Plus**: Authentication, rate limiting, OpenAPI docs

**Key Action**: Full TDD, 100% test coverage

---

## Sprint 6: Artisan Commands (3-4 days)

```bash
# Commands to create:
php artisan neo4j:health-check      # Neo4j diagnostics
php artisan neo4j:sync-decisions    # Sync to graph
php artisan graph:benchmark         # Performance testing
php artisan queue:monitor           # Queue monitoring
php artisan odluke:ingest           # Import decisions
```

**Key Action**: Operational tooling

---

## Sprint 7: Specialist Agents (5-7 days)

```php
// Agents to implement:
app/Services/Agents/ResearchSpecialistAgent.php
app/Services/Agents/PrecedentAnalystAgent.php
app/Services/Agents/StrategySpecialistAgent.php
app/Services/Agents/RiskAnalystAgent.php

// Complete existing:
app/Services/Agents/OdlukeSearchAgent.php
```

**Plus**: Agent registry, monitoring dashboard, docs

**Key Action**: AI-powered legal analysis

---

## Quick Wins (< 30 minutes each)

Start with these for immediate impact:

1. **AGENT_API.md**: Add "STATUS: PLANNED" header (15 min)
2. **citation-network-ui-requirements.md**: Change to "IMPLEMENTED" (10 min)
3. **FORMREQUEST_VALIDATION_GUIDE.md**: Update count 34→75+ (30 min)
4. **GRAPH_EMBEDDINGS.md**: Note missing commands as planned (10 min)

Total: ~65 minutes of high-impact fixes

---

## Dependencies

```
Sprint 1 ────────────┬─→ Sprint 3
                     │
Sprint 2 ────────────┴─→ Sprint 4
                     │
                     └─→ Sprint 5 ─→ Sprint 6 ─┬─→ Sprint 7
                                                │
                                                └──────────┘
```

- **Sprint 1 & 2**: Can run in parallel
- **Sprint 3 & 4**: Can run in parallel, but need Sprint 1 style
- **Sprint 5**: Needs Sprint 2 infrastructure
- **Sprint 6**: Can start once Sprint 5 APIs partially done
- **Sprint 7**: Needs Sprint 5 & 6 complete

---

## File Counts

- **Documentation files to update**: 22
- **Scripts to create**: 12
- **API endpoints to build**: 15+
- **Artisan commands to create**: 5
- **Specialist agents to build**: 5
- **Total tasks**: 60+

---

## Effort Estimates

| Sprint | Days | Developer-Days |
|--------|------|----------------|
| 1 | 0.5 | 0.5 |
| 2 | 2 | 2-4 |
| 3 | 1-2 | 1-2 |
| 4 | 1-2 | 1-2 |
| 5 | 5-7 | 10-21 |
| 6 | 3-4 | 3-8 |
| 7 | 5-7 | 10-21 |
| **Total** | **18-25** | **27.5-58.5** |

*Range depends on team size and parallelization*

---

## Priority Levels

### CRITICAL (Must Have):
- Sprint 1: Critical doc fixes
- Sprint 2: Production scripts (backup, monitoring)

### HIGH (Should Have):
- Sprint 3: Medium priority docs
- Sprint 5: API implementation

### MEDIUM (Nice to Have):
- Sprint 4: Low priority docs
- Sprint 6: Artisan commands

### LOW (Future):
- Sprint 7: Specialist agents
- Backlog items

---

## Success Criteria

### Sprint 1:
- [ ] No docs claim non-existent features
- [ ] Clear PLANNED vs IMPLEMENTED distinction

### Sprint 2:
- [ ] All backup scripts work
- [ ] go-live-checklist.md can be followed
- [ ] Monitoring scripts functional

### Sprint 3-4:
- [ ] Zero broken references
- [ ] All counts accurate
- [ ] All paths correct

### Sprint 5:
- [ ] All endpoints return 200/201 (not 404)
- [ ] 100% test coverage
- [ ] OpenAPI docs generated

### Sprint 6:
- [ ] All commands in `php artisan list`
- [ ] Commands have --help text
- [ ] Full test coverage

### Sprint 7:
- [ ] All agents registered
- [ ] Agent API integration complete
- [ ] Monitoring dashboard live

---

## Commands to Run

### Verify Documentation:
```bash
# Check for broken script references
grep -r "scripts/" documentation/*.md | while read line; do
  script=$(echo "$line" | grep -oP 'scripts/[^"]*\.sh')
  if [ ! -f "$script" ]; then echo "Missing: $script"; fi
done

# Check for broken command references
grep -r "php artisan" documentation/*.md | grep -oP "php artisan [a-z:]+" | sort -u
```

### Verify Scripts (Sprint 2):
```bash
# All scripts exist
ls scripts/backup-*.sh scripts/check-*.sh scripts/install-*.sh

# All scripts executable
find scripts -name "*.sh" -not -perm -u+x

# Shellcheck all
shellcheck scripts/*.sh
```

### Verify APIs (Sprint 5):
```bash
# All routes registered
php artisan route:list --path=api

# Run API tests
php artisan test --group=api

# Generate OpenAPI docs
php artisan l5-swagger:generate
```

### Verify Commands (Sprint 6):
```bash
# List all commands
php artisan list | grep -E "(neo4j|graph|queue|odluke)"

# Run command tests
php artisan test --group=commands
```

### Verify Agents (Sprint 7):
```bash
# Run agent tests
php artisan test --group=agents

# List registered agents
php artisan tinker
>>> app(App\Services\Agents\AgentRegistry::class)->listAgents()
```

---

## Next Steps

### Today:
1. Review full `COMPREHENSIVE_SPRINT_PLAN.md`
2. Create git branch: `claude/doc-sprint-plan-{session-id}`
3. Start Sprint 1 (3 hours to complete)

### This Week:
1. Complete Sprint 1 (Day 1)
2. Complete Sprint 2 (Day 1-3)
3. Review progress, adjust priorities

### Ongoing:
1. Daily standup: Which sprint? Any blockers?
2. Update this doc as sprints complete
3. Re-verify after each sprint

---

## Questions Before Starting?

1. **Team size**: How many developers available?
2. **Timeline**: Production deadline?
3. **Priorities**: API first or docs first?
4. **Resources**: Staging environment ready?

---

**Full details**: `documentation/COMPREHENSIVE_SPRINT_PLAN.md`
**Verification report**: `documentation/DOCUMENTATION_VERIFICATION_REPORT.md`
