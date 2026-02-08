#!/usr/bin/env bash
#######################################
# Autoloop Plan Mode (--plan)
#######################################
# Shows what the TDD autoloop would do without executing anything.
# Checks prerequisites, selects components, and validates readiness.
#
# Usage:
#   ./scripts/autoloop-plan.sh [options]
#
# Options:
#   --sprint <N>    Force specific sprint (default: auto-detect lowest incomplete)
#   --limit <N>     Max components to show (default: 2)
#   --verbose       Show detailed prerequisite checks
#   --quiet         Only output exit code (for scripting)
#   --help          Show this help
#
# Exit codes (standard autoloop codes):
#   0  - Prerequisites satisfied, ready to run
#   3  - No components to work on (EXIT_NO_COMPONENTS)
#   31 - Missing prerequisites (EXIT_PREREQ_MISSING)
#   53 - Queue file not found or malformed (EXIT_QUEUE_NOT_FOUND)
#
# See scripts/lib/autoloop-exit-codes.sh for full exit code reference.
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
QUEUE_FILE="${PROJECT_DIR}/test-results/tdd-test-queue.json"
MATRIX_SCRIPT="${PROJECT_DIR}/scripts/check-matrix.sh"

# Source exit codes library
# shellcheck source=lib/autoloop-exit-codes.sh
source "${SCRIPT_DIR}/lib/autoloop-exit-codes.sh"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Defaults
SPRINT=""
LIMIT=2
VERBOSE=false
QUIET=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case "$1" in
        --sprint)
            SPRINT="$2"
            shift 2
            ;;
        --limit)
            LIMIT="$2"
            shift 2
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --quiet)
            QUIET=true
            shift
            ;;
        --help|-h)
            head -30 "$0" | tail -25
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}" >&2
            exit 1
            ;;
    esac
done

#######################################
# Output helpers
#######################################
log() {
    if [[ "$QUIET" != "true" ]]; then
        echo -e "$@"
    fi
}

log_verbose() {
    if [[ "$VERBOSE" == "true" && "$QUIET" != "true" ]]; then
        echo -e "$@"
    fi
}

#######################################
# Prerequisite checks
#######################################
PREREQ_ERRORS=()
PREREQ_WARNINGS=()

check_queue_file() {
    log_verbose "  Checking queue file..."
    if [[ ! -f "$QUEUE_FILE" ]]; then
        PREREQ_ERRORS+=("Queue file not found: $QUEUE_FILE")
        return 1
    fi

    # Validate JSON
    if ! jq empty "$QUEUE_FILE" 2>/dev/null; then
        PREREQ_ERRORS+=("Queue file is malformed JSON")
        return 1
    fi

    # Check components array exists
    if ! jq -e '.components | length > 0' "$QUEUE_FILE" >/dev/null 2>&1; then
        PREREQ_ERRORS+=("Queue file has no components")
        return 1
    fi

    log_verbose "    ${GREEN}✓${NC} Queue file valid"
    return 0
}

check_php() {
    log_verbose "  Checking PHP..."
    if ! command -v php &>/dev/null; then
        PREREQ_ERRORS+=("PHP not found in PATH")
        return 1
    fi

    local php_version
    php_version=$(php -r 'echo PHP_VERSION;' 2>/dev/null)
    log_verbose "    ${GREEN}✓${NC} PHP $php_version"
    return 0
}

check_composer() {
    log_verbose "  Checking Composer dependencies..."
    if [[ ! -d "${PROJECT_DIR}/vendor" ]]; then
        PREREQ_ERRORS+=("Vendor directory not found - run: composer install")
        return 1
    fi

    if [[ ! -f "${PROJECT_DIR}/vendor/autoload.php" ]]; then
        PREREQ_ERRORS+=("Autoload not found - run: composer install")
        return 1
    fi

    log_verbose "    ${GREEN}✓${NC} Composer dependencies installed"
    return 0
}

check_database() {
    log_verbose "  Checking PostgreSQL..."

    # Check if psql is available
    if ! command -v psql &>/dev/null; then
        PREREQ_WARNINGS+=("psql not in PATH - cannot verify database")
        return 0
    fi

    # Check connection
    if ! PGPASSWORD=password psql -U postgres -h localhost -c '\q' 2>/dev/null; then
        PREREQ_ERRORS+=("PostgreSQL not reachable - check if running")
        return 1
    fi

    # Check database exists
    if ! PGPASSWORD=password psql -U postgres -h localhost -d ai_legal_war_machine -c '\q' 2>/dev/null; then
        PREREQ_ERRORS+=("Database 'ai_legal_war_machine' not found - run migrations")
        return 1
    fi

    log_verbose "    ${GREEN}✓${NC} PostgreSQL connected"
    return 0
}

check_env_file() {
    log_verbose "  Checking .env file..."
    if [[ ! -f "${PROJECT_DIR}/.env" ]]; then
        PREREQ_ERRORS+=(".env file not found - copy from .env.example")
        return 1
    fi

    # Check critical env vars
    local missing_vars=()
    for var in APP_KEY DB_DATABASE; do
        if ! grep -q "^${var}=.\+" "${PROJECT_DIR}/.env" 2>/dev/null; then
            missing_vars+=("$var")
        fi
    done

    if [[ ${#missing_vars[@]} -gt 0 ]]; then
        PREREQ_WARNINGS+=("Missing/empty env vars: ${missing_vars[*]}")
    fi

    log_verbose "    ${GREEN}✓${NC} .env file exists"
    return 0
}

check_test_runner() {
    log_verbose "  Checking test runner..."
    local test_runner="${PROJECT_DIR}/scripts/run-focused-tests.sh"

    if [[ ! -x "$test_runner" ]]; then
        PREREQ_ERRORS+=("Test runner not executable: $test_runner")
        return 1
    fi

    log_verbose "    ${GREEN}✓${NC} Test runner available"
    return 0
}

check_matrix() {
    log_verbose "  Running matrix check..."
    if [[ -x "$MATRIX_SCRIPT" ]]; then
        if ! "$MATRIX_SCRIPT" --quiet 2>/dev/null; then
            PREREQ_WARNINGS+=("Matrix check reported issues - run: ./scripts/check-matrix.sh")
        else
            log_verbose "    ${GREEN}✓${NC} Matrix check passed"
        fi
    fi
    return 0
}

run_all_prereq_checks() {
    log "${BLUE}═══ Prerequisite Checks ═══${NC}"
    log ""

    check_queue_file
    check_php
    check_composer
    check_env_file
    check_database
    check_test_runner
    check_matrix

    log ""
}

#######################################
# Component selection
#######################################
SELECTED_SPRINT=""

select_components() {
    # Determine active sprint
    local active_sprint
    if [[ -n "$SPRINT" ]]; then
        active_sprint="$SPRINT"
    else
        # Find lowest sprint with incomplete components
        active_sprint=$(jq -r '
            .components
            | map(select(.status != "done"))
            | map(.sprint)
            | min // empty
        ' "$QUEUE_FILE" 2>/dev/null)

        if [[ -z "$active_sprint" ]]; then
            return 2  # No components to work on
        fi
    fi

    # Store sprint in global variable instead of echoing
    SELECTED_SPRINT="$active_sprint"

    log "${BLUE}═══ Component Selection ═══${NC}"
    log ""
    log "Active Sprint: ${CYAN}$active_sprint${NC}"

    # Get sprint guide
    local sprint_desc
    sprint_desc=$(jq -r ".sprint_guide[\"$active_sprint\"] // \"No description\"" "$QUEUE_FILE" 2>/dev/null)
    log "Sprint Focus: ${sprint_desc}"
    log ""

    # Select candidate components
    # Priority: in_progress first, then todo
    # Filter: sprint matches, type is unit/feature/feature_livewire/integration
    local candidates
    candidates=$(jq -r --arg sprint "$active_sprint" --argjson limit "$LIMIT" '
        .components
        | map(select(
            .sprint == ($sprint | tonumber) and
            .status != "done" and
            (.type == "unit" or .type == "feature" or .type == "feature_livewire" or .type == "integration")
        ))
        | sort_by(if .status == "in_progress" then 0 else 1 end)
        | .[:$limit]
        | .[]
        | "\(.id)|\(.status)|\(.iterations)|\(.test_class)|\(.type)"
    ' "$QUEUE_FILE" 2>/dev/null)

    if [[ -z "$candidates" ]]; then
        log "${YELLOW}No candidate components found for sprint $active_sprint${NC}"
        return 2
    fi

    log "Components to work on (limit: $LIMIT):"
    log ""

    local count=0
    while IFS='|' read -r id status iterations test_class type; do
        count=$((count + 1))
        local status_color
        case "$status" in
            in_progress) status_color="${YELLOW}" ;;
            todo) status_color="${CYAN}" ;;
            *) status_color="${NC}" ;;
        esac

        log "  ${count}. ${GREEN}$id${NC}"
        log "     Status: ${status_color}$status${NC}, Iterations: $iterations"
        log "     Type: $type"
        log "     Test class: $test_class"
        log ""
    done <<< "$candidates"

    return 0
}

#######################################
# Commands that would run
#######################################
show_planned_commands() {
    local sprint="$1"

    log "${BLUE}═══ Commands That Would Run ═══${NC}"
    log ""

    # Get first candidate component for example
    local first_component
    first_component=$(jq -r --arg sprint "$sprint" '
        .components
        | map(select(
            .sprint == ($sprint | tonumber) and
            .status != "done" and
            (.type == "unit" or .type == "feature" or .type == "feature_livewire" or .type == "integration")
        ))
        | sort_by(if .status == "in_progress" then 0 else 1 end)
        | .[0]
        | "\(.id)|\(.test_class)"
    ' "$QUEUE_FILE" 2>/dev/null)

    if [[ -n "$first_component" ]]; then
        IFS='|' read -r id test_class <<< "$first_component"

        log "For each component (example: $id):"
        log ""
        log "  ${CYAN}# 1. Mark as in_progress${NC}"
        log "  php scripts/queue-update.php --id $id --status in_progress"
        log ""
        log "  ${CYAN}# 2. Run focused tests (RED phase)${NC}"
        log "  ./scripts/run-focused-tests.sh $test_class"
        log ""
        log "  ${CYAN}# 3. After each TDD slice${NC}"
        log "  php scripts/queue-update.php --id $id --increment-iterations"
        log ""
        log "  ${CYAN}# 4. Final verification (before marking done)${NC}"
        log "  ./scripts/run-focused-tests.sh $test_class"
        log ""
        log "  ${CYAN}# 5. Mark as done (only if tests pass)${NC}"
        log "  php scripts/queue-update.php --id $id --status done"
        log ""
    fi

    log "Session end:"
    log ""
    log "  ${CYAN}# Git operations${NC}"
    log "  git add ."
    log "  git commit -m \"TDD: [Sprint $sprint] ...\""
    log "  git push origin HEAD"
    log ""
}

#######################################
# Summary
#######################################
show_summary() {
    local exit_code=$1

    log "${BLUE}═══ Summary ═══${NC}"
    log ""

    # Show errors
    if [[ ${#PREREQ_ERRORS[@]} -gt 0 ]]; then
        log "${RED}Errors (must fix before running):${NC}"
        for err in "${PREREQ_ERRORS[@]}"; do
            log "  ${RED}✗${NC} $err"
        done
        log ""
    fi

    # Show warnings
    if [[ ${#PREREQ_WARNINGS[@]} -gt 0 ]]; then
        log "${YELLOW}Warnings (may affect execution):${NC}"
        for warn in "${PREREQ_WARNINGS[@]}"; do
            log "  ${YELLOW}⚠${NC} $warn"
        done
        log ""
    fi

    # Final status (using standard exit codes)
    case $exit_code in
        $EXIT_SUCCESS)
            log "${GREEN}✓ Ready to run autoloop${NC}"
            log ""
            log "To execute, run the autoloop command:"
            log "  /tdd-multi-component-autoloop"
            ;;
        $EXIT_PREREQ_MISSING)
            log "${RED}✗ Prerequisites not satisfied${NC}"
            log ""
            log "Fix the errors above before running the autoloop."
            ;;
        $EXIT_NO_COMPONENTS)
            log "${YELLOW}⚠ No components to work on${NC}"
            log ""
            log "All components may be done, or sprint filter excluded all candidates."
            ;;
        $EXIT_QUEUE_NOT_FOUND)
            log "${RED}✗ Queue file error${NC}"
            ;;
        *)
            log "${RED}✗ Unknown error (code: $exit_code)${NC}"
            ;;
    esac
    log ""
}

#######################################
# Main
#######################################
main() {
    if [[ "$QUIET" != "true" ]]; then
        log ""
        log "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
        log "${BLUE}║           Autoloop Plan Mode (--plan)                      ║${NC}"
        log "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
        log ""
    fi

    # Run prerequisite checks
    run_all_prereq_checks

    # Check for fatal prereq errors
    if [[ ${#PREREQ_ERRORS[@]} -gt 0 ]]; then
        show_summary $EXIT_PREREQ_MISSING
        if [[ "$QUIET" != "true" ]]; then
            print_exit_status $EXIT_PREREQ_MISSING "Prerequisites not satisfied"
        fi
        exit $EXIT_PREREQ_MISSING
    fi

    # Select components (sets SELECTED_SPRINT global)
    select_components
    local select_status=$?

    if [[ $select_status -eq 2 ]]; then
        show_summary $EXIT_NO_COMPONENTS
        if [[ "$QUIET" != "true" ]]; then
            print_exit_status $EXIT_NO_COMPONENTS "No candidate components for sprint"
        fi
        exit $EXIT_NO_COMPONENTS
    fi

    if [[ $select_status -ne 0 ]]; then
        show_summary $EXIT_QUEUE_NOT_FOUND
        if [[ "$QUIET" != "true" ]]; then
            print_exit_status $EXIT_QUEUE_NOT_FOUND "Queue file error"
        fi
        exit $EXIT_QUEUE_NOT_FOUND
    fi

    # Show planned commands
    show_planned_commands "$SELECTED_SPRINT"

    # Show summary
    show_summary $EXIT_SUCCESS
    if [[ "$QUIET" != "true" ]]; then
        print_exit_status $EXIT_SUCCESS "Ready to run autoloop"
    fi
    exit $EXIT_SUCCESS
}

main
