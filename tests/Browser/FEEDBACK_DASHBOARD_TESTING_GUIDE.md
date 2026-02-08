# FeedbackDashboard Testing Guide

## Component Overview
**Component:** FeedbackDashboard
**Files:**
- `/home/user/ai-legal-war-machine/app/Http/Livewire/FeedbackDashboard.php`
- `/home/user/ai-legal-war-machine/resources/views/livewire/feedback-dashboard.blade.php`

**Purpose:** Displays statistics and metrics for learning opportunities and feedback, allowing users to monitor AI confidence levels, review progress, and recent activity.

---

## All Dusk Selectors Reference

### Page Structure
- `feedback-dashboard` - Main container
- `dashboard-title` - Page title
- `dashboard-description` - Page description text
- `loading-overlay` - Full-page loading overlay during refresh
- `loading-message` - Loading message text

### Statistics Cards (Primary)
#### Total Opportunities Card
- `stat-card-total` - Card container
- `total-label` - Card title
- `total-opportunities` - Stat value
- `total-description` - Card description

#### Pending Review Card
- `stat-card-pending` - Card container
- `pending-label` - Card title
- `pending-opportunities` - Stat value
- `pending-description` - Card description

#### Reviewed Card
- `stat-card-reviewed` - Card container
- `reviewed-label` - Card title
- `reviewed-opportunities` - Stat value
- `reviewed-description` - Card description

#### Completion Rate Card
- `stat-card-completion` - Card container
- `completion-label` - Card title
- `completion-rate` - Stat value (percentage)
- `completion-description` - Card description

### Type Breakdown Card
- `breakdown-card` - Card container
- `breakdown-title` - Card title
- `no-pending-wrapper` - Empty state wrapper
- `no-pending-message` - Empty state message
- `type-breakdown-list` - List container (when data exists)
- `type-item-{index}` - Individual type item (0-indexed)
- `type-name-{index}` - Type name text
- `type-count-{index}` - Type count badge

### Average Confidence Card
- `stat-card-confidence` - Card container
- `confidence-title` - Card title
- `average-confidence` - Confidence value (main number)
- `confidence-description` - Card description
- `confidence-progress-wrapper` - Progress bar wrapper
- `confidence-progress-bar` - Progress bar fill element

### Recent Activity Card
- `recent-activity-card` - Card container
- `activity-title` - Card title
- `no-activity-wrapper` - Empty state wrapper
- `no-recent-activity-message` - Empty state message
- `recent-activity-list` - List container (when data exists)
- `activity-item-{index}` - Individual activity item (0-indexed)
- `activity-type-{index}` - Activity type text
- `activity-reviewer-{index}` - Reviewer name
- `activity-time-{index}` - Timestamp text

### Refresh Button
- `refresh-button-wrapper` - Button wrapper
- `refresh-button` - Main button element
- `refresh-text` - Button text (default state)
- `refresh-loading-text` - Button text (loading state)

---

## Interactive Elements

### 1. Refresh Statistics Button
**Selector:** `dusk="refresh-button"`
**Action:** `wire:click="refreshStats"`
**Loading States:**
- Button becomes disabled during refresh (`wire:loading.attr="disabled"`)
- Button text changes from "Refresh Statistics" to "Refreshing..."
- Spinner icon appears during loading
- Full-page loading overlay appears
- All stat cards show loading state

**Expected Behavior:**
- Triggers `refreshStats()` method in Livewire component
- Updates all statistics from database
- Re-renders all cards with new data
- Loading overlay covers entire page during refresh
- Button cannot be clicked multiple times (disabled state)

---

## User Workflows to Test

### Workflow 1: Initial Page Load
**Steps:**
1. Navigate to FeedbackDashboard component
2. Verify page header displays correctly
3. Verify all 4 stat cards render with data
4. Verify type breakdown card renders (with data or empty state)
5. Verify average confidence card renders with progress bar
6. Verify recent activity card renders (with data or empty state)
7. Verify refresh button is visible and enabled

**Expected Results:**
- All elements render without errors
- Data displays correctly from database
- Gradient backgrounds and shadows are visible
- Responsive layout works on different screen sizes

### Workflow 2: Refresh Statistics
**Steps:**
1. Click "Refresh Statistics" button
2. Observe loading overlay appears
3. Observe button changes to "Refreshing..." with spinner
4. Wait for refresh to complete
5. Verify statistics are updated
6. Verify loading overlay disappears
7. Verify button returns to normal state

**Expected Results:**
- Loading overlay appears immediately
- Button is disabled during refresh
- All stats refresh from database
- UI updates smoothly without flicker
- Button re-enables after completion

### Workflow 3: Empty State Handling
**Steps:**
1. Ensure database has no pending opportunities
2. Ensure database has no reviewed opportunities
3. Load dashboard
4. Verify "No pending opportunities" message in type breakdown
5. Verify "No recent activity" message in activity card

**Expected Results:**
- Empty state messages display with appropriate icons
- No errors occur with empty data
- Layout remains intact

### Workflow 4: Live Data Update (Livewire Event)
**Steps:**
1. Open dashboard in browser
2. Trigger `feedbackSubmitted` Livewire event from another component
3. Observe dashboard automatically refreshes
4. Verify stats update without manual button click

**Expected Results:**
- Dashboard listens to `feedbackSubmitted` event
- Stats refresh automatically
- Loading states appear during refresh
- No page reload required

### Workflow 5: Type Breakdown with Multiple Types
**Steps:**
1. Ensure database has pending opportunities of different types
2. Load dashboard
3. Verify type breakdown card shows all types
4. Verify each type has correct count
5. Verify items have gradient backgrounds and hover effects

**Expected Results:**
- All opportunity types are listed
- Counts are accurate
- Each item has proper styling
- Hover effects work smoothly

### Workflow 6: Recent Activity with Multiple Reviews
**Steps:**
1. Ensure database has reviewed opportunities
2. Load dashboard
3. Verify recent activity shows last 5 reviews
4. Verify reviewer names display correctly
5. Verify timestamps display as human-readable (e.g., "2 hours ago")

**Expected Results:**
- Maximum 5 activities shown
- Ordered by most recent first
- Reviewer information correct
- Relative timestamps display properly

---

## Accessibility Requirements

### ARIA Labels
- `aria-label="Refresh statistics"` on refresh button
- `aria-hidden="true"` on all decorative SVG icons

### Keyboard Navigation
1. **Tab Navigation:**
   - Refresh button must be keyboard accessible
   - Tab order should be logical (top to bottom, left to right)

2. **Enter/Space on Buttons:**
   - Refresh button should trigger on Enter or Space key press

3. **Focus Indicators:**
   - Visible focus ring on interactive elements
   - Focus state should be clearly visible against gradients

### Screen Reader Support
1. **Loading States:**
   - Screen readers should announce when loading starts
   - Screen readers should announce when loading completes

2. **Dynamic Content:**
   - When stats update, screen readers should be notified
   - Use `aria-live` regions for dynamic stat updates (future enhancement)

3. **Empty States:**
   - Empty state messages should be announced to screen readers

### Color Contrast
- Text on gradient backgrounds must meet WCAG AA standards
- White text on colored stat cards: verify contrast ratio ≥ 4.5:1
- Dark text on white cards: verify contrast ratio ≥ 4.5:1

---

## Mobile Testing Scenarios

### Responsive Breakpoints

#### Mobile (< 768px)
**Test Cases:**
1. Stat cards stack vertically (grid-cols-1)
2. All text remains readable
3. Touch targets are at least 44x44px
4. Gradients render correctly
5. Loading overlay covers full viewport
6. Refresh button is easily tappable
7. Scrolling is smooth
8. No horizontal overflow

**Expected Layout:**
- 1 column layout for stat cards
- 1 column for breakdown/confidence cards
- Full-width recent activity card
- Centered refresh button

#### Tablet (768px - 1024px)
**Test Cases:**
1. Stat cards display in 2 columns (md:grid-cols-2)
2. Breakdown and confidence cards side-by-side
3. Proper spacing and padding maintained
4. Touch-friendly interactive elements

**Expected Layout:**
- 2 column layout for stat cards
- 2 column for secondary cards
- Optimized spacing

#### Desktop (≥ 1024px)
**Test Cases:**
1. Stat cards display in 4 columns (lg:grid-cols-4)
2. All hover effects work properly
3. Gradients display with full quality
4. Shadows appear on hover

**Expected Layout:**
- 4 column layout for stat cards
- 2 column for secondary cards
- Full hover effects active

### Touch Interactions
1. **Tap Refresh Button:**
   - Single tap triggers refresh
   - Double-tap prevention works (disabled state)
   - Loading feedback is immediate

2. **Scrolling:**
   - Smooth scroll through all cards
   - No scroll jank or performance issues
   - Sticky elements (if any) work correctly

3. **Hover States on Mobile:**
   - Verify hover styles don't interfere with touch
   - Cards should not get "stuck" in hover state

---

## Edge Cases

### Data Edge Cases

1. **Zero Values:**
   - All stats are 0
   - Completion rate shows 0%
   - Average confidence shows 0.0
   - **Expected:** No division errors, displays "0" properly

2. **Very Large Numbers:**
   - Total opportunities > 1,000,000
   - **Expected:** Numbers display without breaking layout

3. **Decimal Precision:**
   - Completion rate: 33.333...%
   - Average confidence: 0.876543
   - **Expected:** Properly rounded (completion to 1 decimal, confidence to 2)

4. **Missing Reviewer:**
   - Reviewed opportunity has no reviewer assigned
   - **Expected:** Displays "Unknown" as reviewer name

5. **Null Timestamps:**
   - `reviewed_at` is null
   - **Expected:** Graceful handling, no errors

6. **Many Opportunity Types:**
   - Type breakdown has 20+ different types
   - **Expected:** All types display, scrollable if needed

7. **Long Type Names:**
   - Opportunity type name is very long (e.g., "very_long_opportunity_type_name_that_might_break_layout")
   - **Expected:** Text wraps or truncates properly

### Loading Edge Cases

1. **Slow Database Query:**
   - Simulate slow database response (5+ seconds)
   - **Expected:** Loading overlay persists, no timeout errors

2. **Rapid Clicking:**
   - Click refresh button multiple times quickly
   - **Expected:** Only one request sent, button disabled

3. **Network Failure:**
   - Simulate network disconnection during refresh
   - **Expected:** Error handling (if implemented), or graceful failure

### Browser Compatibility

1. **Modern Browsers:**
   - Chrome 90+
   - Firefox 88+
   - Safari 14+
   - Edge 90+

2. **Features to Test:**
   - CSS Grid layout
   - Gradient backgrounds (`gradient-to-br`)
   - Backdrop blur (`backdrop-blur-sm`)
   - CSS transforms (`transform: translateY`)
   - Transitions (`transition-all duration-200`)

3. **Fallbacks:**
   - Verify gradients work in all browsers
   - Verify backdrop blur has fallback if unsupported

---

## Performance Testing

### Metrics to Monitor

1. **Initial Load Time:**
   - Time to render all cards: < 1 second

2. **Refresh Time:**
   - Time to refresh all stats: < 2 seconds (depends on DB)

3. **Animation Performance:**
   - Hover effects should be 60fps
   - Loading spinner should be smooth

4. **Memory Usage:**
   - No memory leaks on repeated refreshes
   - Monitor with browser DevTools

### Load Testing

1. **Large Dataset:**
   - 10,000+ learning opportunities
   - 100+ different opportunity types
   - **Expected:** Dashboard still loads in reasonable time

2. **Repeated Refreshes:**
   - Refresh 100 times in succession
   - **Expected:** No performance degradation, no memory leaks

---

## Visual Regression Testing

### Elements to Screenshot

1. **Full Dashboard - Default State**
2. **Loading Overlay Active**
3. **Stat Cards - Hover State** (desktop)
4. **Empty State - No Pending**
5. **Empty State - No Activity**
6. **Type Breakdown - Multiple Types**
7. **Recent Activity - Multiple Reviews**
8. **Mobile View - All Breakpoints**
9. **Button - Default State**
10. **Button - Loading State**
11. **Button - Hover State**

### Visual Checks

1. **Gradients:**
   - All stat cards have smooth gradients
   - No banding or color artifacts
   - Gradients match design specifications

2. **Shadows:**
   - Shadow elevation increases on hover
   - Shadows are consistent across cards
   - No harsh or pixelated shadows

3. **Typography:**
   - Font sizes are correct
   - Font weights are appropriate
   - Line heights provide good readability

4. **Spacing:**
   - Consistent padding and margins
   - Proper gap between grid items
   - No overlapping elements

5. **Icons:**
   - All SVG icons render correctly
   - Icons are properly sized
   - Icon colors match design

---

## Dusk Test Structure Example

```php
/** @test */
public function it_displays_dashboard_statistics()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/feedback-dashboard')
                ->assertVisible('@feedback-dashboard')
                ->assertSee('Feedback Dashboard')
                ->assertVisible('@stat-card-total')
                ->assertVisible('@stat-card-pending')
                ->assertVisible('@stat-card-reviewed')
                ->assertVisible('@stat-card-completion')
                ->assertVisible('@breakdown-card')
                ->assertVisible('@stat-card-confidence')
                ->assertVisible('@recent-activity-card')
                ->assertVisible('@refresh-button');
    });
}

/** @test */
public function it_refreshes_statistics_when_button_clicked()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/feedback-dashboard')
                ->click('@refresh-button')
                ->assertVisible('@loading-overlay')
                ->assertSee('Refreshing statistics...')
                ->assertAttribute('@refresh-button', 'disabled', 'true')
                ->waitUntilMissing('@loading-overlay', 10)
                ->assertEnabled('@refresh-button');
    });
}

/** @test */
public function it_displays_empty_state_when_no_pending_opportunities()
{
    // Clear all pending opportunities
    LearningOpportunity::query()->delete();

    $this->browse(function (Browser $browser) {
        $browser->visit('/feedback-dashboard')
                ->assertVisible('@no-pending-wrapper')
                ->assertSee('No pending opportunities');
    });
}

/** @test */
public function it_displays_type_breakdown_correctly()
{
    // Create opportunities of different types
    LearningOpportunity::factory()->create([
        'opportunity_type' => 'Low Confidence Citation',
        'status' => 'pending'
    ]);

    LearningOpportunity::factory()->create([
        'opportunity_type' => 'Ambiguous Legal Query',
        'status' => 'pending'
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visit('/feedback-dashboard')
                ->assertVisible('@type-breakdown-list')
                ->assertVisible('@type-item-0')
                ->assertVisible('@type-item-1')
                ->assertSee('Low Confidence Citation')
                ->assertSee('Ambiguous Legal Query');
    });
}

/** @test */
public function it_displays_recent_activity_correctly()
{
    $user = User::factory()->create(['name' => 'John Doe']);

    LearningOpportunity::factory()->create([
        'opportunity_type' => 'Citation Review',
        'status' => 'reviewed',
        'reviewed_by' => $user->id,
        'reviewed_at' => now()->subHours(2)
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visit('/feedback-dashboard')
                ->assertVisible('@recent-activity-list')
                ->assertVisible('@activity-item-0')
                ->assertSee('Citation Review')
                ->assertSee('Reviewed by John Doe')
                ->assertSee('2 hours ago');
    });
}

/** @test */
public function it_is_mobile_responsive()
{
    $this->browse(function (Browser $browser) {
        // Test mobile view
        $browser->resize(375, 667)
                ->visit('/feedback-dashboard')
                ->assertVisible('@feedback-dashboard')
                ->assertVisible('@stat-card-total')
                ->assertVisible('@refresh-button');

        // Test tablet view
        $browser->resize(768, 1024)
                ->assertVisible('@feedback-dashboard');

        // Test desktop view
        $browser->resize(1920, 1080)
                ->assertVisible('@feedback-dashboard');
    });
}

/** @test */
public function it_prevents_double_clicks_on_refresh_button()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/feedback-dashboard')
                ->click('@refresh-button')
                ->click('@refresh-button') // Second click should be ignored
                ->assertAttribute('@refresh-button', 'disabled', 'true')
                ->waitUntilMissing('@loading-overlay', 10);

        // Verify only one refresh occurred (check network requests if needed)
    });
}

/** @test */
public function button_is_keyboard_accessible()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/feedback-dashboard')
                ->keys('@refresh-button', '{tab}') // Tab to button
                ->assertFocused('@refresh-button')
                ->keys('@refresh-button', '{enter}') // Activate with Enter
                ->assertVisible('@loading-overlay');
    });
}
```

---

## Testing Checklist

### Functional Testing
- [ ] Dashboard loads without errors
- [ ] All stat cards display correct values
- [ ] Refresh button triggers data reload
- [ ] Loading overlay appears during refresh
- [ ] Button is disabled during loading
- [ ] Empty states display correctly
- [ ] Type breakdown shows all types
- [ ] Recent activity shows last 5 reviews
- [ ] Completion rate calculates correctly
- [ ] Average confidence displays with 2 decimals
- [ ] Confidence progress bar width is correct
- [ ] Livewire event listener works

### Visual Testing
- [ ] All gradient backgrounds render correctly
- [ ] Shadows appear and intensify on hover
- [ ] Icons display properly
- [ ] Typography is consistent
- [ ] Spacing and alignment is correct
- [ ] Loading spinner animates smoothly
- [ ] Hover effects work (desktop only)
- [ ] Transitions are smooth (200ms duration)

### Accessibility Testing
- [ ] Refresh button has aria-label
- [ ] Decorative icons have aria-hidden
- [ ] Keyboard navigation works
- [ ] Focus indicators are visible
- [ ] Color contrast meets WCAG AA
- [ ] Screen reader announcements work
- [ ] All interactive elements are keyboard accessible

### Responsive Testing
- [ ] Mobile (375px): 1 column layout
- [ ] Tablet (768px): 2 column layout
- [ ] Desktop (1024px+): 4 column layout
- [ ] No horizontal scroll on mobile
- [ ] Touch targets are adequate (44x44px)
- [ ] Text remains readable at all sizes
- [ ] Gradients scale properly

### Edge Case Testing
- [ ] Zero values display correctly
- [ ] Large numbers don't break layout
- [ ] Missing reviewer shows "Unknown"
- [ ] Null timestamps handled gracefully
- [ ] Long type names wrap/truncate
- [ ] Many types display correctly
- [ ] Rapid clicking prevention works
- [ ] Slow queries show loading state

### Performance Testing
- [ ] Initial load < 1 second
- [ ] Refresh completes < 2 seconds
- [ ] Animations run at 60fps
- [ ] No memory leaks on repeated refresh
- [ ] Large datasets load acceptably

---

## Known Issues and Limitations

1. **Confidence Progress Bar:**
   - Assumes confidence is between 0 and 1
   - Uses `min($averageConfidence, 1) * 100` to prevent overflow
   - May need adjustment if confidence scale changes

2. **Recent Activity Limit:**
   - Hard-coded to 5 items
   - No pagination or "show more" functionality

3. **Type Breakdown Scrolling:**
   - If many types exist, card may become very tall
   - Consider adding max-height and scroll if > 10 types

4. **Real-time Updates:**
   - Relies on Livewire events
   - If event not fired, stats won't update automatically
   - Manual refresh still required

---

## Future Enhancements

1. **Auto-refresh:**
   - Add periodic auto-refresh (every 30 seconds)
   - User preference to enable/disable

2. **Filtering:**
   - Filter by date range
   - Filter by opportunity type
   - Filter by reviewer

3. **Sorting:**
   - Sort recent activity by different criteria
   - Sort type breakdown by count or name

4. **Export:**
   - Export statistics as CSV/PDF
   - Email reports

5. **Charts:**
   - Add visual charts for trends
   - Completion rate over time graph
   - Type breakdown pie chart

6. **Notifications:**
   - Toast notifications on successful refresh
   - Error messages if refresh fails

7. **Optimistic UI:**
   - Show immediate feedback before server response
   - Rollback if operation fails

---

## Test Data Setup

### Minimum Test Data
```php
// Create test data for comprehensive testing
use App\Models\LearningOpportunity;
use App\Models\User;

// Create users
$reviewer1 = User::factory()->create(['name' => 'Alice Johnson']);
$reviewer2 = User::factory()->create(['name' => 'Bob Smith']);

// Create pending opportunities
LearningOpportunity::factory()->count(15)->create([
    'status' => 'pending',
    'opportunity_type' => 'Low Confidence Citation',
    'confidence_score' => 0.45
]);

LearningOpportunity::factory()->count(8)->create([
    'status' => 'pending',
    'opportunity_type' => 'Ambiguous Legal Query',
    'confidence_score' => 0.62
]);

// Create reviewed opportunities
LearningOpportunity::factory()->count(12)->create([
    'status' => 'reviewed',
    'opportunity_type' => 'Citation Review',
    'confidence_score' => 0.78,
    'reviewed_by' => $reviewer1->id,
    'reviewed_at' => now()->subHours(rand(1, 48))
]);

LearningOpportunity::factory()->count(7)->create([
    'status' => 'reviewed',
    'opportunity_type' => 'Query Clarification',
    'confidence_score' => 0.85,
    'reviewed_by' => $reviewer2->id,
    'reviewed_at' => now()->subDays(rand(1, 7))
]);
```

### Expected Values from Test Data
- **Total Opportunities:** 42
- **Pending:** 23
- **Reviewed:** 19
- **Completion Rate:** 45.2%
- **Average Confidence:** ~0.68
- **Type Breakdown:** 2 types (15 + 8)
- **Recent Activity:** 5 items (most recent reviews)

---

## Conclusion

This testing guide provides comprehensive coverage for the FeedbackDashboard component. All interactive elements have Dusk selectors, and testing scenarios cover functional, visual, accessibility, responsive, and edge case requirements.

**Priority Testing Areas:**
1. Refresh functionality with loading states
2. Correct data display and calculations
3. Responsive layout across devices
4. Accessibility compliance
5. Empty state handling

For any questions or issues, refer to the component files or contact the development team.
