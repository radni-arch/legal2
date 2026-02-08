# Failure Brief: ApiKeyValidateCommandTest

**Component:** `focused:ApiKeyValidateCommandTest`
**Generated:** 2026-01-12T00:37:49+00:00
**Consecutive Failures:** 1

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh ApiKeyValidateCommandTest
```

---

## Failing Tests



---

## Error Messages

```

```

---

## Stack Frames

```
database/migrations/0001_01_01_000000_create_users_table.php:14
database/migrations/0001_01_01_000002_create_jobs_table.php:14
database/migrations/2025_01_12_000001_create_api_keys_table.php:11
tests/TestCase.php:18
```

---

## Suspected Files

- `tests/TestCase.php`
- `tests/Unit/Contracts/GraphLinkerContractTest.php`
- `tests/Unit/Contracts/GraphSyncServiceContractTest.php`

---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh ApiKeyValidateCommandTest`
2. **View full log:** `less test-logs/ApiKeyValidateCommandTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component ApiKeyValidateCommandTest`

