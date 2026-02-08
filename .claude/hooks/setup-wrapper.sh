#!/usr/bin/env bash
#######################################
# Setup wrapper for SessionStart hook
#######################################
# Provides immediate user feedback and launches setup in background.
# Includes context size budgeting to keep injected context bounded.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SETUP_SCRIPT="${PROJECT_DIR}/scripts/setup-all.sh"
DIGEST_SCRIPT="${PROJECT_DIR}/scripts/tdd-queue-digest.sh"
CONTEXT_SCRIPT="${PROJECT_DIR}/scripts/generate-active-component-context.sh"
SETUP_LOG="/tmp/setup-all-hook.log"
LOCK_FILE="/tmp/setup-all.lock"
STATUS_FILE="${PROJECT_DIR}/test-results/setup-status.json"
CONTEXT_FILE="${PROJECT_DIR}/test-results/context/active-component.md"
BUDGET_REPORT="${PROJECT_DIR}/test-results/context-budget.json"

#######################################
# Context Budget Configuration
#######################################
# Total budget for additionalContext (in bytes)
# ~2000 bytes ≈ ~500 tokens, keeps startup cheap
CONTEXT_BUDGET_BYTES=2000

# Per-section limits (bytes)
PRIMER_BUDGET=400
STATUS_BUDGET=300
QUEUE_BUDGET=600
COMPONENT_BUDGET=400
MESSAGE_BUDGET=300

# Track what was trimmed
TRIMMED_SECTIONS=""
BUDGET_USED=0

#######################################
# Context Budget Functions
#######################################

# Get byte size of a string
get_size() {
    printf '%s' "$1" | wc -c
}

# Trim content to max bytes, add ellipsis if trimmed
trim_to_budget() {
    local content="$1"
    local budget="$2"
    local section_name="$3"
    local size

    size=$(get_size "$content")

    if [ "$size" -le "$budget" ]; then
        echo "$content"
        return
    fi

    # Trim and add indicator
    local trimmed
    trimmed=$(printf '%s' "$content" | head -c "$((budget - 20))")
    trimmed="${trimmed}... [trimmed]"

    # Track trimmed section
    if [ -n "$TRIMMED_SECTIONS" ]; then
        TRIMMED_SECTIONS="${TRIMMED_SECTIONS}, ${section_name}"
    else
        TRIMMED_SECTIONS="${section_name}"
    fi

    echo "$trimmed"
}

# Create digest summary for oversized content
create_digest() {
    local content="$1"
    local section_name="$2"
    local line_count
    local word_count
    local hash

    line_count=$(printf '%s' "$content" | wc -l)
    word_count=$(printf '%s' "$content" | wc -w)
    hash=$(printf '%s' "$content" | md5sum | cut -c1-8)

    echo "[${section_name} digest: ${line_count} lines, ${word_count} words, hash:${hash}]"
}

# Write budget report
write_budget_report() {
    local total_size="$1"
    local trimmed="$2"
    local timestamp
    timestamp=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

    mkdir -p "$(dirname "$BUDGET_REPORT")"

    cat > "$BUDGET_REPORT" << EOF
{
  "timestamp": "${timestamp}",
  "budget_bytes": ${CONTEXT_BUDGET_BYTES},
  "used_bytes": ${total_size},
  "under_budget": $([ "$total_size" -le "$CONTEXT_BUDGET_BYTES" ] && echo "true" || echo "false"),
  "trimmed_sections": "${trimmed}",
  "sections": {
    "primer": ${PRIMER_BUDGET},
    "status": ${STATUS_BUDGET},
    "queue": ${QUEUE_BUDGET},
    "component": ${COMPONENT_BUDGET},
    "message": ${MESSAGE_BUDGET}
  }
}
EOF
}

#######################################
# Workflow Primer (compact, ~350 bytes)
#######################################
PRIMER="**Workflow Primer:**
- TDD queue: \`test-results/tdd-test-queue.json\`
- Test runner: \`./scripts/run-focused-tests.sh <TestClass>\`
- Autoloops: \`/tdd-multi-component-autoloop\`
- Session handoff: \`.claude/session-state.md\`
- Check env: \`/check-setup\`
- Full refs: \`/refs\` or \`.claude/docs/REFERENCE_INDEX.md\`"

#######################################
# Read Setup Status Summary
#######################################
get_status_summary() {
    if [ ! -f "$STATUS_FILE" ]; then
        echo "Setup: unknown (use /check-setup)"
        return
    fi

    local overall timestamp
    overall=$(grep -o '"overall": *"[^"]*"' "$STATUS_FILE" 2>/dev/null | sed 's/.*: *"\([^"]*\)"/\1/' || echo "unknown")
    timestamp=$(grep -o '"timestamp": *"[^"]*"' "$STATUS_FILE" 2>/dev/null | head -1 | sed 's/.*: *"\([^"]*\)"/\1/' || echo "unknown")

    local db migrations seed composer neo4j
    db=$(grep -A1 '"db":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")
    migrations=$(grep -A1 '"migrations":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")
    seed=$(grep -A1 '"seed":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")
    composer=$(grep -A1 '"composer":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")
    neo4j=$(grep -A1 '"neo4j":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")

    local display_ts status_upper
    display_ts=$(echo "$timestamp" | sed 's/T/ /' | cut -c1-19)
    status_upper=$(echo "$overall" | tr '[:lower:]' '[:upper:]')

    echo "Setup: ${status_upper} @ ${display_ts}"
    echo "db:${db} mig:${migrations} seed:${seed} comp:${composer} neo4j:${neo4j}"
}

#######################################
# Get TDD Queue Digest (compact)
#######################################
get_queue_digest() {
    if [ ! -x "$DIGEST_SCRIPT" ]; then
        echo ""
        return
    fi

    local digest
    digest=$("$DIGEST_SCRIPT" --limit 3 2>/dev/null) || true

    if [ -n "$digest" ]; then
        echo "$digest"
    fi
}

#######################################
# Get Active Component Context
#######################################
get_active_component() {
    if [ -x "$CONTEXT_SCRIPT" ]; then
        "$CONTEXT_SCRIPT" >/dev/null 2>&1 || true
    fi

    if [ -f "$CONTEXT_FILE" ]; then
        local header
        header=$(grep -m1 '^\*\*' "$CONTEXT_FILE" 2>/dev/null | head -1)
        if [ -n "$header" ]; then
            echo "Active: ${header}"
        fi
    fi
}

#######################################
# Build Context with Budget Enforcement
#######################################
build_context() {
    local message="$1"
    local context=""
    local total_size=0

    # 1. Status summary (required, budget-limited)
    local status_raw status_trimmed
    status_raw=$(get_status_summary)
    status_trimmed=$(trim_to_budget "$status_raw" "$STATUS_BUDGET" "status")
    context="${status_trimmed}"

    # 2. Message (required, budget-limited)
    local msg_trimmed
    msg_trimmed=$(trim_to_budget "$message" "$MESSAGE_BUDGET" "message")
    context="${context}\\n${msg_trimmed}"

    # 3. Active component (optional, budget-limited)
    local component_raw component_trimmed
    component_raw=$(get_active_component)
    if [ -n "$component_raw" ]; then
        component_trimmed=$(trim_to_budget "$component_raw" "$COMPONENT_BUDGET" "component")
        context="${context}\\n${component_trimmed}"
    fi

    # 4. Queue digest (optional, budget-limited)
    local queue_raw queue_trimmed
    queue_raw=$(get_queue_digest)
    if [ -n "$queue_raw" ]; then
        queue_trimmed=$(trim_to_budget "$queue_raw" "$QUEUE_BUDGET" "queue")
        context="${context}\\n${queue_trimmed}"
    fi

    # 5. Primer (required, budget-limited)
    local primer_trimmed
    primer_trimmed=$(trim_to_budget "$PRIMER" "$PRIMER_BUDGET" "primer")
    context="${context}\\n\\n${primer_trimmed}"

    # Calculate total size
    total_size=$(get_size "$context")

    # Add trim notice if anything was trimmed
    if [ -n "$TRIMMED_SECTIONS" ]; then
        context="${context}\\n\\n[Context trimmed: ${TRIMMED_SECTIONS}]"
    fi

    # Write budget report
    write_budget_report "$total_size" "$TRIMMED_SECTIONS"

    echo "$context"
}

#######################################
# Main Logic
#######################################

# Check if setup is already running
if [ -f "$LOCK_FILE" ] && kill -0 "$(cat "$LOCK_FILE" 2>/dev/null)" 2>/dev/null; then
    CONTEXT=$(build_context "Environment setup is in progress. Use /check-setup to monitor.")
    cat <<EOF
{
  "hookSpecificOutput": {
    "hookEventName": "SessionStart",
    "additionalContext": "${CONTEXT}"
  }
}
EOF
    exit 0
fi

# Check if setup has already completed successfully
if PGPASSWORD=password psql -U postgres -h localhost -d ai_legal_war_machine -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'" 2>/dev/null | grep -q '[1-9]'; then
    CONTEXT=$(build_context "Environment is ready.")
    cat <<EOF
{
  "hookSpecificOutput": {
    "hookEventName": "SessionStart",
    "additionalContext": "${CONTEXT}"
  }
}
EOF
    exit 0
fi

# Start setup in background
chmod +x "$SETUP_SCRIPT"
nohup bash -c "echo \$\$ > '$LOCK_FILE' && '$SETUP_SCRIPT' && rm -f '$LOCK_FILE'" > "$SETUP_LOG" 2>&1 &

CONTEXT=$(build_context "Environment setup started in background (3-5 min). Use /check-setup to monitor.")
cat <<EOF
{
  "hookSpecificOutput": {
    "hookEventName": "SessionStart",
    "additionalContext": "${CONTEXT}"
  }
}
EOF

exit 0
