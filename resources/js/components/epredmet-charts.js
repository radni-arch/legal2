/**
 * EpredmetCharts Alpine Component
 *
 * Handles D3.js chart rendering for the EpredmetWidget Analytics tab.
 * Integrates with Livewire 3 via $wire for reactive data binding.
 *
 * Usage in Blade:
 *   <div x-data="epredmetCharts" x-init="init()">
 *     <div id="chart-yearly-trend"></div>
 *   </div>
 */
export default function epredmetCharts() {
    return {
        // State
        chartData: null,
        isInitialized: false,
        renderAttempts: 0,
        maxRenderAttempts: 10,
        chartErrors: {},
        globalError: null,
        visibilityObserver: null,

        /**
         * Alpine init lifecycle hook
         */
        init() {
            console.log('[EpredmetCharts] Initializing Alpine component');

            // Mark element with instance ID for morph detection
            this.$el.setAttribute('data-epredmet-instance', Date.now());

            if (!this.verifyD3()) {
                this.waitForD3().then(() => this.setupWatchers());
                return;
            }

            this.setupWatchers();

            // Listen for Livewire navigation events
            document.addEventListener('livewire:navigated', () => {
                console.log('[EpredmetCharts] Livewire navigated, checking charts');
                if (this.chartData && Object.keys(this.chartData).length > 0) {
                    this.scheduleRender();
                }
            });
        },

        /**
         * Verify D3.js is loaded and accessible
         */
        verifyD3() {
            const d3Available = typeof window.d3 !== 'undefined';
            console.log('[EpredmetCharts] D3 available:', d3Available, window.d3?.version);
            return d3Available;
        },

        /**
         * Wait for D3 to become available (fallback)
         */
        waitForD3() {
            return new Promise((resolve) => {
                if (window.d3) {
                    resolve();
                    return;
                }

                window.addEventListener('d3:ready', () => resolve(), { once: true });

                const interval = setInterval(() => {
                    if (window.d3) {
                        clearInterval(interval);
                        resolve();
                    }
                }, 50);

                setTimeout(() => {
                    clearInterval(interval);
                    console.error('[EpredmetCharts] D3 failed to load within timeout');
                    resolve();
                }, 5000);
            });
        },

        /**
         * Setup Livewire property watchers
         */
        setupWatchers() {
            console.log('[EpredmetCharts] Setting up watchers');
            this.isInitialized = true;

            this.$wire.$watch('chartData', (value) => {
                console.log('[EpredmetCharts] chartData changed:', value ? Object.keys(value) : 'null');
                if (value && Object.keys(value).length > 0) {
                    this.chartData = value;
                    this.scheduleRender();
                }
            });

            this.$wire.on('epredmet-charts-updated', () => {
                console.log('[EpredmetCharts] Event: epredmet-charts-updated');
                const data = this.$wire.chartData;
                if (data && Object.keys(data).length > 0) {
                    this.chartData = data;
                    this.scheduleRender();
                }
            });

            // Check if data already exists
            const existingData = this.$wire.chartData;
            if (existingData && Object.keys(existingData).length > 0) {
                console.log('[EpredmetCharts] Found existing chartData, rendering');
                this.chartData = existingData;
                this.scheduleRender();
            }

            this.setupVisibilityObserver();
        },

        /**
         * Schedule render with DOM readiness check
         */
        scheduleRender() {
            this.renderAttempts = 0;
            this.attemptRender();
        },

        /**
         * Attempt to render, retrying if DOM not ready
         */
        attemptRender() {
            this.renderAttempts++;

            if (this.renderAttempts > this.maxRenderAttempts) {
                console.error('[EpredmetCharts] Max render attempts exceeded');
                return;
            }

            const container = document.getElementById('chart-yearly-trend');
            if (!container || container.clientWidth === 0) {
                console.log(`[EpredmetCharts] DOM not ready, retry ${this.renderAttempts}/${this.maxRenderAttempts}`);
                setTimeout(() => this.attemptRender(), 100);
                return;
            }

            console.log('[EpredmetCharts] DOM ready, rendering charts');
            this.renderAllCharts();
        },

        /**
         * Wait for an element to exist in DOM with valid dimensions
         */
        waitForElement(selector, timeout = 5000) {
            return new Promise((resolve, reject) => {
                const startTime = Date.now();

                const el = document.querySelector(selector);
                if (el && el.clientWidth > 0) {
                    resolve(el);
                    return;
                }

                const observer = new MutationObserver((mutations, obs) => {
                    const el = document.querySelector(selector);
                    if (el && el.clientWidth > 0) {
                        obs.disconnect();
                        resolve(el);
                    } else if (Date.now() - startTime > timeout) {
                        obs.disconnect();
                        reject(new Error(`Timeout waiting for ${selector}`));
                    }
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });

                setTimeout(() => {
                    observer.disconnect();
                    const el = document.querySelector(selector);
                    if (el && el.clientWidth > 0) {
                        resolve(el);
                    } else {
                        reject(new Error(`Timeout waiting for ${selector}`));
                    }
                }, timeout);
            });
        },

        /**
         * Check if element is visible and has dimensions
         */
        isElementVisible(el) {
            if (!el) return false;
            const rect = el.getBoundingClientRect();
            const style = window.getComputedStyle(el);
            return (
                style.display !== 'none' &&
                style.visibility !== 'hidden' &&
                style.opacity !== '0' &&
                rect.width > 0 &&
                rect.height > 0 &&
                el.offsetParent !== null
            );
        },

        /**
         * Setup IntersectionObserver for lazy rendering
         */
        setupVisibilityObserver() {
            if (this.visibilityObserver) {
                this.visibilityObserver.disconnect();
            }

            this.visibilityObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && entry.intersectionRatio > 0.1) {
                        const chartId = entry.target.id;
                        if (this.chartData && !entry.target.querySelector('svg')) {
                            console.log(`[EpredmetCharts] ${chartId} became visible, rendering`);
                            this.renderSingleChart(chartId);
                        }
                    }
                });
            }, {
                threshold: [0, 0.1, 0.5, 1.0],
                rootMargin: '50px'
            });

            const chartIds = [
                'chart-yearly-trend', 'chart-same-day-donut', 'chart-court-bar',
                'chart-regional-bar', 'chart-hhi-bar', 'chart-processing-dist',
                'chart-judge-profile', 'chart-police-approval', 'chart-monthly-trend',
                'chart-argument-scores'
            ];

            chartIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    this.visibilityObserver.observe(el);
                }
            });

            // Watch for tab visibility changes
            const widget = this.$el.closest('.epredmet-widget');
            if (widget) {
                const analyticsTab = widget.querySelector('[dusk="analytics-panel"]');
                if (analyticsTab) {
                    const tabObserver = new MutationObserver((mutations) => {
                        mutations.forEach(mut => {
                            if (mut.type === 'attributes' && mut.attributeName === 'style') {
                                if (this.isElementVisible(analyticsTab)) {
                                    console.log('[EpredmetCharts] Analytics tab became visible');
                                    this.scheduleRender();
                                }
                            }
                        });
                    });
                    tabObserver.observe(analyticsTab, { attributes: true });
                }
            }
        },

        /**
         * Thoroughly clear a chart container
         */
        clearChart(elementId) {
            const el = document.getElementById(elementId);
            if (!el) {
                return null;
            }

            const d3 = window.d3;
            if (d3) {
                const selection = d3.select(el);
                selection.selectAll('*').remove();
                selection.on('.', null);
            }

            el.innerHTML = '';
            return el;
        },

        /**
         * Safe render wrapper with error handling
         */
        safeRender(chartId, renderFn) {
            try {
                this.chartErrors[chartId] = null;
                renderFn();
            } catch (error) {
                console.error(`[EpredmetCharts] Error rendering ${chartId}:`, error);
                this.chartErrors[chartId] = error.message;
                this.showChartError(chartId, error.message);
            }
        },

        /**
         * Display error message in chart container
         */
        showChartError(chartId, message) {
            const el = document.getElementById(chartId);
            if (!el) return;

            el.innerHTML = `
                <div class="flex flex-col items-center justify-center h-full text-center p-4">
                    <svg class="h-8 w-8 text-red-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div class="text-sm text-red-400 mb-2">Chart failed to render</div>
                    <div class="text-xs text-gray-500">${message}</div>
                </div>
            `;
        },

        /**
         * Render a single chart by ID
         */
        renderSingleChart(chartId) {
            if (!this.chartData || !window.d3) return;
            const d3 = window.d3;

            const chartMap = {
                'chart-yearly-trend': () => this.renderTrendChart(d3),
                'chart-same-day-donut': () => this.renderDonutChart(d3),
                'chart-court-bar': () => this.renderBarChart(d3, 'chart-court-bar', this.chartData.court_bar?.labels, this.chartData.court_bar?.warrants, '#eab308'),
                'chart-regional-bar': () => this.renderBarChart(d3, 'chart-regional-bar', this.chartData.regional_bar?.labels, this.chartData.regional_bar?.per_100k, '#3b82f6'),
                'chart-hhi-bar': () => this.renderHHIChart(d3),
                'chart-processing-dist': () => this.renderDistChart(d3),
                'chart-judge-profile': () => this.renderJudgeProfileChart(d3),
                'chart-police-approval': () => this.renderPoliceApprovalChart(d3),
                'chart-monthly-trend': () => this.renderMonthlyTrendChart(d3),
                'chart-argument-scores': () => this.renderArgumentScoresChart(d3),
            };

            const renderFn = chartMap[chartId];
            if (renderFn) {
                this.safeRender(chartId, renderFn);
            }
        },

        /**
         * Main render orchestrator
         */
        async renderAllCharts() {
            if (!this.chartData || !window.d3) {
                console.warn('[EpredmetCharts] Cannot render: missing data or D3');
                return;
            }

            const d3 = window.d3;
            this.chartErrors = {};

            const chartConfigs = [
                { id: 'chart-yearly-trend', render: () => this.renderTrendChart(d3) },
                { id: 'chart-same-day-donut', render: () => this.renderDonutChart(d3) },
                { id: 'chart-court-bar', render: () => this.renderBarChart(d3, 'chart-court-bar', this.chartData.court_bar?.labels, this.chartData.court_bar?.warrants, '#eab308') },
                { id: 'chart-regional-bar', render: () => this.renderBarChart(d3, 'chart-regional-bar', this.chartData.regional_bar?.labels, this.chartData.regional_bar?.per_100k, '#3b82f6') },
                { id: 'chart-hhi-bar', render: () => this.renderHHIChart(d3) },
                { id: 'chart-processing-dist', render: () => this.renderDistChart(d3) },
                { id: 'chart-judge-profile', render: () => this.renderJudgeProfileChart(d3) },
                { id: 'chart-police-approval', render: () => this.renderPoliceApprovalChart(d3) },
                { id: 'chart-monthly-trend', render: () => this.renderMonthlyTrendChart(d3) },
                { id: 'chart-argument-scores', render: () => this.renderArgumentScoresChart(d3) },
            ];

            for (const config of chartConfigs) {
                try {
                    await this.waitForElement(`#${config.id}`, 2000);
                    if (this.isElementVisible(document.getElementById(config.id))) {
                        this.safeRender(config.id, config.render);
                        console.log(`[EpredmetCharts] Rendered ${config.id}`);
                    } else {
                        console.log(`[EpredmetCharts] Skipped ${config.id}: not visible`);
                    }
                } catch (error) {
                    console.warn(`[EpredmetCharts] Skipped ${config.id}: ${error.message}`);
                }
            }

            console.log('[EpredmetCharts] Render pass complete');
        },

        /**
         * Cleanup on component destroy
         */
        destroy() {
            console.log('[EpredmetCharts] Destroying component, cleaning up');
            if (this.visibilityObserver) {
                this.visibilityObserver.disconnect();
            }
            const chartIds = [
                'chart-yearly-trend', 'chart-same-day-donut', 'chart-court-bar',
                'chart-regional-bar', 'chart-hhi-bar', 'chart-processing-dist',
                'chart-judge-profile', 'chart-police-approval', 'chart-monthly-trend',
                'chart-argument-scores'
            ];
            chartIds.forEach(id => this.clearChart(id));
        },

        // ═══════════════════════════════════════════════════════════════
        // CHART RENDERING METHODS
        // ═══════════════════════════════════════════════════════════════

        renderTrendChart(d3) {
            const data = this.chartData.yearly_trend;
            if (!data || !data.labels?.length) return;

            const el = this.clearChart('chart-yearly-trend');
            if (!el || el.clientWidth === 0) return;

            const cumulative = data.cumulative || [];
            const margin = {top: 20, right: 55, bottom: 30, left: 50};
            const width = el.clientWidth - margin.left - margin.right;
            const height = 220 - margin.top - margin.bottom;
            const svg = d3.select(el).append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g').attr('transform', `translate(${margin.left},${margin.top})`);
            const x = d3.scalePoint().domain(data.labels.map(String)).range([0, width]).padding(0.5);
            const yLeft = d3.scaleLinear().domain([0, d3.max(data.warrants) * 1.2]).range([height, 0]);
            const maxCum = cumulative.length ? d3.max(cumulative) : 1;
            const yRight = d3.scaleLinear().domain([0, maxCum * 1.1]).range([height, 0]);

            // Axes
            svg.append('g').attr('transform', `translate(0,${height})`)
                .call(d3.axisBottom(x)).selectAll('text').style('fill', '#9ca3af').style('font-size', '10px');
            svg.append('g').call(d3.axisLeft(yLeft).ticks(5))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '10px');
            svg.append('g').attr('transform', `translate(${width},0)`)
                .call(d3.axisRight(yRight).ticks(5).tickFormat(d3.format(',')))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '10px');

            // Cumulative area (behind bars)
            if (cumulative.length) {
                const area = d3.area()
                    .x((d, i) => x(String(data.labels[i])))
                    .y0(height)
                    .y1((d, i) => yRight(cumulative[i]));
                svg.append('path').datum(data.labels)
                    .attr('fill', '#3b82f6').attr('opacity', 0.12)
                    .attr('d', area);
                // Cumulative line
                const cumLine = d3.line()
                    .x((d, i) => x(String(data.labels[i])))
                    .y((d, i) => yRight(cumulative[i]));
                svg.append('path').datum(data.labels)
                    .attr('fill', 'none').attr('stroke', '#3b82f6').attr('stroke-width', 2)
                    .attr('stroke-dasharray', '6,3').attr('d', cumLine);
                // Cumulative dots with labels
                svg.selectAll('.dot-cum').data(cumulative).enter().append('circle')
                    .attr('cx', (d, i) => x(String(data.labels[i])))
                    .attr('cy', d => yRight(d))
                    .attr('r', 3).attr('fill', '#3b82f6').attr('opacity', 0.7);
            }

            // Bars – home search warrants per year
            svg.selectAll('.bar').data(data.labels).enter().append('rect')
                .attr('x', (d, i) => x(String(d)) - 14)
                .attr('y', (d, i) => yLeft(data.warrants[i]))
                .attr('width', 28)
                .attr('height', (d, i) => height - yLeft(data.warrants[i]))
                .attr('fill', '#eab308').attr('opacity', 0.75).attr('rx', 3);
            // Value labels on bars
            svg.selectAll('.bar-label').data(data.warrants).enter().append('text')
                .attr('x', (d, i) => x(String(data.labels[i])))
                .attr('y', (d) => yLeft(d) - 4)
                .attr('text-anchor', 'middle')
                .style('fill', '#eab308').style('font-size', '9px').style('font-weight', '600')
                .text(d => d3.format(',')(d));

            // Legend
            svg.append('rect').attr('x', 10).attr('y', -10).attr('width', 12).attr('height', 12).attr('fill', '#eab308').attr('opacity', 0.75);
            svg.append('text').attr('x', 26).attr('y', 0).text('Home Searches / Year').style('fill', '#9ca3af').style('font-size', '10px');
            svg.append('line').attr('x1', 170).attr('x2', 190).attr('y1', -4).attr('y2', -4).attr('stroke', '#3b82f6').attr('stroke-width', 2).attr('stroke-dasharray', '6,3');
            svg.append('text').attr('x', 194).attr('y', 0).text('Cumulative').style('fill', '#9ca3af').style('font-size', '10px');
        },

        renderDonutChart(d3) {
            const data = this.chartData.same_day_donut;
            if (!data) return;

            const el = this.clearChart('chart-same-day-donut');
            if (!el) return;

            const w = el.clientWidth, h = 220;
            const radius = Math.min(w, h) / 2 - 20;
            const svg = d3.select(el).append('svg').attr('width', w).attr('height', h)
                .append('g').attr('transform', `translate(${w/2},${h/2})`);
            const pie = d3.pie().value(d => d.value).sort(null);
            const arc = d3.arc().innerRadius(radius * 0.55).outerRadius(radius);
            const dataset = [
                {label: 'Same-Day', value: data.same_day, color: '#ef4444'},
                {label: 'Other', value: data.not_same_day, color: '#374151'}
            ];
            svg.selectAll('path').data(pie(dataset)).enter().append('path')
                .attr('d', arc).attr('fill', d => d.data.color)
                .attr('stroke', 'rgba(0,0,0,0.3)').attr('stroke-width', 1);
            const total = data.same_day + data.not_same_day;
            const pct = total > 0 ? Math.round(100 * data.same_day / total) : 0;
            svg.append('text').attr('text-anchor', 'middle').attr('dy', '-0.2em')
                .style('fill', pct > 80 ? '#ef4444' : '#eab308')
                .style('font-size', '1.8rem').style('font-weight', '700').text(pct + '%');
            svg.append('text').attr('text-anchor', 'middle').attr('dy', '1.3em')
                .style('fill', '#9ca3af').style('font-size', '0.65rem').text('same-day');
        },

        renderBarChart(d3, containerId, labels, values, color) {
            if (!labels || !values || labels.length === 0) return;

            const el = this.clearChart(containerId);
            if (!el) return;

            const margin = {top: 5, right: 10, bottom: 60, left: 45};
            const width = el.clientWidth - margin.left - margin.right;
            const height = 200 - margin.top - margin.bottom;
            const svg = d3.select(el).append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g').attr('transform', `translate(${margin.left},${margin.top})`);
            const x = d3.scaleBand().domain(labels).range([0, width]).padding(0.3);
            const y = d3.scaleLinear().domain([0, d3.max(values) * 1.1]).range([height, 0]);
            svg.append('g').attr('transform', `translate(0,${height})`).call(d3.axisBottom(x))
                .selectAll('text').attr('transform', 'rotate(-40)').style('text-anchor', 'end')
                .style('fill', '#9ca3af').style('font-size', '8px');
            svg.append('g').call(d3.axisLeft(y).ticks(5))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '9px');
            svg.selectAll('.bar').data(values).enter().append('rect')
                .attr('x', (d, i) => x(labels[i]))
                .attr('y', d => y(d))
                .attr('width', x.bandwidth())
                .attr('height', d => height - y(d))
                .attr('fill', color).attr('opacity', 0.8).attr('rx', 2);
        },

        renderHHIChart(d3) {
            const data = this.chartData.hhi_bar;
            if (!data || !data.labels?.length) return;

            const el = this.clearChart('chart-hhi-bar');
            if (!el) return;

            const margin = {top: 5, right: 10, bottom: 60, left: 50};
            const width = el.clientWidth - margin.left - margin.right;
            const height = 200 - margin.top - margin.bottom;
            const svg = d3.select(el).append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g').attr('transform', `translate(${margin.left},${margin.top})`);
            const x = d3.scaleBand().domain(data.labels).range([0, width]).padding(0.3);
            const y = d3.scaleLinear().domain([0, Math.max(d3.max(data.hhi), 5000) * 1.1]).range([height, 0]);
            svg.append('g').attr('transform', `translate(0,${height})`).call(d3.axisBottom(x))
                .selectAll('text').attr('transform', 'rotate(-40)').style('text-anchor', 'end')
                .style('fill', '#9ca3af').style('font-size', '8px');
            svg.append('g').call(d3.axisLeft(y).ticks(5))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '9px');
            svg.append('line').attr('x1', 0).attr('x2', width).attr('y1', y(2500)).attr('y2', y(2500))
                .attr('stroke', '#eab308').attr('stroke-dasharray', '4').attr('opacity', 0.5);
            svg.append('line').attr('x1', 0).attr('x2', width).attr('y1', y(5000)).attr('y2', y(5000))
                .attr('stroke', '#ef4444').attr('stroke-dasharray', '4').attr('opacity', 0.5);
            svg.selectAll('.bar').data(data.hhi).enter().append('rect')
                .attr('x', (d, i) => x(data.labels[i]))
                .attr('y', d => y(d))
                .attr('width', x.bandwidth())
                .attr('height', d => height - y(d))
                .attr('fill', d => d > 5000 ? '#ef4444' : (d > 2500 ? '#eab308' : '#3b82f6'))
                .attr('opacity', 0.8).attr('rx', 2);
        },

        renderDistChart(d3) {
            const data = this.chartData.processing_dist;
            if (!data || !data.labels?.length) return;

            const el = this.clearChart('chart-processing-dist');
            if (!el) return;

            const margin = {top: 5, right: 10, bottom: 60, left: 50};
            const width = el.clientWidth - margin.left - margin.right;
            const height = 200 - margin.top - margin.bottom;
            const svg = d3.select(el).append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g').attr('transform', `translate(${margin.left},${margin.top})`);
            const x = d3.scaleBand().domain(data.labels).range([0, width]).padding(0.2);
            const y = d3.scaleLinear().domain([0, d3.max(data.counts) * 1.1]).range([height, 0]);
            svg.append('g').attr('transform', `translate(0,${height})`).call(d3.axisBottom(x))
                .selectAll('text').attr('transform', 'rotate(-35)').style('text-anchor', 'end')
                .style('fill', '#9ca3af').style('font-size', '8px');
            svg.append('g').call(d3.axisLeft(y).ticks(5))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '9px');
            svg.selectAll('.bar').data(data.counts).enter().append('rect')
                .attr('x', (d, i) => x(data.labels[i]))
                .attr('y', d => y(d))
                .attr('width', x.bandwidth())
                .attr('height', d => height - y(d))
                .attr('fill', (d, i) => data.labels[i].includes('same day') ? '#ef4444' : '#3b82f6')
                .attr('opacity', 0.8).attr('rx', 2);
        },

        renderJudgeProfileChart(d3) {
            const data = this.chartData.judge_profile_bar;
            if (!data || !data.labels?.length) return;

            const el = this.clearChart('chart-judge-profile');
            if (!el) return;

            const margin = {top: 5, right: 60, bottom: 10, left: 120};
            const width = el.clientWidth - margin.left - margin.right;
            const height = 220 - margin.top - margin.bottom;
            const svg = d3.select(el).append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g').attr('transform', `translate(${margin.left},${margin.top})`);
            const labels = data.labels.map(l => l.length > 18 ? l.slice(0, 18) + '...' : l);
            const y = d3.scaleBand().domain(labels).range([0, height]).padding(0.2);
            const x = d3.scaleLinear().domain([0, d3.max(data.warrants) * 1.1]).range([0, width]);
            svg.append('g').call(d3.axisLeft(y))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '9px');
            svg.append('g').attr('transform', `translate(0,${height})`).call(d3.axisBottom(x).ticks(5))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '9px');
            svg.selectAll('.bar').data(data.warrants).enter().append('rect')
                .attr('y', (d, i) => y(labels[i]))
                .attr('x', 0)
                .attr('height', y.bandwidth())
                .attr('width', d => x(d))
                .attr('fill', (d, i) => data.is_rubber_stamp[i] ? '#ef4444' : (data.same_day_pct[i] > 90 ? '#eab308' : '#3b82f6'))
                .attr('opacity', 0.8).attr('rx', 2);
            svg.selectAll('.label').data(data.warrants).enter().append('text')
                .attr('y', (d, i) => y(labels[i]) + y.bandwidth() / 2 + 4)
                .attr('x', d => x(d) + 4)
                .text((d, i) => data.same_day_pct[i] + '%')
                .style('fill', '#9ca3af').style('font-size', '9px');
        },

        renderPoliceApprovalChart(d3) {
            const data = this.chartData.police_approval_bar;
            if (!data || !data.labels?.length) return;

            const el = this.clearChart('chart-police-approval');
            if (!el) return;

            const margin = {top: 20, right: 10, bottom: 60, left: 45};
            const width = el.clientWidth - margin.left - margin.right;
            const height = 220 - margin.top - margin.bottom;
            const svg = d3.select(el).append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g').attr('transform', `translate(${margin.left},${margin.top})`);
            const x0 = d3.scaleBand().domain(data.labels).range([0, width]).padding(0.3);
            const x1 = d3.scaleBand().domain(['approval', 'same_day']).range([0, x0.bandwidth()]).padding(0.1);
            const y = d3.scaleLinear().domain([0, 105]).range([height, 0]);
            svg.append('g').attr('transform', `translate(0,${height})`).call(d3.axisBottom(x0))
                .selectAll('text').attr('transform', 'rotate(-30)').style('text-anchor', 'end')
                .style('fill', '#9ca3af').style('font-size', '9px');
            svg.append('g').call(d3.axisLeft(y).ticks(5).tickFormat(d => d + '%'))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '9px');
            svg.append('line').attr('x1', 0).attr('x2', width).attr('y1', y(100)).attr('y2', y(100))
                .attr('stroke', '#ef4444').attr('stroke-dasharray', '4').attr('opacity', 0.3);
            data.labels.forEach((label, i) => {
                const g = svg.append('g').attr('transform', `translate(${x0(label)},0)`);
                g.append('rect')
                    .attr('x', x1('approval')).attr('y', y(data.approval_pct[i]))
                    .attr('width', x1.bandwidth())
                    .attr('height', height - y(data.approval_pct[i]))
                    .attr('fill', data.approval_pct[i] >= 100 ? '#ef4444' : '#22c55e')
                    .attr('opacity', 0.8).attr('rx', 2);
                g.append('rect')
                    .attr('x', x1('same_day')).attr('y', y(data.same_day_pct[i]))
                    .attr('width', x1.bandwidth())
                    .attr('height', height - y(data.same_day_pct[i]))
                    .attr('fill', '#eab308')
                    .attr('opacity', 0.6).attr('rx', 2);
            });
            svg.append('rect').attr('x', 10).attr('y', -12).attr('width', 10).attr('height', 10).attr('fill', '#22c55e').attr('opacity', 0.8);
            svg.append('text').attr('x', 24).attr('y', -3).text('Approval %').style('fill', '#9ca3af').style('font-size', '9px');
            svg.append('rect').attr('x', 100).attr('y', -12).attr('width', 10).attr('height', 10).attr('fill', '#eab308').attr('opacity', 0.6);
            svg.append('text').attr('x', 114).attr('y', -3).text('Same-Day %').style('fill', '#9ca3af').style('font-size', '9px');
        },

        renderMonthlyTrendChart(d3) {
            const data = this.chartData.monthly_trend;
            if (!data || !data.labels?.length) return;

            const el = this.clearChart('chart-monthly-trend');
            if (!el) return;

            const margin = {top: 25, right: 55, bottom: 50, left: 50};
            const width = el.clientWidth - margin.left - margin.right;
            const height = 280 - margin.top - margin.bottom;
            const svg = d3.select(el).append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g').attr('transform', `translate(${margin.left},${margin.top})`);
            const x = d3.scaleBand().domain(data.labels).range([0, width]).padding(0.15);
            const yLeft = d3.scaleLinear().domain([0, d3.max(data.warrants) * 1.15]).range([height, 0]);
            const yRight = d3.scaleLinear().domain([0, 100]).range([height, 0]);
            svg.append('g').attr('transform', `translate(0,${height})`)
                .call(d3.axisBottom(x)).selectAll('text')
                .attr('transform', 'rotate(-45)').style('text-anchor', 'end')
                .style('fill', '#9ca3af').style('font-size', '8px');
            svg.append('g').call(d3.axisLeft(yLeft).ticks(6))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '9px');
            svg.append('g').attr('transform', `translate(${width},0)`)
                .call(d3.axisRight(yRight).ticks(5).tickFormat(d => d + '%'))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '9px');
            svg.selectAll('.bar').data(data.warrants).enter().append('rect')
                .attr('x', (d, i) => x(data.labels[i]))
                .attr('y', d => yLeft(d))
                .attr('width', x.bandwidth())
                .attr('height', d => height - yLeft(d))
                .attr('fill', (d, i) => data.is_spike[i] ? '#ef4444' : (data.is_drop[i] ? '#22c55e' : '#3b82f6'))
                .attr('opacity', 0.8).attr('rx', 2);
            data.warrants.forEach((d, i) => {
                if (data.is_spike[i]) {
                    svg.append('text')
                        .attr('x', x(data.labels[i]) + x.bandwidth() / 2)
                        .attr('y', yLeft(d) - 6)
                        .attr('text-anchor', 'middle')
                        .style('fill', '#ef4444').style('font-size', '10px')
                        .text('\u25B2');
                }
            });
            const line = d3.line()
                .x((d, i) => x(data.labels[i]) + x.bandwidth() / 2)
                .y((d, i) => yRight(data.same_day_pct[i]))
                .curve(d3.curveMonotoneX);
            svg.append('path').datum(data.same_day_pct)
                .attr('fill', 'none').attr('stroke', '#eab308').attr('stroke-width', 2)
                .attr('d', line);
            svg.append('rect').attr('x', 10).attr('y', -16).attr('width', 10).attr('height', 10).attr('fill', '#3b82f6').attr('opacity', 0.8);
            svg.append('text').attr('x', 24).attr('y', -8).text('Normal').style('fill', '#9ca3af').style('font-size', '9px');
            svg.append('rect').attr('x', 80).attr('y', -16).attr('width', 10).attr('height', 10).attr('fill', '#ef4444');
            svg.append('text').attr('x', 94).attr('y', -8).text('Spike').style('fill', '#9ca3af').style('font-size', '9px');
            svg.append('rect').attr('x', 140).attr('y', -16).attr('width', 10).attr('height', 10).attr('fill', '#22c55e');
            svg.append('text').attr('x', 154).attr('y', -8).text('Drop').style('fill', '#9ca3af').style('font-size', '9px');
            svg.append('line').attr('x1', 200).attr('x2', 220).attr('y1', -11).attr('y2', -11).attr('stroke', '#eab308').attr('stroke-width', 2);
            svg.append('text').attr('x', 224).attr('y', -8).text('Same-Day %').style('fill', '#9ca3af').style('font-size', '9px');
        },

        renderArgumentScoresChart(d3) {
            const data = this.chartData.argument_scores;
            if (!data || !data.labels?.length) return;

            const el = this.clearChart('chart-argument-scores');
            if (!el) return;

            const margin = {top: 5, right: 30, bottom: 5, left: 150};
            const width = el.clientWidth - margin.left - margin.right;
            const height = 110 - margin.top - margin.bottom;
            const svg = d3.select(el).append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g').attr('transform', `translate(${margin.left},${margin.top})`);
            const labels = data.labels.slice(0, 5);
            const scores = data.scores.slice(0, 5);
            const y = d3.scaleBand().domain(labels).range([0, height]).padding(0.3);
            const x = d3.scaleLinear().domain([0, 100]).range([0, width]);
            svg.append('g').call(d3.axisLeft(y))
                .selectAll('text').style('fill', '#9ca3af').style('font-size', '8px');
            svg.selectAll('.bar').data(scores).enter().append('rect')
                .attr('y', (d, i) => y(labels[i]))
                .attr('x', 0)
                .attr('height', y.bandwidth())
                .attr('width', d => x(d))
                .attr('fill', d => d >= 75 ? '#22c55e' : (d >= 50 ? '#eab308' : '#6b7280'))
                .attr('opacity', 0.8).attr('rx', 2);
            svg.selectAll('.score-label').data(scores).enter().append('text')
                .attr('y', (d, i) => y(labels[i]) + y.bandwidth() / 2 + 3)
                .attr('x', d => x(d) + 4)
                .text(d => d)
                .style('fill', '#9ca3af').style('font-size', '9px').style('font-weight', '600');
        }
    };
}
