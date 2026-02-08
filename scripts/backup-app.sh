#!/bin/bash
set -euo pipefail

#######################################
# Application Backup Script
#
# Creates a full backup of the application including:
# - Application code (excluding vendor, node_modules)
# - Storage directory (uploads, logs, cache)
# - Environment configuration (.env)
#
# Features:
# - Tar+gzip compression
# - Timestamp-based naming
# - 30-day retention policy
# - Excludes development artifacts
#
# Usage:
#   ./scripts/backup-app.sh
#   ./scripts/backup-app.sh --dry-run
#   BACKUP_DIR=/custom/path ./scripts/backup-app.sh
#######################################

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-/backups/app}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
APP_NAME="${APP_NAME:-ai-legal-war-machine}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/${APP_NAME}-${TIMESTAMP}.tar.gz"
LOG_FILE="${LOG_FILE:-/var/log/backup-app.log}"
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
            echo "  BACKUP_DIR       Backup destination (default: /backups/app)"
            echo "  RETENTION_DAYS   Days to keep backups (default: 30)"
            echo "  APP_NAME         Application name for backup file (default: ai-legal-war-machine)"
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

warn() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [WARN] $*" | tee -a "$LOG_FILE" 2>/dev/null >&2 || echo "[$timestamp] [WARN] $*" >&2
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
log "Application Backup"
log "=========================================="
log "Source: $PROJECT_DIR"
log "Backup destination: $BACKUP_DIR"
log "Retention: $RETENTION_DAYS days"

# Verify source directory exists
if [ ! -d "$PROJECT_DIR" ]; then
    error "Project directory not found: $PROJECT_DIR"
    exit 1
fi

# Check for tar
if ! command -v tar >/dev/null 2>&1; then
    error "tar command not found"
    exit 1
fi

#######################################
# Define Exclusions
#######################################

# Items to exclude from backup
EXCLUDE_PATTERNS=(
    "vendor"
    "node_modules"
    ".git"
    "storage/logs/*.log"
    "storage/framework/cache/data/*"
    "storage/framework/sessions/*"
    "storage/framework/views/*"
    "bootstrap/cache/*.php"
    "*.log"
    ".phpunit.result.cache"
    ".idea"
    ".vscode"
    "coverage"
    "test-results/*.json"
)

# Build exclude arguments for tar
EXCLUDE_ARGS=""
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
    EXCLUDE_ARGS="$EXCLUDE_ARGS --exclude=$pattern"
done

#######################################
# Create Backup
#######################################

if [ "$DRY_RUN" = true ]; then
    log "[DRY-RUN] Would create backup directory: $BACKUP_DIR"
    log "[DRY-RUN] Would create backup: $BACKUP_FILE"
    log "[DRY-RUN] Excluded patterns:"
    for pattern in "${EXCLUDE_PATTERNS[@]}"; do
        log "[DRY-RUN]   - $pattern"
    done
    log "[DRY-RUN] Would remove backups older than $RETENTION_DAYS days"

    # Show what would be backed up (approximate size)
    log "[DRY-RUN] Calculating approximate backup size..."
    APPROX_SIZE=$(du -sh "$PROJECT_DIR" --exclude=vendor --exclude=node_modules --exclude=.git 2>/dev/null | cut -f1 || echo "unknown")
    log "[DRY-RUN] Approximate source size (excluding vendor/node_modules/.git): $APPROX_SIZE"
    exit 0
fi

# Create backup directory if it doesn't exist
if [ ! -d "$BACKUP_DIR" ]; then
    log "Creating backup directory: $BACKUP_DIR"
    mkdir -p "$BACKUP_DIR"
    chmod 750 "$BACKUP_DIR"
fi

# Create temporary manifest
MANIFEST_FILE="${BACKUP_DIR}/.manifest-${TIMESTAMP}.txt"

log "Creating backup manifest..."
echo "# Backup Manifest" > "$MANIFEST_FILE"
echo "# Created: $(date)" >> "$MANIFEST_FILE"
echo "# Source: $PROJECT_DIR" >> "$MANIFEST_FILE"
echo "" >> "$MANIFEST_FILE"
echo "# Key files included:" >> "$MANIFEST_FILE"
echo ".env" >> "$MANIFEST_FILE"
echo "storage/" >> "$MANIFEST_FILE"
echo "app/" >> "$MANIFEST_FILE"
echo "config/" >> "$MANIFEST_FILE"
echo "database/migrations/" >> "$MANIFEST_FILE"
echo "resources/" >> "$MANIFEST_FILE"
echo "routes/" >> "$MANIFEST_FILE"
echo "" >> "$MANIFEST_FILE"
echo "# Excluded:" >> "$MANIFEST_FILE"
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
    echo "# - $pattern" >> "$MANIFEST_FILE"
done

# Perform backup
log "Starting backup to: $BACKUP_FILE"
log "This may take several minutes..."
START_TIME=$(date +%s)

cd "$PROJECT_DIR"

# shellcheck disable=SC2086
if tar -czf "$BACKUP_FILE" \
    $EXCLUDE_ARGS \
    --warning=no-file-changed \
    -C "$(dirname "$PROJECT_DIR")" \
    "$(basename "$PROJECT_DIR")" 2>>"$LOG_FILE"; then

    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)

    success "Backup completed in ${DURATION}s"
    log "Backup size: $BACKUP_SIZE"
    log "Backup file: $BACKUP_FILE"
else
    error "Backup failed!"
    rm -f "$BACKUP_FILE"
    rm -f "$MANIFEST_FILE"
    exit 1
fi

# Clean up manifest
rm -f "$MANIFEST_FILE"

#######################################
# Verify Backup Integrity
#######################################

log "Verifying backup integrity..."
if tar -tzf "$BACKUP_FILE" >/dev/null 2>&1; then
    log "Backup integrity verified (archive is readable)"

    # Count files in archive
    FILE_COUNT=$(tar -tzf "$BACKUP_FILE" 2>/dev/null | wc -l)
    log "Archive contains $FILE_COUNT files/directories"
else
    error "Backup integrity check failed!"
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
done < <(find "$BACKUP_DIR" -name "${APP_NAME}-*.tar.gz" -type f -mtime +${RETENTION_DAYS} 2>/dev/null)

log "Removed $DELETED_COUNT old backup(s)"

#######################################
# Summary
#######################################

BACKUP_COUNT=$(find "$BACKUP_DIR" -name "${APP_NAME}-*.tar.gz" -type f 2>/dev/null | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1 || echo "unknown")

log "=========================================="
log "Backup Summary"
log "=========================================="
log "Latest backup: $BACKUP_FILE"
log "Backup size: $BACKUP_SIZE"
log "Files archived: $FILE_COUNT"
log "Total backups: $BACKUP_COUNT"
log "Total storage used: $TOTAL_SIZE"
success "Application backup completed successfully"
