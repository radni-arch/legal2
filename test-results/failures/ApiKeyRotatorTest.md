# Failure Brief: ApiKeyRotatorTest

**Component:** `focused:ApiKeyRotatorTest`
**Generated:** 2026-01-12T01:05:48+00:00
**Consecutive Failures:** 7

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorTest
```

---

## Failing Tests

- Tests\Feature\ApiKeyRotatorTest > get
- Tests\Feature\ApiKeyRotatorTest > key

---

## Error Messages

```
  Failed asserting that App\Models\ApiKey Object #71257 (
```

---

## Stack Frames

```
database/migrations/2025_11_11_040000_create_emerging_entities_table.php:27
tests/Feature/ApiKeyRotatorTest.php:164
tests/TestCase.php:18
```

---

## Suspected Files

- `tests/Feature/ApiKeyRotatorTest.php`
- `tests/TestCase.php`
- `tests/Unit/Contracts/GraphLinkerContractTest.php`
- `tests/Unit/Contracts/GraphSyncServiceContractTest.php`

---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh ApiKeyRotatorTest`
2. **View full log:** `less test-logs/ApiKeyRotatorTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component ApiKeyRotatorTest`

