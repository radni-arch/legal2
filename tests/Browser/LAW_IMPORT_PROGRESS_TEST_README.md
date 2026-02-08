# Law Import Progress E2E Tests

## Overview

E2E browser tests for the ZakonHr law import progress feedback feature. Tests verify that real-time progress events are broadcast and the UI updates correctly during law imports.

## Feature Details

### Event Broadcasting

The `ZakonHrIngestService` broadcasts `LawImportProgress` events to the `law-imports` channel during import:

**Event Stages:**
1. `started` - Import begins (total count available)
2. `processing_url` - Processing a specific URL (includes URL in data)
3. `url_completed` - URL processing complete (includes articles count)
4. `completed` - All imports finished (includes final stats)

**Event Structure:**
```php
LawImportProgress(
    stage: string,      // Event stage
    current: int,       // Current progress
    total: int,         // Total items
    data: array        // Stage-specific data
)
```

### UI Components

**Livewire Component:** `IngestedLawsManager`
- Handles import workflow
- Listens for Echo events via JavaScript
- Updates progress UI in real-time

**Dusk Selectors Added:**
- `@scrape-laws-button` - Opens scraper modal
- `@fetch-laws-button` - Fetches available laws from zakon.hr
- `@select-all-button` - Selects all laws for import
- `@import-button` - Triggers import
- `@progress-bar` - Progress bar container
- `@progress-text` - Progress text (e.g., "3 / 10")
- `@progress-bar-fill` - Progress bar fill element (width percentage)
- `@currently-importing` - Currently importing URL display

### Echo.js Integration

The Livewire blade template includes Echo.js listeners:

```javascript
Echo.channel('law-imports')
    .listen('.import.progress', (event) => {
        // Dispatch to Livewire component
        Livewire.dispatch('import-progress-update', {
            stage: event.stage,
            current: event.current,
            total: event.total,
            data: event.data
        });
    });
```

## Running the Tests

### Prerequisites

1. **PostgreSQL Database** - Browser tests use the full database
   ```bash
   # Ensure PostgreSQL is running
   sudo service postgresql start

   # Verify test database exists
   psql -U postgres -c "\l" | grep legal_war_machine_test
   ```

2. **ChromeDriver** - Must be running on port 9515
   ```bash
   # Start ChromeDriver
   chromedriver --port=9515
   ```

3. **Broadcasting Setup** - Echo.js must be configured
   - Laravel Echo installed
   - Broadcasting driver configured (pusher, soketi, etc.)
   - Websocket server running

4. **Environment** - `.env.dusk.local` file configured
   ```env
   APP_ENV=dusk.local
   DB_CONNECTION=pgsql
   DB_DATABASE=legal_war_machine_test
   BROADCAST_DRIVER=pusher  # or soketi
   ```

### Run Tests

```bash
# Run all law import progress tests
php artisan dusk tests/Browser/LawImportProgressTest.php

# Run specific test
php artisan dusk tests/Browser/LawImportProgressTest.php --filter=test_broadcasts_import_started_event

# Run with visible browser (debugging)
DUSK_HEADLESS_DISABLED=1 php artisan dusk tests/Browser/LawImportProgressTest.php
```

## Test Coverage

### Unit Tests (PASSING ✓)

Unit tests for event broadcasting are in `tests/Unit/Services/ZakonHrIngestServiceTest.php`:

```bash
# Run unit tests
php artisan test tests/Unit/Services/ZakonHrIngestServiceTest.php
```

**Tests:**
- ✓ `test_ingest_dispatches_progress_events` - Verifies all event stages fire
- ✓ `test_progress_event_contains_expected_data` - Validates event structure
- ✓ `test_progress_event_includes_url_in_data` - Confirms URL in processing events

### Browser Tests (E2E)

Browser tests in `tests/Browser/LawImportProgressTest.php`:

**Event Broadcasting Tests:**
- `test_broadcasts_import_started_event` - Verifies started event
- `test_broadcasts_processing_url_event` - Verifies processing event
- `test_broadcasts_url_completed_event` - Verifies completion event per URL
- `test_broadcasts_completed_event` - Verifies final completion event

**UI Tests:**
- `test_displays_realtime_progress_updates` - Progress bar appears and updates
- `test_displays_progress_percentage` - Progress bar width changes
- `test_updates_progress_via_echo_websocket` - Echo events received

## Implementation Details

### Files Modified

1. **Blade Template** - `resources/views/livewire/ingested-laws-manager.blade.php`
   - Added Dusk selectors for testability
   - Added Echo.js integration script
   - Progress UI already existed

2. **Livewire Component** - `app/Http/Livewire/IngestedLawsManager.php`
   - Added `import-progress-update` listener
   - Added `handleProgressUpdate()` method
   - Updates progress properties from Echo events

3. **Service** - `app/Services/ZakonHrIngestService.php`
   - Already dispatches events (existing feature)
   - No changes needed

### Troubleshooting

**Browser tests fail with database connection error:**
```
Solution: Ensure PostgreSQL is running and test database exists
```

**Echo events not received:**
```
Solution:
1. Check broadcasting driver is configured
2. Verify websocket server is running
3. Check browser console for Echo connection errors
```

**ChromeDriver errors:**
```
Solution:
1. Ensure ChromeDriver version matches Chrome version
2. Start ChromeDriver manually: chromedriver --port=9515
3. Check DuskTestCase.php for Chrome binary paths
```

## Future Enhancements

- [ ] Add progress percentage assertion tests
- [ ] Test error handling during import
- [ ] Test import cancellation
- [ ] Add visual regression tests for progress UI
- [ ] Test concurrent imports
- [ ] Add performance benchmarks

## References

- Event class: `app/Events/LawImportProgress.php`
- Service: `app/Services/ZakonHrIngestService.php`
- Livewire component: `app/Http/Livewire/IngestedLawsManager.php`
- Unit tests: `tests/Unit/Services/ZakonHrIngestServiceTest.php`
- Browser tests: `tests/Browser/LawImportProgressTest.php`
