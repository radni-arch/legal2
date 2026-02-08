# Failure Brief: LegalPrincipleGraphSyncServiceTest__it_reuses_existing_principle_node_for_same_pattern

**Component:** `focused:LegalPrincipleGraphSyncServiceTest__it_reuses_existing_principle_node_for_same_pattern`
**Generated:** 2026-01-07T11:52:35+00:00
**Consecutive Failures:** 1

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh LegalPrincipleGraphSyncServiceTest__it_reuses_existing_principle_node_for_same_pattern
```

---

## Failing Tests

- Tests\Unit\Services\Graph\LegalPrincipleGraphSyncServiceTest > it

---

## Error Messages

```
Failed asserting that two strings are equal.
```

---

## Stack Frames

```
tests/Unit/Services/Graph/LegalPrincipleGraphSyncServiceTest.php:409
```

---

## Suspected Files

- `tests/Unit/Contracts/GraphLinkerContractTest.php`
- `tests/Unit/Contracts/GraphSyncServiceContractTest.php`
- `tests/Unit/Services/Graph/LegalPrincipleGraphSyncServiceTest.php`

---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh LegalPrincipleGraphSyncServiceTest__it_reuses_existing_principle_node_for_same_pattern`
2. **View full log:** `less test-logs/LegalPrincipleGraphSyncServiceTest__it_reuses_existing_principle_node_for_same_pattern-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component LegalPrincipleGraphSyncServiceTest__it_reuses_existing_principle_node_for_same_pattern`

