#!/bin/bash

################################################################################
# Production Deployment Script
# AI Legal War Machine
################################################################################
#
# This script automates the production deployment process.
#
# Usage:
#   ./scripts/deploy.sh [OPTIONS]
#
# Options:
#   --skip-tests          Skip running tests before deployment
#   --skip-migrations     Skip database migrations
#   --skip-npm            Skip NPM build
#   --force               Force deployment without confirmation
#   --branch=BRANCH       Deploy specific branch (default: main)
#   --help                Show this help message
#
# Prerequisites:
#   - Run as deploy user
#   - SSH access to production server
#   - Git repository configured
#   - All services running
#
################################################################################

set -e  # Exit on error

# ============================================
# Configuration
# ============================================

APP_DIR="/var/www/ai-legal-war-machine"
APP_USER="deploy"
WEB_USER="www-data"
BRANCH="${DEPLOY_BRANCH:-main}"
PHP_VERSION="8.2"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_FILE="${APP_DIR}/storage/logs/deploy-${TIMESTAMP}.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Flags
SKIP_TESTS=false
SKIP_MIGRATIONS=false
SKIP_NPM=false
FORCE=false

# ============================================
# Functions
# ============================================

log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] ✓${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ✗${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] !${NC} $1" | tee -a "$LOG_FILE"
}

confirm() {
    if [ "$FORCE" = true ]; then
        return 0
    fi

    echo -en "${YELLOW}$1 (y/N): ${NC}"
    read -r response
    case "$response" in
        [yY][eE][sS]|[yY])
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

show_help() {
    cat << EOF
Production Deployment Script - AI Legal War Machine

Usage:
  ./scripts/deploy.sh [OPTIONS]

Options:
  --skip-tests          Skip running tests before deployment
  --skip-migrations     Skip database migrations
  --skip-npm            Skip NPM build
  --force               Force deployment without confirmation
  --branch=BRANCH       Deploy specific branch (default: main)
  --help                Show this help message

Examples:
  # Standard deployment
  ./scripts/deploy.sh

  # Quick deployment (skip tests and npm)
  ./scripts/deploy.sh --skip-tests --skip-npm

  # Deploy specific branch
  ./scripts/deploy.sh --branch=hotfix/urgent-fix --force

EOF
    exit 0
}

check_prerequisites() {
    log "Checking prerequisites..."

    # Check if running as correct user
    if [ "$(whoami)" != "$APP_USER" ]; then
        error "Must run as $APP_USER user"
    fi

    # Check if in app directory
    if [ ! -d "$APP_DIR" ]; then
        error "Application directory not found: $APP_DIR"
    fi

    # Check if Git repository
    if [ ! -d "$APP_DIR/.git" ]; then
        error "Not a Git repository: $APP_DIR"
    fi

    # Check required commands
    for cmd in php composer npm git sudo; do
        if ! command -v $cmd &> /dev/null; then
            error "Required command not found: $cmd"
        fi
    done

    success "Prerequisites check passed"
}

enable_maintenance_mode() {
    log "Enabling maintenance mode..."

    cd "$APP_DIR"

    if php artisan down --render="errors::503" --retry=60; then
        success "Maintenance mode enabled"
    else
        error "Failed to enable maintenance mode"
    fi
}

disable_maintenance_mode() {
    log "Disabling maintenance mode..."

    cd "$APP_DIR"

    if php artisan up; then
        success "Maintenance mode disabled"
    else
        warning "Failed to disable maintenance mode"
    fi
}

backup_current_release() {
    log "Backing up current release..."

    BACKUP_DIR="/var/backups/ai-legal-war-machine/releases"
    mkdir -p "$BACKUP_DIR"

    # Get current commit hash
    cd "$APP_DIR"
    CURRENT_COMMIT=$(git rev-parse --short HEAD)

    # Create backup of .env and composer.lock
    tar czf "${BACKUP_DIR}/release_${TIMESTAMP}_${CURRENT_COMMIT}.tar.gz" \
        .env composer.lock package-lock.json 2>/dev/null || true

    success "Backup created: release_${TIMESTAMP}_${CURRENT_COMMIT}.tar.gz"
}

fetch_latest_code() {
    log "Fetching latest code from Git..."

    cd "$APP_DIR"

    # Fetch latest changes
    if ! git fetch origin "$BRANCH"; then
        error "Failed to fetch from origin"
    fi

    # Show what will be updated
    CURRENT_COMMIT=$(git rev-parse HEAD)
    TARGET_COMMIT=$(git rev-parse "origin/$BRANCH")

    if [ "$CURRENT_COMMIT" = "$TARGET_COMMIT" ]; then
        warning "Already up to date ($(git rev-parse --short HEAD))"
        if ! confirm "Continue deployment anyway?"; then
            error "Deployment cancelled"
        fi
    else
        log "Will update from $(git rev-parse --short HEAD) to $(git rev-parse --short origin/$BRANCH)"
        git log --oneline HEAD..origin/$BRANCH | head -10

        if ! confirm "Continue with deployment?"; then
            error "Deployment cancelled"
        fi
    fi

    # Checkout latest code
    if ! git checkout "$BRANCH"; then
        error "Failed to checkout branch: $BRANCH"
    fi

    if ! git reset --hard "origin/$BRANCH"; then
        error "Failed to reset to origin/$BRANCH"
    fi

    success "Code updated to $(git rev-parse --short HEAD)"
}

install_composer_dependencies() {
    log "Installing Composer dependencies..."

    cd "$APP_DIR"

    if composer install --no-dev --optimize-autoloader --no-interaction; then
        success "Composer dependencies installed"
    else
        error "Failed to install Composer dependencies"
    fi
}

build_frontend_assets() {
    if [ "$SKIP_NPM" = true ]; then
        warning "Skipping NPM build (--skip-npm flag)"
        return 0
    fi

    log "Building frontend assets..."

    cd "$APP_DIR"

    # Install NPM dependencies
    if ! npm ci --no-audit; then
        error "Failed to install NPM dependencies"
    fi

    # Build assets
    if ! npm run build; then
        error "Failed to build frontend assets"
    fi

    success "Frontend assets built"
}

run_database_migrations() {
    if [ "$SKIP_MIGRATIONS" = true ]; then
        warning "Skipping database migrations (--skip-migrations flag)"
        return 0
    fi

    log "Running database migrations..."

    cd "$APP_DIR"

    # Check for pending migrations
    if php artisan migrate:status | grep -q "Pending"; then
        warning "Pending migrations detected"
        if ! confirm "Run migrations now?"; then
            error "Deployment cancelled - migrations required"
        fi

        if ! php artisan migrate --force; then
            error "Database migrations failed"
        fi

        success "Database migrations completed"
    else
        success "No pending migrations"
    fi
}

optimize_application() {
    log "Optimizing application..."

    cd "$APP_DIR"

    # Clear all caches
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear

    # Rebuild caches
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # Optimize autoloader
    composer dump-autoload --optimize --no-dev

    success "Application optimized"
}

restart_services() {
    log "Restarting services..."

    # Restart PHP-FPM
    if sudo systemctl restart "php${PHP_VERSION}-fpm"; then
        success "PHP-FPM restarted"
    else
        error "Failed to restart PHP-FPM"
    fi

    # Restart queue workers
    if sudo supervisorctl restart ai-legal-queue-worker:*; then
        success "Queue workers restarted"
    else
        warning "Failed to restart queue workers"
    fi

    # Reload Nginx
    if sudo systemctl reload nginx; then
        success "Nginx reloaded"
    else
        warning "Failed to reload Nginx"
    fi
}

run_tests() {
    if [ "$SKIP_TESTS" = true ]; then
        warning "Skipping tests (--skip-tests flag)"
        return 0
    fi

    log "Running tests..."

    cd "$APP_DIR"

    if ./vendor/bin/phpunit --testsuite=Unit --stop-on-failure; then
        success "Tests passed"
    else
        error "Tests failed - deployment aborted"
    fi
}

verify_deployment() {
    log "Verifying deployment..."

    cd "$APP_DIR"

    # Check health endpoint
    HEALTH_URL="http://localhost/api/health"

    sleep 2  # Wait for services to fully restart

    if curl -sf "$HEALTH_URL" > /dev/null; then
        HEALTH_STATUS=$(curl -s "$HEALTH_URL" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)

        if [ "$HEALTH_STATUS" = "healthy" ]; then
            success "Health check passed: $HEALTH_STATUS"
        else
            error "Health check failed: $HEALTH_STATUS"
        fi
    else
        error "Health endpoint not responding"
    fi

    # Check queue workers
    if sudo supervisorctl status ai-legal-queue-worker:* | grep -q "RUNNING"; then
        success "Queue workers running"
    else
        error "Queue workers not running"
    fi

    # Check for errors in logs (last 100 lines)
    ERROR_COUNT=$(tail -100 storage/logs/laravel.log | grep -c "ERROR" || true)
    if [ "$ERROR_COUNT" -gt 5 ]; then
        warning "Found $ERROR_COUNT errors in recent logs"
    else
        success "Log check passed ($ERROR_COUNT errors)"
    fi
}

cleanup() {
    log "Cleaning up..."

    cd "$APP_DIR"

    # Remove old release backups (keep last 10)
    BACKUP_DIR="/var/backups/ai-legal-war-machine/releases"
    if [ -d "$BACKUP_DIR" ]; then
        ls -t "$BACKUP_DIR"/release_*.tar.gz | tail -n +11 | xargs rm -f 2>/dev/null || true
    fi

    # Remove old log files (keep last 30 days)
    find storage/logs -name "*.log.*" -mtime +30 -delete 2>/dev/null || true

    success "Cleanup completed"
}

show_deployment_summary() {
    echo ""
    echo "============================================"
    echo "  Deployment Summary"
    echo "============================================"
    echo "  Timestamp: $TIMESTAMP"
    echo "  Branch: $BRANCH"
    echo "  Commit: $(cd "$APP_DIR" && git rev-parse --short HEAD)"
    echo "  Log: $LOG_FILE"
    echo "============================================"
    echo ""
}

rollback() {
    error "Deployment failed - please check logs: $LOG_FILE"
}

# ============================================
# Parse Arguments
# ============================================

for arg in "$@"; do
    case $arg in
        --skip-tests)
            SKIP_TESTS=true
            shift
            ;;
        --skip-migrations)
            SKIP_MIGRATIONS=true
            shift
            ;;
        --skip-npm)
            SKIP_NPM=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --branch=*)
            BRANCH="${arg#*=}"
            shift
            ;;
        --help)
            show_help
            ;;
        *)
            error "Unknown option: $arg (use --help for usage)"
            ;;
    esac
done

# ============================================
# Main Deployment Process
# ============================================

trap rollback ERR

echo ""
echo "============================================"
echo "  AI Legal War Machine"
echo "  Production Deployment"
echo "============================================"
echo ""

# Create log directory if it doesn't exist
mkdir -p "${APP_DIR}/storage/logs"

log "Starting deployment..."
log "Target branch: $BRANCH"

# Step 1: Prerequisites
check_prerequisites

# Step 2: Backup
backup_current_release

# Step 3: Maintenance mode
enable_maintenance_mode

# Step 4: Fetch code
fetch_latest_code

# Step 5: Install dependencies
install_composer_dependencies

# Step 6: Build assets
build_frontend_assets

# Step 7: Run migrations
run_database_migrations

# Step 8: Optimize
optimize_application

# Step 9: Restart services
restart_services

# Step 10: Disable maintenance mode
disable_maintenance_mode

# Step 11: Verify
verify_deployment

# Step 12: Tests (post-deployment)
run_tests

# Step 13: Cleanup
cleanup

# Done!
success "Deployment completed successfully!"
show_deployment_summary

exit 0
