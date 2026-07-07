<x-filament-panels::page>
    <div class="mb-4">
        <a
            href="{{ $this->getHubUrl() }}"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 transition hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400"
        >
            <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
            Report Center
        </a>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
