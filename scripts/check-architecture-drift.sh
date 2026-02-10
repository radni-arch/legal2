#!/bin/bash
# Architecture Drift Check Script (SOT-017)
#
# Runs automated checks ensuring code topology matches documented architecture.
# Designed for CI integration - exits with non-zero on drift detection.
#
# Usage:
#   ./scripts/check-architecture-drift.sh
#
# Checks performed:
#   1. Route topology matches expected endpoints
#   2. Job/event/listener classes exist
#   3. No deprecated classes remain
#   4. Config namespaces are consistent
#   5. No merge conflict markers

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
DRIFT_ERRORS=0

echo "=== Architecture Drift Check ==="
echo "Project root: $PROJECT_ROOT"
echo ""

# ─── Check 1: No merge conflict markers ───────────────────────
echo "--- Check 1: Merge conflict markers ---"
CONFLICT_FILES=$(grep -rl '<<<<<<< \|======= \|>>>>>>> ' \
    --include='*.php' --include='*.blade.php' --include='*.js' --include='*.json' \
    "$PROJECT_ROOT/app" "$PROJECT_ROOT/config" "$PROJECT_ROOT/routes" "$PROJECT_ROOT/resources" \
    2>/dev/null || true)

if [ -n "$CONFLICT_FILES" ]; then
    echo "FAIL: Merge conflict markers found in:"
    echo "$CONFLICT_FILES"
    DRIFT_ERRORS=$((DRIFT_ERRORS + 1))
else
    echo "PASS: No conflict markers"
fi

# ─── Check 2: Critical files exist ────────────────────────────
echo ""
echo "--- Check 2: Critical files exist ---"
CRITICAL_FILES=(
    "app/Services/Ingest/IngestOrchestrator.php"
    "app/Jobs/Ingest/ProcessIngestRunJob.php"
    "app/Models/IngestRun.php"
    "app/Models/IngestStepLog.php"
    "app/Events/CaseDocumentIngested.php"
    "app/Events/DocumentAnalysisCompleted.php"
    "app/Listeners/TriggerCaseLevelAnalysis.php"
    "app/Jobs/Analysis/RunCaseLevelAnalysisJob.php"
    "app/Services/Analysis/DocumentAnalysisPipeline.php"
    "app/Services/Analysis/CaseLevel/DocumentIdentityBuilder.php"
    "app/Services/Analysis/Analyzers/DateContextExtractor.php"
    "app/Services/Analysis/Analyzers/CaseReferenceExtractor.php"
    "app/Contracts/LegalArtillery/EscalationSuggesterInterface.php"
    "app/Services/LegalArtillery/EscalationLadderSuggester.php"
    "config/ocr.php"
    "config/services.php"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ ! -f "$PROJECT_ROOT/$file" ]; then
        echo "FAIL: Missing critical file: $file"
        DRIFT_ERRORS=$((DRIFT_ERRORS + 1))
    fi
done
echo "Checked ${#CRITICAL_FILES[@]} critical files"

# ─── Check 3: Deprecated files are gone ───────────────────────
echo ""
echo "--- Check 3: Deprecated files removed ---"
DEPRECATED_FILES=(
    "app/Services/LegalArtillery/RecursiveDocumentWriter.php"
    "app/Services/LegalArtillery/IterativeRefiner.php"
    "app/Services/LegalArtillery/LegacyDevastatingArgumentBuilder.php"
    "app/Services/EscalationLadderSuggester.php"
    "app/Services/EscalationHierarchySuggester.php"
)

for file in "${DEPRECATED_FILES[@]}"; do
    if [ -f "$PROJECT_ROOT/$file" ]; then
        echo "FAIL: Deprecated file still exists: $file"
        DRIFT_ERRORS=$((DRIFT_ERRORS + 1))
    fi
done
echo "Verified ${#DEPRECATED_FILES[@]} deprecated files removed"

# ─── Check 4: OCR config uses canonical namespace ─────────────
echo ""
echo "--- Check 4: OCR config namespace consistency ---"
VIZRA_OCR_REFS=$(grep -r "vizra-adk\.ocr\." \
    --include='*.php' \
    "$PROJECT_ROOT/app" \
    2>/dev/null || true)

if [ -n "$VIZRA_OCR_REFS" ]; then
    echo "FAIL: Found old vizra-adk.ocr.* config references:"
    echo "$VIZRA_OCR_REFS"
    DRIFT_ERRORS=$((DRIFT_ERRORS + 1))
else
    echo "PASS: All OCR config uses canonical ocr.* namespace"
fi

# ─── Check 5: No duplicate DI bindings ────────────────────────
echo ""
echo "--- Check 5: DI binding consistency ---"
APP_PROVIDER="$PROJECT_ROOT/app/Providers/AppServiceProvider.php"
if grep -q "LegalArtilleryOrchestrator" "$APP_PROVIDER" 2>/dev/null; then
    echo "FAIL: LegalArtilleryOrchestrator found in AppServiceProvider (should only be in LegalArtilleryServiceProvider)"
    DRIFT_ERRORS=$((DRIFT_ERRORS + 1))
else
    echo "PASS: No duplicate DI bindings"
fi

# ─── Check 6: Run PHPUnit drift tests ─────────────────────────
echo ""
echo "--- Check 6: PHPUnit architecture drift tests ---"
if php "$PROJECT_ROOT/artisan" test --filter=ArchitectureDriftCheckTest 2>&1 | tail -5; then
    echo "PASS: Architecture drift tests"
else
    echo "FAIL: Architecture drift tests failed"
    DRIFT_ERRORS=$((DRIFT_ERRORS + 1))
fi

# ─── Summary ──────────────────────────────────────────────────
echo ""
echo "=== Summary ==="
if [ $DRIFT_ERRORS -eq 0 ]; then
    echo "All drift checks PASSED"
    exit 0
else
    echo "DRIFT DETECTED: $DRIFT_ERRORS check(s) failed"
    exit 1
fi
