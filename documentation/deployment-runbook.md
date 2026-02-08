# Production Deployment Runbook

## AI Legal War Machine - Production Deployment Guide

**Version**: 1.0
**Last Updated**: 2025-11-09
**Target Environment**: Ubuntu 22.04 LTS VPS
**Estimated Time**: 4-6 hours

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Server Preparation](#2-server-preparation)
3. [Install System Dependencies](#3-install-system-dependencies)
4. [Database Setup](#4-database-setup)
5. [Application Deployment](#5-application-deployment)
6. [Environment Configuration](#6-environment-configuration)
7. [Queue Worker Setup](#7-queue-worker-setup)
8. [Web Server Configuration](#8-web-server-configuration)
9. [SSL/TLS Setup](#9-ssltls-setup)
10. [Monitoring Setup](#10-monitoring-setup)
11. [Backup Configuration](#11-backup-configuration)
12. [Security Hardening](#12-security-hardening)
13. [Post-Deployment Verification](#13-post-deployment-verification)
14. [Troubleshooting](#14-troubleshooting)

---

## 1. Prerequisites

### 1.1 Server Requirements

- **VPS**: Ubuntu 22.04 LTS (4GB RAM minimum, 8GB recommended)
- **Disk**: 40GB SSD minimum (100GB recommended for logs and data)
- **CPU**: 2 cores minimum (4 cores recommended)
- **Network**: Static IP address, open ports 80, 443

### 1.2 Domain & DNS

- Domain name registered and DNS configured
- A record pointing to server IP
- AAAA record (if using IPv6)

### 1.3 Access Credentials

Gather the following before starting:

- [ ] OpenAI API key (`OPENAI_API_KEY`)
- [ ] AWS Access Key & Secret (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`)
- [ ] AWS S3 bucket name (`AWS_BUCKET`)
- [ ] Google Service Account JSON (for Drive/Textract pipeline)
- [ ] MCP API token (generate secure random string)
- [ ] Database password (generate strong password)
- [ ] Redis password (generate strong password)
- [ ] Neo4j password (generate strong password)
- [ ] Application key (will be generated during setup)

**Password Generation**:
```bash
# Generate strong random passwords
openssl rand -base64 32
```

### 1.4 Local Development Setup

Ensure the following are tested locally:

- [ ] All tests passing (`composer test`)
- [ ] Database migrations clean (`php artisan migrate:fresh`)
- [ ] Queue workers functional
- [ ] Neo4j graph sync working
- [ ] AWS Textract pipeline tested
- [ ] Health endpoint returns 200 OK

---

## 2. Server Preparation

### 2.1 Initial Server Access

```bash
# SSH into server as root
ssh root@your-server-ip

# Update system
apt update && apt upgrade -y

# Set timezone
timedatectl set-timezone Europe/Zagreb

# Check system info
uname -a
lsb_release -a
```

### 2.2 Create Deployment User

```bash
# Create deploy user
adduser deploy
usermod -aG sudo deploy

# Setup SSH key for deploy user
mkdir -p /home/deploy/.ssh
cp /root/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Test SSH as deploy user (from local machine)
ssh deploy@your-server-ip
```

### 2.3 Configure Firewall

```bash
# Install UFW
apt install ufw -y

# Default policies
ufw default deny incoming
ufw default allow outgoing

# Allow SSH, HTTP, HTTPS
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp

# Enable firewall
ufw enable

# Check status
ufw status verbose
```

---

## 3. Install System Dependencies

### 3.1 PHP 8.2

```bash
# Add PHP repository
apt install software-properties-common -y
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP 8.2 and extensions
apt install php8.2-fpm php8.2-cli php8.2-common -y
apt install php8.2-pgsql php8.2-redis php8.2-curl php8.2-mbstring -y
apt install php8.2-xml php8.2-zip php8.2-bcmath php8.2-gd -y
apt install php8.2-intl php8.2-soap -y

# Verify installation
php -v
php -m | grep -E "pgsql|redis|curl|mbstring|xml|zip"

# Configure PHP-FPM
sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' /etc/php/8.2/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 100M/' /etc/php/8.2/fpm/php.ini
sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.2/fpm/php.ini

# Restart PHP-FPM
systemctl restart php8.2-fpm
systemctl enable php8.2-fpm
```

### 3.2 Composer

```bash
# Download Composer
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php

# Install globally
php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Verify
composer --version
```

### 3.3 Node.js & NPM

```bash
# Install Node.js 20.x
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install nodejs -y

# Verify
node -v
npm -v
```

### 3.4 Git

```bash
# Install Git
apt install git -y

# Configure Git
git config --global user.name "Deploy User"
git config --global user.email "deploy@your-domain.com"

# Verify
git --version
```

### 3.5 Supervisor

```bash
# Install Supervisor
apt install supervisor -y

# Enable and start
systemctl enable supervisor
systemctl start supervisor

# Verify
supervisorctl version
```

### 3.6 Nginx

```bash
# Install Nginx
apt install nginx -y

# Enable and start
systemctl enable nginx
systemctl start nginx

# Verify
nginx -v
curl localhost
```

---

## 4. Database Setup

### 4.1 PostgreSQL 15

```bash
# Add PostgreSQL repository
sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | apt-key add -
apt update

# Install PostgreSQL 15
apt install postgresql-15 postgresql-contrib-15 -y

# Start and enable
systemctl enable postgresql
systemctl start postgresql

# Verify
sudo -u postgres psql -c "SELECT version();"
```

**Create Database and User**:

```bash
sudo -u postgres psql <<EOF
-- Create user
CREATE USER ai_legal_user WITH PASSWORD 'YOUR_DB_PASSWORD_HERE';

-- Create database
CREATE DATABASE ai_legal_war_machine OWNER ai_legal_user;

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE ai_legal_war_machine TO ai_legal_user;

-- Enable required extensions
\c ai_legal_war_machine
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- Grant schema privileges
GRANT ALL ON SCHEMA public TO ai_legal_user;

-- Exit
\q
EOF
```

**Configure PostgreSQL** (`/etc/postgresql/15/main/postgresql.conf`):

```bash
# Edit config
sudo nano /etc/postgresql/15/main/postgresql.conf

# Optimize for production (4GB RAM server)
shared_buffers = 1GB
effective_cache_size = 3GB
maintenance_work_mem = 256MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 10MB
min_wal_size = 1GB
max_wal_size = 4GB

# Restart PostgreSQL
systemctl restart postgresql
```

**Test Connection**:

```bash
psql -h localhost -U ai_legal_user -d ai_legal_war_machine -c "SELECT current_database();"
```

### 4.2 Redis 7

```bash
# Install Redis
apt install redis-server -y

# Configure Redis
nano /etc/redis/redis.conf

# Set password (find and modify)
requirepass YOUR_REDIS_PASSWORD_HERE

# Set max memory (2GB for 8GB server)
maxmemory 2gb
maxmemory-policy allkeys-lru

# Disable RDB snapshots (we'll use AOF)
save ""

# Enable AOF persistence
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec

# Restart Redis
systemctl restart redis-server
systemctl enable redis-server

# Test connection
redis-cli
> AUTH YOUR_REDIS_PASSWORD_HERE
> PING
> INFO server
> EXIT
```

### 4.3 Neo4j 5

```bash
# Add Neo4j repository
wget -O - https://debian.neo4j.com/neotechnology.gpg.key | apt-key add -
echo 'deb https://debian.neo4j.com stable latest' > /etc/apt/sources.list.d/neo4j.list
apt update

# Install Neo4j Community Edition
apt install neo4j -y

# Configure Neo4j
nano /etc/neo4j/neo4j.conf

# Uncomment and set:
dbms.default_listen_address=0.0.0.0
server.bolt.listen_address=0.0.0.0:7687
server.http.listen_address=0.0.0.0:7474
dbms.security.auth_enabled=true

# Increase heap size (for 8GB RAM server)
server.memory.heap.initial_size=1g
server.memory.heap.max_size=2g
server.memory.pagecache.size=1g

# Start Neo4j
systemctl enable neo4j
systemctl start neo4j

# Wait for startup
sleep 10

# Set initial password
neo4j-admin dbms set-initial-password YOUR_NEO4J_PASSWORD_HERE

# Restart Neo4j
systemctl restart neo4j

# Test connection (wait 30 seconds for startup)
sleep 30
cypher-shell -u neo4j -p YOUR_NEO4J_PASSWORD_HERE "RETURN 'Connection successful' AS result;"
```

**Create Graph Indexes**:

```bash
cypher-shell -u neo4j -p YOUR_NEO4J_PASSWORD_HERE <<EOF
CREATE INDEX law_id IF NOT EXISTS FOR (l:Law) ON (l.id);
CREATE INDEX case_id IF NOT EXISTS FOR (c:Case) ON (c.id);
CREATE INDEX decision_id IF NOT EXISTS FOR (d:Decision) ON (d.id);
CREATE INDEX keyword_name IF NOT EXISTS FOR (k:Keyword) ON (k.name);
CREATE INDEX topic_name IF NOT EXISTS FOR (t:Topic) ON (t.name);
EOF
```

---

## 5. Application Deployment

### 5.1 Clone Repository

```bash
# Create web directory
sudo mkdir -p /var/www
sudo chown deploy:deploy /var/www

# Clone repository (as deploy user)
cd /var/www
git clone https://github.com/aglavas/ai-legal-war-machine.git
cd ai-legal-war-machine

# Checkout production branch (if exists)
git checkout main

# Verify files
ls -la
```

### 5.2 Install Dependencies

```bash
# Install Composer dependencies (production mode)
composer install --optimize-autoloader --no-dev

# Install NPM dependencies
npm ci

# Build frontend assets
npm run build

# Verify
ls -la public/build
```

### 5.3 Directory Permissions

```bash
# Set ownership
sudo chown -R deploy:www-data /var/www/ai-legal-war-machine

# Set directory permissions
find /var/www/ai-legal-war-machine -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/ai-legal-war-machine -type f -exec chmod 644 {} \;

# Storage and cache directories need write permissions
chmod -R 775 storage bootstrap/cache
chgrp -R www-data storage bootstrap/cache

# Verify
ls -la storage/
```

---

## 6. Environment Configuration

### 6.1 Create Environment File

```bash
cd /var/www/ai-legal-war-machine

# Copy example
cp .env.example .env

# Edit environment file
nano .env
```

### 6.2 Production Environment Variables

```env
# ============================================
# APPLICATION
# ============================================
APP_NAME="AI Legal War Machine"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=  # Will be generated in next step

# ============================================
# LOGGING
# ============================================
LOG_CHANNEL=stack
LOG_LEVEL=info
LOG_STACK=daily,slack

# Log retention (days)
LOG_DAILY_DAYS=14
LOG_PERFORMANCE_LEVEL=info
LOG_QUEUE_LEVEL=info
LOG_SECURITY_LEVEL=warning
LOG_API_LEVEL=info
LOG_DATABASE_LEVEL=info
LOG_MONITORING_LEVEL=info
LOG_AGENTS_LEVEL=info

# Slack alerts (optional)
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
LOG_SLACK_USERNAME="AI Legal War Machine"
LOG_SLACK_EMOJI=":robot_face:"

# ============================================
# DATABASE
# ============================================
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ai_legal_war_machine
DB_USERNAME=ai_legal_user
DB_PASSWORD=YOUR_DB_PASSWORD_HERE

# Connection pool
DB_POOL_MIN=2
DB_POOL_MAX=20

# ============================================
# REDIS
# ============================================
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=YOUR_REDIS_PASSWORD_HERE
REDIS_PORT=6379
REDIS_DB=0

# Cache
CACHE_DRIVER=redis
CACHE_PREFIX=ai_legal

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_DOMAIN=your-domain.com
SESSION_SECURE_COOKIE=true

# Queue
QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database

# ============================================
# NEO4J GRAPH DATABASE
# ============================================
NEO4J_ENABLED=true
NEO4J_URI=bolt://localhost:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=YOUR_NEO4J_PASSWORD_HERE
NEO4J_DATABASE=neo4j
NEO4J_AUTO_SYNC=true
NEO4J_BATCH_SIZE=100
NEO4J_MAX_RETRIES=3

# ============================================
# OPENAI
# ============================================
OPENAI_API_KEY=YOUR_OPENAI_API_KEY_HERE
OPENAI_ORGANIZATION=  # Optional
OPENAI_MODEL=gpt-4o-mini
OPENAI_TIMEOUT=120
OPENAI_MAX_RETRIES=3

# OpenAI cache TTL (seconds)
OPENAI_CACHE_TTL=86400

# ============================================
# AWS (S3 & TEXTRACT)
# ============================================
AWS_ACCESS_KEY_ID=YOUR_AWS_ACCESS_KEY_HERE
AWS_SECRET_ACCESS_KEY=YOUR_AWS_SECRET_KEY_HERE
AWS_DEFAULT_REGION=eu-central-1
AWS_BUCKET=your-s3-bucket-name

# Textract
TEXTRACT_S3_INPUT_PREFIX=textract/input/
TEXTRACT_S3_OUTPUT_PREFIX=textract/output/
TEXTRACT_S3_RESULTS_PREFIX=textract/results/
TEXTRACT_TIMEOUT=300
TEXTRACT_MAX_POLL_ATTEMPTS=60

# ============================================
# GOOGLE DRIVE (FOR TEXTRACT PIPELINE)
# ============================================
GOOGLE_APPLICATION_CREDENTIALS=/var/www/ai-legal-war-machine/storage/app/google-service-account.json
GOOGLE_DRIVE_FOLDER_ID=YOUR_DRIVE_FOLDER_ID

# ============================================
# MCP (MODEL CONTEXT PROTOCOL)
# ============================================
MCP_API_TOKEN=YOUR_MCP_API_TOKEN_HERE
MCP_RATE_LIMIT=60

# ============================================
# ODLUKE.SUDOVI.HR CLIENT
# ============================================
ODLUKE_RPM=30
ODLUKE_DELAY_MS=700
ODLUKE_BACKOFF_MS=800
ODLUKE_TIMEOUT=30
ODLUKE_MAX_RETRIES=3

# Circuit breaker
ODLUKE_CIRCUIT_BREAKER_THRESHOLD=3
ODLUKE_CIRCUIT_BREAKER_TIMEOUT=60

# ============================================
# AGENT CONFIGURATION
# ============================================
AGENT_MAX_ITERATIONS=10
AGENT_MAX_COST=5.00
AGENT_TIMEOUT=300
AGENT_MIN_QUALITY_SCORE=7.0

# ============================================
# MAIL (OPTIONAL)
# ============================================
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# ============================================
# BROADCASTING (NOT USED)
# ============================================
BROADCAST_DRIVER=log
```

### 6.3 Generate Application Key

```bash
# Generate key
php artisan key:generate

# Verify .env has APP_KEY set
grep APP_KEY .env
```

### 6.4 Upload Google Service Account JSON

```bash
# Create storage directory
mkdir -p storage/app

# Upload from local machine (run on local)
scp google-service-account.json deploy@your-server-ip:/var/www/ai-legal-war-machine/storage/app/

# Back on server, set permissions
chmod 600 storage/app/google-service-account.json
chown deploy:www-data storage/app/google-service-account.json
```

### 6.5 Run Migrations

```bash
# Clear config cache
php artisan config:clear

# Test database connection
php artisan db:show

# Run migrations
php artisan migrate --force

# Verify tables
php artisan db:table users
```

### 6.6 Cache Configuration

```bash
# Cache config
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

---

## 7. Queue Worker Setup

### 7.1 Create Supervisor Configuration

```bash
# Create config file
sudo nano /etc/supervisor/conf.d/ai-legal-queue-worker.conf
```

**Configuration**:

```ini
[program:ai-legal-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ai-legal-war-machine/artisan queue:work --queue=high,textract,agents,default,low --tries=3 --timeout=600 --sleep=3 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/ai-legal-war-machine/storage/logs/queue-worker.log
stopwaitsecs=3600
```

### 7.2 Start Queue Workers

```bash
# Reload Supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start workers
sudo supervisorctl start ai-legal-queue-worker:*

# Check status
sudo supervisorctl status

# Monitor logs
tail -f storage/logs/queue-worker.log
```

### 7.3 Schedule Task Runner

```bash
# Add to crontab (as deploy user)
crontab -e

# Add this line
* * * * * cd /var/www/ai-legal-war-machine && php artisan schedule:run >> /dev/null 2>&1

# Verify crontab
crontab -l
```

---

## 8. Web Server Configuration

### 8.1 Create Nginx Site Configuration

```bash
# Create config
sudo nano /etc/nginx/sites-available/ai-legal-war-machine
```

**Configuration**:

```nginx
# Rate limiting zone
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=60r/m;

# Upstream PHP-FPM
upstream php-fpm {
    server unix:/var/run/php/php8.2-fpm.sock;
}

# HTTP redirect to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com www.your-domain.com;

    # Let's Encrypt challenge
    location /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
    }

    # Redirect all other traffic to HTTPS
    location / {
        return 301 https://$server_name$request_uri;
    }
}

# HTTPS server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    root /var/www/ai-legal-war-machine/public;
    index index.php;

    charset utf-8;

    # SSL certificates (will be added by Certbot)
    # ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    # SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    ssl_stapling on;
    ssl_stapling_verify on;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Logging
    access_log /var/log/nginx/ai-legal-access.log;
    error_log /var/log/nginx/ai-legal-error.log;

    # Client body size (for PDF uploads)
    client_max_body_size 100M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/json application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Health check endpoint (no rate limiting)
    location = /api/health {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # API endpoints (rate limited)
    location /api/ {
        limit_req zone=api_limit burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # MCP endpoints (rate limited)
    location /mcp/ {
        limit_req zone=api_limit burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Laravel front controller
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP handler
    location ~ \.php$ {
        fastcgi_pass php-fpm;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # PHP timeouts
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_connect_timeout 300;

        # Hide PHP version
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Deny access to sensitive files
    location ~ /\.(?:git|env|htaccess) {
        deny all;
    }
}
```

### 8.2 Enable Site and Test Configuration

```bash
# Create Let's Encrypt directory
sudo mkdir -p /var/www/letsencrypt

# Enable site
sudo ln -s /etc/nginx/sites-available/ai-legal-war-machine /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx

# Test HTTP access
curl http://your-domain.com
```

---

## 9. SSL/TLS Setup

### 9.1 Install Certbot

```bash
# Install Certbot
apt install certbot python3-certbot-nginx -y

# Verify
certbot --version
```

### 9.2 Obtain SSL Certificate

```bash
# Obtain certificate (interactive)
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Follow prompts:
# - Enter email address
# - Agree to Terms of Service
# - Choose whether to redirect HTTP to HTTPS (select Yes)

# Verify certificate
sudo certbot certificates
```

### 9.3 Test SSL Configuration

```bash
# Test HTTPS
curl -I https://your-domain.com

# Should return HTTP/2 200 OK

# Test SSL Labs (optional, from browser)
# https://www.ssllabs.com/ssltest/analyze.html?d=your-domain.com
```

### 9.4 Auto-Renewal

```bash
# Test renewal
sudo certbot renew --dry-run

# Certbot automatically adds renewal to cron/systemd
# Verify renewal timer
systemctl list-timers | grep certbot
```

---

## 10. Monitoring Setup

### 10.1 Install Logrotate Configuration

```bash
# Copy logrotate config
sudo cp /var/www/ai-legal-war-machine/docs/server-config/logrotate/ai-legal-war-machine /etc/logrotate.d/

# Set permissions
sudo chmod 644 /etc/logrotate.d/ai-legal-war-machine

# Test configuration
sudo logrotate -d /etc/logrotate.d/ai-legal-war-machine

# Verify no errors
```

### 10.2 External Monitoring Setup

**Option 1: UptimeRobot** (Free)

1. Go to https://uptimerobot.com
2. Create account
3. Add new monitor:
   - Monitor Type: HTTP(s)
   - Friendly Name: AI Legal War Machine
   - URL: https://your-domain.com/api/health
   - Monitoring Interval: 5 minutes
   - Alert Contacts: Add your email/SMS

**Option 2: Pingdom** (Paid)

1. Go to https://www.pingdom.com
2. Create account
3. Add new check:
   - Check Type: HTTP
   - URL: https://your-domain.com/api/health
   - Check Interval: 1 minute
   - Alert When: Response time > 5000ms or check fails

### 10.3 Health Check Verification

```bash
# Test health endpoint
curl https://your-domain.com/api/health | jq

# Should return:
# {
#   "status": "healthy",
#   "services": {
#     "database": { "status": "healthy" },
#     "redis": { "status": "healthy" },
#     "neo4j": { "status": "healthy" },
#     "openai": { "status": "healthy" },
#     "aws": { "status": "healthy" },
#     "queue": { "status": "healthy" }
#   }
# }
```

### 10.4 Slack Notifications (Optional)

```bash
# Create Slack incoming webhook
# 1. Go to https://api.slack.com/apps
# 2. Create new app
# 3. Add Incoming Webhooks
# 4. Copy webhook URL

# Add to .env
nano .env

# Add:
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Test notification
php artisan tinker
>>> Log::channel('slack')->critical('Test alert from production');

# Check Slack channel for message
```

---

## 11. Backup Configuration

### 11.1 Database Backup Script

```bash
# Create backup script
nano /var/www/ai-legal-war-machine/scripts/backup-database.sh
```

**Script**:

```bash
#!/bin/bash

# Database backup script
# Run daily via cron

set -e

# Configuration
DB_NAME="ai_legal_war_machine"
DB_USER="ai_legal_user"
BACKUP_DIR="/var/backups/ai-legal-war-machine"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/db_${DATE}.sql.gz"

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Dump database
echo "Starting database backup..."
PGPASSWORD="${DB_PASSWORD}" pg_dump -h localhost -U "$DB_USER" "$DB_NAME" | gzip > "$BACKUP_FILE"

# Verify backup
if [ -f "$BACKUP_FILE" ]; then
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo "Backup completed: $BACKUP_FILE ($SIZE)"
else
    echo "ERROR: Backup failed!"
    exit 1
fi

# Remove old backups
find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +$RETENTION_DAYS -delete
echo "Old backups removed (retention: ${RETENTION_DAYS} days)"

# Backup count
COUNT=$(find "$BACKUP_DIR" -name "db_*.sql.gz" | wc -l)
echo "Total backups: $COUNT"
```

**Make Executable**:

```bash
chmod +x /var/www/ai-legal-war-machine/scripts/backup-database.sh

# Test run
DB_PASSWORD="YOUR_DB_PASSWORD" /var/www/ai-legal-war-machine/scripts/backup-database.sh

# Verify backup
ls -lh /var/backups/ai-legal-war-machine/
```

### 11.2 Schedule Backups

```bash
# Add to crontab (as deploy user)
crontab -e

# Add daily backup at 2 AM
0 2 * * * DB_PASSWORD="YOUR_DB_PASSWORD" /var/www/ai-legal-war-machine/scripts/backup-database.sh >> /var/www/ai-legal-war-machine/storage/logs/backup.log 2>&1
```

### 11.3 Neo4j Backup

```bash
# Create Neo4j backup script
nano /var/www/ai-legal-war-machine/scripts/backup-neo4j.sh
```

**Script**:

```bash
#!/bin/bash

set -e

BACKUP_DIR="/var/backups/neo4j"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=14

mkdir -p "$BACKUP_DIR"

# Stop Neo4j
systemctl stop neo4j

# Backup data directory
tar czf "${BACKUP_DIR}/neo4j_${DATE}.tar.gz" /var/lib/neo4j/data

# Start Neo4j
systemctl start neo4j

# Cleanup old backups
find "$BACKUP_DIR" -name "neo4j_*.tar.gz" -mtime +$RETENTION_DAYS -delete

echo "Neo4j backup completed: neo4j_${DATE}.tar.gz"
```

**Make Executable and Schedule**:

```bash
chmod +x /var/www/ai-legal-war-machine/scripts/backup-neo4j.sh

# Add to crontab (weekly on Sunday at 3 AM)
crontab -e
0 3 * * 0 sudo /var/www/ai-legal-war-machine/scripts/backup-neo4j.sh >> /var/www/ai-legal-war-machine/storage/logs/backup.log 2>&1
```

### 11.4 Application Files Backup

```bash
# Create application backup script
nano /var/www/ai-legal-war-machine/scripts/backup-app.sh
```

**Script**:

```bash
#!/bin/bash

set -e

BACKUP_DIR="/var/backups/ai-legal-war-machine"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7

mkdir -p "$BACKUP_DIR"

# Backup storage directory (uploaded files, logs)
tar czf "${BACKUP_DIR}/storage_${DATE}.tar.gz" \
    -C /var/www/ai-legal-war-machine \
    storage \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*'

# Cleanup old backups
find "$BACKUP_DIR" -name "storage_*.tar.gz" -mtime +$RETENTION_DAYS -delete

echo "Application files backup completed: storage_${DATE}.tar.gz"
```

**Make Executable and Schedule**:

```bash
chmod +x /var/www/ai-legal-war-machine/scripts/backup-app.sh

# Add to crontab (daily at 4 AM)
crontab -e
0 4 * * * /var/www/ai-legal-war-machine/scripts/backup-app.sh >> /var/www/ai-legal-war-machine/storage/logs/backup.log 2>&1
```

---

## 12. Security Hardening

### 12.1 Disable Root SSH Login

```bash
# Edit SSH config
sudo nano /etc/ssh/sshd_config

# Set these values
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
PermitEmptyPasswords no

# Restart SSH
sudo systemctl restart sshd
```

### 12.2 Install Fail2Ban

```bash
# Install Fail2Ban
apt install fail2ban -y

# Create local config
sudo nano /etc/fail2ban/jail.local
```

**Configuration**:

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = 22
logpath = /var/log/auth.log

[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log

[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 10
```

**Start Fail2Ban**:

```bash
systemctl enable fail2ban
systemctl start fail2ban

# Check status
fail2ban-client status
fail2ban-client status sshd
```

### 12.3 Secure File Permissions

```bash
# Ensure proper ownership
sudo chown -R deploy:www-data /var/www/ai-legal-war-machine

# Lock down .env file
chmod 600 /var/www/ai-legal-war-machine/.env
chown deploy:deploy /var/www/ai-legal-war-machine/.env

# Lock down Google credentials
chmod 600 /var/www/ai-legal-war-machine/storage/app/google-service-account.json
chown deploy:www-data /var/www/ai-legal-war-machine/storage/app/google-service-account.json

# Verify
ls -la /var/www/ai-legal-war-machine/ | grep -E "\.env|google"
```

### 12.4 Enable PostgreSQL SSL (Optional)

```bash
# Generate self-signed certificate (or use Let's Encrypt cert)
sudo -u postgres openssl req -new -x509 -days 365 -nodes -text \
    -out /etc/postgresql/15/main/server.crt \
    -keyout /etc/postgresql/15/main/server.key \
    -subj "/CN=localhost"

# Set permissions
sudo chmod 600 /etc/postgresql/15/main/server.key
sudo chown postgres:postgres /etc/postgresql/15/main/server.key

# Enable SSL in postgresql.conf
sudo nano /etc/postgresql/15/main/postgresql.conf
# Set: ssl = on

# Restart PostgreSQL
sudo systemctl restart postgresql

# Verify SSL
sudo -u postgres psql -c "SHOW ssl;"
```

### 12.5 Configure Redis ACLs (Optional)

```bash
# Connect to Redis
redis-cli

# Authenticate
AUTH YOUR_REDIS_PASSWORD_HERE

# Create limited user for application
ACL SETUSER ai_legal_app on >YOUR_APP_REDIS_PASSWORD +@all ~*

# Save ACL
ACL SAVE

# Update .env to use new user
# REDIS_USERNAME=ai_legal_app
# REDIS_PASSWORD=YOUR_APP_REDIS_PASSWORD
```

---

## 13. Post-Deployment Verification

### 13.1 Health Check

```bash
# Test health endpoint
curl https://your-domain.com/api/health | jq

# Verify all services return "healthy"
# Expected output:
# {
#   "status": "healthy",
#   "services": {
#     "database": { "status": "healthy" },
#     "redis": { "status": "healthy" },
#     "neo4j": { "status": "healthy" },
#     "openai": { "status": "healthy" },
#     "aws": { "status": "healthy" },
#     "queue": { "status": "healthy" }
#   },
#   "metrics": {
#     "response_time_ms": 15.42,
#     "memory_usage_mb": 45.23,
#     "uptime_seconds": 86400
#   }
# }
```

### 13.2 Test Database Connection

```bash
cd /var/www/ai-legal-war-machine

# Show database info
php artisan db:show

# Test query
php artisan tinker
>>> DB::select('SELECT 1 as test');
>>> exit
```

### 13.3 Test Queue Workers

```bash
# Check worker status
sudo supervisorctl status ai-legal-queue-worker:*

# Should show 4 RUNNING processes

# Dispatch test job
php artisan tinker
>>> dispatch(function() { Log::info('Queue test job executed'); });
>>> exit

# Check logs
tail -f storage/logs/laravel.log
# Should see "Queue test job executed"
```

### 13.4 Test Neo4j Graph

```bash
# Query Neo4j
cypher-shell -u neo4j -p YOUR_NEO4J_PASSWORD_HERE "MATCH (n) RETURN count(n) AS node_count;"

# From application
php artisan graph:stats

# Expected output showing graph statistics
```

### 13.5 Test OpenAI Integration

```bash
php artisan tinker
>>> $openai = app(\App\Services\OpenAIService::class);
>>> $result = $openai->chat([['role' => 'user', 'content' => 'Test']]);
>>> dump($result['choices'][0]['message']['content']);
>>> exit

# Should return response from OpenAI
```

### 13.6 Test File Upload and Textract

```bash
# Queue a test Textract job
php artisan textract:process-drive-folder YOUR_FOLDER_ID --limit=1

# Monitor queue
php artisan queue:monitor

# Check Textract logs
tail -f storage/logs/queue.log
```

### 13.7 Test Log Analysis

```bash
# Generate some log entries
php artisan tinker
>>> Log::info('Test info message');
>>> Log::warning('Test warning message');
>>> Log::error('Test error message');
>>> exit

# Analyze logs
php artisan logs:analyze --stats

# Should show statistics including test messages
```

### 13.8 Test External Access

```bash
# From external machine (or browser)
curl https://your-domain.com

# Should return application homepage (200 OK)

# Test API authentication
curl https://your-domain.com/api/health

# Should return health status (200 OK)
```

### 13.9 Monitor System Resources

```bash
# Check CPU and memory
htop

# Check disk space
df -h

# Check database connections
sudo -u postgres psql -c "SELECT count(*) FROM pg_stat_activity;"

# Check Redis memory
redis-cli INFO memory

# Check Neo4j status
systemctl status neo4j
```

### 13.10 Verify Backups

```bash
# Check backup directory
ls -lh /var/backups/ai-legal-war-machine/

# Should see recent database backup

# Test database restore (to temporary database)
BACKUP_FILE=$(ls -t /var/backups/ai-legal-war-machine/db_*.sql.gz | head -1)
echo "Testing restore of: $BACKUP_FILE"

# Create test database
sudo -u postgres psql -c "CREATE DATABASE ai_legal_test;"

# Restore backup
gunzip -c "$BACKUP_FILE" | sudo -u postgres psql ai_legal_test

# Verify
sudo -u postgres psql -c "\l" | grep ai_legal_test

# Cleanup
sudo -u postgres psql -c "DROP DATABASE ai_legal_test;"
```

---

## 14. Troubleshooting

### 14.1 Application Errors

**Issue**: White screen / 500 error

```bash
# Check PHP-FPM logs
tail -f /var/log/php8.2-fpm.log

# Check Laravel logs
tail -f /var/www/ai-legal-war-machine/storage/logs/laravel.log

# Check Nginx error log
tail -f /var/log/nginx/ai-legal-error.log

# Common fixes:
# 1. Clear cache
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 2. Fix permissions
sudo chown -R deploy:www-data /var/www/ai-legal-war-machine
chmod -R 775 storage bootstrap/cache

# 3. Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

### 14.2 Database Connection Issues

**Issue**: SQLSTATE[08006] Connection refused

```bash
# Check PostgreSQL is running
systemctl status postgresql

# Check PostgreSQL logs
tail -f /var/log/postgresql/postgresql-15-main.log

# Test connection
psql -h localhost -U ai_legal_user -d ai_legal_war_machine -c "SELECT 1;"

# Check pg_hba.conf allows local connections
sudo cat /etc/postgresql/15/main/pg_hba.conf | grep local

# Should have:
# local   all             all                                     peer
# host    all             all             127.0.0.1/32            scram-sha-256

# Restart PostgreSQL
sudo systemctl restart postgresql
```

### 14.3 Queue Workers Not Processing

**Issue**: Jobs stuck in queue

```bash
# Check Supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart ai-legal-queue-worker:*

# Check queue worker logs
tail -f /var/www/ai-legal-war-machine/storage/logs/queue-worker.log

# Clear failed jobs
php artisan queue:flush

# Monitor queue in real-time
php artisan queue:monitor
```

### 14.4 Neo4j Connection Errors

**Issue**: Neo4j\Driver\Exception\AuthenticationException

```bash
# Check Neo4j is running
systemctl status neo4j

# Check logs
journalctl -u neo4j -f

# Test connection
cypher-shell -u neo4j -p YOUR_PASSWORD "RETURN 'test';"

# Common fixes:
# 1. Verify password in .env matches
grep NEO4J_PASSWORD /var/www/ai-legal-war-machine/.env

# 2. Restart Neo4j
sudo systemctl restart neo4j

# 3. Check firewall
sudo ufw status | grep 7687
```

### 14.5 OpenAI Rate Limiting

**Issue**: OpenAI API rate limit exceeded

```bash
# Check OpenAI logs
tail -f /var/www/ai-legal-war-machine/storage/logs/openai.log

# Temporarily disable agent auto-research
# Edit .env
AGENT_MAX_ITERATIONS=1

# Reload config
php artisan config:cache

# Monitor API usage in OpenAI dashboard
# https://platform.openai.com/usage
```

### 14.6 SSL Certificate Issues

**Issue**: Certificate expired or invalid

```bash
# Check certificate status
sudo certbot certificates

# Renew certificate manually
sudo certbot renew

# Test renewal
sudo certbot renew --dry-run

# Check Nginx SSL config
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

### 14.7 Disk Space Full

**Issue**: No space left on device

```bash
# Check disk usage
df -h

# Find largest directories
du -sh /var/www/ai-legal-war-machine/* | sort -rh | head -10

# Common culprits:
# 1. Logs
du -sh /var/www/ai-legal-war-machine/storage/logs

# Clear old logs manually
find /var/www/ai-legal-war-machine/storage/logs -name "*.log.*" -mtime +7 -delete

# 2. Force log rotation
sudo logrotate -f /etc/logrotate.d/ai-legal-war-machine

# 3. Clear old backups
find /var/backups -type f -mtime +30 -delete
```

### 14.8 High Memory Usage

**Issue**: Server running out of memory

```bash
# Check memory usage
free -h

# Check processes
ps aux --sort=-%mem | head -20

# Common fixes:
# 1. Reduce queue worker count
sudo nano /etc/supervisor/conf.d/ai-legal-queue-worker.conf
# Set: numprocs=2

sudo supervisorctl reread
sudo supervisorctl update

# 2. Reduce Neo4j heap
sudo nano /etc/neo4j/neo4j.conf
# Set: server.memory.heap.max_size=512m

sudo systemctl restart neo4j

# 3. Reduce PHP-FPM workers
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
# Set: pm.max_children = 10

sudo systemctl restart php8.2-fpm
```

### 14.9 Performance Issues

**Issue**: Slow response times

```bash
# Check performance logs
php artisan logs:analyze --channel=performance --tail=50

# Identify slow requests
grep "Slow request" storage/logs/performance.log | tail -20

# Check database query performance
php artisan logs:analyze --channel=database --since="1 hour ago"

# Common fixes:
# 1. Clear query cache
php artisan cache:clear

# 2. Optimize database
sudo -u postgres vacuumdb --analyze ai_legal_war_machine

# 3. Check Neo4j indexes
cypher-shell -u neo4j -p PASSWORD "SHOW INDEXES;"

# 4. Monitor real-time
tail -f storage/logs/performance.log
```

### 14.10 Email Not Sending (If Configured)

**Issue**: Mail not being delivered

```bash
# Test mail configuration
php artisan tinker
>>> Mail::raw('Test email', function($m) { $m->to('test@example.com')->subject('Test'); });
>>> exit

# Check mail logs
tail -f storage/logs/laravel.log | grep -i mail

# Check SMTP connection
telnet smtp.example.com 587

# Verify .env mail settings
grep MAIL_ /var/www/ai-legal-war-machine/.env
```

---

## 15. Rollback Procedure

If deployment fails, follow these steps:

### 15.1 Application Rollback

```bash
# Stop queue workers
sudo supervisorctl stop ai-legal-queue-worker:*

# Checkout previous commit
cd /var/www/ai-legal-war-machine
git log --oneline -10
git checkout PREVIOUS_COMMIT_HASH

# Reinstall dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Run migrations down (if needed)
php artisan migrate:rollback --step=1

# Clear cache
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart workers
sudo supervisorctl start ai-legal-queue-worker:*

# Test
curl https://your-domain.com/api/health
```

### 15.2 Database Rollback

```bash
# Restore from backup
BACKUP_FILE="/var/backups/ai-legal-war-machine/db_YYYYMMDD_HHMMSS.sql.gz"

# Drop current database
sudo -u postgres psql -c "DROP DATABASE ai_legal_war_machine;"

# Recreate database
sudo -u postgres psql -c "CREATE DATABASE ai_legal_war_machine OWNER ai_legal_user;"

# Restore backup
gunzip -c "$BACKUP_FILE" | sudo -u postgres psql ai_legal_war_machine

# Verify
php artisan db:show
```

---

## 16. Deployment Checklist

Before going live, ensure all items are checked:

### Pre-Deployment

- [ ] All tests passing locally (`composer test`)
- [ ] Database migrations tested
- [ ] .env.example updated with all required variables
- [ ] Documentation updated
- [ ] Security audit completed
- [ ] Performance testing completed
- [ ] Backup and restore procedures tested

### Deployment

- [ ] Server provisioned and accessible
- [ ] All system dependencies installed
- [ ] Databases created and configured (PostgreSQL, Redis, Neo4j)
- [ ] Application deployed and permissions set
- [ ] Environment variables configured
- [ ] Migrations run successfully
- [ ] Queue workers running
- [ ] Web server configured and tested
- [ ] SSL certificate obtained and configured
- [ ] Monitoring configured (UptimeRobot/Pingdom)
- [ ] Backups scheduled and tested
- [ ] Security hardening completed

### Post-Deployment

- [ ] Health check endpoint responding
- [ ] All services reporting healthy
- [ ] Queue workers processing jobs
- [ ] Neo4j graph accessible
- [ ] OpenAI integration working
- [ ] AWS Textract pipeline functional
- [ ] External monitoring active
- [ ] Slack notifications working (if configured)
- [ ] SSL certificate valid and auto-renewal configured
- [ ] Backups running and tested
- [ ] Log rotation configured
- [ ] Performance monitoring active

### Go-Live

- [ ] DNS updated to production server
- [ ] Smoke tests passing
- [ ] Monitoring dashboards showing healthy status
- [ ] Support team notified
- [ ] Documentation distributed
- [ ] Rollback procedure documented and tested

---

## 17. Support and Maintenance

### Daily Checks

```bash
# Health check
curl https://your-domain.com/api/health | jq

# Log analysis
php artisan logs:analyze --errors --since="1 day ago"

# Queue status
sudo supervisorctl status

# Disk space
df -h
```

### Weekly Checks

```bash
# Performance analysis
php artisan logs:analyze --channel=performance --stats

# Security log review
php artisan logs:analyze --channel=security --since="1 week ago"

# Backup verification
ls -lh /var/backups/ai-legal-war-machine/

# System updates
sudo apt update
sudo apt list --upgradable
```

### Monthly Checks

```bash
# SSL certificate expiry
sudo certbot certificates

# Security updates
sudo apt update && sudo apt upgrade -y

# Database vacuum
sudo -u postgres vacuumdb --analyze ai_legal_war_machine

# Neo4j store statistics
cypher-shell -u neo4j -p PASSWORD "CALL db.stats.retrieve('GRAPH COUNTS');"

# Review monitoring alerts
# Check UptimeRobot/Pingdom dashboard
```

---

## Appendix A: Environment Variables Reference

See Section 6.2 for complete list.

## Appendix B: Port Reference

| Service | Port | Protocol | Firewall |
|---------|------|----------|----------|
| HTTP | 80 | TCP | Open |
| HTTPS | 443 | TCP | Open |
| SSH | 22 | TCP | Open (restricted) |
| PostgreSQL | 5432 | TCP | Localhost only |
| Redis | 6379 | TCP | Localhost only |
| Neo4j Bolt | 7687 | TCP | Localhost only |
| Neo4j HTTP | 7474 | TCP | Localhost only |

## Appendix C: Service Dependencies

```
Application (Laravel)
├── PHP 8.2 (with extensions)
├── PostgreSQL 15
├── Redis 7
├── Neo4j 5
├── OpenAI API (external)
└── AWS (S3, Textract) (external)

Queue Workers (Supervisor)
├── PHP 8.2
└── All database services

Web Server (Nginx)
└── PHP-FPM 8.2

Monitoring
├── Logrotate
├── UptimeRobot/Pingdom (external)
└── Slack (external, optional)
```

## Appendix D: File Permissions Reference

```
/var/www/ai-legal-war-machine/
├── Owner: deploy:www-data
├── Directories: 755
├── Files: 644
├── .env: 600 (deploy:deploy)
├── storage/: 775 (deploy:www-data)
├── bootstrap/cache/: 775 (deploy:www-data)
└── storage/app/google-service-account.json: 600 (deploy:www-data)
```

---

**End of Deployment Runbook**

For additional help:
- API Documentation: `documentation/API_DOCUMENTATION.md`
- README: `README.md`
- Monitoring Setup: `documentation/monitoring-setup.md`
- PostgreSQL Guide: `documentation/postgresql-setup-guide.md`
