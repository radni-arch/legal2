<header class="relative" style="background: linear-gradient(180deg, var(--surface, #0f172a), var(--bg, #0b1220)); border-bottom: 1px solid var(--border, #1f2937);">
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="rounded-2xl p-4" style="background: rgba(17,24,39,0.65); border: 1px solid var(--border, #1f2937);">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div class="min-w-0">
                    @if(count($breadcrumbs) > 0)
                        <x-breadcrumbs :items="$breadcrumbs" />
                    @endif

                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight" style="color: var(--fg, #e5e7eb);">
                        {{ $title }}
                    </h1>

                    @if($subtitle)
                        <p class="mt-2" style="color: var(--muted, #94a3b8);">
                            {{ $subtitle }}
                        </p>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    @isset($actions)
                        {{ $actions }}
                    @endisset

                    @if($showNav)
                        @auth
                            @livewire('notification-panel')
                        @endauth

                        <x-back-button route="dashboard" label="Dashboard" />
                    @endif
                </div>
            </div>
        </div>
    </div>
</header>
