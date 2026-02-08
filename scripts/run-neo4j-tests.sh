#!/bin/bash
set -euo pipefail

#######################################
# Neo4j Test Runner Script
#
# Runs all Neo4j and Graph-related tests with optional coverage.
#
# Features:
# - Run unit tests for graph services
# - Run integration tests
# - Generate coverage report
# - Filter by test type
#
# Usage:
#   ./scripts/run-neo4j-tests.sh
#   ./scripts/run-neo4j-tests.sh --unit-only
#   ./scripts/run-neo4j-tests.sh --integration-only
#   ./scripts/run-neo4j-tests.sh --coverage
#######################################

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
COVERAGE=false
UNIT_ONLY=false
INTEGRATION_ONLY=false
FILTER=""
PARALLEL="${PARALLEL:-4}"

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --coverage)
            COVERAGE=true
            shift
            ;;
        --unit-only)
            UNIT_ONLY=true
            shift
            ;;
        --integration-only)
            INTEGRATION_ONLY=true
            shift
            ;;
        --filter)
            FILTER="$2"
            shift 2
            ;;
        --parallel)
            PARALLEL="$2"
            shift 2
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --coverage          Generate code coverage report"
            echo "  --unit-only         Run only unit tests"
            echo "  --integration-only  Run only integration tests"
            echo "  --filter PATTERN    Filter tests by name pattern"
            echo "  --parallel N        Number of parallel processes (default: 4)"
            echo "  --help              Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                            # Run all Neo4j/Graph tests"
            echo "  $0 --unit-only --coverage     # Unit tests with coverage"
            echo "  $0 --filter 'GraphSync'       # Run tests matching 'GraphSync'"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

#######################################
# Logging Functions
#######################################

log() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [INFO] $*"
}

success() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [SUCCESS] $*"
}

error() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [ERROR] $*" >&2
}

#######################################
# Pre-flight Checks
#######################################

cd "$PROJECT_DIR"

log "=========================================="
log "Neo4j / Graph Test Runner"
log "=========================================="

# Check for PHPUnit
if [ ! -f "vendor/bin/phpunit" ]; then
    error "PHPUnit not found. Run 'composer install' first."
    exit 1
fi

# Check Neo4j connectivity (optional - tests may mock it)
NEO4J_PASSWORD="${NEO4J_PASSWORD:-password}"
if command -v cypher-shell >/dev/null 2>&1; then
    if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p "$NEO4J_PASSWORD" "RETURN 1;" >/dev/null 2>&1; then
        log "Neo4j connection verified"
    else
        log "Warning: Neo4j not accessible. Integration tests may be skipped."
    fi
else
    log "Warning: cypher-shell not found. Cannot verify Neo4j connection."
fi

#######################################
# Define Test Suites
#######################################

# Unit test directories/files for Neo4j/Graph
UNIT_TESTS=(
    "tests/Unit/Services/GraphDatabaseServiceTest.php"
    "tests/Unit/Services/GraphDatabaseServiceMockTest.php"
    "tests/Unit/Services/GraphDatabaseServiceTransactionTest.php"
    "tests/Unit/Services/GraphQueryHelperTest.php"
    "tests/Unit/Services/GraphRagServiceCharacterizationTest.php"
    "tests/Unit/Services/GraphRagServiceKeywordExtractionTest.php"
    "tests/Unit/Services/Neo4jServiceTest.php"
    "tests/Unit/Services/Graph/"
    "tests/Unit/Services/AI/GraphRagServiceTest.php"
    "tests/Unit/Jobs/Graph/"
    "tests/Unit/Jobs/SyncGraphDataJobTest.php"
    "tests/Unit/Jobs/SyncTextractToGraphTest.php"
    "tests/Unit/Models/GraphMetricTest.php"
    "tests/Unit/Repositories/GraphMetricsRepositoryTest.php"
    "tests/Unit/Requests/Graph/"
    "tests/Unit/Contracts/GraphLinkerContractTest.php"
    "tests/Unit/Contracts/GraphSyncServiceContractTest.php"
)

# Integration test files for Neo4j/Graph
INTEGRATION_TESTS=(
    "tests/Integration/Neo4jComprehensiveTest.php"
    "tests/Integration/Neo4jDataIntegrityInvariantsTest.php"
    "tests/Integration/Neo4jDbConsistencyInvariantsTest.php"
    "tests/Integration/Neo4jDbInvariantsTest.php"
    "tests/Integration/Neo4jGraphRagTest.php"
    "tests/Integration/Neo4jRetryQueueTest.php"
    "tests/Integration/GraphCrossStoreInvariantsTest.php"
    "tests/Integration/GraphDataIntegrityInvariantsTest.php"
    "tests/Integration/GraphDbDataIntegrityExtendedInvariantsTest.php"
    "tests/Integration/GraphDbDataIntegrityInvariantsExtendedTest.php"
    "tests/Integration/GraphDbDataIntegrityInvariantsTest.php"
    "tests/Integration/GraphDbExtendedInvariantsTest.php"
    "tests/Integration/GraphDbSemanticInvariantsTest.php"
    "tests/Integration/GraphEnhancedResearchTest.php"
    "tests/Integration/GraphFixtureBasedProofsTest.php"
    "tests/Integration/GraphSyncWorkflowTest.php"
)

# Feature tests related to Graph
FEATURE_TESTS=(
    "tests/Feature/AnalyzeGraphMetricsCommandTest.php"
    "tests/Feature/Console/GraphInitCommandTest.php"
    "tests/Feature/Console/GraphQueryCommandTest.php"
    "tests/Feature/Console/GraphStatsCommandTest.php"
    "tests/Feature/Console/GraphSyncCommandTest.php"
    "tests/Feature/ExternalAPIs/Neo4jIntegrationTest.php"
    "tests/Feature/Graph/"
    "tests/Feature/GraphCompareCommandTest.php"
    "tests/Feature/GraphDatabaseHealthTest.php"
    "tests/Feature/GraphViewerTest.php"
    "tests/Feature/Livewire/GraphDashboardTest.php"
    "tests/Feature/Livewire/GraphViewerConcurrencyTest.php"
    "tests/Feature/Livewire/GraphViewerMetricsTest.php"
    "tests/Feature/Livewire/GraphViewerTest.php"
    "tests/Feature/Services/GraphDatabaseServiceCrashTest.php"
)

#######################################
# Build Test Command
#######################################

PHPUNIT_ARGS=()

# Add coverage if requested
if [ "$COVERAGE" = true ]; then
    COVERAGE_DIR="${PROJECT_DIR}/coverage/neo4j"
    mkdir -p "$COVERAGE_DIR"
    PHPUNIT_ARGS+=(--coverage-html "$COVERAGE_DIR" --coverage-text)
    log "Coverage will be generated in: $COVERAGE_DIR"
fi

# Add filter if provided
if [ -n "$FILTER" ]; then
    PHPUNIT_ARGS+=(--filter "$FILTER")
    log "Filter: $FILTER"
fi

# Build test paths based on mode
TEST_PATHS=()

if [ "$UNIT_ONLY" = true ]; then
    log "Running unit tests only"
    for test in "${UNIT_TESTS[@]}"; do
        if [ -e "$test" ]; then
            TEST_PATHS+=("$test")
        fi
    done
elif [ "$INTEGRATION_ONLY" = true ]; then
    log "Running integration tests only"
    for test in "${INTEGRATION_TESTS[@]}"; do
        if [ -e "$test" ]; then
            TEST_PATHS+=("$test")
        fi
    done
else
    log "Running all Neo4j/Graph tests"
    for test in "${UNIT_TESTS[@]}" "${INTEGRATION_TESTS[@]}" "${FEATURE_TESTS[@]}"; do
        if [ -e "$test" ]; then
            TEST_PATHS+=("$test")
        fi
    done
fi

# Remove duplicates and non-existent paths
UNIQUE_PATHS=()
declare -A SEEN
for path in "${TEST_PATHS[@]}"; do
    if [ -e "$path" ] && [ -z "${SEEN[$path]:-}" ]; then
        UNIQUE_PATHS+=("$path")
        SEEN[$path]=1
    fi
done

if [ ${#UNIQUE_PATHS[@]} -eq 0 ]; then
    error "No test files found!"
    exit 1
fi

log "Found ${#UNIQUE_PATHS[@]} test file(s)/directory(ies)"

#######################################
# Run Tests
#######################################

log "Starting test execution..."
START_TIME=$(date +%s)

# Run PHPUnit
set +e  # Don't exit on test failures
vendor/bin/phpunit "${PHPUNIT_ARGS[@]}" "${UNIQUE_PATHS[@]}"
TEST_EXIT_CODE=$?
set -e

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

#######################################
# Summary
#######################################

log "=========================================="
log "Test Execution Summary"
log "=========================================="
log "Duration: ${DURATION}s"

if [ "$COVERAGE" = true ] && [ -d "$COVERAGE_DIR" ]; then
    log "Coverage report: $COVERAGE_DIR/index.html"
fi

if [ $TEST_EXIT_CODE -eq 0 ]; then
    success "All Neo4j/Graph tests passed!"
else
    error "Some tests failed (exit code: $TEST_EXIT_CODE)"
fi

exit $TEST_EXIT_CODE
