# Failure Brief: CollaborationDashboardTest

**Component:** `focused:CollaborationDashboardTest`
**Generated:** 2026-01-31T03:56:05+00:00
**Consecutive Failures:** 1

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh CollaborationDashboardTest
```

---

## Failing Tests

- Tests\Feature\CollaborationDashboardTest > it
- Tests\Feature\Livewire\CollaborationDashboardTest > view

---

## Error Messages

```
  Failed asserting that the value at [collaborations] fulfills the expectations defined by the closure.
Failed asserting that false is true.
  Failed asserting that the value at [collaborations] fulfills the expectations defined by the closure.
Failed asserting that false is true.
  Failed asserting that the value at [collaborations] fulfills the expectations defined by the closure.
Failed asserting that false is true.
  Failed asserting that the value at [collaborations] fulfills the expectations defined by the closure.
Failed asserting that false is true.
  Failed asserting that null is not null.
```

---

## Stack Frames

```
tests/DuskTestCase.php:44
tests/Feature/CollaborationDashboardTest.php:551
tests/Feature/CollaborationDashboardTest.php:582
tests/Feature/CollaborationDashboardTest.php:610
tests/Feature/CollaborationDashboardTest.php:641
tests/Feature/Livewire/CollaborationDashboardTest.php:127
tests/Feature/Livewire/CollaborationDashboardTest.php:202
tests/Feature/Livewire/CollaborationDashboardTest.php:275
```

---

## Suspected Files

- `tests/DuskTestCase.php`
- `tests/Feature/CollaborationDashboardTest.php`
- `tests/Feature/Livewire/CollaborationDashboardTest.php`
- `tests/Unit/Contracts/GraphLinkerContractTest.php`
- `tests/Unit/Contracts/GraphSyncServiceContractTest.php`

---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh CollaborationDashboardTest`
2. **View full log:** `less test-logs/CollaborationDashboardTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component CollaborationDashboardTest`

