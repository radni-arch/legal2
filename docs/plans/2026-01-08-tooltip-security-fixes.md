# Tooltip Security & Quality Fixes Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix XSS vulnerability in graph tooltips and address code quality issues from self-review.

**Architecture:** Add HTML escaping utility, cleanup lifecycle methods, remove stale comments.

**Tech Stack:** JavaScript (ES6+), PHP 8.x, d3-v6-tip

---

## Task 1: Add HTML Escape Utility to graph-tooltip.js

**Files:**
- Modify: `resources/js/components/graph-tooltip.js`

**Step 1: Write the escape utility function**

Add at the top of the file after imports:

```javascript
/**
 * Escape HTML entities to prevent XSS attacks
 * @param {string} text - Raw text that may contain HTML
 * @returns {string} - Escaped safe text
 */
function escapeHtml(text) {
    if (text == null) return '';
    const str = String(text);
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
```

**Step 2: Apply escapeHtml to createNodeTooltip**

Update the html callback to escape all user-provided data:

```javascript
.html(d => {
    const nodeType = escapeHtml(d.type || d.labels?.[0] || 'Node');
    const title = d.title || d.name || d.label || d.id;
    const truncatedTitle = escapeHtml(title.length > 50 ? title.substring(0, 47) + '...' : title);

    let summaryHtml = '';
    if (d.summary || d.description) {
        const summary = d.summary || d.description;
        const truncatedSummary = escapeHtml(summary.length > 100 ? summary.substring(0, 97) + '...' : summary);
        summaryHtml = `<div class="tooltip-summary">${truncatedSummary}</div>`;
    }

    return `
        <div class="tooltip-content">
            <div class="tooltip-type">${nodeType}</div>
            <div class="tooltip-title">${truncatedTitle}</div>
            ${summaryHtml}
        </div>
    `;
})
```

**Step 3: Apply escapeHtml to createEdgeTooltip**

Update the html callback:

```javascript
.html(d => {
    const relationshipLabel = escapeHtml(d.label || d.type || 'Related');
    const weight = d.weight ? ` (weight: ${escapeHtml(String(d.weight))})` : '';

    let propertiesHtml = '';
    if (d.properties && Object.keys(d.properties).length > 0) {
        const filteredProps = Object.entries(d.properties)
            .filter(([k]) => !['id', 'created_at', 'updated_at'].includes(k))
            .slice(0, 3);

        if (filteredProps.length > 0) {
            propertiesHtml = `
                <div class="tooltip-properties">
                    ${filteredProps.map(([k, v]) => `<div>${escapeHtml(k)}: ${escapeHtml(String(v))}</div>`).join('')}
                </div>
            `;
        }
    }

    return `
        <div class="tooltip-content">
            <div class="tooltip-title">${relationshipLabel}${weight}</div>
            ${propertiesHtml}
        </div>
    `;
})
```

**Step 4: Verify build succeeds**

Run: `npm run build`
Expected: Build completes without errors

**Step 5: Commit**

```bash
git add resources/js/components/graph-tooltip.js
git commit -m "security: Add HTML escaping to graph tooltips to prevent XSS"
```

---

## Task 2: Add Tooltip Cleanup to ForceGraph.js

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Find or create destroy method**

Check if `destroy()` method exists, if not add one. Add tooltip cleanup:

```javascript
destroy() {
    // Clean up tooltips
    if (this.nodeTip) {
        this.nodeTip.destroy();
        this.nodeTip = null;
    }
    if (this.edgeTip) {
        this.edgeTip.destroy();
        this.edgeTip = null;
    }

    // Clean up simulation
    if (this.simulation) {
        this.simulation.stop();
        this.simulation = null;
    }

    // Clean up SVG
    if (this.svg) {
        this.svg.remove();
        this.svg = null;
    }
},
```

**Step 2: Verify build succeeds**

Run: `npm run build`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "fix: Add proper cleanup for tooltips and simulation on component destroy"
```

---

## Task 3: Remove Stale TODO Comment from GraphDatabaseService

**Files:**
- Modify: `app/Services/GraphDatabaseService.php:2179-2186`

**Step 1: Remove stale TODO comment**

Find and remove this outdated comment block:

```php
// BEFORE (around line 2179-2186):
    /**
     * Get date events for a decision
     *
     * TODO: Implement actual query to fetch date events from Neo4j
     *
     * @param  string  $decisionId  Court decision ID
     */
```

Replace with:

```php
// AFTER:
    /**
     * Get date events for a decision
     *
     * @param  string  $decisionId  Court decision ID
     * @return array<int, array{id: string, date: string, event_type: string, description: string}>
     */
```

**Step 2: Run PHP linting**

Run: `./vendor/bin/pint app/Services/GraphDatabaseService.php`
Expected: File formatted without errors

**Step 3: Commit**

```bash
git add app/Services/GraphDatabaseService.php
git commit -m "chore: Remove stale TODO comment and add return type documentation"
```

---

## Task 4: Fix Inconsistent Whitespace in GraphDatabaseService

**Files:**
- Modify: `app/Services/GraphDatabaseService.php:2101`

**Step 1: Fix inconsistent negation operator spacing**

Find line 2101:

```php
// BEFORE:
if (!$this->isAvailable()) {

// AFTER (consistent with rest of file):
if (! $this->isAvailable()) {
```

**Step 2: Run PHP linting**

Run: `./vendor/bin/pint app/Services/GraphDatabaseService.php`
Expected: File formatted without errors

**Step 3: Run tests**

Run: `php artisan test --filter=GraphDatabaseService`
Expected: All tests pass

**Step 4: Commit**

```bash
git add app/Services/GraphDatabaseService.php
git commit -m "style: Fix inconsistent whitespace in negation operators"
```

---

## Task 5: Final Verification

**Step 1: Run full build**

Run: `npm run build`
Expected: Build completes, no errors

**Step 2: Run related tests**

Run: `php artisan test --filter=GraphDatabaseService`
Expected: All tests pass

**Step 3: Final commit with all changes pushed**

```bash
git push -u origin claude/graph-enhancement-data-integrity-XqqqL
```

---

## Summary

| Task | Priority | Type | Est. Time |
|------|----------|------|-----------|
| 1. XSS escaping | P1 | Security | 5 min |
| 2. Tooltip cleanup | P2 | Bug fix | 3 min |
| 3. Remove stale TODO | P3 | Cleanup | 2 min |
| 4. Fix whitespace | P4 | Style | 1 min |
| 5. Verification | - | QA | 2 min |

**Total: 5 tasks, ~13 minutes**
