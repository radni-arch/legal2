# Failure Brief: LegalPlaygroundTest

**Component:** `focused:LegalPlaygroundTest`
**Generated:** 2026-01-27T14:26:40+00:00
**Consecutive Failures:** 1

---

## Rerun Command

```bash
./scripts/run-focused-tests.sh LegalPlaygroundTest
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

1. **Rerun test:** `./scripts/run-focused-tests.sh LegalPlaygroundTest`
2. **View full log:** `less test-logs/LegalPlaygroundTest-results.txt`
3. **Check metrics:** `php scripts/metrics-summary.php --component LegalPlaygroundTest`

