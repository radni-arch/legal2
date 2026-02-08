#!/usr/bin/env bash
#######################################
# Post-Run Failure Report Generator
#
# Parses PHPUnit failure output and generates:
# - Failure briefs: test-results/failures/<component>.md
# - RCA drafts: test-results/failures/rca-latest.md (on threshold)
#
# Usage:
#   ./scripts/post-run-failure-report.sh <component-id> <log-file> [exit-code]
#
# Example:
#   ./scripts/post-run-failure-report.sh "unit:SomeTest" "test-logs/SomeTest-results.txt" 1
#
# Environment:
#   FAILURE_RCA_THRESHOLD - Consecutive failures before RCA (default: 3)
#######################################

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FAILURES_DIR="${ROOT_DIR}/test-results/failures"
METRICS_FILE="${ROOT_DIR}/test-results/metrics/autoloop.jsonl"

# Configuration
FAILURE_RCA_THRESHOLD="${FAILURE_RCA_THRESHOLD:-3}"

#######################################
# Parse arguments
#######################################
COMPONENT_ID="${1:-}"
LOG_FILE="${2:-}"
EXIT_CODE="${3:-1}"

if [[ -z "$COMPONENT_ID" ]] || [[ -z "$LOG_FILE" ]]; then
    echo "Usage: $0 <component-id> <log-file> [exit-code]"
    echo ""
    echo "Example:"
    echo "  $0 'unit:VectorStoreManagerTest' 'test-logs/VectorStoreManagerTest-results.txt' 1"
    exit 1
fi

# Ensure failures directory exists
mkdir -p "$FAILURES_DIR"

# Extract component name for filename
COMPONENT_NAME="${COMPONENT_ID##*:}"
BRIEF_FILE="${FAILURES_DIR}/${COMPONENT_NAME}.md"
RCA_FILE="${FAILURES_DIR}/rca-latest.md"

#######################################
# Extract failing test names
# PHPUnit formats:
#   • test_method_name
#   FAIL Tests\Unit\SomeTest::test_method
#######################################
extract_failing_tests() {
    local log_file="$1"

    if [[ ! -f "$log_file" ]]; then
        echo "# No log file found"
        return
    fi

    # Pattern 1: "FAIL Tests\Namespace\Class::method"
    grep -oE 'FAIL\s+Tests\\[A-Za-z0-9\\]+::[a-z_]+' "$log_file" 2>/dev/null | \
        sed 's/FAIL\s*//' | sort -u || true

    # Pattern 2: "• test_method_name" (bullet point format)
    grep -oE '•\s+[a-z_]+' "$log_file" 2>/dev/null | \
        sed 's/•\s*//' | sort -u || true

    # Pattern 3: "✕ it_does_something" (x mark format)
    grep -oE '✕\s+[a-z_]+' "$log_file" 2>/dev/null | \
        sed 's/✕\s*//' | sort -u || true

    # Pattern 4: "FAILED Tests\...\SomeTest > method" (newer PHPUnit format)
    grep -oE 'FAILED\s+Tests\\[^>]+>\s+[a-z_]+' "$log_file" 2>/dev/null | \
        sed 's/FAILED\s*//' | sort -u || true
}

#######################################
# Extract assertion errors / key error messages
#######################################
extract_error_messages() {
    local log_file="$1"

    if [[ ! -f "$log_file" ]]; then
        echo "# No log file found"
        return
    fi

    # Pattern 1: "Failed asserting that..."
    grep -E 'Failed asserting' "$log_file" 2>/dev/null | head -10 || true

    # Pattern 2: "Expected ... but got ..."
    grep -E 'Expected .+ (but|to)' "$log_file" 2>/dev/null | head -10 || true

    # Pattern 3: Exception messages
    grep -E '(Exception|Error):' "$log_file" 2>/dev/null | \
        grep -v '^#' | head -10 || true

    # Pattern 4: "AssertionFailedError"
    grep -E 'AssertionFailedError' "$log_file" 2>/dev/null | head -5 || true
}

#######################################
# Extract key stack frames (file:line)
#######################################
extract_stack_frames() {
    local log_file="$1"

    if [[ ! -f "$log_file" ]]; then
        echo "# No log file found"
        return
    fi

    # Pattern: "/path/to/file.php:123" or "file.php on line 123"
    grep -oE '[a-zA-Z0-9_/.-]+\.php(:[0-9]+|\s+on\s+line\s+[0-9]+)' "$log_file" 2>/dev/null | \
        grep -v vendor | \
        head -15 | \
        sort -u || true
}

#######################################
# Extract suspected files (app/ or tests/)
#######################################
extract_suspected_files() {
    local log_file="$1"

    if [[ ! -f "$log_file" ]]; then
        echo "# No log file found"
        return
    fi

    # Look for app/ and tests/ paths
    grep -oE '(app|tests)/[a-zA-Z0-9_/.-]+\.php' "$log_file" 2>/dev/null | \
        sort -u | head -10 || true
}

#######################################
# Generate rerun command
#######################################
generate_rerun_command() {
    local component_id="$1"
    local component_name="${component_id##*:}"

    echo "./scripts/run-focused-tests.sh ${component_name}"
}

#######################################
# Count consecutive failures for component
#######################################
count_consecutive_failures() {
    local component_id="$1"

    if [[ ! -f "$METRICS_FILE" ]]; then
        echo "0"
        return
    fi

    # Get last N entries for this component and count consecutive failures from most recent
    local entries
    entries=$(grep "\"component_id\":\"$component_id\"" "$METRICS_FILE" | tail -n 10)

    if [[ -z "$entries" ]]; then
        echo "0"
        return
    fi

    local consecutive=0
    while IFS= read -r line; do
        local outcome
        outcome=$(echo "$line" | jq -r '.outcome' 2>/dev/null)
        if [[ "$outcome" == "fail" ]] || [[ "$outcome" == "error" ]]; then
            ((consecutive++))
        else
            break
        fi
    done <<< "$(echo "$entries" | tac)"

    echo "$consecutive"
}

#######################################
# Get recent failures for RCA
#######################################
get_recent_failures() {
    local limit="${1:-5}"

    if [[ ! -f "$METRICS_FILE" ]]; then
        echo "No metrics data available"
        return
    fi

    # Get last N failures
    grep -E '"outcome":"(fail|error)"' "$METRICS_FILE" 2>/dev/null | \
        tail -n "$limit" | \
        jq -r '[.timestamp, .component_id, .outcome] | @tsv' 2>/dev/null || \
        echo "Unable to parse metrics"
}

#######################################
# Generate failure brief
#######################################
generate_failure_brief() {
    local component_id="$1"
    local log_file="$2"
    local component_name="${component_id##*:}"
    local timestamp
    timestamp=$(date -Iseconds)
    local consecutive
    consecutive=$(count_consecutive_failures "$component_id")

    cat << EOF
# Failure Brief: ${component_name}

**Component:** \`${component_id}\`
**Generated:** ${timestamp}
**Consecutive Failures:** ${consecutive}

---

## Rerun Command

\`\`\`bash
$(generate_rerun_command "$component_id")
\`\`\`

---

## Failing Tests

$(extract_failing_tests "$log_file" | sed 's/^/- /' || echo "- Unable to extract test names")

---

## Error Messages

\`\`\`
$(extract_error_messages "$log_file" | head -20 || echo "Unable to extract error messages")
\`\`\`

---

## Stack Frames

\`\`\`
$(extract_stack_frames "$log_file" || echo "Unable to extract stack frames")
\`\`\`

---

## Suspected Files

$(extract_suspected_files "$log_file" | sed 's/^/- `/' | sed 's/$/`/' || echo "- Unable to identify suspected files")

---

## Quick Actions

1. **Rerun test:** \`$(generate_rerun_command "$component_id")\`
2. **View full log:** \`less ${log_file}\`
3. **Check metrics:** \`php scripts/metrics-summary.php --component ${component_name}\`

EOF
}

#######################################
# Generate RCA draft
#######################################
generate_rca_draft() {
    local component_id="$1"
    local component_name="${component_id##*:}"
    local timestamp
    timestamp=$(date -Iseconds)
    local consecutive
    consecutive=$(count_consecutive_failures "$component_id")

    cat << EOF
# RCA Draft: Repeated Failures

**Trigger:** ${consecutive} consecutive failures for \`${component_id}\`
**Generated:** ${timestamp}
**Threshold:** ${FAILURE_RCA_THRESHOLD}

---

## Summary

The component \`${component_id}\` has failed ${consecutive} times consecutively,
exceeding the RCA threshold of ${FAILURE_RCA_THRESHOLD}.

---

## Recent Failures (Last 5)

| Timestamp | Component | Outcome |
|-----------|-----------|---------|
$(get_recent_failures 5 | awk -F'\t' '{print "| " $1 " | `" $2 "` | " $3 " |"}')

---

## Failure Pattern Analysis

**Questions to investigate:**

1. [ ] Is this a new failure or regression?
2. [ ] Did recent code changes affect this component?
3. [ ] Are there external dependencies involved (DB, API, network)?
4. [ ] Is this a flaky test or deterministic failure?
5. [ ] Are other related components also failing?

---

## Recommended Actions

1. **Review the failure brief:**
   \`\`\`bash
   cat test-results/failures/${component_name}.md
   \`\`\`

2. **Check recent commits:**
   \`\`\`bash
   git log --oneline -10
   \`\`\`

3. **Run with verbose output:**
   \`\`\`bash
   php artisan test --filter=${component_name} -vvv
   \`\`\`

4. **Check environment:**
   \`\`\`bash
   /check-setup
   \`\`\`

---

## Resolution

_Fill in after investigation:_

**Root Cause:**

**Fix Applied:**

**Verification:**

EOF
}

#######################################
# Main execution
#######################################
echo "Generating failure brief for: $COMPONENT_ID"

# Generate failure brief
generate_failure_brief "$COMPONENT_ID" "$LOG_FILE" > "$BRIEF_FILE"
echo "  -> ${BRIEF_FILE}"

# Check if RCA draft should be generated
CONSECUTIVE=$(count_consecutive_failures "$COMPONENT_ID")
if [[ "$CONSECUTIVE" -ge "$FAILURE_RCA_THRESHOLD" ]]; then
    echo ""
    echo "⚠️  RCA THRESHOLD EXCEEDED: ${CONSECUTIVE} consecutive failures"
    echo "   Generating RCA draft..."
    generate_rca_draft "$COMPONENT_ID" > "$RCA_FILE"
    echo "  -> ${RCA_FILE}"
fi

echo ""
echo "Done."
