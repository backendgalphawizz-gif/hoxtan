<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-3">
        <x-filament::section class="gs-stat-card">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Active Rate</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">
                @if ($currentRate['active'])
                    ₹{{ number_format((float) $currentRate['active'], 2) }}/g
                @else
                    —
                @endif
            </p>
        </x-filament::section>

        <x-filament::section class="gs-stat-card">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Live Market Rate</p>
            <p class="mt-2 text-2xl font-bold text-orange-600">
                ₹{{ number_format((float) $currentRate['live'], 2) }}/g
            </p>
        </x-filament::section>

        <x-filament::section class="gs-stat-card">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Last Updated</p>
            <p class="mt-2 text-sm font-medium text-gray-700">
                {{ $currentRate['updated_at'] ?? 'Not synced yet' }}
            </p>
            @if ($currentRate['source'])
                <p class="mt-1 text-xs capitalize text-gray-500">Active source: {{ str_replace('_', ' ', $currentRate['source']) }}</p>
            @endif
            @if (! empty($currentRate['live_source']))
                <p class="mt-1 text-xs capitalize text-gray-500">Live feed: {{ str_replace('_', ' ', $currentRate['live_source']) }}</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
