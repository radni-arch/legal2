#!/usr/bin/env bash
#######################################
# Queue Progress Badge Generator
#
# Generates an SVG badge showing TDD queue completion status:
# - Completion percentage
# - Remaining components
# - Last green timestamp
#
# Usage:
#   ./scripts/generate-queue-badge.sh [output-path]
#
# Output:
#   documentation/badges/queue-status.svg (default)
#
# Environment:
#   BADGE_OUTPUT_PATH - Override default output path
#######################################

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Configuration
QUEUE_FILE="${ROOT_DIR}/test-results/tdd-test-queue.json"
DEFAULT_OUTPUT="${ROOT_DIR}/documentation/badges/queue-status.svg"
OUTPUT_PATH="${1:-${BADGE_OUTPUT_PATH:-$DEFAULT_OUTPUT}}"

# Ensure output directory exists
mkdir -p "$(dirname "$OUTPUT_PATH")"

#######################################
# Get queue statistics
#######################################
get_queue_stats() {
    if [[ ! -f "$QUEUE_FILE" ]]; then
        echo "0 0 0 0 unknown"
        return
    fi

    local stats
    stats=$(jq -r '
        .components | to_entries |
        {
            total: length,
            done: [.[] | select(.value.status == "done")] | length,
            in_progress: [.[] | select(.value.status == "in_progress")] | length,
            todo: [.[] | select(.value.status == "todo")] | length
        } |
        "\(.done) \(.in_progress) \(.todo) \(.total)"
    ' "$QUEUE_FILE" 2>/dev/null)

    if [[ -z "$stats" ]]; then
        echo "0 0 0 0 unknown"
        return
    fi

    # Get last green timestamp (most recent done component)
    local last_green
    last_green=$(jq -r '
        .components | to_entries |
        [.[] | select(.value.status == "done") | .value.completed_at // empty] |
        sort | last // "unknown"
    ' "$QUEUE_FILE" 2>/dev/null || echo "unknown")

    echo "$stats $last_green"
}

#######################################
# Determine badge color based on completion
#######################################
get_badge_color() {
    local percentage="$1"

    if [[ "$percentage" -ge 90 ]]; then
        echo "#4c1"  # bright green
    elif [[ "$percentage" -ge 70 ]]; then
        echo "#97ca00"  # green
    elif [[ "$percentage" -ge 50 ]]; then
        echo "#dfb317"  # yellow
    elif [[ "$percentage" -ge 30 ]]; then
        echo "#fe7d37"  # orange
    else
        echo "#e05d44"  # red
    fi
}

#######################################
# Generate SVG badge
#######################################
generate_svg() {
    local done="$1"
    local in_progress="$2"
    local todo="$3"
    local total="$4"
    local last_green="$5"

    # Calculate percentage
    local percentage=0
    if [[ "$total" -gt 0 ]]; then
        percentage=$((done * 100 / total))
    fi

    local remaining=$((total - done))
    local color
    color=$(get_badge_color "$percentage")

    # Format last green timestamp
    local last_green_display="never"
    if [[ "$last_green" != "unknown" ]] && [[ "$last_green" != "null" ]]; then
        # Try to format the date (just show date part)
        last_green_display=$(echo "$last_green" | cut -d'T' -f1 2>/dev/null || echo "$last_green")
    fi

    # Badge dimensions
    local label_text="TDD Queue"
    local value_text="${percentage}% (${remaining} left)"

    # Calculate text widths (approximate)
    local label_width=$((${#label_text} * 7 + 10))
    local value_width=$((${#value_text} * 7 + 10))
    local total_width=$((label_width + value_width))

    cat << EOF
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${total_width}" height="20" role="img" aria-label="${label_text}: ${value_text}">
  <title>${label_text}: ${percentage}% complete, ${remaining} remaining, last green: ${last_green_display}</title>
  <linearGradient id="s" x2="0" y2="100%">
    <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
    <stop offset="1" stop-opacity=".1"/>
  </linearGradient>
  <clipPath id="r">
    <rect width="${total_width}" height="20" rx="3" fill="#fff"/>
  </clipPath>
  <g clip-path="url(#r)">
    <rect width="${label_width}" height="20" fill="#555"/>
    <rect x="${label_width}" width="${value_width}" height="20" fill="${color}"/>
    <rect width="${total_width}" height="20" fill="url(#s)"/>
  </g>
  <g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" text-rendering="geometricPrecision" font-size="110">
    <text aria-hidden="true" x="$((label_width * 5))" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="$((label_width * 10 - 100))">${label_text}</text>
    <text x="$((label_width * 5))" y="140" transform="scale(.1)" fill="#fff" textLength="$((label_width * 10 - 100))">${label_text}</text>
    <text aria-hidden="true" x="$((label_width * 10 + value_width * 5))" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="$((value_width * 10 - 100))">${value_text}</text>
    <text x="$((label_width * 10 + value_width * 5))" y="140" transform="scale(.1)" fill="#fff" textLength="$((value_width * 10 - 100))">${value_text}</text>
  </g>
</svg>
EOF
}

#######################################
# Generate detailed badge with more info
#######################################
generate_detailed_svg() {
    local done="$1"
    local in_progress="$2"
    local todo="$3"
    local total="$4"
    local last_green="$5"

    # Calculate percentage
    local percentage=0
    if [[ "$total" -gt 0 ]]; then
        percentage=$((done * 100 / total))
    fi

    local remaining=$((total - done))
    local color
    color=$(get_badge_color "$percentage")

    # Format last green timestamp
    local last_green_display="never"
    if [[ "$last_green" != "unknown" ]] && [[ "$last_green" != "null" ]]; then
        last_green_display=$(echo "$last_green" | cut -d'T' -f1 2>/dev/null || echo "$last_green")
    fi

    # Badge dimensions for detailed view
    local width=280
    local height=60

    cat << EOF
<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" role="img" aria-label="TDD Queue Progress">
  <title>TDD Queue: ${percentage}% complete, ${done}/${total} done, ${remaining} remaining, last green: ${last_green_display}</title>

  <!-- Background -->
  <rect width="${width}" height="${height}" rx="4" fill="#2d2d2d"/>

  <!-- Progress bar background -->
  <rect x="10" y="35" width="$((width - 20))" height="8" rx="4" fill="#444"/>

  <!-- Progress bar fill -->
  <rect x="10" y="35" width="$(( (width - 20) * percentage / 100 ))" height="8" rx="4" fill="${color}"/>

  <!-- Title -->
  <text x="10" y="20" fill="#fff" font-family="Verdana,sans-serif" font-size="12" font-weight="bold">TDD Queue Progress</text>

  <!-- Percentage -->
  <text x="$((width - 10))" y="20" fill="${color}" font-family="Verdana,sans-serif" font-size="12" font-weight="bold" text-anchor="end">${percentage}%</text>

  <!-- Stats line -->
  <text x="10" y="55" fill="#aaa" font-family="Verdana,sans-serif" font-size="9">
    <tspan fill="#4c1">✓ ${done}</tspan>
    <tspan fill="#dfb317"> ⟳ ${in_progress}</tspan>
    <tspan fill="#888"> ○ ${todo}</tspan>
    <tspan fill="#666"> | Last: ${last_green_display}</tspan>
  </text>
</svg>
EOF
}

#######################################
# Main execution
#######################################
echo "Generating queue progress badge..."

# Get statistics
read -r DONE IN_PROGRESS TODO TOTAL LAST_GREEN <<< "$(get_queue_stats)"

echo "  Queue stats:"
echo "    Done:        $DONE"
echo "    In progress: $IN_PROGRESS"
echo "    Todo:        $TODO"
echo "    Total:       $TOTAL"
echo "    Last green:  $LAST_GREEN"

# Calculate percentage for display
PERCENTAGE=0
if [[ "$TOTAL" -gt 0 ]]; then
    PERCENTAGE=$((DONE * 100 / TOTAL))
fi
echo "    Completion:  ${PERCENTAGE}%"

# Generate simple badge
generate_svg "$DONE" "$IN_PROGRESS" "$TODO" "$TOTAL" "$LAST_GREEN" > "$OUTPUT_PATH"
echo ""
echo "Badge generated: $OUTPUT_PATH"

# Also generate detailed badge
DETAILED_OUTPUT="${OUTPUT_PATH%.svg}-detailed.svg"
generate_detailed_svg "$DONE" "$IN_PROGRESS" "$TODO" "$TOTAL" "$LAST_GREEN" > "$DETAILED_OUTPUT"
echo "Detailed badge:  $DETAILED_OUTPUT"

# Output path for scripting
echo ""
echo "$OUTPUT_PATH"
