/**
 * Citation Graph Visualization using D3.js
 *
 * Displays citation network as force-directed graph
 */
export function renderCitationGraph(elementId, data) {
    const d3 = window.d3;
    if (!d3) {
        console.error('D3.js not loaded — cannot render citation graph');
        return;
    }

    const container = document.getElementById(elementId);
    if (!container) return;

    // Clear previous graph
    container.innerHTML = '';

    // Dimensions
    const width = container.clientWidth;
    const height = 600;

    // Create SVG
    const svg = d3.select(`#${elementId}`)
        .append('svg')
        .attr('width', width)
        .attr('height', height)
        .attr('class', 'bg-gray-900 rounded');

    // Parse data
    const nodes = data.nodes || [];
    const edges = data.edges || [];
    const rootId = data.root_decision || null;

    // Create force simulation
    const simulation = d3.forceSimulation(nodes)
        .force('link', d3.forceLink(edges).id(d => d.id).distance(100))
        .force('charge', d3.forceManyBody().strength(-300))
        .force('center', d3.forceCenter(width / 2, height / 2))
        .force('collision', d3.forceCollide().radius(30));

    // Draw edges
    const link = svg.append('g')
        .selectAll('line')
        .data(edges)
        .join('line')
        .attr('stroke', '#4B5563')
        .attr('stroke-width', 2)
        .attr('stroke-opacity', 0.6)
        .attr('marker-end', 'url(#arrowhead)');

    // Define arrowhead marker
    svg.append('defs').append('marker')
        .attr('id', 'arrowhead')
        .attr('viewBox', '-0 -5 10 10')
        .attr('refX', 25)
        .attr('refY', 0)
        .attr('orient', 'auto')
        .attr('markerWidth', 6)
        .attr('markerHeight', 6)
        .append('svg:path')
        .attr('d', 'M 0,-5 L 10,0 L 0,5')
        .attr('fill', '#4B5563');

    // Draw nodes
    const node = svg.append('g')
        .selectAll('circle')
        .data(nodes)
        .join('circle')
        .attr('r', d => d.id === rootId ? 20 : 15)
        .attr('fill', d => {
            if (d.id === rootId) return '#A855F7'; // Purple for root
            if (d.type === 'citing') return '#3B82F6'; // Blue for citing
            if (d.type === 'cited') return '#10B981'; // Green for cited
            return '#6B7280'; // Gray default
        })
        .attr('stroke', '#fff')
        .attr('stroke-width', 2)
        .call(drag(simulation));

    // Add node labels
    const label = svg.append('g')
        .selectAll('text')
        .data(nodes)
        .join('text')
        .text(d => d.label || d.id)
        .attr('font-size', 10)
        .attr('fill', '#E5E7EB')
        .attr('text-anchor', 'middle')
        .attr('dy', -25);

    // Update positions on simulation tick
    simulation.on('tick', () => {
        link
            .attr('x1', d => d.source.x)
            .attr('y1', d => d.source.y)
            .attr('x2', d => d.target.x)
            .attr('y2', d => d.target.y);

        node
            .attr('cx', d => d.x)
            .attr('cy', d => d.y);

        label
            .attr('x', d => d.x)
            .attr('y', d => d.y);
    });

    // Drag behavior
    function drag(simulation) {
        function dragstarted(event) {
            if (!event.active) simulation.alphaTarget(0.3).restart();
            event.subject.fx = event.subject.x;
            event.subject.fy = event.subject.y;
        }

        function dragged(event) {
            event.subject.fx = event.subject.x;
            event.subject.fy = event.subject.y;
        }

        function dragended(event) {
            if (!event.active) simulation.alphaTarget(0);
            event.subject.fx = null;
            event.subject.fy = null;
        }

        return d3.drag()
            .on('start', dragstarted)
            .on('drag', dragged)
            .on('end', dragended);
    }
}

// Make globally available for Livewire
window.renderCitationGraph = renderCitationGraph;
