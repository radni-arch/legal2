# Sprint 11: Monitoring & Logging

**Days**: 8-11
**Goal**: Implement production monitoring, logging, and alerting
**Tasks**: 8
**Priority**: High

[Link back to index](PRODUCTION_SPRINTS_INDEX.md)

---

## Task 11.1: Health Check Endpoint

**File**: `app/Http/Controllers/HealthController.php`, `routes/api.php`

**Priority**: Critical
**Estimated Time**: 1.5 hours
**Dependencies**: None

### Implementation Steps

1. **Create the HealthController**

   File: `app/Http/Controllers/HealthController.php`

   ```php
   <?php

   namespace App\Http\Controllers;

   use Illuminate\Http\JsonResponse;
   use Illuminate\Support\Facades\DB;
   use Illuminate\Support\Facades\Cache;
   use Illuminate\Support\Facades\Queue;
   use Illuminate\Support\Facades\Http;
   use Everest\Neo4j\Facades\Neo4j;

   /**
    * Health Check Controller
    *
    * Provides comprehensive system health status for monitoring services.
    * Used by uptime monitors, load balancers, and deployment scripts.
    */
   class HealthController extends Controller
   {
       /**
        * Basic health check - returns 200 if app is running
        */
       public function ping(): JsonResponse
       {
           return response()->json([
               'status' => 'ok',
               'timestamp' => now()->toIso8601String(),
           ]);
       }

       /**
        * Comprehensive health check
        * Checks all critical services
        */
       public function health(): JsonResponse
       {
           $startTime = microtime(true);
           $checks = [];
           $allHealthy = true;

           // 1. Database check
           $dbCheck = $this->checkDatabase();
           $checks['database'] = $dbCheck;
           if (!$dbCheck['healthy']) {
               $allHealthy = false;
           }

           // 2. Redis/Cache check
           $cacheCheck = $this->checkCache();
           $checks['cache'] = $cacheCheck;
           if (!$cacheCheck['healthy']) {
               $allHealthy = false;
           }

           // 3. Neo4j check
           $neo4jCheck = $this->checkNeo4j();
           $checks['neo4j'] = $neo4jCheck;
           if (!$neo4jCheck['healthy']) {
               $allHealthy = false;
           }

           // 4. OpenAI API check (non-critical)
           $openaiCheck = $this->checkOpenAI();
           $checks['openai'] = $openaiCheck;
           // Don't fail overall health if OpenAI is down

           // 5. AWS S3 check (non-critical)
           $awsCheck = $this->checkAWS();
           $checks['aws'] = $awsCheck;
           // Don't fail overall health if AWS is down

           // 6. Queue check
           $queueCheck = $this->checkQueue();
           $checks['queue'] = $queueCheck;
           if (!$queueCheck['healthy']) {
               $allHealthy = false;
           }

           // Calculate response time
           $responseTime = round((microtime(true) - $startTime) * 1000, 2);

           return response()->json([
               'status' => $allHealthy ? 'healthy' : 'unhealthy',
               'timestamp' => now()->toIso8601String(),
               'environment' => app()->environment(),
               'version' => config('app.version', '1.0.0'),
               'checks' => $checks,
               'metrics' => [
                   'response_time_ms' => $responseTime,
                   'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
                   'uptime_seconds' => $this->getUptime(),
               ],
           ], $allHealthy ? 200 : 503);
       }

       /**
        * Check database connectivity and performance
        */
       protected function checkDatabase(): array
       {
           try {
               $start = microtime(true);
               DB::connection()->getPdo();
               $queryTime = round((microtime(true) - $start) * 1000, 2);

               // Test a simple query
               $result = DB::select('SELECT 1 as test');

               return [
                   'healthy' => true,
                   'message' => 'Database connected',
                   'response_time_ms' => $queryTime,
                   'driver' => config('database.default'),
               ];
           } catch (\Throwable $e) {
               return [
                   'healthy' => false,
                   'message' => 'Database connection failed',
                   'error' => $e->getMessage(),
               ];
           }
       }

       /**
        * Check Redis/Cache connectivity
        */
       protected function checkCache(): array
       {
           try {
               $start = microtime(true);
               $testKey = 'health_check_' . time();
               $testValue = 'ok';

               Cache::put($testKey, $testValue, 10);
               $retrieved = Cache::get($testKey);
               Cache::forget($testKey);

               $responseTime = round((microtime(true) - $start) * 1000, 2);

               return [
                   'healthy' => $retrieved === $testValue,
                   'message' => 'Cache operational',
                   'response_time_ms' => $responseTime,
                   'driver' => config('cache.default'),
               ];
           } catch (\Throwable $e) {
               return [
                   'healthy' => false,
                   'message' => 'Cache connection failed',
                   'error' => $e->getMessage(),
               ];
           }
       }

       /**
        * Check Neo4j connectivity
        */
       protected function checkNeo4j(): array
       {
           try {
               $start = microtime(true);
               $result = Neo4j::run('RETURN 1 as test');
               $responseTime = round((microtime(true) - $start) * 1000, 2);

               return [
                   'healthy' => true,
                   'message' => 'Neo4j connected',
                   'response_time_ms' => $responseTime,
               ];
           } catch (\Throwable $e) {
               return [
                   'healthy' => false,
                   'message' => 'Neo4j connection failed',
                   'error' => $e->getMessage(),
               ];
           }
       }

       /**
        * Check OpenAI API connectivity
        */
       protected function checkOpenAI(): array
       {
           try {
               // Don't actually call API to save costs
               // Just verify configuration
               $apiKey = config('openai.api_key');

               return [
                   'healthy' => !empty($apiKey),
                   'message' => !empty($apiKey) ? 'OpenAI configured' : 'API key not set',
                   'configured' => !empty($apiKey),
               ];
           } catch (\Throwable $e) {
               return [
                   'healthy' => false,
                   'message' => 'OpenAI check failed',
                   'error' => $e->getMessage(),
               ];
           }
       }

       /**
        * Check AWS S3 connectivity
        */
       protected function checkAWS(): array
       {
           try {
               // Verify configuration
               $configured = !empty(config('filesystems.disks.s3.key'));

               return [
                   'healthy' => $configured,
                   'message' => $configured ? 'AWS configured' : 'AWS not configured',
                   'configured' => $configured,
               ];
           } catch (\Throwable $e) {
               return [
                   'healthy' => false,
                   'message' => 'AWS check failed',
                   'error' => $e->getMessage(),
               ];
           }
       }

       /**
        * Check queue worker status
        */
       protected function checkQueue(): array
       {
           try {
               // Check queue size
               $queueSize = Queue::size();

               // In production, you might check if workers are running
               // For now, just check if queue is accessible
               return [
                   'healthy' => true,
                   'message' => 'Queue operational',
                   'pending_jobs' => $queueSize,
                   'driver' => config('queue.default'),
               ];
           } catch (\Throwable $e) {
               return [
                   'healthy' => false,
                   'message' => 'Queue check failed',
                   'error' => $e->getMessage(),
               ];
           }
       }

       /**
        * Get application uptime in seconds
        */
       protected function getUptime(): int
       {
           // Store app start time in cache
           $cacheKey = 'app_start_time';

           if (!Cache::has($cacheKey)) {
               Cache::forever($cacheKey, now()->timestamp);
           }

           $startTime = Cache::get($cacheKey);
           return now()->timestamp - $startTime;
       }
   }
   ```

2. **Add routes**

   Edit: `routes/api.php`

   Add these routes to the top of the file:

   ```php
   use App\Http\Controllers\HealthController;

   // Health check endpoints (no authentication required)
   Route::get('/health', [HealthController::class, 'health'])->name('health');
   Route::get('/ping', [HealthController::class, 'ping'])->name('ping');
   ```

3. **Test the health check endpoint**

   ```bash
   # Start the application
   php artisan serve

   # Test ping endpoint
   curl http://localhost:8000/api/ping

   # Test full health endpoint
   curl http://localhost:8000/api/health | jq

   # Test with different database states
   # Stop database and verify it returns 503
   ```

### Acceptance Criteria

- [ ] HealthController created with all service checks
- [ ] Routes added to `routes/api.php`
- [ ] `/api/ping` returns 200 OK
- [ ] `/api/health` returns comprehensive status
- [ ] Returns 503 when critical services are down
- [ ] Response includes all services: database, cache, neo4j, queue
- [ ] Response includes metrics: response time, memory usage, uptime
- [ ] Health check completes in < 1 second

---

## Task 11.2: Application Performance Monitoring

**File**: `app/Http/Middleware/PerformanceMonitoring.php` (update), `app/Http/Kernel.php`

**Priority**: High
**Estimated Time**: 1 hour
**Dependencies**: Task 11.1

### Implementation Steps

1. **Verify PerformanceMonitoring middleware exists**

   File: `app/Http/Middleware/PerformanceMonitoring.php`

   The middleware already exists. Verify it's properly configured.

2. **Enable middleware in HTTP Kernel**

   Edit: `app/Http/Kernel.php`

   Add to the `$middlewareGroups` array:

   ```php
   protected $middlewareGroups = [
       'web' => [
           // ... existing middleware
           \App\Http\Middleware\PerformanceMonitoring::class,
       ],

       'api' => [
           // ... existing middleware
           \App\Http\Middleware\PerformanceMonitoring::class,
       ],
   ];
   ```

3. **Create metrics storage table**

   Create migration: `database/migrations/2025_11_09_000001_create_metrics_table.php`

   ```php
   <?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
       public function up(): void
       {
           Schema::create('metrics', function (Blueprint $table) {
               $table->id();
               $table->string('name')->index();
               $table->string('type'); // counter, gauge, timing, histogram
               $table->decimal('value', 10, 2);
               $table->json('tags')->nullable();
               $table->timestamp('recorded_at')->index();
               $table->timestamps();

               // Composite index for querying
               $table->index(['name', 'recorded_at']);
           });
       }

       public function down(): void
       {
           Schema::dropIfExists('metrics');
       }
   };
   ```

4. **Create Metric model**

   File: `app/Models/Metric.php`

   ```php
   <?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;

   class Metric extends Model
   {
       protected $fillable = [
           'name',
           'type',
           'value',
           'tags',
           'recorded_at',
       ];

       protected $casts = [
           'tags' => 'array',
           'recorded_at' => 'datetime',
           'value' => 'float',
       ];

       /**
        * Scope to filter by metric name
        */
       public function scopeNamed($query, string $name)
       {
           return $query->where('name', $name);
       }

       /**
        * Scope to filter by date range
        */
       public function scopeBetween($query, $start, $end)
       {
           return $query->whereBetween('recorded_at', [$start, $end]);
       }

       /**
        * Scope to get recent metrics
        */
       public function scopeRecent($query, int $hours = 24)
       {
           return $query->where('recorded_at', '>=', now()->subHours($hours));
       }
   }
   ```

5. **Update MetricsCollector to store in database**

   Check if file exists: `app/Services/Monitoring/MetricsCollector.php`

   If it doesn't exist, create it:

   ```php
   <?php

   namespace App\Services\Monitoring;

   use App\Models\Metric;
   use Illuminate\Support\Facades\Cache;

   /**
    * Metrics Collector Service
    *
    * Collects and stores application metrics.
    */
   class MetricsCollector
   {
       /**
        * Record a timing metric (in milliseconds)
        */
       public function timing(string $name, float $value, array $tags = []): void
       {
           $this->record($name, $value, 'timing', $tags);
       }

       /**
        * Increment a counter
        */
       public function increment(string $name, int $value = 1, array $tags = []): void
       {
           // Use cache for counters to aggregate
           $cacheKey = "metric:counter:{$name}:" . md5(json_encode($tags));
           $current = Cache::get($cacheKey, 0);
           Cache::put($cacheKey, $current + $value, now()->addHour());

           $this->record($name, $value, 'counter', $tags);
       }

       /**
        * Record a gauge value (current state)
        */
       public function gauge(string $name, float $value, array $tags = []): void
       {
           $this->record($name, $value, 'gauge', $tags);
       }

       /**
        * Record a metric
        */
       protected function record(string $name, float $value, string $type, array $tags = []): void
       {
           // Store in database for long-term analysis
           Metric::create([
               'name' => $name,
               'type' => $type,
               'value' => $value,
               'tags' => $tags,
               'recorded_at' => now(),
           ]);

           // Also keep in cache for real-time queries
           $cacheKey = "metric:latest:{$name}";
           Cache::put($cacheKey, [
               'value' => $value,
               'tags' => $tags,
               'timestamp' => now()->toIso8601String(),
           ], now()->addHours(24));
       }

       /**
        * Get latest metric value
        */
       public function getLatest(string $name): ?array
       {
           return Cache::get("metric:latest:{$name}");
       }

       /**
        * Get metric statistics for a time period
        */
       public function getStats(string $name, int $hours = 24): array
       {
           $metrics = Metric::named($name)
               ->recent($hours)
               ->get();

           if ($metrics->isEmpty()) {
               return [];
           }

           $values = $metrics->pluck('value');

           return [
               'count' => $metrics->count(),
               'min' => $values->min(),
               'max' => $values->max(),
               'avg' => round($values->avg(), 2),
               'sum' => $values->sum(),
               'p50' => $this->percentile($values, 50),
               'p95' => $this->percentile($values, 95),
               'p99' => $this->percentile($values, 99),
           ];
       }

       /**
        * Calculate percentile
        */
       protected function percentile($values, int $percentile): float
       {
           $sorted = $values->sort()->values();
           $index = ceil(($percentile / 100) * $sorted->count()) - 1;
           return $sorted[$index] ?? 0;
       }
   }
   ```

6. **Run migrations**

   ```bash
   php artisan migrate
   ```

7. **Test metrics collection**

   ```bash
   # Make some requests
   curl http://localhost:8000/api/health

   # Check metrics in database
   php artisan tinker
   >>> \App\Models\Metric::latest()->take(10)->get()
   >>> app(\App\Services\Monitoring\MetricsCollector::class)->getStats('http.request.duration')
   ```

### Acceptance Criteria

- [ ] PerformanceMonitoring middleware enabled
- [ ] Metrics table created
- [ ] Metric model created
- [ ] MetricsCollector service functional
- [ ] Metrics stored in both database and Redis
- [ ] Can query metric statistics
- [ ] Request metrics automatically collected
- [ ] Database query metrics tracked
- [ ] Memory usage tracked

---

## Task 11.3: Uptime Monitoring Configuration

**File**: `docs/server-config/uptime-monitoring.sh`

**Priority**: High
**Estimated Time**: 45 minutes
**Dependencies**: Task 11.1

### Implementation Steps

1. **Create uptime monitoring script**

   File: `docs/server-config/uptime-monitoring.sh`

   ```bash
   #!/bin/bash

   #####################################################################
   # Uptime Monitoring Script
   # Checks application health and sends alerts on downtime
   #
   # Usage:
   #   ./uptime-monitoring.sh
   #
   # Setup as cron job:
   #   */5 * * * * /path/to/uptime-monitoring.sh
   #####################################################################

   # Configuration
   APP_URL="${APP_URL:-http://localhost:8000}"
   HEALTH_ENDPOINT="${APP_URL}/api/health"
   ALERT_EMAIL="${ALERT_EMAIL:-admin@example.com}"
   STATE_FILE="/tmp/app_health_state"
   MAX_RETRIES=3
   RETRY_DELAY=10

   # Colors for output
   RED='\033[0;31m'
   GREEN='\033[0;32m'
   YELLOW='\033[1;33m'
   NC='\033[0m' # No Color

   # Function to send alert email
   send_alert() {
       local status=$1
       local message=$2
       local timestamp=$(date '+%Y-%m-%d %H:%M:%S')

       # Send email (requires mailutils or sendmail)
       if command -v mail &> /dev/null; then
           echo "Application Health Alert

   Timestamp: ${timestamp}
   Status: ${status}
   Endpoint: ${HEALTH_ENDPOINT}

   ${message}

   ---
   Automated alert from uptime monitoring system" | mail -s "[ALERT] Application ${status}" "${ALERT_EMAIL}"
       fi

       # Log to syslog
       logger -t "uptime-monitor" -p user.alert "${status}: ${message}"
   }

   # Function to send recovery notification
   send_recovery() {
       local timestamp=$(date '+%Y-%m-%d %H:%M:%S')

       if command -v mail &> /dev/null; then
           echo "Application Recovered

   Timestamp: ${timestamp}
   Status: Healthy
   Endpoint: ${HEALTH_ENDPOINT}

   The application has recovered and is now responding normally.

   ---
   Automated notification from uptime monitoring system" | mail -s "[RECOVERY] Application Healthy" "${ALERT_EMAIL}"
       fi

       logger -t "uptime-monitor" -p user.info "Application recovered"
   }

   # Function to check health with retries
   check_health() {
       local retry_count=0

       while [ $retry_count -lt $MAX_RETRIES ]; do
           # Make HTTP request
           response=$(curl -s -w "\n%{http_code}" --max-time 30 "${HEALTH_ENDPOINT}")
           http_code=$(echo "$response" | tail -n 1)
           body=$(echo "$response" | head -n -1)

           if [ "$http_code" == "200" ]; then
               # Parse JSON response
               status=$(echo "$body" | jq -r '.status // "unknown"')

               if [ "$status" == "healthy" ]; then
                   return 0  # Success
               fi
           fi

           retry_count=$((retry_count + 1))
           if [ $retry_count -lt $MAX_RETRIES ]; then
               echo -e "${YELLOW}Retry $retry_count/$MAX_RETRIES after ${RETRY_DELAY}s...${NC}"
               sleep $RETRY_DELAY
           fi
       done

       return 1  # Failed after retries
   }

   # Main monitoring logic
   main() {
       echo "Checking application health: ${HEALTH_ENDPOINT}"

       # Read previous state
       prev_state="unknown"
       if [ -f "$STATE_FILE" ]; then
           prev_state=$(cat "$STATE_FILE")
       fi

       # Check current health
       if check_health; then
           echo -e "${GREEN}✓ Application is healthy${NC}"

           # If previously down, send recovery notification
           if [ "$prev_state" == "down" ]; then
               echo "Application recovered from downtime"
               send_recovery
           fi

           echo "healthy" > "$STATE_FILE"
       else
           echo -e "${RED}✗ Application is down or unhealthy${NC}"

           # Only alert on state change (not every check)
           if [ "$prev_state" != "down" ]; then
               echo "Application went down - sending alert"
               send_alert "DOWN" "Application failed health check after ${MAX_RETRIES} retries"
           fi

           echo "down" > "$STATE_FILE"
       fi
   }

   # Run main function
   main
   ```

2. **Make script executable**

   ```bash
   chmod +x docs/server-config/uptime-monitoring.sh
   ```

3. **Create cron job configuration**

   File: `docs/server-config/uptime-cron.conf`

   ```bash
   # Uptime Monitoring Cron Job
   # Check application health every 5 minutes
   #
   # Installation:
   #   sudo cp uptime-cron.conf /etc/cron.d/app-uptime-monitor
   #   sudo chmod 644 /etc/cron.d/app-uptime-monitor
   #
   # Environment variables should be set in /etc/default/app-monitor:
   #   APP_URL=https://yourdomain.com
   #   ALERT_EMAIL=admin@yourdomain.com

   SHELL=/bin/bash
   PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

   # Check health every 5 minutes
   */5 * * * * root /path/to/ai-legal-war-machine/docs/server-config/uptime-monitoring.sh >> /var/log/uptime-monitor.log 2>&1
   ```

4. **Create environment file template**

   File: `docs/server-config/uptime-monitor.env.example`

   ```bash
   # Uptime Monitor Configuration
   # Copy to /etc/default/app-monitor

   # Application URL
   APP_URL=https://yourdomain.com

   # Alert email address
   ALERT_EMAIL=admin@yourdomain.com

   # Optional: Slack webhook for notifications
   SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
   ```

5. **Create installation instructions**

   File: `docs/server-config/UPTIME_MONITORING_SETUP.md`

   ```markdown
   # Uptime Monitoring Setup

   ## Installation

   1. **Install required packages**:
      ```bash
      sudo apt-get install curl jq mailutils
      ```

   2. **Configure environment**:
      ```bash
      sudo cp docs/server-config/uptime-monitor.env.example /etc/default/app-monitor
      sudo nano /etc/default/app-monitor
      # Set APP_URL and ALERT_EMAIL
      ```

   3. **Install cron job**:
      ```bash
      sudo cp docs/server-config/uptime-cron.conf /etc/cron.d/app-uptime-monitor
      sudo chmod 644 /etc/cron.d/app-uptime-monitor
      # Edit file to set correct script path
      ```

   4. **Test manually**:
      ```bash
      source /etc/default/app-monitor
      ./docs/server-config/uptime-monitoring.sh
      ```

   ## Alternative: UptimeRobot

   Instead of the self-hosted solution, you can use UptimeRobot (free plan):

   1. Sign up at https://uptimerobot.com
   2. Add monitor:
      - Monitor Type: HTTP(s)
      - URL: https://yourdomain.com/api/health
      - Monitoring Interval: 5 minutes
      - Alert Contacts: Your email
   3. Configure alert when keyword "healthy" is not found

   ## Monitoring

   - Check logs: `tail -f /var/log/uptime-monitor.log`
   - Check state: `cat /tmp/app_health_state`
   ```

6. **Test the monitoring script**

   ```bash
   # Test with app running
   APP_URL=http://localhost:8000 ./docs/server-config/uptime-monitoring.sh

   # Test with app down (stop the server first)
   php artisan serve &
   APP_URL=http://localhost:8000 ./docs/server-config/uptime-monitoring.sh
   ```

### Acceptance Criteria

- [ ] Uptime monitoring script created
- [ ] Script checks `/api/health` endpoint
- [ ] Includes retry logic (3 attempts)
- [ ] Sends email alerts on downtime
- [ ] Sends recovery notifications
- [ ] Prevents alert spam (only on state change)
- [ ] Cron job configuration provided
- [ ] Installation documentation complete
- [ ] Script tested manually

---

## Task 11.4: Alert Configuration

**File**: `config/monitoring.php` (update), `app/Services/Monitoring/AlertManager.php`

**Priority**: High
**Estimated Time**: 1.5 hours
**Dependencies**: Task 11.2

### Implementation Steps

1. **Update monitoring config with alert thresholds**

   Edit: `config/monitoring.php`

   Add system resource thresholds:

   ```php
   <?php

   return [
       // ... existing config

       /*
       |--------------------------------------------------------------------------
       | System Resource Thresholds
       |--------------------------------------------------------------------------
       |
       | Define thresholds for system resources. Alerts triggered when exceeded.
       |
       */

       'system_thresholds' => [
           'cpu_percent' => env('MONITORING_CPU_THRESHOLD', 80),
           'memory_percent' => env('MONITORING_MEMORY_THRESHOLD', 85),
           'disk_percent' => env('MONITORING_DISK_THRESHOLD', 90),
           'swap_percent' => env('MONITORING_SWAP_THRESHOLD', 50),
       ],

       /*
       |--------------------------------------------------------------------------
       | Application Thresholds
       |--------------------------------------------------------------------------
       */

       'app_thresholds' => [
           'error_rate_percent' => env('MONITORING_ERROR_RATE_THRESHOLD', 5),
           'slow_request_ms' => env('MONITORING_SLOW_REQUEST_MS', 2000),
           'slow_query_ms' => env('MONITORING_SLOW_QUERY_MS', 1000),
           'high_query_count' => env('MONITORING_HIGH_QUERY_COUNT', 50),
       ],

       /*
       |--------------------------------------------------------------------------
       | Alert Throttling
       |--------------------------------------------------------------------------
       |
       | Prevent alert spam by limiting how often alerts can be sent.
       |
       */

       'alert_throttle' => [
           'enabled' => env('MONITORING_ALERT_THROTTLE', true),
           'window_seconds' => env('MONITORING_ALERT_WINDOW', 300), // 5 minutes
           'max_per_window' => env('MONITORING_MAX_ALERTS_PER_WINDOW', 3),
       ],
   ];
   ```

2. **Create or update AlertManager**

   File: `app/Services/Monitoring/AlertManager.php`

   ```php
   <?php

   namespace App\Services\Monitoring;

   use Illuminate\Support\Facades\Cache;
   use Illuminate\Support\Facades\Log;
   use Illuminate\Support\Facades\Mail;
   use Illuminate\Support\Facades\Http;

   /**
    * Alert Manager Service
    *
    * Handles alert notifications with throttling and multiple channels.
    */
   class AlertManager
   {
       /**
        * Send an alert
        */
       public function alert(string $type, string $message, array $context = [], string $level = 'warning'): void
       {
           // Check if alerts are enabled
           if (!config('monitoring.enabled')) {
               return;
           }

           // Check throttling
           if ($this->isThrottled($type)) {
               Log::debug("Alert throttled: {$type}");
               return;
           }

           // Record alert
           $this->recordAlert($type);

           // Determine channels based on level
           $channels = config("monitoring.alert_channels.{$level}", ['log']);

           // Send to each channel
           foreach ($channels as $channel) {
               $this->sendToChannel($channel, $type, $message, $context, $level);
           }
       }

       /**
        * Send critical alert (always sent, minimal throttling)
        */
       public function critical(string $type, string $message, array $context = []): void
       {
           $this->alert($type, $message, $context, 'critical');
       }

       /**
        * Send error alert
        */
       public function error(string $type, string $message, array $context = []): void
       {
           $this->alert($type, $message, $context, 'error');
       }

       /**
        * Send warning alert
        */
       public function warning(string $type, string $message, array $context = []): void
       {
           $this->alert($type, $message, $context, 'warning');
       }

       /**
        * Check if alert type is throttled
        */
       protected function isThrottled(string $type): bool
       {
           if (!config('monitoring.alert_throttle.enabled')) {
               return false;
           }

           $window = config('monitoring.alert_throttle.window_seconds', 300);
           $maxPerWindow = config('monitoring.alert_throttle.max_per_window', 3);

           $key = "alert:count:{$type}";
           $count = Cache::get($key, 0);

           return $count >= $maxPerWindow;
       }

       /**
        * Record that an alert was sent
        */
       protected function recordAlert(string $type): void
       {
           $window = config('monitoring.alert_throttle.window_seconds', 300);
           $key = "alert:count:{$type}";

           $count = Cache::get($key, 0);
           Cache::put($key, $count + 1, $window);
       }

       /**
        * Send alert to specific channel
        */
       protected function sendToChannel(string $channel, string $type, string $message, array $context, string $level): void
       {
           try {
               match ($channel) {
                   'log' => $this->sendToLog($type, $message, $context, $level),
                   'email' => $this->sendToEmail($type, $message, $context, $level),
                   'slack' => $this->sendToSlack($type, $message, $context, $level),
                   default => Log::warning("Unknown alert channel: {$channel}"),
               };
           } catch (\Throwable $e) {
               Log::error("Failed to send alert to {$channel}", [
                   'error' => $e->getMessage(),
                   'type' => $type,
               ]);
           }
       }

       /**
        * Send to log
        */
       protected function sendToLog(string $type, string $message, array $context, string $level): void
       {
           Log::channel('monitoring')->log($level, "[{$type}] {$message}", $context);
       }

       /**
        * Send to email
        */
       protected function sendToEmail(string $type, string $message, array $context, string $level): void
       {
           $recipients = config('monitoring.alert_email', []);

           if (empty($recipients)) {
               return;
           }

           $subject = "[{$level}] {$type} - " . config('app.name');

           $body = "Alert Type: {$type}\n";
           $body .= "Level: {$level}\n";
           $body .= "Message: {$message}\n";
           $body .= "Timestamp: " . now()->toIso8601String() . "\n";
           $body .= "\nContext:\n" . json_encode($context, JSON_PRETTY_PRINT);

           foreach ($recipients as $recipient) {
               Mail::raw($body, function ($mail) use ($recipient, $subject) {
                   $mail->to($recipient)
                       ->subject($subject);
               });
           }
       }

       /**
        * Send to Slack
        */
       protected function sendToSlack(string $type, string $message, array $context, string $level): void
       {
           $webhookUrl = config('monitoring.slack_webhook');

           if (empty($webhookUrl)) {
               return;
           }

           $color = match ($level) {
               'critical' => 'danger',
               'error' => 'danger',
               'warning' => 'warning',
               default => 'good',
           };

           $emoji = match ($level) {
               'critical' => ':rotating_light:',
               'error' => ':x:',
               'warning' => ':warning:',
               default => ':information_source:',
           };

           $payload = [
               'username' => 'Monitoring Bot',
               'icon_emoji' => $emoji,
               'attachments' => [
                   [
                       'color' => $color,
                       'title' => $type,
                       'text' => $message,
                       'fields' => [
                           [
                               'title' => 'Level',
                               'value' => strtoupper($level),
                               'short' => true,
                           ],
                           [
                               'title' => 'Environment',
                               'value' => app()->environment(),
                               'short' => true,
                           ],
                       ],
                       'footer' => config('app.name'),
                       'ts' => now()->timestamp,
                   ],
               ],
           ];

           // Add context as fields if present
           if (!empty($context)) {
               foreach ($context as $key => $value) {
                   $payload['attachments'][0]['fields'][] = [
                       'title' => ucfirst($key),
                       'value' => is_scalar($value) ? $value : json_encode($value),
                       'short' => true,
                   ];
               }
           }

           Http::post($webhookUrl, $payload);
       }
   }
   ```

3. **Create system resource monitor command**

   File: `app/Console/Commands/MonitorSystemResources.php`

   ```php
   <?php

   namespace App\Console\Commands;

   use App\Services\Monitoring\AlertManager;
   use App\Services\Monitoring\MetricsCollector;
   use Illuminate\Console\Command;

   class MonitorSystemResources extends Command
   {
       protected $signature = 'monitor:system-resources';
       protected $description = 'Monitor system resources and send alerts on threshold violations';

       public function __construct(
           protected MetricsCollector $metrics,
           protected AlertManager $alerts
       ) {
           parent::__construct();
       }

       public function handle(): int
       {
           $this->info('Checking system resources...');

           // Check CPU
           $cpu = $this->getCpuUsage();
           $this->metrics->gauge('system.cpu.percent', $cpu);

           if ($cpu > config('monitoring.system_thresholds.cpu_percent', 80)) {
               $this->alerts->error('high_cpu_usage', "CPU usage is at {$cpu}%", ['cpu_percent' => $cpu]);
               $this->warn("⚠️  High CPU usage: {$cpu}%");
           }

           // Check Memory
           $memory = $this->getMemoryUsage();
           $this->metrics->gauge('system.memory.percent', $memory['percent']);

           if ($memory['percent'] > config('monitoring.system_thresholds.memory_percent', 85)) {
               $this->alerts->error('high_memory_usage', "Memory usage is at {$memory['percent']}%", $memory);
               $this->warn("⚠️  High memory usage: {$memory['percent']}%");
           }

           // Check Disk
           $disk = $this->getDiskUsage();
           $this->metrics->gauge('system.disk.percent', $disk['percent']);

           if ($disk['percent'] > config('monitoring.system_thresholds.disk_percent', 90)) {
               $this->alerts->critical('high_disk_usage', "Disk usage is at {$disk['percent']}%", $disk);
               $this->error("🚨 High disk usage: {$disk['percent']}%");
           }

           $this->info('✓ System resource check complete');

           return 0;
       }

       protected function getCpuUsage(): float
       {
           // Read CPU usage from /proc/stat
           $stat1 = $this->parseCpuStat();
           sleep(1);
           $stat2 = $this->parseCpuStat();

           $diff = [
               'user' => $stat2['user'] - $stat1['user'],
               'nice' => $stat2['nice'] - $stat1['nice'],
               'system' => $stat2['system'] - $stat1['system'],
               'idle' => $stat2['idle'] - $stat1['idle'],
           ];

           $total = array_sum($diff);
           $idle = $diff['idle'];

           return $total > 0 ? round((($total - $idle) / $total) * 100, 2) : 0;
       }

       protected function parseCpuStat(): array
       {
           $stat = file_get_contents('/proc/stat');
           preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat, $matches);

           return [
               'user' => (int) ($matches[1] ?? 0),
               'nice' => (int) ($matches[2] ?? 0),
               'system' => (int) ($matches[3] ?? 0),
               'idle' => (int) ($matches[4] ?? 0),
           ];
       }

       protected function getMemoryUsage(): array
       {
           $meminfo = file_get_contents('/proc/meminfo');

           preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
           preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);

           $totalMb = (int) ($total[1] ?? 0) / 1024;
           $availableMb = (int) ($available[1] ?? 0) / 1024;
           $usedMb = $totalMb - $availableMb;

           return [
               'total_mb' => round($totalMb, 2),
               'used_mb' => round($usedMb, 2),
               'available_mb' => round($availableMb, 2),
               'percent' => $totalMb > 0 ? round(($usedMb / $totalMb) * 100, 2) : 0,
           ];
       }

       protected function getDiskUsage(): array
       {
           $path = base_path();
           $total = disk_total_space($path);
           $free = disk_free_space($path);
           $used = $total - $free;

           return [
               'total_gb' => round($total / 1024 / 1024 / 1024, 2),
               'used_gb' => round($used / 1024 / 1024 / 1024, 2),
               'free_gb' => round($free / 1024 / 1024 / 1024, 2),
               'percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
           ];
       }
   }
   ```

4. **Add to scheduler**

   Edit: `app/Console/Kernel.php`

   ```php
   protected function schedule(Schedule $schedule): void
   {
       // ... existing scheduled tasks

       // Monitor system resources every 5 minutes
       $schedule->command('monitor:system-resources')->everyFiveMinutes();
   }
   ```

5. **Test alerts**

   ```bash
   # Test system resource monitoring
   php artisan monitor:system-resources

   # Test alert manager directly
   php artisan tinker
   >>> $alerts = app(\App\Services\Monitoring\AlertManager::class);
   >>> $alerts->warning('test_alert', 'This is a test alert', ['foo' => 'bar']);
   >>> $alerts->error('test_error', 'This is a test error');
   >>> $alerts->critical('test_critical', 'This is a critical test');
   ```

### Acceptance Criteria

- [ ] Monitoring config updated with system thresholds
- [ ] AlertManager service created with multi-channel support
- [ ] Alert throttling implemented
- [ ] System resource monitoring command created
- [ ] Monitors CPU, memory, disk usage
- [ ] Alerts sent via log, email, and Slack
- [ ] Alert throttling prevents spam
- [ ] Scheduled to run every 5 minutes
- [ ] Tested with manual alerts

---

## Task 11.5: Logging Configuration

**File**: `config/logging.php`

**Priority**: High
**Estimated Time**: 1 hour
**Dependencies**: None

### Implementation Steps

1. **Update logging configuration**

   Edit: `config/logging.php`

   ```php
   <?php

   use Monolog\Handler\NullHandler;
   use Monolog\Handler\StreamHandler;
   use Monolog\Handler\SyslogUdpHandler;
   use Monolog\Processor\PsrLogMessageProcessor;

   return [

       'default' => env('LOG_CHANNEL', 'stack'),

       'deprecations' => [
           'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
           'trace' => false,
       ],

       'channels' => [

           // Main stack - includes multiple channels
           'stack' => [
               'driver' => 'stack',
               'channels' => ['daily', 'slack'],
               'ignore_exceptions' => false,
           ],

           // Single file with daily rotation
           'single' => [
               'driver' => 'single',
               'path' => storage_path('logs/laravel.log'),
               'level' => env('LOG_LEVEL', 'debug'),
           ],

           // Daily rotating logs
           'daily' => [
               'driver' => 'daily',
               'path' => storage_path('logs/laravel.log'),
               'level' => env('LOG_LEVEL', 'debug'),
               'days' => 14,
           ],

           // Slack notifications for errors
           'slack' => [
               'driver' => 'slack',
               'url' => env('LOG_SLACK_WEBHOOK_URL'),
               'username' => 'Laravel Log',
               'emoji' => ':boom:',
               'level' => env('LOG_SLACK_LEVEL', 'critical'),
           ],

           // Syslog
           'syslog' => [
               'driver' => 'syslog',
               'level' => env('LOG_LEVEL', 'debug'),
               'facility' => LOG_USER,
           ],

           // Error log
           'errorlog' => [
               'driver' => 'errorlog',
               'level' => env('LOG_LEVEL', 'debug'),
           ],

           // Null channel (discards logs)
           'null' => [
               'driver' => 'monolog',
               'handler' => NullHandler::class,
           ],

           // Emergency channel
           'emergency' => [
               'path' => storage_path('logs/laravel.log'),
           ],

           /*
           |--------------------------------------------------------------------------
           | Custom Channels
           |--------------------------------------------------------------------------
           */

           // Performance logging
           'performance' => [
               'driver' => 'daily',
               'path' => storage_path('logs/performance.log'),
               'level' => 'info',
               'days' => 7,
           ],

           // Queue job logging
           'queue' => [
               'driver' => 'daily',
               'path' => storage_path('logs/queue.log'),
               'level' => 'debug',
               'days' => 14,
           ],

           // Security events
           'security' => [
               'driver' => 'daily',
               'path' => storage_path('logs/security.log'),
               'level' => 'warning',
               'days' => 30,
           ],

           // API requests/responses
           'api' => [
               'driver' => 'daily',
               'path' => storage_path('logs/api.log'),
               'level' => 'info',
               'days' => 7,
           ],

           // Database queries
           'database' => [
               'driver' => 'daily',
               'path' => storage_path('logs/database.log'),
               'level' => 'debug',
               'days' => 7,
           ],

           // Monitoring and metrics
           'monitoring' => [
               'driver' => 'daily',
               'path' => storage_path('logs/monitoring.log'),
               'level' => 'info',
               'days' => 14,
           ],

           // Agent activities
           'agents' => [
               'driver' => 'daily',
               'path' => storage_path('logs/agents.log'),
               'level' => 'debug',
               'days' => 14,
           ],

       ],

   ];
   ```

2. **Create .env logging variables**

   Add to `.env.example`:

   ```env
   # Logging Configuration
   LOG_CHANNEL=stack
   LOG_LEVEL=debug
   LOG_DEPRECATIONS_CHANNEL=null
   LOG_SLACK_WEBHOOK_URL=
   LOG_SLACK_LEVEL=critical
   ```

3. **Create custom logging service provider**

   File: `app/Providers/LoggingServiceProvider.php`

   ```php
   <?php

   namespace App\Providers;

   use Illuminate\Support\ServiceProvider;
   use Illuminate\Support\Facades\DB;
   use Illuminate\Support\Facades\Log;

   class LoggingServiceProvider extends ServiceProvider
   {
       public function boot(): void
       {
           // Log slow database queries
           if (config('monitoring.database.log_slow_queries')) {
               $this->logSlowQueries();
           }

           // Add context to all logs
           $this->addGlobalContext();
       }

       /**
        * Log slow database queries
        */
       protected function logSlowQueries(): void
       {
           DB::listen(function ($query) {
               $threshold = config('monitoring.database.slow_query_ms', 1000);

               if ($query->time >= $threshold) {
                   Log::channel('database')->warning('Slow query detected', [
                       'sql' => $query->sql,
                       'bindings' => $query->bindings,
                       'time_ms' => $query->time,
                       'connection' => $query->connectionName,
                   ]);
               }
           });
       }

       /**
        * Add global context to all logs
        */
       protected function addGlobalContext(): void
       {
           Log::shareContext([
               'environment' => app()->environment(),
               'app_version' => config('app.version', '1.0.0'),
           ]);

           // Add request context if in HTTP context
           if (app()->runningInConsole()) {
               Log::shareContext([
                   'context' => 'console',
                   'command' => $_SERVER['argv'][1] ?? 'unknown',
               ]);
           } else {
               Log::shareContext([
                   'context' => 'http',
                   'url' => request()->fullUrl(),
                   'method' => request()->method(),
                   'ip' => request()->ip(),
               ]);
           }
       }
   }
   ```

4. **Register the service provider**

   Edit: `config/app.php`

   ```php
   'providers' => [
       // ... other providers
       App\Providers\LoggingServiceProvider::class,
   ],
   ```

5. **Test logging channels**

   ```bash
   # Test different log channels
   php artisan tinker

   # Test default channel
   >>> Log::info('Test default log');

   # Test custom channels
   >>> Log::channel('performance')->info('Performance test');
   >>> Log::channel('security')->warning('Security event test');
   >>> Log::channel('queue')->debug('Queue processing test');

   # Verify log files created
   >>> exit;
   ls -la storage/logs/

   # View logs
   tail -f storage/logs/laravel.log
   tail -f storage/logs/performance.log
   ```

### Acceptance Criteria

- [ ] Logging config updated with custom channels
- [ ] Daily rotation configured (14 days for main log)
- [ ] Custom channels created: performance, queue, security, api, database, monitoring, agents
- [ ] LoggingServiceProvider created
- [ ] Slow queries automatically logged
- [ ] Global context added to all logs
- [ ] Slack notifications for critical errors (optional)
- [ ] Different retention periods per channel
- [ ] Tested all logging channels

---

## Task 11.6: Log Rotation Setup

**File**: `/etc/logrotate.d/ai-legal-war-machine` (system file)

**Priority**: Medium
**Estimated Time**: 30 minutes
**Dependencies**: Task 11.5

### Implementation Steps

1. **Create logrotate configuration**

   File: `docs/server-config/logrotate.conf`

   ```conf
   # Log Rotation Configuration for AI Legal War Machine
   #
   # Installation:
   #   sudo cp docs/server-config/logrotate.conf /etc/logrotate.d/ai-legal-war-machine
   #   sudo chmod 644 /etc/logrotate.d/ai-legal-war-machine
   #   sudo chown root:root /etc/logrotate.d/ai-legal-war-machine
   #
   # Test:
   #   sudo logrotate -d /etc/logrotate.d/ai-legal-war-machine
   #   sudo logrotate -f /etc/logrotate.d/ai-legal-war-machine

   /var/www/ai-legal-war-machine/storage/logs/*.log {
       # Rotate daily
       daily

       # Keep 14 days of logs
       rotate 14

       # Don't error if log file is missing
       missingok

       # Don't rotate empty logs
       notifempty

       # Compress rotated logs
       compress

       # Delay compression until next rotation
       delaycompress

       # Use date as suffix
       dateext
       dateformat -%Y%m%d

       # Create new log file with specific permissions
       create 0640 www-data www-data

       # Share scripts between all logs
       sharedscripts

       # Run after rotation
       postrotate
           # Clear Laravel cache to refresh log handles
           /usr/bin/php /var/www/ai-legal-war-machine/artisan cache:clear > /dev/null 2>&1 || true

           # Reload PHP-FPM to release old file handles
           /usr/bin/systemctl reload php8.2-fpm > /dev/null 2>&1 || true
       endscript
   }

   # Separate rule for performance logs (shorter retention)
   /var/www/ai-legal-war-machine/storage/logs/performance.log {
       daily
       rotate 7
       missingok
       notifempty
       compress
       delaycompress
       dateext
       create 0640 www-data www-data
   }

   # Keep security logs longer
   /var/www/ai-legal-war-machine/storage/logs/security.log {
       daily
       rotate 30
       missingok
       notifempty
       compress
       delaycompress
       dateext
       create 0640 www-data www-data
   }
   ```

2. **Create installation script**

   File: `docs/server-config/install-logrotate.sh`

   ```bash
   #!/bin/bash

   #####################################################################
   # Install Logrotate Configuration
   #####################################################################

   set -e

   # Check if running as root
   if [ "$EUID" -ne 0 ]; then
       echo "Please run as root (use sudo)"
       exit 1
   fi

   # Determine project path
   SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
   PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

   echo "Installing logrotate configuration..."
   echo "Project root: $PROJECT_ROOT"

   # Create temporary file with correct paths
   TEMP_CONF=$(mktemp)
   sed "s|/var/www/ai-legal-war-machine|$PROJECT_ROOT|g" \
       "$SCRIPT_DIR/logrotate.conf" > "$TEMP_CONF"

   # Copy to logrotate directory
   cp "$TEMP_CONF" /etc/logrotate.d/ai-legal-war-machine
   chmod 644 /etc/logrotate.d/ai-legal-war-machine
   chown root:root /etc/logrotate.d/ai-legal-war-machine

   # Clean up
   rm "$TEMP_CONF"

   echo "✓ Logrotate configuration installed"

   # Test configuration
   echo ""
   echo "Testing configuration..."
   logrotate -d /etc/logrotate.d/ai-legal-war-machine

   echo ""
   echo "✓ Installation complete"
   echo ""
   echo "To force rotation (for testing):"
   echo "  sudo logrotate -f /etc/logrotate.d/ai-legal-war-machine"
   ```

3. **Make script executable**

   ```bash
   chmod +x docs/server-config/install-logrotate.sh
   ```

4. **Create manual cleanup command**

   File: `app/Console/Commands/CleanOldLogs.php`

   ```php
   <?php

   namespace App\Console\Commands;

   use Illuminate\Console\Command;
   use Illuminate\Support\Facades\File;
   use Carbon\Carbon;

   class CleanOldLogs extends Command
   {
       protected $signature = 'logs:clean {--days=14 : Number of days to keep}';
       protected $description = 'Clean old log files';

       public function handle(): int
       {
           $days = (int) $this->option('days');
           $cutoffDate = Carbon::now()->subDays($days);

           $this->info("Cleaning logs older than {$days} days (before {$cutoffDate->toDateString()})...");

           $logsPath = storage_path('logs');
           $files = File::files($logsPath);

           $deletedCount = 0;
           $deletedSize = 0;

           foreach ($files as $file) {
               $fileTime = Carbon::createFromTimestamp($file->getMTime());

               if ($fileTime->lt($cutoffDate)) {
                   $size = $file->getSize();
                   $this->line("Deleting: {$file->getFilename()} ({$this->formatBytes($size)})");

                   File::delete($file->getPathname());
                   $deletedCount++;
                   $deletedSize += $size;
               }
           }

           if ($deletedCount > 0) {
               $this->info("✓ Deleted {$deletedCount} log files ({$this->formatBytes($deletedSize)})");
           } else {
               $this->info("No old log files to delete");
           }

           return 0;
       }

       protected function formatBytes(int $bytes): string
       {
           $units = ['B', 'KB', 'MB', 'GB'];
           $index = 0;

           while ($bytes >= 1024 && $index < count($units) - 1) {
               $bytes /= 1024;
               $index++;
           }

           return round($bytes, 2) . ' ' . $units[$index];
       }
   }
   ```

5. **Test log rotation**

   ```bash
   # Test manually (dry run)
   sudo logrotate -d docs/server-config/logrotate.conf

   # Force rotation for testing
   sudo docs/server-config/install-logrotate.sh
   sudo logrotate -f /etc/logrotate.d/ai-legal-war-machine

   # Check rotated logs
   ls -la storage/logs/

   # Test cleanup command
   php artisan logs:clean --days=7
   ```

### Acceptance Criteria

- [ ] Logrotate configuration created
- [ ] Daily rotation configured
- [ ] 14-day retention for main logs
- [ ] 7-day retention for performance logs
- [ ] 30-day retention for security logs
- [ ] Compression enabled for old logs
- [ ] Post-rotation script clears cache
- [ ] Installation script created
- [ ] Manual cleanup command created
- [ ] Tested rotation (dry run and actual)

---

## Task 11.7: Error Rate Monitoring

**File**: `app/Console/Commands/ErrorRateMonitor.php`

**Priority**: High
**Estimated Time**: 1.5 hours
**Dependencies**: Task 11.2, Task 11.4

### Implementation Steps

1. **Create error tracking table**

   File: `database/migrations/2025_11_09_000002_create_error_tracking_table.php`

   ```php
   <?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
       public function up(): void
       {
           Schema::create('error_tracking', function (Blueprint $table) {
               $table->id();
               $table->string('type')->index(); // http, queue, api, database
               $table->integer('error_code')->nullable();
               $table->string('error_class')->nullable();
               $table->text('error_message');
               $table->json('context')->nullable();
               $table->string('file')->nullable();
               $table->integer('line')->nullable();
               $table->text('stack_trace')->nullable();
               $table->string('url')->nullable();
               $table->string('method')->nullable();
               $table->ipAddress('ip_address')->nullable();
               $table->string('user_agent')->nullable();
               $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
               $table->timestamp('occurred_at')->index();
               $table->timestamps();

               // Indexes for querying
               $table->index(['type', 'occurred_at']);
               $table->index('error_class');
           });
       }

       public function down(): void
       {
           Schema::dropIfExists('error_tracking');
       }
   };
   ```

2. **Create ErrorTracking model**

   File: `app/Models/ErrorTracking.php`

   ```php
   <?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;

   class ErrorTracking extends Model
   {
       protected $fillable = [
           'type',
           'error_code',
           'error_class',
           'error_message',
           'context',
           'file',
           'line',
           'stack_trace',
           'url',
           'method',
           'ip_address',
           'user_agent',
           'user_id',
           'occurred_at',
       ];

       protected $casts = [
           'context' => 'array',
           'occurred_at' => 'datetime',
       ];

       public function user(): BelongsTo
       {
           return $this->belongsTo(User::class);
       }

       /**
        * Scope to filter by type
        */
       public function scopeOfType($query, string $type)
       {
           return $query->where('type', $type);
       }

       /**
        * Scope to filter by date range
        */
       public function scopeBetween($query, $start, $end)
       {
           return $query->whereBetween('occurred_at', [$start, $end]);
       }

       /**
        * Scope to get recent errors
        */
       public function scopeRecent($query, int $hours = 24)
       {
           return $query->where('occurred_at', '>=', now()->subHours($hours));
       }
   }
   ```

3. **Create error tracking service**

   File: `app/Services/Monitoring/ErrorTracker.php`

   ```php
   <?php

   namespace App\Services\Monitoring;

   use App\Models\ErrorTracking;
   use Illuminate\Support\Facades\Auth;

   class ErrorTracker
   {
       /**
        * Track an error
        */
       public function track(
           string $type,
           \Throwable $exception,
           array $context = []
       ): void {
           try {
               ErrorTracking::create([
                   'type' => $type,
                   'error_code' => $exception->getCode(),
                   'error_class' => get_class($exception),
                   'error_message' => $exception->getMessage(),
                   'context' => $context,
                   'file' => $exception->getFile(),
                   'line' => $exception->getLine(),
                   'stack_trace' => $exception->getTraceAsString(),
                   'url' => request()->fullUrl(),
                   'method' => request()->method(),
                   'ip_address' => request()->ip(),
                   'user_agent' => request()->userAgent(),
                   'user_id' => Auth::id(),
                   'occurred_at' => now(),
               ]);
           } catch (\Throwable $e) {
               // Don't let error tracking itself cause issues
               logger()->error('Failed to track error', [
                   'tracking_error' => $e->getMessage(),
                   'original_error' => $exception->getMessage(),
               ]);
           }
       }

       /**
        * Track HTTP error
        */
       public function trackHttpError(int $statusCode, string $message, array $context = []): void
       {
           try {
               ErrorTracking::create([
                   'type' => 'http',
                   'error_code' => $statusCode,
                   'error_class' => 'HttpError',
                   'error_message' => $message,
                   'context' => $context,
                   'url' => request()->fullUrl(),
                   'method' => request()->method(),
                   'ip_address' => request()->ip(),
                   'user_agent' => request()->userAgent(),
                   'user_id' => Auth::id(),
                   'occurred_at' => now(),
               ]);
           } catch (\Throwable $e) {
               logger()->error('Failed to track HTTP error', [
                   'error' => $e->getMessage(),
               ]);
           }
       }

       /**
        * Get error rate for a time period
        */
       public function getErrorRate(string $type, int $hours = 1): float
       {
           $errorCount = ErrorTracking::ofType($type)
               ->recent($hours)
               ->count();

           // For HTTP errors, compare to total requests
           if ($type === 'http') {
               $totalRequests = \App\Models\Metric::named('http.request.count')
                   ->recent($hours)
                   ->sum('value');

               return $totalRequests > 0
                   ? round(($errorCount / $totalRequests) * 100, 2)
                   : 0;
           }

           // For other types, return count per hour
           return round($errorCount / $hours, 2);
       }
   }
   ```

4. **Create error rate monitoring command**

   File: `app/Console/Commands/ErrorRateMonitor.php`

   ```php
   <?php

   namespace App\Console\Commands;

   use App\Services\Monitoring\AlertManager;
   use App\Services\Monitoring\ErrorTracker;
   use App\Models\ErrorTracking;
   use Illuminate\Console\Command;

   class ErrorRateMonitor extends Command
   {
       protected $signature = 'monitor:error-rate';
       protected $description = 'Monitor error rates and send alerts';

       public function __construct(
           protected ErrorTracker $tracker,
           protected AlertManager $alerts
       ) {
           parent::__construct();
       }

       public function handle(): int
       {
           $this->info('Monitoring error rates...');

           // Check HTTP errors
           $this->checkHttpErrors();

           // Check API errors
           $this->checkApiErrors();

           // Check queue failures
           $this->checkQueueFailures();

           // Show summary
           $this->showSummary();

           $this->info('✓ Error rate monitoring complete');

           return 0;
       }

       protected function checkHttpErrors(): void
       {
           $hourlyRate = $this->tracker->getErrorRate('http', 1);
           $dailyRate = $this->tracker->getErrorRate('http', 24);

           $this->line("HTTP Error Rate: {$hourlyRate}% (last hour), {$dailyRate}% (last 24h)");

           $threshold = config('monitoring.app_thresholds.error_rate_percent', 5);

           if ($hourlyRate > $threshold) {
               $this->alerts->error(
                   'high_http_error_rate',
                   "HTTP error rate is {$hourlyRate}% (threshold: {$threshold}%)",
                   [
                       'hourly_rate' => $hourlyRate,
                       'daily_rate' => $dailyRate,
                       'threshold' => $threshold,
                   ]
               );

               $this->warn("⚠️  High HTTP error rate: {$hourlyRate}%");
           }
       }

       protected function checkApiErrors(): void
       {
           $recentErrors = ErrorTracking::ofType('api')
               ->recent(1)
               ->count();

           $this->line("API Errors: {$recentErrors} (last hour)");

           if ($recentErrors > 10) {
               $this->alerts->warning(
                   'high_api_errors',
                   "High number of API errors: {$recentErrors} in last hour",
                   ['error_count' => $recentErrors]
               );
           }
       }

       protected function checkQueueFailures(): void
       {
           $failures = ErrorTracking::ofType('queue')
               ->recent(1)
               ->count();

           $this->line("Queue Failures: {$failures} (last hour)");

           if ($failures > 5) {
               $this->alerts->error(
                   'high_queue_failures',
                   "High number of queue failures: {$failures} in last hour",
                   ['failure_count' => $failures]
               );

               $this->warn("⚠️  High queue failure rate: {$failures} failures");
           }
       }

       protected function showSummary(): void
       {
           $this->newLine();
           $this->info('Error Summary (Last 24 Hours):');

           $summary = ErrorTracking::recent(24)
               ->selectRaw('type, COUNT(*) as count')
               ->groupBy('type')
               ->get();

           $this->table(
               ['Type', 'Count'],
               $summary->map(fn($row) => [$row->type, $row->count])
           );

           // Top errors
           $topErrors = ErrorTracking::recent(24)
               ->selectRaw('error_class, error_message, COUNT(*) as count')
               ->groupBy('error_class', 'error_message')
               ->orderByDesc('count')
               ->limit(5)
               ->get();

           if ($topErrors->isNotEmpty()) {
               $this->newLine();
               $this->info('Top 5 Errors:');
               $this->table(
                   ['Error Class', 'Message', 'Count'],
                   $topErrors->map(fn($row) => [
                       $row->error_class,
                       \Str::limit($row->error_message, 50),
                       $row->count,
                   ])
               );
           }
       }
   }
   ```

5. **Update exception handler to track errors**

   Edit: `app/Exceptions/Handler.php`

   ```php
   use App\Services\Monitoring\ErrorTracker;

   public function register(): void
   {
       $this->reportable(function (Throwable $e) {
           // Track all exceptions
           app(ErrorTracker::class)->track('http', $e);
       });
   }
   ```

6. **Add to scheduler**

   Edit: `app/Console/Kernel.php`

   ```php
   protected function schedule(Schedule $schedule): void
   {
       // ... existing tasks

       // Monitor error rates every 15 minutes
       $schedule->command('monitor:error-rate')->everyFifteenMinutes();
   }
   ```

7. **Run migrations and test**

   ```bash
   # Run migration
   php artisan migrate

   # Test error tracking
   php artisan tinker
   >>> app(\App\Services\Monitoring\ErrorTracker::class)->trackHttpError(500, 'Test error');
   >>> exit

   # Run error rate monitor
   php artisan monitor:error-rate

   # Check error tracking table
   php artisan tinker
   >>> \App\Models\ErrorTracking::latest()->first()
   ```

### Acceptance Criteria

- [ ] Error tracking table created
- [ ] ErrorTracking model created
- [ ] ErrorTracker service implemented
- [ ] Tracks HTTP 500s, API failures, queue failures
- [ ] ErrorRateMonitor command created
- [ ] Calculates error rates per hour/day
- [ ] Alerts on >5% error rate
- [ ] Shows top errors in summary
- [ ] Exception handler updated to track all errors
- [ ] Scheduled to run every 15 minutes

---

## Task 11.8: Log Analysis Tools

**File**: `scripts/analyze-logs.sh`, `app/Console/Commands/LogAnalyzer.php`

**Priority**: Medium
**Estimated Time**: 1.5 hours
**Dependencies**: Task 11.5

### Implementation Steps

1. **Create log analysis shell script**

   File: `scripts/analyze-logs.sh`

   ```bash
   #!/bin/bash

   #####################################################################
   # Log Analysis Script
   # Analyzes Laravel logs for errors, patterns, and insights
   #
   # Usage:
   #   ./scripts/analyze-logs.sh [options]
   #
   # Options:
   #   -f, --file FILE      Log file to analyze (default: storage/logs/laravel.log)
   #   -d, --days DAYS      Number of days to analyze (default: 1)
   #   -e, --errors-only    Show only errors
   #   -s, --slow-queries   Show slow queries
   #   -h, --help           Show this help message
   #####################################################################

   set -e

   # Default values
   LOG_FILE="storage/logs/laravel.log"
   DAYS=1
   ERRORS_ONLY=0
   SLOW_QUERIES=0

   # Colors
   RED='\033[0;31m'
   GREEN='\033[0;32m'
   YELLOW='\033[1;33m'
   BLUE='\033[0;34m'
   NC='\033[0m'

   # Parse arguments
   while [[ $# -gt 0 ]]; do
       case $1 in
           -f|--file)
               LOG_FILE="$2"
               shift 2
               ;;
           -d|--days)
               DAYS="$2"
               shift 2
               ;;
           -e|--errors-only)
               ERRORS_ONLY=1
               shift
               ;;
           -s|--slow-queries)
               SLOW_QUERIES=1
               shift
               ;;
           -h|--help)
               grep "^#" "$0" | sed 's/^#//'
               exit 0
               ;;
           *)
               echo "Unknown option: $1"
               exit 1
               ;;
       esac
   done

   # Check if log file exists
   if [ ! -f "$LOG_FILE" ]; then
       echo -e "${RED}Error: Log file not found: $LOG_FILE${NC}"
       exit 1
   fi

   echo -e "${BLUE}=== Log Analysis ===${NC}"
   echo "File: $LOG_FILE"
   echo "Last modified: $(date -r "$LOG_FILE" '+%Y-%m-%d %H:%M:%S')"
   echo "Size: $(du -h "$LOG_FILE" | cut -f1)"
   echo ""

   # Function to count log levels
   analyze_log_levels() {
       echo -e "${GREEN}Log Levels:${NC}"
       echo "----------------------------------------"

       for level in EMERGENCY ALERT CRITICAL ERROR WARNING NOTICE INFO DEBUG; do
           count=$(grep -c "\\.$level:" "$LOG_FILE" || true)
           if [ $count -gt 0 ]; then
               printf "%-12s: %6d\n" "$level" "$count"
           fi
       done
       echo ""
   }

   # Function to show top errors
   show_top_errors() {
       echo -e "${RED}Top 10 Errors:${NC}"
       echo "----------------------------------------"

       grep "\\.ERROR:" "$LOG_FILE" | \
           sed 's/^.*ERROR: //' | \
           sed 's/ {.*//' | \
           sort | uniq -c | sort -rn | head -10 | \
           awk '{printf "%4d  %s\n", $1, substr($0, index($0,$2))}'

       echo ""
   }

   # Function to analyze slow queries
   analyze_slow_queries() {
       echo -e "${YELLOW}Slow Database Queries:${NC}"
       echo "----------------------------------------"

       if grep -q "Slow query detected" "$LOG_FILE"; then
           grep "Slow query detected" "$LOG_FILE" | \
               sed 's/.*time_ms"://' | sed 's/,.*//' | \
               sort -rn | head -10 | \
               awk '{printf "  %6.2f ms\n", $1}'
       else
           echo "  No slow queries found"
       fi

       echo ""
   }

   # Function to show request statistics
   analyze_requests() {
       echo -e "${GREEN}Request Statistics:${NC}"
       echo "----------------------------------------"

       # Count by status code
       echo "Status codes:"
       grep "\"status\":" "$LOG_FILE" | \
           sed 's/.*"status"://' | sed 's/,.*//' | \
           sort | uniq -c | sort -rn | \
           awk '{printf "  %3s: %4d requests\n", $2, $1}'

       echo ""

       # Slowest requests
       echo "Slowest requests:"
       grep "duration_ms" "$LOG_FILE" | \
           sed 's/.*"uri":"//' | sed 's/".*//' | \
           awk '{uri=$1; getline; if (match($0, /"duration_ms":[0-9.]+/)) {
               ms=substr($0, RSTART+14, RLENGTH-14);
               print ms "\t" uri
           }}' | sort -rn | head -5 | \
           awk '{printf "  %8.2f ms  %s\n", $1, $2}'

       echo ""
   }

   # Function to detect patterns
   detect_patterns() {
       echo -e "${BLUE}Detected Patterns:${NC}"
       echo "----------------------------------------"

       # Memory issues
       memory_errors=$(grep -c "Allowed memory size" "$LOG_FILE" || true)
       if [ $memory_errors -gt 0 ]; then
           echo -e "  ${RED}⚠ Memory exhaustion: $memory_errors occurrences${NC}"
       fi

       # Database connection issues
       db_errors=$(grep -c "database connection" "$LOG_FILE" || true)
       if [ $db_errors -gt 0 ]; then
           echo -e "  ${RED}⚠ Database connection issues: $db_errors occurrences${NC}"
       fi

       # API rate limits
       rate_limit=$(grep -c "rate limit" "$LOG_FILE" || true)
       if [ $rate_limit -gt 0 ]; then
           echo -e "  ${YELLOW}⚠ Rate limit hits: $rate_limit occurrences${NC}"
       fi

       # Timeout errors
       timeouts=$(grep -c "timeout" "$LOG_FILE" || true)
       if [ $timeouts -gt 0 ]; then
           echo -e "  ${YELLOW}⚠ Timeouts: $timeouts occurrences${NC}"
       fi

       if [ $memory_errors -eq 0 ] && [ $db_errors -eq 0 ] && [ $rate_limit -eq 0 ] && [ $timeouts -eq 0 ]; then
           echo "  No concerning patterns detected"
       fi

       echo ""
   }

   # Main analysis
   if [ $ERRORS_ONLY -eq 1 ]; then
       show_top_errors
   elif [ $SLOW_QUERIES -eq 1 ]; then
       analyze_slow_queries
   else
       analyze_log_levels
       show_top_errors
       analyze_slow_queries
       analyze_requests
       detect_patterns
   fi

   echo -e "${GREEN}✓ Analysis complete${NC}"
   ```

2. **Make script executable**

   ```bash
   chmod +x scripts/analyze-logs.sh
   ```

3. **Create Laravel log analyzer command**

   File: `app/Console/Commands/LogAnalyzer.php`

   ```php
   <?php

   namespace App\Console\Commands;

   use Illuminate\Console\Command;
   use Illuminate\Support\Facades\File;
   use Carbon\Carbon;

   class LogAnalyzer extends Command
   {
       protected $signature = 'logs:analyze
                               {--file= : Specific log file to analyze}
                               {--days=1 : Number of days to analyze}
                               {--errors : Show only errors}
                               {--report : Generate daily summary report}';

       protected $description = 'Analyze application logs and generate reports';

       public function handle(): int
       {
           if ($this->option('report')) {
               return $this->generateDailySummary();
           }

           $logFile = $this->option('file') ?? storage_path('logs/laravel.log');

           if (!File::exists($logFile)) {
               $this->error("Log file not found: {$logFile}");
               return 1;
           }

           $this->info("Analyzing: {$logFile}");
           $this->newLine();

           if ($this->option('errors')) {
               $this->showTopErrors($logFile);
           } else {
               $this->showLogLevels($logFile);
               $this->newLine();
               $this->showTopErrors($logFile);
               $this->newLine();
               $this->showSlowQueries($logFile);
           }

           return 0;
       }

       protected function showLogLevels(string $logFile): void
       {
           $this->info('Log Levels:');

           $content = File::get($logFile);
           $levels = [
               'EMERGENCY' => 0,
               'ALERT' => 0,
               'CRITICAL' => 0,
               'ERROR' => 0,
               'WARNING' => 0,
               'NOTICE' => 0,
               'INFO' => 0,
               'DEBUG' => 0,
           ];

           foreach ($levels as $level => $count) {
               $levels[$level] = substr_count($content, ".{$level}:");
           }

           $this->table(
               ['Level', 'Count'],
               collect($levels)->map(fn($count, $level) => [$level, $count])
           );
       }

       protected function showTopErrors(string $logFile): void
       {
           $this->info('Top 10 Errors:');

           $errors = [];
           $lines = File::lines($logFile);

           foreach ($lines as $line) {
               if (strpos($line, '.ERROR:') !== false) {
                   // Extract error message
                   preg_match('/\.ERROR: (.+?) \{/', $line, $matches);
                   if (isset($matches[1])) {
                       $message = trim($matches[1]);
                       $errors[$message] = ($errors[$message] ?? 0) + 1;
                   }
               }
           }

           arsort($errors);
           $topErrors = array_slice($errors, 0, 10, true);

           if (empty($topErrors)) {
               $this->line('  No errors found');
               return;
           }

           $this->table(
               ['Count', 'Error Message'],
               collect($topErrors)->map(fn($count, $msg) => [
                   $count,
                   \Str::limit($msg, 80),
               ])
           );
       }

       protected function showSlowQueries(string $logFile): void
       {
           $this->info('Slow Database Queries:');

           $slowQueries = [];
           $lines = File::lines($logFile);

           foreach ($lines as $line) {
               if (strpos($line, 'Slow query detected') !== false) {
                   preg_match('/"time_ms":([0-9.]+)/', $line, $matches);
                   if (isset($matches[1])) {
                       $time = (float) $matches[1];

                       preg_match('/"sql":"(.+?)"/', $line, $sqlMatches);
                       $sql = $sqlMatches[1] ?? 'Unknown';

                       $slowQueries[] = [
                           'time' => $time,
                           'sql' => $sql,
                       ];
                   }
               }
           }

           if (empty($slowQueries)) {
               $this->line('  No slow queries found');
               return;
           }

           usort($slowQueries, fn($a, $b) => $b['time'] <=> $a['time']);
           $slowQueries = array_slice($slowQueries, 0, 10);

           $this->table(
               ['Time (ms)', 'Query'],
               array_map(fn($q) => [
                   number_format($q['time'], 2),
                   \Str::limit($q['sql'], 80),
               ], $slowQueries)
           );
       }

       protected function generateDailySummary(): int
       {
           $this->info('Generating daily summary report...');

           $yesterday = Carbon::yesterday();
           $logFile = storage_path("logs/laravel-{$yesterday->format('Y-m-d')}.log");

           if (!File::exists($logFile)) {
               $this->warn("No log file found for {$yesterday->toDateString()}");
               return 1;
           }

           $content = File::get($logFile);

           // Count errors
           $errorCount = substr_count($content, '.ERROR:');
           $warningCount = substr_count($content, '.WARNING:');
           $criticalCount = substr_count($content, '.CRITICAL:');

           // Generate report
           $report = "Daily Log Summary - {$yesterday->toDateString()}\n";
           $report .= str_repeat('=', 60) . "\n\n";
           $report .= "Critical: {$criticalCount}\n";
           $report .= "Errors: {$errorCount}\n";
           $report .= "Warnings: {$warningCount}\n\n";

           // Add top errors
           $report .= "Top Errors:\n";
           $report .= str_repeat('-', 60) . "\n";

           // Save report
           $reportFile = storage_path("logs/summary-{$yesterday->format('Y-m-d')}.txt");
           File::put($reportFile, $report);

           $this->info("Report saved to: {$reportFile}");

           // Display summary
           $this->newLine();
           $this->line($report);

           return 0;
       }
   }
   ```

4. **Create top errors command**

   File: `app/Console/Commands/ShowTopErrors.php`

   ```php
   <?php

   namespace App\Console\Commands;

   use App\Models\ErrorTracking;
   use Illuminate\Console\Command;

   class ShowTopErrors extends Command
   {
       protected $signature = 'errors:top
                               {--hours=24 : Number of hours to analyze}
                               {--limit=10 : Number of top errors to show}';

       protected $description = 'Show top errors from error tracking';

       public function handle(): int
       {
           $hours = (int) $this->option('hours');
           $limit = (int) $this->option('limit');

           $this->info("Top {$limit} Errors (Last {$hours} Hours):");
           $this->newLine();

           $topErrors = ErrorTracking::recent($hours)
               ->selectRaw('error_class, error_message, COUNT(*) as count, MAX(occurred_at) as last_occurrence')
               ->groupBy('error_class', 'error_message')
               ->orderByDesc('count')
               ->limit($limit)
               ->get();

           if ($topErrors->isEmpty()) {
               $this->info('No errors found in the specified period');
               return 0;
           }

           $this->table(
               ['Count', 'Last Occurrence', 'Error Class', 'Message'],
               $topErrors->map(fn($error) => [
                   $error->count,
                   $error->last_occurrence->diffForHumans(),
                   $error->error_class,
                   \Str::limit($error->error_message, 60),
               ])
           );

           // Show total error count
           $totalErrors = ErrorTracking::recent($hours)->count();
           $this->newLine();
           $this->info("Total errors in period: {$totalErrors}");

           return 0;
       }
   }
   ```

5. **Schedule daily summary report**

   Edit: `app/Console/Kernel.php`

   ```php
   protected function schedule(Schedule $schedule): void
   {
       // ... existing tasks

       // Generate daily log summary at midnight
       $schedule->command('logs:analyze --report')->daily();
   }
   ```

6. **Test log analysis tools**

   ```bash
   # Test shell script
   ./scripts/analyze-logs.sh
   ./scripts/analyze-logs.sh --errors-only
   ./scripts/analyze-logs.sh --slow-queries

   # Test Laravel commands
   php artisan logs:analyze
   php artisan logs:analyze --errors
   php artisan logs:analyze --report

   # Test top errors command
   php artisan errors:top
   php artisan errors:top --hours=48 --limit=20
   ```

### Acceptance Criteria

- [ ] Shell script `analyze-logs.sh` created
- [ ] Parses logs for errors, slow queries, patterns
- [ ] LogAnalyzer command created
- [ ] Shows log level distribution
- [ ] Shows top 10 errors
- [ ] Shows slow queries
- [ ] ShowTopErrors command created
- [ ] Queries from error_tracking table
- [ ] Daily summary report generated automatically
- [ ] All commands tested and working

---

## Sprint 11 Summary

**Completion Checklist**:

- [ ] Task 11.1: Health check endpoint implemented
- [ ] Task 11.2: Performance monitoring enabled
- [ ] Task 11.3: Uptime monitoring configured
- [ ] Task 11.4: Alert system operational
- [ ] Task 11.5: Logging channels configured
- [ ] Task 11.6: Log rotation setup
- [ ] Task 11.7: Error rate monitoring active
- [ ] Task 11.8: Log analysis tools created

**Testing Checklist**:

- [ ] `/api/health` endpoint returns comprehensive status
- [ ] Performance metrics being collected
- [ ] Uptime monitoring script working
- [ ] Alerts sent via email/Slack
- [ ] All log channels writing correctly
- [ ] Log rotation configured and tested
- [ ] Error tracking table populated
- [ ] Log analysis commands working

**Production Readiness**:

- [ ] All monitoring services started
- [ ] Cron jobs configured
- [ ] Alert emails/Slack configured
- [ ] Dashboards accessible
- [ ] Documentation complete

---

**Next Steps**: Proceed to [Sprint 12: Documentation & Go-Live](SPRINT_12_DOCS_GOLIVE.md)

**Need Help?**: Review [Production Sprints Index](PRODUCTION_SPRINTS_INDEX.md) for overview
