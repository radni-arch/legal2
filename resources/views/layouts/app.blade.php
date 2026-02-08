<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'AI Legal War Machine') }} - @yield('title', 'Topic Framework')</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    @vite(['resources/js/app.js'])
    @livewireStyles
</head>
<body class="dark-theme ui-compact" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
    @include('components.dark-theme')
    <nav style="background: var(--surface, #0f172a); border-bottom: 1px solid var(--border, #1f2937);">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-7">
                    <div>
                        <a href="/" class="flex items-center py-4 px-2">
                            <span class="font-semibold text-lg" style="color: var(--fg, #e5e7eb);">AI Legal War Machine</span>
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="/topics-demo" class="py-2 px-2 font-medium rounded hover:text-white transition duration-300" style="color: var(--muted, #94a3b8);">
                        Topic Framework Demo
                    </a>

                    @auth
                        {{-- Notification panel now lives in <x-page-header> per-view --}}

                        {{-- Horizon Dashboard Link --}}
                        <a href="/horizon" class="py-2 px-2 font-medium rounded hover:text-white transition duration-300" style="color: var(--muted, #94a3b8);">
                            Horizon
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <main>
        {{ $slot }}
    </main>

    {{-- Toast notifications for graph updates --}}
    <x-toast-notification />

    @livewireScripts
</body>
</html>
