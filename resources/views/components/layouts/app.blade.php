<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'MCP Tools' }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="dark-theme ui-compact min-h-screen" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
    @include('components.dark-theme')

    {{-- Fixed notification bell (top-right) --}}
    @auth
        <div style="position: fixed; top: 12px; right: 16px; z-index: 45;">
            @livewire('notification-panel')
        </div>
    @endauth

    {{ $slot }}

    @livewireScripts

    {{-- Livewire 2 compatibility shim for browser tests --}}
    <script>
        // Make Livewire 3 accessible via Livewire 2 syntax for legacy tests
        if (typeof window.Livewire !== 'undefined' && typeof window.livewire === 'undefined') {
            // Create compatibility layer
            window.livewire = {
                find: function(componentId) {
                    const component = window.Livewire.find(componentId);
                    if (!component) return component;

                    // Wrap component to provide Livewire 2 API
                    return new Proxy(component, {
                        get(target, prop) {
                            // Intercept 'set' method to convert Livewire 2 API to Livewire 3
                            if (prop === 'set') {
                                return function(key, value) {
                                    // Livewire 2: component.set('key', value)
                                    // Livewire 3: component.$set('key', value)
                                    if (typeof target.$set === 'function') {
                                        return target.$set(key, value);
                                    }
                                    // Fallback: try setting directly on component
                                    target[key] = value;
                                    return target;
                                };
                            }
                            // Pass through all other properties/methods
                            return target[prop];
                        }
                    });
                }
            };
        }
    </script>

    @stack('scripts')
</body>
</html>

