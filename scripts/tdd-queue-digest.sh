#!/usr/bin/env bash
#######################################
# TDD Queue Digest Generator
#######################################
# Parses test-results/tdd-test-queue.json and emits a compact snapshot
# of top TODO/In-Progress entries for quick reference.
#
# Usage: ./scripts/tdd-queue-digest.sh [--limit N]
#
# Options:
#   --limit N    Show top N entries (default: 5)
#
# Exit codes:
#   0 = success
#   1 = queue file missing or malformed
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Default values (can be overridden via env or args)
QUEUE_FILE="${TDD_QUEUE_FILE:-${PROJECT_DIR}/test-results/tdd-test-queue.json}"
LIMIT=5

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --limit)
            LIMIT="$2"
            shift 2
            ;;
        --file)
            QUEUE_FILE="$2"
            shift 2
            ;;
        *)
            shift
            ;;
    esac
done

#######################################
# Check if queue file exists
#######################################
if [[ ! -f "$QUEUE_FILE" ]]; then
    echo "Queue not found at ${QUEUE_FILE}"
    exit 1
fi

#######################################
# Check if jq is available
#######################################
if ! command -v jq &>/dev/null; then
    echo "Error: jq is required but not installed"
    exit 1
fi

#######################################
# Validate JSON and extract components
#######################################
if ! jq empty "$QUEUE_FILE" 2>/dev/null; then
    echo "Error: Malformed JSON in ${QUEUE_FILE}"
    echo "Run: jq . ${QUEUE_FILE} to see parse errors"
    exit 1
fi

# Check if components array exists and has entries
COMPONENT_COUNT=$(jq '.components | length' "$QUEUE_FILE" 2>/dev/null)

if [[ -z "$COMPONENT_COUNT" || "$COMPONENT_COUNT" == "null" ]]; then
    echo "Error: No 'components' array found in queue file"
    exit 1
fi

if [[ "$COMPONENT_COUNT" -eq 0 ]]; then
    echo "Queue empty (0 components)"
    exit 0
fi

#######################################
# Extract and sort components
# Priority: in_progress first, then todo
# Skip 'done' entries
#######################################
DIGEST=$(jq -r --argjson limit "$LIMIT" '
  .components
  | map(select(.status == "in_progress" or .status == "todo"))
  | sort_by(if .status == "in_progress" then 0 else 1 end)
  | .[:$limit]
  | to_entries
  | map(
      "\(.key + 1)) \(.value.id) [\(.value.status)] " +
      "sprint=\(.value.sprint) " +
      "domain=\(.value.domain) " +
      "tests=\(.value.test_path)"
    )
  | .[]
' "$QUEUE_FILE" 2>/dev/null)

if [[ -z "$DIGEST" ]]; then
    echo "Queue complete (all components done)"
    exit 0
fi

#######################################
# Count totals for header
#######################################
IN_PROGRESS=$(jq '[.components[] | select(.status == "in_progress")] | length' "$QUEUE_FILE")
TODO=$(jq '[.components[] | select(.status == "todo")] | length' "$QUEUE_FILE")
DONE=$(jq '[.components[] | select(.status == "done")] | length' "$QUEUE_FILE")

#######################################
# Output
#######################################
echo "TDD Queue (top ${LIMIT} of ${IN_PROGRESS} in_progress + ${TODO} todo, ${DONE} done):"
echo "$DIGEST"
