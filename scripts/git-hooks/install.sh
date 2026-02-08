#!/usr/bin/env bash
#######################################
# Git Hooks Installer
#######################################
# Installs project git hooks by creating symlinks.
#
# Usage: ./scripts/git-hooks/install.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
GIT_HOOKS_DIR="${PROJECT_DIR}/.git/hooks"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}═══ Installing Git Hooks ═══${NC}"
echo ""

# Ensure .git/hooks exists
mkdir -p "$GIT_HOOKS_DIR"

# Make hook scripts executable
chmod +x "${SCRIPT_DIR}/pre-commit" 2>/dev/null || true
chmod +x "${SCRIPT_DIR}/pre-commit-queue-guard.sh" 2>/dev/null || true

# Install pre-commit hook
if [[ -f "${GIT_HOOKS_DIR}/pre-commit" && ! -L "${GIT_HOOKS_DIR}/pre-commit" ]]; then
    echo -e "${YELLOW}⚠ Existing pre-commit hook found (not a symlink)${NC}"
    echo -e "${YELLOW}  Backing up to pre-commit.backup${NC}"
    mv "${GIT_HOOKS_DIR}/pre-commit" "${GIT_HOOKS_DIR}/pre-commit.backup"
fi

# Create symlink (relative path for portability)
ln -sf "../../scripts/git-hooks/pre-commit" "${GIT_HOOKS_DIR}/pre-commit"

echo -e "${GREEN}✓ Installed: pre-commit${NC}"
echo "  → Queue guard: validates TDD queue changes"
echo ""
echo -e "${BLUE}Hooks installed successfully!${NC}"
echo ""
echo "Override options:"
echo "  QUEUE_GUARD_BYPASS=1 git commit  # Skip queue validation"
echo "  git commit --no-verify           # Skip all hooks"
