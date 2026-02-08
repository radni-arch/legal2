# Failure Brief: GraphViewerTest

**Component:** `focused:GraphViewerTest`
**Generated:** 2026-01-07T03:47:03+00:00
**Consecutive Failures:** 3

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh GraphViewerTest
```

---

## Failing Tests

- Tests\Feature\GraphViewerTest > it

---

## Error Messages

```
      NunoMaduro\Collision\Exceptions\TestException::("Call to a member function get() on null")
      NunoMaduro\Collision\Exceptions\TestException::("Call to a member function get() on null")
      NunoMaduro\Collision\Exceptions\TestException::("Call to undefined method stdClass::get()")
```

---

## Stack Frames

```
app/Http/Livewire/GraphViewer.php:622
app/Http/Livewire/GraphViewer.php:630
tests/TestCase.php:18
```

---

## Suspected Files

- `app/Http/Livewire/GraphViewer.php`
- `tests/DuskTestCase.php`
- `tests/TestCase.php`
- `tests/Unit/Contracts/GraphLinkerContractTest.php`
- `tests/Unit/Contracts/GraphSyncServiceContractTest.php`

---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh GraphViewerTest`
2. **View full log:** `less test-logs/GraphViewerTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component GraphViewerTest`

