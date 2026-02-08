# Neo4j Production Setup

## Installation

```bash
# Add Neo4j repository
wget -O - https://debian.neo4j.com/neotechnology.gpg.key | sudo apt-key add -
echo 'deb https://debian.neo4j.com stable latest' | sudo tee /etc/apt/sources.list.d/neo4j.list

# Update and install Neo4j
sudo apt update
sudo apt install -y neo4j

# Check version (should be 5.x)
neo4j version
```

## Configuration

### 1. Backup Original Config

```bash
sudo cp /etc/neo4j/neo4j.conf /etc/neo4j/neo4j.conf.backup
```

### 2. Apply Production Config

```bash
# Copy our optimized config
sudo cp docs/server-config/neo4j.conf /etc/neo4j/neo4j.conf

# Set correct ownership
sudo chown neo4j:neo4j /etc/neo4j/neo4j.conf
sudo chmod 644 /etc/neo4j/neo4j.conf
```

### 3. Set Neo4j Password

```bash
# Set initial password (required before first start)
sudo neo4j-admin set-initial-password your-secure-password

# Or change password after installation
cypher-shell -u neo4j -p neo4j
# Then: ALTER USER neo4j SET PASSWORD 'your-secure-password';

# Save password to .env file
echo "NEO4J_PASSWORD=your-secure-password" >> /var/www/ai-legal-war-machine/.env
```

### 4. Configure JVM Memory (Optional Tuning)

```bash
# Edit JVM settings
sudo nano /etc/neo4j/jvm.conf

# Adjust heap size if needed (already set in neo4j.conf via dbms properties)
# -Xms2g    # Initial heap
# -Xmx4g    # Maximum heap

# Enable G1GC (recommended for production)
-XX:+UseG1GC
-XX:+AlwaysPreTouch
-XX:+UnlockExperimentalVMOptions
```

### 5. Enable and Start Neo4j

```bash
# Enable on boot
sudo systemctl enable neo4j

# Start service
sudo systemctl start neo4j

# Check status
sudo systemctl status neo4j

# View logs
sudo tail -100 /var/log/neo4j/neo4j.log
```

### 6. Verify Installation

```bash
# Check if Neo4j is listening
sudo netstat -tlnp | grep neo4j

# Expected output:
# tcp6       0      0 :::7474                 :::*                    LISTEN      <pid>/java (HTTP)
# tcp6       0      0 :::7687                 :::*                    LISTEN      <pid>/java (Bolt)

# Test connection via cypher-shell
cypher-shell -u neo4j -p your-secure-password

# Run test query
RETURN "Hello Neo4j!" AS greeting;

# Expected output:
# +----------------+
# | greeting       |
# +----------------+
# | "Hello Neo4j!" |
# +----------------+

# Exit
:exit
```

## Laravel Integration

### Update Laravel Configuration

```bash
# .env file should have:
NEO4J_ENABLED=true
NEO4J_CONNECTION=bolt
NEO4J_URI=bolt://localhost:7687
NEO4J_HOST=localhost
NEO4J_PORT=7687
NEO4J_USER=neo4j
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your-secure-password
NEO4J_DATABASE=neo4j
NEO4J_AUTO_SYNC=true
```

### Test Laravel Connection

```bash
php artisan tinker

# Test connection
>>> $client = app('neo4j.client');
>>> $result = $client->run('RETURN "Connected!" AS message');
>>> $result->first()->get('message');
# Expected: "Connected!"
```

## Creating Graph Indexes

See Task 10.6 and `docs/server-config/create-neo4j-indexes.cypher` for index creation.

```bash
# Run index creation script
cypher-shell -u neo4j -p your-password < docs/server-config/create-neo4j-indexes.cypher

# Or manually via cypher-shell
cypher-shell -u neo4j -p your-password

# Create indexes (see Task 10.6 for full list)
CREATE INDEX case_number IF NOT EXISTS FOR (c:Case) ON (c.case_number);
CREATE INDEX law_code IF NOT EXISTS FOR (l:Law) ON (l.code);
```

## Performance Monitoring

### Check Memory Usage

```bash
# Get memory recommendations
neo4j-admin memrec

# Expected output will show recommended settings for your RAM
```

### Monitor Page Cache

```cypher
# Connect to Neo4j
cypher-shell -u neo4j -p your-password

# Check page cache hit ratio (should be > 90%)
CALL dbms.queryJmx('org.neo4j:instance=kernel#0,name=Page cache')
YIELD attributes
RETURN attributes.hitRatio.value AS hitRatio;

# Check page cache size
CALL dbms.queryJmx('org.neo4j:instance=kernel#0,name=Page cache')
YIELD attributes
RETURN
  attributes.`CacheSize`.value AS cacheSize,
  attributes.`MaxCacheSize`.value AS maxCacheSize;
```

### Monitor Queries

```cypher
# List currently running queries
CALL dbms.listQueries();

# Kill slow query (if needed)
CALL dbms.killQuery('query-id-here');

# Show slow queries from log
# tail -f /var/log/neo4j/query.log
```

### Check Database Statistics

```cypher
# Database info
CALL dbms.queryJmx('org.neo4j:instance=kernel#0,name=Store sizes')
YIELD attributes
RETURN attributes;

# Count nodes and relationships
MATCH (n) RETURN count(n) AS nodeCount;
MATCH ()-[r]->() RETURN count(r) AS relationshipCount;

# Count by label
MATCH (n) RETURN labels(n) AS label, count(n) AS count ORDER BY count DESC;

# Count by relationship type
MATCH ()-[r]->() RETURN type(r) AS type, count(r) AS count ORDER BY count DESC;
```

## Maintenance

### Backup Database

```bash
# Stop Neo4j (for offline backup)
sudo systemctl stop neo4j

# Backup database directory
sudo tar -czf /backup/neo4j-$(date +%Y%m%d).tar.gz /var/lib/neo4j/data

# Start Neo4j
sudo systemctl start neo4j

# For online backup (requires Enterprise Edition):
# neo4j-admin backup --database=neo4j --backup-dir=/backup/neo4j
```

### Restore from Backup

```bash
# Stop Neo4j
sudo systemctl stop neo4j

# Remove current data
sudo rm -rf /var/lib/neo4j/data

# Extract backup
sudo tar -xzf /backup/neo4j-20251108.tar.gz -C /

# Set correct ownership
sudo chown -R neo4j:neo4j /var/lib/neo4j/data

# Start Neo4j
sudo systemctl start neo4j
```

### Clear All Data (Dangerous!)

```cypher
# Connect to Neo4j
cypher-shell -u neo4j -p your-password

# Delete all nodes and relationships
MATCH (n) DETACH DELETE n;

# Verify deletion
MATCH (n) RETURN count(n);
# Expected: 0
```

### Compact Database (Reduce Disk Usage)

```bash
# Stop Neo4j
sudo systemctl stop neo4j

# Run database compaction
sudo neo4j-admin database compact neo4j

# Start Neo4j
sudo systemctl start neo4j
```

## Troubleshooting

### Neo4j Won't Start

```bash
# Check logs
sudo tail -100 /var/log/neo4j/neo4j.log

# Check for port conflicts
sudo netstat -tlnp | grep 7474
sudo netstat -tlnp | grep 7687

# Check permissions
ls -la /var/lib/neo4j/data
# Should be owned by neo4j:neo4j

# Fix permissions if needed
sudo chown -R neo4j:neo4j /var/lib/neo4j
sudo chown -R neo4j:neo4j /var/log/neo4j
```

### Out of Memory Errors

```bash
# Check current memory settings
grep -i "heap\|page" /etc/neo4j/neo4j.conf

# Reduce heap or page cache size
sudo nano /etc/neo4j/neo4j.conf

# Change:
# server.memory.heap.max_size=2g  # Reduce from 4g
# server.memory.pagecache.size=6g # Reduce from 8g

# Restart Neo4j
sudo systemctl restart neo4j
```

### Slow Queries

```cypher
# Profile slow query
PROFILE
MATCH (c:Case)-[:RELATES_TO]->(l:Law)
WHERE l.code = 'ZKP'
RETURN c;

# Check for missing indexes
SHOW INDEXES;

# Create missing index
CREATE INDEX IF NOT EXISTS FOR (l:Law) ON (l.code);
```

### Connection Refused

```bash
# Check if Neo4j is running
sudo systemctl status neo4j

# Check if firewall is blocking
sudo ufw status

# Allow Neo4j ports (if needed)
sudo ufw allow 7474/tcp  # HTTP
sudo ufw allow 7687/tcp  # Bolt

# Test local connection
nc -zv localhost 7474
nc -zv localhost 7687
```

### High CPU Usage

```cypher
# List running queries
CALL dbms.listQueries();

# Kill expensive queries
CALL dbms.killQuery('query-id-here');

# Check for full table scans (missing indexes)
PROFILE MATCH (n:Case) WHERE n.case_number = '123' RETURN n;
# Should show "NodeIndexSeek" not "NodeByLabelScan"
```

## Security Best Practices

- ✅ Change default password (`neo4j`)
- ✅ Use strong password (32+ characters)
- ✅ Enable authentication (`dbms.security.auth_enabled=true`)
- ✅ Restrict network access (bind to `127.0.0.1` if only local access needed)
- ✅ Enable HTTPS for web interface (optional)
- ✅ Regular backups
- ✅ Update Neo4j regularly
- ✅ Monitor query logs for suspicious activity

## Performance Checklist

- [x] Memory configured (4GB heap, 8GB page cache)
- [x] Indexes created on frequently queried properties
- [x] Query logging enabled (> 10s threshold)
- [x] Page cache hit ratio > 90%
- [x] Transaction timeout set (5 minutes)
- [x] Checkpoint interval optimized (15 minutes)
- [x] Connection pooling configured
- [x] Metrics enabled

## Acceptance Criteria

Before moving to next task:

- [x] Neo4j installed (version 5.x)
- [x] Production configuration applied
- [x] Password changed from default
- [x] Neo4j starts successfully
- [x] HTTP endpoint accessible (port 7474)
- [x] Bolt endpoint accessible (port 7687)
- [x] Laravel can connect to Neo4j
- [x] Memory settings optimized (4GB heap, 8GB page cache)
- [x] Query logging enabled
- [x] Neo4j auto-starts on boot
- [x] Backup procedure documented

## Quick Reference

```bash
# Start Neo4j
sudo systemctl start neo4j

# Stop Neo4j
sudo systemctl stop neo4j

# Restart Neo4j
sudo systemctl restart neo4j

# Check status
sudo systemctl status neo4j

# View logs
sudo tail -f /var/log/neo4j/neo4j.log

# Connect via cypher-shell
cypher-shell -u neo4j -p your-password

# Get memory recommendations
neo4j-admin memrec

# Check configuration
neo4j-admin check-config

# Backup database
sudo systemctl stop neo4j && sudo tar -czf /backup/neo4j-$(date +%Y%m%d).tar.gz /var/lib/neo4j/data && sudo systemctl start neo4j
```
