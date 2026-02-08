#!/bin/bash
#######################################
# Iteration Logging Library
#
# Provides JSONL iteration logging for TDD autoloop.
# Append-only log format for structured observability.
#
# Usage:
#   source scripts/lib/iteration-log.sh
#   iteration_log_start "unit:SomeTest" "SomeTest"
#   # ... run tests ...
#   iteration_log_complete 0 "pass" 1 "" "No lint errors"
#
# Output:
#   test-results/logs/iterations.jsonl - Append-only JSONL
#   test-results/logs/autoloop-summary.json - Rollup summary
#######################################

ITERATION_LOG_DIR="${ITERATION_LOG_DIR:-test-results/logs}"
ITERATION_LOG_FILE="${ITERATION_LOG_DIR}/iterations.jsonl"
SUMMARY_FILE="${ITERATION_LOG_DIR}/autoloop-summary.json"

# Current iteration state (set by iteration_log_start)
_ITER_COMPONENT_ID=""
_ITER_COMPONENT_NAME=""
_ITER_START_TS=""
_ITER_START_EPOCH=""

#######################################
# Ensure log directory exists
#######################################
iteration_log_init() {
    mkdir -p "$ITERATION_LOG_DIR"

    # Initialize summary file if it doesn't exist
    if [ ! -f "$SUMMARY_FILE" ]; then
        cat > "$SUMMARY_FILE" << 'EOF'
{
  "last_updated": null,
  "total_iterations": 0,
  "total_passes": 0,
  "total_failures": 0,
  "components": {},
  "last_run": null
}
EOF
    fi
}

#######################################
# Start timing an iteration
# Arguments:
#   $1 - Component ID (e.g., "unit:SomeTest")
#   $2 - Component name (e.g., "SomeTest")
#######################################
iteration_log_start() {
    local component_id="$1"
    local component_name="$2"

    iteration_log_init

    _ITER_COMPONENT_ID="$component_id"
    _ITER_COMPONENT_NAME="$component_name"
    _ITER_START_TS=$(date -Iseconds)
    _ITER_START_EPOCH=$(date +%s)
}

#######################################
# Complete and log an iteration
# Arguments:
#   $1 - Exit code (0 = success)
#   $2 - Test outcome ("pass", "fail", "error", "skip")
#   $3 - Retry count
#   $4 - Retry reason (if any)
#   $5 - Lint status ("ok", "warnings", "errors", "skip")
#   $6 - Phase ("red", "green", "refactor", "complete") [optional]
#   $7 - Notes [optional]
#######################################
iteration_log_complete() {
    local exit_code="${1:-0}"
    local test_outcome="${2:-unknown}"
    local retry_count="${3:-0}"
    local retry_reason="${4:-}"
    local lint_status="${5:-skip}"
    local phase="${6:-unknown}"
    local notes="${7:-}"

    local end_ts=$(date -Iseconds)
    local end_epoch=$(date +%s)
    local duration_secs=$((end_epoch - _ITER_START_EPOCH))

    # Build JSON object
    local json_obj
    json_obj=$(cat << EOF
{
  "component_id": "$_ITER_COMPONENT_ID",
  "component_name": "$_ITER_COMPONENT_NAME",
  "timestamp_start": "$_ITER_START_TS",
  "timestamp_end": "$end_ts",
  "duration_seconds": $duration_secs,
  "exit_code": $exit_code,
  "test_outcome": "$test_outcome",
  "lint_status": "$lint_status",
  "retries": {
    "count": $retry_count,
    "reason": "$retry_reason"
  },
  "phase": "$phase",
  "notes": "$notes"
}
EOF
)

    # Compact JSON to single line and append to JSONL
    echo "$json_obj" | jq -c '.' >> "$ITERATION_LOG_FILE"

    # Update summary
    _update_summary "$_ITER_COMPONENT_ID" "$test_outcome" "$exit_code" "$end_ts"

    # Clear state
    _ITER_COMPONENT_ID=""
    _ITER_COMPONENT_NAME=""
    _ITER_START_TS=""
    _ITER_START_EPOCH=""
}

#######################################
# Log an iteration in one call (without start/complete)
# Arguments:
#   $1 - Component ID
#   $2 - Component name
#   $3 - Exit code
#   $4 - Test outcome
#   $5 - Duration seconds
#   $6 - Retry count
#   $7 - Retry reason
#   $8 - Lint status
#   $9 - Phase
#   $10 - Notes
#######################################
iteration_log_entry() {
    local component_id="$1"
    local component_name="$2"
    local exit_code="${3:-0}"
    local test_outcome="${4:-unknown}"
    local duration_secs="${5:-0}"
    local retry_count="${6:-0}"
    local retry_reason="${7:-}"
    local lint_status="${8:-skip}"
    local phase="${9:-unknown}"
    local notes="${10:-}"

    iteration_log_init

    local timestamp=$(date -Iseconds)

    local json_obj
    json_obj=$(cat << EOF
{
  "component_id": "$component_id",
  "component_name": "$component_name",
  "timestamp_start": "$timestamp",
  "timestamp_end": "$timestamp",
  "duration_seconds": $duration_secs,
  "exit_code": $exit_code,
  "test_outcome": "$test_outcome",
  "lint_status": "$lint_status",
  "retries": {
    "count": $retry_count,
    "reason": "$retry_reason"
  },
  "phase": "$phase",
  "notes": "$notes"
}
EOF
)

    echo "$json_obj" | jq -c '.' >> "$ITERATION_LOG_FILE"
    _update_summary "$component_id" "$test_outcome" "$exit_code" "$timestamp"
}

#######################################
# Update the rollup summary file
# Arguments:
#   $1 - Component ID
#   $2 - Test outcome
#   $3 - Exit code
#   $4 - Timestamp
#######################################
_update_summary() {
    local component_id="$1"
    local test_outcome="$2"
    local exit_code="$3"
    local timestamp="$4"

    # Escape component_id for use as jq key (replace : with _)
    local safe_key=$(echo "$component_id" | tr ':' '_')

    # Read current summary
    local current_summary
    current_summary=$(cat "$SUMMARY_FILE")

    # Calculate new totals
    local total_iterations=$(echo "$current_summary" | jq '.total_iterations + 1')
    local total_passes=$(echo "$current_summary" | jq '.total_passes')
    local total_failures=$(echo "$current_summary" | jq '.total_failures')

    if [ "$test_outcome" = "pass" ]; then
        total_passes=$((total_passes + 1))
    elif [ "$test_outcome" = "fail" ] || [ "$test_outcome" = "error" ]; then
        total_failures=$((total_failures + 1))
    fi

    # Update summary with jq
    echo "$current_summary" | jq --arg ts "$timestamp" \
        --arg cid "$component_id" \
        --arg outcome "$test_outcome" \
        --argjson exit "$exit_code" \
        --argjson total "$total_iterations" \
        --argjson passes "$total_passes" \
        --argjson failures "$total_failures" \
        '.last_updated = $ts |
         .total_iterations = $total |
         .total_passes = $passes |
         .total_failures = $failures |
         .components[$cid] = {
           "last_outcome": $outcome,
           "last_exit_code": $exit,
           "last_timestamp": $ts
         } |
         .last_run = {
           "component_id": $cid,
           "outcome": $outcome,
           "exit_code": $exit,
           "timestamp": $ts
         }' > "${SUMMARY_FILE}.tmp" && mv "${SUMMARY_FILE}.tmp" "$SUMMARY_FILE"
}

#######################################
# Query: Get last failed iteration
# Returns JSON of last failure or null
#######################################
iteration_log_last_failure() {
    if [ ! -f "$ITERATION_LOG_FILE" ]; then
        echo "null"
        return
    fi

    # Find last entry with test_outcome != "pass"
    tac "$ITERATION_LOG_FILE" 2>/dev/null | jq -s 'map(select(.test_outcome != "pass")) | first // null' | head -1
}

#######################################
# Query: Get failures for a component
# Arguments:
#   $1 - Component ID
#######################################
iteration_log_component_failures() {
    local component_id="$1"

    if [ ! -f "$ITERATION_LOG_FILE" ]; then
        echo "[]"
        return
    fi

    jq -s --arg cid "$component_id" \
        '[.[] | select(.component_id == $cid and .test_outcome != "pass")]' \
        "$ITERATION_LOG_FILE"
}

#######################################
# Query: Get summary stats
#######################################
iteration_log_stats() {
    if [ ! -f "$SUMMARY_FILE" ]; then
        echo '{"error": "No summary file"}'
        return
    fi

    cat "$SUMMARY_FILE"
}

#######################################
# Regenerate summary from JSONL
# Use if summary gets corrupted
#######################################
iteration_log_rebuild_summary() {
    if [ ! -f "$ITERATION_LOG_FILE" ]; then
        echo "No iterations log to rebuild from"
        return 1
    fi

    # Count totals from JSONL
    local total=$(wc -l < "$ITERATION_LOG_FILE")
    local passes=$(jq -s '[.[] | select(.test_outcome == "pass")] | length' "$ITERATION_LOG_FILE")
    local failures=$(jq -s '[.[] | select(.test_outcome == "fail" or .test_outcome == "error")] | length' "$ITERATION_LOG_FILE")

    # Get last entry
    local last_entry=$(tail -1 "$ITERATION_LOG_FILE")
    local last_cid=$(echo "$last_entry" | jq -r '.component_id')
    local last_outcome=$(echo "$last_entry" | jq -r '.test_outcome')
    local last_exit=$(echo "$last_entry" | jq '.exit_code')
    local last_ts=$(echo "$last_entry" | jq -r '.timestamp_end')

    # Build component map from all entries
    local components
    components=$(jq -s 'group_by(.component_id) | map({
        key: .[0].component_id,
        value: {
            last_outcome: .[-1].test_outcome,
            last_exit_code: .[-1].exit_code,
            last_timestamp: .[-1].timestamp_end
        }
    }) | from_entries' "$ITERATION_LOG_FILE")

    # Write new summary
    jq -n --arg ts "$last_ts" \
        --argjson total "$total" \
        --argjson passes "$passes" \
        --argjson failures "$failures" \
        --argjson components "$components" \
        --arg last_cid "$last_cid" \
        --arg last_outcome "$last_outcome" \
        --argjson last_exit "$last_exit" \
        '{
            last_updated: $ts,
            total_iterations: $total,
            total_passes: $passes,
            total_failures: $failures,
            components: $components,
            last_run: {
                component_id: $last_cid,
                outcome: $last_outcome,
                exit_code: $last_exit,
                timestamp: $ts
            }
        }' > "$SUMMARY_FILE"

    echo "Summary rebuilt: $total iterations, $passes passes, $failures failures"
}
