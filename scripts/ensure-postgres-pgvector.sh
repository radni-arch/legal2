#!/usr/bin/env bash

set -u  # no undefined variables
# (We intentionally do NOT use `set -e` so we can handle non-fatal errors ourselves.)

#######################################
# Helper functions
#######################################
log()   { printf '[INFO] %s\n' "$*" >&2; }
warn()  { printf '[WARN] %s\n' "$*" >&2; }
error() { printf '[ERROR] %s\n' "$*" >&2; }

#######################################
# Pre-checks
#######################################

# Check for required commands (but don't require root yet - we'll check when needed)
if ! command -v apt >/dev/null 2>&1 || ! command -v dpkg >/dev/null 2>&1; then
    error "This script requires a Debian/Ubuntu system with apt/dpkg."
    exit 1
fi

# Function to check if we're running as root
check_root() {
    if [ "${EUID:-$(id -u)}" -ne 0 ]; then
        error "This operation requires root privileges. Please run this script as root."
        exit 1
    fi
}

#######################################
# Fix interrupted dpkg state
#######################################

# If dpkg was previously interrupted, apt operations will fail.
# Run dpkg --configure -a to fix this before any apt operations.
if [ "${EUID:-$(id -u)}" -eq 0 ]; then
    log "Checking for interrupted dpkg state..."
    dpkg --configure -a </dev/null 2>&1 || true
fi

#######################################
# Functions to detect/install PostgreSQL
#######################################

postgres_server_installed() {
    # Treat PostgreSQL as installed if a server cluster config exists
    if compgen -G "/etc/postgresql/*/main/postgresql.conf" >/dev/null 2>&1; then
        return 0
    fi
    return 1
}

get_pg_major() {
    # Determine installed PostgreSQL major version from /etc/postgresql
    local majors=() d bn
    if [ -d /etc/postgresql ]; then
        for d in /etc/postgresql/*; do
            [ -e "$d" ] || continue
            bn=$(basename "$d")
            if [[ "$bn" =~ ^[0-9]+$ ]]; then
                majors+=("$bn")
            fi
        done
    fi

    if [ "${#majors[@]}" -eq 0 ]; then
        return 1
    fi

    # Sort numerically and pick the highest version
    printf '%s\n' "${majors[@]}" | sort -n | tail -n 1
    return 0
}

add_pgdg_repo_and_key() {
    local codename key_url list_file
    if ! command -v lsb_release >/dev/null 2>&1; then
        error "lsb_release command not found. Install 'lsb-release' package or set the codename manually."
        return 1
    fi

    codename=$(lsb_release -cs)
    key_url="https://www.postgresql.org/media/keys/ACCC4CF8.asc"
    list_file="/etc/apt/sources.list.d/pgdg.list"

    if [ ! -f "$list_file" ]; then
        log "Adding PostgreSQL PGDG APT repository for $codename."
        printf 'deb http://apt.postgresql.org/pub/repos/apt %s-pgdg main\n' "$codename" > "$list_file"
    else
        log "PGDG sources list already present at $list_file; reusing it."
    fi

    # Add repository key
    if command -v apt-key >/dev/null 2>&1; then
        log "Importing PostgreSQL APT repository key with apt-key."
        if ! wget --quiet -O - "$key_url" | apt-key add - >/dev/null 2>&1; then
            warn "Failed to import PostgreSQL key with apt-key."
        fi
    else
        # Fallback to keyring-based approach
        local keyring="/usr/share/keyrings/postgresql-pgdg.gpg"
        log "apt-key not found; importing PostgreSQL key to $keyring."
        if command -v curl >/dev/null 2>&1 && command -v gpg >/dev/null 2>&1; then
            if curl -fsSL "$key_url" | gpg --dearmor >/tmp/pgdg.gpg 2>/dev/null; then
                mv /tmp/pgdg.gpg "$keyring"
                chmod 644 "$keyring"
                # Update the sources entry to use signed-by if needed
                if ! grep -q "signed-by=$keyring" "$list_file"; then
                    sed -i "s|^deb |deb [signed-by=$keyring] |" "$list_file" || true
                fi
            else
                warn "Failed to download or dearmor PostgreSQL key; continuing without updating key."
            fi
        else
            warn "curl or gpg not available; cannot install key via modern keyring method."
        fi
    fi
}

install_postgresql_if_needed() {
    if postgres_server_installed; then
        log "PostgreSQL server already appears to be installed; skipping package installation."
        return 0
    fi

    log "No PostgreSQL server installation detected; installing PostgreSQL 16."

    # Only require root when we actually need to install
    check_root

    # Add PGDG repo and key
    if ! add_pgdg_repo_and_key; then
        warn "Failed to fully configure the PGDG repository; attempting apt operations anyway."
    fi

    log "Running apt update..."
    if ! apt update -y >/dev/null 2>&1; then
        error "apt update failed. Check your network or APT configuration."
        return 1
    fi

    log "Installing postgresql-16 and postgresql-contrib-16 (no sudo)..."
    if ! apt install -y postgresql-16 postgresql-contrib-16; then
        error "Failed to install postgresql-16 and postgresql-contrib-16."
        return 1
    fi

    log "PostgreSQL 16 installation attempted."
    return 0
}

#######################################
# Main installation/configuration flow
#######################################

# 1. Install PostgreSQL server if needed (without sudo)
install_postgresql_if_needed || {
    error "PostgreSQL installation step failed; aborting."
    exit 1
}

# 2. Determine which PostgreSQL major version to configure
PG_MAJOR=""
if PG_MAJOR=$(get_pg_major); then
    log "Detected PostgreSQL major version: $PG_MAJOR"
else
    error "Could not determine installed PostgreSQL major version (no /etc/postgresql/* directories)."
    exit 1
fi

PG_CONF_DIR="/etc/postgresql/${PG_MAJOR}/main"
PG_CONF_FILE="${PG_CONF_DIR}/postgresql.conf"
PG_HBA_FILE="${PG_CONF_DIR}/pg_hba.conf"

# Check if we're root for configuration tasks
# If not root, skip configuration but don't fail - postgres might already be configured
IS_ROOT=false
if [ "${EUID:-$(id -u)}" -eq 0 ]; then
    IS_ROOT=true
fi

# 3. Fix ownership of snakeoil SSL key (non-fatal, requires root)
if [ "$IS_ROOT" = true ]; then
    if [ -f /etc/ssl/private/ssl-cert-snakeoil.key ]; then
        chown root:ssl-cert /etc/ssl/private/ssl-cert-snakeoil.key 2>/dev/null || true
        ls -la /etc/ssl/private/ssl-cert-snakeoil.key || true
    else
        warn "/etc/ssl/private/ssl-cert-snakeoil.key not found; skipping ownership change."
    fi
else
    log "Not running as root; skipping SSL certificate ownership fix."
fi

# 4. Configure PostgreSQL SSL and authentication (requires root)
if [ "$IS_ROOT" = true ]; then
    if [ -f "$PG_CONF_FILE" ]; then
        log "Configuring SSL in $PG_CONF_FILE (ssl = off)."

        # If a line with ssl = exists (commented or not), modify it; otherwise append.
        if grep -Eiq '^[#]*\s*ssl\s*=' "$PG_CONF_FILE"; then
            # Replace any ssl line with 'ssl = off'
            sed -i -E 's|^[#]*\s*ssl\s*=.*|ssl = off|' "$PG_CONF_FILE" || true
        else
            printf '\nssl = off\n' >> "$PG_CONF_FILE" || true
        fi

        # Show resulting ssl line(s)
        grep -Ei '^[#]*\s*ssl\s*=' "$PG_CONF_FILE" || true
    else
        warn "PostgreSQL config file not found at $PG_CONF_FILE; skipping SSL configuration."
    fi

    if [ -f "$PG_HBA_FILE" ]; then
        log "Configuring authentication in $PG_HBA_FILE."

        # Make local connections for postgres user trust instead of peer
        sed -i -E 's|^local\s+all\s+postgres\s+peer|local   all             postgres                                trust|' "$PG_HBA_FILE" 2>/dev/null || true

        # Make local connections for all users trust instead of peer
        sed -i -E 's|^local\s+all\s+all\s+peer|local   all             all                                     trust|' "$PG_HBA_FILE" 2>/dev/null || true

        # Make 127.0.0.1/32 host connections use trust authentication for easier setup
        sed -i -E 's|^host\s+all\s+all\s+127\.0\.0\.1/32.*|host    all             all             127.0.0.1/32            trust|' "$PG_HBA_FILE" 2>/dev/null || true
    else
        warn "pg_hba.conf not found at $PG_HBA_FILE; skipping authentication configuration."
    fi

    # 5. Reload/start PostgreSQL
    log "Reloading/starting PostgreSQL service."
    service postgresql reload 2>/dev/null || true
    service postgresql start 2>/dev/null || true
    sleep 10
else
    log "Not running as root; skipping PostgreSQL configuration and service management."
    log "Assuming PostgreSQL is already configured and running."
fi

# 6. Install pgvector extension package if not present (requires root)
PGVECTOR_PKG="postgresql-${PG_MAJOR}-pgvector"
if dpkg -s "$PGVECTOR_PKG" >/dev/null 2>&1; then
    log "Package $PGVECTOR_PKG already installed."
else
    if [ "$IS_ROOT" = true ]; then
        log "Attempting to install $PGVECTOR_PKG."
        if apt install -y "$PGVECTOR_PKG"; then
            log "$PGVECTOR_PKG installed successfully."
        else
            warn "Could not install $PGVECTOR_PKG. The 'vector' extension might not be available."
        fi
    else
        warn "Not running as root; cannot install $PGVECTOR_PKG. The 'vector' extension might not be available."
    fi
fi

# 7. Verify PostgreSQL and create vector extension
if command -v su >/dev/null 2>&1; then
    log "Verifying PostgreSQL server version as postgres user."
    su - postgres -c "psql -c 'SELECT version();'" || warn "Failed to run SELECT version();"

    log "Creating vector extension in 'postgres' database (if available)."
    su - postgres -c "psql -d postgres -c 'CREATE EXTENSION IF NOT EXISTS vector;'" || \
        warn "Failed to create the 'vector' extension (pgvector may not be installed or supported)."
else
    warn "'su' command not available; skipping verification and extension creation as postgres user."
fi

log "PostgreSQL setup script completed."
exit 0
