#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <phpunit-filter-or-path>"
  echo
  echo "Examples:"
  echo "  $0 VectorStoreManagerTest"
  echo "  $0 tests/Feature/Livewire/VectorStoreManagerTest.php"
  exit 1
fi

TARGET="$1"

mkdir -p test-logs test-results

SAFE_NAME="$(echo "$TARGET" | tr '/\\:' '_' )"

echo "======================================================"
echo "Running focused tests for target: $TARGET"
echo "Command: php artisan test --filter=$TARGET"
echo "======================================================"

php artisan test --filter="$TARGET" | tee "test-logs/${SAFE_NAME}-results.txt"

RFC3339_NOW="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
cat > test-results/.last-run.json <<EOF
{
  "suite": "focused",
  "target": "${TARGET}",
  "last_run": "${RFC3339_NOW}"
}
EOF

echo
echo "======================================================"
echo "Focused tests finished for: $TARGET"
echo " - Log:      test-logs/${SAFE_NAME}-results.txt"
echo " - Metadata: test-results/.last-run.json"
echo "======================================================"