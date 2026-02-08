# Failing Tests - Categorized Tasks

> **Auto-generated** by test failure analyzer
> **Generated**: $(date '+%Y-%m-%d %H:%M:%S')

## Summary

Total Failures: 2833
Failure Categories: 6

---

## Task List


### Task 1: Fix FAILED  Failures

**Priority**: HIGH
**Affected Tests**: 61
**Error Category**: FAILED 

**Description**: General test failure - investigate root cause

**Recommended Skill**: systematic-debugging

**Sample Failing Tests** (showing 61 total):
```
Tests\Browser\ChromeFixVerificationTest[22m [90m
Tests\Browser\ExampleTest[22m [90m
Tests\Feature\ExampleTest[22m [90m
Tests\Feature\HoneypotControllerTest[22m [90m
Tests\Feature\HoneypotControllerTest[22m [90m
```

**Action Items**:
1. Read test file and understand what it verifies
2. Reproduce failure locally
3. Use systematic-debugging skill to find root cause
4. Implement fix with proper error handling
5. Verify fix doesn't break other tests

**Skill Invocation**:
```bash
# Use the systematic-debugging skill
claude> /skill systematic-debugging
```

---

### Task 2: Fix QueryException  Failures

**Priority**: HIGH
**Affected Tests**: 535
**Error Category**: QueryException 

**Description**: Database/permission issues require systematic debugging

**Recommended Skill**: systematic-debugging

**Sample Failing Tests** (showing 535 total):
```
Tests\Browser\AgentCollaborationViewerTest[22m [90m
Tests\Browser\AgentCollaborationViewerTest[22m [90m
Tests\Browser\AgentCollaborationViewerTest[22m [90m
Tests\Browser\AgentCollaborationViewerTest[22m [90m
Tests\Browser\AgentCollaborationViewerTest[22m [90m
```

**Action Items**:
1. Verify PostgreSQL is running and accessible
2. Check database permissions and ownership
3. Verify migrations have run successfully
4. Check test database configuration in phpunit.xml
5. Investigate specific SQL queries causing failures

**Skill Invocation**:
```bash
# Use the systematic-debugging skill
claude> /skill systematic-debugging
```

---

### Task 3: Fix ViewException  Failures

**Priority**: MEDIUM
**Affected Tests**: 3
**Error Category**: ViewException 

**Description**: General test failure - investigate root cause

**Recommended Skill**: systematic-debugging

**Sample Failing Tests** (showing 3 total):
```
Tests\Feature\Livewire\GraphDashboardTest[22m [90m
Tests\Feature\ParallelTimelineTest[22m [90m
Tests\Feature\ParallelTimelineTest[22m [90m
```

**Action Items**:
1. Read test file and understand what it verifies
2. Reproduce failure locally
3. Use systematic-debugging skill to find root cause
4. Implement fix with proper error handling
5. Verify fix doesn't break other tests

**Skill Invocation**:
```bash
# Use the systematic-debugging skill
claude> /skill systematic-debugging
```

---

### Task 4: Fix PDOException  Failures

**Priority**: HIGH
**Affected Tests**: 2231
**Error Category**: PDOException 

**Description**: Database/permission issues require systematic debugging

**Recommended Skill**: systematic-debugging

**Sample Failing Tests** (showing 2231 total):
```
Tests\Browser\EpredmetWidgetTest[22m [90m
Tests\Browser\EpredmetWidgetTest[22m [90m
Tests\Browser\EpredmetWidgetTest[22m [90m
Tests\Browser\EpredmetWidgetTest[22m [90m
Tests\Browser\EpredmetWidgetTest[22m [90m
```

**Action Items**:
1. Verify PostgreSQL is running and accessible
2. Check database permissions and ownership
3. Verify migrations have run successfully
4. Check test database configuration in phpunit.xml
5. Investigate specific SQL queries causing failures

**Skill Invocation**:
```bash
# Use the systematic-debugging skill
claude> /skill systematic-debugging
```

---

### Task 5: Fix NoSuchElementException  Failures

**Priority**: LOW
**Affected Tests**: 2
**Error Category**: NoSuchElementException 

**Description**: General test failure - investigate root cause

**Recommended Skill**: systematic-debugging

**Sample Failing Tests** (showing 2 total):
```
Tests\Browser\UserOnboardingTest[22m [90m
Tests\Browser\UserOnboardingTest[22m [90m
```

**Action Items**:
1. Read test file and understand what it verifies
2. Reproduce failure locally
3. Use systematic-debugging skill to find root cause
4. Implement fix with proper error handling
5. Verify fix doesn't break other tests

**Skill Invocation**:
```bash
# Use the systematic-debugging skill
claude> /skill systematic-debugging
```

---

### Task 6: Fix TimeoutException  Failures

**Priority**: LOW
**Affected Tests**: 1
**Error Category**: TimeoutException 

**Description**: Timing/race condition issues - replace timeouts with event-based waiting

**Recommended Skill**: condition-based-waiting

**Sample Failing Tests** (showing 1 total):
```
Tests\Browser\UserOnboardingTest[22m [90m
```

**Action Items**:
1. Read test file and understand what it verifies
2. Reproduce failure locally
3. Use systematic-debugging skill to find root cause
4. Implement fix with proper error handling
5. Verify fix doesn't break other tests

**Skill Invocation**:
```bash
# Use the condition-based-waiting skill
claude> /skill condition-based-waiting
```

---

## Recommended Execution Order

1. **Fix Infrastructure Issues First** (QueryException, PDOException, Permission errors)
   - These block the most tests
   - Fix database connectivity, permissions, configuration
   - Priority: CRITICAL

2. **Fix Interface/Signature Mismatches** (Compatibility errors)
   - These prevent code from loading
   - Usually affect entire test suites
   - Priority: HIGH

3. **Fix Configuration Issues** (GraphQL, missing config)
   - Quick wins with high impact
   - Simple configuration changes
   - Priority: MEDIUM

4. **Fix Deprecation Warnings** (PHPUnit metadata)
   - Can be done incrementally
   - Use automated tools for bulk updates
   - Priority: LOW

5. **Fix Individual Test Logic** (Remaining failures)
   - Business logic issues
   - Require case-by-case investigation
   - Priority: VARIES

## Skills Reference

Available skills for test fixing:

- **systematic-debugging**: Use for any bug, test failure, or unexpected behavior
- **condition-based-waiting**: Use for timing/race condition issues
- **test-driven-development**: Use when implementing features with tests
- **testing-anti-patterns**: Use when writing or changing tests
- **verification-before-completion**: Use before claiming tests are fixed

## Automation Commands

**Re-run analysis after fixes**:
```bash
./scripts/analyze-test-failures.sh
```

**Run specific test suite**:
```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
php artisan dusk
```

**Run all tests in parallel**:
```bash
./scripts/run-all-tests-parallel.sh
```

**Check test status**:
```bash
./scripts/check-test-status.sh
```

---

*Generated by: `scripts/analyze-test-failures.sh`*
