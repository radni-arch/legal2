#!/usr/bin/env bash
#######################################
# Quality Gates Runner
#######################################
# Runs lint/format checks before marking a component as done.
# Required: All gates must pass before a component can be marked "done".
#
# Usage:
#   ./scripts/run-quality-gates.sh [options]
#
# Options:
#   --component <id>    Component ID being checked (for reporting)
#   --files <paths>     Only check specific files (comma-separated)
#   --changed           Only check files changed since last commit
#   --staged            Only check staged files
#   --cached            Use cached result if git state unchanged
#   --no-cache          Force re-run even if cached
#   --fix               Attempt to auto-fix issues (where supported)
#   --quiet             Minimal output
#   --verbose           Show detailed output from each tool
#   --help              Show this help
#
# Gates run (when available):
#   1. PHP: Laravel Pint (code style)
#   2. PHP: PHPStan (static analysis) - if installed
#   3. JS: ESLint - if installed
#   4. JS: Prettier - if installed
#   5. Blade: Blade formatter - if installed
#
# Exit codes (standard autoloop codes):
#   0  - All gates passed
#   10 - Lint gate failed (EXIT_LINT_FAILED)
#   11 - Format gate failed (EXIT_FORMAT_FAILED)
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Source exit codes library
# shellcheck source=lib/autoloop-exit-codes.sh
source "${SCRIPT_DIR}/lib/autoloop-exit-codes.sh"

# Cache directory
CACHE_DIR="${PROJECT_DIR}/.cache/quality-gates"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
GRAY='\033[0;90m'
NC='\033[0m'

# Defaults
COMPONENT_ID=""
SPECIFIC_FILES=""
CHECK_CHANGED=false
CHECK_STAGED=false
USE_CACHE=false
NO_CACHE=false
FIX_MODE=false
QUIET=false
VERBOSE=false

# Gate results
declare -A GATE_RESULTS
declare -A GATE_MESSAGES
GATES_RUN=0
GATES_PASSED=0
GATES_FAILED=0
GATES_SKIPPED=0

#######################################
# Parse arguments
#######################################
while [[ $# -gt 0 ]]; do
    case "$1" in
        --component)
            COMPONENT_ID="$2"
            shift 2
            ;;
        --files)
            SPECIFIC_FILES="$2"
            shift 2
            ;;
        --changed)
            CHECK_CHANGED=true
            shift
            ;;
        --staged)
            CHECK_STAGED=true
            shift
            ;;
        --cached)
            USE_CACHE=true
            shift
            ;;
        --no-cache)
            NO_CACHE=true
            shift
            ;;
        --fix)
            FIX_MODE=true
            shift
            ;;
        --quiet)
            QUIET=true
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --help|-h)
            head -35 "$0" | tail -32
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}" >&2
            exit $EXIT_INVALID_ARGS
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
    if [[ "$VERBOSE" == "true" ]]; then
        echo -e "$@"
    fi
}

#######################################
# Cache functions
#######################################
get_git_state_hash() {
    # Hash of current git state (HEAD + staged + unstaged changes)
    local hash
    hash=$(git -C "$PROJECT_DIR" rev-parse HEAD 2>/dev/null || echo "no-git")
    local staged_hash
    staged_hash=$(git -C "$PROJECT_DIR" diff --cached --stat 2>/dev/null | md5sum | cut -d' ' -f1)
    local unstaged_hash
    unstaged_hash=$(git -C "$PROJECT_DIR" diff --stat 2>/dev/null | md5sum | cut -d' ' -f1)
    echo "${hash}-${staged_hash}-${unstaged_hash}"
}

get_cache_file() {
    echo "${CACHE_DIR}/gate-result-$(get_git_state_hash).cache"
}

check_cache() {
    if [[ "$NO_CACHE" == "true" ]]; then
        return 1
    fi

    if [[ "$USE_CACHE" != "true" ]]; then
        return 1
    fi

    local cache_file
    cache_file=$(get_cache_file)

    if [[ -f "$cache_file" ]]; then
        local cached_result
        cached_result=$(cat "$cache_file")
        log "${GRAY}Using cached result (git state unchanged)${NC}"
        return "$cached_result"
    fi

    return 1
}

save_cache() {
    local result=$1
    mkdir -p "$CACHE_DIR"
    local cache_file
    cache_file=$(get_cache_file)
    echo "$result" > "$cache_file"

    # Clean old cache files (keep only last 10)
    find "$CACHE_DIR" -name "gate-result-*.cache" -type f | \
        sort -r | tail -n +11 | xargs -r rm -f 2>/dev/null || true
}

#######################################
# File selection
#######################################
get_files_to_check() {
    local file_type="$1"  # php, js, blade

    local files=""

    if [[ -n "$SPECIFIC_FILES" ]]; then
        # Use specific files
        files=$(echo "$SPECIFIC_FILES" | tr ',' '\n')
    elif [[ "$CHECK_STAGED" == "true" ]]; then
        # Get staged files
        files=$(git -C "$PROJECT_DIR" diff --cached --name-only 2>/dev/null)
    elif [[ "$CHECK_CHANGED" == "true" ]]; then
        # Get changed files (staged + unstaged)
        files=$(git -C "$PROJECT_DIR" diff --name-only HEAD 2>/dev/null)
    else
        # All files
        files="all"
    fi

    if [[ "$files" == "all" ]]; then
        echo "all"
        return
    fi

    # Filter by file type
    case "$file_type" in
        php)
            echo "$files" | grep -E '\.php$' | grep -v '^vendor/' || true
            ;;
        js)
            echo "$files" | grep -E '\.(js|ts|vue)$' | grep -v '^node_modules/' || true
            ;;
        blade)
            echo "$files" | grep -E '\.blade\.php$' || true
            ;;
        *)
            echo "$files"
            ;;
    esac
}

#######################################
# Gate: Laravel Pint (PHP code style)
#######################################
run_pint_gate() {
    local gate_name="pint"
    local pint="${PROJECT_DIR}/vendor/bin/pint"

    if [[ ! -x "$pint" ]]; then
        GATE_RESULTS[$gate_name]="skipped"
        GATE_MESSAGES[$gate_name]="Pint not installed"
        GATES_SKIPPED=$((GATES_SKIPPED + 1))
        return 0
    fi

    GATES_RUN=$((GATES_RUN + 1))
    log_verbose "  Running Laravel Pint..."

    local files
    files=$(get_files_to_check "php")

    local pint_args=""
    if [[ "$FIX_MODE" != "true" ]]; then
        pint_args="--test"
    fi

    local output
    local exit_code

    if [[ "$files" == "all" ]]; then
        output=$("$pint" $pint_args 2>&1) || exit_code=$?
    elif [[ -n "$files" ]]; then
        # Pint doesn't support file list directly, run on all and filter output
        output=$("$pint" $pint_args 2>&1) || exit_code=$?
    else
        GATE_RESULTS[$gate_name]="skipped"
        GATE_MESSAGES[$gate_name]="No PHP files to check"
        GATES_SKIPPED=$((GATES_SKIPPED + 1))
        return 0
    fi

    exit_code=${exit_code:-0}

    if [[ $exit_code -eq 0 ]]; then
        GATE_RESULTS[$gate_name]="passed"
        GATE_MESSAGES[$gate_name]="Code style OK"
        GATES_PASSED=$((GATES_PASSED + 1))
        return 0
    else
        GATE_RESULTS[$gate_name]="failed"
        # Extract file count from output
        local issue_count
        issue_count=$(echo "$output" | grep -c "FAIL" || echo "?")
        GATE_MESSAGES[$gate_name]="$issue_count file(s) need formatting"
        GATES_FAILED=$((GATES_FAILED + 1))

        if [[ "$VERBOSE" == "true" ]]; then
            echo "$output"
        fi
        return 1
    fi
}

#######################################
# Gate: PHPStan (static analysis)
#######################################
run_phpstan_gate() {
    local gate_name="phpstan"
    local phpstan="${PROJECT_DIR}/vendor/bin/phpstan"

    if [[ ! -x "$phpstan" ]]; then
        GATE_RESULTS[$gate_name]="skipped"
        GATE_MESSAGES[$gate_name]="PHPStan not installed"
        GATES_SKIPPED=$((GATES_SKIPPED + 1))
        return 0
    fi

    GATES_RUN=$((GATES_RUN + 1))
    log_verbose "  Running PHPStan..."

    local output
    local exit_code

    output=$("$phpstan" analyse --no-progress --error-format=raw 2>&1) || exit_code=$?
    exit_code=${exit_code:-0}

    if [[ $exit_code -eq 0 ]]; then
        GATE_RESULTS[$gate_name]="passed"
        GATE_MESSAGES[$gate_name]="Static analysis OK"
        GATES_PASSED=$((GATES_PASSED + 1))
        return 0
    else
        GATE_RESULTS[$gate_name]="failed"
        local error_count
        error_count=$(echo "$output" | wc -l)
        GATE_MESSAGES[$gate_name]="$error_count error(s) found"
        GATES_FAILED=$((GATES_FAILED + 1))

        if [[ "$VERBOSE" == "true" ]]; then
            echo "$output"
        fi
        return 1
    fi
}

#######################################
# Gate: ESLint (JavaScript)
#######################################
run_eslint_gate() {
    local gate_name="eslint"
    local eslint="${PROJECT_DIR}/node_modules/.bin/eslint"

    if [[ ! -x "$eslint" ]]; then
        GATE_RESULTS[$gate_name]="skipped"
        GATE_MESSAGES[$gate_name]="ESLint not installed"
        GATES_SKIPPED=$((GATES_SKIPPED + 1))
        return 0
    fi

    GATES_RUN=$((GATES_RUN + 1))
    log_verbose "  Running ESLint..."

    local files
    files=$(get_files_to_check "js")

    if [[ -z "$files" && "$files" != "all" ]]; then
        GATE_RESULTS[$gate_name]="skipped"
        GATE_MESSAGES[$gate_name]="No JS files to check"
        GATES_SKIPPED=$((GATES_SKIPPED + 1))
        return 0
    fi

    local eslint_args=""
    if [[ "$FIX_MODE" == "true" ]]; then
        eslint_args="--fix"
    fi

    local output
    local exit_code

    if [[ "$files" == "all" ]]; then
        output=$("$eslint" . $eslint_args 2>&1) || exit_code=$?
    else
        output=$(echo "$files" | xargs "$eslint" $eslint_args 2>&1) || exit_code=$?
    fi
    exit_code=${exit_code:-0}

    if [[ $exit_code -eq 0 ]]; then
        GATE_RESULTS[$gate_name]="passed"
        GATE_MESSAGES[$gate_name]="Lint OK"
        GATES_PASSED=$((GATES_PASSED + 1))
        return 0
    else
        GATE_RESULTS[$gate_name]="failed"
        GATE_MESSAGES[$gate_name]="Lint errors found"
        GATES_FAILED=$((GATES_FAILED + 1))

        if [[ "$VERBOSE" == "true" ]]; then
            echo "$output"
        fi
        return 1
    fi
}

#######################################
# Gate: Prettier (code formatting)
#######################################
run_prettier_gate() {
    local gate_name="prettier"
    local prettier="${PROJECT_DIR}/node_modules/.bin/prettier"

    if [[ ! -x "$prettier" ]]; then
        GATE_RESULTS[$gate_name]="skipped"
        GATE_MESSAGES[$gate_name]="Prettier not installed"
        GATES_SKIPPED=$((GATES_SKIPPED + 1))
        return 0
    fi

    GATES_RUN=$((GATES_RUN + 1))
    log_verbose "  Running Prettier..."

    local prettier_args="--check"
    if [[ "$FIX_MODE" == "true" ]]; then
        prettier_args="--write"
    fi

    local output
    local exit_code

    output=$("$prettier" $prettier_args "**/*.{js,ts,vue,css,scss}" 2>&1) || exit_code=$?
    exit_code=${exit_code:-0}

    if [[ $exit_code -eq 0 ]]; then
        GATE_RESULTS[$gate_name]="passed"
        GATE_MESSAGES[$gate_name]="Formatting OK"
        GATES_PASSED=$((GATES_PASSED + 1))
        return 0
    else
        GATE_RESULTS[$gate_name]="failed"
        GATE_MESSAGES[$gate_name]="Formatting issues found"
        GATES_FAILED=$((GATES_FAILED + 1))

        if [[ "$VERBOSE" == "true" ]]; then
            echo "$output"
        fi
        return 1
    fi
}

#######################################
# Gate: PHP Syntax Check
#######################################
run_php_syntax_gate() {
    local gate_name="php-syntax"

    local files
    files=$(get_files_to_check "php")

    # Skip full syntax check for "all" files (too slow)
    # Only run on changed/staged/specific files
    if [[ "$files" == "all" ]]; then
        GATE_RESULTS[$gate_name]="skipped"
        GATE_MESSAGES[$gate_name]="Use --changed or --staged for syntax check"
        GATES_SKIPPED=$((GATES_SKIPPED + 1))
        return 0
    fi

    if [[ -z "$files" ]]; then
        GATE_RESULTS[$gate_name]="skipped"
        GATE_MESSAGES[$gate_name]="No PHP files to check"
        GATES_SKIPPED=$((GATES_SKIPPED + 1))
        return 0
    fi

    GATES_RUN=$((GATES_RUN + 1))
    log_verbose "  Running PHP syntax check..."

    local syntax_errors=0
    local error_files=""

    while IFS= read -r file; do
        if [[ -n "$file" && -f "${PROJECT_DIR}/${file}" ]]; then
            if ! php -l "${PROJECT_DIR}/${file}" &>/dev/null; then
                syntax_errors=$((syntax_errors + 1))
                error_files="$error_files $file"
            fi
        fi
    done <<< "$files"

    if [[ $syntax_errors -eq 0 ]]; then
        GATE_RESULTS[$gate_name]="passed"
        GATE_MESSAGES[$gate_name]="No syntax errors"
        GATES_PASSED=$((GATES_PASSED + 1))
        return 0
    else
        GATE_RESULTS[$gate_name]="failed"
        GATE_MESSAGES[$gate_name]="$syntax_errors file(s) have syntax errors"
        GATES_FAILED=$((GATES_FAILED + 1))

        if [[ "$VERBOSE" == "true" ]]; then
            echo "Files with errors:$error_files"
        fi
        return 1
    fi
}

#######################################
# Print gate summary
#######################################
print_summary() {
    log ""
    log "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
    log "${BLUE}║              Quality Gates Summary                         ║${NC}"
    log "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
    log ""

    if [[ -n "$COMPONENT_ID" ]]; then
        log "Component: ${CYAN}$COMPONENT_ID${NC}"
        log ""
    fi

    # Print each gate result
    log "Gate Results:"
    log ""

    for gate in "php-syntax" "pint" "phpstan" "eslint" "prettier"; do
        local result="${GATE_RESULTS[$gate]:-not-run}"
        local message="${GATE_MESSAGES[$gate]:-}"
        local status_icon
        local status_color

        case "$result" in
            passed)
                status_icon="✓"
                status_color="${GREEN}"
                ;;
            failed)
                status_icon="✗"
                status_color="${RED}"
                ;;
            skipped)
                status_icon="○"
                status_color="${GRAY}"
                ;;
            *)
                status_icon="-"
                status_color="${GRAY}"
                ;;
        esac

        printf "  ${status_color}%s${NC} %-12s %s\n" "$status_icon" "$gate" "$message"
    done

    log ""
    log "────────────────────────────────────────────────────────────────"

    # Summary line
    local summary_color
    if [[ $GATES_FAILED -eq 0 ]]; then
        summary_color="${GREEN}"
    else
        summary_color="${RED}"
    fi

    log "${summary_color}Gates: $GATES_PASSED passed, $GATES_FAILED failed, $GATES_SKIPPED skipped${NC}"
    log ""

    # Final verdict
    if [[ $GATES_FAILED -eq 0 ]]; then
        log "${GREEN}✓ All quality gates passed${NC}"
        log ""
        if [[ -n "$COMPONENT_ID" ]]; then
            log "Component ${CYAN}$COMPONENT_ID${NC} may be marked as ${GREEN}done${NC}."
        fi
    else
        log "${RED}✗ Quality gates failed${NC}"
        log ""
        if [[ -n "$COMPONENT_ID" ]]; then
            log "Component ${CYAN}$COMPONENT_ID${NC} ${RED}cannot${NC} be marked as done."
            log ""
            log "Fix the issues above, then re-run:"
            log "  ${CYAN}./scripts/run-quality-gates.sh --component $COMPONENT_ID${NC}"
        fi
        if [[ "$FIX_MODE" != "true" ]]; then
            log ""
            log "To auto-fix (where supported):"
            log "  ${CYAN}./scripts/run-quality-gates.sh --fix${NC}"
        fi
    fi
    log ""
}

#######################################
# Main
#######################################
main() {
    # Check cache first
    if check_cache; then
        exit $?
    fi

    if [[ "$QUIET" != "true" ]]; then
        log ""
        log "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
        log "${BLUE}║              Running Quality Gates                         ║${NC}"
        log "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
        log ""

        if [[ -n "$COMPONENT_ID" ]]; then
            log "Component: ${CYAN}$COMPONENT_ID${NC}"
        fi
        if [[ "$CHECK_STAGED" == "true" ]]; then
            log "Scope: staged files only"
        elif [[ "$CHECK_CHANGED" == "true" ]]; then
            log "Scope: changed files only"
        elif [[ -n "$SPECIFIC_FILES" ]]; then
            log "Scope: specific files"
        else
            log "Scope: all files"
        fi
        log ""
    fi

    # Run all gates
    run_php_syntax_gate
    run_pint_gate
    run_phpstan_gate
    run_eslint_gate
    run_prettier_gate

    # Print summary
    print_summary

    # Determine exit code
    local final_exit_code=$EXIT_SUCCESS

    if [[ $GATES_FAILED -gt 0 ]]; then
        # Check which type failed for specific exit code
        if [[ "${GATE_RESULTS[pint]:-}" == "failed" ]] || \
           [[ "${GATE_RESULTS[prettier]:-}" == "failed" ]]; then
            final_exit_code=$EXIT_FORMAT_FAILED
        else
            final_exit_code=$EXIT_LINT_FAILED
        fi
    fi

    # Save to cache
    if [[ "$NO_CACHE" != "true" ]]; then
        save_cache $final_exit_code
    fi

    # Print exit status
    if [[ "$QUIET" != "true" ]]; then
        print_exit_status $final_exit_code
    fi

    exit $final_exit_code
}

main
