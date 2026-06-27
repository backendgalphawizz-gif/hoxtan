<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section
            heading="Calculate GST"
            description="Compute CGST (1.5%) and SGST (1.5%) from completed transactions for any date."
            icon="heroicon-o-calculator"
        >
            <form wire:submit="calculateGst">
                {{ $this->form }}

                <div class="mt-6 flex justify-start">
                    <x-filament::button type="submit" icon="heroicon-o-calculator" size="lg">
                        Calculate GST
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @if ($gstResult)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    ['label' => 'Taxable Amount', 'value' => '₹'.number_format($gstResult['taxable'], 2), 'color' => 'text-gray-900 dark:text-white'],
                    ['label' => 'CGST (1.5%)', 'value' => '₹'.number_format($gstResult['cgst'], 2), 'color' => 'text-amber-600'],
                    ['label' => 'SGST (1.5%)', 'value' => '₹'.number_format($gstResult['sgst'], 2), 'color' => 'text-amber-600'],
                    ['label' => 'Total GST', 'value' => '₹'.number_format($gstResult['total_gst'], 2), 'color' => 'text-emerald-600'],
                    ['label' => 'Transactions', 'value' => $gstResult['transactions'], 'color' => 'text-gray-900 dark:text-white'],
                ] as $card)
                    <x-filament::section class="text-center">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold {{ $card['color'] }}">{{ $card['value'] }}</p>
                    </x-filament::section>
                @endforeach
            </div>
        @endif

        <x-filament::section
            heading="GST History"
            description="Previously calculated daily GST records."
            icon="heroicon-o-clock"
        >
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
