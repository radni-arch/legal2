#!/bin/bash
# PostToolUse Hook - Log Tool Usage to Orchestrator Log
#
# This hook fires after each tool execution and logs the usage
# to the orchestrator's session log. This enables full audit trail
# of what the main Claude session did.
#
# This is what makes it "Option D" (full audit). To switch to
# "Option A" (boundary-only), disable this hook in hooks.json.
#
# Input: JSON on stdin with tool info
# Output: JSON allowing the tool result

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"

# Source the orchestrator logging library
source "$PROJECT_ROOT/.claude/lib/orchestrator-logging.sh"

# Read input from stdin
INPUT=$(cat)

# Extract tool info
TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // "unknown"' 2>/dev/null || echo "unknown")

# Extract target/context based on tool type
TARGET=""
case "$TOOL_NAME" in
    Read|Write|Edit|MultiEdit|NotebookEdit)
        TARGET=$(echo "$INPUT" | jq -r '.tool_input.file_path // .tool_input.notebook_path // ""' 2>/dev/null || echo "")
        ;;
    Bash)
        # Get first 100 chars of command
        TARGET=$(echo "$INPUT" | jq -r '.tool_input.command // ""' 2>/dev/null | head -c 100 || echo "")
        ;;
    Glob)
        TARGET=$(echo "$INPUT" | jq -r '.tool_input.pattern // ""' 2>/dev/null || echo "")
        ;;
    Grep)
        TARGET=$(echo "$INPUT" | jq -r '.tool_input.pattern // ""' 2>/dev/null || echo "")
        ;;
    Task)
        TARGET=$(echo "$INPUT" | jq -r '.tool_input.subagent_type // ""' 2>/dev/null || echo "")
        ;;
    WebFetch|WebSearch)
        TARGET=$(echo "$INPUT" | jq -r '.tool_input.url // .tool_input.query // ""' 2>/dev/null || echo "")
        ;;
    TodoWrite)
        # Count todos
        TODO_COUNT=$(echo "$INPUT" | jq -r '.tool_input.todos | length' 2>/dev/null || echo "0")
        TARGET="$TODO_COUNT todos"
        ;;
    *)
        TARGET=""
        ;;
esac

# Skip logging for certain high-frequency/internal tools to reduce noise
case "$TOOL_NAME" in
    BashOutput|KillShell)
        # Skip these - too noisy
        echo '{"hookSpecificOutput":{"hookEventName":"PostToolUse"}}'
        exit 0
        ;;
esac

# Log the tool usage
log_tool_use "$TOOL_NAME" "$TARGET" "Tool execution completed"

# Output JSON response (always allow - this is just logging)
cat <<EOF
{
  "hookSpecificOutput": {
    "hookEventName": "PostToolUse",
    "logged": true,
    "tool": "$TOOL_NAME"
  }
}
EOF

exit 0
