#!/bin/bash

# ============================================
# View Test Suite Logs
# ============================================
# This script allows viewing specific test suite logs
#
# Usage:
#   ./scripts/view-test-logs.sh [suite-name]
#   ./scripts/view-test-logs.sh dusk
#   ./scripts/view-test-logs.sh unit
#   ./scripts/view-test-logs.sh feature
#   ./scripts/view-test-logs.sh integration
#   ./scripts/view-test-logs.sh all
# ============================================

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

LOGS_DIR="./storage/logs"

# Function to show available logs
show_available_logs() {
    echo ""
    echo -e "${CYAN}Available test logs:${NC}"
    echo ""
    for log_file in "$LOGS_DIR"/*.log; do
        if [ -f "$log_file" ]; then
            name=$(basename "$log_file" .log)
            size=$(du -h "$log_file" | cut -f1)
            echo -e "  ${GREEN}•${NC} $name ($size)"
        fi
    done
    echo ""
}

# Function to tail a specific log
tail_log() {
    local log_name=$1
    local log_file="${LOGS_DIR}/${log_name}.log"

    if [ ! -f "$log_file" ]; then
        echo -e "${RED}Error: Log file not found: $log_file${NC}"
        show_available_logs
        exit 1
    fi

    echo ""
    echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║              Viewing: ${log_name}                          ${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${YELLOW}Press Ctrl+C to stop${NC}"
    echo ""

    tail -f "$log_file" | while IFS= read -r line; do
        case "$line" in
            *"[START]"*)
                echo -e "${YELLOW}${line}${NC}"
                ;;
            *"[END]"*)
                echo -e "${YELLOW}${line}${NC}"
                ;;
            *"PASS"*|*"✓"*|*"OK"*)
                echo -e "${GREEN}${line}${NC}"
                ;;
            *"FAIL"*|*"✗"*|*"ERROR"*)
                echo -e "${RED}${line}${NC}"
                ;;
            *"WARN"*)
                echo -e "${YELLOW}${line}${NC}"
                ;;
            *)
                echo "$line"
                ;;
        esac
    done
}

# Main logic
if [ $# -eq 0 ]; then
    echo ""
    echo -e "${CYAN}Usage: $0 <suite-name>${NC}"
    echo ""
    echo "Examples:"
    echo "  $0 dusk          # View Dusk test logs"
    echo "  $0 unit          # View Unit test logs"
    echo "  $0 feature       # View Feature test logs"
    echo "  $0 integration   # View Integration test logs"
    echo "  $0 all           # View all test logs"
    show_available_logs
    exit 1
fi

SUITE=$1

if [ ! -d "$LOGS_DIR" ]; then
    echo -e "${RED}Error: Logs directory not found: $LOGS_DIR${NC}"
    echo -e "${YELLOW}Have you run tests yet?${NC}"
    echo ""
    exit 1
fi

case "$SUITE" in
    dusk)
        tail_log "Dusk Tests"
        ;;
    unit)
        tail_log "Unit Tests"
        ;;
    feature)
        tail_log "Feature Tests"
        ;;
    integration)
        tail_log "Integration Tests"
        ;;
    all)
        echo ""
        echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${CYAN}║              Viewing: All Test Logs                        ║${NC}"
        echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
        echo ""
        echo -e "${YELLOW}Press Ctrl+C to stop${NC}"
        echo ""

        tail -f "$LOGS_DIR"/*.log 2>/dev/null | while IFS= read -r line; do
            case "$line" in
                *"Dusk Tests"*)
                    echo -e "${MAGENTA}╔═══ DUSK TESTS ════════════════════════════════════╗${NC}"
                    ;;
                *"Unit Tests"*)
                    echo -e "${CYAN}╔═══ UNIT TESTS ════════════════════════════════════╗${NC}"
                    ;;
                *"Feature Tests"*)
                    echo -e "${BLUE}╔═══ FEATURE TESTS ═════════════════════════════════╗${NC}"
                    ;;
                *"Integration Tests"*)
                    echo -e "${GREEN}╔═══ INTEGRATION TESTS ═════════════════════════════╗${NC}"
                    ;;
                *"[START]"*)
                    echo -e "${YELLOW}${line}${NC}"
                    ;;
                *"[END]"*)
                    echo -e "${YELLOW}${line}${NC}"
                    ;;
                *"PASS"*|*"✓"*|*"OK"*)
                    echo -e "${GREEN}${line}${NC}"
                    ;;
                *"FAIL"*|*"✗"*|*"ERROR"*)
                    echo -e "${RED}${line}${NC}"
                    ;;
                *"WARN"*)
                    echo -e "${YELLOW}${line}${NC}"
                    ;;
                *)
                    echo "$line"
                    ;;
            esac
        done
        ;;
    *)
        echo -e "${RED}Error: Unknown test suite: $SUITE${NC}"
        show_available_logs
        exit 1
        ;;
esac
