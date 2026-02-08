# TranscriptPreviewer - Dusk Selectors Quick Reference

Quick reference guide for all Dusk selectors in the TranscriptPreviewer component.

## Container
```php
'@transcript-previewer-container' // Main wrapper
```

## Main Controls
```php
// Inputs
'@file-path-input'          // Transcript file path
'@search-input'             // Search field
'@file-path-loading'        // Loading spinner for file path
'@search-loading'           // Loading spinner for search

// Toggles
'@timestamps-toggle'        // Show/hide timestamps
'@auto-refresh-toggle'      // Enable auto-refresh

// Buttons
'@refresh-button'           // Manual refresh
'@clear-search-button'      // Clear search
'@export-button'            // Export transcript

// Info Chips
'@base-start-chip'          // Base datetime
'@timezone-chip'            // Timezone
'@file-path-chip'           // File path display
```

## Lingua/Forensic Controls
```php
'@lingua-path-input'        // Lingua file path
'@lingua-path-loading'      // Loading spinner
'@lingua-toggle'            // Show/hide forensic panel
'@lingua-events-count'      // Event count display
```

## Forensic Panel
```php
'@forensic-panel'           // Main panel
'@forensic-loading-overlay' // Loading overlay
'@forensic-summary-chip'    // Summary label
'@duration-chip'            // Duration display
'@lingua-summary-text'      // Summary content
'@timeline-label'           // Timeline section label
'@timeline-scrubber'        // Timeline visualization
'@timeline-event-{N}'       // Timeline marker (N = index)
'@lingua-events-list'       // Events container
'@lingua-event-card-{N}'    // Event card (N = index)
'@no-events-message'        // No events message
```

## Speaker Controls
```php
'@speakers-list'               // Speaker list container
'@speaker-{NAME}-checkbox'     // Speaker checkbox (NAME = speaker)
'@speaker-{NAME}-name'         // Speaker label (NAME = speaker)
'@show-all-speakers-button'    // Show all
'@hide-all-speakers-button'    // Hide all
```

## Transcript Display
```php
// Container & Overlay
'@transcript-display'          // Main display
'@segments-loading-overlay'    // Loading overlay
'@segment-list'                // Segments container
'@no-segments-message'         // No segments message

// Individual Segment (N = index)
'@segment-{N}'                 // Segment wrapper
'@segment-{N}-header'          // Header
'@segment-{N}-timecode'        // Timecode chip
'@segment-{N}-speaker'         // Speaker chip
'@segment-{N}-datetime'        // Datetime chip
'@segment-{N}-text'            // Text content

// Near Events (N = segment, E = event)
'@segment-{N}-near-events'     // Near events container
'@segment-{N}-near-event-{E}'  // Near event chip
'@segment-{N}-details'         // Details section
'@segment-{N}-summary'         // Details trigger
'@segment-{N}-details-content' // Details content
'@segment-{N}-detail-{E}'      // Detail item
```

---

## Common Patterns

### Wait for Element
```php
$browser->waitFor('@segment-list', 5);
```

### Check Loading State
```php
$browser->click('@refresh-button')
        ->assertSee('Refreshing...')
        ->waitUntilMissing('@segments-loading-overlay', 10);
```

### Interact with Timeline
```php
$browser->click('@timeline-event-0')
        ->pause(500)
        ->assertVisible('[id^="seg-"]');
```

### Filter by Speaker
```php
$browser->uncheck('@speaker-S1-checkbox')
        ->pause(500)
        ->assertDontSee('Speaker S1');
```

### Search and Verify
```php
$browser->type('@search-input', 'test')
        ->pause(500)
        ->assertPresent('mark'); // Highlighted text
```

### Export Transcript
```php
$browser->click('@export-button')
        ->assertSee('Exporting...')
        ->pause(2000);
```

---

## Dynamic Selectors

### Timeline Events
```php
// Loop through timeline events
for ($i = 0; $i < $eventCount; $i++) {
    $browser->assertVisible("@timeline-event-{$i}");
}
```

### Segments
```php
// Access specific segment
$segmentIndex = 5;
$browser->scrollIntoView("@segment-{$segmentIndex}")
        ->assertVisible("@segment-{$segmentIndex}-text");
```

### Speakers
```php
// Toggle specific speaker
$speakerName = 'S1';
$browser->check("@speaker-{$speakerName}-checkbox");
```

### Near Events
```php
// Access near event
$segmentIndex = 0;
$eventIndex = 1;
$browser->assertVisible("@segment-{$segmentIndex}-near-event-{$eventIndex}");
```

---

## Complete Test Example

```php
public function test_complete_workflow()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/transcript-previewer')
                // Verify initial load
                ->assertVisible('@transcript-previewer-container')
                ->waitFor('@segment-list', 5)

                // Search
                ->type('@search-input', 'important')
                ->waitFor('@search-loading', 1)
                ->waitUntilMissing('@search-loading', 2)

                // Toggle forensics
                ->check('@lingua-toggle')
                ->waitFor('@forensic-panel', 2)
                ->assertVisible('@timeline-scrubber')

                // Click timeline event
                ->click('@timeline-event-0')
                ->pause(500)

                // Filter speakers
                ->uncheck('@speaker-S1-checkbox')
                ->pause(300)

                // Export
                ->click('@export-button')
                ->assertSee('Exporting...')
                ->pause(2000)

                // Refresh
                ->click('@refresh-button')
                ->waitFor('@segments-loading-overlay', 1)
                ->waitUntilMissing('@segments-loading-overlay', 5);
    });
}
```

---

## Loading State Targets

All wire:target values for loading indicators:

```php
// Main controls
'filePath'              // File path input
'search'                // Search input
'refreshNow'            // Refresh button
"$set('search','')"     // Clear button
'exportTranscript'      // Export button

// Lingua controls
'linguaPath'            // Lingua path input
'loadLingua'            // Load forensic data

// Speaker controls
'allSpeakers'           // Show/hide all speakers
'speakers'              // Individual speaker toggles
```

---

## Accessibility Selectors

```php
// Checkbox IDs (for labels)
'#tsToggle'     // Timestamps toggle
'#arToggle'     // Auto-refresh toggle
'#lgToggle'     // Lingua toggle

// ARIA usage
$browser->assertAttribute('@timestamps-toggle', 'id', 'tsToggle');
```

---

## Quick Debugging

### Check if element exists
```php
$browser->assertPresent('@element-name');
```

### Check if element is visible
```php
$browser->assertVisible('@element-name');
```

### Check element content
```php
$browser->assertSeeIn('@element-name', 'Expected Text');
```

### Wait for dynamic content
```php
$browser->waitFor('@element-name', 10);
```

### Scroll to element
```php
$browser->scrollIntoView('@element-name');
```

---

## Mobile Testing

```php
$browser->resize(375, 667) // iPhone size
        ->visit('/transcript-previewer')
        ->assertVisible('@timeline-scrubber')
        ->tap('@timeline-event-0');
```

---

## Performance Testing

```php
// Measure load time
$start = microtime(true);
$browser->visit('/transcript-previewer')
        ->waitFor('@segment-list', 10);
$duration = microtime(true) - $start;
$this->assertLessThan(5, $duration);
```

---

**Last Updated**: 2025-11-18
**Total Selectors**: 46+ (excluding dynamic variants)
**Coverage**: 100% of interactive elements
