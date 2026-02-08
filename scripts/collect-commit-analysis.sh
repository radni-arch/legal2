#!/bin/bash
# Script to iterate through commits and collect subagent-evidence-analyzer results
# Using git show instead of checkout to avoid local changes conflicts

set -e

REPO_DIR="/home/user/ai-legal-war-machine"
RESULTS_PATH=".claude/skills/subagent-evidence-analyzer/results"
OUTPUT_DIR="$REPO_DIR/collected-analysis-data"
COMMIT_LIST="$REPO_DIR/commitList.json"

# Create output directory
mkdir -p "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR/raw"

cd "$REPO_DIR"

# Extract commit hashes from JSON
COMMITS=$(cat "$COMMIT_LIST" | grep '"oid"' | sed 's/.*"oid": "\([^"]*\)".*/\1/')

echo "Found commits to process:"
echo "$COMMITS" | wc -l

# Initialize collection files
> "$OUTPUT_DIR/raw/exclusions_all.md"
> "$OUTPUT_DIR/raw/partial_exclusions_all.md"
> "$OUTPUT_DIR/raw/legal_provisions_all.md"
> "$OUTPUT_DIR/raw/summaries_all.md"
> "$OUTPUT_DIR/raw/analysis_reports_all.md"
> "$OUTPUT_DIR/raw/json_data_all.json"

echo "[" > "$OUTPUT_DIR/raw/json_data_all.json"
FIRST_JSON=true

# Counter for progress
TOTAL=$(echo "$COMMITS" | wc -l)
CURRENT=0
FOUND_COUNT=0

for COMMIT in $COMMITS; do
    CURRENT=$((CURRENT + 1))
    echo ""
    echo "[$CURRENT/$TOTAL] Processing commit: $COMMIT"

    # Check if results directory exists in this commit using git show
    if git show "$COMMIT:$RESULTS_PATH/" >/dev/null 2>&1; then
        echo "  Found results directory!"
        FOUND_COUNT=$((FOUND_COUNT + 1))

        # Get list of files in the results directory
        FILES=$(git show "$COMMIT:$RESULTS_PATH/" 2>/dev/null | grep -v "^tree " | grep -v "^$" || true)

        for FILENAME in $FILES; do
            echo "    Collecting: $FILENAME"

            case "$FILENAME" in
                "exclusions.md")
                    echo "" >> "$OUTPUT_DIR/raw/exclusions_all.md"
                    echo "<!-- COMMIT: $COMMIT -->" >> "$OUTPUT_DIR/raw/exclusions_all.md"
                    git show "$COMMIT:$RESULTS_PATH/$FILENAME" >> "$OUTPUT_DIR/raw/exclusions_all.md" 2>/dev/null || true
                    ;;
                "partial-exclusions.md")
                    echo "" >> "$OUTPUT_DIR/raw/partial_exclusions_all.md"
                    echo "<!-- COMMIT: $COMMIT -->" >> "$OUTPUT_DIR/raw/partial_exclusions_all.md"
                    git show "$COMMIT:$RESULTS_PATH/$FILENAME" >> "$OUTPUT_DIR/raw/partial_exclusions_all.md" 2>/dev/null || true
                    ;;
                "legal-provisions.md")
                    echo "" >> "$OUTPUT_DIR/raw/legal_provisions_all.md"
                    echo "<!-- COMMIT: $COMMIT -->" >> "$OUTPUT_DIR/raw/legal_provisions_all.md"
                    git show "$COMMIT:$RESULTS_PATH/$FILENAME" >> "$OUTPUT_DIR/raw/legal_provisions_all.md" 2>/dev/null || true
                    ;;
                "summary.md")
                    echo "" >> "$OUTPUT_DIR/raw/summaries_all.md"
                    echo "<!-- COMMIT: $COMMIT -->" >> "$OUTPUT_DIR/raw/summaries_all.md"
                    git show "$COMMIT:$RESULTS_PATH/$FILENAME" >> "$OUTPUT_DIR/raw/summaries_all.md" 2>/dev/null || true
                    ;;
                "ANALYSIS-REPORT.md")
                    echo "" >> "$OUTPUT_DIR/raw/analysis_reports_all.md"
                    echo "<!-- COMMIT: $COMMIT -->" >> "$OUTPUT_DIR/raw/analysis_reports_all.md"
                    git show "$COMMIT:$RESULTS_PATH/$FILENAME" >> "$OUTPUT_DIR/raw/analysis_reports_all.md" 2>/dev/null || true
                    ;;
                *.json)
                    if [ "$FIRST_JSON" = true ]; then
                        FIRST_JSON=false
                    else
                        echo "," >> "$OUTPUT_DIR/raw/json_data_all.json"
                    fi
                    echo "{\"commit\": \"$COMMIT\", \"filename\": \"$FILENAME\", \"data\": " >> "$OUTPUT_DIR/raw/json_data_all.json"
                    git show "$COMMIT:$RESULTS_PATH/$FILENAME" >> "$OUTPUT_DIR/raw/json_data_all.json" 2>/dev/null || echo "{}"
                    echo "}" >> "$OUTPUT_DIR/raw/json_data_all.json"
                    ;;
            esac
        done
    else
        echo "  No results directory in this commit"
    fi
done

echo "]" >> "$OUTPUT_DIR/raw/json_data_all.json"

echo ""
echo "=== Collection Complete ==="
echo "Processed: $TOTAL commits"
echo "Found results in: $FOUND_COUNT commits"
echo "Output saved to: $OUTPUT_DIR/raw/"
ls -la "$OUTPUT_DIR/raw/"
