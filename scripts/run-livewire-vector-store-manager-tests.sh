#!/usr/bin/env bash
set -euo pipefail

# Resolve repo root (script can be run from anywhere)
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

mkdir -p test-logs test-results

echo "======================================================"
echo "Running Livewire VectorStoreManager feature tests..."
echo "Target test class: tests/Feature/Livewire/VectorStoreManagerTest.php"
echo "Command: php artisan test --filter=VectorStoreManagerTest"
echo "======================================================"

# Run the focused Livewire feature test suite
php artisan test --filter=VectorStoreManagerTest | tee test-logs/vector-store-manager-tests.txt

# Optionally update a simple JSON marker for last run
# (No external tools like jq required.)
RFC3339_NOW="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
cat > test-results/.last-run.json <<EOF
{
  "suite": "livewire-vector-store-manager",
  "last_run": "${RFC3339_NOW}"
}
EOF

echo
echo "======================================================"
echo "Livewire VectorStoreManager tests finished."
echo " - Log:      test-logs/vector-store-manager-tests.txt"
echo " - Metadata: test-results/.last-run.json"
echo "======================================================"
