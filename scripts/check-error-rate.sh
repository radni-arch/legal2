#!/bin/bash
set -euo pipefail

#######################################
# Error Rate Monitoring Script
#
# Monitors Laravel error logs and alerts on spikes.
# Designed to work with external monitoring systems.
#
# Features:
# - Parse laravel.log for errors
# - Count errors in configurable time window
# - Alert if threshold exceeded
# - Output in monitoring-friendly format
#
# Exit codes:
#   0 - OK (under threshold)
#   1 - CRITICAL (over threshold)
#   2 - WARNING (approaching threshold)
#   3 - UNKNOWN (log file issues)
#
# Usage:
#   ./scripts/check-error-rate.sh
#   ./scripts/check-error-rate.sh --threshold 50 --window 3600
#   ./scripts/check-error-rate.sh --json
#######################################

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
LOG_FILE="${LARAVEL_LOG:-${PROJECT_DIR}/storage/logs/laravel.log}"
THRESHOLD="${THRESHOLD:-100}"           # Errors per window to trigger alert
WARNING_THRESHOLD="${WARNING_THRESHOLD:-50}"  # Warning level
TIME_WINDOW="${TIME_WINDOW:-3600}"       # Seconds (default: 1 hour)
OUTPUT_JSON=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --threshold)
            THRESHOLD="$2"
            shift 2
            ;;
        --warning)
            WARNING_THRESHOLD="$2"
            shift 2
            ;;
        --window)
            TIME_WINDOW="$2"
            shift 2
            ;;
        --log)
            LOG_FILE="$2"
            shift 2
            ;;
        --json)
            OUTPUT_JSON=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --threshold N   Error count to trigger CRITICAL (default: 100)"
            echo "  --warning N     Error count to trigger WARNING (default: 50)"
            echo "  --window N      Time window in seconds (default: 3600)"
            echo "  --log PATH      Path to Laravel log file"
            echo "  --json          Output in JSON format"
            echo "  --help          Show this help message"
            echo ""
            echo "Exit codes:"
            echo "  0 - OK (under threshold)"
            echo "  1 - CRITICAL (over threshold)"
            echo "  2 - WARNING (approaching threshold)"
            echo "  3 - UNKNOWN (log file issues)"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 3
            ;;
    esac
done

#######################################
# Output Functions
#######################################

output_result() {
    local status="$1"
    local message="$2"
    local error_count="$3"
    local window_minutes=$((TIME_WINDOW / 60))

    if [ "$OUTPUT_JSON" = true ]; then
        cat << EOF
{
    "status": "$status",
    "message": "$message",
    "metrics": {
        "error_count": $error_count,
        "threshold": $THRESHOLD,
        "warning_threshold": $WARNING_THRESHOLD,
        "time_window_seconds": $TIME_WINDOW,
        "time_window_minutes": $window_minutes,
        "log_file": "$LOG_FILE"
    },
    "timestamp": "$(date -Iseconds)"
}
EOF
    else
        echo "$status - $message | errors=${error_count};${WARNING_THRESHOLD};${THRESHOLD};0"
    fi
}

#######################################
# Pre-flight Checks
#######################################

# Check if log file exists
if [ ! -f "$LOG_FILE" ]; then
    output_result "UNKNOWN" "Log file not found: $LOG_FILE" 0
    exit 3
fi

# Check if log file is readable
if [ ! -r "$LOG_FILE" ]; then
    output_result "UNKNOWN" "Log file not readable: $LOG_FILE" 0
    exit 3
fi

#######################################
# Count Errors
#######################################

# Calculate the cutoff timestamp
CUTOFF_TIME=$(date -d "-${TIME_WINDOW} seconds" '+%Y-%m-%d %H:%M:%S' 2>/dev/null) || \
CUTOFF_TIME=$(date -v-${TIME_WINDOW}S '+%Y-%m-%d %H:%M:%S' 2>/dev/null) || \
CUTOFF_TIME=""

# If we can't calculate cutoff, just count all errors
if [ -z "$CUTOFF_TIME" ]; then
    # Fallback: count errors from last N lines (estimate based on typical log rate)
    ESTIMATE_LINES=$((TIME_WINDOW * 10))  # Assume ~10 lines per second max
    ERROR_COUNT=$(tail -n "$ESTIMATE_LINES" "$LOG_FILE" 2>/dev/null | \
        grep -c -E '^\[.*\] .*(ERROR|CRITICAL|ALERT|EMERGENCY)' 2>/dev/null || echo 0)
else
    # Count errors after cutoff time
    # Laravel log format: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
    ERROR_COUNT=0

    while IFS= read -r line; do
        # Extract timestamp from log line
        if [[ "$line" =~ ^\[([0-9]{4}-[0-9]{2}-[0-9]{2}\ [0-9]{2}:[0-9]{2}:[0-9]{2})\] ]]; then
            LOG_TIME="${BASH_REMATCH[1]}"

            # Compare timestamps (string comparison works for ISO format)
            if [[ "$LOG_TIME" > "$CUTOFF_TIME" ]] || [[ "$LOG_TIME" == "$CUTOFF_TIME" ]]; then
                # Check if it's an error-level entry
                if echo "$line" | grep -qE '\.(ERROR|CRITICAL|ALERT|EMERGENCY):'; then
                    ERROR_COUNT=$((ERROR_COUNT + 1))
                fi
            fi
        fi
    done < "$LOG_FILE"
fi

#######################################
# Categorize Errors (optional detail)
#######################################

# Get error breakdown for context
if [ "$OUTPUT_JSON" = true ]; then
    EXCEPTION_COUNT=$(tail -n 5000 "$LOG_FILE" 2>/dev/null | \
        grep -c 'Exception' 2>/dev/null || echo 0)
    QUERY_ERROR_COUNT=$(tail -n 5000 "$LOG_FILE" 2>/dev/null | \
        grep -c -E 'SQLSTATE|QueryException' 2>/dev/null || echo 0)
    HTTP_ERROR_COUNT=$(tail -n 5000 "$LOG_FILE" 2>/dev/null | \
        grep -c -E 'HttpException|NotFoundHttpException|MethodNotAllowedHttpException' 2>/dev/null || echo 0)
fi

#######################################
# Evaluate Results
#######################################

WINDOW_MINUTES=$((TIME_WINDOW / 60))

if [ "$ERROR_COUNT" -ge "$THRESHOLD" ]; then
    output_result "CRITICAL" "${ERROR_COUNT} errors in last ${WINDOW_MINUTES} minutes (threshold: ${THRESHOLD})" "$ERROR_COUNT"
    exit 1
elif [ "$ERROR_COUNT" -ge "$WARNING_THRESHOLD" ]; then
    output_result "WARNING" "${ERROR_COUNT} errors in last ${WINDOW_MINUTES} minutes (warning: ${WARNING_THRESHOLD})" "$ERROR_COUNT"
    exit 2
else
    output_result "OK" "${ERROR_COUNT} errors in last ${WINDOW_MINUTES} minutes" "$ERROR_COUNT"
    exit 0
fi
