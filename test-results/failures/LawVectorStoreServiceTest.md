# Failure Brief: LawVectorStoreServiceTest

**Component:** `focused:LawVectorStoreServiceTest`
**Generated:** 2026-01-21T14:10:12+00:00
**Consecutive Failures:** 1

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh LawVectorStoreServiceTest
```

---

## Failing Tests

- Tests\Unit\Services\LawVectorStoreServiceTest > it

---

## Error Messages

```

```

---

## Stack Frames

```
/home/user/ai-legal-war-machine/tests/Unit/Services/LawVectorStoreServiceTest.php on line 68
app/Services/LawVectorStoreService.php:16
tests/Unit/Services/LawVectorStoreServiceTest.php:68
```

---

## Suspected Files

- `app/Services/LawVectorStoreService.php`
- `tests/Unit/Contracts/GraphLinkerContractTest.php`
- `tests/Unit/Contracts/GraphSyncServiceContractTest.php`
- `tests/Unit/Services/LawVectorStoreServiceTest.php`

---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh LawVectorStoreServiceTest`
2. **View full log:** `less test-logs/LawVectorStoreServiceTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component LawVectorStoreServiceTest`

