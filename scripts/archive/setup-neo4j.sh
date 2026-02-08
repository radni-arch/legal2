#!/bin/bash
set -e

# Neo4j Setup Script for AI Legal War Machine
# This script attempts to install Neo4j, but gracefully handles failures
# The application can run without Neo4j (NEO4J_ENABLED=false)

echo "========================================="
echo "Neo4j Setup Script"
echo "========================================="
echo ""

# Check if install-neo4j.sh exists and is executable
if [ -f "./scripts/install-neo4j.sh" ]; then
    echo "Running comprehensive Neo4j installation..."
    bash ./scripts/install-neo4j.sh

    if [ $? -eq 0 ]; then
        echo "✓ Neo4j installed successfully"

        # Update .env
        if grep -q "NEO4J_ENABLED" .env; then
            sed -i 's/NEO4J_ENABLED=.*/NEO4J_ENABLED=true/' .env
        else
            echo "NEO4J_ENABLED=true" >> .env
        fi

        echo "✓ Neo4j enabled in .env"
        exit 0
    else
        echo "⚠ Neo4j installation failed (network restrictions or other issues)"
        echo "The application will run without graph database features"
        echo ""
    fi
else
    echo "⚠ install-neo4j.sh not found"
fi

# Fallback: Disable Neo4j in .env
echo "Disabling Neo4j in .env..."
if grep -q "NEO4J_ENABLED" .env; then
    sed -i 's/NEO4J_ENABLED=.*/NEO4J_ENABLED=false/' .env
else
    echo "NEO4J_ENABLED=false" >> .env
fi

echo ""
echo "========================================="
echo "Neo4j Setup Complete (Disabled)"
echo "========================================="
echo ""
echo "⚠ Neo4j is DISABLED. The application will work but graph features are unavailable."
echo ""
echo "To enable Neo4j manually:"
echo "  1. Install Neo4j from: https://neo4j.com/download/"
echo "  2. Or run: bash scripts/install-neo4j.sh"
echo "  3. Set NEO4J_ENABLED=true in .env"
echo "  4. Run: php artisan graph:init"
echo "  5. Run: php artisan neo4j:create-indexes"
echo "  6. Run: php artisan graph:sync --all"
echo ""
echo "For Docker installation:"
echo "  docker run -d --name neo4j -p 7474:7474 -p 7687:7687 -e NEO4J_AUTH=neo4j/password neo4j:5.13-community"
echo "    -p 7474:7474 -p 7687:7687 \\"
echo "    -e NEO4J_AUTH=neo4j/pass \\"
echo "    neo4j:5.13-community"
echo ""
