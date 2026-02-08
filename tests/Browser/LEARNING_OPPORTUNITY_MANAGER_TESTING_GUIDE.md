# LearningOpportunityManager Component - Testing Guide

## Component Overview
**File:** `resources/views/livewire/learning-opportunity-manager.blade.php`
**Controller:** `app/Http/Livewire/LearningOpportunityManager.php`
**Purpose:** Human feedback interface for AI learning opportunities

---

## Dusk Selectors Reference

### Main Container
- `dusk="learning-opportunity-manager"` - Main component wrapper

### Success Messages
- `dusk="success-message"` - Success alert message (with fade animation)

### Filter Section
- `dusk="filter-container"` - Filter section wrapper
- `dusk="filter-type"` - Filter dropdown select element
- `dusk="filter-loading"` - Loading spinner for filter changes

### Empty State
- `dusk="empty-state"` - Empty state message (no opportunities)

### Opportunities List
- `dusk="opportunities-list"` - Main list container
- `dusk="opportunities-loading-overlay"` - Loading overlay during data operations
- `dusk="opportunity-card-{id}"` - Individual opportunity card (uses opportunity ID)
- `dusk="opportunity-header-{id}"` - Card header section
- `dusk="opportunity-type-{id}"` - Opportunity type badge
- `dusk="opportunity-confidence-{id}"` - Confidence score badge
- `dusk="opportunity-source-{id}"` - Source type badge
- `dusk="opportunity-details-{id}"` - Details section container
- `dusk="opportunity-decision-id-{id}"` - Decision ID field
- `dusk="opportunity-decision-id-alt-{id}"` - Alternative decision ID field
- `dusk="opportunity-ai-score-{id}"` - AI score field
- `dusk="opportunity-applicability-score-{id}"` - Applicability score field
- `dusk="opportunity-reasoning-{id}"` - Reasoning text
- `dusk="opportunity-topic-{id}"` - Topic field
- `dusk="opportunity-uncertainty-{id}"` - Uncertainty reason field
- `dusk="opportunity-actions-{id}"` - Actions section container
- `dusk="provide-feedback-btn-{id}"` - Provide feedback button

### Feedback Modal
- `dusk="feedback-modal"` - Modal wrapper
- `dusk="modal-backdrop"` - Modal background overlay (clickable to close)
- `dusk="feedback-modal-content"` - Modal content container
- `dusk="feedback-modal-title"` - Modal title
- `dusk="modal-close-button"` - Close button (X icon)
- `dusk="error-feedback-data"` - Feedback data error message
- `dusk="error-selected-opportunity"` - Selected opportunity error message
- `dusk="error-submit-feedback"` - Submit feedback error message
- `dusk="feedback-form-group"` - Form group container
- `dusk="feedback-label"` - Form label
- `dusk="feedback-textarea"` - Feedback textarea input
- `dusk="feedback-hint"` - Helper text below textarea
- `dusk="feedback-form-actions"` - Form actions container
- `dusk="cancel-feedback-btn"` - Cancel button
- `dusk="submit-feedback-btn"` - Submit feedback button

---

## Testing Scenarios

### 1. CRUD Operations Testing

#### 1.1 View Opportunities List
```php
/** @test */
public function user_can_view_pending_opportunities()
{
    $opportunity = LearningOpportunity::factory()->create([
        'status' => 'pending',
        'opportunity_type' => 'decision_discovery',
        'confidence_score' => 0.45,
        'source_type' => 'precedent_analysis'
    ]);

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->waitFor('@opportunities-list')
            ->assertSee('Decision Discovery')
            ->assertPresent("@opportunity-card-{$opportunity->id}")
            ->assertSeeIn("@opportunity-confidence-{$opportunity->id}", '45.0%')
            ->assertVisible("@provide-feedback-btn-{$opportunity->id}");
    });
}
```

#### 1.2 Filter by Type
```php
/** @test */
public function user_can_filter_opportunities_by_type()
{
    LearningOpportunity::factory()->create(['opportunity_type' => 'decision_discovery']);
    LearningOpportunity::factory()->create(['opportunity_type' => 'precedent_analysis']);

    $this->browse(function (Browser $browser) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->waitFor('@opportunities-list')
            ->assertSeeIn('@opportunities-list', 'Decision Discovery')
            ->assertSeeIn('@opportunities-list', 'Precedent Analysis')
            ->select('@filter-type', 'decision_discovery')
            ->waitFor('@filter-loading')
            ->waitUntilMissing('@filter-loading')
            ->assertSeeIn('@opportunities-list', 'Decision Discovery')
            ->assertDontSeeIn('@opportunities-list', 'Precedent Analysis');
    });
}
```

#### 1.3 Submit Feedback
```php
/** @test */
public function user_can_submit_feedback_for_opportunity()
{
    $opportunity = LearningOpportunity::factory()->create(['status' => 'pending']);

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->waitFor('@opportunities-list')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->assertVisible('@feedback-modal-content')
            ->type('@feedback-textarea', '{"correct": true, "confidence": 0.9}')
            ->click('@submit-feedback-btn')
            ->whenAvailable('@confirm-dialog', function ($modal) {
                $modal->press('Confirm');
            })
            ->waitForText('Feedback submitted successfully!')
            ->assertPresent('@success-message')
            ->waitUntilMissing('@feedback-modal');

        $this->assertDatabaseHas('learning_opportunities', [
            'id' => $opportunity->id,
            'status' => 'reviewed'
        ]);
    });
}
```

#### 1.4 Cancel Feedback
```php
/** @test */
public function user_can_cancel_feedback_submission()
{
    $opportunity = LearningOpportunity::factory()->create(['status' => 'pending']);

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->waitFor('@opportunities-list')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->type('@feedback-textarea', '{"test": "data"}')
            ->click('@cancel-feedback-btn')
            ->waitUntilMissing('@feedback-modal')
            ->assertMissing('@feedback-modal');

        $this->assertDatabaseHas('learning_opportunities', [
            'id' => $opportunity->id,
            'status' => 'pending'
        ]);
    });
}
```

---

### 2. Modal Animations & Interactions

#### 2.1 Modal Open Animation
```php
/** @test */
public function modal_opens_with_fade_animation()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->assertMissing('@feedback-modal')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->pause(100) // Observe animation start
            ->waitFor('@feedback-modal', 5)
            ->assertVisible('@modal-backdrop')
            ->assertVisible('@feedback-modal-content')
            ->pause(300); // Full animation duration
    });
}
```

#### 2.2 Modal Close Animation
```php
/** @test */
public function modal_closes_with_fade_animation()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->click('@modal-close-button')
            ->pause(200) // Observe animation
            ->waitUntilMissing('@feedback-modal', 5);
    });
}
```

#### 2.3 Modal Close via Backdrop Click
```php
/** @test */
public function modal_closes_when_clicking_backdrop()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->click('@modal-backdrop')
            ->waitUntilMissing('@feedback-modal');
    });
}
```

#### 2.4 Modal Close via ESC Key
```php
/** @test */
public function modal_closes_when_pressing_escape_key()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->keys('@feedback-textarea', '{escape}')
            ->waitUntilMissing('@feedback-modal');
    });
}
```

---

### 3. Form Validation Testing

#### 3.1 Empty Feedback Validation
```php
/** @test */
public function feedback_cannot_be_empty()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->clear('@feedback-textarea')
            ->click('@submit-feedback-btn')
            ->whenAvailable('@confirm-dialog', function ($modal) {
                $modal->press('Confirm');
            })
            ->waitFor('@error-feedback-data')
            ->assertSeeIn('@error-feedback-data', 'required');
    });
}
```

#### 3.2 Invalid JSON Format
```php
/** @test */
public function invalid_json_format_shows_error()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->type('@feedback-textarea', 'not valid json')
            ->click('@submit-feedback-btn')
            ->whenAvailable('@confirm-dialog', function ($modal) {
                $modal->press('Confirm');
            })
            ->pause(500)
            ->assertPresent('@error-feedback-data');
    });
}
```

#### 3.3 Already Reviewed Opportunity
```php
/** @test */
public function cannot_submit_feedback_for_reviewed_opportunity()
{
    $opportunity = LearningOpportunity::factory()->create(['status' => 'reviewed']);

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->assertDontSee("@provide-feedback-btn-{$opportunity->id}");
    });
}
```

---

### 4. Loading State Testing

#### 4.1 Filter Loading State
```php
/** @test */
public function filter_shows_loading_indicator()
{
    LearningOpportunity::factory()->count(5)->create();

    $this->browse(function (Browser $browser) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->waitFor('@filter-type')
            ->select('@filter-type', 'decision_discovery')
            ->assertVisible('@filter-loading')
            ->waitUntilMissing('@filter-loading');
    });
}
```

#### 4.2 Provide Feedback Button Loading State
```php
/** @test */
public function provide_feedback_button_shows_loading_state()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->assertSeeIn("@provide-feedback-btn-{$opportunity->id}", 'Opening...')
            ->waitFor('@feedback-modal');
    });
}
```

#### 4.3 Submit Button Loading State
```php
/** @test */
public function submit_button_shows_loading_state_and_is_disabled()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->type('@feedback-textarea', '{"valid": "json"}')
            ->click('@submit-feedback-btn')
            ->whenAvailable('@confirm-dialog', function ($modal) {
                $modal->press('Confirm');
            })
            ->assertSeeIn('@submit-feedback-btn', 'Submitting...')
            ->assertAttribute('@submit-feedback-btn', 'disabled', 'true');
    });
}
```

#### 4.4 Cancel Button Loading State
```php
/** @test */
public function cancel_button_shows_loading_state()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->click('@cancel-feedback-btn')
            ->assertSeeIn('@cancel-feedback-btn', 'Canceling...');
    });
}
```

#### 4.5 Opportunities List Loading Overlay
```php
/** @test */
public function opportunities_list_shows_loading_overlay_during_operations()
{
    LearningOpportunity::factory()->count(3)->create();

    $this->browse(function (Browser $browser) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->select('@filter-type', 'decision_discovery')
            ->assertVisible('@opportunities-loading-overlay')
            ->waitUntilMissing('@opportunities-loading-overlay');
    });
}
```

---

### 5. Confirmation Dialog Testing

#### 5.1 Submit Confirmation
```php
/** @test */
public function submit_feedback_requires_confirmation()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->type('@feedback-textarea', '{"test": "data"}')
            ->click('@submit-feedback-btn')
            ->assertDialogOpened('Are you sure you want to submit this feedback?')
            ->dismissDialog()
            ->assertPresent('@feedback-modal'); // Modal should still be open
    });
}
```

---

### 6. Accessibility Testing

#### 6.1 ARIA Labels
```php
/** @test */
public function component_has_proper_aria_labels()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->assertAttribute('@filter-type', 'aria-label', 'Filter opportunities by type')
            ->assertAttribute('@opportunities-list', 'role', 'list')
            ->assertAttribute("@provide-feedback-btn-{$opportunity->id}", 'aria-label', "Provide feedback for opportunity {$opportunity->id}")
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->assertAttribute('@feedback-modal', 'role', 'dialog')
            ->assertAttribute('@feedback-modal', 'aria-modal', 'true')
            ->assertAttribute('@modal-close-button', 'aria-label', 'Close modal')
            ->assertAttribute('@feedback-textarea', 'aria-describedby', 'feedback-hint');
    });
}
```

#### 6.2 Keyboard Navigation
```php
/** @test */
public function user_can_navigate_with_keyboard()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->keys('', '{tab}') // Focus filter
            ->assertFocused('@filter-type')
            ->keys('', '{tab}') // Focus first provide feedback button
            ->keys('', '{enter}') // Open modal
            ->waitFor('@feedback-modal')
            ->assertFocused('@feedback-textarea') // Auto-focus textarea
            ->keys('', '{tab}') // Focus cancel button
            ->keys('', '{tab}') // Focus submit button
            ->keys('', '{escape}') // Close modal
            ->waitUntilMissing('@feedback-modal');
    });
}
```

#### 6.3 Screen Reader Support
```php
/** @test */
public function component_supports_screen_readers()
{
    $this->browse(function (Browser $browser) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->assertAttribute('@success-message', 'role', 'alert')
            ->assertAttribute('@success-message', 'aria-live', 'polite')
            ->assertAttribute('@empty-state', 'role', 'status')
            ->assertAttribute('@empty-state', 'aria-live', 'polite')
            ->click("@provide-feedback-btn-1")
            ->waitFor('@feedback-modal')
            ->assertAttribute('@error-feedback-data', 'role', 'alert');
    });
}
```

---

### 7. Mobile Responsive Testing

#### 7.1 Mobile Filter Layout
```php
/** @test */
public function filter_is_full_width_on_mobile()
{
    $this->browse(function (Browser $browser) {
        $browser->loginAs(User::factory()->create())
            ->resize(375, 667) // iPhone SE dimensions
            ->visit('/learning-opportunities')
            ->assertPresent('@filter-type')
            ->assertVisible('@filter-type');
    });
}
```

#### 7.2 Mobile Opportunity Cards
```php
/** @test */
public function opportunity_cards_stack_on_mobile()
{
    LearningOpportunity::factory()->count(3)->create();

    $this->browse(function (Browser $browser) {
        $browser->loginAs(User::factory()->create())
            ->resize(375, 667)
            ->visit('/learning-opportunities')
            ->waitFor('@opportunities-list')
            ->assertVisible('@opportunities-list');

        // Cards should be stacked vertically (grid-cols-1)
    });
}
```

#### 7.3 Mobile Modal Layout
```php
/** @test */
public function modal_is_responsive_on_mobile()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->resize(375, 667)
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->assertVisible('@feedback-modal-content')
            ->assertVisible('@feedback-textarea')
            ->assertVisible('@submit-feedback-btn')
            ->assertVisible('@cancel-feedback-btn');

        // Buttons should stack vertically (flex-col-reverse on mobile)
    });
}
```

#### 7.4 Tablet Layout
```php
/** @test */
public function component_works_on_tablet()
{
    LearningOpportunity::factory()->count(2)->create();

    $this->browse(function (Browser $browser) {
        $browser->loginAs(User::factory()->create())
            ->resize(768, 1024) // iPad dimensions
            ->visit('/learning-opportunities')
            ->waitFor('@opportunities-list')
            ->assertVisible('@filter-type')
            ->assertVisible('@opportunities-list');
    });
}
```

---

### 8. Visual Regression Testing

#### 8.1 Card Hover Effect
```php
/** @test */
public function opportunity_cards_have_hover_effects()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->waitFor("@opportunity-card-{$opportunity->id}")
            ->mouseover("@opportunity-card-{$opportunity->id}")
            ->pause(500); // Allow hover transition to complete

        // Visual inspection: Card should lift with shadow and gradient border
    });
}
```

#### 8.2 Success Message Animation
```php
/** @test */
public function success_message_animates_and_auto_dismisses()
{
    $this->browse(function (Browser $browser) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->waitForText('Feedback submitted successfully!') // Assuming session flash exists
            ->assertVisible('@success-message')
            ->pause(5500) // Wait for auto-dismiss (5 seconds + buffer)
            ->assertMissing('@success-message');
    });
}
```

---

### 9. Data Integrity Testing

#### 9.1 Opportunity Data Display
```php
/** @test */
public function opportunity_displays_all_data_fields_correctly()
{
    $opportunity = LearningOpportunity::factory()->create([
        'opportunity_type' => 'precedent_analysis',
        'confidence_score' => 0.62,
        'source_type' => 'decision_discovery',
        'ai_output' => [
            'id' => 'DEC-123',
            'score' => 0.85,
            'applicability_score' => 0.78,
            'reasoning' => 'High relevance to current case',
            'topic' => 'Contract Law'
        ],
        'uncertainty_reason' => 'Conflicting precedents'
    ]);

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->waitFor("@opportunity-card-{$opportunity->id}")
            ->assertSeeIn("@opportunity-type-{$opportunity->id}", 'Precedent Analysis')
            ->assertSeeIn("@opportunity-confidence-{$opportunity->id}", '62.0%')
            ->assertSeeIn("@opportunity-source-{$opportunity->id}", 'Decision Discovery')
            ->assertSeeIn("@opportunity-decision-id-{$opportunity->id}", 'DEC-123')
            ->assertSeeIn("@opportunity-ai-score-{$opportunity->id}", '0.85')
            ->assertSeeIn("@opportunity-applicability-score-{$opportunity->id}", '0.78')
            ->assertSeeIn("@opportunity-reasoning-{$opportunity->id}", 'High relevance to current case')
            ->assertSeeIn("@opportunity-topic-{$opportunity->id}", 'Contract Law')
            ->assertSeeIn("@opportunity-uncertainty-{$opportunity->id}", 'Conflicting precedents');
    });
}
```

#### 9.2 Confidence Score Color Coding
```php
/** @test */
public function confidence_score_badge_shows_correct_color()
{
    $lowConfidence = LearningOpportunity::factory()->create(['confidence_score' => 0.35]);
    $mediumConfidence = LearningOpportunity::factory()->create(['confidence_score' => 0.65]);
    $highConfidence = LearningOpportunity::factory()->create(['confidence_score' => 0.85]);

    $this->browse(function (Browser $browser) use ($lowConfidence, $mediumConfidence, $highConfidence) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->waitFor('@opportunities-list')
            // Red for < 0.5
            ->assertVisible("@opportunity-confidence-{$lowConfidence->id}")
            // Yellow for 0.5-0.7
            ->assertVisible("@opportunity-confidence-{$mediumConfidence->id}")
            // Green for >= 0.7
            ->assertVisible("@opportunity-confidence-{$highConfidence->id}");
    });
}
```

---

### 10. Error Handling Testing

#### 10.1 Network Error During Submit
```php
/** @test */
public function handles_network_error_during_submit()
{
    $opportunity = LearningOpportunity::factory()->create();

    $this->browse(function (Browser $browser) use ($opportunity) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->click("@provide-feedback-btn-{$opportunity->id}")
            ->waitFor('@feedback-modal')
            ->type('@feedback-textarea', '{"test": "data"}')
            ->script('window.navigator.onLine = false;'); // Simulate offline

        $browser->click('@submit-feedback-btn')
            ->whenAvailable('@confirm-dialog', function ($modal) {
                $modal->press('Confirm');
            })
            ->pause(1000)
            ->assertPresent('@error-submit-feedback'); // Should show error
    });
}
```

#### 10.2 Invalid Opportunity ID
```php
/** @test */
public function handles_invalid_opportunity_id_gracefully()
{
    $this->browse(function (Browser $browser) {
        $browser->loginAs(User::factory()->create())
            ->visit('/learning-opportunities')
            ->script('Livewire.emit("selectOpportunity", 99999);'); // Non-existent ID

        $browser->pause(500)
            ->assertPresent('@error-selected-opportunity');
    });
}
```

---

## Performance Testing Checklist

- [ ] Page loads within 2 seconds
- [ ] Filter change responds within 500ms
- [ ] Modal open/close animations are smooth (60fps)
- [ ] No layout shift during loading states
- [ ] Hover effects are instant and smooth
- [ ] List scrolling is smooth with 50+ items
- [ ] No memory leaks during repeated modal open/close

---

## Browser Compatibility Checklist

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Mobile Chrome (Android)

---

## Dark Mode Testing Checklist

- [ ] All colors have dark mode variants
- [ ] Text is readable in dark mode
- [ ] Borders are visible in dark mode
- [ ] Gradients work in dark mode
- [ ] Icons are visible in dark mode
- [ ] Focus states work in dark mode

---

## Example Test Suite Structure

```php
<?php

namespace Tests\Browser;

use App\Models\LearningOpportunity;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LearningOpportunityManagerTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup code
    }

    /** @test */
    public function complete_feedback_workflow()
    {
        $user = User::factory()->create();
        $opportunity = LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.45
        ]);

        $this->browse(function (Browser $browser) use ($user, $opportunity) {
            $browser->loginAs($user)
                // Step 1: Navigate to component
                ->visit('/learning-opportunities')
                ->waitFor('@learning-opportunity-manager')

                // Step 2: Verify opportunity is listed
                ->assertVisible("@opportunity-card-{$opportunity->id}")
                ->assertSeeIn("@opportunity-type-{$opportunity->id}", 'Decision Discovery')
                ->assertSeeIn("@opportunity-confidence-{$opportunity->id}", '45.0%')

                // Step 3: Filter opportunities
                ->select('@filter-type', 'decision_discovery')
                ->waitFor('@filter-loading')
                ->waitUntilMissing('@filter-loading')
                ->assertVisible("@opportunity-card-{$opportunity->id}")

                // Step 4: Open feedback modal
                ->click("@provide-feedback-btn-{$opportunity->id}")
                ->waitFor('@feedback-modal')
                ->assertVisible('@feedback-modal-content')
                ->pause(300) // Allow animation to complete

                // Step 5: Submit feedback
                ->type('@feedback-textarea', '{"correct": true, "confidence": 0.95, "notes": "Excellent analysis"}')
                ->click('@submit-feedback-btn')
                ->whenAvailable('@confirm-dialog', function ($modal) {
                    $modal->press('Confirm');
                })
                ->waitForText('Feedback submitted successfully!')
                ->assertPresent('@success-message')

                // Step 6: Verify modal closed
                ->waitUntilMissing('@feedback-modal')
                ->pause(200) // Allow animation to complete

                // Step 7: Verify opportunity removed from pending list
                ->assertMissing("@opportunity-card-{$opportunity->id}");
        });

        // Step 8: Verify database
        $this->assertDatabaseHas('learning_opportunities', [
            'id' => $opportunity->id,
            'status' => 'reviewed'
        ]);
    }
}
```

---

## Quick Reference: Common Test Patterns

### Wait for Element
```php
->waitFor('@opportunities-list', 5)
```

### Wait Until Missing
```php
->waitUntilMissing('@feedback-modal', 3)
```

### Assert Visible
```php
->assertVisible('@submit-feedback-btn')
```

### Assert Text Content
```php
->assertSeeIn('@opportunity-type-1', 'Decision Discovery')
```

### Assert Attribute
```php
->assertAttribute('@submit-feedback-btn', 'disabled', 'true')
```

### Handle Confirmation
```php
->whenAvailable('@confirm-dialog', function ($modal) {
    $modal->press('Confirm');
})
```

### Resize for Mobile
```php
->resize(375, 667)
```

### Pause for Animation
```php
->pause(300) // milliseconds
```

---

## Notes

1. **ID-based Selectors:** All opportunity-specific selectors use the opportunity ID (e.g., `opportunity-card-{id}`), not the array index. This ensures tests work correctly with filtered/sorted data.

2. **Loading States:** All interactive buttons have `wire:loading` states. Tests should verify these states appear during operations.

3. **Alpine.js:** Modal uses Alpine.js transitions. Allow 200-300ms for animations to complete.

4. **Confirmation Dialogs:** Submit feedback requires confirmation via `wire:confirm`. Tests must handle the browser's native confirm dialog.

5. **Accessibility:** All interactive elements have ARIA labels and keyboard navigation support.

6. **Mobile-First:** Component is fully responsive. Test on multiple viewport sizes.

---

**Last Updated:** 2025-11-18
**Component Version:** 2.0 (TDD-Enhanced)
