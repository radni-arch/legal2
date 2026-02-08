#!/usr/bin/env bash
#######################################
# Environment Matrix Check - Preflight Validation
#######################################
# Usage: ./scripts/check-matrix.sh [--baseline]
#
# Detects environment drift and missing prerequisites.
# Exit codes:
#   0 = all checks passed
#   1 = critical prerequisites missing or version mismatch
#
# Options:
#   --baseline    Compare against baseline and save current matrix
#   --ci          Output in CI-friendly format (no colors)
#   --quiet       Minimal output, just exit code
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
BASELINE_FILE="${PROJECT_DIR}/test-results/matrix-baseline.json"
ENV_FILE="${PROJECT_DIR}/.env"

# Parse arguments
BASELINE_MODE=false
CI_MODE=false
QUIET_MODE=false
for arg in "$@"; do
    case $arg in
        --baseline) BASELINE_MODE=true ;;
        --ci) CI_MODE=true ;;
        --quiet) QUIET_MODE=true ;;
    esac
done

# Track failures
CRITICAL_FAILURES=0
WARNINGS=0

#######################################
# Expected versions (baseline)
#######################################
EXPECTED_PHP_MAJOR="8"
EXPECTED_PHP_MINOR="2"
EXPECTED_NODE_MAJOR="18"
# Critical extensions (failures block work)
REQUIRED_PHP_EXTENSIONS=("pdo_pgsql" "mbstring" "xml" "zip")
# Optional extensions (warnings only)
OPTIONAL_PHP_EXTENSIONS=("curl" "gd" "bcmath")
# Required env vars (empty = warning for APP_KEY, failure for DB)
REQUIRED_ENV_VARS=("DB_CONNECTION" "DB_HOST" "DB_DATABASE")
OPTIONAL_ENV_VARS=("APP_KEY")

#######################################
# Output helpers
#######################################
if [ "$CI_MODE" = true ]; then
    GREEN=''
    RED=''
    YELLOW=''
    BLUE=''
    NC=''
else
    GREEN='\033[0;32m'
    RED='\033[0;31m'
    YELLOW='\033[0;33m'
    BLUE='\033[0;34m'
    NC='\033[0m'
fi

log() {
    [ "$QUIET_MODE" = true ] && return
    echo -e "$*"
}

ok() {
    log "  ${GREEN}✓${NC} $*"
}

fail() {
    log "  ${RED}✗${NC} $*"
    CRITICAL_FAILURES=$((CRITICAL_FAILURES + 1))
}

warn() {
    log "  ${YELLOW}⚠${NC} $*"
    WARNINGS=$((WARNINGS + 1))
}

info() {
    log "  ${BLUE}ℹ${NC} $*"
}

header() {
    [ "$QUIET_MODE" = true ] && return
    echo ""
    log "${BLUE}═══ $* ═══${NC}"
}

#######################################
# Version comparison helpers
#######################################
version_major() {
    echo "$1" | cut -d. -f1
}

version_minor() {
    echo "$1" | cut -d. -f2
}

#######################################
# Check PHP
#######################################
check_php() {
    header "PHP"

    if ! command -v php >/dev/null 2>&1; then
        fail "PHP not found"
        return 1
    fi

    local php_version
    php_version=$(php -r "echo PHP_VERSION;" 2>/dev/null)
    local php_major php_minor
    php_major=$(version_major "$php_version")
    php_minor=$(version_minor "$php_version")

    if [ "$php_major" = "$EXPECTED_PHP_MAJOR" ]; then
        if [ "$php_minor" -ge "$EXPECTED_PHP_MINOR" ]; then
            ok "PHP $php_version (expected $EXPECTED_PHP_MAJOR.$EXPECTED_PHP_MINOR+)"
        else
            warn "PHP $php_version (expected $EXPECTED_PHP_MAJOR.$EXPECTED_PHP_MINOR+, minor version mismatch)"
        fi
    else
        fail "PHP $php_version (expected major version $EXPECTED_PHP_MAJOR)"
        return 1
    fi

    # Check required extensions (critical)
    local missing_ext=0
    for ext in "${REQUIRED_PHP_EXTENSIONS[@]}"; do
        if php -m 2>/dev/null | grep -qi "^${ext}$"; then
            ok "ext-$ext loaded"
        else
            fail "ext-$ext NOT loaded (required)"
            missing_ext=$((missing_ext + 1))
        fi
    done

    # Check optional extensions (warnings only)
    for ext in "${OPTIONAL_PHP_EXTENSIONS[@]}"; do
        if php -m 2>/dev/null | grep -qi "^${ext}$"; then
            ok "ext-$ext loaded"
        else
            warn "ext-$ext not loaded (optional)"
        fi
    done

    [ $missing_ext -gt 0 ] && return 1
    return 0
}

#######################################
# Check Node.js
#######################################
check_node() {
    header "Node.js"

    if ! command -v node >/dev/null 2>&1; then
        warn "Node.js not found (optional for backend-only work)"
        return 0
    fi

    local node_version
    node_version=$(node --version 2>/dev/null | sed 's/^v//')
    local node_major
    node_major=$(version_major "$node_version")

    if [ "$node_major" -ge "$EXPECTED_NODE_MAJOR" ]; then
        ok "Node.js $node_version (expected $EXPECTED_NODE_MAJOR+)"
    else
        warn "Node.js $node_version (expected $EXPECTED_NODE_MAJOR+)"
    fi

    # Check npm
    if command -v npm >/dev/null 2>&1; then
        local npm_version
        npm_version=$(npm --version 2>/dev/null)
        ok "npm $npm_version"
    else
        warn "npm not found"
    fi

    return 0
}

#######################################
# Check Composer
#######################################
check_composer() {
    header "Composer"

    if command -v composer >/dev/null 2>&1; then
        local composer_version
        composer_version=$(composer --version 2>/dev/null | grep -oP '\d+\.\d+\.\d+' | head -1)
        ok "Composer $composer_version"
    elif [ -f "$PROJECT_DIR/composer.phar" ]; then
        ok "composer.phar found in project"
    else
        fail "Composer not found"
        return 1
    fi

    # Check vendor directory
    if [ -d "$PROJECT_DIR/vendor" ] && [ -f "$PROJECT_DIR/vendor/autoload.php" ]; then
        ok "vendor/ directory exists"
    else
        warn "vendor/ directory missing (run composer install)"
    fi

    return 0
}

#######################################
# Check PostgreSQL
#######################################
check_postgres() {
    header "PostgreSQL"

    # Client check
    if command -v psql >/dev/null 2>&1; then
        local psql_version
        psql_version=$(psql --version 2>/dev/null | grep -oP '\d+\.\d+' | head -1)
        ok "psql client $psql_version"
    else
        fail "psql client not found"
        return 1
    fi

    # Server check via pg_isready
    if command -v pg_isready >/dev/null 2>&1; then
        if pg_isready -h 127.0.0.1 -p 5432 -q 2>/dev/null; then
            ok "PostgreSQL server accepting connections"
        else
            warn "PostgreSQL server not responding (may need to start)"
        fi
    else
        info "pg_isready not available, skipping server check"
    fi

    return 0
}

#######################################
# Check Neo4j
#######################################
check_neo4j() {
    header "Neo4j"

    if pgrep -f "neo4j" >/dev/null 2>&1; then
        ok "Neo4j process running"
    else
        warn "Neo4j process not running"
    fi

    if command -v cypher-shell >/dev/null 2>&1; then
        ok "cypher-shell available"
    else
        info "cypher-shell not in PATH"
    fi

    return 0
}

#######################################
# Check Chrome/ChromeDriver
#######################################
check_browser() {
    header "Browser (Dusk/Playwright)"

    # Check for Chrome binary
    local chrome_found=false
    for chrome_path in "/tmp/chrome-linux/chrome" "/usr/bin/google-chrome" "/usr/bin/chromium-browser" "/usr/bin/chromium"; do
        if [ -x "$chrome_path" ]; then
            ok "Chrome binary found: $chrome_path"
            chrome_found=true
            break
        fi
    done

    if [ "$chrome_found" = false ]; then
        warn "Chrome binary not found (Dusk tests may fail)"
    fi

    # Check for ChromeDriver
    local chromedriver_paths=(
        "$PROJECT_DIR/vendor/laravel/dusk/bin/chromedriver-linux"
        "$PROJECT_DIR/vendor/laravel/dusk/bin/chromedriver"
        "$PROJECT_DIR/vendor/bin/chromedriver"
    )

    local chromedriver_found=false
    for driver_path in "${chromedriver_paths[@]}"; do
        if [ -x "$driver_path" ]; then
            ok "ChromeDriver found: $driver_path"
            chromedriver_found=true
            break
        fi
    done

    if [ "$chromedriver_found" = false ]; then
        warn "ChromeDriver not found (run php artisan dusk:chrome-driver)"
    fi

    # Check if running
    if pgrep -f "chromedriver.*9515" >/dev/null 2>&1; then
        ok "ChromeDriver running on port 9515"
    else
        info "ChromeDriver not running (will start on demand)"
    fi

    return 0
}

#######################################
# Check Environment Variables
#######################################
check_env_vars() {
    header "Environment Variables"

    if [ ! -f "$ENV_FILE" ]; then
        fail ".env file not found"
        return 1
    fi

    ok ".env file exists"

    local missing_vars=0

    # Check required env vars (critical)
    for var in "${REQUIRED_ENV_VARS[@]}"; do
        if grep -q "^${var}=" "$ENV_FILE" 2>/dev/null; then
            local value
            value=$(grep "^${var}=" "$ENV_FILE" | cut -d= -f2-)
            if [ -n "$value" ] && [ "$value" != "" ]; then
                ok "$var is set"
            else
                fail "$var is empty (required)"
                missing_vars=$((missing_vars + 1))
            fi
        else
            fail "$var not found in .env (required)"
            missing_vars=$((missing_vars + 1))
        fi
    done

    # Check optional env vars (warnings only)
    for var in "${OPTIONAL_ENV_VARS[@]}"; do
        if grep -q "^${var}=" "$ENV_FILE" 2>/dev/null; then
            local value
            value=$(grep "^${var}=" "$ENV_FILE" | cut -d= -f2-)
            if [ -n "$value" ] && [ "$value" != "" ]; then
                ok "$var is set"
            else
                warn "$var is empty (optional, set for production)"
            fi
        else
            warn "$var not found in .env (optional)"
        fi
    done

    [ $missing_vars -gt 0 ] && return 1
    return 0
}

#######################################
# Generate/Compare Baseline
#######################################
generate_matrix_json() {
    local php_version node_version npm_version composer_version psql_version

    php_version=$(php -r "echo PHP_VERSION;" 2>/dev/null || echo "not found")
    node_version=$(node --version 2>/dev/null | sed 's/^v//' || echo "not found")
    npm_version=$(npm --version 2>/dev/null || echo "not found")
    composer_version=$(composer --version 2>/dev/null | grep -oP '\d+\.\d+\.\d+' | head -1 || echo "not found")
    psql_version=$(psql --version 2>/dev/null | grep -oP '\d+\.\d+' | head -1 || echo "not found")

    cat <<EOF
{
  "generated_at": "$(date -Iseconds)",
  "versions": {
    "php": "$php_version",
    "node": "$node_version",
    "npm": "$npm_version",
    "composer": "$composer_version",
    "psql": "$psql_version"
  },
  "expected": {
    "php_major": "$EXPECTED_PHP_MAJOR",
    "php_minor": "$EXPECTED_PHP_MINOR",
    "node_major": "$EXPECTED_NODE_MAJOR"
  },
  "php_extensions": [$(php -m 2>/dev/null | grep -E "^(pdo_pgsql|mbstring|xml|curl|zip|bcmath|gd)$" | sed 's/.*/"&"/' | tr '\n' ',' | sed 's/,$//')],
  "checks": {
    "vendor_exists": $([ -d "$PROJECT_DIR/vendor" ] && echo "true" || echo "false"),
    "env_exists": $([ -f "$ENV_FILE" ] && echo "true" || echo "false"),
    "chrome_found": $([ -x "/tmp/chrome-linux/chrome" ] || [ -x "/usr/bin/google-chrome" ] && echo "true" || echo "false"),
    "chromedriver_found": $([ -x "$PROJECT_DIR/vendor/laravel/dusk/bin/chromedriver-linux" ] && echo "true" || echo "false")
  }
}
EOF
}

handle_baseline() {
    header "Baseline Comparison"

    local current_matrix
    current_matrix=$(generate_matrix_json)

    if [ -f "$BASELINE_FILE" ]; then
        info "Comparing against baseline: $BASELINE_FILE"

        # Extract versions for comparison
        local baseline_php current_php baseline_node current_node
        baseline_php=$(grep -o '"php": *"[^"]*"' "$BASELINE_FILE" 2>/dev/null | sed 's/.*: *"\([^"]*\)"/\1/')
        current_php=$(echo "$current_matrix" | grep -o '"php": *"[^"]*"' | sed 's/.*: *"\([^"]*\)"/\1/')

        baseline_node=$(grep -o '"node": *"[^"]*"' "$BASELINE_FILE" 2>/dev/null | sed 's/.*: *"\([^"]*\)"/\1/')
        current_node=$(echo "$current_matrix" | grep -o '"node": *"[^"]*"' | sed 's/.*: *"\([^"]*\)"/\1/')

        # Compare PHP
        if [ "$baseline_php" != "$current_php" ]; then
            local baseline_php_major current_php_major
            baseline_php_major=$(version_major "$baseline_php")
            current_php_major=$(version_major "$current_php")
            if [ "$baseline_php_major" != "$current_php_major" ]; then
                fail "PHP major version drift: baseline=$baseline_php, current=$current_php"
            else
                warn "PHP version changed: baseline=$baseline_php, current=$current_php"
            fi
        else
            ok "PHP version matches baseline: $current_php"
        fi

        # Compare Node
        if [ "$baseline_node" != "$current_node" ] && [ "$baseline_node" != "not found" ]; then
            local baseline_node_major current_node_major
            baseline_node_major=$(version_major "$baseline_node")
            current_node_major=$(version_major "$current_node")
            if [ "$baseline_node_major" != "$current_node_major" ]; then
                warn "Node major version drift: baseline=$baseline_node, current=$current_node"
            else
                info "Node version changed: baseline=$baseline_node, current=$current_node"
            fi
        else
            ok "Node version matches baseline: $current_node"
        fi
    else
        info "No baseline file found, creating new baseline"
    fi

    # Save current matrix as new baseline
    mkdir -p "$(dirname "$BASELINE_FILE")"
    echo "$current_matrix" > "$BASELINE_FILE"
    ok "Baseline saved to: $BASELINE_FILE"
}

#######################################
# Main
#######################################
[ "$QUIET_MODE" = false ] && {
    echo ""
    echo "╔═══════════════════════════════════════════════════════════╗"
    echo "║         Environment Matrix - Preflight Check              ║"
    echo "╚═══════════════════════════════════════════════════════════╝"
}

check_php
check_node
check_composer
check_postgres
check_neo4j
check_browser
check_env_vars

[ "$BASELINE_MODE" = true ] && handle_baseline

#######################################
# Summary
#######################################
header "Summary"

if [ $CRITICAL_FAILURES -eq 0 ]; then
    if [ $WARNINGS -eq 0 ]; then
        log "  ${GREEN}▶ PASSED${NC} - All prerequisites met"
    else
        log "  ${YELLOW}▶ PASSED with warnings${NC} - $WARNINGS warning(s)"
    fi
    echo ""
    exit 0
else
    log "  ${RED}▶ FAILED${NC} - $CRITICAL_FAILURES critical issue(s), $WARNINGS warning(s)"
    echo ""
    info "Fix critical issues before proceeding"
    exit 1
fi
