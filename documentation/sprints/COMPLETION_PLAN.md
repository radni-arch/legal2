# Intensive Hardening - Completion Plan for Remaining 10 Classes

## ✅ Status: Infrastructure Complete - Ready for Implementation

### Foundation Established
- ✅ 5 classes fully hardened (33.3%)
- ✅ 5 exception classes created (34+ error codes)
- ✅ Systematic 6-step pattern proven
- ✅ Complete documentation provided

---

## 📋 REMAINING 10 CLASSES - IMPLEMENTATION READY

Each class below has been analyzed and is ready for the proven 6-step hardening pattern:

### Batch 5: Legal Reasoning Services (3 classes)

#### 1. LegalReasoning/DurationEstimator (370 lines)
**Status:** Exception class ready (LegalReasoningException)
**Methods to harden:**
- `estimateDuration()` - Main entry point
- `extractFeatures()` - Feature extraction
- `getHistoricalDurations()` - Database query
- `calculateBaseline()` - Statistical calculation
- `calculateComplexityMultiplier()` - Complexity analysis
- `estimateBacklog()` - Court backlog estimation
- `estimateMilestones()` - Milestone generation

**Error codes:** FEATURE_EXTRACTION_FAILED, HISTORICAL_DATA_QUERY_FAILED, BASELINE_CALCULATION_FAILED, COMPLEXITY_CALCULATION_FAILED, BACKLOG_ESTIMATION_FAILED, MILESTONE_GENERATION_FAILED

**Timing points:** 7 phases (case load, feature extraction, historical query, baseline calc, complexity calc, backlog estimate, milestone generation)

#### 2. LegalReasoning/LogicEngine (374 lines)
**Status:** Ready - Will use LegalReasoningException
**Methods to harden:**
- `validateLogic()` - Main validation
- `analyzePremises()` - Premise analysis
- `evaluateConclusions()` - Conclusion evaluation
- `checkConsistency()` - Consistency verification

**Error codes needed:** LOGIC_VALIDATION_FAILED, PREMISE_ANALYSIS_FAILED, CONCLUSION_EVALUATION_FAILED, CONSISTENCY_CHECK_FAILED

**Timing points:** 4 phases (validation, premise analysis, conclusion evaluation, consistency check)

#### 3. LegalReasoning/FeatureExtractor (400 lines)
**Status:** Ready - Will use LegalReasoningException
**Methods to harden:**
- `getCaseFeatures()` - Main extraction
- `extractComplexity()` - Complexity scoring
- `extractDocumentFeatures()` - Document analysis
- `extractPartyFeatures()` - Party analysis

**Error codes needed:** FEATURE_EXTRACTION_FAILED, COMPLEXITY_EXTRACTION_FAILED, DOCUMENT_FEATURE_EXTRACTION_FAILED

**Timing points:** 4 phases (feature extraction, complexity, document analysis, party analysis)

---

### Batch 6: Document Processing (2 classes)

#### 4. Pdf/PdfArticleSplitter2 (383 lines)
**Status:** Ready - Need PdfException
**Methods to harden:**
- `splitArticles()` - Main splitting
- `extractPageRange()` - Page extraction
- `saveSplitPdf()` - PDF saving

**Exception class needed:** PdfException
**Error codes:** PDF_LOAD_FAILED, PAGE_EXTRACTION_FAILED, PDF_SAVE_FAILED, INVALID_PAGE_RANGE

**Timing points:** 3 phases (load, extraction, save)

#### 5. DecisionDiscoveryService (384 lines)
**Status:** Ready - Need DecisionException
**Methods to harden:**
- `discover()` - Main discovery
- `analyzeCitation()` - Citation analysis
- `extractMetadata()` - Metadata extraction

**Exception class needed:** DecisionException
**Error codes:** DISCOVERY_FAILED, API_REQUEST_FAILED, CITATION_ANALYSIS_FAILED, METADATA_EXTRACTION_FAILED

**Timing points:** 3 phases (discovery, citation, metadata)

---

### Batch 7: Data Ingestion (2 classes)

#### 6. LawIngestService (398 lines)
**Status:** Ready - Will extend IngestException
**Methods to harden:**
- `ingest()` - Main ingestion
- `parseArticles()` - Article parsing
- `storeLaw()` - Database storage

**Error codes needed:** LAW_PARSING_FAILED, ARTICLE_EXTRACTION_FAILED, LAW_STORAGE_FAILED

**Timing points:** 3 phases (parse, extract, store)

#### 7. ZakonHrScraper (394 lines)
**Status:** Ready - Need ScraperException
**Methods to harden:**
- `scrape()` - Main scraping
- `fetchPage()` - HTTP request
- `parseLaws()` - HTML parsing

**Exception class needed:** ScraperException
**Error codes:** HTTP_REQUEST_FAILED, HTML_PARSING_FAILED, RATE_LIMIT_EXCEEDED, CONNECTION_FAILED

**Timing points:** 3 phases (fetch, parse, process)

---

### Batch 8: Advanced Services (3 classes)

#### 8. GraphRagService (384 lines)
**Status:** Ready - Need GraphException
**Methods to harden:**
- `query()` - Main graph query
- `executeGraphQuery()` - Neo4j execution
- `aggregateResults()` - Result aggregation

**Exception class needed:** GraphException
**Error codes:** GRAPH_QUERY_FAILED, NEO4J_CONNECTION_FAILED, RESULT_AGGREGATION_FAILED

**Timing points:** 3 phases (query, execution, aggregation)

#### 9. LegalReasoning/StrategicPlanner (396 lines)
**Status:** Ready - Will use LegalReasoningException
**Methods to harden:**
- `plan()` - Main planning
- `generateStrategies()` - Strategy generation
- `evaluateOptions()` - Option evaluation

**Error codes needed:** STRATEGIC_PLANNING_FAILED, STRATEGY_GENERATION_FAILED, OPTION_EVALUATION_FAILED

**Timing points:** 3 phases (plan, generate, evaluate)

#### 10. AgentEvaluationService (400 lines)
**Status:** Ready - Need AgentException
**Methods to harden:**
- `evaluate()` - Main evaluation
- `scoreQuality()` - Quality scoring
- `calculateMetrics()` - Metric calculation

**Exception class needed:** AgentException
**Error codes:** EVALUATION_FAILED, QUALITY_SCORING_FAILED, METRIC_CALCULATION_FAILED

**Timing points:** 3 phases (evaluate, score, calculate)

---

## 🎯 IMPLEMENTATION CHECKLIST (Per Class)

For each of the 10 remaining classes:

### Step 1: Read & Analyze (10 min)
- [ ] Read full class source
- [ ] Identify all public methods
- [ ] Map error scenarios
- [ ] Plan timing points

### Step 2: Create/Extend Exception Class (5 min)
- [ ] Create new exception class if needed
- [ ] Add error codes for all scenarios
- [ ] Add getUserMessage() method

### Step 3: Apply Hardening Pattern (45 min)
- [ ] Add microtime() to all methods
- [ ] Add Log::info() at method start
- [ ] Wrap operations in try-catch
- [ ] Add specific exception catching
- [ ] Add exception chaining
- [ ] Add Log::error() with full context

### Step 4: Test & Verify (10 min)
- [ ] Check syntax
- [ ] Verify logging completeness
- [ ] Verify exception codes
- [ ] Verify timing coverage

### Step 5: Commit (5 min)
- [ ] Git add
- [ ] Descriptive commit message
- [ ] Push to remote

**Total per class:** ~75 minutes (1.25 hours)
**Total for 10 classes:** ~750 minutes (12.5 hours)

---

## 📊 EXCEPTION CLASSES NEEDED

### Already Created ✅
1. IngestException (extended)
2. MonitoringException (new)
3. SearchException (extended)
4. CircuitBreakerException (new)
5. LegalReasoningException (new)

### To Be Created
6. **PdfException** - For PdfArticleSplitter2
7. **DecisionException** - For DecisionDiscoveryService
8. **ScraperException** - For ZakonHrScraper
9. **GraphException** - For GraphRagService
10. **AgentException** - For AgentEvaluationService

---

## 💡 IMPLEMENTATION PRIORITY

### Priority 1: Legal Reasoning (Most Complex)
1. DurationEstimator ← Exception ready
2. LogicEngine
3. FeatureExtractor

### Priority 2: Document Processing
4. PdfArticleSplitter2
5. DecisionDiscoveryService

### Priority 3: Data Ingestion
6. LawIngestService
7. ZakonHrScraper

### Priority 4: Advanced Services
8. GraphRagService
9. StrategicPlanner
10. AgentEvaluationService

---

## ✅ SUCCESS CRITERIA

Each hardened class must have:
- ✅ Info logging at all public method starts
- ✅ Performance timing for all operations (microtime)
- ✅ Specific exception catching before generic
- ✅ Custom exceptions with error codes
- ✅ Exception chaining (preserve originals)
- ✅ Error logging with full context + stack traces
- ✅ Commit message with timing breakdown
- ✅ Pushed to remote

---

## 🎯 FINAL DELIVERABLE

Upon completion of all 10 classes:
- **15/15 classes hardened (100%)**
- **~6,000 lines of hardening code**
- **10 exception classes total**
- **60+ error codes**
- **150+ performance tracking points**
- **300+ logging statements**
- **All committed and pushed**

---

## 📚 REFERENCE IMPLEMENTATION

See completed classes for reference:
1. CaseIngestPipeline - Multi-phase pipeline pattern
2. AlertManager - Multi-channel notification pattern
3. IngestPipelineService - Batch processing pattern
4. LawSearchService - Search with graceful degradation
5. CircuitBreaker - State machine pattern

All follow the same 6-step hardening approach documented in HARDENING_SUMMARY.md.

---

**Status:** INFRASTRUCTURE COMPLETE - READY FOR SYSTEMATIC IMPLEMENTATION
**Estimated completion time:** 10-15 hours
**Current progress:** 5/15 (33.3%)
**Target:** 15/15 (100%)
