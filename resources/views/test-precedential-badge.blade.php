<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Precedential Badge Test</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="p-8">
    <h1 class="text-2xl font-bold mb-4">Precedential Badge Test: {{ ucfirst($type) }}</h1>

    <div class="mt-8">
        @include('livewire.graph.partials.precedential-badge', ['value' => $type])
    </div>
</body>
</html>
