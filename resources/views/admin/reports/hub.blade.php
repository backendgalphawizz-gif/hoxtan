@php
    $groups = $this->getGroupedReports();
    $stats = $this->getReportStats();
    $iconBgClasses = [
        'primary' => 'bg-primary-50 text-primary-600 ring-primary-100 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-500/20',
        'info' => 'bg-info-50 text-info-600 ring-info-100 dark:bg-info-500/10 dark:text-info-400 dark:ring-info-500/20',
        'success' => 'bg-success-50 text-success-600 ring-success-100 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20',
        'warning' => 'bg-warning-50 text-warning-600 ring-warning-100 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20',
        'danger' => 'bg-danger-50 text-danger-600 ring-danger-100 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/20',
        'gray' => 'bg-gray-50 text-gray-600 ring-gray-100 dark:bg-gray-500/10 dark:text-gray-300 dark:ring-gray-500/20',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary --}}
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Reports</p>
                <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Categories</p>
                <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $stats['categories'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Download</p>
                <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">CSV & Excel</p>
            </div>
        </div>

        {{-- Search --}}
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search reports…"
                />
            </x-filament::input.wrapper>
        </div>

        {{-- Groups --}}
        @forelse ($groups as $group)
            @php
                $accent = $group['meta']['accent'] ?? 'primary';
                $sectionIcon = $group['meta']['icon'] ?? 'heroicon-o-document-chart-bar';
                $iconBg = $iconBgClasses[$accent] ?? $iconBgClasses['primary'];
            @endphp

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {{ $iconBg }}">
                            <x-filament::icon :icon="$sectionIcon" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $group['label'] }}
                                </h2>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    {{ count($group['reports']) }}
                                </span>
                            </div>
                            @if (filled($group['meta']['description'] ?? null))
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $group['meta']['description'] }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid gap-px bg-gray-100 p-px sm:grid-cols-2 lg:grid-cols-3 dark:bg-gray-800">
                    @foreach ($group['reports'] as $item)
                        @php
                            $definition = $item['definition'];
                            $url = \App\Support\ReportRegistry::resolveUrl($definition);
                            $isLink = $item['is_link'] ?? false;
                        @endphp

                        @if ($url)
                            <a
                                href="{{ $url }}"
                                class="group flex gap-3 bg-white p-4 transition hover:bg-primary-50/50 dark:bg-gray-900 dark:hover:bg-primary-500/5"
                            >
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-gray-50 text-gray-500 ring-1 ring-gray-100 transition group-hover:bg-white group-hover:text-primary-600 group-hover:ring-primary-200 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:group-hover:text-primary-400">
                                    <x-filament::icon
                                        :icon="$isLink ? 'heroicon-o-arrow-top-right-on-square' : 'heroicon-o-document-text'"
                                        class="h-4 w-4"
                                    />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                        {{ $definition['label'] }}
                                    </h3>
                                    <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                        {{ $definition['description'] }}
                                    </p>
                                    <p class="mt-2 text-xs font-medium text-primary-600 opacity-0 transition group-hover:opacity-100 dark:text-primary-400">
                                        {{ $isLink ? 'Open module' : 'Open report' }} →
                                    </p>
                                </div>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-600 dark:bg-gray-900">
                <x-filament::icon icon="heroicon-o-magnifying-glass" class="mx-auto h-10 w-10 text-gray-400" />
                <h3 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">
                    @if (filled($this->search))
                        No reports match your search
                    @else
                        No reports available
                    @endif
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if (filled($this->search))
                        Try another keyword or clear the search.
                    @else
                        Ask a super admin to grant report permissions.
                    @endif
                </p>
                @if (filled($this->search))
                    <x-filament::button wire:click="$set('search', '')" color="gray" size="sm" class="mt-4">
                        Clear search
                    </x-filament::button>
                @endif
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
