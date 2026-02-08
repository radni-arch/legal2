#!/bin/bash
# PreToolUse Hook for Task Tool - Injects mandatory requirements into subagent prompts
#
# This hook intercepts ALL Task tool invocations and augments the prompt with:
# 1. Mandatory agent logging instructions
# 2. Iterative completion protocol (partial completion is acceptable)
#
# This is ENFORCEMENT via hook injection - every subagent gets these requirements
# automatically. No reliance on orchestrator remembering to include them.
#
# Input: JSON on stdin with tool parameters
# Output: JSON with updatedInput containing modified prompt

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"

# Read input from stdin
INPUT=$(cat)

# Extract the original prompt
ORIGINAL_PROMPT=$(echo "$INPUT" | jq -r '.tool_input.prompt // ""')
AGENT_TYPE=$(echo "$INPUT" | jq -r '.tool_input.subagent_type // "unknown"')

# Skip if no prompt (shouldn't happen, but safety check)
if [[ -z "$ORIGINAL_PROMPT" ]]; then
    echo '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"allow"}}'
    exit 0
fi

# The logging instructions to inject
LOGGING_INSTRUCTIONS=$(cat <<'LOGGING_EOF'

---
## MANDATORY: Agent Logging Protocol

You MUST log your work using the agent-logging protocol. This is NOT optional.

### At Session START (immediately):
```bash
mkdir -p .claude/logs/agents/$(date +%Y-%m-%d)
SESSION_ID="$(date +%s)-$$"
LOG_FILE=".claude/logs/agents/$(date +%Y-%m-%d)/{AGENT_TYPE}-$SESSION_ID.log"

# Log initialization
echo '{"timestamp":"'$(date -Iseconds)'","agent_type":"{AGENT_TYPE}","session_id":"'$SESSION_ID'","phase":"initialization","action":"Agent session started","reasoning":"Beginning task execution","outcome":"success"}' >> "$LOG_FILE"
```

### During Execution (log each major action):
```bash
echo '{"timestamp":"'$(date -Iseconds)'","agent_type":"{AGENT_TYPE}","session_id":"'$SESSION_ID'","phase":"execution","action":"WHAT you did","reasoning":"WHY you did it","outcome":"success|failure"}' >> "$LOG_FILE"
```

### At Session END (before final response):
```bash
echo '{"timestamp":"'$(date -Iseconds)'","agent_type":"{AGENT_TYPE}","session_id":"'$SESSION_ID'","phase":"completion","action":"Agent session completed","reasoning":"SUMMARY of what was accomplished","outcome":"success|failure"}' >> "$LOG_FILE"
```

### Required Phases
Your log MUST contain:
1. `initialization` - First entry when you start
2. At least one `execution` or `investigation` entry
3. `completion` - Final entry before responding

**YOUR TASK IS NOT COMPLETE UNTIL YOU LOG ALL PHASES.**
---

LOGGING_EOF
)

# Iterative completion protocol - partial completion is acceptable
PARTIAL_COMPLETION_PROTOCOL=$(cat <<'PARTIAL_EOF'

---
## PARTIAL COMPLETION IS ACCEPTABLE

**If you hit context limits before completing all work, that is OK.**

### When to Stop Early
- You sense context is running low
- Quality would suffer if you continue
- You've completed 50-60% of work thoroughly

### How to Report Partial Completion

**DO NOT rush remaining items.** Report honestly:

```json
{
  "completion_status": "partial",
  "items_completed": ["item1", "item2", "item3"],
  "items_remaining": ["item4", "item5", "item6"],
  "reason": "approaching context limit - stopping to maintain quality",
  "work_quality": "thorough for completed items"
}
```

### Key Rules
1. **STOP when quality would suffer** - Don't rush the last 40%
2. **Provide full detail for completed items** - Evidence for each
3. **List remaining items explicitly** - So orchestrator can continue
4. **This is NOT failure** - Honest partial > Dishonest complete

The orchestrator will dispatch additional agents for remaining items.

**NEVER rush remaining items just to claim "done".**
---

PARTIAL_EOF
)

# Replace {AGENT_TYPE} placeholder with actual agent type
LOGGING_INSTRUCTIONS="${LOGGING_INSTRUCTIONS//\{AGENT_TYPE\}/$AGENT_TYPE}"

# Combine original prompt with injected requirements
# Requirements go at the END so they're fresh in context
MODIFIED_PROMPT="${ORIGINAL_PROMPT}

${LOGGING_INSTRUCTIONS}
${PARTIAL_COMPLETION_PROTOCOL}"

# Escape the prompt for JSON (handle newlines, quotes, etc.)
ESCAPED_PROMPT=$(echo "$MODIFIED_PROMPT" | jq -Rs '.')

# Build the output JSON with modified prompt
# We need to preserve all original parameters and just update the prompt
cat <<EOF
{
  "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "allow",
    "permissionDecisionReason": "Task approved with logging and partial-completion protocol injected",
    "updatedInput": {
      "prompt": $ESCAPED_PROMPT,
      "subagent_type": "$AGENT_TYPE"
    }
  }
}
EOF
