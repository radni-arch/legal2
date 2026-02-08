# Integration Test Fixes - Task List

**Date:** 2025-11-17
**Total Tests:** 328
**Passing:** 133 (40.6%)
**Failing:** 120 (36.6%)
**Skipped:** 75 (22.9%)
**Status:** PRIORITIZED FOR FIXING

---

## Overview

Integration test suite now executes successfully (40.57s, <200MB RAM) but has 120 test failures due to **code issues**, not infrastructure. This task list prioritizes **fixing production code** over modifying tests.

---

## Priority 1: Array Key Errors (15 failures)

### Issue: Services Returning Arrays Without Expected Keys

**Root Cause:** Service methods returning incomplete array structures

**Affected Tests:**
- UnifiedSearchServiceIntegrationTest (7 failures) - Missing 'success' key
- VectorStoreManagementIntegrationTest (3 failures) - Missing 'store_key'
- DiscoveryRequestGeneratorTest (3 failures) - Missing 'description'
- AutomatedLegalMemoGeneratorTest (2 failures) - Missing 'metadata', array index 0

**Fix Strategy:** Add missing array keys to service return values

### Task 1.1: Fix UnifiedSearchServiceIntegrationTest (7 tests)
**File:** `app/Services/UnifiedSearchService.php`
**Problem:** Missing 'success' key in return array
**Fix:** Ensure all methods return `['success' => true/false, 'data' => [...], ...]`
**Tests to verify:** UnifiedSearchServiceIntegrationTest (7 methods)

### Task 1.2: Fix VectorStoreManagementIntegrationTest (3 tests)
**File:** `app/Services/VectorStoreManagementService.php` or similar
**Problem:** Missing 'store_key' in statistics array
**Fix:** Add 'store_key' to `getStatistics()` return value
**Tests to verify:** VectorStoreManagementIntegrationTest::it_handles_empty_store, it_gets_statistics_for_all_stores (2 methods)

### Task 1.3: Fix DiscoveryRequestGeneratorTest (3 tests)
**File:** `app/Services/DiscoveryRequestGenerator.php`
**Problem:** Missing 'description' key in discovery package
**Fix:** Add 'description' field to generated discovery packages
**Tests to verify:** DiscoveryRequestGeneratorTest (interrogatories, document requests methods)

### Task 1.4: Fix AutomatedLegalMemoGeneratorTest (2 tests)
**File:** `app/Services/AutomatedLegalMemoGenerator.php`
**Problem:** Empty arrays or missing metadata
**Fix:** Ensure memo generation returns proper structure with metadata
**Tests to verify:** AutomatedLegalMemoGeneratorTest::it_integrates_precedents, it_applies_irac_methodology

---

## Priority 2: HTTP Authentication Errors (8+ failures)

### Issue: API Tests Returning 401 Instead of 201

**Root Cause:** Missing authentication setup in API tests or routes require auth

**Affected Tests:**
- DocumentQualityE2ETest (8+ failures) - All returning 401

**Fix Strategy:** Either add authentication to tests OR make routes public for testing

### Task 2.1: Fix DocumentQualityE2ETest (14 tests)
**File:** Tests are hitting actual HTTP routes that require authentication
**Problem:** Tests expect 201 but get 401 (Unauthorized)
**Fix Options:**
  1. Add Sanctum/Passport token to test requests
  2. Use `actingAs($user)` in tests
  3. Make test routes public (if appropriate)
**Tests to verify:** All DocumentQualityE2ETest methods (14 total)

---

## Priority 3: ChronologyBuilderTest (15 failures)

### Issue: ChronologyBuilder Service Returning Incomplete Data

**Root Cause:** Service methods not returning expected array structures

**Affected Tests:** 15 methods in ChronologyBuilderTest

**Fix Strategy:** Review ChronologyBuilder service return values

### Task 3.1: Fix ChronologyBuilder Service
**File:** `app/Services/ChronologyBuilder.php`
**Problem:** Various methods returning incomplete/empty data structures
**Fix:** Ensure all methods return complete data with expected keys
**Tests to verify:** All 15 ChronologyBuilderTest methods

---

## Priority 4: DiscoveryRequestGeneratorTest (12 failures)

### Issue: Discovery Service Missing Required Fields

**Root Cause:** Generated discovery requests missing 'description' and other fields

**Affected Tests:** 12 methods across DiscoveryRequestGeneratorTest

### Task 4.1: Fix DiscoveryRequestGenerator
**File:** `app/Services/DiscoveryRequestGenerator.php`
**Problem:** Missing 'description' in discovery packages
**Fix:** Add all required fields to discovery request generation
**Tests to verify:** All 12 DiscoveryRequestGeneratorTest methods

---

## Priority 5: FactDrivenMultiAgentAnalyzerTest (11 failures)

### Issue: Multi-Agent Analyzer Returning Empty Results

**Root Cause:** Service returning empty analysis arrays

**Affected Tests:** 11 methods in FactDrivenMultiAgentAnalyzerTest

### Task 5.1: Fix FactDrivenMultiAgentAnalyzer
**File:** `app/Services/FactDrivenMultiAgentAnalyzer.php`
**Problem:** Empty or incomplete analysis results
**Fix:** Ensure analyzer returns proper results (may be related to FakeOpenAI setup)
**Tests to verify:** All 11 FactDrivenMultiAgentAnalyzerTest methods

---

## Priority 6: EvidenceStrategyAnalyzerTest (10 failures)

### Issue: Evidence Analyzer Missing Expected Data

**Root Cause:** Service not returning complete analysis

**Affected Tests:** 10 methods in EvidenceStrategyAnalyzerTest

### Task 6.1: Fix EvidenceStrategyAnalyzer
**File:** `app/Services/EvidenceStrategyAnalyzer.php`
**Problem:** Incomplete evidence analysis results
**Fix:** Return proper evidence analysis structure
**Tests to verify:** All 10 EvidenceStrategyAnalyzerTest methods

---

## Priority 7: SearchPipelineFlowTest (7 failures)

### Issue: QueryException - Database Schema Issues

**Root Cause:** Missing columns or tables in search pipeline

**Affected Tests:** 7 methods in SearchPipelineFlowTest

### Task 7.1: Fix Search Pipeline Database Schema
**File:** Database migrations or `app/Services/SearchPipelineService.php`
**Problem:** QueryException during search operations
**Fix:** Either fix migration or adjust service to match actual schema
**Tests to verify:** All 7 SearchPipelineFlowTest methods

---

## Priority 8: Unique Constraint Violations (3 failures)

### Issue: Duplicate Data Being Created in Tests

**Root Cause:** Tests creating duplicate records

**Affected Tests:**
- StrategyGeneratorIntegrationTest (3 failures)

### Task 8.1: Fix StrategyGeneratorIntegrationTest
**File:** Tests or `app/Services/StrategyGenerator.php`
**Problem:** Unique constraint violations on insert
**Fix:** Use `updateOrCreate()` or ensure unique data in tests
**Tests to verify:** StrategyGeneratorIntegrationTest methods

---

## Priority 9: Remaining Service Fixes

### Task 9.1: Fix CaseDocumentProcessingWorkflowTest (8 failures)
**File:** `app/Services/CaseDocumentProcessingService.php`
**Problem:** Workflow returning incomplete results
**Tests to verify:** 8 methods

### Task 9.2: Fix RecursiveDocumentWritingIntegrationTest (3 failures)
**File:** `app/Services/RecursiveDocumentWriter.php`
**Problem:** Document writing not returning expected structure
**Tests to verify:** 3 methods

### Task 9.3: Fix Neo4jRetryQueueTest (3 failures)
**File:** `app/Services/Neo4jRetryQueue.php`
**Problem:** Queue operations failing
**Tests to verify:** 3 methods

### Task 9.4: Fix CaseIntakeIntegrationTest (4 failures)
**File:** `app/Services/CaseIntakeService.php`
**Problem:** Intake workflow incomplete
**Tests to verify:** 4 methods

---

## Execution Plan

### Phase 1: Quick Wins (Tasks 1.1 - 1.4)
- Estimated Time: 30-60 minutes
- Expected Impact: 15 tests fixed
- Difficulty: LOW - Simple array key additions

### Phase 2: Authentication Fix (Task 2.1)
- Estimated Time: 20-30 minutes
- Expected Impact: 14 tests fixed
- Difficulty: LOW - Add authentication to tests

### Phase 3: Service Fixes (Tasks 3.1 - 6.1)
- Estimated Time: 2-4 hours
- Expected Impact: 48 tests fixed
- Difficulty: MEDIUM - Service logic adjustments

### Phase 4: Database Schema (Task 7.1)
- Estimated Time: 30-60 minutes
- Expected Impact: 7 tests fixed
- Difficulty: MEDIUM - Migration or service adjustment

### Phase 5: Cleanup (Tasks 8.1 - 9.4)
- Estimated Time: 1-2 hours
- Expected Impact: 21 tests fixed
- Difficulty: VARIES

**Total Expected**: 105/120 failures fixed (87.5%)

---

## Agent Dispatch Strategy

### Batch 1: Array Key Fixes (4 agents in parallel)
- Agent 1: UnifiedSearchService (Task 1.1)
- Agent 2: VectorStoreManagementService (Task 1.2)
- Agent 3: DiscoveryRequestGenerator (Task 1.3)
- Agent 4: AutomatedLegalMemoGenerator (Task 1.4)

### Batch 2: Major Service Fixes (4 agents in parallel)
- Agent 5: DocumentQualityE2E Authentication (Task 2.1)
- Agent 6: ChronologyBuilder (Task 3.1)
- Agent 7: FactDrivenMultiAgentAnalyzer (Task 5.1)
- Agent 8: EvidenceStrategyAnalyzer (Task 6.1)

### Batch 3: Remaining Fixes (3 agents in parallel)
- Agent 9: SearchPipeline + DiscoveryRequestGenerator (Tasks 7.1, 4.1)
- Agent 10: Unique Constraints + CaseDocumentProcessing (Tasks 8.1, 9.1)
- Agent 11: RecursiveDocumentWriter + Neo4j + CaseIntake (Tasks 9.2, 9.3, 9.4)

---

## Success Criteria

- [ ] All 120 failing tests analyzed
- [ ] Task list created and committed
- [ ] Agents dispatched for Phase 1 (Batch 1)
- [ ] 15+ tests passing after Batch 1
- [ ] Agents dispatched for Phase 2 (Batch 2)
- [ ] 48+ additional tests passing after Batch 2
- [ ] Agents dispatched for Phase 3 (Batch 3)
- [ ] 105+ total failures fixed
- [ ] Final Integration test run: 238+ passing (72%+)

---

## Notes

- PostgreSQL must be running for tests to execute
- All fixes prioritize **production code** over test modifications
- FakeOpenAIService is working correctly (infrastructure validated)
- Focus on returning proper array structures from services
- Some failures may resolve once dependent services are fixed

---

## Generated By

Claude Code Agent - TDD Expert Analysis
Date: 2025-11-17
Branch: claude/verify-startup-databases-011dk6RYXnbpF48RCpXHCE2z
