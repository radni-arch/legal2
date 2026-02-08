# Laravel Dusk + Chrome Setup Cheatsheet

## Quick Reference: Exact Commands to Get Dusk Running in Cloud

This cheatsheet contains the **exact commands** used to successfully set up and run Laravel Dusk with Chrome in a cloud environment.

---

## 📋 Prerequisites Checklist

- [ ] PostgreSQL installed and running
- [ ] Laravel application installed
- [ ] Composer available
- [ ] Root/sudo access (for installations)

---

## 🚀 Complete Setup (Step-by-Step)

### Step 1: Install Laravel Dusk

```bash
# Install Dusk via Composer
composer require --dev laravel/dusk

# Install Dusk in the application
php artisan dusk:install
```

**Expected Output**:
- `tests/DuskTestCase.php` created
- `tests/Browser/` directory created
- `tests/Browser/ExampleTest.php` created

---

### Step 2: Download and Install Chrome for Testing

```bash
# Download Chrome for Testing v131 (165.9 MB)
wget https://storage.googleapis.com/chrome-for-testing-public/131.0.6778.204/linux64/chrome-linux64.zip

# Extract Chrome
unzip chrome-linux64.zip -d /tmp/

# Verify installation
/tmp/chrome-linux64/chrome --version
# Expected: Google Chrome for Testing 131.0.6778.204
```

**Critical**: Use Chrome for Testing, not regular Chrome! It's designed for automation.

---

### Step 3: Download and Install ChromeDriver

```bash
# Download ChromeDriver v131 (9.9 MB) - MUST match Chrome version
wget https://storage.googleapis.com/chrome-for-testing-public/131.0.6778.204/linux64/chromedriver-linux64.zip

# Extract ChromeDriver
unzip chromedriver-linux64.zip

# Move to Dusk's expected location
cp chromedriver-linux64/chromedriver vendor/laravel/dusk/bin/chromedriver-linux

# Make executable
chmod +x vendor/laravel/dusk/bin/chromedriver-linux

# Verify installation
vendor/laravel/dusk/bin/chromedriver-linux --version
# Expected: ChromeDriver 131.0.6778.204
```

**Critical**: Chrome and ChromeDriver versions MUST match exactly!

---

### Step 4: Configure DuskTestCase for Cloud Chrome

Edit `tests/DuskTestCase.php`:

```php
<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver();
        }
    }

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--no-sandbox',                    // REQUIRED for cloud
            '--disable-dev-shm-usage',         // REQUIRED for cloud
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        // Set Chrome binary path - CRITICAL!
        $chromePaths = [
            '/tmp/chrome-linux64/chrome',      // Check this FIRST
            '/opt/chrome/chrome',
            '/usr/local/bin/chrome-for-testing/chrome',
        ];

        foreach ($chromePaths as $path) {
            if (file_exists($path)) {
                $options->setBinary($path);
                break;
            }
        }

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    protected function hasHeadlessDisabled(): bool
    {
        return isset($_SERVER['DUSK_HEADLESS_DISABLED']) ||
            isset($_ENV['DUSK_HEADLESS_DISABLED']);
    }

    protected function shouldStartMaximized(): bool
    {
        return isset($_SERVER['DUSK_START_MAXIMIZED']) ||
            isset($_ENV['DUSK_START_MAXIMIZED']);
    }
}
```

**Critical Flags**:
- `--no-sandbox`: Required for running as root in cloud
- `--disable-dev-shm-usage`: Prevents /dev/shm issues in containers

---

### Step 5: Create .env.dusk.local Configuration

```bash
# Create .env.dusk.local with complete configuration
cat > .env.dusk.local << 'EOF'
APP_URL=http://localhost:8000
APP_ENV=testing
APP_DEBUG=true
APP_KEY=base64:BIeDsn1SrKKanYNSNL20kE3VgvfEj7PFvWQsAxLVxSo=

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=advokat_dev
DB_USERNAME=postgres
DB_PASSWORD=

CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync

# Neo4j - disable for browser tests
NEO4J_ENABLED=false
EOF
```

**Or copy from .env and modify**:
```bash
cp .env .env.dusk.local
# Then manually add/modify specific values
```

**Critical Variables**:
- `APP_KEY` - MUST be set (encryption key)
- `APP_URL` - Must match server URL (http://localhost:8000)
- `NEO4J_ENABLED=false` - Disable Neo4j for testing
- `CACHE_STORE=array` - Use array cache for testing
- `SESSION_DRIVER=array` - Use array sessions for testing

---

### Step 6: Fix Error View Bug

**Bug**: Error views crash with "Request::id() does not exist"

Edit `resources/views/errors/500.blade.php` (around line 36):

```blade
<!-- BEFORE (BROKEN): -->
@if(request()->id())
    <code>{{ request()->id() }}</code>
@endif

<!-- AFTER (FIXED): -->
@if(method_exists(request(), 'id') && request()->id())
    <code>{{ request()->id() }}</code>
@endif
```

---

### Step 7: Fix /tmp Permissions (if needed)

```bash
# Fix /tmp permissions if Chrome reports permission denied
chmod 777 /tmp
```

---

### Step 8: Start Services

```bash
# 1. Start PostgreSQL
service postgresql start

# Verify PostgreSQL is running
service postgresql status
# Expected: 16/main (port 5432): online

# 2. Start ChromeDriver
vendor/laravel/dusk/bin/chromedriver-linux --port=9515 > /tmp/chromedriver.log 2>&1 &

# Verify ChromeDriver is running
ps aux | grep chromedriver | grep -v grep
# Expected: chromedriver-linux --port=9515

# Check logs
tail /tmp/chromedriver.log
# Expected: ChromeDriver was started successfully on port 9515.

# 3. Start Laravel Server
php artisan serve --host=127.0.0.1 --port=8000 > /tmp/laravel-server.log 2>&1 &

# Verify server is running
sleep 3 && curl -I http://localhost:8000
# Expected: HTTP/1.1 200 OK or HTTP/1.1 302 Found
```

---

### Step 9: Clear Caches (Important!)

```bash
# Clear all caches before testing
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

**Why**: Cached configs can use old .env values, cached views can use broken code.

---

### Step 10: Run Tests

```bash
# Run specific test file
php artisan dusk tests/Browser/CloudExecutionProofTest.php

# Run all Dusk tests
php artisan dusk

# Run with filter
php artisan dusk --filter=test_chrome_actually_works

# Run specific test group
php artisan dusk --group=proof
```

**Expected Output (Success)**:
```
✓ chrome actually works in cloud (2.49s)
✓ chrome can take screenshots (0.22s)

Tests: 2 passed (9 assertions)
```

---

## 🔧 Troubleshooting Commands

### Check Chrome Version
```bash
/tmp/chrome-linux64/chrome --version
```

### Check ChromeDriver Version
```bash
vendor/laravel/dusk/bin/chromedriver-linux --version
```

### Test Chrome Headless Rendering
```bash
/tmp/chrome-linux64/chrome --headless --no-sandbox --dump-dom file:///tmp/test.html
```

### Check ChromeDriver Logs
```bash
tail -f /tmp/chromedriver.log
```

### Check Laravel Server Logs
```bash
tail -f /tmp/laravel-server.log
```

### Check Laravel Application Logs
```bash
tail -f storage/logs/laravel.log
```

### View Latest Screenshot
```bash
ls -lht tests/Browser/screenshots/ | head -5
```

### Kill All Processes and Restart
```bash
# Kill everything
pkill -9 -f "chromedriver"
pkill -9 -f "php artisan serve"

# Restart services
service postgresql start
vendor/laravel/dusk/bin/chromedriver-linux --port=9515 > /tmp/chromedriver.log 2>&1 &
php artisan serve > /tmp/laravel-server.log 2>&1 &

# Wait a moment
sleep 3

# Verify
ps aux | grep -E "chromedriver|php artisan serve" | grep -v grep
```

---

## 🐛 Common Errors and Fixes

### Error: "ChromeDriver only supports Chrome version 131"

**Problem**: Chrome and ChromeDriver version mismatch

**Fix**:
```bash
# Check versions
/tmp/chrome-linux64/chrome --version
vendor/laravel/dusk/bin/chromedriver-linux --version

# If they don't match, download matching versions
# Both must be 131.0.6778.204
```

---

### Error: "No application encryption key has been specified"

**Problem**: APP_KEY missing in .env.dusk.local

**Fix**:
```bash
# Get APP_KEY from .env
grep "^APP_KEY" .env

# Add to .env.dusk.local
echo "APP_KEY=base64:YourKeyHere" >> .env.dusk.local

# Or regenerate
php artisan key:generate
```

---

### Error: "NEO4J_PASSWORD must be set when Neo4j is enabled"

**Problem**: Neo4j enabled but not configured for testing

**Fix**:
```bash
# Disable Neo4j for tests
echo "NEO4J_ENABLED=false" >> .env.dusk.local

# Clear config cache
php artisan config:clear
```

---

### Error: "Method Illuminate\Http\Request::id does not exist"

**Problem**: Error view has bug (recursive error)

**Fix**: See Step 6 above - add method_exists() check in 500.blade.php

---

### Error: "net::ERR_CONNECTION_REFUSED"

**Problem**: Laravel server not running or wrong URL

**Fix**:
```bash
# Check if server is running
ps aux | grep "php artisan serve" | grep -v grep

# Check APP_URL in .env.dusk.local
grep APP_URL .env.dusk.local
# Should be: APP_URL=http://localhost:8000

# Restart server
pkill -f "php artisan serve"
php artisan serve > /tmp/laravel-server.log 2>&1 &
```

---

### Error: "Vite manifest not found"

**Problem**: Frontend assets not built

**Fix Option 1** (Build assets):
```bash
npm install
npm run build
```

**Fix Option 2** (Skip for backend testing):
```bash
# Test routes that don't need views
php artisan dusk tests/Browser/CloudExecutionProofTest.php
```

---

### Error: Screenshots show 500 error page

**Problem**: Application has errors

**Fix**:
```bash
# Check Laravel logs for actual error
tail -50 storage/logs/laravel.log

# Common issues:
# 1. Missing APP_KEY → add to .env.dusk.local
# 2. Neo4j config error → add NEO4J_ENABLED=false
# 3. Database connection → check DB credentials
# 4. Vite manifest → run npm run build
```

---

## 📸 Verify Everything Works

### Quick Verification Script

```bash
#!/bin/bash
echo "=== Verifying Dusk Setup ==="

echo "1. Chrome version:"
/tmp/chrome-linux64/chrome --version

echo "2. ChromeDriver version:"
vendor/laravel/dusk/bin/chromedriver-linux --version

echo "3. PostgreSQL status:"
service postgresql status

echo "4. ChromeDriver running:"
ps aux | grep chromedriver | grep -v grep || echo "NOT RUNNING!"

echo "5. Laravel server running:"
ps aux | grep "php artisan serve" | grep -v grep || echo "NOT RUNNING!"

echo "6. Server responds:"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost:8000/dusk-test

echo "7. Test screenshots directory:"
ls -lh tests/Browser/screenshots/ 2>/dev/null || echo "No screenshots yet"

echo "8. .env.dusk.local exists:"
test -f .env.dusk.local && echo "✓ Exists" || echo "✗ Missing!"

echo "9. APP_KEY in .env.dusk.local:"
grep -q "^APP_KEY" .env.dusk.local && echo "✓ Set" || echo "✗ Missing!"

echo "=== Ready to test! ==="
echo "Run: php artisan dusk tests/Browser/CloudExecutionProofTest.php"
```

Save as `scripts/verify-dusk.sh`, make executable, and run:

```bash
chmod +x scripts/verify-dusk.sh
./scripts/verify-dusk.sh
```

---

## 🎯 Minimal Test Route (For Testing)

Create `routes/dusk-test.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

// Public route for Dusk testing - no authentication required
Route::get('/dusk-test', function () {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dusk Test Page</title>
</head>
<body>
    <h1>Dusk Test Success!</h1>
    <p id="test-content">Chrome and Laravel Dusk are working perfectly in the cloud!</p>
    <button id="test-button">Click Me</button>
    <div id="result" style="display:none;">Button Clicked!</div>
    <script>
        document.getElementById("test-button").addEventListener("click", function() {
            document.getElementById("result").style.display = "block";
        });
    </script>
</body>
</html>';
});
```

Include in `routes/web.php`:
```php
require __DIR__.'/../routes/dusk-test.php';
```

Test it:
```bash
curl http://localhost:8000/dusk-test | grep "Dusk Test Success"
```

---

## 📦 Complete Startup Sequence

**Run this sequence every time you start testing:**

```bash
#!/bin/bash
# Complete Dusk startup sequence

echo "Starting services..."

# 1. PostgreSQL
service postgresql start
echo "✓ PostgreSQL started"

# 2. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
echo "✓ Caches cleared"

# 3. ChromeDriver
vendor/laravel/dusk/bin/chromedriver-linux --port=9515 > /tmp/chromedriver.log 2>&1 &
sleep 2
echo "✓ ChromeDriver started"

# 4. Laravel server
php artisan serve --host=127.0.0.1 --port=8000 > /tmp/laravel-server.log 2>&1 &
sleep 3
echo "✓ Laravel server started"

# 5. Verify
echo ""
echo "Services status:"
ps aux | grep -E "chromedriver|php artisan serve" | grep -v grep
echo ""

# 6. Test connection
curl -s -o /dev/null -w "Server: %{http_code}\n" http://localhost:8000/dusk-test

echo ""
echo "Ready to run tests!"
echo "php artisan dusk tests/Browser/CloudExecutionProofTest.php"
```

Save as `scripts/start-dusk.sh` and use:

```bash
chmod +x scripts/start-dusk.sh
./scripts/start-dusk.sh
```

---

## 🎓 Pro Tips

### 1. Use Background Processes
```bash
# Run in background with logs
chromedriver --port=9515 > /tmp/chromedriver.log 2>&1 &
php artisan serve > /tmp/server.log 2>&1 &
```

### 2. Monitor Logs in Real-Time
```bash
# In separate terminal
tail -f storage/logs/laravel.log
tail -f /tmp/chromedriver.log
tail -f /tmp/laravel-server.log
```

### 3. Clean Restart
```bash
pkill -9 -f "chromedriver|php artisan serve"
rm -f /tmp/chromedriver.log /tmp/laravel-server.log
# Then restart services
```

### 4. Test Single Method
```bash
php artisan dusk --filter=test_chrome_actually_works
```

### 5. Run Without Headless (See Browser)
```bash
DUSK_HEADLESS_DISABLED=true php artisan dusk
```

### 6. Keep Screenshots
```bash
# Screenshots are in:
tests/Browser/screenshots/

# Success screenshots: cloud-execution-proof.png
# Failure screenshots: failure-TestName-0.png
```

---

## ✅ Success Checklist

Before running tests, verify:

- [ ] Chrome installed: `/tmp/chrome-linux64/chrome --version`
- [ ] ChromeDriver installed: `vendor/laravel/dusk/bin/chromedriver-linux --version`
- [ ] Versions match: Both 131.0.6778.204
- [ ] PostgreSQL running: `service postgresql status`
- [ ] ChromeDriver running: `ps aux | grep chromedriver`
- [ ] Laravel server running: `ps aux | grep "php artisan serve"`
- [ ] .env.dusk.local exists with APP_KEY
- [ ] Caches cleared: `php artisan config:clear`
- [ ] Test route works: `curl http://localhost:8000/dusk-test`
- [ ] DuskTestCase has Chrome binary path
- [ ] Error view fixed (method_exists check)

---

## 🚀 Quick Start (Copy-Paste Ready)

```bash
# Complete setup from scratch
composer require --dev laravel/dusk
php artisan dusk:install

wget https://storage.googleapis.com/chrome-for-testing-public/131.0.6778.204/linux64/chrome-linux64.zip
wget https://storage.googleapis.com/chrome-for-testing-public/131.0.6778.204/linux64/chromedriver-linux64.zip

unzip chrome-linux64.zip -d /tmp/
unzip chromedriver-linux64.zip
cp chromedriver-linux64/chromedriver vendor/laravel/dusk/bin/chromedriver-linux
chmod +x vendor/laravel/dusk/bin/chromedriver-linux

cp .env .env.dusk.local
echo "NEO4J_ENABLED=false" >> .env.dusk.local

chmod 777 /tmp

service postgresql start
php artisan config:clear
php artisan cache:clear
php artisan view:clear

vendor/laravel/dusk/bin/chromedriver-linux --port=9515 > /tmp/chromedriver.log 2>&1 &
php artisan serve > /tmp/laravel-server.log 2>&1 &

sleep 5

php artisan dusk tests/Browser/CloudExecutionProofTest.php
```

---

**Created**: 2025-11-08
**Version**: 1.0
**Tested On**: Laravel 11, Chrome 131.0.6778.204
**Environment**: Cloud server (no GUI)
