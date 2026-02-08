# Sprint 12: Documentation & Go-Live

**Days**: 12-14
**Goal**: Complete production documentation and deploy to VPS
**Tasks**: 6
**Priority**: High

[Link back to index](PRODUCTION_SPRINTS_INDEX.md)

---

## Overview

Sprint 12 is the final sprint in the production deployment sequence. This sprint focuses on creating comprehensive operational documentation, deployment automation, and executing the production go-live. All systems must be documented, tested, and verified before final deployment.

**Success Criteria**:
- Complete deployment runbook with VPS setup instructions
- Automated deployment script with rollback capability
- Operations manual for daily maintenance
- Troubleshooting guide for common issues
- Final production testing completed
- Successful production deployment

---

## Task 12.1: Deployment Runbook

**File**: `docs/DEPLOYMENT_RUNBOOK.md`
**Priority**: Critical
**Estimated Time**: 4-6 hours
**Dependencies**: None

### Objective

Create a comprehensive step-by-step guide for deploying the AI Legal War Machine to a production VPS server, including all infrastructure setup, service installation, and application deployment procedures.

### Implementation Details

#### 1. Document Structure

```markdown
# Deployment Runbook

## Table of Contents
1. VPS Server Setup
2. System Prerequisites
3. Database Installation (PostgreSQL)
4. Cache Installation (Redis)
5. Graph Database Installation (Neo4j)
6. Web Server Installation (Nginx + PHP-FPM)
7. SSL Certificate Setup
8. Application Deployment
9. Post-Deployment Verification
10. Rollback Procedure

## 1. VPS Server Setup

### Server Requirements
- **OS**: Ubuntu 22.04 LTS
- **CPU**: 4 cores minimum (8 cores recommended)
- **RAM**: 16GB minimum (32GB recommended)
- **Storage**: 200GB SSD minimum
- **Network**: 100Mbps minimum

### Initial Server Configuration

#### 1.1 Connect to Server
```bash
ssh root@YOUR_VPS_IP
```

#### 1.2 Update System
```bash
apt update && apt upgrade -y
apt install -y software-properties-common curl wget git unzip
```

#### 1.3 Create Application User
```bash
adduser --disabled-password --gecos "" legalwar
usermod -aG sudo legalwar
```

#### 1.4 Configure Firewall
```bash
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw enable
```

#### 1.5 Set Timezone
```bash
timedatectl set-timezone Europe/Zagreb
```

## 2. System Prerequisites

### 2.1 Install Base Packages
```bash
apt install -y \
  build-essential \
  apt-transport-https \
  ca-certificates \
  supervisor \
  fail2ban \
  ufw
```

### 2.2 Configure Fail2Ban
```bash
cat > /etc/fail2ban/jail.local <<EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
EOF

systemctl enable fail2ban
systemctl start fail2ban
```

## 3. PostgreSQL Installation

### 3.1 Install PostgreSQL 15
```bash
sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | apt-key add -
apt update
apt install -y postgresql-15 postgresql-contrib-15
```

### 3.2 Configure PostgreSQL
```bash
# Edit postgresql.conf
nano /etc/postgresql/15/main/postgresql.conf
```

**Key Settings**:
```conf
max_connections = 200
shared_buffers = 4GB
effective_cache_size = 12GB
maintenance_work_mem = 1GB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 10485kB
min_wal_size = 1GB
max_wal_size = 4GB
```

### 3.3 Create Database and User
```bash
sudo -u postgres psql <<EOF
CREATE USER legalwar WITH PASSWORD 'SECURE_PASSWORD_HERE';
CREATE DATABASE legalwar_production OWNER legalwar;
GRANT ALL PRIVILEGES ON DATABASE legalwar_production TO legalwar;
\c legalwar_production
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
EOF
```

### 3.4 Configure Authentication
```bash
nano /etc/postgresql/15/main/pg_hba.conf
```

Add:
```conf
local   legalwar_production   legalwar                     scram-sha-256
host    legalwar_production   legalwar   127.0.0.1/32      scram-sha-256
```

### 3.5 Restart PostgreSQL
```bash
systemctl restart postgresql
systemctl enable postgresql
```

## 4. Redis Installation

### 4.1 Install Redis
```bash
apt install -y redis-server
```

### 4.2 Configure Redis
```bash
nano /etc/redis/redis.conf
```

**Key Settings**:
```conf
supervised systemd
bind 127.0.0.1
maxmemory 2gb
maxmemory-policy allkeys-lru
```

### 4.3 Start Redis
```bash
systemctl restart redis-server
systemctl enable redis-server
```

## 5. Neo4j Installation

### 5.1 Install Java (Required for Neo4j)
```bash
apt install -y openjdk-17-jdk
```

### 5.2 Install Neo4j
```bash
wget -O - https://debian.neo4j.com/neotechnology.gpg.key | apt-key add -
echo 'deb https://debian.neo4j.com stable latest' > /etc/apt/sources.list.d/neo4j.list
apt update
apt install -y neo4j=1:5.13.0
```

### 5.3 Configure Neo4j
```bash
nano /etc/neo4j/neo4j.conf
```

**Key Settings**:
```conf
dbms.memory.heap.initial_size=4g
dbms.memory.heap.max_size=4g
dbms.memory.pagecache.size=4g
dbms.default_listen_address=127.0.0.1
dbms.connector.bolt.enabled=true
dbms.connector.http.enabled=true
```

### 5.4 Set Neo4j Password
```bash
neo4j-admin dbms set-initial-password 'SECURE_PASSWORD_HERE'
```

### 5.5 Start Neo4j
```bash
systemctl restart neo4j
systemctl enable neo4j
```

## 6. PHP 8.2 + Nginx Installation

### 6.1 Install PHP 8.2
```bash
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y \
  php8.2-fpm \
  php8.2-cli \
  php8.2-common \
  php8.2-pgsql \
  php8.2-redis \
  php8.2-xml \
  php8.2-mbstring \
  php8.2-curl \
  php8.2-zip \
  php8.2-gd \
  php8.2-bcmath \
  php8.2-intl
```

### 6.2 Configure PHP-FPM
```bash
nano /etc/php/8.2/fpm/pool.d/www.conf
```

**Key Settings**:
```conf
user = legalwar
group = legalwar
listen = /run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
```

### 6.3 Configure PHP Settings
```bash
nano /etc/php/8.2/fpm/php.ini
```

**Key Settings**:
```ini
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
```

### 6.4 Install Nginx
```bash
apt install -y nginx
```

### 6.5 Configure Nginx
```bash
nano /etc/nginx/sites-available/legalwar
```

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/legalwar/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 6.6 Enable Site
```bash
ln -s /etc/nginx/sites-available/legalwar /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default
nginx -t
systemctl restart nginx
systemctl enable nginx
```

## 7. SSL Certificate Setup (Let's Encrypt)

### 7.1 Install Certbot
```bash
apt install -y certbot python3-certbot-nginx
```

### 7.2 Obtain Certificate
```bash
certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

### 7.3 Auto-Renewal
```bash
systemctl enable certbot.timer
systemctl start certbot.timer
```

## 8. Application Deployment

### 8.1 Install Composer
```bash
cd /tmp
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

### 8.2 Clone Repository
```bash
mkdir -p /var/www
cd /var/www
git clone https://github.com/yourusername/ai-legal-war-machine.git legalwar
cd legalwar
chown -R legalwar:legalwar /var/www/legalwar
```

### 8.3 Install Dependencies
```bash
sudo -u legalwar composer install --no-dev --optimize-autoloader
```

### 8.4 Configure Environment
```bash
sudo -u legalwar cp .env.example .env.production
sudo -u legalwar php artisan key:generate --env=production
```

Edit `.env.production`:
```bash
nano .env.production
```

**Required Settings**:
```env
APP_NAME="AI Legal War Machine"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=legalwar_production
DB_USERNAME=legalwar
DB_PASSWORD=SECURE_PASSWORD_HERE

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

NEO4J_HOST=127.0.0.1
NEO4J_PORT=7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=SECURE_PASSWORD_HERE

OPENAI_API_KEY=your_openai_key_here

QUEUE_CONNECTION=redis
```

### 8.5 Run Migrations
```bash
sudo -u legalwar php artisan migrate --env=production --force
```

### 8.6 Seed Database
```bash
sudo -u legalwar php artisan db:seed --env=production --force
```

### 8.7 Optimize Application
```bash
sudo -u legalwar php artisan config:cache
sudo -u legalwar php artisan route:cache
sudo -u legalwar php artisan view:cache
```

### 8.8 Set Permissions
```bash
chown -R legalwar:www-data /var/www/legalwar
chmod -R 755 /var/www/legalwar
chmod -R 775 /var/www/legalwar/storage
chmod -R 775 /var/www/legalwar/bootstrap/cache
```

## 9. Queue Workers Setup

### 9.1 Configure Supervisor
```bash
nano /etc/supervisor/conf.d/legalwar-worker.conf
```

```ini
[program:legalwar-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/legalwar/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=legalwar
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/legalwar/storage/logs/worker.log
stopwaitsecs=3600
```

### 9.2 Start Workers
```bash
supervisorctl reread
supervisorctl update
supervisorctl start legalwar-worker:*
```

## 10. Post-Deployment Verification

### 10.1 Check Services
```bash
systemctl status postgresql
systemctl status redis-server
systemctl status neo4j
systemctl status nginx
systemctl status php8.2-fpm
supervisorctl status
```

### 10.2 Test Database Connection
```bash
sudo -u legalwar php artisan tinker
# In tinker:
DB::connection()->getPdo();
```

### 10.3 Test Application
```bash
curl -I https://yourdomain.com
```

### 10.4 Check Logs
```bash
tail -f /var/log/nginx/error.log
tail -f /var/www/legalwar/storage/logs/laravel.log
```

## 11. Rollback Procedure

### 11.1 Enable Maintenance Mode
```bash
cd /var/www/legalwar
sudo -u legalwar php artisan down
```

### 11.2 Restore Previous Code
```bash
git reset --hard PREVIOUS_COMMIT_HASH
sudo -u legalwar composer install --no-dev --optimize-autoloader
```

### 11.3 Restore Database
```bash
sudo -u postgres psql legalwar_production < /backups/db_backup_TIMESTAMP.sql
```

### 11.4 Clear Caches
```bash
sudo -u legalwar php artisan config:clear
sudo -u legalwar php artisan route:clear
sudo -u legalwar php artisan view:clear
sudo -u legalwar php artisan cache:clear
```

### 11.5 Disable Maintenance Mode
```bash
sudo -u legalwar php artisan up
```

## First-Time vs Update Deployment

### First-Time Deployment
- Complete server setup (sections 1-7)
- Application deployment (section 8)
- Queue workers setup (section 9)
- Verification (section 10)

### Update Deployment
1. Enable maintenance mode
2. Git pull latest code
3. Run `composer install`
4. Run migrations
5. Clear and rebuild caches
6. Restart queue workers
7. Disable maintenance mode
8. Verify deployment

---

## Troubleshooting

### Service Won't Start
```bash
journalctl -u SERVICE_NAME -n 50
```

### Permission Issues
```bash
chown -R legalwar:www-data /var/www/legalwar
chmod -R 755 /var/www/legalwar
```

### Database Connection Failed
- Check PostgreSQL is running: `systemctl status postgresql`
- Verify credentials in `.env.production`
- Check pg_hba.conf authentication

### 502 Bad Gateway
- Check PHP-FPM status: `systemctl status php8.2-fpm`
- Verify socket path in Nginx config
- Check PHP-FPM logs: `tail -f /var/log/php8.2-fpm.log`
```

#### 2. Checklist

- [ ] Document VPS server requirements
- [ ] Write Ubuntu 22.04 setup instructions
- [ ] Document PostgreSQL installation and configuration
- [ ] Document Redis installation and configuration
- [ ] Document Neo4j installation and configuration
- [ ] Document PHP 8.2 + Nginx setup
- [ ] Document SSL certificate setup with Let's Encrypt
- [ ] Write first-time deployment procedure
- [ ] Write update deployment procedure
- [ ] Document rollback procedure
- [ ] Include troubleshooting for common issues
- [ ] Add verification steps

#### 3. Acceptance Criteria

- Complete server setup instructions from bare Ubuntu to running application
- All service configurations documented
- Clear distinction between first-time and update deployments
- Rollback procedure tested and verified
- Documentation reviewed by operations team

---

## Task 12.2: Deployment Automation Script

**File**: `scripts/deploy.sh`
**Priority**: Critical
**Estimated Time**: 3-4 hours
**Dependencies**: Task 12.1

### Objective

Create an automated deployment script that handles the complete deployment process including maintenance mode, code updates, migrations, cache management, and rollback on failure.

### Implementation Details

#### 1. Deployment Script

**File**: `scripts/deploy.sh`

```bash
#!/bin/bash

#############################################################################
# AI Legal War Machine - Deployment Script
#############################################################################
# This script automates the deployment process with rollback capability
#
# Usage:
#   ./scripts/deploy.sh [branch]
#
# Example:
#   ./scripts/deploy.sh main
#   ./scripts/deploy.sh develop
#############################################################################

set -e  # Exit on error

# Configuration
APP_DIR="/var/www/legalwar"
APP_USER="legalwar"
BRANCH="${1:-main}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/legalwar"
LOG_FILE="/var/www/legalwar/storage/logs/deployment_${TIMESTAMP}.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

#############################################################################
# Helper Functions
#############################################################################

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1" | tee -a "$LOG_FILE"
}

check_user() {
    if [ "$EUID" -ne 0 ]; then
        error "Please run as root or with sudo"
        exit 1
    fi
}

backup_database() {
    log "Backing up database..."
    mkdir -p "$BACKUP_DIR"
    sudo -u postgres pg_dump legalwar_production > "${BACKUP_DIR}/db_${TIMESTAMP}.sql"
    if [ $? -eq 0 ]; then
        log "Database backed up to ${BACKUP_DIR}/db_${TIMESTAMP}.sql"
    else
        error "Database backup failed"
        return 1
    fi
}

backup_code() {
    log "Backing up code..."
    CURRENT_COMMIT=$(cd "$APP_DIR" && git rev-parse HEAD)
    echo "$CURRENT_COMMIT" > "${BACKUP_DIR}/commit_${TIMESTAMP}.txt"
    log "Current commit: $CURRENT_COMMIT"
}

maintenance_on() {
    log "Enabling maintenance mode..."
    cd "$APP_DIR"
    sudo -u "$APP_USER" php artisan down --render="errors::503"
}

maintenance_off() {
    log "Disabling maintenance mode..."
    cd "$APP_DIR"
    sudo -u "$APP_USER" php artisan up
}

pull_code() {
    log "Pulling latest code from branch: $BRANCH"
    cd "$APP_DIR"
    sudo -u "$APP_USER" git fetch origin
    sudo -u "$APP_USER" git checkout "$BRANCH"
    sudo -u "$APP_USER" git pull origin "$BRANCH"
    NEW_COMMIT=$(git rev-parse HEAD)
    log "Deployed commit: $NEW_COMMIT"
}

install_dependencies() {
    log "Installing composer dependencies..."
    cd "$APP_DIR"
    sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --no-interaction
}

run_migrations() {
    log "Running database migrations..."
    cd "$APP_DIR"
    sudo -u "$APP_USER" php artisan migrate --force
}

clear_caches() {
    log "Clearing caches..."
    cd "$APP_DIR"
    sudo -u "$APP_USER" php artisan config:clear
    sudo -u "$APP_USER" php artisan route:clear
    sudo -u "$APP_USER" php artisan view:clear
    sudo -u "$APP_USER" php artisan cache:clear
}

warm_caches() {
    log "Warming caches..."
    cd "$APP_DIR"
    sudo -u "$APP_USER" php artisan config:cache
    sudo -u "$APP_USER" php artisan route:cache
    sudo -u "$APP_USER" php artisan view:cache
}

restart_services() {
    log "Restarting PHP-FPM..."
    systemctl restart php8.2-fpm

    log "Restarting queue workers..."
    supervisorctl restart legalwar-worker:*

    log "Reloading Nginx..."
    systemctl reload nginx
}

health_check() {
    log "Running health checks..."

    # Check database connection
    cd "$APP_DIR"
    if sudo -u "$APP_USER" php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB OK';" | grep -q "DB OK"; then
        log "✓ Database connection OK"
    else
        error "✗ Database connection failed"
        return 1
    fi

    # Check Redis connection
    if redis-cli ping | grep -q "PONG"; then
        log "✓ Redis connection OK"
    else
        error "✗ Redis connection failed"
        return 1
    fi

    # Check Neo4j connection
    if curl -s http://localhost:7474 > /dev/null; then
        log "✓ Neo4j connection OK"
    else
        error "✗ Neo4j connection failed"
        return 1
    fi

    # Check web server
    if curl -s -o /dev/null -w "%{http_code}" http://localhost | grep -q "200\|302"; then
        log "✓ Web server responding"
    else
        error "✗ Web server not responding"
        return 1
    fi

    # Check queue workers
    if supervisorctl status legalwar-worker:* | grep -q "RUNNING"; then
        log "✓ Queue workers running"
    else
        error "✗ Queue workers not running"
        return 1
    fi

    log "All health checks passed!"
    return 0
}

rollback() {
    error "Deployment failed! Rolling back..."

    maintenance_on

    # Restore code
    if [ -f "${BACKUP_DIR}/commit_${TIMESTAMP}.txt" ]; then
        ROLLBACK_COMMIT=$(cat "${BACKUP_DIR}/commit_${TIMESTAMP}.txt")
        log "Rolling back to commit: $ROLLBACK_COMMIT"
        cd "$APP_DIR"
        sudo -u "$APP_USER" git reset --hard "$ROLLBACK_COMMIT"
        install_dependencies
    fi

    # Restore database
    if [ -f "${BACKUP_DIR}/db_${TIMESTAMP}.sql" ]; then
        log "Restoring database..."
        sudo -u postgres psql legalwar_production < "${BACKUP_DIR}/db_${TIMESTAMP}.sql"
    fi

    clear_caches
    warm_caches
    restart_services
    maintenance_off

    error "Rollback completed. Please investigate the issue."
    exit 1
}

#############################################################################
# Main Deployment Process
#############################################################################

main() {
    log "========================================="
    log "Starting deployment to branch: $BRANCH"
    log "========================================="

    check_user

    # Backup phase
    if ! backup_database; then
        error "Backup failed. Aborting deployment."
        exit 1
    fi
    backup_code

    # Deployment phase
    maintenance_on

    if ! pull_code; then
        rollback
    fi

    if ! install_dependencies; then
        rollback
    fi

    if ! run_migrations; then
        rollback
    fi

    clear_caches
    warm_caches
    restart_services

    # Verification phase
    sleep 5  # Give services time to start

    if ! health_check; then
        rollback
    fi

    maintenance_off

    log "========================================="
    log "Deployment completed successfully!"
    log "========================================="
    log "Deployed commit: $(cd "$APP_DIR" && git rev-parse HEAD)"
    log "Backup location: $BACKUP_DIR"
    log "Log file: $LOG_FILE"
}

# Run main function
main

exit 0
```

#### 2. Make Script Executable

```bash
chmod +x scripts/deploy.sh
```

#### 3. Usage Examples

```bash
# Deploy main branch
sudo ./scripts/deploy.sh main

# Deploy develop branch
sudo ./scripts/deploy.sh develop

# View deployment logs
tail -f /var/www/legalwar/storage/logs/deployment_*.log
```

#### 4. Checklist

- [ ] Create deployment script with error handling
- [ ] Implement maintenance mode on/off
- [ ] Add git pull and branch checkout
- [ ] Add composer install step
- [ ] Implement database migration step
- [ ] Add cache clearing and warming
- [ ] Add service restart logic
- [ ] Implement comprehensive health checks
- [ ] Add automatic rollback on failure
- [ ] Add database backup before deployment
- [ ] Add code backup (commit hash)
- [ ] Add detailed logging
- [ ] Test deployment on staging environment
- [ ] Test rollback procedure
- [ ] Document script usage

#### 5. Acceptance Criteria

- Script successfully deploys application with zero downtime
- Automatic rollback works on any failure
- All services verified via health checks
- Database and code backups created before deployment
- Deployment logs captured for auditing
- Script tested on staging environment

---

## Task 12.3: Operations Manual

**File**: `docs/OPERATIONS_MANUAL.md`
**Priority**: High
**Estimated Time**: 4-5 hours
**Dependencies**: Tasks 12.1, 12.2

### Objective

Create a comprehensive operations manual covering daily operations, database maintenance, queue management, log review, backup procedures, performance tuning, and scaling strategies.

### Implementation Details

#### 1. Operations Manual Content

**File**: `docs/OPERATIONS_MANUAL.md`

```markdown
# Operations Manual

## Table of Contents

1. Daily Operations Checklist
2. Database Maintenance
3. Queue Worker Management
4. Log Review Procedures
5. Backup Procedures
6. Performance Tuning Guide
7. Scaling Guide
8. Monitoring and Alerts

---

## 1. Daily Operations Checklist

### Morning Checklist (9:00 AM)

- [ ] Check all services are running
  ```bash
  systemctl status postgresql
  systemctl status redis-server
  systemctl status neo4j
  systemctl status nginx
  systemctl status php8.2-fpm
  supervisorctl status
  ```

- [ ] Review system resources
  ```bash
  htop  # Check CPU and memory
  df -h  # Check disk space
  iostat -x 1 5  # Check disk I/O
  ```

- [ ] Check application logs for errors
  ```bash
  tail -n 100 /var/www/legalwar/storage/logs/laravel.log | grep ERROR
  ```

- [ ] Check Nginx error log
  ```bash
  tail -n 100 /var/log/nginx/error.log
  ```

- [ ] Verify queue workers processing jobs
  ```bash
  supervisorctl status legalwar-worker:*
  tail -f /var/www/legalwar/storage/logs/worker.log
  ```

- [ ] Check failed jobs queue
  ```bash
  cd /var/www/legalwar
  sudo -u legalwar php artisan queue:failed
  ```

- [ ] Monitor database connections
  ```bash
  sudo -u postgres psql -c "SELECT count(*) FROM pg_stat_activity;"
  ```

### Afternoon Checklist (2:00 PM)

- [ ] Review application performance metrics
- [ ] Check disk space growth trends
- [ ] Review backup completion status
- [ ] Check for pending system updates

### Evening Checklist (6:00 PM)

- [ ] Review daily error summary
- [ ] Check batch job completion
- [ ] Verify backup integrity
- [ ] Plan maintenance windows if needed

---

## 2. Database Maintenance

### Daily Maintenance

#### Vacuum and Analyze
```bash
# Run daily at 2 AM via cron
0 2 * * * sudo -u postgres psql legalwar_production -c "VACUUM ANALYZE;"
```

#### Check Database Size
```bash
sudo -u postgres psql -c "
SELECT
    pg_database.datname,
    pg_size_pretty(pg_database_size(pg_database.datname)) AS size
FROM pg_database
WHERE datname = 'legalwar_production';
"
```

### Weekly Maintenance

#### Full Vacuum (Sunday 3 AM)
```bash
0 3 * * 0 sudo -u postgres psql legalwar_production -c "VACUUM FULL ANALYZE;"
```

#### Reindex (Sunday 4 AM)
```bash
0 4 * * 0 sudo -u postgres psql legalwar_production -c "REINDEX DATABASE legalwar_production;"
```

#### Check Table Bloat
```bash
sudo -u postgres psql legalwar_production <<EOF
SELECT
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename) - pg_relation_size(schemaname||'.'||tablename)) AS external_size
FROM pg_tables
WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
LIMIT 10;
EOF
```

### Monthly Maintenance

#### Update Statistics
```bash
sudo -u postgres psql legalwar_production -c "ANALYZE;"
```

#### Check for Missing Indexes
```bash
cd /var/www/legalwar
sudo -u legalwar php artisan db:check-indexes
```

---

## 3. Queue Worker Management

### Check Worker Status
```bash
supervisorctl status legalwar-worker:*
```

### Restart Workers
```bash
# Restart all workers
supervisorctl restart legalwar-worker:*

# Restart specific worker
supervisorctl restart legalwar-worker:legalwar-worker_00
```

### Monitor Queue Length
```bash
cd /var/www/legalwar
sudo -u legalwar php artisan queue:monitor redis:default --max=1000
```

### View Failed Jobs
```bash
sudo -u legalwar php artisan queue:failed
```

### Retry Failed Jobs
```bash
# Retry all failed jobs
sudo -u legalwar php artisan queue:retry all

# Retry specific job
sudo -u legalwar php artisan queue:retry JOB_ID
```

### Clear Failed Jobs
```bash
sudo -u legalwar php artisan queue:flush
```

### Scaling Workers

**Add More Workers**:
Edit `/etc/supervisor/conf.d/legalwar-worker.conf`:
```ini
numprocs=8  # Increase from 4 to 8
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start legalwar-worker:*
```

---

## 4. Log Review Procedures

### Application Logs

#### Laravel Log
```bash
# View recent errors
tail -n 500 /var/www/legalwar/storage/logs/laravel.log | grep ERROR

# Follow log in real-time
tail -f /var/www/legalwar/storage/logs/laravel.log

# Search for specific error
grep "OpenAI" /var/www/legalwar/storage/logs/laravel.log
```

#### Worker Log
```bash
tail -f /var/www/legalwar/storage/logs/worker.log
```

### System Logs

#### Nginx Access Log
```bash
tail -f /var/log/nginx/access.log
```

#### Nginx Error Log
```bash
tail -f /var/log/nginx/error.log
```

#### PHP-FPM Log
```bash
tail -f /var/log/php8.2-fpm.log
```

#### PostgreSQL Log
```bash
tail -f /var/log/postgresql/postgresql-15-main.log
```

#### Neo4j Log
```bash
tail -f /var/log/neo4j/neo4j.log
```

### Log Rotation

Ensure logrotate is configured:
```bash
cat > /etc/logrotate.d/legalwar <<EOF
/var/www/legalwar/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 legalwar legalwar
    sharedscripts
}
EOF
```

---

## 5. Backup Procedures

### Database Backups

#### Daily Backup (3 AM)
```bash
0 3 * * * /usr/local/bin/backup-database.sh
```

**Script**: `/usr/local/bin/backup-database.sh`
```bash
#!/bin/bash
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/legalwar/database"
mkdir -p "$BACKUP_DIR"

# Backup database
sudo -u postgres pg_dump legalwar_production | gzip > "$BACKUP_DIR/db_${TIMESTAMP}.sql.gz"

# Keep last 30 days
find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +30 -delete

# Upload to S3 (optional)
# aws s3 cp "$BACKUP_DIR/db_${TIMESTAMP}.sql.gz" s3://your-bucket/backups/
```

#### Restore Database
```bash
gunzip -c /var/backups/legalwar/database/db_TIMESTAMP.sql.gz | sudo -u postgres psql legalwar_production
```

### Code Backups

#### Git Repository
Ensure all code is committed and pushed to remote:
```bash
cd /var/www/legalwar
git status
git log -1
```

### File Backups

#### Storage Directory Backup
```bash
#!/bin/bash
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/legalwar/storage"
mkdir -p "$BACKUP_DIR"

tar -czf "$BACKUP_DIR/storage_${TIMESTAMP}.tar.gz" /var/www/legalwar/storage

# Keep last 7 days
find "$BACKUP_DIR" -name "storage_*.tar.gz" -mtime +7 -delete
```

---

## 6. Performance Tuning Guide

### Database Performance

#### Identify Slow Queries
```bash
sudo -u postgres psql legalwar_production <<EOF
SELECT
    query,
    calls,
    total_time,
    mean_time,
    max_time
FROM pg_stat_statements
ORDER BY mean_time DESC
LIMIT 10;
EOF
```

#### Add Missing Indexes
```sql
CREATE INDEX idx_cases_status ON cases(status);
CREATE INDEX idx_documents_case_id ON documents(case_id);
CREATE INDEX idx_violations_type ON violations(type);
```

#### Optimize Queries
- Use EXPLAIN ANALYZE
- Add appropriate indexes
- Use query result caching
- Implement database connection pooling

### Application Performance

#### Enable OPcache
Already configured in `/etc/php/8.2/fpm/php.ini`

#### Cache Configuration
```bash
# Cache config and routes
cd /var/www/legalwar
sudo -u legalwar php artisan config:cache
sudo -u legalwar php artisan route:cache
sudo -u legalwar php artisan view:cache
```

#### Redis Optimization
Monitor Redis memory usage:
```bash
redis-cli INFO memory
```

### Web Server Performance

#### Nginx Tuning
Edit `/etc/nginx/nginx.conf`:
```nginx
worker_processes auto;
worker_connections 2048;

http {
    gzip on;
    gzip_comp_level 5;
    gzip_min_length 256;
    gzip_types text/plain text/css application/json application/javascript;

    client_max_body_size 50M;
    client_body_buffer_size 128k;

    keepalive_timeout 65;
    keepalive_requests 100;
}
```

---

## 7. Scaling Guide

### Vertical Scaling (Upgrade Server)

#### Current Resources
- CPU: 4 cores
- RAM: 16GB
- Storage: 200GB SSD

#### Recommended Upgrades

**Phase 1** (Medium Load):
- CPU: 8 cores
- RAM: 32GB
- Storage: 500GB SSD

**Phase 2** (High Load):
- CPU: 16 cores
- RAM: 64GB
- Storage: 1TB SSD

### Horizontal Scaling

#### Load Balancer Setup
Use Nginx as load balancer for multiple application servers.

#### Database Read Replicas
Set up PostgreSQL streaming replication for read-heavy workloads.

#### Queue Worker Scaling
Increase `numprocs` in supervisor config:
```ini
numprocs=16  # Scale up to 16 workers
```

### Caching Strategy

#### Redis Cluster
For high-traffic scenarios, implement Redis cluster.

#### CDN Integration
Use CDN for static assets and file downloads.

---

## 8. Monitoring and Alerts

### Server Monitoring

#### Install Monitoring Tools
```bash
apt install -y netdata
systemctl enable netdata
systemctl start netdata
# Access at http://YOUR_IP:19999
```

### Application Monitoring

#### Laravel Telescope (Development Only)
Already installed for debugging.

#### Custom Health Check Endpoint
```bash
curl https://yourdomain.com/health
```

### Alert Configuration

#### Disk Space Alert
```bash
# Add to cron
0 * * * * /usr/local/bin/check-disk-space.sh
```

**Script**: `/usr/local/bin/check-disk-space.sh`
```bash
#!/bin/bash
THRESHOLD=80
USAGE=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')

if [ "$USAGE" -gt "$THRESHOLD" ]; then
    echo "Disk space alert: ${USAGE}% used" | mail -s "Disk Space Alert" admin@yourdomain.com
fi
```

#### Service Down Alert
Monitor critical services and send alerts if down.

---

## Emergency Procedures

### Application Down
1. Check all services: `systemctl status postgresql redis-server neo4j nginx php8.2-fpm`
2. Check logs: `tail -f /var/www/legalwar/storage/logs/laravel.log`
3. Restart services if needed
4. Check health endpoint: `curl https://yourdomain.com/health`

### Database Corruption
1. Stop application
2. Restore from latest backup
3. Verify data integrity
4. Restart application

### Disk Full
1. Check disk usage: `df -h`
2. Clear old logs: `find /var/log -name "*.gz" -mtime +30 -delete`
3. Clear old backups if needed
4. Consider scaling storage

---

## Contact Information

**System Administrator**: admin@yourdomain.com
**Development Team**: dev@yourdomain.com
**Emergency Hotline**: +XXX-XXX-XXXX

```

#### 2. Checklist

- [ ] Document daily operations checklist
- [ ] Write database maintenance procedures
- [ ] Document queue worker management
- [ ] Write log review procedures
- [ ] Document backup and restore procedures
- [ ] Create performance tuning guide
- [ ] Write scaling strategies
- [ ] Add monitoring and alerting setup
- [ ] Include emergency procedures
- [ ] Add contact information

#### 3. Acceptance Criteria

- Complete daily, weekly, and monthly checklists
- All maintenance procedures documented with commands
- Backup and restore procedures tested
- Performance tuning recommendations validated
- Scaling guide reviewed by infrastructure team

---

## Task 12.4: Troubleshooting Guide

**File**: `docs/TROUBLESHOOTING.md`
**Priority**: High
**Estimated Time**: 3-4 hours
**Dependencies**: Tasks 12.1, 12.3

### Objective

Create a comprehensive troubleshooting guide covering common issues, symptoms, diagnosis steps, and solutions for production environment problems.

### Implementation Details

#### 1. Troubleshooting Guide Content

**File**: `docs/TROUBLESHOOTING.md`

```markdown
# Troubleshooting Guide

This guide provides solutions to common issues encountered in production.

## Table of Contents

1. Queue Workers Not Processing
2. Database Connection Timeout
3. OpenAI Rate Limit Exceeded
4. Neo4j Out of Memory
5. Slow Queries Detected
6. High CPU Usage
7. Disk Space Full
8. Application Not Responding
9. SSL Certificate Issues
10. Email Delivery Failures

---

## 1. Queue Workers Not Processing

### Symptoms
- Jobs stuck in queue
- Workers showing as FATAL in supervisor
- No activity in worker logs

### Diagnosis
```bash
# Check worker status
supervisorctl status legalwar-worker:*

# Check worker logs
tail -f /var/www/legalwar/storage/logs/worker.log

# Check failed jobs
cd /var/www/legalwar
sudo -u legalwar php artisan queue:failed
```

### Solutions

#### Solution 1: Restart Workers
```bash
supervisorctl restart legalwar-worker:*
```

#### Solution 2: Clear and Restart
```bash
# Clear failed jobs
sudo -u legalwar php artisan queue:flush

# Restart workers
supervisorctl restart legalwar-worker:*
```

#### Solution 3: Check Redis Connection
```bash
# Test Redis
redis-cli ping

# If Redis is down
systemctl restart redis-server
```

#### Solution 4: Increase Worker Timeout
Edit `/etc/supervisor/conf.d/legalwar-worker.conf`:
```ini
stopwaitsecs=3600  # Increase timeout
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl restart legalwar-worker:*
```

### Prevention
- Monitor queue length regularly
- Set up alerts for failed jobs
- Implement job timeout limits
- Use queue priorities for critical jobs

---

## 2. Database Connection Timeout

### Symptoms
- "Connection timed out" errors in logs
- Application slow or unresponsive
- High number of database connections

### Diagnosis
```bash
# Check active connections
sudo -u postgres psql -c "
SELECT
    count(*),
    state,
    wait_event_type
FROM pg_stat_activity
GROUP BY state, wait_event_type;
"

# Check connection pool
sudo -u postgres psql -c "
SELECT
    count(*) as connections,
    max_conn,
    max_conn - count(*) as available
FROM pg_stat_activity,
     (SELECT setting::int AS max_conn FROM pg_settings WHERE name='max_connections') mc
GROUP BY max_conn;
"

# Check slow queries
sudo -u postgres psql -c "
SELECT
    pid,
    now() - pg_stat_activity.query_start AS duration,
    query,
    state
FROM pg_stat_activity
WHERE state != 'idle'
AND now() - pg_stat_activity.query_start > interval '1 minute';
"
```

### Solutions

#### Solution 1: Kill Idle Connections
```bash
sudo -u postgres psql -c "
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE state = 'idle'
AND now() - state_change > interval '10 minutes';
"
```

#### Solution 2: Increase Max Connections
Edit `/etc/postgresql/15/main/postgresql.conf`:
```conf
max_connections = 300  # Increase from 200
```

```bash
systemctl restart postgresql
```

#### Solution 3: Implement Connection Pooling
Install PgBouncer:
```bash
apt install -y pgbouncer
```

Configure `/etc/pgbouncer/pgbouncer.ini`:
```ini
[databases]
legalwar_production = host=127.0.0.1 port=5432 dbname=legalwar_production

[pgbouncer]
listen_port = 6432
listen_addr = 127.0.0.1
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 25
```

Update `.env.production`:
```env
DB_PORT=6432  # Use PgBouncer port
```

#### Solution 4: Optimize Application Code
- Close connections explicitly
- Use connection pooling in application
- Implement query caching
- Add database query timeout

### Prevention
- Monitor database connections
- Set connection timeout limits
- Implement proper connection pooling
- Regular database maintenance

---

## 3. OpenAI Rate Limit Exceeded

### Symptoms
- "Rate limit exceeded" errors in logs
- AI features not responding
- Failed jobs in queue

### Diagnosis
```bash
# Check for rate limit errors
grep "rate limit" /var/www/legalwar/storage/logs/laravel.log

# Check failed jobs
cd /var/www/legalwar
sudo -u legalwar php artisan queue:failed | grep OpenAI
```

### Solutions

#### Solution 1: Implement Rate Limiting
Update OpenAI service to implement exponential backoff:

```php
// app/Services/OpenAIService.php
public function chat($messages, $options = [])
{
    $maxRetries = 5;
    $baseDelay = 1; // seconds

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        try {
            return $this->client->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                ...$options,
            ]);
        } catch (RateLimitException $e) {
            if ($attempt === $maxRetries - 1) {
                throw $e;
            }

            $delay = $baseDelay * pow(2, $attempt);
            sleep($delay);
        }
    }
}
```

#### Solution 2: Reduce Request Frequency
- Implement request queuing with delays
- Batch similar requests
- Cache AI responses
- Use lower-priority queue for non-urgent requests

#### Solution 3: Upgrade OpenAI Plan
- Contact OpenAI to increase rate limits
- Consider GPT-4 tier with higher limits

#### Solution 4: Implement Request Queue
```bash
# Create dedicated queue for AI requests
cd /var/www/legalwar
sudo -u legalwar php artisan queue:work redis:openai --sleep=5 --tries=3
```

### Prevention
- Monitor API usage
- Implement rate limiting at application level
- Cache responses where possible
- Set up alerts for rate limit approaching

---

## 4. Neo4j Out of Memory

### Symptoms
- Neo4j service crashes
- "OutOfMemoryError" in Neo4j logs
- Graph queries timing out

### Diagnosis
```bash
# Check Neo4j status
systemctl status neo4j

# Check Neo4j logs
tail -f /var/log/neo4j/neo4j.log

# Check memory usage
curl -u neo4j:PASSWORD http://localhost:7474/db/data/
```

### Solutions

#### Solution 1: Increase Heap Size
Edit `/etc/neo4j/neo4j.conf`:
```conf
dbms.memory.heap.initial_size=8g
dbms.memory.heap.max_size=8g
dbms.memory.pagecache.size=8g
```

```bash
systemctl restart neo4j
```

#### Solution 2: Clear Query Cache
```cypher
CALL dbms.clearQueryCaches();
```

#### Solution 3: Optimize Queries
```cypher
// Before: Expensive query
MATCH (n)-[r]->(m)
WHERE n.type = 'Case'
RETURN n, r, m

// After: Optimized with index
CREATE INDEX case_type IF NOT EXISTS FOR (n:Case) ON (n.type);
MATCH (n:Case)-[r]->(m)
WHERE n.type = 'Case'
RETURN n, r, m
```

#### Solution 4: Clean Up Old Data
```cypher
// Delete old temporary nodes
MATCH (n:Temporary)
WHERE n.created < datetime() - duration('P30D')
DELETE n
```

#### Solution 5: Implement Pagination
```php
// Break large queries into batches
$skip = 0;
$limit = 1000;

do {
    $results = $this->neo4j->run(
        'MATCH (n:Case) RETURN n SKIP $skip LIMIT $limit',
        ['skip' => $skip, 'limit' => $limit]
    );

    // Process results

    $skip += $limit;
} while (count($results) > 0);
```

### Prevention
- Regular memory monitoring
- Implement query timeouts
- Use appropriate indexes
- Regular database cleanup
- Optimize relationship queries

---

## 5. Slow Queries Detected

### Symptoms
- Application slow to respond
- High database CPU usage
- User complaints about performance

### Diagnosis
```bash
# Enable slow query logging
sudo -u postgres psql legalwar_production -c "
ALTER DATABASE legalwar_production SET log_min_duration_statement = 1000;
"

# Check slow queries
tail -f /var/log/postgresql/postgresql-15-main.log | grep "duration:"

# Analyze query performance
sudo -u postgres psql legalwar_production <<EOF
SELECT
    query,
    calls,
    total_time,
    mean_time,
    max_time
FROM pg_stat_statements
ORDER BY mean_time DESC
LIMIT 20;
EOF
```

### Solutions

#### Solution 1: Add Missing Indexes
```sql
-- Analyze query
EXPLAIN ANALYZE SELECT * FROM cases WHERE status = 'active';

-- Add index
CREATE INDEX idx_cases_status ON cases(status);
```

#### Solution 2: Optimize Query
```sql
-- Before: N+1 query problem
SELECT * FROM cases;
-- Then for each case:
SELECT * FROM documents WHERE case_id = ?;

-- After: Use JOIN
SELECT c.*, d.*
FROM cases c
LEFT JOIN documents d ON d.case_id = c.id;
```

#### Solution 3: Implement Query Caching
```php
// Cache expensive queries
$cases = Cache::remember('active_cases', 3600, function () {
    return Case::where('status', 'active')->get();
});
```

#### Solution 4: Use Database Query Builder Optimization
```php
// Before: Loading all relationships
$cases = Case::with(['documents', 'violations', 'metadata'])->get();

// After: Load only needed relationships
$cases = Case::with(['documents' => function($query) {
    $query->select('id', 'case_id', 'title');
}])->get();
```

### Prevention
- Regular query performance review
- Add appropriate indexes
- Implement query result caching
- Use database query monitoring

---

## 6. High CPU Usage

### Symptoms
- Server slow or unresponsive
- High load average
- CPU at 100%

### Diagnosis
```bash
# Check CPU usage
top -bn1 | head -20

# Check load average
uptime

# Identify CPU-intensive processes
ps aux --sort=-%cpu | head -10

# Check PHP-FPM processes
ps aux | grep php-fpm
```

### Solutions

#### Solution 1: Identify and Optimize Code
```bash
# Enable profiling
cd /var/www/legalwar
sudo -u legalwar composer require --dev laravel/telescope

# Review slow requests in Telescope
```

#### Solution 2: Increase PHP-FPM Workers
Edit `/etc/php/8.2/fpm/pool.d/www.conf`:
```ini
pm.max_children = 100
pm.start_servers = 20
pm.min_spare_servers = 10
pm.max_spare_servers = 30
```

```bash
systemctl restart php8.2-fpm
```

#### Solution 3: Optimize Queue Workers
```bash
# Reduce number of workers if too many
supervisorctl stop legalwar-worker:legalwar-worker_04
supervisorctl stop legalwar-worker:legalwar-worker_05
```

#### Solution 4: Enable OPcache Optimization
Already configured, but verify:
```bash
php -i | grep opcache
```

#### Solution 5: Review Neo4j Queries
```bash
# Check Neo4j CPU usage
systemctl status neo4j

# Review query performance
# Access Neo4j Browser and check running queries
```

### Prevention
- Regular performance monitoring
- Code profiling for optimization
- Proper resource allocation
- Load testing before deployment

---

## 7. Disk Space Full

### Symptoms
- "No space left on device" errors
- Application unable to write logs
- Database operations failing

### Diagnosis
```bash
# Check disk usage
df -h

# Find largest directories
du -h / | sort -rh | head -20

# Find large files
find / -type f -size +100M -exec ls -lh {} \;
```

### Solutions

#### Solution 1: Clear Old Logs
```bash
# Clear old application logs
find /var/www/legalwar/storage/logs -name "*.log" -mtime +30 -delete

# Clear old system logs
journalctl --vacuum-time=7d
```

#### Solution 2: Clear Old Backups
```bash
# Keep only last 30 days of backups
find /var/backups/legalwar -name "*.sql.gz" -mtime +30 -delete
```

#### Solution 3: Clear Package Cache
```bash
apt clean
apt autoclean
apt autoremove
```

#### Solution 4: Resize Disk (if VPS supports)
```bash
# Extend partition (varies by VPS provider)
# Contact VPS support for disk expansion
```

#### Solution 5: Move Data to External Storage
```bash
# Move backups to S3 or external storage
aws s3 sync /var/backups/legalwar s3://your-bucket/backups/
rm -rf /var/backups/legalwar/*
```

### Prevention
- Set up disk space monitoring
- Implement automatic log rotation
- Regular backup cleanup
- Consider larger disk or external storage

---

## 8. Application Not Responding

### Symptoms
- 502 Bad Gateway errors
- Timeout errors
- Blank page

### Diagnosis
```bash
# Check all services
systemctl status nginx
systemctl status php8.2-fpm
systemctl status postgresql
systemctl status redis-server
systemctl status neo4j

# Check application logs
tail -f /var/www/legalwar/storage/logs/laravel.log

# Check Nginx error log
tail -f /var/log/nginx/error.log

# Check PHP-FPM log
tail -f /var/log/php8.2-fpm.log
```

### Solutions

#### Solution 1: Restart Services
```bash
systemctl restart php8.2-fpm
systemctl restart nginx
```

#### Solution 2: Clear Application Cache
```bash
cd /var/www/legalwar
sudo -u legalwar php artisan cache:clear
sudo -u legalwar php artisan config:clear
sudo -u legalwar php artisan route:clear
sudo -u legalwar php artisan view:clear
```

#### Solution 3: Check PHP-FPM Socket
```bash
# Verify socket exists
ls -la /run/php/php8.2-fpm.sock

# Check Nginx configuration
nginx -t
```

#### Solution 4: Increase PHP-FPM Timeout
Edit `/etc/php/8.2/fpm/pool.d/www.conf`:
```ini
request_terminate_timeout = 300
```

Edit `/etc/nginx/sites-available/legalwar`:
```nginx
fastcgi_read_timeout 300;
```

```bash
systemctl restart php8.2-fpm nginx
```

### Prevention
- Regular service health checks
- Implement application monitoring
- Set up auto-restart for crashed services
- Configure proper timeouts

---

## 9. SSL Certificate Issues

### Symptoms
- "Your connection is not private" warning
- SSL certificate expired
- Mixed content warnings

### Diagnosis
```bash
# Check certificate expiry
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com 2>/dev/null | openssl x509 -noout -dates

# Check certbot status
certbot certificates
```

### Solutions

#### Solution 1: Renew Certificate
```bash
certbot renew --nginx
systemctl reload nginx
```

#### Solution 2: Force Renewal
```bash
certbot renew --force-renewal --nginx
```

#### Solution 3: Check Auto-Renewal
```bash
systemctl status certbot.timer
systemctl enable certbot.timer
systemctl start certbot.timer
```

#### Solution 4: Fix Mixed Content
Update `.env.production`:
```env
APP_URL=https://yourdomain.com
ASSET_URL=https://yourdomain.com
```

```bash
cd /var/www/legalwar
sudo -u legalwar php artisan config:cache
```

### Prevention
- Enable certbot auto-renewal timer
- Set up expiry monitoring
- Test renewal process monthly

---

## 10. Email Delivery Failures

### Symptoms
- Emails not being delivered
- Bounce errors in logs
- SMTP connection errors

### Diagnosis
```bash
# Check application logs
grep -i "mail" /var/www/legalwar/storage/logs/laravel.log

# Test SMTP connection
telnet smtp.mailgun.org 587
```

### Solutions

#### Solution 1: Verify SMTP Credentials
Check `.env.production`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

#### Solution 2: Test Email Sending
```bash
cd /var/www/legalwar
sudo -u legalwar php artisan tinker
# In tinker:
Mail::raw('Test email', function($msg) {
    $msg->to('test@example.com')->subject('Test');
});
```

#### Solution 3: Switch to Queue for Emails
```php
// In app code
Mail::to($user)->queue(new DocumentReadyNotification($document));
```

#### Solution 4: Check Mail Queue
```bash
# View failed mail jobs
sudo -u legalwar php artisan queue:failed | grep Mail
```

### Prevention
- Use reliable email service (Mailgun, SendGrid, SES)
- Queue email sending
- Monitor email delivery rates
- Implement retry logic

---

## Emergency Contact

If issues persist after following this guide:

**System Administrator**: admin@yourdomain.com
**Development Team**: dev@yourdomain.com
**Emergency Hotline**: +XXX-XXX-XXXX

---

## Escalation Procedure

1. **Level 1**: Follow troubleshooting guide
2. **Level 2**: Contact system administrator
3. **Level 3**: Contact development team
4. **Level 4**: Emergency hotline (production down)
```

#### 2. Checklist

- [ ] Document queue worker issues and solutions
- [ ] Document database connection problems
- [ ] Document OpenAI rate limiting issues
- [ ] Document Neo4j memory problems
- [ ] Document slow query troubleshooting
- [ ] Document high CPU usage scenarios
- [ ] Document disk space issues
- [ ] Document application unresponsive scenarios
- [ ] Document SSL certificate problems
- [ ] Document email delivery failures
- [ ] Include diagnosis steps for each issue
- [ ] Include multiple solution options
- [ ] Add prevention strategies
- [ ] Include emergency contact information

#### 3. Acceptance Criteria

- All common production issues documented
- Each issue includes symptoms, diagnosis, and solutions
- Solutions tested and verified
- Prevention strategies included
- Reviewed by operations team

---

## Task 12.5: Final Production Testing

**File**: `docs/FINAL_TESTING_CHECKLIST.md`
**Priority**: Critical
**Estimated Time**: 6-8 hours
**Dependencies**: Tasks 12.1, 12.2, 12.3

### Objective

Create and execute comprehensive final production testing including load testing, security audit, backup testing, rollback testing, and performance validation.

### Implementation Details

#### 1. Testing Checklist Document

**File**: `docs/FINAL_TESTING_CHECKLIST.md`

```markdown
# Final Production Testing Checklist

This document outlines all tests that must pass before production go-live.

## Table of Contents

1. Load Testing with Apache Bench
2. Security Audit Checklist
3. Backup Strategy Testing
4. Rollback Procedure Testing
5. Performance Validation
6. Services Health Check
7. End-to-End Functionality Testing

---

## 1. Load Testing with Apache Bench

### Install Apache Bench
```bash
apt install -y apache2-utils
```

### Test Scenarios

#### Scenario 1: Homepage Load Test
```bash
ab -n 1000 -c 100 https://yourdomain.com/
```

**Expected Results**:
- [ ] 99% requests complete successfully
- [ ] Average response time < 200ms
- [ ] No 500 errors
- [ ] Server stable under load

#### Scenario 2: API Endpoint Load Test
```bash
ab -n 500 -c 50 -H "Authorization: Bearer YOUR_TOKEN" https://yourdomain.com/api/cases
```

**Expected Results**:
- [ ] 99% requests complete successfully
- [ ] Average response time < 500ms
- [ ] No timeout errors
- [ ] Database connections stable

#### Scenario 3: Document Upload Load Test
```bash
# Create test file
dd if=/dev/urandom of=test.pdf bs=1M count=5

# Run load test
ab -n 100 -c 10 -p test.pdf -T 'application/pdf' https://yourdomain.com/api/documents
```

**Expected Results**:
- [ ] All uploads succeed
- [ ] Average response time < 2s
- [ ] Disk I/O stable
- [ ] No memory issues

#### Scenario 4: Concurrent User Simulation
```bash
# Simulate 200 concurrent users
ab -n 2000 -c 200 -t 60 https://yourdomain.com/dashboard
```

**Expected Results**:
- [ ] 95% requests complete successfully
- [ ] Average response time < 1s
- [ ] CPU usage < 80%
- [ ] Memory usage < 80%
- [ ] No service crashes

### Load Testing Results

| Test | Requests | Concurrency | Success Rate | Avg Response | Max Response | Pass/Fail |
|------|----------|-------------|--------------|--------------|--------------|-----------|
| Homepage | 1000 | 100 | ___% | ___ms | ___ms | [ ] |
| API Endpoint | 500 | 50 | ___% | ___ms | ___ms | [ ] |
| Document Upload | 100 | 10 | ___% | ___ms | ___ms | [ ] |
| Concurrent Users | 2000 | 200 | ___% | ___ms | ___ms | [ ] |

---

## 2. Security Audit Checklist

### Server Security

- [ ] Firewall enabled and configured (UFW)
  ```bash
  ufw status
  ```

- [ ] Fail2ban active and monitoring SSH
  ```bash
  fail2ban-client status sshd
  ```

- [ ] SSH key-only authentication enabled
  ```bash
  grep "PasswordAuthentication" /etc/ssh/sshd_config
  ```

- [ ] Root login disabled
  ```bash
  grep "PermitRootLogin" /etc/ssh/sshd_config
  ```

- [ ] All system packages updated
  ```bash
  apt update && apt list --upgradable
  ```

### Application Security

- [ ] APP_DEBUG=false in production
  ```bash
  grep "APP_DEBUG" /var/www/legalwar/.env.production
  ```

- [ ] Secure session configuration
  ```bash
  grep "SESSION_" /var/www/legalwar/.env.production
  ```

- [ ] CSRF protection enabled
- [ ] XSS protection headers configured
  ```bash
  curl -I https://yourdomain.com | grep "X-Frame-Options"
  curl -I https://yourdomain.com | grep "X-Content-Type-Options"
  ```

- [ ] SQL injection protection tested
- [ ] File upload validation working
- [ ] Authentication and authorization working

### Database Security

- [ ] Database user has minimum required permissions
  ```bash
  sudo -u postgres psql -c "\du legalwar"
  ```

- [ ] Database accessible only from localhost
  ```bash
  grep "listen_addresses" /etc/postgresql/15/main/postgresql.conf
  ```

- [ ] Strong database password set
- [ ] SSL connections enforced (if external access)

### SSL/TLS Security

- [ ] SSL certificate valid and not expiring soon
  ```bash
  certbot certificates
  ```

- [ ] HTTPS enforced (no HTTP access)
  ```bash
  curl -I http://yourdomain.com
  ```

- [ ] Strong SSL ciphers configured
  ```bash
  nmap --script ssl-enum-ciphers -p 443 yourdomain.com
  ```

- [ ] HSTS header present
  ```bash
  curl -I https://yourdomain.com | grep "Strict-Transport-Security"
  ```

### API Security

- [ ] API authentication required
- [ ] API rate limiting configured
- [ ] API CORS properly configured
- [ ] API input validation working

### Secret Management

- [ ] .env file not in git repository
  ```bash
  git ls-files | grep "\.env$"
  ```

- [ ] .env file has restricted permissions
  ```bash
  ls -la /var/www/legalwar/.env.production
  ```

- [ ] API keys rotated and secure
- [ ] No hardcoded credentials in code

---

## 3. Backup Strategy Testing

### Database Backup Test

- [ ] Create manual database backup
  ```bash
  sudo -u postgres pg_dump legalwar_production | gzip > /tmp/test_backup.sql.gz
  ```

- [ ] Verify backup file size is reasonable
  ```bash
  ls -lh /tmp/test_backup.sql.gz
  ```

- [ ] Restore backup to test database
  ```bash
  sudo -u postgres createdb legalwar_test
  gunzip -c /tmp/test_backup.sql.gz | sudo -u postgres psql legalwar_test
  ```

- [ ] Verify restored data integrity
  ```bash
  sudo -u postgres psql legalwar_test -c "SELECT count(*) FROM cases;"
  ```

- [ ] Clean up test database
  ```bash
  sudo -u postgres dropdb legalwar_test
  rm /tmp/test_backup.sql.gz
  ```

### Automated Backup Test

- [ ] Verify backup cron job configured
  ```bash
  crontab -l | grep backup
  ```

- [ ] Trigger backup script manually
  ```bash
  /usr/local/bin/backup-database.sh
  ```

- [ ] Verify backup created successfully
  ```bash
  ls -lh /var/backups/legalwar/database/
  ```

- [ ] Check backup retention policy
  ```bash
  find /var/backups/legalwar/database/ -name "*.sql.gz" -mtime +30
  ```

### Storage Backup Test

- [ ] Backup storage directory
  ```bash
  tar -czf /tmp/storage_test.tar.gz /var/www/legalwar/storage
  ```

- [ ] Restore to test location
  ```bash
  mkdir /tmp/storage_test
  tar -xzf /tmp/storage_test.tar.gz -C /tmp/storage_test
  ```

- [ ] Verify file integrity
  ```bash
  diff -r /var/www/legalwar/storage /tmp/storage_test/var/www/legalwar/storage
  ```

- [ ] Clean up
  ```bash
  rm -rf /tmp/storage_test*
  ```

---

## 4. Rollback Procedure Testing

### Prepare Test Environment

- [ ] Note current commit hash
  ```bash
  cd /var/www/legalwar
  git rev-parse HEAD > /tmp/current_commit.txt
  ```

- [ ] Backup current database state
  ```bash
  sudo -u postgres pg_dump legalwar_production > /tmp/rollback_test_backup.sql
  ```

### Test Rollback

- [ ] Trigger rollback procedure
  ```bash
  # Simulate deployment failure
  cd /var/www/legalwar
  sudo -u legalwar php artisan down
  ```

- [ ] Restore previous code version
  ```bash
  git reset --hard HEAD~1
  sudo -u legalwar composer install --no-dev --optimize-autoloader
  ```

- [ ] Restore database
  ```bash
  sudo -u postgres psql legalwar_production < /tmp/rollback_test_backup.sql
  ```

- [ ] Clear caches
  ```bash
  sudo -u legalwar php artisan config:clear
  sudo -u legalwar php artisan route:clear
  sudo -u legalwar php artisan view:clear
  ```

- [ ] Restart services
  ```bash
  systemctl restart php8.2-fpm
  supervisorctl restart legalwar-worker:*
  systemctl reload nginx
  ```

- [ ] Verify application working
  ```bash
  curl -I https://yourdomain.com
  sudo -u legalwar php artisan up
  ```

### Restore to Current State

- [ ] Return to current commit
  ```bash
  CURRENT_COMMIT=$(cat /tmp/current_commit.txt)
  cd /var/www/legalwar
  git reset --hard $CURRENT_COMMIT
  ```

- [ ] Restore database
  ```bash
  sudo -u postgres psql legalwar_production < /tmp/rollback_test_backup.sql
  ```

- [ ] Clean up
  ```bash
  rm /tmp/current_commit.txt
  rm /tmp/rollback_test_backup.sql
  ```

---

## 5. Performance Validation

### Database Performance

- [ ] Check query performance
  ```bash
  sudo -u postgres psql legalwar_production -c "
  SELECT query, calls, total_time, mean_time
  FROM pg_stat_statements
  ORDER BY mean_time DESC
  LIMIT 10;
  "
  ```

- [ ] Verify all indexes exist
  ```bash
  cd /var/www/legalwar
  sudo -u legalwar php artisan db:check-indexes
  ```

- [ ] Check table bloat
  ```bash
  sudo -u postgres psql legalwar_production -c "
  SELECT schemaname, tablename, pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename))
  FROM pg_tables
  WHERE schemaname = 'public'
  ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
  LIMIT 10;
  "
  ```

### Application Performance

- [ ] Verify OPcache enabled and working
  ```bash
  php -i | grep opcache.enable
  ```

- [ ] Check cache hit ratio
  ```bash
  redis-cli INFO stats | grep keyspace
  ```

- [ ] Verify config/routes/views cached
  ```bash
  ls -la /var/www/legalwar/bootstrap/cache/
  ```

### Response Time Benchmarks

- [ ] Homepage loads in < 500ms
  ```bash
  curl -o /dev/null -s -w '%{time_total}\n' https://yourdomain.com/
  ```

- [ ] API endpoints respond in < 1s
  ```bash
  curl -o /dev/null -s -w '%{time_total}\n' https://yourdomain.com/api/cases
  ```

- [ ] Dashboard loads in < 1s
  ```bash
  curl -o /dev/null -s -w '%{time_total}\n' https://yourdomain.com/dashboard
  ```

---

## 6. Services Health Check

### PostgreSQL

- [ ] Service running
  ```bash
  systemctl status postgresql
  ```

- [ ] Accepting connections
  ```bash
  sudo -u postgres psql -c "SELECT 1"
  ```

- [ ] Database size reasonable
  ```bash
  sudo -u postgres psql -c "
  SELECT pg_size_pretty(pg_database_size('legalwar_production'))
  "
  ```

### Redis

- [ ] Service running
  ```bash
  systemctl status redis-server
  ```

- [ ] Responding to commands
  ```bash
  redis-cli ping
  ```

- [ ] Memory usage acceptable
  ```bash
  redis-cli INFO memory | grep used_memory_human
  ```

### Neo4j

- [ ] Service running
  ```bash
  systemctl status neo4j
  ```

- [ ] Web interface accessible
  ```bash
  curl -s http://localhost:7474 | grep neo4j
  ```

- [ ] Bolt connection working
  ```bash
  cd /var/www/legalwar
  sudo -u legalwar php artisan tinker --execute="Neo4j::run('RETURN 1')"
  ```

### Nginx

- [ ] Service running
  ```bash
  systemctl status nginx
  ```

- [ ] Configuration valid
  ```bash
  nginx -t
  ```

- [ ] Serving requests
  ```bash
  curl -I https://yourdomain.com
  ```

### PHP-FPM

- [ ] Service running
  ```bash
  systemctl status php8.2-fpm
  ```

- [ ] Socket accessible
  ```bash
  ls -la /run/php/php8.2-fpm.sock
  ```

- [ ] Workers healthy
  ```bash
  ps aux | grep php-fpm | wc -l
  ```

### Queue Workers

- [ ] All workers running
  ```bash
  supervisorctl status legalwar-worker:*
  ```

- [ ] Processing jobs
  ```bash
  tail -n 50 /var/www/legalwar/storage/logs/worker.log
  ```

- [ ] No excessive failed jobs
  ```bash
  cd /var/www/legalwar
  sudo -u legalwar php artisan queue:failed | wc -l
  ```

---

## 7. End-to-End Functionality Testing

### User Management

- [ ] User can register
- [ ] User can login
- [ ] User can logout
- [ ] Password reset works
- [ ] Email verification works

### Case Management

- [ ] Create new case
- [ ] View case details
- [ ] Update case information
- [ ] Delete case
- [ ] Search cases
- [ ] Filter cases

### Document Management

- [ ] Upload document
- [ ] View document
- [ ] Download document
- [ ] Delete document
- [ ] OCR processing works

### AI Analysis

- [ ] Legal analysis generates successfully
- [ ] Timeline analysis works
- [ ] Pattern detection functions
- [ ] Graph visualization loads

### Prosecution Oversight

- [ ] Violation detection works
- [ ] Topic framework analyzes cases
- [ ] Risk assessment generates
- [ ] Recommendations display

### Reporting

- [ ] Generate case report
- [ ] Export to PDF
- [ ] Export to Word
- [ ] Email report delivery

---

## Final Sign-Off

### Testing Completion

- [ ] All load tests passed
- [ ] Security audit completed
- [ ] Backup/restore tested successfully
- [ ] Rollback procedure verified
- [ ] Performance benchmarks met
- [ ] All services healthy
- [ ] End-to-end functionality confirmed

### Team Sign-Off

- [ ] Development Team Lead: _________________ Date: _______
- [ ] QA Lead: _________________ Date: _______
- [ ] System Administrator: _________________ Date: _______
- [ ] Project Manager: _________________ Date: _______

### Go-Live Authorization

- [ ] All tests passed: YES / NO
- [ ] Issues identified and resolved: YES / NO / N/A
- [ ] Production deployment authorized: YES / NO

**Authorized By**: _________________ Date: _______

```

#### 2. Checklist

- [ ] Create load testing scenarios
- [ ] Execute Apache Bench tests
- [ ] Complete security audit
- [ ] Test database backup and restore
- [ ] Test rollback procedure
- [ ] Validate performance benchmarks
- [ ] Verify all services health
- [ ] Execute end-to-end functionality tests
- [ ] Document all test results
- [ ] Obtain team sign-off

#### 3. Acceptance Criteria

- All load tests pass with acceptable performance
- Security audit reveals no critical issues
- Backup and restore procedures verified
- Rollback procedure tested successfully
- All services running and healthy
- End-to-end functionality confirmed
- Team sign-off obtained

---

## Task 12.6: Go-Live Checklist

**File**: `docs/GO_LIVE_CHECKLIST.md`
**Priority**: Critical
**Estimated Time**: 8-12 hours
**Dependencies**: All previous tasks

### Objective

Create and execute comprehensive go-live checklist ensuring all infrastructure, services, configuration, and documentation are complete before production deployment.

### Implementation Details

#### 1. Go-Live Checklist Document

**File**: `docs/GO_LIVE_CHECKLIST.md`

```markdown
# Go-Live Checklist

This is the final checklist before production deployment. All items must be completed and verified.

## Pre-Deployment Phase

### Infrastructure Setup

- [ ] VPS provisioned with correct specifications
  - CPU: ___ cores
  - RAM: ___ GB
  - Storage: ___ GB SSD
  - Provider: ___________

- [ ] Server OS installed (Ubuntu 22.04 LTS)
  ```bash
  lsb_release -a
  ```

- [ ] Server accessible via SSH
  ```bash
  ssh root@YOUR_VPS_IP
  ```

- [ ] Firewall configured
  ```bash
  ufw status
  ```

- [ ] System packages updated
  ```bash
  apt update && apt list --upgradable
  ```

### DNS Configuration

- [ ] Domain purchased and registered
  - Domain: ___________
  - Registrar: ___________

- [ ] A record points to VPS IP
  ```bash
  dig yourdomain.com
  ```

- [ ] WWW CNAME configured
  ```bash
  dig www.yourdomain.com
  ```

- [ ] DNS propagation completed
  ```bash
  nslookup yourdomain.com 8.8.8.8
  ```

### Service Installation

- [ ] PostgreSQL 15 installed and running
  ```bash
  systemctl status postgresql
  psql --version
  ```

- [ ] Redis installed and running
  ```bash
  systemctl status redis-server
  redis-cli --version
  ```

- [ ] Neo4j 5.13 installed and running
  ```bash
  systemctl status neo4j
  neo4j version
  ```

- [ ] PHP 8.2 installed
  ```bash
  php -v
  ```

- [ ] PHP-FPM running
  ```bash
  systemctl status php8.2-fpm
  ```

- [ ] Nginx installed and running
  ```bash
  systemctl status nginx
  nginx -v
  ```

- [ ] Supervisor installed and running
  ```bash
  systemctl status supervisor
  supervisorctl version
  ```

- [ ] Certbot installed
  ```bash
  certbot --version
  ```

### Database Setup

- [ ] PostgreSQL database created
  ```bash
  sudo -u postgres psql -c "\l" | grep legalwar_production
  ```

- [ ] Database user created with correct permissions
  ```bash
  sudo -u postgres psql -c "\du legalwar"
  ```

- [ ] PostgreSQL extensions installed
  ```bash
  sudo -u postgres psql legalwar_production -c "\dx"
  ```

- [ ] PostgreSQL optimized for production
  ```bash
  grep "shared_buffers\|max_connections" /etc/postgresql/15/main/postgresql.conf
  ```

- [ ] Neo4j database initialized
  ```bash
  curl -u neo4j:PASSWORD http://localhost:7474/db/data/
  ```

- [ ] Neo4j optimized for production
  ```bash
  grep "dbms.memory" /etc/neo4j/neo4j.conf
  ```

### Application Deployment

- [ ] Application user created
  ```bash
  id legalwar
  ```

- [ ] Repository cloned to /var/www/legalwar
  ```bash
  ls -la /var/www/legalwar
  ```

- [ ] Composer dependencies installed
  ```bash
  cd /var/www/legalwar && composer show
  ```

- [ ] .env.production file created
  ```bash
  ls -la /var/www/legalwar/.env.production
  ```

- [ ] Application key generated
  ```bash
  grep "APP_KEY" /var/www/legalwar/.env.production
  ```

- [ ] File permissions set correctly
  ```bash
  ls -la /var/www/legalwar/storage
  ```

### SSL Certificate

- [ ] SSL certificate obtained
  ```bash
  certbot certificates
  ```

- [ ] Certificate auto-renewal enabled
  ```bash
  systemctl status certbot.timer
  ```

- [ ] HTTPS working
  ```bash
  curl -I https://yourdomain.com
  ```

- [ ] HTTP redirects to HTTPS
  ```bash
  curl -I http://yourdomain.com
  ```

---

## Configuration Phase

### Environment Configuration

- [ ] APP_ENV set to production
- [ ] APP_DEBUG set to false
- [ ] APP_URL set to correct domain
- [ ] Database credentials configured
- [ ] Redis credentials configured
- [ ] Neo4j credentials configured
- [ ] OpenAI API key configured
- [ ] Mail service configured
- [ ] Queue connection set to redis
- [ ] Session driver configured
- [ ] Cache driver configured

**Verify Configuration**:
```bash
cd /var/www/legalwar
sudo -u legalwar php artisan config:show
```

### Nginx Configuration

- [ ] Virtual host configured
  ```bash
  cat /etc/nginx/sites-available/legalwar
  ```

- [ ] Site enabled
  ```bash
  ls -la /etc/nginx/sites-enabled/legalwar
  ```

- [ ] Nginx configuration valid
  ```bash
  nginx -t
  ```

- [ ] SSL configured properly
  ```bash
  grep "ssl_certificate" /etc/nginx/sites-available/legalwar
  ```

### PHP Configuration

- [ ] PHP memory limit appropriate (512M)
  ```bash
  grep "memory_limit" /etc/php/8.2/fpm/php.ini
  ```

- [ ] PHP max execution time set (300)
  ```bash
  grep "max_execution_time" /etc/php/8.2/fpm/php.ini
  ```

- [ ] OPcache enabled
  ```bash
  php -i | grep opcache.enable
  ```

- [ ] PHP-FPM pool configured
  ```bash
  cat /etc/php/8.2/fpm/pool.d/www.conf
  ```

### Queue Workers Configuration

- [ ] Supervisor config created
  ```bash
  cat /etc/supervisor/conf.d/legalwar-worker.conf
  ```

- [ ] Workers started
  ```bash
  supervisorctl status legalwar-worker:*
  ```

- [ ] Worker logs accessible
  ```bash
  tail -f /var/www/legalwar/storage/logs/worker.log
  ```

---

## Deployment Phase

### Database Migration

- [ ] Backup database before migration
  ```bash
  sudo -u postgres pg_dump legalwar_production > /var/backups/pre-migration.sql
  ```

- [ ] Run migrations
  ```bash
  cd /var/www/legalwar
  sudo -u legalwar php artisan migrate --env=production --force
  ```

- [ ] Verify migration success
  ```bash
  sudo -u legalwar php artisan migrate:status
  ```

### Database Seeding

- [ ] Run production seeders
  ```bash
  sudo -u legalwar php artisan db:seed --env=production --force
  ```

- [ ] Verify seed data
  ```bash
  sudo -u legalwar php artisan tinker --execute="User::count()"
  ```

### Cache Optimization

- [ ] Config cached
  ```bash
  sudo -u legalwar php artisan config:cache
  ```

- [ ] Routes cached
  ```bash
  sudo -u legalwar php artisan route:cache
  ```

- [ ] Views cached
  ```bash
  sudo -u legalwar php artisan view:cache
  ```

- [ ] Verify cache files created
  ```bash
  ls -la /var/www/legalwar/bootstrap/cache/
  ```

### Service Restart

- [ ] PHP-FPM restarted
  ```bash
  systemctl restart php8.2-fpm
  ```

- [ ] Queue workers restarted
  ```bash
  supervisorctl restart legalwar-worker:*
  ```

- [ ] Nginx reloaded
  ```bash
  systemctl reload nginx
  ```

---

## Verification Phase

### Application Health

- [ ] Application accessible
  ```bash
  curl -I https://yourdomain.com
  ```

- [ ] Homepage loads correctly
- [ ] Login page accessible
- [ ] Dashboard accessible
- [ ] API endpoints responding
- [ ] No errors in logs
  ```bash
  tail -n 100 /var/www/legalwar/storage/logs/laravel.log
  ```

### Service Health

- [ ] PostgreSQL healthy
  ```bash
  systemctl status postgresql
  sudo -u postgres psql -c "SELECT 1"
  ```

- [ ] Redis healthy
  ```bash
  systemctl status redis-server
  redis-cli ping
  ```

- [ ] Neo4j healthy
  ```bash
  systemctl status neo4j
  curl http://localhost:7474
  ```

- [ ] Nginx healthy
  ```bash
  systemctl status nginx
  ```

- [ ] PHP-FPM healthy
  ```bash
  systemctl status php8.2-fpm
  ```

- [ ] Queue workers healthy
  ```bash
  supervisorctl status
  ```

### Functionality Testing

- [ ] User registration works
- [ ] User login works
- [ ] Case creation works
- [ ] Document upload works
- [ ] AI analysis works
- [ ] Reporting works
- [ ] Email delivery works

### Performance Testing

- [ ] Load test passed (see FINAL_TESTING_CHECKLIST.md)
- [ ] Response times acceptable
- [ ] Resource usage normal
  ```bash
  htop
  df -h
  ```

---

## Monitoring Phase

### Backup Configuration

- [ ] Database backup script deployed
  ```bash
  cat /usr/local/bin/backup-database.sh
  ```

- [ ] Backup cron job scheduled
  ```bash
  crontab -l | grep backup
  ```

- [ ] Backup directory created
  ```bash
  ls -la /var/backups/legalwar/
  ```

- [ ] Test backup executed successfully
  ```bash
  /usr/local/bin/backup-database.sh
  ls -la /var/backups/legalwar/database/
  ```

### Monitoring Setup

- [ ] Disk space monitoring configured
- [ ] Service health monitoring configured
- [ ] Log rotation configured
  ```bash
  cat /etc/logrotate.d/legalwar
  ```

- [ ] Failed job alerts configured

### Security Hardening

- [ ] Firewall rules configured
  ```bash
  ufw status numbered
  ```

- [ ] Fail2ban configured
  ```bash
  fail2ban-client status
  ```

- [ ] SSH key-only authentication
  ```bash
  grep "PasswordAuthentication no" /etc/ssh/sshd_config
  ```

- [ ] Root login disabled
  ```bash
  grep "PermitRootLogin no" /etc/ssh/sshd_config
  ```

- [ ] .env file permissions correct
  ```bash
  ls -la /var/www/legalwar/.env.production
  ```

---

## Documentation Phase

### Documentation Complete

- [ ] Deployment Runbook finalized
- [ ] Operations Manual finalized
- [ ] Troubleshooting Guide finalized
- [ ] Final Testing Checklist completed
- [ ] This Go-Live Checklist completed

### Knowledge Transfer

- [ ] Operations team trained on deployment
- [ ] Operations team trained on maintenance
- [ ] Operations team trained on troubleshooting
- [ ] Emergency procedures documented
- [ ] Contact information updated

---

## Stakeholder Notification

### Internal Notification

- [ ] Development team notified
  - Email sent: [ ]
  - Date: _______

- [ ] Operations team notified
  - Email sent: [ ]
  - Date: _______

- [ ] Management notified
  - Email sent: [ ]
  - Date: _______

### External Notification

- [ ] Legal team notified (if applicable)
- [ ] End users notified (if applicable)
- [ ] Partners notified (if applicable)

---

## Final Checks

### Pre-Go-Live Final Review

- [ ] All checklist items completed
- [ ] No critical issues outstanding
- [ ] Rollback plan ready
- [ ] Support team on standby
- [ ] Monitoring dashboard active

### Go-Live Decision

**Go-Live Date**: _______
**Go-Live Time**: _______
**Timezone**: Europe/Zagreb

**Sign-Off**:
- [ ] Development Lead: _________________ Date: _______
- [ ] Operations Lead: _________________ Date: _______
- [ ] QA Lead: _________________ Date: _______
- [ ] Project Manager: _________________ Date: _______
- [ ] Executive Sponsor: _________________ Date: _______

---

## Go-Live Execution

### During Go-Live

- [ ] Enable maintenance mode (if applicable)
  ```bash
  cd /var/www/legalwar
  sudo -u legalwar php artisan down
  ```

- [ ] Final deployment script execution
  ```bash
  sudo ./scripts/deploy.sh main
  ```

- [ ] Smoke testing
  - [ ] Homepage loads
  - [ ] Login works
  - [ ] Core functionality works

- [ ] Disable maintenance mode
  ```bash
  sudo -u legalwar php artisan up
  ```

- [ ] Monitor logs for errors
  ```bash
  tail -f /var/www/legalwar/storage/logs/laravel.log
  ```

### Post-Go-Live Monitoring (First 24 Hours)

- [ ] Hour 1: Monitor application and services
- [ ] Hour 2: Check error logs
- [ ] Hour 4: Review performance metrics
- [ ] Hour 8: Verify backups running
- [ ] Hour 12: Check queue worker status
- [ ] Hour 24: Full system health check

### Issues Encountered

**Issue 1**: _____________________________________
**Resolution**: _____________________________________
**Time to Resolve**: _____________________________________

**Issue 2**: _____________________________________
**Resolution**: _____________________________________
**Time to Resolve**: _____________________________________

---

## Post-Go-Live

### Success Criteria

- [ ] Application accessible to all users
- [ ] No critical errors in logs
- [ ] All services running normally
- [ ] Performance within acceptable limits
- [ ] Backups running successfully
- [ ] Monitoring functioning

### Lessons Learned

**What went well**:
-
-
-

**What could be improved**:
-
-
-

**Action items for next deployment**:
-
-
-

---

## Production Deployment Complete

**Deployment Status**: SUCCESS / PARTIAL / FAILED

**Final Sign-Off**:

Project Manager: _________________ Date: _______

**Production URL**: https://yourdomain.com

**Deployment Documentation**: /var/www/legalwar/docs/

**Backup Location**: /var/backups/legalwar/

**Support Contact**: admin@yourdomain.com

---

**Congratulations! The AI Legal War Machine is now live in production!**
```

#### 2. Checklist

- [ ] Create comprehensive go-live checklist
- [ ] Include all infrastructure items
- [ ] Include all service installations
- [ ] Include all configurations
- [ ] Include all deployment steps
- [ ] Include all verification steps
- [ ] Include monitoring setup
- [ ] Include documentation requirements
- [ ] Include stakeholder notification
- [ ] Include post-go-live monitoring plan
- [ ] Include success criteria
- [ ] Add sign-off sections

#### 3. Acceptance Criteria

- All checklist items defined and clear
- All sign-off sections included
- Post-go-live monitoring plan defined
- Success criteria clearly stated
- Reviewed and approved by all stakeholders

---

## Sprint 12 Summary

### Deliverables

1. **Deployment Runbook** - Complete VPS setup and deployment guide
2. **Deployment Script** - Automated deployment with rollback
3. **Operations Manual** - Daily operations and maintenance procedures
4. **Troubleshooting Guide** - Common issues and solutions
5. **Final Testing Checklist** - Comprehensive testing procedures
6. **Go-Live Checklist** - Complete production deployment checklist

### Success Metrics

- [ ] All documentation complete and reviewed
- [ ] Deployment script tested successfully
- [ ] Operations manual validated by ops team
- [ ] Troubleshooting guide covers all common issues
- [ ] All final tests passed
- [ ] Production deployment successful

### Risk Mitigation

**Risk**: Incomplete documentation
**Mitigation**: Peer review all documents before go-live

**Risk**: Deployment script fails
**Mitigation**: Test extensively on staging environment

**Risk**: Unexpected production issues
**Mitigation**: Comprehensive troubleshooting guide and rollback plan

**Risk**: Performance issues under load
**Mitigation**: Thorough load testing before go-live

### Next Steps

1. Execute Sprint 12 tasks in order
2. Review and approve all documentation
3. Test deployment script on staging
4. Execute final testing checklist
5. Obtain all required sign-offs
6. Execute go-live checklist
7. **PRODUCTION DEPLOYMENT COMPLETE!**

---

**End of Sprint 12: Documentation & Go-Live**
