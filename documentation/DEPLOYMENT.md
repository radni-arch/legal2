# Deployment Guide

Comprehensive guide for deploying the AI Legal War Machine to production.

## Table of Contents

- [Prerequisites](#prerequisites)
- [System Requirements](#system-requirements)
- [Server Setup](#server-setup)
- [Database Installation](#database-installation)
- [Application Deployment](#application-deployment)
- [Web Server Configuration](#web-server-configuration)
- [Queue Worker Setup](#queue-worker-setup)
- [Security Configuration](#security-configuration)
- [Monitoring & Logging](#monitoring--logging)
- [Backup Strategy](#backup-strategy)
- [Scaling](#scaling)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Accounts

Before deployment, ensure you have:

- [ ] **AWS Account** - For S3 storage and Textract
- [ ] **OpenAI API Key** - For GPT-4o/mini and embeddings
- [ ] **Domain name** - With DNS access
- [ ] **SSL Certificate** - Let's Encrypt or commercial
- [ ] **Git access** - To repository
- [ ] **Server access** - SSH with sudo privileges

### Required Knowledge

- Basic Linux administration
- Docker concepts (optional)
- Laravel framework basics
- PostgreSQL administration
- Web server configuration (Nginx/Apache)

---

## System Requirements

### Minimum Requirements

| Component | Specification |
|-----------|---------------|
| **CPU** | 4 cores (8 recommended) |
| **RAM** | 8 GB (16 GB recommended) |
| **Storage** | 100 GB SSD (250 GB recommended) |
| **OS** | Ubuntu 22.04 LTS or Debian 12 |
| **PHP** | 8.2 or higher |
| **PostgreSQL** | 15+ with pgvector extension |
| **Redis** | 7.0+ |
| **Neo4j** | 5.x (optional but recommended) |
| **Node.js** | 18+ LTS |

### Recommended Production Setup

For production workloads handling 100+ cases:

| Component | Specification |
|-----------|---------------|
| **CPU** | 8 cores |
| **RAM** | 32 GB |
| **Storage** | 500 GB NVMe SSD |
| **Bandwidth** | 1 Gbps |
| **Load Balancer** | Nginx or cloud LB |
| **Database** | Managed PostgreSQL (RDS, Azure DB) |
| **Cache** | Managed Redis (ElastiCache, Azure Cache) |

---

## Server Setup

### 1. Initial Server Configuration

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install essential packages
sudo apt install -y \
    git \
    curl \
    wget \
    unzip \
    software-properties-common \
    ca-certificates \
    apt-transport-https \
    gnupg \
    lsb-release

# Configure timezone
sudo timedatectl set-timezone Europe/Zagreb

# Configure locale
sudo locale-gen hr_HR.UTF-8
sudo update-locale LANG=hr_HR.UTF-8
```

### 2. Install PHP 8.2

```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP and required extensions
sudo apt install -y \
    php8.2-cli \
    php8.2-fpm \
    php8.2-pgsql \
    php8.2-redis \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    php8.2-bcmath \
    php8.2-intl \
    php8.2-gd \
    php8.2-tokenizer \
    php8.2-fileinfo

# Verify PHP installation
php -v

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 3. Configure PHP

```bash
# Edit PHP-FPM configuration
sudo nano /etc/php/8.2/fpm/php.ini
```

Required settings:

```ini
memory_limit = 512M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
max_input_time = 300

; Error logging
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
log_errors = On
error_log = /var/log/php/php-error.log

; OpCache (critical for performance)
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
opcache.enable_cli=0
```

```bash
# Create log directory
sudo mkdir -p /var/log/php
sudo chown www-data:www-data /var/log/php

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
sudo systemctl enable php8.2-fpm
```

---

## Database Installation

### PostgreSQL with pgvector

#### 1. Install PostgreSQL 15

```bash
# Add PostgreSQL repository
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update

# Install PostgreSQL
sudo apt install -y postgresql-15 postgresql-contrib-15

# Start and enable PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

#### 2. Install pgvector Extension

```bash
# Install build dependencies
sudo apt install -y postgresql-server-dev-15 build-essential git

# Clone and build pgvector
cd /tmp
git clone --branch v0.5.1 https://github.com/pgvector/pgvector.git
cd pgvector
make
sudo make install

# Verify installation
ls -la /usr/lib/postgresql/15/lib/vector.so
```

#### 3. Configure PostgreSQL

```bash
# Edit PostgreSQL configuration
sudo nano /etc/postgresql/15/main/postgresql.conf
```

Recommended settings:

```conf
# Connection settings
max_connections = 100
shared_buffers = 4GB                    # 25% of RAM
effective_cache_size = 12GB              # 75% of RAM
maintenance_work_mem = 1GB
work_mem = 64MB

# Write Ahead Log settings
wal_buffers = 16MB
checkpoint_completion_target = 0.9

# Query performance
random_page_cost = 1.1                   # For SSD
effective_io_concurrency = 200           # For SSD
default_statistics_target = 100

# Logging
logging_collector = on
log_directory = 'log'
log_filename = 'postgresql-%Y-%m-%d.log'
log_rotation_age = 1d
log_rotation_size = 100MB
log_min_duration_statement = 1000        # Log slow queries (>1s)
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a,client=%h '
```

```bash
# Restart PostgreSQL
sudo systemctl restart postgresql
```

#### 4. Create Database and User

```bash
# Switch to postgres user
sudo -u postgres psql

-- Create user
CREATE USER ai_legal_user WITH PASSWORD 'your_secure_password_here';

-- Create databases
CREATE DATABASE ai_legal_prod OWNER ai_legal_user;
CREATE DATABASE ai_legal_test OWNER ai_legal_user;

-- Enable pgvector extension
\c ai_legal_prod
CREATE EXTENSION IF NOT EXISTS vector;

\c ai_legal_test
CREATE EXTENSION IF NOT EXISTS vector;

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE ai_legal_prod TO ai_legal_user;
GRANT ALL PRIVILEGES ON DATABASE ai_legal_test TO ai_legal_user;

-- Exit
\q
```

#### 5. Test Database Connection

```bash
psql -h localhost -U ai_legal_user -d ai_legal_prod -c "SELECT version();"
psql -h localhost -U ai_legal_user -d ai_legal_prod -c "SELECT * FROM pg_extension WHERE extname = 'vector';"
```

### Redis Installation

```bash
# Install Redis
sudo apt install -y redis-server

# Configure Redis
sudo nano /etc/redis/redis.conf
```

Recommended settings:

```conf
bind 127.0.0.1 ::1
protected-mode yes
port 6379
maxmemory 2gb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# Logging
loglevel notice
logfile /var/log/redis/redis-server.log
```

```bash
# Restart Redis
sudo systemctl restart redis-server
sudo systemctl enable redis-server

# Test connection
redis-cli ping  # Should return "PONG"
```

### Neo4j Installation (Optional)

```bash
# Add Neo4j repository
wget -O - https://debian.neo4j.com/neotechnology.gpg.key | sudo apt-key add -
echo 'deb https://debian.neo4j.com stable latest' | sudo tee /etc/apt/sources.list.d/neo4j.list
sudo apt update

# Install Neo4j
sudo apt install -y neo4j

# Configure Neo4j
sudo nano /etc/neo4j/neo4j.conf
```

Key settings:

```conf
dbms.default_listen_address=0.0.0.0
dbms.connector.bolt.listen_address=:7687
dbms.connector.http.listen_address=:7474

# Memory settings
dbms.memory.heap.initial_size=2g
dbms.memory.heap.max_size=4g
dbms.memory.pagecache.size=2g

# Security
dbms.security.auth_enabled=true
```

```bash
# Start Neo4j
sudo systemctl start neo4j
sudo systemctl enable neo4j

# Set initial password
sudo neo4j-admin set-initial-password your_neo4j_password

# Verify
curl http://localhost:7474
```

---

## Application Deployment

### 1. Clone Repository

```bash
# Create application directory
sudo mkdir -p /var/www/ai-legal-war-machine
sudo chown $USER:$USER /var/www/ai-legal-war-machine

# Clone repository
cd /var/www
git clone https://github.com/your-org/ai-legal-war-machine.git
cd ai-legal-war-machine

# Checkout production branch
git checkout main
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js and dependencies
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

npm install
npm run build
```

### 3. Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Edit environment file
nano .env
```

**Production .env configuration:**

```env
# Application
APP_NAME="AI Legal War Machine"
APP_ENV=production
APP_KEY=base64:... # Generated by key:generate
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=ai_legal_prod
DB_USERNAME=ai_legal_user
DB_PASSWORD=your_secure_password_here

# Test Database
DB_TEST_DATABASE=ai_legal_test

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Neo4j (optional)
NEO4J_ENABLED=true
NEO4J_URI=bolt://localhost:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your_neo4j_password
NEO4J_AUTO_SYNC=true

# OpenAI
OPENAI_API_KEY=sk-...your_openai_key_here
OPENAI_ORGANIZATION=org-...your_org_id # Optional

# AWS (for Textract)
AWS_ACCESS_KEY_ID=your_aws_access_key
AWS_SECRET_ACCESS_KEY=your_aws_secret_key
AWS_DEFAULT_REGION=eu-central-1
AWS_BUCKET=your-s3-bucket-name
AWS_USE_PATH_STYLE_ENDPOINT=false

# Textract
TEXTRACT_INPUT_PREFIX=textract/input
TEXTRACT_OUTPUT_PREFIX=textract/output

# Google Drive (for Textract pipeline)
GOOGLE_APPLICATION_CREDENTIALS=/var/www/ai-legal-war-machine/storage/google-service-account.json
GOOGLE_DRIVE_FOLDER_ID=your_drive_folder_id

# Odluke.sudovi.hr
ODLUKE_BASE_URL=https://odluke.sudovi.hr
ODLUKE_RPM=30
ODLUKE_TIMEOUT=30
ODLUKE_DELAY_MS=700
ODLUKE_BACKOFF_MS=800

# MCP API
MCP_API_TOKEN=your_secure_random_token_here

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=info
LOG_DEPRECATIONS_CHANNEL=null

# Performance
DB_LOG_QUERIES=false  # Set to true only for debugging
```

### 4. Run Migrations

```bash
# Run database migrations
php artisan migrate --force

# Verify migrations
php artisan migrate:status
```

### 5. Set Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/ai-legal-war-machine

# Set permissions
sudo chmod -R 755 /var/www/ai-legal-war-machine
sudo chmod -R 775 /var/www/ai-legal-war-machine/storage
sudo chmod -R 775 /var/www/ai-legal-war-machine/bootstrap/cache

# Secure .env file
sudo chmod 600 /var/www/ai-legal-war-machine/.env
```

### 6. Optimize Application

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize Composer autoloader
composer dump-autoload --optimize --no-dev
```

---

## Web Server Configuration

### Nginx Configuration

#### 1. Install Nginx

```bash
sudo apt install -y nginx

# Start and enable Nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

#### 2. Create Site Configuration

```bash
sudo nano /etc/nginx/sites-available/ai-legal-war-machine
```

**Configuration:**

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com www.your-domain.com;

    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    root /var/www/ai-legal-war-machine/public;
    index index.php index.html;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;

    # Client upload limits
    client_max_body_size 50M;

    # Timeouts
    client_body_timeout 300s;
    client_header_timeout 300s;
    keepalive_timeout 300s;
    send_timeout 300s;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;

        # Increase timeouts for long-running requests
        fastcgi_read_timeout 300s;
        fastcgi_send_timeout 300s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to sensitive files
    location ~ /\.env {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    # Logging
    access_log /var/log/nginx/ai-legal-access.log;
    error_log /var/log/nginx/ai-legal-error.log;
}
```

#### 3. Enable Site

```bash
# Test configuration
sudo nginx -t

# Enable site
sudo ln -s /etc/nginx/sites-available/ai-legal-war-machine /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Restart Nginx
sudo systemctl restart nginx
```

### SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Test auto-renewal
sudo certbot renew --dry-run

# Set up auto-renewal cron job
echo "0 3 * * * /usr/bin/certbot renew --quiet" | sudo tee -a /etc/crontab
```

---

## Queue Worker Setup

### Supervisor Configuration

#### 1. Install Supervisor

```bash
sudo apt install -y supervisor
```

#### 2. Create Worker Configuration

```bash
sudo nano /etc/supervisor/conf.d/ai-legal-worker.conf
```

**Configuration:**

```ini
[program:ai-legal-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ai-legal-war-machine/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=textract,agents,default
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/ai-legal-war-machine/storage/logs/worker.log
stopwaitsecs=3600
```

#### 3. Start Workers

```bash
# Reload Supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start workers
sudo supervisorctl start ai-legal-worker:*

# Check status
sudo supervisorctl status
```

### Scheduler (Cron)

```bash
# Edit crontab for www-data user
sudo crontab -u www-data -e
```

Add:

```cron
* * * * * cd /var/www/ai-legal-war-machine && php artisan schedule:run >> /dev/null 2>&1
```

---

## Security Configuration

### 1. Firewall (UFW)

```bash
# Install and enable UFW
sudo apt install -y ufw

# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP and HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status
```

### 2. Fail2Ban

```bash
# Install Fail2Ban
sudo apt install -y fail2ban

# Copy default configuration
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local

# Edit configuration
sudo nano /etc/fail2ban/jail.local
```

Add:

```ini
[sshd]
enabled = true
port = 22
maxretry = 3
bantime = 3600

[nginx-http-auth]
enabled = true
port = http,https
```

```bash
# Restart Fail2Ban
sudo systemctl restart fail2ban
sudo systemctl enable fail2ban
```

### 3. Secure PostgreSQL

```bash
# Edit pg_hba.conf
sudo nano /etc/postgresql/15/main/pg_hba.conf
```

Ensure:

```conf
# "local" is for Unix domain socket connections only
local   all             all                                     peer

# IPv4 local connections:
host    all             all             127.0.0.1/32            scram-sha-256

# IPv6 local connections:
host    all             all             ::1/128                 scram-sha-256
```

```bash
# Restart PostgreSQL
sudo systemctl restart postgresql
```

### 4. Application Security

```bash
# In .env - ensure these are set correctly
APP_ENV=production
APP_DEBUG=false

# Secure sensitive files
sudo chmod 600 /var/www/ai-legal-war-machine/.env
sudo chmod 600 /var/www/ai-legal-war-machine/storage/google-service-account.json
```

---

## Monitoring & Logging

### 1. Application Logs

```bash
# Laravel logs
tail -f /var/www/ai-legal-war-machine/storage/logs/laravel.log

# Use Laravel Pail for real-time logs
cd /var/www/ai-legal-war-machine
php artisan pail --timeout=0
```

### 2. System Logs

```bash
# Nginx logs
tail -f /var/log/nginx/ai-legal-access.log
tail -f /var/log/nginx/ai-legal-error.log

# PHP-FPM logs
tail -f /var/log/php8.2-fpm.log

# PostgreSQL logs
sudo tail -f /var/log/postgresql/postgresql-15-main.log

# Redis logs
sudo tail -f /var/log/redis/redis-server.log
```

### 3. System Monitoring

```bash
# Install monitoring tools
sudo apt install -y htop iotop nethogs

# Monitor system resources
htop

# Monitor disk I/O
sudo iotop

# Monitor network
sudo nethogs
```

### 4. Application Monitoring

**Install Laravel Telescope (for debugging):**

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Access at: `https://your-domain.com/telescope`

**Performance Monitoring:**

```bash
# Profile agents
php artisan agents:profile all --runs=5 --save

# Check queue status
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed
```

---

## Backup Strategy

### 1. Database Backups

Create backup script:

```bash
sudo nano /usr/local/bin/backup-database.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/postgresql"
DATE=$(date +%Y-%m-%d_%H-%M-%S)
DB_NAME="ai_legal_prod"
DB_USER="ai_legal_user"

mkdir -p $BACKUP_DIR

# Backup with pg_dump
PGPASSWORD="your_password" pg_dump -h localhost -U $DB_USER -F c -b -v -f "$BACKUP_DIR/${DB_NAME}_${DATE}.backup" $DB_NAME

# Compress backup
gzip "$BACKUP_DIR/${DB_NAME}_${DATE}.backup"

# Delete backups older than 7 days
find $BACKUP_DIR -type f -name "*.backup.gz" -mtime +7 -delete

echo "Backup completed: ${DB_NAME}_${DATE}.backup.gz"
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-database.sh

# Test backup
sudo /usr/local/bin/backup-database.sh

# Schedule daily backups at 2 AM
echo "0 2 * * * /usr/local/bin/backup-database.sh" | sudo tee -a /etc/crontab
```

### 2. Application Backups

```bash
# Create backup script
sudo nano /usr/local/bin/backup-application.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/application"
DATE=$(date +%Y-%m-%d_%H-%M-%S)
APP_DIR="/var/www/ai-legal-war-machine"

mkdir -p $BACKUP_DIR

# Backup storage directory (uploaded files, logs)
tar -czf "$BACKUP_DIR/storage_${DATE}.tar.gz" -C $APP_DIR storage

# Backup .env file
cp "$APP_DIR/.env" "$BACKUP_DIR/env_${DATE}.backup"

# Delete backups older than 14 days
find $BACKUP_DIR -type f -mtime +14 -delete

echo "Application backup completed: storage_${DATE}.tar.gz"
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-application.sh

# Schedule daily backups at 3 AM
echo "0 3 * * * /usr/local/bin/backup-application.sh" | sudo tee -a /etc/crontab
```

### 3. S3 Backup Sync (Optional)

```bash
# Install AWS CLI
sudo apt install -y awscli

# Configure AWS CLI
aws configure

# Sync backups to S3
aws s3 sync /var/backups/postgresql s3://your-bucket/backups/postgresql/
aws s3 sync /var/backups/application s3://your-bucket/backups/application/
```

---

## Scaling

### Horizontal Scaling

For high-traffic deployments:

1. **Load Balancer** - Distribute traffic across multiple app servers
2. **Separate Queue Workers** - Dedicated servers for queue processing
3. **Managed Databases** - Use AWS RDS, Azure Database, or similar
4. **Managed Cache** - Use AWS ElastiCache, Azure Redis Cache
5. **CDN** - CloudFront, Cloudflare for static assets

### Example Architecture

```
                    [Load Balancer]
                          |
          +---------------+---------------+
          |               |               |
    [App Server 1]  [App Server 2]  [App Server 3]
          |               |               |
          +---------------+---------------+
                          |
          +---------------+---------------+
          |               |               |
    [PostgreSQL]      [Redis]         [Neo4j]
       (RDS)       (ElastiCache)    (Managed)
```

### Database Connection Pooling

For multiple app servers, use PgBouncer:

```bash
sudo apt install -y pgbouncer

# Configure PgBouncer
sudo nano /etc/pgbouncer/pgbouncer.ini
```

```ini
[databases]
ai_legal_prod = host=localhost port=5432 dbname=ai_legal_prod

[pgbouncer]
listen_addr = 127.0.0.1
listen_port = 6432
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 25
```

Update Laravel .env:

```env
DB_PORT=6432  # Use PgBouncer port instead of 5432
```

---

## Troubleshooting

### Common Deployment Issues

#### 1. Permission Errors

**Symptom**: "Permission denied" errors in logs

**Solution**:
```bash
sudo chown -R www-data:www-data /var/www/ai-legal-war-machine
sudo chmod -R 775 /var/www/ai-legal-war-machine/storage
sudo chmod -R 775 /var/www/ai-legal-war-machine/bootstrap/cache
```

#### 2. 500 Internal Server Error

**Symptom**: White screen, 500 error

**Solution**:
```bash
# Check Laravel logs
tail -f /var/www/ai-legal-war-machine/storage/logs/laravel.log

# Check Nginx error logs
sudo tail -f /var/log/nginx/ai-legal-error.log

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### 3. Database Connection Failed

**Symptom**: "Could not connect to database"

**Solution**:
```bash
# Test connection
psql -h localhost -U ai_legal_user -d ai_legal_prod

# Check PostgreSQL is running
sudo systemctl status postgresql

# Check credentials in .env
cat .env | grep DB_
```

#### 4. Queue Workers Not Processing

**Symptom**: Jobs stuck in pending

**Solution**:
```bash
# Check worker status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart ai-legal-worker:*

# Check for failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

#### 5. High Memory Usage

**Symptom**: Server OOM, slow performance

**Solution**:
```bash
# Check memory usage
free -h

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Optimize PHP settings
sudo nano /etc/php/8.2/fpm/pool.d/www.conf

# Adjust pm settings:
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```

---

## Post-Deployment Checklist

- [ ] Application is accessible via HTTPS
- [ ] SSL certificate is valid and auto-renewing
- [ ] Database migrations completed successfully
- [ ] Queue workers are running (check `supervisorctl status`)
- [ ] Scheduler cron job is active
- [ ] Backups are configured and tested
- [ ] Logs are being written correctly
- [ ] Firewall is enabled and configured
- [ ] OpenAI API key is working (test with agents)
- [ ] S3 and Textract are working (test upload)
- [ ] Redis cache is working (check `redis-cli ping`)
- [ ] Neo4j is accessible (if enabled)
- [ ] pgvector extension is installed and working
- [ ] Performance optimizations are applied (OpCache, caching)
- [ ] Security headers are set correctly
- [ ] Monitoring is in place
- [ ] Error notifications are configured (Sentry, Slack, etc.)

---

## Initial Data Ingestion

After deployment, ingest initial data:

```bash
cd /var/www/ai-legal-war-machine

# Ingest Croatian laws
php artisan laws:ingest --source=zkp
php artisan laws:ingest --source=kz
php artisan laws:ingest --source=ustav

# Ingest court decisions from odluke.sudovi.hr
php artisan odluke:ingest --limit=100

# Verify vector embeddings
php artisan tinker
>>> DB::table('court_decision_embeddings')->count();
>>> DB::table('law_embeddings')->count();
```

---

## Maintenance

### Regular Maintenance Tasks

**Daily:**
- Check logs for errors
- Monitor queue worker status
- Check disk space

**Weekly:**
- Review failed jobs
- Check slow query log
- Analyze application performance
- Review security logs (Fail2Ban)

**Monthly:**
- Update dependencies: `composer update` (test in staging first)
- Review and optimize database queries
- Clean up old logs: `php artisan log:clear`
- Review backup integrity
- Update SSL certificates (automatic with Let's Encrypt)

### Update Procedure

```bash
# 1. Backup first
/usr/local/bin/backup-database.sh
/usr/local/bin/backup-application.sh

# 2. Enable maintenance mode
php artisan down

# 3. Pull latest code
git pull origin main

# 4. Update dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# 5. Run migrations
php artisan migrate --force

# 6. Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Restart services
sudo systemctl restart php8.2-fpm
sudo supervisorctl restart ai-legal-worker:*

# 8. Disable maintenance mode
php artisan up

# 9. Verify
curl -I https://your-domain.com
```

---

## Support

For deployment issues:
- Check [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
- Review logs: `storage/logs/laravel.log`
- Check system logs: `/var/log/nginx/`, `/var/log/postgresql/`

---

## License

This deployment guide is part of the AI Legal War Machine project.

---

**Last Updated**: 2025-11-11
