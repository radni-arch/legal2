# AI Legal War Machine - Installation Guide

## Quick Start Installation

The system includes a comprehensive installation verification command that checks and installs all required dependencies.

### Basic Installation

```bash
php artisan system:install
```

This command will:
1. Check system requirements (PHP version, memory, disk space)
2. Verify PHP extensions
3. Validate environment configuration
4. Check storage directories
5. Test database connection
6. Verify external services (Redis, Neo4j, OpenAI, AWS)
7. Validate Composer dependencies
8. Check queue configuration

### Command Options

#### Check Only (No Installation)
```bash
php artisan system:install --check-only
```
Only performs checks without attempting any fixes or installations.

#### Automatic Fixes
```bash
php artisan system:install --fix
```
Attempts to automatically fix common issues:
- Creates `.env` from `.env.example` if missing
- Generates `APP_KEY` if not set
- Creates missing storage directories
- Creates storage symlink
- Installs Composer dependencies

#### Skip Migrations
```bash
php artisan system:install --skip-migrations
```
Skips running database migrations during installation.

#### Skip Service Checks
```bash
php artisan system:install --skip-services
```
Skips checking external services (useful for offline installation).

#### Force Installation
```bash
php artisan system:install --force
```
Continues installation even if critical errors are detected.

---

## System Requirements

### Minimum Requirements

- **PHP**: 8.2 or higher
- **Memory**: 512MB+ (recommended: 1GB+)
- **Disk Space**: 10GB+ free space
- **Max Execution Time**: 300 seconds

### Required PHP Extensions

- `pdo`
- `pdo_mysql` or `pdo_pgsql`
- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `curl`
- `fileinfo`
- `gd`
- `zip`

### Optional PHP Extensions

- `redis` - For Redis caching and queues
- `imagick` - Advanced image processing
- `intl` - Internationalization support
- `pcntl` - Process control (required for queue workers)
- `posix` - POSIX functions

---

## External Services

### Required Services

#### Database
- MySQL 8.0+ or PostgreSQL 13+
- Configure in `.env`:
  ```env
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=ai_legal_war_machine
  DB_USERNAME=root
  DB_PASSWORD=
  ```

### Optional Services

#### Redis (Recommended)
- For caching and queue management
- Install: `brew install redis` (macOS) or `apt-get install redis-server` (Ubuntu)
- Configure in `.env`:
  ```env
  REDIS_HOST=127.0.0.1
  REDIS_PASSWORD=null
  REDIS_PORT=6379
  ```

#### Neo4j (For Graph Database Features)
- Version 4.4+ recommended
- Install: [Download from neo4j.com](https://neo4j.com/download/)
- Configure in `config/neo4j.php`

#### AWS Services (For Textract OCR)
- AWS account with Textract access
- Configure in `.env`:
  ```env
  AWS_ACCESS_KEY_ID=your_access_key
  AWS_SECRET_ACCESS_KEY=your_secret_key
  AWS_DEFAULT_REGION=us-east-1
  AWS_BUCKET=your_bucket_name
  ```

#### OpenAI API (For AI Features)
- OpenAI API key
- Configure in `.env`:
  ```env
  OPENAI_API_KEY=sk-...
  ```

#### Google Drive API (For Document Processing)
- Google Cloud project with Drive API enabled
- Configure in `.env`:
  ```env
  GOOGLE_DRIVE_FOLDER_ID=your_folder_id
  ```

---

## Installation Steps

### 1. Clone Repository
```bash
git clone https://github.com/aglavas/ai-legal-war-machine.git
cd ai-legal-war-machine
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure your database and services.

### 4. Run Installation Verification
```bash
php artisan system:install --fix
```

This will:
- Verify all requirements
- Create necessary directories
- Run database migrations
- Check service connectivity

### 5. Create Storage Link
```bash
php artisan storage:link
```

### 6. Run Database Migrations
```bash
php artisan migrate
```

### 7. Seed Initial Data (Optional)
```bash
php artisan db:seed
```

---

## Post-Installation

### Start Queue Workers
```bash
php artisan queue:work --queue=default,textract,high-priority
```

For production, use Supervisor to manage queue workers:
```ini
[program:ai-legal-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasflags=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/queue-worker.log
```

### Import Croatian Laws
```bash
php artisan hrlaws:ingest --since=2020
```

### Discover Court Decisions
```bash
php artisan decisions:discover --topics=5 --per-topic=50
```

---

## Verification Commands

### Check System Status
```bash
php artisan system:install --check-only
```

### Test Database Connection
```bash
php artisan db:show
```

### Test Queue
```bash
php artisan queue:work --once
```

### Clear Caches
```bash
php artisan optimize:clear
```

---

## Troubleshooting

### Database Connection Failed
- Verify database credentials in `.env`
- Ensure database server is running
- Check firewall settings

### Redis Connection Failed
- Verify Redis is running: `redis-cli ping`
- Check Redis configuration in `.env`
- Ensure Redis port (6379) is accessible

### OpenAI API Errors
- Verify API key is correct
- Check API quota and billing
- Test connection: `curl https://api.openai.com/v1/models -H "Authorization: Bearer YOUR_KEY"`

### Permission Errors
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Composer Memory Limit
```bash
COMPOSER_MEMORY_LIMIT=-1 composer install
```

---

## Production Deployment

### Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

### Set Appropriate Permissions
```bash
chmod -R 755 /path/to/project
chmod -R 775 storage bootstrap/cache
```

### Configure Queue Workers (Supervisor)
Create `/etc/supervisor/conf.d/ai-legal-queue.conf`

### Set Up Cron for Scheduled Tasks
```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Docker Installation

A Docker setup is available for quick deployment:

```bash
docker-compose up -d
docker-compose exec app php artisan system:install --fix
```

See `docker-compose.yml` for configuration.

---

## Support

For issues and questions:
- GitHub Issues: https://github.com/aglavas/ai-legal-war-machine/issues
- Documentation: https://docs.ai-legal-war-machine.com

---

## Version Information

- **Current Version**: 1.0.0
- **PHP Version**: 8.2+
- **Laravel Version**: 10.x
- **Last Updated**: 2025-01-29
