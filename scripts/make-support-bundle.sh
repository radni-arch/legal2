#!/usr/bin/env bash
#######################################
# Support Bundle Generator
#
# Creates a debug bundle for escalations containing:
# - Setup logs
# - Iteration logs + metrics
# - Queue snapshot
# - Session notes
# - Failure briefs
# - Matrix output
# - Environment info
#
# Usage:
#   ./scripts/make-support-bundle.sh [reason]
#
# Example:
#   ./scripts/make-support-bundle.sh "repeated failures in VectorStoreManagerTest"
#
# Output:
#   test-results/support-bundles/bundle-<timestamp>.zip
#######################################

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Configuration
BUNDLES_DIR="${ROOT_DIR}/test-results/support-bundles"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BUNDLE_NAME="bundle-${TIMESTAMP}"
BUNDLE_DIR="${BUNDLES_DIR}/${BUNDLE_NAME}"
BUNDLE_ZIP="${BUNDLES_DIR}/${BUNDLE_NAME}.zip"

# Reason for bundle (optional)
REASON="${1:-manual}"

# Colors
CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

#######################################
# Create bundle directory structure
#######################################
echo -e "${CYAN}Creating support bundle: ${BUNDLE_NAME}${NC}"
echo ""

mkdir -p "$BUNDLE_DIR"/{logs,metrics,failures,queue,session,matrix,env}

#######################################
# Collect setup logs
#######################################
echo -n "  Collecting setup logs... "
if [[ -f "/tmp/setup-all-hook.log" ]]; then
    cp "/tmp/setup-all-hook.log" "$BUNDLE_DIR/logs/" 2>/dev/null || true
fi
if [[ -f "/tmp/session-start-hook.log" ]]; then
    cp "/tmp/session-start-hook.log" "$BUNDLE_DIR/logs/" 2>/dev/null || true
fi
# Also grab any setup status
if [[ -f "${ROOT_DIR}/test-results/setup-status.json" ]]; then
    cp "${ROOT_DIR}/test-results/setup-status.json" "$BUNDLE_DIR/logs/" 2>/dev/null || true
fi
echo "done"

#######################################
# Collect iteration logs
#######################################
echo -n "  Collecting iteration logs... "
if [[ -d "${ROOT_DIR}/test-results/logs" ]]; then
    cp -r "${ROOT_DIR}/test-results/logs/"* "$BUNDLE_DIR/logs/" 2>/dev/null || true
fi
echo "done"

#######################################
# Collect metrics
#######################################
echo -n "  Collecting metrics... "
if [[ -d "${ROOT_DIR}/test-results/metrics" ]]; then
    cp "${ROOT_DIR}/test-results/metrics/"*.jsonl "$BUNDLE_DIR/metrics/" 2>/dev/null || true
fi
# Also include last run metadata
if [[ -f "${ROOT_DIR}/test-results/.last-run.json" ]]; then
    cp "${ROOT_DIR}/test-results/.last-run.json" "$BUNDLE_DIR/metrics/" 2>/dev/null || true
fi
echo "done"

#######################################
# Collect queue snapshot
#######################################
echo -n "  Collecting queue snapshot... "
if [[ -f "${ROOT_DIR}/test-results/tdd-test-queue.json" ]]; then
    cp "${ROOT_DIR}/test-results/tdd-test-queue.json" "$BUNDLE_DIR/queue/" 2>/dev/null || true
fi
# Generate queue summary
if [[ -f "${ROOT_DIR}/test-results/tdd-test-queue.json" ]]; then
    jq -r '
        .components | to_entries |
        group_by(.value.status) |
        map({status: .[0].value.status, count: length}) |
        .[] | "\(.status): \(.count)"
    ' "${ROOT_DIR}/test-results/tdd-test-queue.json" > "$BUNDLE_DIR/queue/queue-summary.txt" 2>/dev/null || true
fi
echo "done"

#######################################
# Collect session notes
#######################################
echo -n "  Collecting session notes... "
if [[ -f "${ROOT_DIR}/.claude/session-state.md" ]]; then
    cp "${ROOT_DIR}/.claude/session-state.md" "$BUNDLE_DIR/session/" 2>/dev/null || true
fi
if [[ -f "${ROOT_DIR}/documentation/session-notes.md" ]]; then
    cp "${ROOT_DIR}/documentation/session-notes.md" "$BUNDLE_DIR/session/" 2>/dev/null || true
fi
echo "done"

#######################################
# Collect failure briefs
#######################################
echo -n "  Collecting failure briefs... "
if [[ -d "${ROOT_DIR}/test-results/failures" ]]; then
    cp "${ROOT_DIR}/test-results/failures/"*.md "$BUNDLE_DIR/failures/" 2>/dev/null || true
fi
echo "done"

#######################################
# Run and collect matrix output
#######################################
echo -n "  Running matrix check... "
if [[ -x "${ROOT_DIR}/scripts/check-matrix.sh" ]]; then
    "${ROOT_DIR}/scripts/check-matrix.sh" > "$BUNDLE_DIR/matrix/matrix-output.txt" 2>&1 || true
fi
echo "done"

#######################################
# Collect environment info
#######################################
echo -n "  Collecting environment info... "

# PHP version
php -v > "$BUNDLE_DIR/env/php-version.txt" 2>&1 || echo "PHP not available" > "$BUNDLE_DIR/env/php-version.txt"

# Composer packages (just versions)
if [[ -f "${ROOT_DIR}/composer.lock" ]]; then
    jq -r '.packages[] | "\(.name): \(.version)"' "${ROOT_DIR}/composer.lock" > "$BUNDLE_DIR/env/composer-packages.txt" 2>/dev/null || true
fi

# Node version
node -v > "$BUNDLE_DIR/env/node-version.txt" 2>&1 || echo "Node not available" > "$BUNDLE_DIR/env/node-version.txt"

# Git info
git log --oneline -10 > "$BUNDLE_DIR/env/git-log.txt" 2>&1 || true
git status > "$BUNDLE_DIR/env/git-status.txt" 2>&1 || true
git branch -a > "$BUNDLE_DIR/env/git-branches.txt" 2>&1 || true

# System info
uname -a > "$BUNDLE_DIR/env/system-info.txt" 2>&1 || true

echo "done"

#######################################
# Collect alert thresholds config
#######################################
echo -n "  Collecting config files... "
if [[ -f "${ROOT_DIR}/test-results/alert-thresholds.json" ]]; then
    cp "${ROOT_DIR}/test-results/alert-thresholds.json" "$BUNDLE_DIR/" 2>/dev/null || true
fi
if [[ -f "${ROOT_DIR}/test-results/flaky-tests.json" ]]; then
    cp "${ROOT_DIR}/test-results/flaky-tests.json" "$BUNDLE_DIR/" 2>/dev/null || true
fi
if [[ -f "${ROOT_DIR}/test-results/context-budget.json" ]]; then
    cp "${ROOT_DIR}/test-results/context-budget.json" "$BUNDLE_DIR/" 2>/dev/null || true
fi
echo "done"

#######################################
# Create bundle manifest
#######################################
echo -n "  Creating manifest... "
cat > "$BUNDLE_DIR/MANIFEST.md" << EOF
# Support Bundle: ${BUNDLE_NAME}

**Generated:** $(date -Iseconds)
**Reason:** ${REASON}
**Branch:** $(git branch --show-current 2>/dev/null || echo "unknown")
**Commit:** $(git rev-parse --short HEAD 2>/dev/null || echo "unknown")

## Contents

| Directory | Description |
|-----------|-------------|
| \`logs/\` | Setup logs, iteration logs |
| \`metrics/\` | JSONL metrics, last run metadata |
| \`queue/\` | TDD queue snapshot and summary |
| \`session/\` | Session state and notes |
| \`failures/\` | Failure briefs and RCA drafts |
| \`matrix/\` | Matrix check output |
| \`env/\` | Environment info (PHP, Node, Git) |

## Files

\`\`\`
$(find "$BUNDLE_DIR" -type f | sed "s|$BUNDLE_DIR/||" | sort)
\`\`\`

## Quick Analysis

### Queue Status
\`\`\`
$(cat "$BUNDLE_DIR/queue/queue-summary.txt" 2>/dev/null || echo "No queue data")
\`\`\`

### Recent Commits
\`\`\`
$(head -5 "$BUNDLE_DIR/env/git-log.txt" 2>/dev/null || echo "No git data")
\`\`\`
EOF
echo "done"

#######################################
# Create ZIP archive
#######################################
echo -n "  Creating ZIP archive... "
(cd "$BUNDLES_DIR" && zip -rq "${BUNDLE_NAME}.zip" "${BUNDLE_NAME}")
echo "done"

#######################################
# Cleanup temporary directory
#######################################
rm -rf "$BUNDLE_DIR"

#######################################
# Output result
#######################################
echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  SUPPORT BUNDLE CREATED${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "  ${CYAN}Path:${NC} ${BUNDLE_ZIP}"
echo -e "  ${CYAN}Size:${NC} $(du -h "$BUNDLE_ZIP" | cut -f1)"
echo ""
echo -e "  ${YELLOW}Share this bundle for debugging assistance.${NC}"
echo ""

# Output just the path for scripting
echo "$BUNDLE_ZIP"
