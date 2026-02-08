#!/usr/bin/env bash
#######################################
# Autoloop Resume Helper
#######################################
# Clears pause state and allows autoloop to continue.
#
# Usage:
#   ./scripts/autoloop-resume.sh           # Resume with acknowledgement
#   ./scripts/autoloop-resume.sh --status  # Show current status
#   ./scripts/autoloop-resume.sh --force   # Resume without confirmation
#   ./scripts/autoloop-resume.sh --help    # Show help
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

# State files
PAUSE_STATE_FILE="${PROJECT_DIR}/test-results/.autoloop-pause-state"
PAUSE_LOG_FILE="${PROJECT_DIR}/test-results/autoloop-pause.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

#######################################
# Show help
#######################################
show_help() {
    echo "Autoloop Resume Helper"
    echo ""
    echo "Usage: ./scripts/autoloop-resume.sh [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --status   Show current pause status"
    echo "  --force    Resume without confirmation"
    echo "  --reason   Show why autoloop was paused"
    echo "  --log      Show recent pause log entries"
    echo "  --help     Show this help"
    echo ""
    echo "When autoloop is paused, run this script to acknowledge and resume."
}

#######################################
# Show status
#######################################
show_status() {
    echo ""
    echo -e "${BLUE}═══ Autoloop Pause Status ═══${NC}"
    echo ""

    if [[ -f "$PAUSE_STATE_FILE" ]]; then
        echo -e "  Status: ${RED}PAUSED${NC}"
        echo ""
        echo -e "  ${YELLOW}Pause Details:${NC}"
        if command -v jq &>/dev/null; then
            jq '.' "$PAUSE_STATE_FILE" 2>/dev/null | sed 's/^/    /'
        else
            cat "$PAUSE_STATE_FILE" | sed 's/^/    /'
        fi
        echo ""
        echo -e "  ${CYAN}To resume: ./scripts/autoloop-resume.sh${NC}"
    else
        echo -e "  Status: ${GREEN}Not paused${NC}"
        echo ""
        echo -e "  Autoloop is running normally (or not started)."
    fi
    echo ""
}

#######################################
# Show reason
#######################################
show_reason() {
    if [[ -f "$PAUSE_STATE_FILE" ]]; then
        if command -v jq &>/dev/null; then
            echo ""
            echo -e "${YELLOW}Pause Reason:${NC}"
            jq -r '.reason // "Unknown"' "$PAUSE_STATE_FILE"
            echo ""
            echo -e "${YELLOW}Trigger Type:${NC}"
            jq -r '.trigger_type // "Unknown"' "$PAUSE_STATE_FILE"
            echo ""
        else
            cat "$PAUSE_STATE_FILE"
        fi
    else
        echo "Not currently paused."
    fi
}

#######################################
# Show log
#######################################
show_log() {
    if [[ -f "$PAUSE_LOG_FILE" ]]; then
        echo -e "${BLUE}Recent pause log entries:${NC}"
        echo ""
        tail -20 "$PAUSE_LOG_FILE"
        echo ""
    else
        echo "No pause log found."
    fi
}

#######################################
# Resume with confirmation
#######################################
do_resume() {
    local force="${1:-false}"

    if [[ ! -f "$PAUSE_STATE_FILE" ]]; then
        echo -e "${GREEN}Autoloop is not currently paused.${NC}"
        return 0
    fi

    echo ""
    echo -e "${YELLOW}═══ Resume Autoloop ═══${NC}"
    echo ""

    # Show current state
    echo -e "Current pause state:"
    if command -v jq &>/dev/null; then
        jq '.' "$PAUSE_STATE_FILE" 2>/dev/null | sed 's/^/  /'
    else
        cat "$PAUSE_STATE_FILE" | sed 's/^/  /'
    fi
    echo ""

    if [[ "$force" != "true" ]]; then
        echo -e "${CYAN}By resuming, you acknowledge:${NC}"
        echo "  - You have reviewed the failure reason"
        echo "  - You have taken any necessary corrective action"
        echo "  - You want the autoloop to continue"
        echo ""
        read -p "Resume autoloop? [y/N] " -n 1 -r
        echo ""

        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo -e "${YELLOW}Resume cancelled.${NC}"
            return 1
        fi
    fi

    # Log the resume
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[${timestamp}] [RESUME] Operator acknowledged and resumed" >> "$PAUSE_LOG_FILE"

    # Clear pause state
    rm -f "$PAUSE_STATE_FILE"

    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║              Autoloop Resumed Successfully                 ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "The autoloop will continue on its next check cycle."
    echo ""
}

#######################################
# Main
#######################################
main() {
    case "${1:-}" in
        --help|-h)
            show_help
            ;;
        --status|-s)
            show_status
            ;;
        --reason|-r)
            show_reason
            ;;
        --log|-l)
            show_log
            ;;
        --force|-f)
            do_resume true
            ;;
        "")
            do_resume false
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Use --help for usage information."
            exit 1
            ;;
    esac
}

main "$@"
