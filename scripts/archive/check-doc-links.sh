#!/bin/bash

# Script to check and report broken documentation links
# Usage: ./scripts/check-doc-links.sh

set -e

DOCS_DIR="/home/user/ai-legal-war-machine/docs"
ROOT_DIR="/home/user/ai-legal-war-machine"
REPORT_FILE="$ROOT_DIR/link-check-report.md"

echo "# Documentation Link Check Report" > "$REPORT_FILE"
echo "Generated: $(date)" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

broken_count=0
total_links=0

echo "Checking documentation links..."

# Find all markdown files
while IFS= read -r file; do
    # Extract markdown links from the file
    # Pattern: [text](link)
    while IFS= read -r link; do
        ((total_links++))

        # Skip external links (http/https)
        if [[ "$link" =~ ^https?:// ]]; then
            continue
        fi

        # Skip anchors only
        if [[ "$link" =~ ^# ]]; then
            continue
        fi

        # Remove anchor from link
        clean_link="${link%%#*}"

        # Determine absolute path
        file_dir=$(dirname "$file")

        if [[ "$clean_link" =~ ^\.\. ]]; then
            # Relative link going up
            target_path=$(realpath -m "$file_dir/$clean_link" 2>/dev/null || echo "INVALID")
        elif [[ "$clean_link" =~ ^\. ]]; then
            # Relative link in same dir
            target_path=$(realpath -m "$file_dir/$clean_link" 2>/dev/null || echo "INVALID")
        elif [[ "$clean_link" =~ ^/ ]]; then
            # Absolute link from root
            target_path="$ROOT_DIR$clean_link"
        else
            # Relative link without ./
            target_path=$(realpath -m "$file_dir/$clean_link" 2>/dev/null || echo "INVALID")
        fi

        # Check if target exists
        if [[ ! -e "$target_path" && ! -d "$target_path" ]]; then
            ((broken_count++))
            echo "## Broken Link #$broken_count" >> "$REPORT_FILE"
            echo "- **File**: $file" >> "$REPORT_FILE"
            echo "- **Link**: $link" >> "$REPORT_FILE"
            echo "- **Expected Path**: $target_path" >> "$REPORT_FILE"
            echo "" >> "$REPORT_FILE"
        fi

    done < <(grep -oP '(?<=\]\()[^)]+(?=\))' "$file" 2>/dev/null || true)

done < <(find "$DOCS_DIR" -name "*.md" -type f)

# Also check root README and CLAUDE
for root_file in "$ROOT_DIR/README.md" "$ROOT_DIR/CLAUDE.md"; do
    if [[ -f "$root_file" ]]; then
        while IFS= read -r link; do
            ((total_links++))

            if [[ "$link" =~ ^https?:// ]] || [[ "$link" =~ ^# ]]; then
                continue
            fi

            clean_link="${link%%#*}"
            file_dir=$(dirname "$root_file")

            if [[ "$clean_link" =~ ^\.\. ]] || [[ "$clean_link" =~ ^\. ]]; then
                target_path=$(realpath -m "$file_dir/$clean_link" 2>/dev/null || echo "INVALID")
            elif [[ "$clean_link" =~ ^/ ]]; then
                target_path="$ROOT_DIR$clean_link"
            else
                target_path=$(realpath -m "$file_dir/$clean_link" 2>/dev/null || echo "INVALID")
            fi

            if [[ ! -e "$target_path" && ! -d "$target_path" ]]; then
                ((broken_count++))
                echo "## Broken Link #$broken_count" >> "$REPORT_FILE"
                echo "- **File**: $root_file" >> "$REPORT_FILE"
                echo "- **Link**: $link" >> "$REPORT_FILE"
                echo "- **Expected Path**: $target_path" >> "$REPORT_FILE"
                echo "" >> "$REPORT_FILE"
            fi

        done < <(grep -oP '(?<=\]\()[^)]+(?=\))' "$root_file" 2>/dev/null || true)
    fi
done

echo "" >> "$REPORT_FILE"
echo "---" >> "$REPORT_FILE"
echo "## Summary" >> "$REPORT_FILE"
echo "- **Total Links Checked**: $total_links" >> "$REPORT_FILE"
echo "- **Broken Links Found**: $broken_count" >> "$REPORT_FILE"

if [ $broken_count -eq 0 ]; then
    echo "✅ **All links are valid!**" >> "$REPORT_FILE"
else
    echo "❌ **Found $broken_count broken link(s) that need to be fixed.**" >> "$REPORT_FILE"
fi

echo ""
echo "Link check complete!"
echo "- Total links: $total_links"
echo "- Broken links: $broken_count"
echo ""
echo "Full report: $REPORT_FILE"
