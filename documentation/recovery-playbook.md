# Recovery Playbook

Copy/paste commands for common "local is broken" scenarios.

---

## Quick Diagnosis

### Check Overall Status

```bash
cat test-results/setup-status.json | jq '.'
```

### Check Setup Logs

```bash
tail -100 /tmp/setup-all-*.log 2>/dev/null | grep -E '\[(ERROR|WARN|FAIL)\]'
```

### Run Health Check

```bash
./scripts/check-setup.sh
```

---

## Database Issues

### PostgreSQL Connection Failed

**Symptoms:** `db: fail`, "Connection refused", SQLSTATE errors

```bash
# Check if PostgreSQL is running
service postgresql status || pg_isready

# Start PostgreSQL
service postgresql start

# Verify connection
PGPASSWORD=password psql -h 127.0.0.1 -U postgres -c "SELECT 1;"
```

### Migrations Failed

**Symptoms:** `migrations: fail`, table not found errors

```bash
# Run migrations
php artisan migrate --force

# If migrations are stuck, reset (WARNING: destroys data)
php artisan migrate:fresh --force
```

### Seed Data Missing

**Symptoms:** `seed: fail`, empty tables, "2/3 key tables found"

```bash
# Run seeders
php artisan db:seed --force

# Focused seeding for specific domain
./scripts/seed-focused-fixtures.sh --domain search_services

# Full reseed (WARNING: destroys existing data)
php artisan migrate:fresh --seed --force
```

---

## Neo4j Issues

### Neo4j Not Running

**Symptoms:** `neo4j: fail`, connection refused on port 7687

```bash
# Check status
pgrep -f neo4j || echo "Not running"

# Start Neo4j
neo4j start || service neo4j start

# Wait for startup (30-60 seconds)
sleep 30

# Verify
cypher-shell -a "neo4j://localhost:7687" -u neo4j -p password "RETURN 1;"
```

### Neo4j Password Change Required

**Symptoms:** "credentials were valid but must be changed"

```bash
# Change password from default to configured
cypher-shell -a "neo4j://localhost:7687" -u neo4j -p neo4j -d system \
  "ALTER CURRENT USER SET PASSWORD FROM 'neo4j' TO 'password';"

# Verify new password
cypher-shell -a "neo4j://localhost:7687" -u neo4j -p password "RETURN 1;"
```

### Neo4j Authentication Failed

**Symptoms:** "authentication failure", wrong password

```bash
# Check configured password
grep NEO4J_PASSWORD .env

# If password is correct but failing, may need reset
# (Requires stopping Neo4j and editing neo4j.conf)
```

---

## Composer/PHP Issues

### Composer Dependencies Missing

**Symptoms:** `composer: fail`, class not found errors

```bash
# Install dependencies
composer install --no-interaction

# If lock file issues
rm composer.lock
composer install --no-interaction

# Clear autoload
composer dump-autoload
```

### PHP Configuration Issues

**Symptoms:** Extension errors, memory issues

```bash
# Check PHP version
php -v

# Check loaded extensions
php -m | grep -E 'pdo|pgsql|redis|gd'

# Increase memory limit for single command
php -d memory_limit=512M artisan test
```

---

## Laravel Cache Issues

### Application Cache Corrupted

**Symptoms:** Strange behavior, old config being used

```bash
# Clear all caches
php artisan optimize:clear

# Or individually
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
```

### Compiled Files Corrupted

**Symptoms:** "Failed to open stream", bootstrap errors

```bash
# Clear compiled files
php artisan clear-compiled

# Regenerate autoloader
composer dump-autoload -o
```

---

## Frontend Issues

### Node Modules Missing

**Symptoms:** Frontend build fails, asset compilation errors

```bash
# Install dependencies
npm install

# If node_modules is corrupted
rm -rf node_modules package-lock.json
npm install
```

### Build Failed

**Symptoms:** Vite errors, asset not found

```bash
# Development build
npm run dev

# Production build
npm run build

# Clear Vite cache
rm -rf node_modules/.vite
npm run dev
```

---

## Test Infrastructure Issues

### Dusk/Chrome Issues

**Symptoms:** Browser tests fail, Chrome not found

```bash
# Install Chrome driver
php artisan dusk:chrome-driver --detect

# Check Chrome binary
which google-chrome || which chromium-browser

# Run Dusk with verbose output
php artisan dusk --env=dusk.local -vvv
```

### Playwright Issues

**Symptoms:** Playwright tests fail, browsers not installed

```bash
# Install Playwright browsers
npx playwright install

# Install system dependencies
npx playwright install-deps

# Test installation
npx playwright test --version
```

### Test Database Issues

**Symptoms:** Tests fail with DB errors, stale data

```bash
# Create fresh test database
php artisan migrate:fresh --env=testing --force

# Clear test cache
php artisan cache:clear --env=testing
```

---

## Service Restart

### Full Service Restart

```bash
# Stop all services
service postgresql stop
neo4j stop

# Start all services
service postgresql start
neo4j start

# Wait for services
sleep 10

# Verify
PGPASSWORD=password psql -h 127.0.0.1 -U postgres -c "SELECT 1;"
cypher-shell -a "neo4j://localhost:7687" -u neo4j -p password "RETURN 1;"
```

### Laravel Development Server

```bash
# Stop existing server
pkill -f "artisan serve" || true

# Start server
php artisan serve --host=0.0.0.0 --port=8000 &

# Verify
curl -s http://localhost:8000/health || curl -s http://localhost:8000
```

---

## Full Reset (Nuclear Option)

**WARNING:** This destroys all local data. Use only when other options fail.

```bash
# Stop services
service postgresql stop 2>/dev/null || true
neo4j stop 2>/dev/null || true

# Clear all caches
php artisan optimize:clear
rm -rf node_modules/.vite
rm -rf bootstrap/cache/*.php

# Reinstall dependencies
composer install --no-interaction
npm install

# Start services
service postgresql start
neo4j start
sleep 30

# Fresh database
php artisan migrate:fresh --seed --force

# Rebuild frontend
npm run build

# Verify
./scripts/check-setup.sh
```

---

## Diagnostic Commands Summary

| Check | Command |
|-------|---------|
| Setup status | `cat test-results/setup-status.json \| jq '.'` |
| Setup logs | `tail -100 /tmp/setup-all-*.log` |
| Health check | `./scripts/check-setup.sh` |
| PostgreSQL | `PGPASSWORD=password psql -h 127.0.0.1 -U postgres -c "SELECT 1;"` |
| Neo4j | `cypher-shell -a "neo4j://localhost:7687" -u neo4j -p password "RETURN 1;"` |
| PHP | `php artisan --version` |
| Composer | `composer diagnose` |
| Node | `npm --version && node --version` |
| Chrome | `google-chrome --version` |

---

## Session End Checklist

Before ending a session, complete these verification steps:

### 1. Validate Agent Logs (if agents were dispatched)

```bash
# Check if agents logged their work
./scripts/validate-agent-logs.sh

# If you dispatched N agents, expect N logs
./scripts/validate-agent-logs.sh --expect 3 --strict

# Check logs from last hour
./scripts/validate-agent-logs.sh --since "1 hour"
```

**If logs missing:** Flag in session notes that agent work is "unverified"

### 2. Run Verification

```bash
# Run focused tests for modified components
./scripts/run-focused-tests.sh <TestClass>

# Run quality gates
./scripts/run-quality-gates.sh
```

### 3. Update Session State

```bash
# Append session notes
./scripts/append-session-notes.sh "What was done"

# Or manually update
# Edit .claude/session-state.md
```

### 4. Commit Artifacts

```bash
# Stage all changes including artifacts
git add .

# Commit with descriptive message
git commit -m "Category: What was done"

# Push to remote
git push -u origin <branch>
```

### Session End Summary

| Step | Command | Purpose |
|------|---------|---------|
| Validate logs | `./scripts/validate-agent-logs.sh` | Verify agent logging |
| Run tests | `./scripts/run-focused-tests.sh X` | Verify changes work |
| Quality gates | `./scripts/run-quality-gates.sh` | Check code quality |
| Update notes | Edit `.claude/session-state.md` | Handoff notes |
| Commit | `git add . && git commit` | Save work |
| Push | `git push` | Sync to remote |

---

## Related Documentation

- `documentation/automation-map.md` - System overview
- `.claude/docs/AGENT_AUTOLOOP_ETIQUETTE.md` - Agent rules
- `.claude/commands/resume.md` - Resume flow
- `.claude/commands/check-setup.md` - Health check command
