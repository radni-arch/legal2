<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chunked Uploader</title>
    @vite(['resources/css/app.css','resources/js/uploader.js'])
    @livewireStyles
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
        .container { max-width: 1600px; margin: 0 auto; padding: 0 2rem 2rem; }
        .dropzone { border: 2px dashed var(--border, #1f2937); border-radius: .5rem; padding: 2rem; background: var(--surface, #0f172a); color: var(--muted, #94a3b8); cursor: pointer; transition: border-color 0.2s; }
        .dropzone:hover { border-color: var(--accent, #38bdf8); }

        /* Modern scrollbar styling */
        .vm-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .vm-scroll::-webkit-scrollbar-track { background: rgb(30 41 59 / 0.5); border-radius: 3px; }
        .vm-scroll::-webkit-scrollbar-thumb { background: rgb(51 65 85); border-radius: 3px; transition: background 0.2s; }
        .vm-scroll::-webkit-scrollbar-thumb:hover { background: rgb(100 116 139); }
        .vm-scroll { scrollbar-width: thin; scrollbar-color: rgb(51 65 85) rgb(30 41 59 / 0.5); }
    </style>
</head>
<body class="dark-theme ui-compact" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
@include('components.dark-theme')

<x-page-header
    title="Chunked File Uploader"
    subtitle="Drop files below to upload in 5MB chunks. On completion, a public URL will be shown."
    route-name="uploader"
    :show-nav="true"
/>

<div class="container">
    <div id="dropzone" class="dropzone">Drop files here or click to select</div>
    <div id="upload-list" class="mt-4 space-y-4"></div>

    <hr class="my-8" style="border-color: var(--border, #1f2937);">
    <livewire:openai-vector-manager :show-header="false" />
</div>
@livewireScripts
</body>
</html>
