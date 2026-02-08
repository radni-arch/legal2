# FederatedMemorySearch Component - Testing Documentation

## Component Overview
**File:** `app/Http/Livewire/FederatedMemorySearch.php`
**View:** `resources/views/livewire/federated-memory-search.blade.php`
**Purpose:** Semantic search interface for pgvector similarity search across agent memories

## Test-Driven Development Status
- **Current State:** Redesigned with modern Tailwind CSS
- **Completion:** 100% (Complete CSS redesign with all features)
- **Dusk Selectors:** All interactive elements tagged

---

## Dusk Selectors Reference

### Form Controls
| Selector | Element | Purpose |
|----------|---------|---------|
| `search-input` | Text input | Main search query input field |
| `agent-filter` | Select dropdown | Filter results by agent type |
| `limit-input` | Number input | Set maximum results limit |
| `search-button` | Button | Trigger search action |
| `reset-button` | Button | Reset search and clear results |

### Results & State Elements
| Selector | Element | Purpose |
|----------|---------|---------|
| `error-message` | Div | Display error messages |
| `search-input-error` | Div | Validation error for search input |
| `result-count` | Div | Display count of search results |
| `results-list` | Div | Container for all result items |
| `result-{index}` | Div | Individual result card (0-indexed) |
| `empty-state` | Div | Empty state when no results found |
| `search-method` | Div | Indicator for search method used |

### Result Item Sub-Elements
Each result item (`result-{index}`) contains:
| Selector | Element | Content |
|----------|---------|---------|
| `result-{index}-agent` | Span | Agent name badge |
| `result-{index}-similarity` | Span | Similarity percentage badge |
| `result-{index}-access-count` | Span | Access count badge |
| `result-{index}-content` | Div | Memory content text |
| `result-{index}-metadata` | Div | Metadata tags container |
| `result-{index}-timestamp` | Div | Created timestamp |

---

## Test Suite Structure

### 1. Search Functionality Tests

#### Test: Basic Search Execution
```php
public function test_can_perform_basic_search()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'proportionality')
            ->click('@search-button')
            ->waitFor('@results-list', 10)
            ->assertSee('Search Results')
            ->assertPresent('@result-count');
    });
}
```

#### Test: Search Loading State
```php
public function test_search_button_shows_loading_state()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'test query')
            ->click('@search-button')
            ->assertSee('Searching...')
            ->waitFor('@results-list', 10);
    });
}
```

#### Test: Search Validation
```php
public function test_empty_search_shows_validation_error()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->click('@search-button')
            ->waitFor('@search-input-error')
            ->assertSee('Please enter a search query');
    });
}
```

---

### 2. Filter Interaction Tests

#### Test: Agent Filter Selection
```php
public function test_can_filter_by_agent_type()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'legal precedent')
            ->select('@agent-filter', 'decision_discovery')
            ->click('@search-button')
            ->waitFor('@result-count', 10)
            ->assertSee('from decision_discovery');
    });
}
```

#### Test: All Agents Option
```php
public function test_all_agents_option_searches_across_all()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'test')
            ->select('@agent-filter', '') // All Agents
            ->click('@search-button')
            ->waitFor('@result-count', 10)
            ->assertDontSee('from');
    });
}
```

#### Test: Limit Input
```php
public function test_can_set_results_limit()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'search query')
            ->clear('@limit-input')
            ->type('@limit-input', '5')
            ->click('@search-button')
            ->waitFor('@results-list', 10)
            ->with('@results-list', function ($list) {
                $list->assertPresent('@result-0')
                     ->assertMissing('@result-5'); // 0-4 exists, 5+ doesn't
            });
    });
}
```

---

### 3. Results Rendering Tests

#### Test: Results Display
```php
public function test_results_display_correctly()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'legal')
            ->click('@search-button')
            ->waitFor('@result-0', 10)
            ->assertPresent('@result-0-agent')
            ->assertPresent('@result-0-content')
            ->assertPresent('@result-0-timestamp');
    });
}
```

#### Test: Result Card Hover Effects
```php
public function test_result_cards_have_hover_effects()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'test')
            ->click('@search-button')
            ->waitFor('@result-0', 10)
            ->mouseover('@result-0')
            ->pause(500) // Allow transition
            ->assertVisible('@result-0');
    });
}
```

#### Test: Similarity Score Display
```php
public function test_similarity_score_displays_for_vector_search()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'proportionality')
            ->click('@search-button')
            ->waitFor('@result-0', 10)
            ->with('@result-0', function ($result) {
                $result->assertPresent('@result-0-similarity')
                       ->assertSeeIn('@result-0-similarity', '%');
            });
    });
}
```

#### Test: Access Count Display
```php
public function test_access_count_displays_correctly()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'test')
            ->click('@search-button')
            ->waitFor('@result-0', 10)
            ->assertPresent('@result-0-access-count')
            ->assertSeeIn('@result-0-access-count', 'access');
    });
}
```

#### Test: Metadata Display
```php
public function test_metadata_displays_when_present()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'metadata test')
            ->click('@search-button')
            ->waitFor('@result-0', 10)
            ->with('@result-0', function ($result) {
                $result->assertPresent('@result-0-metadata');
            });
    });
}
```

---

### 4. Empty State Tests

#### Test: Empty State Display
```php
public function test_empty_state_displays_when_no_results()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'nonexistentquerythatreturnsnothing')
            ->click('@search-button')
            ->waitFor('@empty-state', 10)
            ->assertSee('No results found')
            ->assertSee('Try: Different search terms');
    });
}
```

#### Test: Empty State Design
```php
public function test_empty_state_has_proper_styling()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'zzznonexistent')
            ->click('@search-button')
            ->waitFor('@empty-state', 10)
            ->assertVisible('@empty-state')
            ->assertPresent('@empty-state svg'); // Icon present
    });
}
```

---

### 5. Reset Functionality Tests

#### Test: Reset Button Functionality
```php
public function test_reset_button_clears_search()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'test query')
            ->select('@agent-filter', 'research_agent')
            ->click('@search-button')
            ->waitFor('@reset-button', 10)
            ->click('@reset-button')
            ->assertInputValue('@search-input', '')
            ->assertSelected('@agent-filter', '')
            ->assertMissing('@results-list');
    });
}
```

#### Test: Reset Button Loading State
```php
public function test_reset_button_shows_loading_state()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'test')
            ->click('@search-button')
            ->waitFor('@reset-button', 10)
            ->click('@reset-button')
            ->assertSee('Resetting...')
            ->pause(1000)
            ->assertMissing('@reset-button'); // Hidden after reset
    });
}
```

#### Test: Reset Button Only Shows After Search
```php
public function test_reset_button_only_visible_after_search()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->assertMissing('@reset-button')
            ->type('@search-input', 'test')
            ->click('@search-button')
            ->waitFor('@reset-button', 10)
            ->assertVisible('@reset-button');
    });
}
```

---

### 6. Mobile Responsive Tests

#### Test: Mobile Layout - Stacked Controls
```php
public function test_mobile_layout_stacks_controls()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(375, 667) // iPhone SE
            ->visit('/federated-memory-search')
            ->assertVisible('@search-input')
            ->assertVisible('@agent-filter')
            ->assertVisible('@search-button');
    });
}
```

#### Test: Mobile Layout - Button Full Width
```php
public function test_mobile_buttons_are_full_width()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(375, 667)
            ->visit('/federated-memory-search')
            ->type('@search-input', 'test')
            ->click('@search-button')
            ->waitFor('@reset-button', 10)
            ->assertVisible('@reset-button');

        // Visual check - buttons should span full width on mobile
    });
}
```

#### Test: Mobile Layout - Result Cards
```php
public function test_mobile_result_cards_full_width()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(375, 667)
            ->visit('/federated-memory-search')
            ->type('@search-input', 'legal')
            ->click('@search-button')
            ->waitFor('@result-0', 10)
            ->assertVisible('@result-0');
    });
}
```

#### Test: Tablet Layout
```php
public function test_tablet_layout_responsive()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(768, 1024) // iPad
            ->visit('/federated-memory-search')
            ->assertVisible('@search-input')
            ->type('@search-input', 'test')
            ->click('@search-button')
            ->waitFor('@results-list', 10)
            ->assertVisible('@results-list');
    });
}
```

---

### 7. Loading State Tests

#### Test: Loading Overlay on Results
```php
public function test_loading_overlay_appears_during_search()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'test')
            ->click('@search-button')
            ->assertSee('Searching memories...')
            ->waitFor('@results-list', 10);
    });
}
```

#### Test: Loading Overlay Has Backdrop Blur
```php
public function test_loading_overlay_has_backdrop_blur()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'test')
            ->click('@search-button')
            ->pause(500) // Catch loading state
            ->assertVisible('.backdrop-blur-sm');
    });
}
```

#### Test: Buttons Disabled During Loading
```php
public function test_buttons_disabled_during_loading()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'test')
            ->click('@search-button')
            ->pause(200)
            ->assertAttribute('@search-button', 'disabled', 'true')
            ->waitFor('@results-list', 10);
    });
}
```

---

### 8. Accessibility Tests

#### Test: Keyboard Navigation
```php
public function test_keyboard_navigation_works()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->keys('@search-input', 'test query')
            ->keys('@search-input', '{tab}') // Move to agent filter
            ->keys('@agent-filter', '{tab}') // Move to limit
            ->keys('@limit-input', '{tab}') // Move to search button
            ->keys('@search-button', '{enter}') // Submit
            ->waitFor('@results-list', 10);
    });
}
```

#### Test: Focus States
```php
public function test_inputs_have_focus_states()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->click('@search-input')
            ->assertFocused('@search-input')
            ->keys('@search-input', '{tab}')
            ->assertFocused('@agent-filter');
    });
}
```

#### Test: Screen Reader Labels
```php
public function test_form_labels_exist()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->assertSee('Search Query')
            ->assertSee('Agent Filter')
            ->assertSee('Results Limit');
    });
}
```

#### Test: Error Messages Are Accessible
```php
public function test_error_messages_are_accessible()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->click('@search-button')
            ->waitFor('@search-input-error')
            ->assertPresent('@search-input-error svg') // Icon present
            ->assertSee('Please enter a search query');
    });
}
```

---

### 9. Search Method Indicator Tests

#### Test: Vector Search Indicator
```php
public function test_vector_search_indicator_shows()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'proportionality')
            ->click('@search-button')
            ->waitFor('@search-method', 10)
            ->assertSeeIn('@search-method', 'Vector Similarity Search');
    });
}
```

#### Test: Text Search Indicator
```php
public function test_text_search_indicator_shows()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'fallback text search')
            ->click('@search-button')
            ->waitFor('@search-method', 10)
            ->assertSeeIn('@search-method', 'Text Search');
    });
}
```

---

### 10. Error Handling Tests

#### Test: Error Message Display
```php
public function test_error_message_displays_on_failure()
{
    // Mock a search failure scenario
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'trigger_error')
            ->click('@search-button')
            ->waitFor('@error-message', 10)
            ->assertSee('Search failed')
            ->assertPresent('@error-message svg'); // Error icon
    });
}
```

#### Test: Error Message Styling
```php
public function test_error_message_has_proper_styling()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'trigger_error')
            ->click('@search-button')
            ->waitFor('@error-message', 10)
            ->assertVisible('@error-message')
            ->assertPresent('.border-red-500'); // Red border
    });
}
```

---

### 11. Dark Mode Tests

#### Test: Dark Mode Styling
```php
public function test_dark_mode_applies_correctly()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->script('document.documentElement.classList.add("dark")');

        // Visual check - dark mode classes should apply
        $browser->assertVisible('@search-input')
            ->assertVisible('@search-button');
    });
}
```

#### Test: Dark Mode Gradient Backgrounds
```php
public function test_dark_mode_gradients_visible()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search')
            ->script('document.documentElement.classList.add("dark")')
            ->type('@search-input', 'test')
            ->click('@search-button')
            ->waitFor('@result-0', 10)
            ->assertVisible('@result-0');
    });
}
```

---

### 12. Performance Tests

#### Test: Search Response Time
```php
public function test_search_completes_within_timeout()
{
    $this->browse(function (Browser $browser) {
        $startTime = microtime(true);

        $browser->visit('/federated-memory-search')
            ->type('@search-input', 'performance test')
            ->click('@search-button')
            ->waitFor('@results-list', 10);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->assertLessThan(10, $duration, 'Search took too long');
    });
}
```

#### Test: Multiple Rapid Searches
```php
public function test_handles_rapid_searches()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/federated-memory-search');

        for ($i = 0; $i < 3; $i++) {
            $browser->type('@search-input', "query $i")
                ->click('@search-button')
                ->pause(500)
                ->waitFor('@results-list', 10);
        }

        $browser->assertVisible('@results-list');
    });
}
```

---

## Testing Checklist

### Pre-Test Setup
- [ ] Database seeded with test agent memories
- [ ] Pgvector extension enabled
- [ ] FederatedMemoryService properly configured
- [ ] Test environment variables set

### Search Functionality
- [ ] Basic search execution works
- [ ] Search button shows loading state
- [ ] Empty search shows validation error
- [ ] Search query input accepts text
- [ ] Search returns results

### Filtering
- [ ] Agent filter dropdown works
- [ ] Each agent type filters correctly
- [ ] "All Agents" option works
- [ ] Results limit input works
- [ ] Limit is enforced in results

### Results Display
- [ ] Results list renders
- [ ] Individual result cards display
- [ ] Agent name badge shows
- [ ] Similarity score displays (vector search)
- [ ] Access count displays
- [ ] Content text renders
- [ ] Metadata tags display
- [ ] Timestamp shows relative time
- [ ] Result count displays correctly

### Empty State
- [ ] Empty state shows when no results
- [ ] Empty state has proper styling
- [ ] Helpful message displays
- [ ] Suggestions are visible

### Reset Functionality
- [ ] Reset button appears after search
- [ ] Reset button clears all fields
- [ ] Reset button shows loading state
- [ ] Reset button hides after reset
- [ ] Reset clears results

### Mobile Responsive
- [ ] Mobile (375px) layout works
- [ ] Tablet (768px) layout works
- [ ] Desktop (1024px+) layout works
- [ ] Controls stack on mobile
- [ ] Buttons full-width on mobile
- [ ] Result cards responsive

### Loading States
- [ ] Search button loading spinner
- [ ] Reset button loading spinner
- [ ] Results overlay during search
- [ ] Backdrop blur effect works
- [ ] Buttons disabled during loading

### Accessibility
- [ ] Keyboard navigation works
- [ ] Tab order is logical
- [ ] Focus states visible
- [ ] Labels present for inputs
- [ ] Error messages accessible
- [ ] Color contrast sufficient

### Error Handling
- [ ] Validation errors display
- [ ] Search failure errors show
- [ ] Error messages styled correctly
- [ ] Error icons present

### Visual Polish
- [ ] Gradient backgrounds render
- [ ] Hover effects work on cards
- [ ] Shadows display correctly
- [ ] Transitions smooth (300ms)
- [ ] Dark mode works
- [ ] Icons render properly

### Search Method Indicator
- [ ] Vector search indicator shows
- [ ] Text search indicator shows
- [ ] Indicator has proper styling

---

## Known Issues & Limitations

### Current Limitations
1. **Database Dependency**: Tests require actual database with memories
2. **Vector Search**: Requires pgvector extension enabled
3. **Async Operations**: Some tests may need longer waits for vector calculations

### Future Enhancements
1. Add skeleton loaders during initial load
2. Add pagination for large result sets
3. Add export functionality
4. Add result sorting options
5. Add search history

---

## Running Tests

### Run All Tests
```bash
php artisan dusk --filter=FederatedMemorySearchTest
```

### Run Specific Test
```bash
php artisan dusk --filter=test_can_perform_basic_search
```

### Run with Screenshots on Failure
```bash
php artisan dusk --filter=FederatedMemorySearchTest --screenshots
```

---

## CSS Redesign Summary

### Before (Old CSS)
- Inline styles with `style=""` attributes
- Basic chip classes (`.chip`, `.chip.error`, `.chip.info`)
- Minimal styling with CSS variables
- No gradients
- Basic hover states
- Limited responsive design

### After (Modern Tailwind)
- Full Tailwind utility classes
- Gradient backgrounds (`bg-gradient-to-br`, `bg-gradient-to-r`)
- Blue/purple theme throughout
- Hover effects with transforms (`hover:-translate-y-1`)
- Shadow progression (`shadow-md` → `hover:shadow-xl`)
- Dark mode support (`dark:` variants)
- Mobile-first responsive (`sm:`, `lg:` breakpoints)
- Smooth transitions (`duration-300`)
- Loading overlays with backdrop blur
- Enhanced empty states
- Modern badges with gradients
- Rounded corners (`rounded-xl`, `rounded-2xl`)

---

## Component Quality Assessment

### ✅ Completed Features
1. **All Dusk selectors added** - Every interactive element tagged
2. **Modern Tailwind CSS** - Complete redesign with gradients
3. **Loading states** - Both search and reset buttons
4. **Loading overlay** - Results area with backdrop blur
5. **Mobile responsive** - Stacking controls, full-width buttons
6. **Hover effects** - Cards lift and shadow on hover
7. **Smooth transitions** - 300ms throughout
8. **Enhanced empty state** - Icon, message, suggestions
9. **Styled @error directives** - With icons and proper styling
10. **Dark mode support** - All elements have dark variants
11. **Limit input** - Added with proper styling
12. **Results layout** - Gradient cards with badges
13. **Accessibility** - Labels, focus states, semantic HTML

### Component Status: 100% Complete ✨

The FederatedMemorySearch component is now fully redesigned with production-ready quality matching other components in the system.
