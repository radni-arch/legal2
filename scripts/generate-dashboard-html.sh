#!/usr/bin/env bash
#######################################
# HTML Dashboard Generator
#
# Generates a self-contained HTML dashboard from metrics data.
# No server required - just open in a browser.
#
# Usage:
#   ./scripts/generate-dashboard-html.sh [output-path]
#
# Output:
#   documentation/metrics-dashboard.html (default)
#######################################

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Configuration
QUEUE_FILE="${ROOT_DIR}/test-results/tdd-test-queue.json"
METRICS_FILE="${ROOT_DIR}/test-results/metrics/autoloop.jsonl"
ITERATIONS_FILE="${ROOT_DIR}/test-results/logs/iterations.jsonl"
DEFAULT_OUTPUT="${ROOT_DIR}/documentation/metrics-dashboard.html"
OUTPUT_PATH="${1:-$DEFAULT_OUTPUT}"

echo "Generating HTML dashboard..."

#######################################
# Get queue statistics
#######################################
get_queue_stats() {
    if [[ ! -f "$QUEUE_FILE" ]]; then
        echo '{"done":0,"in_progress":0,"todo":0,"total":0}'
        return
    fi

    jq '{
        done: [.components | to_entries[] | select(.value.status == "done")] | length,
        in_progress: [.components | to_entries[] | select(.value.status == "in_progress")] | length,
        todo: [.components | to_entries[] | select(.value.status == "todo")] | length,
        total: [.components | to_entries[]] | length
    }' "$QUEUE_FILE"
}

#######################################
# Get recent failures
#######################################
get_recent_failures() {
    if [[ ! -f "$METRICS_FILE" ]]; then
        echo '[]'
        return
    fi

    jq -s '
        [.[] | select(.outcome != "pass")] |
        sort_by(.timestamp) |
        reverse |
        .[:10] |
        map({
            timestamp: .timestamp,
            component: .component_id,
            outcome: .outcome,
            duration: .duration_seconds
        })
    ' "$METRICS_FILE" 2>/dev/null || echo '[]'
}

#######################################
# Get failure hotspots
#######################################
get_failure_hotspots() {
    if [[ ! -f "$METRICS_FILE" ]]; then
        echo '[]'
        return
    fi

    jq -s '
        [.[] | select(.outcome != "pass")] |
        group_by(.component_id) |
        map({component: .[0].component_id, failures: length}) |
        sort_by(.failures) |
        reverse |
        .[:10]
    ' "$METRICS_FILE" 2>/dev/null || echo '[]'
}

#######################################
# Get daily stats
#######################################
get_daily_stats() {
    if [[ ! -f "$METRICS_FILE" ]]; then
        echo '[]'
        return
    fi

    jq -s '
        group_by(.date) |
        map({
            date: .[0].date,
            runs: length,
            passed: [.[] | select(.outcome == "pass")] | length,
            failed: [.[] | select(.outcome != "pass")] | length,
            avg_duration: ([.[].duration_seconds] | add / length | floor)
        }) |
        sort_by(.date) |
        reverse |
        .[:14]
    ' "$METRICS_FILE" 2>/dev/null || echo '[]'
}

#######################################
# Get domain progress
#######################################
get_domain_progress() {
    if [[ ! -f "$QUEUE_FILE" ]]; then
        echo '[]'
        return
    fi

    jq '
        .components | to_entries |
        group_by(if .value.domain then .value.domain else "unknown" end) |
        map({
            domain: (if .[0].value.domain then .[0].value.domain else "unknown" end),
            total: length,
            done: [.[] | select(.value.status == "done")] | length
        }) |
        sort_by(.domain)
    ' "$QUEUE_FILE"
}

# Collect data
QUEUE_STATS=$(get_queue_stats)
RECENT_FAILURES=$(get_recent_failures)
FAILURE_HOTSPOTS=$(get_failure_hotspots)
DAILY_STATS=$(get_daily_stats)
DOMAIN_PROGRESS=$(get_domain_progress)
GENERATED_AT=$(date -Iseconds)

# Generate HTML
cat > "$OUTPUT_PATH" << 'HTMLHEAD'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDD Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #1a1a2e;
            color: #eee;
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #4ecdc4; margin-bottom: 10px; }
        .generated { color: #666; font-size: 12px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card {
            background: #16213e;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .card h2 {
            color: #4ecdc4;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        .stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .stat {
            text-align: center;
            padding: 15px;
            background: #0f3460;
            border-radius: 6px;
        }
        .stat-value { font-size: 32px; font-weight: bold; }
        .stat-label { font-size: 11px; color: #888; text-transform: uppercase; }
        .stat-done .stat-value { color: #4ecdc4; }
        .stat-progress .stat-value { color: #ffd93d; }
        .stat-todo .stat-value { color: #888; }
        .stat-percent .stat-value { color: #ff6b6b; }
        .progress-bar {
            height: 8px;
            background: #333;
            border-radius: 4px;
            overflow: hidden;
            margin: 15px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4ecdc4, #44a08d);
            transition: width 0.3s;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #333; }
        th { color: #888; font-weight: normal; text-transform: uppercase; font-size: 11px; }
        tr:hover { background: #0f3460; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-pass { background: #4ecdc4; color: #000; }
        .badge-fail { background: #ff6b6b; color: #fff; }
        .badge-error { background: #ffd93d; color: #000; }
        .domain-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 8px 0;
        }
        .domain-name { width: 120px; font-size: 12px; overflow: hidden; text-overflow: ellipsis; }
        .domain-progress {
            flex: 1;
            height: 6px;
            background: #333;
            border-radius: 3px;
            overflow: hidden;
        }
        .domain-fill { height: 100%; background: #4ecdc4; }
        .domain-percent { width: 50px; text-align: right; font-size: 12px; color: #888; }
        .refresh-note { text-align: center; color: #666; font-size: 11px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>TDD Dashboard</h1>
HTMLHEAD

echo "        <p class=\"generated\">Generated: ${GENERATED_AT}</p>" >> "$OUTPUT_PATH"

cat >> "$OUTPUT_PATH" << 'HTMLGRID'
        <div class="grid">
            <!-- Queue Status -->
            <div class="card">
                <h2>Queue Status</h2>
                <div id="queue-stats"></div>
            </div>

            <!-- Domain Progress -->
            <div class="card">
                <h2>Domain Progress</h2>
                <div id="domain-progress"></div>
            </div>

            <!-- Recent Failures -->
            <div class="card" style="grid-column: span 2;">
                <h2>Recent Failures</h2>
                <div id="recent-failures"></div>
            </div>

            <!-- Failure Hotspots -->
            <div class="card">
                <h2>Failure Hotspots</h2>
                <div id="failure-hotspots"></div>
            </div>

            <!-- Daily Stats -->
            <div class="card">
                <h2>Daily Stats (Last 14 Days)</h2>
                <div id="daily-stats"></div>
            </div>
        </div>

        <p class="refresh-note">Regenerate with: ./scripts/generate-dashboard-html.sh</p>
    </div>

    <script>
HTMLGRID

# Inject data
echo "        const queueStats = ${QUEUE_STATS};" >> "$OUTPUT_PATH"
echo "        const recentFailures = ${RECENT_FAILURES};" >> "$OUTPUT_PATH"
echo "        const failureHotspots = ${FAILURE_HOTSPOTS};" >> "$OUTPUT_PATH"
echo "        const dailyStats = ${DAILY_STATS};" >> "$OUTPUT_PATH"
echo "        const domainProgress = ${DOMAIN_PROGRESS};" >> "$OUTPUT_PATH"

cat >> "$OUTPUT_PATH" << 'HTMLSCRIPT'

        // Render queue stats
        function renderQueueStats() {
            const el = document.getElementById('queue-stats');
            const percent = queueStats.total > 0 ? Math.floor(queueStats.done * 100 / queueStats.total) : 0;
            el.innerHTML = `
                <div class="stat-grid">
                    <div class="stat stat-done">
                        <div class="stat-value">${queueStats.done}</div>
                        <div class="stat-label">Done</div>
                    </div>
                    <div class="stat stat-progress">
                        <div class="stat-value">${queueStats.in_progress}</div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat stat-todo">
                        <div class="stat-value">${queueStats.todo}</div>
                        <div class="stat-label">Todo</div>
                    </div>
                    <div class="stat stat-percent">
                        <div class="stat-value">${percent}%</div>
                        <div class="stat-label">Complete</div>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${percent}%"></div>
                </div>
            `;
        }

        // Render domain progress
        function renderDomainProgress() {
            const el = document.getElementById('domain-progress');
            if (!domainProgress.length) {
                el.innerHTML = '<p style="color:#666">No domain data</p>';
                return;
            }
            el.innerHTML = domainProgress.map(d => {
                const percent = d.total > 0 ? Math.floor(d.done * 100 / d.total) : 0;
                return `
                    <div class="domain-bar">
                        <div class="domain-name" title="${d.domain}">${d.domain}</div>
                        <div class="domain-progress">
                            <div class="domain-fill" style="width: ${percent}%"></div>
                        </div>
                        <div class="domain-percent">${d.done}/${d.total}</div>
                    </div>
                `;
            }).join('');
        }

        // Render recent failures
        function renderRecentFailures() {
            const el = document.getElementById('recent-failures');
            if (!recentFailures.length) {
                el.innerHTML = '<p style="color:#666">No failures recorded</p>';
                return;
            }
            el.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Component</th>
                            <th>Outcome</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${recentFailures.map(f => `
                            <tr>
                                <td>${f.timestamp ? f.timestamp.split('T')[0] : '-'}</td>
                                <td>${f.component}</td>
                                <td><span class="badge badge-${f.outcome}">${f.outcome}</span></td>
                                <td>${f.duration}s</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        // Render failure hotspots
        function renderFailureHotspots() {
            const el = document.getElementById('failure-hotspots');
            if (!failureHotspots.length) {
                el.innerHTML = '<p style="color:#666">No failure data</p>';
                return;
            }
            el.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>Failures</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${failureHotspots.map(h => `
                            <tr>
                                <td>${h.component}</td>
                                <td><span class="badge badge-fail">${h.failures}</span></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        // Render daily stats
        function renderDailyStats() {
            const el = document.getElementById('daily-stats');
            if (!dailyStats.length) {
                el.innerHTML = '<p style="color:#666">No daily data</p>';
                return;
            }
            el.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Runs</th>
                            <th>Pass</th>
                            <th>Fail</th>
                            <th>Avg</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${dailyStats.map(d => `
                            <tr>
                                <td>${d.date}</td>
                                <td>${d.runs}</td>
                                <td style="color:#4ecdc4">${d.passed}</td>
                                <td style="color:#ff6b6b">${d.failed}</td>
                                <td>${d.avg_duration}s</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        // Initialize
        renderQueueStats();
        renderDomainProgress();
        renderRecentFailures();
        renderFailureHotspots();
        renderDailyStats();
    </script>
</body>
</html>
HTMLSCRIPT

echo ""
echo "Dashboard generated: $OUTPUT_PATH"
echo ""
echo "Open in browser: file://$OUTPUT_PATH"
