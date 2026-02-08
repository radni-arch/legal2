# Supervisor Queue Worker Setup

## Overview

Supervisor manages Laravel queue workers in production, ensuring they run continuously and automatically restart on failure.

## Installation

```bash
# Install Supervisor
sudo apt update
sudo apt install -y supervisor

# Verify installation
supervisorctl version
```

## Configuration

### 1. Copy Configuration File

```bash
# Copy our configuration
sudo cp docs/server-config/supervisor/ai-legal-war-machine.conf /etc/supervisor/conf.d/

# Set correct permissions
sudo chmod 644 /etc/supervisor/conf.d/ai-legal-war-machine.conf
```

### 2. Create Log Directory

```bash
# Ensure log directory exists with correct permissions
sudo mkdir -p /var/www/ai-legal-war-machine/storage/logs
sudo chown -R www-data:www-data /var/www/ai-legal-war-machine/storage/logs
sudo chmod -R 755 /var/www/ai-legal-war-machine/storage/logs
```

### 3. Update Supervisor Configuration

```bash
# Reload Supervisor configuration
sudo supervisorctl reread

# Expected output:
# ai-legal-queue-worker: available

# Add new programs
sudo supervisorctl update

# Expected output:
# ai-legal-queue-worker: added process group
```

### 4. Start Queue Workers

```bash
# Start all workers
sudo supervisorctl start ai-legal-queue-worker:*

# Expected output:
# ai-legal-queue-worker:ai-legal-queue-worker_00: started
# ai-legal-queue-worker:ai-legal-queue-worker_01: started
# ai-legal-queue-worker:ai-legal-queue-worker_02: started
# ai-legal-queue-worker:ai-legal-queue-worker_03: started
```

### 5. Verify Workers Are Running

```bash
# Check status
sudo supervisorctl status

# Expected output:
# ai-legal-queue-worker:ai-legal-queue-worker_00   RUNNING   pid 12345, uptime 0:00:05
# ai-legal-queue-worker:ai-legal-queue-worker_01   RUNNING   pid 12346, uptime 0:00:05
# ai-legal-queue-worker:ai-legal-queue-worker_02   RUNNING   pid 12347, uptime 0:00:05
# ai-legal-queue-worker:ai-legal-queue-worker_03   RUNNING   pid 12348, uptime 0:00:05
```

## Managing Queue Workers

### Start/Stop/Restart Workers

```bash
# Start all workers
sudo supervisorctl start ai-legal-queue-worker:*

# Stop all workers (graceful - waits for jobs to finish)
sudo supervisorctl stop ai-legal-queue-worker:*

# Restart all workers
sudo supervisorctl restart ai-legal-queue-worker:*

# Start specific worker
sudo supervisorctl start ai-legal-queue-worker:ai-legal-queue-worker_00

# Stop specific worker
sudo supervisorctl stop ai-legal-queue-worker:ai-legal-queue-worker_00
```

### Check Worker Status

```bash
# Detailed status
sudo supervisorctl status ai-legal-queue-worker:*

# Check if workers are processing jobs
ps aux | grep "queue:work"
```

### View Worker Logs

```bash
# View live logs
sudo tail -f /var/www/ai-legal-war-machine/storage/logs/queue-worker.log

# View last 100 lines
sudo tail -100 /var/www/ai-legal-war-machine/storage/logs/queue-worker.log

# Search for errors
sudo grep "ERROR" /var/www/ai-legal-war-machine/storage/logs/queue-worker.log

# Search for specific job
sudo grep "ProcessDrivePdfJob" /var/www/ai-legal-war-machine/storage/logs/queue-worker.log
```

## After Code Deployment

When deploying new code, restart workers to pick up changes:

```bash
# Method 1: Restart via Supervisor (graceful)
sudo supervisorctl restart ai-legal-queue-worker:*

# Method 2: Signal workers to restart after current job (preferred)
php artisan queue:restart

# Workers will finish current jobs, then automatically restart
# Supervisor will detect exit and spawn new processes
```

## Queue Monitoring

### Check Queue Size

```bash
# Connect to Redis
redis-cli -a your-password -n 2

# Check queue sizes
LLEN queues:high
LLEN queues:agents
LLEN queues:textract
LLEN queues:default
LLEN queues:low

# Exit Redis
exit
```

### Check Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# Retry specific failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Flush all failed jobs (delete permanently)
php artisan queue:flush
```

### Monitor Queue Performance

```bash
# Watch queue in real-time
watch -n 1 "redis-cli -a your-password -n 2 LLEN queues:default"

# Monitor worker processes
watch -n 1 "ps aux | grep 'queue:work' | grep -v grep | wc -l"
```

## Troubleshooting

### Workers Not Starting

```bash
# Check Supervisor logs
sudo tail -100 /var/log/supervisor/supervisord.log

# Check configuration syntax
sudo supervisorctl reread

# Check file permissions
ls -la /etc/supervisor/conf.d/ai-legal-war-machine.conf

# Ensure www-data user exists
id www-data

# Check Laravel artisan command
sudo -u www-data php /var/www/ai-legal-war-machine/artisan queue:work --help
```

### Workers Keep Restarting

```bash
# Check worker logs for errors
sudo tail -100 /var/www/ai-legal-war-machine/storage/logs/queue-worker.log

# Check PHP errors
sudo tail -100 /var/log/php8.2-fpm.log

# Check system resources
free -h
df -h
top
```

### Workers Not Processing Jobs

```bash
# Check Redis connection
redis-cli -a your-password ping
# Expected: PONG

# Check if jobs are in queue
redis-cli -a your-password -n 2 LLEN queues:default

# Verify queue configuration
php artisan config:show queue

# Check environment variables
grep QUEUE_ /var/www/ai-legal-war-machine/.env

# Manually run a worker (debug mode)
sudo -u www-data php /var/www/ai-legal-war-machine/artisan queue:work --once --verbose
```

### High Memory Usage

```bash
# Check worker memory usage
ps aux | grep "queue:work" | awk '{sum+=$6} END {print sum/1024 " MB"}'

# Reduce max-time to restart workers more frequently
# Edit: /etc/supervisor/conf.d/ai-legal-war-machine.conf
# Change: --max-time=3600 to --max-time=1800

# Reload configuration
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart ai-legal-queue-worker:*
```

### Jobs Timing Out

```bash
# Increase job timeout
# Edit: /etc/supervisor/conf.d/ai-legal-war-machine.conf
# Change: --timeout=300 to --timeout=600

# Reload configuration
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart ai-legal-queue-worker:*

# Or update job-specific timeout in code
# In your Job class:
# public $timeout = 600;
```

## Performance Tuning

### Adjust Number of Workers

```bash
# Edit configuration
sudo nano /etc/supervisor/conf.d/ai-legal-war-machine.conf

# Change: numprocs=4 to desired number (based on CPU cores)
# Recommended: 1 worker per CPU core

# Reload configuration
sudo supervisorctl reread
sudo supervisorctl update
```

### Optimize Queue Processing

```bash
# Reduce sleep time for faster processing (uses more CPU)
# Change: --sleep=3 to --sleep=1

# Increase max-time for longer-running processes
# Change: --max-time=3600 to --max-time=7200

# Adjust tries for critical jobs
# Change: --tries=3 to --tries=5
```

## Scheduled Tasks Integration

Supervisor manages queue workers. Laravel's scheduler (cron) dispatches jobs to queues. Both work together:

```bash
# Add Laravel scheduler to crontab
sudo crontab -e -u www-data

# Add this line:
* * * * * cd /var/www/ai-legal-war-machine && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler dispatches jobs, Supervisor's workers process them.

## Auto-Start on Boot

Supervisor is configured to auto-start on boot by default:

```bash
# Verify Supervisor is enabled
sudo systemctl is-enabled supervisor
# Expected: enabled

# Enable if not enabled
sudo systemctl enable supervisor

# Check Supervisor status
sudo systemctl status supervisor
```

## Health Checks

### Create Health Check Script

```bash
# Create health check script
cat > /var/www/ai-legal-war-machine/scripts/check-queue-workers.sh << 'EOF'
#!/bin/bash

# Count running workers
RUNNING=$(supervisorctl status ai-legal-queue-worker:* | grep RUNNING | wc -l)
EXPECTED=4

if [ $RUNNING -lt $EXPECTED ]; then
    echo "WARNING: Only $RUNNING/$EXPECTED workers running!"
    exit 1
else
    echo "OK: $RUNNING/$EXPECTED workers running"
    exit 0
fi
EOF

# Make executable
chmod +x /var/www/ai-legal-war-machine/scripts/check-queue-workers.sh

# Test it
/var/www/ai-legal-war-machine/scripts/check-queue-workers.sh
```

### Monitor with Cron

```bash
# Add to crontab (check every 5 minutes)
*/5 * * * * /var/www/ai-legal-war-machine/scripts/check-queue-workers.sh || echo "Queue workers down!" | mail -s "Worker Alert" admin@example.com
```

## Logging Best Practices

### Log Rotation

Supervisor config already includes log rotation:
- Max 10MB per log file
- Keep 5 backups (50MB total)

### Additional Log Rotation (Optional)

```bash
# Create logrotate config
sudo nano /etc/logrotate.d/ai-legal-war-machine

# Add:
/var/www/ai-legal-war-machine/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        supervisorctl restart ai-legal-queue-worker:*
    endscript
}
```

## Security Considerations

- ✅ Workers run as `www-data` user (same as PHP-FPM)
- ✅ Limited to 4 processes (prevents DoS)
- ✅ Graceful shutdown prevents job loss
- ✅ Logs are rotated to prevent disk exhaustion
- ✅ Configuration file has restricted permissions (644)

## Acceptance Criteria

Before moving to next task:

- [x] Supervisor installed
- [x] Configuration file created
- [x] 4 workers running
- [x] Workers processing jobs from all queues (high, agents, textract, default, low)
- [x] Workers auto-restart on failure
- [x] Logs being written correctly
- [x] Queue restart command works (`php artisan queue:restart`)
- [x] Workers restart after code deployment
- [x] Health check script created
- [x] Supervisor auto-starts on boot

## Quick Reference

```bash
# Start workers
sudo supervisorctl start ai-legal-queue-worker:*

# Stop workers
sudo supervisorctl stop ai-legal-queue-worker:*

# Restart workers
sudo supervisorctl restart ai-legal-queue-worker:*

# Check status
sudo supervisorctl status

# View logs
sudo tail -f /var/www/ai-legal-war-machine/storage/logs/queue-worker.log

# Signal workers to restart (preferred during deployment)
php artisan queue:restart

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```
