#!/usr/bin/env bash
set -Eeuo pipefail

# ============================================================
# Laravel Dusk + Chrome in Docker - Automated Setup
# ============================================================
# What this script does:
# - Patches tests/DuskTestCase.php with Docker-safe Chrome flags
# - Downloads & starts ChromeDriver (default: 141.0.7383.0) on port 9515
# - Generates APP_KEY and syncs to .env and .env.dusk.local
# - Builds Vite assets (npm install + npm run build)
# - Creates/updates .env.dusk.local (safe defaults for Dusk tests)
# - Creates a quick verification test and optionally runs it
#
# Defaults can be overridden via env vars:
#   CHROMEDRIVER_VER=141.0.7383.0
#   CHROMEDRIVER_PORT=9515
#   APP_URL=http://localhost:8000
#   START_POSTGRES=0            # 1 to try to start local Postgres
#   POSTGRES_START_CMD=""       # Custom command to start Postgres
#   DB_HOST=127.0.0.1
#   DB_PORT=5432
#   DB_CONNECTION=pgsql
#   DB_DATABASE=laravel_test
#   DB_USERNAME=laravel
#   DB_PASSWORD=secret
#   RUN_TEST=0                  # 1 to run the ChromeStabilityTest after setup
#   SKIP_NPM=0                  # 1 to skip npm install/build
#   SKIP_PATCH=0                # 1 to skip patching DuskTestCase.php
#   CHROME_BINARY=""            # force a Chrome/Chromium binary path for Dusk
#
# Flags:
#   --run-test        Same as RUN_TEST=1
#   --skip-npm        Same as SKIP_NPM=1
#   --skip-patch      Same as SKIP_PATCH=1
#   --start-postgres  Same as START_POSTGRES=1
#   --driver-ver X    Set CHROMEDRIVER_VER to X
#   --port N          Set CHROMEDRIVER_PORT to N
#
# Notes:
# - This script assumes it runs inside your app container.
# - It is safe to re-run; it makes backups of DuskTestCase.php if patching.
# - If you use Laravel Sail's built-in Dusk support, you likely don't need this.
# ============================================================

# Defaults
CHROMEDRIVER_VER="${CHROMEDRIVER_VER:-141.0.7383.0}"
CHROMEDRIVER_FALLBACK_VER="${CHROMEDRIVER_FALLBACK_VER:-131.0.6778.204}"  # Stable fallback
CHROMEDRIVER_PORT="${CHROMEDRIVER_PORT:-9515}"
APP_URL="${APP_URL:-http://localhost:8000}"

START_POSTGRES="${START_POSTGRES:-0}"
POSTGRES_START_CMD="${POSTGRES_START_CMD:-}"

DB_CONNECTION="${DB_CONNECTION:-pgsql}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-laravel_test}"
DB_USERNAME="${DB_USERNAME:-laravel}"
DB_PASSWORD="${DB_PASSWORD:-secret}"

RUN_TEST="${RUN_TEST:-0}"
SKIP_NPM="${SKIP_NPM:-0}"
SKIP_PATCH="${SKIP_PATCH:-0}"
CHROME_BINARY="${CHROME_BINARY:-}"

# Candidate Chrome/Chromium binaries to try (Dusk will also try these)
CHROME_CANDIDATES=(
  "${CHROME_BINARY}"
  "/root/.cache/ms-playwright/chromium-1194/chrome-linux/chrome"
  "/opt/chrome/chrome"
  "/usr/bin/google-chrome"
  "/usr/bin/chromium"
  "/usr/bin/chromium-browser"
)

# Parse flags
while [[ $# -gt 0 ]]; do
  case "$1" in
    --run-test) RUN_TEST=1; shift ;;
    --skip-npm) SKIP_NPM=1; shift ;;
    --skip-patch) SKIP_PATCH=1; shift ;;
    --start-postgres) START_POSTGRES=1; shift ;;
    --driver-ver) CHROMEDRIVER_VER="$2"; shift 2 ;;
    --port) CHROMEDRIVER_PORT="$2"; shift 2 ;;
    *) echo "Unknown arg: $1"; exit 1 ;;
  esac
done

log()  { echo "[$(date +'%H:%M:%S')] $*" >&2; }
warn() { echo "[$(date +'%H:%M:%S')] WARN: $*" >&2; }
die()  { echo "[$(date +'%H:%M:%S')] ERROR: $*" >&2; exit 1; }

need() {
  command -v "$1" >/dev/null 2>&1 || die "Missing '$1'. Install it in your container."
}

cleanup() {
  # Do not kill chromedriver if we started it for ongoing use; only if we ran a one-off test.
  if [[ "${RUN_TEST}" == "1" && -n "${CHROMEDRIVER_PID:-}" ]]; then
    log "Stopping ChromeDriver (PID ${CHROMEDRIVER_PID})"
    kill "${CHROMEDRIVER_PID}" || true
  fi
}
trap cleanup EXIT

require_in_project_root() {
  [[ -f "composer.json" ]] || die "Run this from your Laravel project root (composer.json not found)."
  [[ -f "artisan" ]] || die "artisan not found in current directory."
}

detect_chrome_binary() {
  for p in "${CHROME_CANDIDATES[@]}"; do
    if [[ -n "$p" && -x "$p" ]]; then
      echo "$p"
      return 0
    fi
  done
  return 1
}

patch_dusk_testcase() {
  local dst="tests/DuskTestCase.php"
  if [[ "${SKIP_PATCH}" == "1" ]]; then
    log "Skipping DuskTestCase.php patch (per SKIP_PATCH=1)"
    return 0
  fi

  mkdir -p tests
  if [[ -f "${dst}" ]]; then
    if grep -q "DOCKER STABILITY FLAGS (FIXES CHROME CRASH)" "${dst}"; then
      log "DuskTestCase.php already patched. Skipping."
      return 0
    fi
    cp "${dst}" "${dst}.bak.$(date +%s)"
    log "Backed up existing ${dst} to ${dst}.bak.*"
  fi

  log "Writing Docker-friendly DuskTestCase.php"
  cat > "${dst}" <<'PHP'
<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\TestCase as BaseTestCase;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Prepare for Dusk test execution.
     */
    public static function prepare(): void
    {
        // ChromeDriver should be started manually on CHROMEDRIVER_PORT
        // if (! static::runningInSail()) {
        //     static::startChromeDriver();
        // }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            '--window-size=1920,1080',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--headless=new',
            // ===== DOCKER STABILITY FLAGS (FIXES CHROME CRASH) =====
            '--single-process',
            '--disable-setuid-sandbox',
            '--disable-namespace-sandbox',
            '--disable-features=VizDisplayCompositor',
            '--disable-features=IsolateOrigins,site-per-process',
            '--disable-blink-features=AutomationControlled',
            '--disable-web-security',
            '--allow-running-insecure-content',
            // ===== END DOCKER STABILITY FLAGS =====
        ])->all());

        // Allow overriding Chrome binary via env, else try common paths (Playwright/Chromium)
        $chromePaths = array_filter([
            env('CHROME_BINARY'),
            '/root/.cache/ms-playwright/chromium-1194/chrome-linux/chrome',
            '/opt/chrome/chrome',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ]);
        foreach ($chromePaths as $path) {
            if (is_string($path) && file_exists($path)) {
                $options->setBinary($path);
                break;
            }
        }

        $url = env('CHROMEDRIVER_URL', 'http://localhost:' . env('CHROMEDRIVER_PORT', '9515'));

        return RemoteWebDriver::create(
            $url,
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            )
        );
    }
}
PHP
}

install_chromedriver() {
  local ver="${CHROMEDRIVER_VER}"
  local url="https://storage.googleapis.com/chrome-for-testing-public/${ver}/linux64/chromedriver-linux64.zip"
  local dl="/tmp/chromedriver-${ver}.zip"
  local extract_dir="/tmp/chromedriver-extract-${ver}"
  local final_dir="/tmp/chromedriver-${ver}"
  local bin="${final_dir}/chromedriver"

  if [[ -x "${bin}" ]]; then
    log "ChromeDriver ${ver} already downloaded at ${bin}"
    echo "${bin}"
    return 0
  fi

  need "curl"
  need "unzip"

  # Clean up any previous failed attempts
  rm -rf "${extract_dir}" "${final_dir}" "${dl}"

  mkdir -p "${extract_dir}" "${final_dir}"

  log "Downloading ChromeDriver ${ver} from ${url}"
  if ! curl -fsSL -o "${dl}" "${url}"; then
    die "Failed to download ChromeDriver from ${url}"
  fi

  if [[ ! -f "${dl}" ]]; then
    die "ChromeDriver zip file not found at ${dl}"
  fi

  log "Unzipping ChromeDriver to ${extract_dir}"
  if ! unzip -q -o "${dl}" -d "${extract_dir}"; then
    die "Failed to unzip ChromeDriver"
  fi

  # Find the chromedriver binary (it's usually in chromedriver-linux64/ subdirectory)
  local found_binary=""
  for candidate in \
    "${extract_dir}/chromedriver-linux64/chromedriver" \
    "${extract_dir}/chromedriver" \
    $(find "${extract_dir}" -name "chromedriver" -type f 2>/dev/null | head -1)
  do
    if [[ -f "${candidate}" ]]; then
      found_binary="${candidate}"
      log "Found ChromeDriver binary at ${found_binary}"
      break
    fi
  done

  if [[ -z "${found_binary}" ]]; then
    log "ERROR: ChromeDriver binary not found after extraction. Contents of ${extract_dir}:"
    ls -laR "${extract_dir}" || true
    die "ChromeDriver binary not found in extracted files"
  fi

  # Move to final location
  mv "${found_binary}" "${bin}"
  chmod +x "${bin}"

  # Clean up extraction directory
  rm -rf "${extract_dir}" "${dl}"

  # Verify binary is executable
  if [[ ! -x "${bin}" ]]; then
    die "ChromeDriver binary exists but is not executable at ${bin}"
  fi

  log "ChromeDriver installed successfully at ${bin}"
  "${bin}" --version >&2 || warn "Could not verify ChromeDriver version"

  echo "${bin}"
}

start_chromedriver() {
  local bin="$1"
  local port="${CHROMEDRIVER_PORT}"

  # Verify the binary exists and is executable
  if [[ ! -x "${bin}" ]]; then
    die "ChromeDriver binary not executable at ${bin}"
  fi

  # Test that the binary can be executed
  if ! "${bin}" --version >/dev/null 2>&1; then
    die "ChromeDriver binary cannot be executed. It may be incompatible with this system."
  fi

  # Kill any existing chromedriver on the port
  if command -v lsof >/dev/null 2>&1; then
    if lsof -Pi :${port} -sTCP:LISTEN -t >/dev/null 2>&1; then
      warn "Port ${port} already in use; attempting to kill existing chromedriver"
      local pids=$(lsof -Pi :${port} -sTCP:LISTEN -t 2>/dev/null)
      if [[ -n "${pids}" ]]; then
        echo "${pids}" | xargs kill -9 2>/dev/null || true
      fi
      sleep 1
    fi
  else
    # Fallback: kill by process name
    pkill -9 -f "chromedriver.*port.*${port}" 2>/dev/null || true
    sleep 1
  fi

  log "Starting ChromeDriver on port ${port}"
  log "Command: ${bin} --port=${port}"
  log "Logs will be written to /tmp/chromedriver.log"

  # Start ChromeDriver in background
  "${bin}" --port="${port}" > /tmp/chromedriver.log 2>&1 &
  CHROMEDRIVER_PID=$!

  # Check if process started
  if ! kill -0 "${CHROMEDRIVER_PID}" 2>/dev/null; then
    log "ERROR: ChromeDriver process died immediately. Log contents:"
    cat /tmp/chromedriver.log || true
    die "Failed to start ChromeDriver"
  fi

  log "ChromeDriver process started (PID: ${CHROMEDRIVER_PID})"
  sleep 2

  # Verify ready with better error reporting
  local ok=0
  log "Waiting for ChromeDriver to become ready..."
  for i in {1..20}; do
    if curl -fsS "http://localhost:${port}/status" 2>/dev/null | grep -q '"ready":true'; then
      ok=1
      break
    fi

    # Check if process is still running
    if ! kill -0 "${CHROMEDRIVER_PID}" 2>/dev/null; then
      log "ERROR: ChromeDriver process died. Last 20 lines of log:"
      tail -20 /tmp/chromedriver.log || true
      die "ChromeDriver process terminated unexpectedly"
    fi

    sleep 0.5
  done

  if [[ "${ok}" == "1" ]]; then
    log "✓ ChromeDriver is ready and responding on port ${port}"
    return 0
  else
    warn "ChromeDriver process is running but not responding to status checks"
    log "Last 10 lines of /tmp/chromedriver.log:"
    tail -10 /tmp/chromedriver.log || true
    warn "ChromeDriver may not be fully operational. You can check /tmp/chromedriver.log for details."
    return 1
  fi
}

ensure_app_key() {
  need "php"

  local env_key=""
  if grep -qE '^APP_KEY=' .env 2>/dev/null; then
    env_key="$(grep -E '^APP_KEY=' .env | tail -n1 | cut -d= -f2- || true)"
  fi

  if [[ -n "${env_key}" && "${env_key}" != "base64:" && "${env_key}" != "SomeRandomString" ]]; then
    echo "${env_key}"
    return 0
  fi

  log "Generating a new APP_KEY"
  local key
  key="$(php artisan key:generate --show | tr -d '\r' | tr -d '\n')"
  if [[ -z "${key}" ]]; then
    die "Failed to generate APP_KEY"
  fi
  # Update .env
  if grep -qE '^APP_KEY=' .env 2>/dev/null; then
    sed -i "s|^APP_KEY=.*|APP_KEY=${key}|" .env
  else
    echo "APP_KEY=${key}" >> .env
  fi
  echo "${key}"
}

write_env_dusk_local() {
  local app_key="$1"

  log "Writing .env.dusk.local"
  cat > .env.dusk.local <<EOF
APP_NAME=Laravel
APP_ENV=testing
APP_KEY=${app_key}
APP_DEBUG=true
APP_URL=${APP_URL}

DB_CONNECTION=${DB_CONNECTION}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

CACHE_STORE=array
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
MAIL_MAILER=array

PULSE_ENABLED=false
TELESCOPE_ENABLED=false
NEO4J_ENABLED=false

# Dusk/ChromeDriver connection
CHROMEDRIVER_PORT=${CHROMEDRIVER_PORT}
# CHROMEDRIVER_URL=http://localhost:${CHROMEDRIVER_PORT}
# CHROME_BINARY=/path/to/your/chrome
EOF
}

build_vite_assets() {
  if [[ "${SKIP_NPM}" == "1" ]]; then
    log "Skipping npm install/build (SKIP_NPM=1)"
    return 0
  fi

  if ! command -v npm >/dev/null 2>&1; then
    warn "npm is not installed; skipping asset build"
    return 0
  fi

  log "Installing JS dependencies"
  if [[ -f package-lock.json ]]; then
    npm ci --no-audit --no-fund || npm install --no-audit --no-fund
  else
    npm install --no-audit --no-fund
  fi

  log "Building Vite assets"
  npm run build

  if [[ ! -f public/build/manifest.json ]]; then
    warn "public/build/manifest.json not found after build"
  else
    log "Vite manifest present"
  fi
}

create_verification_test() {
  local test_file="tests/Browser/ChromeStabilityTest.php"
  if [[ -f "${test_file}" ]]; then
    log "Verification test already exists (${test_file})"
    return 0
  fi

  log "Creating quick verification Dusk test (${test_file})"
  mkdir -p tests/Browser
  cat > "${test_file}" <<'PHP'
<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ChromeStabilityTest extends DuskTestCase
{
    public function test_chrome_does_not_crash(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->assertPathIs('/login')
                ->screenshot('chrome-working');
        });
    }
}
PHP
}

check_no_database_transactions() {
  if grep -R "use DatabaseTransactions" tests/Browser >/dev/null 2>&1; then
    warn "Detected 'use DatabaseTransactions' in tests/Browser. Remove it for Dusk tests or the browser won't see uncommitted data."
  fi
}

maybe_start_postgres() {
  if [[ "${START_POSTGRES}" != "1" ]]; then
    return 0
  fi

  if [[ -n "${POSTGRES_START_CMD}" ]]; then
    log "Starting PostgreSQL with custom command"
    bash -lc "${POSTGRES_START_CMD}" || true
  else
    # Try common approaches; may be environment-specific
    if command -v pg_ctlcluster >/dev/null 2>&1; then
      log "Starting PostgreSQL via pg_ctlcluster"
      pg_ctlcluster --force 16 main start || true
    elif command -v service >/dev/null 2>&1; then
      log "Starting PostgreSQL via service"
      service postgresql start || true
    fi
  fi
  sleep 2

  if command -v pg_isready >/dev/null 2>&1; then
    if pg_isready -h "${DB_HOST}" -p "${DB_PORT}"; then
      log "PostgreSQL ready"
    else
      warn "PostgreSQL did not report ready on ${DB_HOST}:${DB_PORT}"
    fi
  fi
}

verify_services() {
  if command -v pg_isready >/dev/null 2>&1; then
    pg_isready -h "${DB_HOST}" -p "${DB_PORT}" && echo " ✓ PostgreSQL reachable" || echo " ✗ PostgreSQL not reachable"
  fi

  if curl -fsS "http://localhost:${CHROMEDRIVER_PORT}/status" | grep -q '"ready":true'; then
    echo " ✓ ChromeDriver running"
  else
    echo " ✗ ChromeDriver not reporting ready"
  fi
}

run_dusk_test() {
  need "php"
  log "Running Dusk verification test"
  php artisan dusk tests/Browser/ChromeStabilityTest.php || die "Dusk test failed"
}

main() {
  require_in_project_root

  need "grep"
  need "sed"
  need "curl"
  need "php"

  log "=== Laravel Dusk Environment Setup ==="
  log "ChromeDriver version: ${CHROMEDRIVER_VER}"
  log "ChromeDriver port: ${CHROMEDRIVER_PORT}"
  log "App URL: ${APP_URL}"
  log ""

  # 1) Patch DuskTestCase with Docker flags and RemoteWebDriver
  patch_dusk_testcase

  # 2) Install & start ChromeDriver (with fallback)
  local chromedriver_bin
  local chromedriver_started=0

  log "Attempting to install ChromeDriver ${CHROMEDRIVER_VER}..."
  if chromedriver_bin="$(install_chromedriver)"; then
    log "Attempting to start ChromeDriver..."
    if start_chromedriver "${chromedriver_bin}"; then
      chromedriver_started=1
    else
      warn "Failed to start ChromeDriver ${CHROMEDRIVER_VER}"
    fi
  else
    warn "Failed to install ChromeDriver ${CHROMEDRIVER_VER}"
  fi

  # Try fallback version if primary failed
  if [[ "${chromedriver_started}" == "0" && "${CHROMEDRIVER_VER}" != "${CHROMEDRIVER_FALLBACK_VER}" ]]; then
    warn "Trying fallback ChromeDriver version ${CHROMEDRIVER_FALLBACK_VER}..."
    CHROMEDRIVER_VER="${CHROMEDRIVER_FALLBACK_VER}"
    if chromedriver_bin="$(install_chromedriver)"; then
      if start_chromedriver "${chromedriver_bin}"; then
        chromedriver_started=1
      fi
    fi
  fi

  if [[ "${chromedriver_started}" == "0" ]]; then
    die "Failed to start ChromeDriver. Check /tmp/chromedriver.log for details."
  fi

  # 3) Ensure APP_KEY and sync to dusk .env
  local app_key
  app_key="$(ensure_app_key)"
  if [[ -z "${app_key}" ]]; then
    die "APP_KEY not found or generated"
  fi

  # 4) Build Vite assets
  build_vite_assets

  # 5) Write .env.dusk.local
  write_env_dusk_local "${app_key}"

  # 6) Optionally start PostgreSQL
  maybe_start_postgres

  # 7) Sanity checks
  log ""
  log "=== Service Status ==="
  verify_services
  check_no_database_transactions

  # 8) Create quick verification test
  create_verification_test

  log ""
  log "=== Setup Complete ==="
  log "ChromeDriver: Running on port ${CHROMEDRIVER_PORT} (PID: ${CHROMEDRIVER_PID})"
  log "Chrome binary: $(detect_chrome_binary || echo 'Will be auto-detected by Dusk')"
  log "Environment: .env.dusk.local configured"
  log "Vite assets: $([ -f public/build/manifest.json ] && echo 'Built' || echo 'Not built')"
  log ""

  # 9) Optionally run the test
  if [[ "${RUN_TEST}" == "1" ]]; then
    run_dusk_test
  else
    log "To run a quick verification test:"
    echo "  php artisan dusk tests/Browser/ChromeStabilityTest.php"
    log ""
    log "To stop ChromeDriver:"
    echo "  kill ${CHROMEDRIVER_PID}"
  fi

  log "Done."
}

main
