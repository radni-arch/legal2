# MEDIUM Priority Documentation Update Tasks

**Created**: 2025-12-21
**Total Files**: 8
**Estimated Effort**: 2-3 hours

---

## Task Group 1: NEO4J_SETUP_SUMMARY.md

### Task 1.1: Remove References to Non-Existent Scripts
- **File**: `documentation/NEO4J_SETUP_SUMMARY.md`
- **Lines**: 30-58 (Scripts section)
- **Issue**: Claims 6 scripts exist; only 1 actually exists
- **Reality**: Only `setup-neo4j.sh` exists in `/scripts/`

**Action**: Replace lines 30-58 with:
```markdown
1. **setup-neo4j.sh** ✅
   - Comprehensive Neo4j setup script
   - Handles environment detection
   - Configures .env automatically
   - Tests connection

Note: Other installation methods require manual setup. See [NEO4J_INSTALLATION.md](NEO4J_INSTALLATION.md) for details.
```

**Verification**:
```bash
grep -n "configure-auradb.sh\|install-neo4j.sh\|install-neo4j-docker.sh\|check-neo4j-requirements.sh\|install-aura-cli.sh" documentation/NEO4J_SETUP_SUMMARY.md
# Should return no matches after update
```

---

## Task Group 2: AURADB_QUICKSTART.md

### Task 2.1: Update "Automated Setup" Section
- **File**: `documentation/AURADB_QUICKSTART.md`
- **Lines**: 16-53
- **Issue**: References scripts that don't exist

**Action**: Replace lines 23-31 with:
```markdown
### Step 1: Prerequisites

Neo4j AuraDB is a cloud-based solution. You'll need:
1. A Neo4j account (free signup at https://neo4j.com)
2. Connection credentials from the AuraDB console

Note: There is no local installation script for AuraDB CLI. Set up is manual via the web console.
```

**Action**: Replace line 44 with:
```markdown
Manual configuration is required. Follow the steps below.
```

**Verification**:
```bash
grep -n "install-aura-cli.sh\|setup-auradb.sh" documentation/AURADB_QUICKSTART.md
# Should return no matches
```

---

## Task Group 3: citation-network-ui-requirements.md

### Task 3.1: Update UI Implementation Status
- **File**: `documentation/citation-network-ui-requirements.md`
- **Lines**: 11
- **Issue**: States "UI Components: NOT YET IMPLEMENTED"
- **Reality**: UI IS implemented in `app/Http/Livewire/GraphViewer.php`

**Action**: Replace line 11 with:
```markdown
- **UI Components**: ✅ PARTIALLY IMPLEMENTED (methods exist in GraphViewer.php)
```

### Task 3.2: Add Implementation Status Section
- **File**: `documentation/citation-network-ui-requirements.md`
- **After Line**: 13
- **Action**: Insert new section:

```markdown
## Current Implementation Status

**Implemented Methods** (in `app/Http/Livewire/GraphViewer.php`):
- ✅ `openCitationAnalysis()` - Opens citation analysis panel
- ✅ `runCitationAnalysis()` - Executes citation analysis

**Remaining Work**:
- UI templates for citation results display
- Frontend interaction components
- Visualization rendering
```

**Verification**:
```bash
grep -A 2 "UI Components:" documentation/citation-network-ui-requirements.md | grep "PARTIALLY IMPLEMENTED"
```

---

## Task Group 4: FORMREQUEST_VALIDATION_GUIDE.md

### Task 4.1: Update FormRequest Count
- **File**: `documentation/FORMREQUEST_VALIDATION_GUIDE.md`
- **Lines**: 15-17
- **Issue**: Claims "34 classes" when there are actually 75+

**Action**: Replace lines 15-17 with:
```markdown
**Total FormRequests:** 75 classes
**Total Tests:** 229+ tests with 374+ assertions
**Validation Coverage:** 85%+
```

### Task 4.2: Update Quick Reference Table
- **File**: `documentation/FORMREQUEST_VALIDATION_GUIDE.md`
- **Line**: 737
- **Issue**: Total shows 34 instead of 75

**Action**: Replace line 737 with:
```markdown
| **TOTAL** | **75** | **229+** | **85%+ validation coverage** |
```

**Verification**:
```bash
grep -n "Total FormRequests" documentation/FORMREQUEST_VALIDATION_GUIDE.md
# Should show 75, not 34
```

---

## Task Group 5: FACT_PATTERN_USAGE_EXAMPLES.md

### Task 5.1: Remove Non-Existent API Endpoint Documentation
- **File**: `documentation/FACT_PATTERN_USAGE_EXAMPLES.md`
- **Lines**: 16-126
- **Issue**: Documents API endpoints that don't exist

**Action**: Add warning banner at top of "API Usage Examples" section (after line 13):
```markdown
> **⚠️ IMPORTANT**: The API endpoints documented below are **PLANNED** but **NOT YET IMPLEMENTED**.
> These examples show the intended API design. For current capabilities, use the Service Layer directly (see "Service Integration Examples" below).
```

### Task 5.2: Mark Endpoints as Planned
- **File**: `documentation/FACT_PATTERN_USAGE_EXAMPLES.md`
- **Lines**: 18, 102, 122

**Action**: Update endpoint headers:
- Line 18: Change to `**Endpoint (Planned):** POST /api/fact-patterns/extract`
- Line 102: Change to `**Endpoint (Planned):** POST /api/fact-patterns/batch-extract`
- Line 122: Change to `**Endpoint (Planned):** POST /api/fact-patterns/{id}/find-similar`

**Verification**:
```bash
# Check if warning is present
grep -n "NOT YET IMPLEMENTED" documentation/FACT_PATTERN_USAGE_EXAMPLES.md
# Check routes
php artisan route:list | grep "fact-patterns"
# Should return no matches if endpoints don't exist
```

---

## Task Group 6: CURRENT_STATUS.md

### Task 6.1: Update Last Updated Date
- **File**: `documentation/CURRENT_STATUS.md`
- **Line**: 3
- **Issue**: Shows stale date (2025-12-04)

**Action**: Replace line 3 with:
```markdown
**Last Updated**: 2025-12-21
```

### Task 6.2: Fix Broken Documentation Link
- **File**: `documentation/CURRENT_STATUS.md`
- **Line**: 121
- **Issue**: References `sprints/README.md` which doesn't exist

**Action**: Replace line 121 with:
```markdown
| [plans/](plans/) | Production sprint plans (9-12) |
```

Remove the sprints/README.md reference entirely.

**Verification**:
```bash
test -f documentation/sprints/README.md && echo "EXISTS" || echo "MISSING"
# Should show MISSING
grep "sprints/README.md" documentation/CURRENT_STATUS.md
# Should return no matches after update
```

---

## Task Group 7: TROUBLESHOOTING.md

### Task 7.1: Remove References to Non-Existent Horizon Command
- **File**: `documentation/TROUBLESHOOTING.md`
- **Line**: 40
- **Issue**: References `php artisan horizon:status` (Horizon not installed)

**Action**: Remove line 40 entirely. Replace with:
```bash
# Check queue workers
ps aux | grep "queue:work"
```

### Task 7.2: Remove References to Pail Command
- **File**: `documentation/TROUBLESHOOTING.md`
- **Lines**: 47, 217, 892, 1446
- **Issue**: References `php artisan pail` (Pail package not installed)

**Action**: Replace all instances of `php artisan pail` with `tail -f storage/logs/laravel.log`

**Specific changes**:
- Line 47: Change to `tail -f storage/logs/laravel.log`
- Line 217: Change to `tail -f storage/logs/laravel.log | grep "CommunicationBus"`
- Line 892: Change to `tail -f storage/logs/laravel.log | grep "Query took"`
- Line 1446: Change to `tail -f storage/logs/laravel.log`

### Task 7.3: Remove References to Non-Existent Commands
- **File**: `documentation/TROUBLESHOOTING.md`
- **Lines**: 148, 810

**Action**:
- Line 148: Remove `php artisan odluke:ingest --limit=100`
- Line 810: Remove `php artisan queue:monitor`

Replace with generic queue checking:
```bash
# Check pending jobs
php artisan tinker
>>> DB::table('jobs')->count();
```

**Verification**:
```bash
php artisan list | grep horizon
# Should return nothing if Horizon not installed
php artisan list | grep pail
# Should return nothing if Pail not installed
grep -n "php artisan pail\|php artisan horizon:status\|php artisan odluke:ingest\|php artisan queue:monitor" documentation/TROUBLESHOOTING.md
# Should return no matches after update
```

---

## Task Group 8: NEO4J_INSTALLATION.md

### Task 8.1: Remove Reference to Missing Docker Script
- **File**: `documentation/NEO4J_INSTALLATION.md`
- **Lines**: 10, 39
- **Issue**: References `install-neo4j-docker.sh` which doesn't exist

**Action**: Replace lines 10-11 with:
```bash
# Manual Docker installation (no script available)
docker run -d --name neo4j \
  -p 7474:7474 -p 7687:7687 \
  -e NEO4J_AUTH=neo4j/pass \
  neo4j:5.13-community
```

**Action**: Replace lines 39-42 with:
```markdown
2. Run manual Docker commands (see Quick Start above):
```

### Task 8.2: Remove Reference to Missing System Script
- **File**: `documentation/NEO4J_INSTALLATION.md`
- **Line**: 58
- **Issue**: References `install-neo4j.sh` as system installation script

**Action**: Replace line 57-59 with:
```markdown
**Steps:**
1. Use the existing setup script:
   ```bash
   sudo ./scripts/setup-neo4j.sh
   ```
```

### Task 8.3: Remove Reference to Missing Command
- **File**: `documentation/NEO4j_INSTALLATION.md`
- **Line**: 120
- **Issue**: References `php artisan neo4j:health-check`

**Action**: Verify if command exists:
```bash
php artisan list | grep neo4j
```

If command doesn't exist, replace line 120 with:
```bash
php artisan tinker
>>> DB::connection('neo4j')->getPdo();
```

### Task 8.4: Remove Reference to Missing Documentation
- **File**: `documentation/NEO4J_INSTALLATION.md`
- **Line**: 357
- **Issue**: References `docs/GRAPH_SCHEMA.md` which doesn't exist

**Action**: Replace line 357 with:
```markdown
- Graph schema configuration: `config/neo4j.php`
```

**Verification**:
```bash
test -f scripts/install-neo4j-docker.sh && echo "EXISTS" || echo "MISSING"
test -f scripts/install-neo4j.sh && echo "EXISTS" || echo "MISSING"
test -f documentation/GRAPH_SCHEMA.md && echo "EXISTS" || echo "MISSING"
# All should show MISSING
```

---

## Summary of Changes

| File | Issues Fixed | Lines Modified | Severity |
|------|-------------|----------------|----------|
| NEO4J_SETUP_SUMMARY.md | 5 missing scripts | 30-58 (28 lines) | High |
| AURADB_QUICKSTART.md | 2 missing scripts | 23-31, 44 (~10 lines) | Medium |
| citation-network-ui-requirements.md | Wrong status | 11 + new section | Low |
| FORMREQUEST_VALIDATION_GUIDE.md | Wrong count (34→75) | 15-17, 737 | Medium |
| FACT_PATTERN_USAGE_EXAMPLES.md | Missing endpoints | 13+, 18, 102, 122 | High |
| CURRENT_STATUS.md | Stale date, broken link | 3, 121 | Low |
| TROUBLESHOOTING.md | 4 missing commands | 40, 47, 148, 217, 810, 892, 1446 | Medium |
| NEO4J_INSTALLATION.md | 2 missing scripts, 1 missing doc | 10, 39, 58, 120, 357 | Medium |

**Total Lines to Update**: ~100 lines across 8 files

---

## Verification Checklist

After completing all tasks, run these commands:

```bash
# 1. Verify no references to missing scripts
grep -r "configure-auradb.sh\|install-neo4j-docker.sh\|install-aura-cli.sh\|check-neo4j-requirements.sh" documentation/ | grep -v "setup-neo4j.sh"

# 2. Verify FormRequest count is 75
grep "Total FormRequests.*75" documentation/FORMREQUEST_VALIDATION_GUIDE.md

# 3. Verify UI status updated
grep "PARTIALLY IMPLEMENTED" documentation/citation-network-ui-requirements.md

# 4. Verify no pail/horizon references
grep -n "php artisan pail\|php artisan horizon:status" documentation/TROUBLESHOOTING.md

# 5. Verify date updated
grep "Last Updated.*2025-12-21" documentation/CURRENT_STATUS.md

# 6. Verify planned API warning
grep "NOT YET IMPLEMENTED" documentation/FACT_PATTERN_USAGE_EXAMPLES.md
```

All grep commands should either:
- Return the expected updated content, OR
- Return no matches (for removed content)

---

## Implementation Order

**Recommended order** (easiest to hardest):

1. **CURRENT_STATUS.md** (2 simple changes)
2. **citation-network-ui-requirements.md** (status update + new section)
3. **FORMREQUEST_VALIDATION_GUIDE.md** (number updates)
4. **AURADB_QUICKSTART.md** (remove script references)
5. **NEO4J_SETUP_SUMMARY.md** (consolidate script list)
6. **NEO4J_INSTALLATION.md** (multiple script references)
7. **FACT_PATTERN_USAGE_EXAMPLES.md** (add warnings)
8. **TROUBLESHOOTING.md** (multiple command replacements)

**Estimated time per file**: 10-20 minutes
**Total time**: 2-3 hours for careful, verified updates
