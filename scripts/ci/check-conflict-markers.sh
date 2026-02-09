#!/bin/bash
# Check for unresolved git merge conflict markers in tracked files.
#
# Exit 1 if any found, exit 0 if clean.
#
# Usage:
#   bash scripts/ci/check-conflict-markers.sh
#
# Searches all git-tracked files (excluding vendor, node_modules, lock files,
# and minified files) for the three standard git conflict markers:
#   <<<<<<< (start of conflict)
#   ======= (conflict separator, only when alone on a line)
#   >>>>>>> (end of conflict)

set -euo pipefail

# Use git grep to search tracked files for conflict markers.
# Patterns:
#   ^<{7}   - line starting with 7 '<' chars (conflict start)
#   ^={7}$  - line with exactly 7 '=' chars (conflict separator)
#   ^>{7}   - line starting with 7 '>' chars (conflict end)
#
# Exclusions: vendor, node_modules, lock files, minified files
FOUND=0

echo "Checking for unresolved git merge conflict markers..."

# Check for <<<<<<< markers
if git grep -n -E '^<{7}\s' -- ':!vendor' ':!node_modules' ':!*.lock' ':!*.min.*' ':!*.min.js' ':!*.min.css' 2>/dev/null; then
    FOUND=1
fi

# Check for ======= markers (exactly 7 = on a line by itself)
if git grep -n -E '^\s*={7}\s*$' -- ':!vendor' ':!node_modules' ':!*.lock' ':!*.min.*' ':!*.min.js' ':!*.min.css' 2>/dev/null; then
    FOUND=1
fi

# Check for >>>>>>> markers
if git grep -n -E '^>{7}\s' -- ':!vendor' ':!node_modules' ':!*.lock' ':!*.min.*' ':!*.min.js' ':!*.min.css' 2>/dev/null; then
    FOUND=1
fi

if [ "$FOUND" -eq 1 ]; then
    echo ""
    echo "ERROR: Unresolved git merge conflict markers found!"
    echo "Please resolve all merge conflicts before committing."
    exit 1
fi

echo "No conflict markers found. Clean!"
exit 0
