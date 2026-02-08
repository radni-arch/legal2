#!/bin/bash

# ============================================
# Test Database Setup Script
# ============================================
# This script creates a test database by copying from production
# Usage:
#   ./scripts/setup-test-db.sh                    # Interactive mode
#   ./scripts/setup-test-db.sh --auto             # Auto mode with defaults
#   ./scripts/setup-test-db.sh --source-db=mydb   # Specify source database
#   ./scripts/setup-test-db.sh --force            # Force overwrite existing test DB
# ============================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
AUTO_MODE=false
FORCE_OVERWRITE=false
SOURCE_DB=""
TEST_DB="laravel_test"

# Parse arguments
for arg in "$@"; do
    case $arg in
        --auto)
            AUTO_MODE=true
            shift
            ;;
        --force)
            FORCE_OVERWRITE=true
            shift
            ;;
        --source-db=*)
            SOURCE_DB="${arg#*=}"
            shift
            ;;
        --test-db=*)
            TEST_DB="${arg#*=}"
            shift
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --auto              Run in automatic mode (no prompts)"
            echo "  --force             Force overwrite existing test database"
            echo "  --source-db=NAME    Specify source database name"
            echo "  --test-db=NAME      Specify test database name (default: laravel_test)"
            echo "  --help              Show this help message"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $arg${NC}"
            exit 1
            ;;
    esac
done

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         Test Database Setup - Production Copy             ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Load .env to get production database name if not specified
if [ -z "$SOURCE_DB" ]; then
    if [ -f .env ]; then
        export $(cat .env | grep -v '^#' | grep 'DB_DATABASE=' | xargs)
        if [ -n "$DB_DATABASE" ]; then
            SOURCE_DB="$DB_DATABASE"
        fi
    fi
fi

# Interactive mode - ask for source database
if [ "$AUTO_MODE" = false ] && [ -z "$SOURCE_DB" ]; then
    echo -e "${YELLOW}Enter the name of the production database to copy:${NC}"
    read -p "Source database name: " SOURCE_DB

    if [ -z "$SOURCE_DB" ]; then
        echo -e "${RED}Error: Source database name is required${NC}"
        exit 1
    fi
fi

# Validate source database name
if [ -z "$SOURCE_DB" ]; then
    echo -e "${RED}Error: Could not determine source database name${NC}"
    echo "Please specify it using --source-db=NAME or set DB_DATABASE in .env"
    exit 1
fi

echo ""
echo -e "${GREEN}Configuration:${NC}"
echo "  Source Database: $SOURCE_DB"
echo "  Test Database:   $TEST_DB"
echo ""

# Check if .env.testing exists
if [ ! -f .env.testing ]; then
    echo -e "${RED}Error: .env.testing file not found${NC}"
    echo "Please create .env.testing file first"
    exit 1
fi

# Determine database connection from .env
export $(cat .env | grep -v '^#' | grep 'DB_CONNECTION=' | xargs)
DB_TYPE="${DB_CONNECTION:-sqlite}"

echo -e "${BLUE}Database type detected: $DB_TYPE${NC}"
echo ""

# Function to copy PostgreSQL database
copy_postgres_db() {
    echo -e "${YELLOW}Setting up PostgreSQL test database...${NC}"

    # Get connection details from .env
    export $(cat .env | grep -v '^#' | grep -E '^DB_' | xargs)

    DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_PORT="${DB_PORT:-5432}"
    DB_USER="${DB_USERNAME:-postgres}"

    # Check if source database exists
    echo "Checking if source database exists..."
    if ! PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -lqt | cut -d \| -f 1 | grep -qw "$SOURCE_DB"; then
        echo -e "${RED}Error: Source database '$SOURCE_DB' does not exist${NC}"
        exit 1
    fi

    # Check if test database exists
    if PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -lqt | cut -d \| -f 1 | grep -qw "$TEST_DB"; then
        if [ "$FORCE_OVERWRITE" = true ]; then
            echo -e "${YELLOW}Dropping existing test database...${NC}"
            PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -c "DROP DATABASE IF EXISTS $TEST_DB;"
        else
            echo -e "${YELLOW}Test database already exists.${NC}"
            read -p "Do you want to recreate it? (y/N): " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -c "DROP DATABASE IF EXISTS $TEST_DB;"
            else
                echo -e "${GREEN}Using existing test database${NC}"
                return 0
            fi
        fi
    fi

    # Create test database by copying production
    echo -e "${YELLOW}Creating test database as copy of production...${NC}"
    PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -c "CREATE DATABASE $TEST_DB WITH TEMPLATE $SOURCE_DB OWNER $DB_USER;"

    echo -e "${GREEN}✓ PostgreSQL test database created successfully${NC}"
}

# Function to copy MySQL database
copy_mysql_db() {
    echo -e "${YELLOW}Setting up MySQL test database...${NC}"

    # Get connection details from .env
    export $(cat .env | grep -v '^#' | grep -E '^DB_' | xargs)

    DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_PORT="${DB_PORT:-3306}"
    DB_USER="${DB_USERNAME:-root}"

    # Check if source database exists
    echo "Checking if source database exists..."
    if ! mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -e "USE $SOURCE_DB" 2>/dev/null; then
        echo -e "${RED}Error: Source database '$SOURCE_DB' does not exist${NC}"
        exit 1
    fi

    # Drop test database if it exists
    if [ "$FORCE_OVERWRITE" = true ]; then
        echo -e "${YELLOW}Dropping existing test database...${NC}"
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -e "DROP DATABASE IF EXISTS $TEST_DB;"
    fi

    # Create test database
    echo -e "${YELLOW}Creating test database...${NC}"
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $TEST_DB;"

    # Copy database structure and data
    echo -e "${YELLOW}Copying database structure and data...${NC}"
    mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$SOURCE_DB" | \
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$TEST_DB"

    echo -e "${GREEN}✓ MySQL test database created successfully${NC}"
}

# Function to copy SQLite database
copy_sqlite_db() {
    echo -e "${YELLOW}Setting up SQLite test database...${NC}"

    # Get database path from .env
    export $(cat .env | grep -v '^#' | grep 'DB_DATABASE=' | xargs)

    if [ "$DB_DATABASE" = ":memory:" ]; then
        echo -e "${RED}Error: Cannot copy from in-memory database${NC}"
        echo "Please use a file-based SQLite database for production"
        exit 1
    fi

    if [ ! -f "$DB_DATABASE" ]; then
        echo -e "${RED}Error: Source database file '$DB_DATABASE' does not exist${NC}"
        exit 1
    fi

    # Copy database file
    TEST_DB_FILE="${TEST_DB}.sqlite"
    echo -e "${YELLOW}Copying database file to $TEST_DB_FILE...${NC}"
    cp "$DB_DATABASE" "$TEST_DB_FILE"

    echo -e "${GREEN}✓ SQLite test database created successfully${NC}"
}

# Execute appropriate copy function based on database type
case $DB_TYPE in
    pgsql|postgresql)
        copy_postgres_db
        ;;
    mysql)
        copy_mysql_db
        ;;
    sqlite)
        copy_sqlite_db
        ;;
    *)
        echo -e "${RED}Error: Unsupported database type: $DB_TYPE${NC}"
        echo "Supported types: pgsql, mysql, sqlite"
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              Test Database Setup Complete!                ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "  1. Update .env.testing with correct DB_DATABASE=$TEST_DB"
echo "  2. Run tests with: composer test:integrated"
echo ""
echo -e "${YELLOW}Note: Tests use database transactions to keep data intact.${NC}"
echo -e "${YELLOW}You can regenerate test data anytime by running this script again.${NC}"
echo ""
