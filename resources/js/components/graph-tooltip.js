// resources/js/components/graph-tooltip.js
import { tip as d3Tip } from 'd3-v6-tip';

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

/**
 * Create rich tooltip for graph edges
 * Shows relationship type, weight, and properties
 */
export function createEdgeTooltip() {
    return d3Tip()
        .attr('class', 'graph-tooltip graph-tooltip-edge')
        .offset([-10, 0])
        .html(d => {
            const relationshipLabel = escapeHtml(d.label || d.type || 'Related');
            const weight = d.weight ? ` (weight: ${escapeHtml(d.weight)})` : '';

            let propertiesHtml = '';
            if (d.properties && Object.keys(d.properties).length > 0) {
                const filteredProps = Object.entries(d.properties)
                    .filter(([k]) => !['id', 'created_at', 'updated_at'].includes(k))
                    .slice(0, 3);

                if (filteredProps.length > 0) {
                    propertiesHtml = `
                        <div class="tooltip-properties">
                            ${filteredProps.map(([k, v]) => `<div>${escapeHtml(k)}: ${escapeHtml(v)}</div>`).join('')}
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
        });
}

/**
 * Create rich tooltip for graph nodes
 * Shows node type, title/name, and summary
 */
export function createNodeTooltip() {
    return d3Tip()
        .attr('class', 'graph-tooltip graph-tooltip-node')
        .offset([-10, 0])
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
        });
}
