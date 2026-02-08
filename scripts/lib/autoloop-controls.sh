#!/usr/bin/env bash
#######################################
# Autoloop Controls
#######################################
# Provides timebox and iteration controls for the TDD autoloop.
# Use these functions to enforce safe limits on autoloop runs.
#
# Usage:
#   source scripts/lib/autoloop-controls.sh
#   autoloop_init --timebox 60m --max-iterations 3
#
#   # In your loop:
#   autoloop_check_limits || exit $?
#   autoloop_increment_iteration
#
# Options:
#   --timebox <duration>    Max runtime (e.g., 30m, 1h, 90m)
#   --max-iterations <n>    Max iterations (components worked on)
#   --max-slices <n>        Max TDD slices across all components
#
# Exit codes (from autoloop-exit-codes.sh):
#   70 - EXIT_TIMEBOX_EXCEEDED
#   71 - EXIT_MAX_ITERATIONS_EXCEEDED
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Source exit codes
# shellcheck source=autoloop-exit-codes.sh
source "${SCRIPT_DIR}/autoloop-exit-codes.sh"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

#######################################
# State variables
#######################################
AUTOLOOP_START_TIME=""
AUTOLOOP_TIMEBOX_SECONDS=0
AUTOLOOP_MAX_ITERATIONS=0
AUTOLOOP_MAX_SLICES=0
AUTOLOOP_CURRENT_ITERATION=0
AUTOLOOP_CURRENT_SLICES=0
AUTOLOOP_INITIALIZED=false

#######################################
# Parse duration string to seconds
# Supports: 30s, 5m, 1h, 90m, 1h30m
#######################################
parse_duration() {
    local duration="$1"
    local total_seconds=0

    # Extract hours
    if [[ "$duration" =~ ([0-9]+)h ]]; then
        total_seconds=$((total_seconds + ${BASH_REMATCH[1]} * 3600))
    fi

    # Extract minutes
    if [[ "$duration" =~ ([0-9]+)m ]]; then
        total_seconds=$((total_seconds + ${BASH_REMATCH[1]} * 60))
    fi

    # Extract seconds
    if [[ "$duration" =~ ([0-9]+)s ]]; then
        total_seconds=$((total_seconds + ${BASH_REMATCH[1]}))
    fi

    # If just a number, assume minutes
    if [[ "$duration" =~ ^[0-9]+$ ]]; then
        total_seconds=$((duration * 60))
    fi

    echo "$total_seconds"
}

#######################################
# Format seconds as human-readable duration
#######################################
format_duration() {
    local seconds=$1
    local hours=$((seconds / 3600))
    local minutes=$(((seconds % 3600) / 60))
    local secs=$((seconds % 60))

    if [[ $hours -gt 0 ]]; then
        printf "%dh %dm %ds" "$hours" "$minutes" "$secs"
    elif [[ $minutes -gt 0 ]]; then
        printf "%dm %ds" "$minutes" "$secs"
    else
        printf "%ds" "$secs"
    fi
}

#######################################
# Initialize autoloop controls
# Usage: autoloop_init [--timebox <duration>] [--max-iterations <n>] [--max-slices <n>]
#######################################
autoloop_init() {
    AUTOLOOP_START_TIME=$(date +%s)
    AUTOLOOP_CURRENT_ITERATION=0
    AUTOLOOP_CURRENT_SLICES=0
    AUTOLOOP_INITIALIZED=true

    while [[ $# -gt 0 ]]; do
        case "$1" in
            --timebox)
                AUTOLOOP_TIMEBOX_SECONDS=$(parse_duration "$2")
                shift 2
                ;;
            --max-iterations)
                AUTOLOOP_MAX_ITERATIONS="$2"
                shift 2
                ;;
            --max-slices)
                AUTOLOOP_MAX_SLICES="$2"
                shift 2
                ;;
            *)
                shift
                ;;
        esac
    done

    echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║              Autoloop Controls Initialized                 ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""

    if [[ $AUTOLOOP_TIMEBOX_SECONDS -gt 0 ]]; then
        echo -e "  Timebox:        ${CYAN}$(format_duration $AUTOLOOP_TIMEBOX_SECONDS)${NC}"
    else
        echo -e "  Timebox:        ${YELLOW}unlimited${NC}"
    fi

    if [[ $AUTOLOOP_MAX_ITERATIONS -gt 0 ]]; then
        echo -e "  Max iterations: ${CYAN}${AUTOLOOP_MAX_ITERATIONS}${NC}"
    else
        echo -e "  Max iterations: ${YELLOW}unlimited${NC}"
    fi

    if [[ $AUTOLOOP_MAX_SLICES -gt 0 ]]; then
        echo -e "  Max slices:     ${CYAN}${AUTOLOOP_MAX_SLICES}${NC}"
    else
        echo -e "  Max slices:     ${YELLOW}unlimited${NC}"
    fi

    echo ""
}

#######################################
# Get elapsed time in seconds
#######################################
autoloop_elapsed() {
    if [[ -z "$AUTOLOOP_START_TIME" ]]; then
        echo 0
        return
    fi

    local now
    now=$(date +%s)
    echo $((now - AUTOLOOP_START_TIME))
}

#######################################
# Get remaining time in seconds (0 if no timebox)
#######################################
autoloop_remaining() {
    if [[ $AUTOLOOP_TIMEBOX_SECONDS -eq 0 ]]; then
        echo -1  # Unlimited
        return
    fi

    local elapsed
    elapsed=$(autoloop_elapsed)
    local remaining=$((AUTOLOOP_TIMEBOX_SECONDS - elapsed))

    if [[ $remaining -lt 0 ]]; then
        remaining=0
    fi

    echo "$remaining"
}

#######################################
# Check if limits are exceeded
# Returns: 0 if within limits, exit code if exceeded
#######################################
autoloop_check_limits() {
    if [[ "$AUTOLOOP_INITIALIZED" != "true" ]]; then
        echo -e "${YELLOW}Warning: autoloop_check_limits called before autoloop_init${NC}" >&2
        return 0
    fi

    # Check timebox
    if [[ $AUTOLOOP_TIMEBOX_SECONDS -gt 0 ]]; then
        local elapsed
        elapsed=$(autoloop_elapsed)

        if [[ $elapsed -ge $AUTOLOOP_TIMEBOX_SECONDS ]]; then
            echo ""
            echo -e "${YELLOW}════════════════════════════════════════════════════════════${NC}"
            echo -e "${YELLOW}  TIMEBOX EXCEEDED${NC}"
            echo -e "${YELLOW}════════════════════════════════════════════════════════════${NC}"
            echo ""
            echo -e "  Elapsed:    $(format_duration $elapsed)"
            echo -e "  Timebox:    $(format_duration $AUTOLOOP_TIMEBOX_SECONDS)"
            echo -e "  Iterations: ${AUTOLOOP_CURRENT_ITERATION}"
            echo -e "  Slices:     ${AUTOLOOP_CURRENT_SLICES}"
            echo ""
            echo -e "  ${YELLOW}Stopping autoloop safely. Work completed so far is preserved.${NC}"
            echo ""

            print_exit_status $EXIT_TIMEBOX_EXCEEDED "Elapsed: $(format_duration $elapsed)"
            return $EXIT_TIMEBOX_EXCEEDED
        fi
    fi

    # Check max iterations
    if [[ $AUTOLOOP_MAX_ITERATIONS -gt 0 ]]; then
        if [[ $AUTOLOOP_CURRENT_ITERATION -ge $AUTOLOOP_MAX_ITERATIONS ]]; then
            echo ""
            echo -e "${YELLOW}════════════════════════════════════════════════════════════${NC}"
            echo -e "${YELLOW}  MAX ITERATIONS EXCEEDED${NC}"
            echo -e "${YELLOW}════════════════════════════════════════════════════════════${NC}"
            echo ""
            echo -e "  Iterations: ${AUTOLOOP_CURRENT_ITERATION} / ${AUTOLOOP_MAX_ITERATIONS}"
            echo -e "  Slices:     ${AUTOLOOP_CURRENT_SLICES}"
            echo -e "  Elapsed:    $(format_duration $(autoloop_elapsed))"
            echo ""
            echo -e "  ${YELLOW}Stopping autoloop safely. Work completed so far is preserved.${NC}"
            echo ""

            print_exit_status $EXIT_MAX_ITERATIONS_EXCEEDED "Iterations: ${AUTOLOOP_CURRENT_ITERATION}"
            return $EXIT_MAX_ITERATIONS_EXCEEDED
        fi
    fi

    # Check max slices
    if [[ $AUTOLOOP_MAX_SLICES -gt 0 ]]; then
        if [[ $AUTOLOOP_CURRENT_SLICES -ge $AUTOLOOP_MAX_SLICES ]]; then
            echo ""
            echo -e "${YELLOW}════════════════════════════════════════════════════════════${NC}"
            echo -e "${YELLOW}  MAX SLICES EXCEEDED${NC}"
            echo -e "${YELLOW}════════════════════════════════════════════════════════════${NC}"
            echo ""
            echo -e "  Slices:     ${AUTOLOOP_CURRENT_SLICES} / ${AUTOLOOP_MAX_SLICES}"
            echo -e "  Iterations: ${AUTOLOOP_CURRENT_ITERATION}"
            echo -e "  Elapsed:    $(format_duration $(autoloop_elapsed))"
            echo ""
            echo -e "  ${YELLOW}Stopping autoloop safely. Work completed so far is preserved.${NC}"
            echo ""

            print_exit_status $EXIT_MAX_ITERATIONS_EXCEEDED "Slices: ${AUTOLOOP_CURRENT_SLICES}"
            return $EXIT_MAX_ITERATIONS_EXCEEDED
        fi
    fi

    return 0
}

#######################################
# Increment iteration count
#######################################
autoloop_increment_iteration() {
    AUTOLOOP_CURRENT_ITERATION=$((AUTOLOOP_CURRENT_ITERATION + 1))
}

#######################################
# Increment slice count
#######################################
autoloop_increment_slice() {
    AUTOLOOP_CURRENT_SLICES=$((AUTOLOOP_CURRENT_SLICES + 1))
}

#######################################
# Print current status
#######################################
autoloop_status() {
    local elapsed
    elapsed=$(autoloop_elapsed)
    local remaining
    remaining=$(autoloop_remaining)

    echo ""
    echo -e "${BLUE}─── Autoloop Status ───${NC}"
    echo -e "  Elapsed:    $(format_duration $elapsed)"

    if [[ $remaining -ge 0 ]]; then
        echo -e "  Remaining:  $(format_duration $remaining)"
    fi

    echo -e "  Iterations: ${AUTOLOOP_CURRENT_ITERATION}"

    if [[ $AUTOLOOP_MAX_ITERATIONS -gt 0 ]]; then
        echo -e "              (max: ${AUTOLOOP_MAX_ITERATIONS})"
    fi

    echo -e "  Slices:     ${AUTOLOOP_CURRENT_SLICES}"

    if [[ $AUTOLOOP_MAX_SLICES -gt 0 ]]; then
        echo -e "              (max: ${AUTOLOOP_MAX_SLICES})"
    fi

    echo ""
}

#######################################
# Get status as JSON (for programmatic use)
#######################################
autoloop_status_json() {
    local elapsed
    elapsed=$(autoloop_elapsed)
    local remaining
    remaining=$(autoloop_remaining)

    cat <<EOF
{
  "elapsed_seconds": $elapsed,
  "elapsed_formatted": "$(format_duration $elapsed)",
  "remaining_seconds": $remaining,
  "timebox_seconds": $AUTOLOOP_TIMEBOX_SECONDS,
  "current_iteration": $AUTOLOOP_CURRENT_ITERATION,
  "max_iterations": $AUTOLOOP_MAX_ITERATIONS,
  "current_slices": $AUTOLOOP_CURRENT_SLICES,
  "max_slices": $AUTOLOOP_MAX_SLICES
}
EOF
}
