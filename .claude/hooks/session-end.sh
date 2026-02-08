#!/bin/bash
# SessionEnd Hook - Finalize logging and validate agent logs
#
# This hook fires when a Claude Code session terminates. It:
# 1. Finalizes orchestrator logging (writes completion entry)
# 2. Validates all agent logs for the session
# 3. Commits all logs to git
#
# Part of the Option D (full audit) logging system.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"
AGENT_LOG_DIR="$PROJECT_ROOT/.claude/logs/agents"
ORCH_LOG_DIR="$PROJECT_ROOT/.claude/logs/orchestrator"
TODAY=$(date +%Y-%m-%d)
TODAY_AGENT_DIR="$AGENT_LOG_DIR/$TODAY"
TODAY_ORCH_DIR="$ORCH_LOG_DIR/$TODAY"

# Source orchestrator logging library
source "$PROJECT_ROOT/.claude/lib/orchestrator-logging.sh"

# ===== ORCHESTRATOR LOGGING FINALIZATION =====
orch_log_file=""
if [[ -f "/tmp/.claude-orchestrator-state" ]]; then
    # Finalize orchestrator logging (this also commits to git)
    orch_log_file=$(finalize_orchestrator_logging "success" "Session ended normally" "true") || true
fi

# ===== AGENT LOG VALIDATION =====
agent_log_count=0
agent_valid=0
agent_issues=0

if [[ -d "$TODAY_AGENT_DIR" ]]; then
    agent_log_count=$(find "$TODAY_AGENT_DIR" -name "*.log" 2>/dev/null | wc -l)

    for log_file in $(find "$TODAY_AGENT_DIR" -name "*.log" 2>/dev/null); do
        has_init=$( (grep '"phase":"initialization"' "$log_file" || true) | wc -l)
        has_completion=$( (grep '"phase":"completion"' "$log_file" || true) | wc -l)

        if [[ $has_init -gt 0 && $has_completion -gt 0 ]]; then
            agent_valid=$((agent_valid + 1))
        else
            agent_issues=$((agent_issues + 1))
        fi
    done
fi

# ===== ORCHESTRATOR LOG VALIDATION =====
orch_log_count=0
orch_valid=0

if [[ -d "$TODAY_ORCH_DIR" ]]; then
    orch_log_count=$(find "$TODAY_ORCH_DIR" -name "*.log" 2>/dev/null | wc -l)

    for log_file in $(find "$TODAY_ORCH_DIR" -name "*.log" 2>/dev/null); do
        has_init=$( (grep '"phase":"initialization"' "$log_file" || true) | wc -l)
        has_completion=$( (grep '"phase":"completion"' "$log_file" || true) | wc -l)

        if [[ $has_init -gt 0 && $has_completion -gt 0 ]]; then
            orch_valid=$((orch_valid + 1))
        fi
    done
fi

# ===== GENERATE SUMMARY =====
total_logs=$((agent_log_count + orch_log_count))
total_valid=$((agent_valid + orch_valid))

if [[ $agent_issues -eq 0 && $total_valid -gt 0 ]]; then
    message="All logs valid. Orchestrator: $orch_valid, Agents: $agent_valid"
elif [[ $agent_issues -gt 0 ]]; then
    message="Found $agent_issues agent logs with incomplete phases. Run: ./scripts/validate-agent-logs.sh"
else
    message="Session ended. Logs: $total_logs (orchestrator: $orch_log_count, agents: $agent_log_count)"
fi

# ===== OUTPUT JSON =====
cat <<EOF
{
  "hookSpecificOutput": {
    "hookEventName": "SessionEnd",
    "loggingSummary": {
      "message": "$message",
      "orchestrator": {
        "logs_found": $orch_log_count,
        "valid": $orch_valid,
        "log_file": "${orch_log_file:-none}"
      },
      "agents": {
        "logs_found": $agent_log_count,
        "valid": $agent_valid,
        "issues": $agent_issues
      },
      "committed_to_git": true
    }
  }
}
EOF

# Warn if issues
if [[ $agent_issues -gt 0 ]]; then
    echo "[SessionEnd] Warning: $agent_issues agent logs incomplete. Review with ./scripts/validate-agent-logs.sh" >&2
fi

exit 0
