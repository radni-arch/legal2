# LearningOpportunityManager - Quick Reference Card

## 🎯 Component Info
- **Location:** `resources/views/livewire/learning-opportunity-manager.blade.php`
- **Controller:** `app/Http/Livewire/LearningOpportunityManager.php`
- **Lines of Code:** 408
- **Dusk Selectors:** 38
- **Test Scenarios:** 30+

---

## 🔍 Key Dusk Selectors

### Most Used Selectors
```php
// Main container
'@learning-opportunity-manager'

// Opportunities list
'@opportunities-list'
'@opportunity-card-{id}'         // Use opportunity ID, not index!
'@provide-feedback-btn-{id}'

// Modal
'@feedback-modal'
'@feedback-modal-content'
'@feedback-textarea'
'@submit-feedback-btn'
'@cancel-feedback-btn'

// Filter
'@filter-type'
'@filter-loading'

// Messages
'@success-message'
'@error-feedback-data'
'@empty-state'
```

---

## 🧪 Quick Test Examples

### Basic Test
```php
$browser->visit('/learning-opportunities')
    ->waitFor('@opportunities-list')
    ->click('@provide-feedback-btn-1')
    ->waitFor('@feedback-modal')
    ->type('@feedback-textarea', '{"test": "data"}')
    ->click('@submit-feedback-btn')
    ->waitForText('Feedback submitted successfully!');
```

### Filter Test
```php
$browser->select('@filter-type', 'decision_discovery')
    ->waitFor('@filter-loading')
    ->waitUntilMissing('@filter-loading');
```

### Modal Close Test
```php
$browser->click('@provide-feedback-btn-1')
    ->waitFor('@feedback-modal')
    ->keys('@feedback-textarea', '{escape}')
    ->waitUntilMissing('@feedback-modal');
```

---

## ⚡ Loading States

All buttons have loading states:
- `wire:loading.attr="disabled"` - Prevents double-clicks
- `wire:target="methodName"` - Specific action targeting
- Loading text changes (e.g., "Submit" → "Submitting...")

**Example:**
```blade
<button wire:click="submitFeedback"
        wire:loading.attr="disabled"
        wire:target="submitFeedback"
        dusk="submit-feedback-btn">
    <span wire:loading.remove wire:target="submitFeedback">
        Submit Feedback
    </span>
    <span wire:loading wire:target="submitFeedback">
        Submitting...
    </span>
</button>
```

---

## 🎨 Animation Timings

- Modal open: 300ms
- Modal close: 200ms
- Success message: 5s auto-dismiss
- Card hover: 300ms
- Loading spinner: Instant

**Test Pauses:**
```php
->pause(300)  // Wait for modal animation
->pause(500)  // Wait for server response
```

---

## 📱 Mobile Testing

```php
// iPhone SE
->resize(375, 667)

// iPad
->resize(768, 1024)

// Desktop
->resize(1920, 1080)
```

---

## ♿ Accessibility

### Required Checks
- [ ] All buttons have `aria-label`
- [ ] Modal has `role="dialog"` and `aria-modal="true"`
- [ ] Form has `aria-describedby` for hints
- [ ] Lists have `role="list"`
- [ ] Alerts have `role="alert"`

### Keyboard Navigation
- **Tab:** Navigate through elements
- **Enter/Space:** Activate buttons
- **Escape:** Close modal

---

## 🐛 Common Issues & Solutions

### Issue: Selector Not Found
**Cause:** Using index instead of ID
```php
// ❌ Wrong
'@opportunity-card-0'

// ✅ Correct
'@opportunity-card-{$opportunity->id}'
```

### Issue: Modal Not Closing
**Cause:** Animation not complete
```php
// ❌ Wrong
->click('@modal-close-button')
->assertMissing('@feedback-modal')

// ✅ Correct
->click('@modal-close-button')
->waitUntilMissing('@feedback-modal', 3)
```

### Issue: Loading State Not Detected
**Cause:** Not waiting for action to start
```php
// ❌ Wrong
->click('@submit-feedback-btn')
->assertVisible('@loading-spinner')

// ✅ Correct
->click('@submit-feedback-btn')
->pause(100)  // Let loading state appear
->assertSeeIn('@submit-feedback-btn', 'Submitting...')
```

---

## 🎯 Testing Checklist

### Before Each Test Run
- [ ] Database seeded with test data
- [ ] User authenticated
- [ ] Browser cache cleared (if needed)
- [ ] Correct viewport size set

### After Each Test
- [ ] Database rollback successful
- [ ] No JavaScript errors in console
- [ ] Screenshots captured (on failure)
- [ ] Cleanup completed

---

## 📊 Component Stats

| Metric | Value |
|--------|-------|
| Total Lines | 408 |
| Dusk Selectors | 38 |
| Wire:Loading | 12 |
| Wire:Target | 12 |
| Alpine Transitions | 27 |
| Interactive Elements | 15+ |
| Test Scenarios | 30+ |

---

## 🚀 Performance Targets

| Metric | Target |
|--------|--------|
| Page Load | < 2s |
| Filter Change | < 500ms |
| Modal Open | < 300ms |
| Form Submit | < 1s |
| Animation FPS | 60 |
| CLS | 0 |

---

## 📚 Documentation Links

1. **Full Testing Guide:** `tests/Browser/LEARNING_OPPORTUNITY_MANAGER_TESTING_GUIDE.md`
2. **Implementation Summary:** `LEARNING_OPPORTUNITY_MANAGER_IMPROVEMENTS.md`
3. **Component View:** `resources/views/livewire/learning-opportunity-manager.blade.php`
4. **Component Controller:** `app/Http/Livewire/LearningOpportunityManager.php`

---

## 💡 Pro Tips

1. **Always use opportunity ID, never array index** for selectors
2. **Wait for animations** before assertions (300ms for modals)
3. **Handle wire:confirm** dialogs with `->whenAvailable('@confirm-dialog')`
4. **Test loading states** on slow connections
5. **Verify disabled attribute** during submissions
6. **Check dark mode** by toggling system preference
7. **Test keyboard navigation** without mouse
8. **Validate ARIA labels** with screen reader

---

## 🔧 Debugging Commands

```php
// Take screenshot
->screenshot('opportunity-manager-debug')

// Dump current HTML
->dump()

// Pause for manual inspection
->pause(5000)

// Check element visibility
->assertPresent('@selector')
->assertVisible('@selector')
->assertMissing('@selector')

// Check text content
->assertSee('text')
->assertSeeIn('@selector', 'text')
->assertDontSee('text')

// Check attributes
->assertAttribute('@selector', 'disabled', 'true')
->assertAttribute('@selector', 'aria-label', 'value')
```

---

**Last Updated:** 2025-11-18
**Version:** 2.0 (TDD-Enhanced)
**Print This Card:** Keep it handy during testing sessions!
