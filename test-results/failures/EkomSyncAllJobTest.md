# Failure Brief: EkomSyncAllJobTest

**Component:** `focused:EkomSyncAllJobTest`
**Generated:** 2026-01-31T00:23:19+00:00
**Consecutive Failures:** 1

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh EkomSyncAllJobTest
```

---

## Failing Tests



---

## Error Messages

```
PHP Fatal error:  Uncaught Error: Failed opening required '/home/user/ai-legal-war-machine/vendor/autoload.php' (include_path='.:/usr/share/php') in /home/user/ai-legal-war-machine/artisan:10
```

---

## Stack Frames

```

```

---

## Suspected Files



---

## Quick Actions

1. **Rerun test:** `./scripts/run-focused-tests.sh EkomSyncAllJobTest`
2. **View full log:** `less test-logs/EkomSyncAllJobTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component EkomSyncAllJobTest`

