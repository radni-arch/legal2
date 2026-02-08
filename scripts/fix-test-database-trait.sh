##!/bin/bash
#
## Script to replace RefreshDatabase with UsesTestDatabase in all test files
## This ensures tests use transactions instead of refreshing the entire database
#
#set -e
#
#echo "Fixing test database trait usage..."
#echo "Replacing RefreshDatabase with UsesTestDatabase"
#echo ""
#
## Counter for files modified
#modified_count=0
#
## Find all PHP test files that use RefreshDatabase
#while IFS= read -r file; do
#    if [ -f "$file" ]; then
#        echo "Processing: $file"
#
#        # Create a backup
#        cp "$file" "$file.bak"
#
#        # Replace the import statement
#        sed -i 's/use Illuminate\\Foundation\\Testing\\RefreshDatabase;/use Tests\\UsesTestDatabase;/g' "$file"
#
#        # Replace the trait usage inside the class (with proper indentation handling)
#        sed -i 's/use RefreshDatabase;/use UsesTestDatabase;/g' "$file"
#
#        # Check if file was actually modified
#        if ! cmp -s "$file" "$file.bak"; then
#            ((modified_count++))
#            rm "$file.bak"
#        else
#            # Restore from backup if no changes
#            mv "$file.bak" "$file"
#        fi
#    fi
#done < <(grep -rl "use RefreshDatabase;" tests/)
#
#echo ""
#echo "✓ Modified $modified_count test files"
#echo "✓ All tests now use UsesTestDatabase (DatabaseTransactions)"
#echo "✓ Database structure and data will be preserved between tests"
