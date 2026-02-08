<x-layouts.app title="Profile">
    <div class="min-h-screen bg-slate-50">
        <!-- Header -->
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-slate-900">User Profile</h1>
                    <div class="flex items-center gap-3">
                        <a href="/dashboard" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Back to Dashboard
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:text-red-600 hover:border-red-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-8">
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Profile Information -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">Profile Information</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                            <p class="text-slate-900">{{ $user->name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                            <p class="text-slate-900">{{ $user->email }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Member Since</label>
                            <p class="text-slate-900">{{ $user->created_at->format('F j, Y') }}</p>
                        </div>
                    </div>
                </div>

                <!-- API Token Management -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">API Token</h2>

                    @if(session('success'))
                        <div class="mb-4 p-3 bg-green-100 border border-green-300 text-green-700 rounded-lg">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('token'))
                        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-300 rounded-lg">
                            <p class="text-sm font-medium text-yellow-900 mb-2">Your new API token (copy it now, you won't see it again):</p>
                            <div class="flex items-center gap-2">
                                <code id="api-token" class="flex-1 p-2 bg-white border border-yellow-300 rounded text-sm font-mono break-all">{{ session('token') }}</code>
                                <button onclick="copyToken()" class="px-3 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 text-sm whitespace-nowrap">
                                    Copy
                                </button>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-4">
                        @if($user->api_token)
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-sm text-blue-900">
                                    <span class="font-medium">Status:</span> Active API token exists
                                </p>
                                <p class="text-xs text-blue-700 mt-1">
                                    You have an active API token. Use it in the <code class="bg-blue-100 px-1 rounded">Authorization: Bearer YOUR_TOKEN</code> header for API requests.
                                </p>
                            </div>

                            <form method="POST" action="{{ route('profile.revoke-token') }}" onsubmit="return confirm('Are you sure you want to revoke your API token? Any applications using it will stop working.')">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                    Revoke API Token
                                </button>
                            </form>
                        @else
                            <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
                                <p class="text-sm text-slate-700">
                                    No active API token. Generate one to use the API endpoints.
                                </p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('profile.generate-token') }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                {{ $user->api_token ? 'Generate New Token' : 'Generate API Token' }}
                            </button>
                        </form>

                        <div class="mt-6 p-4 bg-slate-50 border border-slate-200 rounded-lg">
                            <h3 class="font-medium text-slate-900 mb-2">API Usage</h3>
                            <p class="text-sm text-slate-600 mb-2">Use your API token to authenticate requests:</p>
                            <pre class="text-xs bg-slate-800 text-slate-100 p-3 rounded overflow-x-auto">curl -H "Authorization: Bearer YOUR_TOKEN" \
  {{ url('/api/search') }}</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Documentation -->
            <div class="mt-6 bg-white rounded-lg shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Available API Endpoints</h2>

                <div class="space-y-4">
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h3 class="font-medium text-slate-900">Search API</h3>
                        <code class="text-sm text-slate-600">POST /api/search/*</code>
                        <p class="text-sm text-slate-600 mt-1">Search laws, decisions, and cases</p>
                    </div>

                    <div class="border-l-4 border-green-500 pl-4">
                        <h3 class="font-medium text-slate-900">OpenAI Proxy</h3>
                        <code class="text-sm text-slate-600">POST /api/openai/*</code>
                        <p class="text-sm text-slate-600 mt-1">Chat, embeddings, images, TTS, transcription, assistants, vector stores</p>
                    </div>

                    <div class="border-l-4 border-purple-500 pl-4">
                        <h3 class="font-medium text-slate-900">Content Ingestion</h3>
                        <code class="text-sm text-slate-600">POST /api/ingest/*</code>
                        <p class="text-sm text-slate-600 mt-1">Ingest text, files, and legal documents</p>
                    </div>

                    <div class="border-l-4 border-orange-500 pl-4">
                        <h3 class="font-medium text-slate-900">File Uploads</h3>
                        <code class="text-sm text-slate-600">POST /api/uploads/*</code>
                        <p class="text-sm text-slate-600 mt-1">Direct and chunked file uploads</p>
                    </div>

                    <div class="border-l-4 border-red-500 pl-4">
                        <h3 class="font-medium text-slate-900">Autonomous Agents</h3>
                        <code class="text-sm text-slate-600">POST /api/agent/*</code>
                        <p class="text-sm text-slate-600 mt-1">Start and manage research runs</p>
                    </div>

                    <div class="border-l-4 border-slate-500 pl-4">
                        <h3 class="font-medium text-slate-900">MCP Tools</h3>
                        <code class="text-sm text-slate-600">POST /api/mcp/*</code>
                        <p class="text-sm text-slate-600 mt-1">Uses separate MCP authentication (X-MCP-Token header)</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function copyToken() {
            const tokenElement = document.getElementById('api-token');
            const text = tokenElement.textContent;
            navigator.clipboard.writeText(text).then(() => {
                alert('Token copied to clipboard!');
            });
        }
    </script>
</x-layouts.app>
