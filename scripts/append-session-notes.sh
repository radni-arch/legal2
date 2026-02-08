#!/usr/bin/env bash
#######################################
# Session Notes Appender
#######################################
# Appends structured notes to test-results/session-notes.md after each
# autoloop iteration. Automatically rotates when file exceeds threshold.
#
# Usage:
#   ./scripts/append-session-notes.sh --component <id> --command <cmd> --outcome <pass|fail> [options]
#
# Required:
#   --component <id>    Component ID (e.g., unit:FooTest)
#   --command <cmd>     Command that was run
#   --outcome <status>  Outcome: pass, fail, skip, error
#
# Optional:
#   --failures <list>   Comma-separated list of failing test names
#   --log-path <path>   Path to detailed log file
#   --note <text>       Additional note text
#   --iterations <n>    Current iteration count
#   --sprint <n>        Current sprint number
#   --dry-run           Show what would be appended without writing
#   --help              Show this help
#
# Rotation:
#   - File rotates when exceeding MAX_LINES (default: 500)
#   - Old content archived to session-notes.{timestamp}.md
#   - Last 100 lines kept in main file after rotation
#
# Exit codes:
#   0  - Success
#   1  - Missing required arguments
#   2  - Write failed
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
NOTES_FILE="${PROJECT_DIR}/test-results/session-notes.md"
ARCHIVE_DIR="${PROJECT_DIR}/test-results/session-notes-archive"

# Rotation settings
MAX_LINES=500
KEEP_LINES=100

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
GRAY='\033[0;90m'
NC='\033[0m'

# Arguments
COMPONENT=""
COMMAND=""
OUTCOME=""
FAILURES=""
LOG_PATH=""
NOTE=""
ITERATIONS=""
SPRINT=""
DRY_RUN=false

#######################################
# Parse arguments
#######################################
while [[ $# -gt 0 ]]; do
    case "$1" in
        --component)
            COMPONENT="$2"
            shift 2
            ;;
        --command)
            COMMAND="$2"
            shift 2
            ;;
        --outcome)
            OUTCOME="$2"
            shift 2
            ;;
        --failures)
            FAILURES="$2"
            shift 2
            ;;
        --log-path)
            LOG_PATH="$2"
            shift 2
            ;;
        --note)
            NOTE="$2"
            shift 2
            ;;
        --iterations)
            ITERATIONS="$2"
            shift 2
            ;;
        --sprint)
            SPRINT="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --help|-h)
            head -35 "$0" | tail -32
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}" >&2
            exit 1
            ;;
    esac
done

#######################################
# Validate required arguments
#######################################
if [[ -z "$COMPONENT" ]]; then
    echo -e "${RED}Error: --component is required${NC}" >&2
    exit 1
fi

if [[ -z "$COMMAND" ]]; then
    echo -e "${RED}Error: --command is required${NC}" >&2
    exit 1
fi

if [[ -z "$OUTCOME" ]]; then
    echo -e "${RED}Error: --outcome is required${NC}" >&2
    exit 1
fi

#######################################
# Ensure notes file exists with header
#######################################
ensure_notes_file() {
    mkdir -p "$(dirname "$NOTES_FILE")"

    if [[ ! -f "$NOTES_FILE" ]]; then
        cat > "$NOTES_FILE" << 'EOF'
# Session Notes

Automatically generated notes from TDD autoloop iterations.

**Format:** Each entry contains timestamp, component, command, outcome, and optional details.

**Rotation:** File rotates when exceeding 500 lines. Archives stored in `session-notes-archive/`.

---

EOF
    fi
}

#######################################
# Check and perform rotation if needed
#######################################
rotate_if_needed() {
    if [[ ! -f "$NOTES_FILE" ]]; then
        return 0
    fi

    local line_count
    line_count=$(wc -l < "$NOTES_FILE")

    if [[ $line_count -gt $MAX_LINES ]]; then
        echo -e "${YELLOW}Rotating session notes (${line_count} lines > ${MAX_LINES})...${NC}"

        # Create archive directory
        mkdir -p "$ARCHIVE_DIR"

        # Archive filename with timestamp
        local archive_name="session-notes.$(date +%Y%m%d-%H%M%S).md"
        local archive_path="${ARCHIVE_DIR}/${archive_name}"

        # Copy full file to archive
        cp "$NOTES_FILE" "$archive_path"
        echo -e "${GRAY}  Archived to: ${archive_path}${NC}"

        # Keep header (first 10 lines) + last KEEP_LINES
        local header
        header=$(head -10 "$NOTES_FILE")

        local recent
        recent=$(tail -"$KEEP_LINES" "$NOTES_FILE")

        # Rebuild file
        {
            echo "$header"
            echo ""
            echo "---"
            echo ""
            echo "**[ROTATED]** Previous entries archived to \`session-notes-archive/${archive_name}\`"
            echo ""
            echo "---"
            echo ""
            echo "$recent"
        } > "$NOTES_FILE"

        local new_count
        new_count=$(wc -l < "$NOTES_FILE")
        echo -e "${GREEN}  Rotated: ${line_count} → ${new_count} lines${NC}"

        # Clean old archives (keep last 10)
        find "$ARCHIVE_DIR" -name "session-notes.*.md" -type f | \
            sort -r | tail -n +11 | xargs -r rm -f 2>/dev/null || true
    fi
}

#######################################
# Format entry
#######################################
format_entry() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    local outcome_icon
    case "$OUTCOME" in
        pass)   outcome_icon="✅" ;;
        fail)   outcome_icon="❌" ;;
        skip)   outcome_icon="⏭️" ;;
        error)  outcome_icon="⚠️" ;;
        *)      outcome_icon="❓" ;;
    esac

    # Build entry
    echo "### ${timestamp}"
    echo ""
    echo "| Field | Value |"
    echo "|-------|-------|"
    echo "| **Component** | \`${COMPONENT}\` |"
    echo "| **Outcome** | ${outcome_icon} ${OUTCOME} |"
    echo "| **Command** | \`${COMMAND}\` |"

    if [[ -n "$SPRINT" ]]; then
        echo "| **Sprint** | ${SPRINT} |"
    fi

    if [[ -n "$ITERATIONS" ]]; then
        echo "| **Iterations** | ${ITERATIONS} |"
    fi

    if [[ -n "$FAILURES" ]]; then
        echo ""
        echo "**Failing Tests:**"
        echo ""
        # Split by comma and list
        IFS=',' read -ra FAIL_ARRAY <<< "$FAILURES"
        for test in "${FAIL_ARRAY[@]}"; do
            echo "- \`$(echo "$test" | xargs)\`"
        done
    fi

    if [[ -n "$LOG_PATH" ]]; then
        echo ""
        echo "**Log:** \`${LOG_PATH}\`"
    fi

    if [[ -n "$NOTE" ]]; then
        echo ""
        echo "**Note:** ${NOTE}"
    fi

    echo ""
    echo "---"
    echo ""
}

#######################################
# Main
#######################################
main() {
    # Generate entry
    local entry
    entry=$(format_entry)

    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${BLUE}[DRY RUN] Would append to ${NOTES_FILE}:${NC}"
        echo ""
        echo "$entry"
        exit 0
    fi

    # Ensure file exists
    ensure_notes_file

    # Rotate if needed
    rotate_if_needed

    # Append entry
    echo "$entry" >> "$NOTES_FILE"

    echo -e "${GREEN}✓ Session note appended${NC}"
    echo -e "${GRAY}  Component: ${COMPONENT}${NC}"
    echo -e "${GRAY}  Outcome: ${OUTCOME}${NC}"
    echo -e "${GRAY}  File: ${NOTES_FILE}${NC}"
}

main
