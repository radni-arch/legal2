#!/usr/bin/env bash
set -euo pipefail

# Determine project root (this script is in scripts/)
ROOT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$ROOT_DIR"

# Directory for logs
LOG_DIR="test-results"
mkdir -p "$LOG_DIR"

TIMESTAMP="$(date -Iseconds)"
LOG_FILE="$LOG_DIR/textract-tests-$TIMESTAMP.log"
LATEST_LOG="$LOG_DIR/textract-tests-latest.log"

echo "Running TextractMockDataGenerator unit tests..."
echo "Logging output to: $LOG_FILE"

# Run only the TextractMockDataGenerator unit tests
php artisan test tests/Unit/TextractMockDataGeneratorTest.php | tee "$LOG_FILE"

# Update latest log copy
cp "$LOG_FILE" "$LATEST_LOG"

echo
echo "Textract tests completed."
echo "Latest log: $LATEST_LOG"
