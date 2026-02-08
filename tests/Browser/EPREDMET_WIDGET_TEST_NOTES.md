# EpredmetWidget Test Notes

## Current Status

Created comprehensive E2E test suite with 20 test methods covering all widget functionality. However, tests are currently failing due to GraphQL API mocking limitations in Dusk environment.

## Issue

The `EpredmetWidget` component calls `fetch()` on mount, which makes a GraphQL API request via `GraphQLAutoClient`. In Dusk tests, `Http::fake()` doesn't work because the application runs in a separate PHP process from the test.

## Test Coverage Created

- ✅ Widget loads successfully
- ✅ Initial case data display
- ✅ Custom data fetching
- ✅ Clear button functionality
- ✅ Validation errors (sud, oznakaBroj)
- ✅ GraphQL API error handling
- ✅ Key dates display (ključni datumi)
- ✅ Rocista (hearings) table
- ✅ Stranke (parties) with collapsible section
- ✅ Pismena (documents) table
- ✅ Vjecnici (council members)
- ✅ Povezani predmeti (related cases)
- ✅ Spis status
- ✅ Request duration display
- ✅ Croatian character display (č, ć, š, ž, đ)
- ✅ Empty state scenarios (no rocista, no stranke, no pismena)

## Solutions

### Option 1: Modify Widget (Recommended)

Add environment check to skip auto-fetch in testing:

```php
public function mount(): void
{
    if (app()->environment('testing') && !config('graphql_client.endpoint')) {
        return;
    }
    $this->fetch();
}
```

### Option 2: Use Livewire Testing

Convert to Livewire component tests instead of Dusk:

```php
use Livewire\Livewire;

public function test_widget_renders()
{
    Livewire::test(EpredmetWidget::class)
        ->assertSet('sud', 5107)
        ->assertSet('oznakaBroj', 'Pp Prz-74/2025')
        ->assertSee('e‑Predmet – GraphQL');
}
```

### Option 3: Real Test Endpoint

Set up actual GraphQL test endpoint that returns mock data.

## Files Modified

- `app/Http/Livewire/EpredmetWidget.php` - Added comprehensive PHPDoc comments
- `resources/views/livewire/epredmet-widget.blade.php` - Added 30+ Dusk selectors
- `tests/Browser/Concerns/MocksExternalApis.php` - Added GraphQL mocking methods
- `tests/Browser/EpredmetWidgetTest.php` - Created 20 comprehensive test methods
- `.env.dusk.local` - Added GRAPHQL_ENDPOINT and GRAPHQL_TOKEN configuration

## Next Steps

1. Implement Option 1 to skip auto-fetch in testing
2. OR convert tests to Livewire component tests
3. OR set up dedicated GraphQL test endpoint
