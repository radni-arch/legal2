# Sprints 1 & 2 Testing Guide
## AI Legal War Machine - 7 Components Completed

**Date:** November 18, 2025
**Status:** Ready for Testing
**Components:** 7 production-ready components

---

## Quick Start Testing Commands

### Run All Dusk Tests
```bash
# Run all tests
php artisan dusk

# Run with screenshots on failure
php artisan dusk --screenshots

# Run specific component
php artisan dusk --filter=FeedbackDashboard
php artisan dusk --filter=LearningOpportunityManager
php artisan dusk --filter=TranscriptPreviewer
php artisan dusk --filter=VectorStoreManager
php artisan dusk --filter=CircuitBreakerMonitor
php artisan dusk --filter=FederatedMemorySearch
php artisan dusk --filter=TopicAnalyzer
```

### Clear Caches Before Testing
```bash
php artisan cache:clear
php artisan view:clear
php artisan config:clear
npm run build
```

---

## Component Testing Checklist

### ✅ Sprint 1: Critical Components

#### 1. FeedbackDashboard
**Priority:** CRITICAL
**Completion:** 20% → 100%
**Dusk Selectors:** 48+
**Test Scenarios:** 30+

**Manual Testing Checklist:**
- [ ] Visit `/feedback-dashboard` (or appropriate route)
- [ ] Click Refresh button - verify loading spinner appears
- [ ] Verify button text changes to "Refreshing..."
- [ ] Verify button is disabled during operation
- [ ] Check stat cards have gradient backgrounds (blue, amber, green, purple)
- [ ] Hover over stat cards - verify lift effect and shadow enhancement
- [ ] Test on mobile (375px width) - verify single column layout
- [ ] Test on tablet (768px width) - verify 2-column layout
- [ ] Test on desktop (1024px width) - verify 4-column layout
- [ ] Verify empty states show when no data
- [ ] Check dark mode if enabled

**Key Dusk Selectors to Test:**
```php
'@refresh-button'
'@stat-card-total'
'@stat-card-pending'
'@stat-card-reviewed'
'@stat-card-completion'
'@loading-overlay'
```

**Automated Test Example:**
```php
public function test_refresh_shows_loading_state()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/feedback-dashboard')
            ->click('@refresh-button')
            ->assertAttribute('@refresh-button', 'disabled', 'true')
            ->assertSee('Refreshing...')
            ->waitUntilMissing('@loading-overlay', 10);
    });
}
```

---

#### 2. LearningOpportunityManager
**Priority:** CRITICAL
**Completion:** 25% → 100%
**Dusk Selectors:** 38
**Test Scenarios:** 30+

**Manual Testing Checklist:**
- [ ] Visit `/learning-opportunities` (or appropriate route)
- [ ] Click "Provide Feedback" button - verify modal opens with animation
- [ ] Verify modal fades in smoothly (300ms)
- [ ] Click outside modal or ESC key - verify modal closes
- [ ] Submit feedback - verify button shows spinner and "Submitting..."
- [ ] Verify confirmation dialog appears before submit
- [ ] Click delete - verify confirmation dialog
- [ ] Filter opportunities by type - verify loading state
- [ ] Hover over opportunity cards - verify lift and shadow effects
- [ ] Test on mobile - verify single column layout

**Key Dusk Selectors to Test:**
```php
'@create-opportunity-button'
'@opportunity-{id}'          // ID-based, not index!
'@feedback-modal'
'@submit-feedback-btn'
'@delete-opportunity-{id}'
```

**Automated Test Example:**
```php
public function test_modal_opens_with_animation()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->pause(50) // Mid-animation
            ->assertVisible('@feedback-modal')
            ->pause(300) // Full animation
            ->assertAttribute('@feedback-modal', 'x-show', 'true');
    });
}
```

**IMPORTANT:** Use opportunity **ID** not array index in selectors!

---

#### 3. TranscriptPreviewer
**Priority:** CRITICAL
**Completion:** 30% → 100%
**Dusk Selectors:** 46+
**Test Scenarios:** 80+

**Manual Testing Checklist:**
- [ ] Visit transcript previewer page
- [ ] Click timeline markers - verify segments load with loading state
- [ ] Click Export button - verify loading spinner and "Exporting..." text
- [ ] Verify file downloads successfully
- [ ] Hover over timeline markers - verify scale effect (1.25x)
- [ ] Test timeline scrubber - verify 3D gradient effect
- [ ] Verify forensic panel shows loading overlay during operations
- [ ] Test segment list loading overlay
- [ ] Navigate between segments - verify loading indicators
- [ ] Test on mobile - verify timeline remains functional

**Key Dusk Selectors to Test:**
```php
'@timeline-scrubber'
'@segment-{index}'
'@export-button'
'@forensic-panel'
'@forensic-loading-overlay'
'@segments-loading-overlay'
```

**Automated Test Example:**
```php
public function test_export_button_shows_loading()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
            ->click('@export-button')
            ->assertAttribute('@export-button', 'disabled', 'true')
            ->assertSee('Exporting...')
            ->pause(2000); // Allow export to complete
    });
}
```

---

### ✅ Sprint 2: High Priority Components

#### 4. VectorStoreManager
**Priority:** HIGH
**Completion:** 65% → 100%
**Dusk Selectors:** 43+
**Test Scenarios:** 45+

**Manual Testing Checklist:**
- [ ] Visit `/vectors/manage` (or appropriate route)
- [ ] Click store selection button - verify loading spinner
- [ ] Verify button text changes to "Loading..."
- [ ] Click Refresh Stats - verify loading state
- [ ] Click pagination (Next/Prev) - verify loading states
- [ ] Verify document table shows loading overlay during operations
- [ ] Click Preview document - verify modal opens with fade animation
- [ ] Click Reindex/Delete in table - verify loading indicators
- [ ] Test modal close with animation
- [ ] Test on mobile (480px, 768px) - verify responsive layout

**Key Dusk Selectors to Test:**
```php
'@select-store-laws'
'@refresh-stats-button'
'@document-table'
'@preview-modal'
'@reindex-document-{id}'
'@next-page-button'
```

**Automated Test Example:**
```php
public function test_store_selection_shows_loading()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vectors/manage')
            ->click('@select-store-laws')
            ->assertAttribute('@select-store-laws', 'disabled', 'true')
            ->assertSee('Loading...')
            ->waitFor('@document-table', 10);
    });
}
```

---

#### 5. CircuitBreakerMonitor
**Priority:** HIGH (CRITICAL Reset operations!)
**Completion:** 55% → 100%
**Dusk Selectors:** 40+
**Test Scenarios:** 35+

**Manual Testing Checklist:**
- [ ] Visit `/circuit-breaker-monitor` (or appropriate route)
- [ ] **CRITICAL:** Click Reset Circuit button - MUST show loading!
- [ ] Verify Reset button shows spinner and "Resetting..." text
- [ ] Verify Reset button is disabled during operation
- [ ] Check status cards show loading overlay during auto-refresh
- [ ] Verify events table shows loading overlay during reset
- [ ] Check gradient backgrounds on circuit cards
- [ ] Hover over circuit cards - verify lift effect
- [ ] Wait for auto-refresh (5s polling) - verify indicator changes
- [ ] Test on mobile (768px, 1024px) - verify grid layout changes

**Key Dusk Selectors to Test:**
```php
'@reset-circuit-{service}'    // CRITICAL!
'@circuit-card-{service}'
'@circuit-status-{service}'
'@events-table'
'@status-loading-overlay'
```

**Automated Test Example (CRITICAL):**
```php
public function test_reset_circuit_shows_loading()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/circuit-breaker-monitor')
            ->click('@reset-circuit-openai')
            ->assertAttribute('@reset-circuit-openai', 'disabled', 'true')
            ->assertVisible('@reset-circuit-openai svg.animate-spin')
            ->assertSee('Resetting...')
            ->waitUntilMissing('@reset-circuit-openai svg.animate-spin', 10);
    });
}
```

---

#### 6. FederatedMemorySearch
**Priority:** HIGH
**Completion:** 40% → 100% (COMPLETE REDESIGN)
**Dusk Selectors:** 18+ base
**Test Scenarios:** 40+

**Manual Testing Checklist:**
- [ ] Visit `/federated-memory` (or appropriate route)
- [ ] Verify page has blue/purple gradient background
- [ ] Click Search button - verify loading overlay on results
- [ ] Verify search button shows spinner and "Searching..."
- [ ] Click Reset button - verify loading spinner and "Resetting..."
- [ ] Verify result cards have gradient backgrounds
- [ ] Hover over result cards - verify lift effect and shadow enhancement
- [ ] Check all badges have gradient backgrounds
- [ ] Verify empty state has gradient icon and helpful message
- [ ] Test dark mode - verify all elements have dark variants
- [ ] Test on mobile - verify controls stack vertically

**Key Dusk Selectors to Test:**
```php
'@search-button'
'@reset-button'
'@results-list'
'@result-{index}'
'@empty-state'
```

**Automated Test Example:**
```php
public function test_search_shows_loading_overlay()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory')
            ->type('@search-input', 'test query')
            ->click('@search-button')
            ->waitFor('.absolute.inset-0.bg-gray-900', 1)
            ->assertSee('Searching memories...')
            ->waitUntilMissing('.absolute.inset-0.bg-gray-900', 10);
    });
}
```

---

#### 7. TopicAnalyzer
**Priority:** HIGH
**Completion:** 55% → 100%
**Dusk Selectors:** 72
**Test Scenarios:** 70+

**Manual Testing Checklist:**
- [ ] Visit `/topics-demo` (or appropriate route)
- [ ] Click between tabs - verify loading states with wire:target
- [ ] Verify tab text changes to "Loading..." during switch
- [ ] Click Analyze button - verify shows "Analyzing..." with spinner
- [ ] Verify analyze button has wire:target="analyzeCase"
- [ ] Click Statistics button - verify wire:target="getStatistics"
- [ ] Click Compare button - verify wire:target="compareRegions"
- [ ] Click Reset/Clear buttons - verify loading states
- [ ] Verify all result panels have loading overlays
- [ ] Check gradient backgrounds on all tabs and buttons
- [ ] Hover over buttons - verify scale and shadow effects
- [ ] Test on mobile - verify responsive layout

**Key Dusk Selectors to Test:**
```php
'@tab-analyze'
'@tab-statistics'
'@tab-compare'
'@analyze-button'
'@get-statistics-button'
'@compare-regions-button'
```

**Automated Test Example:**
```php
public function test_analyze_button_has_wire_target()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@analyze-button')
            ->assertAttribute('@analyze-button', 'disabled', 'true')
            ->assertSee('Analyzing...')
            ->pause(100)
            ->assertScript('document.querySelector("[dusk=analyze-button]").getAttribute("wire:target") === "analyzeCase"');
    });
}
```

---

## Cross-Component Testing

### 1. Loading State Consistency
Test that ALL components follow the same loading pattern:
- [ ] Button shows spinner during operation
- [ ] Button text changes (e.g., "Submit" → "Submitting...")
- [ ] Button is disabled (`disabled="true"`)
- [ ] Cursor changes to `not-allowed`
- [ ] Loading state clears after operation completes

### 2. CSS Consistency
Verify design system compliance:
- [ ] All gradients use similar color schemes
- [ ] All hover effects use transform + shadow
- [ ] All transitions are 200-300ms
- [ ] All focus rings are 2px with proper color
- [ ] All disabled states show 50-60% opacity

### 3. Mobile Responsiveness
Test all components at these breakpoints:
- [ ] **375px** - iPhone SE (smallest)
- [ ] **640px** - Small tablet
- [ ] **768px** - Tablet
- [ ] **1024px** - Desktop
- [ ] **1280px** - Large desktop

### 4. Accessibility Testing
Use keyboard and screen reader:
- [ ] Tab through all interactive elements
- [ ] Verify focus indicators are visible
- [ ] Press Enter/Space on buttons
- [ ] Verify ESC closes modals
- [ ] Use screen reader (NVDA/JAWS/VoiceOver)
- [ ] Verify ARIA labels are present

---

## Browser Testing Matrix

| Browser | Version | Status |
|---------|---------|--------|
| Chrome | Latest | ☐ |
| Firefox | Latest | ☐ |
| Safari | Latest | ☐ |
| Edge | Latest | ☐ |
| Mobile Safari | iOS 14+ | ☐ |
| Mobile Chrome | Android | ☐ |

---

## Performance Testing

### Lighthouse Scores (Target: 90+)
Run Lighthouse on each component page:
```bash
# Install Lighthouse
npm install -g lighthouse

# Run audit
lighthouse http://localhost:8000/feedback-dashboard --view
lighthouse http://localhost:8000/learning-opportunities --view
# ... etc for each component
```

**Target Metrics:**
- Performance: ≥ 90
- Accessibility: ≥ 95
- Best Practices: ≥ 90
- SEO: ≥ 90

### Animation Performance
Check animations run at 60fps:
- Open Chrome DevTools
- Go to Performance tab
- Record while interacting with component
- Verify FPS stays at 60

---

## Visual Regression Testing

### Percy.io Setup (Optional)
```bash
# Install Percy
npm install --save-dev @percy/cli @percy/puppeteer

# Run Percy snapshots
npx percy exec -- php artisan dusk
```

### Manual Visual Testing
Take screenshots of each component:
- [ ] Default state
- [ ] Loading state
- [ ] Error state
- [ ] Empty state
- [ ] Hover state
- [ ] Mobile view
- [ ] Dark mode (if applicable)

---

## Known Issues & Edge Cases

### FeedbackDashboard
- ⚠️ If no feedback exists, empty state should show
- ⚠️ Large numbers (>999) should format with commas

### LearningOpportunityManager
- ⚠️ **IMPORTANT:** Selectors use opportunity ID, not array index
- ⚠️ Modal may take 300ms to fully animate (don't assert immediately)

### TranscriptPreviewer
- ⚠️ Timeline markers require transcript data to be loaded
- ⚠️ Export may take 2-3 seconds for large files

### VectorStoreManager
- ⚠️ Store switching may take 1-2 seconds depending on data size
- ⚠️ Preview modal content may be large (test scrolling)

### CircuitBreakerMonitor
- ⚠️ Auto-refresh runs every 5 seconds (wire:poll.5s)
- ⚠️ Reset operation may take several seconds

### FederatedMemorySearch
- ⚠️ Search may be slow if database has many memories
- ⚠️ Empty results should show helpful message

### TopicAnalyzer
- ⚠️ Analysis operations may take 3-5 seconds
- ⚠️ Verify wire:target is present (was the main missing piece!)

---

## Test Data Setup

### Create Test Data
```bash
# Run seeders
php artisan db:seed

# Or create specific test data
php artisan tinker
>>> App\Models\LearningOpportunity::factory(10)->create()
>>> App\Models\VectorDocument::factory(20)->create()
```

### Reset Test Database
```bash
php artisan migrate:fresh --seed
```

---

## Debugging Failed Tests

### Common Issues

**1. Element Not Found**
```php
// Add explicit waits
$browser->waitFor('@element', 10)
```

**2. Animation Timing**
```php
// Pause for animation to complete
$browser->pause(300) // 300ms for most animations
```

**3. Loading State Not Clearing**
```php
// Increase timeout
$browser->waitUntilMissing('@loading-overlay', 15) // 15 seconds
```

**4. Modal Not Visible**
```php
// Check for x-cloak
$browser->assertMissing('.x-cloak')
```

### Debug Commands
```bash
# Run single test with debug output
php artisan dusk --filter=test_name --debug

# Keep browser open on failure
DUSK_HEADLESS_DISABLED=1 php artisan dusk

# Take screenshot manually
$browser->screenshot('debug-screenshot');
```

---

## CI/CD Integration

### GitHub Actions Example
```yaml
name: Dusk Tests

on: [push, pull_request]

jobs:
  dusk:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - name: Install Dependencies
        run: |
          composer install
          npm install
          npm run build

      - name: Run Dusk Tests
        run: php artisan dusk

      - name: Upload Screenshots
        if: failure()
        uses: actions/upload-artifact@v3
        with:
          name: screenshots
          path: tests/Browser/screenshots
```

---

## Success Criteria

All tests pass when:
- ✅ All Dusk tests pass (305+ selectors tested)
- ✅ All manual testing checklists completed
- ✅ All browsers tested successfully
- ✅ Lighthouse scores ≥ 90
- ✅ No console errors
- ✅ Animations run smoothly at 60fps
- ✅ Mobile responsiveness verified
- ✅ Accessibility compliance confirmed

---

## Next Steps After Testing

### If All Tests Pass ✅
1. Mark components as production-ready
2. Proceed to Sprint 3 (4 Medium-High components)
3. Continue with remaining sprints

### If Issues Found ❌
1. Document all issues in GitHub issues
2. Prioritize by severity (Critical, High, Medium, Low)
3. Fix critical issues immediately
4. Create backlog for non-critical issues
5. Re-test after fixes

---

## Documentation References

### Testing Guides (per component):
1. `tests/Browser/FEEDBACK_DASHBOARD_TESTING_GUIDE.md`
2. `tests/Browser/LEARNING_OPPORTUNITY_MANAGER_TESTING_GUIDE.md`
3. `tests/Browser/Documentation/TranscriptPreviewer-Testing-Guide.md`
4. `tests/Browser/VectorStoreManagerTest.md`
5. `tests/Browser/CIRCUIT_BREAKER_MONITOR_TESTING_GUIDE.md`
6. `tests/Browser/TESTING_FEDERATED_MEMORY_SEARCH.md`
7. `tests/Browser/TopicAnalyzer_Testing_Guide.md`

### Implementation Summaries:
- Individual component documentation in project root
- FRONTEND_IMPROVEMENT_SPRINTS.md (sprint plan)
- FRONTEND_TESTING_BLUEPRINT.md (overall testing strategy)

---

## Contact & Support

**Questions about testing?** Refer to component-specific testing guides listed above.

**Found a bug?** Create a GitHub issue with:
- Component name
- Steps to reproduce
- Expected vs actual behavior
- Screenshots (if visual issue)
- Browser & version

---

**Testing Status:** Ready to begin ✅
**Components:** 7 production-ready
**Dusk Selectors:** 305+
**Test Scenarios:** 330+

**Good luck with testing! 🚀**
