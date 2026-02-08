#!/usr/bin/env bash

# PostgreSQL Export/Import Wrapper
# Usage:
#   ./pg_backup.sh export <db_name> <output_file.sql.gz>
#   ./pg_backup.sh import <db_name> <input_file.sql.gz>
#
# Environment variables (optional):
#   PGHOST, PGPORT, PGUSER, PGPASSWORD

set -euo pipefail

# --- Helper functions ---
usage() {
  echo "Usage:"
  echo "  $0 export <db_name> <output_file.sql.gz>"
  echo "  $0 import <db_name> <input_file.sql.gz>"
  echo
  echo "Optional environment variables: PGHOST, PGPORT, PGUSER, PGPASSWORD"
  exit 1
}

log() {
  echo "[INFO] $*"
}

error() {
  echo "[ERROR] $*" >&2
  exit 1
}

# --- Argument parsing ---
if [ "$#" -lt 3 ]; then
  usage
fi

COMMAND="$1"
DB_NAME="$2"
FILE_PATH="$3"



# --- Actions ---
case "$COMMAND" in
  export)
    log "Exporting database '$DB_NAME' to '$FILE_PATH'..."
    sudo su postgres && pg_dump "$DB_NAME" | gzip > "$FILE_PATH"
    log "Export completed successfully."
    ;;
  import)
    log "Importing database dump from '$FILE_PATH' into '$DB_NAME'..."
    if ! [ -f "$FILE_PATH" ]; then
      error "File '$FILE_PATH' not found!"
    fi
    gunzip -c "$FILE_PATH" | psql "$DB_NAME"
    log "Import completed successfully."
    ;;
  *)
    usage
    ;;
esac
