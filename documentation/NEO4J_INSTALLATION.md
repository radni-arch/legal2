# Neo4j Installation Guide

This guide provides multiple methods to install Neo4j 5.13 Community Edition for the AI Legal War Machine project.

## Quick Start (Automated Setup - Recommended)

Run the automated setup script (idempotent - safe to run multiple times):

```bash
sudo ./scripts/setup-neo4j.sh
```

This script will:
- Install Neo4j via apt if not already present
- Start the Neo4j service
- Configure the password (default: "password", override with `NEO4J_PASSWORD` env var)
- Handle all dependencies automatically

**Note:** The script is idempotent, meaning it's safe to run multiple times. It will detect existing installations and skip redundant operations.

## Installation Methods

### Method 1: Docker (Manual)

**Prerequisites:**
- Docker installed and running

**Steps:**
1. Install Docker (if not installed):
   ```bash
   sudo apt-get update
   sudo apt-get install -y docker.io
   sudo systemctl start docker
   sudo systemctl enable docker
   ```

2. Run Neo4j container:
   ```bash
   docker run -d --name neo4j \
     -p 7474:7474 -p 7687:7687 \
     -e NEO4J_AUTH=neo4j/pass \
     neo4j:5.13-community
   ```

3. Verify installation:
   ```bash
   docker ps | grep neo4j
   curl http://localhost:7474
   ```

### Method 2: System Installation (Ubuntu/Debian) - Automated

**Prerequisites:**
- Root/sudo access
- Java 17 or 21

**Steps:**
1. Run the automated setup script:
   ```bash
   sudo ./scripts/setup-neo4j.sh
   ```

   This script handles everything: installation, service start, and password configuration.

2. Check status:
   ```bash
   sudo systemctl status neo4j
   ```

### Method 3: User-Space Installation (No Root Required)

**Prerequisites:**
- Java 17 or 21 installed
- Network access to download Neo4j tarball

**Steps:**
1. Download Neo4j:
   ```bash
   cd ~
   wget https://dist.neo4j.org/neo4j-community-5.13.0-unix.tar.gz
   tar -xzf neo4j-community-5.13.0-unix.tar.gz
   ```

2. Configure:
   ```bash
   cd neo4j-community-5.13.0
   ./bin/neo4j-admin dbms set-initial-password pass
   ```

3. Start Neo4j:
   ```bash
   ./bin/neo4j start
   ```

4. Verify:
   ```bash
   ./bin/neo4j status
   curl http://localhost:7474
   ```

## Connection Details

After successful installation:

- **HTTP Interface:** http://localhost:7474
- **Bolt Protocol:** bolt://localhost:7687
- **Username:** neo4j
- **Password:** pass (default, change in production!)

## Laravel Configuration

Update your `.env` file:

```env
NEO4J_ENABLED=true
NEO4J_URI=bolt://localhost:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=pass
```

Test the connection:

```bash
php artisan neo4j:health-check
```

Initialize the graph schema:

```bash
php artisan graph:init
php artisan neo4j:create-indexes
```

## Docker Management Commands

```bash
# View logs
docker logs neo4j -f

# Stop Neo4j
docker stop neo4j

# Start Neo4j
docker start neo4j

# Restart Neo4j
docker restart neo4j

# Access Cypher shell
docker exec -it neo4j cypher-shell -u neo4j -p pass

# Remove container (data persists in volumes)
docker rm -f neo4j

# Remove container and data
docker rm -f neo4j
docker volume rm neo4j-data neo4j-logs
```

## Systemd Management (Method 2)

```bash
# Status
sudo systemctl status neo4j

# Start
sudo systemctl start neo4j

# Stop
sudo systemctl stop neo4j

# Restart
sudo systemctl restart neo4j

# View logs
sudo journalctl -u neo4j -f

# Cypher shell
/opt/neo4j/bin/cypher-shell -u neo4j -p pass
```

## User-Space Management (Method 3)

```bash
# Status
~/neo4j-community-5.13.0/bin/neo4j status

# Start
~/neo4j-community-5.13.0/bin/neo4j start

# Stop
~/neo4j-community-5.13.0/bin/neo4j stop

# Console mode (foreground)
~/neo4j-community-5.13.0/bin/neo4j console

# Cypher shell
~/neo4j-community-5.13.0/bin/cypher-shell -u neo4j -p pass

# View logs
tail -f ~/neo4j-community-5.13.0/logs/neo4j.log
```

## Troubleshooting

### Docker daemon not running

```bash
# For systemd-based systems
sudo systemctl start docker

# For non-systemd systems
sudo dockerd &
```

### Port already in use

Check what's using ports 7474 or 7687:

```bash
sudo lsof -i :7474
sudo lsof -i :7687
```

Kill the process or use different ports in Neo4j configuration.

### Neo4j not accessible

1. Check if Neo4j is running:
   ```bash
   docker ps | grep neo4j
   # or
   sudo systemctl status neo4j
   ```

2. Check logs for errors:
   ```bash
   docker logs neo4j
   # or
   sudo journalctl -u neo4j -n 50
   ```

3. Verify firewall rules allow connections to ports 7474 and 7687

### Java version issues

Neo4j 5.13 requires Java 17 or 21. Check your version:

```bash
java -version
```

Install Java 21 if needed:

```bash
sudo apt-get install -y openjdk-21-jdk
```

### Network/Download issues

If you can't download Neo4j tarballs due to network restrictions:

1. Download manually from https://neo4j.com/deployment-center/
2. Transfer the file to your server
3. Extract and configure manually

### Permission denied errors

When using user-space installation, ensure all directories are writable:

```bash
chmod -R u+w ~/neo4j-community-5.13.0
```

## Production Recommendations

1. **Change default password:**
   ```bash
   # Docker
   docker exec -it neo4j cypher-shell -u neo4j -p pass
   # Then run: ALTER CURRENT USER SET PASSWORD FROM 'pass' TO 'new-secure-password';
   ```

2. **Configure memory limits** based on your server capacity in `neo4j.conf`

3. **Enable SSL/TLS** for production deployments

4. **Set up backups:**
   ```bash
   # Docker
   docker exec neo4j neo4j-admin database dump neo4j --to-path=/var/lib/neo4j/backups
   ```

5. **Monitor performance** using Neo4j Browser metrics or Prometheus integration

## Network Restrictions Workaround

If you encounter "Access denied" or 403 Forbidden errors when trying to install Neo4j:

### Root Cause
Some environments have specific network policies that block:
- Neo4j Docker Hub images
- dist.neo4j.org distribution site
- debian.neo4j.org APT repository

While allowing access to:
- General Docker Hub (prometheus, grafana work fine)
- GitHub
- Ubuntu's official repositories

### Alternative Solutions

**Option 1: Use Neo4j AuraDB (Free Cloud)**
```bash
# Sign up at https://neo4j.com/cloud/aura-free/
# Get connection details and update .env:
NEO4J_ENABLED=true
NEO4J_URI=neo4j+s://xxxxx.databases.neo4j.io
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your_aura_password
```

**Option 2: Docker Image Transfer**

On a machine with unrestricted internet:
```bash
docker pull neo4j:5.13-community
docker save neo4j:5.13-community > neo4j-5.13.tar
# Transfer file to restricted environment
```

On the restricted machine:
```bash
docker load < neo4j-5.13.tar
docker run -d --name neo4j \
  -p 7474:7474 -p 7687:7687 \
  -e NEO4J_AUTH=neo4j/pass \
  neo4j:5.13-community
```

**Option 3: Manual Tarball Transfer**

Download on unrestricted machine:
```bash
wget https://dist.neo4j.org/neo4j-community-5.13.0-unix.tar.gz
```

Transfer and install on restricted machine:
```bash
cd ~
tar -xzf neo4j-community-5.13.0-unix.tar.gz
cd neo4j-community-5.13.0
bin/neo4j-admin dbms set-initial-password pass
bin/neo4j start
```

## Additional Resources

- [Neo4j Documentation](https://neo4j.com/docs/)
- [Neo4j Operations Manual](https://neo4j.com/docs/operations-manual/current/)
- [Cypher Query Language Reference](https://neo4j.com/docs/cypher-manual/current/)
- [Neo4j AuraDB (Free Tier)](https://neo4j.com/cloud/aura-free/)
- Project configuration: `config/neo4j.php`
- Graph schema docs: `docs/GRAPH_SCHEMA.md`
