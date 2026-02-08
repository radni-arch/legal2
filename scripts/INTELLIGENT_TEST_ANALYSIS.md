# Intelligent Test Analysis & Automated Task Generation

## Overview

This suite of scripts provides **automated test failure analysis** with **intelligent task generation** and **skill recommendations**. When tests fail, the system automatically categorizes failures, suggests fixes, and can create structured todo lists with appropriate skills.

## Features

✅ **Automatic Failure Categorization** - Groups similar failures together
✅ **Skill Recommendations** - Suggests which skills to use for each failure type
✅ **Priority Assignment** - Ranks tasks by impact (HIGH/MEDIUM/LOW)
✅ **Action Item Generation** - Provides specific steps to fix each category
✅ **Todo List Creation** - Generates structured tasks for tracking
✅ **Parallel Agent Readiness** - Identifies independent failure domains for parallel dispatch

---

## Quick Start

### Option 1: Run Tests with Auto-Analysis (Recommended)

```bash
./scripts/run-tests-with-analysis.sh
```

This will:
1. Run all test suites in parallel
2. Automatically analyze failures when complete
3. Generate categorized task document
4. Show summary and next steps

### Option 2: Analyze Existing Test Logs

```bash
# First run tests normally
./scripts/run-all-tests-parallel.sh

# Then analyze the logs
./scripts/analyze-test-failures.sh
```

### Option 3: Create Todo Tasks from Analysis

```bash
# After analysis, generate todo list
./scripts/create-todos-from-failures.sh
```

---

## Scripts Reference

### 1. `run-tests-with-analysis.sh` - All-in-One Runner

**Purpose**: Runs tests and automatically analyzes results

**Usage**:
```bash
./scripts/run-tests-with-analysis.sh
```

**What it does**:
1. Launches parallel test runner
2. Waits for tests to complete (or user interrupt)
3. Analyzes test failure logs
4. Generates categorized task document
5. Shows summary and next steps

**Output Files**:
- `FAILING_TESTS_TASKS.md` - Detailed task document with categories
- `TEST_RUN_SUMMARY.txt` - Quick reference summary

---

### 2. `analyze-test-failures.sh` - Failure Analyzer

**Purpose**: Parses test logs and categorizes failures

**Usage**:
```bash
./scripts/analyze-test-failures.sh
```

**What it does**:
1. Reads all test log files in `storage/logs/tests/`
2. Extracts failing tests and error types
3. Groups failures by category (QueryException, Permission denied, etc.)
4. Counts failures per category
5. Generates `FAILING_TESTS_TASKS.md` with:
   - Prioritized task list
   - Skill recommendations
   - Sample failing tests
   - Specific action items
   - Execution order recommendations

**Failure Categories Detected**:
- Database errors (QueryException, PDOException, Permission denied)
- Interface mismatches (Declaration must be compatible)
- Missing dependencies (Class not found, Undefined)
- Timing issues (Timeout, race condition)
- Configuration issues (GraphQL, endpoints)
- Deprecation warnings (PHPUnit metadata)

**Skill Mapping**:
| Error Pattern | Recommended Skill | Reason |
|---------------|-------------------|--------|
| QueryException, PDOException | `systematic-debugging` | Database issues need root cause analysis |
| Permission denied | `systematic-debugging` | Permission issues need systematic investigation |
| Method signature | `systematic-debugging` | Interface mismatches need careful analysis |
| Timeout, race condition | `condition-based-waiting` | Replace timeouts with event-based waiting |
| GraphQL warnings | None (config fix) | Simple configuration change |
| PHPUnit deprecated | None (bulk update) | Automated attribute conversion |

**Output Format**:
```markdown
# Failing Tests - Categorized Tasks

## Summary
Total Failures: 670
Failure Categories: 6

## Task List

### Task 1: Fix QueryException Failures
**Priority**: HIGH
**Affected Tests**: 299
**Recommended Skill**: systematic-debugging

**Sample Failing Tests**:
Tests\Integration\CaseIntakeIntegrationTest
Tests\Integration\ChronologyBuilderTest
...

**Action Items**:
1. Verify PostgreSQL is running
2. Check database permissions
3. Verify migrations
...

**Skill Invocation**:
claude> /skill systematic-debugging
```

---

### 3. `create-todos-from-failures.sh` - Todo Generator

**Purpose**: Converts analyzed failures into structured todo list

**Usage**:
```bash
./scripts/create-todos-from-failures.sh
```

**What it does**:
1. Parses `FAILING_TESTS_TASKS.md`
2. Extracts task titles, priorities, and counts
3. Generates `test-failure-todos.json` in TodoWrite format
4. Creates `TODO_IMPORT_INSTRUCTIONS.md` with usage guide

**Output Files**:
- `test-failure-todos.json` - Structured JSON for todo import
- `TODO_IMPORT_INSTRUCTIONS.md` - How to use the generated todos

**Example Output**:
```json
{
  "todos": [
    {
      "content": "Fix QueryException Failures (299 tests)",
      "status": "pending",
      "activeForm": "Fixing QueryException Failures (299 tests)"
    },
    {
      "content": "Fix GraphQL Configuration (50 tests)",
      "status": "pending",
      "activeForm": "Fixing GraphQL Configuration (50 tests)"
    }
  ]
}
```

---

## Workflow Examples

### Example 1: Full Automated Workflow

```bash
# 1. Run tests with automatic analysis
./scripts/run-tests-with-analysis.sh

# 2. Review generated tasks
cat FAILING_TESTS_TASKS.md

# 3. Create todo list (optional)
./scripts/create-todos-from-failures.sh

# 4. In Claude session, use skills as recommended
claude> /skill systematic-debugging
# Work on database issues...

# 5. Re-run analysis after fixes
./scripts/analyze-test-failures.sh

# 6. Track progress
./scripts/check-test-status.sh
```

### Example 2: Parallel Agent Dispatch

After analysis, identify independent failure domains:

```bash
# 1. Analyze failures
./scripts/analyze-test-failures.sh

# 2. Review FAILING_TESTS_TASKS.md
cat FAILING_TESTS_TASKS.md

# 3. Identify independent domains
# Example output shows:
# - 299 QueryException failures (database)
# - 50 GraphQL configuration failures (config)
# - 200 PHPUnit deprecation warnings (code updates)

# 4. In Claude session, dispatch parallel agents
claude> Dispatch parallel agents for:
1. Fix database QueryException errors (299 tests)
2. Fix GraphQL configuration (50 tests)
3. Update PHPUnit deprecation warnings (200 tests)
```

Each agent works independently, no conflicts.

### Example 3: Incremental Fixing

```bash
# 1. Run initial analysis
./scripts/run-tests-with-analysis.sh

# 2. Fix HIGH priority items first
# Work on database issues...

# 3. Re-run just analysis (tests don't need to re-run)
./scripts/analyze-test-failures.sh

# 4. See reduced failure count
cat FAILING_TESTS_TASKS.md
# Now shows: 370 failures (299 database issues fixed!)

# 5. Continue with next priority
```

---

## Task Document Structure

Generated `FAILING_TESTS_TASKS.md` contains:

### 1. Summary Section
- Total failure count
- Number of categories
- Quick overview

### 2. Task List
For each category:
- **Task number and title**
- **Priority** (HIGH/MEDIUM/LOW based on test count)
- **Affected test count**
- **Error category** (QueryException, Permission denied, etc.)
- **Description** (what the error means)
- **Recommended skill** (which Claude skill to use)
- **Sample failing tests** (up to 5 examples)
- **Action items** (specific steps to fix)
- **Skill invocation** (command to run)

### 3. Recommended Execution Order
1. Infrastructure issues (database, permissions)
2. Interface/signature mismatches
3. Configuration issues
4. Deprecation warnings
5. Individual test logic

### 4. Skills Reference
- List of available skills
- When to use each skill
- How to invoke skills

### 5. Automation Commands
- Re-run analysis
- Run specific test suites
- Check status
- View logs

---

## Priority Levels

Tasks are automatically prioritized based on impact:

| Priority | Test Count | Impact | Action |
|----------|-----------|--------|--------|
| **HIGH** | 10+ tests | Critical blocker | Fix immediately |
| **MEDIUM** | 3-9 tests | Moderate impact | Fix soon |
| **LOW** | 1-2 tests | Minor issue | Fix when convenient |

---

## Integration with Existing Tools

### Works With Parallel Test Runner

```bash
# Standard parallel runner
./scripts/run-all-tests-parallel.sh

# Enhanced with analysis
./scripts/run-tests-with-analysis.sh
```

### Works With Status Checker

```bash
# Check which tests are still running
./scripts/check-test-status.sh

# Then analyze completed logs
./scripts/analyze-test-failures.sh
```

### Works With Log Viewer

```bash
# View specific suite logs
./scripts/view-test-logs.sh unit

# Analyze all logs
./scripts/analyze-test-failures.sh
```

---

## Advanced Usage

### Custom Analysis After Specific Suite

```bash
# Run only Integration tests
php artisan test --testsuite=Integration > storage/logs/tests/integration-tests.log 2>&1

# Analyze just that log
./scripts/analyze-test-failures.sh
```

### Re-analyze Without Re-running Tests

```bash
# Tests already ran yesterday
# Just re-analyze the existing logs
./scripts/analyze-test-failures.sh
```

### Filter by Priority

```bash
# Generate tasks
./scripts/analyze-test-failures.sh

# Show only HIGH priority
grep -A 20 "Priority\*\*: HIGH" FAILING_TESTS_TASKS.md
```

---

## Output Files Reference

| File | Purpose | When Created |
|------|---------|--------------|
| `FAILING_TESTS_TASKS.md` | Main task document | After analysis |
| `TEST_RUN_SUMMARY.txt` | Quick summary | After run-tests-with-analysis |
| `test-failure-todos.json` | TodoWrite JSON | After create-todos-from-failures |
| `TODO_IMPORT_INSTRUCTIONS.md` | Usage guide | After create-todos-from-failures |
| `storage/logs/tests/*.log` | Raw test output | During test execution |

---

## Troubleshooting

### No tasks generated

**Issue**: `analyze-test-failures.sh` completes but no tasks
**Cause**: All tests passed or log files not found
**Solution**:
```bash
# Check if logs exist
ls -la storage/logs/tests/

# Check if tests actually failed
grep -i "failed" storage/logs/tests/*.log
```

### Wrong failure count

**Issue**: Task document shows incorrect number of failures
**Cause**: Old log files from previous runs
**Solution**:
```bash
# Clean old logs
rm -f storage/logs/tests/*.log

# Re-run tests
./scripts/run-tests-with-analysis.sh
```

### Analysis takes too long

**Issue**: `analyze-test-failures.sh` is slow
**Cause**: Very large log files (100MB+)
**Solution**: This is normal for large test suites. Wait for completion.

---

## Best Practices

### 1. Always Analyze After Fixes

```bash
# Fix some issues
# Then immediately re-analyze
./scripts/analyze-test-failures.sh

# Track progress
```

### 2. Use Parallel Agents for Independent Domains

When analysis shows multiple unrelated failure categories:
- Database errors (infrastructure)
- GraphQL config (configuration)
- Code deprecations (code updates)

Dispatch parallel agents - they won't conflict.

### 3. Start with Infrastructure

Fix blocking issues first:
1. Database connectivity
2. File permissions
3. Missing dependencies

Then move to code issues.

### 4. Use Recommended Skills

Don't guess - use the skill recommendations:
- `systematic-debugging` for root cause analysis
- `condition-based-waiting` for timing issues
- Skills are matched to error patterns

### 5. Track Progress

```bash
# Initial run
./scripts/run-tests-with-analysis.sh
# Shows: 670 failures

# Fix database issues
# Re-analyze
./scripts/analyze-test-failures.sh
# Shows: 370 failures (300 fixed!)

# Continue...
```

---

## Example Complete Session

```bash
# Session start
cd /path/to/project

# Run tests with analysis
./scripts/run-tests-with-analysis.sh
# Output: 670 failures across 6 categories

# Review tasks
cat FAILING_TESTS_TASKS.md
# Sees:
# - 299 QueryException (HIGH)
# - 200 Deprecation warnings (LOW)
# - 50 GraphQL config (MEDIUM)
# etc.

# Dispatch parallel agents for independent domains
claude> Dispatch parallel agents:
1. Fix QueryException database errors
2. Fix GraphQL configuration

# Agents work simultaneously
# Agent 1: Fixes PostgreSQL permissions
# Agent 2: Adds GraphQL config to phpunit.xml

# Re-analyze
./scripts/analyze-test-failures.sh
# Now: 420 failures (250 fixed!)

# Use skill for remaining issues
claude> /skill systematic-debugging
# Work on deprecation warnings...

# Final analysis
./scripts/analyze-test-failures.sh
# Result: 20 failures (650 fixed!)
```

---

## Summary

**Input**: Test log files from parallel test runner
**Processing**: Categorize failures, assign priorities, recommend skills
**Output**: Structured task document with actionable items
**Integration**: Works with TodoWrite, skills, parallel agents
**Benefit**: Turn 670 random failures into 6 organized, fixable tasks

---

*Part of the AI Legal War Machine test automation suite*
