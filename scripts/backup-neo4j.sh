#!/bin/bash
set -euo pipefail

#######################################
# Neo4j Graph Database Backup Script
#
# Features:
# - Graph dump export using neo4j-admin
# - Timestamp-based naming
# - Gzip compression
# - 30-day retention policy
#
# Usage:
#   ./scripts/backup-neo4j.sh
#   ./scripts/backup-neo4j.sh --dry-run
#   BACKUP_DIR=/custom/path ./scripts/backup-neo4j.sh
#######################################

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/backups/neo4j}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
NEO4J_HOME="${NEO4J_HOME:-/var/lib/neo4j}"
NEO4J_DATABASE="${NEO4J_DATABASE:-neo4j}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/neo4j-${NEO4J_DATABASE}-${TIMESTAMP}.dump"
LOG_FILE="${LOG_FILE:-/var/log/backup-neo4j.log}"
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
            echo "  BACKUP_DIR       Backup destination (default: /backups/neo4j)"
            echo "  RETENTION_DAYS   Days to keep backups (default: 30)"
            echo "  NEO4J_HOME       Neo4j installation directory"
            echo "  NEO4J_DATABASE   Database name (default: neo4j)"
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
# Find neo4j-admin binary
#######################################

find_neo4j_admin() {
    for path in \
        /usr/bin/neo4j-admin \
        /usr/share/neo4j/bin/neo4j-admin \
        /opt/neo4j/bin/neo4j-admin \
        /usr/local/bin/neo4j-admin \
        "${NEO4J_HOME}/bin/neo4j-admin"; do
        if [ -x "$path" ]; then
            echo "$path"
            return 0
        fi
    done
    return 1
}

#######################################
# Pre-flight Checks
#######################################

log "=========================================="
log "Neo4j Graph Database Backup"
log "=========================================="
log "Database: $NEO4J_DATABASE"
log "Backup destination: $BACKUP_DIR"
log "Retention: $RETENTION_DAYS days"

# Find neo4j-admin
NEO4J_ADMIN=$(find_neo4j_admin) || {
    error "neo4j-admin not found. Please install Neo4j or set NEO4J_HOME."
    exit 1
}
log "Using neo4j-admin: $NEO4J_ADMIN"

# Check if Neo4j is running (backup may require it to be stopped for consistency)
if pgrep -f "org.neo4j.server" >/dev/null 2>&1; then
    warn "Neo4j is running. For online backup, ensure Neo4j Enterprise Edition."
    warn "Community Edition may require stopping Neo4j first for consistent backup."
fi

#######################################
# Create Backup
#######################################

if [ "$DRY_RUN" = true ]; then
    log "[DRY-RUN] Would create backup directory: $BACKUP_DIR"
    log "[DRY-RUN] Would create backup: ${BACKUP_FILE}.gz"
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
log "Starting Neo4j dump to: $BACKUP_FILE"
START_TIME=$(date +%s)

# Try Neo4j 5.x syntax first, fall back to 4.x
if "$NEO4J_ADMIN" database dump "$NEO4J_DATABASE" --to-path="$BACKUP_DIR" --overwrite-destination=true 2>>"$LOG_FILE"; then
    # Neo4j 5.x creates file in to-path directory
    CREATED_FILE="${BACKUP_DIR}/${NEO4J_DATABASE}.dump"
    if [ -f "$CREATED_FILE" ]; then
        mv "$CREATED_FILE" "$BACKUP_FILE"
    fi
elif "$NEO4J_ADMIN" dump --database="$NEO4J_DATABASE" --to="$BACKUP_FILE" 2>>"$LOG_FILE"; then
    # Neo4j 4.x syntax worked
    :
else
    error "Neo4j dump failed! Check if Neo4j needs to be stopped first."
    exit 1
fi

# Compress the backup
if [ -f "$BACKUP_FILE" ]; then
    log "Compressing backup..."
    gzip -f "$BACKUP_FILE"
    BACKUP_FILE="${BACKUP_FILE}.gz"
else
    error "Backup file not created!"
    exit 1
fi

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))
BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)

success "Backup completed in ${DURATION}s"
log "Backup size: $BACKUP_SIZE"
log "Backup file: $BACKUP_FILE"

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
done < <(find "$BACKUP_DIR" -name "neo4j-*.dump.gz" -type f -mtime +${RETENTION_DAYS} 2>/dev/null)

log "Removed $DELETED_COUNT old backup(s)"

#######################################
# Summary
#######################################

BACKUP_COUNT=$(find "$BACKUP_DIR" -name "neo4j-*.dump.gz" -type f 2>/dev/null | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1 || echo "unknown")

log "=========================================="
log "Backup Summary"
log "=========================================="
log "Latest backup: $BACKUP_FILE"
log "Backup size: $BACKUP_SIZE"
log "Total backups: $BACKUP_COUNT"
log "Total storage used: $TOTAL_SIZE"
success "Neo4j backup completed successfully"
