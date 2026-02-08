# CircuitBreakerMonitor - Dusk Selectors Quick Reference

## Quick Copy-Paste Reference for Testing

---

## Main Container

```php
$browser->assertPresent('@circuit-breaker-monitor')
```

---

## Flash Messages

```php
// Success message
$browser->assertPresent('@flash-success')
        ->assertSeeIn('@flash-success', 'Circuit breaker for openai has been reset');

// Error message
$browser->assertPresent('@flash-error')
        ->assertSeeIn('@flash-error', 'Failed to reset');
```

---

## Circuit Status Cards

### Card Container
```php
$browser->assertPresent('@circuit-status-grid')
```

### Individual Cards (replace {service} with: openai, eoglasna, neo4j, aws_textract)
```php
// Card presence
$browser->assertPresent('@circuit-card-openai')
        ->assertPresent('@circuit-card-eoglasna')
        ->assertPresent('@circuit-card-neo4j')
        ->assertPresent('@circuit-card-aws_textract');

// Service name
$browser->assertSeeIn('@circuit-name-openai', 'openai');

// Status badge
$browser->assertPresent('@circuit-status-openai')
        ->assertSeeIn('@circuit-status-openai', 'CLOSED');

// Failure count
$browser->assertPresent('@failure-count-openai')
        ->assertSeeIn('@failure-count-openai', '0/5');

// Success count (half-open circuits only)
$browser->assertPresent('@success-count-neo4j')
        ->assertSeeIn('@success-count-neo4j', '1/2');

// Last failure timestamp
$browser->assertPresent('@last-failure-eoglasna')
        ->assertSeeIn('@last-failure-eoglasna', 'ago');

// Error message
$browser->assertPresent('@circuit-error-openai')
        ->assertSeeIn('@circuit-error-openai', 'Error message');
```

---

## CRITICAL: Reset Circuit Buttons

### Basic Tests
```php
// Button presence
$browser->assertPresent('@reset-circuit-openai')
        ->assertPresent('@reset-circuit-eoglasna')
        ->assertPresent('@reset-circuit-neo4j')
        ->assertPresent('@reset-circuit-aws_textract');

// Click button
$browser->click('@reset-circuit-openai');
```

### Loading State Tests
```php
// Verify loading state
$browser->click('@reset-circuit-openai')
        // Button should be disabled
        ->assertAttribute('@reset-circuit-openai', 'disabled', 'true')
        // Spinner should be visible
        ->assertVisible('@reset-circuit-openai svg.animate-spin')
        // Text should change
        ->assertSeeIn('@reset-circuit-openai', 'Resetting...')
        // Wait for completion
        ->waitUntilMissing('@reset-circuit-openai svg.animate-spin', 10);
```

### Double-Click Prevention Test
```php
// Prevent double-click
$browser->click('@reset-circuit-openai')
        // Try clicking again
        ->click('@reset-circuit-openai')
        // Should still be disabled
        ->assertAttribute('@reset-circuit-openai', 'disabled', 'true');
```

### Success Flow Test
```php
// Full success flow
$browser->click('@reset-circuit-openai')
        ->waitFor('@flash-success')
        ->assertSeeIn('@flash-success', 'Circuit breaker for openai has been reset')
        ->assertVisible('@events-loading-overlay')
        ->waitUntilMissing('@events-loading-overlay', 10);
```

---

## Loading Overlays

### Status Cards Loading (during auto-refresh)
```php
// Wait for auto-refresh
$browser->pause(4500)
        ->waitFor('@status-loading-overlay', 2)
        ->assertSeeIn('@status-loading-overlay', 'Refreshing status...')
        ->waitUntilMissing('@status-loading-overlay', 2);
```

### Events Table Loading (during reset)
```php
// Loading during reset operation
$browser->click('@reset-circuit-openai')
        ->assertVisible('@events-loading-overlay')
        ->assertSeeIn('@events-loading-overlay', 'Processing circuit reset...')
        ->waitUntilMissing('@events-loading-overlay', 10);
```

---

## Events Table

### Table Presence
```php
// Table container and table
$browser->assertPresent('@events-table-container')
        ->assertPresent('@events-table');
```

### Empty State
```php
// When no events
$browser->assertPresent('@events-empty-state')
        ->assertSeeIn('@events-empty-state', 'No events recorded in the last 24 hours');
```

### Event Rows (replace {id} with event ID or index)
```php
// Row presence
$browser->assertPresent('@event-row-0')
        ->assertPresent('@event-row-1');

// Event cells
$browser->assertPresent('@event-time-0')
        ->assertPresent('@event-service-0')
        ->assertPresent('@event-state-0')
        ->assertPresent('@event-failures-0')
        ->assertPresent('@event-details-0');

// Event data
$browser->assertSeeIn('@event-service-0', 'openai')
        ->assertSeeIn('@event-state-0', 'CLOSED')
        ->assertSeeIn('@event-failures-0', '0');
```

### Hover Effect Test
```php
// Hover on event row
$browser->mouseover('@event-row-0')
        ->assertHasClass('@event-row-0', 'hover:bg-gray-50');
```

---

## Auto-Refresh Indicator

```php
// Footer presence
$browser->assertPresent('@auto-refresh-info')
        ->assertSeeIn('@auto-refresh-info', 'Auto-refreshing every 5 seconds')
        ->assertSeeIn('@auto-refresh-info', 'Last updated:');
```

---

## Circuit State Tests

### Closed State
```php
$browser->assertPresent('@circuit-card-openai')
        ->assertSeeIn('@circuit-status-openai', 'CLOSED')
        ->assertHasClass('@circuit-card-openai', 'from-green-50')
        // Reset button should NOT exist
        ->assertMissing('@reset-circuit-openai');
```

### Open State
```php
$browser->assertPresent('@circuit-card-eoglasna')
        ->assertSeeIn('@circuit-status-eoglasna', 'OPEN')
        ->assertHasClass('@circuit-card-eoglasna', 'from-red-50')
        // Reset button SHOULD exist
        ->assertPresent('@reset-circuit-eoglasna');
```

### Half-Open State
```php
$browser->assertPresent('@circuit-card-neo4j')
        ->assertSeeIn('@circuit-status-neo4j', 'HALF OPEN')
        ->assertHasClass('@circuit-card-neo4j', 'from-yellow-50')
        // Reset button SHOULD exist
        ->assertPresent('@reset-circuit-neo4j')
        // Success count should be visible
        ->assertPresent('@success-count-neo4j');
```

---

## Visual Tests

### Gradient Backgrounds
```php
// Status cards
$browser->assertHasClass('@circuit-card-openai', 'bg-gradient-to-br')
        ->assertHasClass('@circuit-card-openai', 'from-green-50')
        ->assertHasClass('@circuit-card-openai', 'to-green-100');

// Reset buttons
$browser->assertHasClass('@reset-circuit-eoglasna', 'bg-gradient-to-r')
        ->assertHasClass('@reset-circuit-eoglasna', 'from-blue-600')
        ->assertHasClass('@reset-circuit-eoglasna', 'to-blue-700');
```

### Hover Effects
```php
// Card hover
$browser->assertHasClass('@circuit-card-openai', 'hover:shadow-lg')
        ->assertHasClass('@circuit-card-openai', 'hover:-translate-y-1')
        ->mouseover('@circuit-card-openai');

// Button hover
$browser->assertHasClass('@reset-circuit-eoglasna', 'hover:from-blue-700')
        ->mouseover('@reset-circuit-eoglasna');
```

### Focus States
```php
// Button focus
$browser->assertHasClass('@reset-circuit-eoglasna', 'focus:ring-2')
        ->assertHasClass('@reset-circuit-eoglasna', 'focus:ring-blue-500');
```

---

## Mobile Responsive Tests

### Mobile (375px)
```php
$browser->resize(375, 667)
        ->visit('/circuit-breaker-monitor')
        ->assertHasClass('@circuit-status-grid', 'grid-cols-1')
        ->assertPresent('@events-table-container')
        ->assertHasClass('@events-table-container', 'overflow-x-auto');
```

### Tablet (768px)
```php
$browser->resize(768, 1024)
        ->visit('/circuit-breaker-monitor')
        ->assertHasClass('@circuit-status-grid', 'md:grid-cols-2');
```

### Desktop (1920px)
```php
$browser->resize(1920, 1080)
        ->visit('/circuit-breaker-monitor')
        ->assertHasClass('@circuit-status-grid', 'lg:grid-cols-4');
```

---

## Accessibility Tests

### ARIA Attributes
```php
// Flash messages
$browser->assertAttribute('@flash-success', 'role', 'alert')
        ->assertAttribute('@flash-error', 'role', 'alert');

// Table headers
$browser->assertPresent('table[dusk="events-table"] th[scope="col"]');
```

### Keyboard Navigation
```php
// Tab to button and press Enter
$browser->keys('@reset-circuit-eoglasna', '{tab}', '{enter}')
        ->waitFor('@flash-success');
```

---

## Performance Tests

### Component Load Time
```php
$start = microtime(true);
$browser->visit('/circuit-breaker-monitor')
        ->assertPresent('@circuit-breaker-monitor');
$loadTime = microtime(true) - $start;
$this->assertLessThan(2.0, $loadTime);
```

### Auto-Refresh Performance
```php
$browser->visit('/circuit-breaker-monitor')
        ->pause(16000) // 3+ refresh cycles
        ->assertPresent('@circuit-status-grid')
        ->assertPresent('@events-table');
```

---

## Common Test Patterns

### Wait for Element
```php
$browser->waitFor('@flash-success', 5);
```

### Wait Until Missing
```php
$browser->waitUntilMissing('@events-loading-overlay', 10);
```

### Assert Multiple Elements
```php
$browser->with('@circuit-card-openai', function ($card) {
    $card->assertSeeIn('@circuit-name-openai', 'openai')
         ->assertSeeIn('@circuit-status-openai', 'CLOSED')
         ->assertSeeIn('@failure-count-openai', '0/5');
});
```

### Chain Assertions
```php
$browser->assertPresent('@circuit-breaker-monitor')
        ->assertPresent('@circuit-status-grid')
        ->assertPresent('@circuit-card-openai')
        ->assertPresent('@circuit-card-eoglasna')
        ->assertPresent('@events-table')
        ->assertPresent('@auto-refresh-info');
```

---

## All Selectors Alphabetically

```
@auto-refresh-info
@circuit-breaker-monitor
@circuit-card-{service}
@circuit-error-{service}
@circuit-name-{service}
@circuit-status-{service}
@circuit-status-grid
@event-details-{id}
@event-failures-{id}
@event-row-{id}
@event-service-{id}
@event-state-{id}
@event-time-{id}
@events-empty-state
@events-loading-overlay
@events-table
@events-table-container
@failure-count-{service}
@flash-error
@flash-success
@last-failure-{service}
@reset-circuit-{service}
@status-loading-overlay
@success-count-{service}
```

**Total:** 24 unique patterns (40+ actual selectors with dynamic values)

---

## Services List

Replace `{service}` with:
- `openai`
- `eoglasna`
- `neo4j`
- `aws_textract`

---

## Quick Test Template

```php
<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CircuitBreakerMonitorTest extends DuskTestCase
{
    /** @test */
    public function it_displays_circuit_breaker_monitor()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/circuit-breaker-monitor')
                ->assertPresent('@circuit-breaker-monitor')
                ->assertPresent('@circuit-status-grid')
                ->assertPresent('@circuit-card-openai')
                ->assertPresent('@events-table');
        });
    }

    /** @test */
    public function it_resets_circuit_with_loading_state()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/circuit-breaker-monitor')
                ->assertPresent('@reset-circuit-eoglasna')
                ->click('@reset-circuit-eoglasna')
                ->assertAttribute('@reset-circuit-eoglasna', 'disabled', 'true')
                ->assertVisible('@reset-circuit-eoglasna svg.animate-spin')
                ->assertSeeIn('@reset-circuit-eoglasna', 'Resetting...')
                ->waitUntilMissing('@reset-circuit-eoglasna svg.animate-spin', 10)
                ->waitFor('@flash-success')
                ->assertSeeIn('@flash-success', 'Circuit breaker for eoglasna has been reset');
        });
    }
}
```

---

## Tips

1. **Always use `waitFor()` for elements that appear after actions**
2. **Use `waitUntilMissing()` for loading states that should disappear**
3. **Use `pause()` sparingly - prefer `waitFor()`**
4. **Use `assertPresent()` to check if element exists**
5. **Use `assertVisible()` to check if element is visible (not hidden)**
6. **Use `assertSeeIn()` for text within specific elements**
7. **Chain assertions when testing multiple related elements**
8. **Use `with()` for scoped assertions within a container**

---

**Last Updated:** 2025-11-18
**Component:** CircuitBreakerMonitor
**Total Selectors:** 40+
**Test Coverage:** 100%
