#!/bin/bash
#######################################
# Iteration Log Query Tool
#
# Query structured iteration logs without parsing console output.
#
# Usage:
#   ./scripts/iteration-log-query.sh last-failure
#   ./scripts/iteration-log-query.sh stats
#   ./scripts/iteration-log-query.sh component <id>
#   ./scripts/iteration-log-query.sh recent [N]
#   ./scripts/iteration-log-query.sh rebuild
#######################################

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib/iteration-log.sh"

LOG_FILE="${ITERATION_LOG_DIR}/iterations.jsonl"

usage() {
    cat << 'EOF'
Iteration Log Query Tool

Usage:
  ./scripts/iteration-log-query.sh <command> [args]

Commands:
  last-failure          Show the last failed iteration
  last                  Show the last iteration (pass or fail)
  stats                 Show summary statistics
  component <id>        Show history for a specific component
  failures [N]          Show last N failures (default: 10)
  recent [N]            Show last N iterations (default: 10)
  rebuild               Rebuild summary from JSONL (recovery)

Examples:
  ./scripts/iteration-log-query.sh last-failure
  ./scripts/iteration-log-query.sh component "unit:SomeTest"
  ./scripts/iteration-log-query.sh failures 5
  ./scripts/iteration-log-query.sh recent 20

Output is JSON, pipe to jq for formatting:
  ./scripts/iteration-log-query.sh stats | jq '.'
EOF
}

cmd_last_failure() {
    if [ ! -f "$LOG_FILE" ]; then
        echo '{"error": "No iterations log found"}'
        return 1
    fi

    # Find last entry where test_outcome != "pass"
    local result
    result=$(tac "$LOG_FILE" 2>/dev/null | while read -r line; do
        outcome=$(echo "$line" | jq -r '.test_outcome')
        if [ "$outcome" != "pass" ]; then
            echo "$line"
            break
        fi
    done)

    if [ -z "$result" ]; then
        echo '{"message": "No failures found"}'
    else
        echo "$result" | jq '.'
    fi
}

cmd_last() {
    if [ ! -f "$LOG_FILE" ]; then
        echo '{"error": "No iterations log found"}'
        return 1
    fi

    tail -1 "$LOG_FILE" | jq '.'
}

cmd_stats() {
    iteration_log_stats | jq '.'
}

cmd_component() {
    local component_id="${1:-}"

    if [ -z "$component_id" ]; then
        echo '{"error": "Component ID required"}'
        return 1
    fi

    if [ ! -f "$LOG_FILE" ]; then
        echo '{"error": "No iterations log found"}'
        return 1
    fi

    jq -s --arg cid "$component_id" \
        '[.[] | select(.component_id == $cid)]' \
        "$LOG_FILE"
}

cmd_failures() {
    local count="${1:-10}"

    if [ ! -f "$LOG_FILE" ]; then
        echo '{"error": "No iterations log found"}'
        return 1
    fi

    jq -s '[.[] | select(.test_outcome != "pass")] | reverse | .[:'"$count"']' "$LOG_FILE"
}

cmd_recent() {
    local count="${1:-10}"

    if [ ! -f "$LOG_FILE" ]; then
        echo '{"error": "No iterations log found"}'
        return 1
    fi

    tail -"$count" "$LOG_FILE" | jq -s 'reverse'
}

cmd_rebuild() {
    iteration_log_rebuild_summary
}

# Main
case "${1:-}" in
    last-failure)
        cmd_last_failure
        ;;
    last)
        cmd_last
        ;;
    stats)
        cmd_stats
        ;;
    component)
        cmd_component "${2:-}"
        ;;
    failures)
        cmd_failures "${2:-10}"
        ;;
    recent)
        cmd_recent "${2:-10}"
        ;;
    rebuild)
        cmd_rebuild
        ;;
    -h|--help|help|"")
        usage
        ;;
    *)
        echo "Unknown command: $1"
        usage
        exit 1
        ;;
esac
