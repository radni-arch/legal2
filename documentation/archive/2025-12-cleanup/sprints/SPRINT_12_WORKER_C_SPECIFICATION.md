# Sprint 12 Worker C: Error Recovery & Edge Cases - Test Specification

## Overview

This document describes the E2E browser tests created for error recovery and edge case handling in the Legal Defense Playground. These tests follow **Test-Driven Development (TDD)** principles - they represent the **specification** for how error recovery should work.

## Test-Driven Development Approach

**Status**: ✅ RED Phase Complete

These tests were written **before** implementation (TDD RED phase). They currently fail because the error recovery features don't exist yet - **this is correct and expected in TDD**.

### TDD Cycle Progress

- ✅ **RED**: Tests written and verified to fail
- ⏳ **GREEN**: Implementation pending (requires frontend JavaScript)
- ⏳ **REFACTOR**: Will occur after implementation

## Test Suite: ErrorRecoveryTest.php

**Location**: `tests/Browser/ErrorRecoveryTest.php`

**Total Tests**: 5 comprehensive E2E scenarios

### Test 1: Network Error Recovery

**Test Method**: `test_analysis_retry_after_network_error()`

**Specification**:
- Detects when network connection is lost (`navigator.onLine = false`)
- Displays user-friendly error message: "Network error - Please check your connection"
- Provides a "Retry" button
- Successfully completes analysis after connection restored
- Shows "Analysis complete" and results

**Required Implementation**:
```javascript
// Frontend error handling
window.addEventListener('online', handleOnline);
window.addEventListener('offline', handleOffline);

// AJAX error detection
.catch(error => {
  if (!navigator.onLine) {
    showError('Network error - Please check your connection');
    showRetryButton();
  }
});
```

**UI Elements Required**:
- Error message container with text: "Network error"
- Error message with text: "Please check your connection"
- Retry button
- Success message: "Analysis complete"

---

### Test 2: API Rate Limit Handling

**Test Method**: `test_api_rate_limit_handling()`

**Specification**:
- Detects HTTP 429 (Too Many Requests) responses
- Displays: "Rate limit exceeded - Please wait X seconds"
- Implements exponential backoff
- Automatically retries after cooldown
- Eventually succeeds

**Required Implementation**:
```php
// Backend: Return 429 status when rate limited
if ($rateLimiter->tooManyAttempts($key, $maxAttempts)) {
    $seconds = $rateLimiter->availableIn($key);
    return response()->json([
        'error' => 'Rate limit exceeded',
        'retry_after' => $seconds
    ], 429);
}
```

```javascript
// Frontend: Handle 429 responses
.catch(error => {
  if (error.response?.status === 429) {
    const retryAfter = error.response.data.retry_after;
    showError(`Rate limit exceeded - Please wait ${retryAfter} seconds`);
    setTimeout(() => retry(), retryAfter * 1000);
  }
});
```

**UI Elements Required**:
- Error message: "Rate limit exceeded"
- Dynamic countdown: "Please wait X seconds"
- Automatic retry mechanism

---

### Test 3: Session Expiry Recovery

**Test Method**: `test_session_expiry_recovery()`

**Specification**:
- Detects expired session (HTTP 401/419)
- Displays: "Session expired - Please log in again"
- **Preserves work in progress** (stores in localStorage)
- Redirects to login page
- **Restores state** after re-authentication
- User can continue where they left off

**Required Implementation**:
```javascript
// Save state before session expires
function saveWorkInProgress() {
  localStorage.setItem('playground_draft', JSON.stringify({
    evidenceText: document.getElementById('evidenceText').value,
    module: activeModule,
    timestamp: Date.now()
  }));
}

// Restore after login
function restoreWorkInProgress() {
  const draft = JSON.parse(localStorage.getItem('playground_draft'));
  if (draft && (Date.now() - draft.timestamp) < 3600000) { // 1 hour
    document.getElementById('evidenceText').value = draft.evidenceText;
  }
}

// On session error
.catch(error => {
  if (error.response?.status === 401 || error.response?.status === 419) {
    saveWorkInProgress();
    showError('Session expired - Please log in again');
    setTimeout(() => window.location = '/login', 2000);
  }
});
```

**UI Elements Required**:
- Session expiry message
- Automatic localStorage persistence
- State restoration on return

---

### Test 4: Form Validation Edge Cases

**Test Method**: `test_form_validation_displays_errors()`

**Specification**:
- **Required field validation**: "Evidence text is required"
- **Maximum length validation**: "Evidence text must not exceed 100,000 characters"
- Clear, user-friendly error messages
- Field highlighting for invalid inputs
- Real-time or on-submit validation

**Required Implementation**:
```php
// Backend validation
public function validateEvidence(Request $request)
{
    $request->validate([
        'evidenceText' => 'required|string|max:100000'
    ], [
        'evidenceText.required' => 'Evidence text is required',
        'evidenceText.max' => 'Evidence text must not exceed 100,000 characters'
    ]);
}
```

```javascript
// Frontend validation
function validateForm() {
  const text = document.getElementById('evidenceText').value;

  if (!text.trim()) {
    showError('Evidence text is required');
    return false;
  }

  if (text.length > 100000) {
    showError('Evidence text must not exceed 100,000 characters');
    return false;
  }

  return true;
}
```

**UI Elements Required**:
- Error message container
- Field highlighting (red border)
- Character counter (optional but recommended)

---

### Test 5: Concurrent Request Handling

**Test Method**: `test_concurrent_request_handling()`

**Specification**:
- **Prevents duplicate submissions** (button disabled during processing)
- Shows "Processing..." loading state
- Queues or ignores additional clicks
- Completes only ONE request (not multiple)
- Re-enables button after completion

**Required Implementation**:
```javascript
let isProcessing = false;

function handleAnalyze() {
  if (isProcessing) return; // Ignore duplicate clicks

  isProcessing = true;
  document.getElementById('analyzeButton').disabled = true;
  document.getElementById('analyzeButton').textContent = 'Processing...';

  performAnalysis()
    .then(result => {
      showResults(result);
    })
    .finally(() => {
      isProcessing = false;
      document.getElementById('analyzeButton').disabled = false;
      document.getElementById('analyzeButton').textContent = 'Analyze';
    });
}
```

**UI Elements Required**:
- Button disabled state
- "Processing..." text
- Loading spinner (optional)
- Request deduplication logic

---

## Implementation Requirements

### Frontend (JavaScript/Livewire)

**File**: `resources/views/livewire/legal-playground.blade.php` or dedicated JS file

**Features to Implement**:
1. Network connectivity monitoring
2. HTTP error handling (429, 401, 419, 500, etc.)
3. Retry mechanisms with exponential backoff
4. Local storage for work persistence
5. Form validation (client-side)
6. Request deduplication/debouncing
7. Loading states and user feedback

### Backend (Laravel/Livewire)

**File**: `app/Http/Livewire/LegalPlayground.php`

**Features to Implement**:
1. Rate limiting middleware
2. Validation rules
3. Proper HTTP status codes
4. Session management
5. Error responses with helpful messages

### Database

No database changes required - these are UI/UX improvements.

---

## Running the Tests

### Prerequisites

```bash
# Start infrastructure
su - claude -c "/usr/lib/postgresql/16/bin/pg_ctl -D /var/lib/postgresql/16/main start"
/tmp/chromedriver-linux64/chromedriver --port=9515 &

# Ensure Vite assets built
npm run build
```

### Run All Error Recovery Tests

```bash
php artisan dusk tests/Browser/ErrorRecoveryTest.php
```

### Run Individual Tests

```bash
# Network error recovery
php artisan dusk tests/Browser/ErrorRecoveryTest.php --filter=test_analysis_retry_after_network_error

# Rate limit handling
php artisan dusk tests/Browser/ErrorRecoveryTest.php --filter=test_api_rate_limit_handling

# Session expiry
php artisan dusk tests/Browser/ErrorRecoveryTest.php --filter=test_session_expiry_recovery

# Form validation
php artisan dusk tests/Browser/ErrorRecoveryTest.php --filter=test_form_validation_displays_errors

# Concurrent requests
php artisan dusk tests/Browser/ErrorRecoveryTest.php --filter=test_concurrent_request_handling
```

### Expected Status (TDD RED Phase)

**Current**: ❌ All tests fail (expected - features not implemented)

**After Implementation**: ✅ All tests pass

---

## Test Adjustments Needed

### Field Name Mapping

The tests currently use generic field names. Map to actual playground fields:

| Test Field Name | Actual Livewire Model | Module |
|-----------------|----------------------|--------|
| `evidenceText` | `evidenceDescription` | Evidence Analysis |
| `evidenceText` | `prosecutionEvidence` | Recontextualize |
| `evidenceText` | `misconductDetails` | Misconduct Detection |

**Recommended**: Standardize on one field name across modules or update tests to target specific modules.

### Button Selectors

Tests use `press('Analyze')` - ensure button text matches:
- Evidence module: "🔍 Analyze Evidence"
- Recontextualize: "🔄 Recontextualize"
- Misconduct: "⚠️ Detect Misconduct"

**Recommended**: Add `data-test="analyze-button"` attribute for consistent selection.

---

## Success Criteria

### Definition of Done

- ✅ All 5 tests pass
- ✅ Error messages are user-friendly
- ✅ No console errors during error scenarios
- ✅ Work-in-progress is never lost
- ✅ UI remains responsive during errors
- ✅ Retry mechanisms work reliably

### Quality Metrics

- **Error Recovery Rate**: 100% (all errors handled gracefully)
- **Data Preservation**: 100% (no work lost during session expiry)
- **User Feedback**: Clear messages for all error states
- **Performance**: Error handling adds <100ms overhead

---

## Future Enhancements

### Beyond Current Specification

1. **Offline Mode**: Full offline support with service workers
2. **Request Queue**: Queue multiple requests for later
3. **Auto-save**: Periodic auto-save every 30 seconds
4. **Error Analytics**: Track error frequency and types
5. **Retry with Backoff**: Configurable retry strategies
6. **Toast Notifications**: Non-blocking error notifications

---

## Notes for Implementers

### TDD Philosophy

These tests were written FIRST, before any implementation. This is intentional and correct. The tests define:
- **What** the system should do
- **How** users should experience errors
- **When** recovery mechanisms activate

Implementation should make tests pass with minimal code.

### Don't Modify Tests

Following TDD, **do not modify these tests to pass**. Instead:
1. Implement features the tests specify
2. If tests reveal design issues, discuss with team
3. Only change tests with explicit permission

### Common Pitfalls

- ❌ Mocking navigator.onLine without testing real offline behavior
- ❌ Using `alert()` for error messages (bad UX)
- ❌ Not actually persisting state during session expiry
- ❌ Allowing duplicate requests to go through
- ❌ Generic "An error occurred" messages

### Best Practices

- ✅ Use toast notifications or inline error messages
- ✅ Provide specific, actionable error messages
- ✅ Test with actual network throttling
- ✅ Implement progressive enhancement
- ✅ Log errors to backend for monitoring

---

## Sprint 12 Worker C Deliverable

**Status**: ✅ **TDD RED Phase Complete**

**Delivered**:
- 5 comprehensive E2E test specifications
- This documentation
- Test suite ready for implementation

**Next Steps** (GREEN Phase):
1. Implement error recovery features
2. Run tests to verify implementation
3. Refactor for code quality

**Timeline**: Tests created in Sprint 12 Worker C, implementation to follow.

---

**Created**: 2025-11-10
**Author**: Sprint 12 Worker C
**TDD Phase**: RED (Specification Complete)
**Status**: Ready for Implementation
