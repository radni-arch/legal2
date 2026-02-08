# Sprint: Fix D3 Chart Rendering in EpredmetWidget

**Sprint ID**: EPREDMET-D3-FIX  
**Created**: 2026-02-01  
**Priority**: Critical  
**Estimated Duration**: 3 days

---

## Executive Summary

The EpredmetWidget Analytics tab contains D3.js visualizations that fail to render despite no console errors. This is a complex interaction issue between Livewire 3, Alpine.js, and D3.js where timing, scope isolation, and DOM lifecycle create a "silent failure" pattern.

### Root Cause Analysis

| Issue | Impact | Evidence |
|-------|--------|----------|
| Dual D3 Loading | Race condition, undefined behavior | CDN in `app.blade.php` + npm in `bootstrap.js` |
| `@script` Scope Isolation | `window.d3` not accessible | Livewire 3's script blocks run in Alpine's isolated scope |
| DOM Timing | Zero-dimension containers | Charts render before elements exist post-morph |
| Event Timing | Missed events | `$wire.on()` registered after event fires |
| Tab Visibility | Hidden containers have no dimensions | Analytics tab may be collapsed when data loads |

---

## Required Skills & Resources

### Agent Skills to Load

```
/mnt/skills/user/tall-specialist.md     → Livewire 3 + Alpine.js patterns
/mnt/skills/user/d3-viz/SKILL.md        → D3.js v7 best practices
```

### Key Files

| File | Purpose |
|------|---------|
| `app/Http/Livewire/EpredmetWidget.php` | Main Livewire component |
| `resources/views/livewire/epredmet-widget.blade.php` | Template with @script block |
| `resources/views/livewire/partials/epredmet-analytics.blade.php` | Analytics tab with chart containers |
| `resources/js/bootstrap.js` | D3 import and window assignment |
| `resources/views/components/layouts/app.blade.php` | Layout with CDN D3 |
| `resources/js/app.js` | Main JS entry point |

---

## Task 1: Diagnostic Investigation

**ID**: EPREDMET-D3-001  
**Priority**: P0 - Blocker Investigation  
**Estimate**: 1 hour  
**Skills**: `d3-viz`, `tall-specialist.md`

### Objective

Determine the exact failure point by adding comprehensive diagnostics that reveal the timing and availability of D3.js at each stage of the rendering pipeline.

### Problem Statement

Charts silently fail to render with no console errors. We need visibility into:
1. When D3 becomes available on `window`
2. Whether DOM elements exist when render is called
3. If `$wire.chartData` contains expected data
4. Which render path is actually executing (event vs watch vs initial)

### Implementation Steps

#### Step 1: Add Timing Diagnostics to @script Block

Replace the opening of the `@script` block in `epredmet-widget.blade.php`:

```javascript
@script
<script>
    // ═══════════════════════════════════════════════════════════════
    // DIAGNOSTIC BLOCK - Remove after debugging
    // ═══════════════════════════════════════════════════════════════
    const DIAG_PREFIX = '[EPREDMET-D3-DIAG]';
    
    const diag = {
        timestamp: () => performance.now().toFixed(2),
        log: (msg, data = null) => {
            const entry = `${DIAG_PREFIX} [${diag.timestamp()}ms] ${msg}`;
            if (data !== null) {
                console.log(entry, data);
            } else {
                console.log(entry);
            }
        }
    };
    
    diag.log('Script block executing');
    diag.log('document.readyState', document.readyState);
    diag.log('window.d3 type', typeof window.d3);
    diag.log('window.d3 version', window.d3?.version);
    diag.log('$wire available', typeof $wire !== 'undefined');
    diag.log('$wire.chartData', $wire?.chartData ? Object.keys($wire.chartData) : 'null/undefined');
    
    // Check for DOM elements
    const chartContainers = [
        'chart-yearly-trend',
        'chart-same-day-donut', 
        'chart-court-bar',
        'chart-regional-bar'
    ];
    
    chartContainers.forEach(id => {
        const el = document.getElementById(id);
        diag.log(`DOM #${id}`, el ? `exists, width=${el.clientWidth}` : 'NOT FOUND');
    });
    
    // Monitor D3 availability
    if (typeof window.d3 === 'undefined') {
        diag.log('D3 NOT YET AVAILABLE - setting up poller');
        const d3Poller = setInterval(() => {
            if (typeof window.d3 !== 'undefined') {
                diag.log('D3 BECAME AVAILABLE', window.d3.version);
                clearInterval(d3Poller);
            }
        }, 50);
        setTimeout(() => clearInterval(d3Poller), 5000); // Stop after 5s
    }
    
    // ═══════════════════════════════════════════════════════════════
    // END DIAGNOSTIC BLOCK
    // ═══════════════════════════════════════════════════════════════
```

#### Step 2: Add Render Function Diagnostics

Wrap each render function entry point:

```javascript
window.epredmetRenderAllCharts = function(data) {
    diag.log('renderAllCharts CALLED', data ? Object.keys(data) : 'NO DATA');
    
    if (!data) {
        diag.log('ABORT: No data provided');
        return;
    }
    
    if (!window.d3) {
        diag.log('ABORT: window.d3 is undefined');
        return;
    }
    
    // Check each chart container before rendering
    const containers = {
        'chart-yearly-trend': data.yearly_trend,
        'chart-same-day-donut': data.same_day_donut,
        'chart-court-bar': data.court_bar,
    };
    
    Object.entries(containers).forEach(([id, chartData]) => {
        const el = document.getElementById(id);
        diag.log(`Pre-render #${id}`, {
            elementExists: !!el,
            width: el?.clientWidth,
            height: el?.clientHeight,
            visible: el?.offsetParent !== null,
            dataPresent: !!chartData
        });
    });
    
    // Continue with actual rendering...
};
```

#### Step 3: Monitor Event Flow

Add logging to all event listeners:

```javascript
$wire.on('epredmet-charts-updated', () => {
    diag.log('EVENT RECEIVED: epredmet-charts-updated');
    diag.log('$wire.chartData at event time', $wire.chartData ? Object.keys($wire.chartData) : 'empty');
});

$wire.$watch('chartData', (value) => {
    diag.log('WATCH TRIGGERED: chartData changed');
    diag.log('New chartData keys', value ? Object.keys(value) : 'null');
});
```

### Expected Diagnostic Output

After running with diagnostics, you should see output like:

```
[EPREDMET-D3-DIAG] [12.45ms] Script block executing
[EPREDMET-D3-DIAG] [12.52ms] document.readyState loading
[EPREDMET-D3-DIAG] [12.58ms] window.d3 type undefined        ← PROBLEM!
[EPREDMET-D3-DIAG] [245.30ms] D3 BECAME AVAILABLE 7.9.0
[EPREDMET-D3-DIAG] [1205.00ms] EVENT RECEIVED: epredmet-charts-updated
[EPREDMET-D3-DIAG] [1205.50ms] renderAllCharts CALLED [yearly_trend, ...]
[EPREDMET-D3-DIAG] [1206.00ms] Pre-render #chart-yearly-trend { elementExists: false, ... } ← PROBLEM!
```

### Acceptance Criteria

- [ ] Diagnostic output clearly shows D3 availability timeline
- [ ] Can identify if DOM elements exist at render time
- [ ] Can confirm data is present when render is called
- [ ] Document findings in task notes before proceeding

### Deliverables

1. Modified `epredmet-widget.blade.php` with diagnostic code
2. Investigation notes documenting exact failure point
3. Screenshot/paste of console output showing the issue

---

## Task 2: Consolidate D3 Loading Strategy

**ID**: EPREDMET-D3-002  
**Priority**: P0 - Critical  
**Estimate**: 30 minutes  
**Skills**: `d3-viz`  
**Depends On**: Task 1 (diagnosis confirms dual-loading issue)

### Objective

Eliminate the dual D3 loading that causes race conditions and ensure D3 is available synchronously before any chart code executes.

### Problem Statement

D3 is currently loaded in two places:

1. **CDN Script** in `resources/views/components/layouts/app.blade.php`:
   ```html
   <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.9.0/d3.min.js" ...></script>
   ```

2. **NPM Import** in `resources/js/bootstrap.js`:
   ```javascript
   import * as d3 from 'd3';
   window.d3 = d3;
   ```

The CDN script loads synchronously in `<head>`, while the npm bundle loads asynchronously via Vite. This creates a race condition where:
- Sometimes CDN wins → `window.d3` available immediately
- Sometimes npm wins → `window.d3` overwritten
- Sometimes neither is ready when `@script` executes

### Implementation Steps

#### Step 1: Remove CDN Script from Layout

Edit `resources/views/components/layouts/app.blade.php`:

```diff
 <head>
     <meta charset="utf-8">
     <meta name="viewport" content="width=device-width, initial-scale=1">
-    <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.9.0/d3.min.js" integrity="sha512-vc58qvvBdrDR4etbxMdlTt4GBQk1qjvyORR2nrsPsFPyrs+/u5c3+1Ct6upOgdZoIl7eq6k3a1UPDSNAQi/32A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
     <title>{{ $title ?? 'MCP Tools' }}</title>
     @vite(['resources/css/app.css','resources/js/app.js'])
```

#### Step 2: Ensure Synchronous D3 Assignment in Bootstrap

Verify `resources/js/bootstrap.js` has D3 at the top:

```javascript
// resources/js/bootstrap.js
// D3 MUST be imported and assigned FIRST before anything else
import * as d3 from 'd3';
window.d3 = d3;

// Verify assignment
if (typeof window.d3 === 'undefined') {
    console.error('[CRITICAL] D3 failed to load - charts will not render');
}

// Rest of imports...
import axios from 'axios';
// ...
```

#### Step 3: Add D3 Ready Event

Create a custom event that fires when D3 is confirmed available:

```javascript
// In bootstrap.js, after window.d3 = d3
window.d3 = d3;

// Dispatch event for any waiting code
window.dispatchEvent(new CustomEvent('d3:ready', { detail: { version: d3.version } }));

// Also set a flag for synchronous checks
window.d3Ready = true;
```

#### Step 4: Verify in Dashboard Layout

Also check `resources/views/dashboard.blade.php` for duplicate CDN loading and remove:

```diff
 <head>
     <meta charset="utf-8">
     <meta name="viewport" content="width=device-width, initial-scale=1">
-    <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.9.0/d3.min.js" ...></script>
     <title>Unified Dashboard</title>
     @vite(['resources/css/app.css'])
```

**Note**: The dashboard uses `@vite(['resources/css/app.css'])` but NOT `resources/js/app.js`. This needs to be added:

```diff
     <title>Unified Dashboard</title>
-    @vite(['resources/css/app.css'])
+    @vite(['resources/css/app.css', 'resources/js/app.js'])
     @livewireStyles
```

### Verification Steps

After changes, verify in browser console:

```javascript
// Should work immediately after page load
console.log(window.d3.version); // "7.9.0"
console.log(window.d3Ready);    // true
```

### Acceptance Criteria

- [ ] Only ONE D3 source (npm bundle via Vite)
- [ ] `window.d3` available immediately when any JS executes
- [ ] `window.d3Ready` flag set for synchronous availability checks
- [ ] `d3:ready` event dispatched for async listeners
- [ ] No console errors about D3

### Rollback Plan

If issues arise, temporarily restore CDN while debugging:
```html
<script>
    // Fallback: ensure D3 exists even if bundle fails
    if (typeof window.d3 === 'undefined') {
        document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.9.0/d3.min.js"><\/script>');
    }
</script>
```

---

## Task 3: Replace @script with Alpine Component Pattern

**ID**: EPREDMET-D3-003  
**Priority**: P0 - Critical  
**Estimate**: 2 hours  
**Skills**: `tall-specialist.md`, `d3-viz`  
**Depends On**: Task 2

### Objective

Replace the Livewire 3 `@script` block with a proper Alpine.js component that has full access to `window.d3` and proper lifecycle management.

### Problem Statement

Livewire 3's `@script` blocks run in an isolated Alpine.js scope. While `$wire` is available, access to global variables like `window.d3` can be unreliable due to:

1. Script execution timing relative to Vite bundle loading
2. Alpine's scope isolation preventing direct window access in some contexts
3. Lack of proper lifecycle hooks for component mounting

The Alpine component pattern provides:
- Explicit initialization via `init()`
- Proper `$watch` integration with Livewire properties
- Full access to `window` globals
- Clean separation of concerns

### Implementation Steps

#### Step 1: Create Alpine Component File

Create `resources/js/components/epredmet-charts.js`:

```javascript
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
        
        /**
         * Alpine init lifecycle hook
         * Called automatically when component mounts
         */
        init() {
            console.log('[EpredmetCharts] Initializing Alpine component');
            
            // Verify D3 is available
            if (!this.verifyD3()) {
                this.waitForD3().then(() => this.setupWatchers());
                return;
            }
            
            this.setupWatchers();
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
                
                // Listen for custom event
                window.addEventListener('d3:ready', () => resolve(), { once: true });
                
                // Also poll as backup
                const interval = setInterval(() => {
                    if (window.d3) {
                        clearInterval(interval);
                        resolve();
                    }
                }, 50);
                
                // Timeout after 5 seconds
                setTimeout(() => {
                    clearInterval(interval);
                    console.error('[EpredmetCharts] D3 failed to load within timeout');
                    resolve(); // Continue anyway, will fail gracefully
                }, 5000);
            });
        },
        
        /**
         * Setup Livewire property watchers
         */
        setupWatchers() {
            console.log('[EpredmetCharts] Setting up watchers');
            this.isInitialized = true;
            
            // Watch for chartData changes from Livewire
            this.$wire.$watch('chartData', (value) => {
                console.log('[EpredmetCharts] chartData changed:', value ? Object.keys(value) : 'null');
                if (value && Object.keys(value).length > 0) {
                    this.chartData = value;
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
            
            // Check if container exists and has dimensions
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
         * Main render orchestrator
         */
        renderAllCharts() {
            if (!this.chartData || !window.d3) {
                console.warn('[EpredmetCharts] Cannot render: missing data or D3');
                return;
            }
            
            const d3 = window.d3;
            
            try {
                this.renderTrendChart(d3);
                this.renderDonutChart(d3);
                this.renderBarChart(d3, 'chart-court-bar', this.chartData.court_bar?.labels, this.chartData.court_bar?.warrants, '#eab308');
                this.renderBarChart(d3, 'chart-regional-bar', this.chartData.regional_bar?.labels, this.chartData.regional_bar?.per_100k, '#3b82f6');
                this.renderHHIChart(d3);
                this.renderDistChart(d3);
                this.renderJudgeProfileChart(d3);
                this.renderPoliceApprovalChart(d3);
                this.renderMonthlyTrendChart(d3);
                this.renderArgumentScoresChart(d3);
                
                console.log('[EpredmetCharts] All charts rendered successfully');
            } catch (error) {
                console.error('[EpredmetCharts] Render error:', error);
            }
        },
        
        /**
         * Clear a chart container before re-rendering
         */
        clearChart(elementId) {
            const el = document.getElementById(elementId);
            if (el) {
                el.innerHTML = '';
            }
            return el;
        },
        
        /**
         * Render yearly trend chart (bars + line)
         */
        renderTrendChart(d3) {
            const data = this.chartData.yearly_trend;
            if (!data || !data.labels?.length) return;
            
            const el = this.clearChart('chart-yearly-trend');
            if (!el || el.clientWidth === 0) return;
            
            const margin = { top: 20, right: 50, bottom: 30, left: 50 };
            const width = el.clientWidth - margin.left - margin.right;
            const height = 220 - margin.top - margin.bottom;
            
            const svg = d3.select(el)
                .append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g')
                .attr('transform', `translate(${margin.left},${margin.top})`);
            
            // Scales
            const x = d3.scalePoint()
                .domain(data.labels.map(String))
                .range([0, width])
                .padding(0.5);
            
            const yLeft = d3.scaleLinear()
                .domain([0, d3.max(data.warrants) * 1.1])
                .range([height, 0]);
            
            const yRight = d3.scaleLinear()
                .domain([0, 100])
                .range([height, 0]);
            
            // Axes
            svg.append('g')
                .attr('transform', `translate(0,${height})`)
                .call(d3.axisBottom(x))
                .selectAll('text')
                .style('fill', '#9ca3af')
                .style('font-size', '10px');
            
            svg.append('g')
                .call(d3.axisLeft(yLeft).ticks(5))
                .selectAll('text')
                .style('fill', '#9ca3af')
                .style('font-size', '10px');
            
            svg.append('g')
                .attr('transform', `translate(${width},0)`)
                .call(d3.axisRight(yRight).ticks(5).tickFormat(d => d + '%'))
                .selectAll('text')
                .style('fill', '#9ca3af')
                .style('font-size', '10px');
            
            // Bars
            svg.selectAll('.bar')
                .data(data.labels)
                .enter()
                .append('rect')
                .attr('x', (d, i) => x(String(d)) - 12)
                .attr('y', (d, i) => yLeft(data.warrants[i]))
                .attr('width', 24)
                .attr('height', (d, i) => height - yLeft(data.warrants[i]))
                .attr('fill', '#eab308')
                .attr('opacity', 0.6)
                .attr('rx', 3);
            
            // Line
            const line = d3.line()
                .x((d, i) => x(String(data.labels[i])))
                .y((d, i) => yRight(data.same_day_pct[i]));
            
            svg.append('path')
                .datum(data.same_day_pct)
                .attr('fill', 'none')
                .attr('stroke', '#ef4444')
                .attr('stroke-width', 2.5)
                .attr('d', line);
            
            // Dots
            svg.selectAll('.dot-sd')
                .data(data.same_day_pct)
                .enter()
                .append('circle')
                .attr('cx', (d, i) => x(String(data.labels[i])))
                .attr('cy', d => yRight(d))
                .attr('r', 4)
                .attr('fill', '#ef4444');
            
            // Legend
            svg.append('rect').attr('x', 10).attr('y', -10).attr('width', 12).attr('height', 12).attr('fill', '#eab308').attr('opacity', 0.6);
            svg.append('text').attr('x', 26).attr('y', 0).text('Warrants').style('fill', '#9ca3af').style('font-size', '10px');
            svg.append('line').attr('x1', 100).attr('x2', 120).attr('y1', -4).attr('y2', -4).attr('stroke', '#ef4444').attr('stroke-width', 2.5);
            svg.append('text').attr('x', 124).attr('y', 0).text('Same-Day %').style('fill', '#9ca3af').style('font-size', '10px');
        },
        
        /**
         * Render donut chart for same-day ratio
         */
        renderDonutChart(d3) {
            const data = this.chartData.same_day_donut;
            if (!data) return;
            
            const el = this.clearChart('chart-same-day-donut');
            if (!el) return;
            
            const w = el.clientWidth;
            const h = 220;
            const radius = Math.min(w, h) / 2 - 20;
            
            const svg = d3.select(el)
                .append('svg')
                .attr('width', w)
                .attr('height', h)
                .append('g')
                .attr('transform', `translate(${w/2},${h/2})`);
            
            const pie = d3.pie().value(d => d.value).sort(null);
            const arc = d3.arc().innerRadius(radius * 0.55).outerRadius(radius);
            
            const dataset = [
                { label: 'Same-Day', value: data.same_day, color: '#ef4444' },
                { label: 'Other', value: data.not_same_day, color: '#374151' }
            ];
            
            svg.selectAll('path')
                .data(pie(dataset))
                .enter()
                .append('path')
                .attr('d', arc)
                .attr('fill', d => d.data.color)
                .attr('stroke', 'rgba(0,0,0,0.3)')
                .attr('stroke-width', 1);
            
            const total = data.same_day + data.not_same_day;
            const pct = total > 0 ? Math.round(100 * data.same_day / total) : 0;
            
            svg.append('text')
                .attr('text-anchor', 'middle')
                .attr('dy', '-0.2em')
                .style('fill', pct > 80 ? '#ef4444' : '#eab308')
                .style('font-size', '1.8rem')
                .style('font-weight', '700')
                .text(pct + '%');
            
            svg.append('text')
                .attr('text-anchor', 'middle')
                .attr('dy', '1.3em')
                .style('fill', '#9ca3af')
                .style('font-size', '0.65rem')
                .text('same-day');
        },
        
        /**
         * Generic bar chart renderer
         */
        renderBarChart(d3, containerId, labels, values, color) {
            if (!labels || !values || labels.length === 0) return;
            
            const el = this.clearChart(containerId);
            if (!el) return;
            
            const margin = { top: 5, right: 10, bottom: 60, left: 45 };
            const width = el.clientWidth - margin.left - margin.right;
            const height = 200 - margin.top - margin.bottom;
            
            const svg = d3.select(el)
                .append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g')
                .attr('transform', `translate(${margin.left},${margin.top})`);
            
            const x = d3.scaleBand().domain(labels).range([0, width]).padding(0.3);
            const y = d3.scaleLinear().domain([0, d3.max(values) * 1.1]).range([height, 0]);
            
            svg.append('g')
                .attr('transform', `translate(0,${height})`)
                .call(d3.axisBottom(x))
                .selectAll('text')
                .attr('transform', 'rotate(-40)')
                .style('text-anchor', 'end')
                .style('fill', '#9ca3af')
                .style('font-size', '8px');
            
            svg.append('g')
                .call(d3.axisLeft(y).ticks(5))
                .selectAll('text')
                .style('fill', '#9ca3af')
                .style('font-size', '9px');
            
            svg.selectAll('.bar')
                .data(values)
                .enter()
                .append('rect')
                .attr('x', (d, i) => x(labels[i]))
                .attr('y', d => y(d))
                .attr('width', x.bandwidth())
                .attr('height', d => height - y(d))
                .attr('fill', color)
                .attr('opacity', 0.8)
                .attr('rx', 2);
        },
        
        // ... Additional chart methods follow the same pattern
        // renderHHIChart, renderDistChart, renderJudgeProfileChart, 
        // renderPoliceApprovalChart, renderMonthlyTrendChart, renderArgumentScoresChart
        
        renderHHIChart(d3) {
            const data = this.chartData.hhi_bar;
            if (!data || !data.labels?.length) return;
            
            const el = this.clearChart('chart-hhi-bar');
            if (!el) return;
            
            // Implementation matches existing window.epredmetRenderHHIChart
            // Copy from existing @script block
        },
        
        renderDistChart(d3) {
            const data = this.chartData.processing_dist;
            if (!data || !data.labels?.length) return;
            
            const el = this.clearChart('chart-processing-dist');
            if (!el) return;
            
            // Implementation matches existing
        },
        
        renderJudgeProfileChart(d3) {
            const data = this.chartData.judge_profile_bar;
            if (!data || !data.labels?.length) return;
            
            const el = this.clearChart('chart-judge-profile');
            if (!el) return;
            
            // Implementation matches existing
        },
        
        renderPoliceApprovalChart(d3) {
            const data = this.chartData.police_approval_bar;
            if (!data || !data.labels?.length) return;
            
            const el = this.clearChart('chart-police-approval');
            if (!el) return;
            
            // Implementation matches existing
        },
        
        renderMonthlyTrendChart(d3) {
            const data = this.chartData.monthly_trend;
            if (!data || !data.labels?.length) return;
            
            const el = this.clearChart('chart-monthly-trend');
            if (!el) return;
            
            // Implementation matches existing
        },
        
        renderArgumentScoresChart(d3) {
            const data = this.chartData.argument_scores;
            if (!data || !data.labels?.length) return;
            
            const el = this.clearChart('chart-argument-scores');
            if (!el) return;
            
            // Implementation matches existing
        }
    };
}
```

#### Step 2: Register Alpine Component

Edit `resources/js/app.js`:

```javascript
import './bootstrap';
import './navigation-shortcuts.js';
import { renderCitationGraph } from './components/citation-graph';
import './components/pdf-viewer';
import ForceGraph from './components/ForceGraph';
import epredmetCharts from './components/epredmet-charts'; // ADD THIS

// ... existing hljs setup ...

// Register Alpine components BEFORE Alpine starts
document.addEventListener('alpine:init', () => {
    Alpine.data('epredmetCharts', epredmetCharts);
});

// Make ForceGraph available globally for Alpine
window.ForceGraph = ForceGraph;
```

#### Step 3: Update Blade Template

Edit `resources/views/livewire/partials/epredmet-analytics.blade.php`:

Replace the opening `<div>` tag:

```diff
-<div dusk="analytics-panel">
+<div dusk="analytics-panel" x-data="epredmetCharts">
```

#### Step 4: Remove @script Block

Edit `resources/views/livewire/epredmet-widget.blade.php`:

Delete the entire `@script` ... `@endscript` block (approximately 200 lines at the bottom of the file).

### Verification Steps

1. Open Dashboard
2. Expand EpredmetWidget
3. Click "Analytics" tab
4. Click "Refresh" to load data
5. Verify charts render
6. Check console for `[EpredmetCharts]` log messages

### Acceptance Criteria

- [ ] Alpine component registered and initializing
- [ ] D3 access confirmed in component
- [ ] Charts render on data load
- [ ] Charts re-render on year filter change
- [ ] No console errors
- [ ] `@script` block completely removed

---

## Task 4: Implement Deferred Rendering with MutationObserver

**ID**: EPREDMET-D3-004  
**Priority**: P1 - High  
**Estimate**: 1 hour  
**Skills**: `d3-viz`, `tall-specialist.md`  
**Depends On**: Task 3

### Objective

Ensure charts only render when their container elements exist in the DOM and have valid dimensions, using MutationObserver for reliable detection.

### Problem Statement

After Livewire morphs the DOM (e.g., tab switch, filter change), chart containers may not exist or may have zero dimensions when the render function is called. The current retry loop is fragile and can miss elements.

### Implementation Steps

#### Step 1: Add DOM Readiness Utilities to Alpine Component

Add to `epredmet-charts.js`:

```javascript
/**
 * Wait for an element to exist in DOM with valid dimensions
 * @param {string} selector - CSS selector
 * @param {number} timeout - Max wait time in ms
 * @returns {Promise<HTMLElement>}
 */
waitForElement(selector, timeout = 5000) {
    return new Promise((resolve, reject) => {
        const startTime = Date.now();
        
        // Check immediately
        const el = document.querySelector(selector);
        if (el && el.clientWidth > 0) {
            resolve(el);
            return;
        }
        
        // Setup MutationObserver
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
        
        // Also set timeout as fallback
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
 * Wait for multiple elements
 * @param {string[]} selectors - Array of CSS selectors
 * @returns {Promise<HTMLElement[]>}
 */
async waitForElements(selectors) {
    const results = await Promise.allSettled(
        selectors.map(sel => this.waitForElement(sel, 3000))
    );
    
    return results
        .filter(r => r.status === 'fulfilled')
        .map(r => r.value);
}
```

#### Step 2: Update Render Methods to Use Waiters

```javascript
async renderAllCharts() {
    if (!this.chartData || !window.d3) {
        console.warn('[EpredmetCharts] Cannot render: missing data or D3');
        return;
    }
    
    const d3 = window.d3;
    
    // Define chart containers
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
    
    // Render each chart when its container is ready
    for (const config of chartConfigs) {
        try {
            await this.waitForElement(`#${config.id}`, 2000);
            config.render();
            console.log(`[EpredmetCharts] Rendered ${config.id}`);
        } catch (error) {
            console.warn(`[EpredmetCharts] Skipped ${config.id}: ${error.message}`);
        }
    }
}
```

### Acceptance Criteria

- [ ] MutationObserver correctly detects when elements appear
- [ ] Charts render only when container has width > 0
- [ ] Graceful handling when elements never appear (timeout)
- [ ] Console logs show which charts rendered vs skipped

---

## Task 5: Add Livewire Hook for Post-Morph Rendering

**ID**: EPREDMET-D3-005  
**Priority**: P1 - High  
**Estimate**: 45 minutes  
**Skills**: `tall-specialist.md`  
**Depends On**: Task 3

### Objective

Ensure charts re-render correctly after Livewire morphs the DOM (e.g., after filter changes, tab switches, or polling updates).

### Problem Statement

When Livewire updates the DOM, it may:
1. Replace chart container elements entirely
2. Trigger while charts are mid-render
3. Update data without triggering the `$watch` (if data reference is same)

### Implementation Steps

#### Step 1: Add Livewire Hook Registration

Add to `resources/js/app.js` (after Alpine component registration):

```javascript
// Livewire morph hook for chart re-rendering
document.addEventListener('livewire:init', () => {
    let renderDebounce = null;
    
    Livewire.hook('morph.updated', ({ el, component }) => {
        // Only handle EpredmetWidget morphs
        if (!el.closest('.epredmet-widget')) return;
        
        // Check if this is the analytics panel
        const analyticsPanel = el.closest('[dusk="analytics-panel"]');
        if (!analyticsPanel) return;
        
        console.log('[Livewire Hook] EpredmetWidget analytics panel morphed');
        
        // Debounce rapid morphs
        clearTimeout(renderDebounce);
        renderDebounce = setTimeout(() => {
            // Get Alpine component instance
            const alpineEl = analyticsPanel.closest('[x-data]');
            if (alpineEl && alpineEl._x_dataStack) {
                const alpineData = alpineEl._x_dataStack[0];
                if (alpineData && typeof alpineData.renderAllCharts === 'function') {
                    console.log('[Livewire Hook] Triggering chart re-render');
                    alpineData.scheduleRender();
                }
            }
        }, 150);
    });
});
```

#### Step 2: Add Component Re-initialization Detection

Add to Alpine component:

```javascript
init() {
    console.log('[EpredmetCharts] Initializing');
    
    // Mark element with instance ID for morph detection
    this.$el.setAttribute('data-epredmet-instance', Date.now());
    
    // Setup initialization
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
}
```

### Acceptance Criteria

- [ ] Charts re-render after Livewire morph
- [ ] Debouncing prevents excessive re-renders
- [ ] Only EpredmetWidget morphs trigger re-render
- [ ] Navigation between pages doesn't break charts

---

## Task 6: Implement SVG Cleanup Before Render

**ID**: EPREDMET-D3-006  
**Priority**: P1 - High  
**Estimate**: 30 minutes  
**Skills**: `d3-viz`  
**Depends On**: Task 3

### Objective

Ensure clean chart renders by properly removing old SVG elements and preventing memory leaks from accumulated DOM nodes.

### Problem Statement

Multiple renders (from polling, filter changes, or re-initialization) can stack SVG elements, causing:
1. Visual artifacts (overlapping charts)
2. Memory leaks (thousands of DOM nodes)
3. Performance degradation

### Implementation Steps

#### Step 1: Enhance clearChart Method

```javascript
/**
 * Thoroughly clear a chart container
 * @param {string} elementId 
 * @returns {HTMLElement|null}
 */
clearChart(elementId) {
    const el = document.getElementById(elementId);
    if (!el) {
        console.warn(`[EpredmetCharts] Container #${elementId} not found`);
        return null;
    }
    
    // Remove all D3-created elements
    const d3 = window.d3;
    if (d3) {
        const selection = d3.select(el);
        
        // Remove all child elements
        selection.selectAll('*').remove();
        
        // Also remove any event listeners D3 may have attached
        selection.on('.', null);
    }
    
    // Fallback: clear innerHTML
    el.innerHTML = '';
    
    return el;
}
```

#### Step 2: Add Cleanup on Component Destroy

```javascript
// In Alpine component
destroy() {
    console.log('[EpredmetCharts] Destroying component, cleaning up');
    
    const chartIds = [
        'chart-yearly-trend',
        'chart-same-day-donut',
        'chart-court-bar',
        'chart-regional-bar',
        'chart-hhi-bar',
        'chart-processing-dist',
        'chart-judge-profile',
        'chart-police-approval',
        'chart-monthly-trend',
        'chart-argument-scores'
    ];
    
    chartIds.forEach(id => this.clearChart(id));
}
```

### Acceptance Criteria

- [ ] No duplicate SVG elements after multiple renders
- [ ] Memory doesn't grow with repeated renders (check DevTools Memory)
- [ ] Clean slate before each render

---

## Task 7: Add Visibility-Aware Rendering

**ID**: EPREDMET-D3-007  
**Priority**: P2 - Medium  
**Estimate**: 1 hour  
**Skills**: `tall-specialist.md`, `d3-viz`  
**Depends On**: Task 4

### Objective

Only render charts when their containers are actually visible to the user, and re-render when they become visible.

### Problem Statement

The Analytics tab may be collapsed or hidden when data loads. Rendering to invisible containers:
1. Results in zero-dimension SVGs
2. Wastes CPU cycles
3. May produce incorrect layouts

### Implementation Steps

#### Step 1: Add Visibility Detection

```javascript
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
}
```

#### Step 2: Add IntersectionObserver for Lazy Rendering

```javascript
setupVisibilityObserver() {
    if (this.visibilityObserver) {
        this.visibilityObserver.disconnect();
    }
    
    this.visibilityObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && entry.intersectionRatio > 0.1) {
                const chartId = entry.target.id;
                console.log(`[EpredmetCharts] Chart container ${chartId} became visible`);
                
                // Check if this chart needs rendering
                if (this.chartData && !entry.target.querySelector('svg')) {
                    this.renderSingleChart(chartId);
                }
            }
        });
    }, {
        threshold: [0, 0.1, 0.5, 1.0],
        rootMargin: '50px'
    });
    
    // Observe all chart containers
    const chartIds = [
        'chart-yearly-trend',
        'chart-same-day-donut',
        // ... etc
    ];
    
    chartIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            this.visibilityObserver.observe(el);
        }
    });
}
```

#### Step 3: Add Tab Visibility Handler

Since charts are in a collapsible tab, detect tab expansion:

```javascript
// In setupWatchers
const widget = this.$el.closest('.epredmet-widget');
if (widget) {
    // Watch for x-show changes on analytics tab
    const analyticsTab = widget.querySelector('[dusk="analytics-panel"]');
    if (analyticsTab) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(mut => {
                if (mut.type === 'attributes' && mut.attributeName === 'style') {
                    if (this.isElementVisible(analyticsTab)) {
                        console.log('[EpredmetCharts] Analytics tab became visible');
                        this.scheduleRender();
                    }
                }
            });
        });
        
        observer.observe(analyticsTab, { attributes: true });
    }
}
```

### Acceptance Criteria

- [ ] Charts don't render while tab is hidden
- [ ] Charts render immediately when tab becomes visible
- [ ] IntersectionObserver fires for newly visible containers
- [ ] No unnecessary re-renders for already-visible charts

---

## Task 8: Add Error Boundary and Fallback UI

**ID**: EPREDMET-D3-008  
**Priority**: P2 - Medium  
**Estimate**: 45 minutes  
**Skills**: `tall-specialist.md`  
**Depends On**: Task 3

### Objective

Provide graceful error handling and user feedback when charts fail to render.

### Implementation Steps

#### Step 1: Add Error State to Component

```javascript
// State
chartErrors: {},
globalError: null,

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
}

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
            <button onclick="document.querySelector('[x-data]').__x.$data.retrySingleChart('${chartId}')" 
                    class="text-xs px-2 py-1 bg-red-500/20 hover:bg-red-500/30 rounded text-red-400">
                Retry
            </button>
        </div>
    `;
}

/**
 * Retry rendering a single chart
 */
retrySingleChart(chartId) {
    console.log(`[EpredmetCharts] Retrying ${chartId}`);
    this.renderSingleChart(chartId);
}
```

#### Step 2: Add Global Retry Button to Template

In `epredmet-analytics.blade.php`, add near the refresh button:

```html
<button type="button" 
        @click="$data.renderAllCharts()" 
        class="btn-secondary epw-btn"
        x-show="Object.values($data.chartErrors || {}).some(e => e)"
        dusk="retry-all-charts">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
    </svg>
    Retry Charts
</button>
```

### Acceptance Criteria

- [ ] Failed charts show error message instead of blank
- [ ] Retry button allows re-attempting single chart
- [ ] Global retry button appears when any chart has errors
- [ ] Errors logged to console with full context

---

## Task 9: Write Integration Tests

**ID**: EPREDMET-D3-009  
**Priority**: P2 - Medium  
**Estimate**: 1 hour  
**Skills**: `tall-specialist.md`  
**Depends On**: Tasks 1-8

### Objective

Ensure chart rendering works reliably across user interactions with automated browser tests.

### Implementation Steps

#### Step 1: Create Dusk Test

Create `tests/Browser/EpredmetChartsTest.php`:

```php
<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class EpredmetChartsTest extends DuskTestCase
{
    /**
     * Test charts render on analytics tab
     */
    public function test_analytics_charts_render(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::first())
                ->visit('/dashboard')
                ->waitFor('@epredmet-widget')
                // Expand widget
                ->click('@epredmet-widget-toggle')
                ->waitFor('@tab-analytics')
                // Switch to analytics tab
                ->click('@tab-analytics')
                ->waitFor('@analytics-panel')
                // Load data
                ->click('@refresh-analytics')
                ->waitFor('@chart-yearly-trend svg', 10)
                // Assert SVG elements exist
                ->assertPresent('@chart-yearly-trend svg')
                ->assertPresent('@chart-same-day-donut svg')
                ->assertPresent('@chart-court-bar svg');
        });
    }
    
    /**
     * Test charts re-render on year filter change
     */
    public function test_charts_rerender_on_filter_change(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::first())
                ->visit('/dashboard')
                ->click('@epredmet-widget-toggle')
                ->click('@tab-analytics')
                ->click('@refresh-analytics')
                ->waitFor('@chart-yearly-trend svg', 10)
                // Change year filter
                ->select('@analytics-year', '2024')
                ->waitFor('@chart-yearly-trend svg', 10)
                // Verify chart updated (has new SVG)
                ->assertPresent('@chart-yearly-trend svg');
        });
    }
    
    /**
     * Test charts survive tab switching
     */
    public function test_charts_survive_tab_switch(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::first())
                ->visit('/dashboard')
                ->click('@epredmet-widget-toggle')
                ->click('@tab-analytics')
                ->click('@refresh-analytics')
                ->waitFor('@chart-yearly-trend svg', 10)
                // Switch to different tab
                ->click('@tab-lookup')
                ->pause(500)
                // Switch back
                ->click('@tab-analytics')
                ->waitFor('@chart-yearly-trend svg', 5)
                ->assertPresent('@chart-yearly-trend svg');
        });
    }
}
```

### Acceptance Criteria

- [ ] All three test cases pass
- [ ] Tests run in CI pipeline
- [ ] Tests don't flake (consistent results)

---

## Sprint Execution Checklist

### Day 1 - Foundation

| Task | Status | Assignee | Notes |
|------|--------|----------|-------|
| Task 1: Diagnostics | ⬜ | | |
| Task 2: D3 Consolidation | ⬜ | | |
| Task 3: Alpine Component | ⬜ | | |

### Day 2 - Reliability

| Task | Status | Assignee | Notes |
|------|--------|----------|-------|
| Task 4: MutationObserver | ⬜ | | |
| Task 5: Livewire Hooks | ⬜ | | |
| Task 6: SVG Cleanup | ⬜ | | |

### Day 3 - Polish

| Task | Status | Assignee | Notes |
|------|--------|----------|-------|
| Task 7: Visibility-Aware | ⬜ | | |
| Task 8: Error Boundary | ⬜ | | |
| Task 9: Integration Tests | ⬜ | | |

---

## Definition of Done

- [ ] All charts render on initial Analytics tab load
- [ ] Charts re-render correctly after filter changes
- [ ] Charts survive tab switching
- [ ] No console errors related to D3 or rendering
- [ ] Dusk tests pass
- [ ] Code reviewed and merged to main

---

## Rollback Plan

If sprint changes cause regressions:

1. Revert Alpine component changes
2. Restore `@script` block from git history
3. Restore CDN D3 loading as backup
4. File follow-up ticket for root cause analysis

---

## References

- [Livewire 3 JavaScript Hooks](https://livewire.laravel.com/docs/javascript#hooks)
- [Alpine.js Components](https://alpinejs.dev/globals/alpine-data)
- [D3.js API Reference](https://d3js.org/getting-started)
- [MutationObserver MDN](https://developer.mozilla.org/en-US/docs/Web/API/MutationObserver)
- [IntersectionObserver MDN](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API)
