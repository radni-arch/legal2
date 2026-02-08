# Failure Brief: ApiKeyManageCommandTest

**Component:** `focused:ApiKeyManageCommandTest`
**Generated:** 2026-01-12T00:37:57+00:00
**Consecutive Failures:** 2

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh ApiKeyManageCommandTest
```

---

## Failing Tests

- Tests\Feature\Console\ApiKeyManageCommandTest > a
- Tests\Feature\Console\ApiKeyManageCommandTest > add
- Tests\Feature\Console\ApiKeyManageCommandTest > l

---

## Error Messages

```
  Failed asserting that null is not null.
```

---

## Stack Frames

```
app/Console/Commands/ApiKeyManage.php:27
app/Console/Commands/ApiKeyManage.php:87
database/migrations/0001_01_01_000000_create_users_table.php:14
database/migrations/0001_01_01_000001_create_cache_table.php:14
database/migrations/2024_03_10_000000_create_agent_sessions_table.php:16
tests/TestCase.php:18
```

---

## Suspected Files

- `app/Console/Commands/ApiKeyManage.php`
- `tests/Feature/Console/ApiKeyManageCommandTest.php`
- `tests/TestCase.php`
- `tests/Unit/Contracts/GraphLinkerContractTest.php`
- `tests/Unit/Contracts/GraphSyncServiceContractTest.php`

---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh ApiKeyManageCommandTest`
2. **View full log:** `less test-logs/ApiKeyManageCommandTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component ApiKeyManageCommandTest`

