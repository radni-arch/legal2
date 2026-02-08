---
description: Check environment setup status (non-destructive health check)
---

Run the setup health check script and report results to the user:

```bash
./scripts/check-setup.sh
```

This script performs a non-destructive health check:
- Reads last setup status from `test-results/setup-status.json`
- Live-checks: PostgreSQL connectivity (SELECT 1), migrations, Neo4j, Composer
- Shows recent log output from `/tmp/setup-all-hook.log`
- Returns exit code 0 if healthy, 1 if unhealthy

After running, summarize the results for the user:
- If HEALTHY: confirm environment is ready
- If UNHEALTHY: highlight which subsystem(s) failed and suggest next steps
