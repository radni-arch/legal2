#!/bin/bash
set -euo pipefail

#######################################
# Neo4j Setup Script (Idempotent)
#
# Responsibilities:
# - Ensure Neo4j service is running locally.
# - Ensure the "neo4j" user password matches $NEO4J_PASSWORD (default: "password").
#
# This script is designed to be SAFE TO RUN MULTIPLE TIMES.
# On re-runs it will:
#   - Detect if Neo4j is already running and skip redundant starts.
#   - Detect if the configured password already works and skip resetting it.
#######################################

NEO4J_PASSWORD="${NEO4J_PASSWORD:-password}"
LOG_FILE="${LOG_FILE:-/tmp/setup-neo4j-$(date +%Y%m%d-%H%M%S).log}"

#######################################
# Logging
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

#######################################
# Helpers
#######################################

have_systemd() {
    command -v systemctl >/dev/null 2>&1 && [ -d /run/systemd/system ]
}

have_service() {
    command -v service >/dev/null 2>&1
}

find_neo4j_bin() {
    # Try to find a standalone neo4j binary (for tarball installs etc.)
    for path in /usr/bin/neo4j /usr/share/neo4j/bin/neo4j /opt/neo4j/bin/neo4j /usr/local/bin/neo4j; do
        if [ -x "$path" ]; then
            echo "$path"
            return 0
        fi
    done
    return 1
}

neo4j_running() {
    # Best-effort check: look for a Neo4j Java process
    if pgrep -f "org.neo4j.server.CommunityBootstrapper|org.neo4j.server.EnterpriseBootstrapper|neo4j\.server" >/dev/null 2>&1; then
        return 0
    fi

    # Fallback: check if bolt port is in use (7687)
    if command -v ss >/dev/null 2>&1; then
        ss -ltn 2>/dev/null | grep -q ":7687 "
    elif command -v netstat >/dev/null 2>&1; then
        netstat -ltn 2>/dev/null | grep -q ":7687 "
    else
        return 1
    fi
}

wait_for_neo4j() {
    local timeout="${1:-60}"  # seconds
    local waited=0
    local interval=3

    log "Waiting for Neo4j to accept connections on bolt://localhost:7687 (timeout: ${timeout}s)"

    while [ "$waited" -lt "$timeout" ]; do
        if command -v cypher-shell >/dev/null 2>&1; then
            # Try configured password first
            if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p "$NEO4J_PASSWORD" "RETURN 1;" >/dev/null 2>&1; then
                success "Neo4j is reachable via cypher-shell (configured password works)"
                return 0
            fi
            # Try default password (may require change, but server is up)
            local output
            output=$(cypher-shell -a "neo4j://localhost:7687" -u neo4j -p neo4j "RETURN 1;" 2>&1) || true
            if echo "$output" | grep -q "must be changed\|password change required\|credentials you provided were valid"; then
                success "Neo4j is reachable (password change required - will handle in ensure_password)"
                return 0
            fi
            # Try with -d system for Neo4j 5.x initial connect
            if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p neo4j -d system "RETURN 1;" >/dev/null 2>&1; then
                success "Neo4j is reachable via cypher-shell (default password, system db)"
                return 0
            fi
        else
            # No cypher-shell: fall back to port check
            if neo4j_running; then
                success "Neo4j appears to be running (port 7687 open / process detected)"
                return 0
            fi
        fi

        sleep "$interval"
        waited=$((waited + interval))
        log "Still waiting for Neo4j... (${waited}/${timeout}s)"
    done

    warn "Timed out waiting for Neo4j to become reachable"
    return 1
}

ensure_password() {
    if ! command -v cypher-shell >/dev/null 2>&1; then
        warn "cypher-shell not found; cannot verify or change Neo4j password"
        return 0
    fi

    log "Ensuring Neo4j password for user 'neo4j' matches configured NEO4J_PASSWORD"

    # 1. Check if the configured password already works (idempotent fast path)
    if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p "$NEO4J_PASSWORD" "RETURN 1;" >/dev/null 2>&1; then
        success "Configured password for 'neo4j' already works; no password change needed"
        return 0
    fi

    # 2. Check if default password works or requires change
    # IMPORTANT: Neo4j 5.x shows "credentials were valid but must be changed" WITHOUT -d system
    # So we first check without -d system to detect password change requirement
    local output
    local needs_change=false

    # Try without -d system first (this is where the password change message appears)
    output=$(cypher-shell -a "neo4j://localhost:7687" -u neo4j -p neo4j "RETURN 1;" 2>&1) || true

    # Check various indicators that default password is valid but needs changing
    if echo "$output" | grep -qi "must be changed\|password change required\|credentials you provided were valid"; then
        needs_change=true
        log "Default password 'neo4j' valid but requires change (detected via error message)"
    fi

    # Also try with -d system (Neo4j 5.x sometimes allows system db access with default creds)
    if [ "$needs_change" = false ]; then
        output=$(cypher-shell -a "neo4j://localhost:7687" -u neo4j -p neo4j -d system "RETURN 1;" 2>&1) || true
        if echo "$output" | grep -qi "must be changed\|password change required\|credentials you provided were valid"; then
            needs_change=true
            log "Default password 'neo4j' valid but requires change (detected via system db)"
        elif cypher-shell -a "neo4j://localhost:7687" -u neo4j -p neo4j -d system "RETURN 1;" >/dev/null 2>&1; then
            needs_change=true
            log "Default password 'neo4j' works; changing it to configured NEO4J_PASSWORD"
        fi
    fi

    if [ "$needs_change" = true ]; then
        # Neo4j 5.x syntax: ALTER CURRENT USER SET PASSWORD FROM 'old' TO 'new' (must use -d system)
        if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p neo4j -d system "ALTER CURRENT USER SET PASSWORD FROM 'neo4j' TO '$NEO4J_PASSWORD';" >/dev/null 2>&1; then
            success "Neo4j password changed using ALTER CURRENT USER SET PASSWORD FROM"
            # Verify the new password works
            if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p "$NEO4J_PASSWORD" "RETURN 1;" >/dev/null 2>&1; then
                success "New password verified successfully"
                return 0
            fi
            return 0
        fi

        # Neo4j 4+ syntax: ALTER CURRENT USER SET PASSWORD
        if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p neo4j "ALTER CURRENT USER SET PASSWORD '$NEO4J_PASSWORD';" >/dev/null 2>&1; then
            success "Neo4j password changed using ALTER CURRENT USER SET PASSWORD"
            return 0
        fi

        # Fallback for older Neo4j: CALL dbms.changePassword
        if cypher-shell -a "neo4j://localhost:7687" -u neo4j -p neo4j "CALL dbms.changePassword('$NEO4J_PASSWORD');" >/dev/null 2>&1; then
            success "Neo4j password changed using CALL dbms.changePassword"
            return 0
        fi

        warn "Failed to change Neo4j password even though default password worked"
        return 1
    fi

    # 3. Neither configured password nor default password works; we don't know the current password
    warn "Cannot verify or change Neo4j password (configured and default passwords both failed)"
    warn "Ensure that NEO4J_PASSWORD in your environment matches the actual admin password"
    return 1
}

is_neo4j_installed() {
    # Check if Neo4j is installed via any method
    # 1. Check for binary in common locations
    if find_neo4j_bin >/dev/null 2>&1; then
        return 0
    fi
    # 2. Check for systemd service
    if have_systemd && systemctl list-unit-files 2>/dev/null | grep -q '^neo4j\.service'; then
        return 0
    fi
    # 3. Check for service wrapper
    if have_service && service --status-all 2>&1 | grep -q 'neo4j'; then
        return 0
    fi
    return 1
}

cleanup_partial_downloads() {
    # Clean up any partial apt downloads that may have been left from interrupted installs
    local partial_dir="/var/cache/apt/archives/partial"
    if [ -d "$partial_dir" ]; then
        local partial_files
        partial_files=$(find "$partial_dir" -name "*.deb" -o -name "neo4j*" 2>/dev/null | wc -l)
        if [ "$partial_files" -gt 0 ]; then
            log "Cleaning up $partial_files partial download(s) from previous interrupted install..."
            rm -f "$partial_dir"/*.deb 2>/dev/null || true
            rm -f "$partial_dir"/neo4j* 2>/dev/null || true
        fi
    fi
}

wait_for_apt_lock() {
    # Wait for apt locks to be released (max 60 seconds)
    local timeout=60
    local waited=0
    local interval=2

    while [ "$waited" -lt "$timeout" ]; do
        if ! fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 \
           && ! fuser /var/lib/apt/lists/lock >/dev/null 2>&1 \
           && ! fuser /var/cache/apt/archives/lock >/dev/null 2>&1; then
            return 0
        fi
        log "Waiting for apt lock to be released... (${waited}s/${timeout}s)"
        sleep "$interval"
        waited=$((waited + interval))
    done

    warn "Timed out waiting for apt lock"
    return 1
}

install_neo4j() {
    log "Neo4j not installed. Attempting to install..."

    # Check for Java (required for Neo4j)
    if ! command -v java >/dev/null 2>&1; then
        error "Java is not installed. Neo4j requires Java 17 or 21."
        return 1
    fi

    # Ensure /tmp has correct permissions for apt
    chmod 1777 /tmp 2>/dev/null || true

    # Clean up any partial downloads from previous interrupted installs
    cleanup_partial_downloads

    # Wait for any existing apt operations to complete
    wait_for_apt_lock || true

    # Add Neo4j repository if not present
    if [ ! -f /etc/apt/sources.list.d/neo4j.list ]; then
        log "Adding Neo4j apt repository..."

        # Download and install GPG key (log errors instead of suppressing)
        log "Downloading Neo4j GPG key..."
        if ! curl -fsSL https://debian.neo4j.com/neotechnology.gpg.key | gpg --dearmor -o /usr/share/keyrings/neo4j-archive-keyring.gpg 2>&1 | tee -a "$LOG_FILE"; then
            error "Failed to download Neo4j GPG key"
            return 1
        fi

        # Add repository
        echo "deb [signed-by=/usr/share/keyrings/neo4j-archive-keyring.gpg] https://debian.neo4j.com stable 5" > /etc/apt/sources.list.d/neo4j.list
        log "Neo4j repository added to /etc/apt/sources.list.d/neo4j.list"
    fi

    # Always update apt cache (not just when adding repo) and show output for debugging
    log "Updating apt cache..."
    if ! apt-get update 2>&1 | tee -a "$LOG_FILE"; then
        warn "apt-get update returned non-zero, checking if Neo4j repo is accessible..."
    fi

    # Verify Neo4j package is available before attempting install
    log "Verifying Neo4j package availability..."
    if ! apt-cache show neo4j >/dev/null 2>&1; then
        error "Neo4j package not found in apt cache. Repository may not be properly configured."
        log "Attempting apt-get update again..."
        apt-get update 2>&1 | tee -a "$LOG_FILE"
        if ! apt-cache show neo4j >/dev/null 2>&1; then
            error "Neo4j package still not available after retry"
            return 1
        fi
    fi
    log "Neo4j package found in apt cache"

    # Install Neo4j with retry logic for interrupted downloads
    local max_retries=3
    local retry=0

    while [ "$retry" -lt "$max_retries" ]; do
        retry=$((retry + 1))
        log "Installing Neo4j package (attempt $retry/$max_retries)..."

        # Clean partial downloads before each attempt
        cleanup_partial_downloads

        if DEBIAN_FRONTEND=noninteractive apt-get install -y neo4j 2>&1 | tee -a "$LOG_FILE"; then
            success "Neo4j installed successfully"
            return 0
        fi

        # Check if neo4j was actually installed despite apt errors
        if command -v neo4j >/dev/null 2>&1 || [ -x /usr/bin/neo4j ]; then
            warn "apt-get returned error but Neo4j binary exists - installation likely succeeded"
            success "Neo4j installed (with warnings)"
            return 0
        fi

        if [ "$retry" -lt "$max_retries" ]; then
            warn "Install attempt $retry failed, cleaning up and retrying in 5 seconds..."
            # Fix any broken packages before retry
            dpkg --configure -a 2>/dev/null || true
            apt-get -f install -y 2>/dev/null || true
            sleep 5
        fi
    done

    error "Failed to install Neo4j after $max_retries attempts"
    return 1
}

start_neo4j() {
    if neo4j_running; then
        log "Neo4j already appears to be running; skipping start"
        return 0
    fi

    # First check if Neo4j is installed at all
    if ! is_neo4j_installed; then
        log "Neo4j is not installed on this system"
        # Attempt to install Neo4j
        if ! install_neo4j; then
            warn "Failed to install Neo4j - graph database functionality will not be available"
            return 1
        fi
    fi

    log "Attempting to start Neo4j..."

    # Prefer systemd if available
    if have_systemd && systemctl list-unit-files 2>/dev/null | grep -q '^neo4j\.service'; then
        if systemctl start neo4j >/dev/null 2>&1; then
            log "Started Neo4j via systemctl"
        else
            warn "systemctl start neo4j failed"
        fi
    # Fall back to legacy service wrapper
    elif have_service && service --status-all 2>/dev/null | grep -q 'neo4j'; then
        if service neo4j start >/dev/null 2>&1; then
            log "Started Neo4j via service"
        else
            warn "service neo4j start failed"
        fi
    else
        # Direct binary start as a last resort
        local neo4j_bin
        neo4j_bin="$(find_neo4j_bin || true)"
        if [ -n "$neo4j_bin" ]; then
            if "$neo4j_bin" start >/dev/null 2>&1; then
                log "Started Neo4j via binary: $neo4j_bin"
            else
                warn "Failed to start Neo4j via binary: $neo4j_bin"
            fi
        else
            error "Neo4j binary or service not found; cannot start Neo4j automatically"
            return 1
        fi
    fi

    # After attempting start, wait for it to come up
    if wait_for_neo4j 120; then
        success "Neo4j started and is reachable"
        return 0
    fi

    warn "Neo4j did not become reachable after start attempt"
    return 1
}

#######################################
# Main
#######################################

log "=========================================="
log "Neo4j Setup Script (idempotent)"
log "Log file: $LOG_FILE"
log "NEO4J_PASSWORD: [hidden]"
log "Script started at: $(date)"
log "=========================================="

# 1. Ensure Neo4j is running
if ! start_neo4j; then
    error "Failed to ensure Neo4j is running"
    exit 1
fi

# 2. Ensure the 'neo4j' password is set to NEO4J_PASSWORD (if possible)
if ! ensure_password; then
    warn "Neo4j password could not be verified/changed; continuing, but app may not be able to connect"
else
    success "Neo4j password verified/updated successfully"
fi

log "Neo4j setup script finished at: $(date)"
success "Neo4j is ready for use."
