# Technical Debt Inventory - AI Legal War Machine

**Generated:** 2025-11-11
**Last Updated:** 2025-11-19
**Total TODOs:** 11 (1 completed)
**Total @deprecated:** 17 (3 classes, 14 methods)

---

## Summary

This document tracks technical debt in the codebase, including:
- TODO/FIXME markers indicating incomplete features
- @deprecated classes and methods that should be migrated
- Recommended actions and priorities

---

## 📋 TODO Markers (12 items)

### HIGH Priority TODOs (Should implement)

#### 1. **pgvector Similarity Search** - `app/Services/FederatedMemoryService.php:212`
```php
// TODO: Implement pgvector similarity search when extension is available
```

**Context:** Currently returning empty results for federated memory search
**Impact:** Agent memory sharing doesn't work optimally
**Effort:** 1-2 days
**Priority:** HIGH (affects agent collaboration)

**Recommendation:** Implement once pgvector is confirmed in production

---

#### 2. **Court Tracking in Topic Trends** - ✅ COMPLETED (2025-11-19)
~~`app/Console/Commands/Graph/AnalyzeTopicTrendsCommand.php:144`~~

**Implementation:**
- Added `getAffectedCourts()` method to `TopicAnalyticsService` that queries Neo4j for courts with decisions containing a specific topic
- Updated `AnalyzeTopicTrendsCommand` to populate the `affected_courts` JSON field with actual court data
- Added verbose mode display to show affected courts for each topic spike
- Includes comprehensive test coverage with 3 test cases

**Files Changed:**
- `app/Services/Graph/TopicAnalyticsService.php` - Added court tracking method
- `app/Console/Commands/Graph/AnalyzeTopicTrendsCommand.php` - Integrated court tracking
- `tests/Unit/Services/Graph/TopicAnalyticsServiceTest.php` - Added test coverage

---

#### 3. **Outlier PDF Reports** - `app/Console/Commands/Graph/DetectOutliersCommand.php:98`
```php
$this->comment('TODO: Implement OutlierReportGenerator for PDF reports');
```

**Context:** Outlier detection exists but no PDF export for attorney use
**Impact:** Attorneys can't easily share reports
**Effort:** 1-2 days
**Priority:** MEDIUM (nice to have for professional reports)

**Recommendation:** Implement using TCPDF or similar

---

### MEDIUM Priority TODOs (Enhance existing features)

#### 4. **Citation Time Series Tracking** - `app/Services/LegalReasoning/CitationAnalyzer.php:351`
```php
'citations_over_time' => [], // TODO: Implement time series tracking
```

**Context:** Citation analyzer doesn't track citation patterns over time
**Impact:** Missing trend analysis capability
**Effort:** 1 day
**Priority:** MEDIUM

**Recommendation:** Implement when attorney requests historical trend analysis

---

#### 5. **Full Concept Analysis Logic** - `app/Services/LawSearchService.php:880`
```php
// TODO: Implement full concept analysis logic
```

**Context:** Law search has basic concept matching but not full semantic analysis
**Impact:** Search quality could be better
**Effort:** 3-4 days
**Priority:** MEDIUM

**Recommendation:** Evaluate if current search quality is sufficient first

---

#### 6. **Full Statutory Interpretation Logic** - `app/Services/LawSearchService.php:929`
```php
// TODO: Implement full statutory interpretation logic
```

**Context:** Statutory interpretation is basic
**Impact:** AI could provide deeper legal analysis
**Effort:** 1 week
**Priority:** LOW (advanced feature)

**Recommendation:** Future enhancement, not critical

---

#### 7. **Full Citation Network Analysis** - `app/Services/DecisionCitationService.php:998`
```php
// TODO: Implement full citation network analysis logic
```

**Context:** Citation network analysis is partial
**Impact:** Missing advanced graph analytics
**Effort:** 1 week
**Priority:** LOW

**Recommendation:** Future enhancement when graph features mature

---

#### 8. **Full Case Analysis Logic** - `app/Services/CaseSearchService.php:831`
```php
// TODO: Implement full case analysis logic
```

**Context:** Case search works but analysis could be deeper
**Impact:** Quality improvement
**Effort:** 1 week
**Priority:** LOW

**Recommendation:** Future enhancement

---

### LOW Priority TODOs (Unimplemented features)

#### 9. **Graph Similarity Implementation** - `app/Services/Graph/GraphSimilarityLinker.php:297`
```php
return 0; // TODO: Implement when needed
```

**Context:** Graph similarity linking returns dummy value
**Impact:** Graph similarity features not working
**Effort:** 2-3 days
**Priority:** LOW (if graph similarity not actively used)

**Recommendation:** Implement only if attorneys request this feature

---

#### 10. **ZakonHr Progress Feedback** - `app/Services/ZakonHrIngestService.php:30`
```php
// TODO: For better progress feedback, consider:
```

**Context:** Ingestion progress reporting could be better
**Impact:** User experience during law ingestion
**Effort:** 4-6 hours
**Priority:** LOW

**Recommendation:** Nice to have, not critical

---

### TEST TODOs (3 items)

#### 11-13. **Livewire Component Features** - `tests/Feature/Livewire/UnifiedSearchTest.php`
```php
// Line 385: TODO: Implement when component has PDF export
// Line 442: TODO: Implement when component has recent searches feature
// Line 459: TODO: Implement when component has recent searches feature
```

**Context:** Tests are placeholders for unimplemented UI features
**Impact:** Missing features in search component
**Effort:** 1-2 days total
**Priority:** LOW (UI enhancements)

**Recommendation:** Implement based on user feedback

---

## ⚠️ Deprecated Classes & Methods (17 items)

### DEPRECATED CLASSES (3 classes)

#### 1. **AutonomousResearchAgent** - `app/Agents/AutonomousResearchAgent.php`
```php
/**
 * @deprecated This class is deprecated. Use \App\Services\ResearchOrchestrator instead.
 * The ResearchOrchestrator provides the same research capabilities with a cleaner,
 * service-oriented architecture.
 */
class AutonomousResearchAgent
```

**Status:** DEPRECATED - Use `ResearchOrchestrator` instead
**Migration:** Use `app(ResearchOrchestrator::class)->research()` instead of agent
**Used By:** Agent API endpoints, research jobs
**Impact:** HIGH - Core research functionality

**Action Required:**
1. Update all calls to use `ResearchOrchestrator`
2. Add deprecation warning to API responses
3. Remove after 1 month grace period

**Deprecated Methods (3):**
- `execute()` → Use `ResearchOrchestrator::research()`
- `executeAsync()` → Use `ResearchOrchestrator::research()` with queue
- `executeOnDemand()` → Use `ResearchOrchestrator::research()`

---

#### 2. **GraphRagService** - `app/Services/GraphRagService.php`
```php
/**
 * @deprecated Use GraphRagOrchestrator directly instead
 * @see \App\Services\Graph\GraphRagOrchestrator
 */
class GraphRagService
```

**Status:** DEPRECATED - Use `GraphRagOrchestrator` instead
**Migration:** Use `app(GraphRagOrchestrator::class)` instead
**Used By:** Graph sync operations
**Impact:** MEDIUM - Graph database operations

**Action Required:**
1. Update all calls to use `GraphRagOrchestrator`
2. Remove wrapper after migration complete

**Deprecated Methods (10):**
- `syncLaw()` → Use `GraphRagOrchestrator::syncLaw()`
- `syncCase()` → Use `GraphRagOrchestrator::syncCase()`
- `syncCourtDecision()` → Use `GraphRagOrchestrator::syncCourtDecision()`
- `syncTextract()` → Use `GraphRagOrchestrator::syncTextract()`
- `syncTextractJob()` → Use `GraphRagOrchestrator::syncTextractJob()`
- `syncDocument()` → Use `GraphRagOrchestrator::syncDocument()`
- `syncAllLaws()` → Use `GraphRagOrchestrator::syncAllLaws()`
- `syncAllCases()` → Use `GraphRagOrchestrator::syncAllCases()`
- `syncAllCourtDecisions()` → Use `GraphRagOrchestrator::syncAllCourtDecisions()`
- `syncAllTextractJobs()` → Use `GraphRagOrchestrator::syncAllTextractJobs()`

---

#### 3. **AgentToolbox** - `app/Services/AgentToolbox.php`
```php
/**
 * @deprecated Use LawSearchService, CaseSearchService, or DecisionSearchService directly
 */
class AgentToolbox
```

**Status:** DEPRECATED - Use specific search services instead
**Migration:** Use dedicated search services
**Used By:** Agents, search operations
**Impact:** MEDIUM - Agent tooling

**Action Required:**
1. Update agents to use specific services
2. Remove toolbox wrapper

**Deprecated Methods (6):**
- `searchLaws()` → Use `LawSearchService::vectorSearch()`
- `searchCases()` → Use `CaseSearchService::vectorSearch()`
- `searchDecisions()` → Use `DecisionSearchService::vectorSearch()`
- `lookupLawByNumber()` → Use `LawSearchService::lookupByNumber()`
- `lookupDecisionByCriteria()` → Use `DecisionSearchService::lookupByCriteria()`

---

## 📊 Technical Debt Statistics

### By Priority

| Priority | Count | % of Total |
|----------|-------|------------|
| HIGH | 2 TODOs (1 completed) | 18% |
| MEDIUM | 5 TODOs | 45% |
| LOW | 4 TODOs | 36% |
| **Total** | **11 TODOs** | **100%** |

### By Category

| Category | Count |
|----------|-------|
| Incomplete Features | 8 TODOs (1 completed) |
| Test Placeholders | 3 TODOs |
| Deprecated Classes | 3 classes |
| Deprecated Methods | 14 methods |
| **Total** | **28 items (1 completed)** |

---

## 🎯 Recommended Actions

### Immediate (This Week)

**None blocking for personal use** - All deprecated code still works

### Short-term (This Month)

1. ~~**Implement Court Tracking** (6 hours)~~ ✅ COMPLETED 2025-11-19
   - ~~Add value for attorneys~~
   - ~~File: `app/Console/Commands/Graph/AnalyzeTopicTrendsCommand.php:144`~~

2. **Implement pgvector Similarity Search** (1-2 days)
   - Improves agent memory
   - File: `app/Services/FederatedMemoryService.php:212`

3. **Add PDF Report Generation** (1-2 days)
   - Professional reports for attorneys
   - File: `app/Console/Commands/Graph/DetectOutliersCommand.php:98`

### Long-term (Next Quarter)

4. **Migrate from Deprecated Classes** (1 week total)
   - Create migration guide
   - Update all callers
   - Add deprecation warnings
   - Remove old code after grace period

5. **Implement Remaining TODOs** (2-3 weeks)
   - Based on user feedback
   - Prioritize most requested features

---

## 🔄 Migration Guide for Deprecated Code

### 1. Migrating from AutonomousResearchAgent

**OLD CODE:**
```php
use App\Agents\AutonomousResearchAgent;

$agent = new AutonomousResearchAgent();
$result = $agent->execute($objective, $context);
```

**NEW CODE:**
```php
use App\Services\ResearchOrchestrator;

$orchestrator = app(ResearchOrchestrator::class);
$result = $orchestrator->research(
    query: $objective,
    options: [
        'max_iterations' => 5,
        'quality_threshold' => 85,
    ]
);
```

---

### 2. Migrating from GraphRagService

**OLD CODE:**
```php
use App\Services\GraphRagService;

$graphRag = app(GraphRagService::class);
$graphRag->syncLaw($law);
```

**NEW CODE:**
```php
use App\Services\Graph\GraphRagOrchestrator;

$orchestrator = app(GraphRagOrchestrator::class);
$orchestrator->syncLaw($law);
```

---

### 3. Migrating from AgentToolbox

**OLD CODE:**
```php
use App\Services\AgentToolbox;

$toolbox = new AgentToolbox();
$laws = $toolbox->searchLaws('proportionality');
```

**NEW CODE:**
```php
use App\Services\LawSearchService;

$lawSearch = app(LawSearchService::class);
$laws = $lawSearch->vectorSearch('proportionality');
```

---

## 📝 Notes

### Why Deprecations Exist

The deprecated classes represent the evolution from monolithic agents to service-oriented architecture:

- **AutonomousResearchAgent** → **ResearchOrchestrator**: Better separation of concerns, more testable
- **GraphRagService** → **GraphRagOrchestrator**: Cleaner orchestration pattern
- **AgentToolbox** → Specific search services: Single responsibility principle

### Should You Migrate Now?

**For personal use (2 users): NO, not urgent**

Reasons:
- ✅ Deprecated code still works perfectly
- ✅ No performance impact
- ✅ No security risk
- ✅ Migration can wait until you have time

**When to migrate:**
- If you encounter bugs in deprecated code
- If you need features only in new services
- If you're adding new features (use new services)
- Before scaling to more users

---

## 🔍 How to Find Technical Debt

```bash
# Find all TODOs
grep -rn "TODO\|FIXME\|XXX\|HACK" app/ --include="*.php"

# Find all deprecated code
grep -rn "@deprecated" app/ --include="*.php"

# Count technical debt
grep -r "@deprecated" app/ | wc -l
grep -r "TODO" app/ | wc -l
```

---

## 📅 Review Schedule

- **Weekly:** Check for new TODOs introduced
- **Monthly:** Review and prioritize technical debt
- **Quarterly:** Plan migration sprints for deprecated code

---

**Last Updated:** 2025-11-11
**Next Review:** 2025-12-11
**Status:** For personal use, technical debt is low priority and non-blocking
