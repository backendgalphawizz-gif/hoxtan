@php
    $groups = $this->getGroupedReports();
    $stats = $this->getReportStats();
    $accentClasses = [
        'primary' => 'gs-reports-hub__accent-primary',
        'info' => 'gs-reports-hub__accent-info',
        'success' => 'gs-reports-hub__accent-success',
        'warning' => 'gs-reports-hub__accent-warning',
        'danger' => 'gs-reports-hub__accent-danger',
        'gray' => 'gs-reports-hub__accent-gray',
    ];
    $cardAccentClasses = [
        'primary' => 'gs-report-card--accent-primary',
        'info' => 'gs-report-card--accent-info',
        'success' => 'gs-report-card--accent-success',
        'warning' => 'gs-report-card--accent-warning',
        'danger' => 'gs-report-card--accent-danger',
        'gray' => 'gs-report-card--accent-gray',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary --}}
        <div class="gs-reports-hub__stats">
            <div class="gs-reports-hub__stat">
                <p class="gs-reports-hub__stat-label">Reports</p>
                <p class="gs-reports-hub__stat-value">{{ $stats['total'] }}</p>
            </div>
            <div class="gs-reports-hub__stat">
                <p class="gs-reports-hub__stat-label">Categories</p>
                <p class="gs-reports-hub__stat-value">{{ $stats['categories'] }}</p>
            </div>
            <div class="gs-reports-hub__stat">
                <p class="gs-reports-hub__stat-label">Export formats</p>
                <p class="gs-reports-hub__stat-value" style="font-size: 0.9375rem;">CSV & Excel</p>
            </div>
        </div>

        {{-- Search --}}
        <div class="gs-reports-hub__search">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search reports by name or description…"
                />
            </x-filament::input.wrapper>
        </div>

        {{-- Category sections with card grid --}}
        @forelse ($groups as $group)
            @php
                $accent = $group['meta']['accent'] ?? 'primary';
                $sectionIcon = $group['meta']['icon'] ?? 'heroicon-o-document-chart-bar';
                $sectionAccentClass = $accentClasses[$accent] ?? $accentClasses['primary'];
                $cardAccentClass = $cardAccentClasses[$accent] ?? $cardAccentClasses['primary'];
            @endphp

            <section class="gs-reports-hub__section {{ $sectionAccentClass }}">
                <header class="gs-reports-hub__section-header">
                    <div class="gs-reports-hub__section-icon">
                        <x-filament::icon :icon="$sectionIcon" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="gs-reports-hub__section-title">{{ $group['label'] }}</h2>
                            <span class="gs-reports-hub__section-count">{{ count($group['reports']) }}</span>
                        </div>
                        @if (filled($group['meta']['description'] ?? null))
                            <p class="gs-reports-hub__section-desc">{{ $group['meta']['description'] }}</p>
                        @endif
                    </div>
                </header>

                <div class="gs-reports-hub__grid">
                    @foreach ($group['reports'] as $item)
                        @php
                            $definition = $item['definition'];
                            $url = \App\Support\ReportRegistry::resolveUrl($definition);
                            $isLink = $item['is_link'] ?? false;
                            $reportNumber = $definition['number'] ?? null;
                        @endphp

                        @if ($url)
                            <a href="{{ $url }}" class="gs-report-card {{ $cardAccentClass }}">
                                <div class="gs-report-card__top">
                                    @if ($reportNumber)
                                        <span class="gs-report-card__number">
                                            #{{ str_pad((string) $reportNumber, 2, '0', STR_PAD_LEFT) }}
                                        </span>
                                    @else
                                        <span></span>
                                    @endif

                                    <div class="gs-report-card__badges">
                                        @if ($isLink)
                                            <span class="gs-report-card__badge gs-report-card__badge--module">
                                                <x-filament::icon icon="heroicon-m-link" class="h-3 w-3" />
                                                Module
                                            </span>
                                        @else
                                            <span class="gs-report-card__badge gs-report-card__badge--report">
                                                <x-filament::icon icon="heroicon-m-table-cells" class="h-3 w-3" />
                                                Report
                                            </span>
                                            <span class="gs-report-card__badge gs-report-card__badge--export">
                                                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="h-3 w-3" />
                                                Export
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="gs-report-card__icon-wrap">
                                    <x-filament::icon
                                        :icon="$isLink ? 'heroicon-o-arrow-top-right-on-square' : 'heroicon-o-document-chart-bar'"
                                        class="h-5 w-5"
                                    />
                                </div>

                                <h3 class="gs-report-card__title">{{ $definition['label'] }}</h3>
                                <p class="gs-report-card__desc">{{ $definition['description'] }}</p>

                                <div class="gs-report-card__footer">
                                    @if (! $isLink)
                                        <div class="gs-report-card__exports">
                                            <span class="gs-report-card__export-tag">CSV</span>
                                            <span class="gs-report-card__export-tag">Excel</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">Opens admin module</span>
                                    @endif

                                    <span class="gs-report-card__cta">
                                        {{ $isLink ? 'Open module' : 'View report' }}
                                        <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                                    </span>
                                </div>
                            </a>
                        @endif
                    @endforeach
                </div>
            </section>
        @empty
            <div class="gs-reports-hub__empty">
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
