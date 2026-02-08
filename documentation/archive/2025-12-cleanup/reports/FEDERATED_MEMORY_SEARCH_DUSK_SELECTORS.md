# FederatedMemorySearch - Dusk Selectors Quick Reference

## Component: FederatedMemorySearch
**View File:** `resources/views/livewire/federated-memory-search.blade.php`
**Test Documentation:** `tests/Browser/TESTING_FEDERATED_MEMORY_SEARCH.md`

---

## All Dusk Selectors (18 Base + 6 Dynamic)

### Form Controls (5 Selectors)

| Dusk Selector | Element Type | Purpose | Example Usage |
|---------------|--------------|---------|---------------|
| `search-input` | `<input type="text">` | Main search query input | `$browser->type('@search-input', 'legal precedent')` |
| `agent-filter` | `<select>` | Agent type dropdown filter | `$browser->select('@agent-filter', 'decision_discovery')` |
| `limit-input` | `<input type="number">` | Results limit number input | `$browser->type('@limit-input', '10')` |
| `search-button` | `<button>` | Primary search action button | `$browser->click('@search-button')` |
| `reset-button` | `<button>` | Reset/clear search button | `$browser->click('@reset-button')` |

---

### State & Display Elements (6 Selectors)

| Dusk Selector | Element Type | Purpose | Example Usage |
|---------------|--------------|---------|---------------|
| `error-message` | `<div>` | Global error message display | `$browser->assertPresent('@error-message')` |
| `search-input-error` | `<div>` | Search input validation error | `$browser->assertSee('Please enter a search query')->within('@search-input-error')` |
| `result-count` | `<div>` | Results count display | `$browser->assertSeeIn('@result-count', 'Found 5 results')` |
| `results-list` | `<div>` | Container for all result items | `$browser->waitFor('@results-list', 10)` |
| `empty-state` | `<div>` | No results empty state | `$browser->assertVisible('@empty-state')` |
| `search-method` | `<div>` | Search method indicator | `$browser->assertSeeIn('@search-method', 'Vector Similarity')` |

---

### Result Item Selectors (Dynamic - Per Result)

Each result has an index starting from 0. Replace `{index}` with actual number (e.g., `result-0`, `result-1`).

| Dusk Selector Pattern | Element Type | Purpose | Example Usage |
|-----------------------|--------------|---------|---------------|
| `result-{index}` | `<div>` | Individual result card container | `$browser->assertPresent('@result-0')` |
| `result-{index}-agent` | `<span>` | Agent name badge | `$browser->assertSeeIn('@result-0-agent', 'Research Agent')` |
| `result-{index}-similarity` | `<span>` | Similarity percentage badge | `$browser->assertSeeIn('@result-0-similarity', '95.2%')` |
| `result-{index}-access-count` | `<span>` | Access count badge | `$browser->assertSeeIn('@result-0-access-count', '3 accesses')` |
| `result-{index}-content` | `<div>` | Memory content text | `$browser->assertVisible('@result-0-content')` |
| `result-{index}-metadata` | `<div>` | Metadata tags container | `$browser->assertPresent('@result-0-metadata')` |
| `result-{index}-timestamp` | `<div>` | Creation timestamp | `$browser->assertSeeIn('@result-0-timestamp', 'Created')` |

---

## Usage Examples

### Basic Search Flow
```php
$browser->visit('/federated-memory-search')
    ->type('@search-input', 'proportionality')
    ->select('@agent-filter', 'decision_discovery')
    ->type('@limit-input', '5')
    ->click('@search-button')
    ->waitFor('@results-list', 10)
    ->assertPresent('@result-0')
    ->assertSeeIn('@result-0-agent', 'Decision Discovery');
```

### Testing Empty State
```php
$browser->visit('/federated-memory-search')
    ->type('@search-input', 'nonexistentquery123')
    ->click('@search-button')
    ->waitFor('@empty-state', 10)
    ->assertSee('No results found');
```

### Testing Reset Functionality
```php
$browser->visit('/federated-memory-search')
    ->type('@search-input', 'test query')
    ->click('@search-button')
    ->waitFor('@reset-button', 10)
    ->click('@reset-button')
    ->assertInputValue('@search-input', '')
    ->assertMissing('@results-list');
```

### Testing Loading States
```php
$browser->visit('/federated-memory-search')
    ->type('@search-input', 'test')
    ->click('@search-button')
    ->assertSee('Searching...')
    ->waitFor('@results-list', 10);
```

### Testing Result Details
```php
$browser->visit('/federated-memory-search')
    ->type('@search-input', 'legal')
    ->click('@search-button')
    ->waitFor('@result-0', 10)
    ->with('@result-0', function ($result) {
        $result->assertPresent('@result-0-agent')
               ->assertPresent('@result-0-similarity')
               ->assertPresent('@result-0-access-count')
               ->assertPresent('@result-0-content')
               ->assertPresent('@result-0-timestamp');
    });
```

### Testing Multiple Results
```php
$browser->visit('/federated-memory-search')
    ->type('@search-input', 'common query')
    ->click('@search-button')
    ->waitFor('@results-list', 10)
    ->assertPresent('@result-0')
    ->assertPresent('@result-1')
    ->assertPresent('@result-2')
    ->assertMissing('@result-10'); // Limit was 10, so no 11th result
```

### Testing Error States
```php
// Validation error
$browser->visit('/federated-memory-search')
    ->click('@search-button')
    ->waitFor('@search-input-error')
    ->assertSee('Please enter a search query');

// Global error
$browser->visit('/federated-memory-search')
    ->type('@search-input', 'trigger_error')
    ->click('@search-button')
    ->waitFor('@error-message', 10)
    ->assertSee('Search failed');
```

### Testing Agent Filter
```php
$browser->visit('/federated-memory-search')
    ->type('@search-input', 'test')
    ->select('@agent-filter', 'research_agent')
    ->click('@search-button')
    ->waitFor('@result-count', 10)
    ->assertSee('from research_agent');
```

### Testing Search Method Indicator
```php
// Vector search
$browser->visit('/federated-memory-search')
    ->type('@search-input', 'proportionality')
    ->click('@search-button')
    ->waitFor('@search-method', 10)
    ->assertSeeIn('@search-method', 'Vector Similarity Search');

// Text search fallback
$browser->visit('/federated-memory-search')
    ->type('@search-input', 'fallback query')
    ->click('@search-button')
    ->waitFor('@search-method', 10)
    ->assertSeeIn('@search-method', 'Text Search');
```

---

## Selector Naming Convention

### Pattern
- **Form inputs:** `{field-name}-input` (e.g., `search-input`, `limit-input`)
- **Buttons:** `{action}-button` (e.g., `search-button`, `reset-button`)
- **Filters/Dropdowns:** `{name}-filter` (e.g., `agent-filter`)
- **Display elements:** `{content-name}` (e.g., `result-count`, `empty-state`)
- **Result items:** `result-{index}` (0-indexed)
- **Result sub-elements:** `result-{index}-{field}` (e.g., `result-0-agent`)

### Best Practices
1. **Always use `@` prefix** in Dusk tests: `$browser->click('@search-button')`
2. **Wait for dynamic elements:** Use `waitFor()` for elements that appear after actions
3. **Use `with()` for scoped assertions:** Test within specific result cards
4. **Check visibility:** Use `assertVisible()` and `assertPresent()` appropriately
5. **Test loading states:** Verify wire:loading selectors appear during actions

---

## Conditional Selectors

### Elements that may or may not appear:

| Selector | Condition | Notes |
|----------|-----------|-------|
| `error-message` | Only if `$errorMessage` is set | Global errors |
| `search-input-error` | Only if validation fails | Input-specific errors |
| `reset-button` | Only if `$searchPerformed` is true | Appears after search |
| `results-list` | Only if `$searchPerformed` is true | Contains result cards |
| `empty-state` | Only if `$searchPerformed` and no results | No results message |
| `result-{index}-similarity` | Only if vector search | Distance-based score |
| `result-{index}-metadata` | Only if metadata exists | Optional metadata tags |

---

## Common Test Assertions

### Form Interactions
```php
// Check input values
$browser->assertInputValue('@search-input', 'expected value');

// Check selected option
$browser->assertSelected('@agent-filter', 'research_agent');

// Check element presence
$browser->assertPresent('@search-button');
$browser->assertVisible('@search-button');
$browser->assertMissing('@reset-button');
```

### Content Assertions
```php
// Check text content
$browser->assertSee('Search Results');
$browser->assertSeeIn('@result-count', 'Found 5 results');
$browser->assertDontSee('Error message');

// Check within element
$browser->with('@result-0', function ($result) {
    $result->assertSee('Decision Discovery');
});
```

### State Assertions
```php
// Check loading states
$browser->assertSee('Searching...');
$browser->assertAttribute('@search-button', 'disabled', 'true');

// Check visibility
$browser->assertVisible('@results-list');
$browser->assertMissing('@empty-state');
```

---

## Quick Reference Checklist

### Before Search
- [ ] `search-input` - Empty or has value
- [ ] `agent-filter` - Default "All Agents"
- [ ] `limit-input` - Default value (10)
- [ ] `search-button` - Visible and enabled
- [ ] `reset-button` - Not visible
- [ ] `results-list` - Not visible
- [ ] `error-message` - Not visible

### During Search (Loading)
- [ ] `search-button` - Shows "Searching..." with spinner
- [ ] `search-button` - Disabled
- [ ] Loading overlay - Visible with backdrop blur

### After Successful Search (With Results)
- [ ] `search-button` - Back to normal state
- [ ] `reset-button` - Visible
- [ ] `results-list` - Visible
- [ ] `result-count` - Shows count
- [ ] `result-0`, `result-1`, etc. - Visible (up to limit)
- [ ] `search-method` - Shows method used
- [ ] `empty-state` - Not visible

### After Search (No Results)
- [ ] `empty-state` - Visible
- [ ] `results-list` - Not visible or empty
- [ ] `result-count` - Shows "0 results"
- [ ] `reset-button` - Visible

### After Reset
- [ ] `search-input` - Empty
- [ ] `agent-filter` - Reset to "All Agents"
- [ ] `reset-button` - Not visible
- [ ] `results-list` - Not visible
- [ ] `error-message` - Not visible

### On Validation Error
- [ ] `search-input-error` - Visible
- [ ] Error message - "Please enter a search query"
- [ ] `search-input` - Has error styling (red border)

### On Search Failure
- [ ] `error-message` - Visible
- [ ] Error text - "Search failed: ..."
- [ ] Error icon - Visible

---

## Selector Coverage: 100% ✅

All interactive elements, display elements, and result components have Dusk selectors for comprehensive testing coverage.

**Total Selectors:** 18 base + 6 dynamic per result = 24+ selectors

---

## Related Documentation

- **Full Testing Guide:** `tests/Browser/TESTING_FEDERATED_MEMORY_SEARCH.md`
- **Redesign Summary:** `FEDERATED_MEMORY_SEARCH_REDESIGN_SUMMARY.md`
- **Component File:** `resources/views/livewire/federated-memory-search.blade.php`
- **PHP Component:** `app/Http/Livewire/FederatedMemorySearch.php`
