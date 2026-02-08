# TDD Dashboard - Local Metrics Viewer

No-infrastructure way to view TDD progress, trends, and failures using `jq` recipes.

**Prerequisites:** `jq` installed (`apt install jq` or `brew install jq`)

---

## Quick Status

### Current Queue Status

```bash
# Queue breakdown by status
jq -r '
  .components | to_entries |
  group_by(.value.status) |
  map({status: .[0].value.status, count: length}) |
  sort_by(.status) |
  .[] | "\(.status): \(.count)"
' test-results/tdd-test-queue.json
```

### Completion Percentage

```bash
# Calculate completion percentage
jq -r '
  .components | to_entries |
  {
    total: length,
    done: [.[] | select(.value.status == "done")] | length
  } |
  "Progress: \(.done)/\(.total) (\(.done * 100 / .total | floor)%)"
' test-results/tdd-test-queue.json
```

### Active Work

```bash
# Components currently in progress
jq -r '
  .components | to_entries |
  map(select(.value.status == "in_progress")) |
  .[] | "[\(.value.sprint // "?")] \(.key)"
' test-results/tdd-test-queue.json
```

---

## Burndown & Progress

### Sprint Burndown

```bash
# Components remaining per sprint
jq -r '
  .components | to_entries |
  map(select(.value.status != "done")) |
  group_by(.value.sprint // 0) |
  map({sprint: .[0].value.sprint // 0, remaining: length}) |
  sort_by(.sprint) |
  .[] | "Sprint \(.sprint): \(.remaining) remaining"
' test-results/tdd-test-queue.json
```

### Domain Progress

```bash
# Progress by domain
jq -r '
  .components | to_entries |
  group_by(.value.domain // "unknown") |
  map({
    domain: .[0].value.domain // "unknown",
    total: length,
    done: [.[] | select(.value.status == "done")] | length
  }) |
  sort_by(.domain) |
  .[] | "\(.domain): \(.done)/\(.total) (\(.done * 100 / .total | floor)%)"
' test-results/tdd-test-queue.json
```

### Daily Progress (from metrics)

```bash
# Runs per day with pass rate
jq -s '
  group_by(.date) |
  map({
    date: .[0].date,
    runs: length,
    passed: [.[] | select(.outcome == "pass")] | length
  }) |
  sort_by(.date) |
  .[] | "\(.date): \(.passed)/\(.runs) passed"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

---

## Recent Failures

### Last 10 Failures

```bash
# Most recent failures
jq -s '
  [.[] | select(.outcome != "pass")] |
  sort_by(.timestamp) |
  reverse |
  .[:10] |
  .[] | "[\(.timestamp | split("T")[0])] \(.component_id) - \(.outcome)"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

### Failure Hotspots (Most Failing Components)

```bash
# Components with most failures
jq -s '
  [.[] | select(.outcome != "pass")] |
  group_by(.component_id) |
  map({component: .[0].component_id, failures: length}) |
  sort_by(.failures) |
  reverse |
  .[:10] |
  .[] | "\(.failures)x \(.component)"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

### Failures by Domain

```bash
# Failure count per domain
jq -s '
  [.[] | select(.outcome != "pass")] |
  group_by(.domain // "unknown") |
  map({domain: .[0].domain // "unknown", failures: length}) |
  sort_by(.failures) |
  reverse |
  .[] | "\(.domain): \(.failures) failures"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

### Components Never Passed

```bash
# Components that have failed but never passed
jq -s '
  group_by(.component_id) |
  map({
    component: .[0].component_id,
    total: length,
    passes: [.[] | select(.outcome == "pass")] | length,
    failures: [.[] | select(.outcome != "pass")] | length
  }) |
  [.[] | select(.passes == 0 and .failures > 0)] |
  sort_by(.failures) |
  reverse |
  .[] | "\(.component) - \(.failures) failures, 0 passes"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

---

## Duration Trends

### Average Duration by Component

```bash
# Average test duration per component
jq -s '
  group_by(.component_id) |
  map({
    component: .[0].component_id,
    avg_duration: ([.[].duration_seconds] | add / length | floor),
    runs: length
  }) |
  sort_by(.avg_duration) |
  reverse |
  .[:10] |
  .[] | "\(.avg_duration)s avg - \(.component) (\(.runs) runs)"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

### Slowest Runs

```bash
# Top 10 slowest test runs
jq -s '
  sort_by(.duration_seconds) |
  reverse |
  .[:10] |
  .[] | "\(.duration_seconds)s - \(.component_id) [\(.date)]"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

### Duration Trend (Daily Average)

```bash
# Average duration trend per day
jq -s '
  group_by(.date) |
  map({
    date: .[0].date,
    avg_duration: ([.[].duration_seconds] | add / length | floor),
    runs: length
  }) |
  sort_by(.date) |
  .[] | "\(.date): \(.avg_duration)s avg (\(.runs) runs)"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

---

## Retry Analysis

### Total Retries

```bash
# Count of runs with retries
jq -s '
  {
    total_runs: length,
    runs_with_retries: [.[] | select(.retry_count > 0)] | length,
    total_retries: [.[].retry_count] | add
  } |
  "Total runs: \(.total_runs)\nRuns with retries: \(.runs_with_retries)\nTotal retries: \(.total_retries)"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

### Retry Reasons

```bash
# Breakdown of retry reasons
jq -s '
  [.[] | select(.retry_count > 0 and .retry_reason != "")] |
  group_by(.retry_reason) |
  map({reason: .[0].retry_reason, count: length}) |
  sort_by(.count) |
  reverse |
  .[] | "\(.count)x \(.reason)"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

### Flakiest Components (Most Retries)

```bash
# Components requiring most retries
jq -s '
  [.[] | select(.retry_count > 0)] |
  group_by(.component_id) |
  map({
    component: .[0].component_id,
    total_retries: [.[].retry_count] | add,
    runs: length
  }) |
  sort_by(.total_retries) |
  reverse |
  .[:10] |
  .[] | "\(.total_retries) retries - \(.component) (\(.runs) runs)"
' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No metrics data yet"
```

---

## Iteration Log Analysis

### Recent Iterations

```bash
# Last 10 iterations
jq -s '
  sort_by(.timestamp) |
  reverse |
  .[:10] |
  .[] | "[\(.timestamp | split("T")[0])] \(.component_id) - \(.test_outcome)"
' test-results/logs/iterations.jsonl 2>/dev/null || echo "No iteration data yet"
```

### Iterations with Lint Failures

```bash
# Iterations where lint failed
jq -s '
  [.[] | select(.lint_status == "fail")] |
  .[] | "[\(.timestamp | split("T")[0])] \(.component_id)"
' test-results/logs/iterations.jsonl 2>/dev/null || echo "No lint failures"
```

---

## One-Liner Dashboard

Copy-paste this for a quick overview:

```bash
echo "=== TDD Queue Status ===" && \
jq -r '.components | to_entries | group_by(.value.status) | map({status: .[0].value.status, count: length}) | .[] | "\(.status): \(.count)"' test-results/tdd-test-queue.json && \
echo "" && \
echo "=== Recent Failures (last 5) ===" && \
jq -s '[.[] | select(.outcome != "pass")] | sort_by(.timestamp) | reverse | .[:5] | .[] | "\(.component_id)"' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No failures" && \
echo "" && \
echo "=== Failure Hotspots ===" && \
jq -s '[.[] | select(.outcome != "pass")] | group_by(.component_id) | map({c: .[0].component_id, n: length}) | sort_by(.n) | reverse | .[:5] | .[] | "\(.n)x \(.c)"' test-results/metrics/autoloop.jsonl 2>/dev/null || echo "No data"
```

---

## Scripted Dashboard

For a formatted dashboard, use:

```bash
php scripts/metrics-summary.php --since 7d
```

Or generate a support bundle with all data:

```bash
./scripts/make-support-bundle.sh "dashboard snapshot"
```

---

## Tips

1. **Pipe to `less`** for long output: `jq ... | less`
2. **Export to file**: `jq ... > report.txt`
3. **Watch mode**: `watch -n 60 'jq ... test-results/metrics/autoloop.jsonl'`
4. **CSV export**: Add `| @csv` to jq output for spreadsheet import

---

## File Locations

| File | Contents | Status |
|------|----------|--------|
| `test-results/tdd-test-queue.json` | Component queue with status | ✅ Exists |
| `test-results/metrics/autoloop.jsonl` | Per-run metrics (JSONL) | ⚠️ Not yet created - directory exists but file is not yet populated |
| `test-results/logs/iterations.jsonl` | Iteration logs (JSONL) | ✅ Exists |
| `test-results/logs/autoloop-summary.json` | Component summaries | ✅ Exists |
