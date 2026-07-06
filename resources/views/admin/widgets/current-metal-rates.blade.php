<x-filament-widgets::widget>
    <x-filament::section>
        <div class="gs-metal-rates">
            <div class="gs-metal-rates__header">
                <div>
                    <h3 class="gs-metal-rates__title">Live Metal Rates</h3>
                    <p class="gs-metal-rates__subtitle">Active rates used in the app · Market feed updated {{ $this->getRates()['fetched_at'] }}</p>
                </div>
            </div>

            <div class="gs-metal-rates__grid">
                @foreach (['gold', 'silver'] as $metal)
                    @php($rate = $this->getRates()[$metal])
                    <div @class(['gs-metal-rates__card', 'gs-metal-rates__card--gold' => $metal === 'gold', 'gs-metal-rates__card--silver' => $metal === 'silver'])>
                        <div class="gs-metal-rates__card-head">
                            <span class="gs-metal-rates__badge">{{ $rate['label'] }}</span>
                            <span class="gs-metal-rates__feed">{{ $this->formatSource($rate['live_source']) }}</span>
                        </div>

                        <div class="gs-metal-rates__prices">
                            <div>
                                <p class="gs-metal-rates__label">Active Rate</p>
                                <p class="gs-metal-rates__value">
                                    @if ($rate['active'])
                                        ₹{{ number_format($rate['active'], 2) }}<span>/g</span>
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="gs-metal-rates__label">Live Market</p>
                                <p class="gs-metal-rates__value gs-metal-rates__value--live">
                                    ₹{{ number_format($rate['live'], 2) }}<span>/g</span>
                                </p>
                            </div>
                        </div>

                        <p class="gs-metal-rates__meta">
                            @if ($rate['updated_at'])
                                Last synced {{ $rate['updated_at'] }}
                                @if ($rate['active_source'])
                                    · {{ $this->formatSource($rate['active_source']) }}
                                @endif
                            @else
                                Not synced yet — use Live Rate Sync to apply market rates
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
