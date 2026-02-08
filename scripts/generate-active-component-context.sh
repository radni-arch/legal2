#!/usr/bin/env bash
#######################################
# Active Component Context Generator
#######################################
# Generates a compact context snippet for the currently active TDD component.
# Selects: in_progress first, else top todo.
#
# Usage: ./scripts/generate-active-component-context.sh
#
# Output: test-results/context/active-component.md (1-2KB max)
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
QUEUE_FILE="${PROJECT_DIR}/test-results/tdd-test-queue.json"
OUTPUT_DIR="${PROJECT_DIR}/test-results/context"
OUTPUT_FILE="${OUTPUT_DIR}/active-component.md"

#######################################
# Check prerequisites
#######################################
if [[ ! -f "$QUEUE_FILE" ]]; then
    echo "Queue not found: $QUEUE_FILE" >&2
    exit 1
fi

if ! command -v jq &>/dev/null; then
    echo "Error: jq is required" >&2
    exit 1
fi

# Ensure output directory exists
mkdir -p "$OUTPUT_DIR"

#######################################
# Select active component
# Priority: in_progress first, then todo
#######################################
ACTIVE=$(jq -r '
  .components
  | (map(select(.status == "in_progress")) | first) //
    (map(select(.status == "todo")) | first) //
    null
' "$QUEUE_FILE" 2>/dev/null)

if [[ "$ACTIVE" == "null" || -z "$ACTIVE" ]]; then
    # No active component - write minimal file
    cat > "$OUTPUT_FILE" << 'EOF'
# Active Component

No active component. Queue is complete or empty.

Run `/tdd-multi-component-autoloop` to select next component.
EOF
    echo "$OUTPUT_FILE"
    exit 0
fi

#######################################
# Extract component fields
#######################################
ID=$(echo "$ACTIVE" | jq -r '.id // "unknown"')
STATUS=$(echo "$ACTIVE" | jq -r '.status // "unknown"')
SPRINT=$(echo "$ACTIVE" | jq -r '.sprint // "?"')
DOMAIN=$(echo "$ACTIVE" | jq -r '.domain // "unknown"')
TYPE=$(echo "$ACTIVE" | jq -r '.type // "unknown"')
TEST_CLASS=$(echo "$ACTIVE" | jq -r '.test_class // "unknown"')
TEST_PATH=$(echo "$ACTIVE" | jq -r '.test_path // "unknown"')
ITERATIONS=$(echo "$ACTIVE" | jq -r '.iterations // 0')
RELATED=$(echo "$ACTIVE" | jq -r '.related_tests // [] | join(", ")')

#######################################
# Infer implementation paths
#######################################
IMPL_PATHS=""

# Livewire component
if [[ "$ID" == livewire:* || "$TYPE" == "feature_livewire" ]]; then
    # Extract component name from test class (remove "Test" suffix)
    COMPONENT_NAME="${TEST_CLASS%Test}"
    IMPL_PATHS="- \`app/Livewire/${COMPONENT_NAME}.php\`
- \`resources/views/livewire/$(echo "$COMPONENT_NAME" | sed 's/\([A-Z]\)/-\L\1/g' | sed 's/^-//').blade.php\`"
fi

# Unit service test
if [[ "$TEST_PATH" == *"Unit/Services/"* ]]; then
    SERVICE_PATH=$(echo "$TEST_PATH" | sed 's|tests/Unit/|app/|' | sed 's|Test\.php|.php|')
    IMPL_PATHS="- \`${SERVICE_PATH}\`"
fi

# Feature API test
if [[ "$TEST_PATH" == *"Feature/Api/"* || "$TEST_PATH" == *"Feature/API/"* ]]; then
    IMPL_PATHS="- Check \`routes/api.php\` for endpoint
- Controller in \`app/Http/Controllers/Api/\`"
fi

#######################################
# Generate markdown snippet
#######################################
cat > "$OUTPUT_FILE" << EOF
# Active Component

**${ID}** [${STATUS}]

## Details

| Field | Value |
|-------|-------|
| Sprint | ${SPRINT} |
| Domain | ${DOMAIN} |
| Type | ${TYPE} |
| Iterations | ${ITERATIONS} |

## Test

\`\`\`bash
./scripts/run-focused-tests.sh ${TEST_CLASS}
\`\`\`

**Path:** \`${TEST_PATH}\`

EOF

# Add implementation paths if inferred
if [[ -n "$IMPL_PATHS" ]]; then
    cat >> "$OUTPUT_FILE" << EOF
## Likely Implementation

${IMPL_PATHS}

EOF
fi

# Add related tests if any
if [[ -n "$RELATED" && "$RELATED" != "" ]]; then
    cat >> "$OUTPUT_FILE" << EOF
## Related Tests

${RELATED}

EOF
fi

# Add quick actions
cat >> "$OUTPUT_FILE" << EOF
## Quick Actions

- Run test: \`./scripts/run-focused-tests.sh ${TEST_CLASS}\`
- Mark done: Update \`test-results/tdd-test-queue.json\` status to "done"
- Full TDD: \`/tdd-multi-component-autoloop\`
EOF

echo "$OUTPUT_FILE"
