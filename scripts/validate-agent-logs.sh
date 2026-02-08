#!/bin/bash
# Agent Log Validation Script
# Validates that subagents have created required log files
#
# Usage:
#   ./scripts/validate-agent-logs.sh                    # Check today's logs
#   ./scripts/validate-agent-logs.sh --since "1 hour"   # Check logs from last hour
#   ./scripts/validate-agent-logs.sh --expect 3         # Expect at least 3 agent sessions
#   ./scripts/validate-agent-logs.sh --strict           # Fail if any issues found

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
AGENT_LOG_DIR="$PROJECT_ROOT/.claude/logs/agents"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Defaults
SINCE=""
EXPECT_COUNT=0
STRICT_MODE=false
TODAY=$(date +%Y-%m-%d)

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --since)
            SINCE="$2"
            shift 2
            ;;
        --expect)
            EXPECT_COUNT="$2"
            shift 2
            ;;
        --strict)
            STRICT_MODE=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --since TIME    Check logs since TIME (e.g., '1 hour', '30 minutes')"
            echo "  --expect N      Expect at least N agent sessions"
            echo "  --strict        Exit with error if any validation issues found"
            echo "  --help          Show this help message"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

echo -e "${BLUE}=== Agent Log Validation ===${NC}"
echo ""

# Check if log directory exists
if [[ ! -d "$AGENT_LOG_DIR" ]]; then
    echo -e "${RED}ERROR: Agent log directory does not exist: $AGENT_LOG_DIR${NC}"
    echo "Create it with: mkdir -p $AGENT_LOG_DIR"
    exit 1
fi

# Find log files
if [[ -n "$SINCE" ]]; then
    # Find files modified since the specified time
    SINCE_SECONDS=$(date -d "$SINCE ago" +%s 2>/dev/null || echo "0")
    LOG_FILES=$(find "$AGENT_LOG_DIR" -name "*.log" -newermt "@$SINCE_SECONDS" 2>/dev/null | sort)
else
    # Find today's log files
    TODAY_DIR="$AGENT_LOG_DIR/$TODAY"
    if [[ -d "$TODAY_DIR" ]]; then
        LOG_FILES=$(find "$TODAY_DIR" -name "*.log" 2>/dev/null | sort)
    else
        LOG_FILES=""
    fi
fi

# Count log files
if [[ -z "$LOG_FILES" ]]; then
    LOG_COUNT=0
else
    LOG_COUNT=$(echo "$LOG_FILES" | grep -c "\.log$" 2>/dev/null || echo "0")
fi

echo -e "${BLUE}Log files found: ${LOG_COUNT}${NC}"

if [[ $LOG_COUNT -eq 0 ]]; then
    echo -e "${YELLOW}WARNING: No agent log files found${NC}"

    if [[ $EXPECT_COUNT -gt 0 ]]; then
        echo -e "${RED}FAIL: Expected at least $EXPECT_COUNT agent sessions, found 0${NC}"
        echo ""
        echo "This indicates agents were dispatched but did not log their work."
        echo ""
        echo "To fix:"
        echo "1. Ensure agents use the 'agent-logging' skill"
        echo "2. Check that agents call init_agent_logging() at start"
        echo "3. Verify agents call finalize_agent_logging() at end"
        exit 1
    fi

    if [[ "$STRICT_MODE" == "true" ]]; then
        exit 1
    fi
    exit 0
fi

echo ""
echo -e "${BLUE}=== Log File Analysis ===${NC}"

ISSUES_FOUND=0
VALID_SESSIONS=0

for log_file in $LOG_FILES; do
    if [[ ! -f "$log_file" ]]; then
        continue
    fi

    filename=$(basename "$log_file")
    echo ""
    echo -e "${BLUE}Checking: $filename${NC}"

    # Check file is not empty
    if [[ ! -s "$log_file" ]]; then
        echo -e "  ${RED}✗ Empty log file${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
        continue
    fi

    # Check for required phases (use || true to prevent grep exit code from failing with pipefail)
    has_init=$( (grep '"phase":"initialization"' "$log_file" || true) | wc -l | tr -d ' ')
    has_completion=$( (grep '"phase":"completion"' "$log_file" || true) | wc -l | tr -d ' ')
    has_investigation=$( (grep '"phase":"investigation"' "$log_file" || true) | wc -l | tr -d ' ')

    entry_count=$(wc -l < "$log_file")

    echo -e "  Entries: $entry_count"

    # Validate initialization
    if [[ $has_init -eq 0 ]]; then
        echo -e "  ${RED}✗ Missing initialization phase${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    else
        echo -e "  ${GREEN}✓ Has initialization phase${NC}"
    fi

    # Validate completion
    if [[ $has_completion -eq 0 ]]; then
        echo -e "  ${YELLOW}⚠ Missing completion phase (agent may not have finished properly)${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    else
        echo -e "  ${GREEN}✓ Has completion phase${NC}"
    fi

    # Validate investigation/execution
    if [[ $has_investigation -eq 0 ]]; then
        echo -e "  ${YELLOW}⚠ No investigation entries (minimal logging)${NC}"
    else
        echo -e "  ${GREEN}✓ Has investigation entries${NC}"
    fi

    # Check for reasoning in entries
    has_reasoning=$( (grep '"reasoning":' "$log_file" || true) | wc -l | tr -d ' ')
    if [[ $has_reasoning -lt $entry_count ]]; then
        echo -e "  ${YELLOW}⚠ Some entries missing reasoning${NC}"
    else
        echo -e "  ${GREEN}✓ All entries have reasoning${NC}"
    fi

    # Extract agent type and session info
    agent_type=$(head -1 "$log_file" | jq -r '.agent_type // "unknown"' 2>/dev/null || echo "unknown")
    session_id=$(head -1 "$log_file" | jq -r '.session_id // "unknown"' 2>/dev/null || echo "unknown")

    echo -e "  Agent: $agent_type"
    echo -e "  Session: $session_id"

    if [[ $has_init -gt 0 && $has_completion -gt 0 ]]; then
        VALID_SESSIONS=$((VALID_SESSIONS + 1))
    fi
done

echo ""
echo -e "${BLUE}=== Summary ===${NC}"
echo -e "Total log files: $LOG_COUNT"
echo -e "Valid sessions (init + completion): $VALID_SESSIONS"
echo -e "Issues found: $ISSUES_FOUND"

# Check expected count
if [[ $EXPECT_COUNT -gt 0 && $VALID_SESSIONS -lt $EXPECT_COUNT ]]; then
    echo ""
    echo -e "${RED}FAIL: Expected at least $EXPECT_COUNT agent sessions, found $VALID_SESSIONS${NC}"
    exit 1
fi

# Final status
if [[ $ISSUES_FOUND -gt 0 ]]; then
    echo ""
    echo -e "${YELLOW}Some issues found. Review above for details.${NC}"
    if [[ "$STRICT_MODE" == "true" ]]; then
        exit 1
    fi
else
    echo ""
    echo -e "${GREEN}All agent logs validated successfully.${NC}"
fi

exit 0
