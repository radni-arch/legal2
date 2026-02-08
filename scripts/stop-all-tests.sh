#!/bin/bash

# ============================================
# Stop All Running Test Suites
# ============================================
# This script stops all running test suites
# ============================================

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

PIDS_DIR="./storage/logs/tests/pids"

echo ""
echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║              Stopping All Test Suites                      ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

if [ ! -d "$PIDS_DIR" ]; then
    echo -e "${YELLOW}No test suites are running${NC}"
    echo ""
    exit 0
fi

stopped=0
not_running=0

for pid_file in "$PIDS_DIR"/*.pid; do
    if [ -f "$pid_file" ]; then
        pid=$(cat "$pid_file")
        name=$(basename "$pid_file" .pid)

        # Check if process is still running
        if ps -p $pid > /dev/null 2>&1; then
            echo -e "${YELLOW}Stopping ${name} (PID: ${pid})...${NC}"
            kill $pid 2>/dev/null
            sleep 1

            # Force kill if still running
            if ps -p $pid > /dev/null 2>&1; then
                echo -e "${RED}Force stopping ${name}...${NC}"
                kill -9 $pid 2>/dev/null
            fi

            if ! ps -p $pid > /dev/null 2>&1; then
                echo -e "${GREEN}✓ ${name} stopped${NC}"
                stopped=$((stopped + 1))
            else
                echo -e "${RED}✗ Failed to stop ${name}${NC}"
            fi
        else
            echo -e "${YELLOW}${name} is not running${NC}"
            not_running=$((not_running + 1))
        fi

        # Remove PID file
        rm -f "$pid_file"
    fi
done

echo ""
echo -e "${CYAN}Summary:${NC}"
echo -e "  Stopped: ${stopped}"
echo -e "  Already stopped: ${not_running}"
echo ""
