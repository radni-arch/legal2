#!/bin/bash
set -euo pipefail

#######################################
# Ensure Chrome Binary for Laravel Dusk
#
# Responsibilities:
# - Ensure that /tmp/chrome-linux/chrome exists and is executable.
# - Prefer using an already installed Chrome/Chromium binary.
# - As a fallback, attempt to install Chromium via system package manager.
#
# This is designed to be idempotent and safe to run multiple times.
#######################################

TARGET_DIR="/tmp/chrome-linux"
TARGET_BIN="$TARGET_DIR/chrome"
LOG_FILE="${LOG_FILE:-/tmp/ensure-chrome-$(date +%Y%m%d-%H%M%S).log}"

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

find_existing_chrome() {
    # Try a list of common browser binaries
    local candidates=(
        google-chrome-stable
        google-chrome
        chromium-browser
        chromium
        chrome
        brave-browser
    )

    for bin in "${candidates[@]}"; do
        if command -v "$bin" >/dev/null 2>&1; then
            echo "$(command -v "$bin")"
            return 0
        fi
    done

    return 1
}

install_chromium_apt() {
    if ! command -v apt-get >/dev/null 2>&1; then
        return 1
    fi

    log "Attempting to install Chromium via apt-get"

    # Non-interactive apt install
    export DEBIAN_FRONTEND=noninteractive

    if ! apt-get update -y >/dev/null 2>&1; then
        warn "apt-get update failed"
        return 1
    fi

    # Try common package names
    if apt-get install -y chromium-browser >/dev/null 2>&1; then
        log "Installed chromium-browser via apt-get"
        return 0
    fi

    if apt-get install -y chromium >/dev/null 2>&1; then
        log "Installed chromium via apt-get"
        return 0
    fi

    warn "Failed to install Chromium via apt-get"
    return 1
}

install_chromium_dnf_yum() {
    if command -v dnf >/dev/null 2>&1; then
        log "Attempting to install Chromium via dnf"
        if dnf install -y chromium >/dev/null 2>&1; then
            log "Installed chromium via dnf"
            return 0
        fi
        warn "Failed to install chromium via dnf"
        return 1
    fi

    if command -v yum >/dev/null 2>&1; then
        log "Attempting to install Chromium via yum"
        if yum install -y chromium >/dev/null 2>&1; then
            log "Installed chromium via yum"
            return 0
        fi
        warn "Failed to install chromium via yum"
        return 1
    fi

    return 1
}

download_chromium_snapshot() {
    # Download Chromium snapshot directly from Google Cloud Storage
    # This works in container environments where apt/snap packages may not be available
    # Using a stable snapshot version (144.0.7532.0)
    local SNAPSHOT_VERSION="1546514"
    local CHROME_ZIP_URL="https://commondatastorage.googleapis.com/chromium-browser-snapshots/Linux_x64/${SNAPSHOT_VERSION}/chrome-linux.zip"
    local CHROME_ZIP="/tmp/chrome-linux.zip"

    log "Attempting to download Chromium snapshot from Google Cloud Storage"

    if ! command -v wget >/dev/null 2>&1 && ! command -v curl >/dev/null 2>&1; then
        warn "Neither wget nor curl available for download"
        return 1
    fi

    # Download the snapshot
    if command -v wget >/dev/null 2>&1; then
        log "Downloading Chromium snapshot using wget..."
        if ! wget -q --show-progress -O "$CHROME_ZIP" "$CHROME_ZIP_URL" 2>&1 | tee -a "$LOG_FILE"; then
            # Try without --show-progress for older wget versions
            if ! wget -q -O "$CHROME_ZIP" "$CHROME_ZIP_URL" 2>&1 | tee -a "$LOG_FILE"; then
                warn "wget download failed"
                return 1
            fi
        fi
    else
        log "Downloading Chromium snapshot using curl..."
        if ! curl -L -o "$CHROME_ZIP" "$CHROME_ZIP_URL" 2>&1 | tee -a "$LOG_FILE"; then
            warn "curl download failed"
            return 1
        fi
    fi

    # Verify download
    if [ ! -f "$CHROME_ZIP" ] || [ ! -s "$CHROME_ZIP" ]; then
        warn "Downloaded file is missing or empty"
        return 1
    fi

    log "Download complete, extracting..."

    # Extract to target directory
    if ! command -v unzip >/dev/null 2>&1; then
        warn "unzip not available for extraction"
        return 1
    fi

    # Remove any existing partial extraction
    rm -rf "$TARGET_DIR"

    # Extract (the zip contains chrome-linux/ directory)
    if ! unzip -q -o "$CHROME_ZIP" -d /tmp 2>&1 | tee -a "$LOG_FILE"; then
        warn "unzip extraction failed"
        return 1
    fi

    # Clean up zip file
    rm -f "$CHROME_ZIP"

    # Verify extraction
    if [ -x "$TARGET_BIN" ]; then
        success "Chromium snapshot extracted successfully to $TARGET_DIR"
        return 0
    else
        warn "Extraction completed but chrome binary not found or not executable"
        # Try to make it executable
        if [ -f "$TARGET_BIN" ]; then
            chmod +x "$TARGET_BIN"
            if [ -x "$TARGET_BIN" ]; then
                success "Made chrome binary executable at $TARGET_BIN"
                return 0
            fi
        fi
        return 1
    fi
}

ensure_target_symlink() {
    local source_bin="$1"

    mkdir -p "$TARGET_DIR"
    ln -sf "$source_bin" "$TARGET_BIN"
    chmod +x "$TARGET_BIN"

    success "Ensured Chrome binary at $TARGET_BIN (-> $source_bin)"
}

#######################################
# Main
#######################################

log "=========================================="
log "Ensure Chrome Binary for Laravel Dusk"
log "Target binary: $TARGET_BIN"
log "Log file: $LOG_FILE"
log "Script started at: $(date)"
log "=========================================="

# Fast path: already present
if [ -x "$TARGET_BIN" ]; then
    success "Chrome binary already present at $TARGET_BIN"
    exit 0
fi

log "Chrome binary not found at $TARGET_BIN; attempting to provide one"

# 1. Try to use an existing browser binary
EXISTING_CHROME="$(find_existing_chrome || true)"

if [ -n "${EXISTING_CHROME:-}" ]; then
    log "Found existing browser binary: $EXISTING_CHROME"
    ensure_target_symlink "$EXISTING_CHROME"
    exit 0
fi

log "No existing Chrome/Chromium binary found in PATH"

# 2. Try to install Chromium via apt (Debian/Ubuntu)
if install_chromium_apt; then
    EXISTING_CHROME="$(find_existing_chrome || true)"
    if [ -n "${EXISTING_CHROME:-}" ]; then
        ensure_target_symlink "$EXISTING_CHROME"
        exit 0
    fi
    warn "Chromium installation via apt succeeded but binary still not found in PATH"
fi

# 3. Try to install Chromium via dnf/yum (RHEL/Fedora)
if install_chromium_dnf_yum; then
    EXISTING_CHROME="$(find_existing_chrome || true)"
    if [ -n "${EXISTING_CHROME:-}" ]; then
        ensure_target_symlink "$EXISTING_CHROME"
        exit 0
    fi
    warn "Chromium installation via dnf/yum succeeded but binary still not found in PATH"
fi

# 4. Fallback: Download Chromium snapshot directly from Google Cloud Storage
# This is the most reliable method for container environments
log "Package manager installation failed; trying direct snapshot download"
if download_chromium_snapshot; then
    success "Chrome binary available at $TARGET_BIN via snapshot download"
    exit 0
fi

# If we reach here, we failed to set up Chrome
error "Unable to ensure Chrome/Chromium binary at $TARGET_BIN. Please install Chrome/Chromium manually or provide a binary in PATH."
exit 1
