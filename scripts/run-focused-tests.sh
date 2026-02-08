#!/usr/bin/env bash
#
# Generic focused test runner for TDD autoloops
# With bounded retry system for flaky tests
#
# Usage: ./scripts/run-focused-tests.sh <phpunit-filter-or-path> [options]
#
# Examples:
#   ./scripts/run-focused-tests.sh VectorStoreManagerTest
#   ./scripts/run-focused-tests.sh tests/Feature/Livewire/VectorStoreManagerTest.php
#   ./scripts/run-focused-tests.sh TextractPipelineFlowTest --no-retry
#
# Options:
#   --no-retry    Disable retry logic for this run
#   --verbose     Show detailed retry decision logging
#
# Retry Policy:
#   - Known flaky tests (in flaky-tests.json): retry per-test limit (default: 1)
#   - Dusk browser tests: retry once with stabilization flags
#   - DB connectivity errors: retry once after clearing config cache
#   - Hard cap: 2 total retries maximum per invocation
#
set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Source iteration logging library
if [[ -f "$ROOT_DIR/scripts/lib/iteration-log.sh" ]]; then
    source "$ROOT_DIR/scripts/lib/iteration-log.sh"
    ITERATION_LOGGING_ENABLED=true
else
    ITERATION_LOGGING_ENABLED=false
fi

# Source metrics sink library
if [[ -f "$ROOT_DIR/scripts/lib/metrics-sink.sh" ]]; then
    source "$ROOT_DIR/scripts/lib/metrics-sink.sh"
    METRICS_ENABLED=true
else
    METRICS_ENABLED=false
fi

# Source telemetry alerts library
if [[ -f "$ROOT_DIR/scripts/lib/alerts.sh" ]]; then
    source "$ROOT_DIR/scripts/lib/alerts.sh"
    alerts_init
    ALERTS_ENABLED=true
else
    ALERTS_ENABLED=false
fi

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
FLAKY_TESTS_FILE="$ROOT_DIR/test-results/flaky-tests.json"
HARD_RETRY_CAP=2
RETRY_ENABLED=true
VERBOSE=false

#######################################
# Parse arguments
#######################################
TARGET=""
for arg in "$@"; do
    case "$arg" in
        --no-retry)
            RETRY_ENABLED=false
            ;;
        --verbose)
            VERBOSE=true
            ;;
        *)
            if [[ -z "$TARGET" ]]; then
                TARGET="$arg"
            fi
            ;;
    esac
done

if [[ -z "$TARGET" ]]; then
    echo "Usage: $0 <phpunit-filter-or-path> [--no-retry] [--verbose]"
    echo
    echo "Examples:"
    echo "  $0 VectorStoreManagerTest"
    echo "  $0 tests/Feature/Livewire/VectorStoreManagerTest.php"
    echo "  $0 TextractPipelineFlowTest --no-retry"
    echo
    echo "Options:"
    echo "  --no-retry    Disable retry logic"
    echo "  --verbose     Show detailed retry decisions"
    exit 1
fi

# Create output directories
mkdir -p test-logs test-results

# Build a safe filename for logs (replace / \ : with _)
SAFE_NAME="$(echo "$TARGET" | tr '/\\:' '_')"
LOG_FILE="test-logs/${SAFE_NAME}-results.txt"
METADATA_FILE="test-results/.last-run.json"

# Derive component ID from target (for iteration logging)
# Format: "focused:<ClassName>" or "focused:<filepath>"
COMPONENT_ID="focused:${SAFE_NAME}"

# Start iteration logging
if [[ "$ITERATION_LOGGING_ENABLED" == "true" ]]; then
    iteration_log_start "$COMPONENT_ID" "$TARGET"
fi

#######################################
# Logging helpers
#######################################
log_retry() {
    local attempt="$1"
    local reason="$2"
    local recovery="${3:-none}"

    echo ""
    echo -e "${YELLOW}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║                      RETRY TRIGGERED                       ║${NC}"
    echo -e "${YELLOW}╠════════════════════════════════════════════════════════════╣${NC}"
    echo -e "${YELLOW}║${NC} Attempt:   ${CYAN}${attempt}${NC} of ${HARD_RETRY_CAP} (hard cap)"
    echo -e "${YELLOW}║${NC} Reason:    ${reason}"
    echo -e "${YELLOW}║${NC} Recovery:  ${recovery}"
    echo -e "${YELLOW}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

log_verbose() {
    if [[ "$VERBOSE" == "true" ]]; then
        echo -e "${BLUE}[retry-debug]${NC} $1" >&2
    fi
}

log_no_retry() {
    local reason="$1"
    log_verbose "No retry: $reason"
}

#######################################
# Detect test type
#######################################
is_dusk_test() {
    local target="$1"
    # Check if path contains Browser or if class name suggests Dusk
    if [[ "$target" == *"tests/Browser/"* ]] || [[ "$target" == *"Browser"* ]]; then
        return 0
    fi
    return 1
}

#######################################
# Check flaky tests registry
#######################################
get_flaky_test_config() {
    local target="$1"

    if [[ ! -f "$FLAKY_TESTS_FILE" ]]; then
        echo ""
        return
    fi

    # Check exact match in tests object
    local config
    config=$(jq -r --arg t "$target" '.tests[$t] // empty' "$FLAKY_TESTS_FILE" 2>/dev/null)

    if [[ -n "$config" ]]; then
        echo "$config"
        return
    fi

    # Check pattern matches (e.g., tests containing the target name)
    # This handles partial matches like "SomeTest" matching "SomeTest::test_method"
    config=$(jq -r --arg t "$target" '
        .tests | to_entries[] |
        select(.key | contains($t)) |
        .value
    ' "$FLAKY_TESTS_FILE" 2>/dev/null | head -1)

    echo "$config"
}

get_flaky_max_retries() {
    local target="$1"
    local config
    config=$(get_flaky_test_config "$target")

    if [[ -n "$config" ]]; then
        local max
        max=$(echo "$config" | jq -r '.max_retries // 1' 2>/dev/null)
        echo "$max"
    else
        echo "0"
    fi
}

get_flaky_reason() {
    local target="$1"
    local config
    config=$(get_flaky_test_config "$target")

    if [[ -n "$config" ]]; then
        echo "$config" | jq -r '.reason // "Known flaky test"' 2>/dev/null
    fi
}

#######################################
# Check error signatures in output
#######################################
check_db_connection_error() {
    local output="$1"

    if [[ ! -f "$FLAKY_TESTS_FILE" ]]; then
        # Fallback patterns if no config
        if echo "$output" | grep -qE "SQLSTATE\[HY000\].*Connection refused|could not connect to server"; then
            return 0
        fi
        return 1
    fi

    local patterns
    patterns=$(jq -r '.error_signatures.db_connection.patterns[]?' "$FLAKY_TESTS_FILE" 2>/dev/null)

    while IFS= read -r pattern; do
        if [[ -n "$pattern" ]] && echo "$output" | grep -qE "$pattern"; then
            return 0
        fi
    done <<< "$patterns"

    return 1
}

check_db_lock_error() {
    local output="$1"

    if [[ ! -f "$FLAKY_TESTS_FILE" ]]; then
        if echo "$output" | grep -qE "Deadlock|Lock wait timeout"; then
            return 0
        fi
        return 1
    fi

    local patterns
    patterns=$(jq -r '.error_signatures.db_lock.patterns[]?' "$FLAKY_TESTS_FILE" 2>/dev/null)

    while IFS= read -r pattern; do
        if [[ -n "$pattern" ]] && echo "$output" | grep -qE "$pattern"; then
            return 0
        fi
    done <<< "$patterns"

    return 1
}

#######################################
# Recovery actions
#######################################
do_recovery() {
    local action="$1"

    case "$action" in
        clear_config_cache)
            log_verbose "Running: php artisan config:clear"
            php artisan config:clear >/dev/null 2>&1 || true
            log_verbose "Running: php artisan cache:clear"
            php artisan cache:clear >/dev/null 2>&1 || true
            ;;
        add_stabilization_flags)
            # Flags are added in the run command, not here
            log_verbose "Stabilization flags will be added to next run"
            ;;
        none|*)
            log_verbose "No recovery action needed"
            ;;
    esac
}

#######################################
# Determine retry eligibility
# Returns: max_retries:reason:recovery
#######################################
determine_retry_policy() {
    local target="$1"
    local output="$2"
    local exit_code="$3"

    # Success - no retry needed
    if [[ "$exit_code" -eq 0 ]]; then
        echo "0:success:none"
        return
    fi

    # Check known flaky test first
    local flaky_max
    flaky_max=$(get_flaky_max_retries "$target")
    if [[ "$flaky_max" -gt 0 ]]; then
        local reason
        reason=$(get_flaky_reason "$target")
        echo "${flaky_max}:${reason}:none"
        return
    fi

    # Check if Dusk test
    if is_dusk_test "$target"; then
        echo "1:Browser test timing variability:add_stabilization_flags"
        return
    fi

    # Check DB connection error
    if check_db_connection_error "$output"; then
        echo "1:Database connection error:clear_config_cache"
        return
    fi

    # Check DB lock error
    if check_db_lock_error "$output"; then
        echo "1:Database lock contention:none"
        return
    fi

    # No retry policy matches - this is a real failure
    echo "0:No retry policy matches:none"
}

#######################################
# Run test with optional Dusk flags
# Sets RUN_TEST_EXIT_CODE global variable
#######################################
RUN_TEST_EXIT_CODE=0

run_test() {
    local target="$1"
    local attempt="$2"
    local use_dusk_stabilization="${3:-false}"

    local cmd=""
    if [[ -f "$target" ]]; then
        cmd="php artisan test $target"
    else
        cmd="php artisan test --filter=$target"
    fi

    # Add Dusk stabilization for retries
    if [[ "$use_dusk_stabilization" == "true" ]] && is_dusk_test "$target"; then
        # Use without-tty to avoid terminal issues
        if [[ -f "$target" ]]; then
            cmd="php artisan dusk $target --without-tty"
        else
            cmd="php artisan dusk --filter=$target --without-tty"
        fi
        log_verbose "Using Dusk command with stabilization: $cmd"
    fi

    log_verbose "Running (attempt $attempt): $cmd"

    set +e
    eval "$cmd" 2>&1 | tee "$LOG_FILE"
    RUN_TEST_EXIT_CODE=${PIPESTATUS[0]}
    set -e
}

#######################################
# Main execution
#######################################
echo "======================================================"
echo "TDD Focused Test Runner (with bounded retries)"
echo "======================================================"
echo "Target:       $TARGET"
echo "Retry:        $(if [[ "$RETRY_ENABLED" == "true" ]]; then echo "enabled (cap: $HARD_RETRY_CAP)"; else echo "disabled"; fi)"
echo "Flaky config: $FLAKY_TESTS_FILE"
echo "Log:          $LOG_FILE"
echo "======================================================"
echo

# Track retry state
ATTEMPT=1
TOTAL_RETRIES=0
FINAL_EXIT_CODE=0
RETRY_SUMMARY=""

# Start timing for metrics
START_TIME=$(date +%s)

while true; do
    # Determine if we should use stabilization flags (only on retries)
    USE_STABILIZATION="false"
    if [[ $ATTEMPT -gt 1 ]]; then
        USE_STABILIZATION="true"
    fi

    # Run the test
    if [[ $ATTEMPT -eq 1 ]]; then
        echo -e "${CYAN}Running tests (attempt $ATTEMPT)...${NC}"
    fi

    run_test "$TARGET" "$ATTEMPT" "$USE_STABILIZATION"
    FINAL_EXIT_CODE=$RUN_TEST_EXIT_CODE

    # Success - we're done
    if [[ "$FINAL_EXIT_CODE" -eq 0 ]]; then
        if [[ $ATTEMPT -gt 1 ]]; then
            RETRY_SUMMARY="Passed on retry $((ATTEMPT - 1))"
        fi
        break
    fi

    # Failure - check retry policy
    if [[ "$RETRY_ENABLED" != "true" ]]; then
        log_no_retry "Retries disabled via --no-retry"
        break
    fi

    if [[ $TOTAL_RETRIES -ge $HARD_RETRY_CAP ]]; then
        log_no_retry "Hard cap reached ($HARD_RETRY_CAP retries)"
        RETRY_SUMMARY="Failed after $TOTAL_RETRIES retries (hard cap)"
        break
    fi

    # Read test output for error detection
    TEST_OUTPUT=""
    if [[ -f "$LOG_FILE" ]]; then
        TEST_OUTPUT=$(cat "$LOG_FILE")
    fi

    # Determine retry eligibility
    POLICY=$(determine_retry_policy "$TARGET" "$TEST_OUTPUT" "$FINAL_EXIT_CODE")
    MAX_RETRIES=$(echo "$POLICY" | cut -d: -f1)
    REASON=$(echo "$POLICY" | cut -d: -f2)
    RECOVERY=$(echo "$POLICY" | cut -d: -f3)

    log_verbose "Policy: max=$MAX_RETRIES, reason=$REASON, recovery=$RECOVERY"

    # Check if we should retry
    if [[ "$MAX_RETRIES" -eq 0 ]]; then
        log_no_retry "$REASON"
        RETRY_SUMMARY="Failed (no retry: $REASON)"
        break
    fi

    # Check per-test retry limit
    if [[ $TOTAL_RETRIES -ge $MAX_RETRIES ]]; then
        log_no_retry "Per-test limit reached ($TOTAL_RETRIES/$MAX_RETRIES)"
        RETRY_SUMMARY="Failed after $TOTAL_RETRIES retries (per-test limit)"
        break
    fi

    # Execute retry
    TOTAL_RETRIES=$((TOTAL_RETRIES + 1))
    ATTEMPT=$((ATTEMPT + 1))

    log_retry "$TOTAL_RETRIES" "$REASON" "$RECOVERY"

    # Perform recovery action
    do_recovery "$RECOVERY"

    # Small delay before retry
    sleep 1
done

# Generate metadata
RFC3339_NOW="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

if [[ $FINAL_EXIT_CODE -eq 0 ]]; then
    STATUS="passed"
else
    STATUS="failed"
fi

# Count tests from output if possible
TESTS_RUN=$(grep -oP '\d+(?= tests)' "$LOG_FILE" 2>/dev/null | head -1 || echo "0")
FAILURES=$(grep -oP '\d+(?= failures)' "$LOG_FILE" 2>/dev/null | head -1 || echo "0")
ERRORS=$(grep -oP '\d+(?= errors)' "$LOG_FILE" 2>/dev/null | head -1 || echo "0")

cat > "$METADATA_FILE" <<EOF
{
  "suite": "focused",
  "target": "${TARGET}",
  "last_run": "${RFC3339_NOW}",
  "status": "${STATUS}",
  "exit_code": ${FINAL_EXIT_CODE},
  "log_file": "${LOG_FILE}",
  "stats": {
    "tests": ${TESTS_RUN:-0},
    "failures": ${FAILURES:-0},
    "errors": ${ERRORS:-0}
  },
  "retry": {
    "enabled": ${RETRY_ENABLED},
    "attempts": ${ATTEMPT},
    "total_retries": ${TOTAL_RETRIES},
    "hard_cap": ${HARD_RETRY_CAP},
    "summary": "${RETRY_SUMMARY:-none}"
  }
}
EOF

echo
echo "======================================================"
echo "Focused tests finished for: $TARGET"
echo " - Status:   $STATUS"
if [[ -n "$RETRY_SUMMARY" ]]; then
    echo -e " - Retries:  ${YELLOW}${RETRY_SUMMARY}${NC}"
fi
echo " - Attempts: $ATTEMPT"
echo " - Log:      $LOG_FILE"
echo " - Metadata: $METADATA_FILE"
echo "======================================================"

# Log iteration to JSONL
if [[ "$ITERATION_LOGGING_ENABLED" == "true" ]]; then
    # Determine lint status (skip for now - can be enhanced later)
    LINT_STATUS="skip"

    # Determine retry reason for logging
    RETRY_REASON_LOG=""
    if [[ $TOTAL_RETRIES -gt 0 ]]; then
        RETRY_REASON_LOG="${REASON:-unknown}"
    fi

    iteration_log_complete \
        "$FINAL_EXIT_CODE" \
        "$STATUS" \
        "$TOTAL_RETRIES" \
        "$RETRY_REASON_LOG" \
        "$LINT_STATUS" \
        "complete" \
        "${RETRY_SUMMARY:-}"
fi

# Append metrics to JSONL for trend analysis
if [[ "$METRICS_ENABLED" == "true" ]]; then
    # Calculate duration (uses START_TIME set earlier)
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))

    # Determine outcome for metrics
    METRICS_OUTCOME="pass"
    if [[ "$FINAL_EXIT_CODE" -ne 0 ]]; then
        METRICS_OUTCOME="fail"
    fi

    # Determine retry reason for metrics
    METRICS_RETRY_REASON=""
    if [[ $TOTAL_RETRIES -gt 0 ]]; then
        METRICS_RETRY_REASON="${REASON:-unknown}"
    fi

    metrics_append \
        "$COMPONENT_ID" \
        "$METRICS_OUTCOME" \
        "$DURATION" \
        "$TOTAL_RETRIES" \
        "$METRICS_RETRY_REASON" \
        "complete"
fi

# Check telemetry alerts (thresholds configured in alert-thresholds.json)
if [[ "$ALERTS_ENABLED" == "true" ]]; then
    # Get outcome and duration for alerts (reuse from metrics block or calculate)
    ALERT_OUTCOME="${METRICS_OUTCOME:-$STATUS}"
    ALERT_DURATION="${DURATION:-0}"

    # Run all alert checks
    alerts_check_all "$COMPONENT_ID" "$ALERT_OUTCOME" "$ALERT_DURATION" || true
fi

# Generate failure brief on test failure
if [[ "$FINAL_EXIT_CODE" -ne 0 ]]; then
    FAILURE_REPORT_SCRIPT="$ROOT_DIR/scripts/post-run-failure-report.sh"
    if [[ -x "$FAILURE_REPORT_SCRIPT" ]]; then
        echo ""
        "$FAILURE_REPORT_SCRIPT" "$COMPONENT_ID" "$LOG_FILE" "$FINAL_EXIT_CODE" || true
    fi
fi

# Exit with the test exit code so CI can detect failures
exit $FINAL_EXIT_CODE
