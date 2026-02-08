# Queue Priorities

## Overview

The application uses 5 priority queues to ensure critical jobs are processed first while preventing lower-priority jobs from blocking the system.

## Queue Priority Order

Queue workers process jobs in this priority order:

| Priority | Queue      | Purpose                                          | Examples                                  |
|----------|------------|--------------------------------------------------|-------------------------------------------|
| 1        | `high`     | Critical operations requiring immediate processing | Payment processing, user actions, alerts  |
| 2        | `agents`   | AI agent jobs and autonomous operations          | Research agents, decision discovery       |
| 3        | `textract` | AWS Textract OCR processing                      | PDF OCR, document analysis                |
| 4        | `default`  | Standard background jobs                         | Emails, data sync, reports                |
| 5        | `low`      | Non-urgent maintenance and cleanup               | Cache warming, statistics, cleanup        |

## Using Queue Priorities

### Method 1: Using HasQueuePriority Trait

```php
use App\Jobs\Concerns\HasQueuePriority;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasQueuePriority;

    public function __construct()
    {
        // Set priority in constructor
        $this->onHighPriorityQueue();
    }

    public function handle(): void
    {
        // Job logic here
    }
}
```

### Method 2: Dispatch with Queue

```php
// Dispatch to high priority queue
MyJob::dispatch()->onQueue('high');

// Dispatch to agents queue
ResearchJob::dispatch($topic)->onQueue('agents');

// Dispatch to textract queue
ProcessPdfJob::dispatch($file)->onQueue('textract');

// Dispatch to default queue (no need to specify)
SendEmailJob::dispatch($user);

// Dispatch to low priority queue
CacheWarmJob::dispatch()->onQueue('low');
```

### Method 3: Using Trait Helper Methods

```php
// In job constructor
class MyJob implements ShouldQueue
{
    use HasQueuePriority;

    public function __construct()
    {
        $this->onHighPriorityQueue();    // Critical operations
        $this->onAgentsQueue();          // AI agent jobs
        $this->onTextractQueue();        // OCR processing
        $this->onDefaultQueue();         // Standard jobs
        $this->onLowPriorityQueue();     // Maintenance
    }
}
```

### Method 4: Dynamic Priority Selection

```php
use App\Jobs\Concerns\HasQueuePriority;

class MyJob implements ShouldQueue
{
    use HasQueuePriority;

    public function __construct(private string $priority = 'standard')
    {
        $queue = HasQueuePriority::getQueueForType($this->priority);
        $this->onQueue($queue);
    }
}

// Usage:
MyJob::dispatch('critical');    // Goes to 'high' queue
MyJob::dispatch('agent');       // Goes to 'agents' queue
MyJob::dispatch('ocr');         // Goes to 'textract' queue
MyJob::dispatch('standard');    // Goes to 'default' queue
MyJob::dispatch('maintenance'); // Goes to 'low' queue
```

## Queue Assignment Guidelines

### High Priority Queue

**Use for:**
- Payment processing
- User-triggered actions that expect immediate feedback
- Security alerts and notifications
- Real-time data updates
- Critical system operations

**Examples:**
```php
class ProcessPayment implements ShouldQueue
{
    use HasQueuePriority;

    public function __construct()
    {
        $this->onHighPriorityQueue();
        $this->tries = 5; // More retries for critical jobs
        $this->timeout = 120; // 2 minutes timeout
    }
}
```

**Do NOT use for:**
- Long-running operations (> 2 minutes)
- Batch processing
- Non-critical notifications
- Background maintenance

### Agents Queue

**Use for:**
- Autonomous AI agent jobs
- Legal research agents
- Court decision discovery
- Document analysis with AI
- Autonomous data gathering

**Examples:**
```php
class AutonomousResearchJob implements ShouldQueue
{
    use HasQueuePriority;

    public function __construct()
    {
        $this->onAgentsQueue();
        $this->tries = 3;
        $this->timeout = 1800; // 30 minutes for research
    }
}
```

**Do NOT use for:**
- Simple data queries
- Non-AI operations
- User-facing operations

### Textract Queue

**Use for:**
- AWS Textract OCR processing
- PDF text extraction
- Image text extraction
- Table extraction from documents
- Document structure analysis

**Examples:**
```php
class ProcessDrivePdfJob implements ShouldQueue
{
    use HasQueuePriority;

    public function __construct()
    {
        $this->onTextractQueue();
        $this->tries = 3;
        $this->timeout = 600; // 10 minutes for large PDFs
    }
}
```

**Do NOT use for:**
- Non-OCR document processing
- Image uploads without OCR
- Simple file operations

### Default Queue

**Use for:**
- Email sending
- Report generation
- Data synchronization
- File uploads
- Standard data processing
- Notifications

**Examples:**
```php
class SendWelcomeEmail implements ShouldQueue
{
    use HasQueuePriority;

    public function __construct()
    {
        $this->onDefaultQueue(); // Or just don't specify - default is 'default'
        $this->tries = 3;
        $this->timeout = 60;
    }
}
```

**Use as fallback** when job doesn't fit other categories.

### Low Priority Queue

**Use for:**
- Cache warming
- Database cleanup
- Statistics calculation
- Old file deletion
- Log rotation
- Index rebuilding
- Data migrations
- Archive operations

**Examples:**
```php
class CacheWarmProductionJob implements ShouldQueue
{
    use HasQueuePriority;

    public function __construct()
    {
        $this->onLowPriorityQueue();
        $this->tries = 1; // Don't retry low-priority jobs much
        $this->timeout = 300;
    }
}
```

**Do NOT use for:**
- User-facing operations
- Time-sensitive tasks
- Critical system functions

## Monitoring Queue Priorities

### Check Queue Sizes

```bash
# Connect to Redis (queue database is DB 2)
redis-cli -a your-password -n 2

# Check each queue size
LLEN queues:high
LLEN queues:agents
LLEN queues:textract
LLEN queues:default
LLEN queues:low

# Exit
exit
```

### Watch Queue in Real-Time

```bash
# Watch all queues
watch -n 1 'redis-cli -a your-password -n 2 LLEN queues:high && redis-cli -a your-password -n 2 LLEN queues:agents && redis-cli -a your-password -n 2 LLEN queues:textract && redis-cli -a your-password -n 2 LLEN queues:default && redis-cli -a your-password -n 2 LLEN queues:low'
```

### Check Failed Jobs by Queue

```bash
# List failed jobs
php artisan queue:failed

# Retry failed jobs from specific queue
php artisan queue:retry --queue=high

# Flush failed jobs from specific queue
php artisan queue:flush --queue=low
```

## Performance Tuning

### Adjust Worker Count Per Queue

If a specific queue consistently has jobs waiting:

```bash
# Option 1: Add dedicated workers for that queue
# Edit: /etc/supervisor/conf.d/ai-legal-war-machine.conf

# Add dedicated textract workers:
[program:ai-legal-textract-worker]
command=php /var/www/ai-legal-war-machine/artisan queue:work redis --queue=textract --sleep=1 --tries=3 --max-time=3600
numprocs=2
# ... other settings

# Reload Supervisor
sudo supervisorctl reread
sudo supervisorctl update
```

### Balance Queue Processing

Monitor queue backlogs:

```bash
# Create monitoring script
cat > /var/www/ai-legal-war-machine/scripts/monitor-queues.sh << 'EOF'
#!/bin/bash

HIGH=$(redis-cli -a $REDIS_PASSWORD -n 2 LLEN queues:high 2>/dev/null || echo 0)
AGENTS=$(redis-cli -a $REDIS_PASSWORD -n 2 LLEN queues:agents 2>/dev/null || echo 0)
TEXTRACT=$(redis-cli -a $REDIS_PASSWORD -n 2 LLEN queues:textract 2>/dev/null || echo 0)
DEFAULT=$(redis-cli -a $REDIS_PASSWORD -n 2 LLEN queues:default 2>/dev/null || echo 0)
LOW=$(redis-cli -a $REDIS_PASSWORD -n 2 LLEN queues:low 2>/dev/null || echo 0)

echo "Queue Sizes:"
echo "  High:     $HIGH"
echo "  Agents:   $AGENTS"
echo "  Textract: $TEXTRACT"
echo "  Default:  $DEFAULT"
echo "  Low:      $LOW"
echo "  Total:    $((HIGH + AGENTS + TEXTRACT + DEFAULT + LOW))"
EOF

chmod +x /var/www/ai-legal-war-machine/scripts/monitor-queues.sh
```

## Best Practices

1. **Always set priority in job constructor** - Don't rely on dispatch-time settings
2. **Use HasQueuePriority trait** - Consistent API across all jobs
3. **Document why a job has high priority** - Add comments explaining critical nature
4. **Monitor queue sizes** - Set up alerts if high-priority queue grows
5. **Set appropriate timeouts** - High-priority jobs should have shorter timeouts
6. **Adjust retry logic** - Critical jobs may need more retries
7. **Test job priority** - Ensure jobs go to correct queue
8. **Review priorities quarterly** - Job importance may change over time

## Common Mistakes

### ❌ Wrong: Setting priority at dispatch time only

```php
// Job class
class MyJob implements ShouldQueue
{
    // No queue set here
}

// Dispatch
MyJob::dispatch()->onQueue('high'); // ❌ Can be forgotten
```

### ✅ Right: Setting priority in constructor

```php
// Job class
class MyJob implements ShouldQueue
{
    use HasQueuePriority;

    public function __construct()
    {
        $this->onHighPriorityQueue(); // ✅ Always high priority
    }
}

// Dispatch
MyJob::dispatch(); // ✅ Automatically goes to high queue
```

### ❌ Wrong: Everything on high priority

```php
// Don't do this - defeats the purpose of priorities
class CacheWarmJob implements ShouldQueue
{
    public function __construct()
    {
        $this->onHighPriorityQueue(); // ❌ Not critical
    }
}
```

### ✅ Right: Appropriate priority for job type

```php
class CacheWarmJob implements ShouldQueue
{
    use HasQueuePriority;

    public function __construct()
    {
        $this->onLowPriorityQueue(); // ✅ Correct priority
    }
}
```

## Testing Queue Priorities

```php
// In your test
use Tests\TestCase;

class MyJobTest extends TestCase
{
    public function test_job_uses_high_priority_queue()
    {
        $job = new MyJob();

        $this->assertEquals('high', $job->queue);
    }

    public function test_job_dispatches_to_correct_queue()
    {
        Queue::fake();

        MyJob::dispatch();

        Queue::assertPushedOn('high', MyJob::class);
    }
}
```

## Migration Guide

To migrate existing jobs to use queue priorities:

1. **Add HasQueuePriority trait** to job class
2. **Determine appropriate priority** based on guidelines above
3. **Set priority in constructor** using trait methods
4. **Update tests** to verify correct queue
5. **Monitor queue sizes** after deployment

Example:

```php
// Before
class MyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // ...
    }
}

// After
class MyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasQueuePriority;

    public function __construct()
    {
        $this->onAgentsQueue(); // Or appropriate priority
    }

    public function handle(): void
    {
        // ...
    }
}
```
