# Live Notifications System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement real-time job notifications using Laravel Horizon for queue management, Laravel Reverb for WebSocket broadcasting, and a Livewire notification panel for user-facing UI.

**Architecture:** All 19 jobs broadcast progress events (start, progress, complete, failed) via WebSockets to user-specific private channels. Notifications are ephemeral by default, with failures and completions persisted to database. A slide-out notification panel displays real-time updates with detailed progress (stage + percentage + current item).

**Tech Stack:** Laravel Horizon, Laravel Reverb, Laravel Echo, Livewire 3, Alpine.js, PostgreSQL

---

## Phase 1: Infrastructure Setup

### Task 1.1: Install Laravel Horizon

**Files:**
- Modify: `composer.json`
- Create: `config/horizon.php` (via publish)
- Create: `app/Providers/HorizonServiceProvider.php`

**Step 1: Install Horizon via Composer**

Run:
```bash
composer require laravel/horizon
```

Expected: Package installed successfully

**Step 2: Publish Horizon assets and config**

Run:
```bash
php artisan horizon:install
```

Expected: Creates `config/horizon.php` and `app/Providers/HorizonServiceProvider.php`

**Step 3: Verify installation**

Run:
```bash
php artisan horizon --help
```

Expected: Shows Horizon command help

**Step 4: Commit**

```bash
git add composer.json composer.lock config/horizon.php app/Providers/HorizonServiceProvider.php
git commit -m "feat: Install Laravel Horizon for queue management"
```

---

### Task 1.2: Configure Horizon for Project Queues

**Files:**
- Modify: `config/horizon.php`

**Step 1: Read current queue configuration**

Read `config/queue.php` to understand existing queue names.

**Step 2: Update Horizon configuration**

Edit `config/horizon.php` to configure supervisors for existing queues:

```php
<?php

use Illuminate\Support\Str;

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => 'horizon',
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'),
    'middleware' => ['web', 'auth'],
    'waits' => [
        'redis:default' => 60,
    ],
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],
    'silenced' => [],
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],
    'fast_termination' => false,
    'memory_limit' => 128,
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 3,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],
    'environments' => [
        'production' => [
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 90,
            ],
            'supervisor-textract' => [
                'connection' => 'redis',
                'queue' => ['textract', 'textract-high', 'textract-tables'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 5,
                'tries' => 3,
                'timeout' => 1800,
            ],
            'supervisor-embeddings' => [
                'connection' => 'redis',
                'queue' => ['embeddings'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'tries' => 3,
                'timeout' => 300,
            ],
            'supervisor-research' => [
                'connection' => 'redis',
                'queue' => ['research', 'agent-research'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'tries' => 3,
                'timeout' => 600,
            ],
            'supervisor-graph' => [
                'connection' => 'redis',
                'queue' => ['graph-sync'],
                'balance' => 'simple',
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'tries' => 3,
                'timeout' => 300,
            ],
        ],
        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'textract', 'textract-high', 'textract-tables', 'embeddings', 'research', 'agent-research', 'graph-sync'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'tries' => 3,
                'timeout' => 1800,
            ],
        ],
    ],
];
```

**Step 3: Run test to verify config loads**

Run:
```bash
php artisan config:cache && php artisan config:clear
```

Expected: No errors

**Step 4: Commit**

```bash
git add config/horizon.php
git commit -m "feat: Configure Horizon supervisors for project queues"
```

---

### Task 1.3: Configure Horizon Authentication

**Files:**
- Modify: `app/Providers/HorizonServiceProvider.php`

**Step 1: Update HorizonServiceProvider for open access**

Edit `app/Providers/HorizonServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon notification settings (optional)
        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            // All authenticated users can view Horizon (per design decision)
            return true;
        });
    }
}
```

**Step 2: Verify Horizon dashboard accessible**

Run:
```bash
php artisan serve &
# Visit http://localhost:8000/horizon in browser
```

Expected: Horizon dashboard loads (requires authentication)

**Step 3: Commit**

```bash
git add app/Providers/HorizonServiceProvider.php
git commit -m "feat: Configure Horizon authentication for all users"
```

---

### Task 1.4: Install Laravel Reverb

**Files:**
- Modify: `composer.json`
- Create: `config/reverb.php` (via publish)
- Modify: `.env.example`

**Step 1: Install Reverb via Composer**

Run:
```bash
composer require laravel/reverb
```

Expected: Package installed successfully

**Step 2: Install and publish Reverb**

Run:
```bash
php artisan reverb:install
```

Expected: Creates `config/reverb.php`, updates `config/broadcasting.php`

**Step 3: Verify installation**

Run:
```bash
php artisan reverb --help
```

Expected: Shows Reverb command help

**Step 4: Commit**

```bash
git add composer.json composer.lock config/reverb.php config/broadcasting.php
git commit -m "feat: Install Laravel Reverb for WebSocket broadcasting"
```

---

### Task 1.5: Configure Broadcasting

**Files:**
- Modify: `config/broadcasting.php`
- Modify: `.env.example`
- Modify: `.env` (local only)

**Step 1: Update .env.example with Reverb settings**

Add to `.env.example`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=ai-legal-war-machine
REVERB_APP_KEY=local-reverb-key
REVERB_APP_SECRET=local-reverb-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**Step 2: Verify broadcasting config**

Run:
```bash
php artisan config:show broadcasting
```

Expected: Shows reverb as available connection

**Step 3: Commit**

```bash
git add .env.example config/broadcasting.php
git commit -m "feat: Configure broadcasting for Reverb WebSockets"
```

---

### Task 1.6: Install Frontend Broadcasting Dependencies

**Files:**
- Modify: `package.json`
- Modify: `resources/js/bootstrap.js`

**Step 1: Install Laravel Echo and Pusher JS**

Run:
```bash
npm install --save-dev laravel-echo pusher-js
```

Expected: Packages added to package.json

**Step 2: Configure Echo in bootstrap.js**

Edit `resources/js/bootstrap.js`:

```javascript
import axios from 'axios';
import {Timeline} from "vis-timeline/peer";
import {DataSet, DataView, Queue} from "vis-data";
import "vis-timeline/styles/vis-timeline-graph2d.css";
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.dataset = DataSet;
window.timeline = Timeline;
window.Pusher = Pusher;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Configure Laravel Echo with Reverb
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

**Step 3: Build assets**

Run:
```bash
npm run build
```

Expected: Build completes without errors

**Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/bootstrap.js
git commit -m "feat: Configure Laravel Echo for frontend WebSocket support"
```

---

### Task 1.7: Switch Queue Driver to Redis

**Files:**
- Modify: `.env.example`
- Modify: `config/queue.php`

**Step 1: Update .env.example**

Change in `.env.example`:

```env
QUEUE_CONNECTION=redis
```

**Step 2: Verify Redis configuration exists in queue.php**

Read `config/queue.php` and ensure redis connection is properly configured.

**Step 3: Commit**

```bash
git add .env.example
git commit -m "feat: Configure Redis as default queue driver for Horizon"
```

---

## Phase 2: Notification Infrastructure

### Task 2.1: Create Job Notification Events

**Files:**
- Create: `app/Events/JobStarted.php`
- Create: `app/Events/JobProgress.php`
- Create: `app/Events/JobCompleted.php`
- Create: `app/Events/JobFailed.php`

**Step 1: Write test for JobStarted event**

Create `tests/Unit/Events/JobStartedTest.php`:

```php
<?php

namespace Tests\Unit\Events;

use App\Events\JobStarted;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class JobStartedTest extends TestCase
{
    public function test_job_started_event_broadcasts_on_private_channel(): void
    {
        $event = new JobStarted(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            metadata: ['file_name' => 'document.pdf']
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.1.jobs', $channels[0]->name);
    }

    public function test_job_started_event_has_correct_broadcast_data(): void
    {
        $event = new JobStarted(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            metadata: ['file_name' => 'document.pdf']
        );

        $data = $event->broadcastWith();

        $this->assertEquals('job-123', $data['job_id']);
        $this->assertEquals('ProcessTextractJob', $data['job_type']);
        $this->assertEquals('Processing document.pdf', $data['job_name']);
        $this->assertEquals('started', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('metadata', $data);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
./scripts/run-focused-tests.sh JobStartedTest
```

Expected: FAIL with "Class 'App\Events\JobStarted' not found"

**Step 3: Create JobStarted event**

Create `app/Events/JobStarted.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $jobId,
        public string $jobType,
        public string $jobName,
        public array $metadata = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId.'.jobs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job.started';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'job_name' => $this->jobName,
            'status' => 'started',
            'progress' => 0,
            'stage' => 'Initializing',
            'metadata' => $this->metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

**Step 4: Run test to verify it passes**

Run:
```bash
./scripts/run-focused-tests.sh JobStartedTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add tests/Unit/Events/JobStartedTest.php app/Events/JobStarted.php
git commit -m "feat: Add JobStarted broadcast event"
```

**Step 6: Write test for JobProgress event**

Create `tests/Unit/Events/JobProgressTest.php`:

```php
<?php

namespace Tests\Unit\Events;

use App\Events\JobProgress;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class JobProgressTest extends TestCase
{
    public function test_job_progress_event_broadcasts_on_private_channel(): void
    {
        $event = new JobProgress(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            progress: 50,
            stage: 'Extracting text',
            currentItem: 'Page 5 of 10',
            metadata: []
        );

        $channels = $event->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.1.jobs', $channels[0]->name);
    }

    public function test_job_progress_event_includes_detailed_progress(): void
    {
        $event = new JobProgress(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            progress: 50,
            stage: 'Extracting text',
            currentItem: 'Page 5 of 10',
            metadata: ['total_pages' => 10]
        );

        $data = $event->broadcastWith();

        $this->assertEquals(50, $data['progress']);
        $this->assertEquals('Extracting text', $data['stage']);
        $this->assertEquals('Page 5 of 10', $data['current_item']);
        $this->assertEquals('in_progress', $data['status']);
    }
}
```

**Step 7: Run test to verify it fails**

Run:
```bash
./scripts/run-focused-tests.sh JobProgressTest
```

Expected: FAIL

**Step 8: Create JobProgress event**

Create `app/Events/JobProgress.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $jobId,
        public string $jobType,
        public string $jobName,
        public int $progress,
        public string $stage,
        public ?string $currentItem = null,
        public array $metadata = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId.'.jobs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'job_name' => $this->jobName,
            'status' => 'in_progress',
            'progress' => $this->progress,
            'stage' => $this->stage,
            'current_item' => $this->currentItem,
            'metadata' => $this->metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

**Step 9: Run test to verify it passes**

Run:
```bash
./scripts/run-focused-tests.sh JobProgressTest
```

Expected: PASS

**Step 10: Commit**

```bash
git add tests/Unit/Events/JobProgressTest.php app/Events/JobProgress.php
git commit -m "feat: Add JobProgress broadcast event with detailed progress"
```

**Step 11: Write test for JobCompleted event**

Create `tests/Unit/Events/JobCompletedTest.php`:

```php
<?php

namespace Tests\Unit\Events;

use App\Events\JobCompleted;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class JobCompletedTest extends TestCase
{
    public function test_job_completed_event_broadcasts_correctly(): void
    {
        $event = new JobCompleted(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            result: ['pages_processed' => 10, 'tables_found' => 3],
            metadata: []
        );

        $data = $event->broadcastWith();

        $this->assertEquals('completed', $data['status']);
        $this->assertEquals(100, $data['progress']);
        $this->assertEquals('Complete', $data['stage']);
        $this->assertArrayHasKey('result', $data);
    }

    public function test_job_completed_event_is_marked_for_persistence(): void
    {
        $event = new JobCompleted(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            result: [],
            metadata: []
        );

        $this->assertTrue($event->shouldPersist);
    }
}
```

**Step 12: Run test to verify it fails**

Run:
```bash
./scripts/run-focused-tests.sh JobCompletedTest
```

Expected: FAIL

**Step 13: Create JobCompleted event**

Create `app/Events/JobCompleted.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $shouldPersist = true;

    public function __construct(
        public int $userId,
        public string $jobId,
        public string $jobType,
        public string $jobName,
        public array $result = [],
        public array $metadata = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId.'.jobs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'job_name' => $this->jobName,
            'status' => 'completed',
            'progress' => 100,
            'stage' => 'Complete',
            'current_item' => null,
            'result' => $this->result,
            'metadata' => $this->metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

**Step 14: Run test to verify it passes**

Run:
```bash
./scripts/run-focused-tests.sh JobCompletedTest
```

Expected: PASS

**Step 15: Commit**

```bash
git add tests/Unit/Events/JobCompletedTest.php app/Events/JobCompleted.php
git commit -m "feat: Add JobCompleted broadcast event with persistence flag"
```

**Step 16: Write test for JobFailed event**

Create `tests/Unit/Events/JobFailedTest.php`:

```php
<?php

namespace Tests\Unit\Events;

use App\Events\JobFailed;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class JobFailedTest extends TestCase
{
    public function test_job_failed_event_broadcasts_correctly(): void
    {
        $event = new JobFailed(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            error: 'Connection timeout',
            stage: 'Uploading to S3',
            metadata: []
        );

        $data = $event->broadcastWith();

        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('Connection timeout', $data['error']);
        $this->assertEquals('Uploading to S3', $data['stage']);
    }

    public function test_job_failed_event_is_marked_for_persistence(): void
    {
        $event = new JobFailed(
            userId: 1,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            error: 'Error',
            stage: 'Processing',
            metadata: []
        );

        $this->assertTrue($event->shouldPersist);
    }
}
```

**Step 17: Run test to verify it fails**

Run:
```bash
./scripts/run-focused-tests.sh JobFailedTest
```

Expected: FAIL

**Step 18: Create JobFailed event**

Create `app/Events/JobFailed.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $shouldPersist = true;

    public function __construct(
        public int $userId,
        public string $jobId,
        public string $jobType,
        public string $jobName,
        public string $error,
        public string $stage,
        public array $metadata = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId.'.jobs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'job_name' => $this->jobName,
            'status' => 'failed',
            'progress' => 0,
            'stage' => $this->stage,
            'current_item' => null,
            'error' => $this->error,
            'metadata' => $this->metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

**Step 19: Run test to verify it passes**

Run:
```bash
./scripts/run-focused-tests.sh JobFailedTest
```

Expected: PASS

**Step 20: Commit**

```bash
git add tests/Unit/Events/JobFailedTest.php app/Events/JobFailed.php
git commit -m "feat: Add JobFailed broadcast event with persistence flag"
```

---

### Task 2.2: Create Channel Authorization

**Files:**
- Create: `routes/channels.php` (or modify if exists)

**Step 1: Check if channels.php exists**

Run:
```bash
ls routes/channels.php
```

**Step 2: Create or update channels.php**

Create/Edit `routes/channels.php`:

```php
<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Private channel for user-specific job notifications
Broadcast::channel('user.{userId}.jobs', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

**Step 3: Verify channel registration**

Run:
```bash
php artisan route:list --name=broadcast
```

Expected: Shows broadcasting routes

**Step 4: Commit**

```bash
git add routes/channels.php
git commit -m "feat: Add broadcast channel authorization for user job notifications"
```

---

### Task 2.3: Create Job Notification Database Migration

**Files:**
- Create: `database/migrations/xxxx_xx_xx_create_job_notifications_table.php`

**Step 1: Create migration**

Run:
```bash
php artisan make:migration create_job_notifications_table
```

**Step 2: Edit migration file**

Edit the created migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('job_id');
            $table->string('job_type');
            $table->string('job_name');
            $table->string('status'); // completed, failed
            $table->string('stage')->nullable();
            $table->text('error')->nullable();
            $table->json('result')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read']);
            $table->index(['user_id', 'created_at']);
            $table->index('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_notifications');
    }
};
```

**Step 3: Run migration**

Run:
```bash
php artisan migrate
```

Expected: Migration runs successfully

**Step 4: Commit**

```bash
git add database/migrations/*_create_job_notifications_table.php
git commit -m "feat: Add job_notifications table migration"
```

---

### Task 2.4: Create JobNotification Model

**Files:**
- Create: `app/Models/JobNotification.php`
- Create: `tests/Unit/Models/JobNotificationTest.php`

**Step 1: Write test for JobNotification model**

Create `tests/Unit/Models/JobNotificationTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\JobNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_notification_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $notification = JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-123',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Processing document.pdf',
            'status' => 'completed',
        ]);

        $this->assertInstanceOf(User::class, $notification->user);
        $this->assertEquals($user->id, $notification->user->id);
    }

    public function test_job_notification_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();
        $notification = JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-123',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Processing document.pdf',
            'status' => 'completed',
        ]);

        $this->assertFalse($notification->read);

        $notification->markAsRead();

        $this->assertTrue($notification->fresh()->read);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_unread_scope_returns_only_unread_notifications(): void
    {
        $user = User::factory()->create();

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-1',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 1',
            'status' => 'completed',
            'read' => false,
        ]);

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-2',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 2',
            'status' => 'completed',
            'read' => true,
        ]);

        $unread = JobNotification::unread()->get();

        $this->assertCount(1, $unread);
        $this->assertEquals('job-1', $unread->first()->job_id);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
./scripts/run-focused-tests.sh JobNotificationTest
```

Expected: FAIL

**Step 3: Create JobNotification model**

Create `app/Models/JobNotification.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'job_id',
        'job_type',
        'job_name',
        'status',
        'stage',
        'error',
        'result',
        'metadata',
        'read',
        'read_at',
    ];

    protected $casts = [
        'result' => 'array',
        'metadata' => 'array',
        'read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        $this->update([
            'read' => true,
            'read_at' => now(),
        ]);
    }

    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
```

**Step 4: Run test to verify it passes**

Run:
```bash
./scripts/run-focused-tests.sh JobNotificationTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/JobNotification.php tests/Unit/Models/JobNotificationTest.php
git commit -m "feat: Add JobNotification model with read/unread functionality"
```

---

### Task 2.5: Create Notification Persistence Listener

**Files:**
- Create: `app/Listeners/PersistJobNotification.php`
- Create: `tests/Unit/Listeners/PersistJobNotificationTest.php`

**Step 1: Write test for PersistJobNotification listener**

Create `tests/Unit/Listeners/PersistJobNotificationTest.php`:

```php
<?php

namespace Tests\Unit\Listeners;

use App\Events\JobCompleted;
use App\Events\JobFailed;
use App\Events\JobProgress;
use App\Listeners\PersistJobNotification;
use App\Models\JobNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersistJobNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_job_completed_event(): void
    {
        $user = User::factory()->create();
        $event = new JobCompleted(
            userId: $user->id,
            jobId: 'job-123',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing document.pdf',
            result: ['pages' => 10],
            metadata: []
        );

        $listener = new PersistJobNotification();
        $listener->handle($event);

        $this->assertDatabaseHas('job_notifications', [
            'user_id' => $user->id,
            'job_id' => 'job-123',
            'job_type' => 'ProcessTextractJob',
            'status' => 'completed',
        ]);
    }

    public function test_persists_job_failed_event(): void
    {
        $user = User::factory()->create();
        $event = new JobFailed(
            userId: $user->id,
            jobId: 'job-456',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing failed.pdf',
            error: 'Connection timeout',
            stage: 'Uploading',
            metadata: []
        );

        $listener = new PersistJobNotification();
        $listener->handle($event);

        $this->assertDatabaseHas('job_notifications', [
            'user_id' => $user->id,
            'job_id' => 'job-456',
            'status' => 'failed',
            'error' => 'Connection timeout',
        ]);
    }

    public function test_does_not_persist_progress_events(): void
    {
        $user = User::factory()->create();
        $event = new JobProgress(
            userId: $user->id,
            jobId: 'job-789',
            jobType: 'ProcessTextractJob',
            jobName: 'Processing doc.pdf',
            progress: 50,
            stage: 'Processing',
            currentItem: 'Page 5',
            metadata: []
        );

        $listener = new PersistJobNotification();
        $listener->handle($event);

        $this->assertDatabaseMissing('job_notifications', [
            'job_id' => 'job-789',
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
./scripts/run-focused-tests.sh PersistJobNotificationTest
```

Expected: FAIL

**Step 3: Create PersistJobNotification listener**

Create `app/Listeners/PersistJobNotification.php`:

```php
<?php

namespace App\Listeners;

use App\Events\JobCompleted;
use App\Events\JobFailed;
use App\Models\JobNotification;

class PersistJobNotification
{
    public function handle($event): void
    {
        // Only persist events marked for persistence
        if (!property_exists($event, 'shouldPersist') || !$event->shouldPersist) {
            return;
        }

        $data = [
            'user_id' => $event->userId,
            'job_id' => $event->jobId,
            'job_type' => $event->jobType,
            'job_name' => $event->jobName,
            'metadata' => $event->metadata,
        ];

        if ($event instanceof JobCompleted) {
            $data['status'] = 'completed';
            $data['result'] = $event->result;
        } elseif ($event instanceof JobFailed) {
            $data['status'] = 'failed';
            $data['error'] = $event->error;
            $data['stage'] = $event->stage;
        }

        JobNotification::create($data);
    }
}
```

**Step 4: Run test to verify it passes**

Run:
```bash
./scripts/run-focused-tests.sh PersistJobNotificationTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Listeners/PersistJobNotification.php tests/Unit/Listeners/PersistJobNotificationTest.php
git commit -m "feat: Add PersistJobNotification listener for hybrid storage"
```

---

### Task 2.6: Register Event Listeners

**Files:**
- Modify: `app/Providers/EventServiceProvider.php`

**Step 1: Read current EventServiceProvider**

Read `app/Providers/EventServiceProvider.php` to understand current structure.

**Step 2: Register listeners**

Add to `$listen` array in EventServiceProvider:

```php
use App\Events\JobCompleted;
use App\Events\JobFailed;
use App\Listeners\PersistJobNotification;

protected $listen = [
    // ... existing listeners ...
    JobCompleted::class => [
        PersistJobNotification::class,
    ],
    JobFailed::class => [
        PersistJobNotification::class,
    ],
];
```

**Step 3: Commit**

```bash
git add app/Providers/EventServiceProvider.php
git commit -m "feat: Register job notification event listeners"
```

---

## Phase 3: Job Broadcasting Trait

### Task 3.1: Create BroadcastsJobProgress Trait

**Files:**
- Create: `app/Traits/BroadcastsJobProgress.php`
- Create: `tests/Unit/Traits/BroadcastsJobProgressTest.php`

**Step 1: Write test for trait**

Create `tests/Unit/Traits/BroadcastsJobProgressTest.php`:

```php
<?php

namespace Tests\Unit\Traits;

use App\Events\JobCompleted;
use App\Events\JobFailed;
use App\Events\JobProgress;
use App\Events\JobStarted;
use App\Models\User;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastsJobProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcasts_job_started_event(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $job = new class {
            use BroadcastsJobProgress;

            public function getJobDisplayName(): string
            {
                return 'Test Job';
            }
        };

        $job->broadcastStarted($user->id, 'test-job-id', ['test' => 'data']);

        Event::assertDispatched(JobStarted::class, function ($event) use ($user) {
            return $event->userId === $user->id
                && $event->jobId === 'test-job-id'
                && $event->jobName === 'Test Job';
        });
    }

    public function test_broadcasts_job_progress_event(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $job = new class {
            use BroadcastsJobProgress;

            public function getJobDisplayName(): string
            {
                return 'Test Job';
            }
        };

        $job->broadcastProgress($user->id, 'test-job-id', 50, 'Processing', 'Item 5 of 10');

        Event::assertDispatched(JobProgress::class, function ($event) {
            return $event->progress === 50
                && $event->stage === 'Processing'
                && $event->currentItem === 'Item 5 of 10';
        });
    }

    public function test_broadcasts_job_completed_event(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $job = new class {
            use BroadcastsJobProgress;

            public function getJobDisplayName(): string
            {
                return 'Test Job';
            }
        };

        $job->broadcastCompleted($user->id, 'test-job-id', ['pages' => 10]);

        Event::assertDispatched(JobCompleted::class, function ($event) {
            return $event->result === ['pages' => 10];
        });
    }

    public function test_broadcasts_job_failed_event(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $job = new class {
            use BroadcastsJobProgress;

            public function getJobDisplayName(): string
            {
                return 'Test Job';
            }
        };

        $job->broadcastFailed($user->id, 'test-job-id', 'Connection error', 'Uploading');

        Event::assertDispatched(JobFailed::class, function ($event) {
            return $event->error === 'Connection error'
                && $event->stage === 'Uploading';
        });
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
./scripts/run-focused-tests.sh BroadcastsJobProgressTest
```

Expected: FAIL

**Step 3: Create BroadcastsJobProgress trait**

Create `app/Traits/BroadcastsJobProgress.php`:

```php
<?php

namespace App\Traits;

use App\Events\JobCompleted;
use App\Events\JobFailed;
use App\Events\JobProgress;
use App\Events\JobStarted;

trait BroadcastsJobProgress
{
    /**
     * Get the display name for this job (override in job class)
     */
    abstract public function getJobDisplayName(): string;

    /**
     * Broadcast that the job has started
     */
    protected function broadcastStarted(int $userId, string $jobId, array $metadata = []): void
    {
        event(new JobStarted(
            userId: $userId,
            jobId: $jobId,
            jobType: class_basename($this),
            jobName: $this->getJobDisplayName(),
            metadata: $metadata
        ));
    }

    /**
     * Broadcast job progress update
     */
    protected function broadcastProgress(
        int $userId,
        string $jobId,
        int $progress,
        string $stage,
        ?string $currentItem = null,
        array $metadata = []
    ): void {
        event(new JobProgress(
            userId: $userId,
            jobId: $jobId,
            jobType: class_basename($this),
            jobName: $this->getJobDisplayName(),
            progress: $progress,
            stage: $stage,
            currentItem: $currentItem,
            metadata: $metadata
        ));
    }

    /**
     * Broadcast that the job has completed
     */
    protected function broadcastCompleted(int $userId, string $jobId, array $result = [], array $metadata = []): void
    {
        event(new JobCompleted(
            userId: $userId,
            jobId: $jobId,
            jobType: class_basename($this),
            jobName: $this->getJobDisplayName(),
            result: $result,
            metadata: $metadata
        ));
    }

    /**
     * Broadcast that the job has failed
     */
    protected function broadcastFailed(
        int $userId,
        string $jobId,
        string $error,
        string $stage,
        array $metadata = []
    ): void {
        event(new JobFailed(
            userId: $userId,
            jobId: $jobId,
            jobType: class_basename($this),
            jobName: $this->getJobDisplayName(),
            error: $error,
            stage: $stage,
            metadata: $metadata
        ));
    }
}
```

**Step 4: Run test to verify it passes**

Run:
```bash
./scripts/run-focused-tests.sh BroadcastsJobProgressTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Traits/BroadcastsJobProgress.php tests/Unit/Traits/BroadcastsJobProgressTest.php
git commit -m "feat: Add BroadcastsJobProgress trait for job notification broadcasting"
```

---

### Task 3.2: Integrate Broadcasting into ProcessTextractJob

**Files:**
- Modify: `app/Jobs/ProcessTextractJob.php`

**Step 1: Read current ProcessTextractJob**

Already read in exploration phase.

**Step 2: Update ProcessTextractJob with broadcasting**

Edit `app/Jobs/ProcessTextractJob.php`:

```php
<?php

namespace App\Jobs;

use App\Actions\Textract\ProcessDrivePdf;
use App\Models\TextractBatch;
use App\Models\TextractJob;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTextractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsJobProgress;

    public $tries = 3;
    public $backoff = [60, 300, 900];
    public $timeout = 1800;

    protected string $jobId;
    protected ?string $batchId;
    protected ?int $userId = null;
    protected ?string $fileName = null;

    public function __construct(string $jobId, ?string $batchId = null)
    {
        $this->jobId = $jobId;
        $this->batchId = $batchId;

        $job = TextractJob::find($jobId);
        if ($job) {
            $this->onQueue($job->queue_name ?? 'textract');
            $this->userId = $job->user_id;
            $this->fileName = $job->drive_file_name;
        }
    }

    public function getJobDisplayName(): string
    {
        return 'Processing: ' . ($this->fileName ?? 'Document');
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        $job = TextractJob::find($this->jobId);

        if (!$job) {
            Log::error('ProcessTextractJob - Job not found', ['job_id' => $this->jobId]);
            return;
        }

        // Determine user for broadcasting (job owner or fallback)
        $userId = $job->user_id ?? $this->userId;

        try {
            // Broadcast start
            if ($userId) {
                $this->broadcastStarted($userId, $this->jobId, [
                    'file_name' => $job->drive_file_name,
                    'batch_id' => $this->batchId,
                ]);
            }

            // Mark as processing
            $job->update([
                'status' => 'processing',
                'processing_started_at' => now(),
                'worker_id' => getmypid(),
                'retry_count' => $this->attempts() - 1,
            ]);

            // Broadcast progress: Initializing
            if ($userId) {
                $this->broadcastProgress($userId, $this->jobId, 10, 'Initializing', 'Loading document metadata');
            }

            Log::info('ProcessTextractJob - Starting', [
                'job_id' => $this->jobId,
                'drive_file_id' => $job->drive_file_id,
                'drive_file_name' => $job->drive_file_name,
                'attempt' => $this->attempts(),
                'worker_id' => getmypid(),
            ]);

            // Broadcast progress: Processing
            if ($userId) {
                $this->broadcastProgress($userId, $this->jobId, 30, 'Processing', 'Running Textract OCR');
            }

            // Execute the ProcessDrivePdf pipeline
            app(ProcessDrivePdf::class)->handle(
                $job->drive_file_id,
                $job->drive_file_name,
                true
            );

            // Broadcast progress: Finalizing
            if ($userId) {
                $this->broadcastProgress($userId, $this->jobId, 80, 'Finalizing', 'Storing results');
            }

            // Reload and update metadata
            $job->refresh();

            $jsonPrefix = trim(env('S3_JSON_PREFIX', 'textract/json'), '/');
            $inputPrefix = trim(env('S3_INPUT_PREFIX', 'textract/input'), '/');
            $outputPrefix = trim(env('S3_OUTPUT_PREFIX', 'textract/output'), '/');

            $metadata = $job->metadata ?? [];
            $metadata['s3_json_key'] = $jsonPrefix.'/'.$job->drive_file_id.'.json';
            $metadata['s3_input_key'] = $inputPrefix.'/'.$job->drive_file_id.'.pdf';
            $metadata['s3_output_key'] = $outputPrefix.'/'.$job->drive_file_id.'.pdf';
            $metadata['processed_at'] = now()->toIso8601String();

            $duration = microtime(true) - $startTime;

            $metrics = [
                'duration_seconds' => round($duration, 2),
                'pages_processed' => $metadata['page_count'] ?? 0,
                'blocks_extracted' => $metadata['block_count'] ?? 0,
                'tables_extracted' => count($metadata['tables'] ?? []),
                'worker_id' => getmypid(),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'completed_at' => now()->toIso8601String(),
            ];

            $job->update([
                'status' => 'completed',
                'metadata' => $metadata,
                'performance_metrics' => $metrics,
            ]);

            // Update batch if exists
            if ($this->batchId) {
                $batch = TextractBatch::find($this->batchId);
                if ($batch) {
                    $batch->incrementProcessed(false);
                }
            }

            Log::info('ProcessTextractJob - Completed', [
                'job_id' => $this->jobId,
                'metrics' => $metrics,
            ]);

            // Broadcast completion
            if ($userId) {
                $this->broadcastCompleted($userId, $this->jobId, [
                    'pages_processed' => $metrics['pages_processed'],
                    'tables_extracted' => $metrics['tables_extracted'],
                    'duration_seconds' => $metrics['duration_seconds'],
                ], ['file_name' => $job->drive_file_name]);
            }

            // Dispatch follow-up jobs
            $this->dispatchFollowUpJobs($job);

        } catch (\Exception $e) {
            Log::error('ProcessTextractJob - Failed', [
                'job_id' => $this->jobId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $job->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            // Broadcast failure
            if ($userId) {
                $this->broadcastFailed(
                    $userId,
                    $this->jobId,
                    $e->getMessage(),
                    'Processing',
                    ['file_name' => $job->drive_file_name]
                );
            }

            // Update batch if exists
            if ($this->batchId) {
                $batch = TextractBatch::find($this->batchId);
                if ($batch) {
                    $batch->incrementProcessed(true);
                }
            }

            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    protected function dispatchFollowUpJobs(TextractJob $job): void
    {
        if (config('distributed-processing.textract.auto_extract_tables', true)) {
            ExtractTablesFromTextractJob::dispatch($job->id)
                ->onQueue('textract-tables')
                ->delay(now()->addSeconds(10));
        }

        if (config('distributed-processing.textract.auto_generate_embeddings', true)) {
            GenerateEmbeddingsJob::dispatch($job->id)
                ->onQueue('embeddings')
                ->delay(now()->addSeconds(30));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessTextractJob - Permanently failed', [
            'job_id' => $this->jobId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        $job = TextractJob::find($this->jobId);
        if ($job) {
            $job->update([
                'status' => 'failed',
                'error' => 'Failed after '.$this->tries.' attempts: '.$exception->getMessage(),
            ]);

            // Broadcast final failure
            if ($job->user_id) {
                $this->broadcastFailed(
                    $job->user_id,
                    $this->jobId,
                    'Failed after '.$this->tries.' attempts: '.$exception->getMessage(),
                    'Permanently Failed',
                    ['file_name' => $job->drive_file_name]
                );
            }
        }
    }

    public function tags(): array
    {
        return ['textract', 'job:'.$this->jobId, 'batch:'.($this->batchId ?? 'none')];
    }
}
```

**Step 3: Run existing tests**

Run:
```bash
./scripts/run-focused-tests.sh ProcessTextractJob
```

Expected: PASS (or investigate failures)

**Step 4: Commit**

```bash
git add app/Jobs/ProcessTextractJob.php
git commit -m "feat: Add broadcasting to ProcessTextractJob"
```

---

## Phase 4: Notification UI Components

### Task 4.1: Create NotificationPanel Livewire Component

**Files:**
- Create: `app/Livewire/NotificationPanel.php`
- Create: `resources/views/livewire/notification-panel.blade.php`
- Create: `tests/Feature/Livewire/NotificationPanelTest.php`

**Step 1: Write test for NotificationPanel**

Create `tests/Feature/Livewire/NotificationPanelTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\NotificationPanel;
use App\Models\JobNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->assertStatus(200);
    }

    public function test_shows_user_notifications(): void
    {
        $user = User::factory()->create();

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-123',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Processing test.pdf',
            'status' => 'completed',
        ]);

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->assertSee('Processing test.pdf')
            ->assertSee('completed');
    }

    public function test_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-123',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Processing test.pdf',
            'status' => 'completed',
            'read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->call('markAsRead', $notification->id);

        $this->assertTrue($notification->fresh()->read);
    }

    public function test_can_mark_all_as_read(): void
    {
        $user = User::factory()->create();

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-1',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 1',
            'status' => 'completed',
            'read' => false,
        ]);

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-2',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 2',
            'status' => 'failed',
            'read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->call('markAllAsRead');

        $this->assertEquals(0, JobNotification::forUser($user->id)->unread()->count());
    }

    public function test_can_toggle_panel(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->assertSet('isOpen', false)
            ->call('toggle')
            ->assertSet('isOpen', true)
            ->call('toggle')
            ->assertSet('isOpen', false);
    }

    public function test_shows_unread_count(): void
    {
        $user = User::factory()->create();

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-1',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 1',
            'status' => 'completed',
            'read' => false,
        ]);

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-2',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 2',
            'status' => 'completed',
            'read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->assertSee('2');
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
./scripts/run-focused-tests.sh NotificationPanelTest
```

Expected: FAIL

**Step 3: Create NotificationPanel component**

Create `app/Livewire/NotificationPanel.php`:

```php
<?php

namespace App\Livewire;

use App\Models\JobNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationPanel extends Component
{
    public bool $isOpen = false;

    // Real-time notifications (ephemeral)
    public array $liveNotifications = [];

    public function toggle(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    #[On('echo-private:user.{userId}.jobs,job.started')]
    public function handleJobStarted(array $data): void
    {
        $this->addLiveNotification($data);
    }

    #[On('echo-private:user.{userId}.jobs,job.progress')]
    public function handleJobProgress(array $data): void
    {
        $this->updateLiveNotification($data);
    }

    #[On('echo-private:user.{userId}.jobs,job.completed')]
    public function handleJobCompleted(array $data): void
    {
        $this->updateLiveNotification($data);
        // Remove from live after a delay (handled in frontend)
    }

    #[On('echo-private:user.{userId}.jobs,job.failed')]
    public function handleJobFailed(array $data): void
    {
        $this->updateLiveNotification($data);
    }

    protected function addLiveNotification(array $data): void
    {
        // Add to beginning of array
        array_unshift($this->liveNotifications, $data);

        // Keep only last 20 live notifications
        $this->liveNotifications = array_slice($this->liveNotifications, 0, 20);
    }

    protected function updateLiveNotification(array $data): void
    {
        $found = false;
        foreach ($this->liveNotifications as $key => $notification) {
            if ($notification['job_id'] === $data['job_id']) {
                $this->liveNotifications[$key] = $data;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->addLiveNotification($data);
        }
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = JobNotification::find($notificationId);

        if ($notification && $notification->user_id === auth()->id()) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead(): void
    {
        JobNotification::forUser(auth()->id())
            ->unread()
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);
    }

    public function dismissLiveNotification(string $jobId): void
    {
        $this->liveNotifications = array_filter(
            $this->liveNotifications,
            fn($n) => $n['job_id'] !== $jobId
        );
        $this->liveNotifications = array_values($this->liveNotifications);
    }

    public function clearCompleted(): void
    {
        $this->liveNotifications = array_filter(
            $this->liveNotifications,
            fn($n) => !in_array($n['status'], ['completed', 'failed'])
        );
        $this->liveNotifications = array_values($this->liveNotifications);
    }

    #[Computed]
    public function persistedNotifications(): Collection
    {
        return JobNotification::forUser(auth()->id())
            ->recent(7)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return JobNotification::forUser(auth()->id())
            ->unread()
            ->count();
    }

    #[Computed]
    public function userId(): int
    {
        return auth()->id();
    }

    public function render()
    {
        return view('livewire.notification-panel');
    }
}
```

**Step 4: Create notification panel view**

Create `resources/views/livewire/notification-panel.blade.php`:

```blade
<div class="relative" x-data="{ userId: {{ $this->userId }} }">
    {{-- Notification Bell Button --}}
    <button
        wire:click="toggle"
        class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        {{-- Unread Badge --}}
        @if($this->unreadCount > 0 || count($liveNotifications) > 0)
            <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full">
                {{ $this->unreadCount + count(array_filter($liveNotifications, fn($n) => $n['status'] === 'in_progress')) }}
            </span>
        @endif
    </button>

    {{-- Slide-out Panel --}}
    <div
        x-show="$wire.isOpen"
        x-transition:enter="transform transition ease-in-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in-out duration-300"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @click.away="$wire.close()"
        class="fixed inset-y-0 right-0 w-96 bg-white shadow-xl z-50 overflow-hidden flex flex-col"
        style="display: none;"
    >
        {{-- Header --}}
        <div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Notifications</h2>
            <div class="flex items-center space-x-2">
                @if($this->unreadCount > 0)
                    <button
                        wire:click="markAllAsRead"
                        class="text-sm text-indigo-600 hover:text-indigo-800"
                    >
                        Mark all read
                    </button>
                @endif
                <button
                    wire:click="close"
                    class="text-gray-500 hover:text-gray-700"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Content --}}
        <div class="flex-1 overflow-y-auto">
            {{-- Live Notifications Section --}}
            @if(count($liveNotifications) > 0)
                <div class="px-4 py-2 bg-blue-50 border-b">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-blue-800">Active Jobs</span>
                        <button
                            wire:click="clearCompleted"
                            class="text-xs text-blue-600 hover:text-blue-800"
                        >
                            Clear completed
                        </button>
                    </div>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($liveNotifications as $notification)
                        <div class="px-4 py-3 hover:bg-gray-50 transition-colors {{ $notification['status'] === 'failed' ? 'bg-red-50' : '' }}">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        {{ $notification['job_name'] }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ $notification['job_type'] }}
                                    </p>
                                </div>
                                <button
                                    wire:click="dismissLiveNotification('{{ $notification['job_id'] }}')"
                                    class="ml-2 text-gray-400 hover:text-gray-600"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Status Badge --}}
                            <div class="mt-2 flex items-center">
                                @if($notification['status'] === 'in_progress')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        <svg class="animate-spin -ml-0.5 mr-1.5 h-3 w-3 text-blue-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ $notification['stage'] }}
                                    </span>
                                @elseif($notification['status'] === 'started')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Starting...
                                    </span>
                                @elseif($notification['status'] === 'completed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        Completed
                                    </span>
                                @elseif($notification['status'] === 'failed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                        Failed
                                    </span>
                                @endif
                            </div>

                            {{-- Progress Bar --}}
                            @if($notification['status'] === 'in_progress' && isset($notification['progress']))
                                <div class="mt-2">
                                    <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                        <span>{{ $notification['current_item'] ?? $notification['stage'] }}</span>
                                        <span>{{ $notification['progress'] }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div
                                            class="bg-blue-600 h-1.5 rounded-full transition-all duration-300"
                                            style="width: {{ $notification['progress'] }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endif

                            {{-- Error Message --}}
                            @if($notification['status'] === 'failed' && isset($notification['error']))
                                <p class="mt-2 text-xs text-red-600 truncate" title="{{ $notification['error'] }}">
                                    {{ $notification['error'] }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Persisted Notifications Section --}}
            @if($this->persistedNotifications->isNotEmpty())
                <div class="px-4 py-2 bg-gray-50 border-b border-t">
                    <span class="text-sm font-medium text-gray-700">History</span>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($this->persistedNotifications as $notification)
                        <div
                            wire:click="markAsRead('{{ $notification->id }}')"
                            class="px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors {{ !$notification->read ? 'bg-blue-50' : '' }}"
                        >
                            <div class="flex items-start">
                                @if(!$notification->read)
                                    <span class="flex-shrink-0 w-2 h-2 mt-2 mr-2 bg-blue-600 rounded-full"></span>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        {{ $notification->job_name }}
                                    </p>
                                    <div class="mt-1 flex items-center space-x-2">
                                        @if($notification->status === 'completed')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                Completed
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                Failed
                                            </span>
                                        @endif
                                        <span class="text-xs text-gray-500">
                                            {{ $notification->created_at->diffForHumans() }}
                                        </span>
                                    </div>
                                    @if($notification->status === 'failed' && $notification->error)
                                        <p class="mt-1 text-xs text-red-600 truncate">
                                            {{ $notification->error }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Empty State --}}
            @if(count($liveNotifications) === 0 && $this->persistedNotifications->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-gray-500">
                    <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="text-sm">No notifications yet</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Backdrop --}}
    <div
        x-show="$wire.isOpen"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="$wire.close()"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 z-40"
        style="display: none;"
    ></div>
</div>
```

**Step 5: Run test to verify it passes**

Run:
```bash
./scripts/run-focused-tests.sh NotificationPanelTest
```

Expected: PASS

**Step 6: Commit**

```bash
git add app/Livewire/NotificationPanel.php resources/views/livewire/notification-panel.blade.php tests/Feature/Livewire/NotificationPanelTest.php
git commit -m "feat: Add NotificationPanel Livewire component with real-time updates"
```

---

### Task 4.2: Integrate Notification Panel into Layout

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

**Step 1: Update app layout**

Edit `resources/views/layouts/app.blade.php` to include the notification panel:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'AI Legal War Machine') }} - @yield('title', 'Topic Framework')</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    @vite(['resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-7">
                    <div>
                        <a href="/" class="flex items-center py-4 px-2">
                            <span class="font-semibold text-gray-500 text-lg">AI Legal War Machine</span>
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="/topics-demo" class="py-2 px-2 font-medium text-gray-500 rounded hover:bg-indigo-600 hover:text-white transition duration-300">
                        Topic Framework Demo
                    </a>

                    @auth
                        {{-- Notification Panel --}}
                        @livewire('notification-panel')

                        {{-- Horizon Link --}}
                        <a href="/horizon" class="py-2 px-2 font-medium text-gray-500 rounded hover:bg-indigo-600 hover:text-white transition duration-300">
                            Horizon
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <main>
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
```

**Step 2: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat: Integrate notification panel and Horizon link into layout"
```

---

## Phase 5: Integrate Broadcasting into Remaining Jobs

### Task 5.1: Create Job Integration Script

Due to the number of jobs (19 total), create a checklist for systematic integration. Each job follows this pattern:

1. Add `use App\Traits\BroadcastsJobProgress;` to imports
2. Add `BroadcastsJobProgress` to trait usage
3. Implement `getJobDisplayName(): string` method
4. Add `$userId` property if not present
5. Add broadcasting calls at appropriate points:
   - `broadcastStarted()` at job start
   - `broadcastProgress()` at stage transitions
   - `broadcastCompleted()` on success
   - `broadcastFailed()` on failure

**Jobs to integrate:**

- [ ] `ProcessTextractJob.php` (DONE in Task 3.2)
- [ ] `ExtractTablesFromTextractJob.php`
- [ ] `GenerateEmbeddingsJob.php`
- [ ] `ReprocessTextractJob.php`
- [ ] `RegenerateTextractEmbeddings.php`
- [ ] `ProcessDrivePdfJob.php`
- [ ] `ExecuteAgentResearch.php`
- [ ] `RunAutonomousResearchJob.php`
- [ ] `ExecuteDecisionDiscoveryJob.php`
- [ ] `RunDecisionDiscovery.php`
- [ ] `ExecuteOdlukeAgentJob.php`
- [ ] `IngestOdlukeDecision.php`
- [ ] `SyncGraphDataJob.php`
- [ ] `SyncTextractToGraph.php`
- [ ] `MatchCaseToDecisionJob.php`
- [ ] `ReconcileUnmatchedCasesJob.php`
- [ ] `FetchCourtCasesJob.php`
- [ ] `EkomSyncAllJob.php`
- [ ] `GenerateLawMetadata.php`

**Note:** This is repetitive work. Execute in batches of 3-4 jobs per commit.

---

## Phase 6: Development Scripts

### Task 6.1: Update Composer Dev Script

**Files:**
- Modify: `composer.json`

**Step 1: Update dev script to include Horizon and Reverb**

Update the `scripts.dev` section in `composer.json`:

```json
"dev": [
    "Composer\\Config::disableProcessTimeout",
    "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74,#a3e635,#f472b6\" \"php artisan serve\" \"php artisan horizon\" \"php artisan reverb:start\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,horizon,reverb,logs,vite --kill-others"
],
```

**Step 2: Commit**

```bash
git add composer.json
git commit -m "feat: Update dev script to include Horizon and Reverb"
```

---

## Verification Checklist

After completing all tasks, verify:

1. [ ] `composer dev` starts all services (server, horizon, reverb, vite)
2. [ ] Horizon dashboard accessible at `/horizon`
3. [ ] WebSocket connection established (check browser console)
4. [ ] Notification panel renders in header
5. [ ] Processing a document shows real-time progress
6. [ ] Completed/failed notifications persist to database
7. [ ] Notification panel shows persisted notifications
8. [ ] Mark as read functionality works
9. [ ] All existing tests pass

---

## Post-Implementation

After verification:

1. Create PR with summary of changes
2. Document new environment variables in README
3. Add Horizon and Reverb to deployment documentation
4. Consider adding notification preferences (future enhancement)
