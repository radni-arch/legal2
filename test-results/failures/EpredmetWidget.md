# Failure Brief: EpredmetWidget

**Component:** `focused:EpredmetWidget`
**Generated:** 2026-01-20T02:02:24+00:00
**Consecutive Failures:** 2

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh EpredmetWidget
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

1. **Rerun test:** `./scripts/run-focused-tests.sh EpredmetWidget`
2. **View full log:** `less test-logs/EpredmetWidget-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component EpredmetWidget`

