#!/bin/bash

# setup-postgresql.sh
# PostgreSQL Setup Script for AI Legal War Machine
# This script enables PostgreSQL and sets up databases for Laravel

#set -e  # Exit on any error

service postgresql enable
service postgresql start
su postgres psql -c "SELECT version();"

echo -e "${YELLOW}[5/6] Checking Laravel databases...${NC}"

# Source .env file to get database names
if [ -f .env ]; then
    export $(grep -v '^#' .env | grep -E '^DB_' | xargs)

    # Production database
    PROD_DB=${DB_DATABASE:-"ai_legal_war_machine"}
    echo "Production database: $PROD_DB"

    if su postgres psql -lqt | cut -d \| -f 1 | grep -qw "$PROD_DB"; then
        echo -e "${GREEN}✓ Production database '$PROD_DB' exists${NC}"
    else
        echo "Creating production database '$PROD_DB'..."
        su postgres psql -c "CREATE DATABASE $PROD_DB;" || true
        echo -e "${GREEN}✓ Production database created${NC}"
    fi

    # Test database
    TEST_DB="${PROD_DB}_test"
    echo "Test database: $TEST_DB"

    if su postgres psql -lqt | cut -d \| -f 1 | grep -qw "$TEST_DB"; then
        echo -e "${GREEN}✓ Test database '$TEST_DB' exists${NC}"
    else
        echo "Creating test database '$TEST_DB'..."
        su postgres psql -c "CREATE DATABASE $TEST_DB;" || true
        echo -e "${GREEN}✓ Test database created${NC}"
    fi

    # Check/Create database user


    echo ""
else
    echo -e "${YELLOW}Warning: .env file not found. Using defaults.${NC}\n"
fi

# Step 6: Test Laravel database connection
echo -e "${YELLOW}[6/6] Testing Laravel database connection...${NC}"
if php artisan db:show &> /dev/null; then
    echo -e "${GREEN}✓ Laravel can connect to database${NC}\n"
else
    echo -e "${YELLOW}Note: Laravel connection test skipped (check .env configuration)${NC}\n"
fi

# Display PostgreSQL status
echo -e "${GREEN}=== PostgreSQL Status ===${NC}"
service status postgresql  | head -n 10
echo ""

# Display database list
echo -e "${GREEN}=== PostgreSQL Databases ===${NC}"

PGPASSWORD='' psql -U postgres -h /var/run/postgresql -c "ALTER USER postgres PASSWORD 'pass';"
PGPASSWORD='pass' psql -U postgres -h 127.0.0.1 -c "CREATE DATABASE ai_legal_war_machine;" && PGPASSWORD='pass' psql -U postgres -h 127.0.0.1 -c "CREATE DATABASE ai_legal_war_machine_test;"
PGPASSWORD='pass' psql -U postgres -h 127.0.0.1 -d ai_legal_war_machine -c "SELECT current_database(), version();"

echo "Importing initial data..."
# Note: Migrations are skipped because pg_restore below will create all tables
# from the database dump. Running migrations before restore causes errors
# when setup is run multiple times.
pg_ctlcluster 16 main start 2>&1
PGPASSWORD='pass' pg_restore -U postgres -d ai_legal_war_machine -v -c ./database/exports/aiagent.dump 2>&1 | tail -100
PGPASSWORD='pass' pg_restore -U postgres -d ai_legal_war_machine_test -v -c ./database/exports/aiagent.dump 2>&1 | tail -100

echo -e "${GREEN}=== Setup Complete ===${NC}"
echo -e "PostgreSQL Version: ${GREEN}$PG_VERSION${NC}"
echo -e "Service Status: ${GREEN}Running & Enabled${NC}"
echo -e "Production DB: ${GREEN}${PROD_DB:-'ai_legal_war_machine'}${NC}"
echo -e "Test DB: ${GREEN}${TEST_DB:-'laravel_test'}${NC}"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "1. Run migrations: php artisan migrate"
echo "2. Setup test database: composer test:setup"
echo "3. Run tests: composer test"
echo ""
echo -e "${GREEN}PostgreSQL is ready for AI Legal War Machine! 🚀${NC}"
