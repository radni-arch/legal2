#!/bin/bash

# ============================================
# Integrated Test Suite Runner
# ============================================
# This script runs the complete test suite with various options
# It uses .env.testing and a production database copy
#
# Usage:
#   ./scripts/run-tests.sh                    # Run all tests
#   ./scripts/run-tests.sh --unit             # Run only unit tests
#   ./scripts/run-tests.sh --feature          # Run only feature tests
#   ./scripts/run-tests.sh --coverage         # Run with coverage report
#   ./scripts/run-tests.sh --parallel         # Run tests in parallel (faster)
#   ./scripts/run-tests.sh --filter=TestName  # Run specific test
#   ./scripts/run-tests.sh --setup            # Setup test database first
# ============================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# Default values
RUN_SETUP=true
TEST_SUITE=""
COVERAGE=false
PARALLEL=false
FILTER=""
STOP_ON_FAILURE=false
VERBOSE=false
EXTRA_ARGS=""

# Parse arguments
for arg in "$@"; do
    case $arg in
        --setup)
            RUN_SETUP=true
            shift
            ;;
        --unit)
            TEST_SUITE="Unit"
            shift
            ;;
        --feature)
            TEST_SUITE="Feature"
            shift
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        --parallel)
            PARALLEL=true
            shift
            ;;
        --filter=*)
            FILTER="${arg#*=}"
            shift
            ;;
        --stop-on-failure)
            STOP_ON_FAILURE=true
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --setup              Setup test database before running tests"
            echo "  --unit               Run only unit tests"
            echo "  --feature            Run only feature tests"
            echo "  --coverage           Generate code coverage report (min 80%)"
            echo "  --parallel           Run tests in parallel (faster)"
            echo "  --filter=NAME        Run only tests matching the given name"
            echo "  --stop-on-failure    Stop on first test failure"
            echo "  -v, --verbose        Verbose output"
            echo "  --help               Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0 --setup                    # Setup DB and run all tests"
            echo "  $0 --unit --coverage          # Unit tests with coverage"
            echo "  $0 --feature --parallel       # Feature tests in parallel"
            echo "  $0 --filter=UserTest          # Run only UserTest"
            exit 0
            ;;
        *)
            EXTRA_ARGS="$EXTRA_ARGS $arg"
            ;;
    esac
done

# Banner
echo ""
echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║           AI Legal War Machine - Test Suite               ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Check if .env.testing exists
if [ ! -f .env.testing ]; then
    echo -e "${RED}Error: .env.testing file not found${NC}"
    echo "Creating default .env.testing file..."
    cp .env.example .env.testing
    echo -e "${YELLOW}Please configure .env.testing with test database settings${NC}"
    exit 1
fi

# Setup test database if requested
if [ "$RUN_SETUP" = true ]; then
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  Step 1: Setting up Test Database${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo ""
##    sudo pkill -9 "ai_agent*";
    if [ -f ./scripts/setup-test-db.sh ]; then
        ./scripts/setup-test-db.sh --auto
    else
        echo -e "${YELLOW}Warning: setup-test-db.sh not found, skipping database setup${NC}"
    fi
    echo ""
fi

# Clear configuration cache
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Step 2: Preparing Test Environment${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${YELLOW}Clearing configuration cache...${NC}"
php artisan config:clear --ansi > /dev/null 2>&1

# Export environment to testing
echo -e "${YELLOW}Loading .env.testing configuration...${NC}"
cp .env.testing .env.testing.backup 2>/dev/null || true

echo -e "${GREEN}✓ Test environment ready${NC}"
echo ""

# Build test command
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Step 3: Running Tests${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

TEST_CMD="php artisan test"

# Add test suite filter
if [ -n "$TEST_SUITE" ]; then
    TEST_CMD="$TEST_CMD --testsuite=$TEST_SUITE"
    echo -e "${CYAN}Test Suite:${NC} $TEST_SUITE"
fi

# Add test filter
if [ -n "$FILTER" ]; then
    TEST_CMD="$TEST_CMD --filter=$FILTER"
    echo -e "${CYAN}Filter:${NC} $FILTER"
fi

# Add coverage
if [ "$COVERAGE" = true ]; then
    TEST_CMD="$TEST_CMD --coverage --min=80"
    echo -e "${CYAN}Coverage:${NC} Enabled (minimum 80%)"
fi

# Add parallel execution
if [ "$PARALLEL" = true ]; then
    TEST_CMD="$TEST_CMD --parallel"
    echo -e "${CYAN}Parallel:${NC} Enabled"
fi

# Add stop on failure
if [ "$STOP_ON_FAILURE" = true ]; then
    TEST_CMD="$TEST_CMD --stop-on-failure"
    echo -e "${CYAN}Stop on Failure:${NC} Enabled"
fi

# Add verbose
if [ "$VERBOSE" = true ]; then
    TEST_CMD="$TEST_CMD -v"
fi

# Add extra arguments
TEST_CMD="$TEST_CMD $EXTRA_ARGS"

echo ""
echo -e "${MAGENTA}Running: $TEST_CMD${NC}"
echo ""

# Run tests with .env.testing
if APP_ENV=testing $TEST_CMD; then
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                   All Tests Passed! ✓                      ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    exit 0
else
    EXIT_CODE=$?
    echo ""
    echo -e "${RED}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                   Tests Failed! ✗                          ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    exit $EXIT_CODE
fi
