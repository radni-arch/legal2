import axios from 'axios';
import * as d3 from 'd3';
window.d3 = d3;

// Dispatch event for any waiting code
window.dispatchEvent(new CustomEvent('d3:ready', { detail: { version: d3.version } }));
window.d3Ready = true;
import {Timeline} from "vis-timeline/peer";
import {DataSet, DataView, Queue} from "vis-data";
import "vis-timeline/styles/vis-timeline-graph2d.css";
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.dataset = DataSet;
window.timeline = Timeline;
window.Pusher = Pusher;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Laravel Echo configuration for Reverb
// Configure Laravel Echo with Reverb
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

//import './echo';
