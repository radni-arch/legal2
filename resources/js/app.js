import './bootstrap';
import './navigation-shortcuts.js';
import { renderCitationGraph } from './components/citation-graph';
import './components/pdf-viewer';
import ForceGraph from './components/ForceGraph';
import epredmetCharts from './components/epredmet-charts';

// Import highlight.js for Cypher syntax highlighting
import hljs from 'highlight.js/lib/core';
import cypher from 'highlightjs-cypher';
import 'highlight.js/styles/github-dark.css';

hljs.registerLanguage('cypher', cypher);

// Register Alpine components BEFORE Alpine starts
document.addEventListener('alpine:init', () => {
    Alpine.data('epredmetCharts', epredmetCharts);
});

// Auto-highlight on Livewire updates
document.addEventListener('livewire:navigated', () => {
    document.querySelectorAll('pre code.language-cypher').forEach((el) => {
        hljs.highlightElement(el);
    });
});

// Also highlight on morph updates
if (typeof Livewire !== 'undefined') {
    Livewire.hook('morph.updated', ({ el }) => {
        el.querySelectorAll('pre code.language-cypher').forEach((block) => {
            hljs.highlightElement(block);
        });
    });
}

// Livewire morph hook for chart re-rendering
document.addEventListener('livewire:init', () => {
    let renderDebounce = null;

    Livewire.hook('morph.updated', ({ el, component }) => {
        if (!el.closest('.epredmet-widget')) return;

        const analyticsPanel = el.closest('[dusk="analytics-panel"]');
        if (!analyticsPanel) return;

        console.log('[Livewire Hook] EpredmetWidget analytics panel morphed');

        clearTimeout(renderDebounce);
        renderDebounce = setTimeout(() => {
            const alpineEl = analyticsPanel.closest('[x-data]');
            if (alpineEl && alpineEl._x_dataStack) {
                const alpineData = alpineEl._x_dataStack[0];
                if (alpineData && typeof alpineData.scheduleRender === 'function') {
                    console.log('[Livewire Hook] Triggering chart re-render');
                    alpineData.scheduleRender();
                }
            }
        }, 150);
    });
});

// Make ForceGraph available globally for Alpine
window.ForceGraph = ForceGraph;
