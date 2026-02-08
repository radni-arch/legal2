# TopicAnalyzer Component - Testing Guide

## Overview
This document provides comprehensive testing requirements for the TopicAnalyzer Livewire component following TDD principles.

**Component Location:**
- PHP: `/home/user/ai-legal-war-machine/app/Http/Livewire/TopicAnalyzer.php`
- Blade: `/home/user/ai-legal-war-machine/resources/views/livewire/topic-analyzer.blade.php`

**Route:** `/topics-demo`

---

## Critical Improvements Made

### 1. Wire:target Attributes (MAIN PRIORITY - COMPLETED)
All interactive elements now have proper `wire:target` attributes to ensure loading states are scoped correctly:

**Tab Buttons:**
- `wire:target="setTab"` - All three tab buttons

**Action Buttons:**
- `wire:target="analyzeCase"` - Analyze button
- `wire:target="getStatistics"` - Get Statistics button
- `wire:target="compareRegions"` - Compare Regions button

**Reset Buttons:**
- `wire:target="resetAnalysis"` - Clear Analysis button
- `wire:target="resetStatistics"` - Clear Statistics button
- `wire:target="resetComparison"` - Clear Comparison button

### 2. Loading States
- All buttons have `wire:loading.attr="disabled"` with proper `wire:target`
- Loading overlays added to all result panels
- Animated spinners with proper targeting
- Loading text changes during operations

### 3. Visual Enhancements
- Gradient backgrounds on all panels and buttons
- Hover effects with scale and shadow transitions
- Smooth transitions (duration-200 to duration-300)
- Enhanced shadows (shadow-md, shadow-lg, shadow-xl)
- Gradient text for headings using `bg-clip-text`

---

## Complete Dusk Selector Reference

### Global Elements
- `dusk="header"` - Page header
- `dusk="topic-selector"` - Topic selection dropdown
- `dusk="error-message"` - Error message container
- `dusk="tabs-navigation"` - Tabs navigation container

### Tab Navigation
- `dusk="tab-analyze"` - Analyze Case tab button
- `dusk="tab-statistics"` - Statistics tab button
- `dusk="tab-compare"` - Compare Regions tab button

### Analyze Tab (activeTab === 'analyze')

#### Container
- `dusk="analyze-tab-content"` - Main container

#### Form Inputs
- `dusk="case-selector"` - Case selection dropdown
- `dusk="drug-type-selector"` - Drug type dropdown
- `dusk="amount-input"` - Amount input field
- `dusk="amount-error"` - Amount validation error
- `dusk="charged-as-selector"` - Charged as dropdown
- `dusk="evidence-checkboxes"` - Evidence checkboxes container
- `dusk="evidence-scales"` - Scales checkbox
- `dusk="evidence-baggies"` - Baggies checkbox
- `dusk="evidence-large_cash"` - Large cash checkbox
- `dusk="evidence-phone_records"` - Phone records checkbox

#### Action Buttons
- `dusk="analyze-button"` - Analyze case button
- `dusk="clear-analysis-button"` - Clear results button

#### Results Panel
- `dusk="analysis-results-panel"` - Results container
- `dusk="no-results-message"` - No results placeholder
- `dusk="overcharge-detection"` - Overcharge detection panel
- `dusk="overcharge-status"` - Overcharge status text
- `dusk="overcharge-severity"` - Severity score
- `dusk="threshold-analysis"` - Threshold analysis panel
- `dusk="actual-amount"` - Actual amount value
- `dusk="threshold-amount"` - Threshold amount value
- `dusk="threshold-percentage"` - Percentage of threshold
- `dusk="threshold-analysis-text"` - Analysis explanation
- `dusk="overcharging-patterns"` - Patterns container
- `dusk="pattern-{index}"` - Individual pattern (dynamic)
- `dusk="defense-strategies"` - Defense strategies container
- `dusk="strategy-{index}"` - Individual strategy (dynamic)
- `dusk="recommended-charge"` - Recommended charge panel
- `dusk="recommended-charge-value"` - Recommended charge text

### Statistics Tab (activeTab === 'statistics')

#### Container
- `dusk="statistics-tab-content"` - Main container

#### Form Inputs
- `dusk="stats-year-input"` - Year input field
- `dusk="stats-year-error"` - Year validation error
- `dusk="stats-region-selector"` - Region dropdown

#### Action Buttons
- `dusk="get-statistics-button"` - Get statistics button
- `dusk="clear-statistics-button"` - Clear results button

#### Results Panel
- `dusk="statistics-results-panel"` - Results container
- `dusk="no-statistics-message"` - No results placeholder
- `dusk="total-cases"` - Total cases panel
- `dusk="total-cases-count"` - Total cases number
- `dusk="overcharged-cases"` - Overcharged cases panel
- `dusk="overcharged-count"` - Overcharged count
- `dusk="overcharge-percentage"` - Overcharge percentage
- `dusk="by-drug-type"` - By drug type panel
- `dusk="drug-type-{drug}"` - Individual drug type (dynamic)
- `dusk="alarming-findings"` - Alarming findings panel
- `dusk="finding-{index}"` - Individual finding (dynamic)
- `dusk="stats-status"` - Status information

### Compare Tab (activeTab === 'compare')

#### Container
- `dusk="compare-tab-content"` - Main container

#### Form Inputs
- `dusk="region1-selector"` - Region 1 dropdown
- `dusk="region2-selector"` - Region 2 dropdown
- `dusk="comparison-year-input"` - Year input field
- `dusk="comparison-year-error"` - Year validation error

#### Action Buttons
- `dusk="compare-regions-button"` - Compare regions button
- `dusk="clear-comparison-button"` - Clear results button

#### Results Panel
- `dusk="comparison-results-panel"` - Results container
- `dusk="no-comparison-message"` - No results placeholder
- `dusk="worse-region"` - Worse region panel
- `dusk="worse-region-name"` - Worse region name
- `dusk="worse-region-analysis"` - Worse region analysis
- `dusk="region1-stats"` - Region 1 statistics panel
- `dusk="region1-name"` - Region 1 name
- `dusk="region1-percentage"` - Region 1 percentage
- `dusk="region2-stats"` - Region 2 statistics panel
- `dusk="region2-name"` - Region 2 name
- `dusk="region2-percentage"` - Region 2 percentage
- `dusk="comparison-analysis"` - Analysis panel
- `dusk="comparison-analysis-text"` - Analysis text

---

## Testing Requirements

### 1. Tab Switching Tests

#### Test: Tab Navigation Works
```php
public function test_can_switch_between_tabs()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            // Verify default tab
            ->assertPresent('@analyze-tab-content')
            ->assertMissing('@statistics-tab-content')
            ->assertMissing('@compare-tab-content')

            // Switch to statistics
            ->click('@tab-statistics')
            ->waitFor('@statistics-tab-content')
            ->assertPresent('@statistics-tab-content')
            ->assertMissing('@analyze-tab-content')

            // Switch to compare
            ->click('@tab-compare')
            ->waitFor('@compare-tab-content')
            ->assertPresent('@compare-tab-content')
            ->assertMissing('@statistics-tab-content')

            // Back to analyze
            ->click('@tab-analyze')
            ->waitFor('@analyze-tab-content')
            ->assertPresent('@analyze-tab-content');
    });
}
```

#### Test: Tab Loading States
```php
public function test_tabs_show_loading_state_during_switch()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@tab-statistics')
            // Verify button is disabled during load
            ->assertAttribute('@tab-statistics', 'disabled', 'true')
            ->waitFor('@statistics-tab-content')
            // Verify button is enabled after load
            ->assertAttributeMissing('@tab-statistics', 'disabled');
    });
}
```

### 2. Analyze Tab Tests

#### Test: Analyze Case Flow
```php
public function test_can_analyze_case()
{
    $this->browse(function (Browser $browser) {
        $case = LegalCase::first();

        $browser->visit('/topics-demo')
            ->select('@case-selector', $case->id)
            ->select('@drug-type-selector', 'cannabis')
            ->type('@amount-input', '35')
            ->select('@charged-as-selector', 'dealing')
            ->check('@evidence-scales')
            ->check('@evidence-baggies')

            // Click analyze and verify loading state
            ->click('@analyze-button')
            ->assertAttribute('@analyze-button', 'disabled', 'true')
            ->waitFor('@overcharge-detection', 10)

            // Verify results are displayed
            ->assertPresent('@overcharge-status')
            ->assertPresent('@overcharge-severity')
            ->assertPresent('@threshold-analysis')
            ->assertPresent('@recommended-charge');
    });
}
```

#### Test: Loading Overlay Appears
```php
public function test_analyze_shows_loading_overlay()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@analyze-button')
            // Loading overlay should appear
            ->assertSeeIn('@analysis-results-panel', 'Analyzing case...')
            ->waitFor('@overcharge-detection', 10);
    });
}
```

#### Test: Clear Analysis Works
```php
public function test_can_clear_analysis_results()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@analyze-button')
            ->waitFor('@overcharge-detection', 10)
            ->assertPresent('@clear-analysis-button')

            // Click clear
            ->click('@clear-analysis-button')
            ->waitFor('@no-results-message')
            ->assertSeeIn('@no-results-message', 'No results yet');
    });
}
```

#### Test: Validation Errors Display
```php
public function test_analyze_shows_validation_errors()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->type('@amount-input', '-5')
            ->click('@analyze-button')
            ->waitFor('@amount-error')
            ->assertPresent('@amount-error');
    });
}
```

### 3. Statistics Tab Tests

#### Test: Get Statistics Flow
```php
public function test_can_get_statistics()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@tab-statistics')
            ->waitFor('@statistics-tab-content')

            ->type('@stats-year-input', '2025')
            ->select('@stats-region-selector', 'Osijek')

            // Click get statistics
            ->click('@get-statistics-button')
            ->assertAttribute('@get-statistics-button', 'disabled', 'true')
            ->waitFor('@total-cases', 10)

            // Verify results
            ->assertPresent('@total-cases-count')
            ->assertPresent('@stats-status');
    });
}
```

#### Test: Statistics Loading Overlay
```php
public function test_statistics_shows_loading_overlay()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@tab-statistics')
            ->waitFor('@statistics-tab-content')
            ->click('@get-statistics-button')
            ->assertSeeIn('@statistics-results-panel', 'Loading statistics...')
            ->waitFor('@total-cases', 10);
    });
}
```

#### Test: Clear Statistics Works
```php
public function test_can_clear_statistics_results()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@tab-statistics')
            ->waitFor('@statistics-tab-content')
            ->click('@get-statistics-button')
            ->waitFor('@total-cases', 10)

            ->click('@clear-statistics-button')
            ->waitFor('@no-statistics-message')
            ->assertSeeIn('@no-statistics-message', 'No statistics yet');
    });
}
```

### 4. Compare Tab Tests

#### Test: Compare Regions Flow
```php
public function test_can_compare_regions()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@tab-compare')
            ->waitFor('@compare-tab-content')

            ->select('@region1-selector', 'Osijek')
            ->select('@region2-selector', 'Zagreb')
            ->type('@comparison-year-input', '2025')

            // Click compare
            ->click('@compare-regions-button')
            ->assertAttribute('@compare-regions-button', 'disabled', 'true')
            ->waitFor('@worse-region', 10)

            // Verify results
            ->assertPresent('@worse-region-name')
            ->assertPresent('@region1-stats')
            ->assertPresent('@region2-stats');
    });
}
```

#### Test: Comparison Loading Overlay
```php
public function test_comparison_shows_loading_overlay()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@tab-compare')
            ->waitFor('@compare-tab-content')
            ->click('@compare-regions-button')
            ->assertSeeIn('@comparison-results-panel', 'Comparing regions...')
            ->waitFor('@worse-region', 10);
    });
}
```

### 5. Wire:target Verification Tests

#### Test: Analyze Button Wire:target
```php
public function test_analyze_button_has_correct_wire_target()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@analyze-button')
            // Only analyze button should be disabled, not others
            ->assertAttribute('@analyze-button', 'disabled', 'true')
            ->assertAttributeMissing('@tab-statistics', 'disabled')
            ->assertAttributeMissing('@tab-compare', 'disabled');
    });
}
```

#### Test: Tab Switching Wire:target
```php
public function test_tab_buttons_have_correct_wire_target()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@tab-statistics')
            // All tab buttons should be disabled during switch
            ->assertAttribute('@tab-statistics', 'disabled', 'true')
            ->assertAttribute('@tab-analyze', 'disabled', 'true')
            ->assertAttribute('@tab-compare', 'disabled', 'true')
            ->waitFor('@statistics-tab-content');
    });
}
```

### 6. Visual Enhancement Tests

#### Test: Gradients Applied
```php
public function test_gradient_backgrounds_are_present()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            // Check gradient classes exist
            ->assertSourceHas('bg-gradient-to-r from-indigo-600 to-purple-600')
            ->assertSourceHas('bg-gradient-to-r from-blue-50 to-indigo-50');
    });
}
```

#### Test: Hover Effects Work
```php
public function test_buttons_have_hover_effects()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            // Verify hover classes are present
            ->assertSourceHas('hover:scale-105')
            ->assertSourceHas('hover:shadow-xl')
            ->assertSourceHas('transition-all');
    });
}
```

### 7. Mobile Responsiveness Tests

#### Test: Mobile Layout
```php
public function test_mobile_responsive_layout()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(375, 667) // iPhone SE size
            ->visit('/topics-demo')
            ->assertPresent('@header')
            ->assertPresent('@tabs-navigation')
            ->assertPresent('@analyze-tab-content')

            // Tabs should be visible and clickable
            ->click('@tab-statistics')
            ->waitFor('@statistics-tab-content');
    });
}
```

### 8. Accessibility Tests

#### Test: ARIA Labels
```php
public function test_error_messages_have_proper_aria()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->type('@amount-input', '-5')
            ->click('@analyze-button')
            ->waitFor('@amount-error')
            // Error message should be in an alert role
            ->assertSourceHas('role="alert"');
    });
}
```

#### Test: Keyboard Navigation
```php
public function test_keyboard_navigation_works()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            // Tab through interactive elements
            ->keys('@case-selector', ['{tab}'])
            ->keys('@drug-type-selector', ['{tab}'])
            ->keys('@amount-input', ['{tab}'])

            // Should be able to activate button with Enter
            ->keys('@analyze-button', ['{enter}']);
    });
}
```

### 9. Error Handling Tests

#### Test: Network Error Display
```php
public function test_displays_error_message_on_failure()
{
    // This test would need to mock a network failure
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            // Trigger an error condition
            ->waitFor('@error-message', 10)
            ->assertPresent('@error-message')
            ->assertSourceHas('bg-gradient-to-r from-red-50 to-red-100');
    });
}
```

### 10. Performance Tests

#### Test: Loading Overlay Appears Quickly
```php
public function test_loading_overlay_appears_immediately()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/topics-demo')
            ->click('@analyze-button')
            // Loading should appear within 100ms
            ->pause(100)
            ->assertSeeIn('@analysis-results-panel', 'Analyzing case...');
    });
}
```

---

## Test Execution Commands

### Run All Dusk Tests
```bash
php artisan dusk
```

### Run Specific Test File
```bash
php artisan dusk tests/Browser/TopicAnalyzerTest.php
```

### Run Specific Test Method
```bash
php artisan dusk --filter test_can_analyze_case
```

### Run with Screenshots on Failure
```bash
php artisan dusk --screenshots
```

---

## Known Issues & Edge Cases

### 1. Empty Results Handling
- All tabs properly display "No results yet" messages
- Clear buttons only appear when results exist

### 2. Validation
- Amount field validates for positive numbers
- Year fields validate for range 2020-2030

### 3. Loading States
- All actions properly disable buttons during execution
- Loading overlays prevent interaction during data fetch
- Wire:target ensures only relevant elements are disabled

### 4. Data Requirements
- At least one LegalCase must exist in database
- DrugChargeAbuseDetector must be properly configured

---

## CSS Classes Reference

### Gradients
- `bg-gradient-to-r from-indigo-600 to-purple-600` - Primary gradient (buttons, headings)
- `bg-gradient-to-r from-blue-50 to-indigo-50` - Light blue gradient (panels)
- `bg-gradient-to-r from-red-50 to-red-100` - Error/warning gradient
- `bg-gradient-to-r from-green-50 to-emerald-50` - Success gradient
- `bg-gradient-to-r from-gray-50 to-gray-100` - Neutral gradient
- `bg-gradient-to-b from-indigo-50 to-transparent` - Active tab gradient

### Transitions
- `transition-all duration-200` - Fast transitions (inputs, small elements)
- `transition-all duration-300` - Medium transitions (panels, cards)
- `transform hover:scale-105` - Hover scale effect
- `hover:shadow-xl` - Hover shadow effect

### Loading States
- `animate-spin` - Spinner animation
- `disabled:opacity-50` - Disabled state opacity
- `disabled:cursor-not-allowed` - Disabled cursor

---

## Maintenance Notes

### When Adding New Features
1. Always add `dusk="selector-name"` to new interactive elements
2. Add `wire:target` to all new buttons/actions
3. Include loading states for async operations
4. Add corresponding test cases
5. Update this documentation

### When Modifying Existing Features
1. Verify existing dusk selectors still work
2. Ensure wire:target attributes are preserved
3. Update test cases if behavior changes
4. Run full test suite before committing

---

## Contact & Support
For questions about testing this component, refer to:
- Laravel Dusk Documentation: https://laravel.com/docs/dusk
- Livewire Documentation: https://laravel-livewire.com/docs
- TailwindCSS Documentation: https://tailwindcss.com/docs
