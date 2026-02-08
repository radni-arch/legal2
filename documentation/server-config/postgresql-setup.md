# PostgreSQL Production Setup

## Installation

```bash
# Add PostgreSQL repository
sudo apt install -y postgresql-common
sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh

# Install PostgreSQL 14+
sudo apt update
sudo apt install -y postgresql-14 postgresql-contrib-14

# Install pgvector extension for vector similarity search
sudo apt install -y postgresql-14-pgvector
```

## Configuration

### 1. Backup Original Config

```bash
sudo cp /etc/postgresql/14/main/postgresql.conf /etc/postgresql/14/main/postgresql.conf.backup
```

### 2. Apply Production Config

```bash
# Copy our optimized config
sudo cp docs/server-config/postgresql.conf /etc/postgresql/14/main/postgresql.conf

# Set correct ownership
sudo chown postgres:postgres /etc/postgresql/14/main/postgresql.conf
sudo chmod 644 /etc/postgresql/14/main/postgresql.conf
```

### 3. Validate Configuration

```bash
# Check for syntax errors
sudo -u postgres postgres -C config_file=/etc/postgresql/14/main/postgresql.conf -C shared_buffers

# If command succeeds, configuration is valid
```

### 4. Restart PostgreSQL

```bash
# Restart to apply all changes (required for shared_buffers)
sudo systemctl restart postgresql

# Check status
sudo systemctl status postgresql

# Check logs for any errors
sudo tail -50 /var/log/postgresql/postgresql-14-main.log
```

## Database Setup

### 1. Create Application Database

```bash
# Switch to postgres user
sudo -i -u postgres

# Create database
createdb ai_legal_war_machine_production

# Create user
createuser --pwprompt ai_legal_user

# Exit postgres user shell
exit
```

### 2. Grant Permissions

```bash
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ai_legal_war_machine_production TO ai_legal_user;"
sudo -u postgres psql -c "ALTER DATABASE ai_legal_war_machine_production OWNER TO ai_legal_user;"
```

### 3. Enable Extensions

```bash
# Connect to database
sudo -u postgres psql ai_legal_war_machine_production

# Enable extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "vector";
CREATE EXTENSION IF NOT EXISTS "pg_stat_statements";

# Verify extensions
\dx

# Exit
\q
```

## Performance Monitoring

### Check Query Performance

```sql
-- Top 20 slowest queries
SELECT
    query,
    calls,
    total_exec_time,
    mean_exec_time,
    max_exec_time
FROM pg_stat_statements
ORDER BY total_exec_time DESC
LIMIT 20;
```

### Check Cache Hit Ratio

```sql
-- Should be > 90% for good performance
SELECT
    sum(heap_blks_read) as heap_read,
    sum(heap_blks_hit) as heap_hit,
    sum(heap_blks_hit) / (sum(heap_blks_hit) + sum(heap_blks_read)) as ratio
FROM pg_statio_user_tables;
```

### Check Index Usage

```sql
-- Find unused indexes
SELECT
    schemaname,
    tablename,
    indexname,
    idx_scan
FROM pg_stat_user_indexes
WHERE idx_scan = 0
ORDER BY schemaname, tablename;
```

### Check Table Bloat

```sql
-- Check if autovacuum is working
SELECT
    schemaname,
    tablename,
    last_vacuum,
    last_autovacuum,
    last_analyze,
    last_autoanalyze
FROM pg_stat_user_tables
ORDER BY last_autovacuum DESC NULLS LAST;
```

## Troubleshooting

### PostgreSQL Won't Start

```bash
# Check logs
sudo tail -100 /var/log/postgresql/postgresql-14-main.log

# Check configuration validity
sudo -u postgres postgres -C config_file=/etc/postgresql/14/main/postgresql.conf -C shared_buffers

# Check disk space
df -h /var/lib/postgresql/
```

### Slow Queries

```bash
# Enable slow query logging (already in config)
# Check slow query log
sudo tail -f /var/log/postgresql/postgresql-14-main.log | grep "duration:"
```

### High Memory Usage

```bash
# Check current settings
sudo -u postgres psql -c "SHOW shared_buffers;"
sudo -u postgres psql -c "SHOW effective_cache_size;"
sudo -u postgres psql -c "SHOW work_mem;"

# Monitor memory
sudo -u postgres psql -c "SELECT * FROM pg_stat_activity WHERE state = 'active';"
```

## Maintenance

### Manual Vacuum (if needed)

```bash
# Vacuum all databases
sudo -u postgres vacuumdb --all --analyze --verbose

# Vacuum specific database
sudo -u postgres vacuumdb ai_legal_war_machine_production --analyze --verbose
```

### Reindex (if needed)

```bash
# Reindex all databases
sudo -u postgres reindexdb --all

# Reindex specific database
sudo -u postgres reindexdb ai_legal_war_machine_production
```

### Backup

```bash
# Create backup
sudo -u postgres pg_dump ai_legal_war_machine_production > backup-$(date +%Y%m%d).sql

# Create compressed backup
sudo -u postgres pg_dump ai_legal_war_machine_production | gzip > backup-$(date +%Y%m%d).sql.gz

# Restore from backup
sudo -u postgres psql ai_legal_war_machine_production < backup-20251108.sql
```

## Security

### Configure pg_hba.conf

```bash
# Edit authentication config
sudo nano /etc/postgresql/14/main/pg_hba.conf

# Add application access (replace with your app server IP)
# host    ai_legal_war_machine_production    ai_legal_user    127.0.0.1/32    scram-sha-256

# Reload configuration
sudo systemctl reload postgresql
```

### SSL Configuration (Optional)

```bash
# Generate SSL certificate
sudo openssl req -new -x509 -days 365 -nodes -text \
    -out /etc/postgresql/14/main/server.crt \
    -keyout /etc/postgresql/14/main/server.key

# Set permissions
sudo chown postgres:postgres /etc/postgresql/14/main/server.{crt,key}
sudo chmod 600 /etc/postgresql/14/main/server.key

# Enable SSL in postgresql.conf
# ssl = on
# ssl_cert_file = '/etc/postgresql/14/main/server.crt'
# ssl_key_file = '/etc/postgresql/14/main/server.key'

# Restart PostgreSQL
sudo systemctl restart postgresql
```

## Acceptance Criteria

- [x] PostgreSQL 14+ installed
- [x] pgvector extension installed
- [x] Production config applied
- [x] Configuration validated (no syntax errors)
- [x] PostgreSQL restarted successfully
- [x] Database and user created
- [x] Extensions enabled (uuid-ossp, vector, pg_stat_statements)
- [x] Query performance monitoring working
- [x] Autovacuum enabled and configured
- [x] Slow query logging enabled
- [x] Backup procedure documented
