#!/bin/bash
# Orchestrator Logging Library
# Provides logging functions for the main Claude session (orchestrator).
#
# Usage in hooks:
#   source .claude/lib/orchestrator-logging.sh
#   init_orchestrator_logging
#   log_tool_use "Read" "/path/to/file.php" "Gathering context"
#   finalize_orchestrator_logging "success" "Completed 5 edits"
#
# Design:
#   - Option A (boundary-only): Only SessionStart/End hooks call init/finalize
#   - Option D (full audit): PostToolUse hook also calls log_tool_use
#   - Switch between options by enabling/disabling PostToolUse hook in hooks.json

set -euo pipefail

# Globals - these persist across hook invocations via state file
ORCH_STATE_FILE="/tmp/.claude-orchestrator-state"
ORCH_LOG_DIR=""
ORCH_LOG_FILE=""
ORCH_SESSION_ID=""

# Get project root (works from any hook location)
_get_project_root() {
    if [[ -n "${CLAUDE_PROJECT_DIR:-}" ]]; then
        echo "$CLAUDE_PROJECT_DIR"
    else
        # Fallback: find .claude directory
        local dir="$PWD"
        while [[ "$dir" != "/" ]]; do
            if [[ -d "$dir/.claude" ]]; then
                echo "$dir"
                return
            fi
            dir="$(dirname "$dir")"
        done
        echo "$PWD"
    fi
}

# Initialize orchestrator logging session
# Called by SessionStart hook
init_orchestrator_logging() {
    local project_root
    project_root="$(_get_project_root)"

    ORCH_SESSION_ID="$(date +%s)-$$-$(head -c 4 /dev/urandom | xxd -p 2>/dev/null || echo "$$")"
    ORCH_LOG_DIR="$project_root/.claude/logs/orchestrator/$(date +%Y-%m-%d)"
    ORCH_LOG_FILE="$ORCH_LOG_DIR/orchestrator-$ORCH_SESSION_ID.log"

    mkdir -p "$ORCH_LOG_DIR"

    # Save state for other hooks to use
    cat > "$ORCH_STATE_FILE" <<EOF
ORCH_SESSION_ID="$ORCH_SESSION_ID"
ORCH_LOG_DIR="$ORCH_LOG_DIR"
ORCH_LOG_FILE="$ORCH_LOG_FILE"
ORCH_START_TIME="$(date -Iseconds)"
EOF

    # Write initialization entry
    _write_orch_entry "initialization" "Orchestrator session started" "Beginning session execution" "success" "{}"

    echo "$ORCH_LOG_FILE"
}

# Load state from previous hook (for PostToolUse/SessionEnd)
_load_orch_state() {
    if [[ -f "$ORCH_STATE_FILE" ]]; then
        source "$ORCH_STATE_FILE"
        return 0
    fi
    return 1
}

# Log a tool usage (called by PostToolUse hook)
# Args: $1=tool_name, $2=target (file/command), $3=purpose
log_tool_use() {
    local tool_name="${1:?Tool name required}"
    local target="${2:-}"
    local purpose="${3:-}"

    if ! _load_orch_state; then
        # No active session, skip silently
        return 0
    fi

    local context
    context=$(cat <<EOF
{"tool":"$tool_name","target":"$(echo "$target" | sed 's/"/\\"/g' | head -c 200)"}
EOF
)

    _write_orch_entry "execution" "Used $tool_name" "$purpose" "success" "$context"
}

# Log a subagent dispatch (called by PreToolUse on Task)
# Args: $1=agent_type, $2=description
log_subagent_dispatch() {
    local agent_type="${1:?Agent type required}"
    local description="${2:-}"

    if ! _load_orch_state; then
        return 0
    fi

    local context
    context=$(cat <<EOF
{"subagent":"$agent_type"}
EOF
)

    _write_orch_entry "execution" "Dispatched subagent: $agent_type" "$description" "success" "$context"
}

# Log a decision
# Args: $1=decision, $2=reasoning
log_orchestrator_decision() {
    local decision="${1:?Decision required}"
    local reasoning="${2:-}"

    if ! _load_orch_state; then
        return 0
    fi

    _write_orch_entry "planning" "Decision: $decision" "$reasoning" "success" "{}"
}

# Finalize orchestrator logging and optionally commit to git
# Args: $1=outcome, $2=summary, $3=commit_to_git (true/false, default true)
finalize_orchestrator_logging() {
    local outcome="${1:-success}"
    local summary="${2:-Session completed}"
    local commit_to_git="${3:-true}"

    if ! _load_orch_state; then
        echo "No orchestrator session to finalize" >&2
        return 0
    fi

    local end_time
    end_time="$(date -Iseconds)"

    # Calculate duration
    local start_epoch end_epoch duration_minutes
    start_epoch=$(date -d "${ORCH_START_TIME:-$(date -Iseconds)}" +%s 2>/dev/null || echo "0")
    end_epoch=$(date +%s)
    duration_minutes=$(( (end_epoch - start_epoch) / 60 ))

    local context
    context=$(cat <<EOF
{"start_time":"${ORCH_START_TIME:-unknown}","end_time":"$end_time","duration_minutes":$duration_minutes}
EOF
)

    _write_orch_entry "completion" "Orchestrator session completed" "$summary" "$outcome" "$context"

    # Update daily summary
    _update_orch_daily_summary "$outcome" "$summary" "$duration_minutes"

    # Commit logs to git if requested
    if [[ "$commit_to_git" == "true" ]]; then
        _commit_logs_to_git
    fi

    # Clean up state file
    rm -f "$ORCH_STATE_FILE"

    echo "$ORCH_LOG_FILE"
}

# Internal: Write a log entry
_write_orch_entry() {
    local phase="$1"
    local action="$2"
    local reasoning="$3"
    local outcome="$4"
    local context="$5"

    [[ -z "$ORCH_LOG_FILE" ]] && return 0

    # Escape special characters for JSON
    action=$(echo "$action" | sed 's/"/\\"/g' | tr '\n' ' ' | head -c 500)
    reasoning=$(echo "$reasoning" | sed 's/"/\\"/g' | tr '\n' ' ' | head -c 500)

    local entry
    entry=$(cat <<EOF
{"timestamp":"$(date -Iseconds)","agent_type":"orchestrator","session_id":"$ORCH_SESSION_ID","phase":"$phase","action":"$action","reasoning":"$reasoning","outcome":"$outcome","context":$context}
EOF
)
    echo "$entry" >> "$ORCH_LOG_FILE"
}

# Internal: Update daily summary
_update_orch_daily_summary() {
    local outcome="$1"
    local summary="$2"
    local duration="$3"

    local summary_file="$ORCH_LOG_DIR/summary.json"
    local end_time
    end_time="$(date -Iseconds)"

    local session_entry
    session_entry=$(cat <<EOF
{
  "session_id": "$ORCH_SESSION_ID",
  "agent_type": "orchestrator",
  "start_time": "${ORCH_START_TIME:-unknown}",
  "end_time": "$end_time",
  "duration_minutes": $duration,
  "outcome": "$outcome",
  "summary": "$(echo "$summary" | sed 's/"/\\"/g' | head -c 200)",
  "log_file": "$ORCH_LOG_FILE"
}
EOF
)

    if [[ -f "$summary_file" ]]; then
        # Append to existing (simplified approach)
        sed -i '$ d' "$summary_file" 2>/dev/null || true
        echo ",$session_entry" >> "$summary_file"
        echo "]" >> "$summary_file"
    else
        cat > "$summary_file" <<EOF
{
  "date": "$(date +%Y-%m-%d)",
  "sessions": [
$session_entry
  ]
}
EOF
    fi
}

# Internal: Commit logs to git
_commit_logs_to_git() {
    local project_root
    project_root="$(_get_project_root)"

    cd "$project_root" || return 1

    # Check if we're in a git repo
    if ! git rev-parse --git-dir > /dev/null 2>&1; then
        echo "Not in a git repository, skipping log commit" >&2
        return 0
    fi

    # Stage log files
    local log_base=".claude/logs"
    if [[ -d "$log_base" ]]; then
        git add "$log_base" 2>/dev/null || true

        # Check if there are changes to commit
        if git diff --cached --quiet 2>/dev/null; then
            # No changes
            return 0
        fi

        # Commit with session info
        local today
        today="$(date +%Y-%m-%d)"
        git commit -m "$(cat <<EOF
Logs: Session $ORCH_SESSION_ID ($today)

Auto-committed by orchestrator-logging on session end.
EOF
)" 2>/dev/null || true
    fi
}

# Get current session info (for status checks)
get_orchestrator_session_info() {
    if _load_orch_state; then
        echo "session_id=$ORCH_SESSION_ID"
        echo "log_file=$ORCH_LOG_FILE"
        echo "start_time=${ORCH_START_TIME:-unknown}"
    else
        echo "No active orchestrator session"
        return 1
    fi
}

# Export functions
export -f init_orchestrator_logging log_tool_use log_subagent_dispatch log_orchestrator_decision finalize_orchestrator_logging get_orchestrator_session_info
