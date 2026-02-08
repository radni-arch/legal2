#!/bin/bash

# Simple link checker for documentation
# Finds all markdown files and extracts links

DOCS_DIR="/home/user/ai-legal-war-machine/docs"
ROOT_DIR="/home/user/ai-legal-war-machine"

echo "Checking for common broken link patterns..."
echo ""

# Pattern 1: Links to files that were moved from root to docs/
echo "=== Files potentially moved from root ==="
grep -r "](../HARDENING" "$DOCS_DIR" 2>/dev/null || echo "None found"
grep -r "](HARDENING" "$DOCS_DIR" 2>/dev/null || echo "None found"
grep -r "](../INTENSIVE" "$DOCS_DIR" 2>/dev/null || echo "None found"
grep -r "](../COMPLETION" "$DOCS_DIR" 2>/dev/null || echo "None found"
grep -r "](../DOCUMENTATION_CONSOLIDATION" "$DOCS_DIR" 2>/dev/null || echo "None found"
grep -r "](../SESSION_COMPLETION" "$DOCS_DIR" 2>/dev/null || echo "None found"
grep -r "](../PROGRESS_REANALYSIS" "$DOCS_DIR" 2>/dev/null || echo "None found"

echo ""
echo "=== Checking root README and CLAUDE ==="
grep -o '\[.*\](.*\.md)' "$ROOT_DIR/README.md" 2>/dev/null | head -20 || echo "No markdown links in README"
grep -o '\[.*\](.*\.md)' "$ROOT_DIR/CLAUDE.md" 2>/dev/null | head -20 || echo "No markdown links in CLAUDE"

echo ""
echo "Link check complete!"
