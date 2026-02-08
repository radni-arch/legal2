# OpenAI Session Credentials Modal Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a modal to the OpenAI Responses Viewer that prompts users for session credentials (Bearer token, Organization, Project) when a 401 error occurs, stores them in the session, and retries the API call.

**Architecture:** The Livewire component detects 401 errors with the "session key" message, triggers a modal with three input fields, stores credentials in Laravel session, and passes them to OpenAIService which uses them as header overrides for the Responses API calls.

**Tech Stack:** Laravel 11, Livewire 3, Alpine.js, Laravel Session, TailwindCSS

---

## Task 1: Add Session Credential Properties to Livewire Component

**Files:**
- Modify: `app/Http/Livewire/OpenAIResponsesViewer.php`

**Step 1: Add component properties for modal state and credentials**

Add these properties after the existing public properties (around line 45):

```php
// Session credentials modal
public bool $showCredentialsModal = false;
public string $sessionToken = '';
public string $organizationId = '';
public string $projectId = '';
public bool $needsCredentials = false;
```

**Step 2: Add method to check if credentials exist in session**

Add this method after the `mount()` method:

```php
/**
 * Load any existing session credentials from Laravel session.
 */
protected function loadSessionCredentials(): void
{
    $this->sessionToken = session('openai_session_token', '');
    $this->organizationId = session('openai_organization_id', '');
    $this->projectId = session('openai_project_id', '');
}
```

**Step 3: Call credential loader in mount()**

In the `mount()` method, add before `$this->loadResponses()`:

```php
$this->loadSessionCredentials();
```

**Step 4: Commit**

```bash
git add app/Http/Livewire/OpenAIResponsesViewer.php
git commit -m "feat(openai-viewer): add session credential properties"
```

---

## Task 2: Add Credential Save and Modal Control Methods

**Files:**
- Modify: `app/Http/Livewire/OpenAIResponsesViewer.php`

**Step 1: Add method to save credentials and retry**

Add after `loadSessionCredentials()`:

```php
/**
 * Save credentials to session and retry loading responses.
 */
public function saveCredentials(): void
{
    // Validate inputs
    if (empty(trim($this->sessionToken))) {
        $this->error = 'Session token is required.';
        return;
    }

    // Store in Laravel session
    session([
        'openai_session_token' => trim($this->sessionToken),
        'openai_organization_id' => trim($this->organizationId),
        'openai_project_id' => trim($this->projectId),
    ]);

    // Close modal and retry
    $this->showCredentialsModal = false;
    $this->needsCredentials = false;
    $this->error = null;

    $this->loadResponses();
}

/**
 * Cancel credentials modal without saving.
 */
public function cancelCredentials(): void
{
    $this->showCredentialsModal = false;
    // Restore from session if available
    $this->loadSessionCredentials();
}

/**
 * Open credentials modal (for manual trigger).
 */
public function openCredentialsModal(): void
{
    $this->loadSessionCredentials();
    $this->showCredentialsModal = true;
}

/**
 * Clear stored credentials from session.
 */
public function clearCredentials(): void
{
    session()->forget(['openai_session_token', 'openai_organization_id', 'openai_project_id']);
    $this->sessionToken = '';
    $this->organizationId = '';
    $this->projectId = '';
}
```

**Step 2: Commit**

```bash
git add app/Http/Livewire/OpenAIResponsesViewer.php
git commit -m "feat(openai-viewer): add credential save/cancel/clear methods"
```

---

## Task 3: Update Error Handling to Detect 401 Session Key Error

**Files:**
- Modify: `app/Http/Livewire/OpenAIResponsesViewer.php`

**Step 1: Modify the catch block in loadResponses()**

Replace the existing catch block in `loadResponses()` (around line 95-102) with:

```php
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
    
    // Check for 401 session key error
    if ($this->isSessionKeyError($errorMessage)) {
        $this->needsCredentials = true;
        $this->showCredentialsModal = true;
        $this->error = 'OpenAI requires session authentication. Please enter your credentials.';
        Log::info('openai.responses.viewer.session_required', [
            'message' => $errorMessage,
        ]);
        return;
    }

    // Surface a friendly message without leaking secrets
    $this->error = 'Failed to load OpenAI responses: ' . $errorMessage;
    Log::warning('openai.responses.viewer.error', [
        'error' => $errorMessage,
        'class' => get_class($e),
    ]);
}
```

**Step 2: Add helper method to detect session key errors**

Add this method after `clip()`:

```php
/**
 * Check if error message indicates a session key requirement.
 *
 * @param string $message Error message from API
 * @return bool True if this is a 401 session key error
 */
protected function isSessionKeyError(string $message): bool
{
    $indicators = [
        'status code 401',
        'session key',
        'must be made with a session',
    ];

    $lowerMessage = mb_strtolower($message);
    
    foreach ($indicators as $indicator) {
        if (str_contains($lowerMessage, mb_strtolower($indicator))) {
            return true;
        }
    }

    return false;
}
```

**Step 3: Commit**

```bash
git add app/Http/Livewire/OpenAIResponsesViewer.php
git commit -m "feat(openai-viewer): detect 401 session key errors and trigger modal"
```

---

## Task 4: Update OpenAIService to Accept Session Credentials Override

**Files:**
- Modify: `app/Services/OpenAIService.php`

**Step 1: Add method for responses with custom session headers**

Add this new method after `getResponses()` (around line 350):

```php
/**
 * Fetch responses list with custom session credentials.
 * Used when browser-based session authentication is required.
 *
 * @param array $query Query parameters
 * @param array $include Include parameters
 * @param array $sessionCredentials ['token' => '...', 'organization' => '...', 'project' => '...']
 * @return array Response data
 */
public function getResponsesWithSession(array $query = [], array $include = [], array $sessionCredentials = []): array
{
    $query = array_filter([
        'created_after' => $query['created_after'] ?? null,
        'created_before' => $query['created_before'] ?? null,
        'limit' => $query['limit'] ?? null,
        'order' => $query['order'] ?? null,
        'input_item_limit' => $query['input_item_limit'] ?? 1,
        'output_item_limit' => $query['output_item_limit'] ?? 1,
    ], fn ($v) => $v !== null);

    $include = $include ?: [
        'message.input_text',
        'output_text',
    ];

    return $this->responsesListWithSession($query, $include, $sessionCredentials);
}

/**
 * Fetch list of responses using session-based authentication.
 *
 * @param array $query Query parameters
 * @param array $include Include fields
 * @param array $sessionCredentials Session credentials
 * @return array Response data
 */
protected function responsesListWithSession(array $query = [], array $include = [], array $sessionCredentials = []): array
{
    // Build query string manually to support repeated include[] keys
    $parts = [];
    foreach ($include as $inc) {
        $parts[] = 'include[]=' . rawurlencode($inc);
    }
    foreach ($query as $k => $v) {
        if ($v === null) {
            continue;
        }
        $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
    }
    $qs = $parts ? ('?' . implode('&', $parts)) : '';

    // Build headers with session credentials
    $headers = [
        'OpenAI-Beta' => 'responses=v1',
        'Accept' => '*/*',
    ];

    if (!empty($sessionCredentials['token'])) {
        $headers['Authorization'] = 'Bearer ' . $sessionCredentials['token'];
    }
    if (!empty($sessionCredentials['organization'])) {
        $headers['OpenAI-Organization'] = $sessionCredentials['organization'];
    }
    if (!empty($sessionCredentials['project'])) {
        $headers['OpenAI-Project'] = $sessionCredentials['project'];
    }

    return $this->getWithSessionHeaders('/responses' . $qs, $headers);
}

/**
 * GET request with fully custom headers (bypasses default auth).
 *
 * @param string $url URL path
 * @param array $headers Full headers to use
 * @return array Response JSON
 * @throws Throwable
 */
protected function getWithSessionHeaders(string $url, array $headers): array
{
    $reqId = (string) Str::uuid();
    $start = microtime(true);

    $this->logChannel()->info('openai.request.session', [
        'event' => 'openai.request.session',
        'request_id' => $reqId,
        'method' => 'GET',
        'url' => ltrim($url, '/'),
        'headers' => array_keys($headers),
    ]);

    try {
        $resp = $this->circuitBreaker->call(function () use ($url, $headers) {
            return Http::withHeaders($headers)
                ->baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->get(ltrim($url, '/'));
        });

        $duration = (int) round((microtime(true) - $start) * 1000);

        $status = $resp->status();
        $body = (string) $resp->body();
        $json = null;
        try {
            $json = $resp->json();
        } catch (Throwable $e) {
            $json = null;
        }

        $this->logChannel()->info('openai.response.session', [
            'event' => 'openai.response.session',
            'request_id' => $reqId,
            'status' => $status,
            'duration_ms' => $duration,
            'response' => $json ?? [
                'text' => Str::limit($body, 2000),
            ],
        ]);

        $resp->throw();

        return $json ?? [];
    } catch (Throwable $e) {
        $duration = (int) round((microtime(true) - $start) * 1000);
        $this->logChannel()->error('openai.error.session', [
            'event' => 'openai.error.session',
            'request_id' => $reqId,
            'duration_ms' => $duration,
            'error' => [
                'message' => $e->getMessage(),
                'code' => method_exists($e, 'getCode') ? $e->getCode() : 0,
                'class' => get_class($e),
            ],
        ]);
        throw $e;
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/OpenAIService.php
git commit -m "feat(openai-service): add session-based responses API methods"
```

---

## Task 5: Update Livewire Component to Use Session Credentials

**Files:**
- Modify: `app/Http/Livewire/OpenAIResponsesViewer.php`

**Step 1: Modify loadResponses() to use session credentials**

Replace the API call section in `loadResponses()` (the try block content before the catch):

```php
try {
    /** @var OpenAIService $svc */
    $svc = app(OpenAIService::class);

    $query = [
        'created_after' => $fromTs,
        'created_before' => $toTs,
        'limit' => max(1, min(100, (int) $this->limit)),
        'order' => in_array(strtolower($this->order), ['asc', 'desc'], true) ? strtolower($this->order) : 'desc',
        'input_item_limit' => 1,
        'output_item_limit' => 1,
    ];

    $include = [
        'message.input_text',
        'message.input_image.image_url',
        'output_text',
        'computer_call_output.output.image_url',
        'file_search_call.results',
    ];

    // Check if we have session credentials
    $sessionToken = session('openai_session_token');
    
    if ($sessionToken) {
        // Use session-based authentication
        $sessionCredentials = [
            'token' => $sessionToken,
            'organization' => session('openai_organization_id', ''),
            'project' => session('openai_project_id', ''),
        ];
        $resp = $svc->getResponsesWithSession($query, $include, $sessionCredentials);
    } else {
        // Use default API key authentication
        $resp = $svc->getResponses($query, $include);
    }

    $data = (array) ($resp['data'] ?? []);

    $items = [];
    foreach ($data as $row) {
        $items[] = $this->mapResponseRow((array) $row);
    }

    // Optional search filter on mapped content
    if ($this->search) {
        $q = mb_strtolower($this->search);
        $items = array_values(array_filter($items, function ($it) use ($q) {
            $hay = mb_strtolower(json_encode([$it['id'], $it['input_text'], $it['output_text'], $it['model'] ?? '', $it['created_at'] ?? '']));

            return str_contains($hay, $q);
        }));
    }

    $this->items = $items;
    $this->needsCredentials = false; // Success, clear flag
```

**Step 2: Commit**

```bash
git add app/Http/Livewire/OpenAIResponsesViewer.php
git commit -m "feat(openai-viewer): use session credentials when available"
```

---

## Task 6: Create Modal Blade Partial

**Files:**
- Create: `resources/views/livewire/partials/openai-credentials-modal.blade.php`

**Step 1: Create the modal partial**

```blade
{{-- OpenAI Session Credentials Modal --}}
<div
    x-data="{ open: @entangle('showCredentialsModal') }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
>
    {{-- Background overlay --}}
    <div
        x-show="open"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="$wire.cancelCredentials()"
    ></div>

    {{-- Modal panel --}}
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div
            x-show="open"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6"
        >
            {{-- Header --}}
            <div class="mb-4">
                <h3 class="text-lg font-semibold leading-6 text-gray-900" id="modal-title">
                    OpenAI Session Authentication
                </h3>
                <p class="mt-2 text-sm text-gray-500">
                    The OpenAI Responses API requires session-based authentication. 
                    Enter your credentials from the OpenAI platform.
                </p>
            </div>

            {{-- Form --}}
            <div class="space-y-4">
                {{-- Session Token --}}
                <div>
                    <label for="sessionToken" class="block text-sm font-medium text-gray-700">
                        Session Token <span class="text-red-500">*</span>
                    </label>
                    <div class="mt-1">
                        <input
                            type="password"
                            id="sessionToken"
                            wire:model="sessionToken"
                            placeholder="sess-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                        >
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Found in browser DevTools → Network → Request Headers → Authorization: Bearer sess-...
                    </p>
                </div>

                {{-- Organization ID --}}
                <div>
                    <label for="organizationId" class="block text-sm font-medium text-gray-700">
                        Organization ID
                    </label>
                    <div class="mt-1">
                        <input
                            type="text"
                            id="organizationId"
                            wire:model="organizationId"
                            placeholder="org-xxxxxxxxxxxxxxxxxxxxxxxx"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                        >
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Header: OpenAI-Organization
                    </p>
                </div>

                {{-- Project ID --}}
                <div>
                    <label for="projectId" class="block text-sm font-medium text-gray-700">
                        Project ID
                    </label>
                    <div class="mt-1">
                        <input
                            type="text"
                            id="projectId"
                            wire:model="projectId"
                            placeholder="proj_xxxxxxxxxxxxxxxxxxxxxxxx"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                        >
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Header: OpenAI-Project
                    </p>
                </div>
            </div>

            {{-- Footer buttons --}}
            <div class="mt-6 flex justify-end space-x-3">
                <button
                    type="button"
                    wire:click="cancelCredentials"
                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    wire:click="saveCredentials"
                    class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Save & Retry
                </button>
            </div>
        </div>
    </div>
</div>
```

**Step 2: Commit**

```bash
git add resources/views/livewire/partials/openai-credentials-modal.blade.php
git commit -m "feat(openai-viewer): add credentials modal blade partial"
```

---

## Task 7: Update Main Livewire View to Include Modal

**Files:**
- Modify: `resources/views/livewire/openai-responses-viewer.blade.php`

**Step 1: Include the modal partial at the end of the main view**

Add before the closing `</div>` of the main container:

```blade
{{-- Session Credentials Modal --}}
@include('livewire.partials.openai-credentials-modal')
```

**Step 2: Add a "Configure Credentials" button to the toolbar**

Find the filter/toolbar section and add this button (typically near the refresh button):

```blade
{{-- Credentials button --}}
<button
    type="button"
    wire:click="openCredentialsModal"
    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
    title="Configure OpenAI Session Credentials"
>
    <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
    </svg>
    Credentials
</button>

{{-- Show indicator if using session auth --}}
@if(session('openai_session_token'))
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
        Session Auth Active
    </span>
@endif
```

**Step 3: Update error display to show modal trigger on auth errors**

Find the error display section and enhance it:

```blade
@if($error)
    <div class="rounded-md bg-red-50 p-4 mb-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-red-800">{{ $error }}</p>
                @if($needsCredentials)
                    <button
                        type="button"
                        wire:click="openCredentialsModal"
                        class="mt-2 text-sm font-medium text-red-600 hover:text-red-500 underline"
                    >
                        Enter credentials →
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif
```

**Step 4: Commit**

```bash
git add resources/views/livewire/openai-responses-viewer.blade.php
git commit -m "feat(openai-viewer): integrate credentials modal in main view"
```

---

## Task 8: Add Clear Credentials Button

**Files:**
- Modify: `resources/views/livewire/openai-responses-viewer.blade.php`

**Step 1: Add clear credentials button when session auth is active**

Near the "Session Auth Active" indicator, add a clear button:

```blade
@if(session('openai_session_token'))
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
        Session Auth Active
    </span>
    <button
        type="button"
        wire:click="clearCredentials"
        wire:confirm="Are you sure you want to clear the stored credentials?"
        class="ml-1 text-xs text-gray-500 hover:text-red-600"
        title="Clear stored credentials"
    >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
@endif
```

**Step 2: Commit**

```bash
git add resources/views/livewire/openai-responses-viewer.blade.php
git commit -m "feat(openai-viewer): add clear credentials button"
```

---

## Task 9: Add Feature Test for Credentials Flow

**Files:**
- Create: `tests/Feature/Livewire/OpenAIResponsesViewerCredentialsTest.php`

**Step 1: Create the test file**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\OpenAIResponsesViewer;
use App\Services\OpenAIService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class OpenAIResponsesViewerCredentialsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_shows_credentials_modal_on_401_session_error(): void
    {
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('getResponses')
            ->andThrow(new \Exception('HTTP request returned status code 401: { "error": { "message": "Your request to GET /v1/responses must be made with a session key'));

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIResponsesViewer::class)
            ->assertSet('showCredentialsModal', true)
            ->assertSet('needsCredentials', true)
            ->assertSee('OpenAI requires session authentication');
    }

    /** @test */
    public function it_saves_credentials_to_session(): void
    {
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('getResponsesWithSession')
            ->once()
            ->andReturn(['data' => []]);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIResponsesViewer::class)
            ->set('showCredentialsModal', true)
            ->set('sessionToken', 'sess-test123')
            ->set('organizationId', 'org-test456')
            ->set('projectId', 'proj_test789')
            ->call('saveCredentials')
            ->assertSet('showCredentialsModal', false);

        $this->assertEquals('sess-test123', session('openai_session_token'));
        $this->assertEquals('org-test456', session('openai_organization_id'));
        $this->assertEquals('proj_test789', session('openai_project_id'));
    }

    /** @test */
    public function it_requires_session_token(): void
    {
        Livewire::test(OpenAIResponsesViewer::class)
            ->set('showCredentialsModal', true)
            ->set('sessionToken', '')
            ->call('saveCredentials')
            ->assertSet('showCredentialsModal', true)
            ->assertSet('error', 'Session token is required.');
    }

    /** @test */
    public function it_clears_credentials(): void
    {
        session([
            'openai_session_token' => 'test-token',
            'openai_organization_id' => 'test-org',
            'openai_project_id' => 'test-proj',
        ]);

        Livewire::test(OpenAIResponsesViewer::class)
            ->call('clearCredentials')
            ->assertSet('sessionToken', '')
            ->assertSet('organizationId', '')
            ->assertSet('projectId', '');

        $this->assertNull(session('openai_session_token'));
    }

    /** @test */
    public function it_uses_session_credentials_when_available(): void
    {
        session([
            'openai_session_token' => 'sess-stored',
            'openai_organization_id' => 'org-stored',
            'openai_project_id' => 'proj_stored',
        ]);

        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('getResponsesWithSession')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($creds) {
                    return $creds['token'] === 'sess-stored'
                        && $creds['organization'] === 'org-stored'
                        && $creds['project'] === 'proj_stored';
                })
            )
            ->andReturn(['data' => []]);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIResponsesViewer::class);
    }
}
```

**Step 2: Run the tests**

```bash
php artisan test --filter=OpenAIResponsesViewerCredentialsTest
```

Expected: All 5 tests should pass.

**Step 3: Commit**

```bash
git add tests/Feature/Livewire/OpenAIResponsesViewerCredentialsTest.php
git commit -m "test(openai-viewer): add credentials flow tests"
```

---

## Task 10: Final Integration Test

**Step 1: Run full test suite**

```bash
php artisan test
```

**Step 2: Manual testing checklist**

1. Navigate to OpenAI Responses Viewer page
2. If you don't have valid API key configured, should see 401 error
3. Modal should appear automatically
4. Enter test credentials and click "Save & Retry"
5. Credentials should be stored (check "Session Auth Active" badge)
6. Click "Credentials" button to update credentials
7. Click X next to badge to clear credentials
8. Verify refresh still works with stored credentials

**Step 3: Final commit**

```bash
git add -A
git commit -m "feat(openai-viewer): complete session credentials modal feature"
```

---

## Summary

This implementation adds:
1. **Modal UI** - Clean Tailwind-styled modal with form inputs
2. **Session storage** - Laravel session-based credential persistence
3. **Auto-detection** - Automatically shows modal on 401 session key errors
4. **Manual access** - "Credentials" button for manual configuration
5. **Visual feedback** - "Session Auth Active" badge when using session auth
6. **Clear function** - Ability to remove stored credentials
7. **Service layer** - New methods in OpenAIService for session-based auth
8. **Tests** - Comprehensive Livewire feature tests
