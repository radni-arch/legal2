#!/usr/bin/env bash
#######################################
# Check Setup - Non-destructive health check
#######################################
# Usage: ./scripts/check-setup.sh
#
# Checks environment readiness without mutating anything.
# Reads status from test-results/setup-status.json and performs live checks.
# Exit codes:
#   0 = healthy
#   1 = unhealthy or setup incomplete
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
STATUS_FILE="${PROJECT_DIR}/test-results/setup-status.json"
SETUP_LOG="/tmp/setup-all-hook.log"

# Database credentials (match setup-all.sh)
DB_USER="${DB_USER:-claude}"
DB_PASSWORD="${DB_PASSWORD:-claude}"
export PGPASSWORD="$DB_PASSWORD"

# Track health
UNHEALTHY=0

#######################################
# Output helpers
#######################################
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

ok() {
    echo -e "  ${GREEN}✓${NC} $*"
}

fail() {
    echo -e "  ${RED}✗${NC} $*"
    UNHEALTHY=1
}

warn() {
    echo -e "  ${YELLOW}⚠${NC} $*"
}

info() {
    echo -e "  ${BLUE}ℹ${NC} $*"
}

header() {
    echo ""
    echo -e "${BLUE}═══ $* ═══${NC}"
}

#######################################
# Check if setup is running
#######################################
check_setup_running() {
    header "Setup Process"

    if pgrep -f "setup-all.sh" >/dev/null 2>&1; then
        warn "setup-all.sh is currently running"
        local pids
        pids=$(pgrep -f "setup-all.sh" | tr '\n' ' ')
        info "PIDs: $pids"
        return 1
    else
        ok "No setup process running"
        return 0
    fi
}

#######################################
# Environment Matrix Check
#######################################
check_matrix() {
    header "Environment Matrix"

    local matrix_script="${PROJECT_DIR}/scripts/check-matrix.sh"

    if [ ! -x "$matrix_script" ]; then
        warn "Matrix check script not found: $matrix_script"
        return 0
    fi

    # Run matrix check in quiet mode, capture exit code
    if "$matrix_script" --quiet 2>/dev/null; then
        ok "Environment matrix: all prerequisites met"
        return 0
    else
        fail "Environment matrix: critical prerequisites missing"
        info "Run: ./scripts/check-matrix.sh for details"
        return 1
    fi
}

#######################################
# Read last setup status from JSON
#######################################
read_last_status() {
    header "Last Setup Status"

    if [ ! -f "$STATUS_FILE" ]; then
        warn "No status file found at $STATUS_FILE"
        info "Run setup first or wait for it to complete"
        return 1
    fi

    # Parse JSON (avoid jq dependency)
    local overall timestamp log_file
    overall=$(grep -o '"overall": *"[^"]*"' "$STATUS_FILE" 2>/dev/null | sed 's/.*: *"\([^"]*\)"/\1/' || echo "unknown")
    timestamp=$(grep -o '"timestamp": *"[^"]*"' "$STATUS_FILE" 2>/dev/null | head -1 | sed 's/.*: *"\([^"]*\)"/\1/' || echo "unknown")
    log_file=$(grep -o '"log_file": *"[^"]*"' "$STATUS_FILE" 2>/dev/null | sed 's/.*: *"\([^"]*\)"/\1/' || echo "$SETUP_LOG")

    # Format timestamp
    local display_ts
    display_ts=$(echo "$timestamp" | sed 's/T/ /' | cut -c1-19)

    if [ "$overall" = "ok" ]; then
        ok "Last setup: OK @ $display_ts"
    elif [ "$overall" = "failed" ]; then
        fail "Last setup: FAILED @ $display_ts"
        info "Log: $log_file"
    else
        warn "Last setup: $overall @ $display_ts"
    fi

    # Extract subsystem statuses
    local db migrations seed composer neo4j playwright
    db=$(grep -A1 '"db":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")
    migrations=$(grep -A1 '"migrations":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")
    seed=$(grep -A1 '"seed":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")
    composer=$(grep -A1 '"composer":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")
    neo4j=$(grep -A1 '"neo4j":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")
    playwright=$(grep -A1 '"playwright":' "$STATUS_FILE" 2>/dev/null | grep '"status"' | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "?")

    echo "  Subsystems: db=$db, migrations=$migrations, seed=$seed"
    echo "              composer=$composer, neo4j=$neo4j, playwright=$playwright"

    [ "$overall" = "ok" ] && return 0 || return 1
}

#######################################
# Live DB connectivity check
#######################################
check_db_connectivity() {
    header "PostgreSQL (Live Check)"

    # pg_isready check
    if command -v pg_isready >/dev/null 2>&1; then
        if pg_isready -h 127.0.0.1 -p 5432 -q 2>/dev/null; then
            ok "pg_isready: accepting connections"
        else
            fail "pg_isready: not accepting connections"
            return 1
        fi
    else
        warn "pg_isready not available, using psql"
    fi

    # Real query check
    if psql -h 127.0.0.1 -U "$DB_USER" -d ai_legal_war_machine -c "SELECT 1;" >/dev/null 2>&1; then
        ok "SELECT 1: query successful"
    else
        # Try as postgres superuser
        if su - postgres -c "psql -U postgres -d ai_legal_war_machine -c 'SELECT 1;'" >/dev/null 2>&1; then
            ok "SELECT 1: query successful (as postgres)"
        else
            fail "SELECT 1: query failed"
            return 1
        fi
    fi

    return 0
}

#######################################
# Migration status check (non-destructive)
#######################################
check_migrations() {
    header "Migrations (Live Check)"

    if ! command -v php >/dev/null 2>&1; then
        warn "PHP not available"
        return 1
    fi

    cd "$PROJECT_DIR" || return 1

    local status_output
    status_output=$(php artisan migrate:status 2>&1)
    local exit_code=$?

    if [ $exit_code -ne 0 ]; then
        fail "migrate:status command failed"
        echo "    $status_output" | head -3
        return 1
    fi

    # Check for pending
    if echo "$status_output" | grep -q "Pending"; then
        local pending_count
        pending_count=$(echo "$status_output" | grep -c "Pending" || echo "0")
        fail "$pending_count pending migrations"
        return 1
    fi

    # Count ran
    local ran_count
    ran_count=$(echo "$status_output" | grep -c "Ran" || echo "0")
    if [ "$ran_count" -gt 0 ]; then
        ok "$ran_count migrations applied"
    else
        warn "No migrations found"
    fi

    return 0
}

#######################################
# Neo4j status check
#######################################
check_neo4j() {
    header "Neo4j (Live Check)"

    # Process check
    if pgrep -f "neo4j" >/dev/null 2>&1; then
        ok "Neo4j process running"
    else
        fail "Neo4j process not running"
        return 1
    fi

    # Connection check if cypher-shell available
    if command -v cypher-shell >/dev/null 2>&1; then
        if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p "${NEO4J_PASSWORD:-password}" "RETURN 1;" >/dev/null 2>&1; then
            ok "cypher-shell: connection successful"
        else
            warn "cypher-shell: connection failed (may still be starting)"
        fi
    fi

    return 0
}

#######################################
# Composer dependencies check
#######################################
check_composer() {
    header "Composer Dependencies"

    if [ -d "$PROJECT_DIR/vendor" ] && [ -f "$PROJECT_DIR/vendor/autoload.php" ]; then
        ok "vendor/ directory exists"
        local package_count
        package_count=$(find "$PROJECT_DIR/vendor" -maxdepth 2 -type d | wc -l)
        info "$package_count directories in vendor/"
    else
        fail "vendor/ directory missing or incomplete"
        return 1
    fi

    return 0
}

#######################################
# ChromeDriver check
#######################################
check_chromedriver() {
    header "ChromeDriver/Playwright"

    if pgrep -f "chromedriver.*9515" >/dev/null 2>&1; then
        ok "ChromeDriver running on port 9515"
    else
        warn "ChromeDriver not running (will start on demand)"
    fi

    # Check binary exists
    local chromedriver_bin="${PROJECT_DIR}/vendor/laravel/dusk/bin/chromedriver-linux"
    if [ -x "$chromedriver_bin" ]; then
        ok "ChromeDriver binary present"
    else
        warn "ChromeDriver binary not found at expected location"
    fi

    return 0
}

#######################################
# Show recent log output
#######################################
show_recent_log() {
    header "Recent Setup Log"

    if [ -f "$SETUP_LOG" ]; then
        info "Last 10 lines of $SETUP_LOG:"
        echo "  ─────────────────────────────────────"
        tail -10 "$SETUP_LOG" 2>/dev/null | sed 's/^/  /'
        echo "  ─────────────────────────────────────"
    else
        info "No log file at $SETUP_LOG"
    fi
}

#######################################
# Main
#######################################
echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║           AI Legal War Machine - Setup Check              ║"
echo "╚═══════════════════════════════════════════════════════════╝"

check_setup_running
check_matrix
read_last_status
check_db_connectivity
check_migrations
check_neo4j
check_composer
check_chromedriver
show_recent_log

#######################################
# Final Summary
#######################################
header "Summary"

if [ $UNHEALTHY -eq 0 ]; then
    echo -e "  ${GREEN}▶ HEALTHY${NC} - Environment is ready"
    echo ""
    exit 0
else
    echo -e "  ${RED}▶ UNHEALTHY${NC} - Issues detected above"
    echo ""
    info "To re-run setup: wait for completion or check logs"
    info "Log file: $SETUP_LOG"
    echo ""
    exit 1
fi
