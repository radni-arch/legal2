<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 - Server Error | {{ config('app.name', 'AI Legal War Machine') }}</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full space-y-8">
            <div class="text-center">
                <!-- Error Icon -->
                <div class="flex justify-center">
                    <svg class="h-24 w-24 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <!-- Error Code -->
                <h1 class="mt-6 text-6xl font-bold text-gray-900">500</h1>

                <!-- Error Title -->
                <h2 class="mt-4 text-3xl font-semibold text-gray-800">
                    Server Error
                </h2>

                <!-- Error Message -->
                <p class="mt-4 text-lg text-gray-600">
                    We're sorry, but something went wrong on our end.
                </p>

                <!-- Exception Details (Development Only) -->
                @if(isset($exception) && $exception && !app()->isProduction())
                <div class="mt-6 p-4 bg-red-50 border-l-4 border-red-500 text-left rounded">
                    <h3 class="text-lg font-semibold text-red-800 mb-2">
                        Development Debug Information
                    </h3>

                    <div class="space-y-2">
                        <div>
                            <span class="font-semibold text-red-700">Exception:</span>
                            <code class="text-sm text-red-900">{{ get_class($exception) }}</code>
                        </div>

                        <div>
                            <span class="font-semibold text-red-700">Message:</span>
                            <p class="text-sm text-red-900 mt-1">{{ $exception->getMessage() }}</p>
                        </div>

                        <div>
                            <span class="font-semibold text-red-700">File:</span>
                            <code class="text-sm text-red-900">{{ $exception->getFile() }}:{{ $exception->getLine() }}</code>
                        </div>

                        <details class="mt-2">
                            <summary class="cursor-pointer font-semibold text-red-700 hover:text-red-800">
                                Stack Trace
                            </summary>
                            <pre class="mt-2 text-xs bg-red-100 p-3 rounded overflow-x-auto text-red-900">{{ $exception->getTraceAsString() }}</pre>
                        </details>
                    </div>
                </div>
                @endif

                <!-- Request ID for support reference -->
                @if(isset($requestId) && $requestId)
                <div class="mt-4 p-3 bg-gray-50 rounded text-sm text-gray-600">
                    <p><span class="font-semibold">Request ID:</span> <code>{{ $requestId }}</code></p>
                    <p class="mt-1 text-xs">Please reference this ID when contacting support.</p>
                </div>
                @endif

                <!-- Action Buttons -->
                <div class="mt-8 flex flex-col sm:flex-row justify-center gap-4">
                    <a href="javascript:history.back()"
                       class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 shadow-sm text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Go Back
                    </a>

                    <a href="/"
                       class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Go Home
                    </a>
                </div>

                <!-- Additional Support Info -->
                <div class="mt-8 text-sm text-gray-500">
                    <p>If this problem persists, please contact support or try again later.</p>
                    <p class="mt-2">
                        <a href="/" class="text-indigo-600 hover:text-indigo-800 underline">Return to homepage</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
