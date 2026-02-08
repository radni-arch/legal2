#!/bin/bash

set -e

echo "🧪 AI Legal War Machine - E2E Browser Tests"
echo "============================================"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if server is running
if ! curl -s http://localhost:8000 > /dev/null; then
    echo -e "${YELLOW}⚠️  Laravel server not running. Starting...${NC}"
    php artisan serve > /dev/null 2>&1 &
    SERVER_PID=$!
    sleep 3
    echo -e "${GREEN}✓ Server started (PID: $SERVER_PID)${NC}"
else
    echo -e "${GREEN}✓ Server already running${NC}"
    SERVER_PID=""
fi

# Parse arguments
FILTER=""
SUITE="all"

while [[ $# -gt 0 ]]; do
    case $1 in
        --filter)
            FILTER="--filter=$2"
            shift 2
            ;;
        --suite)
            SUITE="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Run tests based on suite
case $SUITE in
    auth)
        echo -e "${BLUE}Running authentication tests...${NC}"
        php artisan dusk --filter=AuthenticationTest $FILTER
        ;;
    playground)
        echo -e "${BLUE}Running Legal Playground tests...${NC}"
        php artisan dusk --filter=LegalPlaygroundTest $FILTER
        ;;
    textract)
        echo -e "${BLUE}Running Textract Manager tests...${NC}"
        php artisan dusk --filter=TextractManagerTest $FILTER
        ;;
    graph)
        echo -e "${BLUE}Running Graph Viewer tests...${NC}"
        php artisan dusk --filter=GraphViewerTest $FILTER
        ;;
    laws)
        echo -e "${BLUE}Running Law Download tests...${NC}"
        php artisan dusk --filter=LawDownloadTest $FILTER
        ;;
    timeline)
        echo -e "${BLUE}Running Timeline tests...${NC}"
        php artisan dusk --filter=TimelineTest $FILTER
        ;;
    all)
        echo -e "${BLUE}Running ALL E2E browser tests...${NC}"
        php artisan dusk $FILTER
        ;;
    *)
        echo "Unknown suite: $SUITE"
        echo "Available suites: auth, playground, textract, graph, laws, timeline, all"
        exit 1
        ;;
esac

EXIT_CODE=$?

# Cleanup
if [ ! -z "$SERVER_PID" ]; then
    echo -e "${YELLOW}Stopping server (PID: $SERVER_PID)...${NC}"
    kill $SERVER_PID 2>/dev/null || true
fi

if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo -e "${YELLOW}⚠️  Some tests failed${NC}"
fi

exit $EXIT_CODE
