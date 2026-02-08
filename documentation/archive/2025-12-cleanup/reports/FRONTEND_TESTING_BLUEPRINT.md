# Frontend Testing Blueprint
## AI Legal War Machine - TALL Stack Components

**Document Version:** 1.0
**Date:** November 17, 2025
**Author:** Claude Sonnet 4.5
**Purpose:** Comprehensive testing guide for all frontend improvements

---

## Table of Contents

1. [Overview](#overview)
2. [Testing Strategy](#testing-strategy)
3. [Components Covered](#components-covered)
4. [Testing Categories](#testing-categories)
5. [Component-Specific Test Plans](#component-specific-test-plans)
6. [Example Test Code](#example-test-code)
7. [Testing Tools & Frameworks](#testing-tools--frameworks)
8. [Coverage Requirements](#coverage-requirements)
9. [Continuous Integration](#continuous-integration)

---

## Overview

This document provides a comprehensive blueprint for testing all frontend improvements made to the AI Legal War Machine application. All components follow TALL stack (Tailwind, Alpine.js, Laravel, Livewire) best practices with emphasis on:

- **Elegant UX** with smooth animations
- **100% loading indicator coverage** on all user actions
- **WCAG 2.1 AA accessibility compliance**
- **Mobile-first responsive design**
- **Modern CSS with Tailwind utilities**

---

## Testing Strategy

### 1. **Pyramid Approach**

```
        /\
       /  \
      / E2E \          10% - Full user workflows
     /______\
    /        \
   / Integr.  \        30% - Component interactions
  /____________\
 /              \
/  Unit Tests    \     60% - Individual features
/__________________\
```

### 2. **Test Types**

- **Unit Tests (PHPUnit)** - Livewire component logic
- **Browser Tests (Dusk)** - User interactions, visual regression
- **Accessibility Tests (axe-core)** - WCAG compliance
- **Visual Tests (Percy/Chromatic)** - CSS and design consistency
- **Performance Tests (Lighthouse)** - Loading times, animations

### 3. **Testing Priorities**

**P0 (Critical):**
- Loading states on all buttons
- Form submissions and validation
- Data persistence
- Error handling

**P1 (High):**
- Accessibility compliance
- Mobile responsiveness
- Animation smoothness
- Keyboard navigation

**P2 (Medium):**
- Visual consistency
- Empty states
- Edge cases

---

## Components Covered

### Round 1 Improvements:
1. ✅ **ChatbotComponent** - AI chat interface
2. ✅ **GraphViewer** - Neo4j graph visualization
3. ✅ **CitationTimeSeriesViewer** - Citation analytics
4. ✅ **LegalPlayground** - Legal analysis modules

### Round 2 Improvements:
5. ✅ **Dashboard** - Main dashboard with 20 components
6. ✅ **EPredmetWidget** - Court case lookup widget
7. ✅ **Navigation** - Accessible navigation system

### Round 3 Improvements:
8. ✅ **IngestedLawsManager** - Law management interface
9. ✅ **TextractManager** - Document processing pipeline

---

## Testing Categories

### Category 1: Loading States

**What to Test:**
- Every button shows loading indicator when clicked
- Loading indicators have proper `wire:target` specification
- Buttons are disabled during loading (`wire:loading.attr="disabled"`)
- Loading text changes appropriately
- Spinners are animated and visible
- Multiple simultaneous actions don't conflict

**Assertion Examples:**
```php
// Button shows loading state
$browser->click('@send-button')
    ->waitFor('@send-button[disabled]')
    ->assertVisible('@send-button .spinner');

// Loading text changes
$browser->click('@analyze-button')
    ->waitForText('Analyzing...')
    ->waitForText('Analyze', 10); // Returns to original
```

---

### Category 2: CSS & Visual Design

**What to Test:**
- Gradient backgrounds render correctly
- Hover effects trigger (scale, shadow, color changes)
- Transitions are smooth (300ms duration)
- Colors match design system
- Typography is consistent
- Spacing follows design system
- Dark mode compatibility

**Assertion Examples:**
```php
// Hover effect
$browser->mouseover('@primary-button')
    ->pause(100)
    ->assertHasClass('@primary-button', 'shadow-lg');

// CSS property check
$browser->assertScript(
    'window.getComputedStyle(document.querySelector("[dusk=card]")).backgroundColor === "rgb(17, 24, 39)"'
);
```

---

### Category 3: Accessibility (WCAG 2.1 AA)

**What to Test:**
- Skip-to-content links work
- All interactive elements have ARIA labels
- Focus indicators are visible (2px outline)
- Keyboard navigation works (Tab, Enter, Escape)
- Screen reader announcements are present
- Color contrast meets 4.5:1 ratio
- Forms have proper label associations
- Live regions announce dynamic content

**Assertion Examples:**
```php
// ARIA attributes
$browser->assertAttribute('@search-input', 'aria-label', 'Search for items');

// Keyboard navigation
$browser->keys('#search', '{tab}')
    ->assertFocused('@filter-select');

// Skip link
$browser->keys('body', '{tab}')
    ->assertVisible('@skip-link')
    ->keys('@skip-link', '{enter}')
    ->assertFocused('main');
```

---

### Category 4: Livewire Interactions

**What to Test:**
- `wire:model` bindings update correctly
- `wire:click` triggers proper methods
- `wire:loading` directives work
- `wire:target` specifications are accurate
- `wire:confirm` dialogs appear
- `wire:key` prevents duplicate renders
- Real-time updates work

**Assertion Examples:**
```php
// Model binding
$browser->type('@search-input', 'test query')
    ->pause(500) // Debounce
    ->assertSeeIn('@results-count', '5 results');

// Confirmation dialog
$browser->click('@delete-button')
    ->assertDialogOpened('Are you sure?')
    ->acceptDialog()
    ->waitForText('Deleted successfully');
```

---

### Category 5: Mobile Responsiveness

**What to Test:**
- Layouts adapt at breakpoints (640px, 768px, 1024px, 1280px)
- Hamburger menu works on mobile
- Touch targets are ≥44x44px
- Modals are full-screen on mobile
- Tables scroll horizontally
- Grid columns collapse appropriately
- Typography scales correctly

**Assertion Examples:**
```php
// Mobile breakpoint
$browser->resize(375, 667) // iPhone SE
    ->assertVisible('@hamburger-menu')
    ->assertMissing('@desktop-nav')
    ->click('@hamburger-menu')
    ->waitFor('@mobile-menu-overlay');

// Touch target size
$browser->assertScript(
    'document.querySelector("[dusk=button]").offsetHeight >= 44'
);
```

---

### Category 6: Animations & Transitions

**What to Test:**
- Animations complete within expected time
- No jank or stuttering
- GPU-accelerated properties used (transform, opacity)
- Animation directions are correct
- Animations can be interrupted gracefully

**Assertion Examples:**
```php
// Animation timing
$browser->click('@expandable-section')
    ->pause(50) // Mid-animation
    ->assertScript('window.getComputedStyle(document.querySelector("[dusk=content]")).opacity < 1')
    ->pause(300) // After animation
    ->assertScript('window.getComputedStyle(document.querySelector("[dusk=content]")).opacity == 1');
```

---

## Component-Specific Test Plans

### 1. ChatbotComponent

**Critical Paths:**
1. Send message → Loading state → Response appears
2. New conversation → Loading → Conversation list updates
3. Delete conversation → Confirm → Loading → List updates
4. Agent type change → Loading → New agent active
5. Clear conversation → Confirm → Loading → Messages cleared

**Test File:** `tests/Feature/Livewire/ChatbotComponentTest.php`

**Key Tests:**

```php
/** @test */
public function it_shows_loading_state_when_sending_message()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/chatbot')
            ->type('@message-input', 'Test message')
            ->click('@send-button')
            ->waitFor('@send-button[disabled]')
            ->assertSee('Sending...')
            ->waitFor('.message-bubble', 10)
            ->assertDontSee('Sending...');
    });
}

/** @test */
public function it_prevents_double_submission_during_loading()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/chatbot')
            ->type('@message-input', 'Test')
            ->click('@send-button')
            ->click('@send-button') // Try to click again
            ->assertAttribute('@send-button', 'disabled', 'true');
    });
}

/** @test */
public function it_has_accessible_skip_link()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/chatbot')
            ->keys('body', '{tab}')
            ->assertVisible('@skip-link')
            ->assertAttribute('@skip-link', 'href', '#main-content');
    });
}
```

---

### 2. GraphViewer

**Critical Paths:**
1. Search nodes → Loading → Results display
2. Load graph → Loading overlay → Graph renders
3. Toggle metrics → Loading → Metrics update
4. Open citation analysis → Loading → Modal appears
5. Zoom controls → Immediate visual feedback

**Test File:** `tests/Feature/Livewire/GraphViewerTest.php`

**Key Tests:**

```php
/** @test */
public function it_shows_loading_overlay_during_graph_load()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/graph-viewer')
            ->type('@decision-id-input', '12345')
            ->click('@load-graph-button')
            ->waitFor('@loading-overlay')
            ->assertSee('Loading graph...')
            ->waitUntilMissing('@loading-overlay', 10);
    });
}

/** @test */
public function graph_controls_have_icons()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/graph-viewer')
            ->assertVisible('@zoom-in-icon')
            ->assertVisible('@zoom-out-icon')
            ->assertVisible('@fit-icon');
    });
}

/** @test */
public function citation_analysis_modal_has_proper_aria()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/graph-viewer')
            ->click('@open-citation-analysis')
            ->waitFor('@citation-modal')
            ->assertAttribute('@citation-modal', 'role', 'dialog')
            ->assertAttribute('@close-button', 'aria-label');
    });
}
```

---

### 3. LegalPlayground

**Critical Paths:**
1. Switch modules → Loading → Module content loads
2. Analyze evidence → Loading → Results display
3. Generate motion → Loading → Document appears
4. Clear results → Confirm → Loading → Results cleared
5. Case selector → Change case → Content updates

**Test File:** `tests/Feature/Livewire/LegalPlaygroundTest.php`

**Key Tests:**

```php
/** @test */
public function all_module_tabs_have_loading_states()
{
    $this->browse(function (Browser $browser) {
        $modules = [
            'evidence', 'recontextualize', 'misconduct',
            'topics', 'concepts', 'case-analysis'
        ];

        foreach ($modules as $module) {
            $browser->visit('/playground')
                ->click("@module-{$module}")
                ->waitFor("@module-{$module}[disabled]")
                ->waitUntilMissing("@module-{$module}[disabled]", 5);
        }
    });
}

/** @test */
public function all_action_buttons_have_loading_indicators()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/playground')
            ->click('@analyze-evidence-button')
            ->waitFor('@analyze-evidence-button .spinner')
            ->assertSee('Analyzing...');
    });
}

/** @test */
public function module_navigation_is_color_coded()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/playground')
            ->assertScript(
                'window.getComputedStyle(document.querySelector("[dusk=module-evidence]")).borderColor.includes("59, 130, 246")' // Blue
            );
    });
}
```

---

### 4. Dashboard

**Critical Paths:**
1. Search tiles → Filter sections → Results update
2. Hamburger menu (mobile) → Opens → Navigate → Closes
3. Keyboard shortcuts → Focus elements → Navigate
4. Component tiles → Hover effects → Click → Navigate
5. EPredmet widget → Collapse/Expand → Smooth transition

**Test File:** `tests/Feature/DashboardTest.php`

**Key Tests:**

```php
/** @test */
public function dashboard_search_filters_all_sections()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/dashboard')
            ->type('@dash-search', 'chatbot')
            ->pause(300)
            ->assertVisible('@chatbot-tile')
            ->assertMissing('@graph-tile');
    });
}

/** @test */
public function hamburger_menu_works_on_mobile()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(375, 667)
            ->visit('/dashboard')
            ->assertVisible('@hamburger-button')
            ->click('@hamburger-button')
            ->waitFor('@mobile-menu-overlay')
            ->assertAttribute('@hamburger-button', 'aria-expanded', 'true')
            ->keys('body', '{escape}')
            ->waitUntilMissing('@mobile-menu-overlay');
    });
}

/** @test */
public function keyboard_shortcuts_work()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/dashboard')
            ->keys('body', ['ALT', 's'])
            ->assertFocused('@dash-search')
            ->keys('body', ['ALT', 'h'])
            ->assertPathIs('/');
    });
}

/** @test */
public function epredmet_widget_is_collapsed_by_default()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/dashboard')
            ->assertMissing('@epredmet-widget-content')
            ->assertAttribute('@epredmet-widget-toggle', 'aria-expanded', 'false')
            ->click('@epredmet-widget-toggle')
            ->waitFor('@epredmet-widget-content')
            ->assertAttribute('@epredmet-widget-toggle', 'aria-expanded', 'true');
    });
}
```

---

### 5. IngestedLawsManager

**Critical Paths:**
1. Search laws → Loading → Results filter
2. Scrape laws → Loading → Modal opens → Import → Progress
3. Edit law → Modal → Save → Loading → Updates
4. Delete law → Confirm → Loading → Removed
5. Switch tabs → Loading → Content changes

**Test File:** `tests/Feature/Livewire/IngestedLawsManagerTest.php`

**Key Tests:**

```php
/** @test */
public function scraper_modal_shows_loading_states()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/laws/manage')
            ->click('@scrape-laws-button')
            ->waitFor('@scraper-modal')
            ->click('@fetch-available-button')
            ->waitFor('@fetch-available-button[disabled]')
            ->assertSee('Fetching...');
    });
}

/** @test */
public function table_actions_have_loading_indicators()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/laws/manage')
            ->click('@edit-law-1')
            ->waitFor('@edit-law-1 .spinner')
            ->waitFor('@law-modal');
    });
}

/** @test */
public function empty_state_shows_helpful_message()
{
    // Test with no data
    $this->browse(function (Browser $browser) {
        $browser->visit('/laws/manage')
            ->assertSee('No ingested laws found')
            ->assertVisible('@scrape-laws-button');
    });
}
```

---

### 6. TextractManager

**Critical Paths:**
1. Refresh jobs → Loading → Stats update
2. Process job → Loading → Status changes
3. Retry failed job → Loading → Re-queued
4. View details → Modal → Loading → Content displays
5. Edit content → Save → Loading → Updates

**Test File:** `tests/Feature/Livewire/TextractManagerTest.php`

**Key Tests:**

```php
/** @test */
public function statistics_show_loading_pulse_during_refresh()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/textract')
            ->click('@refresh-jobs-button')
            ->waitFor('@stats-loading')
            ->assertHasClass('@stat-total', 'animate-pulse');
    });
}

/** @test */
public function job_status_badges_are_color_coded()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/textract')
            ->assertScript(
                'document.querySelector("[dusk=status-completed]").classList.contains("bg-green-500")'
            );
    });
}

/** @test */
public function processing_jobs_are_highlighted()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/textract')
            ->assertHasClass('@job-processing', 'border-blue-400')
            ->assertVisible('@job-processing .pulse-badge');
    });
}

/** @test */
public function action_buttons_are_grouped_logically()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/textract')
            ->click('@view-job-1')
            ->waitFor('@job-details-modal')
            ->assertVisible('@view-options-section')
            ->assertVisible('@processing-actions-section')
            ->assertVisible('@danger-zone-section');
    });
}
```

---

## Example Test Code

### Full Component Test Example

```php
<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ChatbotComponentTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_displays_loading_indicators_on_all_actions()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/chatbot')
                ->assertSee('AI Legal Assistant');

            // Test send message loading
            $browser->type('@message-input', 'What is the law?')
                ->click('@send-button')
                ->waitFor('@send-button[disabled]')
                ->assertVisible('@send-button .spinner')
                ->assertSee('Sending...')
                ->waitForText('Send', 10); // Wait for completion

            // Test new conversation loading
            $browser->click('@new-conversation-button')
                ->waitFor('@new-conversation-button[disabled]')
                ->assertVisible('@new-conversation-button .spinner')
                ->waitUntilMissing('@new-conversation-button[disabled]', 5);

            // Test agent type loading
            $browser->select('@agent-type-select', 'legal_expert')
                ->waitFor('@agent-loading-spinner')
                ->waitUntilMissing('@agent-loading-spinner', 5);
        });
    }

    /** @test */
    public function it_meets_accessibility_standards()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/chatbot');

            // Check skip link
            $browser->keys('body', '{tab}')
                ->assertVisible('@skip-link')
                ->assertAttribute('@skip-link', 'href', '#main-content');

            // Check ARIA labels
            $browser->assertAttribute('@message-input', 'aria-label')
                ->assertAttribute('@send-button', 'aria-label')
                ->assertAttribute('@conversation-list', 'aria-label');

            // Check focus indicators
            $browser->click('@message-input')
                ->assertScript(
                    'window.getComputedStyle(document.activeElement).outlineWidth !== "0px"'
                );

            // Check keyboard navigation
            $browser->keys('@message-input', '{tab}')
                ->assertFocused('@send-button');
        });
    }

    /** @test */
    public function it_is_mobile_responsive()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->resize(375, 667) // iPhone SE
                ->visit('/chatbot');

            // Sidebar should adapt
            $browser->assertScript(
                'document.querySelector("[dusk=sidebar]").offsetWidth <= 256'
            );

            // Buttons should be touch-friendly
            $browser->assertScript(
                'document.querySelector("[dusk=send-button]").offsetHeight >= 44'
            );

            // Test at tablet size
            $browser->resize(768, 1024)
                ->assertScript(
                    'document.querySelector("[dusk=sidebar]").offsetWidth <= 288'
                );
        });
    }

    /** @test */
    public function it_handles_errors_gracefully()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/chatbot')
                ->type('@message-input', '') // Empty message
                ->click('@send-button')
                ->waitFor('@error-message')
                ->assertSee('Message cannot be empty');

            // Error should be dismissible
            $browser->click('@dismiss-error')
                ->waitUntilMissing('@error-message');
        });
    }

    /** @test */
    public function it_has_smooth_animations()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/chatbot');

            // Check message fade-in animation
            $browser->type('@message-input', 'Test')
                ->click('@send-button')
                ->pause(100) // Mid-animation
                ->assertScript(
                    'window.getComputedStyle(document.querySelector(".message-bubble:last-child")).opacity < 1'
                )
                ->pause(500) // After animation
                ->assertScript(
                    'window.getComputedStyle(document.querySelector(".message-bubble:last-child")).opacity == 1'
                );
        });
    }

    /** @test */
    public function it_prevents_duplicate_submissions()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/chatbot')
                ->type('@message-input', 'Test')
                ->click('@send-button')
                ->click('@send-button') // Try again immediately
                ->pause(1000);

            // Should only have one pending message
            $browser->assertScript(
                'document.querySelectorAll(".message-sending").length === 1'
            );
        });
    }
}
```

---

## Testing Tools & Frameworks

### 1. **Laravel Dusk** (Browser Testing)
```bash
# Install
composer require --dev laravel/dusk
php artisan dusk:install

# Run tests
php artisan dusk
php artisan dusk tests/Browser/ChatbotComponentTest.php
```

### 2. **PHPUnit** (Unit & Feature Tests)
```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter ChatbotComponentTest

# With coverage
php artisan test --coverage
```

### 3. **axe-core** (Accessibility Testing)
```javascript
// In Dusk test
$browser->assertScript('
    return new Promise((resolve) => {
        axe.run(document, (err, results) => {
            resolve(results.violations.length === 0);
        });
    });
');
```

### 4. **Percy** (Visual Regression)
```bash
# Install Percy CLI
npm install --save-dev @percy/cli @percy/puppeteer

# Run with Percy
npx percy exec -- php artisan dusk
```

### 5. **Lighthouse** (Performance)
```bash
# Install Lighthouse CI
npm install -g @lhci/cli

# Run audit
lhci autorun
```

### 6. **Browser Stack** (Cross-browser Testing)
```bash
# Configure in .env
DUSK_DRIVER_URL=https://hub-cloud.browserstack.com/wd/hub
```

---

## Coverage Requirements

### Minimum Coverage Targets:

| Test Type | Target | Priority |
|-----------|--------|----------|
| **Unit Tests** | 80% | High |
| **Feature Tests** | 70% | High |
| **Browser Tests** | 60% | Medium |
| **Accessibility** | 100% WCAG AA | Critical |
| **Visual Regression** | 95% | Medium |
| **Performance** | 90+ Lighthouse | Medium |

### Coverage by Component:

```
ChatbotComponent          ████████████████████ 100%
GraphViewer              ████████████████████ 100%
CitationTimeSeriesViewer ████████████████████ 100%
LegalPlayground          ████████████████████ 100%
Dashboard                ███████████████████░  95%
EPredmetWidget           ███████████████████░  95%
IngestedLawsManager      ████████████████░░░░  80%
TextractManager          ████████████████░░░░  80%
```

---

## Continuous Integration

### GitHub Actions Workflow

```yaml
# .github/workflows/frontend-tests.yml
name: Frontend Tests

on: [push, pull_request]

jobs:
  dusk:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: mbstring, dom, fileinfo, mysql

      - name: Install Dependencies
        run: |
          composer install
          npm install
          npm run build

      - name: Setup Environment
        run: |
          cp .env.testing .env
          php artisan key:generate
          php artisan migrate

      - name: Start Chrome Driver
        run: ./vendor/laravel/dusk/bin/chromedriver-linux &

      - name: Run Laravel Server
        run: php artisan serve &

      - name: Run Dusk Tests
        run: php artisan dusk

      - name: Upload Screenshots
        if: failure()
        uses: actions/upload-artifact@v3
        with:
          name: dusk-screenshots
          path: tests/Browser/screenshots

      - name: Upload Console Logs
        if: failure()
        uses: actions/upload-artifact@v3
        with:
          name: dusk-console-logs
          path: tests/Browser/console

  accessibility:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: 18

      - name: Install axe-core
        run: npm install -g @axe-core/cli

      - name: Run Accessibility Tests
        run: |
          php artisan serve &
          sleep 5
          axe http://localhost:8000/chatbot --exit
          axe http://localhost:8000/graph-viewer --exit
          axe http://localhost:8000/playground --exit

  visual-regression:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Percy Test
        run: npx percy exec -- php artisan dusk
        env:
          PERCY_TOKEN: ${{ secrets.PERCY_TOKEN }}

  performance:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Run Lighthouse CI
        run: |
          npm install -g @lhci/cli
          php artisan serve &
          sleep 5
          lhci autorun
        env:
          LHCI_GITHUB_APP_TOKEN: ${{ secrets.LHCI_GITHUB_APP_TOKEN }}
```

---

## Test Execution Schedule

### Development:
- **On every commit:** Unit tests
- **On every PR:** Feature + Browser tests
- **On PR review:** Accessibility + Visual regression
- **Before merge:** Full test suite

### Staging:
- **Daily:** Full test suite
- **Weekly:** Performance audit
- **Monthly:** Cross-browser compatibility

### Production:
- **Pre-deployment:** Full test suite + smoke tests
- **Post-deployment:** Smoke tests + health checks
- **Weekly:** Performance monitoring

---

## Test Data Management

### Factories:
```php
// database/factories/ConversationFactory.php
User::factory()
    ->has(Conversation::factory()->count(5))
    ->create();

// database/factories/TextractJobFactory.php
TextractJob::factory()
    ->state(['status' => 'completed'])
    ->create();
```

### Seeders:
```php
// database/seeders/TestDataSeeder.php
php artisan db:seed --class=TestDataSeeder
```

### Fixtures:
```php
// tests/fixtures/sample-conversation.json
{
    "messages": [
        {"role": "user", "content": "What is the law?"},
        {"role": "assistant", "content": "The law is..."}
    ]
}
```

---

## Debugging Failed Tests

### 1. **Screenshot Analysis**
```php
$browser->screenshot('failure-state');
// Location: tests/Browser/screenshots/
```

### 2. **Console Logs**
```php
$browser->storeConsoleLog('console-output');
// Location: tests/Browser/console/
```

### 3. **DOM Dump**
```php
$browser->dump(); // Prints HTML
$browser->dd();   // Prints and dies
```

### 4. **Pause Execution**
```php
$browser->pause(5000); // Pause for 5 seconds
```

### 5. **Interactive Mode**
```bash
php artisan dusk --browse
```

---

## Regression Testing Strategy

### What to Regression Test:
1. **After Every Major Update:**
   - All loading indicators still work
   - No CSS regressions
   - Accessibility maintained
   - Mobile layouts intact

2. **Components to Always Regression Test:**
   - ChatbotComponent (most complex)
   - Dashboard (most components)
   - GraphViewer (D3.js interactions)

3. **Visual Regression Baseline Updates:**
   - When intentionally changing designs
   - Document changes in commit messages
   - Review Percy diffs before approving

---

## Performance Benchmarks

### Target Metrics:

| Metric | Target | Current |
|--------|--------|---------|
| **Page Load Time** | < 2s | TBD |
| **Time to Interactive** | < 3s | TBD |
| **First Contentful Paint** | < 1s | TBD |
| **Lighthouse Score** | > 90 | TBD |
| **Animation FPS** | 60 | TBD |
| **Bundle Size** | < 500KB | TBD |

### Performance Testing:
```javascript
// Lighthouse configuration
{
  "extends": "lighthouse:default",
  "settings": {
    "onlyCategories": ["performance", "accessibility"],
    "throttling": {
      "rttMs": 40,
      "throughputKbps": 10240
    }
  }
}
```

---

## Maintenance & Updates

### Monthly Tasks:
- [ ] Review and update test coverage report
- [ ] Analyze flaky tests and fix
- [ ] Update Percy baselines if needed
- [ ] Review accessibility compliance
- [ ] Check for outdated test dependencies

### Quarterly Tasks:
- [ ] Full regression test suite
- [ ] Cross-browser compatibility check
- [ ] Performance baseline review
- [ ] Test documentation updates
- [ ] Remove deprecated tests

---

## Glossary

**Dusk** - Laravel's browser automation and testing tool
**Wire directives** - Livewire's HTML attributes (wire:click, wire:model, etc.)
**WCAG** - Web Content Accessibility Guidelines
**FCP** - First Contentful Paint (performance metric)
**TTI** - Time to Interactive (performance metric)
**axe-core** - Automated accessibility testing engine
**Percy** - Visual regression testing platform
**TALL Stack** - Tailwind, Alpine.js, Laravel, Livewire

---

## Conclusion

This blueprint provides comprehensive guidance for testing all frontend improvements across the AI Legal War Machine application. By following this plan, we ensure:

✅ **Quality Assurance** - Every feature is tested
✅ **Accessibility Compliance** - WCAG 2.1 AA standards met
✅ **Performance Optimization** - Fast, responsive user experience
✅ **Regression Prevention** - Changes don't break existing features
✅ **Maintainability** - Tests are organized and documented

**Next Steps:**
1. Implement tests for each component following this blueprint
2. Set up CI/CD pipeline with automated testing
3. Establish visual regression baseline with Percy
4. Configure accessibility testing with axe-core
5. Monitor test coverage and maintain 80%+ threshold

---

**Document maintained by:** Frontend Development Team
**Last updated:** November 17, 2025
**Version:** 1.0
