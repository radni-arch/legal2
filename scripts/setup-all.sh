#!/bin/bash
set -u  # Error on undefined variables, but continue on command failures

# Early exit for environments that manage setup themselves
if [ -n "${CLAUDE_SKIP_ENV_SETUP:-}" ]; then
    echo "[INFO] CLAUDE_SKIP_ENV_SETUP is set, skipping setup-all.sh" >&2
    exit 0
fi

#######################################
# Setup All Services Script
# Sets up PostgreSQL, Neo4j, Composer dependencies, migrations, and data
#######################################

# Configuration
LOG_FILE="${LOG_FILE:-/tmp/setup-all-$(date +%Y%m%d-%H%M%S).log}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
DB_IMPORT_MARKER="${DB_IMPORT_MARKER:-/tmp/ai_legal_war_machine-import.done}"

# Database credentials (match start-test-env-db.sh defaults)
DB_USER="${DB_USER:-claude}"
DB_PASSWORD="${DB_PASSWORD:-claude}"
export PGPASSWORD="$DB_PASSWORD"

# Optional log paths initialization to keep set -u happy on idempotent runs
IMPORT_MARKER_LOG="${DB_IMPORT_MARKER}.log"
CHROMEDRIVER_LOG=""

#######################################
# Subsystem Status Tracking
#######################################
# Each status: ok, fail, skip
# Each timestamp: ISO 8601 format
STATUS_DB="skip"
STATUS_DB_TS=""
STATUS_MIGRATIONS="skip"
STATUS_MIGRATIONS_TS=""
STATUS_SEED="skip"
STATUS_SEED_TS=""
STATUS_COMPOSER="skip"
STATUS_COMPOSER_TS=""
STATUS_NODE="skip"
STATUS_NODE_TS=""
STATUS_PLAYWRIGHT="skip"
STATUS_PLAYWRIGHT_TS=""
STATUS_NEO4J="skip"
STATUS_NEO4J_TS=""

# Track overall setup result
SETUP_FAILED=0

#######################################
# Logging Functions
#######################################

log() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [INFO] $*" | tee -a "$LOG_FILE"
}

warn() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [WARN] $*" | tee -a "$LOG_FILE" >&2
}

error() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [ERROR] $*" | tee -a "$LOG_FILE" >&2
}

success() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [SUCCESS] $*" | tee -a "$LOG_FILE"
}

step() {
    local step_num="$1"
    shift
    log "=========================================="
    log "STEP $step_num: $*"
    log "=========================================="
}

#######################################
# Error Handler
#######################################

cleanup_on_error() {
    local exit_code=$?
    local line_num=$1
    error "Command failed with exit code $exit_code at line $line_num"
    warn "Continuing with setup despite error..."
    return 0  # Don't exit, just log the error
}

# Note: trap on ERR is disabled to allow script to continue on failures
# Individual critical failures are handled explicitly
# trap 'cleanup_on_error $LINENO' ERR

#######################################
# Validation Functions
#######################################

have_service() {
    command -v service >/dev/null 2>&1
}

have_systemd() {
    command -v systemctl >/dev/null 2>&1 && [ -d /run/systemd/system ]
}

validate_postgres() {
    log "Validating PostgreSQL..."

    # Prefer pg_isready which only checks server availability (no auth required)
    if command -v pg_isready >/dev/null 2>&1; then
        if pg_isready -h 127.0.0.1 -p 5432 >/dev/null 2>&1; then
            success "PostgreSQL server is accepting connections"
            return 0
        else
            error "PostgreSQL server is not accepting connections (pg_isready failed)"
            return 1
        fi
    fi

    # Fallback: try a simple query as the postgres superuser via unix socket
    if command -v su >/dev/null 2>&1; then
        if su - postgres -c "psql -U postgres -d postgres -c 'SELECT 1;'" >/dev/null 2>&1; then
            success "PostgreSQL server is accepting connections (verified as postgres)"
            return 0
        else
            error "PostgreSQL server did not respond to postgres user check"
            return 1
        fi
    fi

    # As a last resort, attempt to connect as the configured DB user (may fail if role doesn't exist)
    if psql -h 127.0.0.1 -U "$DB_USER" -d ai_legal_war_machine -c "SELECT 1;" >/dev/null 2>&1; then
        success "PostgreSQL is running and accessible as $DB_USER"
        return 0
    else
        error "PostgreSQL validation failed (no pg_isready, su postgres, or app DB access)"
        return 1
    fi
}

postgres_app_db_ready() {
    # Returns 0 if the application's DB exists and is accessible as DB_USER
    psql -h 127.0.0.1 -U "$DB_USER" -d ai_legal_war_machine -c "SELECT 1;" >/dev/null 2>&1
}

validate_neo4j() {
    log "Validating Neo4j..."

    # 1. First priority: Check service wrapper if available
    if have_service; then
        log "Checking Neo4j via 'service' wrapper..."
        if service neo4j status >/dev/null 2>&1; then
            success "Neo4j service reported as active by 'service'"
            if command -v cypher-shell >/dev/null 2>&1; then
                if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p ${NEO4J_PASSWORD:-password} "RETURN 1;" >/dev/null 2>&1; then
                    success "Neo4j is running and accessible"
                    return 0
                fi
            else
                return 0
            fi
        else
            warn "'service neo4j status' did not report active"
        fi
    fi

    # 2. systemd check
    if have_systemd; then
        log "Checking Neo4j systemd service..."
        if systemctl is-active --quiet neo4j 2>/dev/null; then
            success "Neo4j systemd service is active"
            if command -v cypher-shell >/dev/null 2>&1; then
                if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p ${NEO4J_PASSWORD:-password} "RETURN 1;" >/dev/null 2>&1; then
                    success "Neo4j is running and accessible"
                    return 0
                fi
            else
                return 0
            fi
        else
            warn "Neo4j systemd service is not active"
        fi
    fi

    # 3. Check for Neo4j binaries in common locations and running processes
    NEO4J_BIN=""
    for path in /usr/bin/neo4j /usr/share/neo4j/bin/neo4j /opt/neo4j/bin/neo4j /usr/local/bin/neo4j; do
        if [ -x "$path" ]; then
            NEO4J_BIN="$path"
            break
        fi
    done

    if [ -n "$NEO4J_BIN" ]; then
        log "Found Neo4j binary at: $NEO4J_BIN"

        # Check if Neo4j process is running
        if pgrep -f "neo4j" >/dev/null 2>&1; then
            log "Neo4j process is running"

            # Test connection with cypher-shell
            if command -v cypher-shell >/dev/null 2>&1; then
                if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p ${NEO4J_PASSWORD:-password} "RETURN 1;" >/dev/null 2>&1; then
                    success "Neo4j is running and accessible"
                    return 0
                else
                    warn "Neo4j process running but connection test failed"
                    return 1
                fi
            else
                # No cypher-shell but process is running
                success "Neo4j process is running (cypher-shell not available for connection test)"
                return 0
            fi
        else
            warn "Neo4j binary found but process is not running"
            return 1
        fi
    else
        error "Neo4j installation not found"
        return 1
    fi
}

validate_composer_deps() {
    log "Validating Composer dependencies..."
    if [ -d "$PROJECT_DIR/vendor" ] && [ -f "$PROJECT_DIR/vendor/autoload.php" ]; then
        success "Composer dependencies are installed"
        return 0
    else
        error "Composer dependencies validation failed"
        return 1
    fi
}

validate_migrations() {
    log "Validating database migrations..."
    local table_count
    table_count=$(PGPASSWORD=claude psql -h 127.0.0.1 -U claude -d ai_legal_war_machine -tAc "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE';" 2>/dev/null || echo "0")

    if [ "$table_count" -gt 0 ]; then
        success "Database has $table_count tables - migrations appear to be applied"
        return 0
    else
        error "No tables found in database - migrations may not have run"
        return 1
    fi
}

#######################################
# Retry with Exponential Backoff
#######################################
# Usage: retry_with_backoff <max_attempts> <command...>
# Backoff: 1s, 2s, 4s, 8s (capped)
retry_with_backoff() {
    local max_attempts="$1"
    shift
    local attempt=1
    local delay=1

    while [ $attempt -le $max_attempts ]; do
        log "Attempt $attempt/$max_attempts: $*"
        if "$@"; then
            return 0
        fi
        if [ $attempt -lt $max_attempts ]; then
            warn "Attempt $attempt failed, retrying in ${delay}s..."
            sleep $delay
            delay=$((delay * 2))
            [ $delay -gt 8 ] && delay=8  # Cap at 8 seconds
        fi
        attempt=$((attempt + 1))
    done

    error "All $max_attempts attempts failed for: $*"
    return 1
}

#######################################
# DB Connectivity Probe (with real query)
#######################################
db_connectivity_probe() {
    log "Probing database connectivity with real query..."

    # First check pg_isready (fast port check)
    if command -v pg_isready >/dev/null 2>&1; then
        if ! pg_isready -h 127.0.0.1 -p 5432 -q; then
            error "PostgreSQL is not accepting connections (pg_isready failed)"
            return 1
        fi
        log "pg_isready: PostgreSQL is accepting connections"
    fi

    # Real query test - SELECT 1 with credentials
    if psql -h 127.0.0.1 -U "$DB_USER" -d ai_legal_war_machine -c "SELECT 1;" >/dev/null 2>&1; then
        success "DB connectivity probe passed (SELECT 1 succeeded)"
        return 0
    fi

    # Fallback: try as postgres superuser
    if su - postgres -c "psql -U postgres -d ai_legal_war_machine -c 'SELECT 1;'" >/dev/null 2>&1; then
        success "DB connectivity probe passed (as postgres superuser)"
        return 0
    fi

    error "DB connectivity probe failed - cannot execute SELECT 1"
    return 1
}

#######################################
# Migration Status Check (via artisan)
#######################################
check_migration_status() {
    log "Checking migration status via artisan..."

    if ! command -v php >/dev/null 2>&1; then
        warn "PHP not available for migration status check"
        return 1
    fi

    cd "$PROJECT_DIR" || return 1

    # Check if migrations are runnable
    local status_output
    status_output=$(php artisan migrate:status 2>&1) || {
        error "php artisan migrate:status failed"
        echo "$status_output" | tee -a "$LOG_FILE"
        return 1
    }

    # Check for pending migrations
    if echo "$status_output" | grep -q "Pending"; then
        warn "Pending migrations detected"
        echo "$status_output" | tee -a "$LOG_FILE"
        return 1
    fi

    # Count ran migrations
    local ran_count
    ran_count=$(echo "$status_output" | grep -c "Ran" || echo "0")
    if [ "$ran_count" -gt 0 ]; then
        success "Migration status: $ran_count migrations have been run"
        return 0
    fi

    warn "No migrations found or status unclear"
    return 1
}

#######################################
# Seed Verification (non-destructive)
#######################################
verify_seed_data() {
    log "Verifying seed data (non-destructive check)..."

    # Check for key tables that should exist after migrations
    # Note: This project uses 'textract_documents' not 'documents'
    local key_tables=("users" "cases" "textract_documents")
    local tables_found=0

    for table in "${key_tables[@]}"; do
        if psql -h 127.0.0.1 -U "$DB_USER" -d ai_legal_war_machine -tAc "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='$table' LIMIT 1;" 2>/dev/null | grep -q "1"; then
            log "Table '$table' exists"
            tables_found=$((tables_found + 1))
        else
            warn "Table '$table' not found"
        fi
    done

    if [ $tables_found -eq ${#key_tables[@]} ]; then
        # Check if users table has at least one row (marker for seeding)
        local user_count
        user_count=$(psql -h 127.0.0.1 -U "$DB_USER" -d ai_legal_war_machine -tAc "SELECT COUNT(*) FROM users;" 2>/dev/null || echo "0")
        if [ "$user_count" -gt 0 ]; then
            success "Seed verification passed: $user_count users found"
            return 0
        else
            warn "Key tables exist but no seed data found (users table is empty)"
            return 1
        fi
    fi

    warn "Seed verification incomplete: $tables_found/${#key_tables[@]} key tables found"
    return 1
}

wait_for_task() {
    local task_name="$1"
    local task_pid="$2"
    local task_log="$3"
    local timeout="${4:-0}"
    local interval=3
    local waited=0

    if [ -z "${task_pid:-}" ]; then
        log "$task_name was not started; skipping wait"
        return 0
    fi

    log "Waiting for $task_name (PID $task_pid)"

    while kill -0 "$task_pid" 2>/dev/null; do
        if [ "$timeout" -gt 0 ] && [ "$waited" -ge "$timeout" ]; then
            warn "$task_name still running after ${timeout}s; see $task_log for details"
            return 124
        fi
        sleep "$interval"
        waited=$((waited + interval))
        log "$task_name still in progress (${waited}s${timeout:+/${timeout}s})"
    done

    if wait "$task_pid" >/dev/null 2>&1; then
        success "$task_name completed successfully"
        return 0
    fi

    local exit_code=$?
    error "$task_name exited with code $exit_code (log: $task_log)"
    return $exit_code
}

#######################################
# Main Setup Process
#######################################

log "=========================================="
log "AI Legal War Machine - Setup Script"
log "=========================================="
log "Log file: $LOG_FILE"
log "Project directory: $PROJECT_DIR"
log "Script started at: $(date)"
log ""

cd "$PROJECT_DIR" || {
    error "Failed to change to project directory: $PROJECT_DIR"
    exit 1
}

#######################################
# Step 0: Setup Environment File (.env)
#######################################

step 0 "Setting up environment configuration"

if [ ! -f "$PROJECT_DIR/.env" ]; then
    log "Creating .env file from .env.example..."
    if [ ! -f "$PROJECT_DIR/.env.example" ]; then
        error ".env.example not found in project directory"
        exit 1
    fi

    cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
    success ".env file created"
else
    log ".env file already exists, updating configuration..."
fi

# Configure database credentials (handles both commented and uncommented lines)
log "Configuring database credentials in .env..."
# Function to set .env variable (uncomments if needed, updates if exists)
set_env_var() {
    local key="$1"
    local value="$2"
    local file="$3"
    # First try to update uncommented line
    if grep -q "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$file"
    # Then try to uncomment and update commented line
    elif grep -q "^#\s*${key}=" "$file"; then
        sed -i "s|^#\s*${key}=.*|${key}=${value}|" "$file"
    # Otherwise append at the end
    else
        echo "${key}=${value}" >> "$file"
    fi
}

set_env_var "DB_CONNECTION" "pgsql" "$PROJECT_DIR/.env"
set_env_var "DB_HOST" "127.0.0.1" "$PROJECT_DIR/.env"
set_env_var "DB_PORT" "5432" "$PROJECT_DIR/.env"
set_env_var "DB_DATABASE" "ai_legal_war_machine" "$PROJECT_DIR/.env"
set_env_var "DB_USERNAME" "$DB_USER" "$PROJECT_DIR/.env"
set_env_var "DB_PASSWORD" "$DB_PASSWORD" "$PROJECT_DIR/.env"

# Configure Neo4j password (will be set after Neo4j installation)
NEO4J_PASSWORD="${NEO4J_PASSWORD:-password}"
sed -i "s|^NEO4J_PASSWORD=.*|NEO4J_PASSWORD=$NEO4J_PASSWORD|" "$PROJECT_DIR/.env"

# Set dummy OpenAI key for setup (user should replace with real key)
if grep -q "^OPENAI_API_KEY=$" "$PROJECT_DIR/.env" 2>/dev/null; then
    log "Setting placeholder OpenAI API key..."
    sed -i "s|^OPENAI_API_KEY=.*|OPENAI_API_KEY=dummy_key_for_setup|" "$PROJECT_DIR/.env"
fi

# Generate application key if not set
if grep -q "^APP_KEY=$" "$PROJECT_DIR/.env" 2>/dev/null || ! grep -q "^APP_KEY=" "$PROJECT_DIR/.env" 2>/dev/null; then
    log "Generating application key..."
    # We'll do this after composer install
fi

success "Environment file configured"

#######################################
# Step 1: Setup Neo4j, PostgreSQL, and Chrome (idempotent, parallel where safe)
#######################################

step 1 "Setting up Neo4j, PostgreSQL, and Chrome"

# Validate scripts exist
if [ ! -x "$SCRIPT_DIR/setup-neo4j.sh" ]; then
    error "setup-neo4j.sh not found or not executable at: $SCRIPT_DIR/setup-neo4j.sh"
    exit 1
fi
if [ ! -x "$SCRIPT_DIR/start-test-env-db.sh" ]; then
    error "start-test-env-db.sh not found or not executable at: $SCRIPT_DIR/start-test-env-db.sh"
    exit 1
fi

# Prepare logs and initialize PIDs
NEO4J_LOG="/tmp/neo4j-setup-$(date +%Y%m%d-%H%M%S).log"
POSTGRES_LOG="/tmp/postgres-setup-$(date +%Y%m%d-%H%M%S).log"
CHROME_LOG="/tmp/chrome-setup-$(date +%Y%m%d-%H%M%S).log"

NEO4J_PID=""
POSTGRES_PID=""
CHROME_PID=""

# Dispatch Neo4j setup if not already running (idempotent check)
if validate_neo4j; then
    log "Neo4j already running and accessible; skipping setup-neo4j.sh"
else
    log "Dispatching Neo4j setup in background (log: $NEO4J_LOG)"
    nohup "$SCRIPT_DIR/setup-neo4j.sh" > "$NEO4J_LOG" 2>&1 &
    NEO4J_PID=$!
    log "Neo4j setup PID: $NEO4J_PID"
fi

# Wait for Neo4j to complete BEFORE dispatching Chrome (to avoid APT lock contention)
# Both Neo4j and Chrome may use apt-get, so they must be serialized.
PREP_MAX_WAIT=${PREP_MAX_WAIT:-120}  # seconds

NEO4J_STATUS=0
if [ -n "$NEO4J_PID" ]; then
    log "Waiting for Neo4j setup to complete (max ${PREP_MAX_WAIT}s) to avoid APT lock contention..."
    wait_for_task "Neo4j setup" "$NEO4J_PID" "$NEO4J_LOG" "$PREP_MAX_WAIT"
    NEO4J_STATUS=$?
    if [ $NEO4J_STATUS -eq 124 ]; then
        warn "Neo4j setup still running after ${PREP_MAX_WAIT}s timeout; continuing anyway (see $NEO4J_LOG)"
    elif [ $NEO4J_STATUS -ne 0 ]; then
        warn "Neo4j setup failed with exit code $NEO4J_STATUS (see $NEO4J_LOG); continuing with Chrome"
    fi
fi

# Dispatch Chrome download AFTER Neo4j completes (both may use apt, avoid lock contention)
if [ -x "/tmp/chrome-linux/chrome" ]; then
    log "Chrome binary already present at /tmp/chrome-linux/chrome; skipping download"
elif [ -x "$SCRIPT_DIR/ensure-chrome.sh" ]; then
    log "Dispatching Chrome downloader in background (log: $CHROME_LOG)"
    nohup "$SCRIPT_DIR/ensure-chrome.sh" > "$CHROME_LOG" 2>&1 &
    CHROME_PID=$!
    log "Chrome downloader PID: $CHROME_PID"
else
    warn "ensure-chrome.sh not found or not executable; skipping chrome download"
fi

# Dispatch PostgreSQL setup if not already accessible (idempotent check)
# PostgreSQL doesn't use apt (service is pre-installed), so it can run in parallel with Chrome
POSTGRES_STATUS=0
if postgres_app_db_ready; then
    log "PostgreSQL already has database 'ai_legal_war_machine' accessible as '$DB_USER'; skipping start-test-env-db.sh"
else
    log "Dispatching PostgreSQL setup (log: $POSTGRES_LOG)"
    "$SCRIPT_DIR/start-test-env-db.sh" > "$POSTGRES_LOG" 2>&1 &
    POSTGRES_PID=$!
    log "PostgreSQL setup PID: $POSTGRES_PID"

    # Wait for PostgreSQL setup to complete
    wait_for_task "PostgreSQL setup" "$POSTGRES_PID" "$POSTGRES_LOG" "$PREP_MAX_WAIT"
    POSTGRES_STATUS=$?
fi

# Wait for Chrome download if we started it
CHROME_STATUS=0
if [ -n "$CHROME_PID" ]; then
    wait_for_task "Chrome download" "$CHROME_PID" "$CHROME_LOG" "$PREP_MAX_WAIT"
    CHROME_STATUS=$?
    if [ $CHROME_STATUS -ne 0 ]; then
        warn "Chrome download failed or timed out (code $CHROME_STATUS). See $CHROME_LOG for details"
    fi
fi

# Check results but don't exit on failures (allow setup to continue)
if [ $POSTGRES_STATUS -ne 0 ]; then
    warn "PostgreSQL setup reported failure (code $POSTGRES_STATUS). See $POSTGRES_LOG for details"
fi

log "Service setup phase completed"

# Post-checks with retry for DB connectivity
log "Running DB connectivity probe with retry/backoff..."
if retry_with_backoff 4 db_connectivity_probe; then
    STATUS_DB="ok"
    STATUS_DB_TS=$(date -Iseconds)
    success "Database connectivity verified"
else
    STATUS_DB="fail"
    STATUS_DB_TS=$(date -Iseconds)
    error "Database connectivity probe failed after retries"
    SETUP_FAILED=1
fi

if ! validate_neo4j; then
    STATUS_NEO4J="fail"
    STATUS_NEO4J_TS=$(date -Iseconds)
    warn "Neo4j validation failed after preparatory phase (may still be starting)"
else
    STATUS_NEO4J="ok"
    STATUS_NEO4J_TS=$(date -Iseconds)
    success "Neo4j is ready"
fi

#######################################
# Step 2: Install Composer Dependencies
#######################################

step 2 "Installing Composer dependencies"

if command -v composer >/dev/null 2>&1; then
    COMPOSER_CMD=$(command -v composer)
elif [ -f "$PROJECT_DIR/composer.phar" ]; then
    COMPOSER_CMD="php $PROJECT_DIR/composer.phar"
elif [ -f "/usr/local/bin/composer" ]; then
    COMPOSER_CMD="/usr/local/bin/composer"
else
    error "Composer not found. Please install Composer or place composer.phar in the project folder."
    exit 1
fi

log "Using Composer at: $COMPOSER_CMD"

if $COMPOSER_CMD install --no-interaction --prefer-dist 2>&1 | tee -a "$LOG_FILE"; then
    success "Composer dependencies installed successfully"
    STATUS_COMPOSER="ok"
    STATUS_COMPOSER_TS=$(date -Iseconds)
else
    error "Composer install failed"
    STATUS_COMPOSER="fail"
    STATUS_COMPOSER_TS=$(date -Iseconds)
fi

if ! validate_composer_deps; then
    error "Composer dependencies validation failed after install"
    STATUS_COMPOSER="fail"
    STATUS_COMPOSER_TS=$(date -Iseconds)
fi

# Generate APP_KEY if not set
log "Checking application key..."
if grep -q "^APP_KEY=$" "$PROJECT_DIR/.env" 2>/dev/null || ! grep -q "^APP_KEY=base64:" "$PROJECT_DIR/.env" 2>/dev/null; then
    log "Generating Laravel application key..."
    php artisan key:generate --force 2>&1 | tee -a "$LOG_FILE"
    success "Application key generated"
else
    log "Application key already set"
fi

#######################################
# Step 2.5: Ensure pgvector extension in application databases
#######################################

step "2.5" "Ensuring pgvector extension in application databases"

# The vector extension must be created by a superuser BEFORE migrations run
# This avoids "permission denied to create extension 'vector'" errors
ensure_vector_extension() {
    local db_name="$1"
    log "Ensuring 'vector' extension in database '$db_name'..."

    if su - postgres -c "psql -d '$db_name' -c 'CREATE EXTENSION IF NOT EXISTS vector;'" 2>&1 | tee -a "$LOG_FILE"; then
        success "vector extension ensured in '$db_name'"
        return 0
    else
        warn "Could not create vector extension in '$db_name' (pgvector may not be installed)"
        return 1
    fi
}

# Install vector extension in main application database
if postgres_app_db_ready || validate_postgres; then
    ensure_vector_extension "ai_legal_war_machine" || true
    ensure_vector_extension "laravel_test" || true
else
    warn "PostgreSQL not ready; skipping pgvector extension setup"
fi

#######################################
# Step 3: Run Database Migrations
#######################################

step 3 "Running database migrations"

# Fix Laravel storage permissions before migrations
log "Fixing storage and cache permissions..."
chown -R claude:claude "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache" 2>/dev/null || true
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache" 2>/dev/null || true

# Fail fast if DB connectivity is broken
if [ "$STATUS_DB" = "fail" ]; then
    error "Skipping migrations - database connectivity failed"
    STATUS_MIGRATIONS="skip"
    STATUS_MIGRATIONS_TS=$(date -Iseconds)
else
    # Run migrations for main application database
    log "Running database migrations on main database (ai_legal_war_machine)..."
    if su - claude -c "cd $PROJECT_DIR && php artisan migrate --force" 2>&1 | tee -a "$LOG_FILE"; then
        success "Main database migrations completed"

        # Verify migration status
        if check_migration_status; then
            STATUS_MIGRATIONS="ok"
            STATUS_MIGRATIONS_TS=$(date -Iseconds)
        else
            warn "Migration status check found issues"
            STATUS_MIGRATIONS="fail"
            STATUS_MIGRATIONS_TS=$(date -Iseconds)
            SETUP_FAILED=1
        fi
    else
        error "Main database migrations failed"
        STATUS_MIGRATIONS="fail"
        STATUS_MIGRATIONS_TS=$(date -Iseconds)
        SETUP_FAILED=1
    fi

    # Run migrations for PHPUnit test database (laravel_test)
    log "Running test database migrations (laravel_test)..."
    if APP_ENV=testing php artisan migrate --force 2>&1 | tee -a "$LOG_FILE"; then
        success "Test database migrations completed"
    else
        warn "Test database migrations failed"
    fi
fi

#######################################
# Step 4: Import Database Data
#######################################

step 4 "Importing database data"

if [ "$STATUS_MIGRATIONS" = "fail" ] || [ "$STATUS_MIGRATIONS" = "skip" ]; then
    warn "Skipping data import - migrations not successful"
    STATUS_SEED="skip"
    STATUS_SEED_TS=$(date -Iseconds)
elif [ ! -x "$SCRIPT_DIR/import-db-data.sh" ]; then
    warn "import-db-data.sh not found or not executable, skipping data import"
    STATUS_SEED="skip"
    STATUS_SEED_TS=$(date -Iseconds)
else
    if [ -f "$DB_IMPORT_MARKER" ]; then
        log "Database import marker found at $DB_IMPORT_MARKER; skipping import-db-data.sh"
        log "Existing import log (from previous run, if present): $IMPORT_MARKER_LOG"
        # Verify existing seed data
        if verify_seed_data; then
            STATUS_SEED="ok"
            STATUS_SEED_TS=$(date -Iseconds)
        else
            STATUS_SEED="fail"
            STATUS_SEED_TS=$(date -Iseconds)
        fi
    else
        log "Dispatching database import in background..."

        # Use a deterministic marker file path so we can safely skip re-imports
        IMPORT_MARKER_LOG="${DB_IMPORT_MARKER}.log"

        if nohup "$SCRIPT_DIR/import-db-data.sh" > "$IMPORT_MARKER_LOG" 2>&1 & then
            IMPORT_PID=$!
            log "Database import started with PID: $IMPORT_PID"
            log "Import log file: $IMPORT_MARKER_LOG"
            success "Database import dispatched in background"

            # Record completion marker asynchronously when process exits
            (
                wait "$IMPORT_PID" 2>/dev/null || true
                date > "$DB_IMPORT_MARKER" 2>/dev/null || true
            ) &

            # For now, mark seed as pending (import is async)
            # Verify seed data availability
            if verify_seed_data; then
                STATUS_SEED="ok"
                STATUS_SEED_TS=$(date -Iseconds)
            else
                # Seed may still be loading in background
                STATUS_SEED="skip"
                STATUS_SEED_TS=$(date -Iseconds)
                log "Seed data not yet available (import running in background)"
            fi
        else
            warn "Failed to start database import in background"
            STATUS_SEED="fail"
            STATUS_SEED_TS=$(date -Iseconds)
        fi
    fi
fi

#######################################
# Step 6: Prepare Dusk ChromeDriver
#######################################

step 6 "Preparing Dusk ChromeDriver (download & start)"

# Run dusk:chrome-driver to download the appropriate driver if missing
if command -v php >/dev/null 2>&1; then
    if ! php artisan dusk:chrome-driver >/dev/null 2>&1; then
        warn "artisan dusk:chrome-driver returned non-zero (it may have already downloaded or failed)"
    else
        success "artisan dusk:chrome-driver ran (driver downloaded if needed)"
    fi
else
    warn "PHP not available to run 'php artisan dusk:chrome-driver'"
fi

# Locate chromedriver binary
CHROMEDRIVER_BIN="$(pwd)/vendor/laravel/dusk/bin/chromedriver"
if [ ! -x "$CHROMEDRIVER_BIN" ]; then
    # try alternative path
    CHROMEDRIVER_BIN="$(pwd)/vendor/bin/chromedriver"
fi

if [ -x "$CHROMEDRIVER_BIN" ]; then
    # Start chromedriver in background if not already running on 9515
    if pgrep -f "chromedriver.*9515" >/dev/null 2>&1; then
        log "Chromedriver appears to be running"
        STATUS_PLAYWRIGHT="ok"
        STATUS_PLAYWRIGHT_TS=$(date -Iseconds)
    else
        CHROMEDRIVER_LOG="/tmp/chromedriver-$(date +%Y%m%d-%H%M%S).log"
        log "Starting chromedriver in background (log: $CHROMEDRIVER_LOG)"
        nohup "$CHROMEDRIVER_BIN" --port=9515 > "$CHROMEDRIVER_LOG" 2>&1 &
        CHROMEDRIVER_PID=$!
        echo "$CHROMEDRIVER_PID" > /tmp/chromedriver.pid 2>/dev/null || true
        success "Chromedriver started with PID: $CHROMEDRIVER_PID"
        STATUS_PLAYWRIGHT="ok"
        STATUS_PLAYWRIGHT_TS=$(date -Iseconds)
    fi
else
    warn "Chromedriver binary not found; Dusk tests may start chromedriver via TestCase"
    STATUS_PLAYWRIGHT="skip"
    STATUS_PLAYWRIGHT_TS=$(date -Iseconds)
fi

#######################################
# Final Summary
#######################################

log "=========================================="
log "AI Legal War Machine - Setup Complete"
log "==========================================="
log "Log file: $LOG_FILE"
log "Project directory: $PROJECT_DIR"
log "Script finished at: $(date)"
log ""

# Show subsystem status with timestamps
log "Subsystem Status:"
log "----------------"
log "  db:         $STATUS_DB $([ -n "$STATUS_DB_TS" ] && echo "@ $STATUS_DB_TS")"
log "  neo4j:      $STATUS_NEO4J $([ -n "$STATUS_NEO4J_TS" ] && echo "@ $STATUS_NEO4J_TS")"
log "  composer:   $STATUS_COMPOSER $([ -n "$STATUS_COMPOSER_TS" ] && echo "@ $STATUS_COMPOSER_TS")"
log "  migrations: $STATUS_MIGRATIONS $([ -n "$STATUS_MIGRATIONS_TS" ] && echo "@ $STATUS_MIGRATIONS_TS")"
log "  seed:       $STATUS_SEED $([ -n "$STATUS_SEED_TS" ] && echo "@ $STATUS_SEED_TS")"
log "  playwright: $STATUS_PLAYWRIGHT $([ -n "$STATUS_PLAYWRIGHT_TS" ] && echo "@ $STATUS_PLAYWRIGHT_TS")"
log "----------------"

# Show relevant logs for quick access
log "Relevant logs:"
log "----------------"
log "Neo4j log: ${NEO4J_LOG:-"(not started in this session)"}"
log "PostgreSQL log: ${POSTGRES_LOG:-"(not started in this session)"}"
log "Chrome log: ${CHROME_LOG:-"(not started in this session)"}"
log "Import log: ${IMPORT_MARKER_LOG:-"(no import run in this session)"}"
log "Chromedriver log: ${CHROMEDRIVER_LOG:-"(chromedriver not started by setup-all.sh in this session)"}"
log "----------------"

# Reminder for user actions
log "Reminder: Please check the following:"
log "- Application key in .env (APP_KEY)"
log "- Database credentials in .env (DB_USERNAME, DB_PASSWORD)"
log "- Neo4j password in .env (NEO4J_PASSWORD)"
log "- OpenAI API key in .env (OPENAI_API_KEY)"
log "- Review logs for any warnings or errors"
log "----------------"

#######################################
# Persist Setup Status to JSON
#######################################
# Write structured status to test-results/setup-status.json
# This file is read by SessionStart hook for quick status display

SETUP_STATUS_FILE="${PROJECT_DIR}/test-results/setup-status.json"
mkdir -p "$(dirname "$SETUP_STATUS_FILE")"

# Determine overall status
OVERALL_STATUS="ok"
if [ $SETUP_FAILED -ne 0 ] || [ "$STATUS_DB" = "fail" ] || [ "$STATUS_MIGRATIONS" = "fail" ]; then
    OVERALL_STATUS="failed"
fi

# Determine exit code
FINAL_EXIT_CODE=0
if [ "$OVERALL_STATUS" = "failed" ]; then
    FINAL_EXIT_CODE=1
fi

# Write JSON status file
cat > "$SETUP_STATUS_FILE" <<STATUSEOF
{
  "timestamp": "$(date -Iseconds)",
  "overall": "$OVERALL_STATUS",
  "exit_code": $FINAL_EXIT_CODE,
  "log_file": "$LOG_FILE",
  "subsystems": {
    "db": {
      "status": "$STATUS_DB",
      "timestamp": "${STATUS_DB_TS:-null}"
    },
    "migrations": {
      "status": "$STATUS_MIGRATIONS",
      "timestamp": "${STATUS_MIGRATIONS_TS:-null}"
    },
    "seed": {
      "status": "$STATUS_SEED",
      "timestamp": "${STATUS_SEED_TS:-null}"
    },
    "composer": {
      "status": "$STATUS_COMPOSER",
      "timestamp": "${STATUS_COMPOSER_TS:-null}"
    },
    "neo4j": {
      "status": "$STATUS_NEO4J",
      "timestamp": "${STATUS_NEO4J_TS:-null}"
    },
    "playwright": {
      "status": "$STATUS_PLAYWRIGHT",
      "timestamp": "${STATUS_PLAYWRIGHT_TS:-null}"
    }
  }
}
STATUSEOF

log "Setup status written to: $SETUP_STATUS_FILE"

#######################################
# Emit Stable Summary Line (MUST be last)
#######################################
# Format: SETUP READY|FAILED (db=X, migrations=X, seed=X, composer=X, playwright=X, neo4j=X)
# This line is parseable by other tools - do not change format without updating consumers

SUMMARY_LINE=""
if [ $SETUP_FAILED -eq 0 ] && [ "$STATUS_DB" = "ok" ] && [ "$STATUS_MIGRATIONS" = "ok" ]; then
    SUMMARY_LINE="SETUP READY (db=$STATUS_DB, migrations=$STATUS_MIGRATIONS, seed=$STATUS_SEED, composer=$STATUS_COMPOSER, playwright=$STATUS_PLAYWRIGHT, neo4j=$STATUS_NEO4J)"
    success "$SUMMARY_LINE"
else
    SUMMARY_LINE="SETUP FAILED (db=$STATUS_DB, migrations=$STATUS_MIGRATIONS, seed=$STATUS_SEED, composer=$STATUS_COMPOSER, playwright=$STATUS_PLAYWRIGHT, neo4j=$STATUS_NEO4J)"
    error "$SUMMARY_LINE"
fi

# Exit with appropriate code
if [ $SETUP_FAILED -ne 0 ] || [ "$STATUS_DB" = "fail" ] || [ "$STATUS_MIGRATIONS" = "fail" ]; then
    exit 1
fi

exit 0
