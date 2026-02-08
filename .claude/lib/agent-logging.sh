#!/bin/bash
# Agent Logging Library
# Source this file in agent scripts to enable structured logging
#
# Usage:
#   source .claude/lib/agent-logging.sh
#   init_agent_logging "code-reviewer"
#   log_phase "investigation" "Reading source files" "Need to understand implementation"
#   log_decision "Using batch processing" "More efficient for large datasets" "streaming,single-file"
#   finalize_agent_logging "success" "Reviewed 5 files, found 3 issues"

set -euo pipefail

# Globals
AGENT_LOG_DIR=""
AGENT_LOG_FILE=""
AGENT_SESSION_ID=""
AGENT_TYPE=""
AGENT_START_TIME=""

# Initialize logging for an agent session
# Args: $1 = agent type (e.g., "code-reviewer", "tdd-tall-specialist")
init_agent_logging() {
    local agent_type="${1:?Agent type required}"

    AGENT_TYPE="$agent_type"
    AGENT_START_TIME=$(date -Iseconds)
    AGENT_SESSION_ID="$(date +%s)-$$-$(head -c 4 /dev/urandom | xxd -p)"
    AGENT_LOG_DIR="${CLAUDE_PROJECT_DIR:-.}/.claude/logs/agents/$(date +%Y-%m-%d)"
    AGENT_LOG_FILE="${AGENT_LOG_DIR}/${AGENT_TYPE}-${AGENT_SESSION_ID}.log"

    mkdir -p "$AGENT_LOG_DIR"

    # Initial log entry
    _write_log_entry "initialization" "Agent session started" "Beginning task execution" "success" "{}"

    echo "Agent logging initialized: $AGENT_LOG_FILE" >&2
}

# Log a phase transition
# Args: $1 = phase, $2 = action, $3 = reasoning, $4 = outcome (optional), $5 = context_json (optional)
log_phase() {
    local phase="${1:?Phase required}"
    local action="${2:?Action required}"
    local reasoning="${3:?Reasoning required}"
    local outcome="${4:-success}"
    local context="${5:-{}}"

    _write_log_entry "$phase" "$action" "$reasoning" "$outcome" "$context"
}

# Log a decision between alternatives
# Args: $1 = decision, $2 = reasoning, $3 = alternatives_considered (comma-separated)
log_decision() {
    local decision="${1:?Decision required}"
    local reasoning="${2:?Reasoning required}"
    local alternatives="${3:-none}"

    local context=$(cat <<EOF
{"decision":"$decision","alternatives":"$alternatives"}
EOF
)
    _write_log_entry "planning" "Decision: $decision" "$reasoning" "success" "$context"
}

# Log a blocker or issue
# Args: $1 = blocker description, $2 = impact, $3 = needed resolution
log_blocker() {
    local blocker="${1:?Blocker required}"
    local impact="${2:?Impact required}"
    local resolution="${3:?Resolution required}"

    local context=$(cat <<EOF
{"blocker":"$blocker","impact":"$impact","resolution_needed":"$resolution"}
EOF
)
    _write_log_entry "execution" "BLOCKER: $blocker" "Impact: $impact. Need: $resolution" "blocked" "$context"
}

# Log file operations
# Args: $1 = operation (read/write/modify), $2 = file_path, $3 = reason
log_file_op() {
    local operation="${1:?Operation required}"
    local file_path="${2:?File path required}"
    local reason="${3:?Reason required}"

    local context=$(cat <<EOF
{"file_operation":"$operation","file":"$file_path"}
EOF
)
    _write_log_entry "execution" "${operation^} file: $file_path" "$reason" "success" "$context"
}

# Log tool usage
# Args: $1 = tool name, $2 = purpose, $3 = outcome
log_tool() {
    local tool="${1:?Tool required}"
    local purpose="${2:?Purpose required}"
    local outcome="${3:-success}"

    local context=$(cat <<EOF
{"tool":"$tool"}
EOF
)
    _write_log_entry "execution" "Used tool: $tool" "$purpose" "$outcome" "$context"
}

# Log test execution
# Args: $1 = test_command, $2 = passed (true/false), $3 = details
log_test() {
    local test_cmd="${1:?Test command required}"
    local passed="${2:?Pass status required}"
    local details="${3:-}"

    local outcome="success"
    [[ "$passed" != "true" ]] && outcome="failure"

    local context=$(cat <<EOF
{"test_command":"$test_cmd","passed":$passed}
EOF
)
    _write_log_entry "verification" "Test: $test_cmd" "Result: $passed. $details" "$outcome" "$context"
}

# Finalize agent logging session
# Args: $1 = final outcome, $2 = summary
finalize_agent_logging() {
    local outcome="${1:?Outcome required}"
    local summary="${2:?Summary required}"

    local end_time=$(date -Iseconds)
    local context=$(cat <<EOF
{"start_time":"$AGENT_START_TIME","end_time":"$end_time"}
EOF
)
    _write_log_entry "completion" "Agent session completed" "$summary" "$outcome" "$context"

    # Update daily summary
    _update_daily_summary "$outcome" "$summary"

    echo "Agent logging finalized: $AGENT_LOG_FILE" >&2
}

# Internal: Write a log entry
_write_log_entry() {
    local phase="$1"
    local action="$2"
    local reasoning="$3"
    local outcome="$4"
    local context="$5"

    # Escape special characters in strings for JSON
    action=$(echo "$action" | sed 's/"/\\"/g' | tr '\n' ' ')
    reasoning=$(echo "$reasoning" | sed 's/"/\\"/g' | tr '\n' ' ')

    local entry=$(cat <<EOF
{"timestamp":"$(date -Iseconds)","agent_type":"$AGENT_TYPE","session_id":"$AGENT_SESSION_ID","phase":"$phase","action":"$action","reasoning":"$reasoning","outcome":"$outcome","context":$context}
EOF
)
    echo "$entry" >> "$AGENT_LOG_FILE"
}

# Internal: Update the daily summary file
_update_daily_summary() {
    local outcome="$1"
    local summary="$2"

    local summary_file="${AGENT_LOG_DIR}/summary.json"
    local end_time=$(date -Iseconds)

    # Calculate duration (approximate)
    local start_epoch=$(date -d "$AGENT_START_TIME" +%s 2>/dev/null || echo "0")
    local end_epoch=$(date +%s)
    local duration_minutes=$(( (end_epoch - start_epoch) / 60 ))

    # Create or update summary
    local session_entry=$(cat <<EOF
{
  "session_id": "$AGENT_SESSION_ID",
  "agent_type": "$AGENT_TYPE",
  "start_time": "$AGENT_START_TIME",
  "end_time": "$end_time",
  "duration_minutes": $duration_minutes,
  "outcome": "$outcome",
  "summary": "$(echo "$summary" | sed 's/"/\\"/g')",
  "log_file": "$AGENT_LOG_FILE"
}
EOF
)

    # Append to summary file (simple approach - not full JSON merge)
    if [[ -f "$summary_file" ]]; then
        # Remove closing bracket, add comma and new entry
        sed -i '$ d' "$summary_file"  # Remove last line (])
        echo ",$session_entry" >> "$summary_file"
        echo "]" >> "$summary_file"
    else
        # Create new summary file
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

# Export functions for subshells
export -f init_agent_logging log_phase log_decision log_blocker log_file_op log_tool log_test finalize_agent_logging
