#!/bin/bash
# SubagentStop Hook - Validates agent logging after subagent completion
#
# This hook fires when a subagent (Task tool) completes. It checks if
# the agent created a log file as required by the agent-logging protocol.
#
# Note: We can't identify the specific agent that just finished, so we
# check for recent log activity and warn if logs appear missing.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"
AGENT_LOG_DIR="$PROJECT_ROOT/.claude/logs/agents"
TODAY=$(date +%Y-%m-%d)
TODAY_LOG_DIR="$AGENT_LOG_DIR/$TODAY"

# Track when we last checked (to avoid spamming warnings)
LAST_CHECK_FILE="/tmp/.claude-agent-log-check"

# Function to output JSON response
output_json() {
    local message="$1"
    local status="${2:-success}"
    cat <<EOF
{
  "hookSpecificOutput": {
    "hookEventName": "SubagentStop",
    "status": "$status",
    "message": "$message"
  }
}
EOF
}

# Check if this is a recent check (within 30 seconds)
if [[ -f "$LAST_CHECK_FILE" ]]; then
    last_check=$(cat "$LAST_CHECK_FILE")
    current_time=$(date +%s)
    if (( current_time - last_check < 30 )); then
        # Too soon since last check, skip
        output_json "Skipped - recent check"
        exit 0
    fi
fi

# Update last check time
date +%s > "$LAST_CHECK_FILE"

# Check if log directory exists
if [[ ! -d "$TODAY_LOG_DIR" ]]; then
    output_json "Warning: No agent logs for today. If you dispatched agents, they may not be logging." "warning"
    exit 0
fi

# Count log files created in the last 5 minutes
recent_logs=$(find "$TODAY_LOG_DIR" -name "*.log" -mmin -5 2>/dev/null | wc -l)

if [[ $recent_logs -eq 0 ]]; then
    # Check if any logs exist at all today
    total_logs=$(find "$TODAY_LOG_DIR" -name "*.log" 2>/dev/null | wc -l)

    if [[ $total_logs -eq 0 ]]; then
        output_json "Warning: Agent completed but no logs found. Verify agent used logging protocol." "warning"
    else
        output_json "Agent logs present ($total_logs today), but no recent activity."
    fi
else
    # Recent logs found - check if they have completion phase
    incomplete=0
    for log_file in $(find "$TODAY_LOG_DIR" -name "*.log" -mmin -5 2>/dev/null); do
        has_completion=$(grep -c '"phase":"completion"' "$log_file" 2>/dev/null || echo "0")
        if [[ $has_completion -eq 0 ]]; then
            ((incomplete++))
        fi
    done

    if [[ $incomplete -gt 0 ]]; then
        output_json "Found $incomplete recent agent logs without completion phase." "info"
    else
        output_json "Agent logged properly ($recent_logs recent logs with completion)." "success"
    fi
fi

exit 0
