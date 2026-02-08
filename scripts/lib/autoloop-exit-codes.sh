#!/usr/bin/env bash
#######################################
# Autoloop Exit Codes Library
#######################################
# Standard exit codes for TDD autoloop and related scripts.
# Source this file to use consistent exit codes across all autoloop tooling.
#
# Usage:
#   source "$(dirname "${BASH_SOURCE[0]}")/lib/autoloop-exit-codes.sh"
#   exit_with $EXIT_TESTS_FAILED "3 tests failed in FooTest"
#
# Exit Code Ranges:
#   0      = Success
#   1-9    = General/unknown errors
#   10-19  = Lint/format gate failures
#   20-29  = Test failures
#   30-39  = Setup/readiness failures
#   40-49  = Validation failures
#   50-59  = Queue operation failures
#   60-69  = Git operation failures
#   70-79  = Limit exceeded (timebox, iterations)
#
#######################################

#######################################
# Exit Code Constants
#######################################

# Success
readonly EXIT_SUCCESS=0

# General errors (1-9)
readonly EXIT_UNKNOWN_ERROR=1
readonly EXIT_INVALID_ARGS=2
readonly EXIT_NO_COMPONENTS=3

# Lint/format gate failures (10-19)
readonly EXIT_LINT_FAILED=10
readonly EXIT_FORMAT_FAILED=11
readonly EXIT_PHPSTAN_FAILED=12
readonly EXIT_ESLINT_FAILED=13

# Test failures (20-29)
readonly EXIT_TESTS_FAILED=20
readonly EXIT_UNIT_TESTS_FAILED=21
readonly EXIT_FEATURE_TESTS_FAILED=22
readonly EXIT_INTEGRATION_TESTS_FAILED=23
readonly EXIT_BROWSER_TESTS_FAILED=24

# Setup/readiness failures (30-39)
readonly EXIT_SETUP_FAILED=30
readonly EXIT_PREREQ_MISSING=31
readonly EXIT_DATABASE_UNAVAILABLE=32
readonly EXIT_SERVICE_UNAVAILABLE=33
readonly EXIT_ENV_MISSING=34

# Validation failures (40-49)
readonly EXIT_VALIDATION_FAILED=40
readonly EXIT_QUEUE_SCHEMA_INVALID=41
readonly EXIT_QUEUE_TRANSITION_INVALID=42
readonly EXIT_CONFIG_INVALID=43

# Queue operation failures (50-59)
readonly EXIT_QUEUE_READ_FAILED=50
readonly EXIT_QUEUE_WRITE_FAILED=51
readonly EXIT_QUEUE_LOCK_FAILED=52
readonly EXIT_QUEUE_NOT_FOUND=53

# Git operation failures (60-69)
readonly EXIT_GIT_COMMIT_FAILED=60
readonly EXIT_GIT_PUSH_FAILED=61
readonly EXIT_GIT_DIRTY_WORKTREE=62

# Limit exceeded (70-79)
readonly EXIT_TIMEBOX_EXCEEDED=70
readonly EXIT_MAX_ITERATIONS_EXCEEDED=71
readonly EXIT_BUDGET_EXCEEDED=72

#######################################
# Exit Code Descriptions
#######################################

declare -A EXIT_CODE_DESCRIPTIONS=(
    [0]="Success"
    [1]="Unknown error"
    [2]="Invalid arguments"
    [3]="No components to work on"
    [10]="Lint check failed"
    [11]="Code format check failed"
    [12]="PHPStan static analysis failed"
    [13]="ESLint check failed"
    [20]="Tests failed"
    [21]="Unit tests failed"
    [22]="Feature tests failed"
    [23]="Integration tests failed"
    [24]="Browser tests failed"
    [30]="Setup failed"
    [31]="Prerequisites missing"
    [32]="Database unavailable"
    [33]="Required service unavailable"
    [34]="Environment file missing"
    [40]="Validation failed"
    [41]="Queue schema invalid"
    [42]="Invalid queue status transition"
    [43]="Configuration invalid"
    [50]="Queue read failed"
    [51]="Queue write failed"
    [52]="Queue lock timeout"
    [53]="Queue file not found"
    [60]="Git commit failed"
    [61]="Git push failed"
    [62]="Git working tree is dirty"
    [70]="Timebox exceeded"
    [71]="Max iterations exceeded"
    [72]="Budget exceeded"
)

#######################################
# Helper Functions
#######################################

# Get description for an exit code
# Usage: get_exit_description $code
get_exit_description() {
    local code=$1
    echo "${EXIT_CODE_DESCRIPTIONS[$code]:-Unknown exit code}"
}

# Get category for an exit code
# Usage: get_exit_category $code
get_exit_category() {
    local code=$1

    if [[ $code -eq 0 ]]; then
        echo "SUCCESS"
    elif [[ $code -ge 1 && $code -le 9 ]]; then
        echo "GENERAL"
    elif [[ $code -ge 10 && $code -le 19 ]]; then
        echo "LINT"
    elif [[ $code -ge 20 && $code -le 29 ]]; then
        echo "TEST"
    elif [[ $code -ge 30 && $code -le 39 ]]; then
        echo "SETUP"
    elif [[ $code -ge 40 && $code -le 49 ]]; then
        echo "VALIDATION"
    elif [[ $code -ge 50 && $code -le 59 ]]; then
        echo "QUEUE"
    elif [[ $code -ge 60 && $code -le 69 ]]; then
        echo "GIT"
    elif [[ $code -ge 70 && $code -le 79 ]]; then
        echo "LIMIT"
    else
        echo "UNKNOWN"
    fi
}

# Print exit status with formatting
# Usage: print_exit_status $code [$message]
print_exit_status() {
    local code=$1
    local message="${2:-}"
    local category
    local description
    local color

    category=$(get_exit_category "$code")
    description=$(get_exit_description "$code")

    # Color based on category
    case "$category" in
        SUCCESS)    color='\033[0;32m' ;;  # Green
        LINT|TEST)  color='\033[0;31m' ;;  # Red
        SETUP)      color='\033[0;33m' ;;  # Yellow
        VALIDATION) color='\033[0;35m' ;;  # Magenta
        QUEUE|GIT)  color='\033[0;34m' ;;  # Blue
        LIMIT)      color='\033[0;33m' ;;  # Yellow (warning, not error)
        *)          color='\033[0;31m' ;;  # Red
    esac

    local nc='\033[0m'

    echo ""
    echo -e "${color}╔════════════════════════════════════════════════════════════╗${nc}"
    printf "${color}║ EXIT CODE: %-3s | Category: %-10s                    ║${nc}\n" "$code" "$category"
    echo -e "${color}║ ${description}$(printf '%*s' $((45 - ${#description})) '')║${nc}"
    if [[ -n "$message" ]]; then
        # Truncate message if too long
        local truncated="${message:0:55}"
        echo -e "${color}║ ${truncated}$(printf '%*s' $((57 - ${#truncated})) '')║${nc}"
    fi
    echo -e "${color}╚════════════════════════════════════════════════════════════╝${nc}"
    echo ""
}

# Exit with formatted status
# Usage: exit_with $code [$message]
exit_with() {
    local code=$1
    local message="${2:-}"

    print_exit_status "$code" "$message"
    exit "$code"
}

# Map test runner exit code to standard exit code
# Usage: map_test_exit $runner_exit $test_type
# Returns: Standard exit code via echo
map_test_exit() {
    local runner_exit=$1
    local test_type="${2:-unit}"

    if [[ $runner_exit -eq 0 ]]; then
        echo $EXIT_SUCCESS
        return
    fi

    case "$test_type" in
        unit)        echo $EXIT_UNIT_TESTS_FAILED ;;
        feature)     echo $EXIT_FEATURE_TESTS_FAILED ;;
        integration) echo $EXIT_INTEGRATION_TESTS_FAILED ;;
        browser*)    echo $EXIT_BROWSER_TESTS_FAILED ;;
        *)           echo $EXIT_TESTS_FAILED ;;
    esac
}

#######################################
# Export for subshells
#######################################
export EXIT_SUCCESS EXIT_UNKNOWN_ERROR EXIT_INVALID_ARGS EXIT_NO_COMPONENTS
export EXIT_LINT_FAILED EXIT_FORMAT_FAILED EXIT_PHPSTAN_FAILED EXIT_ESLINT_FAILED
export EXIT_TESTS_FAILED EXIT_UNIT_TESTS_FAILED EXIT_FEATURE_TESTS_FAILED
export EXIT_INTEGRATION_TESTS_FAILED EXIT_BROWSER_TESTS_FAILED
export EXIT_SETUP_FAILED EXIT_PREREQ_MISSING EXIT_DATABASE_UNAVAILABLE
export EXIT_SERVICE_UNAVAILABLE EXIT_ENV_MISSING
export EXIT_VALIDATION_FAILED EXIT_QUEUE_SCHEMA_INVALID EXIT_QUEUE_TRANSITION_INVALID
export EXIT_CONFIG_INVALID
export EXIT_QUEUE_READ_FAILED EXIT_QUEUE_WRITE_FAILED EXIT_QUEUE_LOCK_FAILED
export EXIT_QUEUE_NOT_FOUND
export EXIT_GIT_COMMIT_FAILED EXIT_GIT_PUSH_FAILED EXIT_GIT_DIRTY_WORKTREE
export EXIT_TIMEBOX_EXCEEDED EXIT_MAX_ITERATIONS_EXCEEDED EXIT_BUDGET_EXCEEDED
