#!/bin/bash
set -euo pipefail

#######################################
# Postgres Test Environment Setup (Idempotent)
#
# Responsibilities:
# - Ensure local PostgreSQL server is running.
# - Ensure application DB user and database exist.
# - Ensure test database exists.
# - Optionally ensure pgvector extension via ensure-postgres-pgvector.sh.
#
# Safe to run multiple times:
# - If Postgres is already running and DB+user exist, exits quickly with success.
#######################################

DB_NAME="${DB_NAME:-ai_legal_war_machine}"
DB_USER="${DB_USER:-claude}"
DB_PASSWORD="${DB_PASSWORD:-claude}"
TEST_DB_NAME="${TEST_DB_NAME:-laravel_test}"

PGHOST="${PGHOST:-127.0.0.1}"
PGPORT="${PGPORT:-5432}"

LOG_FILE="${LOG_FILE:-/tmp/start-test-env-db-$(date +%Y%m%d-%H%M%S).log}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Use app user credentials for direct psql calls
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

have_systemd() {
    command -v systemctl >/dev/null 2>&1 && [ -d /run/systemd/system ]
}

have_service() {
    command -v service >/dev/null 2>&1
}

postgres_running() {
    # Prefer pg_isready if available
    if command -v pg_isready >/dev/null 2>&1; then
        pg_isready -h "$PGHOST" -p "$PGPORT" >/dev/null 2>&1
        return $?
    fi

    # Fallback: try simple psql to postgres DB as superuser or app user
    if command -v psql >/dev/null 2>&1; then
        psql -h "$PGHOST" -p "$PGPORT" -U "$DB_USER" -d postgres -c "SELECT 1;" >/dev/null 2>&1 && return 0
        # Try default 'postgres' superuser (may need peer auth)
        PGPASSWORD="" psql -h "$PGHOST" -p "$PGPORT" -U postgres -d postgres -c "SELECT 1;" >/dev/null 2>&1 && return 0
    fi

    return 1
}

wait_for_postgres() {
    local timeout="${1:-60}"
    local waited=0
    local interval=2

    log "Waiting for PostgreSQL to accept connections on ${PGHOST}:${PGPORT} (timeout: ${timeout}s)"

    while [ "$waited" -lt "$timeout" ]; do
        if postgres_running; then
            success "PostgreSQL is up and accepting connections"
            return 0
        fi
        sleep "$interval"
        waited=$((waited + interval))
        log "Still waiting for PostgreSQL... (${waited}/${timeout}s)"
    done

    warn "Timed out waiting for PostgreSQL"
    return 1
}

run_as_postgres() {
    # Run a shell command as the 'postgres' OS user.
    # Tries su first (works better in containers), then sudo, then current user.
    local cmd="$*"

    # If we're already postgres, just run it
    if [ "$(whoami)" = "postgres" ]; then
        bash -lc "$cmd"
        return $?
    fi

    # Prefer su (works in containers without sudo)
    if command -v su >/dev/null 2>&1; then
        su - postgres -c "$cmd"
        return $?
    fi

    # Try sudo as fallback
    if command -v sudo >/dev/null 2>&1; then
        sudo -u postgres bash -lc "$cmd"
        return $?
    fi

    # Last resort - try as current user
    bash -lc "$cmd"
}

find_pg_ctl() {
    # Find pg_ctl binary - check common locations
    for path in /usr/lib/postgresql/*/bin/pg_ctl /usr/pgsql-*/bin/pg_ctl /usr/local/pgsql/bin/pg_ctl; do
        # Use glob expansion
        for expanded in $path; do
            if [ -x "$expanded" ]; then
                echo "$expanded"
                return 0
            fi
        done
    done
    # Check PATH
    if command -v pg_ctl >/dev/null 2>&1; then
        command -v pg_ctl
        return 0
    fi
    return 1
}

find_pg_data_dir() {
    # Find the PostgreSQL data directory
    # Check common locations for Debian/Ubuntu style installs
    for datadir in /var/lib/postgresql/*/main; do
        if [ -d "$datadir" ] && [ -f "$datadir/PG_VERSION" ]; then
            echo "$datadir"
            return 0
        fi
    done
    # Check PGDATA environment variable
    if [ -n "${PGDATA:-}" ] && [ -d "$PGDATA" ]; then
        echo "$PGDATA"
        return 0
    fi
    return 1
}

find_pg_config_file() {
    # Find postgresql.conf for socket/port configuration
    for conf in /etc/postgresql/*/main/postgresql.conf; do
        if [ -f "$conf" ]; then
            echo "$conf"
            return 0
        fi
    done
    return 1
}

fix_ssl_permissions() {
    # PostgreSQL requires strict permissions on SSL private keys
    # - The /etc/ssl/private directory must be accessible by ssl-cert group
    # - The key file must be owned by postgres:ssl-cert with 600 permissions
    local ssl_dir="/etc/ssl/private"
    local ssl_key="/etc/ssl/private/ssl-cert-snakeoil.key"

    # First fix the directory - must allow ssl-cert group to traverse
    if [ -d "$ssl_dir" ]; then
        local dir_owner dir_group
        dir_owner=$(stat -c '%U' "$ssl_dir" 2>/dev/null || echo "unknown")
        dir_group=$(stat -c '%G' "$ssl_dir" 2>/dev/null || echo "unknown")

        if [ "$dir_group" != "ssl-cert" ]; then
            log "Fixing SSL directory group ($ssl_dir): $dir_group -> ssl-cert"
            chown root:ssl-cert "$ssl_dir" 2>/dev/null || warn "Could not fix SSL directory group"
        fi
        chmod 710 "$ssl_dir" 2>/dev/null || warn "Could not fix SSL directory permissions"
    fi

    # Then fix the key file
    if [ -f "$ssl_key" ]; then
        local perms owner
        perms=$(stat -c '%a' "$ssl_key" 2>/dev/null || echo "unknown")
        owner=$(stat -c '%U' "$ssl_key" 2>/dev/null || echo "unknown")

        # Ensure owned by postgres:ssl-cert with 600 permissions
        if [ "$owner" != "postgres" ]; then
            log "Fixing SSL key ownership ($ssl_key): $owner -> postgres:ssl-cert"
            chown postgres:ssl-cert "$ssl_key" 2>/dev/null || warn "Could not fix SSL key ownership"
        fi
        if [ "$perms" != "600" ]; then
            log "Fixing SSL key permissions ($ssl_key): $perms -> 600"
            chmod 600 "$ssl_key" 2>/dev/null || warn "Could not fix SSL key permissions"
        fi
    fi
}

fix_config_permissions() {
    # Ensure PostgreSQL config files are readable by the server process
    local config_dir
    config_dir="$(dirname "$(find_pg_config_file)")" 2>/dev/null || return 0

    for conf_file in "$config_dir"/*.conf "$config_dir"/pg_ident.conf; do
        if [ -f "$conf_file" ]; then
            local perms
            perms=$(stat -c '%a' "$conf_file" 2>/dev/null || echo "unknown")
            # Config files need at least 644 to be readable
            if [ "${perms:2:1}" -lt 4 ] 2>/dev/null; then
                log "Fixing config file permissions ($conf_file): $perms -> 644"
                chmod 644 "$conf_file" 2>/dev/null || warn "Could not fix permissions on $conf_file"
            fi
        fi
    done
}

fix_postgres_ownership() {
    # Fix ownership of all PostgreSQL directories - must be owned by postgres user
    # This is critical because PostgreSQL refuses to start if data dir has wrong owner
    log "Checking PostgreSQL directory ownership..."

    local data_dir config_dir log_dir run_dir
    data_dir="$(find_pg_data_dir)" 2>/dev/null
    config_dir="$(dirname "$(find_pg_config_file)" 2>/dev/null)" 2>/dev/null
    log_dir="/var/log/postgresql"
    run_dir="/var/run/postgresql"

    # Fix data directory ownership
    if [ -n "$data_dir" ] && [ -d "$data_dir" ]; then
        local owner
        owner=$(stat -c '%U' "$data_dir" 2>/dev/null || echo "unknown")
        if [ "$owner" != "postgres" ]; then
            log "Fixing data directory ownership ($data_dir): $owner -> postgres:postgres"
            chown -R postgres:postgres "$data_dir" 2>/dev/null || warn "Could not fix data directory ownership"
        fi
    fi

    # Fix config directory ownership
    if [ -n "$config_dir" ] && [ -d "$config_dir" ]; then
        local owner
        owner=$(stat -c '%U' "$config_dir" 2>/dev/null || echo "unknown")
        if [ "$owner" != "postgres" ]; then
            log "Fixing config directory ownership ($config_dir): $owner -> postgres:postgres"
            chown -R postgres:postgres "$config_dir" 2>/dev/null || warn "Could not fix config directory ownership"
        fi
    fi

    # Fix log directory ownership
    if [ -d "$log_dir" ]; then
        local owner
        owner=$(stat -c '%U' "$log_dir" 2>/dev/null || echo "unknown")
        if [ "$owner" != "postgres" ]; then
            log "Fixing log directory ownership ($log_dir): $owner -> postgres:postgres"
            chown -R postgres:postgres "$log_dir" 2>/dev/null || warn "Could not fix log directory ownership"
        fi
    fi

    # Fix run directory ownership
    if [ -d "$run_dir" ]; then
        local owner
        owner=$(stat -c '%U' "$run_dir" 2>/dev/null || echo "unknown")
        if [ "$owner" != "postgres" ]; then
            log "Fixing run directory ownership ($run_dir): $owner -> postgres:postgres"
            chown -R postgres:postgres "$run_dir" 2>/dev/null || warn "Could not fix run directory ownership"
        fi
    fi
}

configure_pg_hba_trust() {
    # Configure pg_hba.conf to use trust authentication for local connections
    # This allows the setup script to connect without password issues
    local pg_hba
    pg_hba="$(dirname "$(find_pg_config_file)" 2>/dev/null)/pg_hba.conf" 2>/dev/null

    if [ ! -f "$pg_hba" ]; then
        warn "pg_hba.conf not found, skipping authentication configuration"
        return 0
    fi

    # Check if we need to update (look for peer auth on local connections)
    if grep -q "^local.*all.*all.*peer" "$pg_hba" 2>/dev/null || \
       grep -q "^host.*all.*all.*127.0.0.1/32.*scram-sha-256" "$pg_hba" 2>/dev/null; then
        log "Configuring pg_hba.conf for trust authentication..."

        # Backup original
        cp "$pg_hba" "${pg_hba}.bak.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true

        # Update peer -> trust for local connections
        sed -i 's/^local\s\+all\s\+postgres\s\+peer/local   all             postgres                                trust/' "$pg_hba" 2>/dev/null || true
        sed -i 's/^local\s\+all\s\+all\s\+peer/local   all             all                                     trust/' "$pg_hba" 2>/dev/null || true

        # Update scram-sha-256 -> trust for IPv4 localhost
        sed -i 's/^host\s\+all\s\+all\s\+127.0.0.1\/32\s\+scram-sha-256/host    all             all             127.0.0.1\/32            trust/' "$pg_hba" 2>/dev/null || true

        # Fix ownership after editing
        chown postgres:postgres "$pg_hba" 2>/dev/null || true

        success "pg_hba.conf configured for trust authentication"
    else
        log "pg_hba.conf already configured for trust authentication"
    fi
}

get_data_dir_owner() {
    local data_dir="$1"
    # Get the owner of the data directory
    stat -c '%U' "$data_dir" 2>/dev/null || echo "postgres"
}

run_as_user() {
    # Run a command as a specific user
    local user="$1"
    shift
    local cmd="$*"

    # If we're already that user, just run it
    if [ "$(whoami)" = "$user" ]; then
        bash -lc "$cmd"
        return $?
    fi

    # Try su (works in containers without sudo)
    if command -v su >/dev/null 2>&1; then
        su - "$user" -c "$cmd"
        return $?
    fi

    # Try sudo
    if command -v sudo >/dev/null 2>&1; then
        sudo -u "$user" bash -lc "$cmd"
        return $?
    fi

    # Can't switch users
    warn "Cannot switch to user $user"
    return 1
}

start_postgres_with_pg_ctl() {
    local pg_ctl_bin
    local data_dir
    local config_file

    pg_ctl_bin="$(find_pg_ctl)" || {
        warn "pg_ctl binary not found"
        return 1
    }

    data_dir="$(find_pg_data_dir)" || {
        warn "PostgreSQL data directory not found"
        return 1
    }

    config_file="$(find_pg_config_file)" || {
        warn "PostgreSQL config file not found; will use defaults"
        config_file=""
    }

    log "Found pg_ctl at: $pg_ctl_bin"
    log "Using data directory: $data_dir"
    [ -n "$config_file" ] && log "Using config file: $config_file"

    # Fix SSL permissions before starting (common issue in containers)
    fix_ssl_permissions

    # Fix config file permissions (may be owned by root after edits)
    fix_config_permissions

    # Determine who owns the data directory - we need to run as that user
    local data_owner
    data_owner="$(get_data_dir_owner "$data_dir")"
    log "Data directory owned by: $data_owner"

    # Ensure the socket directory exists and has correct permissions
    local socket_dir="/var/run/postgresql"
    if [ ! -d "$socket_dir" ]; then
        log "Creating socket directory: $socket_dir"
        mkdir -p "$socket_dir"
    fi
    # Make socket dir accessible to the data owner
    chown "$data_owner" "$socket_dir" 2>/dev/null || true
    chmod 775 "$socket_dir"

    # Build pg_ctl options
    local pg_opts="-D $data_dir"
    if [ -n "$config_file" ]; then
        pg_opts="$pg_opts -o '-c config_file=$config_file'"
    fi

    # Start PostgreSQL as the data directory owner (pg_ctl refuses to run as root)
    log "Starting PostgreSQL with pg_ctl as user '$data_owner'..."
    if run_as_user "$data_owner" "$pg_ctl_bin start $pg_opts -l /var/log/postgresql/postgresql.log -w" 2>&1; then
        log "Started PostgreSQL via pg_ctl"
        return 0
    fi

    # If that failed, try without the log file (in case directory doesn't exist)
    log "Retrying pg_ctl start without log file..."
    if run_as_user "$data_owner" "$pg_ctl_bin start $pg_opts -w" 2>&1; then
        log "Started PostgreSQL via pg_ctl (without log file)"
        return 0
    fi

    warn "pg_ctl start failed"
    return 1
}

ensure_postgres_started() {
    if postgres_running; then
        log "PostgreSQL already appears to be running; skipping start"
        return 0
    fi

    log "Attempting to start PostgreSQL service..."

    # Fix all permission and ownership issues BEFORE trying to start
    # These are critical for PostgreSQL to start in container environments

    # 1. Fix SSL permissions (directory and key file)
    fix_ssl_permissions

    # 2. Fix PostgreSQL directory ownership (data, config, log, run dirs)
    fix_postgres_ownership

    # 3. Configure pg_hba.conf for trust authentication
    configure_pg_hba_trust

    # 4. Fix config file permissions
    fix_config_permissions

    local started=0

    if have_systemd; then
        # Common service name on Debian/Ubuntu: postgresql
        if systemctl list-unit-files 2>/dev/null | grep -q '^postgresql\.service'; then
            if systemctl start postgresql >/dev/null 2>&1; then
                log "Started PostgreSQL via systemctl (postgresql.service)"
                started=1
            else
                warn "systemctl start postgresql failed"
            fi
        fi

        # If there are versioned clusters, try starting all
        if [ "$started" -eq 0 ] && command -v pg_lsclusters >/dev/null 2>&1; then
            if run_as_postgres "pg_ctlcluster --all start" >/dev/null 2>&1; then
                log "Started PostgreSQL via pg_ctlcluster --all start"
                started=1
            else
                warn "pg_ctlcluster --all start failed"
            fi
        fi
    fi

    if [ "$started" -eq 0 ] && have_service; then
        if service postgresql start >/dev/null 2>&1; then
            log "Started PostgreSQL via service postgresql start"
            started=1
        else
            warn "service postgresql start failed"
        fi
    fi

    # Fallback: try pg_ctl directly (useful in containers without systemd/service)
    if [ "$started" -eq 0 ]; then
        log "Trying direct pg_ctl start as fallback..."
        if start_postgres_with_pg_ctl; then
            started=1
        fi
    fi

    if [ "$started" -eq 0 ]; then
        error "Could not start PostgreSQL service automatically"
        return 1
    fi

    if ! wait_for_postgres 60; then
        error "PostgreSQL did not become ready after start attempt"
        return 1
    fi

    return 0
}

app_db_ready() {
    psql -h "$PGHOST" -p "$PGPORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1;" >/dev/null 2>&1
}

ensure_db_user_and_db() {
    log "Ensuring PostgreSQL role '$DB_USER' and database '$DB_NAME' exist..."

    local sql_role="SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'"
    local sql_db="SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'"
    local sql_test_db="SELECT 1 FROM pg_database WHERE datname='${TEST_DB_NAME}'"

    # Ensure role exists
    if run_as_postgres "psql -tAc \"$sql_role\"" | grep -q 1; then
        log "Role '$DB_USER' already exists"
    else
        log "Creating role '$DB_USER'..."
        run_as_postgres "psql -c \"CREATE ROLE ${DB_USER} WITH LOGIN PASSWORD '${DB_PASSWORD}';\""
        success "Role '$DB_USER' created"
    fi

    # Ensure main DB exists
    if run_as_postgres "psql -tAc \"$sql_db\"" | grep -q 1; then
        log "Database '$DB_NAME' already exists"
    else
        log "Creating database '$DB_NAME' owned by '$DB_USER'..."
        run_as_postgres "createdb -O \"${DB_USER}\" \"${DB_NAME}\""
        success "Database '$DB_NAME' created"
    fi

    # Ensure test DB exists
    if run_as_postgres "psql -tAc \"$sql_test_db\"" | grep -q 1; then
        log "Test database '$TEST_DB_NAME' already exists"
    else
        log "Creating test database '$TEST_DB_NAME' owned by '$DB_USER'..."
        run_as_postgres "createdb -O \"${DB_USER}\" \"${TEST_DB_NAME}\""
        success "Test database '$TEST_DB_NAME' created"
    fi
}

ensure_pgvector() {
    local ensure_script="$SCRIPT_DIR/ensure-postgres-pgvector.sh"

    if [ -x "$ensure_script" ]; then
        log "Ensuring pgvector extension via $ensure_script"
        if "$ensure_script" >>"$LOG_FILE" 2>&1; then
            success "pgvector extension ensured via ensure-postgres-pgvector.sh"
        else
            warn "ensure-postgres-pgvector.sh reported an error; continuing"
        fi
    else
        log "ensure-postgres-pgvector.sh not found or not executable; skipping pgvector setup"
    fi
}

#######################################
# Main
#######################################

log "=========================================="
log "PostgreSQL Test Environment Setup (idempotent)"
log "Log file: $LOG_FILE"
log "DB_NAME: $DB_NAME"
log "DB_USER: $DB_USER"
log "TEST_DB_NAME: $TEST_DB_NAME"
log "Script started at: $(date)"
log "=========================================="

# Fast path check: if app DB is already ready, we still need to ensure test DB exists
if app_db_ready; then
    log "Application database '$DB_NAME' already accessible as '$DB_USER'"
    # But still ensure test DB exists
    log "Ensuring test database '$TEST_DB_NAME' exists..."
    sql_test_db="SELECT 1 FROM pg_database WHERE datname='${TEST_DB_NAME}'"
    if run_as_postgres "psql -tAc \"$sql_test_db\"" 2>/dev/null | grep -q 1; then
        log "Test database '$TEST_DB_NAME' already exists"
    else
        log "Creating test database '$TEST_DB_NAME' owned by '$DB_USER'..."
        run_as_postgres "createdb -O \"${DB_USER}\" \"${TEST_DB_NAME}\"" 2>/dev/null || warn "Could not create test database"
        success "Test database '$TEST_DB_NAME' created"
    fi
    success "PostgreSQL environment is ready"
    exit 0
fi

# Ensure server is running
if ! ensure_postgres_started; then
    error "Failed to ensure PostgreSQL is running"
    exit 1
fi

# Ensure role and databases exist
ensure_db_user_and_db

# Ensure pgvector extension (if script present)
ensure_pgvector

# Final verification
if app_db_ready; then
    success "PostgreSQL environment is ready (DB '$DB_NAME' accessible as '$DB_USER')"
    log "PostgreSQL setup finished at: $(date)"
    exit 0
else
    error "PostgreSQL environment still not accessible as '$DB_USER' to DB '$DB_NAME' after setup"
    exit 1
fi
