#!/usr/bin/env bash
#######################################
# Autoloop Pause/Resume Controls
#######################################
# Human-in-the-loop override system for TDD autoloop.
# Implements pause triggers, state management, and resume protocol.
#
# Usage:
#   source scripts/lib/autoloop-pause.sh
#   pause_init
#
#   # In your loop:
#   pause_check_triggers "$exit_code" "$error_output"
#   if pause_is_paused; then
#       pause_wait_for_resume
#   fi
#
# Pause Triggers:
#   - Consecutive failures exceed threshold (default: 3)
#   - Error pattern matches configured patterns
#   - Manual pause file created
#
# Resume Methods:
#   - Delete pause state file
#   - Run: ./scripts/autoloop-resume.sh
#   - Set AUTOLOOP_FORCE_RESUME=1
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# State files
PAUSE_STATE_FILE="${PROJECT_DIR}/test-results/.autoloop-pause-state"
PAUSE_CONFIG_FILE="${PROJECT_DIR}/test-results/.autoloop-pause-config.json"
PAUSE_LOG_FILE="${PROJECT_DIR}/test-results/autoloop-pause.log"

# Default thresholds
DEFAULT_CONSECUTIVE_FAILURES=3
DEFAULT_TOTAL_FAILURES=10

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

# State variables
PAUSE_CONSECUTIVE_FAILURES=0
PAUSE_TOTAL_FAILURES=0
PAUSE_INITIALIZED=false

#######################################
# Initialize pause system
# Creates default config if not exists
#######################################
pause_init() {
    PAUSE_CONSECUTIVE_FAILURES=0
    PAUSE_TOTAL_FAILURES=0
    PAUSE_INITIALIZED=true

    # Ensure directories exist
    mkdir -p "$(dirname "$PAUSE_STATE_FILE")"
    mkdir -p "$(dirname "$PAUSE_LOG_FILE")"

    # Create default config if not exists
    if [[ ! -f "$PAUSE_CONFIG_FILE" ]]; then
        pause_create_default_config
    fi

    # Clear any stale pause state from previous runs
    # (only if AUTOLOOP_CLEAR_PAUSE is set)
    if [[ "${AUTOLOOP_CLEAR_PAUSE:-}" == "1" ]]; then
        rm -f "$PAUSE_STATE_FILE"
    fi

    # Log initialization
    pause_log "INFO" "Pause system initialized"
}

#######################################
# Create default configuration
#######################################
pause_create_default_config() {
    cat > "$PAUSE_CONFIG_FILE" << 'EOF'
{
  "enabled": true,
  "thresholds": {
    "consecutive_failures": 3,
    "total_failures": 10
  },
  "error_patterns": [
    {
      "pattern": "SQLSTATE\\[HY000\\].*too many connections",
      "description": "Database connection exhaustion",
      "action": "pause"
    },
    {
      "pattern": "Cannot allocate memory",
      "description": "Memory exhaustion",
      "action": "pause"
    },
    {
      "pattern": "Maximum execution time.*exceeded",
      "description": "Timeout - possible infinite loop",
      "action": "pause"
    },
    {
      "pattern": "Killed",
      "description": "Process killed (likely OOM)",
      "action": "pause"
    },
    {
      "pattern": "FATAL|Fatal error",
      "description": "Fatal PHP error",
      "action": "warn"
    }
  ],
  "resume_requires_acknowledgement": true,
  "auto_resume_after_minutes": 0
}
EOF
    echo -e "${CYAN}Created default pause config: ${PAUSE_CONFIG_FILE}${NC}"
}

#######################################
# Log message to pause log
#######################################
pause_log() {
    local level="$1"
    local message="$2"
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    echo "[${timestamp}] [${level}] ${message}" >> "$PAUSE_LOG_FILE"
}

#######################################
# Get config value using jq
#######################################
pause_get_config() {
    local key="$1"
    local default="${2:-}"

    if [[ -f "$PAUSE_CONFIG_FILE" ]] && command -v jq &>/dev/null; then
        local value
        value=$(jq -r "$key // empty" "$PAUSE_CONFIG_FILE" 2>/dev/null)
        if [[ -n "$value" && "$value" != "null" ]]; then
            echo "$value"
            return
        fi
    fi

    echo "$default"
}

#######################################
# Check if pause system is enabled
#######################################
pause_is_enabled() {
    local enabled
    enabled=$(pause_get_config ".enabled" "true")
    [[ "$enabled" == "true" ]]
}

#######################################
# Check if currently paused
#######################################
pause_is_paused() {
    [[ -f "$PAUSE_STATE_FILE" ]]
}

#######################################
# Get pause reason from state file
#######################################
pause_get_reason() {
    if [[ -f "$PAUSE_STATE_FILE" ]]; then
        cat "$PAUSE_STATE_FILE"
    else
        echo "Not paused"
    fi
}

#######################################
# Trigger pause with reason
#######################################
pause_trigger() {
    local reason="$1"
    local trigger_type="${2:-manual}"
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    # Write pause state
    cat > "$PAUSE_STATE_FILE" << EOF
{
  "paused_at": "${timestamp}",
  "trigger_type": "${trigger_type}",
  "reason": "${reason}",
  "consecutive_failures": ${PAUSE_CONSECUTIVE_FAILURES},
  "total_failures": ${PAUSE_TOTAL_FAILURES}
}
EOF

    pause_log "PAUSE" "Triggered: ${reason} (type: ${trigger_type})"

    # Print pause banner
    echo ""
    echo -e "${RED}╔════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                        AUTOLOOP PAUSED                             ║${NC}"
    echo -e "${RED}╠════════════════════════════════════════════════════════════════════╣${NC}"
    echo -e "${RED}║${NC} Trigger:  ${YELLOW}${trigger_type}${NC}"
    printf "${RED}║${NC} Reason:   %s\n" "$reason"
    echo -e "${RED}║${NC}"
    echo -e "${RED}║${NC} Stats:"
    echo -e "${RED}║${NC}   Consecutive failures: ${PAUSE_CONSECUTIVE_FAILURES}"
    echo -e "${RED}║${NC}   Total failures:       ${PAUSE_TOTAL_FAILURES}"
    echo -e "${RED}║${NC}"
    echo -e "${RED}╠════════════════════════════════════════════════════════════════════╣${NC}"
    echo -e "${RED}║${NC} ${GREEN}TO RESUME:${NC}"
    echo -e "${RED}║${NC}   Option 1: ${CYAN}./scripts/autoloop-resume.sh${NC}"
    echo -e "${RED}║${NC}   Option 2: ${CYAN}rm ${PAUSE_STATE_FILE}${NC}"
    echo -e "${RED}║${NC}   Option 3: ${CYAN}AUTOLOOP_FORCE_RESUME=1${NC} (env var)"
    echo -e "${RED}╚════════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

#######################################
# Check triggers and pause if needed
# Args: $1 = exit_code, $2 = error_output (optional)
# Returns: 0 if should continue, 1 if paused
#######################################
pause_check_triggers() {
    local exit_code="${1:-0}"
    local error_output="${2:-}"

    # Skip if pause system disabled
    if ! pause_is_enabled; then
        return 0
    fi

    # Skip if forced resume
    if [[ "${AUTOLOOP_FORCE_RESUME:-}" == "1" ]]; then
        return 0
    fi

    # Already paused?
    if pause_is_paused; then
        return 1
    fi

    # Track failures
    if [[ "$exit_code" -ne 0 ]]; then
        PAUSE_CONSECUTIVE_FAILURES=$((PAUSE_CONSECUTIVE_FAILURES + 1))
        PAUSE_TOTAL_FAILURES=$((PAUSE_TOTAL_FAILURES + 1))
    else
        # Reset consecutive on success
        PAUSE_CONSECUTIVE_FAILURES=0
    fi

    # Check consecutive failure threshold
    local threshold
    threshold=$(pause_get_config ".thresholds.consecutive_failures" "$DEFAULT_CONSECUTIVE_FAILURES")
    if [[ $PAUSE_CONSECUTIVE_FAILURES -ge $threshold ]]; then
        pause_trigger "Consecutive failures reached threshold (${PAUSE_CONSECUTIVE_FAILURES}/${threshold})" "consecutive_failures"
        return 1
    fi

    # Check total failure threshold
    local total_threshold
    total_threshold=$(pause_get_config ".thresholds.total_failures" "$DEFAULT_TOTAL_FAILURES")
    if [[ $PAUSE_TOTAL_FAILURES -ge $total_threshold ]]; then
        pause_trigger "Total failures reached threshold (${PAUSE_TOTAL_FAILURES}/${total_threshold})" "total_failures"
        return 1
    fi

    # Check error patterns
    if [[ -n "$error_output" ]]; then
        pause_check_error_patterns "$error_output"
        if pause_is_paused; then
            return 1
        fi
    fi

    return 0
}

#######################################
# Check error output against patterns
#######################################
pause_check_error_patterns() {
    local error_output="$1"

    if [[ ! -f "$PAUSE_CONFIG_FILE" ]] || ! command -v jq &>/dev/null; then
        return 0
    fi

    # Get patterns from config
    local patterns_count
    patterns_count=$(jq '.error_patterns | length' "$PAUSE_CONFIG_FILE" 2>/dev/null || echo "0")

    for ((i=0; i<patterns_count; i++)); do
        local pattern description action
        pattern=$(jq -r ".error_patterns[$i].pattern" "$PAUSE_CONFIG_FILE")
        description=$(jq -r ".error_patterns[$i].description" "$PAUSE_CONFIG_FILE")
        action=$(jq -r ".error_patterns[$i].action" "$PAUSE_CONFIG_FILE")

        if echo "$error_output" | grep -qE "$pattern" 2>/dev/null; then
            pause_log "MATCH" "Pattern matched: ${description}"

            if [[ "$action" == "pause" ]]; then
                pause_trigger "Error pattern matched: ${description}" "error_pattern"
                return 1
            elif [[ "$action" == "warn" ]]; then
                echo -e "${YELLOW}⚠ Warning: ${description}${NC}"
            fi
        fi
    done

    return 0
}

#######################################
# Wait for resume (blocking)
# Polls until pause state is cleared
#######################################
pause_wait_for_resume() {
    local poll_interval=5
    local waited=0

    echo ""
    echo -e "${YELLOW}Waiting for resume...${NC}"
    echo -e "${CYAN}(Checking every ${poll_interval}s for pause state to be cleared)${NC}"
    echo ""

    while pause_is_paused; do
        # Check for force resume env var
        if [[ "${AUTOLOOP_FORCE_RESUME:-}" == "1" ]]; then
            echo -e "${GREEN}Force resume detected via environment variable${NC}"
            rm -f "$PAUSE_STATE_FILE"
            break
        fi

        sleep "$poll_interval"
        waited=$((waited + poll_interval))

        # Show periodic reminder
        if [[ $((waited % 60)) -eq 0 ]]; then
            echo -e "${YELLOW}Still paused after ${waited}s. To resume: ./scripts/autoloop-resume.sh${NC}"
        fi
    done

    # Clear counters on resume
    PAUSE_CONSECUTIVE_FAILURES=0

    pause_log "RESUME" "Resumed after ${waited}s"

    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                        AUTOLOOP RESUMED                            ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

#######################################
# Clear pause state (resume)
#######################################
pause_clear() {
    if [[ -f "$PAUSE_STATE_FILE" ]]; then
        pause_log "CLEAR" "Pause state cleared"
        rm -f "$PAUSE_STATE_FILE"
        echo -e "${GREEN}✓ Pause state cleared${NC}"
    else
        echo -e "${YELLOW}Not currently paused${NC}"
    fi

    # Reset counters
    PAUSE_CONSECUTIVE_FAILURES=0
}

#######################################
# Print current pause status
#######################################
pause_status() {
    echo ""
    echo -e "${BLUE}─── Pause System Status ───${NC}"

    if pause_is_enabled; then
        echo -e "  Enabled:     ${GREEN}yes${NC}"
    else
        echo -e "  Enabled:     ${YELLOW}no${NC}"
    fi

    if pause_is_paused; then
        echo -e "  Status:      ${RED}PAUSED${NC}"
        echo ""
        echo -e "  Pause state:"
        if command -v jq &>/dev/null; then
            jq '.' "$PAUSE_STATE_FILE" 2>/dev/null | sed 's/^/    /'
        else
            cat "$PAUSE_STATE_FILE" | sed 's/^/    /'
        fi
    else
        echo -e "  Status:      ${GREEN}running${NC}"
    fi

    echo ""
    echo -e "  Consecutive failures: ${PAUSE_CONSECUTIVE_FAILURES}"
    echo -e "  Total failures:       ${PAUSE_TOTAL_FAILURES}"

    local threshold total_threshold
    threshold=$(pause_get_config ".thresholds.consecutive_failures" "$DEFAULT_CONSECUTIVE_FAILURES")
    total_threshold=$(pause_get_config ".thresholds.total_failures" "$DEFAULT_TOTAL_FAILURES")
    echo -e "  Thresholds:           consecutive=${threshold}, total=${total_threshold}"

    echo ""
    echo -e "  Config:  ${CYAN}${PAUSE_CONFIG_FILE}${NC}"
    echo -e "  State:   ${CYAN}${PAUSE_STATE_FILE}${NC}"
    echo -e "  Log:     ${CYAN}${PAUSE_LOG_FILE}${NC}"
    echo ""
}

#######################################
# Manual pause command
#######################################
pause_manual() {
    local reason="${1:-Manual pause requested by operator}"
    pause_trigger "$reason" "manual"
}

#######################################
# Record success (resets consecutive counter)
#######################################
pause_record_success() {
    PAUSE_CONSECUTIVE_FAILURES=0
    pause_log "SUCCESS" "Test passed, consecutive failures reset"
}

#######################################
# Record failure (increments counters)
#######################################
pause_record_failure() {
    local error_output="${1:-}"
    PAUSE_CONSECUTIVE_FAILURES=$((PAUSE_CONSECUTIVE_FAILURES + 1))
    PAUSE_TOTAL_FAILURES=$((PAUSE_TOTAL_FAILURES + 1))
    pause_log "FAILURE" "Consecutive: ${PAUSE_CONSECUTIVE_FAILURES}, Total: ${PAUSE_TOTAL_FAILURES}"
}
