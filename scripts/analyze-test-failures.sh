#!/bin/bash

# ============================================
# Test Failure Analyzer and Task Generator
# ============================================
# This script analyzes test logs, categorizes failures,
# and generates structured tasks with skill recommendations
# ============================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

LOGS_DIR="./storage/logs/tests"
OUTPUT_FILE="./FAILING_TESTS_TASKS.md"

echo ""
echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║        Test Failure Analyzer & Task Generator             ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

if [ ! -d "$LOGS_DIR" ]; then
    echo -e "${RED}Error: Test logs directory not found: $LOGS_DIR${NC}"
    echo -e "${YELLOW}Have you run tests yet? Use: ./scripts/run-all-tests-parallel.sh${NC}"
    exit 1
fi

# Initialize counters
declare -A failure_categories
declare -A failure_details
total_failures=0

# Analyze function
analyze_log() {
    local log_file=$1
    local suite_name=$2

    echo -e "${BLUE}Analyzing ${suite_name}...${NC}"

    # Extract FAILED tests with details
    while IFS= read -r line; do
        if [[ "$line" =~ FAILED.*Tests\\ ]]; then
            # Extract test class and error type
            test_class=$(echo "$line" | grep -oP 'Tests\\[^>]*' | head -1)
            error_type=$(echo "$line" | grep -oP '\[\d+;1m \K[^[]+' | tail -1 | xargs)

            if [ -n "$test_class" ]; then
                category="$error_type"
                [ -z "$category" ] && category="Unknown Error"

                # Increment category counter
                failure_categories[$category]=$((${failure_categories[$category]:-0} + 1))

                # Store details
                if [ -z "${failure_details[$category]}" ]; then
                    failure_details[$category]="$test_class"
                else
                    failure_details[$category]="${failure_details[$category]}|$test_class"
                fi

                total_failures=$((total_failures + 1))
            fi
        fi
    done < "$log_file"
}

# Analyze all test logs
for log_file in "$LOGS_DIR"/*.log; do
    if [ -f "$log_file" ]; then
        suite_name=$(basename "$log_file" .log)
        analyze_log "$log_file" "$suite_name"
    fi
done

echo ""
echo -e "${CYAN}Analysis complete. Found ${total_failures} failures across ${#failure_categories[@]} categories${NC}"
echo ""

# Generate tasks document
cat > "$OUTPUT_FILE" << 'EOF'
# Failing Tests - Categorized Tasks

> **Auto-generated** by test failure analyzer
> **Generated**: $(date '+%Y-%m-%d %H:%M:%S')

## Summary

Total Failures: TOTAL_FAILURES_PLACEHOLDER
Failure Categories: CATEGORIES_COUNT_PLACEHOLDER

---

## Task List

EOF

# Replace placeholders
sed -i "s/TOTAL_FAILURES_PLACEHOLDER/$total_failures/g" "$OUTPUT_FILE"
sed -i "s/CATEGORIES_COUNT_PLACEHOLDER/${#failure_categories[@]}/g" "$OUTPUT_FILE"

# Function to recommend skill based on error pattern
recommend_skill() {
    local error_type=$1
    local skill=""
    local description=""

    case "$error_type" in
        *QueryException*|*PDOException*|*"Permission denied"*)
            skill="systematic-debugging"
            description="Database/permission issues require systematic debugging"
            ;;
        *"Method signature"*|*"must be compatible"*|*"Declaration"*)
            skill="systematic-debugging"
            description="Interface/signature mismatches need careful analysis"
            ;;
        *"Class not found"*|*"Undefined"*|*"does not exist"*)
            skill="systematic-debugging"
            description="Missing dependencies or autoload issues"
            ;;
        *Timeout*|*"race condition"*|*"timing"*)
            skill="condition-based-waiting"
            description="Timing/race condition issues - replace timeouts with event-based waiting"
            ;;
        *GraphQL*|*"endpoint is not configured"*)
            skill=""
            description="Configuration issue - add GraphQL config to .env.testing or disable"
            ;;
        *"deprecated"*|*"PHPUnit 12"*|*"doc-comment"*)
            skill=""
            description="Convert doc-comment annotations to PHP attributes"
            ;;
        *)
            skill="systematic-debugging"
            description="General test failure - investigate root cause"
            ;;
    esac

    echo "$skill|$description"
}

# Generate tasks for each category
task_number=1
for category in "${!failure_categories[@]}"; do
    count=${failure_categories[$category]}

    # Get skill recommendation
    IFS='|' read -r skill description <<< "$(recommend_skill "$category")"

    # Get sample tests (up to 5)
    sample_tests=$(echo "${failure_details[$category]}" | tr '|' '\n' | head -5)
    test_count=$(echo "${failure_details[$category]}" | tr '|' '\n' | wc -l)

    # Determine priority
    priority="HIGH"
    [ $count -lt 10 ] && priority="MEDIUM"
    [ $count -lt 3 ] && priority="LOW"

    # Write task to file
    cat >> "$OUTPUT_FILE" << TASK_EOF

### Task ${task_number}: Fix ${category} Failures

**Priority**: ${priority}
**Affected Tests**: ${count}
**Error Category**: ${category}

**Description**: ${description}

**Recommended Skill**: ${skill:-"None - manual fix"}

**Sample Failing Tests** (showing ${test_count} total):
\`\`\`
$(echo "$sample_tests")
\`\`\`

**Action Items**:
TASK_EOF

    # Add specific action items based on error type
    case "$category" in
        *QueryException*|*PDOException*)
            cat >> "$OUTPUT_FILE" << 'ACTION_EOF'
1. Verify PostgreSQL is running and accessible
2. Check database permissions and ownership
3. Verify migrations have run successfully
4. Check test database configuration in phpunit.xml
5. Investigate specific SQL queries causing failures
ACTION_EOF
            ;;
        *"Permission denied"*)
            cat >> "$OUTPUT_FILE" << 'ACTION_EOF'
1. Check file/directory ownership and permissions
2. Verify process user has necessary access rights
3. Review recent permission changes
4. Update setup scripts to fix permissions
ACTION_EOF
            ;;
        *"must be compatible"*|*"Declaration"*)
            cat >> "$OUTPUT_FILE" << 'ACTION_EOF'
1. Compare interface definition with implementation
2. Update method signatures to match interface
3. Find and update all callers with new signature
4. Run PHP syntax check to verify compatibility
ACTION_EOF
            ;;
        *GraphQL*)
            cat >> "$OUTPUT_FILE" << 'ACTION_EOF'
1. Add GraphQL configuration to phpunit.xml
2. Or disable GraphQL in test environment
3. Set GRAPHQL_ENABLED=false in .env.testing
ACTION_EOF
            ;;
        *"deprecated"*)
            cat >> "$OUTPUT_FILE" << 'ACTION_EOF'
1. Convert doc-comment @test annotations to #[Test] attributes
2. Update @dataProvider to #[DataProvider]
3. Update other metadata annotations to attributes
4. Can be automated with script or refactoring tool
ACTION_EOF
            ;;
        *)
            cat >> "$OUTPUT_FILE" << 'ACTION_EOF'
1. Read test file and understand what it verifies
2. Reproduce failure locally
3. Use systematic-debugging skill to find root cause
4. Implement fix with proper error handling
5. Verify fix doesn't break other tests
ACTION_EOF
            ;;
    esac

    # Add skill invocation command if applicable
    if [ -n "$skill" ]; then
        cat >> "$OUTPUT_FILE" << SKILL_EOF

**Skill Invocation**:
\`\`\`bash
# Use the $skill skill
claude> /skill $skill
\`\`\`
SKILL_EOF
    fi

    echo "" >> "$OUTPUT_FILE"
    echo "---" >> "$OUTPUT_FILE"

    task_number=$((task_number + 1))
done

# Add footer with commands
cat >> "$OUTPUT_FILE" << 'FOOTER_EOF'

## Recommended Execution Order

1. **Fix Infrastructure Issues First** (QueryException, PDOException, Permission errors)
   - These block the most tests
   - Fix database connectivity, permissions, configuration
   - Priority: CRITICAL

2. **Fix Interface/Signature Mismatches** (Compatibility errors)
   - These prevent code from loading
   - Usually affect entire test suites
   - Priority: HIGH

3. **Fix Configuration Issues** (GraphQL, missing config)
   - Quick wins with high impact
   - Simple configuration changes
   - Priority: MEDIUM

4. **Fix Deprecation Warnings** (PHPUnit metadata)
   - Can be done incrementally
   - Use automated tools for bulk updates
   - Priority: LOW

5. **Fix Individual Test Logic** (Remaining failures)
   - Business logic issues
   - Require case-by-case investigation
   - Priority: VARIES

## Skills Reference

Available skills for test fixing:

- **systematic-debugging**: Use for any bug, test failure, or unexpected behavior
- **condition-based-waiting**: Use for timing/race condition issues
- **test-driven-development**: Use when implementing features with tests
- **testing-anti-patterns**: Use when writing or changing tests
- **verification-before-completion**: Use before claiming tests are fixed

## Automation Commands

**Re-run analysis after fixes**:
```bash
./scripts/analyze-test-failures.sh
```

**Run specific test suite**:
```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
php artisan dusk
```

**Run all tests in parallel**:
```bash
./scripts/run-all-tests-parallel.sh
```

**Check test status**:
```bash
./scripts/check-test-status.sh
```

---

*Generated by: `scripts/analyze-test-failures.sh`*
FOOTER_EOF

echo ""
echo -e "${GREEN}✓ Task document generated: ${OUTPUT_FILE}${NC}"
echo ""
echo -e "${CYAN}Failure Categories:${NC}"
for category in "${!failure_categories[@]}"; do
    count=${failure_categories[$category]}
    echo -e "  ${YELLOW}${count}${NC} - ${category}"
done
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo -e "  1. Review tasks: ${YELLOW}cat ${OUTPUT_FILE}${NC}"
echo -e "  2. Prioritize high-impact failures first"
echo -e "  3. Use recommended skills for systematic fixes"
echo -e "  4. Re-run analysis after fixes to track progress"
echo ""
