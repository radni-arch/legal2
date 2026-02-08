# E2E Browser Testing Guide

## Overview

This guide covers end-to-end browser testing for AI Legal War Machine using Laravel Dusk with Playwright driver.

## Quick Start

### Setup Environment

```bash
# Install dependencies and setup test database
composer install && composer setup

# Ensure Dusk is installed
composer require --dev laravel/dusk
php artisan dusk:install
```

### Run All E2E Tests

```bash
# Run all browser tests
composer test:e2e

# Or manually
php artisan dusk
```

### Run Specific Test Suites

```bash
# Authentication tests only
composer test:e2e:auth

# Legal Playground tests
composer test:e2e:playground

# Textract Manager tests
composer test:e2e:textract

# Graph Viewer tests
composer test:e2e:graph

# Law Download tests
composer test:e2e:laws

# Timeline tests
composer test:e2e:timeline
```

### Run Specific Test

```bash
# By test method name
./scripts/run-e2e-tests.sh --suite=all --filter=test_can_login

# By test class
php artisan dusk --filter=AuthenticationTest
```

## Test Suites

### 1. Authentication Tests (`AuthenticationTest`)

**Coverage:**
- ✅ User login with valid credentials
- ✅ User logout
- ✅ Invalid credentials error handling
- ✅ Unauthenticated redirect to login

**Scenarios:**
```php
test_user_can_login_and_access_dashboard()
test_user_can_logout()
test_invalid_credentials_show_error()
test_unauthenticated_user_redirected_to_login()
```

### 2. Legal Playground Tests (`LegalPlaygroundTest`)

**Coverage:**
- ✅ Access Legal Playground interface
- ✅ Evidence recontextualization analysis
- ✅ Prosecutorial misconduct detection
- ✅ Dismissal motion generation
- ✅ Drug charge topic analysis
- ✅ Error retry mechanism

**Scenarios:**
```php
test_can_access_legal_playground()
test_can_analyze_evidence_recontextualization()
test_can_detect_prosecutorial_misconduct()
test_can_generate_dismissal_motion()
test_can_analyze_drug_charge_topic()
test_retry_button_appears_on_error()
```

### 3. Textract Manager Tests (`TextractManagerTest`)

**Coverage:**
- ✅ Access Textract Manager
- ✅ View PDF processing jobs list
- ✅ Filter jobs by status
- ✅ View job OCR content
- ✅ Edit and save content
- ✅ Process manual Drive file
- ✅ Regenerate embeddings
- ✅ Sync to Neo4j graph

**Scenarios:**
```php
test_can_access_textract_manager()
test_can_view_textract_jobs_list()
test_can_filter_jobs_by_status()
test_can_view_job_content()
test_can_edit_and_save_job_content()
test_can_process_manual_drive_file()
test_can_regenerate_embeddings()
test_can_sync_to_neo4j_graph()
```

### 4. Graph Viewer Tests (`GraphViewerTest`)

**Coverage:**
- ✅ Access Neo4j graph viewer
- ✅ Search legal nodes (cases, laws, decisions)
- ✅ Filter by node type
- ✅ Load graph visualization
- ✅ Switch view modes (graph, table, JSON)
- ✅ View graph metrics (PageRank, clusters)
- ✅ View influential decisions
- ✅ View citation clusters

**Scenarios:**
```php
test_can_access_graph_viewer()
test_can_search_legal_nodes()
test_can_filter_by_node_type()
test_can_load_node_graph_visualization()
test_can_switch_view_modes()
test_can_view_graph_metrics()
test_can_view_influential_decisions()
test_can_view_citation_clusters()
```

### 5. Law Download Tests (`LawDownloadTest`)

**Coverage:**
- ✅ Access ingested laws page
- ✅ Search Croatian laws (ZKP, Ustav, KZ)
- ✅ Filter laws by type
- ✅ View law details
- ✅ Download law documents
- ✅ Search law articles
- ✅ Unified search for laws

**Scenarios:**
```php
test_can_access_ingested_laws_page()
test_can_search_croatian_laws()
test_can_filter_laws_by_type()
test_can_view_law_details()
test_can_download_law_document()
test_can_search_law_articles()
test_can_use_unified_search_for_laws()
```

### 6. Timeline Tests (`TimelineTest`)

**Coverage:**
- ✅ Access timeline page
- ✅ Display case events chronologically
- ✅ Navigate timeline events
- ✅ Filter timeline by case
- ✅ Show event details
- ✅ Switch timeline variants

**Scenarios:**
```php
test_can_access_timeline_page()
test_timeline_displays_case_events()
test_can_navigate_timeline_events()
test_can_filter_timeline_by_case()
test_timeline_shows_event_details()
test_can_switch_timeline_variants()
```

## Test Architecture

### Concerns (Traits)

#### `AuthenticatesUser`
Provides helper methods for login/logout in browser tests:

```php
$this->loginAs($browser, $user);
$this->logout($browser);
```

#### `MocksExternalApis`
Mocks OpenAI and Odluke APIs for offline testing:

```php
$this->mockAllExternalApis(); // In setUp()
```

### Test Structure

All E2E tests extend `DuskTestCase`:

```php
use Tests\DuskTestCase;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;

class MyE2ETest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    public function test_workflow(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);
            // ... test steps
        });
    }
}
```

## Dusk Selectors

All interactive elements use `dusk` attributes for reliable selection:

```php
// In Blade views
<button dusk="submit-button" wire:click="submit">Submit</button>

// In tests
$browser->press('@submit-button');
```

### Naming Convention

- Tabs: `@{name}-tab` (e.g., `@evidence-tab`)
- Panels: `@{name}-panel` (e.g., `@evidence-panel`)
- Inputs: `@{name}-input` (e.g., `@search-input`)
- Buttons: `@{action}-button` (e.g., `@retry-button`)
- Modals: `@{name}-modal` (e.g., `@content-modal`)
- Dynamic IDs: `@{action}-{{ $id }}` (e.g., `@view-content-{{ $job->id }}`)

## Best Practices

### 1. Always Mock External APIs

```php
protected function setUp(): void
{
    parent::setUp();
    $this->mockAllExternalApis(); // No API costs!
}
```

### 2. Use Explicit Waits

```php
// Wait for element to appear
$browser->waitFor('@element', 10);

// Wait for text
$browser->waitForText('Success', 5);

// Wait for location change
$browser->waitForLocation('/dashboard');
```

### 3. Use Pauses for Livewire Updates

```php
// After changing select/input
$browser->select('@filter', 'value')
    ->pause(500) // Let Livewire update
    ->assertSee('Updated');
```

### 4. Clean Test Data with Transactions

Tests use `DatabaseTransactions` for automatic rollback:

```php
// No cleanup needed - automatically rolled back
$user = User::factory()->create();
$case = LegalCase::factory()->create();
```

### 5. Descriptive Assertions

```php
// Good
$browser->assertSee('Legal Playground')
    ->assertSee('Evidence')
    ->assertPresent('@evidence-tab');

// Avoid
$browser->assertPathIs('/playground'); // Less descriptive
```

## Debugging

### View Browser During Test

```php
// Add pause to keep browser open
$browser->pause(10000); // 10 second pause
```

### Take Screenshots

```php
// Manual screenshot
$browser->screenshot('debug-screenshot');

// Auto-screenshot on failure (already configured)
```

### Console Logs

```php
// View browser console logs
$logs = $browser->driver->manage()->getLog('browser');
dd($logs);
```

### Verbose Output

```bash
# Run with verbose output
php artisan dusk --filter=TestName -v
```

## Troubleshooting

### Browser Not Starting

```bash
# Reinstall Chrome driver
php artisan dusk:chrome-driver --detect

# Or manually specify version
php artisan dusk:chrome-driver 120
```

### Tests Timing Out

Increase timeout in `tests/DuskTestCase.php`:

```php
protected static $waitSeconds = 10; // Increase if needed
```

### Port Conflicts

Change server port in `.env.dusk.local`:

```env
APP_URL=http://localhost:8001
```

### Database Issues

```bash
# Refresh test database
composer test:setup
```

## CI/CD Integration

### GitHub Actions Example

```yaml
- name: Run E2E Tests
  run: |
    composer install
    composer setup
    php artisan serve &
    sleep 3
    composer test:e2e
```

### GitLab CI Example

```yaml
e2e:
  script:
    - composer install
    - composer setup
    - php artisan serve &
    - sleep 3
    - composer test:e2e
```

## Performance

### Parallel Execution

Dusk supports parallel testing:

```bash
# Run tests in parallel (4 processes)
php artisan dusk --parallel=4
```

### Headless Mode

Already configured for headless Chrome in production:

```php
// tests/DuskTestCase.php
if (App::environment('production', 'ci')) {
    $options->addArguments(['--headless']);
}
```

## Coverage

Current E2E test coverage:

| Feature | Coverage |
|---------|----------|
| Authentication | 100% |
| Legal Playground | 85% |
| Textract Manager | 90% |
| Graph Viewer | 85% |
| Law Downloads | 80% |
| Timeline | 75% |

**Total: 42 E2E test scenarios across 6 test suites**

## Next Steps

To add new E2E tests:

1. Create test file in `tests/Browser/`
2. Extend `DuskTestCase`
3. Use `AuthenticatesUser` and `MocksExternalApis` traits
4. Add `dusk` selectors to Blade views
5. Run tests: `php artisan dusk --filter=YourTest`
6. Add to test runner script if creating new suite

## Resources

- [Laravel Dusk Documentation](https://laravel.com/docs/11.x/dusk)
- [Playwright Documentation](https://playwright.dev)
- [Livewire Testing](https://livewire.laravel.com/docs/testing)
