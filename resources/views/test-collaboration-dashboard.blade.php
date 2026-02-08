<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Collaboration Dashboard Test</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Collaboration Dashboard Test</h1>
        <livewire:collaboration-dashboard />
    </div>
    @livewireScripts
</body>
</html>
