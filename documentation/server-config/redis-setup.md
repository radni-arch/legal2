# Redis Production Setup

## Installation

```bash
# Install Redis
sudo apt update
sudo apt install -y redis-server

# Check version (should be 7.0+)
redis-server --version
```

## Configuration

### 1. Backup Original Config

```bash
sudo cp /etc/redis/redis.conf /etc/redis/redis.conf.backup
```

### 2. Apply Production Config

```bash
# Copy our optimized config
sudo cp docs/server-config/redis.conf /etc/redis/redis.conf

# Set correct ownership
sudo chown redis:redis /etc/redis/redis.conf
sudo chmod 640 /etc/redis/redis.conf
```

### 3. Set Redis Password

```bash
# Generate strong password
REDIS_PASSWORD=$(openssl rand -base64 32)
echo "REDIS_PASSWORD=$REDIS_PASSWORD"

# Add password to config
sudo sed -i "s/# requirepass your-strong-password-here/requirepass $REDIS_PASSWORD/" /etc/redis/redis.conf

# Save password to .env file (do this on app server)
echo "REDIS_PASSWORD=$REDIS_PASSWORD" >> /var/www/ai-legal-war-machine/.env
```

### 4. Configure Systemd

```bash
# Edit systemd service
sudo systemctl edit redis-server --full

# Ensure these settings:
# [Service]
# Type=forking
# ExecStart=/usr/bin/redis-server /etc/redis/redis.conf
# ExecStop=/bin/kill -s TERM $MAINPID
# PIDFile=/var/run/redis/redis-server.pid
# User=redis
# Group=redis
# Restart=always

# Reload systemd
sudo systemctl daemon-reload
```

### 5. Start Redis

```bash
# Enable on boot
sudo systemctl enable redis-server

# Start service
sudo systemctl start redis-server

# Check status
sudo systemctl status redis-server

# Check logs
sudo tail -50 /var/log/redis/redis-server.log
```

## Verification

### Test Connection

```bash
# Connect to Redis CLI
redis-cli

# Authenticate (if password is set)
AUTH your-password-here

# Test basic commands
PING
# Expected: PONG

# Check info
INFO server
INFO memory
INFO stats

# Exit
exit
```

### Test Database Separation

```bash
# Test each database
redis-cli -a your-password

# Database 0 (Cache)
SELECT 0
SET test:cache "cache-value"
GET test:cache

# Database 1 (Sessions)
SELECT 1
SET test:session "session-value"
GET test:session

# Database 2 (Queues)
SELECT 2
SET test:queue "queue-value"
GET test:queue

# Database 3 (Broadcasting)
SELECT 3
SET test:broadcast "broadcast-value"
GET test:broadcast

# Clean up
SELECT 0
DEL test:cache
SELECT 1
DEL test:session
SELECT 2
DEL test:queue
SELECT 3
DEL test:broadcast

# Exit
exit
```

## Performance Monitoring

### Monitor Real-time Stats

```bash
# Monitor all commands in real-time
redis-cli -a your-password MONITOR

# Check memory usage
redis-cli -a your-password INFO memory | grep human

# Check connected clients
redis-cli -a your-password INFO clients

# Check command statistics
redis-cli -a your-password INFO stats
```

### Check Slow Queries

```bash
# Get slow log (commands slower than 10ms)
redis-cli -a your-password SLOWLOG GET 10

# Reset slow log
redis-cli -a your-password SLOWLOG RESET
```

### Memory Analysis

```bash
# Check memory usage by database
redis-cli -a your-password INFO memory

# Get biggest keys
redis-cli -a your-password --bigkeys

# Get sample of keys
redis-cli -a your-password --scan --pattern '*' | head -20
```

## Database Usage Configuration

Update Laravel `config/database.php` to use separate databases:

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'), // Cache
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => '0', // Database 0 for cache
    ],

    'session' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => '1', // Database 1 for sessions
    ],

    'queue' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => '2', // Database 2 for queues
    ],

    'broadcast' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => '3', // Database 3 for broadcasting
    ],
],
```

## Maintenance

### Manual Backup

```bash
# Trigger immediate save
redis-cli -a your-password BGSAVE

# Check last save time
redis-cli -a your-password LASTSAVE

# Copy RDB file
sudo cp /var/lib/redis/dump.rdb /backup/redis-$(date +%Y%m%d).rdb
```

### Restore from Backup

```bash
# Stop Redis
sudo systemctl stop redis-server

# Replace RDB file
sudo cp /backup/redis-20251108.rdb /var/lib/redis/dump.rdb
sudo chown redis:redis /var/lib/redis/dump.rdb

# Start Redis
sudo systemctl start redis-server
```

### Clear Database (if needed)

```bash
# Clear specific database
redis-cli -a your-password -n 0 FLUSHDB  # Clear cache only

# NEVER run FLUSHALL in production (disabled in our config)
```

### Rewrite AOF (if AOF file is too large)

```bash
# Trigger AOF rewrite
redis-cli -a your-password BGREWRITEAOF

# Monitor progress
redis-cli -a your-password INFO persistence | grep aof_rewrite_in_progress
```

## Troubleshooting

### Redis Won't Start

```bash
# Check logs
sudo tail -100 /var/log/redis/redis-server.log

# Check configuration
redis-server /etc/redis/redis.conf --test-memory 1024

# Check permissions
ls -la /var/lib/redis/
ls -la /var/log/redis/

# Fix permissions if needed
sudo chown -R redis:redis /var/lib/redis
sudo chown -R redis:redis /var/log/redis
```

### High Memory Usage

```bash
# Check memory info
redis-cli -a your-password INFO memory

# Check biggest keys
redis-cli -a your-password --bigkeys

# Adjust maxmemory in redis.conf if needed
sudo nano /etc/redis/redis.conf
# Change: maxmemory 4gb

# Restart Redis
sudo systemctl restart redis-server
```

### Connection Refused

```bash
# Check if Redis is running
sudo systemctl status redis-server

# Check if listening on correct port
sudo netstat -tlnp | grep redis

# Check firewall (should allow localhost)
sudo ufw status

# Test connection
redis-cli ping
```

### Slow Performance

```bash
# Check slow log
redis-cli -a your-password SLOWLOG GET 20

# Check latency
redis-cli -a your-password --latency

# Check network latency
redis-cli -a your-password --latency-history

# Check CPU usage
top -u redis
```

## Security Checklist

- [x] Redis bound to 127.0.0.1 only
- [x] Strong password configured
- [x] Dangerous commands disabled (FLUSHDB, FLUSHALL)
- [x] CONFIG command renamed
- [x] File permissions correct (640 for config)
- [x] Redis running as dedicated user
- [x] Firewall configured (if needed)
- [x] Regular backups enabled

## Performance Checklist

- [x] maxmemory set appropriately (4GB)
- [x] maxmemory-policy set to allkeys-lru
- [x] AOF enabled for durability
- [x] RDB snapshots configured
- [x] Slow log enabled (10ms threshold)
- [x] Active defragmentation enabled
- [x] Separate databases for different purposes

## Acceptance Criteria

- [x] Redis 7.0+ installed
- [x] Production config applied
- [x] Password authentication enabled
- [x] Dangerous commands disabled
- [x] 4 databases configured (cache, session, queue, broadcast)
- [x] AOF persistence enabled
- [x] RDB snapshots configured
- [x] Slow query logging enabled
- [x] Memory limit set (4GB)
- [x] Redis starts successfully
- [x] Connection test successful
- [x] All 4 databases accessible
