# Failure Brief: EpredmetWidgetTest

**Component:** `focused:EpredmetWidgetTest`
**Generated:** 2026-01-29T12:40:12+00:00
**Consecutive Failures:** 1

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh EpredmetWidgetTest
```

---

## Failing Tests

- Tests\Feature\Livewire\EpredmetWidgetTest > it

---

## Error Messages

```
  Failed asserting that null is not null.
  Failed asserting that null is not null.
  Failed asserting that null is not null.
  Failed asserting that null is not null.
```

---

## Stack Frames

```
tests/DuskTestCase.php:44
tests/Feature/Livewire/EpredmetWidgetTest.php:252
tests/Feature/Livewire/EpredmetWidgetTest.php:35
tests/Feature/Livewire/EpredmetWidgetTest.php:412
tests/Feature/Livewire/EpredmetWidgetTest.php:443
```

---

## Suspected Files

- `tests/DuskTestCase.php`
- `tests/Feature/Livewire/EpredmetWidgetTest.php`
- `tests/Unit/Contracts/GraphLinkerContractTest.php`
- `tests/Unit/Contracts/GraphSyncServiceContractTest.php`

---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh EpredmetWidgetTest`
2. **View full log:** `less test-logs/EpredmetWidgetTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component EpredmetWidgetTest`

