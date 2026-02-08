

#!/bin/bash
set -e

# Neo4j Installation Script for AI Legal War Machine
# Installs Neo4j Community Edition 5.x via tarball

NEO4J_VERSION="5.13.0"
NEO4J_HOME="/opt/neo4j"
NEO4J_DATA="/var/lib/neo4j/data"
NEO4J_PASSWORD="pass"

echo "==================================="
echo "Neo4j Installation Script"
echo "==================================="
echo ""
echo "Neo4j Version: ${NEO4J_VERSION}"
echo "Installation Path: ${NEO4J_HOME}"
echo "Data Path: ${NEO4J_DATA}"
echo ""

# Check if Java is installed
if ! command -v java &> /dev/null; then
    echo "Java is not installed. Installing OpenJDK 21..."
    apt update
    apt install -y openjdk-21-jdk
fi

JAVA_VERSION=$(java -version 2>&1 | head -n 1 | awk -F '"' '{print $2}')
echo "✓ Java version: ${JAVA_VERSION}"
echo ""

# Check if Neo4j is already installed
if [ -d "${NEO4J_HOME}" ]; then
    echo "Neo4j is already installed at ${NEO4J_HOME}"
    echo "To reinstall, remove the directory first: rm -rf ${NEO4J_HOME}"
    read -p "Do you want to remove and reinstall? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "Stopping Neo4j if running..."
        systemctl stop neo4j 2>/dev/null || true
        echo "Removing ${NEO4J_HOME}..."
        rm -rf ${NEO4J_HOME}
    else
        echo "Skipping installation. Exiting."
        exit 0
    fi
fi

# Create directories
echo "Creating directories..."
mkdir -p ${NEO4J_HOME}
mkdir -p ${NEO4J_DATA}
mkdir -p /tmp/neo4j-install
cd /tmp/neo4j-install

# Download Neo4j
echo "Downloading Neo4j ${NEO4J_VERSION}..."
NEO4J_TARBALL="neo4j-community-${NEO4J_VERSION}-unix.tar.gz"
NEO4J_URL="https://dist.neo4j.org/neo4j-community-${NEO4J_VERSION}-unix.tar.gz"

# Try to download with retries
MAX_RETRIES=3
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if wget -q --show-progress "${NEO4J_URL}" -O "${NEO4J_TARBALL}"; then
        echo "✓ Download successful"
        break
    else
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
            echo "Download failed. Retrying ($RETRY_COUNT/$MAX_RETRIES)..."
            sleep 2
        else
            echo "❌ Download failed after $MAX_RETRIES attempts"
            echo ""
            echo "Manual installation steps:"
            echo "1. Download Neo4j from: ${NEO4J_URL}"
            echo "2. Place it in /tmp/neo4j-install/"
            echo "3. Run this script again"
            exit 1
        fi
    fi
done

# Extract
echo "Extracting Neo4j..."
tar -xzf "${NEO4J_TARBALL}" -C /tmp/neo4j-install/
mv /tmp/neo4j-install/neo4j-community-${NEO4J_VERSION}/* ${NEO4J_HOME}/

# Configure Neo4j
echo "Configuring Neo4j..."
cat > ${NEO4J_HOME}/conf/neo4j.conf <<EOF
# Network configuration
server.default_listen_address=0.0.0.0
server.bolt.listen_address=:7687
server.http.listen_address=:7474

# Database location
server.directories.data=${NEO4J_DATA}
server.directories.logs=/var/log/neo4j

# Memory settings (adjust based on available RAM)
server.memory.heap.initial_size=512m
server.memory.heap.max_size=1G
server.memory.pagecache.size=512m

# Security
dbms.security.auth_enabled=true

# Performance
dbms.transaction.timeout=60s
dbms.lock.acquisition.timeout=30s
EOF

echo "✓ Configuration written to ${NEO4J_HOME}/conf/neo4j.conf"

# Set initial password
echo "Setting initial password..."
${NEO4J_HOME}/bin/neo4j-admin dbms set-initial-password "${NEO4J_PASSWORD}"
echo "✓ Initial password set to: ${NEO4J_PASSWORD}"

# Enable and start Neo4j
echo "Starting Neo4j..."
service neo4j enable || true
service neo4j start || true

# Wait for Neo4j to start
echo "Waiting for Neo4j to start (30 seconds)..."
sleep 30

# Test connection
echo "Testing connection..."
if ${NEO4J_HOME}/bin/cypher-shell -u neo4j -p "${NEO4J_PASSWORD}" "RETURN 'Connection successful' AS result;" &> /dev/null; then
    echo "✓ Neo4j is running and accessible"
else
    echo "⚠ Neo4j started but connection test failed. Waiting another 10 seconds..."
    sleep 10
    if ${NEO4J_HOME}/bin/cypher-shell -u neo4j -p "${NEO4J_PASSWORD}" "RETURN 'Connection successful' AS result;" &> /dev/null; then
        echo "✓ Neo4j is running and accessible"
    else
        echo "❌ Connection test failed. Check logs: journalctl -u neo4j"
        exit 1
    fi
fi

# Cleanup
echo "Cleaning up..."
rm -rf /tmp/neo4j-install

echo ""
echo "==================================="
echo "✓ Neo4j Installation Complete!"
echo "==================================="
echo ""
echo "Connection Details:"
echo "  Bolt URI: bolt://localhost:7687"
echo "  HTTP URI: http://localhost:7474"
echo "  Username: neo4j"
echo "  Password: ${NEO4J_PASSWORD}"
echo ""
echo "Service Management:"
echo "  Status:  systemctl status neo4j"
echo "  Stop:    systemctl stop neo4j"
echo "  Start:   systemctl start neo4j"
echo "  Restart: systemctl restart neo4j"
echo "  Logs:    journalctl -u neo4j -f"
echo ""
echo "Cypher Shell:"
echo "  ${NEO4J_HOME}/bin/cypher-shell -u neo4j -p ${NEO4J_PASSWORD}"
echo ""
echo "Next Steps:"
echo "  1. Update .env file with:"
echo "     NEO4J_ENABLED=true"
echo "     NEO4J_URI=bolt://localhost:7687"
echo "     NEO4J_PASSWORD=${NEO4J_PASSWORD}"
echo "  2. Initialize graph schema: php artisan graph:init"
echo "  3. Create indexes: php artisan neo4j:create-indexes"
echo "  4. Sync data: php artisan graph:sync --all"
echo ""
