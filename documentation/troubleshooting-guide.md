# Troubleshooting Guide

## AI Legal War Machine - Production Troubleshooting

**Version**: 1.0
**Last Updated**: 2025-11-09

---

## Table of Contents

1. [Application Not Responding](#1-application-not-responding)
2. [HTTP 500 Internal Server Error](#2-http-500-internal-server-error)
3. [Database Connection Errors](#3-database-connection-errors)
4. [Queue Workers Not Processing](#4-queue-workers-not-processing)
5. [Slow Performance](#5-slow-performance)
6. [High Memory Usage](#6-high-memory-usage)
7. [Disk Space Full](#7-disk-space-full)
8. [Neo4j Connection Errors](#8-neo4j-connection-errors)
9. [OpenAI API Errors](#9-openai-api-errors)
10. [SSL Certificate Issues](#10-ssl-certificate-issues)
11. [Email Not Sending](#11-email-not-sending)
12. [Redis Connection Errors](#12-redis-connection-errors)
13. [Session/Authentication Issues](#13-sessionauthentication-issues)
14. [Deployment Failures](#14-deployment-failures)
15. [Log Files Too Large](#15-log-files-too-large)

---

## 1. Application Not Responding

### Symptoms

- Website shows "Connection timed out"
- `curl http://localhost` hangs or fails
- Health endpoint returns connection error

### Diagnosis

```bash
# Check if Nginx is running
systemctl status nginx

# Check if PHP-FPM is running
systemctl status php8.2-fpm

# Check Nginx error logs
tail -f /var/log/nginx/ai-legal-error.log

# Check PHP-FPM logs
tail -f /var/log/php8.2-fpm.log
```

### Common Causes & Solutions

**Cause 1: Nginx is down**

```bash
# Check status
systemctl status nginx

# Start Nginx
sudo systemctl start nginx

# If start fails, test configuration
sudo nginx -t

# Fix configuration errors, then restart
sudo systemctl restart nginx
```

**Cause 2: PHP-FPM is down**

```bash
# Check status
systemctl status php8.2-fpm

# Start PHP-FPM
sudo systemctl start php8.2-fpm

# If start fails, check logs
tail -100 /var/log/php8.2-fpm.log

# Common issues:
# - Port already in use
# - Configuration error
# - Permission denied

# Restart
sudo systemctl restart php8.2-fpm
```

**Cause 3: PHP-FPM socket permissions**

```bash
# Check socket
ls -la /var/run/php/php8.2-fpm.sock

# Should be: srw-rw---- 1 www-data www-data

# Fix permissions
sudo chown www-data:www-data /var/run/php/php8.2-fpm.sock
sudo chmod 660 /var/run/php/php8.2-fpm.sock

# Restart
sudo systemctl restart php8.2-fpm
```

**Cause 4: Out of PHP-FPM workers**

```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Increase worker count
sudo nano /etc/php/8.2/fpm/pool.d/www.conf

# Increase these values:
pm.max_children = 20      # From 10
pm.start_servers = 5      # From 2
pm.min_spare_servers = 5  # From 1
pm.max_spare_servers = 10 # From 3

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### Prevention

- Set up monitoring alerts for Nginx and PHP-FPM
- Monitor PHP-FPM worker usage
- Configure auto-restart in systemd

---

## 2. HTTP 500 Internal Server Error

### Symptoms

- Website shows generic error page
- API returns HTTP 500
- "Server Error" in browser

### Diagnosis

```bash
# Check Laravel logs
tail -100 /var/www/ai-legal-war-machine/storage/logs/laravel.log

# Check Nginx error logs
tail -100 /var/log/nginx/ai-legal-error.log

# Check PHP-FPM logs
tail -100 /var/log/php8.2-fpm.log

# Try to reproduce error
curl -i http://localhost/api/health
```

### Common Causes & Solutions

**Cause 1: Missing or corrupted .env file**

```bash
# Check if .env exists
ls -la /var/www/ai-legal-war-machine/.env

# If missing, restore from backup or recreate
cp /var/www/ai-legal-war-machine/.env.example /var/www/ai-legal-war-machine/.env

# Edit .env with correct values
nano /var/www/ai-legal-war-machine/.env

# Generate new application key
php artisan key:generate

# Clear cache
php artisan config:cache
```

**Cause 2: Cache corruption**

```bash
cd /var/www/ai-legal-war-machine

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

**Cause 3: Permission errors**

```bash
# Check ownership
ls -la /var/www/ai-legal-war-machine/

# Fix ownership
sudo chown -R deploy:www-data /var/www/ai-legal-war-machine

# Fix permissions
chmod -R 775 /var/www/ai-legal-war-machine/storage
chmod -R 775 /var/www/ai-legal-war-machine/bootstrap/cache

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

**Cause 4: Composer dependencies missing**

```bash
cd /var/www/ai-legal-war-machine

# Check if vendor directory exists
ls -la vendor/

# Reinstall dependencies
composer install --no-dev --optimize-autoloader

# Clear cache
php artisan config:cache
```

**Cause 5: PHP memory limit exceeded**

```bash
# Check PHP memory limit
php -i | grep memory_limit

# Increase in php.ini
sudo nano /etc/php/8.2/fpm/php.ini

# Change:
memory_limit = 512M  # From 256M

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### Prevention

- Enable debug mode temporarily to see actual error
- Monitor error logs daily
- Set up error rate alerts

---

## 3. Database Connection Errors

### Symptoms

- "SQLSTATE[08006] could not connect to server"
- "Connection refused" errors
- "Too many connections" error

### Diagnosis

```bash
# Check if PostgreSQL is running
systemctl status postgresql

# Test connection
psql -h localhost -U ai_legal_user -d ai_legal_war_machine -c "SELECT 1;"

# Check connection count
sudo -u postgres psql -c "SELECT count(*) FROM pg_stat_activity;"

# Check max connections
sudo -u postgres psql -c "SHOW max_connections;"

# Check PostgreSQL logs
tail -100 /var/log/postgresql/postgresql-15-main.log
```

### Common Causes & Solutions

**Cause 1: PostgreSQL is down**

```bash
# Start PostgreSQL
sudo systemctl start postgresql

# If start fails, check logs
tail -100 /var/log/postgresql/postgresql-15-main.log

# Common issues:
# - Data directory corruption
# - Port already in use
# - Disk space full

# Restart
sudo systemctl restart postgresql
```

**Cause 2: Too many connections**

```bash
# Check current connections
sudo -u postgres psql -c "
SELECT count(*) as connections,
       (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') as max_connections
FROM pg_stat_activity;
"

# Kill idle connections
sudo -u postgres psql -c "
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = 'ai_legal_war_machine'
  AND state = 'idle'
  AND state_change < current_timestamp - INTERVAL '10 minutes';
"

# Increase max_connections (if needed)
sudo nano /etc/postgresql/15/main/postgresql.conf

# Change:
max_connections = 200  # From 100

# Restart PostgreSQL
sudo systemctl restart postgresql
```

**Cause 3: Wrong credentials in .env**

```bash
# Verify .env database credentials
grep DB_ /var/www/ai-legal-war-machine/.env

# Test connection with .env credentials
psql -h 127.0.0.1 -U ai_legal_user -d ai_legal_war_machine -c "SELECT 1;"

# If authentication fails, reset password
sudo -u postgres psql -c "ALTER USER ai_legal_user WITH PASSWORD 'new_password';"

# Update .env
nano /var/www/ai-legal-war-machine/.env
# DB_PASSWORD=new_password

# Clear config cache
php artisan config:cache
```

**Cause 4: PostgreSQL not listening on correct interface**

```bash
# Check listen addresses
sudo -u postgres psql -c "SHOW listen_addresses;"

# Should show: localhost or *

# Edit postgresql.conf
sudo nano /etc/postgresql/15/main/postgresql.conf

# Set:
listen_addresses = 'localhost'

# Check pg_hba.conf
sudo nano /etc/postgresql/15/main/pg_hba.conf

# Should have:
# host    all             all             127.0.0.1/32            scram-sha-256

# Restart PostgreSQL
sudo systemctl restart postgresql
```

### Prevention

- Monitor connection count
- Set up connection pooling
- Configure max connections appropriately
- Monitor PostgreSQL logs

---

## 4. Queue Workers Not Processing

### Symptoms

- Jobs stuck in `pending` state
- Queue sizes growing
- No queue activity in logs

### Diagnosis

```bash
# Check Supervisor status
sudo supervisorctl status ai-legal-queue-worker:*

# Check queue sizes
curl -s http://localhost/api/health | jq '.services.queue.queue_sizes'

# Check failed jobs
php artisan queue:failed

# Check queue worker logs
tail -100 /var/www/ai-legal-war-machine/storage/logs/queue-worker.log
```

### Common Causes & Solutions

**Cause 1: Workers not running**

```bash
# Check status
sudo supervisorctl status

# Start workers
sudo supervisorctl start ai-legal-queue-worker:*

# If start fails, check config
sudo nano /etc/supervisor/conf.d/ai-legal-queue-worker.conf

# Reload Supervisor
sudo supervisorctl reread
sudo supervisorctl update
```

**Cause 2: Workers stuck/hung**

```bash
# Restart all workers
sudo supervisorctl restart ai-legal-queue-worker:*

# Check logs for errors
tail -100 /var/www/ai-legal-war-machine/storage/logs/queue-worker.log

# Check for long-running processes
ps aux | grep "queue:work"

# Kill stuck workers if needed
sudo killall -9 php
sudo supervisorctl restart ai-legal-queue-worker:*
```

**Cause 3: Database queue table locked**

```bash
cd /var/www/ai-legal-war-machine

# Check for long-running queue jobs
php artisan queue:monitor

# Clear failed jobs (cautiously)
php artisan queue:flush

# Restart workers
sudo supervisorctl restart ai-legal-queue-worker:*
```

**Cause 4: Redis connection issues** (if using Redis queue)

```bash
# Test Redis connection
redis-cli PING

# Should return: PONG

# If authentication required
redis-cli -a YOUR_PASSWORD PING

# Check Redis logs
sudo journalctl -u redis-server -n 100
```

**Cause 5: Worker configuration error**

```bash
# Check Supervisor config
sudo cat /etc/supervisor/conf.d/ai-legal-queue-worker.conf

# Verify command path is correct
which php

# Verify application path
ls -la /var/www/ai-legal-war-machine/artisan

# Test worker manually
cd /var/www/ai-legal-war-machine
php artisan queue:work --once

# If error, fix and reload Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart ai-legal-queue-worker:*
```

### Prevention

- Monitor queue sizes daily
- Set up alerts for growing queues
- Configure auto-restart in Supervisor
- Monitor worker logs

---

## 5. Slow Performance

### Symptoms

- Pages loading > 3 seconds
- API responses slow
- Health check response_time_ms > 1000

### Diagnosis

```bash
# Check performance logs
php artisan logs:analyze --channel=performance --tail=50

# Check slow requests
grep "Slow request" /var/www/ai-legal-war-machine/storage/logs/performance.log | tail -20

# Check system resources
htop

# Check database performance
sudo -u postgres psql -c "
SELECT pid, now() - pg_stat_activity.query_start AS duration, query
FROM pg_stat_activity
WHERE state = 'active' AND now() - pg_stat_activity.query_start > interval '1 second'
ORDER BY duration DESC;
"
```

### Common Causes & Solutions

**Cause 1: High CPU usage**

```bash
# Identify CPU hog
ps aux --sort=-%cpu | head -20

# Common culprits:
# - Queue workers processing heavy jobs
# - Long-running database queries
# - Neo4j graph operations

# If queue workers, reduce count
sudo supervisorctl stop ai-legal-queue-worker:4
sudo supervisorctl stop ai-legal-queue-worker:5

# If database queries, optimize
# See Section 7 of operations-manual.md
```

**Cause 2: Cache disabled or cleared**

```bash
cd /var/www/ai-legal-war-machine

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verify cache is working
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
>>> exit

# Should return: "value"
```

**Cause 3: No database query cache**

```bash
# Check Redis connection
redis-cli PING

# Check cache hit rate
redis-cli INFO stats | grep -E "keyspace_hits|keyspace_misses"

# If low hit rate, review caching strategy
# Enable query caching in application code
```

**Cause 4: Unoptimized database queries**

```bash
# Enable query logging
# Edit .env
LOG_DATABASE_LEVEL=debug

# Reload config
php artisan config:cache
sudo systemctl restart php8.2-fpm

# Reproduce slow request

# Analyze queries
php artisan logs:analyze --channel=database --tail=100

# Look for:
# - N+1 queries
# - Missing indexes
# - Full table scans

# Add indexes as needed
# php artisan make:migration add_index_to_table
```

**Cause 5: Neo4j slow queries**

```bash
# Check Neo4j query performance
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "CALL db.stats.retrieve('GRAPH COUNTS');"

# Check indexes
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "SHOW INDEXES;"

# If indexes missing, create them
# See deployment-runbook.md Section 4.3
```

### Prevention

- Monitor performance logs daily
- Set up alerts for slow requests
- Optimize database queries regularly
- Use caching effectively

---

## 6. High Memory Usage

### Symptoms

- Memory usage > 90%
- OOM (Out of Memory) errors
- Services crashing randomly

### Diagnosis

```bash
# Check memory usage
free -h

# Identify memory hogs
ps aux --sort=-%mem | head -20

# Check for memory leaks
top -o %MEM
```

### Common Causes & Solutions

**Cause 1: Too many queue workers**

```bash
# Check worker count
sudo supervisorctl status | grep ai-legal-queue-worker | wc -l

# Reduce workers
sudo nano /etc/supervisor/conf.d/ai-legal-queue-worker.conf

# Change:
numprocs=2  # From 4

# Apply changes
sudo supervisorctl reread
sudo supervisorctl update

# Stop excess workers
sudo supervisorctl stop ai-legal-queue-worker:2
sudo supervisorctl stop ai-legal-queue-worker:3
```

**Cause 2: Memory leak in queue jobs**

```bash
# Reduce max jobs per worker
sudo nano /etc/supervisor/conf.d/ai-legal-queue-worker.conf

# Change command to include:
--max-jobs=100 --max-time=3600

# Restart workers
sudo supervisorctl restart ai-legal-queue-worker:*
```

**Cause 3: Neo4j using too much memory**

```bash
# Check Neo4j memory
sudo -u neo4j neo4j-admin memrec

# Reduce heap size
sudo nano /etc/neo4j/neo4j.conf

# Change:
server.memory.heap.max_size=1g  # From 2g
server.memory.pagecache.size=512m  # From 1g

# Restart Neo4j
sudo systemctl restart neo4j
```

**Cause 4: PHP memory limit too high**

```bash
# Check PHP memory limit
php -i | grep memory_limit

# Reduce if unnecessarily high
sudo nano /etc/php/8.2/fpm/php.ini

# Change:
memory_limit = 256M  # From 512M

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

**Cause 5: Redis using too much memory**

```bash
# Check Redis memory usage
redis-cli INFO memory | grep used_memory_human

# Set max memory
redis-cli CONFIG SET maxmemory 1gb

# Or edit config
sudo nano /etc/redis/redis.conf

# Change:
maxmemory 1gb
maxmemory-policy allkeys-lru

# Restart Redis
sudo systemctl restart redis-server
```

### Prevention

- Monitor memory usage daily
- Configure appropriate limits for all services
- Set up alerts at 80% memory usage
- Consider upgrading server if consistently high

---

## 7. Disk Space Full

### Symptoms

- "No space left on device" errors
- Cannot write files
- Services failing to start

### Diagnosis

```bash
# Check disk usage
df -h

# Find largest directories
du -sh /var/www/ai-legal-war-machine/* | sort -rh | head -10

# Find largest files
find /var/www/ai-legal-war-machine -type f -size +100M -exec ls -lh {} \;
```

### Common Causes & Solutions

**Cause 1: Log files too large**

```bash
# Check log sizes
du -sh /var/www/ai-legal-war-machine/storage/logs/*.log | sort -rh

# Force log rotation
sudo logrotate -f /etc/logrotate.d/ai-legal-war-machine

# Delete old compressed logs
find /var/www/ai-legal-war-machine/storage/logs -name "*.log.gz" -mtime +7 -delete

# Truncate current log if huge (cautiously)
# > /var/www/ai-legal-war-machine/storage/logs/laravel.log
```

**Cause 2: Too many backups**

```bash
# Check backup sizes
du -sh /var/backups/ai-legal-war-machine/*

# Delete old backups (keep last 10)
ls -t /var/backups/ai-legal-war-machine/db_*.sql.gz | tail -n +11 | xargs rm -f

# Delete old Neo4j backups
find /var/backups/neo4j -name "*.tar.gz" -mtime +14 -delete
```

**Cause 3: Cached files**

```bash
# Clear application cache
cd /var/www/ai-legal-war-machine
php artisan cache:clear
php artisan view:clear

# Clear Redis if using large amount of disk
# (Be cautious - this clears ALL cache)
# redis-cli FLUSHDB
```

**Cause 4: Orphaned files in storage**

```bash
# Find large files in storage
find /var/www/ai-legal-war-machine/storage -type f -size +50M -ls

# Review and delete unnecessary files
# Be careful not to delete application files
```

**Cause 5: System logs**

```bash
# Check system log sizes
sudo du -sh /var/log/* | sort -rh | head -10

# Truncate large logs (cautiously)
sudo truncate -s 0 /var/log/syslog
sudo truncate -s 0 /var/log/kern.log

# Force system logrotate
sudo logrotate -f /etc/logrotate.conf
```

### Prevention

- Monitor disk usage daily
- Set up alerts at 80% disk usage
- Configure logrotate properly
- Automate backup cleanup

---

## 8. Neo4j Connection Errors

### Symptoms

- "Neo4j connection failed" in health check
- "AuthenticationException" errors
- Graph features not working

### Diagnosis

```bash
# Check if Neo4j is running
systemctl status neo4j

# Check Neo4j logs
sudo journalctl -u neo4j -n 100

# Test connection
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "RETURN 'test';"

# Check application logs
grep -i neo4j /var/www/ai-legal-war-machine/storage/logs/laravel.log | tail -20
```

### Common Causes & Solutions

**Cause 1: Neo4j is down**

```bash
# Start Neo4j
sudo systemctl start neo4j

# Wait for startup (can take 30-60 seconds)
sleep 30

# Test connection
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "RETURN 'connected';"

# If start fails, check logs
sudo journalctl -u neo4j -n 100
```

**Cause 2: Wrong password in .env**

```bash
# Verify .env has correct password
grep NEO4J_PASSWORD /var/www/ai-legal-war-machine/.env

# Test with password
cypher-shell -u neo4j -p "YOUR_PASSWORD" "RETURN 'test';"

# If wrong, update .env
nano /var/www/ai-legal-war-machine/.env

# Clear config cache
php artisan config:cache
```

**Cause 3: Neo4j not listening on correct port**

```bash
# Check Neo4j config
sudo nano /etc/neo4j/neo4j.conf

# Verify:
server.bolt.listen_address=0.0.0.0:7687

# Check if port is open
sudo netstat -tulpn | grep 7687

# Restart Neo4j
sudo systemctl restart neo4j
```

**Cause 4: Firewall blocking connection**

```bash
# Check if port 7687 is allowed (for localhost should not be needed)
sudo ufw status | grep 7687

# If needed, allow (only for specific IPs)
# sudo ufw allow from 127.0.0.1 to any port 7687
```

**Cause 5: Neo4j out of memory**

```bash
# Check Neo4j memory usage
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "CALL dbms.queryJmx('java.lang:type=Memory') YIELD attributes RETURN attributes.HeapMemoryUsage.value.used;"

# Check configured heap size
grep heap /etc/neo4j/neo4j.conf

# Increase if needed
sudo nano /etc/neo4j/neo4j.conf

# Change:
server.memory.heap.max_size=2g  # From 1g

# Restart Neo4j
sudo systemctl restart neo4j
```

### Prevention

- Monitor Neo4j status daily
- Include Neo4j in health checks
- Configure appropriate memory limits
- Monitor Neo4j logs

---

## 9. OpenAI API Errors

### Symptoms

- "OpenAI API rate limit exceeded"
- "Invalid API key" errors
- AI features not working

### Diagnosis

```bash
# Check OpenAI logs
tail -100 /var/www/ai-legal-war-machine/storage/logs/openai.log

# Check application logs for OpenAI errors
grep -i openai /var/www/ai-legal-war-machine/storage/logs/laravel.log | tail -20

# Test OpenAI API key
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer YOUR_OPENAI_API_KEY"
```

### Common Causes & Solutions

**Cause 1: Invalid API key**

```bash
# Verify API key in .env
grep OPENAI_API_KEY /var/www/ai-legal-war-machine/.env

# Test API key
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer sk-..." \
  | jq

# If invalid, update .env with correct key
nano /var/www/ai-legal-war-machine/.env

# Clear config cache
php artisan config:cache
```

**Cause 2: Rate limit exceeded**

```bash
# Check OpenAI usage dashboard
# https://platform.openai.com/usage

# Temporarily reduce agent activity
# Edit .env
AGENT_MAX_ITERATIONS=1
AGENT_MAX_COST=1.00

# Clear config cache
php artisan config:cache

# Consider upgrading OpenAI plan
```

**Cause 3: OpenAI service outage**

```bash
# Check OpenAI status
curl https://status.openai.com/api/v2/status.json | jq

# If outage, wait for resolution
# Configure fallback behavior in application
```

**Cause 4: Network connectivity issues**

```bash
# Test connectivity to OpenAI
curl -I https://api.openai.com

# Check firewall rules
sudo ufw status

# Check proxy settings (if applicable)
echo $HTTP_PROXY
echo $HTTPS_PROXY
```

**Cause 5: Timeout errors**

```bash
# Increase timeout in .env
nano /var/www/ai-legal-war-machine/.env

# Change:
OPENAI_TIMEOUT=180  # From 120

# Clear config cache
php artisan config:cache
```

### Prevention

- Monitor OpenAI usage and costs
- Set up alerts for rate limits
- Configure retry logic with exponential backoff
- Monitor OpenAI service status

---

## 10. SSL Certificate Issues

### Symptoms

- "Certificate expired" in browser
- "SSL handshake failed" errors
- HTTPS not working

### Diagnosis

```bash
# Check certificate status
sudo certbot certificates

# Check certificate expiry
openssl s_client -connect your-domain.com:443 -servername your-domain.com 2>/dev/null | openssl x509 -noout -dates

# Check Nginx SSL config
sudo nginx -t

# Check Nginx error logs
tail -100 /var/log/nginx/ai-legal-error.log
```

### Common Causes & Solutions

**Cause 1: Certificate expired**

```bash
# Check expiry date
sudo certbot certificates

# Renew certificate
sudo certbot renew

# Reload Nginx
sudo systemctl reload nginx

# Verify
curl -I https://your-domain.com
```

**Cause 2: Auto-renewal failed**

```bash
# Check certbot timer status
systemctl status certbot.timer

# Enable timer if disabled
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer

# Test renewal
sudo certbot renew --dry-run

# If errors, fix and retry
sudo certbot renew --force-renewal
```

**Cause 3: Wrong certificate path in Nginx config**

```bash
# Check Nginx SSL config
sudo nano /etc/nginx/sites-enabled/ai-legal-war-machine

# Verify paths match certbot output
ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

# Test config
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

**Cause 4: Certificate revoked**

```bash
# Check certificate status
openssl s_client -connect your-domain.com:443 -servername your-domain.com 2>/dev/null | openssl x509 -noout -text | grep -A 2 "OCSP"

# If revoked, obtain new certificate
sudo certbot delete --cert-name your-domain.com
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

### Prevention

- Monitor certificate expiry (auto-renewed at < 30 days)
- Test auto-renewal monthly
- Set up expiry alerts
- Keep Certbot updated

---

## 11. Email Not Sending

### Symptoms

- Password reset emails not received
- Notification emails not delivered
- Mail errors in logs

### Diagnosis

```bash
# Check mail configuration
grep MAIL_ /var/www/ai-legal-war-machine/.env

# Check mail logs
grep -i mail /var/www/ai-legal-war-machine/storage/logs/laravel.log | tail -20

# Test mail
php artisan tinker
>>> Mail::raw('Test', function($m) { $m->to('test@example.com')->subject('Test'); });
>>> exit
```

### Common Causes & Solutions

**Cause 1: SMTP credentials wrong**

```bash
# Verify SMTP settings in .env
grep MAIL_ /var/www/ai-legal-war-machine/.env

# Test SMTP connection
telnet smtp.example.com 587

# Update .env with correct credentials
nano /var/www/ai-legal-war-machine/.env

# Clear config cache
php artisan config:cache
```

**Cause 2: Firewall blocking SMTP port**

```bash
# Check if port 587 (or 465) is open
sudo ufw status | grep -E "587|465"

# If needed, allow outbound SMTP
sudo ufw allow out 587/tcp
sudo ufw allow out 465/tcp
```

**Cause 3: Mail driver misconfigured**

```bash
# Check mail driver in .env
grep MAIL_MAILER /var/www/ai-legal-war-machine/.env

# Should be: MAIL_MAILER=smtp

# If wrong, update
nano /var/www/ai-legal-war-machine/.env
MAIL_MAILER=smtp

# Clear config cache
php artisan config:cache
```

**Cause 4: Queue delay** (if emails queued)

```bash
# Check queue workers
sudo supervisorctl status ai-legal-queue-worker:*

# Check queue size
curl -s http://localhost/api/health | jq '.services.queue.queue_sizes'

# Process queued emails
php artisan queue:work --once
```

### Prevention

- Test email configuration after setup
- Monitor email delivery logs
- Use a reliable SMTP service
- Set up email bounce handling

---

## 12. Redis Connection Errors

### Symptoms

- "Connection refused" errors
- Cache not working
- Session errors

### Diagnosis

```bash
# Check if Redis is running
systemctl status redis-server

# Test connection
redis-cli PING

# If authentication required
redis-cli -a YOUR_PASSWORD PING

# Check Redis logs
sudo journalctl -u redis-server -n 100
```

### Common Causes & Solutions

**Cause 1: Redis is down**

```bash
# Start Redis
sudo systemctl start redis-server

# Check status
systemctl status redis-server

# Test connection
redis-cli PING
```

**Cause 2: Wrong password in .env**

```bash
# Verify password
grep REDIS_PASSWORD /var/www/ai-legal-war-machine/.env

# Test with password
redis-cli -a YOUR_PASSWORD PING

# Update .env if wrong
nano /var/www/ai-legal-war-machine/.env

# Clear config cache
php artisan config:cache
```

**Cause 3: Redis maxmemory reached**

```bash
# Check memory usage
redis-cli INFO memory | grep used_memory_human

# Check maxmemory setting
redis-cli CONFIG GET maxmemory

# Flush old keys
redis-cli FLUSHDB

# Or increase maxmemory
redis-cli CONFIG SET maxmemory 2gb
```

**Cause 4: Too many connections**

```bash
# Check connection count
redis-cli INFO clients | grep connected_clients

# Check max connections
redis-cli CONFIG GET maxclients

# Increase if needed
redis-cli CONFIG SET maxclients 10000
```

### Prevention

- Monitor Redis status daily
- Configure appropriate maxmemory
- Set up eviction policy
- Monitor connection count

---

## 13. Session/Authentication Issues

### Symptoms

- Users logged out unexpectedly
- "CSRF token mismatch" errors
- "Unauthenticated" errors

### Diagnosis

```bash
# Check session configuration
grep SESSION_ /var/www/ai-legal-war-machine/.env

# Check Redis (if using Redis sessions)
redis-cli KEYS "laravel_session:*"

# Check application logs
grep -i "session\|csrf" /var/www/ai-legal-war-machine/storage/logs/laravel.log | tail -20
```

### Common Causes & Solutions

**Cause 1: APP_KEY changed**

```bash
# APP_KEY change invalidates all sessions

# If accidentally changed, restore from backup
# cp .env.backup .env

# Clear sessions
php artisan cache:clear
redis-cli FLUSHDB

# Users will need to re-login
```

**Cause 2: Session lifetime too short**

```bash
# Check session lifetime
grep SESSION_LIFETIME /var/www/ai-legal-war-machine/.env

# Increase if needed
nano /var/www/ai-legal-war-machine/.env
SESSION_LIFETIME=120  # minutes

# Clear config cache
php artisan config:cache
```

**Cause 3: Domain mismatch**

```bash
# Check session domain
grep SESSION_DOMAIN /var/www/ai-legal-war-machine/.env

# Should match application domain
SESSION_DOMAIN=your-domain.com

# Update if wrong
nano /var/www/ai-legal-war-machine/.env

# Clear config cache
php artisan config:cache
```

**Cause 4: Redis sessions lost** (Redis restarted)

```bash
# Check if Redis persistence is enabled
grep -E "appendonly|save" /etc/redis/redis.conf

# Enable AOF persistence
sudo nano /etc/redis/redis.conf

# Set:
appendonly yes

# Restart Redis
sudo systemctl restart redis-server
```

### Prevention

- Don't change APP_KEY in production
- Configure appropriate session lifetime
- Enable Redis persistence
- Monitor session store health

---

## 14. Deployment Failures

### Symptoms

- Deployment script exits with error
- Application broken after deployment
- Services not restarting

### Diagnosis

```bash
# Check deployment log
tail -100 /var/www/ai-legal-war-machine/storage/logs/deploy-*.log

# Check Git status
cd /var/www/ai-legal-war-machine
git status

# Check service status
systemctl status php8.2-fpm nginx
sudo supervisorctl status
```

### Common Causes & Solutions

**Cause 1: Composer install failed**

```bash
# Check Composer version
composer --version

# Clear Composer cache
composer clear-cache

# Retry install
cd /var/www/ai-legal-war-machine
composer install --no-dev --optimize-autoloader

# If still fails, check composer.log
cat storage/logs/composer.log
```

**Cause 2: NPM build failed**

```bash
# Check Node version
node -v
npm -v

# Clear NPM cache
npm cache clean --force

# Retry build
cd /var/www/ai-legal-war-machine
npm ci
npm run build
```

**Cause 3: Migration failed**

```bash
# Check migration status
php artisan migrate:status

# Review failed migration
cat database/migrations/YYYY_MM_DD_XXXXXX_migration_name.php

# Rollback if safe
php artisan migrate:rollback

# Or manually fix database
sudo -u postgres psql ai_legal_war_machine

# Then retry migration
php artisan migrate
```

**Cause 4: Permission denied during deployment**

```bash
# Fix ownership
sudo chown -R deploy:www-data /var/www/ai-legal-war-machine

# Fix permissions
chmod -R 775 /var/www/ai-legal-war-machine/storage
chmod -R 775 /var/www/ai-legal-war-machine/bootstrap/cache

# Retry deployment
./scripts/deploy.sh
```

### Prevention

- Test deployments in staging first
- Always create backup before deployment
- Keep rollback plan ready
- Monitor deployment logs

---

## 15. Log Files Too Large

### Symptoms

- Disk space filling up
- Slow log file access
- Logrotate not working

### Diagnosis

```bash
# Check log file sizes
du -sh /var/www/ai-legal-war-machine/storage/logs/*.log | sort -rh

# Check logrotate status
sudo logrotate -d /etc/logrotate.d/ai-legal-war-machine

# Check logrotate cron
ls -la /etc/cron.daily/logrotate
```

### Common Causes & Solutions

**Cause 1: Logrotate not configured**

```bash
# Verify logrotate config exists
ls -la /etc/logrotate.d/ai-legal-war-machine

# If missing, copy from repository
sudo cp /var/www/ai-legal-war-machine/docs/server-config/logrotate/ai-legal-war-machine /etc/logrotate.d/

# Set permissions
sudo chmod 644 /etc/logrotate.d/ai-legal-war-machine

# Test
sudo logrotate -d /etc/logrotate.d/ai-legal-war-machine
```

**Cause 2: Excessive logging**

```bash
# Reduce log level
nano /var/www/ai-legal-war-machine/.env

# Change:
LOG_LEVEL=warning  # From debug

# Disable database query logging
LOG_DATABASE_LEVEL=warning  # From debug

# Clear config cache
php artisan config:cache
```

**Cause 3: Log file too large to rotate**

```bash
# Truncate large log file
> /var/www/ai-legal-war-machine/storage/logs/laravel.log

# Or move to backup
mv /var/www/ai-legal-war-machine/storage/logs/laravel.log \
   /var/backups/laravel.log.backup

# Create new log file
touch /var/www/ai-legal-war-machine/storage/logs/laravel.log
chown www-data:www-data /var/www/ai-legal-war-machine/storage/logs/laravel.log

# Force rotation
sudo logrotate -f /etc/logrotate.d/ai-legal-war-machine
```

**Cause 4: Logrotate cron not running**

```bash
# Check cron service
systemctl status cron

# Check logrotate cron
ls -la /etc/cron.daily/logrotate

# Manually run logrotate
sudo /etc/cron.daily/logrotate
```

### Prevention

- Monitor log sizes daily
- Configure appropriate log levels
- Verify logrotate is working
- Set up disk space alerts

---

## Quick Troubleshooting Checklist

When encountering any issue:

1. **Check health endpoint**
   ```bash
   curl http://localhost/api/health | jq
   ```

2. **Review recent logs**
   ```bash
   php artisan logs:analyze --errors --tail=50
   ```

3. **Check service status**
   ```bash
   systemctl status nginx php8.2-fpm postgresql redis-server neo4j
   sudo supervisorctl status
   ```

4. **Check system resources**
   ```bash
   df -h
   free -h
   htop
   ```

5. **Check recent changes**
   ```bash
   cd /var/www/ai-legal-war-machine
   git log --oneline -10
   ```

6. **Test database connection**
   ```bash
   psql -h localhost -U ai_legal_user -d ai_legal_war_machine -c "SELECT 1;"
   ```

7. **Restart services if needed**
   ```bash
   sudo systemctl restart php8.2-fpm nginx
   sudo supervisorctl restart ai-legal-queue-worker:*
   ```

---

## Additional Resources

- [Deployment Runbook](deployment-runbook.md) - Complete deployment guide
- [Operations Manual](operations-manual.md) - Day-to-day operations
- [Monitoring Setup](monitoring-setup.md) - Monitoring and logging
- [README](../README.md) - Application overview

---

**End of Troubleshooting Guide**
