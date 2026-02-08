# Failure Brief: FeedbackDashboardTest

**Component:** `focused:FeedbackDashboardTest`
**Generated:** 2026-01-31T03:54:37+00:00
**Consecutive Failures:** 2

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh FeedbackDashboardTest
```

---

## Failing Tests

- Tests\Feature\Livewire\FeedbackDashboardTest > it

---

## Error Messages

```

```

---

## Stack Frames

```
tests/DuskTestCase.php:44
tests/Feature/Livewire/FeedbackDashboardTest.php:143
```

---

## Suspected Files

- `tests/DuskTestCase.php`
- `tests/Feature/Livewire/FeedbackDashboardTest.php`
- `tests/Unit/Contracts/GraphLinkerContractTest.php`
- `tests/Unit/Contracts/GraphSyncServiceContractTest.php`

---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh FeedbackDashboardTest`
2. **View full log:** `less test-logs/FeedbackDashboardTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component FeedbackDashboardTest`

