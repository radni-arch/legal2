# Failure Brief: GraphDatabaseHealthTest

**Component:** `focused:GraphDatabaseHealthTest`
**Generated:** 2026-01-21T12:59:18+00:00
**Consecutive Failures:** 1

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh GraphDatabaseHealthTest
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

1. **Rerun test:** `./scripts/run-focused-tests.sh GraphDatabaseHealthTest`
2. **View full log:** `less test-logs/GraphDatabaseHealthTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component GraphDatabaseHealthTest`

