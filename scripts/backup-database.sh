#!/bin/bash
set -euo pipefail

#######################################
# PostgreSQL Database Backup Script
#
# Features:
# - Timestamp-based backup files
# - Gzip compression
# - 30-day retention policy
# - Backup to /backups/database/
#
# Usage:
#   ./scripts/backup-database.sh
#   ./scripts/backup-database.sh --dry-run
#   BACKUP_DIR=/custom/path ./scripts/backup-database.sh
#######################################

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/backups/database}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
DB_NAME="${DB_DATABASE:-ai_legal_war_machine}"
DB_USER="${DB_USERNAME:-claude}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}-${TIMESTAMP}.sql.gz"
LOG_FILE="${LOG_FILE:-/var/log/backup-database.log}"
DRY_RUN=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --dry-run    Show what would be done without making changes"
            echo "  --help       Show this help message"
            echo ""
            echo "Environment variables:"
            echo "  BACKUP_DIR       Backup destination (default: /backups/database)"
            echo "  RETENTION_DAYS   Days to keep backups (default: 30)"
            echo "  DB_DATABASE      Database name (default: ai_legal_war_machine)"
            echo "  DB_USERNAME      Database user (default: claude)"
            echo "  DB_HOST          Database host (default: 127.0.0.1)"
            echo "  DB_PORT          Database port (default: 5432)"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

#######################################
# Logging Functions
#######################################

log() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [INFO] $*" | tee -a "$LOG_FILE" 2>/dev/null || echo "[$timestamp] [INFO] $*"
}

error() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [ERROR] $*" | tee -a "$LOG_FILE" 2>/dev/null >&2 || echo "[$timestamp] [ERROR] $*" >&2
}

success() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [SUCCESS] $*" | tee -a "$LOG_FILE" 2>/dev/null || echo "[$timestamp] [SUCCESS] $*"
}

#######################################
# Pre-flight Checks
#######################################

log "=========================================="
log "PostgreSQL Database Backup"
log "=========================================="
log "Database: $DB_NAME"
log "Backup destination: $BACKUP_DIR"
log "Retention: $RETENTION_DAYS days"

# Check for pg_dump
if ! command -v pg_dump >/dev/null 2>&1; then
    error "pg_dump not found. Please install PostgreSQL client tools."
    exit 1
fi

# Check database connectivity
export PGPASSWORD="${DB_PASSWORD:-claude}"
if ! psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1;" >/dev/null 2>&1; then
    error "Cannot connect to database $DB_NAME"
    exit 1
fi
log "Database connectivity verified"

#######################################
# Create Backup
#######################################

if [ "$DRY_RUN" = true ]; then
    log "[DRY-RUN] Would create backup directory: $BACKUP_DIR"
    log "[DRY-RUN] Would create backup: $BACKUP_FILE"
    log "[DRY-RUN] Would remove backups older than $RETENTION_DAYS days"
    exit 0
fi

# Create backup directory if it doesn't exist
if [ ! -d "$BACKUP_DIR" ]; then
    log "Creating backup directory: $BACKUP_DIR"
    mkdir -p "$BACKUP_DIR"
    chmod 750 "$BACKUP_DIR"
fi

# Perform backup
log "Starting backup to: $BACKUP_FILE"
START_TIME=$(date +%s)

if pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" \
    --format=plain \
    --no-owner \
    --no-privileges \
    --verbose 2>>"$LOG_FILE" | gzip > "$BACKUP_FILE"; then

    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)

    success "Backup completed in ${DURATION}s"
    log "Backup size: $BACKUP_SIZE"
    log "Backup file: $BACKUP_FILE"
else
    error "Backup failed!"
    rm -f "$BACKUP_FILE"
    exit 1
fi

#######################################
# Cleanup Old Backups
#######################################

log "Cleaning up backups older than $RETENTION_DAYS days..."

DELETED_COUNT=0
while IFS= read -r old_backup; do
    if [ -f "$old_backup" ]; then
        log "Removing old backup: $old_backup"
        rm -f "$old_backup"
        DELETED_COUNT=$((DELETED_COUNT + 1))
    fi
done < <(find "$BACKUP_DIR" -name "${DB_NAME}-*.sql.gz" -type f -mtime +${RETENTION_DAYS} 2>/dev/null)

log "Removed $DELETED_COUNT old backup(s)"

#######################################
# Summary
#######################################

# Count remaining backups
BACKUP_COUNT=$(find "$BACKUP_DIR" -name "${DB_NAME}-*.sql.gz" -type f 2>/dev/null | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1 || echo "unknown")

log "=========================================="
log "Backup Summary"
log "=========================================="
log "Latest backup: $BACKUP_FILE"
log "Backup size: $BACKUP_SIZE"
log "Total backups: $BACKUP_COUNT"
log "Total storage used: $TOTAL_SIZE"
success "Database backup completed successfully"
