#!/bin/bash

# ============================================
# Intelligent Test Runner with Auto-Analysis
# ============================================
# Runs tests in parallel, analyzes failures, creates tasks
# ============================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOGS_DIR="./storage/logs/tests"

echo ""
echo -e "${MAGENTA}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${MAGENTA}║     Intelligent Test Runner with Auto-Analysis            ║${NC}"
echo -e "${MAGENTA}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Step 1: Run parallel tests
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Step 1: Running Test Suites in Parallel${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

if [ ! -f "$SCRIPT_DIR/run-all-tests-parallel.sh" ]; then
    echo -e "${RED}Error: run-all-tests-parallel.sh not found${NC}"
    exit 1
fi

# Run tests in background
"$SCRIPT_DIR/run-all-tests-parallel.sh" &
TEST_RUNNER_PID=$!

echo -e "${YELLOW}Test runner started (PID: ${TEST_RUNNER_PID})${NC}"
echo -e "${YELLOW}Press Ctrl+C to stop monitoring and proceed to analysis${NC}"
echo ""

# Function to handle Ctrl+C
cleanup() {
    echo ""
    echo -e "${YELLOW}Stopping test monitoring...${NC}"

    # Don't kill the tests, let them finish in background
    echo -e "${CYAN}Tests continue running in background${NC}"

    # Proceed to analysis
    proceed_to_analysis=true
}

trap cleanup INT

# Wait for user interrupt or test completion
proceed_to_analysis=false
wait $TEST_RUNNER_PID 2>/dev/null || proceed_to_analysis=true

# Step 2: Wait a bit for logs to be written
if [ "$proceed_to_analysis" = true ]; then
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  Step 2: Waiting for Test Logs to Complete${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo ""

    echo -e "${YELLOW}Waiting 5 seconds for logs to flush...${NC}"
    sleep 5
fi

# Step 3: Analyze test failures
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Step 3: Analyzing Test Failures${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

if [ ! -f "$SCRIPT_DIR/analyze-test-failures.sh" ]; then
    echo -e "${RED}Error: analyze-test-failures.sh not found${NC}"
    exit 1
fi

"$SCRIPT_DIR/analyze-test-failures.sh"

# Step 4: Generate todo tasks (create JSON for claude)
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Step 4: Creating Task List${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

if [ ! -f "./FAILING_TESTS_TASKS.md" ]; then
    echo -e "${YELLOW}No task file generated - possibly no failures?${NC}"
    exit 0
fi

# Extract task count
task_count=$(grep -c "^### Task" ./FAILING_TESTS_TASKS.md || echo "0")

echo -e "${CYAN}Generated ${task_count} categorized tasks${NC}"
echo ""

# Step 5: Show summary and next steps
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Summary & Next Steps${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${GREEN}✓ Tests executed in parallel${NC}"
echo -e "${GREEN}✓ Failures analyzed and categorized${NC}"
echo -e "${GREEN}✓ Tasks generated with skill recommendations${NC}"
echo ""

echo -e "${CYAN}Task Document:${NC} ${YELLOW}./FAILING_TESTS_TASKS.md${NC}"
echo ""

# Show top 5 failure categories
echo -e "${CYAN}Top Failure Categories:${NC}"
if [ -f "$LOGS_DIR/dusk-tests.log" ] || [ -f "$LOGS_DIR/unit-tests.log" ]; then
    # Count error types from logs
    {
        grep -h "FAILED.*\[" "$LOGS_DIR"/*.log 2>/dev/null | \
        grep -oP '\[\d+;1m \K[^[]+' | \
        sort | uniq -c | sort -rn | head -5
    } || echo -e "${YELLOW}No categorized failures found${NC}"
fi
echo ""

echo -e "${CYAN}Recommended Actions:${NC}"
echo ""
echo -e "  ${MAGENTA}1.${NC} Review tasks:"
echo -e "     ${YELLOW}cat ./FAILING_TESTS_TASKS.md${NC}"
echo ""
echo -e "  ${MAGENTA}2.${NC} Check test status:"
echo -e "     ${YELLOW}./scripts/check-test-status.sh${NC}"
echo ""
echo -e "  ${MAGENTA}3.${NC} View detailed logs:"
echo -e "     ${YELLOW}./scripts/view-test-logs.sh all${NC}"
echo ""
echo -e "  ${MAGENTA}4.${NC} Use recommended skills for fixes:"
echo -e "     ${YELLOW}claude> /skill systematic-debugging${NC}"
echo -e "     ${YELLOW}claude> /skill condition-based-waiting${NC}"
echo ""
echo -e "  ${MAGENTA}5.${NC} Dispatch parallel agents for independent failures:"
echo -e "     ${YELLOW}# Review FAILING_TESTS_TASKS.md for independent domains${NC}"
echo -e "     ${YELLOW}# Dispatch one agent per domain${NC}"
echo ""

# Create a quick summary file for reference
cat > "./TEST_RUN_SUMMARY.txt" << EOF
Test Run Summary - $(date '+%Y-%m-%d %H:%M:%S')
================================================

Task Count: ${task_count}
Logs Directory: ${LOGS_DIR}
Tasks Document: ./FAILING_TESTS_TASKS.md

Test Suites:
$(ls -1 "$LOGS_DIR"/*.log 2>/dev/null | xargs -I{} basename {} .log || echo "No logs found")

Next Steps:
1. Review: cat ./FAILING_TESTS_TASKS.md
2. Status: ./scripts/check-test-status.sh
3. Logs: ./scripts/view-test-logs.sh all

Generated by: run-tests-with-analysis.sh
EOF

echo -e "${GREEN}✓ Summary saved to: ${YELLOW}./TEST_RUN_SUMMARY.txt${NC}"
echo ""

# Offer to display tasks
echo -e "${CYAN}Would you like to see the task summary now? (y/n)${NC}"
read -t 10 -n 1 -r response || response="n"
echo ""

if [[ $response =~ ^[Yy]$ ]]; then
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  Task Summary${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo ""

    # Show just the headers
    grep "^##\|^###" ./FAILING_TESTS_TASKS.md | head -20

    echo ""
    echo -e "${YELLOW}Full details: cat ./FAILING_TESTS_TASKS.md${NC}"
    echo ""
fi

echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              Analysis Complete! ✓                          ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
