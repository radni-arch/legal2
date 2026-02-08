#!/bin/bash

# ============================================
# Check Test Suite Status
# ============================================
# This script checks the status of running test suites
# ============================================

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

PIDS_DIR="./storage/logs/tests/pids"
LOGS_DIR="./storage/logs/tests"

echo ""
echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║              Test Suite Status Monitor                     ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

if [ ! -d "$PIDS_DIR" ]; then
    echo -e "${YELLOW}No test suites are running${NC}"
    echo ""
    exit 0
fi

all_passed=true
any_running=false

for pid_file in "$PIDS_DIR"/*.pid; do
    if [ -f "$pid_file" ]; then
        pid=$(cat "$pid_file")
        name=$(basename "$pid_file" .pid)
        log_file="${LOGS_DIR}/${name}.log"

        # Check if process is still running
        if ps -p $pid > /dev/null 2>&1; then
            echo -e "  ${YELLOW}⏳${NC} $name"
            echo -e "     Status: ${YELLOW}Running${NC}"
            echo -e "     PID: $pid"
            echo -e "     Log: $log_file"

            # Show last few lines
            if [ -f "$log_file" ]; then
                last_line=$(tail -n 1 "$log_file" 2>/dev/null)
                if [ -n "$last_line" ]; then
                    echo -e "     Last: ${last_line:0:60}..."
                fi
            fi
            echo ""
            any_running=true
        else
            # Process finished, check exit code from log
            if [ -f "$log_file" ]; then
                if grep -q "exit code: 0" "$log_file" 2>/dev/null; then
                    echo -e "  ${GREEN}✓${NC} $name"
                    echo -e "     Status: ${GREEN}Passed${NC}"
                    echo -e "     Log: $log_file"
                    echo ""
                else
                    exit_code=$(grep "exit code:" "$log_file" 2>/dev/null | tail -n 1 | grep -o '[0-9]*$')
                    echo -e "  ${RED}✗${NC} $name"
                    echo -e "     Status: ${RED}Failed (exit code: ${exit_code:-unknown})${NC}"
                    echo -e "     Log: $log_file"
                    echo ""
                    all_passed=false
                fi
            else
                echo -e "  ${RED}✗${NC} $name"
                echo -e "     Status: ${RED}No log file found${NC}"
                echo ""
                all_passed=false
            fi
        fi
    fi
done

echo -e "${CYAN}Log directory:${NC} ${LOGS_DIR}"
echo ""

if [ "$any_running" = true ]; then
    echo -e "${YELLOW}Some tests are still running...${NC}"
elif [ "$all_passed" = true ]; then
    echo -e "${GREEN}All tests passed! ✓${NC}"
else
    echo -e "${RED}Some tests failed! ✗${NC}"
fi

echo ""
