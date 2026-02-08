# Frontend Improvement Sprints - TDD Approach
## AI Legal War Machine - Parallel Agent Execution Plan

**Date:** November 18, 2025
**Total Components Remaining:** 15 components
**Already Completed:** 5 components (EoglasnaMonitoring, OpenAIVectorManager, OpenAILogViewer, CollaborationDashboard, AgentCollaborationViewer)

---

## Sprint Organization Strategy

Each sprint is designed for **parallel execution** using TDD (Test-Driven Development) approach:

1. **Agents work in parallel** on different components
2. **Each agent follows TDD**: Write/update tests first, then implement
3. **All agents add Dusk selectors** for future testing
4. **All agents document testing requirements** for QA team
5. **Each agent creates verification checklist** for their component

---

## SPRINT 1: Critical Dashboard Redesigns (3 components)
**Priority:** CRITICAL
**Estimated Time:** 1 day (parallel execution)
**Theme:** Complete redesigns with modern TALL stack patterns

### Agent 1A: FeedbackDashboard
**Status:** Critical (20% complete)
**Files:**
- `app/Http/Livewire/FeedbackDashboard.php`
- `resources/views/livewire/feedback-dashboard.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for refresh button loading state
- [ ] Write Dusk test for stat card hover effects
- [ ] Write Dusk test for mobile responsive grid
- [ ] Add `@pt` (Playwright) attributes for all interactive elements

**Dusk Selectors to Add:**
```php
dusk="refresh-button"
dusk="stat-card-{type}"
dusk="breakdown-card"
dusk="feedback-list"
```

**Implementation Tasks:**
1. Replace Bootstrap with Tailwind CSS
2. Add modern gradient backgrounds to stat cards
3. Implement wire:loading on Refresh button
4. Add loading overlay on stats during refresh
5. Create custom CSS for stat-card and breakdown-card classes
6. Add hover effects (transform, shadow)
7. Ensure mobile responsive (grid-cols-1 md:grid-cols-2 lg:grid-cols-4)

**Test Documentation:**
- Document modal interactions (if any)
- Document stat card click interactions
- Document refresh polling behavior
- Add accessibility testing notes (ARIA labels)

---

### Agent 1B: LearningOpportunityManager
**Status:** Critical (25% complete)
**Files:**
- `app/Http/Livewire/LearningOpportunityManager.php`
- `resources/views/livewire/learning-opportunity-manager.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for create opportunity button loading
- [ ] Write Dusk test for modal open/close animations
- [ ] Write Dusk test for opportunity list loading overlay
- [ ] Write Dusk test for delete confirmation dialog

**Dusk Selectors to Add:**
```php
dusk="create-opportunity-button"
dusk="opportunity-{id}"
dusk="opportunity-modal"
dusk="modal-close-button"
dusk="delete-opportunity-{id}"
dusk="edit-opportunity-{id}"
dusk="opportunity-list"
```

**Implementation Tasks:**
1. Add wire:loading to all buttons (Create, Edit, Delete)
2. Add wire:loading.attr="disabled" to prevent double-clicks
3. Implement modal fade-in/fade-out with Alpine.js
4. Add loading overlay to opportunities list
5. Modernize CSS with gradients and shadows
6. Add hover effects to opportunity cards
7. Implement mobile responsive layout

**Test Documentation:**
- Modal interaction testing requirements
- CRUD operation testing checklist
- Form validation testing scenarios
- Accessibility testing (keyboard navigation, screen readers)

---

### Agent 1C: TranscriptPreviewer
**Status:** Critical (30% complete)
**Files:**
- `app/Http/Livewire/TranscriptPreviewer.php`
- `resources/views/livewire/transcript-previewer.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for segment navigation loading
- [ ] Write Dusk test for timeline scrubbing
- [ ] Write Dusk test for forensic analysis panel
- [ ] Write Dusk test for export button loading

**Dusk Selectors to Add:**
```php
dusk="segment-{id}"
dusk="timeline-scrubber"
dusk="forensic-panel"
dusk="export-button"
dusk="segment-list"
dusk="play-segment-{id}"
```

**Implementation Tasks:**
1. Add wire:loading to all navigation buttons
2. Add wire:target to specific methods
3. Add wire:loading.attr="disabled"
4. Add loading overlay to segment list
5. Modernize CSS with gradients
6. Add hover effects to timeline segments
7. Ensure timeline visualization is smooth

**Test Documentation:**
- Timeline interaction testing
- Segment playback testing
- Forensic analysis feature testing
- Export functionality testing

---

## SPRINT 2: Manager Components (4 components)
**Priority:** HIGH
**Estimated Time:** 1 day (parallel execution)
**Theme:** Add comprehensive loading states to manager components

### Agent 2A: VectorStoreManager
**Status:** High (65% complete)
**Files:**
- `app/Http/Livewire/VectorStoreManager.php`
- `resources/views/livewire/vector-store-manager.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for store selection loading
- [ ] Write Dusk test for pagination loading
- [ ] Write Dusk test for document table loading overlay
- [ ] Write Dusk test for preview modal

**Dusk Selectors to Add:**
```php
dusk="select-store-{id}"
dusk="refresh-stats-button"
dusk="next-page-button"
dusk="prev-page-button"
dusk="reindex-document-{id}"
dusk="delete-document-{id}"
dusk="preview-document-{id}"
dusk="document-table"
dusk="preview-modal"
```

**Implementation Tasks:**
1. Add wire:loading to store selection buttons
2. Add wire:loading to Refresh Stats button
3. Add wire:loading to pagination buttons
4. Add wire:loading to reindex/delete buttons in table
5. Add wire:loading to preview buttons
6. Add loading overlay to document table
7. Add modal fade animations

**Test Documentation:**
- Store switching testing
- Pagination testing
- CRUD operations on documents
- Modal preview testing

---

### Agent 2B: CircuitBreakerMonitor
**Status:** High (55% complete)
**Files:**
- `app/Http/Livewire/CircuitBreakerMonitor.php`
- `resources/views/livewire/circuit-breaker-monitor.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for Reset Circuit button (CRITICAL)
- [ ] Write Dusk test for auto-refresh polling
- [ ] Write Dusk test for events table loading
- [ ] Write Dusk test for status badge updates

**Dusk Selectors to Add:**
```php
dusk="reset-circuit-{name}"
dusk="circuit-status-{name}"
dusk="events-table"
dusk="circuit-card-{name}"
dusk="failure-count-{name}"
```

**Implementation Tasks:**
1. Add wire:loading to Reset Circuit buttons (CRITICAL!)
2. Add wire:loading.attr="disabled" to Reset buttons
3. Add loading overlay to events table
4. Modernize CSS with gradients
5. Add loading skeleton for status cards
6. Add hover effects to circuit cards
7. Ensure wire:poll works smoothly

**Test Documentation:**
- Critical: Reset operation testing (must have loading feedback)
- Auto-refresh polling testing
- Status transition testing
- Event log testing

---

### Agent 2C: FederatedMemorySearch
**Status:** High (40% complete)
**Files:**
- `app/Http/Livewire/FederatedMemorySearch.php`
- `resources/views/livewire/federated-memory-search.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for search loading
- [ ] Write Dusk test for filter changes
- [ ] Write Dusk test for results list loading
- [ ] Write Dusk test for reset filters

**Dusk Selectors to Add:**
```php
dusk="search-input"
dusk="search-button"
dusk="reset-button"
dusk="agent-filter"
dusk="limit-input"
dusk="results-list"
dusk="result-{id}"
```

**Implementation Tasks:**
1. Complete CSS redesign with modern Tailwind
2. Add wire:loading to search button (already has some)
3. Add wire:loading to reset button
4. Add loading overlay to results list
5. Add gradients to result cards
6. Add skeleton loaders for results
7. Implement mobile responsive layout

**Test Documentation:**
- Search functionality testing
- Filter interaction testing
- Results rendering testing
- Empty state testing

---

### Agent 2D: TopicAnalyzer
**Status:** High (55% complete)
**Files:**
- `app/Http/Livewire/TopicAnalyzer.php`
- `resources/views/livewire/topic-analyzer.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for tab switching
- [ ] Write Dusk test for analyze button with wire:target
- [ ] Write Dusk test for reset/clear buttons
- [ ] Write Dusk test for results display

**Dusk Selectors to Add:**
```php
dusk="tab-{name}"
dusk="analyze-button"
dusk="reset-button"
dusk="clear-button"
dusk="results-panel"
dusk="visualization-{type}"
```

**Implementation Tasks:**
1. Add wire:target to all buttons
2. Add wire:loading to tab buttons
3. Add wire:loading to reset/clear buttons
4. Add loading overlays for results
5. Modernize CSS with gradients
6. Add hover effects
7. Ensure visualizations render smoothly

**Test Documentation:**
- Tab switching testing
- Analysis function testing
- Visualization rendering testing
- Reset/clear functionality testing

---

## SPRINT 3: Dashboard & Log Components (4 components)
**Priority:** MEDIUM-HIGH
**Estimated Time:** 0.5-1 day (parallel execution)
**Theme:** Add loading states to dashboards and logs

### Agent 3A: DecisionDiscoveryDashboard
**Status:** Medium-High (70% complete)
**Files:**
- `app/Http/Livewire/DecisionDiscoveryDashboard.php`
- `resources/views/livewire/decision-discovery-dashboard.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for reset button loading
- [ ] Write Dusk test for refresh stats loading
- [ ] Write Dusk test for preview buttons loading
- [ ] Write Dusk test for modal animations

**Dusk Selectors to Add:**
```php
dusk="reset-button"
dusk="refresh-stats-button"
dusk="preview-decision-{id}"
dusk="results-table"
dusk="preview-modal"
dusk="modal-close-button"
dusk="ingest-selected-button"
```

**Implementation Tasks:**
1. Add wire:loading to Reset, Refresh Stats buttons
2. Add wire:loading to Preview buttons
3. Add wire:loading to modal close button
4. Add loading overlay to results table
5. Add modal fade animations
6. Add loading skeleton for table

**Test Documentation:**
- Search workflow testing
- Preview modal testing
- Ingestion process testing
- Stats refresh testing

---

### Agent 3B: GraphDashboard
**Status:** Medium (60% complete)
**Files:**
- `app/Http/Livewire/GraphDashboard.php`
- `resources/views/livewire/graph-dashboard.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for tab switching loading
- [ ] Write Dusk test for panel content loading
- [ ] Write Dusk test for back to dashboard link

**Dusk Selectors to Add:**
```php
dusk="tab-{name}"
dusk="panel-{name}"
dusk="back-to-dashboard"
dusk="graph-content"
```

**Implementation Tasks:**
1. Add wire:loading to tab switching buttons
2. Add wire:target to tab methods
3. Add wire:loading.attr="disabled"
4. Add loading overlay when switching panels
5. Add loading indicator to Back to Dashboard link

**Test Documentation:**
- Tab switching testing
- Panel content loading testing
- Navigation testing

---

### Agent 3C: LaravelLogViewer
**Status:** Medium (80% complete - CSS EXCELLENT)
**Files:**
- `app/Http/Livewire/LaravelLogViewer.php`
- `resources/views/livewire/laravel-log-viewer.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for file selection loading
- [ ] Write Dusk test for download button
- [ ] Write Dusk test for delete button with confirmation
- [ ] Write Dusk test for refresh button

**Dusk Selectors to Add:**
```php
dusk="select-file-{name}"
dusk="download-button"
dusk="delete-button"
dusk="refresh-button"
dusk="clear-filters-button"
dusk="log-entries"
dusk="log-entry-{index}"
```

**Implementation Tasks:**
1. Add wire:loading to file selection buttons
2. Add wire:loading to download button
3. Add wire:loading to delete button
4. Add wire:loading to refresh button
5. Add loading overlay to log entries scrollable area
6. Keep existing excellent CSS!

**Test Documentation:**
- File selection testing
- Download functionality testing
- Delete with confirmation testing
- Auto-refresh polling testing

---

### Agent 3D: AnalyticsPanel
**Status:** Medium (60% complete)
**Files:**
- `app/Http/Livewire/AnalyticsPanel.php`
- `resources/views/livewire/analytics-panel.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for view switching
- [ ] Write Dusk test for refresh button
- [ ] Write Dusk test for data loading overlay

**Dusk Selectors to Add:**
```php
dusk="view-{name}"
dusk="refresh-button"
dusk="analytics-content"
dusk="stat-card-{type}"
```

**Implementation Tasks:**
1. Refactor from manual $loading to wire:loading
2. Add wire:loading to view switch buttons
3. Add wire:loading.attr="disabled"
4. Add loading overlays to data tables/grids
5. Add loading skeleton for data cards

**Test Documentation:**
- View switching testing
- Data refresh testing
- Statistics display testing

---

## SPRINT 4: Search & Response Viewers (2 components)
**Priority:** MEDIUM
**Estimated Time:** 0.5 day (parallel execution)
**Theme:** Polish search and viewer components

### Agent 4A: UnifiedSearch
**Status:** Medium (60% complete)
**Files:**
- `app/Http/Livewire/UnifiedSearch.php`
- `resources/views/livewire/unified-search.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for search loading (refactored to wire:loading)
- [ ] Write Dusk test for corpus toggle
- [ ] Write Dusk test for advanced options
- [ ] Write Dusk test for results rendering

**Dusk Selectors to Add:**
```php
dusk="search-input"
dusk="search-button"
dusk="corpus-toggle-{name}"
dusk="advanced-options-toggle"
dusk="results-list"
dusk="result-{id}"
dusk="export-button"
```

**Implementation Tasks:**
1. Refactor from @if($isSearching) to wire:loading
2. Use wire:loading.attr="disabled" instead of manual disabled
3. Modernize CSS with gradients
4. Add result card hover effects
5. Add loading overlay to results
6. Add fade-in animation for results
7. Improve mobile responsiveness

**Test Documentation:**
- Search functionality testing
- Corpus filtering testing
- Advanced options testing
- Export functionality testing

---

### Agent 4B: OpenAIResponsesViewer
**Status:** Medium (55% complete)
**Files:**
- `app/Http/Livewire/OpenAIResponsesViewer.php`
- `resources/views/livewire/openai-responses-viewer.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for refresh button
- [ ] Write Dusk test for filter inputs
- [ ] Write Dusk test for timeline scrolling

**Dusk Selectors to Add:**
```php
dusk="refresh-button"
dusk="filter-model"
dusk="filter-prompt"
dusk="timeline-list"
dusk="timeline-item-{id}"
```

**Implementation Tasks:**
1. Add wire:loading to refresh button
2. Add wire:loading.attr="disabled"
3. Add loading overlay to timeline list
4. Show loading state during filter debounce
5. Modernize CSS
6. Add timeline item animations
7. Add loading skeleton for timeline

**Test Documentation:**
- Refresh functionality testing
- Filter interaction testing
- Timeline rendering testing

---

## SPRINT 5: Quick Wins - Panel Components (3 components)
**Priority:** MEDIUM-LOW
**Estimated Time:** 2-3 hours (parallel execution)
**Theme:** Simple wire:target additions to nearly-complete components

### Agent 5A: LlmBrainPanel
**Status:** Medium (80% complete - CSS EXCELLENT)
**Files:**
- `app/Http/Livewire/LlmBrainPanel.php`
- `resources/views/livewire/llm-brain-panel.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for Execute button with wire:target
- [ ] Write Dusk test for Clear Results button
- [ ] Write Dusk test for example query buttons

**Dusk Selectors to Add:**
```php
dusk="execute-button"
dusk="clear-results-button"
dusk="example-query-{id}"
dusk="mode-selector"
dusk="results-panel"
```

**Implementation Tasks:**
1. Add wire:target="executeQuery" to Execute button
2. Add wire:loading to Clear Results button
3. Add wire:loading to example query buttons
4. Verify existing CSS is maintained

**Test Documentation:**
- Execute query testing
- Clear results testing
- Example query testing
- Mode switching testing

---

### Agent 5B: TemporalPanel
**Status:** Medium (80% complete - CSS EXCELLENT)
**Files:**
- `app/Http/Livewire/TemporalPanel.php`
- `resources/views/livewire/temporal-panel.blade.php`

**TDD Requirements:**
- [ ] Write Dusk test for Execute button with wire:target
- [ ] Write Dusk test for Clear Results button
- [ ] Write Dusk test for query type selector

**Dusk Selectors to Add:**
```php
dusk="execute-button"
dusk="clear-results-button"
dusk="query-type-{type}"
dusk="results-panel"
```

**Implementation Tasks:**
1. Add wire:target="executeQuery" to Execute button
2. Add wire:loading to Clear Results button
3. Add wire:loading to query type selector buttons
4. Verify existing CSS is maintained

**Test Documentation:**
- Execute query testing
- Clear results testing
- Query type switching testing

---

### Agent 5C: Final Polish & Consistency Check
**Files:** All components from Sprints 1-5

**TDD Requirements:**
- [ ] Cross-component consistency testing
- [ ] Design system compliance testing
- [ ] Accessibility audit testing

**Tasks:**
1. Review all Dusk selectors for consistency
2. Verify all components follow same loading state pattern
3. Check CSS consistency across all components
4. Verify mobile responsiveness across all
5. Document any remaining edge cases

**Test Documentation:**
- Create comprehensive smoke test suite
- Document regression testing requirements
- Create accessibility testing checklist

---

## Agent Execution Guidelines

### Each Agent Must:

1. **Follow TDD Approach:**
   ```
   1. Write/update Dusk tests FIRST
   2. Run tests (they should fail)
   3. Implement features
   4. Run tests (they should pass)
   5. Refactor if needed
   ```

2. **Add Dusk Selectors:**
   - Use `dusk="selector-name"` on ALL interactive elements
   - Follow naming convention: `{element-type}-{identifier}`
   - Examples: `dusk="save-button"`, `dusk="modal-close"`, `dusk="item-{id}"`

3. **Document Testing Requirements:**
   - Create a `TESTING_NOTES_{COMPONENT}.md` file
   - List all interactive elements
   - List all user workflows to test
   - List all edge cases
   - List accessibility requirements
   - List mobile testing requirements

4. **Use Standard Patterns:**
   - Loading state pattern (from FRONTEND_TESTING_BLUEPRINT.md)
   - Modal animation pattern
   - Table loading overlay pattern
   - Button gradient pattern

5. **Verify Before Completing:**
   - [ ] All Dusk selectors added
   - [ ] All tests written and passing
   - [ ] All loading states implemented
   - [ ] Mobile responsive verified
   - [ ] Accessibility checked (ARIA labels, keyboard navigation)
   - [ ] Documentation created

---

## Parallel Execution Strategy

### Sprint 1 (3 agents in parallel):
```
Agent 1A → FeedbackDashboard
Agent 1B → LearningOpportunityManager
Agent 1C → TranscriptPreviewer
```

### Sprint 2 (4 agents in parallel):
```
Agent 2A → VectorStoreManager
Agent 2B → CircuitBreakerMonitor
Agent 2C → FederatedMemorySearch
Agent 2D → TopicAnalyzer
```

### Sprint 3 (4 agents in parallel):
```
Agent 3A → DecisionDiscoveryDashboard
Agent 3B → GraphDashboard
Agent 3C → LaravelLogViewer
Agent 3D → AnalyticsPanel
```

### Sprint 4 (2 agents in parallel):
```
Agent 4A → UnifiedSearch
Agent 4B → OpenAIResponsesViewer
```

### Sprint 5 (3 agents in parallel):
```
Agent 5A → LlmBrainPanel
Agent 5B → TemporalPanel
Agent 5C → Final Polish
```

---

## Success Criteria

Each component must achieve:
- ✅ **100% Dusk selector coverage** on interactive elements
- ✅ **100% loading state coverage** on all actions
- ✅ **All tests written and passing**
- ✅ **Testing documentation complete**
- ✅ **Mobile responsive** (tested at 375px, 768px, 1024px)
- ✅ **Accessible** (ARIA labels, keyboard navigation)
- ✅ **Modern CSS** (gradients, hover effects, animations)

---

## Estimated Timeline

- **Sprint 1:** 1 day (3 components, parallel)
- **Sprint 2:** 1 day (4 components, parallel)
- **Sprint 3:** 1 day (4 components, parallel)
- **Sprint 4:** 0.5 day (2 components, parallel)
- **Sprint 5:** 0.5 day (3 components, parallel)

**Total:** 4 days with parallel execution

---

**Ready for agent swarm dispatch!**
