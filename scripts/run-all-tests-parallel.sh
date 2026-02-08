#!/bin/bash

# ============================================
# Parallel Test Suite Runner with Logging
# ============================================
# This script runs all test suites in parallel with nohup
# and tails their logs in real-time
#
# Usage:
#   ./scripts/run-all-tests-parallel.sh
# ============================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# Create logs directory
LOGS_DIR="./storage/logs/tests"
mkdir -p "$LOGS_DIR"

# Log files
DUSK_LOG="$LOGS_DIR/dusk-tests.log"
UNIT_LOG="$LOGS_DIR/unit-tests.log"
FEATURE_LOG="$LOGS_DIR/feature-tests.log"
INTEGRATION_LOG="$LOGS_DIR/integration-tests.log"
MASTER_LOG="$LOGS_DIR/all-tests.log"

# PID files
PIDS_DIR="./storage/logs/tests/pids"
mkdir -p "$PIDS_DIR"

# Clean up old logs
echo -e "${BLUE}Cleaning up old logs...${NC}"
rm -f "$LOGS_DIR"/*.log
rm -f "$PIDS_DIR"/*.pid

# Banner
echo ""
echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║       AI Legal War Machine - Parallel Test Runner         ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Clear Laravel config cache
echo -e "${YELLOW}Clearing configuration cache...${NC}"
php artisan config:clear --ansi > /dev/null 2>&1

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Starting Test Suites in Background${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

# Function to start a test suite
start_test_suite() {
    local name=$1
    local command=$2
    local log_file=$3
    local pid_file="$PIDS_DIR/${name}.pid"

    echo -e "${CYAN}Starting ${name}...${NC}"
    echo "========================================" > "$log_file"
    echo "Test Suite: ${name}" >> "$log_file"
    echo "Started: $(date '+%Y-%m-%d %H:%M:%S')" >> "$log_file"
    echo "Command: ${command}" >> "$log_file"
    echo "========================================" >> "$log_file"
    echo "" >> "$log_file"

    # Run in background with nohup
    nohup bash -c "
        echo '[START] ${name} test suite started at \$(date)' >> '$log_file' 2>&1
        $command >> '$log_file' 2>&1
        EXIT_CODE=\$?
        echo '' >> '$log_file' 2>&1
        echo '[END] ${name} test suite finished at \$(date) with exit code: \$EXIT_CODE' >> '$log_file' 2>&1
        exit \$EXIT_CODE
    " > /dev/null 2>&1 &

    local pid=$!
    echo $pid > "$pid_file"
    echo -e "${GREEN}✓ ${name} started (PID: ${pid})${NC}"
    echo "  Log: ${log_file}"
    echo ""
}

# Start Dusk tests (Browser/E2E tests)
start_test_suite \
    "Dusk Tests" \
    "php artisan dusk --colors=always" \
    "$DUSK_LOG"

# Start Unit tests
start_test_suite \
    "Unit Tests" \
    "php artisan test --testsuite=Unit --colors=always" \
    "$UNIT_LOG"

# Start Feature tests
start_test_suite \
    "Feature Tests" \
    "php artisan test --testsuite=Feature --colors=always" \
    "$FEATURE_LOG"

# Start Integration tests
start_test_suite \
    "Integration Tests" \
    "php artisan test --testsuite=Integration --colors=always" \
    "$INTEGRATION_LOG"

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  All Test Suites Started${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

# Show running processes
echo -e "${CYAN}Running Test Processes:${NC}"
for pid_file in "$PIDS_DIR"/*.pid; do
    if [ -f "$pid_file" ]; then
        pid=$(cat "$pid_file")
        name=$(basename "$pid_file" .pid)
        if ps -p $pid > /dev/null 2>&1; then
            echo -e "  ${GREEN}✓${NC} $name (PID: $pid)"
        else
            echo -e "  ${RED}✗${NC} $name (PID: $pid) - Already finished"
        fi
    fi
done

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Monitoring Logs (Ctrl+C to stop monitoring)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}Tip: Logs are saved in ${LOGS_DIR}${NC}"
echo ""

# Wait a moment for logs to start populating
sleep 2

# Function to tail all logs with colored prefixes
tail_logs() {
    # Use tail with -f to follow logs and add colored prefixes
    tail -f -n +1 \
        "$DUSK_LOG" \
        "$UNIT_LOG" \
        "$FEATURE_LOG" \
        "$INTEGRATION_LOG" 2>/dev/null |
    while IFS= read -r line; do
        case "$line" in
            *"==> $DUSK_LOG <=="*)
                echo -e "${MAGENTA}╔═══ DUSK TESTS ════════════════════════════════════╗${NC}"
                ;;
            *"==> $UNIT_LOG <=="*)
                echo -e "${CYAN}╔═══ UNIT TESTS ════════════════════════════════════╗${NC}"
                ;;
            *"==> $FEATURE_LOG <=="*)
                echo -e "${BLUE}╔═══ FEATURE TESTS ═════════════════════════════════╗${NC}"
                ;;
            *"==> $INTEGRATION_LOG <=="*)
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
}

# Trap Ctrl+C to show summary
cleanup() {
    echo ""
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  Test Suite Summary${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo ""

    all_passed=true

    for pid_file in "$PIDS_DIR"/*.pid; do
        if [ -f "$pid_file" ]; then
            pid=$(cat "$pid_file")
            name=$(basename "$pid_file" .pid)

            # Check if process is still running
            if ps -p $pid > /dev/null 2>&1; then
                echo -e "  ${YELLOW}⏳${NC} $name - Still running (PID: $pid)"
                all_passed=false
            else
                # Process finished, check exit code from log
                log_file="${LOGS_DIR}/${name}.log"
                if grep -q "exit code: 0" "$log_file" 2>/dev/null; then
                    echo -e "  ${GREEN}✓${NC} $name - PASSED"
                else
                    echo -e "  ${RED}✗${NC} $name - FAILED"
                    all_passed=false
                fi
            fi
        fi
    done

    echo ""
    echo -e "${CYAN}Log files location:${NC} ${LOGS_DIR}"
    echo ""

    if [ "$all_passed" = true ]; then
        echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${GREEN}║              All Test Suites Passed! ✓                     ║${NC}"
        echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    else
        echo -e "${YELLOW}╔════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${YELLOW}║          Some Tests Failed or Still Running                ║${NC}"
        echo -e "${YELLOW}╚════════════════════════════════════════════════════════════╝${NC}"
    fi
    echo ""

    exit 0
}

trap cleanup INT TERM

# Tail all logs
tail_logs

# If tail exits (shouldn't happen with -f), show summary
cleanup
