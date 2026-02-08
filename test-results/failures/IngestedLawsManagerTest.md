# Failure Brief: IngestedLawsManagerTest

**Component:** `focused:IngestedLawsManagerTest`
**Generated:** 2026-01-27T14:27:55+00:00
**Consecutive Failures:** 2

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh IngestedLawsManagerTest
```

---

## Failing Tests

- Tests\Feature\IngestedLawsManagerTest > it

---

## Error Messages

```

```

---

## Stack Frames

```
app/Http/Livewire/IngestedLawsManager.php:398
app/Models/Law.php:96
app/Services/Graph/GraphRagOrchestrator.php:100
storage/framework/views/97f662f03eff5b645ea4b786bb8505ba.php:1218
tests/DuskTestCase.php:44
tests/Feature/IngestedLawsManagerTest.php:738
tests/Feature/IngestedLawsManagerTest.php:760
tests/Feature/IngestedLawsManagerTest.php:796
```

---

## Suspected Files

- `app/Http/Livewire/IngestedLawsManager.php`
- `app/Models/Law.php`
- `app/Services/Graph/GraphRagOrchestrator.php`
- `tests/DuskTestCase.php`
- `tests/Feature/IngestedLawsManagerTest.php`
- `tests/Unit/Contracts/GraphLinkerContractTest.php`
- `tests/Unit/Contracts/GraphSyncServiceContractTest.php`

---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh IngestedLawsManagerTest`
2. **View full log:** `less test-logs/IngestedLawsManagerTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component IngestedLawsManagerTest`

