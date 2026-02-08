<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * The callback that should be run on class tear down.
     */
    protected static $serverProcess;

    /**
     * Prepare for Dusk test execution.
     *
     * Note: ChromeDriver should be started manually or via system service
     * Run: chromedriver --port=9515
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        // Ensure Chrome is present (download only if missing)
        if (! File::exists(base_path('/tmp/chrome-linux/chrome'))) {
            // Run the script to fetch Chrome; ignore failures here to avoid blocking tests
            @shell_exec('./scripts/ensure-chrome.sh 2>&1');
        }

        // Install chromedriver binary used by Dusk if not present
        // dusk:chrome-driver will detect and download the correct driver
        // but avoid running it on every test run unnecessarily
        $driverPath = base_path('/vendor/laravel/dusk/bin/chromedriver');
        if (! File::exists($driverPath)) {
            Artisan::call('dusk:chrome-driver');
        }

        // Start chromedriver if not already running on port 9515
        $chromedriverRunning = false;
        $pidFile = '/tmp/chromedriver.pid';
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if (is_numeric($pid) && shell_exec("kill -0 $pid 2>/dev/null && echo 1") === '1') {
                $chromedriverRunning = true;
            }
        }
        if (! $chromedriverRunning) {
            // Check via pgrep fallback
            $pgrep = trim(shell_exec("pgrep -f 'chromedriver.*9515' || true"));
            if (! empty($pgrep)) {
                $chromedriverRunning = true;
            }
        }

        if (! $chromedriverRunning) {
            static::startChromeDriver(['--port=9515']);
        }

        // Start Laravel server with Dusk environment
        static::startLaravelServer();
    }

    /**
     * Start the Laravel development server with Dusk environment.
     */
    protected static function startLaravelServer(): void
    {
        $basePath = dirname(__DIR__);  // Go up from tests/ to project root
        $port = 8000;

        // Check if server is already running
        $serverRunning = @file_get_contents("http://127.0.0.1:$port") !== false;

        if ($serverRunning) {
            // Server already running, don't kill it
            return;
        }

        // Kill any existing server on this port (if not responding)
        exec("lsof -ti :$port | xargs kill -9 2>/dev/null");

        if (! File::exists(base_path('.env')) && File::exists(base_path('.env.dusk.local'))) {
            File::copy(base_path('.env.dusk.local'), base_path('.env'));
        }

        // Start server with APP_ENV set to use .env.dusk.local
        $command = sprintf(
            'cd %s && APP_ENV=testing php artisan serve --host=127.0.0.1 --port=%d > /tmp/dusk-server.log 2>&1 & echo $!',
            $basePath,
            $port
        );

        $pid = exec($command);

        // Wait for server to start
        sleep(3);

        static::$serverProcess = $pid;
    }

    /**
     * Tear down the Dusk test case class.
     */
    #[AfterClass]
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // Kill the server process
        //        if (static::$serverProcess) {
        //            exec('kill '.static::$serverProcess.' 2>/dev/null');
        //        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        // Use unique user data dir to avoid conflicts
        $userDataDir = '/tmp/chrome-dusk-'.uniqid();
        @mkdir($userDataDir, 0755, true);

        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--headless=new',

            // ===== DOCKER STABILITY FLAGS (FIXES CHROME CRASH) =====
            '--single-process',                               // Run Chrome as single process
            '--disable-setuid-sandbox',                       // Disable setuid sandbox
            '--disable-namespace-sandbox',                    // Disable namespace sandbox
            '--disable-features=VizDisplayCompositor',        // Disable compositor
            '--disable-features=IsolateOrigins,site-per-process',  // Disable isolation
            '--disable-blink-features=AutomationControlled',  // Disable automation detection
            '--disable-web-security',                         // Disable web security
            '--allow-running-insecure-content',              // Allow insecure content
            // ===== END DOCKER STABILITY FLAGS =====

            // Additional optimizations
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--disable-software-rasterizer',
            '--disable-extensions',
            '--disable-background-networking',
            '--disable-sync',
            '--metrics-recording-only',
            '--no-first-run',
            '--mute-audio',
            '--hide-scrollbars',
            '--disable-notifications',
            '--disable-logging',
            '--disable-permissions-api',
            '--ignore-certificate-errors',
            '--user-data-dir='.$userDataDir,
            '--disk-cache-dir='.$userDataDir.'/cache',
            // Additional Docker stability flags
            '--single-process',
            '--disable-setuid-sandbox',
            '--disable-namespace-sandbox',
            '--disable-features=VizDisplayCompositor',
            '--disable-features=IsolateOrigins,site-per-process',
            '--disable-blink-features=AutomationControlled',
            '--disable-web-security',
            '--allow-running-insecure-content',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        $chromePaths = [
            '/tmp/chrome-linux/chrome',
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

    /**
     * Determine whether the Dusk command has disabled headless mode.
     */
    protected function hasHeadlessDisabled(): bool
    {
        return isset($_SERVER['DUSK_HEADLESS_DISABLED']) ||
            isset($_ENV['DUSK_HEADLESS_DISABLED']);
    }

    /**
     * Determine if the browser window should start maximized.
     */
    protected function shouldStartMaximized(): bool
    {
        return isset($_SERVER['DUSK_START_MAXIMIZED']) ||
            isset($_ENV['DUSK_START_MAXIMIZED']);
    }
}
