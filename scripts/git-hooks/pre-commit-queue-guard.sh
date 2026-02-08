#!/usr/bin/env bash
#######################################
# Pre-commit Queue Guard Hook
#######################################
# Validates TDD queue changes before commit.
# Blocks illegal structural changes, only allows status/iterations edits.
#
# Usage:
#   Called automatically by git pre-commit hook
#
# Override:
#   QUEUE_GUARD_BYPASS=1 git commit -m "message"
#   git commit --no-verify -m "message"  (skips all hooks)
#
# Installation:
#   ln -sf ../../scripts/git-hooks/pre-commit-queue-guard.sh .git/hooks/pre-commit
#   # Or add to existing pre-commit hook
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Find project root (handle both direct call and symlink from .git/hooks)
if [[ "$SCRIPT_DIR" == *".git/hooks"* ]]; then
    PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
else
    PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
fi

QUEUE_FILE="test-results/tdd-test-queue.json"
VALIDATOR="${PROJECT_DIR}/scripts/validate-test-queue.php"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m'

#######################################
# Check for bypass
#######################################
if [[ "${QUEUE_GUARD_BYPASS:-}" == "1" ]]; then
    echo -e "${YELLOW}⚠ QUEUE_GUARD_BYPASS=1 detected${NC}"
    echo -e "${YELLOW}⚠ Skipping queue validation (bypassed by user)${NC}"
    echo ""
    exit 0
fi

#######################################
# Check if queue file is being committed
#######################################
if ! git diff --cached --name-only | grep -q "^${QUEUE_FILE}$"; then
    # Queue file not in staged changes, nothing to check
    exit 0
fi

echo -e "${BLUE}═══ Queue Guard: Validating TDD queue changes ═══${NC}"
echo ""

#######################################
# Check if validator exists
#######################################
if [[ ! -f "$VALIDATOR" ]]; then
    echo -e "${YELLOW}⚠ Validator not found: ${VALIDATOR}${NC}"
    echo -e "${YELLOW}⚠ Skipping queue validation (validator missing)${NC}"
    exit 0
fi

#######################################
# Run validator with --diff-check
#######################################
if php "$VALIDATOR" --diff-check; then
    echo ""
    echo -e "${GREEN}✓ Queue changes validated successfully${NC}"
    exit 0
fi

#######################################
# Validation failed - block commit
#######################################
echo ""
echo -e "${RED}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${RED}║  COMMIT BLOCKED: Illegal queue changes detected            ║${NC}"
echo -e "${RED}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Allowed changes:${NC}"
echo "  • components[*].status (todo → in_progress → done)"
echo "  • components[*].iterations (can only increase)"
echo ""
echo -e "${YELLOW}Not allowed:${NC}"
echo "  • Changing immutable fields (id, sprint, domain, type, test_class, test_path)"
echo "  • Modifying stats, visual_groupings, sprint_guide, meta sections"
echo "  • Invalid status transitions (e.g., done → todo)"
echo ""
echo -e "${YELLOW}To fix:${NC}"
echo "  1. Review your changes: git diff --cached ${QUEUE_FILE}"
echo "  2. Unstage illegal changes: git checkout HEAD -- ${QUEUE_FILE}"
echo "  3. Re-apply only allowed changes"
echo ""
echo -e "${YELLOW}To bypass (use sparingly):${NC}"
echo "  QUEUE_GUARD_BYPASS=1 git commit -m \"message\""
echo ""
echo -e "${BLUE}For details, run:${NC}"
echo "  php scripts/validate-test-queue.php --diff-check"
echo ""

exit 1
