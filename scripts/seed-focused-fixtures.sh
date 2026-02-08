#!/usr/bin/env bash
#######################################
# Focused Fixture Seeding
#######################################
# Seeds only the fixtures needed for a specific component/domain.
# Much faster than full seeding for focused TDD work.
#
# Usage:
#   ./scripts/seed-focused-fixtures.sh --component <name>
#   ./scripts/seed-focused-fixtures.sh --domain <domain>
#   ./scripts/seed-focused-fixtures.sh --full
#   ./scripts/seed-focused-fixtures.sh --list
#
# Examples:
#   ./scripts/seed-focused-fixtures.sh --domain vector_stores
#   ./scripts/seed-focused-fixtures.sh --component livewire:VectorStoreManagerTest
#   ./scripts/seed-focused-fixtures.sh --full
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
FIXTURE_MAP="${PROJECT_DIR}/database/seeders/fixture-map.json"
QUEUE_FILE="${PROJECT_DIR}/test-results/tdd-test-queue.json"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Parse arguments
MODE=""
TARGET=""
VERBOSE=false
FRESH=false

show_help() {
    cat <<EOF
Focused Fixture Seeding - Fast, component-scoped database seeding

Usage:
  $(basename "$0") --component <id>    Seed fixtures for a component from queue
  $(basename "$0") --domain <name>     Seed fixtures for a domain
  $(basename "$0") --full              Run full seed (all fixtures)
  $(basename "$0") --list              List available domains and mappings
  $(basename "$0") --seeders <list>    Run specific seeders (comma-separated)

Options:
  --fresh       Run migrate:fresh before seeding
  --verbose     Show detailed output
  --help        Show this help

Examples:
  $(basename "$0") --domain vector_stores
  $(basename "$0") --component livewire:VectorStoreManagerTest
  $(basename "$0") --seeders UserTestSeeder,LegalCaseTestSeeder
  $(basename "$0") --full --fresh

Fixture Map: ${FIXTURE_MAP}
EOF
}

for arg in "$@"; do
    case "$arg" in
        --component)
            MODE="component"
            ;;
        --domain)
            MODE="domain"
            ;;
        --full)
            MODE="full"
            ;;
        --list)
            MODE="list"
            ;;
        --seeders)
            MODE="seeders"
            ;;
        --fresh)
            FRESH=true
            ;;
        --verbose)
            VERBOSE=true
            ;;
        --help|-h)
            show_help
            exit 0
            ;;
        *)
            if [[ -n "$MODE" && -z "$TARGET" && "$arg" != --* ]]; then
                TARGET="$arg"
            fi
            ;;
    esac
done

if [[ -z "$MODE" ]]; then
    show_help
    exit 1
fi

#######################################
# Logging
#######################################
log() {
    echo -e "$*"
}

log_verbose() {
    if [[ "$VERBOSE" == "true" ]]; then
        echo -e "${BLUE}[verbose]${NC} $1"
    fi
}

#######################################
# Get domain from component ID
#######################################
get_domain_from_component() {
    local component_id="$1"

    if [[ ! -f "$QUEUE_FILE" ]]; then
        echo ""
        return
    fi

    jq -r --arg id "$component_id" '
        .components[] | select(.id == $id) | .domain
    ' "$QUEUE_FILE" 2>/dev/null
}

#######################################
# Get seeders for domain
#######################################
get_seeders_for_domain() {
    local domain="$1"

    if [[ ! -f "$FIXTURE_MAP" ]]; then
        log "${RED}Error: Fixture map not found: ${FIXTURE_MAP}${NC}"
        exit 1
    fi

    # Try domain-specific mapping first
    local seeders
    seeders=$(jq -r --arg d "$domain" '
        .domains[$d] // .default | .[]
    ' "$FIXTURE_MAP" 2>/dev/null)

    echo "$seeders"
}

#######################################
# Get full seed list
#######################################
get_full_seeders() {
    if [[ ! -f "$FIXTURE_MAP" ]]; then
        log "${RED}Error: Fixture map not found: ${FIXTURE_MAP}${NC}"
        exit 1
    fi

    jq -r '.full_seed[]' "$FIXTURE_MAP" 2>/dev/null
}

#######################################
# List available domains
#######################################
list_domains() {
    if [[ ! -f "$FIXTURE_MAP" ]]; then
        log "${RED}Error: Fixture map not found: ${FIXTURE_MAP}${NC}"
        exit 1
    fi

    log ""
    log "${CYAN}═══════════════════════════════════════════════════════════${NC}"
    log "${CYAN}                Available Fixture Mappings                  ${NC}"
    log "${CYAN}═══════════════════════════════════════════════════════════${NC}"
    log ""

    log "${YELLOW}Default seeders:${NC}"
    jq -r '.default[]' "$FIXTURE_MAP" | while read -r seeder; do
        log "  - $seeder"
    done
    log ""

    log "${YELLOW}Domain mappings:${NC}"
    jq -r '.domains | to_entries[] | "\(.key): \(.value | join(", "))"' "$FIXTURE_MAP" | while read -r line; do
        local domain="${line%%:*}"
        local seeders="${line#*: }"
        log "  ${GREEN}${domain}${NC}"
        log "    → ${seeders}"
    done
    log ""

    log "${YELLOW}Full seed (CI):${NC}"
    jq -r '.full_seed | join(", ")' "$FIXTURE_MAP"
    log ""
}

#######################################
# Run seeders
#######################################
run_seeders() {
    local seeders=("$@")
    local total=${#seeders[@]}
    local success=0
    local failed=0
    local start_time
    local end_time
    local duration

    start_time=$(date +%s)

    log ""
    log "${CYAN}═══════════════════════════════════════════════════════════${NC}"
    log "${CYAN}              Focused Fixture Seeding                       ${NC}"
    log "${CYAN}═══════════════════════════════════════════════════════════${NC}"
    log ""
    log "  Seeders to run: ${total}"
    log "  Fresh migrate:  ${FRESH}"
    log ""

    # Run fresh migration if requested
    if [[ "$FRESH" == "true" ]]; then
        log "${YELLOW}Running migrate:fresh...${NC}"
        if php artisan migrate:fresh --force 2>&1; then
            log "${GREEN}✓ Migrations complete${NC}"
        else
            log "${RED}✗ Migration failed${NC}"
            return 1
        fi
        log ""
    fi

    log "${YELLOW}Seeding fixtures:${NC}"
    log ""

    for seeder in "${seeders[@]}"; do
        local seeder_start
        local seeder_end
        local seeder_duration

        seeder_start=$(date +%s%3N)

        log -n "  ${seeder}... "

        if php artisan db:seed --class="$seeder" --force 2>&1 | grep -v "^$" > /tmp/seeder-output.txt; then
            seeder_end=$(date +%s%3N)
            seeder_duration=$((seeder_end - seeder_start))
            log "${GREEN}✓${NC} (${seeder_duration}ms)"
            success=$((success + 1))

            if [[ "$VERBOSE" == "true" ]]; then
                cat /tmp/seeder-output.txt | sed 's/^/    /'
            fi
        else
            seeder_end=$(date +%s%3N)
            seeder_duration=$((seeder_end - seeder_start))
            log "${RED}✗${NC} (${seeder_duration}ms)"
            failed=$((failed + 1))

            # Show error output
            cat /tmp/seeder-output.txt | sed 's/^/    /'
        fi
    done

    end_time=$(date +%s)
    duration=$((end_time - start_time))

    log ""
    log "${CYAN}═══════════════════════════════════════════════════════════${NC}"
    log "  ${GREEN}Succeeded:${NC} ${success}/${total}"
    if [[ $failed -gt 0 ]]; then
        log "  ${RED}Failed:${NC}    ${failed}/${total}"
    fi
    log "  ${BLUE}Duration:${NC}  ${duration}s"
    log "${CYAN}═══════════════════════════════════════════════════════════${NC}"
    log ""

    if [[ $failed -gt 0 ]]; then
        return 1
    fi
    return 0
}

#######################################
# Main
#######################################
case "$MODE" in
    list)
        list_domains
        ;;

    full)
        seeders_list=$(get_full_seeders)
        if [[ -z "$seeders_list" ]]; then
            log "${RED}Error: Could not get full seeder list${NC}"
            exit 1
        fi

        # Convert to array
        mapfile -t seeders <<< "$seeders_list"
        run_seeders "${seeders[@]}"
        ;;

    domain)
        if [[ -z "$TARGET" ]]; then
            log "${RED}Error: --domain requires a domain name${NC}"
            show_help
            exit 1
        fi

        seeders_list=$(get_seeders_for_domain "$TARGET")
        if [[ -z "$seeders_list" ]]; then
            log "${YELLOW}Warning: No mapping for domain '$TARGET', using default${NC}"
            seeders_list=$(jq -r '.default[]' "$FIXTURE_MAP")
        fi

        log "Domain: ${CYAN}${TARGET}${NC}"

        mapfile -t seeders <<< "$seeders_list"
        run_seeders "${seeders[@]}"
        ;;

    component)
        if [[ -z "$TARGET" ]]; then
            log "${RED}Error: --component requires a component ID${NC}"
            show_help
            exit 1
        fi

        domain=$(get_domain_from_component "$TARGET")
        if [[ -z "$domain" ]]; then
            log "${YELLOW}Warning: Could not find domain for '$TARGET', using default${NC}"
            domain="default"
        fi

        log "Component: ${CYAN}${TARGET}${NC}"
        log "Domain:    ${CYAN}${domain}${NC}"

        seeders_list=$(get_seeders_for_domain "$domain")
        if [[ -z "$seeders_list" ]]; then
            seeders_list=$(jq -r '.default[]' "$FIXTURE_MAP")
        fi

        mapfile -t seeders <<< "$seeders_list"
        run_seeders "${seeders[@]}"
        ;;

    seeders)
        if [[ -z "$TARGET" ]]; then
            log "${RED}Error: --seeders requires a comma-separated list${NC}"
            show_help
            exit 1
        fi

        # Split by comma
        IFS=',' read -ra seeders <<< "$TARGET"
        run_seeders "${seeders[@]}"
        ;;

    *)
        log "${RED}Error: Unknown mode${NC}"
        show_help
        exit 1
        ;;
esac
