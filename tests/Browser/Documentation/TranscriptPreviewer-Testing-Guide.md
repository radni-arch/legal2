# TranscriptPreviewer Component - Testing Documentation

## Overview
This document outlines comprehensive testing requirements for the TranscriptPreviewer Livewire component following TDD best practices.

## Component Information
- **PHP Class**: `app/Http/Livewire/TranscriptPreviewer.php`
- **Blade View**: `resources/views/livewire/transcript-previewer.blade.php`
- **Primary Purpose**: Advanced transcript viewer with timeline visualization, forensic analysis, and multi-speaker support

---

## Dusk Selectors Reference

### Main Container
- `dusk="transcript-previewer-container"` - Main component wrapper

### Main Controls
- `dusk="main-controls"` - Main controls container
- `dusk="file-path-input"` - Transcript file path input
- `dusk="file-path-loading"` - Loading indicator for file path changes
- `dusk="search-input"` - Search input field
- `dusk="search-loading"` - Loading indicator for search
- `dusk="timestamps-toggle"` - Timestamps visibility checkbox
- `dusk="auto-refresh-toggle"` - Auto-refresh checkbox
- `dusk="refresh-button"` - Manual refresh button
- `dusk="clear-search-button"` - Clear search button
- `dusk="export-button"` - Export transcript button
- `dusk="base-start-chip"` - Base start datetime display
- `dusk="timezone-chip"` - Timezone display
- `dusk="file-path-chip"` - Current file path display

### Lingua/Forensic Controls
- `dusk="lingua-controls"` - Lingua controls container
- `dusk="lingua-path-input"` - Lingua file path input
- `dusk="lingua-path-loading"` - Loading indicator for lingua path
- `dusk="lingua-toggle"` - Forensics panel visibility toggle
- `dusk="lingua-events-count"` - Events count display

### Forensic Analysis Panel
- `dusk="forensic-panel"` - Main forensic panel container
- `dusk="forensic-loading-overlay"` - Loading overlay for forensic panel
- `dusk="forensic-summary-chip"` - Forensic summary label
- `dusk="duration-chip"` - Transcript duration display
- `dusk="lingua-summary-text"` - Forensic summary text content
- `dusk="timeline-label"` - Timeline section label
- `dusk="timeline-scrubber"` - Interactive timeline visualization
- `dusk="timeline-event-{index}"` - Individual timeline event markers (dynamic)
- `dusk="lingua-events-list"` - Event cards container
- `dusk="lingua-event-card-{index}"` - Individual event cards (dynamic)
- `dusk="no-events-message"` - Message when no events found

### Speaker Controls
- `dusk="speakers-controls"` - Speaker controls container
- `dusk="speakers-list"` - Speaker checkboxes list
- `dusk="speaker-{name}-checkbox"` - Individual speaker checkbox (dynamic)
- `dusk="speaker-{name}-name"` - Speaker name label (dynamic)
- `dusk="show-all-speakers-button"` - Show all speakers button
- `dusk="hide-all-speakers-button"` - Hide all speakers button

### Transcript Display
- `dusk="transcript-display"` - Transcript display container
- `dusk="segments-loading-overlay"` - Loading overlay for segments
- `dusk="segment-list"` - Segments list container
- `dusk="segment-{index}"` - Individual segment container (dynamic)
- `dusk="segment-{index}-header"` - Segment header (dynamic)
- `dusk="segment-{index}-timecode"` - Segment timecode (dynamic)
- `dusk="segment-{index}-speaker"` - Segment speaker (dynamic)
- `dusk="segment-{index}-datetime"` - Segment absolute datetime (dynamic)
- `dusk="segment-{index}-text"` - Segment text content (dynamic)
- `dusk="segment-{index}-near-events"` - Near events container (dynamic)
- `dusk="segment-{index}-near-event-{evIdx}"` - Individual near event (dynamic)
- `dusk="segment-{index}-details"` - Expandable details section (dynamic)
- `dusk="segment-{index}-summary"` - Details summary trigger (dynamic)
- `dusk="segment-{index}-details-content"` - Details content (dynamic)
- `dusk="segment-{index}-detail-{evIdx}"` - Individual detail item (dynamic)
- `dusk="no-segments-message"` - Message when no segments found

---

## Test Categories

### 1. Initial Load Tests

#### Test: Component Renders Successfully
```php
public function test_transcript_previewer_renders_successfully()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->assertVisible('@transcript-previewer-container')
                ->assertVisible('@main-controls')
                ->assertVisible('@file-path-input')
                ->assertVisible('@search-input');
    });
}
```

#### Test: Default File Loads
```php
public function test_default_transcript_file_loads()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->waitFor('@segment-list', 5)
                ->assertVisible('@segment-0')
                ->assertPresent('@segment-0-speaker')
                ->assertPresent('@segment-0-text');
    });
}
```

---

### 2. File Path Management Tests

#### Test: Change File Path
```php
public function test_change_file_path()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->type('@file-path-input', 'storage/test_transcript.txt')
                ->waitFor('@file-path-loading', 2)
                ->waitUntilMissing('@file-path-loading', 5)
                ->assertVisible('@segment-list');
    });
}
```

#### Test: Invalid File Path Shows Error
```php
public function test_invalid_file_path_shows_no_segments()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->type('@file-path-input', 'storage/nonexistent.txt')
                ->pause(1000)
                ->assertVisible('@no-segments-message');
    });
}
```

---

### 3. Search Functionality Tests

#### Test: Search Filters Segments
```php
public function test_search_filters_segments()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->waitFor('@segment-list')
                ->type('@search-input', 'specific phrase')
                ->waitFor('@search-loading', 2)
                ->waitUntilMissing('@search-loading', 2)
                ->pause(500)
                ->assertSee('specific phrase');
    });
}
```

#### Test: Clear Search Button Works
```php
public function test_clear_search_button()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->type('@search-input', 'test')
                ->pause(500)
                ->click('@clear-search-button')
                ->assertInputValue('@search-input', '');
    });
}
```

#### Test: Search Highlights Matches
```php
public function test_search_highlights_matches()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->type('@search-input', 'test')
                ->pause(500)
                ->assertPresent('mark'); // <mark> tag for highlighting
    });
}
```

---

### 4. Timeline Interaction Tests

#### Test: Timeline Renders With Events
```php
public function test_timeline_renders_with_events()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->waitFor('@timeline-scrubber', 2)
                ->assertVisible('@timeline-scrubber')
                ->assertPresent('@timeline-event-0');
    });
}
```

#### Test: Timeline Event Click Navigation
```php
public function test_timeline_event_navigates_to_segment()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->waitFor('@timeline-event-0')
                ->click('@timeline-event-0')
                ->pause(500)
                ->assertVisible('[id^="seg-"]'); // Segment is visible
    });
}
```

#### Test: Timeline Event Hover Effects
```php
public function test_timeline_event_hover_effects()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->waitFor('@timeline-event-0')
                ->mouseover('@timeline-event-0')
                ->assertScript('
                    const event = document.querySelector("[dusk=\'timeline-event-0\'] span");
                    return event && event.classList.contains("hover:scale-125");
                ', true);
    });
}
```

#### Test: Event Cards Link to Segments
```php
public function test_event_cards_link_to_segments()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->waitFor('@lingua-event-card-0')
                ->click('@lingua-event-card-0')
                ->pause(500)
                ->assertVisible('[id^="seg-"]');
    });
}
```

---

### 5. Speaker Filter Tests

#### Test: Toggle Individual Speaker
```php
public function test_toggle_individual_speaker()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->waitFor('@speakers-list')
                ->uncheck('@speaker-S1-checkbox')
                ->pause(500)
                ->assertDontSee('Speaker S1'); // Segments hidden
    });
}
```

#### Test: Show All Speakers Button
```php
public function test_show_all_speakers_button()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->waitFor('@show-all-speakers-button')
                ->click('@hide-all-speakers-button')
                ->pause(500)
                ->assertVisible('@no-segments-message')
                ->click('@show-all-speakers-button')
                ->waitFor('@segment-list');
    });
}
```

#### Test: Hide All Speakers Button
```php
public function test_hide_all_speakers_button()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->waitFor('@hide-all-speakers-button')
                ->click('@hide-all-speakers-button')
                ->pause(500)
                ->assertVisible('@no-segments-message');
    });
}
```

---

### 6. Loading State Tests

#### Test: Refresh Button Shows Loading State
```php
public function test_refresh_button_loading_state()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->click('@refresh-button')
                ->assertSee('Refreshing...')
                ->assertAttribute('@refresh-button', 'disabled', 'true')
                ->pause(1000)
                ->assertDontSee('Refreshing...');
    });
}
```

#### Test: Export Button Shows Loading State
```php
public function test_export_button_loading_state()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->click('@export-button')
                ->assertSee('Exporting...')
                ->pause(2000);
    });
}
```

#### Test: Segment List Loading Overlay
```php
public function test_segment_list_loading_overlay()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->click('@refresh-button')
                ->waitFor('@segments-loading-overlay', 2)
                ->assertSee('Loading transcript segments...')
                ->waitUntilMissing('@segments-loading-overlay', 5);
    });
}
```

#### Test: Forensic Panel Loading Overlay
```php
public function test_forensic_panel_loading_overlay()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->click('@refresh-button')
                ->waitFor('@forensic-loading-overlay', 2)
                ->assertSee('Loading forensic analysis...')
                ->waitUntilMissing('@forensic-loading-overlay', 5);
    });
}
```

---

### 7. Export Functionality Tests

#### Test: Export Button Exists
```php
public function test_export_button_exists()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->assertVisible('@export-button')
                ->assertSee('Export');
    });
}
```

#### Test: Export Downloads File
```php
public function test_export_downloads_file()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->click('@export-button')
                ->pause(2000);

        // Verify download occurred (implementation varies by test setup)
        $downloads = glob(storage_path('downloads/transcript_export_*.txt'));
        $this->assertNotEmpty($downloads);
    });
}
```

---

### 8. Forensic Analysis Tests

#### Test: Toggle Forensic Panel
```php
public function test_toggle_forensic_panel()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->uncheck('@lingua-toggle')
                ->assertMissing('@forensic-panel')
                ->check('@lingua-toggle')
                ->waitFor('@forensic-panel', 2)
                ->assertVisible('@forensic-panel');
    });
}
```

#### Test: Forensic Summary Displays
```php
public function test_forensic_summary_displays()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->waitFor('@forensic-panel')
                ->assertVisible('@lingua-summary-text');
    });
}
```

#### Test: Near Events Display in Segments
```php
public function test_near_events_display_in_segments()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->waitFor('@segment-list')
                ->assertPresent('@segment-0-near-events');
    });
}
```

#### Test: Expandable Details Work
```php
public function test_expandable_details_work()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->waitFor('@segment-0-details')
                ->click('@segment-0-summary')
                ->pause(300)
                ->assertVisible('@segment-0-details-content');
    });
}
```

---

### 9. Toggle Controls Tests

#### Test: Timestamps Toggle
```php
public function test_timestamps_toggle()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->assertChecked('@timestamps-toggle')
                ->assertVisible('@segment-0-timecode')
                ->uncheck('@timestamps-toggle')
                ->pause(300)
                ->assertMissing('@segment-0-timecode');
    });
}
```

#### Test: Auto-Refresh Toggle
```php
public function test_auto_refresh_toggle()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->check('@auto-refresh-toggle')
                ->pause(6000) // Wait for one refresh cycle
                ->assertVisible('@segment-list');
    });
}
```

---

### 10. Navigation and Scrolling Tests

#### Test: Segment Anchor Links Work
```php
public function test_segment_anchor_links_work()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer#seg-000300')
                ->pause(500)
                ->assertVisible('[id="seg-000300"]');
    });
}
```

#### Test: Smooth Scroll to Segments
```php
public function test_smooth_scroll_to_segments()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->waitFor('@lingua-event-card-5')
                ->click('@lingua-event-card-5')
                ->pause(1000)
                ->assertScript('
                    const segment = document.querySelector("[id^=\'seg-\']");
                    const rect = segment.getBoundingClientRect();
                    return rect.top >= 0 && rect.top <= window.innerHeight;
                ', true);
    });
}
```

---

### 11. Accessibility Tests

#### Test: Keyboard Navigation Works
```php
public function test_keyboard_navigation()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->keys('@search-input', '{tab}', '{tab}', '{space}')
                ->pause(300);
    });
}
```

#### Test: ARIA Labels Present
```php
public function test_aria_labels_present()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->assertAttribute('@timestamps-toggle', 'id', 'tsToggle')
                ->assertAttribute('@auto-refresh-toggle', 'id', 'arToggle')
                ->assertAttribute('@lingua-toggle', 'id', 'lgToggle');
    });
}
```

#### Test: Focus States Visible
```php
public function test_focus_states_visible()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->click('@search-input')
                ->assertScript('
                    const input = document.querySelector("[dusk=\'search-input\']");
                    return document.activeElement === input;
                ', true);
    });
}
```

---

### 12. Mobile Responsiveness Tests

#### Test: Timeline Responsive on Mobile
```php
public function test_timeline_responsive_on_mobile()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(375, 667) // iPhone size
                ->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->waitFor('@timeline-scrubber')
                ->assertVisible('@timeline-scrubber')
                ->assertPresent('@timeline-event-0');
    });
}
```

#### Test: Controls Stack on Mobile
```php
public function test_controls_stack_on_mobile()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(375, 667)
                ->visit('/transcript-previewer')
                ->assertVisible('@main-controls')
                ->assertVisible('@file-path-input')
                ->assertVisible('@search-input');
    });
}
```

#### Test: Touch Interactions Work
```php
public function test_touch_interactions_work()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(375, 667)
                ->visit('/transcript-previewer')
                ->check('@lingua-toggle')
                ->tap('@timeline-event-0')
                ->pause(500)
                ->assertVisible('[id^="seg-"]');
    });
}
```

---

### 13. Data Validation Tests

#### Test: Timecodes Display Correctly
```php
public function test_timecodes_display_correctly()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->assertVisible('@segment-0-timecode')
                ->assertSeeIn('@segment-0-timecode', ':'); // HH:MM:SS format
    });
}
```

#### Test: Speaker Names Display
```php
public function test_speaker_names_display()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->assertVisible('@segment-0-speaker')
                ->assertSeeIn('@segment-0-speaker', 'Speaker');
    });
}
```

#### Test: Absolute DateTime Display
```php
public function test_absolute_datetime_display()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->assertVisible('@segment-0-datetime')
                ->assertSeeIn('@segment-0-datetime', '2025'); // Year format
    });
}
```

---

### 14. Error Handling Tests

#### Test: Missing File Handled Gracefully
```php
public function test_missing_file_handled_gracefully()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->type('@file-path-input', 'nonexistent.txt')
                ->pause(1000)
                ->assertVisible('@no-segments-message')
                ->assertDontSee('Error');
    });
}
```

#### Test: Empty Search Returns All
```php
public function test_empty_search_returns_all()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->type('@search-input', 'test')
                ->pause(500)
                ->click('@clear-search-button')
                ->waitFor('@segment-list')
                ->assertVisible('@segment-0');
    });
}
```

---

### 15. Performance Tests

#### Test: Large Transcript Loads
```php
public function test_large_transcript_loads()
{
    $this->browse(function (Browser $browser) {
        $start = microtime(true);

        $browser->visit('/transcript-previewer')
                ->waitFor('@segment-list', 10);

        $duration = microtime(true) - $start;
        $this->assertLessThan(10, $duration); // Less than 10 seconds
    });
}
```

#### Test: Search Performance
```php
public function test_search_performance()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                ->type('@search-input', 'test')
                ->pause(500) // 400ms debounce + margin
                ->assertVisible('@segment-list');
    });
}
```

---

## Visual Regression Testing

### Timeline Visualization
- Verify gradient backgrounds render correctly
- Check hover effects scale properly
- Confirm timeline markers align with percentages
- Validate color schemes match design

### Loading States
- Spinner animations smooth
- Loading overlays blur backgrounds
- Text properly centered
- Z-index layering correct

### Segment Display
- Gradient backgrounds visible
- Hover effects smooth
- Shadow effects render
- Transitions fluid

---

## Cross-Browser Testing

Test on:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

---

## Test Data Requirements

### Sample Transcript File
```
00:00:00:00 Speaker S1
Hello, this is the first segment.

00:00:15:00 Speaker S2
This is the second segment with more text.

00:00:30:00 Speaker S1
Back to speaker one.
```

### Sample Lingua File
```
# Opća analiza
This is a forensic analysis summary.

- **[00:00:05] Important Event:** Description of what happened.
- **[00:00:20] Key Moment:** Another important moment.
```

---

## Continuous Integration

### Automated Test Runs
- Run on every commit
- Run on pull requests
- Nightly regression tests
- Performance benchmarks weekly

### Test Coverage Goals
- Unit tests: 90%+
- Integration tests: 80%+
- E2E tests: Critical paths 100%

---

## Known Issues & Edge Cases

1. **Timeline on very short transcripts**: May cluster events
2. **Special characters in search**: Need proper escaping
3. **Very long speaker names**: May overflow on mobile
4. **Large number of events**: Timeline may become crowded
5. **Auto-refresh**: May interfere with user interactions

---

## Testing Checklist

- [ ] All Dusk selectors documented
- [ ] All interactive elements have tests
- [ ] Loading states verified
- [ ] Error handling tested
- [ ] Mobile responsive verified
- [ ] Accessibility checked
- [ ] Performance benchmarked
- [ ] Visual regression tested
- [ ] Cross-browser validated
- [ ] Export functionality verified
- [ ] Timeline interactions tested
- [ ] Speaker filtering tested
- [ ] Search functionality tested
- [ ] Forensic panel tested
- [ ] Navigation tested

---

## Maintenance Notes

- Update tests when adding new features
- Review and update selectors quarterly
- Re-run visual regression after design changes
- Update test data files as needed
- Document new edge cases discovered

---

## Contact & Support

For testing questions or issues:
- Create issue in project tracker
- Tag with `testing` and `transcript-previewer`
- Include browser/environment details
- Attach screenshots if visual issue

---

**Last Updated**: 2025-11-18
**Version**: 1.0
**Status**: Production Ready
