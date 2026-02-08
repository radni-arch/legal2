#!/bin/bash
set -euo pipefail

#######################################
# Database Data Import Script (Idempotent at orchestration level)
#
# Responsibilities:
# - Ensure the application DB is reachable.
# - Run Laravel database seeders (or a custom import command).
#
# NOTE:
# - setup-all.sh already uses a marker file (DB_IMPORT_MARKER) to
#   guarantee this script is only executed once per environment.
# - This script itself will just try to seed/initialize data and
#   exits with 0 on success, non-zero on failure.
#######################################

DB_NAME="${DB_NAME:-ai_legal_war_machine}"
DB_USER="${DB_USER:-claude}"
DB_PASSWORD="${DB_PASSWORD:-claude}"

PGHOST="${PGHOST:-127.0.0.1}"
PGPORT="${PGPORT:-5432}"

# Custom import command (override via env if needed)
DB_IMPORT_COMMAND="${DB_IMPORT_COMMAND:-php artisan db:seed --force}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
LOG_FILE="${LOG_FILE:-/tmp/import-db-data-$(date +%Y%m%d-%H%M%S).log}"

export PGPASSWORD="${PGPASSWORD:-$DB_PASSWORD}"

#######################################
# Logging
#######################################

log() {
    local ts
    ts=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$ts] [INFO] $*" | tee -a "$LOG_FILE"
}

warn() {
    local ts
    ts=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$ts] [WARN] $*" | tee -a "$LOG_FILE" >&2
}

error() {
    local ts
    ts=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$ts] [ERROR] $*" | tee -a "$LOG_FILE" >&2
}

success() {
    local ts
    ts=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$ts] [SUCCESS] $*" | tee -a "$LOG_FILE"
}

#######################################
# Helpers
#######################################

db_ready() {
    if command -v pg_isready >/dev/null 2>&1; then
        pg_isready -h "$PGHOST" -p "$PGPORT" >/dev/null 2>&1 || return 1
    fi

    psql -h "$PGHOST" -p "$PGPORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1;" >/dev/null 2>&1
}

wait_for_db() {
    local timeout="${1:-60}"
    local waited=0
    local interval=2

    log "Waiting for DB '$DB_NAME' to become ready as '$DB_USER' on ${PGHOST}:${PGPORT} (timeout: ${timeout}s)"

    while [ "$waited" -lt "$timeout" ]; do
        if db_ready; then
            success "DB '$DB_NAME' is ready"
            return 0
        fi
        sleep "$interval"
        waited=$((waited + interval))
        log "Still waiting for DB... (${waited}/${timeout}s)"
    done

    warn "Timed out waiting for DB '$DB_NAME'"
    return 1
}

#######################################
# Main
#######################################

log "=========================================="
log "Database Data Import Script"
log "Project directory: $PROJECT_DIR"
log "DB_NAME: $DB_NAME"
log "DB_USER: $DB_USER"
log "Import command: $DB_IMPORT_COMMAND"
log "Log file: $LOG_FILE"
log "Script started at: $(date)"
log "=========================================="

cd "$PROJECT_DIR"

# 1. Ensure DB is reachable
if ! wait_for_db 120; then
    error "Database is not reachable; cannot import data"
    exit 1
fi

# 2. Ensure PHP & artisan are available
if ! command -v php >/dev/null 2>&1; then
    error "PHP is not installed or not in PATH; cannot run Laravel artisan commands"
    exit 1
fi

if [ ! -f "$PROJECT_DIR/artisan" ]; then
    error "Laravel artisan file not found in project root ($PROJECT_DIR); cannot run import"
    exit 1
fi

# 3. Run the import command
log "Running data import command: $DB_IMPORT_COMMAND"

set +e
eval "$DB_IMPORT_COMMAND" 2>&1 | tee -a "$LOG_FILE"
IMPORT_EXIT_CODE=${PIPESTATUS[0]}
set -e

if [ "$IMPORT_EXIT_CODE" -ne 0 ]; then
    error "Data import command failed with exit code $IMPORT_EXIT_CODE"
    exit "$IMPORT_EXIT_CODE"
fi

success "Database data import completed successfully"
log "Import script finished at: $(date)"

exit 0
