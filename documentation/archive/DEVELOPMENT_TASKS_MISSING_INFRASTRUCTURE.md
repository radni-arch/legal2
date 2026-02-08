# ARCHIVED: 2025-12-22

**Reason for Archival**: Proposed Neo4j scripts are not needed. The existing `setup-neo4j.sh` script handles all Neo4j installation and configuration, including both local and AuraDB deployment options. The tasks outlined in this document for creating separate Neo4j configuration scripts (configure-auradb.sh, install-neo4j-docker.sh, check-neo4j-requirements.sh, install-aura-cli.sh) are redundant and should not be implemented.

**Date Archived**: 2025-12-22
**Archived By**: Documentation Verifier Agent

---

# Development Tasks: Missing Scripts & Infrastructure

**Generated**: 2025-12-21
**Purpose**: Task specifications for missing scripts and infrastructure referenced in documentation
**Priority**: High (blocks production deployment and documentation accuracy)

---

## Overview

Documentation verification revealed multiple shell scripts, configuration files, and documentation files that are referenced but do not exist in the codebase. This document provides detailed development tasks for creating these missing components.

**Reference Scripts Analyzed**:
- `/home/user/ai-legal-war-machine/scripts/setup-neo4j.sh`
- `/home/user/ai-legal-war-machine/scripts/check-setup.sh`
- `/home/user/ai-legal-war-machine/scripts/ensure-postgres-pgvector.sh`

**Script Patterns Observed**:
1. Bash strict mode: `set -euo pipefail` or `set -u`
2. Standard logging functions: `log()`, `warn()`, `error()`, `success()`
3. Idempotent design - safe to run multiple times
4. Comprehensive error handling with retry logic
5. Root permission checks where needed
6. Self-documenting headers with usage examples
7. Timeout and wait functions for service readiness

---

## Sprint 1: Neo4j Setup Automation (Priority: HIGH)

**Estimated Effort**: 2-3 days
**Blockers**: Production deployment requires these scripts
**Dependencies**: Existing `scripts/setup-neo4j.sh` provides pattern reference

### Epic 1.1: AuraDB Configuration Script

#### Task 1.1.1: Create `scripts/configure-auradb.sh`

**File to Create**: `/home/user/ai-legal-war-machine/scripts/configure-auradb.sh`

**Purpose**: Configure connection to Neo4j AuraDB cloud instance

**Script Requirements**:

```bash
#!/bin/bash
set -euo pipefail

#######################################
# AuraDB Configuration Script
#
# Purpose:
# - Configure application to connect to Neo4j AuraDB
# - Validate AuraDB credentials
# - Test connectivity to cloud instance
# - Update .env with AuraDB connection string
#
# Usage:
#   ./scripts/configure-auradb.sh
#   ./scripts/configure-auradb.sh --help
#   AURADB_URI="neo4j+s://xxx.databases.neo4j.io" ./scripts/configure-auradb.sh
#
# Environment Variables:
#   AURADB_URI - AuraDB connection URI (required)
#   AURADB_USERNAME - AuraDB username (default: neo4j)
#   AURADB_PASSWORD - AuraDB password (required)
#######################################
```

**Implementation Details**:

1. **Credential Validation**:
   - Check for required environment variables: `AURADB_URI`, `AURADB_PASSWORD`
   - Validate URI format: `neo4j+s://` or `neo4j+ssc://`
   - Ensure username defaults to `neo4j` if not provided

2. **Connectivity Test**:
   - Use `cypher-shell` to test connection
   - Command: `cypher-shell -a "$AURADB_URI" -u "$AURADB_USERNAME" -p "$AURADB_PASSWORD" "RETURN 1;"`
   - Timeout after 30 seconds
   - Clear error messages if connection fails

3. **Environment File Update**:
   - Backup existing `.env` to `.env.backup-$(date +%s)`
   - Update or add these variables:
     ```
     NEO4J_HOST=
     NEO4J_PORT=
     NEO4J_USERNAME=$AURADB_USERNAME
     NEO4J_PASSWORD=$AURADB_PASSWORD
     NEO4J_URI=$AURADB_URI
     NEO4J_DATABASE=neo4j
     ```
   - Use `sed` for idempotent updates (replace if exists, append if not)

4. **Verification**:
   - Run Laravel artisan command to test Neo4j connection
   - `php artisan tinker --execute="app('neo4j')->run('RETURN 1');"`
   - Display success message with connection details (hide password)

**Testing Requirements**:
- [ ] Script runs without errors when all variables provided
- [ ] Script fails gracefully with clear error if credentials missing
- [ ] Script validates URI format
- [ ] `.env` file is correctly updated
- [ ] Backup is created before modification
- [ ] `--help` flag displays usage information
- [ ] Idempotent - safe to run multiple times

**Acceptance Criteria**:
- [ ] Executable script at `scripts/configure-auradb.sh`
- [ ] Follows project logging patterns (log/warn/error/success functions)
- [ ] Returns exit code 0 on success, 1 on failure
- [ ] Self-documenting with header comment
- [ ] Tested manually with mock AuraDB credentials

---

#### Task 1.1.2: Create `scripts/install-neo4j-docker.sh`

**File to Create**: `/home/user/ai-legal-war-machine/scripts/install-neo4j-docker.sh`

**Purpose**: Install and configure Neo4j via Docker for local development

**Script Requirements**:

```bash
#!/bin/bash
set -euo pipefail

#######################################
# Neo4j Docker Installation Script
#
# Purpose:
# - Install Neo4j via Docker/Docker Compose
# - Configure persistent volumes
# - Set up authentication
# - Verify connectivity
#
# Usage:
#   ./scripts/install-neo4j-docker.sh
#   ./scripts/install-neo4j-docker.sh --version 5.15.0
#
# Options:
#   --version <version>  Neo4j version to install (default: latest)
#   --port <port>        HTTP port (default: 7474)
#   --bolt-port <port>   Bolt port (default: 7687)
#   --help               Display this help
#######################################
```

**Implementation Details**:

1. **Docker Check**:
   - Verify Docker is installed: `command -v docker`
   - Verify Docker is running: `docker info >/dev/null 2>&1`
   - Check for docker-compose: `command -v docker-compose || command -v docker compose`

2. **Neo4j Version Selection**:
   - Default: `neo4j:latest`
   - Allow version override via `--version` flag
   - Validate version exists: `docker pull neo4j:$VERSION --dry-run`

3. **Volume Setup**:
   - Create persistent volumes for data, logs, and plugins:
     ```bash
     mkdir -p "${PROJECT_DIR}/storage/neo4j/data"
     mkdir -p "${PROJECT_DIR}/storage/neo4j/logs"
     mkdir -p "${PROJECT_DIR}/storage/neo4j/plugins"
     ```

4. **Docker Compose Configuration**:
   - Create or update `docker-compose.neo4j.yml`:
     ```yaml
     version: '3.8'
     services:
       neo4j:
         image: neo4j:${NEO4J_VERSION:-latest}
         container_name: ai-legal-neo4j
         restart: unless-stopped
         ports:
           - "7474:7474"  # HTTP
           - "7687:7687"  # Bolt
         environment:
           - NEO4J_AUTH=neo4j/${NEO4J_PASSWORD:-password}
           - NEO4J_PLUGINS=["apoc"]
           - NEO4J_dbms_security_procedures_unrestricted=apoc.*
         volumes:
           - ./storage/neo4j/data:/data
           - ./storage/neo4j/logs:/logs
           - ./storage/neo4j/plugins:/plugins
         healthcheck:
           test: ["CMD", "cypher-shell", "-u", "neo4j", "-p", "${NEO4J_PASSWORD:-password}", "RETURN 1"]
           interval: 10s
           timeout: 10s
           retries: 5
     ```

5. **Container Startup**:
   - Start container: `docker-compose -f docker-compose.neo4j.yml up -d`
   - Wait for health check to pass (max 120 seconds)
   - Poll: `docker inspect --format='{{.State.Health.Status}}' ai-legal-neo4j`

6. **Password Configuration**:
   - Read `NEO4J_PASSWORD` from environment or `.env`
   - Default to "password" if not set
   - Verify password works after startup

7. **Connectivity Verification**:
   - Test Bolt connection on port 7687
   - Test HTTP interface on port 7474
   - Run sample query: `cypher-shell -a bolt://localhost:7687 -u neo4j -p "$NEO4J_PASSWORD" "RETURN 1;"`

**Testing Requirements**:
- [ ] Docker check fails gracefully if Docker not installed
- [ ] Creates docker-compose.neo4j.yml correctly
- [ ] Starts Neo4j container successfully
- [ ] Container restarts on system reboot (restart policy)
- [ ] Persistent volumes are created and mounted
- [ ] Health check passes within timeout
- [ ] Connectivity test succeeds
- [ ] `--help` displays usage
- [ ] Idempotent - safe to run multiple times

**Acceptance Criteria**:
- [ ] Executable script at `scripts/install-neo4j-docker.sh`
- [ ] Creates `docker-compose.neo4j.yml` in project root
- [ ] Neo4j accessible at `bolt://localhost:7687`
- [ ] Browser interface at `http://localhost:7474`
- [ ] Data persists between container restarts
- [ ] Follows project logging patterns
- [ ] Returns exit code 0 on success

---

#### Task 1.1.3: Create `scripts/check-neo4j-requirements.sh`

**File to Create**: `/home/user/ai-legal-war-machine/scripts/check-neo4j-requirements.sh`

**Purpose**: Verify system meets Neo4j requirements before installation

**Script Requirements**:

```bash
#!/bin/bash
set -euo pipefail

#######################################
# Neo4j Requirements Check
#
# Purpose:
# - Check system prerequisites for Neo4j
# - Verify Java version (17 or 21 required)
# - Check available disk space
# - Verify available memory
# - Check network ports availability
#
# Usage:
#   ./scripts/check-neo4j-requirements.sh
#   ./scripts/check-neo4j-requirements.sh --mode docker
#
# Options:
#   --mode <local|docker>  Check mode (default: local)
#   --help                 Display this help
#######################################
```

**Implementation Details**:

1. **Mode Detection**:
   - `local`: Check for Java, system resources
   - `docker`: Check for Docker, docker-compose

2. **Java Version Check** (local mode):
   - Command: `java -version 2>&1 | head -1`
   - Extract version number
   - Validate: Java 17 or Java 21
   - Error if Java < 17 or Java 18-20

3. **Docker Check** (docker mode):
   - Verify Docker installed and running
   - Check Docker version >= 20.10
   - Check docker-compose available

4. **System Resources**:
   - **Minimum RAM**: 2GB available
     - Command: `free -m | awk '/^Mem:/{print $7}'`
   - **Minimum Disk**: 10GB available in `/var` (for local) or project dir (for docker)
     - Command: `df -BG /var | awk 'NR==2 {print $4}' | sed 's/G//'`

5. **Port Availability**:
   - Check ports 7474 (HTTP) and 7687 (Bolt) are not in use
   - Command: `ss -ltn | grep -E ':(7474|7687) '` or `netstat -ltn`
   - Warn if ports in use (may be existing Neo4j)

6. **User Permissions**:
   - Check if running as root (for local install)
   - Check current user in docker group (for docker install)
   - Command: `groups | grep -q docker`

7. **Output Format**:
   - Green checkmarks for passed requirements
   - Red X for failed requirements
   - Yellow warnings for recommendations
   - Summary at end with pass/fail status

**Testing Requirements**:
- [ ] Correctly detects Java version
- [ ] Correctly detects Docker installation
- [ ] Accurately reports available memory
- [ ] Accurately reports available disk space
- [ ] Detects if ports are in use
- [ ] Provides helpful error messages
- [ ] Exit code 0 if all requirements met
- [ ] Exit code 1 if requirements not met
- [ ] `--help` displays usage

**Acceptance Criteria**:
- [ ] Executable script at `scripts/check-neo4j-requirements.sh`
- [ ] Supports `--mode local` and `--mode docker`
- [ ] Color-coded output (green/red/yellow)
- [ ] Clear summary of pass/fail
- [ ] Follows project logging patterns
- [ ] Non-destructive (read-only checks)

---

#### Task 1.1.4: Create `scripts/install-aura-cli.sh`

**File to Create**: `/home/user/ai-legal-war-machine/scripts/install-aura-cli.sh`

**Purpose**: Install Neo4j Aura CLI tool for cloud database management

**Script Requirements**:

```bash
#!/bin/bash
set -euo pipefail

#######################################
# Neo4j Aura CLI Installation
#
# Purpose:
# - Download and install Neo4j Aura CLI
# - Verify installation
# - Configure authentication
#
# Usage:
#   ./scripts/install-aura-cli.sh
#   ./scripts/install-aura-cli.sh --version 1.2.0
#
# Options:
#   --version <version>  Aura CLI version (default: latest)
#   --help               Display this help
#######################################
```

**Implementation Details**:

1. **Installation Method Detection**:
   - Check for npm: `command -v npm` → use `npm install -g @neo4j/aura-cli`
   - Check for pip: `command -v pip3` → use `pip3 install neo4j-aura`
   - Fallback: Download binary from GitHub releases

2. **Version Selection**:
   - Default: latest release
   - Allow version override via `--version` flag
   - Fetch latest version from GitHub API if "latest":
     ```bash
     LATEST_VERSION=$(curl -s https://api.github.com/repos/neo4j/aura-cli/releases/latest | grep '"tag_name"' | sed -E 's/.*"([^"]+)".*/\1/')
     ```

3. **Binary Installation** (if npm/pip not available):
   - Detect OS and architecture: `uname -s` and `uname -m`
   - Download binary:
     ```bash
     wget https://github.com/neo4j/aura-cli/releases/download/${VERSION}/aura-cli-${OS}-${ARCH}
     ```
   - Install to `/usr/local/bin/aura-cli`
   - Set executable: `chmod +x /usr/local/bin/aura-cli`

4. **Verification**:
   - Test command: `aura-cli --version`
   - Display installed version
   - Test help: `aura-cli --help`

5. **Initial Configuration** (optional):
   - If `AURA_CLIENT_ID` and `AURA_CLIENT_SECRET` in environment
   - Run: `aura-cli auth login --client-id "$AURA_CLIENT_ID" --client-secret "$AURA_CLIENT_SECRET"`

**Testing Requirements**:
- [ ] Installs via npm if available
- [ ] Falls back to binary download if npm/pip unavailable
- [ ] Correctly detects OS and architecture
- [ ] Downloads correct binary for platform
- [ ] Sets correct permissions
- [ ] Verification command succeeds
- [ ] `--help` displays usage
- [ ] Handles network errors gracefully

**Acceptance Criteria**:
- [ ] Executable script at `scripts/install-aura-cli.sh`
- [ ] Aura CLI installed and accessible in PATH
- [ ] `aura-cli --version` works
- [ ] Follows project logging patterns
- [ ] Returns exit code 0 on success
- [ ] Works on Linux (primary) and macOS (secondary)

---

#### Task 1.1.5: Create `scripts/setup-auradb.sh`

**File to Create**: `/home/user/ai-legal-war-machine/scripts/setup-auradb.sh`

**Purpose**: Full automation for AuraDB instance creation and configuration

**Script Requirements**:

```bash
#!/bin/bash
set -euo pipefail

#######################################
# AuraDB Setup Automation
#
# Purpose:
# - Create new AuraDB instance (if needed)
# - Configure instance settings
# - Set up authentication
# - Test connectivity
# - Update application configuration
#
# Usage:
#   ./scripts/setup-auradb.sh --create
#   ./scripts/setup-auradb.sh --existing-uri <uri>
#
# Options:
#   --create              Create new AuraDB instance
#   --existing-uri <uri>  Use existing instance
#   --instance-name <name> Name for new instance
#   --region <region>     Cloud region (default: us-east-1)
#   --tier <tier>         Instance tier (default: free)
#   --help                Display this help
#######################################
```

**Implementation Details**:

1. **Prerequisites Check**:
   - Verify Aura CLI installed: `command -v aura-cli`
   - If not, suggest running `./scripts/install-aura-cli.sh`
   - Verify authentication: `aura-cli auth status`

2. **New Instance Creation** (if `--create` flag):
   - Instance name: `ai-legal-war-machine-${ENVIRONMENT:-dev}`
   - Region: from `--region` or default `us-east-1`
   - Tier: from `--tier` or default `free`
   - Command: `aura-cli create instance --name "$INSTANCE_NAME" --region "$REGION" --tier "$TIER"`
   - Capture instance ID and connection URI from output

3. **Wait for Instance Ready**:
   - Poll instance status: `aura-cli get instance "$INSTANCE_ID"`
   - Wait for status: `running`
   - Timeout: 300 seconds (5 minutes)
   - Poll interval: 10 seconds

4. **Retrieve Connection Details**:
   - Get connection URI: `aura-cli get instance "$INSTANCE_ID" --format json | jq -r '.connection_uri'`
   - Get username (typically `neo4j`)
   - Generate or retrieve password

5. **Configure Application**:
   - Call `./scripts/configure-auradb.sh` with connection details
   - Update `.env` with AuraDB credentials

6. **Verification**:
   - Test connection using cypher-shell
   - Run sample query to verify access
   - Display success message with instance details

7. **Existing Instance** (if `--existing-uri` flag):
   - Skip creation
   - Validate provided URI
   - Test connectivity
   - Configure application

**Testing Requirements**:
- [ ] Verifies Aura CLI is installed
- [ ] Creates new instance when `--create` used
- [ ] Waits for instance to be ready
- [ ] Retrieves connection details correctly
- [ ] Configures application with correct credentials
- [ ] Tests connectivity before completion
- [ ] Works with existing instance when `--existing-uri` used
- [ ] `--help` displays usage
- [ ] Handles API errors gracefully

**Acceptance Criteria**:
- [ ] Executable script at `scripts/setup-auradb.sh`
- [ ] Can create new AuraDB instance
- [ ] Can configure existing instance
- [ ] Updates application `.env` correctly
- [ ] Verifies connectivity before completion
- [ ] Follows project logging patterns
- [ ] Returns exit code 0 on success
- [ ] Provides clear error messages on failure

---

## Sprint 2: Backup & Recovery Scripts (Priority: CRITICAL)

**Estimated Effort**: 2 days
**Blockers**: Required for production deployment
**Dependencies**: PostgreSQL and Neo4j must be running

### Epic 2.1: Database Backup Scripts

#### Task 2.1.1: Create `scripts/backup-database.sh`

**File to Create**: `/home/user/ai-legal-war-machine/scripts/backup-database.sh`

**Purpose**: Automated PostgreSQL database backup

**Script Requirements**:

```bash
#!/bin/bash
set -euo pipefail

#######################################
# PostgreSQL Database Backup Script
#
# Purpose:
# - Create full database backup
# - Compress backup file
# - Rotate old backups
# - Verify backup integrity
#
# Usage:
#   ./scripts/backup-database.sh
#   ./scripts/backup-database.sh --retention 7
#   ./scripts/backup-database.sh --output /custom/path
#
# Options:
#   --output <dir>       Backup directory (default: storage/backups/database)
#   --retention <days>   Keep backups for N days (default: 30)
#   --database <name>    Database name (default: from .env)
#   --compress           Compress backup with gzip (default: true)
#   --help               Display this help
#######################################
```

**Implementation Details**:

1. **Configuration**:
   - Read database credentials from `.env`:
     ```bash
     DB_HOST=${DB_HOST:-127.0.0.1}
     DB_PORT=${DB_PORT:-5432}
     DB_DATABASE=${DB_DATABASE:-ai_legal_war_machine}
     DB_USERNAME=${DB_USERNAME:-claude}
     DB_PASSWORD=${DB_PASSWORD:-claude}
     ```
   - Backup directory: `storage/backups/database`
   - Default retention: 30 days

2. **Backup Creation**:
   - Timestamp: `$(date +%Y%m%d_%H%M%S)`
   - Filename: `${DB_DATABASE}_${TIMESTAMP}.sql`
   - Command: `pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -F c -f "$BACKUP_FILE"`
   - Format: `-F c` (custom format, compressed)
   - Use `PGPASSWORD` environment variable for authentication

3. **Compression** (if `--compress` flag):
   - Compress with gzip: `gzip "$BACKUP_FILE"`
   - Result: `${DB_DATABASE}_${TIMESTAMP}.sql.gz`

4. **Integrity Verification**:
   - Check file size > 0
   - For custom format: `pg_restore --list "$BACKUP_FILE" >/dev/null`
   - Log success with file size

5. **Rotation**:
   - Find backups older than retention period:
     ```bash
     find "$BACKUP_DIR" -name "*.sql*" -mtime +${RETENTION_DAYS} -type f
     ```
   - Delete old backups
   - Log deleted files

6. **Metadata File**:
   - Create JSON metadata: `${BACKUP_FILE}.meta.json`
     ```json
     {
       "timestamp": "2025-12-21T10:30:00Z",
       "database": "ai_legal_war_machine",
       "host": "127.0.0.1",
       "size_bytes": 1234567,
       "format": "custom",
       "compressed": true,
       "pg_version": "16.1"
     }
     ```

**Testing Requirements**:
- [ ] Creates backup file successfully
- [ ] Backup file is not empty
- [ ] Backup can be restored with pg_restore
- [ ] Compression works correctly
- [ ] Old backups are deleted based on retention
- [ ] Metadata file is created
- [ ] Works with custom database name
- [ ] `--help` displays usage
- [ ] Handles connection errors gracefully

**Acceptance Criteria**:
- [ ] Executable script at `scripts/backup-database.sh`
- [ ] Creates compressed backup in `storage/backups/database/`
- [ ] Backup verified before completion
- [ ] Old backups rotated correctly
- [ ] Follows project logging patterns
- [ ] Returns exit code 0 on success
- [ ] Can be run via cron job

---

#### Task 2.1.2: Create `scripts/backup-neo4j.sh`

**File to Create**: `/home/user/ai-legal-war-machine/scripts/backup-neo4j.sh`

**Purpose**: Automated Neo4j graph database backup

**Script Requirements**:

```bash
#!/bin/bash
set -euo pipefail

#######################################
# Neo4j Database Backup Script
#
# Purpose:
# - Create full Neo4j database backup
# - Compress backup file
# - Rotate old backups
# - Verify backup integrity
#
# Usage:
#   ./scripts/backup-neo4j.sh
#   ./scripts/backup-neo4j.sh --retention 7
#   ./scripts/backup-neo4j.sh --output /custom/path
#
# Options:
#   --output <dir>       Backup directory (default: storage/backups/neo4j)
#   --retention <days>   Keep backups for N days (default: 30)
#   --database <name>    Database name (default: neo4j)
#   --mode <local|aura>  Backup mode (default: auto-detect)
#   --help               Display this help
#######################################
```

**Implementation Details**:

1. **Mode Detection**:
   - **Local mode**: If `NEO4J_HOST=localhost` or `127.0.0.1`
   - **AuraDB mode**: If `NEO4J_URI` contains `databases.neo4j.io`
   - Allow override with `--mode` flag

2. **Local Mode Backup**:
   - Use `neo4j-admin backup` command:
     ```bash
     neo4j-admin database backup --database=neo4j --to-path="$BACKUP_DIR"
     ```
   - Requires Neo4j to be running
   - Creates backup in Neo4j native format

3. **AuraDB Mode Backup**:
   - AuraDB doesn't support direct backup access
   - Use `CALL apoc.export.cypher.all()` to export Cypher scripts:
     ```cypher
     cypher-shell -a "$NEO4J_URI" -u "$NEO4J_USERNAME" -p "$NEO4J_PASSWORD" \
       "CALL apoc.export.cypher.all('backup.cypher', {format: 'cypher-shell'})"
     ```
   - Download export file
   - Alternative: Export to JSON via APOC

4. **Compression**:
   - Create tarball: `tar -czf "${BACKUP_NAME}.tar.gz" -C "$BACKUP_DIR" .`
   - Remove uncompressed files

5. **Verification**:
   - Check archive integrity: `tar -tzf "${BACKUP_NAME}.tar.gz" >/dev/null`
   - Check file size > 0
   - Log success with file size

6. **Rotation**:
   - Delete backups older than retention period
   - Keep at least 1 backup even if older than retention

**Testing Requirements**:
- [ ] Detects local vs AuraDB mode correctly
- [ ] Creates backup in local mode
- [ ] Creates export in AuraDB mode
- [ ] Compresses backup successfully
- [ ] Archive integrity verified
- [ ] Old backups rotated
- [ ] `--help` displays usage
- [ ] Handles connection errors

**Acceptance Criteria**:
- [ ] Executable script at `scripts/backup-neo4j.sh`
- [ ] Works for both local and AuraDB
- [ ] Creates compressed backup
- [ ] Backup verified before completion
- [ ] Follows project logging patterns
- [ ] Returns exit code 0 on success

---

#### Task 2.1.3: Create `scripts/backup-app.sh`

**File to Create**: `/home/user/ai-legal-war-machine/scripts/backup-app.sh`

**Purpose**: Backup application files, configuration, and uploaded content

**Script Requirements**:

```bash
#!/bin/bash
set -euo pipefail

#######################################
# Application Backup Script
#
# Purpose:
# - Backup application code
# - Backup .env configuration
# - Backup uploaded files (storage/app)
# - Backup logs (optional)
# - Create compressed archive
#
# Usage:
#   ./scripts/backup-app.sh
#   ./scripts/backup-app.sh --include-logs
#   ./scripts/backup-app.sh --output /custom/path
#
# Options:
#   --output <dir>       Backup directory (default: storage/backups/app)
#   --retention <days>   Keep backups for N days (default: 30)
#   --include-logs       Include log files in backup
#   --include-vendor     Include vendor directory (large)
#   --help               Display this help
#######################################
```

**Implementation Details**:

1. **Backup Components**:
   - **Required**:
     - `.env` file
     - `storage/app/` (uploaded files, documents)
     - `config/` directory
     - `database/migrations/`
   - **Optional** (flags):
     - `storage/logs/` (if `--include-logs`)
     - `vendor/` (if `--include-vendor`)

2. **Exclusions**:
   - `storage/framework/cache/*`
   - `storage/framework/sessions/*`
   - `storage/framework/views/*`
   - `node_modules/`
   - `.git/`
   - `tests/`

3. **Archive Creation**:
   - Filename: `app_backup_${TIMESTAMP}.tar.gz`
   - Create tar archive with compression:
     ```bash
     tar -czf "$BACKUP_FILE" \
       --exclude='storage/framework/cache/*' \
       --exclude='storage/framework/sessions/*' \
       --exclude='storage/framework/views/*' \
       --exclude='node_modules' \
       --exclude='.git' \
       .env config/ storage/app/ database/migrations/
     ```

4. **Metadata**:
   - Git commit hash: `git rev-parse HEAD`
   - Git branch: `git rev-parse --abbrev-ref HEAD`
   - Laravel version: `php artisan --version`
   - Backup size
   - Create metadata JSON file

5. **Verification**:
   - Test archive: `tar -tzf "$BACKUP_FILE" | head -5`
   - Verify .env in archive: `tar -tzf "$BACKUP_FILE" | grep -q ".env"`

6. **Rotation**:
   - Delete backups older than retention period

**Testing Requirements**:
- [ ] Creates backup archive
- [ ] .env file included
- [ ] storage/app/ included
- [ ] Excluded directories not in archive
- [ ] Logs included when `--include-logs` used
- [ ] Archive can be extracted
- [ ] Metadata file created
- [ ] Old backups rotated
- [ ] `--help` displays usage

**Acceptance Criteria**:
- [ ] Executable script at `scripts/backup-app.sh`
- [ ] Creates compressed archive
- [ ] Essential files included
- [ ] Cache/temp files excluded
- [ ] Metadata with git info
- [ ] Follows project logging patterns
- [ ] Returns exit code 0 on success

---

## Sprint 3: Monitoring & Diagnostics (Priority: MEDIUM)

**Estimated Effort**: 1-2 days
**Dependencies**: Application must be deployed

### Epic 3.1: Error Monitoring

#### Task 3.1.1: Create `scripts/check-error-rate.sh`

**File to Create**: `/home/user/ai-legal-war-machine/scripts/check-error-rate.sh`

**Purpose**: Monitor application error rates from logs

**Script Requirements**:

```bash
#!/bin/bash
set -euo pipefail

#######################################
# Error Rate Monitoring Script
#
# Purpose:
# - Analyze Laravel logs for errors
# - Calculate error rate over time
# - Alert if error rate exceeds threshold
# - Generate error summary report
#
# Usage:
#   ./scripts/check-error-rate.sh
#   ./scripts/check-error-rate.sh --threshold 10
#   ./scripts/check-error-rate.sh --period 1h
#
# Options:
#   --threshold <num>    Error threshold per minute (default: 5)
#   --period <time>      Time period to analyze (default: 1h)
#   --log-file <path>    Laravel log file (default: storage/logs/laravel.log)
#   --alert              Send alert if threshold exceeded
#   --help               Display this help
#######################################
```

**Implementation Details**:

1. **Log Parsing**:
   - Default log: `storage/logs/laravel.log`
   - Parse Laravel log format:
     ```
     [2025-12-21 10:30:00] production.ERROR: ...
     ```
   - Extract timestamp and error level

2. **Time Period Selection**:
   - Support formats: `1h`, `30m`, `1d`
   - Convert to seconds for comparison
   - Filter log entries within period:
     ```bash
     awk -v cutoff="$CUTOFF_TIMESTAMP" '$1 >= cutoff' "$LOG_FILE"
     ```

3. **Error Counting**:
   - Count by severity:
     - ERROR
     - CRITICAL
     - EMERGENCY
   - Count total errors in period
   - Calculate errors per minute: `total_errors / (period_seconds / 60)`

4. **Threshold Check**:
   - Compare error rate to threshold
   - Exit code 0 if below threshold
   - Exit code 1 if above threshold

5. **Report Generation**:
   - Display summary:
     ```
     Error Rate Report (Last 1 hour)
     ================================
     Total Errors: 45
     ERROR: 40
     CRITICAL: 4
     EMERGENCY: 1

     Error Rate: 0.75 errors/minute
     Threshold: 5.00 errors/minute
     Status: HEALTHY
     ```

6. **Alert Integration** (if `--alert` flag):
   - If threshold exceeded, trigger alert
   - Methods:
     - Write to alert file: `storage/alerts/error-rate-alert.txt`
     - Send to logging service (future: integrate with monitoring)
     - Exit with code 1 (for monitoring systems)

7. **Error Sampling**:
   - Show top 5 most common error messages
   - Extract unique error patterns
   - Group similar errors

**Testing Requirements**:
- [ ] Correctly parses Laravel log format
- [ ] Filters logs by time period
- [ ] Counts errors by severity
- [ ] Calculates error rate accurately
- [ ] Threshold comparison works
- [ ] Report displays correctly
- [ ] `--help` displays usage
- [ ] Handles missing log file gracefully

**Acceptance Criteria**:
- [ ] Executable script at `scripts/check-error-rate.sh`
- [ ] Analyzes Laravel logs correctly
- [ ] Calculates error rate per minute
- [ ] Compares to threshold
- [ ] Generates summary report
- [ ] Exit code indicates healthy/unhealthy
- [ ] Follows project logging patterns

---

### Epic 3.2: Log Rotation Configuration

#### Task 3.2.1: Create Logrotate Configuration

**File to Create**: `/home/user/ai-legal-war-machine/docs/server-config/logrotate/ai-legal-war-machine`

**Purpose**: Logrotate configuration for application logs

**Configuration Requirements**:

```
#######################################
# AI Legal War Machine - Logrotate Configuration
#
# Purpose:
# - Rotate Laravel application logs
# - Rotate web server logs
# - Compress old logs
# - Retain logs for compliance
#
# Installation:
#   sudo cp docs/server-config/logrotate/ai-legal-war-machine /etc/logrotate.d/
#   sudo chmod 644 /etc/logrotate.d/ai-legal-war-machine
#   sudo logrotate -d /etc/logrotate.d/ai-legal-war-machine  # Test
#######################################

# Laravel Application Logs
/var/www/ai-legal-war-machine/storage/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        # Reload PHP-FPM to release log file handles
        systemctl reload php8.2-fpm > /dev/null 2>&1 || true
    endscript
}

# Laravel Queue Worker Logs (if separate)
/var/www/ai-legal-war-machine/storage/logs/queue-worker.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        # Signal queue workers to reopen logs
        killall -USR1 php > /dev/null 2>&1 || true
    endscript
}

# Nginx Access Logs
/var/log/nginx/ai-legal-war-machine-access.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        systemctl reload nginx > /dev/null 2>&1 || true
    endscript
}

# Nginx Error Logs
/var/log/nginx/ai-legal-war-machine-error.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        systemctl reload nginx > /dev/null 2>&1 || true
    endscript
}
```

**Implementation Details**:

1. **Directory Structure**:
   - Create: `docs/server-config/logrotate/`
   - File: `ai-legal-war-machine`

2. **Configuration Options**:
   - `daily`: Rotate logs daily
   - `rotate 30`: Keep 30 days of logs
   - `compress`: Compress old logs with gzip
   - `delaycompress`: Don't compress the most recent rotation
   - `notifempty`: Don't rotate empty logs
   - `create`: Create new log file with specified permissions
   - `sharedscripts`: Run postrotate once for all logs

3. **Installation Script**:
   - Create: `scripts/install-logrotate.sh`
   - Copy config to `/etc/logrotate.d/`
   - Set correct permissions
   - Test configuration

**Testing Requirements**:
- [ ] Configuration file has correct syntax
- [ ] `logrotate -d` test mode succeeds
- [ ] Creates directory structure
- [ ] Installation script works

**Acceptance Criteria**:
- [ ] Configuration file at `docs/server-config/logrotate/ai-legal-war-machine`
- [ ] Valid logrotate syntax
- [ ] Includes all application logs
- [ ] Documented installation steps
- [ ] Follows logrotate best practices

---

## Sprint 4: Testing Infrastructure (Priority: MEDIUM)

**Estimated Effort**: 1 day

### Epic 4.1: Neo4j Testing

#### Task 4.1.1: Create `scripts/run-neo4j-tests.sh`

**File to Create**: `/home/user/ai-legal-war-machine/scripts/run-neo4j-tests.sh`

**Purpose**: Run tests specifically for Neo4j integration

**Script Requirements**:

```bash
#!/bin/bash
set -euo pipefail

#######################################
# Neo4j Integration Tests Runner
#
# Purpose:
# - Run only Neo4j-related tests
# - Verify Neo4j connectivity
# - Clean test data after run
# - Generate coverage report
#
# Usage:
#   ./scripts/run-neo4j-tests.sh
#   ./scripts/run-neo4j-tests.sh --coverage
#   ./scripts/run-neo4j-tests.sh --filter GraphServiceTest
#
# Options:
#   --filter <pattern>   Run specific test class/method
#   --coverage           Generate code coverage report
#   --clean              Clean Neo4j test database first
#   --help               Display this help
#######################################
```

**Implementation Details**:

1. **Test Discovery**:
   - Find Neo4j-related tests:
     ```bash
     find tests/ -name "*Neo4j*Test.php"
     find tests/ -name "*Graph*Test.php"
     ```
   - Test groups: `@group neo4j`

2. **Pre-Test Setup**:
   - Verify Neo4j is running
   - Create/clear test database if `--clean`:
     ```bash
     cypher-shell -u neo4j -p "$NEO4J_PASSWORD" \
       "CREATE DATABASE neo4j_test IF NOT EXISTS;"
     cypher-shell -d neo4j_test -u neo4j -p "$NEO4J_PASSWORD" \
       "MATCH (n) DETACH DELETE n;"
     ```

3. **Test Execution**:
   - Run PHPUnit with Neo4j group:
     ```bash
     php artisan test --group=neo4j
     ```
   - Or specific filter:
     ```bash
     php artisan test --filter="$FILTER"
     ```

4. **Coverage** (if `--coverage` flag):
   - Generate coverage report:
     ```bash
     php artisan test --group=neo4j --coverage --min=80
     ```
   - Output to: `test-results/neo4j-coverage/`

5. **Post-Test Cleanup**:
   - Clean test database:
     ```bash
     cypher-shell -d neo4j_test "MATCH (n) DETACH DELETE n;"
     ```
   - Remove test data files

6. **Report**:
   - Display test summary
   - Show coverage percentage
   - List failed tests if any

**Testing Requirements**:
- [ ] Discovers Neo4j tests correctly
- [ ] Verifies Neo4j connectivity before run
- [ ] Runs tests successfully
- [ ] Cleans test data when `--clean` used
- [ ] Generates coverage when requested
- [ ] `--help` displays usage

**Acceptance Criteria**:
- [ ] Executable script at `scripts/run-neo4j-tests.sh`
- [ ] Runs Neo4j-specific tests
- [ ] Verifies connectivity first
- [ ] Cleans up after tests
- [ ] Follows project logging patterns
- [ ] Returns PHPUnit exit code

---

## Sprint 5: Missing Documentation Files (Priority: LOW)

**Estimated Effort**: 2-3 days

### Epic 5.1: MCP Tools Documentation

#### Task 5.1.1: Create `docs/MCP_TOOLS.md`

**File to Create**: `/home/user/ai-legal-war-machine/docs/MCP_TOOLS.md`

**Purpose**: Document MCP (Model Context Protocol) tools integration

**Content Requirements**:

1. **Overview**:
   - What is MCP
   - Why we use MCP tools
   - Available MCP servers

2. **Installation**:
   - How to install MCP tools
   - Configuration in Claude Desktop
   - Environment setup

3. **Available Tools**:
   - List each MCP tool
   - Purpose and use cases
   - Example usage

4. **Integration**:
   - How application uses MCP
   - API endpoints
   - Authentication

5. **Troubleshooting**:
   - Common issues
   - Debugging steps
   - Log locations

**Acceptance Criteria**:
- [ ] File exists at `docs/MCP_TOOLS.md`
- [ ] Comprehensive MCP documentation
- [ ] Code examples included
- [ ] Troubleshooting section
- [ ] Links to external resources

---

#### Task 5.1.2: Create `docs/RAG_GUIDE.md`

**File to Create**: `/home/user/ai-legal-war-machine/docs/RAG_GUIDE.md`

**Purpose**: Guide for RAG (Retrieval-Augmented Generation) implementation

**Content Requirements**:

1. **RAG Overview**:
   - What is RAG
   - Architecture overview
   - Components

2. **Vector Store**:
   - Meilisearch integration
   - Embedding generation
   - Document indexing

3. **Retrieval Process**:
   - Query processing
   - Similarity search
   - Ranking algorithms

4. **Generation**:
   - LLM integration
   - Prompt engineering
   - Context injection

5. **Implementation**:
   - Code examples
   - API usage
   - Best practices

6. **Performance**:
   - Optimization techniques
   - Caching strategies
   - Benchmarks

**Acceptance Criteria**:
- [ ] File exists at `docs/RAG_GUIDE.md`
- [ ] Complete RAG implementation guide
- [ ] Architecture diagrams
- [ ] Code examples
- [ ] Performance tuning section

---

#### Task 5.1.3: Create `docs/MCP_ACCESS_GUIDE.md`

**File to Create**: `/home/user/ai-legal-war-machine/docs/MCP_ACCESS_GUIDE.md`

**Purpose**: Guide for accessing and using MCP tools in the application

**Content Requirements**:

1. **Access Setup**:
   - Authentication configuration
   - API key management
   - Permissions setup

2. **Using MCP Tools**:
   - Available tools list
   - Usage examples
   - Rate limits

3. **Security**:
   - API key storage
   - Access control
   - Audit logging

4. **Integration Examples**:
   - PHP code examples
   - Livewire integration
   - API endpoints

**Acceptance Criteria**:
- [ ] File exists at `docs/MCP_ACCESS_GUIDE.md`
- [ ] Comprehensive access guide
- [ ] Security best practices
- [ ] Code examples

---

#### Task 5.1.4: Create `docs/GRAPH_SCHEMA.md`

**File to Create**: `/home/user/ai-legal-war-machine/docs/GRAPH_SCHEMA.md`

**Purpose**: Document Neo4j graph database schema

**Content Requirements**:

1. **Schema Overview**:
   - Graph model philosophy
   - Node types
   - Relationship types

2. **Node Definitions**:
   - Each node type documented
   - Properties and indexes
   - Constraints

3. **Relationship Definitions**:
   - Each relationship type
   - Properties
   - Direction and cardinality

4. **Cypher Queries**:
   - Common query patterns
   - Example queries
   - Query optimization

5. **Schema Evolution**:
   - Migration strategy
   - Versioning
   - Backward compatibility

6. **Visual Diagrams**:
   - Graph schema diagram
   - Example subgraphs
   - Query visualization

**Acceptance Criteria**:
- [ ] File exists at `docs/GRAPH_SCHEMA.md`
- [ ] Complete schema documentation
- [ ] All nodes and relationships documented
- [ ] Cypher examples
- [ ] Visual diagrams (Mermaid or ASCII)

---

#### Task 5.1.5: Create `MIGRATION_AGENT_TOOLBOX.md`

**File to Create**: `/home/user/ai-legal-war-machine/MIGRATION_AGENT_TOOLBOX.md`

**Purpose**: Toolbox guide for AI agents performing database migrations

**Content Requirements**:

1. **Agent Overview**:
   - What is migration agent
   - Responsibilities
   - Workflow

2. **Migration Tools**:
   - Laravel migration commands
   - Neo4j migration scripts
   - Data transformation tools

3. **Migration Process**:
   - Pre-migration checks
   - Execution steps
   - Post-migration verification

4. **Rollback Procedures**:
   - How to rollback
   - Backup restoration
   - Data consistency checks

5. **Testing Migrations**:
   - Test database setup
   - Migration testing
   - Data validation

6. **Common Patterns**:
   - Adding columns
   - Changing types
   - Data transformations
   - Index management

**Acceptance Criteria**:
- [ ] File exists at `MIGRATION_AGENT_TOOLBOX.md`
- [ ] Complete migration guide
- [ ] Step-by-step procedures
- [ ] Rollback instructions
- [ ] Testing procedures

---

## Implementation Priority Matrix

| Sprint | Epic | Priority | Estimated Effort | Blocking? |
|--------|------|----------|------------------|-----------|
| 1 | Neo4j Setup Scripts | HIGH | 2-3 days | Yes - Production |
| 2 | Backup Scripts | CRITICAL | 2 days | Yes - Production |
| 3 | Monitoring Scripts | MEDIUM | 1-2 days | No |
| 4 | Testing Scripts | MEDIUM | 1 day | No |
| 5 | Documentation Files | LOW | 2-3 days | No |

**Total Estimated Effort**: 8-11 days

---

## Testing Strategy

### Unit Testing
- Each script should have manual test cases documented
- Test both success and failure scenarios
- Test with and without required permissions

### Integration Testing
- Test scripts in sequence (setup → backup → monitoring)
- Test on clean system
- Test idempotency (run twice, same result)

### Documentation Testing
- Verify all code examples in docs execute correctly
- Check all links in documentation
- Validate configuration examples

---

## Acceptance Criteria (Overall)

- [ ] All scripts executable and working
- [ ] All scripts follow project patterns (logging, error handling)
- [ ] All scripts have `--help` flag
- [ ] All scripts are idempotent where applicable
- [ ] All backup scripts tested with restore procedures
- [ ] All documentation files complete and accurate
- [ ] All configuration files have installation instructions
- [ ] Code review completed
- [ ] Documentation review completed

---

## Notes

1. **Script Patterns**: All scripts should follow the patterns established in existing scripts:
   - Use `set -euo pipefail` for strict error handling
   - Include standard logging functions (log, warn, error, success)
   - Include comprehensive header documentation
   - Support `--help` flag
   - Exit with appropriate codes (0 = success, 1 = failure)

2. **Testing**: All scripts should be tested on a clean system to ensure they work without assumptions about existing setup.

3. **Documentation**: All documentation should include:
   - Overview/purpose
   - Prerequisites
   - Step-by-step instructions
   - Code examples that actually work
   - Troubleshooting section
   - Links to related documentation

4. **Security**: Scripts handling credentials should:
   - Never log passwords
   - Read from environment variables or `.env`
   - Use secure methods for credential storage
   - Support `--dry-run` for testing without side effects

5. **Maintenance**: All scripts should be maintainable:
   - Clear variable names
   - Comments for complex logic
   - Modular functions
   - Error messages that help debugging

---

## Next Steps

1. **Review this document** with the development team
2. **Prioritize tasks** based on immediate needs
3. **Create GitHub issues** for each task
4. **Assign to sprints** based on team capacity
5. **Begin with Sprint 1** (Neo4j Setup Scripts) as they block production deployment
6. **Track progress** in project management tool
7. **Update documentation** as scripts are completed

---

**Document Version**: 1.0
**Last Updated**: 2025-12-21
**Maintained By**: AI Legal War Machine Development Team
