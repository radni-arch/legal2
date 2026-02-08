<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OpenAI Responses</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
        .container { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
    </style>
</head>
<body class="dark-theme ui-compact" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
@include('components.dark-theme')
@auth
<div style="position: fixed; top: 12px; right: 16px; z-index: 45;">
    @livewire('notification-panel')
</div>
@endauth
<div class="container">
    <x-breadcrumbs :items="breadcrumbs('openai.responses')" />
    <h1 class="text-2xl font-semibold mb-4" style="color: var(--fg, #e5e7eb);">OpenAI Responses</h1>
    <livewire:openai-responses-viewer />
</div>
@livewireScripts
</body>
</html>
