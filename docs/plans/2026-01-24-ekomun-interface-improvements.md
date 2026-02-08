# E-Komunikacije Interface Improvements Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix critical security issues and improve code quality in the EKOM interface implementation based on code review findings.

**Architecture:** Address security vulnerabilities (rate limiting, input validation), improve error handling consistency, centralize status options to model constants, and implement proper file cleanup. All changes follow TDD with tests first.

**Tech Stack:** Laravel 11, Livewire 3, PHPUnit

---

## Overview

| Priority | Issue | Task |
|----------|-------|------|
| CRITICAL | Missing rate limiting on API | Task 1 |
| CRITICAL | Missing input validation in API | Task 2 |
| IMPORTANT | File cleanup after upload | Task 3 |
| IMPORTANT | Inconsistent error handling | Task 4 |
| IMPORTANT | Hardcoded status options | Task 5 |
| IMPORTANT | Cap per_page parameter | Task 6 |

---

## Task 1: Add Rate Limiting to EKOM API Routes

**Files:**
- Modify: `routes/api.php:694-707`
- Test: `tests/Feature/Api/EkomApiTest.php`

**Step 1: Write failing test for rate limiting**

Add to `tests/Feature/Api/EkomApiTest.php`:

```php
public function test_api_has_rate_limiting(): void
{
    $user = User::factory()->create();

    // Make 61 requests (should exceed 60/minute limit)
    for ($i = 0; $i < 61; $i++) {
        $response = $this->withApiAuth($user)->getJson('/api/ekom/predmeti');

        if ($i < 60) {
            $response->assertStatus(200);
        } else {
            $response->assertStatus(429); // Too Many Requests
        }
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh EkomApiTest::test_api_has_rate_limiting`
Expected: FAIL (currently no rate limiting, all 61 requests return 200)

**Step 3: Add rate limiting middleware**

In `routes/api.php`, modify the EKOM route group (around line 694):

```php
// E-Komunikacije API Routes
Route::prefix('ekom')->middleware(['api.token', 'throttle:60,1'])->name('api.ekom.')->group(function () {
    Route::get('/predmeti', [EkomController::class, 'listPredmeti'])->name('predmeti.index');
    Route::get('/predmeti/{remoteId}', [EkomController::class, 'showPredmet'])->name('predmeti.show');
    Route::get('/podnesci', [EkomController::class, 'listPodnesci'])->name('podnesci.index');
    Route::get('/otpravci', [EkomController::class, 'listOtpravci'])->name('otpravci.index');
    Route::post('/sync', [EkomController::class, 'triggerSync'])->name('sync');
});
```

**Step 4: Run test to verify it passes**

Run: `./scripts/run-focused-tests.sh EkomApiTest::test_api_has_rate_limiting`
Expected: PASS

**Step 5: Run all EKOM API tests**

Run: `./scripts/run-focused-tests.sh EkomApiTest`
Expected: All tests PASS

**Step 6: Commit**

```bash
git add routes/api.php tests/Feature/Api/EkomApiTest.php
git commit -m "$(cat <<'EOF'
security(ekom): Add rate limiting to API routes

- Add throttle:60,1 middleware to EKOM API routes
- Prevents API abuse and protects external EKOM service
- Add test verifying rate limiting is enforced
EOF
)"
```

---

## Task 2: Add Input Validation to EkomController

**Files:**
- Create: `app/Http/Requests/Api/ListPredmetiRequest.php`
- Create: `app/Http/Requests/Api/ListPodnesciRequest.php`
- Create: `app/Http/Requests/Api/ListOtpravciRequest.php`
- Modify: `app/Http/Controllers/Api/EkomController.php`
- Test: `tests/Feature/Api/EkomApiTest.php`

**Step 1: Write failing tests for validation**

Add to `tests/Feature/Api/EkomApiTest.php`:

```php
public function test_predmeti_validates_per_page_max(): void
{
    $this->withApiAuth(User::factory()->create())
        ->getJson('/api/ekom/predmeti?per_page=500')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['per_page']);
}

public function test_predmeti_validates_status_values(): void
{
    $this->withApiAuth(User::factory()->create())
        ->getJson('/api/ekom/predmeti?status=invalid_status')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
}

public function test_predmeti_validates_search_max_length(): void
{
    $longSearch = str_repeat('a', 300);

    $this->withApiAuth(User::factory()->create())
        ->getJson('/api/ekom/predmeti?search=' . $longSearch)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['search']);
}

public function test_otpravci_validates_pending_is_boolean(): void
{
    $this->withApiAuth(User::factory()->create())
        ->getJson('/api/ekom/otpravci?pending=invalid')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['pending']);
}
```

**Step 2: Run tests to verify they fail**

Run: `./scripts/run-focused-tests.sh EkomApiTest`
Expected: New validation tests FAIL (currently no validation)

**Step 3: Create Form Request classes**

Create `app/Http/Requests/Api/ListPredmetiRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use App\Models\EkomPredmet;
use Illuminate\Foundation\Http\FormRequest;

class ListPredmetiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:' . implode(',', array_keys(EkomPredmet::STATUSES)),
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
```

Create `app/Http/Requests/Api/ListPodnesciRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use App\Models\EkomPodnesak;
use Illuminate\Foundation\Http\FormRequest;

class ListPodnesciRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:' . implode(',', array_keys(EkomPodnesak::STATUSES)),
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
```

Create `app/Http/Requests/Api/ListOtpravciRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use App\Models\EkomOtpravak;
use Illuminate\Foundation\Http\FormRequest;

class ListOtpravciRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:' . implode(',', array_keys(EkomOtpravak::STATUSES)),
            'pending' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert string 'true'/'false' to boolean
        if ($this->has('pending')) {
            $this->merge([
                'pending' => filter_var($this->pending, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
```

**Step 4: Update EkomController to use Form Requests**

Modify `app/Http/Controllers/Api/EkomController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListOtpravciRequest;
use App\Http\Requests\Api\ListPodnesciRequest;
use App\Http\Requests\Api\ListPredmetiRequest;
use App\Jobs\EkomSyncAllJob;
use App\Models\EkomOtpravak;
use App\Models\EkomPodnesak;
use App\Models\EkomPredmet;
use Illuminate\Http\JsonResponse;

class EkomController extends Controller
{
    public function listPredmeti(ListPredmetiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $predmeti = EkomPredmet::query()
            ->when($validated['search'] ?? null, fn ($q, $s) => $q->where('oznaka', 'like', "%{$s}%"))
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('last_synced_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json($predmeti);
    }

    public function showPredmet(string $remoteId): JsonResponse
    {
        $predmet = EkomPredmet::where('remote_id', $remoteId)->firstOrFail();

        return response()->json(['data' => $predmet]);
    }

    public function listPodnesci(ListPodnesciRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $podnesci = EkomPodnesak::query()
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('last_synced_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json($podnesci);
    }

    public function listOtpravci(ListOtpravciRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $otpravci = EkomOtpravak::query()
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($validated['pending'] ?? false, fn ($q) => $q->whereNull('vrijeme_potvrde_primitka'))
            ->orderByDesc('last_synced_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json($otpravci);
    }

    public function triggerSync(): JsonResponse
    {
        EkomSyncAllJob::dispatch();

        return response()->json(['message' => 'Sync job dispatched'], 202);
    }
}
```

**Step 5: Run tests to verify they pass**

Run: `./scripts/run-focused-tests.sh EkomApiTest`
Expected: All tests PASS

**Step 6: Commit**

```bash
git add app/Http/Requests/Api/List*.php app/Http/Controllers/Api/EkomController.php tests/Feature/Api/EkomApiTest.php
git commit -m "$(cat <<'EOF'
security(ekom): Add input validation to API controller

- Create Form Request classes for each list endpoint
- Validate per_page max:100 to prevent memory exhaustion
- Validate status against allowed model values
- Validate search max length
- Validate pending as boolean
EOF
)"
```

---

## Task 3: Add Status Constants to Models

**Files:**
- Modify: `app/Models/EkomPredmet.php`
- Modify: `app/Models/EkomPodnesak.php`
- Modify: `app/Models/EkomOtpravak.php`
- Test: `tests/Unit/Models/EkomPredmetTest.php`

**Step 1: Write failing test**

Add to `tests/Unit/Models/EkomPredmetTest.php`:

```php
public function test_has_statuses_constant(): void
{
    $this->assertIsArray(EkomPredmet::STATUSES);
    $this->assertArrayHasKey('otvoren', EkomPredmet::STATUSES);
    $this->assertArrayHasKey('aktivan', EkomPredmet::STATUSES);
    $this->assertArrayHasKey('u_tijeku', EkomPredmet::STATUSES);
    $this->assertArrayHasKey('zatvoren', EkomPredmet::STATUSES);
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh EkomPredmetTest::test_has_statuses_constant`
Expected: FAIL (constant not defined)

**Step 3: Add constants to models**

Add to `app/Models/EkomPredmet.php`:

```php
/**
 * Status options for predmeti
 */
public const STATUSES = [
    'otvoren' => 'Otvoren',
    'aktivan' => 'Aktivan',
    'u_tijeku' => 'U tijeku',
    'zatvoren' => 'Zatvoren',
];
```

Add to `app/Models/EkomPodnesak.php`:

```php
/**
 * Status options for podnesci
 */
public const STATUSES = [
    'kreiran' => 'Kreiran',
    'poslan' => 'Poslan',
    'zaprimljen' => 'Zaprimljen',
    'u_obradi' => 'U obradi',
];
```

Add to `app/Models/EkomOtpravak.php`:

```php
/**
 * Status options for otpravci
 */
public const STATUSES = [
    'kreiran' => 'Kreiran',
    'poslan' => 'Poslan',
    'dostavljen' => 'Dostavljen',
    'istekao_rok' => 'Istekao rok',
];
```

**Step 4: Run tests to verify they pass**

Run: `./scripts/run-focused-tests.sh EkomPredmetTest`
Expected: PASS

**Step 5: Update Livewire components to use constants**

Update `app/Http/Livewire/EkomPredmetiList.php:61-67`:

```php
public function getStatusOptionsProperty(): array
{
    return array_merge(['' => 'All Statuses'], EkomPredmet::STATUSES);
}
```

Update similarly for `EkomPodnesciList.php` and `EkomOtpravciList.php`.

**Step 6: Run all EKOM tests**

Run: `./scripts/run-focused-tests.sh Ekom`
Expected: All tests PASS

**Step 7: Commit**

```bash
git add app/Models/Ekom*.php app/Http/Livewire/Ekom*.php tests/Unit/Models/EkomPredmetTest.php
git commit -m "$(cat <<'EOF'
refactor(ekom): Centralize status options to model constants

- Add STATUSES constant to EkomPredmet, EkomPodnesak, EkomOtpravak
- Update Livewire components to use model constants
- Remove hardcoded status arrays from components
- Enables validation and UI to share same source of truth
EOF
)"
```

---

## Task 4: Add Error Handling to toggleDnd in EkomPredmetiList

**Files:**
- Modify: `app/Http/Livewire/EkomPredmetiList.php:175-188`
- Test: `tests/Feature/Livewire/EkomPredmetiListTest.php`

**Step 1: Write failing test**

Add to `tests/Feature/Livewire/EkomPredmetiListTest.php`:

```php
public function test_toggle_dnd_handles_service_error(): void
{
    $user = User::factory()->create();
    $predmet = EkomPredmet::factory()->create(['do_not_disturb' => false]);

    $this->mock(\App\Contracts\External\EkomServiceInterface::class)
        ->shouldReceive('turnOnDndPredmet')
        ->andThrow(new \Exception('API Error'));

    Livewire::actingAs($user)
        ->test(EkomPredmetiList::class)
        ->call('toggleDnd', $predmet->id)
        ->assertDispatched('error');
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh EkomPredmetiListTest::test_toggle_dnd_handles_service_error`
Expected: FAIL (exception not caught)

**Step 3: Add try-catch to toggleDnd**

Update `app/Http/Livewire/EkomPredmetiList.php`:

```php
public function toggleDnd(int $predmetId): void
{
    try {
        $predmet = EkomPredmet::findOrFail($predmetId);

        if ($predmet->do_not_disturb) {
            $this->ekomService->turnOffDndPredmet($predmetId);
            $predmet->update(['do_not_disturb' => false]);
            $this->dispatch('success', message: 'DND disabled for ' . $predmet->oznaka);
        } else {
            $this->ekomService->turnOnDndPredmet($predmetId);
            $predmet->update(['do_not_disturb' => true]);
            $this->dispatch('success', message: 'DND enabled for ' . $predmet->oznaka);
        }
    } catch (\Throwable $e) {
        $this->dispatch('error', message: 'Failed to toggle DND: ' . $e->getMessage());
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `./scripts/run-focused-tests.sh EkomPredmetiListTest`
Expected: All tests PASS

**Step 5: Commit**

```bash
git add app/Http/Livewire/EkomPredmetiList.php tests/Feature/Livewire/EkomPredmetiListTest.php
git commit -m "$(cat <<'EOF'
fix(ekom): Add error handling to toggleDnd in EkomPredmetiList

- Wrap service call in try-catch block
- Dispatch error event on failure
- Matches error handling pattern in EkomPredmetDetail
EOF
)"
```

---

## Task 5: Add File Cleanup to EkomPodnesakCreate

**Files:**
- Modify: `app/Http/Livewire/EkomPodnesakCreate.php:103-114`
- Test: `tests/Feature/Livewire/EkomPodnesakCreateTest.php`

**Step 1: Write failing test**

Add to `tests/Feature/Livewire/EkomPodnesakCreateTest.php`:

```php
public function test_uploaded_files_are_cleaned_up_after_success(): void
{
    Storage::fake('local');
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 100);

    $this->mock(\App\Contracts\External\EkomServiceInterface::class)
        ->shouldReceive('createPodnesak')
        ->andReturn(['id' => 12345, 'status' => 'kreiran']);

    Livewire::actingAs($user)
        ->test(EkomPodnesakCreate::class)
        ->set('predmetId', '123456')
        ->set('vrstaPodneskaId', '1')
        ->set('naziv', 'Test Submission')
        ->set('attachments', [$file])
        ->call('submit');

    // Verify file was cleaned up
    Storage::disk('local')->assertMissing('ekom-uploads/' . $file->hashName());
}

public function test_uploaded_files_are_cleaned_up_after_failure(): void
{
    Storage::fake('local');
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 100);

    $this->mock(\App\Contracts\External\EkomServiceInterface::class)
        ->shouldReceive('createPodnesak')
        ->andThrow(new \Exception('API Error'));

    Livewire::actingAs($user)
        ->test(EkomPodnesakCreate::class)
        ->set('predmetId', '123456')
        ->set('vrstaPodneskaId', '1')
        ->set('naziv', 'Test Submission')
        ->set('attachments', [$file])
        ->call('submit');

    // Verify file was cleaned up even on failure
    Storage::disk('local')->assertMissing('ekom-uploads/' . $file->hashName());
}
```

**Step 2: Run tests to verify they fail**

Run: `./scripts/run-focused-tests.sh EkomPodnesakCreateTest::test_uploaded_files_are_cleaned_up`
Expected: FAIL (files not deleted)

**Step 3: Add file cleanup with finally block**

Update `app/Http/Livewire/EkomPodnesakCreate.php`:

```php
public function submit(): void
{
    $this->validate();

    $filePaths = [];

    try {
        $service = app(EkomServiceInterface::class);

        // Store uploaded files temporarily
        foreach ($this->attachments as $file) {
            $filePaths[] = $file->store('ekom-uploads');
        }

        $result = $service->createPodnesak([
            'predmetId' => $this->predmetId,
            'vrstaPodneskaId' => $this->vrstaPodneskaId,
            'naziv' => $this->naziv,
            'opis' => $this->opis,
        ], $filePaths);

        $this->successMessage = 'Submission created successfully. ID: ' . ($result['id'] ?? 'unknown');
        $this->dispatch('success', message: $this->successMessage);
        $this->reset(['predmetId', 'vrstaPodneskaId', 'naziv', 'opis', 'attachments']);

    } catch (\Throwable $e) {
        $this->errorMessage = 'Failed to create submission: ' . $e->getMessage();
        $this->dispatch('error', message: $this->errorMessage);
    } finally {
        // Clean up temporary files regardless of success/failure
        foreach ($filePaths as $path) {
            Storage::delete($path);
        }
    }
}
```

**Step 4: Add Storage import**

Add to top of file:
```php
use Illuminate\Support\Facades\Storage;
```

**Step 5: Run tests to verify they pass**

Run: `./scripts/run-focused-tests.sh EkomPodnesakCreateTest`
Expected: All tests PASS

**Step 6: Commit**

```bash
git add app/Http/Livewire/EkomPodnesakCreate.php tests/Feature/Livewire/EkomPodnesakCreateTest.php
git commit -m "$(cat <<'EOF'
fix(ekom): Clean up uploaded files after submission

- Add finally block to delete temporary files
- Files cleaned up on both success and failure
- Prevents disk space accumulation from uploads
EOF
)"
```

---

## Task 6: Final Verification and Push

**Step 1: Run all EKOM tests**

Run: `./scripts/run-focused-tests.sh Ekom`
Expected: All tests PASS

**Step 2: Run full test suite to check for regressions**

Run: `php artisan test --filter=Ekom`
Expected: All EKOM tests PASS

**Step 3: Commit any remaining changes**

```bash
git status
git add -A
git commit -m "Chore: Final improvements and cleanup"
```

**Step 4: Push to remote**

```bash
git push -u origin claude/ekomun-api-integration-N1L4w
```

---

## Summary

| Task | Issue Fixed | Priority |
|------|-------------|----------|
| 1 | Rate limiting on API routes | CRITICAL |
| 2 | Input validation in API controller | CRITICAL |
| 3 | Status constants in models | IMPORTANT |
| 4 | Error handling in toggleDnd | IMPORTANT |
| 5 | File cleanup after upload | IMPORTANT |
| 6 | Final verification | - |

**Total: 6 tasks, ~30 steps**
