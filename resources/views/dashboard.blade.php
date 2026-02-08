<!-- resources/views/dashboard.blade.php -->
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unified Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @include('components.dark-theme')
    <style>
        /* Dashboard-specific compact overrides on top of dark theme */
        .glass { background: rgba(17,24,39,.65); backdrop-filter: saturate(140%) blur(8px); border:1px solid var(--border); }
        .badge { background: var(--chip); color: #d1d5db; padding: 2px 8px; border-radius: 9999px; font-size: .75rem; border:1px solid var(--border); }
        .tile { border-radius: 1rem; border:1px solid var(--border); background: var(--card); padding: 14px; }
        .tile:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(0,0,0,.22); }

        /* Accessibility: Screen reader only content */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        .sr-only:focus,
        .focus\:not-sr-only:focus {
            position: static;
            width: auto;
            height: auto;
            padding: inherit;
            margin: inherit;
            overflow: visible;
            clip: auto;
            white-space: normal;
        }

        /* Mobile navigation improvements */
        @media (max-width: 639px) {
            #main-navigation {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 50;
                background: rgba(0, 0, 0, 0.8);
                backdrop-filter: blur(4px);
                padding: 1rem;
                overflow-y: auto;
            }

            #main-navigation > div {
                background: var(--card);
                border-radius: 1rem;
                padding: 1.5rem;
                border: 1px solid var(--border);
                max-width: 400px;
                margin: 2rem auto;
            }

            #main-navigation a,
            #main-navigation button {
                justify-content: center;
            }
        }

        /* Focus visible styles for better keyboard navigation */
        a:focus-visible,
        button:focus-visible,
        input:focus-visible {
            outline: 2px solid var(--accent, #3b82f6);
            outline-offset: 2px;
            border-radius: 0.375rem;
        }

        /* Skip to content link positioning */
        a[href="#main-content"] {
            z-index: 9999;
        }
    </style>
</head>
<body class="min-h-full dark-theme ui-compact">
<!-- Skip to content link for accessibility -->
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:px-4 focus:py-2 focus:rounded-lg btn-primary">
    Skip to main content
</a>

<header class="relative dash-header" role="banner">
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="dash-shell rounded-2xl p-4">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight" style="color: var(--fg)" id="page-title">Unified Dashboard</h1>
                    <p class="mt-2 muted" aria-describedby="page-title">Centralized access to AI research tools, data visualization, document management, and system monitoring.</p>
                </div>

                <!-- Mobile menu button -->
                <button
                    type="button"
                    id="mobile-menu-button"
                    class="sm:hidden self-end btn-secondary"
                    aria-expanded="false"
                    aria-controls="main-navigation"
                    aria-label="Toggle navigation menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span class="sr-only">Open menu</span>
                </button>

                <!-- Navigation -->
                <nav
                    id="main-navigation"
                    class="hidden sm:flex items-center gap-3"
                    role="navigation"
                    aria-label="Main navigation">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full sm:w-auto">
                        <a href="/profile" class="text-sm muted sm:mr-2" aria-label="View profile for {{ auth()->user()->name }}">
                            <span class="font-medium" style="color: var(--fg)">{{ auth()->user()->name }}</span>
                        </a>
                        <a href="/" class="btn-secondary w-full sm:w-auto" aria-label="Go to home page" accesskey="h">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m3 10 9-7 9 7M4 10v10h6v-6h4v6h6V10"/></svg>
                            Home
                            <span class="sr-only">(Alt+H)</span>
                        </a>
                        <a href="/dashboard" class="btn-primary w-full sm:w-auto" aria-label="Dashboard - Current page" aria-current="page" accesskey="d">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-90" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 3h8v8H3zM13 3h8v5h-8zM13 10h8v11h-8zM3 13h8v8H3z"/></svg>
                            Dashboard
                            <span class="sr-only">(Alt+D)</span>
                        </a>
                        <a href="/profile" class="btn-secondary w-full sm:w-auto" aria-label="Go to user profile" accesskey="p">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            Profile
                            <span class="sr-only">(Alt+P)</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline w-full sm:w-auto">
                            @csrf
                            <button type="submit" class="btn-secondary w-full sm:w-auto" aria-label="Logout from your account" accesskey="l">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                Logout
                                <span class="sr-only">(Alt+L)</span>
                            </button>
                        </form>
                    </div>
                </nav>
            </div>
            <div class="mt-6 flex flex-col sm:flex-row gap-4 sm:items-center" role="search">
                <div class="relative flex-1">
                    <label for="dash-search" class="sr-only">Search dashboard tiles</label>
                    <input
                        id="dash-search"
                        type="search"
                        placeholder="Search tiles (e.g. timeline, uploader, logs)..."
                        class="dt-input pl-10"
                        aria-label="Search dashboard tiles"
                        aria-describedby="search-hint"
                        accesskey="s" />
                    <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-2.5 h-5 w-5 pointer-events-none" viewBox="0 0 24 24" fill="currentColor" style="color: var(--muted)" aria-hidden="true"><path fill-rule="evenodd" d="M10 3.5a6.5 6.5 0 1 0 3.96 11.68l4.43 4.43a.75.75 0 1 0 1.06-1.06l-4.43-4.43A6.5 6.5 0 0 0 10 3.5Zm-5 6.5a5 5 0 1 1 10 0 5 5 0 0 1-10 0Z" clip-rule="evenodd"/></svg>
                    <span id="search-hint" class="sr-only">Type to filter dashboard tiles by name or keywords. Press Alt+S to focus search.</span>
                </div>
                <div class="flex gap-2" role="group" aria-label="Quick actions">
                    <a href="/uploader" class="btn-secondary hidden sm:inline-flex" aria-label="Quick upload files" accesskey="u">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 20h14v-2H5v2Zm7-16 5 5h-3v4h-4v-4H7l5-5Z"/></svg>
                        Quick upload
                    </a>
                    <a href="/timeline" class="btn-secondary hidden sm:inline-flex" aria-label="Open timeline view" accesskey="t">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm1 10.414V7h-2v6a1 1 0 0 0 .293.707l4 4 1.414-1.414L13 12.414Z"/></svg>
                        Open timeline
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<main id="main-content" class="max-w-7xl mx-auto px-4 py-8" role="main" tabindex="-1">
    <section class="mb-6" aria-label="E-Predmet widget">
        <?php try { ?>
            <livewire:epredmet-widget />
        <?php } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('EpredmetWidget render failed: ' . $e->getMessage());
            if (config('app.debug')) { ?>
                <div class="card p-4 text-sm" style="border-left:3px solid #eab308; color:#ca8a04">
                    <strong>EpredmetWidget:</strong> {{ $e->getMessage() }}
                </div>
            <?php }
        } ?>
    </section>

    <!-- AI & Research Tools Section -->
    <section class="mb-8" aria-labelledby="ai-tools-heading">
        <h2 id="ai-tools-heading" class="text-xl font-bold mb-4 flex items-center gap-2" style="color: var(--fg)">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color:#a78bfa" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12c0 5.52 4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
            AI & Research Tools
        </h2>
        <div id="ai-tools" class="grid tiles-compact sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- AI Legal Assistant (Chatbot) -->
            <a href="/chatbot" data-title="chatbot ai assistant legal research court decisions chat conversation"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(126,34,206,.15); color:#a855f7; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12c0 5.52 4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">AI Legal Assistant</h3>
                            <p class="muted text-sm">Chat with AI for legal research and case analysis.</p>
                        </div>
                    </div>
                    <span class="badge">AI</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#a855f7">
                    <span>Open Chat</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Unified Search -->
            <a href="/search" data-title="search unified vector semantic laws decisions cases documents hybrid"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(139,92,246,.15); color:#a78bfa; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10 3.5a6.5 6.5 0 1 0 3.96 11.68l4.43 4.43a.75.75 0 1 0 1.06-1.06l-4.43-4.43A6.5 6.5 0 0 0 10 3.5Zm-5 6.5a5 5 0 1 1 10 0 5 5 0 0 1-10 0Z" clip-rule="evenodd"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Unified Search</h3>
                            <p class="muted text-sm">Search laws, decisions, and cases with vector & hybrid search.</p>
                        </div>
                    </div>
                    <span class="badge">Search</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#a78bfa">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Legal Playground -->
            <a href="/playground" data-title="legal playground testing modules comprehensive tools defense"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(59,130,246,.15); color:#3b82f6; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Legal Playground</h3>
                            <p class="muted text-sm">Comprehensive testing interface for all legal defense modules.</p>
                        </div>
                    </div>
                    <span class="badge">Tools</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#3b82f6">
                    <span>Launch</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Federated Memory Search -->
            <a href="/federated-memory" data-title="federated memory search semantic pgvector agents"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(16,185,129,.15); color:#10b981; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Federated Memory</h3>
                            <p class="muted text-sm">Semantic search across all agent memories with pgvector.</p>
                        </div>
                    </div>
                    <span class="badge">AI</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#10b981">
                    <span>Search</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Topic Analyzer -->
            <a href="/topics-demo" data-title="topics demo analyzer framework testing"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(245,158,11,.15); color:#f59e0b; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Topic Analyzer</h3>
                            <p class="muted text-sm">Interactive testing interface for topic framework.</p>
                        </div>
                    </div>
                    <span class="badge">AI</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#f59e0b">
                    <span>Demo</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Decision Discovery -->
            <a href="/decisions/discover" data-title="decision discovery court rulings odluke sudovi search"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(139,92,246,.15); color:#a78bfa; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M9 2h6l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm5 2v4h4l-4-4zM8 10h8v2H8v-2zm0 4h8v2H8v-2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Decision Discovery</h3>
                            <p class="muted text-sm">Manual search and ingestion from odluke.sudovi.hr.</p>
                        </div>
                    </div>
                    <span class="badge">Search</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#a78bfa">
                    <span>Discover</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Feedback Dashboard -->
            <a href="/feedback" data-title="feedback dashboard learning opportunities human review active learning metrics"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(34,197,94,.15); color:#22c55e; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM11 5h2v4h-2V5zm0 6h2v2h-2v-2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Feedback Dashboard</h3>
                            <p class="muted text-sm">Review metrics and stats for AI learning opportunities.</p>
                        </div>
                    </div>
                    <span class="badge">AI</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#22c55e">
                    <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Learning Opportunity Manager -->
            <a href="/learning-opportunities" data-title="learning opportunities manager review low confidence ai feedback active"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(251,191,36,.15); color:#fbbf24; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Learning Opportunities</h3>
                            <p class="muted text-sm">Review and provide feedback on low-confidence AI outputs.</p>
                        </div>
                    </div>
                    <span class="badge">AI</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#fbbf24">
                    <span>Review</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Multi-Agent Collaboration -->
            <a href="/collaborations" data-title="collaboration dashboard multi agent orchestration agents status results"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(168,85,247,.15); color:#a855f7; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Agent Collaboration</h3>
                            <p class="muted text-sm">Multi-agent collaboration overview and execution tracking.</p>
                        </div>
                    </div>
                    <span class="badge">AI</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#a855f7">
                    <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>
        </div>
    </section>

    <!-- Data Analysis & Visualization Section -->
    <section class="mb-8" aria-labelledby="data-tools-heading">
        <h2 id="data-tools-heading" class="text-xl font-bold mb-4 flex items-center gap-2" style="color: var(--fg)">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color:#3b82f6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
            Data Analysis & Visualization
        </h2>
        <div id="data-tools" class="grid tiles-compact sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- Graph Viewer (Neo4j) -->
            <a href="/graph" data-title="graph neo4j knowledge graph relationships connections nodes visualization"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(126,34,206,.15); color:#a855f7; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Graph Viewer</h3>
                            <p class="muted text-sm">Explore Neo4j knowledge graph connections and relationships.</p>
                        </div>
                    </div>
                    <span class="badge">Visualization</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#a855f7">
                    <span>Explore Graph</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Citation Time Series -->
            <a href="/citation-time-series" data-title="citation time series analysis trends visualization court decisions"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(59,130,246,.15); color:#3b82f6; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h18v2H3V3zm0 4h18v2H3V7zm0 4h18v2H3v-2zm0 4h18v2H3v-2zm0 4h18v2H3v-2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Citation Analysis</h3>
                            <p class="muted text-sm">Interactive visualization of citation trends over time.</p>
                        </div>
                    </div>
                    <span class="badge">Analytics</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#3b82f6">
                    <span>View Trends</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Timeline -->
            <a href="/timeline" data-title="timeline events kp-do pp prz comparative"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(99,102,241,.15); color:#6366f1; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm1 10.414V7h-2v6a1 1 0 0 0 .293.707l4 4 1.414-1.414L13 12.414Z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Timeline</h3>
                            <p class="muted text-sm">Interactive event view with filters and jump-to-date.</p>
                        </div>
                    </div>
                    <span class="badge">Data</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#6366f1">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Comparative Timeline -->
            <a href="/comparative-timeline" data-title="timeline gup comparative"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(219,39,119,.15); color:#db2777; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l9 4-9 4-9-4 9-4Zm0 7 9 4-9 4-9-4 9-4Z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Comparative Timeline</h3>
                            <p class="muted text-sm">Side‑by‑side timeline comparison.</p>
                        </div>
                    </div>
                    <span class="badge">Data</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#db2777">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Transcript -->
            <a href="/transcript" data-title="transcript"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(126,34,206,.15); color:#a855f7; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M4 5h6v14H4zM14 5h6v10h-6z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Transcript</h3>
                            <p class="muted text-sm">Transcript preview and analysis.</p>
                        </div>
                    </div>
                    <span class="badge">Data</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#a855f7">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Graph Dashboard (Multi-panel Neo4j) -->
            <a href="/graph-dashboard" data-title="graph dashboard neo4j llm brain analytics temporal admin multi-panel"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(236,72,153,.15); color:#ec4899; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Graph Dashboard</h3>
                            <p class="muted text-sm">Multi-panel Neo4j: LLM Brain, Analytics, Temporal, Admin.</p>
                        </div>
                    </div>
                    <span class="badge">Neo4j</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#ec4899">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Parallel Timeline -->
            <a href="/parallel-timeline" data-title="parallel timeline dual lane comparison proceedings visualization"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(14,165,233,.15); color:#0ea5e9; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M3 4h7v2H3V4zm0 6h7v2H3v-2zm0 6h7v2H3v-2zm11-12h7v2h-7V4zm0 6h7v2h-7v-2zm0 6h7v2h-7v-2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Parallel Timeline</h3>
                            <p class="muted text-sm">Dual-lane timeline for comparing parallel proceedings.</p>
                        </div>
                    </div>
                    <span class="badge">Data</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#0ea5e9">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- e‑Oglasna Monitoring -->
            <a href="/eoglasna" data-title="eoglasna court monitoring keywords notices osijek"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(6,182,212,.15); color:#06b6d4; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 6h6v2h-6V8ZM5 14h14v2H5v-2Zm0-6h6v2H5V8Z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">e‑Oglasna Monitoring</h3>
                            <p class="muted text-sm">Court notices (Osijek), keywords, and activity feed.</p>
                        </div>
                    </div>
                    <span class="badge">Data</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#06b6d4">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Force Graph Explorer -->
            <a href="/graph/explore" data-title="graph explorer force directed interactive nodes edges neo4j visualization"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(236,72,153,.15); color:#ec4899; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 5.5c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM5 12c-2.8 0-5 2.2-5 5s2.2 5 5 5 5-2.2 5-5-2.2-5-5-5zm0 8.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5zm5.8-10l2.4-2.4.8.8c1.3 1.3 3 2.1 5.1 2.1V9c-1.5 0-2.7-.6-3.6-1.5l-1.9-1.9c-.5-.4-1-.6-1.6-.6s-1.1.2-1.4.6L7.8 8.4c-.4.4-.6.9-.6 1.4 0 .6.2 1.1.6 1.4L11 14v5h2v-6.2l-2.2-2.3zM19 12c-2.8 0-5 2.2-5 5s2.2 5 5 5 5-2.2 5-5-2.2-5-5-5zm0 8.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Graph Explorer</h3>
                            <p class="muted text-sm">Interactive force-directed graph with node panels.</p>
                        </div>
                    </div>
                    <span class="badge">Visualization</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#ec4899">
                    <span>Explore</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>
        </div>
    </section>

    <!-- Document Management Section -->
    <section class="mb-8" aria-labelledby="document-tools-heading">
        <h2 id="document-tools-heading" class="text-xl font-bold mb-4 flex items-center gap-2" style="color: var(--fg)">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color:#f59e0b" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 7h4l-4-4v4Z"/></svg>
            Document Management
        </h2>
        <div id="document-tools" class="grid tiles-compact sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- Chunked Uploader -->
            <a href="/uploader" data-title="uploader files chunk upload file manager"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(6,182,212,.15); color:#06b6d4; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M5 20h14v-2H5v2Zm7-16 5 5h-3v4h-4v-4H7l5-5Z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Chunked Uploader</h3>
                            <p class="muted text-sm">Upload large files in 5MB chunks and get a public URL.</p>
                        </div>
                    </div>
                    <span class="badge">Tools</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#06b6d4">
                    <span>Upload</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Ingested Laws -->
            <a href="/ingested-laws" data-title="ingested laws vector store documents"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(251,146,60,.15); color:#fb923c; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 7h4l-4-4v4Z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Ingested Laws</h3>
                            <p class="muted text-sm">Browse and manage ingested legal documents.</p>
                        </div>
                    </div>
                    <span class="badge">Documents</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#fb923c">
                    <span>Browse</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Textract Pipeline -->
            <a href="/textract" data-title="textract pipeline ocr pdf aws processing reconstruction"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(6,182,212,.15); color:#06b6d4; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6ZM6 20V4h7v5h5v11H6Zm2-8h8v2H8v-2Zm0 4h8v2H8v-2Z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Textract Pipeline</h3>
                            <p class="muted text-sm">Process PDFs with OCR and generate searchable documents.</p>
                        </div>
                    </div>
                    <span class="badge">OCR</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#06b6d4">
                    <span>Process</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Vector Store Manager -->
            <a href="/vectors/manage" data-title="vector store manager embeddings openai pinecone"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(139,92,246,.15); color:#a78bfa; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Vector Store Manager</h3>
                            <p class="muted text-sm">Browse and manage all vector stores and embeddings.</p>
                        </div>
                    </div>
                    <span class="badge">Vectors</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#a78bfa">
                    <span>Manage</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- OpenAI Vector Manager -->
            <a href="/openai/vectors" data-title="openai vector manager stores embeddings rag semantic files metadata"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(16,185,129,.15); color:#10b981; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-2 .89-2 2v11c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">OpenAI Vector Manager</h3>
                            <p class="muted text-sm">Manage OpenAI vector stores, files, and metadata.</p>
                        </div>
                    </div>
                    <span class="badge">Vectors</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#10b981">
                    <span>Manage</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Case Analysis Overview -->
            <a href="/cases" data-title="case analysis overview documents completeness retry failed"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(239,68,68,.15); color:#ef4444; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Case Analysis</h3>
                            <p class="muted text-sm">View all cases, analysis status, and retry failed analyses.</p>
                        </div>
                    </div>
                    <span class="badge">Analysis</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#ef4444">
                    <span>View Cases</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>
        </div>
    </section>

    <!-- Court & e-Communication Section -->
    <section class="mb-8" aria-labelledby="court-tools-heading">
        <h2 id="court-tools-heading" class="text-xl font-bold mb-4 flex items-center gap-2" style="color: var(--fg)">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color:#06b6d4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2L2 7v1h20V7L12 2zm0 2.24L18.47 7H5.53L12 4.24zM2 19v2h20v-2H2zm2-8v7h2v-7H4zm4 0v7h2v-7H8zm4 0v7h2v-7h-2zm4 0v7h2v-7h-2zm4 0v7h2v-7h-2z"/></svg>
            Court & e-Communication
        </h2>
        <div id="court-tools" class="grid tiles-compact sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- Legal Cases -->
            <a href="/cases" data-title="cases predmeti legal case overview analysis completeness"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(168,85,247,.15); color:#a855f7; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 12H4V8h16v10z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Predmeti (Cases)</h3>
                            <p class="muted text-sm">Browse legal cases, documents, analysis, and completeness.</p>
                        </div>
                    </div>
                    <span class="badge">Cases</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#a855f7">
                    <span>Browse</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- E-Komunikacije Dashboard -->
            <a href="/ekom" data-title="ekom e-komunikacije court electronic communication predmeti podnesci otpravci"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(6,182,212,.15); color:#06b6d4; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">E-Komunikacije</h3>
                            <p class="muted text-sm">Court e-communication: cases, submissions, and dispatches.</p>
                        </div>
                    </div>
                    <span class="badge">Court</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#06b6d4">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- E-Komunikacije Predmeti -->
            <a href="/ekom/predmeti" data-title="ekom predmeti cases subjects court e-komunikacije list"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(99,102,241,.15); color:#6366f1; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Predmeti (Cases)</h3>
                            <p class="muted text-sm">Browse and manage court cases from e-Komunikacije.</p>
                        </div>
                    </div>
                    <span class="badge">Court</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#6366f1">
                    <span>Browse</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- E-Komunikacije Podnesci -->
            <a href="/ekom/podnesci" data-title="ekom podnesci submissions filings court e-komunikacije"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(245,158,11,.15); color:#f59e0b; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41-1.41L11 15.17V9h2v6.17l1.59-1.59L16 15.01 12.01 19 8 15.01z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Podnesci (Submissions)</h3>
                            <p class="muted text-sm">View and create court submissions and filings.</p>
                        </div>
                    </div>
                    <span class="badge">Court</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#f59e0b">
                    <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- E-Komunikacije Otpravci -->
            <a href="/ekom/otpravci" data-title="ekom otpravci dispatches senders court e-komunikacije"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(251,146,60,.15); color:#fb923c; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Otpravci (Dispatches)</h3>
                            <p class="muted text-sm">Court dispatches and outgoing documents.</p>
                        </div>
                    </div>
                    <span class="badge">Court</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#fb923c">
                    <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- E-Komunikacije Sync Status -->
            <a href="/ekom/sync-status" data-title="ekom sync status synchronization court e-komunikacije"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(34,197,94,.15); color:#22c55e; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Sync Status</h3>
                            <p class="muted text-sm">E-Komunikacije synchronization status and health.</p>
                        </div>
                    </div>
                    <span class="badge">Court</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#22c55e">
                    <span>Check</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Create Podnesak -->
            <a href="/ekom/podnesci/create" data-title="ekom podnesak create new submission filing court"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(59,130,246,.15); color:#3b82f6; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">New Submission</h3>
                            <p class="muted text-sm">Create a new court submission (podnesak).</p>
                        </div>
                    </div>
                    <span class="badge">Court</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#3b82f6">
                    <span>Create</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>
        </div>
    </section>

    <!-- Legal Artillery Section -->
    <section class="mb-8" aria-labelledby="legal-artillery-heading">
        <h2 id="legal-artillery-heading" class="text-xl font-bold mb-4 flex items-center gap-2" style="color: var(--fg)">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color:#ef4444" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 2v11h3v9l7-12h-4l4-8z"/></svg>
            Legal Artillery
        </h2>
        <div id="legal-artillery-tools" class="grid tiles-compact sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- Legal Artillery Dashboard -->
            <a href="/legal-artillery" data-title="legal artillery dashboard document generation runs status scores iterations"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(239,68,68,.15); color:#ef4444; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Artillery Dashboard</h3>
                            <p class="muted text-sm">Document generation runs, status tracking, and scoring.</p>
                        </div>
                    </div>
                    <span class="badge">Generation</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#ef4444">
                    <span>View Runs</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- New Document Generation -->
            <a href="/legal-artillery/new" data-title="legal artillery new generation document create profile iterations email"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(251,146,60,.15); color:#fb923c; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">New Generation</h3>
                            <p class="muted text-sm">Start a new AI-powered legal document generation.</p>
                        </div>
                    </div>
                    <span class="badge">Create</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#fb923c">
                    <span>Generate</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>
        </div>
    </section>

    <!-- System Tools & Monitoring Section -->
    <section class="mb-8" aria-labelledby="system-tools-heading">
        <h2 id="system-tools-heading" class="text-xl font-bold mb-4 flex items-center gap-2" style="color: var(--fg)">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color:#10b981" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
            System Tools & Monitoring
        </h2>
        <div id="system-tools" class="grid tiles-compact sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- Honeypot Security -->
            <a href="/honeypot" data-title="honeypot security monitoring attacks threats intrusion detection"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(239,68,68,.15); color:#ef4444; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Honeypot Security</h3>
                            <p class="muted text-sm">Monitor unauthorized API access attempts and threats.</p>
                        </div>
                    </div>
                    <span class="badge">Security</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#ef4444">
                    <span>Monitor</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- OpenAI Logs -->
            <a href="/openai/logs" data-title="openai logs requests responses"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(16,185,129,.15); color:#10b981; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4h16v4H4zM4 10h10v4H4zM4 16h16v4H4z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">OpenAI Logs</h3>
                            <p class="muted text-sm">Inspect prompts, responses, and metadata.</p>
                        </div>
                    </div>
                    <span class="badge">Monitoring</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#10b981">
                    <span>View Logs</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Laravel Logs -->
            <a href="/logs" data-title="logs laravel application monitoring debugging errors"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(245,158,11,.15); color:#f59e0b; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Laravel Logs</h3>
                            <p class="muted text-sm">Monitor and analyze application logs.</p>
                        </div>
                    </div>
                    <span class="badge">Monitoring</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#f59e0b">
                    <span>View Logs</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- OpenAI Responses -->
            <a href="/openai/responses" data-title="openai responses api viewer requests"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(16,185,129,.15); color:#10b981; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14zM5 15h14v2H5v-2zm0-4h14v2H5v-2zm0-4h14v2H5V7z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">OpenAI Responses</h3>
                            <p class="muted text-sm">View OpenAI API responses and conversation data.</p>
                        </div>
                    </div>
                    <span class="badge">Monitoring</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#10b981">
                    <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Autonomous Agent Dashboard -->
            <a href="/agent/dashboard" data-title="agent dashboard autonomous agents runs executions"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(139,92,246,.15); color:#a78bfa; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M20 9V7c0-1.1-.9-2-2-2h-3c0-1.66-1.34-3-3-3S9 3.34 9 5H6c-1.1 0-2 .9-2 2v2c-1.66 0-3 1.34-3 3s1.34 3 3 3v4c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-4c1.66 0 3-1.34 3-3s-1.34-3-3-3zM7.5 11.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5S9.83 13 9 13s-1.5-.67-1.5-1.5zM16 17H8v-2h8v2zm-1-4c-.83 0-1.5-.67-1.5-1.5S14.17 10 15 10s1.5.67 1.5 1.5S15.83 13 15 13z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Agent Dashboard</h3>
                            <p class="muted text-sm">Autonomous agent runs and execution history.</p>
                        </div>
                    </div>
                    <span class="badge">Agents</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#a78bfa">
                    <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Agent Performance Dashboard -->
            <a href="/agent/performance" data-title="agent performance dashboard metrics success rate confidence trends"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(59,130,246,.15); color:#3b82f6; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-5h2v5zm4 0h-2V7h2v10zm4 0h-2v-3h2v3z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Agent Performance</h3>
                            <p class="muted text-sm">Agent metrics, success rates, and performance trends.</p>
                        </div>
                    </div>
                    <span class="badge">Agents</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#3b82f6">
                    <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Circuit Breaker Monitor -->
            <a href="/circuit-breaker" data-title="circuit breaker monitor service health status openai neo4j textract eoglasna"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(234,179,8,.15); color:#eab308; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M7 2v11h3v9l7-12h-4l4-8z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Circuit Breaker</h3>
                            <p class="muted text-sm">Service health monitoring with manual reset controls.</p>
                        </div>
                    </div>
                    <span class="badge">Health</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#eab308">
                    <span>Monitor</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Horizon Dashboard -->
            <a href="/horizon" data-title="horizon queue dashboard jobs workers laravel"
               class="tile group block transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl" style="background: rgba(126,34,206,.15); color:#a855f7; padding:.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--fg)">Horizon</h3>
                            <p class="muted text-sm">Laravel Horizon queue dashboard and job monitoring.</p>
                        </div>
                    </div>
                    <span class="badge">Queue</span>
                </div>
                <div class="mt-3 flex items-center gap-2" style="color:#a855f7">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>
        </div>
    </section>
    <section class="mt-8" aria-labelledby="quick-previews-heading">
        <div class="flex items-center justify-between mb-3">
            <h2 id="quick-previews-heading" class="text-xl font-bold" style="color: var(--fg)">Quick previews</h2>
            <p class="text-sm muted">Lightweight iframes; open full view from tiles above for best UX.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="card">
                <div class="flex items-center justify-between px-4 py-3" style="border-bottom:1px solid var(--border)">
                    <div class="font-semibold">Timeline</div>
                    <a href="/timeline" class="text-sm" style="color: var(--accent)" aria-label="Open timeline in full view">Open</a>
                </div>
                <iframe src="/timeline" class="w-full rounded-b-2xl" style="height:420px; background: var(--bg)" loading="lazy" title="Timeline preview"></iframe>
            </div>
            <div class="card">
                <div class="flex items-center justify-between px-4 py-3" style="border-bottom:1px solid var(--border)">
                    <div class="font-semibold">Uploader</div>
                    <a href="/uploader" class="text-sm" style="color: var(--accent)" aria-label="Open uploader in full view">Open</a>
                </div>
                <iframe src="/uploader" class="w-full rounded-b-2xl" style="height:420px; background: var(--bg)" loading="lazy" title="Uploader preview"></iframe>
            </div>
        </div>
    </section>
</main>

<footer class="max-w-7xl mx-auto px-4 pb-8 pt-6 text-sm muted" role="contentinfo">
    <div class="flex items-center justify-between">
        <span>© {{ date('Y') }} Dashboard</span>
        <a class="hover:underline" style="color: var(--accent)" href="#main-content" aria-label="Back to top of page">Back to top</a>
    </div>
</footer>

<!-- Accessibility: Live region for search results announcements -->
<div id="search-announcements" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></div>

<script>
    (function() {
        'use strict';

        // ===== Mobile Menu Functionality =====
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mainNavigation = document.getElementById('main-navigation');
        let menuOpen = false;

        function toggleMobileMenu() {
            menuOpen = !menuOpen;
            mainNavigation.classList.toggle('hidden', !menuOpen);
            mainNavigation.classList.toggle('flex', menuOpen);
            mobileMenuButton.setAttribute('aria-expanded', menuOpen.toString());

            // Update button icon
            const icon = mobileMenuButton.querySelector('svg path');
            if (menuOpen) {
                icon.setAttribute('d', 'M6 18L18 6M6 6l12 12'); // X icon
                // Focus first link in menu
                const firstLink = mainNavigation.querySelector('a, button');
                firstLink?.focus();
            } else {
                icon.setAttribute('d', 'M4 6h16M4 12h16M4 18h16'); // Hamburger icon
            }
        }

        function closeMobileMenu() {
            if (menuOpen) {
                toggleMobileMenu();
                mobileMenuButton.focus();
            }
        }

        // Toggle menu on button click
        mobileMenuButton?.addEventListener('click', toggleMobileMenu);

        // Close menu on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && menuOpen) {
                closeMobileMenu();
            }
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (menuOpen &&
                !mainNavigation.contains(e.target) &&
                !mobileMenuButton.contains(e.target)) {
                closeMobileMenu();
            }
        });

        // ===== Enhanced Search with Accessibility =====
        const searchInput = document.getElementById('dash-search');
        const searchAnnouncements = document.getElementById('search-announcements');
        const sections = ['ai-tools', 'data-tools', 'document-tools', 'court-tools', 'legal-artillery-tools', 'system-tools'];
        let searchTimeout;

        searchInput?.addEventListener('input', () => {
            const term = searchInput.value.toLowerCase().trim();
            let totalVisible = 0;

            // Search across all sections
            sections.forEach(sectionId => {
                const section = document.getElementById(sectionId);
                if (!section) return;

                const tiles = section.children;
                let visibleCount = 0;

                for (const el of tiles) {
                    const hay = (el.getAttribute('data-title') || '').toLowerCase();
                    const title = el.querySelector('h3')?.textContent?.toLowerCase() || '';
                    const isVisible = !term || hay.includes(term) || title.includes(term);
                    el.style.display = isVisible ? '' : 'none';
                    if (isVisible) {
                        visibleCount++;
                        totalVisible++;
                    }
                }

                // Hide section header if no tiles visible
                const sectionHeader = section.closest('section');
                if (sectionHeader && term) {
                    sectionHeader.style.display = visibleCount > 0 ? '' : 'none';
                } else if (sectionHeader) {
                    sectionHeader.style.display = '';
                }
            });

            // Announce results to screen readers (debounced)
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (term) {
                    const message = totalVisible === 0
                        ? 'No results found'
                        : `${totalVisible} result${totalVisible !== 1 ? 's' : ''} found`;
                    searchAnnouncements.textContent = message;
                } else {
                    searchAnnouncements.textContent = '';
                }
            }, 500);
        });

        // ===== Keyboard Shortcuts =====
        document.addEventListener('keydown', (e) => {
            // Don't trigger shortcuts when typing in input fields
            if (e.target.matches('input, textarea, select')) {
                return;
            }

            // Alt + key shortcuts
            if (e.altKey) {
                switch(e.key.toLowerCase()) {
                    case 'h':
                        e.preventDefault();
                        window.location.href = '/';
                        break;
                    case 'd':
                        e.preventDefault();
                        window.location.href = '/dashboard';
                        break;
                    case 'p':
                        e.preventDefault();
                        window.location.href = '/profile';
                        break;
                    case 's':
                        e.preventDefault();
                        searchInput?.focus();
                        break;
                    case 't':
                        e.preventDefault();
                        window.location.href = '/timeline';
                        break;
                    case 'u':
                        e.preventDefault();
                        window.location.href = '/uploader';
                        break;
                }
            }

            // Forward slash (/) to focus search (like GitHub)
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                searchInput?.focus();
            }
        });

        // ===== Focus Management for Skip Link =====
        document.querySelector('a[href="#main-content"]')?.addEventListener('click', (e) => {
            e.preventDefault();
            const mainContent = document.getElementById('main-content');
            mainContent?.focus();
            mainContent?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        // ===== Announce keyboard shortcuts on load =====
        console.log('📋 Keyboard shortcuts available:');
        console.log('  Alt+H: Home');
        console.log('  Alt+D: Dashboard');
        console.log('  Alt+P: Profile');
        console.log('  Alt+S or /: Focus search');
        console.log('  Alt+T: Timeline');
        console.log('  Alt+U: Uploader');
        console.log('  Escape: Close mobile menu');

    })();
</script>
@livewireScripts
</body>
</html>
