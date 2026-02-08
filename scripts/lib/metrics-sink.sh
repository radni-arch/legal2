#!/bin/bash
#######################################
# Metrics Sink Library
#
# Append-only metrics collection for TDD autoloop trend analysis.
# Captures: durations, pass/fail, retry counts, component hotspots.
#
# Usage:
#   source scripts/lib/metrics-sink.sh
#   metrics_append "unit:SomeTest" "pass" 15 0 "" "red"
#
# Output:
#   test-results/metrics/autoloop.jsonl
#######################################

METRICS_DIR="${METRICS_DIR:-test-results/metrics}"
METRICS_FILE="${METRICS_DIR}/autoloop.jsonl"

#######################################
# Ensure metrics directory exists
#######################################
metrics_init() {
    mkdir -p "$METRICS_DIR"
}

#######################################
# Append a metrics entry
# Arguments:
#   $1 - Component ID (e.g., "unit:SomeTest")
#   $2 - Outcome ("pass", "fail", "error", "skip")
#   $3 - Duration in seconds
#   $4 - Retry count
#   $5 - Retry reason (if any)
#   $6 - Phase ("red", "green", "refactor", "complete")
#   $7 - Sprint number [optional]
#   $8 - Domain [optional]
#   $9 - Test type [optional]
#######################################
metrics_append() {
    local component_id="$1"
    local outcome="$2"
    local duration="${3:-0}"
    local retry_count="${4:-0}"
    local retry_reason="${5:-}"
    local phase="${6:-unknown}"
    local sprint="${7:-0}"
    local domain="${8:-unknown}"
    local test_type="${9:-unknown}"

    metrics_init

    local timestamp=$(date -Iseconds)
    local date_only=$(date +%Y-%m-%d)
    local hour=$(date +%H)

    # Extract component name from ID (e.g., "unit:SomeTest" -> "SomeTest")
    local component_name="${component_id##*:}"

    # Build JSON object
    local json_obj
    json_obj=$(cat << EOF
{
  "timestamp": "$timestamp",
  "date": "$date_only",
  "hour": $hour,
  "component_id": "$component_id",
  "component_name": "$component_name",
  "outcome": "$outcome",
  "duration_seconds": $duration,
  "retry_count": $retry_count,
  "retry_reason": "$retry_reason",
  "phase": "$phase",
  "sprint": $sprint,
  "domain": "$domain",
  "test_type": "$test_type"
}
EOF
)

    # Compact JSON to single line and append
    echo "$json_obj" | jq -c '.' >> "$METRICS_FILE"
}

#######################################
# Append metrics from test run context
# Arguments:
#   $1 - Component ID
#   $2 - Exit code
#   $3 - Duration seconds
#   $4 - Retry count
#   $5 - Retry reason
#   $6 - Phase
#######################################
metrics_append_test_run() {
    local component_id="$1"
    local exit_code="$2"
    local duration="${3:-0}"
    local retry_count="${4:-0}"
    local retry_reason="${5:-}"
    local phase="${6:-complete}"

    local outcome="pass"
    if [[ "$exit_code" -ne 0 ]]; then
        outcome="fail"
    fi

    metrics_append "$component_id" "$outcome" "$duration" "$retry_count" "$retry_reason" "$phase"
}

#######################################
# Get metrics file path
#######################################
metrics_file_path() {
    echo "$METRICS_FILE"
}

#######################################
# Count total entries
#######################################
metrics_count() {
    if [[ -f "$METRICS_FILE" ]]; then
        wc -l < "$METRICS_FILE"
    else
        echo "0"
    fi
}

#######################################
# Get entries since a date
# Arguments:
#   $1 - Since date (ISO format or relative like "7d", "24h")
#######################################
metrics_since() {
    local since="$1"
    local since_date

    # Parse relative dates
    case "$since" in
        *d)
            local days="${since%d}"
            since_date=$(date -d "-${days} days" +%Y-%m-%d)
            ;;
        *h)
            local hours="${since%h}"
            since_date=$(date -d "-${hours} hours" -Iseconds)
            ;;
        *)
            since_date="$since"
            ;;
    esac

    if [[ ! -f "$METRICS_FILE" ]]; then
        echo "[]"
        return
    fi

    jq -s --arg since "$since_date" '[.[] | select(.timestamp >= $since)]' "$METRICS_FILE"
}
