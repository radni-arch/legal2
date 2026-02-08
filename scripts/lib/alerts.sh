#!/bin/bash
#######################################
# Telemetry Alerts Library
#
# Checks for threshold violations and emits prominent alerts:
# - Consecutive failures
# - Component failure streaks
# - Duration spikes
# - Hourly failure rate
#
# Usage:
#   source scripts/lib/alerts.sh
#   alerts_init
#   alerts_check_all "focused:SomeTest" "fail" 45
#
# Configuration:
#   test-results/alert-thresholds.json
#   Environment overrides: ALERT_CONSECUTIVE_FAILURES, etc.
#######################################

ALERTS_DIR="${ALERTS_DIR:-test-results}"
ALERTS_CONFIG="${ALERTS_DIR}/alert-thresholds.json"
ALERTS_METRICS="${ALERTS_DIR}/metrics/autoloop.jsonl"

# Colors for alerts
ALERT_RED='\033[1;31m'
ALERT_YELLOW='\033[1;33m'
ALERT_CYAN='\033[0;36m'
ALERT_NC='\033[0m'
ALERT_BG_RED='\033[41m'
ALERT_WHITE='\033[1;37m'

# Cached config values
_ALERT_CONSECUTIVE_FAILURES=""
_ALERT_COMPONENT_STREAK=""
_ALERT_DURATION_MULTIPLIER=""
_ALERT_HOURLY_FAILURE_RATE=""
_ALERT_SHOW_LAST_N=""

#######################################
# Initialize alert system, load config
#######################################
alerts_init() {
    if [[ ! -f "$ALERTS_CONFIG" ]]; then
        # Use defaults if no config
        _ALERT_CONSECUTIVE_FAILURES="${ALERT_CONSECUTIVE_FAILURES:-3}"
        _ALERT_COMPONENT_STREAK="${ALERT_COMPONENT_STREAK:-2}"
        _ALERT_DURATION_MULTIPLIER="${ALERT_DURATION_MULTIPLIER:-3.0}"
        _ALERT_HOURLY_FAILURE_RATE="${ALERT_HOURLY_FAILURE_RATE:-50}"
        _ALERT_SHOW_LAST_N="${ALERT_SHOW_LAST_N:-5}"
        return
    fi

    # Load from config, allow env overrides
    _ALERT_CONSECUTIVE_FAILURES="${ALERT_CONSECUTIVE_FAILURES:-$(jq -r '.thresholds.consecutive_failures.limit // 3' "$ALERTS_CONFIG")}"
    _ALERT_COMPONENT_STREAK="${ALERT_COMPONENT_STREAK:-$(jq -r '.thresholds.component_failure_streak.limit // 2' "$ALERTS_CONFIG")}"
    _ALERT_DURATION_MULTIPLIER="${ALERT_DURATION_MULTIPLIER:-$(jq -r '.thresholds.duration_spike.multiplier // 3.0' "$ALERTS_CONFIG")}"
    _ALERT_HOURLY_FAILURE_RATE="${ALERT_HOURLY_FAILURE_RATE:-$(jq -r '.thresholds.hourly_failure_rate.max_percent // 50' "$ALERTS_CONFIG")}"
    _ALERT_SHOW_LAST_N="${ALERT_SHOW_LAST_N:-$(jq -r '.alert_display.show_last_n_failures // 5' "$ALERTS_CONFIG")}"
}

#######################################
# Emit a prominent alert box
# Arguments:
#   $1 - Alert title
#   $2 - Alert message (can be multiline)
#   $3 - Alert level (error, warning)
#######################################
alerts_emit() {
    local title="$1"
    local message="$2"
    local level="${3:-error}"

    local color="$ALERT_RED"
    local icon="!!!"
    if [[ "$level" == "warning" ]]; then
        color="$ALERT_YELLOW"
        icon="!!!"
    fi

    echo ""
    echo -e "${color}╔══════════════════════════════════════════════════════════════════╗${ALERT_NC}"
    echo -e "${color}║${ALERT_NC} ${ALERT_BG_RED}${ALERT_WHITE} ${icon} ALERT: ${title} ${icon} ${ALERT_NC}"
    echo -e "${color}╠══════════════════════════════════════════════════════════════════╣${ALERT_NC}"

    # Print each line of message
    while IFS= read -r line; do
        printf "${color}║${ALERT_NC}  %-64s ${color}║${ALERT_NC}\n" "$line"
    done <<< "$message"

    echo -e "${color}╚══════════════════════════════════════════════════════════════════╝${ALERT_NC}"
    echo ""
}

#######################################
# Check for N consecutive failures
# Returns 0 if alert triggered, 1 otherwise
#######################################
alerts_check_consecutive_failures() {
    if [[ ! -f "$ALERTS_METRICS" ]]; then
        return 1
    fi

    local limit="${_ALERT_CONSECUTIVE_FAILURES:-3}"

    # Get last N entries and check if all are failures
    local last_n
    last_n=$(tail -n "$limit" "$ALERTS_METRICS" 2>/dev/null)

    if [[ -z "$last_n" ]]; then
        return 1
    fi

    # Count entries
    local count
    count=$(echo "$last_n" | wc -l)

    if [[ "$count" -lt "$limit" ]]; then
        return 1
    fi

    # Check if all are failures
    local fail_count
    fail_count=$(echo "$last_n" | jq -r '.outcome' | grep -cE '^(fail|error)$' || echo "0")

    if [[ "$fail_count" -ge "$limit" ]]; then
        # Get failing components for display
        local failing_components
        failing_components=$(echo "$last_n" | jq -r '.component_id' | tail -n "$_ALERT_SHOW_LAST_N")

        local message="Threshold: ${limit} consecutive failures
Last failing tests:
$(echo "$failing_components" | sed 's/^/  - /')"

        alerts_emit "CONSECUTIVE FAILURES" "$message" "error"
        return 0
    fi

    return 1
}

#######################################
# Check for component-specific failure streak
# Arguments:
#   $1 - Component ID to check
# Returns 0 if alert triggered, 1 otherwise
#######################################
alerts_check_component_streak() {
    local component_id="$1"

    if [[ ! -f "$ALERTS_METRICS" ]] || [[ -z "$component_id" ]]; then
        return 1
    fi

    local limit="${_ALERT_COMPONENT_STREAK:-2}"

    # Get last N entries for this component
    local component_entries
    component_entries=$(grep "\"component_id\":\"$component_id\"" "$ALERTS_METRICS" | tail -n "$limit" 2>/dev/null)

    if [[ -z "$component_entries" ]]; then
        return 1
    fi

    # Count entries
    local count
    count=$(echo "$component_entries" | wc -l)

    if [[ "$count" -lt "$limit" ]]; then
        return 1
    fi

    # Check if all are failures
    local fail_count
    fail_count=$(echo "$component_entries" | jq -r '.outcome' | grep -cE '^(fail|error)$' || echo "0")

    if [[ "$fail_count" -ge "$limit" ]]; then
        # Get timestamps for context
        local timestamps
        timestamps=$(echo "$component_entries" | jq -r '.timestamp' | tail -n 3)

        local message="Component: ${component_id}
Threshold: ${limit} consecutive failures for same component
Recent failures:
$(echo "$timestamps" | sed 's/^/  - /')"

        alerts_emit "COMPONENT FAILURE STREAK" "$message" "error"
        return 0
    fi

    return 1
}

#######################################
# Check for duration spike
# Arguments:
#   $1 - Component ID
#   $2 - Current duration in seconds
# Returns 0 if alert triggered, 1 otherwise
#######################################
alerts_check_duration_spike() {
    local component_id="$1"
    local current_duration="$2"

    if [[ ! -f "$ALERTS_METRICS" ]] || [[ -z "$current_duration" ]]; then
        return 1
    fi

    local multiplier="${_ALERT_DURATION_MULTIPLIER:-3.0}"

    # Get baseline average for this component (last 10 passing runs)
    local baseline_data
    baseline_data=$(grep "\"component_id\":\"$component_id\"" "$ALERTS_METRICS" | \
        jq -r 'select(.outcome == "pass") | .duration_seconds' | \
        tail -n 10 2>/dev/null)

    if [[ -z "$baseline_data" ]]; then
        return 1
    fi

    # Count baseline entries
    local baseline_count
    baseline_count=$(echo "$baseline_data" | wc -l)

    # Need minimum baseline runs
    local min_runs
    min_runs=$(jq -r '.thresholds.duration_spike.min_baseline_runs // 5' "$ALERTS_CONFIG" 2>/dev/null || echo "5")

    if [[ "$baseline_count" -lt "$min_runs" ]]; then
        return 1
    fi

    # Calculate average
    local sum=0
    while IFS= read -r dur; do
        sum=$((sum + dur))
    done <<< "$baseline_data"

    local avg=$((sum / baseline_count))
    local threshold
    threshold=$(echo "$avg * $multiplier" | bc 2>/dev/null | cut -d. -f1)

    if [[ -z "$threshold" ]]; then
        # bc not available, use integer math
        threshold=$((avg * 3))
    fi

    if [[ "$current_duration" -gt "$threshold" ]]; then
        local message="Component: ${component_id}
Current duration: ${current_duration}s
Baseline average: ${avg}s (from ${baseline_count} runs)
Threshold: ${threshold}s (${multiplier}x baseline)"

        alerts_emit "DURATION SPIKE" "$message" "warning"
        return 0
    fi

    return 1
}

#######################################
# Check hourly failure rate
# Returns 0 if alert triggered, 1 otherwise
#######################################
alerts_check_hourly_failure_rate() {
    if [[ ! -f "$ALERTS_METRICS" ]]; then
        return 1
    fi

    local max_rate="${_ALERT_HOURLY_FAILURE_RATE:-50}"
    local current_hour
    current_hour=$(date +%Y-%m-%dT%H)

    # Get entries from current hour
    local hour_entries
    hour_entries=$(jq -c --arg hour "$current_hour" 'select(.timestamp | startswith($hour))' "$ALERTS_METRICS" 2>/dev/null)

    if [[ -z "$hour_entries" ]]; then
        return 1
    fi

    local total_count
    total_count=$(echo "$hour_entries" | wc -l)

    # Need minimum runs to trigger
    local min_runs
    min_runs=$(jq -r '.thresholds.hourly_failure_rate.min_runs // 5' "$ALERTS_CONFIG" 2>/dev/null || echo "5")

    if [[ "$total_count" -lt "$min_runs" ]]; then
        return 1
    fi

    local fail_count
    fail_count=$(echo "$hour_entries" | jq -r '.outcome' | grep -cE '^(fail|error)$' || echo "0")

    local rate=$((fail_count * 100 / total_count))

    if [[ "$rate" -gt "$max_rate" ]]; then
        # Get failing components
        local failing
        failing=$(echo "$hour_entries" | jq -r 'select(.outcome != "pass") | .component_id' | sort | uniq -c | sort -rn | head -5)

        local message="Hour: ${current_hour}
Failure rate: ${rate}% (${fail_count}/${total_count} runs)
Threshold: ${max_rate}%
Top failing components:
$(echo "$failing" | sed 's/^/  /')"

        alerts_emit "HIGH FAILURE RATE" "$message" "error"
        return 0
    fi

    return 1
}

#######################################
# Run all alert checks
# Arguments:
#   $1 - Component ID
#   $2 - Outcome (pass/fail/error)
#   $3 - Duration in seconds
# Returns number of alerts triggered
#######################################
alerts_check_all() {
    local component_id="$1"
    local outcome="$2"
    local duration="${3:-0}"

    local alerts_triggered=0
    local consecutive_alert_fired=false

    # Only check alerts on failures
    if [[ "$outcome" == "pass" ]]; then
        # Still check duration spike on pass
        if alerts_check_duration_spike "$component_id" "$duration"; then
            ((alerts_triggered++))
        fi
        return "$alerts_triggered"
    fi

    # Check all failure-related alerts
    if alerts_check_consecutive_failures; then
        ((alerts_triggered++))
        consecutive_alert_fired=true
    fi

    if alerts_check_component_streak "$component_id"; then
        ((alerts_triggered++))
    fi

    if alerts_check_duration_spike "$component_id" "$duration"; then
        ((alerts_triggered++))
    fi

    if alerts_check_hourly_failure_rate; then
        ((alerts_triggered++))
    fi

    # Auto-generate support bundle on consecutive failures
    if [[ "$consecutive_alert_fired" == "true" ]]; then
        alerts_generate_support_bundle "$component_id" "Consecutive failures threshold exceeded"
    fi

    return "$alerts_triggered"
}

#######################################
# Generate support bundle (called on threshold)
# Arguments:
#   $1 - Component ID
#   $2 - Reason
#######################################
alerts_generate_support_bundle() {
    local component_id="$1"
    local reason="$2"

    local bundle_script="${ALERTS_DIR}/../scripts/make-support-bundle.sh"

    if [[ ! -x "$bundle_script" ]]; then
        # Try alternate path
        bundle_script="$(dirname "${BASH_SOURCE[0]}")/../make-support-bundle.sh"
    fi

    if [[ -x "$bundle_script" ]]; then
        echo ""
        echo -e "${ALERT_CYAN}Generating support bundle for escalation...${ALERT_NC}"
        local bundle_path
        bundle_path=$("$bundle_script" "${reason}: ${component_id}" 2>/dev/null | tail -1)
        if [[ -n "$bundle_path" ]] && [[ -f "$bundle_path" ]]; then
            echo ""
            echo -e "${ALERT_YELLOW}╔══════════════════════════════════════════════════════════════════╗${ALERT_NC}"
            echo -e "${ALERT_YELLOW}║${ALERT_NC}  SUPPORT BUNDLE READY"
            echo -e "${ALERT_YELLOW}║${ALERT_NC}  ${bundle_path}"
            echo -e "${ALERT_YELLOW}╚══════════════════════════════════════════════════════════════════╝${ALERT_NC}"
        fi
    fi
}

#######################################
# Get current alert thresholds summary
#######################################
alerts_show_config() {
    alerts_init

    echo "Alert Thresholds:"
    echo "  Consecutive failures: $_ALERT_CONSECUTIVE_FAILURES"
    echo "  Component streak:     $_ALERT_COMPONENT_STREAK"
    echo "  Duration multiplier:  $_ALERT_DURATION_MULTIPLIER"
    echo "  Hourly failure rate:  $_ALERT_HOURLY_FAILURE_RATE%"
    echo "  Show last N failures: $_ALERT_SHOW_LAST_N"
    echo ""
    echo "Override via environment:"
    echo "  ALERT_CONSECUTIVE_FAILURES, ALERT_COMPONENT_STREAK,"
    echo "  ALERT_DURATION_MULTIPLIER, ALERT_HOURLY_FAILURE_RATE"
}
