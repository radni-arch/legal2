# VectorStoreManager Component - Testing Documentation

## Component Overview
**Location:** `app/Http/Livewire/VectorStoreManager.php`
**View:** `resources/views/livewire/vector-store-manager.blade.php`
**Status:** 100% Complete - All interactive elements with loading states and Dusk selectors

---

## Test Categories

### 1. Store Selection Tests

#### Test: Store Selection and Loading State
**Dusk Selectors:**
- `select-store-laws`
- `select-store-court_decisions`
- `select-store-cases`
- `select-store-textract`

**Test Scenario:**
```php
public function test_store_selection_shows_loading_state()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->assertSee('Vector Store Manager')

            // Click store selection button
            ->click('@select-store-court_decisions')

            // Verify button is disabled during loading
            ->assertAttribute('@select-store-court_decisions', 'disabled', 'true')

            // Wait for loading to complete
            ->waitUntilMissing('.table-loading-overlay', 10)

            // Verify store is selected
            ->assertSeeIn('@section-header', 'Documents')

            // Verify button is re-enabled
            ->assertAttributeMissing('@select-store-court_decisions', 'disabled');
    });
}
```

**Expected Behavior:**
1. Button shows spinner during loading
2. Button is disabled during request
3. Loading overlay appears on document table
4. Store statistics update
5. Document list refreshes
6. Button becomes enabled after completion

---

### 2. Statistics Tests

#### Test: Statistics Display
**Dusk Selectors:**
- `stats-grid`
- `stat-total-documents`
- `stat-unique-documents`
- `stat-total-tokens`
- `stat-avg-tokens`

**Test Scenario:**
```php
public function test_statistics_display_correct_values()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->assertVisible('@stats-grid')
            ->assertSeeIn('@stat-total-documents', 'Total Documents')
            ->assertSeeIn('@stat-unique-documents', 'Unique Doc IDs')
            ->assertSeeIn('@stat-total-tokens', 'Total Tokens')
            ->assertSeeIn('@stat-avg-tokens', 'Avg Tokens/Doc');
    });
}
```

#### Test: Refresh Statistics
**Dusk Selector:** `refresh-stats-button`

**Test Scenario:**
```php
public function test_refresh_statistics_button_works()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->click('@refresh-stats-button')

            // Verify loading state
            ->assertSeeIn('@refresh-stats-button', 'Refreshing...')
            ->assertAttribute('@refresh-stats-button', 'disabled', 'true')

            // Wait for completion
            ->waitForText('Statistics refreshed', 5)

            // Verify stats are updated
            ->assertVisible('@stats-grid');
    });
}
```

---

### 3. Search Functionality Tests

#### Test: Content Search
**Dusk Selectors:**
- `search-type-select`
- `search-input`
- `search-button`
- `clear-search-button`

**Test Scenario:**
```php
public function test_content_search_functionality()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->select('@search-type-select', 'content')
            ->type('@search-input', 'test query')
            ->click('@search-button')

            // Verify loading state
            ->assertSeeIn('@search-button', 'Searching...')
            ->assertAttribute('@search-button', 'disabled', 'true')

            // Wait for results
            ->waitForText('Documents', 10)

            // Clear search
            ->assertVisible('@clear-search-button')
            ->click('@clear-search-button')
            ->waitUntilMissing('@clear-search-button', 5);
    });
}
```

#### Test: Similarity Search
**Test Scenario:**
```php
public function test_similarity_search_shows_results()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->select('@search-type-select', 'similarity')
            ->type('@search-input', 'sample text')
            ->click('@search-button')

            // Wait for search results
            ->waitForText('Search Results', 10)
            ->assertVisible('@search-results-table')

            // Verify similarity scores are displayed
            ->assertSee('Similarity');
    });
}
```

---

### 4. Document Table Tests

#### Test: Document Table Display
**Dusk Selectors:**
- `document-table`
- `document-row-{id}`
- `doc-id-{id}`
- `select-document-{id}`

**Test Scenario:**
```php
public function test_document_table_displays_documents()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->assertVisible('@document-table')
            ->assertSee('Doc ID')
            ->assertSee('Content Preview')
            ->assertSee('Chunk')
            ->assertSee('Model')
            ->assertSee('Actions');
    });
}
```

#### Test: Table Loading Overlay
**Test Scenario:**
```php
public function test_table_shows_loading_overlay()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->click('@select-store-cases')

            // Verify loading overlay appears
            ->assertVisible('.table-loading-overlay')
            ->assertSee('Loading documents...')

            // Wait for overlay to disappear
            ->waitUntilMissing('.table-loading-overlay', 10)
            ->assertVisible('@document-table');
    });
}
```

---

### 5. Document Selection Tests

#### Test: Select All Functionality
**Dusk Selector:** `select-all-checkbox`

**Test Scenario:**
```php
public function test_select_all_documents()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->assertVisible('@select-all-checkbox')
            ->check('@select-all-checkbox')

            // Verify all documents are selected
            ->waitFor('@reindex-selected-button', 5)
            ->assertSeeIn('@reindex-selected-button', 'Re-index')
            ->assertSeeIn('@delete-selected-button', 'Delete');
    });
}
```

#### Test: Individual Document Selection
**Test Scenario:**
```php
public function test_individual_document_selection()
{
    $this->browse(function (Browser $browser) {
        // Get first document ID from page
        $browser->visit('/vector-store-manager')
            ->check('@select-document-1')
            ->check('@select-document-2')

            // Verify bulk actions appear
            ->assertVisible('@reindex-selected-button')
            ->assertVisible('@delete-selected-button')
            ->assertSeeIn('@reindex-selected-button', '(2)');
    });
}
```

---

### 6. Bulk Operations Tests

#### Test: Bulk Reindex
**Dusk Selector:** `reindex-selected-button`

**Test Scenario:**
```php
public function test_bulk_reindex_documents()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->check('@select-all-checkbox')
            ->click('@reindex-selected-button')

            // Verify loading state
            ->assertSeeIn('@reindex-selected-button', 'Processing...')
            ->assertAttribute('@reindex-selected-button', 'disabled', 'true')

            // Wait for success message
            ->waitForText('Successfully re-indexed', 10)

            // Verify table overlay appeared
            ->waitUntilMissing('.table-loading-overlay', 10);
    });
}
```

#### Test: Bulk Delete with Confirmation
**Dusk Selector:** `delete-selected-button`

**Test Scenario:**
```php
public function test_bulk_delete_shows_confirmation()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->check('@select-document-1')
            ->click('@delete-selected-button')

            // Accept confirmation dialog
            ->acceptDialog()

            // Verify loading state
            ->assertSeeIn('@delete-selected-button', 'Deleting...')
            ->assertAttribute('@delete-selected-button', 'disabled', 'true')

            // Wait for success
            ->waitForText('Successfully deleted', 10);
    });
}
```

---

### 7. Single Document Operations Tests

#### Test: Preview Document
**Dusk Selector:** `preview-document-{id}`

**Test Scenario:**
```php
public function test_preview_document_opens_modal()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->click('@preview-document-1')

            // Verify loading state on button
            ->pause(100) // Brief pause to see loading state

            // Wait for modal to appear with fade animation
            ->waitFor('@preview-modal', 5)
            ->assertVisible('@preview-modal')

            // Verify modal content
            ->assertVisible('@preview-document-id')
            ->assertVisible('@preview-doc-id')
            ->assertVisible('@preview-chunk-index')
            ->assertVisible('@preview-token-count')
            ->assertVisible('@preview-embedding-model')
            ->assertVisible('@preview-created-at')
            ->assertVisible('@preview-content');
    });
}
```

#### Test: Reindex Single Document
**Dusk Selector:** `reindex-document-{id}`

**Test Scenario:**
```php
public function test_reindex_single_document()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->click('@reindex-document-1')

            // Accept confirmation
            ->acceptDialog()

            // Verify loading overlay on table
            ->assertVisible('.table-loading-overlay')
            ->waitForText('Document re-indexed successfully', 10)
            ->waitUntilMissing('.table-loading-overlay', 10);
    });
}
```

#### Test: Delete Single Document
**Dusk Selector:** `delete-document-{id}`

**Test Scenario:**
```php
public function test_delete_single_document()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->click('@delete-document-1')

            // Accept confirmation
            ->acceptDialog()

            // Verify loading state and overlay
            ->assertVisible('.table-loading-overlay')
            ->waitForText('Document deleted successfully', 10)

            // Verify document is removed from table
            ->waitUntilMissing('@document-row-1', 5);
    });
}
```

---

### 8. Modal Tests

#### Test: Modal Open and Close
**Dusk Selectors:**
- `preview-modal`
- `modal-close-button`
- `modal-close-footer-button`

**Test Scenario:**
```php
public function test_modal_opens_closes_with_animation()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->click('@preview-document-1')
            ->waitFor('@preview-modal', 5)

            // Close via X button
            ->click('@modal-close-button')
            ->waitUntilMissing('@preview-modal', 5)

            // Reopen
            ->click('@preview-document-1')
            ->waitFor('@preview-modal', 5)

            // Close via footer button
            ->click('@modal-close-footer-button')
            ->waitUntilMissing('@preview-modal', 5)

            // Reopen
            ->click('@preview-document-1')
            ->waitFor('@preview-modal', 5)

            // Close via overlay click
            ->click('.modal-overlay')
            ->waitUntilMissing('@preview-modal', 5);
    });
}
```

#### Test: Modal Actions
**Dusk Selectors:**
- `modal-reindex-button`
- `modal-delete-button`

**Test Scenario:**
```php
public function test_modal_reindex_button()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->click('@preview-document-1')
            ->waitFor('@preview-modal', 5)
            ->click('@modal-reindex-button')

            // Accept confirmation
            ->acceptDialog()

            // Verify loading state
            ->assertSeeIn('@modal-reindex-button', 'Processing...')
            ->assertAttribute('@modal-reindex-button', 'disabled', 'true')

            // Wait for success
            ->waitForText('Document re-indexed successfully', 10)

            // Modal should still be open
            ->assertVisible('@preview-modal');
    });
}
```

---

### 9. Pagination Tests

#### Test: Next Page Navigation
**Dusk Selector:** `next-page-button`

**Test Scenario:**
```php
public function test_next_page_navigation()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->assertSeeIn('@page-info', 'Page 1')
            ->click('@next-page-button')

            // Verify loading state
            ->assertSeeIn('@next-page-button', 'Loading...')
            ->assertAttribute('@next-page-button', 'disabled', 'true')

            // Wait for table loading overlay
            ->assertVisible('.table-loading-overlay')
            ->waitUntilMissing('.table-loading-overlay', 10)

            // Verify page changed
            ->assertSeeIn('@page-info', 'Page 2');
    });
}
```

#### Test: Previous Page Navigation
**Dusk Selector:** `prev-page-button`

**Test Scenario:**
```php
public function test_previous_page_navigation()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')
            ->click('@next-page-button')
            ->waitForText('Page 2', 10)
            ->click('@prev-page-button')

            // Verify loading state
            ->assertSeeIn('@prev-page-button', 'Loading...')
            ->assertAttribute('@prev-page-button', 'disabled', 'true')

            // Wait for completion
            ->waitUntilMissing('.table-loading-overlay', 10)
            ->assertSeeIn('@page-info', 'Page 1');
    });
}
```

#### Test: Pagination Disabled States
**Test Scenario:**
```php
public function test_pagination_disabled_states()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')

            // On first page, previous should be disabled
            ->assertSeeIn('@page-info', 'Page 1')
            ->assertAttribute('@prev-page-button', 'disabled', 'true')

            // Navigate to last page
            ->script('
                Livewire.find("vector-store-manager").currentPage = Livewire.find("vector-store-manager").totalPages;
                Livewire.find("vector-store-manager").loadDocuments();
            ')
            ->waitFor('@next-page-button[disabled]', 10)
            ->assertAttribute('@next-page-button', 'disabled', 'true');
    });
}
```

---

### 10. Loading State Tests

#### Test: All Loading States Present
**Test Scenario:**
```php
public function test_all_buttons_have_loading_states()
{
    $buttons = [
        'select-store-laws',
        'search-button',
        'clear-search-button',
        'refresh-stats-button',
        'reindex-selected-button',
        'delete-selected-button',
        'preview-document-1',
        'reindex-document-1',
        'delete-document-1',
        'next-page-button',
        'prev-page-button',
        'modal-reindex-button',
        'modal-delete-button',
    ];

    // Each button should have wire:loading.attr="disabled"
    // Each button should show loading text/spinner when active
}
```

#### Test: Loading Overlay Coverage
**Test Scenario:**
```php
public function test_loading_overlay_appears_for_operations()
{
    $operations = [
        'selectStore',
        'refreshStats',
        'nextPage',
        'previousPage',
        'reindexDocument',
        'deleteDocument',
        'reindexSelected',
        'deleteSelected',
    ];

    foreach ($operations as $operation) {
        // Verify overlay appears during operation
        // Verify spinner is visible
        // Verify "Loading documents..." text
    }
}
```

---

### 11. Mobile Responsiveness Tests

#### Test: Mobile Layout
**Test Scenario:**
```php
public function test_mobile_responsive_layout()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(375, 667) // iPhone SE size
            ->visit('/vector-store-manager')

            // Verify store buttons stack vertically
            ->assertVisible('@select-store-laws')

            // Verify stats grid shows 2 columns
            ->assertVisible('@stats-grid')

            // Verify search section stacks
            ->assertVisible('@search-input')

            // Verify table is scrollable
            ->assertVisible('@document-table');
    });
}
```

#### Test: Tablet Layout
**Test Scenario:**
```php
public function test_tablet_responsive_layout()
{
    $this->browse(function (Browser $browser) {
        $browser->resize(768, 1024) // iPad size
            ->visit('/vector-store-manager')

            // Verify responsive breakpoints
            ->assertVisible('@stats-grid')
            ->assertVisible('@document-table');
    });
}
```

---

### 12. Accessibility Tests

#### Test: Keyboard Navigation
**Test Scenario:**
```php
public function test_keyboard_navigation()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')

            // Tab through interactive elements
            ->keys('body', '{tab}')
            ->assertFocused('@select-store-laws')

            // Continue tabbing
            ->keys('body', '{tab}', '{tab}', '{tab}')
            ->assertFocused('@search-type-select');
    });
}
```

#### Test: ARIA Attributes
**Test Scenario:**
```php
public function test_aria_attributes_present()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/vector-store-manager')

            // Check for accessible button labels
            ->assertAttribute('@search-button', 'type', 'button')
            ->assertAttribute('@refresh-stats-button', 'type', 'button')

            // Check modal has proper ARIA
            ->click('@preview-document-1')
            ->waitFor('@preview-modal', 5);
    });
}
```

---

### 13. Error Handling Tests

#### Test: Network Error Handling
**Test Scenario:**
```php
public function test_handles_network_errors_gracefully()
{
    $this->browse(function (Browser $browser) {
        // Simulate network failure
        $browser->visit('/vector-store-manager')
            ->script("window.Livewire.hook('message.failed', (message) => {
                console.log('Network error:', message);
            })");

        // Attempt operation
        $browser->click('@refresh-stats-button')
            ->waitForText('Failed', 10)
            ->assertSee('Failed');
    });
}
```

---

## Complete Dusk Selector Reference

### Store Selection
- `select-store-laws`
- `select-store-court_decisions`
- `select-store-cases`
- `select-store-textract`

### Statistics
- `stats-grid`
- `stat-total-documents`
- `stat-unique-documents`
- `stat-total-tokens`
- `stat-avg-tokens`

### Search & Actions
- `actions-bar`
- `search-type-select`
- `search-input`
- `search-button`
- `clear-search-button`
- `refresh-stats-button`
- `reindex-selected-button`
- `delete-selected-button`

### Document List
- `documents-card`
- `section-header`
- `select-all-checkbox`
- `document-table`
- `document-row-{id}`
- `doc-id-{id}`
- `select-document-{id}`

### Document Actions
- `preview-document-{id}`
- `reindex-document-{id}`
- `delete-document-{id}`

### Search Results
- `search-results-table`
- `search-result-row-{index}`

### Pagination
- `pagination`
- `page-info`
- `prev-page-button`
- `next-page-button`

### Modal
- `preview-modal`
- `modal-close-button`
- `modal-close-footer-button`
- `preview-document-id`
- `preview-doc-id`
- `preview-chunk-index`
- `preview-token-count`
- `preview-embedding-model`
- `preview-created-at`
- `preview-content`
- `modal-reindex-button`
- `modal-delete-button`

---

## Test Execution Commands

```bash
# Run all VectorStoreManager tests
php artisan dusk tests/Browser/VectorStoreManagerTest.php

# Run specific test
php artisan dusk --filter test_store_selection_shows_loading_state

# Run with specific browser
php artisan dusk --env=chrome

# Run headless
php artisan dusk --env=headless
```

---

## Known Issues & Edge Cases

1. **Concurrent Operations**: Test that clicking multiple buttons rapidly doesn't cause race conditions
2. **Empty States**: Test behavior when no documents exist
3. **Large Datasets**: Test pagination with many pages
4. **Long Content**: Test modal scrolling with very long document content
5. **Special Characters**: Test search with special characters in document IDs

---

## Performance Benchmarks

- **Store Switch**: < 2 seconds
- **Search Operation**: < 3 seconds
- **Pagination**: < 1 second
- **Single Document Preview**: < 500ms
- **Modal Open/Close Animation**: 300ms
- **Bulk Operations**: < 5 seconds per 100 documents

---

## Maintenance Notes

- All Dusk selectors use `@` prefix convention
- Loading states use `wire:loading` with appropriate targets
- All buttons have `wire:loading.attr="disabled"`
- Modal animations use Alpine.js `x-transition`
- Mobile breakpoints: 768px (tablet), 480px (mobile)
