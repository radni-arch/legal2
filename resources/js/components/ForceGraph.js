// resources/js/components/ForceGraph.js
// D3 and graph-tooltip are loaded dynamically to reduce initial bundle size
// and avoid d3-v6-tip parse errors on pages that don't use the graph

/**
 * Module-level D3 reference - loaded on demand
 */
let d3 = null;

/**
 * ForceGraph - Interactive D3.js force-directed graph visualization
 *
 * Features: zoom, pan, drag, click-to-expand, filtering, search
 * D3.js is loaded on-demand when the component initializes.
 */
export default function ForceGraph() {
    return {
        // State
        nodes: [],
        edges: [],
        selectedNode: null,
        filters: {
            nodeTypes: [],
            activeTypes: [],
            relationshipTypes: ['CITES', 'CONTRADICTS', 'SUPPORTS', 'REFERENCES', 'SUPERSEDES', 'HAS_KEYWORD'],
            activeRelationships: ['CITES', 'CONTRADICTS', 'SUPPORTS', 'REFERENCES', 'SUPERSEDES', 'HAS_KEYWORD'],
        },
        searchQuery: '',
        searchResults: [],
        serverSearchResults: [],
        highlightedNodeId: null,
        expandedNodeIds: new Set(),
        pinnedNodes: [],
        isLoading: false,
        pendingUpdates: 0,
        lastUpdateTimestamp: null,
        d3Loaded: false,

        // Alert state (Phase 3: Contradiction Radar)
        alerts: [],
        nodeAlerts: {}, // Map of nodeId -> alert array

        // Metadata panel state
        metadataPanel: {
            isOpen: false,
            node: null,
            loading: false,
        },

        // D3 references
        svg: null,
        simulation: null,
        zoom: null,
        nodeTip: null,
        edgeTip: null,

        // Layout
        layout: 'force',

        // Config
        width: 0,
        height: 600,
        performanceMode: false, // Auto-enable for large graphs
        nodeLimit: 500,

        // Node colors by type
        nodeColors: {
            CourtDecisionDocument: '#3B82F6', // blue
            LawDocument: '#10B981',           // green
            Judge: '#8B5CF6',                 // purple
            Lawyer: '#F59E0B',                // amber
            LegalTopic: '#EC4899',            // pink
            LegalConcept: '#6366F1',          // indigo
            Article: '#14B8A6',               // teal
            Verdict: '#EF4444',               // red
            Evidence: '#84CC16',              // lime
            LegalArgument: '#F97316',         // orange
            DateEvent: '#06B6D4',             // cyan
            LegalDefinition: '#D946EF',       // fuchsia
        },

        // Edge colors by relationship type
        edgeColors: {
            CITES: '#60A5FA',           // blue
            CITED_BY: '#60A5FA',        // blue
            INVOLVES: '#A78BFA',        // purple
            ARGUED_BY: '#FBBF24',       // amber
            DECIDED_BY: '#8B5CF6',      // purple
            REFERENCES: '#A78BFA',      // purple
            CONTAINS: '#F472B6',        // pink
            SUPPORTS: '#4ADE80',        // green
            OPPOSES: '#F87171',         // red
            CONTRADICTS: '#F87171',     // red
            SUPERSEDES: '#FBBF24',      // yellow
            HAS_KEYWORD: '#94A3B8',     // gray
            OCCURRED_ON: '#22D3EE',     // cyan
        },

        /**
         * Calculate node radius based on citation count
         */
        getNodeRadius(node) {
            // Base radius
            const baseRadius = 8;

            // Scale by citation count (if available)
            const citationCount = node.properties?.citation_count || 0;
            const scaleFactor = Math.min(Math.log(citationCount + 1) * 2, 10);

            return baseRadius + scaleFactor;
        },

        /**
         * Get edge color by relationship type
         */
        getEdgeColor(edge) {
            return this.edgeColors[edge.type] || '#6B7280';
        },

        /**
         * Calculate node opacity based on superseded status
         */
        getNodeOpacity(node) {
            // Fade superseded laws
            if (node.properties?.is_superseded === true) {
                return 0.4;
            }
            // Fade old versions
            if (node.properties?.valid_until && new Date(node.properties.valid_until) < new Date()) {
                return 0.4;
            }
            return 1;
        },

        /**
         * Load D3.js dynamically to reduce initial bundle size
         */
        async loadD3() {
            if (window.d3) {
                d3 = window.d3;
                this.d3Loaded = true;
                return d3;
            }
            if (d3) {
                this.d3Loaded = true;
                return d3;
            }
            this.isLoading = true;
            try {
                const d3Module = await import('d3');
                d3 = d3Module;
                this.d3Loaded = true;
                return d3;
            } catch (error) {
                console.error('Failed to load D3.js:', error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async init() {
            // Load D3 first (code splitting)
            await this.loadD3();

            this.width = this.$refs.container.clientWidth;
            await this.initSvg();
            this.initZoom();
            this.initSimulation();
            this.initEchoListener();

            this.initKeyboardShortcuts();

            // Watch for data updates
            this.$watch('nodes', () => this.updateGraph());
            this.$watch('edges', () => this.updateGraph());

            // Livewire event listener for filtered expand results
            // Livewire 3 may wrap dispatch params in an array
            this.$wire.on('filtered-nodes-loaded', (rawData) => {
                const data = Array.isArray(rawData) ? rawData[0] : rawData;
                if (!data) {
                    this.isLoading = false;
                    return;
                }
                // Merge new nodes and edges
                const newNodes = data.nodes || [];
                const newEdges = data.edges || [];
                if (newNodes.length > 0) {
                    const existingIds = new Set(this.nodes.map(n => n.id));
                    newNodes.forEach(node => {
                        if (!existingIds.has(node.id)) {
                            this.nodes.push(node);
                        }
                    });
                }
                if (newEdges.length > 0) {
                    this.edges = [...this.edges, ...newEdges];
                }
                this.isLoading = false;
                this.extractNodeTypes();
                this.updateGraph();
            });

            // Listen for alerts updates (Phase 3: Contradiction Radar)
            this.$wire.on('alerts-updated', (rawData) => {
                const data = Array.isArray(rawData) ? rawData[0] : rawData;
                this.alerts = data?.alerts || [];
                this.updateNodeAlerts();
                this.updateGraph();
            });

            // Listen for session load events
            this.$wire.on('session-loaded', (rawData) => {
                const data = Array.isArray(rawData) ? rawData[0] : rawData;
                const session = data?.session || data;

                // Restore pinned nodes
                if (session.pinned_nodes && session.pinned_nodes.length > 0) {
                    this.pinnedNodes = session.pinned_nodes;
                }

                // Restore filter settings
                if (session.filter_settings) {
                    if (session.filter_settings.activeRelationships) {
                        this.filters.activeRelationships = session.filter_settings.activeRelationships;
                    }
                    if (session.filter_settings.activeTypes) {
                        this.filters.activeTypes = session.filter_settings.activeTypes;
                    }
                }

                // Restore alerts (Phase 3: Contradiction Radar)
                if (session.alerts && session.alerts.length > 0) {
                    this.alerts = session.alerts;
                    this.updateNodeAlerts();
                }

                this.updateGraph();
            });
        },

        async initSvg() {
            const container = this.$refs.container;
            container.innerHTML = '';

            this.svg = d3.select(container)
                .append('svg')
                .attr('width', this.width)
                .attr('height', this.height)
                .attr('class', 'bg-gray-900 rounded-lg');

            // Container group for zoom transforms
            this.graphGroup = this.svg.append('g').attr('class', 'graph-group');

            // Separate groups for edges and nodes (edges behind nodes)
            this.edgeGroup = this.graphGroup.append('g').attr('class', 'edges');
            this.nodeGroup = this.graphGroup.append('g').attr('class', 'nodes');
            this.labelGroup = this.graphGroup.append('g').attr('class', 'labels');

            // Define arrowhead marker and glow filter
            const defs = this.svg.append('defs');

            defs.append('marker')
                .attr('id', 'arrowhead')
                .attr('viewBox', '-0 -5 10 10')
                .attr('refX', 20)
                .attr('refY', 0)
                .attr('orient', 'auto')
                .attr('markerWidth', 6)
                .attr('markerHeight', 6)
                .append('path')
                .attr('d', 'M 0,-5 L 10,0 L 0,5')
                .attr('fill', '#4B5563');

            // Add glow filter for pinned nodes
            const filter = defs.append('filter')
                .attr('id', 'glow')
                .attr('x', '-50%')
                .attr('y', '-50%')
                .attr('width', '200%')
                .attr('height', '200%');

            filter.append('feGaussianBlur')
                .attr('in', 'SourceGraphic')
                .attr('stdDeviation', '3')
                .attr('result', 'blur');

            filter.append('feMerge')
                .selectAll('feMergeNode')
                .data(['blur', 'SourceGraphic'])
                .enter()
                .append('feMergeNode')
                .attr('in', d => d);

            // Initialize tooltips (dynamically imported to avoid loading d3-v6-tip on non-graph pages)
            const { createNodeTooltip, createEdgeTooltip } = await import('./graph-tooltip');
            this.nodeTip = createNodeTooltip();
            this.edgeTip = createEdgeTooltip();
            this.svg.call(this.nodeTip);
            this.svg.call(this.edgeTip);
        },

        initZoom() {
            this.zoom = d3.zoom()
                .scaleExtent([0.1, 4])
                .on('zoom', (event) => {
                    this.graphGroup.attr('transform', event.transform);
                });

            this.svg.call(this.zoom);
        },

        initSimulation() {
            this.simulation = d3.forceSimulation()
                .force('link', d3.forceLink().id(d => d.id).distance(100))
                .force('charge', d3.forceManyBody().strength(-300))
                .force('center', d3.forceCenter(this.width / 2, this.height / 2))
                .force('collision', d3.forceCollide().radius(35))
                .on('tick', () => this.tick());
        },

        initEchoListener() {
            if (typeof window.Echo === 'undefined') {
                console.warn('Laravel Echo not available');
                return;
            }

            window.Echo.channel('graph-updates')
                .listen('.graph.updated', (event) => {
                    this.pendingUpdates++;
                    this.lastUpdateTimestamp = event.timestamp;

                    // Dispatch notification
                    this.$dispatch('graph-notification', {
                        type: 'info',
                        title: 'Graph Updated',
                        message: event.message,
                        duration: 8000,
                        action: {
                            label: 'Refresh Now',
                            callback: () => this.refreshGraph()
                        }
                    });
                });
        },

        refreshGraph() {
            this.pendingUpdates = 0;
            this.$dispatch('refresh-graph');
        },

        updateGraph() {
            // Guard against calls before init completes or after destroy
            if (!this.simulation || !this.svg) return;

            // Enable performance mode for large graphs
            this.performanceMode = this.nodes.length > 200;

            // Filter nodes by active types
            let visibleNodes = this.nodes.filter(n =>
                this.filters.activeTypes.includes(n.type)
            );

            // Limit nodes in performance mode
            if (visibleNodes.length > this.nodeLimit) {
                console.warn(`Graph has ${visibleNodes.length} nodes, limiting to ${this.nodeLimit}`);
                visibleNodes = visibleNodes.slice(0, this.nodeLimit);
            }

            const visibleNodeIds = new Set(visibleNodes.map(n => n.id));
            const visibleEdges = this.edges.filter(e =>
                visibleNodeIds.has(e.source.id || e.source) &&
                visibleNodeIds.has(e.target.id || e.target) &&
                this.filters.activeRelationships.includes(e.type)
            );

            // Update simulation with performance tweaks
            this.simulation.nodes(visibleNodes);

            if (this.performanceMode) {
                this.simulation
                    .force('charge', d3.forceManyBody().strength(-100)) // Weaker charge
                    .alphaDecay(0.05) // Faster settling
                    .alpha(0.1);
            } else {
                this.simulation
                    .force('charge', d3.forceManyBody().strength(-300))
                    .alphaDecay(0.0228) // Default
                    .alpha(0.3);
            }

            this.simulation.force('link').links(visibleEdges);

            // Render edges (simplified in performance mode)
            const edgeTip = this.edgeTip;
            this.edgeGroup.selectAll('g.edge')
                .data(visibleEdges, d => `${d.source.id || d.source}-${d.target.id || d.target}`)
                .join(
                    enter => {
                        const g = enter.append('g').attr('class', 'edge');
                        g.append('line')
                            .attr('stroke', d => this.performanceMode ? '#4B5563' : (this.edgeColors[d.type] || '#4B5563'))
                            .attr('stroke-width', this.performanceMode ? 1 : 2)
                            .attr('stroke-opacity', 0.6)
                            .attr('marker-end', this.performanceMode ? null : 'url(#arrowhead)');
                        // Add invisible wider line for easier hover detection
                        g.append('line')
                            .attr('class', 'edge-hover-target')
                            .attr('stroke', 'transparent')
                            .attr('stroke-width', 10)
                            .on('mouseover', function(event, d) { edgeTip.show(d, this); })
                            .on('mouseout', function(event, d) { edgeTip.hide(d, this); });
                        return g;
                    },
                    update => {
                        update.select('line:not(.edge-hover-target)')
                            .attr('stroke', d => this.performanceMode ? '#4B5563' : (this.edgeColors[d.type] || '#4B5563'))
                            .attr('stroke-width', this.performanceMode ? 1 : 2)
                            .attr('marker-end', this.performanceMode ? null : 'url(#arrowhead)');
                        return update;
                    },
                    exit => exit.remove()
                );

            // Render alert rings (Phase 3: Contradiction Radar)
            // These render behind nodes to show as colored rings
            this.nodeGroup.selectAll('circle.alert-ring')
                .data(visibleNodes.filter(d => this.hasAlerts(d.id)), d => d.id)
                .join(
                    enter => enter.append('circle')
                        .attr('class', 'alert-ring')
                        .attr('r', d => this.getNodeRadius(d) + 6)
                        .attr('fill', 'none')
                        .attr('stroke', d => this.getAlertColor(d))
                        .attr('stroke-width', 3)
                        .attr('opacity', 0.8),
                    update => update
                        .attr('r', d => this.getNodeRadius(d) + 6)
                        .attr('stroke', d => this.getAlertColor(d)),
                    exit => exit.remove()
                );

            // Render nodes
            const nodeTip = this.nodeTip;
            this.nodeGroup.selectAll('circle:not(.alert-ring)')
                .data(visibleNodes, d => d.id)
                .join('circle')
                .attr('r', d => {
                    if (d.id === this.highlightedNodeId) return 20;
                    if (this.performanceMode) return 8;
                    return this.getNodeRadius(d);
                })
                .attr('fill', d => this.nodeColors[d.type] || '#6B7280')
                .attr('opacity', d => this.getNodeOpacity(d))
                .attr('stroke', d => d.id === this.highlightedNodeId ? '#FBBF24' : '#fff')
                .attr('stroke-width', d => d.id === this.highlightedNodeId ? 3 : (this.performanceMode ? 1 : 2))
                .attr('filter', d => this.isPinned(d.id) ? 'url(#glow)' : null)
                .attr('cursor', 'pointer')
                .on('click', (event, d) => this.onNodeClick(d))
                .on('dblclick', (event, d) => this.onNodeDoubleClick(d))
                .on('contextmenu', (event, d) => {
                    event.preventDefault();
                    this.$dispatch('graph-context-menu', {
                        x: event.clientX,
                        y: event.clientY,
                        node: d,
                    });
                })
                .on('mouseover', function(event, d) { nodeTip.show(d, this); })
                .on('mouseout', function(event, d) { nodeTip.hide(d, this); })
                .call(this.drag());

            // Skip labels in performance mode
            if (this.performanceMode) {
                this.labelGroup.selectAll('text').remove();
            } else {
                this.labelGroup.selectAll('text')
                    .data(visibleNodes, d => d.id)
                    .join('text')
                    .text(d => {
                        const label = d.label || d.properties?.case_number || d.properties?.name
                            || d.properties?.value || d.properties?.keyword || d.properties?.title
                            || d.name || d.id;
                        return label.length > 25 ? label.substring(0, 25) + '...' : label;
                    })
                    .attr('font-size', 10)
                    .attr('fill', '#E5E7EB')
                    .attr('text-anchor', 'middle')
                    .attr('dy', -20)
                    .attr('pointer-events', 'none');
            }

            this.simulation.restart();
        },

        loadData(graphData) {
            this.nodes = graphData.nodes || [];
            this.edges = graphData.edges || [];
            this.extractNodeTypes();
        },

        extractNodeTypes() {
            const types = [...new Set(this.nodes.map(n => n.type))];
            this.filters.nodeTypes = types;
            this.filters.activeTypes = [...types]; // All active by default
        },

        tick() {
            this.edgeGroup.selectAll('g.edge line')
                .attr('x1', d => d.source.x)
                .attr('y1', d => d.source.y)
                .attr('x2', d => d.target.x)
                .attr('y2', d => d.target.y);

            // Position alert rings (Phase 3: Contradiction Radar)
            this.nodeGroup.selectAll('circle.alert-ring')
                .attr('cx', d => d.x)
                .attr('cy', d => d.y);

            this.nodeGroup.selectAll('circle:not(.alert-ring)')
                .attr('cx', d => d.x)
                .attr('cy', d => d.y);

            this.labelGroup.selectAll('text')
                .attr('x', d => d.x)
                .attr('y', d => d.y);

            // Render minimap every 5 ticks
            if (!this._minimapCounter) this._minimapCounter = 0;
            this._minimapCounter++;
            if (this._minimapCounter % 5 === 0) {
                this.renderMinimap();
            }
        },

        onNodeClick(node) {
            this.selectedNode = node;
            this.openMetadataPanel(node);
            this.$dispatch('node-selected', { node });
            if (this.$wire) {
                this.$wire.call('selectNode', {
                    id: node.id,
                    type: node.type,
                    label: node.label || node.name || node.id,
                });
            }
        },

        onNodeDoubleClick(node) {
            this.expandNode(node);
        },

        openMetadataPanel(node) {
            this.metadataPanel.node = node;
            this.metadataPanel.isOpen = true;
            this.metadataPanel.loading = false;
        },

        closeMetadataPanel() {
            this.metadataPanel.isOpen = false;
            this.metadataPanel.node = null;
        },

        pinNode(node) {
            if (!this.pinnedNodes.find(n => n.id === node.id)) {
                this.pinnedNodes.push({
                    id: node.id,
                    type: node.type,
                    label: node.properties?.case_number || node.properties?.title || node.label || node.name || node.id,
                });

                // Dispatch to Livewire for session tracking
                this.$wire.pinNode(node);
            }
        },

        unpinNode(nodeId) {
            this.pinnedNodes = this.pinnedNodes.filter(n => n.id !== nodeId);

            // Dispatch to Livewire for session tracking
            this.$wire.unpinNode(nodeId);
        },

        isPinned(nodeId) {
            return this.pinnedNodes.some(n => n.id === nodeId);
        },

        /**
         * Update node alerts mapping (Phase 3: Contradiction Radar)
         */
        updateNodeAlerts() {
            this.nodeAlerts = {};
            for (const alert of this.alerts) {
                if (alert.dismissed) continue;
                const nodeId = alert.source_node_id;
                if (!this.nodeAlerts[nodeId]) {
                    this.nodeAlerts[nodeId] = [];
                }
                this.nodeAlerts[nodeId].push(alert);
            }
        },

        /**
         * Get alert ring color based on highest severity (Phase 3: Contradiction Radar)
         */
        getAlertColor(node) {
            const alerts = this.nodeAlerts[node.id] || [];
            if (alerts.length === 0) return 'transparent';
            if (alerts.some(a => a.severity === 'critical')) return '#ef4444'; // red
            if (alerts.some(a => a.severity === 'warning')) return '#f59e0b'; // amber
            if (alerts.some(a => a.severity === 'caution')) return '#f97316'; // orange
            return 'transparent';
        },

        /**
         * Check if node has any alerts (Phase 3: Contradiction Radar)
         */
        hasAlerts(nodeId) {
            return this.nodeAlerts[nodeId] && this.nodeAlerts[nodeId].length > 0;
        },

        async expandNode(node) {
            if (this.expandedNodeIds.has(node.id)) return;

            this.isLoading = true;
            this.expandedNodeIds.add(node.id);

            // Use filtered expand with active relationship types
            if (this.$wire) {
                this.$wire.call('expandNodeFiltered', node.id, this.filters.activeRelationships);
            }
        },

        addConnectedNodes(newNodes, newEdges) {
            // Merge new nodes (avoid duplicates)
            const existingIds = new Set(this.nodes.map(n => n.id));
            const uniqueNewNodes = newNodes.filter(n => !existingIds.has(n.id));

            this.nodes = [...this.nodes, ...uniqueNewNodes];
            this.edges = [...this.edges, ...newEdges];

            if (this.selectedNode) {
                this.expandedNodeIds.add(this.selectedNode.id);
            }
            this.extractNodeTypes();
            this.isLoading = false;
        },

        toggleNodeType(type) {
            const index = this.filters.activeTypes.indexOf(type);
            if (index > -1) {
                this.filters.activeTypes.splice(index, 1);
            } else {
                this.filters.activeTypes.push(type);
            }
            this.updateGraph();
        },

        toggleRelationship(relType) {
            const index = this.filters.activeRelationships.indexOf(relType);
            if (index > -1) {
                this.filters.activeRelationships.splice(index, 1);
            } else {
                this.filters.activeRelationships.push(relType);
            }
            this.updateGraph();
            this.saveFilterSettings();
        },

        selectAllRelationships() {
            this.filters.activeRelationships = [...this.filters.relationshipTypes];
            this.updateGraph();
            this.saveFilterSettings();
        },

        clearAllRelationships() {
            this.filters.activeRelationships = [];
            this.updateGraph();
            this.saveFilterSettings();
        },

        saveFilterSettings() {
            // Dispatch filter settings to Livewire for session tracking
            this.$wire.dispatch('update-filter-settings', {
                settings: {
                    activeRelationships: this.filters.activeRelationships,
                    activeTypes: this.filters.activeTypes,
                }
            });
        },

        drag() {
            const simulation = this.simulation;

            function dragstarted(event) {
                if (!event.active) simulation.alphaTarget(0.3).restart();
                event.subject.fx = event.subject.x;
                event.subject.fy = event.subject.y;
            }

            function dragged(event) {
                event.subject.fx = event.x;
                event.subject.fy = event.y;
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
        },

        zoomIn() {
            this.svg.transition().duration(300).call(
                this.zoom.scaleBy, 1.3
            );
        },

        zoomOut() {
            this.svg.transition().duration(300).call(
                this.zoom.scaleBy, 0.7
            );
        },

        resetZoom() {
            this.svg.transition().duration(300).call(
                this.zoom.transform,
                d3.zoomIdentity.translate(this.width / 2, this.height / 2).scale(1)
            );
        },

        fitToView() {
            if (this.nodes.length === 0) return;

            const bounds = this.graphGroup.node().getBBox();
            const fullWidth = this.width;
            const fullHeight = this.height;
            const width = bounds.width;
            const height = bounds.height;
            const midX = bounds.x + width / 2;
            const midY = bounds.y + height / 2;

            const scale = 0.8 / Math.max(width / fullWidth, height / fullHeight);
            const translate = [fullWidth / 2 - scale * midX, fullHeight / 2 - scale * midY];

            this.svg.transition().duration(500).call(
                this.zoom.transform,
                d3.zoomIdentity.translate(translate[0], translate[1]).scale(scale)
            );
        },

        async searchNodes() {
            if (!this.searchQuery.trim()) {
                this.searchResults = [];
                this.serverSearchResults = [];
                this.highlightedNodeId = null;
                this.updateGraph();
                return;
            }

            const query = this.searchQuery.toLowerCase();

            // First search locally loaded nodes
            const localResults = this.nodes.filter(node => {
                const label = (node.label || node.name || node.id || '').toLowerCase();
                return label.includes(query);
            }).slice(0, 10);

            // If we have enough local results, use them
            if (localResults.length >= 5) {
                this.searchResults = localResults;
                return;
            }

            // Otherwise, search server-side for nodes in the graph database
            if (this.$wire && query.length >= 2) {
                try {
                    const serverResults = await this.$wire.call('searchNodes', this.searchQuery);
                    // Mark server results that aren't loaded locally
                    const loadedIds = new Set(this.nodes.map(n => n.id));
                    const combined = [...localResults];
                    for (const result of (serverResults || [])) {
                        if (!combined.find(r => r.id === result.id)) {
                            result._fromServer = !loadedIds.has(result.id);
                            combined.push(result);
                        }
                    }
                    this.searchResults = combined.slice(0, 15);
                } catch (e) {
                    // Fall back to local results on error
                    this.searchResults = localResults;
                }
            } else {
                this.searchResults = localResults;
            }
        },

        clearSearch() {
            this.searchResults = [];
            this.serverSearchResults = [];
            this.highlightedNodeId = null;
            this.updateGraph();
        },

        handleServerSearchResults(results) {
            // Filter out results already in local search results
            const localIds = new Set(this.searchResults.map(n => n.id));
            this.serverSearchResults = (results || []).filter(r => !localIds.has(r.id));
        },

        async focusOnNode(nodeOrId) {
            // Support both node objects and node IDs
            let node;
            if (typeof nodeOrId === 'object') {
                node = nodeOrId;
            } else {
                node = this.nodes.find(n => n.id === nodeOrId);
                if (!node || !this.svg) return;
            }

            // If node is from server search (not loaded locally), load it first
            if (node._fromServer || !this.nodes.find(n => n.id === node.id)) {
                this.searchResults = [];
                this.isLoading = true;
                if (this.$wire) {
                    await this.$wire.call('loadNodeIntoGraph', node.id);
                }
                this.isLoading = false;
                // Wait for graph data to update, then find the newly loaded node
                await this.$nextTick;
                const loadedNode = this.nodes.find(n => n.id === node.id);
                if (loadedNode) {
                    node = loadedNode;
                }
                return; // graph-data-loaded event will trigger re-render
            }

            this.highlightedNodeId = node.id;
            this.selectedNode = node;
            this.searchResults = [];

            // Calculate new transform to center on node
            if (node.x != null && node.y != null) {
                const scale = 1.5;
                const x = this.width / 2 - node.x * scale;
                const y = this.height / 2 - node.y * scale;

                // Animate to new position
                this.svg.transition()
                    .duration(500)
                    .call(this.zoom.transform, d3.zoomIdentity.translate(x, y).scale(scale));
            }

            this.updateGraph();
            this.$dispatch('node-selected', { node });

            // Clear highlight after 2 seconds when focusing from workspace
            if (typeof nodeOrId !== 'object') {
                setTimeout(() => {
                    if (this.highlightedNodeId === node.id) {
                        this.highlightedNodeId = null;
                        this.updateGraph();
                    }
                }, 2000);
            }
        },

        renderMinimap() {
            const canvas = this.$refs.minimap;
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const w = canvas.width;
            const h = canvas.height;
            ctx.clearRect(0, 0, w, h);
            if (this.nodes.length === 0) return;
            let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
            for (const n of this.nodes) {
                if (n.x == null || n.y == null) continue;
                if (n.x < minX) minX = n.x;
                if (n.x > maxX) maxX = n.x;
                if (n.y < minY) minY = n.y;
                if (n.y > maxY) maxY = n.y;
            }
            if (!isFinite(minX)) return;
            const pad = 10;
            const scaleX = (w - pad * 2) / (maxX - minX || 1);
            const scaleY = (h - pad * 2) / (maxY - minY || 1);
            const scale = Math.min(scaleX, scaleY);
            const offsetX = (w - (maxX - minX) * scale) / 2;
            const offsetY = (h - (maxY - minY) * scale) / 2;
            ctx.strokeStyle = '#374151';
            ctx.lineWidth = 0.5;
            for (const e of this.edges) {
                if (!e.source?.x || !e.target?.x) continue;
                const sx = (e.source.x - minX) * scale + offsetX;
                const sy = (e.source.y - minY) * scale + offsetY;
                const tx = (e.target.x - minX) * scale + offsetX;
                const ty = (e.target.y - minY) * scale + offsetY;
                ctx.beginPath();
                ctx.moveTo(sx, sy);
                ctx.lineTo(tx, ty);
                ctx.stroke();
            }
            for (const n of this.nodes) {
                if (n.x == null || n.y == null) continue;
                const x = (n.x - minX) * scale + offsetX;
                const y = (n.y - minY) * scale + offsetY;
                ctx.fillStyle = this.nodeColors[n.type] || '#6B7280';
                ctx.beginPath();
                ctx.arc(x, y, 2, 0, Math.PI * 2);
                ctx.fill();
            }
        },

        exportSVG() {
            if (!this.svg) return;
            const svgElement = this.svg.node();
            const serializer = new XMLSerializer();
            const svgString = serializer.serializeToString(svgElement);
            const blob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `graph-${Date.now()}.svg`;
            a.click();
            URL.revokeObjectURL(url);
        },

        exportPNG() {
            if (!this.svg) return;
            const svgElement = this.svg.node();
            const serializer = new XMLSerializer();
            const svgString = serializer.serializeToString(svgElement);
            const canvas = document.createElement('canvas');
            canvas.width = this.width * 2;
            canvas.height = this.height * 2;
            const ctx = canvas.getContext('2d');
            ctx.scale(2, 2);
            const img = new Image();
            const svgBlob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(svgBlob);
            img.onload = () => {
                ctx.fillStyle = '#0b1220';
                ctx.fillRect(0, 0, this.width, this.height);
                ctx.drawImage(img, 0, 0, this.width, this.height);
                URL.revokeObjectURL(url);
                canvas.toBlob((blob) => {
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = `graph-${Date.now()}.png`;
                    a.click();
                }, 'image/png');
            };
            img.src = url;
        },

        setLayout(newLayout) {
            this.layout = newLayout;
            switch (newLayout) {
                case 'radial':
                    this.simulation
                        .force('center', null)
                        .force('r', d3.forceRadial(200, this.width / 2, this.height / 2)
                            .strength(d => d.isCenter ? 0 : 0.5))
                        .force('charge', d3.forceManyBody().strength(-100));
                    break;
                case 'hierarchical':
                    const layerHeight = this.height / 6;
                    this.simulation
                        .force('center', null)
                        .force('r', null)
                        .force('y', d3.forceY(d => {
                            return d.isCenter ? this.height / 2 : this.height / 2 + layerHeight;
                        }).strength(0.5))
                        .force('x', d3.forceX(this.width / 2).strength(0.1));
                    break;
                case 'force':
                default:
                    this.simulation
                        .force('r', null)
                        .force('y', null)
                        .force('x', null)
                        .force('center', d3.forceCenter(this.width / 2, this.height / 2))
                        .force('charge', d3.forceManyBody().strength(-300));
                    break;
            }
            this.simulation.alpha(1).restart();
        },

        initKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                if (!this.$refs.container || !this.$refs.container.closest(':hover')) return;
                switch (e.key) {
                    case '+':
                    case '=':
                        e.preventDefault();
                        this.zoomIn();
                        break;
                    case '-':
                        e.preventDefault();
                        this.zoomOut();
                        break;
                    case '0':
                        if (e.ctrlKey || e.metaKey) {
                            e.preventDefault();
                            this.fitToView();
                        }
                        break;
                    case 'f':
                        if (e.ctrlKey || e.metaKey) {
                            e.preventDefault();
                            const searchInput = this.$el.querySelector('input[type="text"]');
                            if (searchInput) searchInput.focus();
                        }
                        break;
                    case 'Escape':
                        this.closeMetadataPanel();
                        this.highlightedNodeId = null;
                        this.searchQuery = '';
                        this.searchResults = [];
                        this.serverSearchResults = [];
                        this.updateGraph();
                        break;
                    case 'Delete':
                    case 'Backspace':
                        if (this.selectedNode && !document.activeElement.matches('input, textarea')) {
                            this.nodes = this.nodes.filter(n => n.id !== this.selectedNode.id);
                            this.selectedNode = null;
                            this.closeMetadataPanel();
                            this.updateGraph();
                        }
                        break;
                }
            });
        },

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
    };
}

// Register globally
window.ForceGraph = ForceGraph;
