#!/bin/bash
# SessionStart Hook - Initialize Orchestrator Logging
#
# This hook initializes logging for the main Claude session (orchestrator).
# Called on session start/resume to create a new log file.
#
# Part of the Option D (full audit) logging system:
# - SessionStart: This hook (init)
# - PostToolUse: Log each tool usage
# - SessionEnd: Finalize and commit to git
#
# For Option A (boundary-only), disable the PostToolUse hook in hooks.json.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"

# Source the orchestrator logging library
source "$PROJECT_ROOT/.claude/lib/orchestrator-logging.sh"

# Initialize orchestrator logging
LOG_FILE=$(init_orchestrator_logging)

# Output JSON response
cat <<EOF
{
  "hookSpecificOutput": {
    "hookEventName": "SessionStart",
    "orchestratorLogging": {
      "status": "initialized",
      "log_file": "$LOG_FILE",
      "mode": "full_audit"
    }
  }
}
EOF

exit 0
